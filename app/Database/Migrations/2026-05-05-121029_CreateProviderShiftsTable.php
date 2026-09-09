<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProviderShiftsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('provider_shifts')) {
            $db->query("
                CREATE TABLE `provider_shifts` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT(11) UNSIGNED NOT NULL,
                    `day` ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
                    `shift_number` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    `opening_time` TIME NOT NULL,
                    `closing_time` TIME NOT NULL,
                    `is_open` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_shift` (`partner_id`, `day`, `shift_number`),
                    KEY `idx_partner_day` (`partner_id`, `day`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Provider working shifts (multi-shift per day support).'
            ");

            if ($db->tableExists('users')) {
                try {
                    $db->query("
                        ALTER TABLE `provider_shifts`
                        ADD CONSTRAINT `fk_provider_shifts_partner`
                        FOREIGN KEY (`partner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                }
            }
        }

        if ($db->tableExists('partner_timings')) {
            $db->query("
                INSERT IGNORE INTO `provider_shifts`
                    (`partner_id`, `day`, `shift_number`, `opening_time`, `closing_time`, `is_open`)
                SELECT
                    `partner_id`, `day`, 1,
                    COALESCE(`opening_time`, '09:00:00'),
                    COALESCE(`closing_time`, '18:00:00'),
                    `is_open`
                FROM `partner_timings`
                WHERE `opening_time` IS NOT NULL AND `closing_time` IS NOT NULL
            ");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('provider_shifts')) {
            try {
                $db->query("ALTER TABLE `provider_shifts` DROP FOREIGN KEY `fk_provider_shifts_partner`");
            } catch (\Throwable $e) {
            }
        }

        $db->query("DROP TABLE IF EXISTS `provider_shifts`");
    }
}
