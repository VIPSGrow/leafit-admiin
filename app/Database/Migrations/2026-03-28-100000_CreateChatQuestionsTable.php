<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatQuestionsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('chat_questions')) {
            $db->query("
                CREATE TABLE `chat_questions` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `type` ENUM('pre_booking', 'customer_post_booking', 'provider_post_booking', 'customer_admin_support', 'provider_admin_support') NOT NULL,
                    `question` VARCHAR(500) NOT NULL COMMENT 'Default language text (fallback)',
                    `sort_order` INT(11) NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_type_order` (`type`, `sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Suggested chat questions for pre-booking, customer post-booking, provider post-booking, customer admin support, and provider admin support.'
            ");
        } else {
            // Existing installs: expand ENUM, rename admin_support → customer_admin_support, rename post_booking → customer_post_booking
            $db->query("
                ALTER TABLE `chat_questions`
                MODIFY COLUMN `type` ENUM('pre_booking', 'post_booking', 'customer_post_booking', 'provider_post_booking', 'admin_support', 'customer_admin_support', 'provider_admin_support') NOT NULL
            ");
            $db->query("
                UPDATE `chat_questions` SET `type` = 'customer_admin_support' WHERE `type` = 'admin_support'
            ");
            $db->query("
                UPDATE `chat_questions` SET `type` = 'customer_post_booking' WHERE `type` = 'post_booking'
            ");
            $db->query("
                ALTER TABLE `chat_questions`
                MODIFY COLUMN `type` ENUM('pre_booking', 'customer_post_booking', 'provider_post_booking', 'customer_admin_support', 'provider_admin_support') NOT NULL
            ");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->query("DROP TABLE IF EXISTS `chat_questions`");
    }
}
