<?php

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;
use Exception;

/**
 * SendNotificationJob
 * 
 * This job handles the processing of notifications using NotificationService in the background.
 * It supports all notification channels:
 * - Push notifications (FCM)
 * - Email notifications
 * - SMS notifications
 * 
 * The job receives notification parameters and processes them using NotificationService
 * in a queued manner for better performance. This prevents blocking the main request
 * thread when sending notifications.
 * 
 * Usage:
 * Queue a notification by calling:
 * $queue = service('queue');
 * $queue->push('notifications', 'sendNotification', [
 *     'eventType' => 'booking_status_updated',
 *     'recipients' => ['user_id' => 123],
 *     'context' => ['booking_id' => 456],
 *     'options' => ['channels' => ['fcm', 'email', 'sms']]
 * ], 'default');
 */
class SendNotificationJob extends BaseJob implements JobInterface
{
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
            $logger = service('logger');
            if ($logger !== null) {
                $logger->log($level, $message);
                return; // Success, exit early
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
        // Fallback: try to get from ROOTPATH
        elseif (defined('ROOTPATH')) {
            // Use standard writable directory (without dot)
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
     * Process the notification job
     * 
     * This method handles the actual sending of notifications using NotificationService.
     * It extracts the notification parameters from the job data and calls NotificationService->send().
     */
    public function process()
    {
        $this->writeLog('info', '[SEND_NOTIFICATION_JOB] ===== PROCESS METHOD CALLED =====');
        $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Job started for event: ' . ($this->data['eventType'] ?? 'unknown'));

        try {
            // Validate required data
            if (empty($this->data['eventType'])) {
                throw new Exception('Event type is required for notification job');
            }

            // Extract notification parameters
            $eventType = $this->data['eventType'];
            $recipients = $this->data['recipients'] ?? [];
            $context = $this->data['context'] ?? [];
            $options = $this->data['options'] ?? [];

            $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Processing notification for event: ' . $eventType);
            $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Recipients: ' . json_encode($recipients));
            $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Context: ' . json_encode($context));
            $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Options: ' . json_encode($options));

            // Initialize NotificationService
            $notificationService = service('notification');

            // Send notification using NotificationService
            $result = $notificationService->send($eventType, $recipients, $context, $options);

            // Log the result
            if ($result['success'] ?? false) {
                $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Notification sent successfully for event: ' . $eventType);
                $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Result: ' . json_encode($result));
            } else {
                $this->writeLog('warning', '[SEND_NOTIFICATION_JOB] Notification failed for event: ' . $eventType);
                $this->writeLog('warning', '[SEND_NOTIFICATION_JOB] Result: ' . json_encode($result));
            }

            $this->writeLog('info', '[SEND_NOTIFICATION_JOB] Job completed for event: ' . $eventType);
            return true;
        } catch (\Throwable $th) {
            $this->writeLog('error', '[SEND_NOTIFICATION_JOB] Error processing notification job: ' . $th->getMessage());
            $this->writeLog('error', '[SEND_NOTIFICATION_JOB] Stack trace: ' . $th->getTraceAsString());
            throw $th;
        }
    }
}
