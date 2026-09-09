<?php

namespace App\Services\CustomFields;

use App\Models\CustomFieldModel;
use Throwable;

// CustomFieldsService
// Business logic for admin custom fields.
class CustomFieldsService
{
    private CustomFieldModel $model;
    private CustomFieldsListMapper $listMapper;
    private CustomFieldsUpsertValidator $upsertValidator;

    // Groups that must always retain at least one field and one visible field.
    private const PROTECTED_GROUPS = ['bank_details', 'customer_address'];

    public function __construct(?CustomFieldModel $model = null)
    {
        $this->model = $model ?? new CustomFieldModel();
        $this->listMapper = new CustomFieldsListMapper();
        $this->upsertValidator = new CustomFieldsUpsertValidator();
    }

    public function list(
        string $search = '',
        int $limit = 10,
        int $offset = 0,
        string $sort = 'id',
        string $order = 'DESC',
        string $group = '',
        array $permissions = []
    ): array {
        $data = $this->model->getPaginatedList(
            search: $search,
            limit: $limit,
            offset: $offset,
            sort: $sort,
            order: $order,
            group: $group
        );

        $total = (int) ($data['total'] ?? 0);
        $rowsRaw = (array) ($data['rows'] ?? []);

        // Load translations for all rows in one query so the edit modal can prefill them.
        $fieldIds = array_filter(array_map(fn ($r) => (int) ($r['id'] ?? 0), $rowsRaw));
        $translationsByFieldId = [];
        if (!empty($fieldIds) && $this->model->translatedTableExists()) {
            $db = \Config\Database::connect();
            $tRows = $db->table('translated_custom_fields tcf')
                ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                ->join('languages l', 'l.id = tcf.language_id')
                ->whereIn('tcf.custom_field_id', $fieldIds)
                ->get()
                ->getResultArray();
            foreach ($tRows as $tr) {
                $translationsByFieldId[(int) $tr['custom_field_id']][(string) $tr['language_code']] = (string) $tr['field_label'];
            }
        }

        // Attach translations to each row before mapping.
        foreach ($rowsRaw as &$row) {
            $row['translations'] = $translationsByFieldId[(int) ($row['id'] ?? 0)] ?? [];
        }
        unset($row);

        $outRows = $this->listMapper->mapRows($rowsRaw, $permissions);

        return [
            'total' => $total,
            'rows'  => $outRows,
        ];
    }

    public function create(array $post): array
    {
        try {
            $payload = $this->upsertValidator->normalizeAndValidateUpsert($post, false);
            if ($payload['error']) {
                return $payload;
            }

            $defaultLanguage = get_default_language();
            $defaultLabel = $payload['default_label'];
            if ($this->model->existsByLabelForLanguage($defaultLabel, $defaultLanguage)) {
                return [
                    'error' => true,
                    'message' => labels('label_already_exists', 'A custom field with this label already exists.'),
                ];
            }

            // Auto-assign sort_order: new fields always go to the end.
            $db = \Config\Database::connect();
            $maxSortOrder = (int) $db->table('custom_fields')
                ->selectMax('sort_order')
                ->where('field_group', $payload['field_group'])
                ->get()
                ->getRow()
                ->sort_order ?? 0;

            $saved = $this->model->createWithTranslations(
                fieldData: [
                    'field_label' => $payload['default_label'],
                    'field_type'  => $payload['field_type'],
                    'field_group' => $payload['field_group'],
                    'required'    => $payload['required'],
                    'visible'     => $payload['visible'],
                    'sort_order'  => $maxSortOrder + 1,
                    'file_config'       => $payload['file_config'],
                ],
                labelsByLanguage: $payload['field_label_by_language']
            );

            if (!$saved) {
                return [
                    'error' => true,
                    'message' => labels('failed_to_save_custom_field', 'Failed to save custom field.'),
                ];
            }

            return [
                'error' => false,
                'message' => labels('data_saved_successfully', 'Data saved successfully'),
            ];
        } catch (Throwable $e) {
            log_message('error', 'Exception in CustomFieldsService::create() - ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
            ];
        }
    }

