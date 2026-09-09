<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Services\NotificationService;
use App\Libraries\JWT;

class ReportAndBlockApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/get_report_reasons",
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

    public function block_user()
    {
        try {

            $validation = \Config\Services::validation();
            $validation->setRules([
                'partner_id' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }

            $user_id = $this->user_details['id'];
            $partner_id = $this->request->getPost('partner_id');
            $reason_id = $this->request->getPost('reason_id');
            $additional_info = "";
            $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id]);

            if (empty($partner_details)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(PARTNER_NOT_FOUND, 'Partner not found'),
                ]);
            }

            if (isset($reason_id) && !empty($reason_id)) {
                $reasons = fetch_details('reasons_for_report_and_block_chat', ['id' => $reason_id], ['id', 'needs_additional_info', 'type']);
                if (empty($reasons)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(INVALID_REASON_SELECTED, 'Invalid reason selected.'),
                    ]);
                }

                if ($reasons[0]['needs_additional_info'] == "1") {

                    $validation->setRules([
                        'additional_info' => 'required',
                    ]);
                    if (!$validation->withRequest($this->request)->run()) {
                        return $this->response->setJSON([
                            'error'   => true,
                            'message' => $validation->getErrors(),
                            'data'    => [],
                        ]);
                    }

                    $additional_info = $this->request->getPost('additional_info');
                }
            }

            $user_report = fetch_details('user_reports', ['reporter_id' => $user_id, 'reported_user_id' => $partner_id], ['id']);

            if (!empty($user_report)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(YOU_HAVE_ALREADY_REPORTED_THIS_USER, 'You have already reported this user.'),
                ]);
            }

            $data = [
                'reporter_id' => $user_id,
                'reported_user_id' => $partner_id,
                'reason_id' => $reason_id ?? 0,
                'additional_info' => $additional_info
            ];

            $user_report_id = insert_details($data, 'user_reports', 'id');
            $user_report_id = $user_report_id['id'];

            // Send notifications for user blocking
            // Using NotificationService for all channels (FCM, Email, SMS)
            try {
                $language = get_current_language_from_request();

                // Get blocker (customer) and blocked user (provider) details
                $blocker_data = fetch_details('users', ['id' => $user_id], ['username']);

                // Get provider name if blocked user is a provider
                $blocked_user_name = 'User';
                $blocked_user_data = fetch_details('users', ['id' => $partner_id], ['username']);
                $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                if (!empty($partner_details)) {
                    $defaultLanguage = get_default_language();
                    $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                    $translatedPartnerDetails = $translationModel->getTranslatedDetails($partner_id, $defaultLanguage);
                    if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
                        $blocked_user_name = $translatedPartnerDetails['company_name'];
                    } else {
                        $blocked_user_name = $partner_details[0]['company_name'] ?? $blocked_user_name;
                    }
                } else {
                    $blocked_user_name = $blocked_user_data[0]['username'] ?? 'User';
                }

                // Get order_id if available from request (when reported from order booking)
                $order_id = $this->request->getPost('order_id');

                // Prepare context data for notification templates.
                // Templates contain the message content, we just provide the variables.
                $notificationContext = [
                    'blocker_name' => $blocker_data[0]['username'] ?? 'Customer',
                    'blocker_type' => 'customer',
                    'blocker_id' => $user_id,
                    'blocked_user_name' => $blocked_user_name,
                    'blocked_user_type' => 'provider',
                    'blocked_user_id' => $partner_id,
                    'user_id' => $user_id, // Customer user ID
                    'provider_id' => $partner_id, // Provider ID (legacy snake_case key)
                    'order_id' => !empty($order_id) ? $order_id : null, // Order ID if reported from order booking
                    'include_logo' => true, // Include logo in email templates
                ];

                // Attach booking / provider metadata for block-user notifications so that
                // all FCM payloads have a consistent structure with chat notifications.
                // This adds: bookingId, bookingStatus, companyName, translatedName,
                // receiverType, providerId, profile, senderId.
                $chatMetaForBlock = build_chat_message_details(
                    (int) $partner_id,
                    !empty($order_id) ? (int) $order_id : null,
                    1, // receiverType = 1 (provider) in customer->provider block flow
                    (int) $user_id
                );
                if (!empty($chatMetaForBlock) && is_array($chatMetaForBlock)) {
                    $notificationContext = array_merge($notificationContext, $chatMetaForBlock);
                }

                // Build chat_user payload with the blocker's (customer's) data for notification consumers.
                // Same structure as new_message chat_user so panels/apps can show who blocked (customer).
                $customerForPayload = fetch_details('users', ['id' => $user_id], ['id', 'username', 'image']);
                $bookingStatus = '';
                if (!empty($order_id)) {
                    $orderRow = fetch_details('orders', ['id' => (int) $order_id], ['status']);
                    $bookingStatus = $orderRow[0]['status'] ?? '';
                }
                $customerObject = [
                    'customer_id'             => (string) $user_id,
                    'customer_name'           => $customerForPayload[0]['username'] ?? ($blocker_data[0]['username'] ?? 'Customer'),
                    'image'                    => $customerForPayload[0]['image'] ?? '',
                    'last_chat_date'           => null,
                    'booking_id'               => !empty($order_id) ? (string) $order_id : null,
                    'booking_status'           => $bookingStatus,
                    'translated_booking_status' => !empty($bookingStatus) ? getTranslatedValue($bookingStatus, 'panel') : '',
                    'order_id'                 => !empty($order_id) ? (string) $order_id : '',
                    'order_status'             => $bookingStatus,
                    'is_block_by_user'         => 1,
                    'is_block_by_provider'     => 0,
                ];
                if (!empty($customerObject['image'])) {
                    $customerObject['image'] = service('fileService')->url($customerObject['image'], 'profile');
                }
                $chatUserPayload = json_encode($customerObject, JSON_UNESCAPED_SLASHES);
                if ($chatUserPayload !== false) {
                    $notificationContext['chat_user'] = $chatUserPayload;
                }

                // Queue notifications to admin users (group_id = 1) via all channels
                queue_notification_service(
                    eventType: 'user_blocked',
                    recipients: [],
                    context: $notificationContext,
                    options: [
                        'user_groups' => [1], // Admin user group
                        'channels' => ['fcm', 'email', 'sms'], // All channels
                        'language' => $language,
                        'platforms' => ['admin_panel'] // Admin panel platform for FCM
                    ]
                );

                // Queue notifications to the blocked provider via all channels
                queue_notification_service(
                    eventType: 'user_blocked',
                    recipients: ['user_id' => $partner_id],
                    context: $notificationContext,
                    options: [
                        'channels' => ['fcm', 'email', 'sms'], // All channels
                        'language' => $language,
                        'platforms' => ['android', 'ios', 'web', 'provider_panel'] // Provider platforms
                    ]
                );
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the blocking action
                log_message('error', '[USER_BLOCKED] Notification error: ' . $notificationError->getMessage());
            }

            // Queue notifications to admin users about the user report
            // Using NotificationService for all channels (FCM, Email, SMS)
            // try {
            //     $language = get_current_language_from_request();

            //     // Get reporter and reported user details
            //     $reporter_data = fetch_details('users', ['id' => $user_id], ['username']);
            //     $reported_user_data = fetch_details('users', ['id' => $partner_id], ['username']);

            //     // Get provider name if reported user is a provider
            //     $reported_user_name = $reported_user_data[0]['username'] ?? 'User';
            //     $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
            //     if (!empty($partner_details)) {
            //         $defaultLanguage = get_default_language();
            //         $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            //         $translatedPartnerDetails = $translationModel->getTranslatedDetails($partner_id, $defaultLanguage);
            //         if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
            //             $reported_user_name = $translatedPartnerDetails['company_name'];
            //         } else {
            //             $reported_user_name = $partner_details[0]['company_name'] ?? $reported_user_name;
            //         }
            //     }

            //     // Get reason name with translation support
            //     $report_reason = 'Not specified';
            //     if (!empty($reason_id)) {
            //         $defaultLanguage = get_default_language();
            //         $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();
            //         $report_reason = $translatedReasonModel->getTranslatedReasonText($reason_id, $language, $defaultLanguage);

            //         // Fallback to main table if translation not found
            //         if (empty($report_reason)) {
            //             $reason_data = fetch_details('reasons_for_report_and_block_chat', ['id' => $reason_id], ['reason']);
            //             $report_reason = !empty($reason_data) ? ($reason_data[0]['reason'] ?? 'Not specified') : 'Not specified';
            //         }
            //     }

            //     // Get order_id if available from request (when reported from order booking)
            //     $order_id = $this->request->getPost('order_id');

            //     // Prepare base context data for notification templates.
            //     $baseContext = [
            //         'reporter_name' => $reporter_data[0]['username'] ?? 'Customer',
            //         'reporter_type' => 'customer',
            //         'reporter_id' => $user_id,
            //         'reported_user_name' => $reported_user_name,
            //         'reported_user_type' => 'provider',
            //         'reported_user_id' => $partner_id,
            //         'user_id' => $user_id, // Customer user ID
            //         'provider_id' => $partner_id, // Provider ID (legacy snake_case key)
            //         'order_id' => !empty($order_id) ? $order_id : null, // Order ID if reported from order booking
            //         'report_reason' => $report_reason,
            //         'report_reason_id' => $reason_id ?? 0,
            //         'additional_info' => $additional_info ?: 'None',
            //         'include_logo' => true, // Include logo in email templates
            //     ];

            //     // Attach booking / provider metadata for report-user notifications so that
            //     // all FCM payloads have the same extra fields as chat notifications.
            //     // This adds: bookingId, bookingStatus, companyName, translatedName,
            //     // receiverType, providerId, profile, senderId.
            //     $chatMetaForReport = build_chat_message_details(
            //         (int) $partner_id,
            //         !empty($order_id) ? (int) $order_id : null,
            //         1, // receiverType = 1 (provider) in customer->provider report flow
            //         (int) $user_id
            //     );
            //     if (!empty($chatMetaForReport) && is_array($chatMetaForReport)) {
            //         $baseContext = array_merge($baseContext, $chatMetaForReport);
            //     }

            //     // Send notifications to admin users (group_id = 1) via all channels
            //     // $adminContext = array_merge($baseContext, [
            //     //     'notification_message' => 'A user report has been submitted on the platform. ' . $reporter_data[0]['username'] . ' (customer) has reported ' . $reported_user_name . ' (provider).',
            //     //     'action_message' => 'Please review this report and take appropriate action.',
            //     // ]);
            //     // queue_notification_service(
            //     //     eventType: 'user_reported',
            //     //     recipients: [],
            //     //     context: $adminContext,
            //     //     options: [
            //     //         'user_groups' => [1], // Admin user group
            //     //         'channels' => ['fcm', 'email', 'sms'], // All channels
            //     //         'language' => $language,
            //     //         'platforms' => ['admin_panel'] // Admin panel platform for FCM
            //     //     ]
            //     // );

            //     // Queue notifications to the reported provider via all channels
            //     $providerContext = array_merge($baseContext, [
            //         'notification_message' => 'You have been reported by ' . $reporter_data[0]['username'] . ' (customer).',
            //         'action_message' => 'Please review the report details. If you believe this is a mistake, please contact support.',
            //     ]);
            //     queue_notification_service(
            //         eventType: 'user_reported',
            //         recipients: ['user_id' => $partner_id],
            //         context: $providerContext,
            //         options: [
            //             'channels' => ['fcm', 'email', 'sms'], // All channels
            //             'language' => $language,
            //             'platforms' => ['android', 'ios', 'web', 'provider_panel'] // Provider platforms
            //         ]
            //     );
            // } catch (\Throwable $notificationError) {
            //     // Log error but don't fail the report submission
            //     log_message('error', '[USER_REPORTED] Notification error: ' . $notificationError->getMessage());
            // }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(PROVIDER_BLOCKED_SUCCESSFULLY, 'Provider Blocked Successfully'),
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function unblock_user()
    {
        try {
            $customer_id = $this->user_details['id'];
            $partner_id = $this->request->getPost('partner_id');
            $user_details = fetch_details('users', ['id' => $partner_id]);
            if (empty($user_details)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(USER_NOT_FOUND, 'User not found'),
                ]);
            }

            $update_user = update_details(['is_blocked' => 0, 'is_block_by_user' => 0], ['sender_id' => $customer_id, 'receiver_id' => $partner_id], 'chats');

            $delete_user_report = delete_details(['reporter_id' => $customer_id, 'reported_user_id' => $partner_id], 'user_reports');

            $user_report = fetch_details('user_reports', ['reporter_id' => $customer_id, 'reported_user_id' => $partner_id], ['id']);

            $provider_report = fetch_details('user_reports', ['reporter_id' => $partner_id, 'reported_user_id' => $customer_id], ['id']);

            $data = [
                "is_blocked" => $user_report || $provider_report ? 1 : 0,
                "is_block_by_user" => $user_report ? 1 : 0,
                "is_block_by_provider" => $provider_report ? 1 : 0,
            ];

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(USER_UNBLOCKED_SUCCESSFULLY, 'User Unblocked Successfully'),
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function get_report_reasons()
    {
        try {
            // get_current_language_from_request() reads the Content-Language header — no DB hit.
            // get_default_language() queries the DB, so we call it once and reuse below.
            $currentLanguage = get_current_language_from_request();
            $defaultLanguage = get_default_language();

            // If no language header was sent, fall back to the site default.
            if (empty($currentLanguage)) {
                $currentLanguage = $defaultLanguage;
            }

            $db = db_connect();

            $builder = $db->table('reasons_for_report_and_block_chat r');

            if ($currentLanguage === $defaultLanguage) {
                // ---------------------------------------------------------------
                // FAST PATH: current lang == default lang.
                // Only ONE join is needed — both "reason" and "translated_reason"
                // will carry the same value, so a second join is wasteful.
                // ---------------------------------------------------------------
                $builder->select("
                    r.id,
                    r.needs_additional_info,
                    r.type,
                    r.reason AS main_reason,
                    t.reason AS translated_reason_raw
                ");
                $builder->join(
                    'translated_reasons_for_report_and_block_chat t',
                    "t.reason_id = r.id AND t.language_code = " . $db->escape($currentLanguage),
                    'left'
                );

                $results = $builder->get()->getResultArray();

                // Single-join fallback: use translation if available, else main table value.
                foreach ($results as &$row) {
                    $text = $row['translated_reason_raw'] ?? $row['main_reason'] ?? '';
                    $row['reason']             = $text;
                    $row['translated_reason']  = $text; // same language, so same value
                    unset($row['main_reason'], $row['translated_reason_raw']);
                }
                unset($row);
            } else {
                // ---------------------------------------------------------------
                // FULL PATH: different current and default languages.
                // Two joins are required: one for the current lang (displayed text)
                // and one for the default lang (fallback "reason" field).
                // ---------------------------------------------------------------
                $builder->select("
                    r.id,
                    r.needs_additional_info,
                    r.type,
                    r.reason AS main_reason,
                    cur.reason AS current_reason,
                    def.reason AS default_reason
                ");
                $builder->join(
                    'translated_reasons_for_report_and_block_chat cur',
                    "cur.reason_id = r.id AND cur.language_code = " . $db->escape($currentLanguage),
                    'left'
                );
                $builder->join(
                    'translated_reasons_for_report_and_block_chat def',
                    "def.reason_id = r.id AND def.language_code = " . $db->escape($defaultLanguage),
                    'left'
                );

                $results = $builder->get()->getResultArray();

                // Apply fallback: default translation > main table for "reason".
                // Current translation > default translation > main table for "translated_reason".
                foreach ($results as &$row) {
                    $row['reason']            = $row['default_reason'] ?? $row['main_reason'] ?? '';
                    $row['translated_reason'] = $row['current_reason'] ?? $row['default_reason'] ?? $row['main_reason'] ?? '';
                    unset($row['main_reason'], $row['current_reason'], $row['default_reason']);
                }
                unset($row);
            }

            return $this->response->setJSON([
                'error'   => false,
                'message' => labels(REPORT_REASONS_FETCHED_SUCCESSFULLY, 'Report Reasons Fetched Successfully'),
                'data'    => $results,
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') .
                    ' Params: ' . json_encode($_POST) .
                    " Issue: " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_report_reasons()'
            );

            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function get_blocked_providers()
    {
        try {
            $user_id = $this->user_details['id'];
            $db = \Config\Database::connect();

            // Get blocked users through user_reports table
            $builder = $db->table('user_reports ur');
            $builder->select('u.id, u.username, u.email, u.phone, u.image, r.id as reason_id, ur.additional_info, ur.created_at as blocked_date, pd.company_name as provider_name')
                ->join('users u', 'u.id = ur.reported_user_id')
                ->join('partner_details pd', 'pd.partner_id = ur.reported_user_id')
                ->join('reasons_for_report_and_block_chat r', 'r.id = ur.reason_id', 'left')
                ->where('ur.reporter_id', $user_id);
            $blocked_users = $builder->get()->getResultArray();


            $currentLanguage = get_current_language_from_request();
            $defaultLanguage = get_default_language();

            // Get all reason IDs to fetch translations
            $reasonIds = array_column($blocked_users, 'reason_id');
            $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();
            $translations = [];

            if (!empty($reasonIds)) {
                $translations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $currentLanguage);
            }

            // Create lookup array for translations
            $translationLookup = [];
            foreach ($translations as $translation) {
                $translationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Get default language translations
            $defaultTranslations = [];
            if (!empty($reasonIds)) {
                $defaultTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $defaultLanguage);
            }

            // Create lookup array for default translations
            $defaultTranslationLookup = [];
            foreach ($defaultTranslations as $translation) {
                $defaultTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Add translated reason text to each blocked user
            foreach ($blocked_users as &$user) {
                // Set reason field with default language data or main table fallback
                $user['reason'] = $defaultTranslationLookup[$user['reason_id']] ?? $user['reason'] ?? '';

                // Set translated_reason field with current language translation if available
                $currentTranslation = $translationLookup[$user['reason_id']] ?? null;
                $user['translated_reason'] = $currentTranslation;
            }

            $fileService = service('fileService');
            // Format image paths and add translations for each user
            foreach ($blocked_users as &$user) {
                // Add translation support for provider names
                if (!empty($user['id'])) {
                    $partnerData = [
                        'company_name' => $user['provider_name'] ?? '',
                        'about' => '',
                        'long_description' => '',

                    ];
                    $translatedData = get_translated_partner_data_for_api($user['id'], $partnerData);
                    $user['provider_name'] = $translatedData['company_name'];
                    $user['translated_provider_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                }

                $user['image'] = $fileService->url($user['image'] ?? '', 'profile', 'public/uploads/profiles/default.png');
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(BLOCKED_USERS_FETCHED_SUCCESSFULLY, 'Blocked Users Fetched Successfully'),
                'data' => $blocked_users ?? []
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }
}
