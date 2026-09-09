<?php

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;

/**
 * SendEmailSmsNotificationJob
 *
 * Handles Email and SMS notifications only. Part of the optimised notification
 * dispatch in queue_notification_service().
 *
 * Why a dedicated job?
 * - Separating Email/SMS from FCM ensures that slow SMTP sends never delay
 *   push notifications sitting behind them in the queue.
 * - The job is always queued (never sent directly) because Email and SMS are
 *   inherently slower channels with no strict latency requirement.
 * - The caller decides which subset of ['email', 'sms'] to include by setting
 *   $options['channels'] when pushing the job. This job enforces that only
 *   email/sms values are ever active — FCM is always stripped out.
 *
 * Chunk size: queue_notification_service() defaults to 20 users/chunk for
 * Email/SMS (vs 25 for FCM), giving more granular parallelism across workers.
 *
 * Usage: pushed by queue_notification_service() for all Email/SMS delivery.
 * Registered in Config/Queue.php as handler key 'sendEmailSmsNotification'.
 */
class SendEmailSmsNotificationJob extends BaseJob implements JobInterface
{
    // -------------------------------------------------------------------------
    // Logging helpers — same pattern used in SendNotificationJob / SendFcmNotificationJob
    // -------------------------------------------------------------------------

    /**
     * Write a log entry. Tries the logger service first; falls back to a direct
     * file write so logs always land even in CLI queue-worker context.
     */
    private function writeLog(string $level, string $message): void
    {
        try {
            $logger = service('logger');
            if ($logger !== null) {
                $logger->log($level, $message);
                return;
            }
        } catch (\Throwable $e) {
            // Logger service unavailable — fall through to file write.
        }

        $this->writeLogToFile($level, $message);
    }

    /**
     * Direct file write fallback for CLI workers where the logger service may
     * not be fully initialised.
     */
    private function writeLogToFile(string $level, string $message): void
    {
        // Determine the writable/logs directory using available constants.
        if (defined('WRITEPATH') && !empty(WRITEPATH)) {
            $logPath = rtrim(WRITEPATH, '/') . '/logs/';
        } elseif (defined('ROOTPATH')) {
            $logPath = rtrim(ROOTPATH, '/') . '/writable/logs/';
        } else {
            $logPath = getcwd() . '/writable/logs/';
        }

        if (!is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
        }

        $logFile = $logPath . 'log-' . date('Y-m-d') . '.log';
        $entry   = strtoupper($level) . ' - ' . date('Y-m-d H:i:s') . ' --> ' . $message . "\n";
        $result  = @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        if ($result === false && !is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        }
    }

    // -------------------------------------------------------------------------
    // Job entry point
    // -------------------------------------------------------------------------

    /**
     * Process the Email/SMS notification job.
     *
     * Steps:
     *  1. Extract job data (eventType, recipients, context, options).
     *  2. Determine which of ['email', 'sms'] were requested for this job.
     *  3. Strip any 'fcm' channel that may have slipped in (safety guard).
     *  4. Call NotificationService::send() with the sanitised channel list.
     *
     * @return bool true on success, throws on failure so the queue can retry.
     */
    public function process(): bool
    {
        $this->writeLog('info', '[EMAIL_SMS_JOB] Started for event: ' . ($this->data['eventType'] ?? 'unknown'));

        // Validate required data before doing any work.
        if (empty($this->data['eventType'])) {
            throw new \Exception('[EMAIL_SMS_JOB] eventType is required but was not provided in job data.');
        }

        $eventType  = $this->data['eventType'];
        $recipients = $this->data['recipients'] ?? [];
        $context    = $this->data['context']    ?? [];
        $options    = $this->data['options']    ?? [];

        // ------------------------------------------------------------------
        // Determine which email/sms channels are active for this job.
        // The caller (queue_notification_service) sets channels to a subset
        // of ['email', 'sms'] when pushing the job. We honour that subset.
        // FCM is always stripped as a safety guard — this job must never
        // trigger push notifications.
        // ------------------------------------------------------------------
        $requestedChannels  = $options['channels'] ?? ['email', 'sms'];
        $allowedChannels    = ['email', 'sms']; // FCM is never allowed here
        $activeChannels     = array_values(array_intersect($requestedChannels, $allowedChannels));

        if (empty($activeChannels)) {
            // Nothing to do — log and exit cleanly rather than throwing.
            $this->writeLog('warning', '[EMAIL_SMS_JOB] No email/sms channels active for event: ' . $eventType . '. Skipping.');
            return true;
        }

        // Lock options to the sanitised channel list.
        $options['channels'] = $activeChannels;

        $this->writeLog('info', '[EMAIL_SMS_JOB] Channels: [' . implode(', ', $activeChannels) . '] for event: ' . $eventType);
        $this->writeLog('info', '[EMAIL_SMS_JOB] User IDs count: ' . count($options['user_ids'] ?? []));

        // ------------------------------------------------------------------
        // Dispatch per-user so the queue tray can show real-time progress.
        // Each send updates the dispatch counter immediately, and the tray
        // picks it up on the next 3-second poll — even mid-job.
        // ------------------------------------------------------------------
        $userIds  = $options['user_ids'] ?? [];
        $batchId  = $options['batch_id'] ?? null;

        try {
            $notificationService = service('notification');

            foreach ($userIds as $userId) {
                $perUserOptions             = $options;
                $perUserOptions['user_ids'] = [$userId];

                try {
                    $result = $notificationService->send($eventType, $recipients, $context, $perUserOptions);

                    // Extract per-channel success from the nested result.
                    $channelResults = $result['results'] ?? [];
                    foreach ($activeChannels as $ch) {
                        $chResult  = $channelResults[$ch] ?? [];
                        $succeeded = ($chResult['success'] ?? false) || (($chResult['success_count'] ?? 0) > 0);
                        $this->incrementChannelCounter($batchId, $ch, $succeeded);
                    }
                } catch (\Throwable $e) {
                    $this->writeLog('error', '[EMAIL_SMS_JOB] Failed for user ' . $userId . ': ' . $e->getMessage());
                    foreach ($activeChannels as $ch) {
                        $this->incrementChannelCounter($batchId, $ch, false);
                    }
                }
            }

            $this->writeLog('info', '[EMAIL_SMS_JOB] Completed for event: ' . $eventType . ' (' . count($userIds) . ' users)');

            // Mark this job as done and check for batch completion.
            $this->completeJob($batchId);

            return true;
        } catch (\Throwable $th) {
            $this->writeLog('error', '[EMAIL_SMS_JOB] Exception for event: ' . $eventType . ' — ' . $th->getMessage());
            $this->writeLog('error', '[EMAIL_SMS_JOB] Stack trace: ' . $th->getTraceAsString());

            $this->completeJob($batchId);

            throw $th;
        }
    }

