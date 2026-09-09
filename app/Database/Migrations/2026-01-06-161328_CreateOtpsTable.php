<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOtpsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('otps')) {

            // =========================
            // MODIFY EXISTING TABLE
            // =========================

            // Make mobile nullable if exists
            if ($db->fieldExists('mobile', 'otps')) {
                $db->query("
                    ALTER TABLE `otps`
                    MODIFY `mobile` VARCHAR(191) NULL
                ");
            }

            // Add user_id if not exists
            if (!$db->fieldExists('user_id', 'otps')) {
                $db->query("
                    ALTER TABLE `otps`
                    ADD COLUMN `user_id` INT(11) UNSIGNED NULL AFTER `mobile`
                ");
            }

            // Add identity if not exists
            if (!$db->fieldExists('identity', 'otps')) {
                $db->query("
                    ALTER TABLE `otps`
                    ADD COLUMN `identity` VARCHAR(191) NOT NULL AFTER `user_id`
                ");
            }

            // Add channel if not exists
            if (!$db->fieldExists('channel', 'otps')) {
                $db->query("
                    ALTER TABLE `otps`
                    ADD COLUMN `channel` ENUM('sms','email') NOT NULL AFTER `identity`
                ");
            }

            // Add otp if not exists
            if (!$db->fieldExists('otp', 'otps')) {
                $db->query("
                    ALTER TABLE `otps`
                    ADD COLUMN `otp` VARCHAR(10) NOT NULL
                ");
            }

            // Modify created_at default
            if ($db->fieldExists('created_at', 'otps')) {
                $db->query("
                    ALTER TABLE `otps`
                    MODIFY `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ");
            }

        }
        else {

            // =========================
            // CREATE TABLE FRESH
            // =========================

            $db->query("
                CREATE TABLE IF NOT EXISTS `otps` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT(11) UNSIGNED NULL,
                    `identity` VARCHAR(191) NOT NULL,
                    `channel` ENUM('sms','email') NOT NULL,
                    `otp` VARCHAR(10) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_user_id` (`user_id`),
                    KEY `idx_identity_channel` (`identity`,`channel`),
                    KEY `idx_identity_otp` (`identity`,`otp`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->query("DROP TABLE IF EXISTS `otps`");
    }
}