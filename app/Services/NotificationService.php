<?php

namespace App\Services;

use App\Notification\Factories\EmailProviderFactory;
use App\Notification\Factories\PushProviderFactory;
use App\Notification\Factories\SmsProviderFactory;

/**
 * NotificationService
 * 
 * Unified notification service that handles FCM, SMS, and Email notifications.
 * Provides a simple, DRY interface for sending notifications across all channels.
 * 
 * Features:
 * - Template-based notifications for Email and SMS
 * - Direct messaging for FCM (templates deferred)
 * - Automatic language fallback
 * - Notification preference checking
 * - User unsubscribe support
 * 
 * Usage:
 * $service = new NotificationService();
 * $result = $service->send(
 *     eventType: 'booking_confirmed',
 *     recipients: ['user_id' => 123],
 *     context: ['booking_id' => 456, 'provider_id' => 789],
 *     options: ['channels' => ['fcm', 'email', 'sms']]
 * );
 */
class NotificationService
{
    /**
     * @var NotificationTemplateService Template service instance
     */
    private NotificationTemplateService $templateService;
    private $logger;

    /**
     * Constructor
     * 
     * Initializes the notification service with template service
     */
    public function __construct()
    {
        $this->templateService = new NotificationTemplateService();
        $this->logger = service('logger');
    }

    /**
     * Write log using logger service with fallback to direct file writing
     * 
     * This wrapper ensures logs are written even if logger service fails.
     * It tries the logger service first, then falls back to direct file writing.
     * This is especially important in CLI context where logger service might not be fully initialized.
     * 
     * @param string $level Log level (info, error, warning, etc.)
     * @param string $message Log message
     */
    private function writeLog(string $level, string $message): void
    {
        // Try logger service first
        try {
            // Check if logger is already set
            if (isset($this->logger) && $this->logger !== null) {
                $this->logger->log($level, $message);
                return; // Success, exit early
            }

            // Try to get logger service
            try {
                $this->logger = service('logger');
                if ($this->logger !== null) {
                    $this->logger->log($level, $message);
                    return; // Success, exit early
                }
            } catch (\Throwable $serviceException) {
                // Service might not be available in CLI, fall through to file writing
            }
        } catch (\Throwable $e) {
            // If logger service fails, fall back to direct file writing
            // This ensures logs are always written, especially in CLI context
        }

        // Always use file writing as fallback to ensure logs are written
        // This is critical for CLI queue workers where logger service might not work
        $this->writeLogToFile($level, $message);
    }

