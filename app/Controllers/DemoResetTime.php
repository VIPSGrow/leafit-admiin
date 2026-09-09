<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * DemoResetTime Controller
 *
 * Exposes GET /get-demo-reset-time as a lightweight JSON endpoint.
 * Returns the number of seconds remaining until the next automatic demo reset.
 *
 * The calculation mirrors DemoAutoReset::RESET_INTERVAL_SECONDS (7200 s = 2 h)
 * and the query used in demo_reset_timer.php so the timer always agrees with
 * what the cron job will actually do.
 *
 * Response shape:
 *   { "remaining": <int> }
 *
 * "remaining" is always between 0 and 7200 inclusive.
 */
class DemoResetTime extends Controller
{
    public function index()
    {
        // Only available in demo mode
        if (!defined('ALLOW_MODIFICATION') || ALLOW_MODIFICATION != 0) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'Not in demo mode']);
        }

        $cfg      = config('DemoReset');
        $interval = (int) $cfg->intervalSeconds;

        $now    = time();
        $offset = (int) date('Z');
        $db     = \Config\Database::connect();

        // Wall-clock aligned next cron boundary (matches DemoAutoResetJob::computeDelaySeconds).
        $scheduledRem = $interval - (($now + $offset) % $interval);
        $nextBoundary = $now + $scheduledRem;

        // If a reset just finished within the buffer window, the next cron tick will be
        // skipped (recentlyFinished check) — actual next reset is the boundary after.
        $lastFinishedRow = $db->table('demo_reset_logs')
            ->where('status', 'success')
            ->where('finished_at IS NOT NULL')
            ->orderBy('finished_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        $buffer = (int) $cfg->recentFinishBufferSeconds;
        if ($lastFinishedRow && !empty($lastFinishedRow->finished_at)) {
            $lastFinishedAt = strtotime($lastFinishedRow->finished_at . ' UTC');
            if ($lastFinishedAt && ($nextBoundary - $lastFinishedAt) < $buffer) {
                $nextBoundary += $interval;
            }
        }

        $remaining = max(0, $nextBoundary - $now);
        $remaining = min($remaining, $interval);

        $runningLog = $db->table('demo_reset_logs')
            ->where('status', 'running')
            ->orderBy('started_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        $status = $runningLog ? 'running' : 'counting';

        return $this->response->setJSON([
            'remaining' => (int) $remaining,
            'status' => $status
        ]);
    }
}
