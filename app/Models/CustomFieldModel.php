<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * CustomFieldModel (table: custom_fields)
 * DB access for custom field definitions.
 */
class CustomFieldModel extends Model
{
    protected $table         = 'custom_fields';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'field_label',
        'field_type',
        'field_group',
        'file_config',
        'required',
        'visible',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    public function customFieldsTableExists(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function translatedTableExists(): bool
    {
        return $this->db->tableExists('translated_custom_fields');
    }

    public function partnerCustomFieldsTableExists(): bool
    {
        return $this->db->tableExists('partner_custom_fields');
    }

    public function customerAddressCustomFieldsTableExists(): bool
    {
        return $this->db->tableExists('customer_address_custom_fields');
    }

    /**
     * Check if a label already exists for a given language code in translated_custom_fields.
     */
    public function existsByLabelForLanguage(string $label, string $languageCode): bool
    {
        if ($label === '' || $languageCode === '' || !$this->translatedTableExists()) {
            return false;
        }

        return $this->db->table('translated_custom_fields tcf')
            ->join('languages l', 'l.id = tcf.language_id')
            ->where('l.code', $languageCode)
            ->where('tcf.field_label', $label)
            ->countAllResults() > 0;
    }

    /**
     * Check if a label already exists for a given language, excluding a specific custom field.
     */
    public function existsByLabelForLanguageExceptId(string $label, string $languageCode, int $excludeId): bool
    {
        if ($label === '' || $languageCode === '' || $excludeId <= 0 || !$this->translatedTableExists()) {
            return false;
        }

        return $this->db->table('translated_custom_fields tcf')
            ->join('languages l', 'l.id = tcf.language_id')
            ->where('l.code', $languageCode)
            ->where('tcf.field_label', $label)
            ->where('tcf.custom_field_id !=', $excludeId)
            ->countAllResults() > 0;
    }

    /**
     * Resolve a language code to the languages.id.
     */
    public function resolveLanguageId(string $code): int
    {
        if ($code === '') {
            return 0;
        }

        $row = $this->db->table('languages')
            ->select('id')
            ->where('code', $code)
            ->get()
            ->getRow();

        return $row ? (int) $row->id : 0;
    }

    /**
     * Build a code → id map for a set of language codes.
     */
    public function resolveLanguageIds(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $rows = $this->db->table('languages')
            ->select(['id', 'code'])
            ->whereIn('code', $codes)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }
        return $map;
    }

    /**
     * Create a custom field and insert its translated labels.
     *
     * @param array<string,mixed> $fieldData
     * @param array<string,string> $labelsByLanguage
     */
    public function createWithTranslations(array $fieldData, array $labelsByLanguage): bool
    {
        if (!$this->customFieldsTableExists()) {
            return false;
        }

        // Business rule: every custom field must also have translation rows.
        // If translation table is missing, do not create partial data.
        if (!$this->translatedTableExists()) {
            return false;
        }

        $languages = fetch_details(
            'languages',
            [],
            ['id', 'code', 'is_default'],
            "",
            '0',
            'id',
            'ASC'
        );

        if (empty($languages)) {
            return false;
        }

        $languageCodes = [];
        $langIdByCode = [];
        foreach ($languages as $language) {
            $code = (string) ($language['code'] ?? '');
            $langId = (int) ($language['id'] ?? 0);
            if ($code !== '' && $langId > 0) {
                $languageCodes[] = $code;
                $langIdByCode[$code] = $langId;
            }
        }
        $languageCodes = array_values(array_unique($languageCodes));
        if (empty($languageCodes)) {
            return false;
        }

        // Resolve default language from DB (same source of truth used by language module).
        $defaultLanguageCode = '';
        foreach ($languages as $language) {
            if (!empty($language['is_default']) && !empty($language['code'])) {
                $defaultLanguageCode = (string) $language['code'];
                break;
            }
        }
        if ($defaultLanguageCode === '' || !in_array($defaultLanguageCode, $languageCodes, true)) {
            $defaultLanguageCode = (string) $languageCodes[0];
        }

        $defaultLabel = trim((string) ($labelsByLanguage[$defaultLanguageCode] ?? ($fieldData['field_label'] ?? '')));
        if ($defaultLabel === '') {
            return false;
        }

        // Keep base table aligned with default language label.
        $fieldData['field_label'] = $defaultLabel;

        $this->db->transStart();
        $this->insert($fieldData);
        $customFieldId = (int) $this->getInsertID();
        if ($customFieldId <= 0) {
            $this->db->transRollback();
            return false;
        }

        $translationRows = [];
        foreach ($languageCodes as $code) {
            $label = trim((string) ($labelsByLanguage[$code] ?? ''));
            if ($label === '' || !isset($langIdByCode[$code])) {
                continue;
            }

            $translationRows[] = [
                'custom_field_id' => $customFieldId,
                'language_id' => $langIdByCode[$code],
                'field_label' => $label,
            ];
        }

        // Ensure at least one translation row exists and points to the new base record.
        $defaultLangId = $langIdByCode[$defaultLanguageCode] ?? 0;
        if (empty($translationRows) && $defaultLangId > 0) {
            $translationRows[] = [
                'custom_field_id' => $customFieldId,
                'language_id' => $defaultLangId,
                'field_label' => $defaultLabel,
            ];
        } else {
            // Ensure default language translation always exists.
            $hasDefaultTranslation = false;
            foreach ($translationRows as $row) {
                if (($row['language_id'] ?? 0) === $defaultLangId) {
                    $hasDefaultTranslation = true;
                    break;
                }
            }
            if (!$hasDefaultTranslation && $defaultLangId > 0) {
                $translationRows[] = [
                    'custom_field_id' => $customFieldId,
                    'language_id' => $defaultLangId,
                    'field_label' => $defaultLabel,
                ];
            }
        }

        $inserted = $this->db->table('translated_custom_fields')->insertBatch($translationRows);
        if ($inserted === false) {
            $this->db->transRollback();
            return false;
        }

        $this->db->transComplete();
        return $this->db->transStatus() !== false;
    }

