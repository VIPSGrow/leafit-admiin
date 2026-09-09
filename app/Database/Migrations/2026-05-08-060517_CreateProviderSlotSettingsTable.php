<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProviderSlotSettingsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('provider_slot_settings')) {
            $db->query("
                CREATE TABLE `provider_slot_settings` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT(11) UNSIGNED NOT NULL,
                    `slot_interval` SMALLINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Minutes between slot start times',
                    `allow_multiple_bookings` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Toggle for concurrent bookings',
                    `slot_capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Max parallel bookings per slot when allow_multiple_bookings=1',
                    `min_advance_booking_value` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Lead-time numeric value',
                    `min_advance_booking_unit` ENUM('minutes','hours','days') NOT NULL DEFAULT 'hours' COMMENT 'Lead-time unit',
                    `max_advance_booking_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Max future booking horizon',
                    `same_day_booking` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Allow bookings for today',
                    `buffer_before` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minutes blocked before each booking',
                    `buffer_after` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minutes blocked after each booking',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_partner` (`partner_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Per-provider slot generation + booking-window configuration.'
            ");

            if ($db->tableExists('users')) {
                try {
                    $db->query("
                        ALTER TABLE `provider_slot_settings`
                        ADD CONSTRAINT `fk_provider_slot_settings_partner`
                        FOREIGN KEY (`partner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                }
            }
        }

        if ($db->tableExists('partner_details')) {
            $db->query("
                INSERT IGNORE INTO `provider_slot_settings`
                    (`partner_id`, `max_advance_booking_days`)
                SELECT `partner_id`, COALESCE(`advance_booking_days`, 30)
                FROM `partner_details`
                WHERE `partner_id` IS NOT NULL
            ");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('provider_slot_settings')) {
            try {
                $db->query("ALTER TABLE `provider_slot_settings` DROP FOREIGN KEY `fk_provider_slot_settings_partner`");
            } catch (\Throwable $e) {
            }
        }

        $db->query("DROP TABLE IF EXISTS `provider_slot_settings`");
    }
}
