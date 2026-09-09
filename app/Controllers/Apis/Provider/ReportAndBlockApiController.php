<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;

class ReportAndBlockApiController extends BaseController
{
    protected $request;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
    ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
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

    public function get_report_reasons()
    {
        try {
            // Get current language for translations
            // Fix: Don't replace 'en' with default language - if user wants English, use English
            $session = session();
            $currentLanguage = $session->get('lang') ?? 'en';

            // Get default language code (for fallback purposes)
            $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';

            // Get all reasons from main table
            $reasons = fetch_details('reasons_for_report_and_block_chat', [], ['id', 'reason', 'needs_additional_info', 'type']);

            // Get translations for all reasons
            $reasonIds = array_column($reasons, 'id');
            $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();

            // Get English translations first (priority when English exists)
            // This fixes the issue where reasons show in wrong language even when English exists
            $englishTranslations = [];
            if (!empty($reasonIds)) {
                $englishTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, 'en');
            }

            // Get current language translations (if not English)
            $translations = [];
            if (!empty($reasonIds) && $currentLanguage !== 'en') {
                $translations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $currentLanguage);
            }

            // Create lookup array for English translations
            $englishTranslationLookup = [];
            foreach ($englishTranslations as $translation) {
                $englishTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Create lookup array for current language translations
            $translationLookup = [];
            foreach ($translations as $translation) {
                $translationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Get default language translations (if different from English and current)
            $defaultTranslations = [];
            if (!empty($reasonIds) && $defaultLanguage !== 'en' && $currentLanguage !== $defaultLanguage) {
                $defaultTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $defaultLanguage);
            }

            // Create lookup array for default translations
            $defaultTranslationLookup = [];
            foreach ($defaultTranslations as $translation) {
                $defaultTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Add translated reason text to each reason
            // Priority: English translation (if exists) > Current language > Default language > Main table
            // This ensures English is shown when available, fixing the language display issue
            foreach ($reasons as &$reason) {
                // Priority 1: English translation (prefer English when it exists)
                if (isset($englishTranslationLookup[$reason['id']]) && !empty($englishTranslationLookup[$reason['id']])) {
                    $reason['reason'] = $englishTranslationLookup[$reason['id']];
                }
                // Priority 2: Current language translation (if not English)
                elseif (isset($translationLookup[$reason['id']]) && !empty($translationLookup[$reason['id']])) {
                    $reason['reason'] = $translationLookup[$reason['id']];
                }
                // Priority 3: Default language translation (if different from English and current)
                elseif (isset($defaultTranslationLookup[$reason['id']]) && !empty($defaultTranslationLookup[$reason['id']])) {
                    $reason['reason'] = $defaultTranslationLookup[$reason['id']];
                }
                // Priority 4: Main table data (fallback)
                else {
                    $reason['reason'] = $reason['reason'] ?? '';
                }

                // Set translated_reason field with current language translation if available
                $currentTranslation = $translationLookup[$reason['id']] ?? null;
                $reason['translated_reason'] = $currentTranslation;
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(REPORT_REASONS_FETCHED_SUCCESSFULLY, 'Report reasons fetched successfully'),
                'data' => $reasons,
            ]);
        } catch (\Throwable $th) {
            log_the_responce($this->request->header('Authorization') . ' Params: ' . json_encode($_POST) . " Issue: " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_report_reasons()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function block_user()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'user_id' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }

            $partner_id = $this->user_details['id'];
            $user_id = $this->request->getPost('user_id');
            $reason_id = $this->request->getPost('reason_id');
            $order_id = $this->request->getPost('order_id'); // Order ID if reported from order booking
            $additional_info = "";

            $customer_details = fetch_details('users', ['id' => $user_id]);

            if (empty($customer_details)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(CUSTOMER_NOT_FOUND, 'Customer not found'),
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

            $user_report = fetch_details('user_reports', ['reporter_id' => $partner_id, 'reported_user_id' => $user_id], ['id']);

            if (!empty($user_report)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(YOU_HAVE_ALREADY_REPORTED_THIS_USER, 'You have already reported this user.'),
                ]);
            }

            $data = [
                'reporter_id' => $partner_id,
                'reported_user_id' => $user_id,
                'reason_id' => $reason_id ?? 0,
                'additional_info' => $additional_info
            ];

            $user_report_id = insert_details($data, 'user_reports');

            // Send notifications for user blocking
            // Using NotificationService for all channels (FCM, Email, SMS)
            try {
                $language = get_current_language_from_request();

                // Get blocker (provider) and blocked user (customer) details
                $blocked_user_data = fetch_details('users', ['id' => $user_id], ['username']);

                // Get provider name with translation support
                $blocker_name = 'Provider';
                $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                if (!empty($partner_details)) {
                    $defaultLanguage = get_default_language();
                    $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                    $translatedPartnerDetails = $translationModel->getTranslatedDetails($partner_id, $defaultLanguage);
                    if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
                        $blocker_name = $translatedPartnerDetails['company_name'];
                    } else {
                        $blocker_name = $partner_details[0]['company_name'] ?? $blocker_name;
                    }
                }

