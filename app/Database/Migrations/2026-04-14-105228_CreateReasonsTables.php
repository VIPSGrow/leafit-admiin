<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReasonsTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('reasons')) {
            $db->query("
                CREATE TABLE `reasons` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `type` VARCHAR(50) NOT NULL COMMENT 'cancel, reschedule',
                    `reason` VARCHAR(500) NOT NULL,
                    `needs_additional_info` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Unified reasons for cancel, reschedule.'
            ");
        }

        if (! $db->tableExists('translated_reasons')) {
            $db->query("
                CREATE TABLE `translated_reasons` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `reason_id` INT(11) UNSIGNED NOT NULL COMMENT 'References reasons.id',
                    `language_id` INT(11) UNSIGNED NOT NULL COMMENT 'References languages.id',
                    `reason` VARCHAR(500) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_reason_lang` (`reason_id`, `language_id`),
                    KEY `idx_language_id` (`language_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Translations for reasons.'
            ");
        }

        if ($db->tableExists('translated_reasons') && $db->tableExists('reasons')) {
            try {
                $db->query("
                    ALTER TABLE `translated_reasons`
                    ADD CONSTRAINT `fk_tr_reason`
                    FOREIGN KEY (`reason_id`) REFERENCES `reasons`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
            }
        }

        if ($db->tableExists('translated_reasons') && $db->tableExists('languages')) {
            try {
                $db->query("
                    ALTER TABLE `translated_reasons`
                    ADD CONSTRAINT `fk_tr_language`
                    FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('translated_reasons')) {
            try {
                $db->query("ALTER TABLE `translated_reasons` DROP FOREIGN KEY `fk_tr_reason`");
            } catch (\Throwable $e) {
            }
            try {
                $db->query("ALTER TABLE `translated_reasons` DROP FOREIGN KEY `fk_tr_language`");
            } catch (\Throwable $e) {
            }
        }

        $db->query("DROP TABLE IF EXISTS `translated_reasons`");
        $db->query("DROP TABLE IF EXISTS `reasons`");
    }
}