    public function update(array $post): array
    {
        try {
            $payload = $this->upsertValidator->normalizeAndValidateUpsert($post, true);
            if ($payload['error']) {
                return $payload;
            }

            $id = (int) $payload['id'];

            $existing = $this->model->find($id);
            if (!$existing) {
                return [
                    'error'   => true,
                    'message' => labels('data_not_found', 'Data not found'),
                ];
            }

            // If making a field invisible in a protected group, ensure at least one other visible field remains.
            if (in_array($payload['field_group'], self::PROTECTED_GROUPS, true) && (int) $payload['visible'] === 0) {
                if ((int) ($existing['visible'] ?? 0) === 1) {
                    $visibleCount = $this->model->countVisibleByGroup($payload['field_group']);
                    if ($visibleCount <= 1) {
                        return [
                            'error'   => true,
                            'message' => labels('cannot_hide_last_custom_field', 'At least one custom field must remain visible.'),
                        ];
                    }
                }
            }

            // Label uniqueness check for default language.
            $defaultLanguage = get_default_language();
            $defaultLabel = $payload['default_label'];
            if ($this->model->existsByLabelForLanguageExceptId($defaultLabel, $defaultLanguage, $id)) {
                return [
                    'error' => true,
                    'message' => labels('label_already_exists', 'A custom field with this label already exists.'),
                ];
            }

            // sort_order is managed via reorder — do not update it here.
            $updated = $this->model->updateWithTranslations(
                customFieldId: $id,
                fieldData: [
                    'field_type' => $payload['field_type'],
                    'field_group' => $payload['field_group'],
                    'required'   => $payload['required'],
                    'visible'    => $payload['visible'],
                    'file_config'       => $payload['file_config'],
                ],
                labelsByLanguage: $payload['field_label_by_language']
            );

            if (!$updated) {
                return [
                    'error' => true,
                    'message' => labels('failed_to_save_custom_field', 'Failed to update custom field.'),
                ];
            }

            return [
                'error' => false,
                'message' => labels('data_updated_successfully', 'Data updated successfully'),
            ];
        } catch (Throwable $e) {
            log_message('error', 'Exception in CustomFieldsService::update() - ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
            ];
        }
    }

    public function delete(array $post): array
    {
        try {
            $id = (int) ($post['id'] ?? 0);
            if ($id <= 0) {
                return [
                    'error' => true,
                    'message' => labels('invalid_request', 'Invalid request'),
                ];
            }

            // Check if the record exists before attempting deletion.
            $existing = $this->model->find($id);
            if (!$existing) {
                return [
                    'error'   => true,
                    'message' => labels('data_not_found', 'Data not found'),
                ];
            }

            // Protected groups must always retain at least one custom field.
            if (in_array($existing['field_group'] ?? '', self::PROTECTED_GROUPS, true)) {
                $group = $existing['field_group'];
                $totalCount = $this->model->countByGroup($group);
                if ($totalCount <= 1) {
                    return [
                        'error'   => true,
                        'message' => labels('cannot_delete_last_custom_field', 'At least one custom field must exist. You cannot delete the last field.'),
                    ];
                }

                // If deleting a visible field and it's the only visible one, block deletion.
                if ((int) ($existing['visible'] ?? 0) === 1) {
                    $visibleCount = $this->model->countVisibleByGroup($group);
                    if ($visibleCount <= 1) {
                        return [
                            'error'   => true,
                            'message' => labels('cannot_delete_last_visible_custom_field', 'At least one custom field must remain visible. Make another field visible before deleting this one.'),
                        ];
                    }
                }
            }

            $deleted = $this->model->deleteWithRelations($id);
            if (!$deleted) {
                return [
                    'error' => true,
                    'message' => labels('failed_to_delete_custom_field', 'Failed to delete custom field.'),
                ];
            }

            return [
                'error' => false,
                'message' => labels('data_deleted_successfully', 'Data deleted successfully'),
            ];
        } catch (Throwable $e) {
            log_message('error', 'Exception in CustomFieldsService::delete() - ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
            ];
        }
    }
}

