<?php

namespace App\Services\Provider;

use CodeIgniter\HTTP\IncomingRequest;
use Config\Database;

/**
 * Shared partner-custom-field plumbing for ProviderCreateService +
 * ProviderUpdateService. Owns the query/upsert logic against
 * `partner_custom_fields` + `custom_fields` so the two flows can call
 * one source of truth instead of carrying duplicate helpers.
 *
 * Phase 3 step 6 of the Provider refactor — finalizes the helper duplication
 * intentionally left during steps 1 + 2.
 */
class ProviderCustomFieldsService
{
    /**
     * Insert/update per-field values for a partner.
     *
     * Values keyed by custom_field_id. Empty / null / [] values cause the
     * corresponding partner_custom_fields row to be deleted. Other values
     * are upserted via INSERT ... ON DUPLICATE KEY UPDATE.
     */
    public function upsert(int $partnerId, array $valuesById): void
    {
        if ($partnerId <= 0 || empty($valuesById)) {
            return;
        }

        $db = Database::connect();

        $valuesSql   = [];
        $deletionIds = [];

        foreach ($valuesById as $customFieldId => $rawValue) {
            $customFieldId = (int) $customFieldId;
            if ($customFieldId <= 0) {
                continue;
            }

            if (is_array($rawValue)) {
                $rawValue = json_encode($rawValue, JSON_UNESCAPED_SLASHES);
            }

            if ($rawValue === '' || $rawValue === [] || $rawValue === null) {
                $deletionIds[] = $customFieldId;
                continue;
            }

            $valuesSql[] = '(' . $partnerId . ',' . $customFieldId . ',' . $db->escape((string) $rawValue) . ')';
        }

        if (!empty($deletionIds)) {
            $db->table('partner_custom_fields')
                ->where('partner_id', $partnerId)
                ->whereIn('custom_field_id', array_values(array_unique($deletionIds)))
                ->delete();
        }

        if (empty($valuesSql)) {
            return;
        }

        $sql = 'INSERT INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
            . implode(',', $valuesSql)
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';

        $db->query($sql);
    }

    /**
     * Build a [custom_field_id => value] map from the request, scoped to
     * visible custom_fields in `documents` + `bank_details` groups. For
     * file-type fields, prefers the pre-resolved upload path passed by the
     * caller.
     */
    public function collectFromRequest(IncomingRequest $request, array $uploadedFileValues = []): array
    {
        $db = Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $customFieldRows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->get()
            ->getResultArray();

        $valuesById = [];
        foreach ($customFieldRows as $fieldRow) {
            $cfId = (int) ($fieldRow['id'] ?? 0);
            if ($cfId <= 0) {
                continue;
            }

            $fieldType = strtolower(trim((string) ($fieldRow['field_type'] ?? 'text')));
            $inputName = 'cf_' . $cfId;

            if ($fieldType === 'file') {
                if (array_key_exists($cfId, $uploadedFileValues)) {
                    $valuesById[$cfId] = $uploadedFileValues[$cfId];
                }
                continue;
            }

            $valuesById[$cfId] = $request->getPost($inputName) ?? '';
        }

        return $valuesById;
    }

    /**
     * Fetch existing partner_custom_fields values for the given partner +
     * custom_field id list. Returned as [custom_field_id => value].
     */
    public function getValuesById(int $partnerId, array $fieldIds): array
    {
        if ($partnerId <= 0 || empty($fieldIds)) {
            return [];
        }

        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), fn ($id) => $id > 0));
        if (empty($fieldIds)) {
            return [];
        }

        $db = Database::connect();

        $valueRows = $db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', $partnerId)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($valueRows as $row) {
            $out[(int) $row['custom_field_id']] = $row['value'] ?? null;
        }
        return $out;
    }
}
