<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Simulates notification dispatch progress for testing the Queue Progress Tray UI.
 *
 * Usage: php spark queue:simulate
 *
 * Inserts a fake batch into notification_dispatches and incrementally
 * updates counters every 2 seconds to simulate real queue processing.
 * The admin panel tray (polling every 3s) will pick up the changes live.
 */
class SimulateQueueTray extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'queue:simulate';
    protected $description = 'Simulate notification queue progress for tray UI testing.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        // Clean up any previous simulation rows.
        $db->table('notification_dispatches')->where('batch_id', 'sim-batch-001')->delete();
        $db->table('notification_dispatches')->where('batch_id', 'sim-batch-002')->delete();

        CLI::write('=== Queue Tray Simulation ===', 'green');
        CLI::write('Open the admin panel in your browser and watch the bottom of the screen.');
        CLI::newLine();

        // --- Batch 1: Large campaign ---
        $batch1 = [
            'batch_id'         => 'sim-batch-001',
            'title'            => 'Holiday Sale Promo',
            'event_type'       => 'admin_custom_notification',
            'total_recipients' => 1200,
            'total_jobs'       => 48,
            'completed_jobs'   => 0,
            'fcm_sent'         => 0,
            'fcm_failed'       => 0,
            'email_sent'       => 0,
            'email_failed'     => 0,
            'sms_sent'         => 0,
            'sms_failed'       => 0,
            'status'           => 'processing',
        ];

        $db->table('notification_dispatches')->insert($batch1);
        CLI::write('[0s] Batch 1 created: "Holiday Sale Promo" — 1,200 recipients, 48 jobs', 'yellow');

        sleep(3);

        // --- Batch 2: Smaller notification ---
        $batch2 = [
            'batch_id'         => 'sim-batch-002',
            'title'            => 'New Provider Welcome',
            'event_type'       => 'admin_custom_notification',
            'total_recipients' => 200,
            'total_jobs'       => 10,
            'completed_jobs'   => 0,
            'fcm_sent'         => 0,
            'fcm_failed'       => 0,
            'email_sent'       => 0,
            'email_failed'     => 0,
            'sms_sent'         => 0,
            'sms_failed'       => 0,
            'status'           => 'processing',
        ];

        $db->table('notification_dispatches')->insert($batch2);
        CLI::write('[3s] Batch 2 created: "New Provider Welcome" — 200 recipients, 10 jobs', 'yellow');

        // --- Simulate progress in steps ---
        $steps = [
            // [sleep, batch_id, data]
            [2, 'sim-batch-001', ['completed_jobs' => 10, 'fcm_sent' => 250, 'email_sent' => 50]],
            [2, 'sim-batch-002', ['completed_jobs' => 3,  'fcm_sent' => 60,  'email_sent' => 20]],
            [2, 'sim-batch-001', ['completed_jobs' => 20, 'fcm_sent' => 500, 'email_sent' => 150]],
            [2, 'sim-batch-002', ['completed_jobs' => 7,  'fcm_sent' => 140, 'email_sent' => 60]],
            [2, 'sim-batch-001', ['completed_jobs' => 30, 'fcm_sent' => 750, 'fcm_failed' => 10, 'email_sent' => 300, 'email_failed' => 5]],
            [2, 'sim-batch-002', ['completed_jobs' => 10, 'fcm_sent' => 195, 'fcm_failed' => 5, 'email_sent' => 190, 'email_failed' => 10, 'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]],
            [3, 'sim-batch-001', ['completed_jobs' => 40, 'fcm_sent' => 1000, 'fcm_failed' => 15, 'email_sent' => 600, 'email_failed' => 10]],
            [3, 'sim-batch-001', ['completed_jobs' => 48, 'fcm_sent' => 1170, 'fcm_failed' => 30, 'email_sent' => 1150, 'email_failed' => 50, 'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]],
        ];

        $elapsed = 5; // already 3s + 2s from batch creation
        foreach ($steps as $step) {
            sleep($step[0]);
            $elapsed += $step[0];

            $batchId = $step[1];
            $data    = $step[2];

            $db->table('notification_dispatches')
                ->where('batch_id', $batchId)
                ->update($data);

            $label = $batchId === 'sim-batch-001' ? 'Holiday Sale' : 'Provider Welcome';
            $jobs  = $data['completed_jobs'];
            $status = $data['status'] ?? 'processing';

            if ($status === 'completed') {
                CLI::write("[{$elapsed}s] {$label}: COMPLETED", 'green');
            } else {
                CLI::write("[{$elapsed}s] {$label}: {$jobs} jobs done — FCM:{$data['fcm_sent']} Email:{$data['email_sent']}", 'cyan');
            }
        }

        CLI::newLine();
        CLI::write('Simulation complete. Tray should auto-hide in ~5 seconds.', 'green');
        CLI::write('Run again anytime with: php spark queue:simulate', 'light_gray');
    }
}
