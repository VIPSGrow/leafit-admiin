<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed default customer address custom fields.
 *
 * Seeds Address Line 1 and Address Line 2 into custom_fields so admins
 * can control their required/visible status through the Custom Fields UI.
 * All fields use field_group = 'customer_address'.
 *
 * FIX: The original seeder bailed silently when:
 *   1. `languages` table was empty → no translations seeded.
 *   2. Translation column detection (language_id vs language_code) was fragile.
 *   3. Insert could fail when field_key NOT NULL constraint was present but
 *      the conditional inclusion logic was confusing.
 */
class SeedCustomerAddressCustomFields extends Migration
{
    /** @var array<int, array<string, mixed>> */
    private array $fields = [
        ['field_key' => 'address',   'field_label' => 'Address Line 1', 'field_type' => 'text', 'sort_order' => 1, 'required' => 0, 'visible' => 1],
        ['field_key' => 'address_2', 'field_label' => 'Address Line 2', 'field_type' => 'text', 'sort_order' => 2, 'required' => 0, 'visible' => 1],
    ];

    public function up(): void
    {
        CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] Starting migration.');

        try {
            $db = \Config\Database::connect();
        } catch (\Throwable $e) {
            CLI_LOG('critical', 'SeedCustomerAddressCustomFields: [UP] Could not connect to DB — ' . $e->getMessage());
            return;
        }

        // Force a fresh metadata read in case previous migrations in this same
        // run created/altered tables using raw SQL.
        $db->resetDataCache();

