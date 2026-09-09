<?php

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;

/**
 * SendFcmNotificationJob
 *
 * Handles FCM (push) notifications only. Part of the optimised notification
 * dispatch in queue_notification_service().
 *
 * Why a dedicated job?
 * - Separating FCM from Email/SMS means a slow SMTP send can never delay the
 *   next push notification waiting in the queue.
 * - This job pre-resolves FCM tokens from the database BEFORE calling
 *   NotificationService. FcmProvider already short-circuits when
 *   recipients['fcm_tokens_by_language'] is populated (line 100 of FcmProvider),
 *   so it skips its own DB query entirely. One fewer DB round-trip per job.
 *
 * Token resolution query:
 *   SELECT fcm_id, language_code
 *   FROM   users_fcm_ids
 *   WHERE  user_id IN (...)
 *   AND    status = 1
 *
 * This mirrors exactly what FcmProvider::getFcmTokensByUserIdsGroupedByLanguage()
 * does internally — we just do it earlier so FcmProvider never needs to.
 *
 * Usage: pushed by queue_notification_service() for FCM with >= 100 recipients.
 * Registered in Config/Queue.php as handler key 'sendFcmNotification'.
 */
class SendFcmNotificationJob extends BaseJob implements JobInterface
{
    // -------------------------------------------------------------------------
    // Logging helpers — same pattern used in SendNotificationJob
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

