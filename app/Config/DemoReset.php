<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Single source of truth for the demo auto-reset schedule.
 *
 * Used by:
 *   - App\Jobs\DemoAutoResetJob          (queue self-reschedule + buffer)
 *   - App\Commands\DemoQueueBootstrap    (watchdog stale-detection threshold)
 *   - App\Controllers\DemoResetTime      (countdown JSON endpoint)
 *   - app/Views/backend/partials/demo_reset_timer.php (UI badge)
 *
 * Change the interval in ONE place — all four read from here.
 */
class DemoReset extends BaseConfig
{
    /**
     * Interval between resets, in seconds.
     *
     * Must divide 86400 evenly to stay aligned with local midnight.
     * Safe values: 3600, 7200, 10800, 14400, 21600, 43200, 86400.
     */
    public int $intervalSeconds = 7200;

    /**
     * Skip-window after a recent reset finish — prevents back-to-back runs
     * if a job fires while the previous reset just completed.
     */
    public int $recentFinishBufferSeconds = 300;

    /**
     * A "running" log row older than this is considered crashed and gets
     * marked failed by both the job startup and the watchdog cron.
     */
    public int $staleLockSeconds = 1800;

    /**
     * Queue name for the demo reset job (matches what the worker consumes).
     */
    public string $queueName = 'demo_reset';

    /**
     * Job handler key registered in Config\Queue::$jobHandlers.
     */
    public string $jobHandler = 'demoAutoReset';
}