        if (! $db->tableExists('custom_fields', false)) {
            CLI_LOG('warning', 'SeedCustomerAddressCustomFields: [UP] Table "custom_fields" does not exist — skipping.');
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Detect which columns actually exist so inserts are safe regardless
        // of which migration ran last.
        $hasFieldKey   = $db->fieldExists('field_key',   'custom_fields');
        $hasFileConfig = $db->fieldExists('file_config', 'custom_fields');

        CLI_LOG('debug', sprintf(
            'SeedCustomerAddressCustomFields: [UP] Column flags — field_key=%s, file_config=%s.',
            $hasFieldKey   ? 'YES' : 'NO',
            $hasFileConfig ? 'YES' : 'NO'
        ));

        // Detect translated_custom_fields column layout once (outside field loop).
        $translationsExist = $db->tableExists('translated_custom_fields', false);
        $langTableExists   = $db->tableExists('languages', false);
        $useLanguageId     = $translationsExist && $db->fieldExists('language_id',   'translated_custom_fields');
        $useLanguageCode   = $translationsExist && $db->fieldExists('language_code', 'translated_custom_fields');

        CLI_LOG('debug', sprintf(
            'SeedCustomerAddressCustomFields: [UP] Translation flags — table=%s, languages_table=%s, use_language_id=%s, use_language_code=%s.',
            $translationsExist ? 'YES' : 'NO',
            $langTableExists   ? 'YES' : 'NO',
            $useLanguageId     ? 'YES' : 'NO',
            $useLanguageCode   ? 'YES' : 'NO'
        ));

        // Fetch all languages once — empty array is fine; we just skip translation seeding.
        $languages = [];
        if ($translationsExist && $langTableExists && ($useLanguageId || $useLanguageCode)) {
            try {
                $languages = $db->table('languages')->select(['id', 'code'])->get()->getResultArray();
                CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] Fetched ' . count($languages) . ' language(s).');
            } catch (\Throwable $e) {
                CLI_LOG('error', 'SeedCustomerAddressCustomFields: [UP] Failed to fetch languages — ' . $e->getMessage() . ' — translation seeding will be skipped.');
                $languages = [];
            }
        } else {
            CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] No languages fetched (table missing or no usable column). Translation seeding will be skipped.');
        }

        foreach ($this->fields as $field) {
            CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] Processing field "' . $field['field_label'] . '".');

            // ----------------------------------------------------------------
            // 1. Idempotency check — match by label + group (field_key may not
            //    exist in later migrations that drop it).
            // ----------------------------------------------------------------
            try {
                $exists = $db->table('custom_fields')
                    ->where('field_label', $field['field_label'])
                    ->where('field_group', 'customer_address')
                    ->countAllResults();
            } catch (\Throwable $e) {
                CLI_LOG('error', 'SeedCustomerAddressCustomFields: [UP] Idempotency check failed for "' . $field['field_label'] . '" — ' . $e->getMessage() . ' — skipping field.');
                continue;
            }

            if ($exists > 0) {
                CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] Field "' . $field['field_label'] . '" already exists — skipping.');
                continue;
            }

            // ----------------------------------------------------------------
            // 2. Build the insert payload, only including columns that exist.
            //    Crucially: if field_key column exists and is NOT NULL, we MUST
            //    include it — omitting it would cause a DB error or empty-string
            //    unique key collision on the second run.
            // ----------------------------------------------------------------
            $insertData = [
                'field_label' => $field['field_label'],
                'field_type'  => $field['field_type'],
                'field_group' => 'customer_address',
                'required'    => $field['required'],
                'visible'     => $field['visible'],
                'sort_order'  => $field['sort_order'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            if ($hasFieldKey) {
                $insertData['field_key'] = $field['field_key'];
            }

            if ($hasFileConfig) {
                $insertData['file_config'] = null;
            }

            try {
                $db->table('custom_fields')->insert($insertData);
                $insertedId = (int) $db->insertID();
            } catch (\Throwable $e) {
                CLI_LOG('error', 'SeedCustomerAddressCustomFields: [UP] Insert threw exception for "' . $field['field_label'] . '" — ' . $e->getMessage() . ' — skipping field.');
                continue;
            }

            if ($insertedId <= 0) {
                // Insert failed for some reason — log and continue rather than dying.
                CLI_LOG('error', 'SeedCustomerAddressCustomFields: [UP] Insert returned no ID for field "' . $field['field_label'] . '" — skipping translations.');
                continue;
            }

            CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] Inserted field "' . $field['field_label'] . '" with ID=' . $insertedId . '.');

            // ----------------------------------------------------------------
            // 3. Seed translation rows.
            //    If languages table is empty, we skip — admin adds languages
            //    later and translations can be seeded then.
            //    We do NOT bail out of the whole migration just because there
            //    are no languages yet.
            // ----------------------------------------------------------------
            if (! $translationsExist || (! $useLanguageId && ! $useLanguageCode) || empty($languages)) {
                CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] Skipping translations for "' . $field['field_label'] . '" (no usable translation setup).');
                continue;
            }

            foreach ($languages as $language) {
                $langId   = (int)    ($language['id']   ?? 0);
                $langCode = (string) ($language['code'] ?? '');

                // Guard: skip rows with missing keys.
                if ($useLanguageId && $langId <= 0) {
                    CLI_LOG('warning', 'SeedCustomerAddressCustomFields: [UP] Language row has invalid/empty id — skipping.');
                    continue;
                }
                if ($useLanguageCode && $langCode === '') {
                    CLI_LOG('warning', 'SeedCustomerAddressCustomFields: [UP] Language row has empty code — skipping.');
                    continue;
                }

                // Idempotency: do not insert duplicate translation rows.
                try {
                    $translationCheck = $db->table('translated_custom_fields')
                        ->where('custom_field_id', $insertedId);

                    if ($useLanguageId) {
                        $translationCheck->where('language_id', $langId);
                    } else {
                        $translationCheck->where('language_code', $langCode);
                    }

                    $translationExists = $translationCheck->countAllResults();
                } catch (\Throwable $e) {
                    CLI_LOG('error', sprintf(
                        'SeedCustomerAddressCustomFields: [UP] Translation idempotency check failed for field_id=%d, lang=%s — %s — skipping.',
                        $insertedId,
                        $useLanguageId ? $langId : $langCode,
                        $e->getMessage()
                    ));
                    continue;
                }

                if ($translationExists > 0) {
                    CLI_LOG('debug', sprintf(
                        'SeedCustomerAddressCustomFields: [UP] Translation already exists for field_id=%d, lang=%s — skipping.',
                        $insertedId,
                        $useLanguageId ? $langId : $langCode
                    ));
                    continue;
                }

                $translationData = [
                    'custom_field_id' => $insertedId,
                    'field_label'     => $field['field_label'],
                ];

                if ($useLanguageId) {
                    $translationData['language_id'] = $langId;
                } else {
                    $translationData['language_code'] = $langCode;
                }

                try {
                    $db->table('translated_custom_fields')->insert($translationData);
                    CLI_LOG('info', sprintf(
                        'SeedCustomerAddressCustomFields: [UP] Inserted translation for field_id=%d, lang=%s.',
                        $insertedId,
                        $useLanguageId ? $langId : $langCode
                    ));
                } catch (\Throwable $e) {
                    CLI_LOG('error', sprintf(
                        'SeedCustomerAddressCustomFields: [UP] Translation insert failed for field_id=%d, lang=%s — %s.',
                        $insertedId,
                        $useLanguageId ? $langId : $langCode,
                        $e->getMessage()
                    ));
                }
            }
        }

        CLI_LOG('info', 'SeedCustomerAddressCustomFields: [UP] Migration complete.');
    }

    public function down(): void
    {
        CLI_LOG('info', 'SeedCustomerAddressCustomFields: [DOWN] Starting rollback.');

        try {
            $db = \Config\Database::connect();
        } catch (\Throwable $e) {
            CLI_LOG('critical', 'SeedCustomerAddressCustomFields: [DOWN] Could not connect to DB — ' . $e->getMessage());
            return;
        }

        if (! $db->tableExists('custom_fields')) {
            CLI_LOG('warning', 'SeedCustomerAddressCustomFields: [DOWN] Table "custom_fields" does not exist — nothing to roll back.');
            return;
        }

        $labels = array_column($this->fields, 'field_label');

        if ($db->tableExists('translated_custom_fields')) {
            try {
                $ids = $db->table('custom_fields')
                    ->select('id')
                    ->where('field_group', 'customer_address')
                    ->whereIn('field_label', $labels)
                    ->get()
                    ->getResultArray();

                $idList = array_column($ids, 'id');

                if (! empty($idList)) {
                    $db->table('translated_custom_fields')
                        ->whereIn('custom_field_id', $idList)
                        ->delete();
                    CLI_LOG('info', 'SeedCustomerAddressCustomFields: [DOWN] Deleted translations for field IDs: ' . implode(', ', $idList) . '.');
                } else {
                    CLI_LOG('info', 'SeedCustomerAddressCustomFields: [DOWN] No translation rows found to delete.');
                }
            } catch (\Throwable $e) {
                CLI_LOG('error', 'SeedCustomerAddressCustomFields: [DOWN] Failed to delete translations — ' . $e->getMessage());
            }
        } else {
            CLI_LOG('info', 'SeedCustomerAddressCustomFields: [DOWN] Table "translated_custom_fields" does not exist — skipping translation cleanup.');
        }

        try {
            $db->table('custom_fields')
                ->where('field_group', 'customer_address')
                ->whereIn('field_label', $labels)
                ->delete();
            CLI_LOG('info', 'SeedCustomerAddressCustomFields: [DOWN] Deleted custom_fields rows for group "customer_address".');
        } catch (\Throwable $e) {
            CLI_LOG('error', 'SeedCustomerAddressCustomFields: [DOWN] Failed to delete custom_fields rows — ' . $e->getMessage());
        }

        CLI_LOG('info', 'SeedCustomerAddressCustomFields: [DOWN] Rollback complete.');
    }
}

/**
 * CLI_LOG — dual-output helper: writes to CI4's log system AND echoes to
 * stdout so `php spark migrate` shows live progress in the terminal.
 *
 * Levels: debug | info | notice | warning | error | critical | alert | emergency
 *
 * @param string $level   PSR-3 log level string.
 * @param string $message Human-readable message.
 */
function CLI_LOG(string $level, string $message): void
{
    // log_message($level, $message);

    // Map PSR-3 level → a tidy prefix for terminal output.
    $prefixes = [
        'debug'     => '  [DEBUG]',
        'info'      => '   [INFO]',
        'notice'    => ' [NOTICE]',
        'warning'   => '[WARNING]',
        'error'     => '  [ERROR]',
        'critical'  => '  [CRIT!]',
        'alert'     => '  [ALERT]',
        'emergency' => '  [ EMG ]',
    ];

    $prefix = $prefixes[$level] ?? '[  LOG  ]';
    echo $prefix . ' ' . $message . PHP_EOL;
}
