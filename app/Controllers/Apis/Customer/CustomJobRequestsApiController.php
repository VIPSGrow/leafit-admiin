<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use CodeIgniter\I18n\Time;

class CustomJobRequestsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    /** Fixed UTC timezone for all time-related operations in this controller. */
    protected string $utcTimezone = 'UTC';
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1"
    ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        } else {
            $token = verify_app_request();
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function make_custom_job_request()
    {
        try {

            $validation = \Config\Services::validation();
            $validation->setRules([
                'category_id'               => 'required',
                'service_title'             => 'required',
                'service_short_description' => 'required',
                'min_price'                 => 'required',
                'max_price'                 => 'required',
                'requested_start_date'      => 'required',
                'requested_start_time'      => 'required',
                'requested_end_date'        => 'required',
                'requested_end_time'        => 'required',
                'latitude'        => 'required',
                'longitude'        => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $firstError = reset($errors);
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $firstError,
                    'data'    => [],
                ]);
            }
            $categoryId        = $this->request->getVar('category_id');
            $serviceTitle      = $this->request->getVar('service_title');
            $serviceShortDesc  = $this->request->getVar('service_short_description');
            $minPrice          = $this->request->getVar('min_price');
            $maxPrice          = $this->request->getVar('max_price');
            $startDate         = $this->request->getVar('requested_start_date');
            $endDate           = $this->request->getVar('requested_end_date');
            $startTime         = $this->request->getVar('requested_start_time');
            $endTime           = $this->request->getVar('requested_end_time');
            $latitude          = $this->request->getVar('latitude');
            $longitude         = $this->request->getVar('longitude');
            /** @var \CodeIgniter\I18n\Time $today */
            $today = Time::today();
            /** @var \CodeIgniter\I18n\Time $startDateObj */
            $startDateObj = Time::parse($startDate)->setTime(0,0,0,0);
            /** @var \CodeIgniter\I18n\Time $endDateObj */
            $endDateObj = Time::parse($endDate)->setTime(0,0,0,0);

            if ($startDateObj->isBefore($today)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_select_an_upcoming_start_date', 'Please select an upcoming start date!'),
                ]);
            }
            if ($endDateObj->isBefore($today)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_select_an_upcoming_end_date', 'Please select an upcoming end date!'),
                ]);
            }

            $generalSettings = get_settings('general_settings', true);
            $systemTimezone  = !empty($generalSettings['system_timezone']) ? $generalSettings['system_timezone'] : date_default_timezone_get();

            $startUtc = Time::parse($startDate . ' ' . $startTime, $systemTimezone)->setTimezone($this->utcTimezone);
            $endUtc   = Time::parse($endDate . ' ' . $endTime, $systemTimezone)->setTimezone($this->utcTimezone);
            $nowUtc   = Time::now('UTC')->setSecond(0);

            if ($startDateObj->equals($today) && $startUtc->setSecond(0)->isBefore($nowUtc)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_select_an_upcoming_start_time', 'Please select an upcoming start time!'),
                ]);
            }
            if ($endDateObj->equals($today) && $endUtc->setSecond(0)->isBefore($nowUtc)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_select_an_upcoming_end_time', 'Please select an upcoming end time!'),
                ]);
            }

            // Handle file uploads
            $files_paths = [];
            $uploaded_files = $this->request->getFiles();
            if (!empty($uploaded_files)) {
                // Flatten the files array if it's nested (e.g., from multiple file inputs or array inputs)
                $flat_files = [];
                foreach ($uploaded_files as $input_name => $file_or_array) {
                    if (is_array($file_or_array)) {
                        foreach ($file_or_array as $file) {
                            if ($file->isValid()) {
                                $flat_files[] = $file;
                            }
                        }
                    } else {
                        if ($file_or_array->isValid()) {
                            $flat_files[] = $file_or_array;
                        }
                    }
                }

                if (!empty($flat_files)) {
                    $quote_settings = get_settings('request_quote_settings', true);
                    $upload_result = upload_custom_job_request_files($flat_files, $quote_settings);

                    if ($upload_result['error']) {
                        return $this->response->setJSON($upload_result);
                    }
                    $files_paths = $upload_result['file_paths'];
                }
            }

            $user_id = $this->user_details['id'];
            $data = [
                'user_id'                   => $user_id,
                'category_id'               => $categoryId,
                'service_title'             => $serviceTitle,
                'service_short_description' => $serviceShortDesc,
                'min_price'                 => $minPrice,
                'max_price'                 => $maxPrice,
                'requested_start_date'      => $startUtc->format('Y-m-d'),
                'requested_start_time'      => $startUtc->format('H:i:s'),
                'requested_end_date'        => $endUtc->format('Y-m-d'),
                'requested_end_time'        => $endUtc->format('H:i:s'),
                'status'                    => 'pending',
                'files'                     => !empty($files_paths) ? json_encode($files_paths) : null,
                'created_at'                => Time::now($this->utcTimezone)->format('Y-m-d H:i:s'),
                'updated_at'                => Time::now($this->utcTimezone)->format('Y-m-d H:i:s'),
            ];
            $insert = insert_details($data, 'custom_job_requests');
            if ($insert) {
                // Send notification to related providers (existing functionality)
                send_notification_to_related_providers($categoryId, $insert, $latitude, $longitude);

                // Send template-based notifications to admin and providers
                try {
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Starting notification process for custom_job_request_id: ' . $insert['id']);

                    // Get customer information
                    $customerData = fetch_details('users', ['id' => $user_id], ['username']);
                    $customerName = !empty($customerData) ? $customerData[0]['username'] : 'Customer';
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Customer name: ' . $customerName . ', Customer ID: ' . $user_id);

                    // Get category information
                    $categoryData = fetch_details('categories', ['id' => $categoryId], ['name']);
                    $categoryName = !empty($categoryData) ? $categoryData[0]['name'] : 'Category';
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Category name: ' . $categoryName . ', Category ID: ' . $this->request->getVar('category_id'));

                    // Get currency from settings
                    $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

                    // Prepare context data for the notification template
                    $context = [
                        'customer_name' => $customerName,
                        'customer_id' => $user_id,
                        'custom_job_request_id' => $insert['id'],
                        'service_title' => $serviceTitle,
                        'service_short_description' => $serviceShortDesc,
                        'category_name' => $categoryName,
                        'category_id' => $categoryId,
                        'min_price' => number_format($minPrice, 2),
                        'max_price' => number_format($maxPrice, 2),
                        'currency' => $currency,
                        'requested_start_date' => $startDate,
                        'requested_start_time' => $startTime,
                        'requested_end_date' => $endDate,
                        'requested_end_time' => $endTime
                    ];
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Context prepared: ' . json_encode($context));

                    // Queue notification to admin users (group_id = 1) using user_groups filter.
                    // In demo mode, limit FCM tokens per language to the last 20, while keeping
                    // full user_ids for DB/email/SMS notifications.
                    $isDemoMode = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;
                    $fcmDemoTokenLimit = $isDemoMode ? 20 : 0;

                    queue_notification_service(
                        eventType: 'new_custom_job_request',
                        recipients: [],
                        context: $context,
                        options: [
                            'user_groups' => [1], // Admin group
                            'channels' => ['fcm', 'email', 'sms'], // All channels - service will check preferences
                            'fcm_demo_token_limit' => $fcmDemoTokenLimit,
                        ]
                    );
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Notification result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // log_message('error', '[NEW_CUSTOM_JOB_REQUEST] Notification error: ' . $notificationError->getMessage());
                    log_message('error', '[NEW_CUSTOM_JOB_REQUEST] Notification error trace: ' . $notificationError->getTraceAsString());
                }
            }
            $response = $insert ?
                ['error' => false, 'message' => labels(REQUEST_SUCCESSFUL, 'Request successful!')] :
                ['error' => true, 'message' => labels(REQUEST_FAILED, 'Request failed!')];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - make_custom_job_request()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function fetch_my_custom_job_requests()
    {
        try {
            $db = \Config\Database::connect();
            $fileService = service('fileService');

            $languageCode = get_current_language_from_request();

            // Fetch single custom job request by id when id param is passed
            if (!empty($this->request->getPost('id'))) {
                $job_id = (int) $this->request->getPost('id');
                $data = $db->table('custom_job_requests cj')
                    ->select('cj.*, c.name as category_name, c.parent_id as category_parent_id, c.image as category_image')
                    ->join('categories c', 'c.id = cj.category_id', 'left')
                    ->where('cj.user_id', $this->user_details['id'])
                    ->where('cj.id', $job_id)
                    ->get()
                    ->getResultArray();
                $total = count($data);
                foreach ($data as $index => $row) {
                    $data[$index] = $this->normalizeRequestedDateTimes($data[$index]);
                    $row = $data[$index];
                    $data[$index]['translated_status'] = getTranslatedValue($row['status'], 'panel');
                    $cancelReasonData = $this->resolveCancelReason(isset($row['cancel_reason_id']) ? (int) $row['cancel_reason_id'] : null, $languageCode);
                    $data[$index]['cancel_reason_id'] = $cancelReasonData['id'];
                    $data[$index]['cancel_reason'] = $cancelReasonData['reason'];
                    $category_image = !empty($row['category_image'])
                        ? $fileService->url($row['category_image'], 'categories')
                        : '';
                    $data[$index]['total_bids'] = 0;
                    $data[$index]['bidders'] = [];
                    $data[$index]['category_image'] = $category_image;

                    $request_files = !empty($row['files']) ? json_decode($row['files'], true) : [];
                    $processed_files = [];
                    foreach ($request_files as $file) {
                        if (is_array($file) && isset($file['data'])) {
                            $processed_files[] = $file['data'];
                        } else if (is_string($file)) {
                            $processed_files[] = $fileService->url($file, 'custom_job_requests');
                        }
                    }
                    $data[$index]['files'] = $processed_files ?? [];

                    $biddersBuilder = $db->table('partner_bids pb')
                        ->select('pd.banner as provider_image')
                        ->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left')
                        ->where('pb.custom_job_request_id', $row['id'])
                        ->get()
                        ->getResultArray();
                    foreach ($biddersBuilder as $index1 => $bidRow) {
                        $biddersBuilder[$index1]['provider_image'] = !empty($bidRow['provider_image'])
                            ? $fileService->url($bidRow['provider_image'], 'banner')
                            : base_url('public/uploads/profiles/default.png');
                    }
                    $data[$index]['total_bids'] = count($biddersBuilder);
                    $data[$index]['bidders'] = $biddersBuilder;
                }
                if (!empty($data)) {
                    $data = update_category_names_in_query_results($data);
                    return response_helper(labels(MY_CUSTOM_JOBS_FETCHED_SUCCESSFULLY, 'My Custom Jobs fetched successfully'), false, $data, 200, ['total' => $total]);
                }
                return response_helper(labels(MY_CUSTOM_JOBS_NOT_FOUND, 'My Custom Jobs not found'), false);
            }

            // Default: list custom job requests with pagination
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = !empty($this->request->getPost('offset')) ? $this->request->getPost('offset') : 0;
            $sort = !empty($this->request->getPost('sort')) ? $this->request->getPost('sort') : 'id';
            $order = !empty($this->request->getPost('order')) ? $this->request->getPost('order') : 'DESC';
            $builder = $db->table('custom_job_requests cj');
            $total = $builder->select('COUNT(id) as total')->where('user_id', $this->user_details['id'])->get()->getRowArray()['total'];
            $builder->select('cj.*, c.name as category_name, c.parent_id as category_parent_id,c.image as category_image');
            $data = $builder
                ->join('categories c', 'c.id = cj.category_id', 'left')
                ->orderBy($sort, $order)
                ->limit($limit, $offset)
                ->where('cj.user_id', $this->user_details['id'])
                ->get()
                ->getResultArray();

            foreach ($data as $index => $row) {
                $data[$index] = $this->normalizeRequestedDateTimes($data[$index]);
                $row = $data[$index];
                $data[$index]['translated_status'] = getTranslatedValue($row['status'], 'panel');
                $cancelReasonData = $this->resolveCancelReason(isset($row['cancel_reason_id']) ? (int) $row['cancel_reason_id'] : null, $languageCode);
                $data[$index]['cancel_reason_id'] = $cancelReasonData['id'];
                $data[$index]['cancel_reason'] = $cancelReasonData['reason'];
                $category_image = !empty($row['category_image'])
                    ? $fileService->url($row['category_image'], 'categories')
                    : '';
                $data[$index]['total_bids'] = 0;
                $data[$index]['bidders'] = [];
                $data[$index]['category_image'] = $category_image;

                $request_files = !empty($row['files']) ? json_decode($row['files'], true) : [];
                $processed_files = [];
                foreach ($request_files as $file) {
                    if (is_array($file) && isset($file['data'])) {
                        $processed_files[] = $file['data'];
                    } else if (is_string($file)) {
                        $processed_files[] = $fileService->url($file, 'custom_job_requests');
                    }
                }
                $data[$index]['files'] = $processed_files ?? [];

                $biddersBuilder = $db->table('partner_bids pb')
                    ->select('pd.banner as provider_image')
                    ->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left')
                    ->where('pb.custom_job_request_id', $row['id'])
                    ->get()
                    ->getResultArray();
                foreach ($biddersBuilder as $index1 => $row) {
                    $biddersBuilder[$index1]['provider_image'] = !empty($row['provider_image'])
                        ? $fileService->url($row['provider_image'], 'banner')
                        : base_url('public/uploads/profiles/default.png');
                }
                $data[$index]['total_bids'] = count($biddersBuilder);
                $data[$index]['bidders'] = $biddersBuilder;
            }
            if (!empty($data)) {
                // Update category names with translations
                $data = update_category_names_in_query_results($data);

                return response_helper(labels(MY_CUSTOM_JOBS_FETCHED_SUCCESSFULLY, 'My Custom Jobs fetched successfully'), false, $data, 200, ['total' => $total]);
            } else {
                return response_helper(labels(MY_CUSTOM_JOBS_NOT_FOUND, 'My Custom Jobs not found'), false);
            }
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - fetch_my_custom_job_requests()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function fetch_custom_job_bidders()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'custom_job_request_id' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = !empty($this->request->getPost('offset')) ? $this->request->getPost('offset') : 0;
            $sort = !empty($this->request->getPost('sort')) ? $this->request->getPost('sort') : 'id';
            $order = !empty($this->request->getPost('order')) ? $this->request->getPost('order') : 'DESC';
            $db = \Config\Database::connect();
            $totalBuilder = $db->table('partner_bids pb')
                ->select('COUNT(pb.id) as total_bidders')
                ->where('pb.custom_job_request_id', $this->request->getPost('custom_job_request_id'))
                ->get()
                ->getRowArray();
            $total = $totalBuilder['total_bidders'];
            $biddersBuilder = $db->table('partner_bids pb')
                ->select('pb.*, pd.company_name as company_name,u.username as provider_name,pd.advance_booking_days,pd.visiting_charges, pd.banner as provider_image,pd.at_store,pd.at_doorstep,u.payable_commision')
                ->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left')
                ->join('users u', 'u.id = pd.partner_id')
                ->where('pb.custom_job_request_id', $this->request->getPost('custom_job_request_id'))
                ->orderBy($sort, $order)
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();
            $check_payment_gateway = get_settings('payment_gateways_settings', true);
            $fileService = service('fileService');
            foreach ($biddersBuilder as $index => $row) {
                // Add translation support for partner company names
                if (!empty($row['partner_id'])) {
                    $partnerData = [
                        'company_name' => $row['company_name'] ?? '',
                        'about' => '',
                        'long_description' => '',
                        'username' => $row['username'] ?? ''
                    ];
                    $translatedData = $this->getTranslatedPartnerData($row['partner_id'], $partnerData);
                    $biddersBuilder[$index]['company_name'] = $translatedData['company_name'];
                    $biddersBuilder[$index]['translated_company_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                    $biddersBuilder[$index]['translated_username'] = $translatedData['translated_username'] ?? $translatedData['username'];
                }
                $rating_data = $db->table('services_ratings sr')
                    ->select('
                    COUNT(sr.rating) as number_of_rating,
                    SUM(sr.rating) as total_rating,
                    (SUM(sr.rating) / COUNT(sr.rating)) as average_rating
                    ')
                    ->join('services s', 'sr.service_id = s.id', 'left')
                    ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
                    ->join('partner_bids pd', 'pd.custom_job_request_id = cj.id', 'left')
                    ->where("(s.user_id = {$row['partner_id']}) OR (pd.partner_id = {$row['partner_id']})")
                    ->get()->getResultArray();
                $biddersBuilder[$index]['rating'] = (($rating_data[0]['average_rating'] != "") ? sprintf('%0.1f', $rating_data[0]['average_rating']) : '0.0');
                $biddersBuilder[$index]['provider_image'] = !empty($row['provider_image'])
                    ? $fileService->url($row['provider_image'], 'banner')
                    : base_url('public/uploads/profiles/default.png');
                $total_orders = $db->table('orders o')->where('partner_id', $row['partner_id'])->where('status', 'completed')->select('count(o.id) as `total`')->where('o.parent_id  IS NULL')->get()->getResultArray()[0]['total'];
                $biddersBuilder[$index]['total_orders'] = $total_orders;
                $biddersBuilder[$index]['is_online_payment_allowed'] = $check_payment_gateway['payment_gateway_setting'];
                $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $row['partner_id'], 'status' => 'active']);
                if (!empty($active_partner_subscription)) {
                    if ($active_partner_subscription[0]['is_commision'] == "yes") {
                        $commission_threshold = $active_partner_subscription[0]['commission_threshold'];
                    } else {
                        $commission_threshold = 0;
                    }
                } else {
                    $commission_threshold = 0;
                }
                if ($check_payment_gateway['cod_setting'] == 1 && $check_payment_gateway['payment_gateway_setting'] == 0) {
                    $biddersBuilder[$index]['is_pay_later_allowed'] = 1;
                } else if ($check_payment_gateway['cod_setting'] == 0) {
                    $biddersBuilder[$index]['is_pay_later_allowed'] = 0;
                } else {
                    $payable_commission_of_provider = $biddersBuilder[$index]['payable_commision'];
                    if (($payable_commission_of_provider >= $commission_threshold) && $commission_threshold != 0) {
                        $biddersBuilder[$index]['is_pay_later_allowed'] = 0;
                    } else {
                        $biddersBuilder[$index]['is_pay_later_allowed'] = 1;
                    }
                }
                if ($biddersBuilder[$index]['tax_amount'] == "") {
                    $biddersBuilder[$index]['final_total'] =  $biddersBuilder[$index]['counter_price'];
                } else {
                    $biddersBuilder[$index]['final_total'] =  $biddersBuilder[$index]['counter_price'] + ($biddersBuilder[$index]['tax_amount']);
                }
            }
            $data['bidders'] = $biddersBuilder;
            $custom_job = $db->table('custom_job_requests cj')
                ->select('cj.*,c.name as category_name,c.image as category_image')
                ->join('categories c', 'c.id = cj.category_id', 'left')
                ->where('cj.id', $this->request->getPost('custom_job_request_id'))
                ->get()
                ->getResultArray();
            $fileService = service('fileService');

            $bidderLanguageCode = get_current_language_from_request();

            foreach ($custom_job as &$job) { // Use a reference to update the array directly
                $cancelReasonData = $this->resolveCancelReason(isset($job['cancel_reason_id']) ? (int) $job['cancel_reason_id'] : null, $bidderLanguageCode);
                $job['cancel_reason_id'] = $cancelReasonData['id'];
                $job['cancel_reason'] = $cancelReasonData['reason'];
                $job = $this->normalizeRequestedDateTimes($job);
                $job['category_image'] = !empty($job['category_image'])
                    ? $fileService->url($job['category_image'], 'categories')
                    : '';

                $request_files = !empty($job['files']) ? json_decode($job['files'], true) : [];
                $processed_files = [];
                foreach ($request_files as $file) {
                    if (is_array($file) && isset($file['data'])) {
                        $processed_files[] = $file['data'];
                    } else if (is_string($file)) {
                        $processed_files[] = $fileService->url($file, 'custom_job_requests');
                    }
                }
                $job['files'] = $processed_files;
            }
            unset($job); // Unset the reference to avoid unintended side effects

            // Update category names with translations
            $custom_job = update_category_names_in_query_results($custom_job);

            $data['custom_job'] = !empty($custom_job[0]) ? $custom_job[0] : [];
            if (!empty($data)) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels(BIDDERS_FETCHED_SUCCESSFULLY, 'Bidders fetched successfully'),
                    'data'    => $data,
                    'total'   => $total,
                    'status'  => 200
                ]);
            } else {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels(NO_BIDDERS_FOUND, 'No bidders found'),
                    'data'    => [],
                    'total'   => 0,
                    'status'  => 200
                ]);
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - fetch_custom_job_bidders()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public  function  cancle_custom_job_request()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'custom_job_request_id' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }
            $custom_job = fetch_details('custom_job_requests', ['id' => $this->request->getPost('custom_job_request_id'), 'user_id' => $this->user_details['id']]);
            if (empty($custom_job)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(MY_CUSTOM_JOBS_NOT_FOUND, 'My Custom Jobs not found'),
                    'data'    => [],
                ]);
            }
            if ($custom_job[0]['status'] != "pending") {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(YOU_CAN_NOT_CANCEL_SERVICE, 'You can not cancle service'),
                    'data'    => [],
                ]);
            }
            $cancel_reason_id = $this->request->getPost('cancel_reason_id');
            $additional_info  = $this->request->getPost('additional_info');
            $cancel_validation = validate_cancel_reason($cancel_reason_id, $additional_info);
            if ($cancel_validation['error']) {
                return $this->response->setJSON($cancel_validation);
            }
            $update = update_details([
                'status'                 => 'cancelled',
                'cancel_reason_id'       => (int) $cancel_reason_id,
                'cancel_additional_info' => $cancel_validation['additional_info'],
            ], ['id' => $this->request->getPost('custom_job_request_id')], 'custom_job_requests');
            if ($update) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels(CUSTOM_JOB_REQUEST_CANCELLED_SUCCESSFULLY, 'Custom Job Request cancelled successfully'),
                    'status'  => 200
                ]);
            }
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - cancle_custom_job_request()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    //Private helper methods
    /**
     * Normalize stored requested start/end datetimes for response output.
     * Stored values are already UTC; this only enforces consistent formatting.
     */
    private function normalizeRequestedDateTimes(array $row): array
    {
        foreach (['start', 'end'] as $part) {
            $dateKey = "requested_{$part}_date";
            $timeKey = "requested_{$part}_time";
            if (empty($row[$dateKey]) || empty($row[$timeKey])) {
                continue;
            }
            try {
                $utc = Time::parse($row[$dateKey] . ' ' . $row[$timeKey], $this->utcTimezone);
                $row[$dateKey] = $utc->format('Y-m-d');
                $row[$timeKey] = $utc->format('H:i:s');
            } catch (\Exception $e) {
                // Leave original values on parse failure.
            }
        }
        return $row;
    }

    /**
     * Get translated partner data based on language preference
     * 
     * @param int $partnerId Partner ID
     * @param array $partnerData Original partner data from main table
     * @return array Partner data with translations
     */
    private function getTranslatedPartnerData(int $partnerId, array $partnerData): array
    {
        // Validate partner ID to prevent errors
        if (empty($partnerId) || $partnerId <= 0) {
            log_message('error', 'Invalid partner ID provided to getTranslatedPartnerData: ' . $partnerId);
            return $partnerData; // Return original data if partner ID is invalid
        }

        return get_translated_partner_data_for_api($partnerId, $partnerData);
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