    /**
     * Delete a custom field and all related rows in dependent tables.
     *
     * Why this is handled in model:
     * - Controller stays focused on validation/response only.
     * - DB consistency is guaranteed in one transaction.
     */
    public function deleteWithRelations(int $customFieldId): bool
    {
        if ($customFieldId <= 0 || !$this->customFieldsTableExists()) {
            return false;
        }

        $exists = $this->where('id', $customFieldId)->countAllResults() > 0;
        if (!$exists) {
            return false;
        }

        $this->db->transStart();

        // Always remove translation rows explicitly.
        // Even with FK cascade, this keeps behavior explicit and predictable.
        if ($this->translatedTableExists()) {
            $deletedTranslations = $this->db->table('translated_custom_fields')
                ->where('custom_field_id', $customFieldId)
                ->delete();
            if ($deletedTranslations === false) {
                $this->db->transRollback();
                return false;
            }
        }

        // Remove partner values explicitly as a safe fallback when FK is missing.
        if ($this->partnerCustomFieldsTableExists()) {
            $deletedValues = $this->db->table('partner_custom_fields')
                ->where('custom_field_id', $customFieldId)
                ->delete();
            if ($deletedValues === false) {
                $this->db->transRollback();
                return false;
            }
        }

        // Remove customer address values explicitly as a safe fallback when FK is missing.
        if ($this->customerAddressCustomFieldsTableExists()) {
            $deletedAddressValues = $this->db->table('customer_address_custom_fields')
                ->where('custom_field_id', $customFieldId)
                ->delete();
            if ($deletedAddressValues === false) {
                $this->db->transRollback();
                return false;
            }
        }

        $deletedField = $this->where('id', $customFieldId)->delete();
        if ($deletedField === false) {
            $this->db->transRollback();
            return false;
        }

        $this->db->transComplete();
        return $this->db->transStatus() !== false;
    }

