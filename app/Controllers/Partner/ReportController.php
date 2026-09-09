<?php

namespace App\Controllers\Partner;

use App\Controllers\Partner\Partner;
use App\Models\ReasonsForReportAndBlockChat_model;

class ReportController extends Partner
{
    protected $reasonsForReportAndBlockChat;

    public function __construct()
    {
        parent::__construct();
        $this->reasonsForReportAndBlockChat = new ReasonsForReportAndBlockChat_model();
    }

    public function get_report_reasons()
    {
        try {
            if (!$this->isLoggedIn) {
                return $this->response->setJSON(['error' => true, 'message' => labels(UNAUTHORIZED_ACCESS, "Unauthorized access")]);
            }

            // Get current language for translations
            // Get language from session, fallback to default language if not set
            $session = session();
            $currentLanguage = $session->get('lang') ?? 'en';

            // If no language set in session, try to get from database
            if ($currentLanguage === 'en') {
                $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code']);
                if (!empty($defaultLanguage)) {
                    $currentLanguage = $defaultLanguage[0]['code'];
                }
            }

            // Get default language code (for fallback purposes)
            $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';

            // Get all reasons from main table with reason field for fallback
            $reasons = fetch_details('reasons_for_report_and_block_chat', ['type' => 'admin'], ['id', 'reason', 'needs_additional_info', 'type']);

            // Get translations for all reasons
            $reasonIds = array_column($reasons, 'id');
            $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();

            // Get current language translations (first priority - show in user's selected language)
            $currentTranslations = [];
            if (!empty($reasonIds)) {
                $currentTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $currentLanguage);
            }

            // Get default language translations (fallback if current language translation not available)
            $defaultTranslations = [];
            if (!empty($reasonIds)) {
                $defaultTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $defaultLanguage);
            }

