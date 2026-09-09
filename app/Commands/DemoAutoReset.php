<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Demo Auto Reset Command (Heartbeat / Scheduler)
 *
 *
 * Logic:
 *   1. Calculate if a reset has already occurred in the current 2-hour window.
 *   2. If not, trigger the reset.
 */
class DemoAutoReset extends BaseCommand
{
    protected $group = 'Demo';
    protected $name = 'demo:auto-reset';
    protected $description = 'Triggers a partial demo reset on a fixed 2-hour schedule.';
    protected $usage = 'demo:auto-reset';

    /**
     * Resets happen at 0, 2, 4... hours from midnight.
     */
    private const RESET_INTERVAL_SECONDS = 7200;

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        // --- STALE LOCK CLEANUP ---
        // If a reset crashed, it stays 'running' forever. 
        // We clear any entry that's been 'running' for more than 30 mins.
        $staleTime = gmdate('Y-m-d H:i:s', time() - 1800);
        $db->table('demo_reset_logs')
            ->where('status', 'running')
            ->where('started_at <', $staleTime)
            ->update(['status' => 'failed', 'message' => 'Stale reset cleared by watchdog']);

        // Check if one is currently running
        $running = $db->table('demo_reset_logs')
            ->where('status', 'running')
            ->get()
            ->getRow();

        if ($running) {
            CLI::write("Another reset is already running (ID: {$running->id}, Started: {$running->started_at}). Skipping.", 'light_red');
            return;
        }

        // Buffer: prevent manual runs if one finished in the last 5 mins
        $lastReset = $db->table('demo_reset_logs')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        if ($lastReset && !empty($lastReset->finished_at)) {
            $elapsed = time() - strtotime($lastReset->finished_at . ' UTC');
            if ($elapsed < 300) {
                CLI::write("A reset just finished recently ({$elapsed}s ago). Use --force if needed (not implemented). skipping.", 'white');
                return;
            }
        }

        // Trigger the partial reset
        CLI::write('Triggering partial demo reset (demo:reset)...', 'green');

        // Using the command() helper is the most reliable way to trigger 
        // another spark command from within a command.
        try {
            command('demo:reset');
        } catch (\Exception $e) {
            CLI::error('Failed to trigger demo:reset: ' . $e->getMessage());
        }
    }
}