    /**
     * Update a custom field definition and its translated labels.
     *
     * Rules:
     * - Base row is always updated (type/group/required/visible/sort_order).
     * - Default language label is always required and will be updated.
     * - For non-default languages, empty labels are treated as "do not change"
     *   (so editing the default language won't wipe other translations).
     */
    public function updateWithTranslations(int $customFieldId, array $fieldData, array $labelsByLanguage): bool
    {
        if ($customFieldId <= 0 || !$this->customFieldsTableExists()) {
            return false;
        }

        if (!$this->translatedTableExists()) {
            return false;
        }

        $exists = $this->where('id', $customFieldId)->countAllResults() > 0;
        if (!$exists) {
            return false;
        }

        $languages = fetch_details(
            'languages',
            [],
            ['id', 'code', 'is_default'],
            "",
            '0',
            'id',
            'ASC'
        );

        if (empty($languages)) {
            return false;
        }

        $languageCodes = [];
        $langIdByCode = [];
        $defaultLanguageCode = '';
        foreach ($languages as $language) {
            $code = (string) ($language['code'] ?? '');
            $langId = (int) ($language['id'] ?? 0);
            if ($code !== '' && $langId > 0) {
                $languageCodes[] = $code;
                $langIdByCode[$code] = $langId;
            }

            if (!empty($language['is_default']) && !empty($language['code'])) {
                $defaultLanguageCode = (string) $language['code'];
            }
        }

        $languageCodes = array_values(array_unique($languageCodes));
        if (empty($languageCodes)) {
            return false;
        }

        if ($defaultLanguageCode === '' || !in_array($defaultLanguageCode, $languageCodes, true)) {
            $defaultLanguageCode = (string) $languageCodes[0];
        }

        $defaultLabel = trim((string) ($labelsByLanguage[$defaultLanguageCode] ?? ''));
        if ($defaultLabel === '') {
            return false;
        }

        // Align base record field_label with default language label.
        $fieldData['field_label'] = $defaultLabel;

        $this->db->transStart();

        $updatedBase = $this->update($customFieldId, $fieldData);
        if ($updatedBase === false) {
            $this->db->transRollback();
            return false;
        }

        // Load existing translation language IDs so we can update vs insert.
        $existingTranslations = $this->db->table('translated_custom_fields')
            ->select('language_id')
            ->where('custom_field_id', $customFieldId)
            ->get()
            ->getResultArray();

        $existingLangIds = [];
        foreach ($existingTranslations as $row) {
            $lid = (int) ($row['language_id'] ?? 0);
            if ($lid > 0) {
                $existingLangIds[$lid] = true;
            }
        }

        $translationTable = $this->db->table('translated_custom_fields');

        // Update/insert translations only for languages with non-empty labels.
        foreach ($languageCodes as $code) {
            $label = trim((string) ($labelsByLanguage[$code] ?? ''));
            $langId = $langIdByCode[$code] ?? 0;
            if ($langId <= 0) {
                continue;
            }

            if ($code === $defaultLanguageCode) {
                $label = $defaultLabel;
            } else {
                // Empty label for non-default language: delete existing translation.
                if ($label === '') {
                    if (isset($existingLangIds[$langId])) {
                        $ok = $translationTable
                            ->where('custom_field_id', $customFieldId)
                            ->where('language_id', $langId)
                            ->delete();
                        if ($ok === false) {
                            $this->db->transRollback();
                            return false;
                        }
                    }
                    continue;
                }
            }

            if (isset($existingLangIds[$langId])) {
                $ok = $translationTable
                    ->where('custom_field_id', $customFieldId)
                    ->where('language_id', $langId)
                    ->update(['field_label' => $label]);
                if ($ok === false) {
                    $this->db->transRollback();
                    return false;
                }
            } else {
                $ok = $translationTable->insert([
                    'custom_field_id' => $customFieldId,
                    'language_id'     => $langId,
                    'field_label'     => $label,
                ]);
                if ($ok === false) {
                    $this->db->transRollback();
                    return false;
                }
            }
        }

        $this->db->transComplete();
        return $this->db->transStatus() !== false;
    }

    /**
     * Count total custom fields in a given group.
     */
    public function countByGroup(string $group): int
    {
        if ($group === '' || !$this->customFieldsTableExists()) {
            return 0;
        }

        return (int) $this->where('field_group', $group)->countAllResults();
    }

    /**
     * Count visible custom fields in a given group.
     */
    public function countVisibleByGroup(string $group): int
    {
        if ($group === '' || !$this->customFieldsTableExists()) {
            return 0;
        }

        return (int) $this->where('field_group', $group)
            ->where('visible', 1)
            ->countAllResults();
    }

    /**
     * Get paginated custom fields for Bootstrap Table.
     *
     * This uses the Model's DB connection (`$this->db`) and Query Builder.
     * No manual database connection setup is needed.
     *
     * @return array{total:int, rows:array<int,array<string,mixed>>}
     */
    public function getPaginatedList(
        string $search = '',
        int $limit = 10,
        int $offset = 0,
        string $sort = 'id',
        string $order = 'DESC',
        string $group = ''
    ): array {
        // If the table does not exist yet (fresh install / migrations not run),
        // return an empty dataset so the UI can still load safely.
        if (! $this->db->tableExists($this->table)) {
            return ['total' => 0, 'rows' => []];
        }

        // Whitelist sortable columns to avoid SQL injection via `sort`.
        $allowedSort = [
            'id',
            'field_label',
            'field_type',
            'field_group',
            'required',
            'visible',
            'sort_order',
        ];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }
        $order = (strtoupper($order) === 'DESC') ? 'DESC' : 'ASC';

        $builder = $this->db->table($this->table);
        $builder->select([
            'id',
            'field_label',
            'field_type',
            'field_group',
            'file_config',
            'required',
            'visible',
            'sort_order',
        ]);

        if ($group !== '') {
            $builder->where('field_group', $group);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('field_label', $search)
                ->orLike('field_type', $search)
                ->orLike('field_group', $search)
                ->groupEnd();
        }

        // Total count (clone builder so filters stay identical).
        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults(false);

        $rows = $builder
            ->orderBy($sort, $order)
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        return [
            'total' => $total,
            'rows'  => $rows,
        ];
    }
}

