<?php

namespace App\Commands;

use App\Jobs\DemoAutoResetJob;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Bootstrap + watchdog for the demo reset queue chain.
 *
 * Two modes:
 *   php spark demo:queue-bootstrap          # idempotent first push
 *   php spark demo:queue-bootstrap --force  # always push (ignores existing pending)
 *
 * Recommended cron (every 10 min) acts as a watchdog — if the queue chain has
 * broken (worker dead, no pending job, last reset >interval+buffer ago), it re-bootstraps.
 *
 * No persistent worker needed. Every cron run spawns a short-lived worker (--stop-when-empty)
 * that exits in <1s if no jobs are available yet, or processes the job immediately if one is
 * ready. When delay=0 (stall/force), the job is instantly available so the worker runs it
 * right away. When delay>0, successive cron runs keep spawning workers until available_at
 * arrives and one finally picks it up.
 */
class DemoQueueBootstrap extends BaseCommand
{
    protected $group       = 'Demo';
    protected $name        = 'demo:queue-bootstrap';
    protected $description = 'Push initial demo reset job + watchdog re-bootstrap if chain broken.';
    protected $usage       = 'demo:queue-bootstrap [--force]';
    protected $options     = ['--force' => 'Push even if a pending job already exists.'];

    public function run(array $params)
    {
        $cfg   = config('DemoReset');
        $db    = \Config\Database::connect();
        $force = isset($params['force']) || CLI::getOption('force');

        $staleTime = gmdate('Y-m-d H:i:s', time() - $cfg->staleLockSeconds);
        $db->table('demo_reset_logs')
            ->where('status', 'running')
            ->where('started_at <', $staleTime)
            ->update(['status' => 'failed', 'message' => 'Stale reset cleared by bootstrap watchdog']);

        $pending = $db->table('queue_jobs')
            ->where('queue', $cfg->queueName)
            ->where('status', 0)
            ->countAllResults();

        $stalled = false;

        if ($pending > 0 && !$force) {
            $last = $db->table('demo_reset_logs')->orderBy('id', 'DESC')->limit(1)->get()->getRow();
            $lastFinished = $last && !empty($last->finished_at)
                ? (time() - strtotime($last->finished_at . ' UTC'))
                : null;

            $stallThreshold = $cfg->intervalSeconds + $cfg->recentFinishBufferSeconds;

            if ($lastFinished !== null && $lastFinished > $stallThreshold) {
                CLI::write("Pending job exists but last reset was {$lastFinished}s ago (chain stalled). Forcing.", 'yellow');
                $stalled = true;
            } else {
                CLI::write("Pending job already queued ({$pending}). Skip push. Spawning worker in case job is now available.", 'white');
                $this->spawnWorker($cfg->queueName);
                return;
            }
        }

        // Clear accumulated stale pending jobs before pushing a fresh one.
        if ($stalled || $force) {
            $cleared = $db->table('queue_jobs')
                ->where('queue', $cfg->queueName)
                ->where('status', 0)
                ->delete();
            if ($cleared) {
                CLI::write("Cleared {$pending} stale pending job(s).", 'yellow');
                log_message('warning', "[DEMO_RESET_BOOTSTRAP] Cleared {$pending} stale pending job(s).");
            }
        }

        // On stall or force, run immediately (delay=0). Otherwise align to next boundary.
        $delay = ($stalled || $force) ? 0 : DemoAutoResetJob::computeDelaySeconds();

        try {
            service('queue')
                ->setDelay($delay)
                ->push($cfg->queueName, $cfg->jobHandler, [
                    'scheduled_at' => gmdate('Y-m-d H:i:s'),
                    'source'       => ($stalled ? 'stall-recovery' : ($force ? 'force' : 'bootstrap')),
                ]);

            $runAt = $delay > 0 ? gmdate('Y-m-d H:i:s', time() + $delay) . ' UTC' : 'immediately';
            CLI::write("Queued. delay={$delay}s run={$runAt}", 'green');

            log_message('info', "[DEMO_RESET_BOOTSTRAP] Queued reset. delay={$delay}s force=" . ($force ? '1' : '0') . " stalled=" . ($stalled ? '1' : '0'));

            $this->spawnWorker($cfg->queueName);
        } catch (\Throwable $e) {
            CLI::error('Failed to queue: ' . $e->getMessage());
            log_message('error', '[DEMO_RESET_BOOTSTRAP] Failed: ' . $e->getMessage());
        }
    }

    /**
     * Spawn a short-lived queue worker in the background.
     *
     * Uses --stop-when-empty so the process exits once no jobs are available.
     * When delay=0 the job is immediately available and the worker processes it right away.
     * When delay>0 the worker exits instantly; the next cron run's worker will catch the job.
     */
    private function spawnWorker(string $queueName): void
    {
        $php   = PHP_BINARY;
        $spark = ROOTPATH . 'spark';
        $cmd   = escapeshellarg($php) . ' ' . escapeshellarg($spark)
               . ' queue:work ' . escapeshellarg($queueName)
               . ' --stop-when-empty';

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B ' . $cmd . ' > NUL 2>&1', 'r'));
        } else {
            exec('nohup ' . $cmd . ' > /dev/null 2>&1 &');
        }

        CLI::write("Worker spawned for queue: {$queueName}", 'cyan');
        log_message('info', "[DEMO_RESET_BOOTSTRAP] Worker spawned for queue: {$queueName}");
    }
}
