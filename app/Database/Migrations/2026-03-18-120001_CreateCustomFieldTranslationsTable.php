<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Custom Field Translations
 *
 * Stores translated labels (and later placeholders/help text) for custom fields.
 * Uses the same "base + translated_* table" pattern used across the project.
 *
 * Table:
 * - translated_custom_fields (custom_field_id + language_code unique)
 *
 * Seed:
 * - Inserts default-language rows for existing custom_fields using custom_fields.field_label.
 */
class CreateCustomFieldTranslationsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('translated_custom_fields')) {
            $db->query("
                CREATE TABLE `translated_custom_fields` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `custom_field_id` INT(11) UNSIGNED NOT NULL COMMENT 'References custom_fields.id',
                    `language_code` VARCHAR(16) NOT NULL COMMENT 'References languages.code',
                    `field_label` VARCHAR(255) NOT NULL COMMENT 'Translated display label',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_field_lang` (`custom_field_id`, `language_code`),
                    KEY `idx_language_code` (`language_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Translations for custom field labels.'
            ");
        }

        // Add FKs only when referenced tables exist (keeps fresh installs resilient).
        if ($db->tableExists('translated_custom_fields') && $db->tableExists('custom_fields')) {
            try {
                $db->query("
                    ALTER TABLE `translated_custom_fields`
                    ADD CONSTRAINT `fk_tcf_field`
                    FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
                // Ignore if FK already exists or DB does not support FKs.
            }
        }

        if ($db->tableExists('translated_custom_fields') && $db->tableExists('languages')) {
            try {
                $db->query("
                    ALTER TABLE `translated_custom_fields`
                    ADD CONSTRAINT `fk_tcf_language`
                    FOREIGN KEY (`language_code`) REFERENCES `languages`(`code`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
                // Ignore if FK already exists or DB does not support FKs.
            }
        }

        // Seed default-language translations from custom_fields.field_label.
        if ($db->tableExists('custom_fields') && $db->tableExists('translated_custom_fields')) {
            // IMPORTANT:
            // - We also have an FK to languages(code).
            // - On fresh installs the languages table may exist but not be populated yet.
            // So we must only seed using a language_code that actually exists.
            $defaultLangCode = null;
            if ($db->tableExists('languages')) {
                $defaultLang = $db->table('languages')
                    ->where('is_default', 1)
                    ->get()
                    ->getRowArray();

                if (!empty($defaultLang) && !empty($defaultLang['code'])) {
                    $defaultLangCode = (string) $defaultLang['code'];
                } else {
                    // Fallback: first available language code (still valid for FK).
                    $firstLang = $db->table('languages')
                        ->select(['code'])
                        ->orderBy('id', 'ASC')
                        ->get()
                        ->getRowArray();
                    if (!empty($firstLang) && !empty($firstLang['code'])) {
                        $defaultLangCode = (string) $firstLang['code'];
                    }
                }
            }

            // If there is no language row yet, skip seeding.
            // The admin can add languages later; translations can be inserted then.
            if (empty($defaultLangCode)) {
                return;
            }

            $fields = $db->table('custom_fields')
                ->select(['id', 'field_label'])
                ->get()
                ->getResultArray();

            foreach ($fields as $field) {
                $fieldId = (int) ($field['id'] ?? 0);
                $label = (string) ($field['field_label'] ?? '');
                $label = trim($label);
                if ($fieldId <= 0 || $label === '') {
                    continue;
                }

                // Insert only if missing (idempotent).
                $exists = $db->table('translated_custom_fields')
                    ->where('custom_field_id', $fieldId)
                    ->where('language_code', $defaultLangCode)
                    ->countAllResults();

                if ($exists === 0) {
                    $db->table('translated_custom_fields')->insert([
                        'custom_field_id' => $fieldId,
                        'language_code'   => $defaultLangCode,
                        'field_label'     => $label,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('translated_custom_fields')) {
            try {
                $db->query("ALTER TABLE `translated_custom_fields` DROP FOREIGN KEY `fk_tcf_field`");
            } catch (\Throwable $e) {
                // Ignore if FK does not exist.
            }
            try {
                $db->query("ALTER TABLE `translated_custom_fields` DROP FOREIGN KEY `fk_tcf_language`");
            } catch (\Throwable $e) {
                // Ignore if FK does not exist.
            }
        }

        $db->query("DROP TABLE IF EXISTS `translated_custom_fields`");
    }
}

