<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProviderLeavesTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('provider_leaves')) {
            $db->query("
                CREATE TABLE `provider_leaves` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT(11) UNSIGNED NOT NULL,
                    `leave_date` DATE NOT NULL,
                    `day` ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
                    `shift_number` TINYINT UNSIGNED NOT NULL COMMENT 'Maps to provider_shifts.shift_number for the same day',
                    `opening_time` TIME NOT NULL COMMENT 'Snapshot of shift opening_time at the time of leave creation',
                    `closing_time` TIME NOT NULL COMMENT 'Snapshot of shift closing_time at the time of leave creation',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_leave` (`partner_id`, `leave_date`, `shift_number`),
                    KEY `idx_partner_date` (`partner_id`, `leave_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Shift-based provider leaves. Full-day leave = all shifts of that day marked.'
            ");

            if ($db->tableExists('users')) {
                try {
                    $db->query("
                        ALTER TABLE `provider_leaves`
                        ADD CONSTRAINT `fk_provider_leaves_partner`
                        FOREIGN KEY (`partner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('provider_leaves')) {
            try {
                $db->query("ALTER TABLE `provider_leaves` DROP FOREIGN KEY `fk_provider_leaves_partner`");
            } catch (\Throwable $e) {
            }
        }

        $db->query("DROP TABLE IF EXISTS `provider_leaves`");
    }
}
