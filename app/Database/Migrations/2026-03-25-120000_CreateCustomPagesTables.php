<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomPagesTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Create custom_pages base table
        if (! $db->tableExists('custom_pages')) {
            $db->query("
                CREATE TABLE `custom_pages` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `title` VARCHAR(255) NOT NULL COMMENT 'Default language title (fallback)',
                    `slug` VARCHAR(255) NOT NULL COMMENT 'URL-friendly identifier',
                    `content` LONGTEXT NULL COMMENT 'Default language content (fallback)',
                    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Custom content pages created by admin'
            ");
        }

        // Create custom_page_translations table
        if (! $db->tableExists('custom_page_translations')) {
            $db->query("
                CREATE TABLE `custom_page_translations` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `custom_page_id` INT(11) UNSIGNED NOT NULL COMMENT 'References custom_pages.id',
                    `language_code` VARCHAR(16) NOT NULL COMMENT 'References languages.code',
                    `title` VARCHAR(255) NOT NULL COMMENT 'Page title in this language',
                    `content` LONGTEXT NULL COMMENT 'Rich HTML content in this language',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_page_lang` (`custom_page_id`, `language_code`),
                    KEY `idx_language_code` (`language_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Translations for custom pages'
            ");
        }

        // Add FK: custom_page_translations -> custom_pages
        if ($db->tableExists('custom_page_translations') && $db->tableExists('custom_pages')) {
            try {
                $db->query("
                    ALTER TABLE `custom_page_translations`
                    ADD CONSTRAINT `fk_cpt_page`
                    FOREIGN KEY (`custom_page_id`) REFERENCES `custom_pages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
                // Ignore if FK already exists
            }
        }

        // Add FK: custom_page_translations -> languages
        if ($db->tableExists('custom_page_translations') && $db->tableExists('languages')) {
            try {
                $db->query("
                    ALTER TABLE `custom_page_translations`
                    ADD CONSTRAINT `fk_cpt_language`
                    FOREIGN KEY (`language_code`) REFERENCES `languages`(`code`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
                // Ignore if FK already exists
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('custom_page_translations')) {
            try {
                $db->query("ALTER TABLE `custom_page_translations` DROP FOREIGN KEY `fk_cpt_page`");
            } catch (\Throwable $e) {}
            try {
                $db->query("ALTER TABLE `custom_page_translations` DROP FOREIGN KEY `fk_cpt_language`");
            } catch (\Throwable $e) {}
        }

        $db->query("DROP TABLE IF EXISTS `custom_page_translations`");
        $db->query("DROP TABLE IF EXISTS `custom_pages`");
    }
}