    /**
     * Write log directly to file (fallback method)
     * 
     * This is used when logger service is not available or fails.
     * Ensures logs are written to writable/logs directory even in CLI context.
     * 
     * @param string $level Log level (info, error, warning, etc.)
     * @param string $message Log message
     */
    private function writeLogToFile(string $level, string $message): void
    {
        // Determine log path - try multiple methods to ensure we get the correct path
        $logPath = null;

        // First try WRITEPATH constant (should be defined in CodeIgniter)
        if (defined('WRITEPATH') && !empty(WRITEPATH)) {
            $logPath = rtrim(WRITEPATH, '/') . '/logs/';
        }
        // Fallback: try to get from Paths config
        elseif (defined('ROOTPATH')) {
            // Try standard writable directory (without dot)
            $logPath = rtrim(ROOTPATH, '/') . '/writable/logs/';
        }
        // Last resort: use current working directory
        else {
            $logPath = getcwd() . '/writable/logs/';
        }

        // Ensure directory exists with proper permissions
        if (!is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
        }

        // Build log file name with date
        $logFile = $logPath . 'log-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = strtoupper($level) . " - {$timestamp} --> {$message}\n";

        // Write to file with error suppression (we don't want to break execution if logging fails)
        // But ensure we try to write even if directory creation failed
        $result = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // If writing failed, try one more time with directory creation
        if ($result === false && !is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
            @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Send notification via specified channels
     * 
     * Main entry point for sending notifications. Automatically routes to
     * appropriate channels based on settings and preferences.
     * 
     * Supports multiple sending modes:
     * - Single user: recipients['user_id'] = 123
     * - Multiple users: options['user_ids'] = [123, 456, 789]
     * - By platforms: options['platforms'] = ['android', 'ios']
     * - By user groups: options['user_groups'] = [2, 3]
     * - All users: options['send_to_all'] = true
     * 
     * @param string $eventType Event type (e.g., 'booking_confirmed', 'booking_cancelled', 'booking_completed', etc.)
     * @param array $recipients Recipient information ['user_id' => int, 'email' => string, 'phone' => string, 'fcm_tokens' => array]
     * @param array $context Context data for template variable replacement
     * @param array $options Options array:
     *   - 'channels' => ['fcm', 'email', 'sms']
     *   - 'language' => 'en'
     *   - 'user_ids' => [123, 456] (for multiple users)
     *   - 'platforms' => ['android', 'ios', 'admin_panel', 'provider_panel', 'web']
     *   - 'user_groups' => [2, 3] (group IDs)
     *   - 'send_to_all' => true (send to all active users)
     * @return array Result array with success status and details
     */
    public function send(string $eventType, array $recipients, array $context = [], array $options = []): array
    {
        // $this->writeLog('info', '[NOTIFICATION_SERVICE] ===== SEND METHOD CALLED =====');
        // $this->writeLog('info', '[NOTIFICATION_SERVICE] Sending notification: ' . $eventType);
        // $this->writeLog('info', '[NOTIFICATION_SERVICE] Recipients: ' . json_encode($recipients));
        // $this->writeLog('info', '[NOTIFICATION_SERVICE] Context: ' . json_encode($context));
        // $this->writeLog('info', '[NOTIFICATION_SERVICE] Options: ' . json_encode($options));
        $results = [];
        $channels = $options['channels'] ?? ['fcm', 'email', 'sms'];
        $language = $options['language'] ?? null;

        // Determine recipients based on options
        $userIds = $options['user_ids'] ?? [];
        $platforms = $options['platforms'] ?? null;
        $userGroups = $options['user_groups'] ?? null;
        $sendToAll = $options['send_to_all'] ?? false;

        // Backward compatibility: if user_id in recipients, add to user_ids array
        if (isset($recipients['user_id']) && !empty($recipients['user_id'])) {
            if (empty($userIds)) {
                $userIds = [$recipients['user_id']];
            } elseif (!in_array($recipients['user_id'], $userIds)) {
                $userIds[] = $recipients['user_id'];
            }
        }

        // Ensure downstream functions can always see the final resolved user IDs.
        // Why this matters:
        // - We store "system_generated" notifications in DB so they show in the app/panel list.
        // - DB storage should NOT depend on whether the user currently has valid FCM tokens.
        // - Without this, a provider might miss DB notifications when tokens are missing/expired.
        if (!empty($userIds)) {
            $options['user_ids'] = $userIds;
        }

        // FCM: token resolution, templates, send, and DB storage are handled inside FcmProvider::sendNotification().

        // Send via each requested channel
        foreach ($channels as $channel) {
            // $this->writeLog('info', '[NOTIFICATION_SERVICE] Processing channel: ' . $channel . ' for event: ' . $eventType);

            // For non-FCM channels, handle multiple users by looping
            // Check if we need to send to multiple recipients (userIds, userGroups, or sendToAll)
            if ($channel !== 'fcm' && (!empty($userIds) || !empty($userGroups) || $sendToAll)) {
                // $this->writeLog('info', '[NOTIFICATION_SERVICE] Sending to multiple recipients for channel: ' . $channel);

                // Check if we should bypass preference checks for admin custom notifications
                // This needs to be checked here before calling sendToMultipleRecipients
                // Always bypass for admin_custom_notification event type
                $isAdminCustomEvent = (trim($eventType) === 'admin_custom_notification');
                $isAdminNotification = $context['admin_notification'] ?? false;
                $isAdminEmail = $context['admin_email'] ?? false;
                $shouldBypassForMultiple = $isAdminCustomEvent
                    || $isAdminNotification === true
                    || $isAdminNotification === 'true'
                    || $isAdminNotification === 1
                    || $isAdminEmail === true
                    || $isAdminEmail === 'true'
                    || $isAdminEmail === 1
                    || $eventType === 'send_otp';

                // Pass bypass flag in options so sendToMultipleRecipients can use it
                if ($shouldBypassForMultiple) {
                    $options['bypass_preference_check'] = true;
                    // $this->writeLog('info', '[NOTIFICATION_SERVICE] Setting bypass_preference_check flag for admin custom notification (email/SMS with multiple recipients)');
                }

                // Handle multiple recipients for email/SMS
                $channelResults = $this->sendToMultipleRecipients($channel, $eventType, $userIds, $platforms, $userGroups, $sendToAll, $recipients, $context, $options);
                // $this->writeLog('info', '[NOTIFICATION_SERVICE] Multiple recipients result for ' . $channel . ': ' . json_encode($channelResults));
                $results[$channel] = $channelResults;
                continue;
            }

            // For FCM with user groups, skip preference check per user (check is done at system level)
            // For other channels or single user FCM, check preference
            $userId = !empty($userIds) ? $userIds[0] : ($recipients['user_id'] ?? null);

            // Check if we should bypass preference checks
            // Always bypass for admin_custom_notification event type
            $shouldBypass = ($eventType === 'admin_custom_notification' || $eventType === 'send_otp');

            // if ($shouldBypass) {
            //     $this->writeLog('info', '[NOTIFICATION_SERVICE] Bypassing preference check for admin_custom_notification event');
            // }

            if (!$shouldBypass) {
                // For FCM with user groups/platforms, we check preference once at system level
                // For email/SMS with user groups, preference is checked per user in sendToMultipleRecipients
                if ($channel === 'fcm' && (!empty($userGroups) || !empty($platforms) || $sendToAll)) {
                    // For FCM with groups/platforms, check system-level preference only
                    // $this->writeLog('info', '[NOTIFICATION_SERVICE] FCM with user groups/platforms - checking system-level preference');
                    $preferenceCheck = $this->checkNotificationPreference($eventType, $channel, null);
                    // $this->writeLog('info', '[NOTIFICATION_SERVICE] System preference check result for FCM: ' . ($preferenceCheck ? 'enabled' : 'disabled'));
                    if (!$preferenceCheck) {
                        // $this->writeLog('warning', '[NOTIFICATION_SERVICE] FCM channel is disabled for event ' . $eventType . ' at system level');
                        $results[$channel] = [
                            'success' => false,
                            'message' => "FCM channel is disabled for event {$eventType} at system level"
                        ];
                        continue;
                    }
                } else {
                    // Single recipient or standard FCM - check preference normally
                    // $this->writeLog('info', '[NOTIFICATION_SERVICE] Checking notification preference for channel: ' . $channel . ', event: ' . $eventType . ', userId: ' . ($userId ?? 'null'));

                    // Check if channel is enabled for this event
                    $preferenceCheck = $this->checkNotificationPreference($eventType, $channel, $userId);
                    // $this->writeLog('info', '[NOTIFICATION_SERVICE] Preference check result for ' . $channel . ': ' . ($preferenceCheck ? 'enabled' : 'disabled'));
                    if (!$preferenceCheck) {
                        // $this->writeLog('warning', '[NOTIFICATION_SERVICE] Channel ' . $channel . ' is disabled for event ' . $eventType);
                        $results[$channel] = [
                            'success' => false,
                            'message' => "Channel {$channel} is disabled for event {$eventType} or user preferences"
                        ];
                        continue;
                    }
                }
            }
            // else {
            //     $this->writeLog('info', '[NOTIFICATION_SERVICE] Bypassing preference check for admin notification or explicit bypass request (shouldBypass: true)');
            // }

            // Send notification via appropriate channel
            // $this->writeLog('info', '[NOTIFICATION_SERVICE] About to send via channel: ' . $channel);
            switch ($channel) {
                case 'fcm':
                    $provider = (new PushProviderFactory())->make('fcm');
                    $result   = $provider->sendNotification($eventType, $recipients, $context, $options);
                    break;
                case 'sms':
                    $result = $this->sendSmsNotification($eventType, $recipients, $context, $language, $options);
                    break;
                case 'email':
                    $result = $this->sendEmailNotification($eventType, $recipients, $context, $language, $options);
                    break;
                default:
                    $result = [
                        'success' => false,
                        'message' => "Unknown channel: {$channel}"
                    ];
            }

            $results[$channel] = $result;
        }

        return [
            'success' => true,
            'results' => $results
        ];
    }

    /**
     * Send notification to multiple users
     * 
     * Convenience method to send notifications to specific users.
     * 
     * @param array $userIds Array of user IDs
     * @param string $eventType Event type
     * @param array $context Context data
     * @param array $options Additional options
     * @return array Result array
     */
    public function sendToUsers(array $userIds, string $eventType, array $context = [], array $options = []): array
    {
        $options['user_ids'] = $userIds;
        return $this->send($eventType, [], $context, $options);
    }

    /**
     * Send notification to all users on specific platforms
     * 
     * Convenience method to send notifications to all users on specified platforms.
     * 
     * @param array $platforms Array of platform types (android, ios, admin_panel, provider_panel, web)
     * @param string $eventType Event type
     * @param array $context Context data
     * @param array $options Additional options (can include user_groups for filtering)
     * @return array Result array
     */
    public function sendToPlatforms(array $platforms, string $eventType, array $context = [], array $options = []): array
    {
        $options['platforms'] = $platforms;
        return $this->send($eventType, [], $context, $options);
    }

    /**
     * Send notification to all active users
     * 
     * Convenience method to send notifications to all active users.
     * Can be filtered by platforms and user groups.
     * 
     * @param string $eventType Event type
     * @param array $context Context data
     * @param array $options Additional options (can include platforms, user_groups for filtering)
     * @return array Result array
     */
    public function sendToAllUsers(string $eventType, array $context = [], array $options = []): array
    {
        $options['send_to_all'] = true;
        return $this->send($eventType, [], $context, $options);
    }

    /**
     * Send to multiple recipients for non-FCM channels
     * 
     * Handles sending email/SMS to multiple users by looping through them.
     * 
     * @param string $channel Channel name (email, sms)
     * @param string $eventType Event type
     * @param array $userIds Array of user IDs
     * @param array|null $platforms Platforms filter (not used for email/SMS)
     * @param array|null $userGroups User groups filter
     * @param bool $sendToAll Whether to send to all
     * @param array $recipients Original recipients
     * @param array $context Context data
     * @param array $options Options
     * @return array Result array
     */
    private function sendToMultipleRecipients(string $channel, string $eventType, array $userIds, ?array $platforms, ?array $userGroups, bool $sendToAll, array $recipients, array $context, array $options): array
    {
        // log_message('info', '[SEND_TO_MULTIPLE] Channel: ' . $channel . ', Event: ' . $eventType . ', UserGroups: ' . json_encode($userGroups) . ', SendToAll: ' . ($sendToAll ? 'true' : 'false'));

        // Get user IDs if send_to_all or user_groups specified
        if ($sendToAll || !empty($userGroups)) {
            $db = \Config\Database::connect();
            $builder = $db->table('users u');

            if (!empty($userGroups)) {
                // log_message('info', '[SEND_TO_MULTIPLE] Filtering by user groups: ' . json_encode($userGroups));
                $builder->join('users_groups ug', 'ug.user_id = u.id')
                    ->whereIn('ug.group_id', $userGroups)
                    ->groupBy('u.id');
            }

            $users = $builder->select('u.id')->get()->getResultArray();
            $userIds = array_column($users, 'id');
            // log_message('info', '[SEND_TO_MULTIPLE] Found ' . count($userIds) . ' users: ' . json_encode($userIds));
        }

        if (empty($userIds)) {
            // log_message('warning', '[SEND_TO_MULTIPLE] No users found to send notification to');
            return [
                'success' => false,
                'message' => 'No users found to send notification to'
            ];
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        // Check if we should bypass preference checks (for admin notifications, etc.)
        // Always bypass for admin_custom_notification event type (admin custom emails/notifications)
        // This is critical - admin custom emails should always bypass preference checks
        // Use trim and strict comparison to ensure we catch the event type correctly
        $isAdminCustomEvent = (trim($eventType) === 'admin_custom_notification');

        // Also check context flags and explicit bypass option
        $bypassPreferenceCheck = $options['bypass_preference_check'] ?? false;
        $isAdminNotification = $context['admin_notification'] ?? false;
        $isAdminEmail = $context['admin_email'] ?? false;

        // Check for both boolean true and string "true" (in case of JSON serialization issues)
        // Bypass if: admin custom event (highest priority), explicit bypass flag, admin notification context, or admin email context
        // For email channel, always bypass if it's admin_custom_notification event
        $shouldBypass = $isAdminCustomEvent
            || $bypassPreferenceCheck
            || $isAdminNotification === true
            || $isAdminNotification === 'true'
            || $isAdminNotification === 1
            || $isAdminEmail === true
            || $isAdminEmail === 'true'
            || $isAdminEmail === 1
            || $eventType === 'send_otp';

        // Log bypass decision for debugging (only for email channel to avoid FCM logs)
        // if ($channel === 'email') {
        //     log_message('info', '[SEND_TO_MULTIPLE] Email bypass check - eventType: ' . $eventType . ', isAdminCustomEvent: ' . ($isAdminCustomEvent ? 'true' : 'false') . ', shouldBypass: ' . ($shouldBypass ? 'true' : 'false'));
        // }

        foreach ($userIds as $userId) {
            $userRecipients = array_merge($recipients, ['user_id' => $userId]);
            $language = $options['language'] ?? null;

            // Check preference unless bypassed for admin notifications
            if (!$shouldBypass) {
                if (!$this->checkNotificationPreference($eventType, $channel, $userId)) {
                    // log_message('info', '[SEND_TO_MULTIPLE] Preference check failed for user_id: ' . $userId . ', channel: ' . $channel);
                    $failureCount++;
                    continue;
                }
            } else {
                // Log why we're bypassing (for debugging)
                $bypassReason = [];
                if ($isAdminCustomEvent) $bypassReason[] = 'admin_custom_notification event';
                if ($isAdminNotification) $bypassReason[] = 'admin_notification context';
                if ($isAdminEmail) $bypassReason[] = 'admin_email context';
                if ($bypassPreferenceCheck) $bypassReason[] = 'explicit bypass flag';
                // log_message('info', '[SEND_TO_MULTIPLE] Bypassing preference check for user_id: ' . $userId . ' - Reason: ' . implode(', ', $bypassReason));
            }

            // Send notification
            switch ($channel) {
                case 'sms':
                    $result = $this->sendSmsNotification($eventType, $userRecipients, $context, $language, $options);
                    break;
                case 'email':
                    $result = $this->sendEmailNotification($eventType, $userRecipients, $context, $language, $options);
                    break;
                default:
                    $result = ['success' => false, 'message' => "Unknown channel: {$channel}"];
            }

            // Support both legacy 'success' and simplified 'error' (e.g. FCM: success when error is false)
            $isSuccess = ($result['success'] ?? false) || !($result['error'] ?? true);
            if ($isSuccess) {
                $successCount++;
            } else {
                $failureCount++;
            }
            $results[] = ['user_id' => $userId, 'result' => $result];
        }

        return [
            'success' => $failureCount === 0,
            'message' => "Sent to {$successCount} users, {$failureCount} failed",
            'total' => count($userIds),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Send SMS notification
     * 
     * Sends SMS using template system with variable replacement.
     * For admin custom notifications, uses custom message from options.
     * 
     * @param string $eventType Event type
     * @param array $recipients Recipient information
     * @param array $context Context data
     * @param string|null $language Language code
     * @param array $options Additional options (for admin custom notifications)
     * @return array Result array
     */
    private function sendSmsNotification(string $eventType, array $recipients, array $context, ?string $language, array $options = []): array
    {
        try {
            // Get recipient phone number
            $phone = $recipients['phone'] ?? null;

            // $this->writeLog('info', '[SMS_NOTIFICATION] Initial Phone: ' . $phone);

            // If phone not provided but email is, try to get phone from email
            if (!$phone && isset($recipients['email'])) {
                $phone = $this->getPhoneFromEmail($recipients['email']);
            }

            // $this->writeLog('info', '[SMS_NOTIFICATION] Phone from email: ' . $phone);

            // If phone still not found and user_id is provided, try to get from user
            if (!$phone && isset($recipients['user_id'])) {
                $phone = $this->getPhoneFromUserId($recipients['user_id']);
            }

            // $this->writeLog('info', '[SMS_NOTIFICATION] Phone from user_id: ' . $phone);

            if (empty($phone)) {
                return [
                    'success' => false,
                    'message' => 'No phone number found for SMS recipient'
                ];
            }

            // Get user's language_code from users table if user_id is provided and language not explicitly set
            if (empty($language) && isset($recipients['user_id'])) {
                $user = $this->fetchDetails('users', ['id' => $recipients['user_id']], ['preferred_language']);
                if (!empty($user) && !empty($user[0]['preferred_language'])) {
                    $language = $user[0]['preferred_language'];
                    // log_message('info', '[SMS_NOTIFICATION] Using user language_code: ' . $language . ' for user_id: ' . $recipients['user_id']);
                }
            }

            // For admin custom notifications, always use custom message from options
            // Skip template lookup entirely to ensure admin's custom content is used
            if ($eventType === 'admin_custom_notification') {
                // log_message('info', '[SMS_NOTIFICATION] Admin custom notification detected - using custom message from options');
                // Use custom message directly from options
                $message = $options['message'] ?? $context['message'] ?? '';
            } else {
                // For regular notifications, get SMS template with user's language preference
                // log_message('info', '[SMS_NOTIFICATION] Getting SMS template for event: ' . $eventType . ', language: ' . ($language ?? 'default'));
                $templateData = $this->templateService->getSmsTemplate($eventType, $language);
                if (!$templateData) {
                    // log_message('error', '[SMS_NOTIFICATION] SMS template not found for event: ' . $eventType);
                    return [
                        'success' => false,
                        'message' => "SMS template not found for event: {$eventType}"
                    ];
                }
                // log_message('info', '[SMS_NOTIFICATION] SMS template found');

                $variables = $this->templateService->extractVariablesFromContext($eventType, $context);
                $message = $this->templateService->replaceVariables($templateData['template'], $variables);
                $message = $this->cleanSmsMessage($message);

                // Check if active gateway uses template IDs (e.g. Fast2SMS DLT, MSG91)
                $provider = (new SmsProviderFactory())->make();
                $activeGateway = $provider->getName();
                $templateBasedGateways = array_keys(config('Sms')->templateBasedGateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91']);
                $gatewayTemplateId = $this->templateService->getGatewayTemplateId($templateData, $activeGateway);

                if (in_array($activeGateway, $templateBasedGateways, true) && $gatewayTemplateId !== null && $gatewayTemplateId !== '') {
                    // Use template ID + variables for template-based gateways
                    $orderedVariables = $this->templateService->getOrderedVariableValuesForSms($templateData, $context);
                    log_message('info', '[MSG91] NotificationService: sending via ' . $activeGateway . ', template_id=' . $gatewayTemplateId . ', variables_count=' . count($orderedVariables) . ', event=' . $eventType);
                    return $this->sendSms($phone, $message, [
                        'template_id' => $gatewayTemplateId,
                        'variables'   => $orderedVariables,
                    ]);
                }

            }

            // Clean message for SMS (remove HTML, normalize whitespace)
            $message = $this->cleanSmsMessage($message);

            // Send SMS
            return $this->sendSms($phone, $message);
        } catch (\Throwable $th) {
            $this->writeLog('error', '[SMS_NOTIFICATION] Exception trace: ' . $th->getTraceAsString());
            // log_message('error', 'SMS notification error: ' . $th->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS notification: ' . $th->getMessage()
            ];
        }
    }

    /**
     * Send email notification
     * 
     * Sends email using template system with variable replacement.
     * Supports HTML emails with logo embedding.
     * For admin custom notifications, uses custom title/message from options.
     * 
     * @param string $eventType Event type
     * @param array $recipients Recipient information
     * @param array $context Context data
     * @param string|null $language Language code
     * @param array $options Additional options (for admin custom notifications)
     * @return array Result array
     */
    private function sendEmailNotification(string $eventType, array $recipients, array $context, ?string $language, array $options = []): array
    {
        try {
            // Get recipient email
            $email = $recipients['email'] ?? null;

            // If email not provided but user_id is, try to get email from user
            if (!$email && isset($recipients['user_id'])) {
                $email = $this->getEmailFromUserId($recipients['user_id']);
            }

            if (empty($email)) {
                return [
                    'success' => false,
                    'message' => 'No email address found for email recipient'
                ];
            }

            // Check if this is an admin custom notification
            // Admin custom notifications should bypass unsubscribe checks
            // This allows admins to send important emails regardless of user preferences
            $isAdminCustomNotification = ($eventType === 'admin_custom_notification');

            // Log when bypassing unsubscribe check for admin notifications
            if ($isAdminCustomNotification && isset($recipients['user_id'])) {
                // log_message('info', '[EMAIL_NOTIFICATION] Admin custom notification - bypassing unsubscribe check for user_id: ' . $recipients['user_id']);
            }

            // Check if user has unsubscribed from emails
            // Skip this check for admin custom notifications - admins should be able to send regardless
            if (!$isAdminCustomNotification && isset($recipients['user_id']) && !$this->isUnsubscribeEnabled($recipients['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'User has unsubscribed from email notifications'
                ];
            }

            // Get user's language_code from users table if user_id is provided and language not explicitly set
            if (empty($language) && isset($recipients['user_id'])) {
                $user = $this->fetchDetails('users', ['id' => $recipients['user_id']], ['preferred_language']);
                if (!empty($user) && !empty($user[0]['preferred_language'])) {
                    $language = $user[0]['preferred_language'];
                    // log_message('info', '[EMAIL_NOTIFICATION] Using user language_code: ' . $language . ' for user_id: ' . $recipients['user_id']);
                }
            }

            // For admin custom notifications, always use custom title/message from options
            // Skip template lookup entirely to ensure admin's custom content is used
            // Store logo attachment info for later use
            $adminLogoAttachment = null;

            if ($isAdminCustomNotification) {
                // log_message('info', '[EMAIL_NOTIFICATION] Admin custom notification detected - using custom title/message from options');
                // Use custom title and message directly from options
                $subject = $options['title'] ?? $context['title'] ?? 'Notification';
                $template = $options['message'] ?? $context['message'] ?? '';

                // Get public site URL using templateService method to ensure correct domain
                // This handles various server configurations (proxies, load balancers, etc.)
                $siteUrl = $this->templateService->getPublicSiteUrl();

                // Get company settings for logo and other company information
                $company_settings = get_settings('general_settings', true);
                $company_logo_html = '';

                // Prepare company logo HTML if logo exists
                // This will be used to replace [[company_logo]] placeholder
                // Use fixed CID value: company_logo (constant string, not filename)
                // Also prepare logo attachment info for email attachment
                if (!empty($company_settings['logo'])) {
                    $logoFileName = $company_settings['logo'];
                    $logoPath = "public/uploads/site/" . $logoFileName;

                    // Always set the logo HTML with CID reference
                    // This ensures [[company_logo]] gets replaced even if file not found yet
                    $company_logo_html = '<img src="cid:company_logo" alt="Company Logo">';

                    // Check if file exists (try various path combinations)
                    // FCPATH is /var/www/html/edemand/public/, so the file should be at FCPATH/uploads/site/...
                    $actualLogoPath = null;
                    $pathsToTry = [
                        // Try with the full relative path from FCPATH (same as above, just different construction)
                        FCPATH . 'uploads/site/' . $logoFileName,
                        // Try relative paths (less likely to work but worth trying)
                        $logoPath,
                    ];

                    // Remove duplicates
                    $pathsToTry = array_unique($pathsToTry);

                    foreach ($pathsToTry as $path) {
                        if (file_exists($path)) {
                            $actualLogoPath = $path;
                            break;
                        }
                    }

                    // Store logo attachment info for later use in email sending
                    // CID must be constant: company_logo
                    if ($actualLogoPath) {
                        // Use the actual path that exists
                        $adminLogoAttachment = [
                            'path' => $actualLogoPath,
                            'cid' => 'company_logo'
                        ];
                    }
                }

                // Process template variables for admin custom emails
                // Get user information for variable replacement
                $user_id = $recipients['user_id'] ?? null;
                if ($user_id) {
                    $user = $this->fetchDetails('users', ['id' => $user_id], ['id', 'username', 'email']);
                    if (!empty($user)) {
                        $userData = $user[0];
                        // Process template variables with correct URLs and logo
                        // Use getPublicSiteUrl() instead of base_url() to avoid localhost issues
                        $replacements = [
                            '[[unsubscribe_link]]' => $siteUrl . '/unsubscribe_link/' . unsubscribe_link_user_encrypt($userData['id'], $userData['email']),
                            '[[user_id]]' => $userData['id'],
                            '[[user_name]]' => $userData['username'] ?? '',
                            '[[company_name]]' => getTranslatedSetting('general_settings', 'company_title'),
                            '[[site_url]]' => $siteUrl,
                            '[[company_contact_info]]' => getTranslatedSetting('contact_us', 'contact_us') ?? '',
                            '[[company_logo]]' => $company_logo_html,
                        ];
                        $template = str_replace(array_keys($replacements), array_values($replacements), $template);
                        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
                    }
                } else {
                    // If no user_id, still process common variables
                    $replacements = [
                        '[[company_name]]' => getTranslatedSetting('general_settings', 'company_title'),
                        '[[site_url]]' => $siteUrl,
                        '[[company_contact_info]]' => getTranslatedSetting('contact_us', 'contact_us') ?? '',
                        '[[company_logo]]' => $company_logo_html,
                    ];
                    $template = str_replace(array_keys($replacements), array_values($replacements), $template);
                    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
                }

                // For email, wrap message in basic HTML if it's plain text
                if (strip_tags($template) === $template) {
                    $template = '<html><body>' . nl2br(htmlspecialchars($template)) . '</body></html>';
                }
            } else {
                // For regular notifications, get email template with user's language preference
                // log_message('info', '[EMAIL_NOTIFICATION] Getting email template for event: ' . $eventType . ', language: ' . ($language ?? 'default'));
                $templateData = $this->templateService->getEmailTemplate($eventType, $language);
                if (!$templateData) {
                    // log_message('error', '[EMAIL_NOTIFICATION] Email template not found for event: ' . $eventType);
                    return [
                        'success' => false,
                        'message' => "Email template not found for event: {$eventType}"
                    ];
                }
                // log_message('info', '[EMAIL_NOTIFICATION] Email template found');

                // Extract and replace template variables
                $context['include_logo'] = true; // Include logo for email
                $variables = $this->templateService->extractVariablesFromContext($eventType, $context);
                $template = $this->templateService->replaceVariables($templateData['template'], $variables);
                $subject = $this->templateService->replaceVariables($templateData['subject'], $variables);
            }

            // Handle logo attachment if present
            // For both template-based emails and admin custom notifications
            $logoAttachment = null;
            $bcc = [];
            $cc = [];
            if ($eventType !== 'admin_custom_notification') {
                // For regular template-based emails, get logo from company settings
                // Use fixed CID: company_logo (constant string, not filename)
                $company_settings = get_settings('general_settings', true);
                if (!empty($company_settings['logo'])) {
                    $logoPath = "public/uploads/site/" . $company_settings['logo'];
                    if (file_exists($logoPath)) {
                        $logoAttachment = [
                            'path' => $logoPath,
                            'cid' => 'company_logo'
                        ];
                    }
                }
                // Get BCC and CC from template
                $templateData = $this->templateService->getEmailTemplate($eventType, $language);
                if ($templateData) {
                    $bcc = $this->parseEmailList($templateData['bcc'] ?? '');
                    $cc = $this->parseEmailList($templateData['cc'] ?? '');
                }
            } else {
                // For admin custom notifications, get BCC and CC from options if provided
                if (isset($options['bcc']) && is_array($options['bcc'])) {
                    $bcc = $options['bcc'];
                } elseif (isset($options['bcc']) && is_string($options['bcc'])) {
                    $bcc = $this->parseEmailList($options['bcc']);
                }
                if (isset($options['cc']) && is_array($options['cc'])) {
                    $cc = $options['cc'];
                } elseif (isset($options['cc']) && is_string($options['cc'])) {
                    $cc = $this->parseEmailList($options['cc']);
                }

                // Handle logo attachment for admin custom notifications
                // Use the logo attachment info we prepared earlier when processing the template
                // CID must be constant: company_logo
                // Always pass logo attachment info if it was set, even if file check fails here
                // (the extractAndProcessImages method will handle the file check)
                // Also check if template contains cid:company_logo - if so, we need to ensure logo is attached
                $templateNeedsLogo = (strpos($template, 'cid:company_logo') !== false);

                if (
                    isset($adminLogoAttachment) &&
                    $adminLogoAttachment !== null &&
                    isset($adminLogoAttachment['path'])
                ) {
                    $logoAttachment = $adminLogoAttachment;
                } elseif ($templateNeedsLogo) {
                    // Template needs logo but adminLogoAttachment wasn't set
                    // Try to get logo from settings and create attachment info
                    $company_settings = get_settings('general_settings', true);
                    if (!empty($company_settings['logo'])) {
                        $logoFileName = $company_settings['logo'];
                        // Try to find the actual path
                        // FCPATH is /var/www/html/edemand/public/, so the file should be at FCPATH/uploads/site/...
                        $pathsToTry = [
                            FCPATH . 'uploads/site/' . $logoFileName,
                            'public/uploads/site/' . $logoFileName,
                        ];

                        foreach ($pathsToTry as $path) {
                            if (file_exists($path)) {
                                $logoAttachment = [
                                    'path' => $path,
                                    'cid' => 'company_logo'
                                ];
                                break;
                            }
                        }
                    }
                }
            }

            // Extract and process all images from the template (TinyMCE images + company logo)
            // This will convert image URLs to file paths, attach them as inline attachments,
            // and replace image src attributes with cid: references
            $imageAttachments = $this->extractAndProcessImages($template, $logoAttachment);

            // Send email with all image attachments
            return $this->sendEmail($email, $subject, $template, $bcc, $cc, $imageAttachments);
        } catch (\Throwable $th) {
            $this->writeLog('error', '[EMAIL_NOTIFICATION] Exception trace: ' . $th->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to send email notification: ' . $th->getMessage()
            ];
        }
    }

    /**
     * Send SMS via configured gateway (provider pattern).
     *
     * Delegates to SmsProviderFactory: reads current_sms_gateway from settings,
     * gets the matching provider (e.g. Twilio), and calls send(). For template-based
     * gateways (Fast2SMS, MSG91), pass options with template_id and variables.
     *
     * @param string $phone   Phone number
     * @param string $message SMS message (already cleaned by caller). Empty when using options.
     * @param array  $options Optional. For template-based gateways: template_id, variables.
     * @return array Result array ['success' => bool, 'message' => string, ...]
     */
    private function sendSms(string $phone, string $message, array $options = []): array
    {
        try {
            $provider = (new SmsProviderFactory())->make();
            return $provider->send($phone, $message, $options);
        } catch (\Throwable $th) {
            $this->writeLog('error', '[SMS_SEND] Exception trace: ' . $th->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $th->getMessage(),
            ];
        }
    }

    /**
     * Send email via CodeIgniter Email service
     * 
     * @param string $email Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $bcc BCC recipients
     * @param array $cc CC recipients
     * @param array|null $logoAttachment Logo attachment info
     * @return array Result array
     */
    /**
     * Extract and process all images from HTML template
     * 
     * Extracts all images from the HTML template (both TinyMCE images and company logo),
     * converts URLs to file paths, and prepares them for inline attachment.
     * Also replaces image src attributes with cid: references.
     * 
     * @param string &$template HTML template (passed by reference, will be modified)
     * @param array|null $logoAttachment Optional logo attachment info
     * @return array Array of image attachment info with 'path' and 'cid' keys
     */
    private function extractAndProcessImages(string &$template, ?array $logoAttachment = null): array
    {
        $imageAttachments = [];

        // Extract all image tags from the template
        // This regex matches <img> tags with src attributes (handles both single and double quotes)
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $template, $matches);
        $imageSources = $matches[1] ?? [];

        // Track CID references that need attachments (like company_logo)
        $cidReferencesNeedingAttachments = [];

        // Process each image source
        foreach ($imageSources as $imageSrc) {
            // Check if it's a CID reference
            if (strpos($imageSrc, 'cid:') === 0) {
                $cid = substr($imageSrc, 4); // Remove 'cid:' prefix

                // If this is company_logo, we need to ensure it's attached
                if ($cid === 'company_logo') {
                    $cidReferencesNeedingAttachments['company_logo'] = true;
                }
                continue;
            }

            // Convert image URL/path to file path
            $filePath = $this->convertImageUrlToPath($imageSrc);

            // Only include real, readable, non-empty files (avoids empty attachment parts in email)
            if (empty($filePath) || !is_file($filePath) || !is_readable($filePath) || @filesize($filePath) === 0) {
                continue;
            }

            // Generate CID for this image
            // Use a unique CID based on filename to avoid conflicts
            $filename = basename($filePath);
            $cid = $this->generateImageCID($filename, $filePath);

            // Store attachment info
            $imageAttachments[] = [
                'path' => $filePath,
                'cid' => $cid,
                'original_src' => $imageSrc
            ];

            // Replace image src in template with CID reference
            // Use preg_replace to handle both single and double quotes
            $template = preg_replace(
                '/(<img[^>]+src=["\'])(' . preg_quote($imageSrc, '/') . ')(["\'][^>]*>)/i',
                '$1cid:' . $cid . '$3',
                $template
            );
        }

        // Add company logo only when the template actually references it (e.g. [[company_logo]] or img src="cid:company_logo").
        // Do not attach the logo to every email just because a logo is configured.
        $shouldAddLogo = false;
        $logoPath = null;
        $logoCid = 'company_logo';

        // Only add logo if template contains cid:company_logo reference (placeholder was replaced with this)
        if (isset($cidReferencesNeedingAttachments['company_logo'])) {
            $shouldAddLogo = true;
        }

        // Resolve logo file path only when template needs it
        if ($shouldAddLogo) {
            if ($logoAttachment && isset($logoAttachment['path'])) {
                $logoPath = $logoAttachment['path'];
                $logoCid = $logoAttachment['cid'] ?? 'company_logo';
            } else {
                $company_settings = get_settings('general_settings', true);
                if (!empty($company_settings['logo'])) {
                    $logoPath = "public/uploads/site/" . $company_settings['logo'];
                }
            }
        }

        // Add logo if needed and file exists
        if ($shouldAddLogo && $logoPath) {
            // Check if file exists (try both with and without FCPATH)
            $actualLogoPath = null;
            if (file_exists($logoPath)) {
                $actualLogoPath = $logoPath;
            } elseif (file_exists(FCPATH . $logoPath)) {
                $actualLogoPath = FCPATH . $logoPath;
            } elseif (file_exists(ltrim($logoPath, '/'))) {
                $actualLogoPath = ltrim($logoPath, '/');
            }

            // Only add logo if file is readable and has content (avoids empty attachment in email)
            if ($actualLogoPath && is_readable($actualLogoPath) && @filesize($actualLogoPath) > 0) {
                // Check if logo is already in the attachments list
                $logoAlreadyAdded = false;
                foreach ($imageAttachments as $attachment) {
                    if (
                        isset($attachment['path']) &&
                        ($attachment['path'] === $actualLogoPath ||
                            $attachment['path'] === $logoPath ||
                            realpath($attachment['path'] ?? '') === realpath($actualLogoPath))
                    ) {
                        $logoAlreadyAdded = true;
                        break;
                    }
                }

                if (!$logoAlreadyAdded) {
                    $imageAttachments[] = [
                        'path' => $actualLogoPath,
                        'cid' => $logoCid,
                        'original_src' => 'company_logo'
                    ];
                }
            } else {
                $this->writeLog('warning', '[EMAIL_IMAGES] Logo file not found at: ' . $logoPath . ' (also tried: ' . FCPATH . $logoPath . ')');
            }
        } elseif ($shouldAddLogo && !$logoPath) {
            $this->writeLog('warning', '[EMAIL_IMAGES] Logo needed but no logo path available');
        }

        return $imageAttachments;
    }

    /**
     * Convert image URL to file path
     * 
     * Converts various image URL formats to actual file paths:
     * - Absolute URLs (http://example.com/public/uploads/media/image.jpg)
     * - Relative URLs (/public/uploads/media/image.jpg)
     * - Base URL paths (base_url() + /public/uploads/media/image.jpg)
     * - Direct file paths (public/uploads/media/image.jpg)
     * 
     * @param string $imageUrl Image URL or path
     * @return string|null File path if conversion successful, null otherwise
     */
    private function convertImageUrlToPath(string $imageUrl): ?string
    {
        // Remove query strings and fragments
        $imageUrl = preg_replace('/[?#].*$/', '', $imageUrl);

        // If it's already a relative file path (starts with public/ or /public/)
        if (preg_match('/^(?:\/)?public\//', $imageUrl)) {
            $filePath = ltrim($imageUrl, '/');
            // FCPATH is the public/ folder, so "public/uploads/..." on disk is FCPATH . "uploads/..."
            $pathWithoutPublic = (strpos($filePath, 'public/') === 0) ? substr($filePath, 7) : $filePath;
            $candidates = [
                FCPATH . $pathWithoutPublic, // Correct: public folder + uploads/...
                FCPATH . $filePath,
                $filePath,
            ];
            foreach ($candidates as $c) {
                if ($c !== '' && file_exists($c)) {
                    return $c;
                }
            }
        }

        // If it's an absolute URL, extract the path
        if (preg_match('/^https?:\/\//', $imageUrl)) {
            // Parse URL to get path
            $parsedUrl = parse_url($imageUrl);
            $path = $parsedUrl['path'] ?? '';

            // Remove leading slash and check common upload directories
            $path = ltrim($path, '/');

            // Try common paths (order matters: FCPATH is usually public/, so path "public/uploads/..." needs FCPATH + "uploads/...")
            $possiblePaths = [
                $path, // Relative to CWD
                FCPATH . $path, // FCPATH + full path (e.g. public/public/uploads/... - often wrong)
                FCPATH . ltrim($path, '/'), // Same as above, ensure no leading slash
                'public/' . $path,
                FCPATH . 'public/' . $path,
            ];

            // TinyMCE and base_url() often produce URLs like https://site.com/public/uploads/media/file.jpg.
            // FCPATH points to the public/ folder, so the file on disk is at FCPATH . "uploads/media/file.jpg".
            // We must try path without the leading "public/" when prepending FCPATH.
            if (strpos($path, 'public/') === 0) {
                $pathWithoutPublic = substr($path, 7); // Strip "public/" (7 chars)
                $possiblePaths[] = FCPATH . $pathWithoutPublic;
                $possiblePaths[] = FCPATH . ltrim($pathWithoutPublic, '/');
            }

            foreach ($possiblePaths as $possiblePath) {
                if ($possiblePath !== '' && file_exists($possiblePath)) {
                    return $possiblePath;
                }
            }

            // Try to extract from base_url() pattern
            // If URL contains /public/uploads/, extract that part
            if (preg_match('/(public\/uploads\/[^?#]+)/', $imageUrl, $matches)) {
                $extractedPath = $matches[1];
                $possiblePaths = [
                    FCPATH . $extractedPath,
                    $extractedPath,
                    FCPATH . substr($extractedPath, 7), // FCPATH + "uploads/..." (strip "public/")
                ];

                foreach ($possiblePaths as $possiblePath) {
                    if ($possiblePath !== '' && file_exists($possiblePath)) {
                        return $possiblePath;
                    }
                }
            }
        }

        // If it's a relative path starting with /, try removing the leading slash
        if (strpos($imageUrl, '/') === 0 && strpos($imageUrl, '//') !== 0) {
            $path = ltrim($imageUrl, '/');
            $possiblePaths = [
                FCPATH . $path,
                $path,
                'public/' . $path,
                FCPATH . 'public/' . $path,
            ];
            // FCPATH is public/, so path "public/uploads/..." resolves to FCPATH . "uploads/..."
            if (strpos($path, 'public/') === 0) {
                $possiblePaths[] = FCPATH . substr($path, 7);
            }
            foreach ($possiblePaths as $possiblePath) {
                if ($possiblePath !== '' && file_exists($possiblePath)) {
                    return $possiblePath;
                }
            }
        }

        return null;
    }

    /**
     * Generate Content-ID (CID) for image attachment
     * 
     * Generates a unique CID for inline image attachments.
     * For company logo, uses fixed CID 'company_logo'.
     * For other images, generates a unique CID based on filename.
     * 
     * @param string $filename Image filename
     * @param string $filePath Full file path
     * @return string Content-ID for the image
     */
    private function generateImageCID(string $filename, string $filePath): string
    {
        // Use fixed CID for company logo
        // Check if this is the company logo by checking the path
        if (strpos($filePath, 'uploads/site/') !== false) {
            // This might be the company logo, but we'll use a more specific check
            // If the filename matches common logo patterns, use company_logo CID
            $logoPatterns = ['logo', 'company', 'brand'];
            $filenameLower = strtolower($filename);
            foreach ($logoPatterns as $pattern) {
                if (strpos($filenameLower, $pattern) !== false) {
                    return 'company_logo';
                }
            }
        }

        // For other images, generate a unique CID based on filename
        // Remove file extension and sanitize
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nameWithoutExt);
        $cid = strtolower($sanitized);

        // Ensure CID is not empty
        if (empty($cid)) {
            $cid = 'image_' . uniqid();
        }

        return $cid;
    }

    /**
     * Send one email by delegating to the configured email provider (smtp by default).
     *
     * Body normalization and attachment validation are handled by the provider.
     * The service only orchestrates: get provider and call send with raw body and attachments.
     */
    private function sendEmail(string $email, string $subject, string $body, array $bcc = [], array $cc = [], ?array $imageAttachments = null): array
    {
        try {
            $provider = (new EmailProviderFactory())->make('smtp');
            $result = $provider->send($email, $subject, $body, [
                'bcc'         => $bcc,
                'cc'          => $cc,
                'attachments' => $imageAttachments ?? [],
            ]);

            if (!$result['success']) {
                $this->writeLog('error', '[EMAIL_SEND] ' . ($result['message'] ?? 'Send failed'));
            }
            return $result;
        } catch (\Throwable $th) {
            $this->writeLog('error', '[EMAIL_SEND] Exception: ' . $th->getMessage());
            $this->writeLog('error', '[EMAIL_SEND] Exception trace: ' . $th->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $th->getMessage()
            ];
        }
    }

    /**
     * Check notification preference
     * 
     * Checks if a notification channel is enabled for a specific event type
     * and user preferences.
     * 
     * @param string $eventType Event type
     * @param string $channel Channel (fcm, email, sms)
     * @param int|null $userId User ID
     * @return bool True if notification should be sent
     */
    private function checkNotificationPreference(string $eventType, string $channel, ?int $userId): bool
    {
        // `new_message` chat push should always go through and is not controlled by system toggles.
        if ($eventType === 'new_message' && $channel === 'fcm') {
            return true;
        }

        // Check system-level notification settings
        $notificationSettings = $this->getSettings('notification_settings', true);
        // $this->writeLog('info', '[NOTIFICATION_PREFERENCE] Checking preference for event: ' . $eventType . ', channel: ' . $channel);

        // Map channel names to database setting key suffixes
        // Database stores: eventKey_notification (for FCM), eventKey_email, eventKey_sms
        $channelSuffix = match ($channel) {
            'fcm' => 'notification',
            'email' => 'email',
            'sms' => 'sms',
            default => $channel
        };

        $settingKey = $eventType . '_' . $channelSuffix;
        // $this->writeLog('info', '[NOTIFICATION_PREFERENCE] Setting key: ' . $settingKey);

        // Check if channel is enabled for this event type
        // Default to true for any settings not yet present in the DB (new settings)
        $isEnabled = isset($notificationSettings[$settingKey]) ? filter_var($notificationSettings[$settingKey], FILTER_VALIDATE_BOOLEAN) : true;

        if (!$isEnabled) {
            // $this->writeLog('warning', '[NOTIFICATION_PREFERENCE] Channel ' . $channel . ' is disabled for event ' . $eventType . ' (setting key: ' . $settingKey . ')');
            return false;
        }

        // For email, check user unsubscribe status
        if ($channel === 'email' && $userId) {
            if (!$this->isUnsubscribeEnabled($userId)) {
                return false;
            }
        }

        // For FCM, check if user has notifications enabled
        if ($channel === 'fcm' && $userId) {
            // $user = $this->fetchDetails('users', ['id' => $userId], ['notification']);
            // if (!empty($user) && isset($user[0]['notification']) && $user[0]['notification'] != 1) {
            //     return false;
            // }
            return true;
        }

        return true;
    }

    /**
     * Get phone number from email
     * 
     * @param string $email Email address
     * @return string|null Phone number or null
     */
    private function getPhoneFromEmail(string $email): ?string
    {
        $user = $this->fetchDetails('users', ['email' => $email], ['phone']);
        return !empty($user) && !empty($user[0]['phone']) ? $user[0]['phone'] : null;
    }

    /**
     * Get phone number from user ID
     * 
     * Fetches user phone number from database using user ID.
     * This is used when sending SMS notifications to users identified by user_id
     * (e.g., when using user_groups or user_ids options).
     * 
     * @param int $userId User ID
     * @return string|null Phone number or null if not found
     */
    private function getPhoneFromUserId(int $userId): ?string
    {
        $user = $this->fetchDetails('users', ['id' => $userId], ['phone', 'country_code']);
        $phone = !empty($user) && !empty($user[0]['phone']) ? $user[0]['country_code'] . $user[0]['phone'] : null;
        return $phone;
    }

    /**
     * Get email from user ID
     * 
     * Fetches user email address from database using user ID.
     * This is used when sending notifications to users identified by user_id
     * (e.g., when using user_groups or user_ids options).
     * 
     * @param int $userId User ID
     * @return string|null Email address or null if not found
     */
    private function getEmailFromUserId(int $userId): ?string
    {
        $user = $this->fetchDetails('users', ['id' => $userId], ['email']);
        $email = !empty($user) && !empty($user[0]['email']) ? $user[0]['email'] : null;
        return $email;
    }

    /**
     * Check if user has email notifications enabled
     * 
     * @param int $userId User ID
     * @return bool True if enabled
     */
    private function isUnsubscribeEnabled(int $userId): bool
    {
        $user = $this->fetchDetails('users', ['id' => $userId], ['unsubscribe_email']);
        return !empty($user) && isset($user[0]['unsubscribe_email']) && $user[0]['unsubscribe_email'] == 1;
    }

    /**
     * Clean SMS message
     * 
     * Removes HTML tags and normalizes whitespace for SMS.
     * 
     * @param string $message Raw message
     * @return string Cleaned message
     */
    private function cleanSmsMessage(string $message): string
    {
        // Remove HTML tags
        $message = strip_tags($message);
        // Decode HTML entities
        $message = htmlspecialchars_decode($message);
        $message = html_entity_decode($message);
        // Normalize whitespace
        $message = preg_replace('/\s+/', ' ', $message);
        $message = trim($message);
        return $message;
    }

    /**
     * Parse email list
     * 
     * Parses comma-separated email list into array.
     * 
     * @param string $emailList Comma-separated email list
     * @return array Array of email addresses
     */
    private function parseEmailList(string $emailList): array
    {
        // $this->writeLog('info', '[PARSE_EMAIL_LIST] Parsing email list: ' . $emailList);
        if (empty($emailList)) {
            return [];
        }

        $emails = explode(',', $emailList);
        $emails = array_map('trim', $emails);
        $emails = array_filter($emails);

        return array_values($emails);
    }

    /**
     * Get logo attachment info
     * 
     * @param string $logoHtml Logo HTML with CID
     * @return array|null Attachment info or null
     */
    private function getLogoAttachment(string $logoHtml): ?array
    {
        if (empty($logoHtml) || strpos($logoHtml, 'cid:') === false) {
            return null;
        }

        // Extract CID from HTML
        preg_match('/cid:([^"\']+)/', $logoHtml, $matches);
        if (empty($matches[1])) {
            return null;
        }

        $cid = $matches[1];
        $logoPath = "public/uploads/site/" . $cid;

        if (file_exists($logoPath)) {
            return [
                'path' => $logoPath,
                'cid' => $cid
            ];
        }

        return null;
    }

    /**
     * Get settings from database
     * 
     * @param string $type Settings type
     * @param bool $isJson Whether to return JSON decoded value
     * @return array|string Settings value
     */
    private function getSettings(string $type = 'system_settings', bool $isJson = false): array|string
    {
        $db = \Config\Database::connect();
        $builder = $db->table('settings');
        $res = $builder->select('*')->where('variable', $type)->get()->getResultArray();

        if (!empty($res)) {
            if ($isJson) {
                return json_decode($res[0]['value'], true) ?? [];
            } else {
                return $res[0]['value'];
            }
        }

        return $isJson ? [] : '';
    }

    /**
     * Fetch details from database
     *
     * @param string $table Table name
     * @param array $where Where conditions
     * @param array $select Select fields
     * @return array Result array
     */
    private function fetchDetails(string $table, array $where = [], array $select = ['*']): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table($table);

        if (!empty($select) && !in_array('*', $select)) {
            $builder->select(implode(', ', $select));
        }

        if (!empty($where)) {
            $builder->where($where);
        }

        $result = $builder->get()->getResultArray();

        return $result;
    }

    /**
     * Make cURL request
     *
     * @param string $url URL
     * @param string $method HTTP method
     * @param string $data Request data
     * @param array $headers Request headers
     * @return array Result array with body and http_code
     */
    private function curlRequest(string $url, string $method = 'GET', string $data = '', array $headers = []): array
    {
        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
        ];

        if (!empty($headers)) {
            $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        } else {
            $curlOptions[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
        }

        if (strtolower($method) === 'post') {
            $curlOptions[CURLOPT_POST] = 1;
            $curlOptions[CURLOPT_POSTFIELDS] = $data;
        } else {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($ch, $curlOptions);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        unset($ch);

        return [
            'body' => json_decode($body, true),
            'http_code' => $httpCode,
            'error' => $error
        ];
    }
}
