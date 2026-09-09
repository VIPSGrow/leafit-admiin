<?php

namespace App\Services\CustomFields;

/**
 * CustomFieldsListMapper
 *
 * Maps raw DB rows to the bootstrap-table row format expected by the UI.
 * This keeps the main service focused on orchestration.
 */
class CustomFieldsListMapper
{
    /**
     * @param array<int,array<string,mixed>> $rowsRaw
     * @param array<string,bool> $permissions
     * @return array<int,array<string,mixed>>
     */
    public function mapRows(array $rowsRaw, array $permissions = []): array
    {
        $escape = static function ($value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $canUpdate = !empty($permissions['update']);
        $canDelete = !empty($permissions['delete']);

        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        $outRows = [];
        foreach ($rowsRaw as $r) {
            $required = (int) ($r['required'] ?? 0) === 1;
            $visible  = (int) ($r['visible'] ?? 0) === 1;

            // Resolve display label: current language → default language → base table
            $translations = $r['translations'] ?? [];
            $resolvedLabel = $translations[$currentLang]
                ?? $translations[$defaultLang]
                ?? ($r['field_label'] ?? '');

            $operations = '';
            if ($canUpdate) {
                $operations .= '<button type="button" class="btn btn-sm btn-outline-primary custom-fields-edit-btn mr-1" title="' . $escape(labels('edit', 'Edit')) . '"><i class="fas fa-pencil-alt"></i></button>';
            }
            if ($canDelete) {
                $operations .= '<button type="button" class="btn btn-sm btn-outline-danger custom-fields-delete-btn" title="' . $escape(labels('delete', 'Delete')) . '"><i class="fas fa-trash-alt"></i></button>';
            }

            $outRows[] = [
                'id'                => (int) ($r['id'] ?? 0),
                'field_label_plain' => $escape($resolvedLabel),
                'field_label'       => $escape($resolvedLabel),
                'field_type_plain'  => (string) ($r['field_type'] ?? ''),
                'field_type'        => $escape($r['field_type'] ?? ''),
                'field_group_plain' => (string) ($r['field_group'] ?? ''),
                'field_group'       => $escape($r['field_group'] ?? ''),
                'file_config_plain'       => $escape($r['file_config'] ?? ''),
                'required_raw'      => $required ? 1 : 0,
                'required'          => $required
                    ? '<span class="badge badge-success">' . $escape(labels('yes', 'Yes')) . '</span>'
                    : '<span class="badge badge-secondary">' . $escape(labels('no', 'No')) . '</span>',
                'visible_raw'       => $visible ? 1 : 0,
                'visible'           => $visible
                    ? '<span class="badge badge-success">' . $escape(labels('yes', 'Yes')) . '</span>'
                    : '<span class="badge badge-secondary">' . $escape(labels('no', 'No')) . '</span>',
                'sort_order'        => (int) ($r['sort_order'] ?? 0),
                'translations'      => $r['translations'] ?? [],
                'reorder_icon'      => '<i class="fas fa-sort text-new-primary drag-handle"></i>',

                // Row operations: inline action buttons
                'operations' => $operations,
            ];
        }

        return $outRows;
    }
}

