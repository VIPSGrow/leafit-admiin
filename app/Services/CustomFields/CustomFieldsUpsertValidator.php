<?php

namespace App\Services\CustomFields;

/**
 * CustomFieldsUpsertValidator
 *
 * Normalizes and validates the incoming create/update payload used by
 * CustomFieldsService. It returns either:
 * - ['error' => true, 'message' => string]
 * - or a normalized payload structure used by the model layer
 */
class CustomFieldsUpsertValidator
{
    /**
     * Maps UI category names to their resolved file extensions.
     */
    private const FILE_TYPE_CATEGORIES = [
        'images'    => ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg', '.tif', '.tiff'],
        'video'     => ['.mp4', '.mov', '.avi', '.mkv', '.webm', '.wmv', '.flv', '.m4v'],
        'audio'     => ['.mp3', '.wav', '.aac', '.ogg', '.flac', '.m4a', '.wma'],
        'documents' => ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.txt', '.csv', '.rtf'],
    ];

    /**
     * @return array<string,mixed>
     */
    public function normalizeAndValidateUpsert(array $post, bool $isUpdate): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($isUpdate && $id <= 0) {
            return [
                'error' => true,
                'message' => labels('invalid_request', 'Invalid request'),
            ];
        }

        $fieldType = trim((string) ($post['field_type'] ?? ''));
        $fieldGroup = trim((string) ($post['field_group'] ?? ''));
        $sortOrder = 0; // Managed by reorder; auto-assigned on create.
        $required = ((int) ($post['required'] ?? 0)) === 1 ? 1 : 0;
        $visible = ((int) ($post['visible'] ?? 0)) === 1 ? 1 : 0;
        $labelsByLanguage = $post['field_label'] ?? [];
        $labelsByLanguage = is_array($labelsByLanguage) ? $labelsByLanguage : [];

        $fileConfigRaw = $post['file_config'] ?? null;

        $allowedFieldTypes = ['text', 'number', 'textarea', 'file'];
        if (!in_array($fieldType, $allowedFieldTypes, true)) {
            return [
                'error' => true,
                'message' => labels('invalid_field_type', 'Invalid field type selected.'),
            ];
        }

        $allowedGroups = ['documents', 'bank_details', 'customer_address', 'handyman_details'];
        if (!in_array($fieldGroup, $allowedGroups, true)) {
            return [
                'error' => true,
                'message' => labels('invalid_field_group', 'Invalid field group selected.'),
            ];
        }

        // Enforce group-based type restrictions.
        if ($fieldGroup === 'documents' && $fieldType !== 'file') {
            return [
                'error' => true,
                'message' => labels('invalid_field_type_for_documents_group', 'Provider Documents group only supports the File field type.'),
            ];
        }
        if (in_array($fieldGroup, ['bank_details', 'customer_address'], true) && $fieldType === 'file') {
            return [
                'error' => true,
                'message' => labels('invalid_field_type_for_bank_address_group', 'Bank Details and Customer Address groups only support Text, Number, and Textarea field types.'),
            ];
        }

        // Normalize file-field configuration.
        //
        // Stored in DB as:
        // - `file_config` (TEXT JSON string)
        //   {allowed_types: [], max_size_mb: 0, max_files: 1}
        //
        // Conventions:
        // - `allowed_types` empty array => allow any file types
        // - `max_size_mb` = 0 => unlimited
        // - `max_files` >= 1
        $fileConfigNormalizedJson = null;
        if ($fieldType === 'file') {
            $fileConfig = [
                'allowed_types' => [],
                'max_size_mb'   => 0,
                'max_files'     => 1,
            ];

            // Accept either:
            // - JSON string
            // - already-parsed array
            if (is_string($fileConfigRaw)) {
                $raw = trim($fileConfigRaw);
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $fileConfig = array_merge($fileConfig, $decoded);
                    }
                }
            } elseif (is_array($fileConfigRaw)) {
                $fileConfig = array_merge($fileConfig, $fileConfigRaw);
            }

            $allowedTypes = $fileConfig['allowed_types'] ?? [];
            if ($allowedTypes === null) {
                $allowedTypes = [];
            }

            if (!is_array($allowedTypes)) {
                return [
                    'error' => true,
                    'message' => labels('invalid_file_allowed_types', 'Allowed file types must be an array.'),
                ];
            }

            if (empty($allowedTypes)) {
                return [
                    'error'   => true,
                    'message' => labels('file_types_required', 'Please select at least one allowed file type.'),
                ];
            }

            $normalizedTypes = [];
            foreach ($allowedTypes as $t) {
                $t = strtolower(trim((string) $t));
                if ($t === '') {
                    continue;
                }

                // Expand known category names to their resolved extensions.
                if (array_key_exists($t, self::FILE_TYPE_CATEGORIES)) {
                    foreach (self::FILE_TYPE_CATEGORIES[$t] as $ext) {
                        $normalizedTypes[] = $ext;
                    }
                    continue;
                }

                if (strpos($t, '/') === false) {
                    // Treat as extension token.
                    if ($t[0] !== '.') {
                        $t = '.' . $t;
                    }

                    if (!preg_match('/^\.[a-z0-9*._-]+$/', $t)) {
                        return [
                            'error' => true,
                            'message' => labels('invalid_file_allowed_types', 'Allowed file types contains an invalid extension token.'),
                        ];
                    }
                } else {
                    // Treat as mime token.
                    if (!preg_match('/^[a-z0-9+.-]+\/[a-z0-9+.-]+$/', $t)) {
                        return [
                            'error' => true,
                            'message' => labels('invalid_file_allowed_types', 'Allowed file types contains an invalid MIME token.'),
                        ];
                    }
                }

                $normalizedTypes[] = $t;
            }

            $normalizedTypes = array_values(array_unique($normalizedTypes));

            $maxSizeMb = (int) ($fileConfig['max_size_mb'] ?? 1);
            if ($maxSizeMb < 1) {
                $maxSizeMb = 1;
            }
            if ($maxSizeMb > 5) {
                $maxSizeMb = 5;
            }

            $maxFiles = (int) ($fileConfig['max_files'] ?? 1);
            if ($maxFiles < 1) {
                $maxFiles = 1;
            }

            $fileConfigNormalizedJson = json_encode([
                'allowed_types' => $normalizedTypes,
                'max_size_mb'   => $maxSizeMb,
                'max_files'     => $maxFiles,
            ], JSON_UNESCAPED_SLASHES);
        }

        $defaultLanguage = get_default_language();
        $defaultLabel = trim((string) ($labelsByLanguage[$defaultLanguage] ?? ''));
        if ($defaultLabel === '') {
            return [
                'error' => true,
                'message' => labels('default_language_label_required', 'Label is required for default language.'),
            ];
        }

        return [
            'error' => false,
            'id' => $id,
            'field_type' => $fieldType,
            'field_group' => $fieldGroup,
            'sort_order' => $sortOrder,
            'required' => $required,
            'visible' => $visible,
            'default_label' => $defaultLabel,
            'field_label_by_language' => $labelsByLanguage,
            'file_config' => $fileConfigNormalizedJson,
        ];
    }
}

