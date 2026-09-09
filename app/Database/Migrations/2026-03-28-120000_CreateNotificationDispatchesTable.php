<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationDispatchesTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('notification_dispatches')) {
            $db->query("
                CREATE TABLE `notification_dispatches` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `batch_id` VARCHAR(36) NOT NULL COMMENT 'UUID grouping all chunked jobs for one dispatch',
                    `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Notification title for tray display',
                    `event_type` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'e.g. admin_custom_notification',
                    `total_recipients` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total users targeted',
                    `total_jobs` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total queued jobs (FCM + Email/SMS chunks)',
                    `completed_jobs` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Jobs that have finished processing',
                    `fcm_sent` INT UNSIGNED NOT NULL DEFAULT 0,
                    `fcm_failed` INT UNSIGNED NOT NULL DEFAULT 0,
                    `email_sent` INT UNSIGNED NOT NULL DEFAULT 0,
                    `email_failed` INT UNSIGNED NOT NULL DEFAULT 0,
                    `sms_sent` INT UNSIGNED NOT NULL DEFAULT 0,
                    `sms_failed` INT UNSIGNED NOT NULL DEFAULT 0,
                    `status` ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `completed_at` DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_batch_id` (`batch_id`),
                    KEY `idx_status_completed` (`status`, `completed_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Tracks notification queue batch progress for admin tray UI'
            ");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->query("DROP TABLE IF EXISTS `notification_dispatches`");
    }
}
