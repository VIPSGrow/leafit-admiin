<?php

namespace App\Models;

use CodeIgniter\Model;

class ReasonModel extends Model
{
    protected $table         = 'reasons';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'type',
        'reason',
        'needs_additional_info',
        'created_at',
        'updated_at',
    ];

    protected $reasonType = '';

    public function __construct()
    {
        parent::__construct();
        if (!empty($this->reasonType)) {
            $this->where('type', $this->reasonType);
        }
    }

    public function reasonsTableExists(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function translatedTableExists(): bool
    {
        return $this->db->tableExists('translated_reasons');
    }

    public function createWithTranslations(array $fieldData, array $labelsByLanguage): bool
    {
        if (!$this->reasonsTableExists() || !$this->translatedTableExists()) {
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

        $defaultLabel = trim((string) ($labelsByLanguage[$defaultLanguageCode] ?? ($fieldData['reason'] ?? '')));
        if ($defaultLabel === '') {
            return false;
        }

        $fieldData['reason'] = $defaultLabel;
        if (!empty($this->reasonType)) {
            $fieldData['type'] = $this->reasonType;
        }

        $this->db->transStart();
        $this->insert($fieldData);
        $reasonId = (int) $this->getInsertID();
        if ($reasonId <= 0) {
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
                'reason_id' => $reasonId,
                'language_id' => $langIdByCode[$code],
                'reason' => $label,
            ];
        }

        $defaultLangId = $langIdByCode[$defaultLanguageCode] ?? 0;
        if (empty($translationRows) && $defaultLangId > 0) {
            $translationRows[] = [
                'reason_id' => $reasonId,
                'language_id' => $defaultLangId,
                'reason' => $defaultLabel,
            ];
        } else {
            $hasDefaultTranslation = false;
            foreach ($translationRows as $row) {
                if (($row['language_id'] ?? 0) === $defaultLangId) {
                    $hasDefaultTranslation = true;
                    break;
                }
            }
            if (!$hasDefaultTranslation && $defaultLangId > 0) {
                $translationRows[] = [
                    'reason_id' => $reasonId,
                    'language_id' => $defaultLangId,
                    'reason' => $defaultLabel,
                ];
            }
        }

        $inserted = $this->db->table('translated_reasons')->insertBatch($translationRows);
        if ($inserted === false) {
            $this->db->transRollback();
            return false;
        }

        $this->db->transComplete();
        return $this->db->transStatus() !== false;
    }

    public function deleteWithRelations(int $reasonId): bool
    {
        if ($reasonId <= 0 || !$this->reasonsTableExists()) {
            return false;
        }

        $exists = $this->where('id', $reasonId)->countAllResults() > 0;
        if (!$exists) {
            return false;
        }

        $this->db->transStart();

        if ($this->translatedTableExists()) {
            $deletedTranslations = $this->db->table('translated_reasons')
                ->where('reason_id', $reasonId)
                ->delete();
            if ($deletedTranslations === false) {
                $this->db->transRollback();
                return false;
            }
        }

        $deletedReason = $this->where('id', $reasonId)->delete();
        if ($deletedReason === false) {
            $this->db->transRollback();
            return false;
        }

        $this->db->transComplete();
        return $this->db->transStatus() !== false;
    }

    public function updateWithTranslations(int $reasonId, array $fieldData, array $labelsByLanguage): bool
    {
        if ($reasonId <= 0 || !$this->reasonsTableExists()) {
            return false;
        }

        if (!$this->translatedTableExists()) {
            return false;
        }

        $exists = $this->where('id', $reasonId)->countAllResults() > 0;
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

        $fieldData['reason'] = $defaultLabel;

        $this->db->transStart();

        $updatedBase = $this->update($reasonId, $fieldData);
        if ($updatedBase === false) {
            $this->db->transRollback();
            return false;
        }

        $existingTranslations = $this->db->table('translated_reasons')
            ->select('language_id')
            ->where('reason_id', $reasonId)
            ->get()
            ->getResultArray();

        $existingLangIds = [];
        foreach ($existingTranslations as $row) {
            $lid = (int) ($row['language_id'] ?? 0);
            if ($lid > 0) {
                $existingLangIds[$lid] = true;
            }
        }

        $translationTable = $this->db->table('translated_reasons');

        foreach ($languageCodes as $code) {
            $label = trim((string) ($labelsByLanguage[$code] ?? ''));
            $langId = $langIdByCode[$code] ?? 0;
            if ($langId <= 0) {
                continue;
            }

            if ($code === $defaultLanguageCode) {
                $label = $defaultLabel;
            } else {
                if ($label === '') {
                    if (isset($existingLangIds[$langId])) {
                        $ok = $translationTable
                            ->where('reason_id', $reasonId)
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
                    ->where('reason_id', $reasonId)
                    ->where('language_id', $langId)
                    ->update(['reason' => $label]);
                if ($ok === false) {
                    $this->db->transRollback();
                    return false;
                }
            } else {
                $ok = $translationTable->insert([
                    'reason_id' => $reasonId,
                    'language_id'   => $langId,
                    'reason'   => $label,
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

    public function getPaginatedList(
        string $search = '',
        int $limit = 10,
        int $offset = 0,
        string $sort = 'id',
        string $order = 'DESC',
        array $permissions = []
    ): array {
        if (! $this->reasonsTableExists()) {
            return ['total' => 0, 'rows' => []];
        }

        $allowedSort = ['id', 'type', 'reason', 'needs_additional_info', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }
        $order = (strtoupper($order) === 'DESC') ? 'DESC' : 'ASC';

        $builder = $this->db->table($this->table);
        $builder->select(['id', 'type', 'reason', 'needs_additional_info', 'created_at']);

        if (!empty($this->reasonType)) {
            $builder->where('type', $this->reasonType);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('reason', $search)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults(false);

        $rows = $builder
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        $canUpdate = !empty($permissions['update']);
        $canDelete = !empty($permissions['delete']);

        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Fetch translations to allow pre-filling and localized display
        if (!empty($rows) && $this->translatedTableExists()) {
            $reasonIds = array_column($rows, 'id');
            $translations = $this->db->table('translated_reasons tr')
                ->select('tr.reason_id, tr.reason as translated_reason, l.code as language_code')
                ->join('languages l', 'l.id = tr.language_id')
                ->whereIn('tr.reason_id', $reasonIds)
                ->get()
                ->getResultArray();

            $translationsByReasonId = [];
            foreach ($translations as $t) {
                $translationsByReasonId[$t['reason_id']][$t['language_code']] = $t['translated_reason'];
            }

            foreach ($rows as &$row) {
                $row['translations'] = $translationsByReasonId[$row['id']] ?? [];
                
                // Resolve display reason: current language -> default language -> base table
                $resolvedReason = $row['translations'][$currentLang]
                    ?? $row['translations'][$defaultLang]
                    ?? $row['reason'];
                
                $row['reason'] = htmlspecialchars($resolvedReason, ENT_QUOTES, 'UTF-8');

                $row['needs_additional_info_badge'] = ($row['needs_additional_info'] == 1) ?
                    '<div class="badge badge-success">' . htmlspecialchars(labels('yes', 'Yes'), ENT_QUOTES, 'UTF-8') . '</div>' :
                    '<div class="badge badge-danger">' . htmlspecialchars(labels('no', 'No'), ENT_QUOTES, 'UTF-8') . '</div>';
                
                $operations = '';
                if ($canUpdate) {
                    $operations .= '<button type="button" class="btn btn-sm btn-outline-primary edit_reason mr-1" title="' . htmlspecialchars(labels('edit', 'Edit'), ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-pencil-alt"></i></button>';
                }
                if ($canDelete) {
                    $operations .= '<button type="button" class="btn btn-sm btn-outline-danger delete_reason" title="' . htmlspecialchars(labels('delete', 'Delete'), ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-trash-alt"></i></button>';
                }
                $row['operations'] = $operations;
            }
        } elseif (!empty($rows)) {
            // fallback if no translation table exists
            foreach ($rows as &$row) {
                $row['translations'] = [];
                $row['reason'] = htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8');
                $row['needs_additional_info_badge'] = ($row['needs_additional_info'] == 1) ?
                    '<div class="badge badge-success">' . htmlspecialchars(labels('yes', 'Yes'), ENT_QUOTES, 'UTF-8') . '</div>' :
                    '<div class="badge badge-danger">' . htmlspecialchars(labels('no', 'No'), ENT_QUOTES, 'UTF-8') . '</div>';
                
                $operations = '';
                if ($canUpdate) {
                    $operations .= '<button type="button" class="btn btn-sm btn-outline-primary edit_reason mr-1" title="' . htmlspecialchars(labels('edit', 'Edit'), ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-pencil-alt"></i></button>';
                }
                if ($canDelete) {
                    $operations .= '<button type="button" class="btn btn-sm btn-outline-danger delete_reason" title="' . htmlspecialchars(labels('delete', 'Delete'), ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-trash-alt"></i></button>';
                }
                $row['operations'] = $operations;
            }
        }

        return [
            'total' => $total,
            'rows'  => $rows,
        ];
    }
}