                // Prepare context data for notification templates.
                // Templates contain the message content, we just provide the variables.
                $notificationContext = [
                    'blocker_name' => $blocker_name,
                    'blocker_type' => 'provider',
                    'blocker_id' => $partner_id,
                    'blocked_user_name' => $blocked_user_data[0]['username'] ?? 'Customer',
                    'blocked_user_type' => 'customer',
                    'blocked_user_id' => $user_id,
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
                    2, // receiverType = 2 (customer) in block-user flow
                    (int) $partner_id
                );
                if (!empty($chatMetaForBlock) && is_array($chatMetaForBlock)) {
                    $notificationContext = array_merge($notificationContext, $chatMetaForBlock);
                }

                // Build chat_user payload (stringified provider object) for notification consumers.
                // Matches the chat_user structure used in new_message notifications.
                // Provider is the blocker here, so is_block_by_provider = true.
                $chatUserPayload = build_chat_user_payload(
                    partnerId: (int) $partner_id,
                    orderId: !empty($order_id) ? (string) $order_id : null,
                    partnerName: $partner_details[0]['company_name'] ?? $blocker_name,
                    translatedPartnerName: $blocker_name,
                    isBlockByUser: false,
                    isBlockByProvider: true
                );
                if ($chatUserPayload !== null) {
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

                // Queue notifications to the blocked customer via all channels
                queue_notification_service(
                    eventType: 'user_blocked',
                    recipients: ['user_id' => $user_id],
                    context: $notificationContext,
                    options: [
                        'channels' => ['fcm', 'email', 'sms'], // All channels
                        'language' => $language,
                        'platforms' => ['android', 'ios', 'web'] // Customer platforms
                    ]
                );
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the blocking action
                log_message('error', '[USER_BLOCKED] Notification error (partner): ' . $notificationError->getMessage());
            }

            // Send notifications to admin users about the user report
            // Using NotificationService for all channels (FCM, Email, SMS)
            // try {
            //     $language = get_current_language_from_request();

            //     // Get reporter (provider) and reported user (customer) details
            //     $reported_user_data = fetch_details('users', ['id' => $user_id], ['username']);

            //     // Get provider name with translation support
            //     $reporter_name = 'Provider';
            //     $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
            //     if (!empty($partner_details)) {
            //         $defaultLanguage = get_default_language();
            //         $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            //         $translatedPartnerDetails = $translationModel->getTranslatedDetails($partner_id, $defaultLanguage);
            //         if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
            //             $reporter_name = $translatedPartnerDetails['company_name'];
            //         } else {
            //             $reporter_name = $partner_details[0]['company_name'] ?? $reporter_name;
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

            //     // Prepare base context data for notification templates.
            //     $baseContext = [
            //         'reporter_name' => $reporter_name,
            //         'reporter_type' => 'provider',
            //         'reporter_id' => $partner_id,
            //         'reported_user_name' => $reported_user_data[0]['username'] ?? 'Customer',
            //         'reported_user_type' => 'customer',
            //         'reported_user_id' => $user_id,
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
            //         2, // receiverType = 2 (customer) in report-user flow
            //         (int) $partner_id
            //     );
            //     if (!empty($chatMetaForReport) && is_array($chatMetaForReport)) {
            //         $baseContext = array_merge($baseContext, $chatMetaForReport);
            //     }

            //     // Queue notifications to admin users (group_id = 1) via all channels
            //     $adminContext = array_merge($baseContext, [
            //         'notification_message' => 'A user report has been submitted on the platform. ' . $reporter_name . ' (provider) has reported ' . ($reported_user_data[0]['username'] ?? 'Customer') . ' (customer).',
            //         'action_message' => 'Please review this report and take appropriate action.',
            //     ]);
            //     queue_notification_service(
            //         eventType: 'user_reported',
            //         recipients: [],
            //         context: $adminContext,
            //         options: [
            //             'user_groups' => [1], // Admin user group
            //             'channels' => ['fcm', 'email', 'sms'], // All channels
            //             'language' => $language,
            //             'platforms' => ['admin_panel'] // Admin panel platform for FCM
            //         ]
            //     );