    /**
     * Increment a single channel counter by 1 (sent or failed) for real-time
     * per-user progress. When the channel reaches total_recipients, it is
     * recorded in `completed_channels` so the frontend can reliably fire toasts
     * without depending on poll timing.
     */
    private function incrementChannelCounter(?string $batchId, string $channel, bool $success): void
    {
        if (!$batchId) {
            return;
        }

        $col = '`' . $channel . ($success ? '_sent' : '_failed') . '`';

        try {
            $db = \Config\Database::connect();

            $db->query(
                "UPDATE `notification_dispatches` SET {$col} = {$col} + 1 WHERE `batch_id` = ?",
                [$batchId]
            );

            // Check if this channel just reached total_recipients and mark it done.
            $row = $db->query(
                "SELECT (`{$channel}_sent` + `{$channel}_failed`) AS done, `total_recipients`, `completed_channels`
                 FROM `notification_dispatches` WHERE `batch_id` = ?",
                [$batchId]
            )->getRowArray();

            if ($row && (int) $row['done'] >= (int) $row['total_recipients'] && (int) $row['total_recipients'] > 0) {
                $existing = array_filter(explode(',', $row['completed_channels'] ?? ''));
                if (!in_array($channel, $existing, true)) {
                    $existing[] = $channel;
                    $db->query(
                        "UPDATE `notification_dispatches` SET `completed_channels` = ? WHERE `batch_id` = ?",
                        [implode(',', $existing), $batchId]
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->writeLog('error', '[EMAIL_SMS_JOB] Counter increment failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark this job as done and transition batch to 'completed' if all jobs finished.
     */
    private function completeJob(?string $batchId): void
    {
        if (!$batchId) {
            return;
        }

        try {
            $db = \Config\Database::connect();

            $db->query(
                "UPDATE `notification_dispatches` SET `completed_jobs` = `completed_jobs` + 1 WHERE `batch_id` = ?",
                [$batchId]
            );

            $row = $db->query(
                "SELECT `completed_jobs`, `total_jobs`, `status` FROM `notification_dispatches` WHERE `batch_id` = ?",
                [$batchId]
            )->getRowArray();

            if ($row && (int) $row['total_jobs'] > 0 && (int) $row['completed_jobs'] >= (int) $row['total_jobs'] && $row['status'] !== 'completed') {
                $db->query(
                    "UPDATE `notification_dispatches` SET `status` = 'completed', `completed_at` = UTC_TIMESTAMP()
                     WHERE `batch_id` = ? AND `status` != 'completed'",
                    [$batchId]
                );
            }
        } catch (\Throwable $e) {
            $this->writeLog('error', '[EMAIL_SMS_JOB] Job completion tracking failed: ' . $e->getMessage());
        }
    }
}
