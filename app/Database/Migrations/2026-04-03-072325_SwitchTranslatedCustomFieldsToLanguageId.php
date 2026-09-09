<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Switches translated_custom_fields from language_code (VARCHAR) to language_id (INT FK).
 * Adds FK to languages(id) with ON DELETE CASCADE ON UPDATE CASCADE.
 * Updates unique constraints accordingly.
 */
class SwitchTranslatedCustomFieldsToLanguageId extends Migration
{
    private function foreignKeyExists($db, string $table, string $constraintName): bool
    {
        $result = $db->query(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
             LIMIT 1",
            [$table, $constraintName]
        )->getRowArray();

        return ! empty($result);
    }

    private function indexExists($db, string $table, string $indexName): bool
    {
        $result = $db->query(
            "SELECT 1
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1",
            [$table, $indexName]
        )->getRowArray();

        return ! empty($result);
    }

    private function isUnsignedColumn($db, string $table, string $column): bool
    {
        $row = $db->query(
            "SELECT COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            [$table, $column]
        )->getRowArray();

        if (empty($row['COLUMN_TYPE'])) {
            return false;
        }

        return stripos((string) $row['COLUMN_TYPE'], 'unsigned') !== false;
    }

    public function up(): void
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('translated_custom_fields') || !$db->tableExists('languages')) {
            return;
        }

        $languageIdUnsigned = $this->isUnsignedColumn($db, 'languages', 'id');

        // --- 1. Add language_id column ---
        if (!$db->fieldExists('language_id', 'translated_custom_fields')) {
            $column = [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'custom_field_id',
            ];
            if ($languageIdUnsigned) {
                $column['unsigned'] = true;
            }
            $this->forge->addColumn('translated_custom_fields', [
                'language_id' => $column,
            ]);
        }

        // --- 2. Populate language_id from language_code ---
        $db->query("
            UPDATE `translated_custom_fields` tcf
            INNER JOIN `languages` l
                ON l.`code` COLLATE utf8mb4_unicode_ci
                 = tcf.`language_code` COLLATE utf8mb4_unicode_ci
            SET tcf.`language_id` = l.`id`
        ");

        // Delete orphaned rows where language_code didn't match any language
        $db->table('translated_custom_fields')
            ->where('language_id IS NULL')
            ->delete();

        // Make language_id NOT NULL
        $modifyColumn = [
            'type'       => 'INT',
            'constraint' => 11,
            'null'       => false,
        ];
        if ($languageIdUnsigned) {
            $modifyColumn['unsigned'] = true;
        }
        $this->forge->modifyColumn('translated_custom_fields', [
            'language_id' => $modifyColumn,
        ]);

        // --- 3. Drop old constraints and indexes that reference language_code ---
        if ($this->foreignKeyExists($db, 'translated_custom_fields', 'fk_tcf_language')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` DROP FOREIGN KEY `fk_tcf_language`');
            } catch (\Throwable $e) {
            }
        }
        if ($this->indexExists($db, 'translated_custom_fields', 'uk_field_lang')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` DROP KEY `uk_field_lang`');
            } catch (\Throwable $e) {
            }
        }
        if ($this->indexExists($db, 'translated_custom_fields', 'idx_language_code')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` DROP KEY `idx_language_code`');
            } catch (\Throwable $e) {
            }
        }
        if ($this->indexExists($db, 'translated_custom_fields', 'uk_language_label')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` DROP KEY `uk_language_label`');
            } catch (\Throwable $e) {
            }
        }

        // --- 4. Drop language_code column ---
        if ($db->fieldExists('language_code', 'translated_custom_fields')) {
            $this->forge->dropColumn('translated_custom_fields', 'language_code');
        }

        // --- 5. Add new constraints ---
        if (! $this->indexExists($db, 'translated_custom_fields', 'uk_field_lang')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` ADD UNIQUE KEY `uk_field_lang` (`custom_field_id`, `language_id`)');
            } catch (\Throwable $e) {
            }
        }
        if (! $this->indexExists($db, 'translated_custom_fields', 'uk_language_label')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` ADD UNIQUE KEY `uk_language_label` (`language_id`, `field_label`)');
            } catch (\Throwable $e) {
            }
        }

        if (! $this->foreignKeyExists($db, 'translated_custom_fields', 'fk_tcf_language')) {
            try {
                $db->query("
                    ALTER TABLE `translated_custom_fields`
                    ADD CONSTRAINT `fk_tcf_language`
                    FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('translated_custom_fields') || !$db->tableExists('languages')) {
            return;
        }

        // Add language_code back
        if (!$db->fieldExists('language_code', 'translated_custom_fields')) {
            $this->forge->addColumn('translated_custom_fields', [
                'language_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'null'       => true,
                    'after'      => 'custom_field_id',
                ],
            ]);
        }

        // Populate language_code from language_id
        $db->query("
            UPDATE `translated_custom_fields` tcf
            INNER JOIN `languages` l ON l.`id` = tcf.`language_id`
            SET tcf.`language_code` = l.`code`
        ");

        // Drop new constraints
        if ($this->foreignKeyExists($db, 'translated_custom_fields', 'fk_tcf_language')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` DROP FOREIGN KEY `fk_tcf_language`');
            } catch (\Throwable $e) {
            }
        }
        if ($this->indexExists($db, 'translated_custom_fields', 'uk_field_lang')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` DROP KEY `uk_field_lang`');
            } catch (\Throwable $e) {
            }
        }
        if ($this->indexExists($db, 'translated_custom_fields', 'uk_language_label')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` DROP KEY `uk_language_label`');
            } catch (\Throwable $e) {
            }
        }

        // Drop language_id
        if ($db->fieldExists('language_id', 'translated_custom_fields')) {
            $this->forge->dropColumn('translated_custom_fields', 'language_id');
        }

        // Restore old schema
        $this->forge->modifyColumn('translated_custom_fields', [
            'language_code' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false],
        ]);

        if (! $this->indexExists($db, 'translated_custom_fields', 'uk_field_lang')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` ADD UNIQUE KEY `uk_field_lang` (`custom_field_id`, `language_code`)');
            } catch (\Throwable $e) {
            }
        }
        if (! $this->indexExists($db, 'translated_custom_fields', 'idx_language_code')) {
            try {
                $db->query('ALTER TABLE `translated_custom_fields` ADD KEY `idx_language_code` (`language_code`)');
            } catch (\Throwable $e) {
            }
        }
        if (! $this->foreignKeyExists($db, 'translated_custom_fields', 'fk_tcf_language')) {
            try {
                $db->query("ALTER TABLE `translated_custom_fields` ADD CONSTRAINT `fk_tcf_language` FOREIGN KEY (`language_code`) REFERENCES `languages`(`code`) ON DELETE CASCADE ON UPDATE CASCADE");
            } catch (\Throwable $e) {
            }
        }
    }
}
