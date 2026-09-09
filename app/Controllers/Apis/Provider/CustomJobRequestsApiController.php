<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use CodeIgniter\I18n\Time;

class CustomJobRequestsApiController extends BaseController
{
    protected $request, $validation, $db, $data;
    protected $toDateTime;
    protected $builder;
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->validation = \Config\Services::validation();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error' => true,
                'message' => $token['message'],
                'status' => 401,
            ]));
            die();
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_custom_job_requests()
    {
        try {
            // When id is not passed, job_type is required (original validation)
            if (empty($this->request->getPost('id'))) {
                $this->validation->setRules([
                    'job_type' => [
                        'label' => 'Field',
                        'rules' => 'required',
                        'errors' => [
                            'required' => 'The {field} field is required. Note: The value can be either "applied_jobs" or "open_jobs".',
                        ],
                    ],
                ]);
                if (!$this->validation->withRequest($this->request)->run()) {
                    $errors = $this->validation->getErrors();
                    $response = [
                        'error' => true,
                        'message' => $errors,
                        'data' => [],
                    ];
                    return $this->response->setJSON($response);
                }
            }

            $partner_id = $this->user_details['id'];
            $limit = !empty($this->request->getPost('limit')) ?  $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'DESC';
            $custom_job_categories = fetch_details('partner_details', ['partner_id' => $partner_id], ['custom_job_categories', 'is_accepting_custom_jobs']);
            $partner_categoried_preference = !empty($custom_job_categories) &&
                isset($custom_job_categories[0]['custom_job_categories']) &&
                !empty($custom_job_categories[0]['custom_job_categories']) ?
                json_decode($custom_job_categories[0]['custom_job_categories']) : [];
            $db = \Config\Database::connect();
            $fileService = service('fileService');

            $total_count = 0;
            $total_filteredJobs = 0;
            $jobs = [];

            // Branch: fetch single custom job request by id (job_type ignored when id is passed)
            if (!empty($this->request->getPost('id'))) {
                $job_id = (int) $this->request->getPost('id');
                // Try applied_jobs style first (partner has a bid on this job)
                $jobsWithBid = $db->table('partner_bids pb')
                    ->select('pb.*, cj.user_id,cj.category_id,cj.service_title,cj.service_short_description,cj.min_price,cj.max_price,cj.requested_start_date,cj.requested_start_time,cj.requested_end_date,cj.requested_end_time,cj.status,cj.cancel_reason_id,cj.cancel_additional_info,cj.files, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                    ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id')
                    ->join('users u', 'u.id = cj.user_id')
                    ->join('categories c', 'c.id = cj.category_id')
                    ->where('pb.partner_id', $partner_id)
                    ->where('cj.id', $job_id)
                    ->get()
                    ->getResultArray();
                if (!empty($jobsWithBid)) {
                    $jobs = $jobsWithBid;
                    $idBranchLanguageCode = get_current_language_from_request();
                    foreach ($jobs as &$job) {
                        $cancelReasonData = $this->resolveCancelReason(isset($job['cancel_reason_id']) ? (int) $job['cancel_reason_id'] : null, $idBranchLanguageCode);
                        $job['cancel_reason_id'] = $cancelReasonData['id'];
                        $job['cancel_reason'] = $cancelReasonData['reason'];
                        $job['bid_applied'] = '1'; // Sending bid details
                        if ($job['tax_amount'] == "") {
                            $job['final_total'] = $job['counter_price'];
                        } else {
                            $job['final_total'] = $job['counter_price'] + ($job['tax_amount']);
                        }
                        $job['image'] = !empty($job['image'])
                            ? $fileService->url(basename($job['image']), 'profile')
                            : base_url('public/uploads/profiles/default.png');
                        $job['category_image'] = !empty($job['category_image'])
                            ? $fileService->url($job['category_image'], 'categories')
                            : '';
                        if (!empty($job['category_id'])) {
                            $categoryData = ['name' => $job['category_name'] ?? ''];
                            $translatedCategoryData = get_translated_category_data_for_api($job['category_id'], $categoryData);
                            $job['translated_category_name'] = $translatedCategoryData['translated_name'] ?? $job['category_name'];
                        } else {
                            $job['translated_category_name'] = $job['category_name'] ?? '';
                        }
                    }
                    $total_count = 1;
                } else {
                    // No bid: fetch custom_job_request by id (same shape as open_jobs)
                    $jobsOpen = $db->table('custom_job_requests cj')
                        ->select('cj.*, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                        ->join('users u', 'u.id = cj.user_id')
                        ->join('categories c', 'c.id = cj.category_id')
                        ->where('cj.id', $job_id)
                        ->get()
                        ->getResultArray();
                    $jobs = $jobsOpen;
                    if (!empty($jobs)) {
                        $idBranchOpenLanguageCode = get_current_language_from_request();
                        foreach ($jobs as &$job) {
                            $cancelReasonData = $this->resolveCancelReason(isset($job['cancel_reason_id']) ? (int) $job['cancel_reason_id'] : null, $idBranchOpenLanguageCode);
                            $job['cancel_reason_id'] = $cancelReasonData['id'];
                            $job['cancel_reason'] = $cancelReasonData['reason'];
                            $job['bid_applied'] = '0'; // No bid details in this response
                            $job['image'] = !empty($job['image'])
                                ? $fileService->url(basename($job['image']), 'profile')
                                : base_url('public/uploads/profiles/default.png');
                            $job['category_image'] = !empty($job['category_image'])
                                ? $fileService->url($job['category_image'], 'categories')
                                : '';
                            if (!empty($job['category_id'])) {
                                $categoryData = ['name' => $job['category_name'] ?? ''];
                                $translatedCategoryData = get_translated_category_data_for_api($job['category_id'], $categoryData);
                                $job['translated_category_name'] = $translatedCategoryData['translated_name'] ?? $job['category_name'];
                            } else {
                                $job['translated_category_name'] = $job['category_name'] ?? '';
                            }
                        }
                        $total_count = 1;
                    } else {
                        $total_count = 0;
                    }
                }
            } else if ($this->request->getPost('job_type') == "applied_jobs") {
                $total_count = $db->table('partner_bids pb')
                    ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id')
                    ->join('users u', 'u.id = cj.user_id')
                    ->join('categories c', 'c.id = cj.category_id')
                    ->where('pb.partner_id', $partner_id)
                    ->countAllResults(false);
                $jobs = $db->table('partner_bids pb')
                    ->select('pb.*, cj.user_id,cj.category_id,cj.service_title,cj.service_short_description,cj.min_price,cj.max_price,cj.requested_start_date,cj.requested_start_time,cj.requested_end_date,cj.requested_end_time,cj.status,cj.cancel_reason_id,cj.cancel_additional_info,cj.files, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                    ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id')
                    ->join('users u', 'u.id = cj.user_id')
                    ->join('categories c', 'c.id = cj.category_id')
                    ->where('pb.partner_id', $partner_id)
                    ->orderBy('pb.id', 'DESC')
                    ->limit($limit, $offset)
                    ->get()
                    ->getResultArray();
                $languageCode = get_current_language_from_request();
                foreach ($jobs as &$job) {
                    $cancelReasonData = $this->resolveCancelReason(isset($job['cancel_reason_id']) ? (int) $job['cancel_reason_id'] : null, $languageCode);
                    $job['cancel_reason_id'] = $cancelReasonData['id'];
                    $job['cancel_reason'] = $cancelReasonData['reason'];

                    if ($job['tax_amount'] == "") {
                        $job['final_total'] =  $job['counter_price'];
                    } else {
                        $job['final_total'] =  $job['counter_price'] + ($job['tax_amount']);
                    }

                    $job['image'] = !empty($job['image'])
                        ? $fileService->url(basename($job['image']), 'profile')
                        : '';
                    $job['category_image'] = !empty($job['category_image'])
                        ? $fileService->url($job['category_image'], 'categories')
                        : '';

                    // Add translated category name using helper function
                    if (!empty($job['category_id'])) {
                        $categoryData = ['name' => $job['category_name'] ?? ''];
                        $translatedCategoryData = get_translated_category_data_for_api($job['category_id'], $categoryData);
                        $job['translated_category_name'] = $translatedCategoryData['translated_name'] ?? $job['category_name'];
                    } else {
                        $job['translated_category_name'] = $job['category_name'] ?? '';
                    }
                }
            } else if ($this->request->getPost('job_type') == "open_jobs") {

                $totalJobsQuery = $db->table('custom_job_requests cj')
                    ->select('cj.id')
                    ->join('users u', 'u.id = cj.user_id')
                    ->join('categories c', 'c.id = cj.category_id')
                    ->where('cj.status', 'pending')
                    ->where("(SELECT COUNT(1) FROM partner_bids pb WHERE pb.custom_job_request_id = cj.id AND pb.partner_id = $partner_id) = 0");
                if (!empty($partner_categoried_preference)) {
                    $totalJobsQuery->whereIn('cj.category_id', $partner_categoried_preference);
                }
                $totalJobsQueryResult = $totalJobsQuery->get()->getResultArray();
                $total_filteredJobs = [];
                foreach ($totalJobsQueryResult as $row) {
                    $did_partner_bid = fetch_details('partner_bids', [
                        'custom_job_request_id' => $row['id'],
                        'partner_id' => $partner_id,
                    ]);
                    if (empty($did_partner_bid)) {
                        $check = fetch_details('custom_job_provider', ['partner_id' => $partner_id, 'custom_job_request_id' => $row['id']]);
                        if (!empty($check)) {
                            $total_filteredJobs[] = $row;
                        }
                    }
                }
                // Get the total count
                // Now get the paginated results with limit and offset
                $jobsQuery = $db->table('custom_job_requests cj')
                    ->select('cj.*, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                    ->join('users u', 'u.id = cj.user_id')
                    ->join('categories c', 'c.id = cj.category_id')
                    ->where('cj.status', 'pending')
                    ->where("(SELECT COUNT(1) FROM partner_bids pb WHERE pb.custom_job_request_id = cj.id AND pb.partner_id = $partner_id) = 0");
                if (!empty($partner_categoried_preference)) {
                    $jobsQuery->whereIn('cj.category_id', $partner_categoried_preference);
                }
                // Apply limit and offset for pagination
                $jobsQuery->orderBy('cj.id', 'DESC')->limit($limit, $offset);
                $jobs = $jobsQuery->get()->getResultArray();
                // Filter out jobs with existing custom job provider records
                $filteredJobs = [];
                foreach ($jobs as $row) {
                    $check = fetch_details('custom_job_provider', ['partner_id' => $partner_id, 'custom_job_request_id' => $row['id']]);

                    if (!empty($check)) {
                        $filteredJobs[] = $row;
                    }
                }
                if (!empty($partner_categoried_preference)) {
                    $jobs =  $filteredJobs;
                } else {
                    $jobs = [];
                    $total_count = 0;
                }
                if (!empty($jobs)) {
                    foreach ($jobs as &$job) {
                        $job['image'] = !empty($job['image'])
                            ? $fileService->url(basename($job['image']), 'profile')
                            : base_url('public/uploads/profiles/default.png');
                        $job['category_image'] = !empty($job['category_image'])
                            ? $fileService->url($job['category_image'], 'categories')
                            : '';

                        // Add translated category name using helper function
                        if (!empty($job['category_id'])) {
                            $categoryData = ['name' => $job['category_name'] ?? ''];
                            $translatedCategoryData = get_translated_category_data_for_api($job['category_id'], $categoryData);
                            $job['translated_category_name'] = $translatedCategoryData['translated_name'] ?? $job['category_name'];
                        } else {
                            $job['translated_category_name'] = $job['category_name'] ?? '';
                        }
                    }
                }
            }

            // Add translation support for service data in custom job requests
            if (!empty($jobs)) {
                foreach ($jobs as &$job) {
                    // Stored values are already UTC; only normalize formatting.
                    try {
                        if (!empty($job['requested_start_date']) && !empty($job['requested_start_time'])) {
                            $startUtc = Time::parse($job['requested_start_date'] . ' ' . $job['requested_start_time'], 'UTC');
                            $job['requested_start_date'] = $startUtc->format('Y-m-d');
                            $job['requested_start_time'] = $startUtc->format('H:i:s');
                        }
                        if (!empty($job['requested_end_date']) && !empty($job['requested_end_time'])) {
                            $endUtc = Time::parse($job['requested_end_date'] . ' ' . $job['requested_end_time'], 'UTC');
                            $job['requested_end_date'] = $endUtc->format('Y-m-d');
                            $job['requested_end_time'] = $endUtc->format('H:i:s');
                        }
                    } catch (\Exception $e) {
                        // Leave original values on parse failure.
                    }
                    // For custom job requests, the service data is in service_title and service_short_description
                    // These are stored in the custom_job_requests table, not the services table
                    // So we need to handle them differently

                    // Get default language
                    $defaultLanguage = 'en';

                    // Get requested language from headers
                    $contentLanguage = get_current_language_from_request();
                    $requestedLanguage = $defaultLanguage; // Default fallback

                    if ($contentLanguage) {
                        $requestedLanguage = strtolower($contentLanguage);
                    }

                    // For custom job requests, we'll add translated fields based on the current language
                    // Since custom job requests don't have a service_id, we'll use the job data directly
                    if ($requestedLanguage !== $defaultLanguage) {
                        // Add translated fields for custom job requests
                        $job['translated_service_title'] = $job['service_title'] ?? '';
                        $job['translated_service_short_description'] = $job['service_short_description'] ?? '';
                    } else {
                        // For default language, keep the original fields
                        $job['translated_service_title'] = $job['service_title'] ?? '';
                        $job['translated_service_short_description'] = $job['service_short_description'] ?? '';
                    }

                    // Process attachments from the JSON files column into a flat list of URLs.
                    $request_files = !empty($job['files']) ? json_decode($job['files'], true) : [];
                    $processed_files = [];
                    if (\is_array($request_files)) {
                        foreach ($request_files as $file) {
                            if (\is_array($file) && isset($file['data'])) {
                                $processed_files[] = $file['data'];
                            } else if (\is_string($file)) {
                                $processed_files[] = $fileService->url($file, 'custom_job_requests');
                            }
                        }
                    }
                    $job['files'] = $processed_files ?? [];
                }
            }

            // When id was passed, total comes from $total_count; otherwise use open_jobs/applied_jobs logic
            $responseTotal = !empty($this->request->getPost('id'))
                ? $total_count
                : (($this->request->getPost('job_type') == "open_jobs") ? count($total_filteredJobs) : $total_count);
            $response = [
                'error' => false,
                'message' => labels(CUSTOM_JOB_FETCHED_SUCCESSFULLY, 'Custom job fetched successfully'),
                'data' => $jobs,
                'total' => $responseTotal,
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {

            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_custom_job_requests()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function apply_for_custom_job()
    {
        try {
            $this->validation->setRules([
                'custom_job_request_id' => 'required',
                'counter_price' => 'required',
                'cover_note' => 'required',
                'duration' => 'required',
            ]);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }

            // ------------------------------------------------------------------
            // Prevent duplicate bids from the same provider on the same job.
            // This is mainly to avoid race conditions / double-taps where
            // two identical "apply" requests are processed at almost
            // the same time and both would otherwise be inserted.
            // We do a quick existence check before inserting.
            // NOTE: For full protection at DB level, also add a UNIQUE KEY on
            // (partner_id, custom_job_request_id) in `partner_bids`.
            // ------------------------------------------------------------------
            $existingBid = fetch_details(
                'partner_bids',
                [
                    'partner_id'            => $this->user_details['id'],
                    'custom_job_request_id' => $_POST['custom_job_request_id'],
                ]
            );

            if (!empty($existingBid)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('you_already_applied_for_this_job', 'You have already applied for this job.'),
                    'data'    => [],
                ]);
            }

            // Subscription check: provider must have active subscription to bid.
            $subscriptionStatus = fetch_details(
                table: 'partner_subscriptions',
                where: ['partner_id' => $this->user_details['id']],
                fields: ['status'],
                limit: 1,
                sort: 'id',
                order: 'DESC'
            );
            if (empty($subscriptionStatus) || $subscriptionStatus[0]['status'] !== 'active') {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('you_do_not_have_an_active_subscription', 'You do not have an active subscription'),
                    'data'    => [],
                ]);
            }

            // Fetch custom job request details early to validate status and use for notifications later.
            $fetch_custom_job_Data = fetch_details('custom_job_requests', ['id' => $_POST['custom_job_request_id']]);
            if (empty($fetch_custom_job_Data)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('job_request_not_found', 'Job request not found.'),
                    'data'    => [],
                ]);
            }
            $custom_job_request = $fetch_custom_job_Data[0];

            // Block bidding if the job has already started.
            if (!empty($custom_job_request['status']) && $custom_job_request['status'] === "booked") {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('bidding_not_allowed_job_started', 'Bidding is not allowed as the job has already started.')
                ]);
            }

            $data['partner_id'] = $this->user_details['id'];
            $data['counter_price'] = $_POST['counter_price'];
            $data['note'] = $_POST['cover_note'];
            $data['duration'] = $_POST['duration'];
            $data['custom_job_request_id'] = $_POST['custom_job_request_id'];
            $data['status'] = 'pending';
            if (isset($_POST['tax_id']) && $_POST['tax_id'] != "") {
                $data['tax_id'] = $_POST['tax_id'] ?? "";
                $tax_details = fetch_details('taxes', ['id' => $_POST['tax_id']]);
                $data['tax_id'] = $tax_details[0]['id'];
                $data['tax_percentage'] = $tax_details[0]['percentage'];
                $data['tax_amount'] = ($_POST['counter_price'] * $tax_details[0]['percentage']) / 100;
            } else {
                $data['tax_id'] = "";
                $data['tax_percentage'] = "";
                $data['tax_amount'] = 0;
            }
            $insert = insert_details($data, 'partner_bids');
            if (isset($insert['error']) && !$insert['error']) {
                $startTime = microtime(true);
                $customer_id = $custom_job_request['user_id'];

                // Send notification to customer using NotificationService
                // This unified approach handles FCM, Email, and SMS notifications using templates
                try {
                    // $step1Start = microtime(true);
                    // Get provider name with translation support
                    $provider_id = $this->user_details['id'];
                    $providerName = get_translated_partner_field($provider_id, 'company_name');
                    if (empty($providerName)) {
                        $partner_data = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
                        $providerName = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
                    }

                    // Get customer details
                    $customer_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);
                    $customer_name = !empty($customer_details) && !empty($customer_details[0]['username']) ? $customer_details[0]['username'] : 'Customer';

                    // Get category name
                    $category_id = $custom_job_request['category_id'] ?? null;
                    $category_name = '';
                    if (!empty($category_id)) {
                        $category_data = fetch_details('categories', ['id' => $category_id], ['name']);
                        $category_name = !empty($category_data) && !empty($category_data[0]['name']) ? $category_data[0]['name'] : '';
                    }

                    // Get currency from settings
                    $currency = get_settings('general_settings', true)['currency'] ?? 'USD';
                    // $step1Time = microtime(true) - $step1Start;

                    // Prepare context data for notification templates
                    // This context will be used to populate template variables like [[provider_name]], [[counter_price]], etc.
                    $notificationContext = [
                        'custom_job_request_id' => (string)$_POST['custom_job_request_id'],
                        'service_title' => $custom_job_request['service_title'] ?? '',
                        'service_short_description' => $custom_job_request['service_short_description'] ?? '',
                        'provider_id' => (string)$provider_id,
                        'provider_name' => $providerName,
                        'bid_id' => (string)$insert['id'],
                        'bid_status' => $data['status'],
                        'bidder_id' => (string)$provider_id,
                        'counter_price' => number_format($data['counter_price'], 2),
                        'currency' => $currency,
                        'duration' => (string)$data['duration'],
                        'cover_note' => $data['note'] ?? '',
                        'customer_id' => (string)$customer_id,
                        'customer_name' => $customer_name,
                        'category_name' => $category_name
                    ];

                    // $queueStart = microtime(true);
                    // Queue all notifications (FCM, Email, SMS) to customer using NotificationService
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    queue_notification_service(
                        eventType: 'bid_on_custom_job_request',
                        recipients: ['user_id' => $customer_id],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels
                            'language' => get_current_language_from_request(),
                            'platforms' => ['android', 'ios', 'web'], // Customer platforms
                            'type' => 'bid', // Notification type for app routing
                            'data' => [
                                'custom_job_request_id' => (string)$_POST['custom_job_request_id'],
                                'bid_id' => (string)$insert['id'],
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                'redirect_to' => 'job_details_screen' // Redirect to job details screen
                            ]
                        ]
                    );
                    // $queueTime = microtime(true) - $queueStart;
                    // $totalTime = microtime(true) - $startTime;

                    // log_message('info', sprintf(
                    //     '[PERF] apply_for_custom_job post-insert: Total=%.4fs, FetchData=%.4fs, QueueService=%.4fs',
                    //     $totalTime,
                    //     $step1Time,
                    //     $queueTime
                    // ));

                    // log_message('info', '[BID_ON_CUSTOM_JOB_REQUEST] Notification queued for customer: ' . $customer_id . ', Custom Job Request: ' . $_POST['custom_job_request_id'] . ', Result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the bid creation
                    log_message('error', '[BID_ON_CUSTOM_JOB_REQUEST] Notification error trace: ' . $notificationError->getTraceAsString());
                }

                $response = [
                    'error' => false,
                    'message' => labels(YOUR_BID_HAS_BEEN_PLACED_SUCCESSFULLY, 'Your bid has been placed successfully'),
                    'data' => $data
                ];
                return $this->response->setJSON($response);
            } else {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $insert['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                    'data'    => [],
                ]);
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - apply_for_custom_job()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function manage_category_preference()
    {
        try {
            if (empty($_POST['category_id'])) {
                return ErrorResponse(labels(SELECT_AT_LEAST_ONE_CATEGORY, "Select at least one category"), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $selected_categories = $_POST['category_id'];
            update_details(
                ['custom_job_categories' => json_encode($selected_categories)],
                ['partner_id' => $this->user_details['id']],
                'partner_details',
                false
            );
            $response = [
                'error' => false,
                'message' => labels(CATEGORY_PREFERENCE_SET_SUCCESSFULLY, 'Category Preference set successfully'),
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - manage_category_preference()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function manage_custom_job_request_setting()
    {
        try {
            $this->validation->setRules([
                'custom_job_value' => 'required',
            ]);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $update =  update_details(['is_accepting_custom_jobs' => $_POST['custom_job_value']], ['partner_id' => $this->user_details['id']], 'partner_details');
            if ($update) {
                $response = [
                    'error' => false,
                    'message' => labels(YOUR_SETTING_HAS_BEEN_SUCCESSFULLY, 'Your setting has been successfully'),
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                ];
            }
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - manage_category_preference()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Resolve cancel reason with translation fallback.
     * Priority: requested language → default language → base reasons table value.
     */
    private function resolveCancelReason(?int $cancelReasonId, string $languageCode): array
    {
        $result = ['id' => null, 'reason' => null];

        if (empty($cancelReasonId)) {
            return $result;
        }

        $db = \Config\Database::connect();

        $base = $db->table('reasons')
            ->select('id, reason')
            ->where('id', (int) $cancelReasonId)
            ->get()
            ->getRowArray();

        if (empty($base)) {
            return ['id' => (int) $cancelReasonId, 'reason' => null];
        }

        $defaultLang = get_default_language();
        $resolved = $base['reason'];

        if ($db->tableExists('translated_reasons')) {
            $translations = $db->table('translated_reasons tr')
                ->select('tr.reason, l.code as language_code')
                ->join('languages l', 'l.id = tr.language_id')
                ->where('tr.reason_id', (int) $cancelReasonId)
                ->whereIn('l.code', array_values(array_unique([$languageCode, $defaultLang])))
                ->get()
                ->getResultArray();

            $byLang = [];
            foreach ($translations as $t) {
                $byLang[$t['language_code']] = $t['reason'];
            }

            if (!empty($byLang[$languageCode])) {
                $resolved = $byLang[$languageCode];
            } elseif (!empty($byLang[$defaultLang])) {
                $resolved = $byLang[$defaultLang];
            }
        }

        return [
            'id'     => (int) $cancelReasonId,
            'reason' => $resolved,
        ];
    }
}