            //     // Queue notifications to the reported customer via all channels
            //     $customerContext = array_merge($baseContext, [
            //         'notification_message' => 'You have been reported by ' . $reporter_name . ' (provider).',
            //         'action_message' => 'Please review the report details. If you believe this is a mistake, please contact support.',
            //     ]);
            //     queue_notification_service(
            //         eventType: 'user_reported',
            //         recipients: ['user_id' => $user_id],
            //         context: $customerContext,
            //         options: [
            //             'channels' => ['fcm', 'email', 'sms'], // All channels
            //             'language' => $language,
            //             'platforms' => ['android', 'ios', 'web'] // Customer platforms
            //         ]
            //     );
            // } catch (\Throwable $notificationError) {
            //     // Log error but don't fail the report submission
            //     log_message('error', '[USER_REPORTED] Notification error (partner): ' . $notificationError->getMessage());
            // }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(CUSTOMER_BLOCKED_SUCCESSFULLY, 'Customer Blocked Successfully'),
            ]);
        } catch (\Throwable $th) {
            throw $th;
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function unblock_user()
    {
        try {
            $partner_id = $this->user_details['id'];
            $user_id = $this->request->getPost('user_id');
            $user_details = fetch_details('users', ['id' => $user_id]);
            if (empty($user_details)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(USER_NOT_FOUND, 'User not found'),
                ]);
            }

            $chats = update_details(['is_blocked' => 0, 'is_block_by_provider' => 0], ['sender_id' => $partner_id, 'receiver_id' => $user_id], 'chats');

            $delete_user_report = delete_details(['reporter_id' => $partner_id, 'reported_user_id' => $user_id], 'user_reports');

            $users = fetch_details('users', ['id' => $user_id], ['id', 'username', 'email', 'phone', 'image']);
            return $this->response->setJSON([
                'error' => false,
                'message' => labels(USER_UNBLOCKED_SUCCESSFULLY, 'User Unblocked Successfully'),
                'data' => $users,
            ]);
        } catch (\Throwable $th) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function get_blocked_users()
    {
        try {
            $partner_id = $this->user_details['id'];

            $db = \Config\Database::connect();

            // Get blocked users through user_reports table
            // Fix: Added r.reason to SELECT so it's available for fallback when translations are missing
            $builder = $db->table('user_reports ur');
            $builder->select('u.id, u.username, u.email, u.phone, u.image, r.id as reason_id, r.reason, ur.additional_info, ur.created_at as blocked_date')
                ->join('users u', 'u.id = ur.reported_user_id')
                ->join('reasons_for_report_and_block_chat r', 'r.id = ur.reason_id', 'left')
                ->where('ur.reporter_id', $partner_id);

            $blocked_users = $builder->get()->getResultArray();

            // Get current language for translations
            // Fix: Don't replace 'en' with default language - if user wants English, use English
            $session = session();
            $currentLanguage = $session->get('lang') ?? 'en';

            // Get default language code (for fallback purposes)
            $defaultLanguageData = fetch_details('languages', ['is_default' => 1], ['code']);
            $defaultLanguage = !empty($defaultLanguageData) ? $defaultLanguageData[0]['code'] : 'en';

            // Get all reason IDs to fetch translations
            $reasonIds = array_column($blocked_users, 'reason_id');
            $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();

            // Get English translations first (priority when English exists)
            // This fixes the issue where reasons show in wrong language even when English exists
            $englishTranslations = [];
            if (!empty($reasonIds)) {
                $englishTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, 'en');
            }

            // Get current language translations (if not English)
            $translations = [];
            if (!empty($reasonIds) && $currentLanguage !== 'en') {
                $translations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $currentLanguage);
            }

            // Create lookup array for English translations
            $englishTranslationLookup = [];
            foreach ($englishTranslations as $translation) {
                $englishTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Create lookup array for current language translations
            $translationLookup = [];
            foreach ($translations as $translation) {
                $translationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Get default language translations (if different from English and current)
            $defaultTranslations = [];
            if (!empty($reasonIds) && $defaultLanguage !== 'en' && $currentLanguage !== $defaultLanguage) {
                $defaultTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $defaultLanguage);
            }

            // Create lookup array for default translations
            $defaultTranslationLookup = [];
            foreach ($defaultTranslations as $translation) {
                $defaultTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Add translated reason text to each blocked user
            // Priority: English translation (if exists) > Current language > Default language > Main table
            // This ensures English is shown when available, fixing the language display issue
            foreach ($blocked_users as &$user) {
                // Priority 1: English translation (prefer English when it exists)
                if (isset($englishTranslationLookup[$user['reason_id']]) && !empty($englishTranslationLookup[$user['reason_id']])) {
                    $user['reason'] = $englishTranslationLookup[$user['reason_id']];
                }
                // Priority 2: Current language translation (if not English)
                elseif (isset($translationLookup[$user['reason_id']]) && !empty($translationLookup[$user['reason_id']])) {
                    $user['reason'] = $translationLookup[$user['reason_id']];
                }
                // Priority 3: Default language translation (if different from English and current)
                elseif (isset($defaultTranslationLookup[$user['reason_id']]) && !empty($defaultTranslationLookup[$user['reason_id']])) {
                    $user['reason'] = $defaultTranslationLookup[$user['reason_id']];
                }
                // Priority 4: Main table data (fallback)
                else {
                    $user['reason'] = $user['reason'] ?? '';
                }

                // Set translated_reason field with current language translation if available
                $currentTranslation = $translationLookup[$user['reason_id']] ?? null;
                $user['translated_reason'] = $currentTranslation;
            }

            $fileService = service('fileService');
            // Format image paths for each user
            foreach ($blocked_users as &$user) {
                $user['image'] = $fileService->url($user['image'] ?? '', 'profile', 'public/uploads/profiles/default.png');
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(BLOCKED_USERS_FETCHED_SUCCESSFULLY, 'Blocked Users Fetched Successfully'),
                'data' => $blocked_users
            ]);
        } catch (\Throwable $th) {
            throw $th;
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }
}