        $logFile  = $logPath . 'log-' . date('Y-m-d') . '.log';
        $entry    = strtoupper($level) . ' - ' . date('Y-m-d H:i:s') . ' --> ' . $message . "\n";
        $result   = @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        if ($result === false && !is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        }
    }

    // -------------------------------------------------------------------------
    // Token resolution
    // -------------------------------------------------------------------------

    /**
     * Query FCM tokens for the given user IDs and group them by language code.
     *
     * The returned structure matches exactly what FcmProvider expects in
     * recipients['fcm_tokens_by_language']:
     *   [ 'en' => ['token1', 'token2'], 'ar' => ['token3'], '' => ['token4'] ]
     *
     * An empty string key means the token has no language set — FcmProvider
     * handles that by falling back to the system default language.
     *
     * @param  int[]       $userIds   User IDs to look up
     * @param  string[]|null $platforms Optional platform filter (android, ios, etc.)
     * @return array<string, string[]>
     */
    private function resolveTokensByLanguage(array $userIds, ?array $platforms = null): array
    {
        if (empty($userIds)) {
            return [];
        }

        try {
            $db      = \Config\Database::connect();
            $builder = $db->table('users_fcm_ids')
                ->select(['fcm_id', 'language_code'])
                ->whereIn('user_id', $userIds)
                ->where('status', 1);

            // Optionally filter by platform (android, ios, admin_panel, etc.).
            if (!empty($platforms)) {
                $builder->whereIn('platform', $platforms);
            }

            $rows = $builder->get()->getResultArray();
        } catch (\Throwable $e) {
            $this->writeLog('error', '[FCM_JOB] Token resolution DB query failed: ' . $e->getMessage());
            return [];
        }

        // Group tokens by language code so FcmProvider can send per-language templates.
        $tokensByLanguage = [];
        foreach ($rows as $row) {
            if (empty($row['fcm_id'])) {
                continue; // Skip rows with no token value.
            }

            // Use empty string when language_code is null/empty — FcmProvider falls
            // back to the system default language for empty-string keys.
            $lang = !empty($row['language_code']) ? $row['language_code'] : '';

            if (!isset($tokensByLanguage[$lang])) {
                $tokensByLanguage[$lang] = [];
            }
            $tokensByLanguage[$lang][] = $row['fcm_id'];
        }

        return $tokensByLanguage;
    }

    // -------------------------------------------------------------------------
    // Job entry point
    // -------------------------------------------------------------------------

    /**
     * Process the FCM notification job.
     *
     * Steps:
     *  1. Extract job data (eventType, recipients, context, options).
     *  2. Resolve FCM tokens from users_fcm_ids into recipients['fcm_tokens_by_language'].
     *  3. Force channels to ['fcm'] — this job never sends email or SMS.
     *  4. Call NotificationService::send(). FcmProvider sees pre-resolved tokens
     *     and skips its own DB query (short-circuit at line 100 of FcmProvider).
     *
     * @return bool true on success, throws on failure so the queue can retry.
     */
    public function process(): bool
    {
        $this->writeLog('info', '[FCM_JOB] Started for event: ' . ($this->data['eventType'] ?? 'unknown'));

        // Validate required data before doing any work.
        if (empty($this->data['eventType'])) {
            throw new \Exception('[FCM_JOB] eventType is required but was not provided in job data.');
        }

        $eventType  = $this->data['eventType'];
        $recipients = $this->data['recipients'] ?? [];
        $context    = $this->data['context']    ?? [];
        $options    = $this->data['options']    ?? [];

        // ------------------------------------------------------------------
        // Pre-resolve FCM tokens so FcmProvider skips its own DB query.
        // FcmProvider checks recipients['fcm_tokens_by_language'] first (line 100)
        // and bails out of all four fallback fetch paths if it is already set.
        // ------------------------------------------------------------------
        $userIds   = $options['user_ids'] ?? [];
        $platforms = $options['platforms'] ?? null;

        // Only resolve if user_ids are present AND tokens not already supplied.
        if (!empty($userIds) && empty($recipients['fcm_tokens_by_language']) && empty($recipients['fcm_tokens'])) {
            $this->writeLog('info', '[FCM_JOB] Resolving tokens for ' . count($userIds) . ' users, event: ' . $eventType);

            $fcmTokensByLanguage = $this->resolveTokensByLanguage($userIds, $platforms);

            if (empty($fcmTokensByLanguage)) {
                // No tokens found — FcmProvider would do the same and return an error.
                // We still call it so it can store the notification in DB (for the
                // in-app notification list) via storeNotificationsInDatabase().
                $this->writeLog('info', '[FCM_JOB] No FCM tokens found for ' . count($userIds) . ' users, event: ' . $eventType . '. Proceeding so DB storage still runs.');
            } else {
                // Inject pre-resolved tokens — FcmProvider will use these directly.
                $recipients['fcm_tokens_by_language'] = $fcmTokensByLanguage;

                $tokenCount = array_sum(array_map('count', $fcmTokensByLanguage));
                $this->writeLog('info', '[FCM_JOB] Pre-resolved ' . $tokenCount . ' tokens across ' . count($fcmTokensByLanguage) . ' language(s) for event: ' . $eventType);
            }
        }

        // Always lock this job to the FCM channel only.
        $options['channels'] = ['fcm'];

        // ------------------------------------------------------------------
        // Dispatch to NotificationService — FcmProvider takes it from here.
        // ------------------------------------------------------------------
        try {
            $notificationService = service('notification');
            $result              = $notificationService->send($eventType, $recipients, $context, $options);

            if ($result['success'] ?? false) {
                $this->writeLog('info', '[FCM_JOB] Completed successfully for event: ' . $eventType);
            } else {
                $this->writeLog('warning', '[FCM_JOB] Completed with issues for event: ' . $eventType . ' — ' . json_encode($result));
            }

            // Queue Progress Tray: update dispatch tracking counters.
            $this->updateDispatchTracking($options, $result);

            return true;
        } catch (\Throwable $th) {
            $this->writeLog('error', '[FCM_JOB] Exception for event: ' . $eventType . ' — ' . $th->getMessage());
            $this->writeLog('error', '[FCM_JOB] Stack trace: ' . $th->getTraceAsString());

            // Queue Progress Tray: record failure before rethrowing.
            $this->updateDispatchTracking($options, ['success' => false]);

            // Re-throw so the queue worker can mark this job as failed and retry.
            throw $th;
        }
    }

    /**
     * Update notification_dispatches counters for this chunk.
     */
    private function updateDispatchTracking(array $options, array $result): void
    {
        $batchId = $options['batch_id'] ?? null;
        if (!$batchId) {
            return;
        }

        try {
            $db = \Config\Database::connect();
            $chunkSize = count($options['user_ids'] ?? []);
            $success   = $result['success'] ?? false;
            $sent      = $success ? $chunkSize : 0;
            $failed    = $chunkSize - $sent;

            $db->query(
                "UPDATE `notification_dispatches`
                 SET `fcm_sent` = `fcm_sent` + ?,
                     `fcm_failed` = `fcm_failed` + ?,
                     `completed_jobs` = `completed_jobs` + 1
                 WHERE `batch_id` = ?",
                [$sent, $failed, $batchId]
            );

            // Check if FCM channel just completed and mark it.
            $row = $db->query(
                "SELECT (`fcm_sent` + `fcm_failed`) AS done, `total_recipients`, `completed_channels`,
                        `completed_jobs`, `total_jobs`, `status`
                 FROM `notification_dispatches` WHERE `batch_id` = ?",
                [$batchId]
            )->getRowArray();

            if ($row) {
                // Mark FCM channel as completed if it reached total_recipients.
                if ((int) $row['done'] >= (int) $row['total_recipients'] && (int) $row['total_recipients'] > 0) {
                    $existing = array_filter(explode(',', $row['completed_channels'] ?? ''));
                    if (!in_array('fcm', $existing, true)) {
                        $existing[] = 'fcm';
                        $db->query(
                            "UPDATE `notification_dispatches` SET `completed_channels` = ? WHERE `batch_id` = ?",
                            [implode(',', $existing), $batchId]
                        );
                    }
                }

                // Mark batch as completed if all jobs are done.
                if ((int) $row['total_jobs'] > 0 && (int) $row['completed_jobs'] >= (int) $row['total_jobs'] && $row['status'] !== 'completed') {
                    $db->query(
                        "UPDATE `notification_dispatches` SET `status` = 'completed', `completed_at` = UTC_TIMESTAMP()
                         WHERE `batch_id` = ? AND `status` != 'completed'",
                        [$batchId]
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->writeLog('error', '[FCM_JOB] Dispatch tracking update failed: ' . $e->getMessage());
        }
    }
}
