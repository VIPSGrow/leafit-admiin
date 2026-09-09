<?php

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;

/**
 * Self-rescheduling demo reset job.
 *
 * Runs on a fixed wall-clock boundary (config('DemoReset')->intervalSeconds).
 * Re-enqueues the next run BEFORE doing work so a crash mid-reset cannot break the chain.
 *
 * Worker should run with --max-jobs=1 so the PHP process exits after each
 * run; supervisor respawns it. This makes memory leaks impossible by construction.
 */
class DemoAutoResetJob extends BaseJob implements JobInterface
{
    public function process()
    {
        $this->log('info', '[DEMO_RESET_JOB] ===== process() start =====');

        try {
            $this->scheduleNext();
        } catch (\Throwable $e) {
            $this->log('error', '[DEMO_RESET_JOB] scheduleNext FAILED: ' . $e->getMessage());
        }

        $cfg       = config('DemoReset');
        $db        = \Config\Database::connect();
        $startTime = microtime(true);
        $startedAt = gmdate('Y-m-d H:i:s');

        $this->cleanupStaleLocks($db, $cfg->staleLockSeconds);

        if ($this->isAnotherRunning($db)) {
            $this->log('warning', '[DEMO_RESET_JOB] Another reset already running. Skip.');
            return true;
        }

        if ($this->recentlyFinished($db, $cfg->recentFinishBufferSeconds)) {
            $this->log('info', "[DEMO_RESET_JOB] Last reset finished <{$cfg->recentFinishBufferSeconds}s ago. Skip.");
            return true;
        }

        $this->log('info', '[DEMO_RESET_JOB] Triggering demo:reset at ' . $startedAt);

        try {
            command('demo:reset');
            $duration = (int) round(microtime(true) - $startTime);
            $this->log('info', "[DEMO_RESET_JOB] demo:reset finished in {$duration}s");
        } catch (\Throwable $e) {
            $this->log('error', '[DEMO_RESET_JOB] demo:reset FAILED: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $this->log('info', '[DEMO_RESET_JOB] ===== process() end =====');
        return true;
    }

    /**
     * Push the next run aligned to the next interval boundary.
     * Idempotent — checks queue table to avoid duplicate scheduling.
     */
    private function scheduleNext(): void
    {
        $cfg     = config('DemoReset');
        $db      = \Config\Database::connect();
        $pending = $db->table('queue_jobs')
            ->where('queue', $cfg->queueName)
            ->where('status', 0)
            ->countAllResults();

        if ($pending > 0) {
            $this->log('info', "[DEMO_RESET_JOB] Pending job already queued ({$pending}). Skip reschedule.");
            return;
        }

        $delay = self::computeDelaySeconds();

        service('queue')
            ->setDelay($delay)
            ->push($cfg->queueName, $cfg->jobHandler, ['scheduled_at' => gmdate('Y-m-d H:i:s')]);

        $this->log('info', "[DEMO_RESET_JOB] Next run scheduled in {$delay}s");
    }

    public static function computeDelaySeconds(): int
    {
        $interval = (int) config('DemoReset')->intervalSeconds;
        $now      = time();
        $offset   = (int) date('Z');
        $delay    = $interval - (($now + $offset) % $interval);
        return max(60, min($delay, $interval));
    }

    private function cleanupStaleLocks($db, int $staleSeconds): void
    {
        $staleTime = gmdate('Y-m-d H:i:s', time() - $staleSeconds);
        $db->table('demo_reset_logs')
            ->where('status', 'running')
            ->where('started_at <', $staleTime)
            ->update(['status' => 'failed', 'message' => 'Stale reset cleared by queue watchdog']);

        $cleared = $db->affectedRows();
        if ($cleared > 0) {
            $this->log('warning', "[DEMO_RESET_JOB] Cleared {$cleared} stale running log(s)");
        }
    }

    private function isAnotherRunning($db): bool
    {
        return (bool) $db->table('demo_reset_logs')->where('status', 'running')->countAllResults();
    }

    private function recentlyFinished($db, int $bufferSeconds): bool
    {
        $last = $db->table('demo_reset_logs')->orderBy('id', 'DESC')->limit(1)->get()->getRow();
        if (!$last || empty($last->finished_at)) {
            return false;
        }
        return (time() - strtotime($last->finished_at . ' UTC')) < $bufferSeconds;
    }

    private function log(string $level, string $message): void
    {
        try {
            $logger = service('logger');
            if ($logger !== null) {
                $logger->log($level, $message);
            }
        } catch (\Throwable $e) {
            // fall through to file
        }

        $logPath = (defined('WRITEPATH') ? rtrim(WRITEPATH, '/') : getcwd() . '/writable') . '/logs/';
        if (!is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
        }
        $file = $logPath . 'demo-reset-' . date('Y-m-d') . '.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ' ' . $message . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
