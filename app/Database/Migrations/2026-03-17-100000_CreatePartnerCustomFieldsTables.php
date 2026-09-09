<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Partner Custom Fields – tables and default seed data.
 *
 * Creates:
 * - custom_fields: field definitions (documents and bank details).
 * - partner_custom_fields: one value per partner per field.
 *
 * Seeds default fields: documents (passport, national_id, address_id) and
 * bank details (tax_name, tax_number, bank_name, account_number, account_name, bank_code, swift_code).
 *
 * Also performs a one-time backfill from existing `partner_details` columns into
 * `partner_custom_fields` for the seeded keys.
 *
 * Written for compatibility with MySQL 5.5+ and MariaDB 5.5+ (standard SQL,
 * InnoDB, utf8mb4, no MySQL 8–specific syntax).
 */
class CreatePartnerCustomFieldsTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // ---------------------------------------------------------------------
        // Read partner document visibility/required flags from general settings.
        //
        // The admin UI stores these in the `settings` table under:
        // - variable: "general_settings"
        // - value: JSON payload containing keys like:
        //   - passport_verification_status
        //   - passport_required_status
        //
        // This migration uses those keys to seed `custom_fields.required` and
        // `custom_fields.visible` so the default document behavior matches
        // what is configured in `general_settings.php`.
        // ---------------------------------------------------------------------
        $generalSettings = [];
        if ($db->tableExists('settings')) {
            $settingsRow = $db->table('settings')
                ->select(['value'])
                ->where('variable', 'general_settings')
                ->get()
                ->getRowArray();

            if (! empty($settingsRow['value'])) {
                $decoded = json_decode((string) $settingsRow['value'], true);
                if (is_array($decoded)) {
                    $generalSettings = $decoded;
                }
            }
        }

        // Keep backwards-compatible defaults if settings are missing.
        $passportVerificationStatus = (int) ($generalSettings['passport_verification_status'] ?? 1);
        $nationalIdVerificationStatus = (int) ($generalSettings['national_id_verification_status'] ?? 1);
        $addressIdVerificationStatus = (int) ($generalSettings['address_id_verification_status'] ?? 1);

        $passportRequiredStatus = (int) ($generalSettings['passport_required_status'] ?? 0);
        $nationalIdRequiredStatus = (int) ($generalSettings['national_id_required_status'] ?? 0);
        $addressIdRequiredStatus = (int) ($generalSettings['address_id_required_status'] ?? 0);

        // Only mark a field as required when it is also visible.
        $passportVisible = $passportVerificationStatus === 1 ? 1 : 0;
        $nationalIdVisible = $nationalIdVerificationStatus === 1 ? 1 : 0;
        $addressIdVisible = $addressIdVerificationStatus === 1 ? 1 : 0;

        $passportRequired = $passportVisible === 1 ? ($passportRequiredStatus === 1 ? 1 : 0) : 0;
        $nationalIdRequired = $nationalIdVisible === 1 ? ($nationalIdRequiredStatus === 1 ? 1 : 0) : 0;
        $addressIdRequired = $addressIdVisible === 1 ? ($addressIdRequiredStatus === 1 ? 1 : 0) : 0;

        // ---------------------------------------------------------------------
        // Table: custom_fields (definitions only)
        // ---------------------------------------------------------------------
        if (! $db->tableExists('custom_fields')) {
            $db->query("
                CREATE TABLE `custom_fields` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `field_key` VARCHAR(64) NOT NULL COMMENT 'Unique key e.g. passport, national_id, bank_name',
                    `field_label` VARCHAR(255) NOT NULL COMMENT 'Display label',
                    `field_type` VARCHAR(32) NOT NULL DEFAULT 'text' COMMENT 'text, textarea, file, number, date',
                    `file_config` TEXT NULL COMMENT 'JSON config for file fields (allowed_types, max_size_mb, max_files)',
                    `field_group` VARCHAR(64) NOT NULL DEFAULT 'documents' COMMENT 'documents or bank_details',
                    `required` TINYINT(1) NOT NULL DEFAULT 0,
                    `visible` TINYINT(1) NOT NULL DEFAULT 1,
                    `sort_order` INT(11) NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_field_key` (`field_key`),
                    KEY `idx_field_group` (`field_group`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Custom field definitions: documents and bank details.'
            ");
        } else {
            // Some existing deployments may have `custom_fields` created before
            // this migration added the `file_config` column. The app and seeded
            // data expect the column to always exist.
            if (! $db->fieldExists('file_config', 'custom_fields')) {
                $db->query("ALTER TABLE `custom_fields` ADD COLUMN `file_config` TEXT NULL");
            }
        }

        // ---------------------------------------------------------------------
        // Table: partner_custom_fields (one row per partner per field)
        // ---------------------------------------------------------------------
        if (! $db->tableExists('partner_custom_fields')) {
            $db->query("
                CREATE TABLE `partner_custom_fields` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT(11) NOT NULL COMMENT 'References partner_details.partner_id',
                    `custom_field_id` INT(11) UNSIGNED NOT NULL COMMENT 'References custom_fields.id',
                    `value` TEXT NULL COMMENT 'Stored value: text or file path for file type',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_partner_field` (`partner_id`, `custom_field_id`),
                    KEY `idx_partner_id` (`partner_id`),
                    KEY `idx_custom_field_id` (`custom_field_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Values of partner custom fields: one value per partner per field.'
            ");

            // Add FKs only if partner_details exists (avoids failure on fresh installs without partner_details).
            if ($db->tableExists('partner_details')) {
                try {
                    $db->query("
                        ALTER TABLE `partner_custom_fields`
                        ADD CONSTRAINT `fk_pcfv_partner`
                        FOREIGN KEY (`partner_id`) REFERENCES `partner_details`(`partner_id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                    // Ignore if FK already exists or DB does not support FKs.
                }
                try {
                    $db->query("
                        ALTER TABLE `partner_custom_fields`
                        ADD CONSTRAINT `fk_pcfv_field`
                        FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                    // Ignore if FK already exists or DB does not support FKs.
                }
            }
        }

        // Raw CREATE/ALTER queries do not always refresh CI metadata caches
        // for table/field discovery in subsequent migrations in the same run.
        $db->resetDataCache();

        // ---------------------------------------------------------------------
        // Default seed: only if no custom fields exist yet (idempotent)
        // ---------------------------------------------------------------------
        $count = $db->table('custom_fields')->countAll();
        if ($count === 0) {
            $defaultFileConfig = json_encode([
                'allowed_types' => [
                    '.jpg',
                    '.jpeg',
                    '.png',
                    '.gif',
                    '.webp',
                    '.bmp',
                    '.tif',
                    '.tiff',
                    '.svg',
                ],
                'max_size_mb'   => 2,
                'max_files'     => 1,
            ], JSON_UNESCAPED_SLASHES);

            $db->table('custom_fields')->insertBatch([
                [
                    'field_key'   => 'passport',
                    'field_label' => 'Passport',
                    'field_type'  => 'file',
                    'file_config' => $defaultFileConfig,
                    'field_group' => 'documents',
                    'required'    => $passportRequired,
                    'visible'     => $passportVisible,
                    'sort_order'  => 10,
                ],
                [
                    'field_key'   => 'national_id',
                    'field_label' => 'National Identity',
                    'field_type'  => 'file',
                    'file_config' => $defaultFileConfig,
                    'field_group' => 'documents',
                    'required'    => $nationalIdRequired,
                    'visible'     => $nationalIdVisible,
                    'sort_order'  => 20,
                ],
                [
                    'field_key'   => 'address_id',
                    'field_label' => 'Address Identity',
                    'field_type'  => 'file',
                    'file_config' => $defaultFileConfig,
                    'field_group' => 'documents',
                    'required'    => $addressIdRequired,
                    'visible'     => $addressIdVisible,
                    'sort_order'  => 30,
                ],
                [
                    'field_key'   => 'tax_name',
                    'field_label' => 'Tax Name',
                    'field_type'  => 'text',
                    'file_config' => null,
                    'field_group' => 'bank_details',
                    'required'    => 0,
                    'visible'     => 1,
                    'sort_order'  => 35,
                ],
                [
                    'field_key'   => 'tax_number',
                    'field_label' => 'Tax Number',
                    'field_type'  => 'text',
                    'file_config' => null,
                    'field_group' => 'bank_details',
                    'required'    => 0,
                    'visible'     => 1,
                    'sort_order'  => 36,
                ],
                [
                    'field_key'   => 'bank_name',
                    'field_label' => 'Bank Name',
                    'field_type'  => 'text',
                    'file_config' => null,
                    'field_group' => 'bank_details',
                    'required'    => 0,
                    'visible'     => 1,
                    'sort_order'  => 40,
                ],
                [
                    'field_key'   => 'account_number',
                    'field_label' => 'Account Number',
                    'field_type'  => 'text',
                    'file_config' => null,
                    'field_group' => 'bank_details',
                    'required'    => 0,
                    'visible'     => 1,
                    'sort_order'  => 50,
                ],
                [
                    'field_key'   => 'account_name',
                    'field_label' => 'Account Name',
                    'field_type'  => 'text',
                    'file_config' => null,
                    'field_group' => 'bank_details',
                    'required'    => 0,
                    'visible'     => 1,
                    'sort_order'  => 60,
                ],
                [
                    'field_key'   => 'bank_code',
                    'field_label' => 'Bank Code',
                    'field_type'  => 'text',
                    'file_config' => null,
                    'field_group' => 'bank_details',
                    'required'    => 0,
                    'visible'     => 1,
                    'sort_order'  => 70,
                ],
                [
                    'field_key'   => 'swift_code',
                    'field_label' => 'SWIFT Code',
                    'field_type'  => 'text',
                    'file_config' => null,
                    'field_group' => 'bank_details',
                    'required'    => 0,
                    'visible'     => 1,
                    'sort_order'  => 80,
                ],
            ]);
        }

        // Ensure seeded defaults exist even when the table is not empty.
        // Why:
        // - Previous deployments may have partial data.
        // - Or a failed/partial migration run can leave some rows missing.
        // This block is idempotent because it only inserts rows whose
        // `field_key` are missing.
        $seededKeys = [
            'passport',
            'national_id',
            'address_id',
            'tax_name',
            'tax_number',
            'bank_name',
            'account_number',
            'account_name',
            'bank_code',
            'swift_code',
        ];

        $existingSeededKeys = [];
        $existingSeededRows = $db->table('custom_fields')
            ->select(['field_key'])
            ->whereIn('field_key', $seededKeys)
            ->get()
            ->getResultArray();
        foreach ($existingSeededRows as $r) {
            $key = (string) ($r['field_key'] ?? '');
            if ($key !== '') {
                $existingSeededKeys[$key] = true;
            }
        }

        $defaultFileConfigForInsert = json_encode([
            // Same defaults as the initial seeding block.
            'allowed_types' => [
                '.jpg',
                '.jpeg',
                '.png',
                '.gif',
                '.webp',
                '.bmp',
                '.tif',
                '.tiff',
                '.svg',
            ],
            'max_size_mb'   => 2,
            'max_files'     => 1,
        ], JSON_UNESCAPED_SLASHES);

        $seededRows = [
            [
                'field_key'   => 'passport',
                'field_label' => 'Passport',
                'field_type'  => 'file',
                'file_config' => $defaultFileConfigForInsert,
                'field_group' => 'documents',
                'required'    => $passportRequired,
                'visible'     => $passportVisible,
                'sort_order'  => 10,
            ],
            [
                'field_key'   => 'national_id',
                'field_label' => 'National Identity',
                'field_type'  => 'file',
                'file_config' => $defaultFileConfigForInsert,
                'field_group' => 'documents',
                'required'    => $nationalIdRequired,
                'visible'     => $nationalIdVisible,
                'sort_order'  => 20,
            ],
            [
                'field_key'   => 'address_id',
                'field_label' => 'Address Identity',
                'field_type'  => 'file',
                'file_config' => $defaultFileConfigForInsert,
                'field_group' => 'documents',
                'required'    => $addressIdRequired,
                'visible'     => $addressIdVisible,
                'sort_order'  => 30,
            ],
            [
                'field_key'   => 'tax_name',
                'field_label' => 'Tax Name',
                'field_type'  => 'text',
                'file_config' => null,
                'field_group' => 'bank_details',
                'required'    => 0,
                'visible'     => 1,
                'sort_order'  => 35,
            ],
            [
                'field_key'   => 'tax_number',
                'field_label' => 'Tax Number',
                'field_type'  => 'text',
                'file_config' => null,
                'field_group' => 'bank_details',
                'required'    => 0,
                'visible'     => 1,
                'sort_order'  => 36,
            ],
            [
                'field_key'   => 'bank_name',
                'field_label' => 'Bank Name',
                'field_type'  => 'text',
                'file_config' => null,
                'field_group' => 'bank_details',
                'required'    => 0,
                'visible'     => 1,
                'sort_order'  => 40,
            ],
            [
                'field_key'   => 'account_number',
                'field_label' => 'Account Number',
                'field_type'  => 'text',
                'file_config' => null,
                'field_group' => 'bank_details',
                'required'    => 0,
                'visible'     => 1,
                'sort_order'  => 50,
            ],
            [
                'field_key'   => 'account_name',
                'field_label' => 'Account Name',
                'field_type'  => 'text',
                'file_config' => null,
                'field_group' => 'bank_details',
                'required'    => 0,
                'visible'     => 1,
                'sort_order'  => 60,
            ],
            [
                'field_key'   => 'bank_code',
                'field_label' => 'Bank Code',
                'field_type'  => 'text',
                'file_config' => null,
                'field_group' => 'bank_details',
                'required'    => 0,
                'visible'     => 1,
                'sort_order'  => 70,
            ],
            [
                'field_key'   => 'swift_code',
                'field_label' => 'SWIFT Code',
                'field_type'  => 'text',
                'file_config' => null,
                'field_group' => 'bank_details',
                'required'    => 0,
                'visible'     => 1,
                'sort_order'  => 80,
            ],
        ];

        $rowsToInsert = [];
        foreach ($seededRows as $row) {
            $key = (string) ($row['field_key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (!isset($existingSeededKeys[$key])) {
                $rowsToInsert[] = $row;
            }
        }

        if (!empty($rowsToInsert)) {
            $db->table('custom_fields')->insertBatch($rowsToInsert);
        }

        // Backfill file_config for existing deployments where custom_fields
        // rows were created before this column existed.
        if ($db->fieldExists('file_config', 'custom_fields')) {
            $defaultFileConfig = json_encode([
                'allowed_types' => [
                    '.jpg',
                    '.jpeg',
                    '.png',
                    '.gif',
                    '.webp',
                    '.bmp',
                    '.tif',
                    '.tiff',
                    '.svg',
                ],
                'max_size_mb'   => 2,
                'max_files'     => 1,
            ], JSON_UNESCAPED_SLASHES);

            $db->query(
                "UPDATE `custom_fields`
                 SET `file_config` = " . $db->escape($defaultFileConfig) . "
                 WHERE `field_type` = 'file'
                   AND (`file_config` IS NULL OR `file_config` = '')"
            );
        }

        // ---------------------------------------------------------------------
        // One-time data migration: copy existing partner_details columns into
        // partner_custom_fields (only for the default seeded field keys).
        //
        // This makes sure existing partners keep their document/bank values after
        // switching to the custom-fields storage.
        // ---------------------------------------------------------------------
        if ($db->tableExists('partner_details')) {
            // Map field_key => custom_fields.id for seeded keys.
            $seededKeys = [
                'passport',
                'national_id',
                'address_id',
                'tax_name',
                'tax_number',
                'bank_name',
                'account_number',
                'account_name',
                'bank_code',
                'swift_code',
            ];

            $fieldIdByKey = [];
            $fieldRows = $db->table('custom_fields')
                ->select(['id', 'field_key'])
                ->whereIn('field_key', $seededKeys)
                ->get()
                ->getResultArray();

            foreach ($fieldRows as $row) {
                $fieldIdByKey[$row['field_key']] = (int) $row['id'];
            }

            // Only proceed if we have at least the document fields defined.
            if (! empty($fieldIdByKey)) {
                $partners = $db->table('partner_details')
                    ->select(array_merge(['partner_id'], $seededKeys))
                    ->get()
                    ->getResultArray();

                $rowsToInsert = [];

                foreach ($partners as $partner) {
                    $partnerId = (int) ($partner['partner_id'] ?? 0);
                    if ($partnerId <= 0) {
                        continue;
                    }

                    foreach ($seededKeys as $key) {
                        if (! array_key_exists($key, $fieldIdByKey)) {
                            continue;
                        }

                        $value = $partner[$key] ?? null;
                        if ($value === null) {
                            continue;
                        }

                        // Normalize and skip empty strings.
                        if (is_string($value)) {
                            $value = trim($value);
                            if ($value === '') {
                                continue;
                            }
                        }

                        $rowsToInsert[] = [
                            'partner_id'       => $partnerId,
                            'custom_field_id'  => $fieldIdByKey[$key],
                            'value'            => (string) $value,
                        ];
                    }
                }

                // Insert rows while ignoring duplicates (migration is "once", but this keeps it safe).
                // Uses MySQL/MariaDB INSERT IGNORE for compatibility with the unique key.
                if (! empty($rowsToInsert)) {
                    $chunkSize = 500;
                    $total = count($rowsToInsert);
                    for ($i = 0; $i < $total; $i += $chunkSize) {
                        $chunk = array_slice($rowsToInsert, $i, $chunkSize);

                        $valuesSql = [];
                        foreach ($chunk as $r) {
                            $valuesSql[] = '('
                                . (int) $r['partner_id'] . ','
                                . (int) $r['custom_field_id'] . ','
                                . $db->escape($r['value'])
                                . ')';
                        }

                        $db->query(
                            'INSERT IGNORE INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
                            . implode(',', $valuesSql)
                        );
                    }
                }
            }
        }

        // ---------------------------------------------------------------------
        // Ensure existing seeded document fields match the current
        // `general_settings` values.
        //
        // Without this, deployments that already ran the original migration
        // would keep the old hardcoded flags forever.
        // ---------------------------------------------------------------------
        if ($db->tableExists('custom_fields')) {
            $db->table('custom_fields')
                ->whereIn('field_key', ['passport', 'national_id', 'address_id'])
                ->update([
                    // NOTE: We update via CASE so we only run one query.
                    // MySQL/MariaDB compatible.
                    // required / visible are always stored as TINYINT(1).
                    'required' => new \CodeIgniter\Database\RawSql("
                        CASE
                            WHEN field_key = 'passport' THEN {$passportRequired}
                            WHEN field_key = 'national_id' THEN {$nationalIdRequired}
                            WHEN field_key = 'address_id' THEN {$addressIdRequired}
                            ELSE required
                        END
                    "),
                    'visible' => new \CodeIgniter\Database\RawSql("
                        CASE
                            WHEN field_key = 'passport' THEN {$passportVisible}
                            WHEN field_key = 'national_id' THEN {$nationalIdVisible}
                            WHEN field_key = 'address_id' THEN {$addressIdVisible}
                            ELSE visible
                        END
                    "),
                ]);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        // Drop FKs first (partner_custom_fields references both tables).
        if ($db->tableExists('partner_custom_fields')) {
            try {
                $db->query("ALTER TABLE `partner_custom_fields` DROP FOREIGN KEY `fk_pcfv_partner`");
            } catch (\Throwable $e) {
                // Ignore if FK does not exist.
            }
            try {
                $db->query("ALTER TABLE `partner_custom_fields` DROP FOREIGN KEY `fk_pcfv_field`");
            } catch (\Throwable $e) {
                // Ignore if FK does not exist.
            }
            $db->query("DROP TABLE IF EXISTS `partner_custom_fields`");
        }

        $db->query("DROP TABLE IF EXISTS `custom_fields`");
    }
}