            // Create lookup arrays for translations
            $currentTranslationLookup = [];
            foreach ($currentTranslations as $translation) {
                $currentTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            $defaultTranslationLookup = [];
            foreach ($defaultTranslations as $translation) {
                $defaultTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Add translated reason text to each reason with proper fallback logic
            // Priority: current language translation -> default language translation -> main table data
            // This matches the admin panel behavior and ensures reasons show in the selected language
            foreach ($reasons as &$reason) {
                // Priority 1: Current language translation (user's selected language)
                if (isset($currentTranslationLookup[$reason['id']]) && !empty($currentTranslationLookup[$reason['id']])) {
                    $reason['reason'] = $currentTranslationLookup[$reason['id']];
                }
                // Priority 2: Default language translation (fallback if current language not available)
                elseif (isset($defaultTranslationLookup[$reason['id']]) && !empty($defaultTranslationLookup[$reason['id']])) {
                    $reason['reason'] = $defaultTranslationLookup[$reason['id']];
                }
                // Priority 3: Main table data (final fallback)
                else {
                    $reason['reason'] = $reason['reason'] ?? '';
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(REASONS_FETCHED_SUCCESSFULLY, "Reasons fetched successfully"),
                'data' => $reasons
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/ReportController.php - get_report_reasons()');
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, "Something went wrong")]);
        }
    }

    public function submit_report()
    {
        try {
            if (!$this->isLoggedIn) {
                return $this->response->setJSON(['error' => true, 'message' => labels(UNAUTHORIZED_ACCESS, "Unauthorized access")]);
            }

            $reported_user_id = $this->request->getPost('reported_user_id');
            $reason_id = $this->request->getPost('reason_id');
            $additional_info = $this->request->getPost('additional_info');
            $order_id = $this->request->getPost('order_id');

            // Get reason details to check if additional info is required

            if (!empty($reason_id)) {
                $reason = fetch_details('reasons_for_report_and_block_chat', ['id' => $reason_id], ['id', 'needs_additional_info', 'type'])[0];
                if ($reason['needs_additional_info'] == 1 && empty($additional_info)) {
                    return $this->response->setJSON(['error' => true, 'message' => labels(ADDITIONAL_INFORMATION_IS_REQUIRED_FOR_THIS_REASON, "Additional information is required for this reason")]);
                }
            }
            // Save the report
            $data = [
                'reporter_id' => $this->userId,
                'reported_user_id' => $reported_user_id,
                'reason_id' => $reason_id ?? null,
                'additional_info' => $additional_info,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $db = \Config\Database::connect();
            $builder = $db->table('user_reports');
            $builder->insert($data);

            // Send notifications for user blocking
            // Using NotificationService for all channels (FCM, Email, SMS)
            // This notification is sent when provider blocks a customer from the partner panel
            try {
                $language = get_current_language_from_request();

                // Get blocker (provider) and blocked user (customer) details
                $blocked_user_data = fetch_details('users', ['id' => $reported_user_id], ['username']);

                // Get provider name with translation support
                $blocker_name = 'Provider';
                $partner_details = fetch_details('partner_details', ['partner_id' => $this->userId], ['company_name']);
                if (!empty($partner_details)) {
                    $defaultLanguage = get_default_language();
                    $translationModel = new \App\Models\TranslatedPartnerDetails_model();
                    $translatedPartnerDetails = $translationModel->getTranslatedDetails($this->userId, $defaultLanguage);
                    if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
                        $blocker_name = $translatedPartnerDetails['company_name'];
                    } else {
                        $blocker_name = $partner_details[0]['company_name'] ?? $blocker_name;
                    }
                }

                // Prepare context data for notification templates
                // Templates contain the message content, we just provide the variables
                $notificationContext = [
                    'blocker_name' => $blocker_name,
                    'blocker_type' => 'provider',
                    'blocker_id' => $this->userId,
                    'blocked_user_name' => $blocked_user_data[0]['username'] ?? 'Customer',
                    'blocked_user_type' => 'customer',
                    'blocked_user_id' => $reported_user_id,
                    'user_id' => $reported_user_id, // Customer user ID
                    'provider_id' => $this->userId, // Provider ID
                    'order_id' => !empty($order_id) ? $order_id : null, // Order ID if reported from order booking
                    'include_logo' => true, // Include logo in email templates
                ];

                // Build chat_user payload (stringified provider object) for notification consumers.
                // Matches the chat_user structure used in new_message notifications.
                // Provider is the blocker here (from partner panel), so is_block_by_provider = true.
                $chatUserPayload = build_chat_user_payload(
                    partnerId: (int) $this->userId,
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
                    recipients: ['user_id' => $reported_user_id],
                    context: $notificationContext,
                    options: [
                        'channels' => ['fcm', 'email', 'sms'], // All channels
                        'language' => $language,
                        'platforms' => ['android', 'ios', 'web'] // Customer platforms
                    ]
                );
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the blocking action
                log_message('error', '[USER_BLOCKED] Notification error (partner panel): ' . $notificationError->getMessage());
            }

            // Send notifications to admin users about the user report
            // Using NotificationService for all channels (FCM, Email, SMS)
            // This notification is sent when provider reports a customer from the partner panel
            // try {
            //     $language = get_current_language_from_request();

            //     // Get reporter (provider) and reported user (customer) details
            //     $reported_user_data = fetch_details('users', ['id' => $reported_user_id], ['username']);

            //     // Get provider name with translation support
            //     $reporter_name = 'Provider';
            //     $partner_details = fetch_details('partner_details', ['partner_id' => $this->userId], ['company_name']);
            //     if (!empty($partner_details)) {
            //         $defaultLanguage = get_default_language();
            //         $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            //         $translatedPartnerDetails = $translationModel->getTranslatedDetails($this->userId, $defaultLanguage);
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

            //     // Prepare base context data for notification templates
            //     $baseContext = [
            //         'reporter_name' => $reporter_name,
            //         'reporter_type' => 'provider',
            //         'reporter_id' => $this->userId,
            //         'reported_user_name' => $reported_user_data[0]['username'] ?? 'Customer',
            //         'reported_user_type' => 'customer',
            //         'reported_user_id' => $reported_user_id,
            //         'user_id' => $reported_user_id, // Customer user ID
            //         'provider_id' => $this->userId, // Provider ID
            //         'order_id' => !empty($order_id) ? $order_id : null, // Order ID if reported from order booking
            //         'report_reason' => $report_reason,
            //         'report_reason_id' => $reason_id ?? 0,
            //         'additional_info' => $additional_info ?: 'None',
            //         'include_logo' => true, // Include logo in email templates
            //     ];

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
            //         recipients: ['user_id' => $reported_user_id],
            //         context: $customerContext,
            //         options: [
            //             'channels' => ['fcm', 'email', 'sms'], // All channels
            //             'language' => $language,
            //             'platforms' => ['android', 'ios', 'web'] // Customer platforms
            //         ]
            //     );
            // } catch (\Throwable $notificationError) {
            //     // Log error but don't fail the report submission
            //     log_message('error', '[USER_REPORTED] Notification error (partner panel): ' . $notificationError->getMessage());
            // }

            // Add event tracking data
            $eventData = [
                'clarity_event' => 'user_reported',
                'user_id' => $reported_user_id,
                'reason_id' => $reason_id ?? ''
            ];

            // Also track user_blocked since reporting blocks the user
            $eventData['user_blocked'] = true;
            $eventData['blocked_user_id'] = $reported_user_id;

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(USER_REPORTED_AND_BLOCKED_SUCCESSFULLY, "User Reported and Blocked Successfully"),
                'data' => $eventData
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/ReportController.php - submit_report()');
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, "Something went wrong")]);
        }
    }
}
