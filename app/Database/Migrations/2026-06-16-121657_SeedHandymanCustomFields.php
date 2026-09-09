<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed default handyman custom fields.
 *
 * Seeds Email, Skills, and Experience into custom_fields so admins
 * can control their required/visible status through the Custom Fields UI.
 * All fields use field_group = 'handyman_details'.
 */
class SeedHandymanCustomFields extends Migration
{
    /** @var array<int, array<string, mixed>> */
    private array $fields = [
        ['field_label' => 'Skills', 'field_type' => 'textarea', 'sort_order' => 2, 'required' => 0, 'visible' => 1],
        ['field_label' => 'Experience', 'field_type' => 'number', 'sort_order' => 3, 'required' => 0, 'visible' => 1],
    ];

    public function up(): void
    {
        // CLI_LOG('info', 'SeedHandymanCustomFields: [UP] Starting migration.');

        try {
            $db = \Config\Database::connect();
        } catch (\Throwable $e) {
            CLI_LOG('critical', 'SeedHandymanCustomFields: [UP] Could not connect to DB — ' . $e->getMessage());
            return;
        }

        $db->resetDataCache();

        if (!$db->tableExists('custom_fields', false)) {
            CLI_LOG('warning', 'SeedHandymanCustomFields: [UP] Table "custom_fields" does not exist — skipping.');
            return;
        }

        $now = date('Y-m-d H:i:s');

        $hasFileConfig = $db->fieldExists('file_config', 'custom_fields');

        $translationsExist = $db->tableExists('translated_custom_fields', false);
        $langTableExists = $db->tableExists('languages', false);
        $useLanguageId = $translationsExist && $db->fieldExists('language_id', 'translated_custom_fields');
        $useLanguageCode = $translationsExist && $db->fieldExists('language_code', 'translated_custom_fields');

        $languages = [];
        if ($translationsExist && $langTableExists && ($useLanguageId || $useLanguageCode)) {
            try {
                $languages = $db->table('languages')->select(['id', 'code'])->get()->getResultArray();
            } catch (\Throwable $e) {
                $languages = [];
            }
        }

        foreach ($this->fields as $field) {
            try {
                $exists = $db->table('custom_fields')
                    ->where('field_label', $field['field_label'])
                    ->where('field_group', 'handyman_details')
                    ->countAllResults();
            } catch (\Throwable $e) {
                continue;
            }

            if ($exists > 0) {
                continue;
            }

            $insertData = [
                'field_label' => $field['field_label'],
                'field_type' => $field['field_type'],
                'field_group' => 'handyman_details',
                'required' => $field['required'],
                'visible' => $field['visible'],
                'sort_order' => $field['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasFileConfig) {
                $insertData['file_config'] = null;
            }

            try {
                $db->table('custom_fields')->insert($insertData);
                $insertedId = (int) $db->insertID();
            } catch (\Throwable $e) {
                continue;
            }

            if ($insertedId <= 0) {
                continue;
            }

            if (!$translationsExist || (!$useLanguageId && !$useLanguageCode) || empty($languages)) {
                continue;
            }

            foreach ($languages as $language) {
                $langId = (int) ($language['id'] ?? 0);
                $langCode = (string) ($language['code'] ?? '');

                if ($useLanguageId && $langId <= 0) {
                    continue;
                }
                if ($useLanguageCode && $langCode === '') {
                    continue;
                }

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
                    continue;
                }

                if ($translationExists > 0) {
                    continue;
                }

                $translationData = [
                    'custom_field_id' => $insertedId,
                    'field_label' => $field['field_label'],
                ];

                if ($useLanguageId) {
                    $translationData['language_id'] = $langId;
                } else {
                    $translationData['language_code'] = $langCode;
                }

                try {
                    $db->table('translated_custom_fields')->insert($translationData);
                } catch (\Throwable $e) {
                    // skip
                }
            }
        }

        // CLI_LOG('info', 'SeedHandymanCustomFields: [UP] Migration complete.');
    }

    public function down(): void
    {
        // CLI_LOG('info', 'SeedHandymanCustomFields: [DOWN] Starting rollback.');

        try {
            $db = \Config\Database::connect();
        } catch (\Throwable $e) {
            CLI_LOG('critical', 'SeedHandymanCustomFields: [DOWN] Could not connect to DB — ' . $e->getMessage());
            return;
        }

        if (!$db->tableExists('custom_fields')) {
            return;
        }

        $labels = array_column($this->fields, 'field_label');

        if ($db->tableExists('translated_custom_fields')) {
            try {
                $ids = $db->table('custom_fields')
                    ->select('id')
                    ->where('field_group', 'handyman_details')
                    ->whereIn('field_label', $labels)
                    ->get()
                    ->getResultArray();

                $idList = array_column($ids, 'id');

                if (!empty($idList)) {
                    $db->table('translated_custom_fields')
                        ->whereIn('custom_field_id', $idList)
                        ->delete();
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        try {
            $db->table('custom_fields')
                ->where('field_group', 'handyman_details')
                ->whereIn('field_label', $labels)
                ->delete();
        } catch (\Throwable $e) {
            // skip
        }

        // CLI_LOG('info', 'SeedHandymanCustomFields: [DOWN] Rollback complete.');
    }
}

if (!function_exists('CLI_LOG')) {
    function CLI_LOG(string $level, string $message): void
    {
        $prefixes = [
            'debug' => '  [DEBUG]',
            'info' => '   [INFO]',
            'notice' => ' [NOTICE]',
            'warning' => '[WARNING]',
            'error' => '  [ERROR]',
            'critical' => '  [CRIT!]',
            'alert' => '  [ALERT]',
            'emergency' => '  [ EMG ]',
        ];

        $prefix = $prefixes[$level] ?? '[  LOG  ]';
        echo $prefix . ' ' . $message . PHP_EOL;
    }
}
