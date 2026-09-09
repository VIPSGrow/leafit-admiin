<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fallback migration for environments where
 * 2026-04-03-063535_RemoveFieldKeyAndCentralizeFiles was executed
 * but partner_custom_fields.value still points to legacy paths.
 */
class BackfillPartnerCustomFieldCentralizedPaths extends Migration
{
    private const NEW_DIR = 'public/uploads/custom_fields/';

    /**
     * Legacy DB prefixes we need to rewrite in partner_custom_fields.value.
     */
    private const LEGACY_PREFIXES = [
        'public/backend/assets/passport/',
        'backend/assets/passport/',
        'public/backend/assets/national_id/',
        'backend/assets/national_id/',
        'public/backend/assets/address_id/',
        'backend/assets/address_id/',
    ];

    public function up(): void
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('partner_custom_fields')) {
            return;
        }

        $targetDir = rtrim(ROOTPATH . self::NEW_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $rows = $this->legacyPathRows($db);
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $value = (string) ($row['value'] ?? '');
            if ($id <= 0 || $value === '') {
                continue;
            }

            $newValue = $this->resolveNewValue($value, $targetDir);
            if ($newValue === null || $newValue === $value) {
                continue;
            }

            $db->table('partner_custom_fields')
                ->where('id', $id)
                ->update(['value' => $newValue]);
        }
    }

    public function down(): void
    {
        // No safe rollback: legacy source folder/type information was intentionally removed.
    }

    /**
     * @return array<int,array{id:mixed,value:mixed}>
     */
    private function legacyPathRows($db): array
    {
        $likes = [];
        foreach (self::LEGACY_PREFIXES as $prefix) {
            $likes[] = "value LIKE '" . $db->escapeLikeString($prefix) . "%' ESCAPE '!'";
        }

        if ($likes === []) {
            return [];
        }

        return $db->table('partner_custom_fields')
            ->select('id, value')
            ->where('value IS NOT NULL', null, false)
            ->where('(' . implode(' OR ', $likes) . ')', null, false)
            ->get()
            ->getResultArray();
    }

    private function resolveNewValue(string $legacyValue, string $targetDir): ?string
    {
        if (! $this->startsWithLegacyPrefix($legacyValue)) {
            return null;
        }

        $filename = basename($legacyValue);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $newRelativePath = self::NEW_DIR . $filename;
        $newAbsolutePath = $targetDir . $filename;

        // Standard rewrite to centralized path.
        if (is_file($newAbsolutePath)) {
            return $newRelativePath;
        }

        // If the original migration renamed due to collisions, map to a matching suffixed filename.
        $alternateFilename = $this->findAlternateFilename($filename, $targetDir);
        if ($alternateFilename !== null) {
            return self::NEW_DIR . $alternateFilename;
        }

        // Fall back to canonical centralized path even when file cannot be checked here.
        return $newRelativePath;
    }

    private function startsWithLegacyPrefix(string $value): bool
    {
        foreach (self::LEGACY_PREFIXES as $prefix) {
            if (strpos($value, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function findAlternateFilename(string $filename, string $targetDir): ?string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($name === '') {
            return null;
        }

        $quotedName = preg_quote($name, '/');
        $quotedExt = preg_quote($ext, '/');
        $pattern = '/^' . $quotedName . '_\\d+' . ($ext !== '' ? '\\.' . $quotedExt : '') . '$/';

        $entries = @scandir($targetDir);
        if ($entries === false) {
            return null;
        }

        $matches = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (preg_match($pattern, $entry) === 1) {
                $matches[] = $entry;
            }
        }

        if ($matches === []) {
            return null;
        }

        sort($matches, SORT_STRING);
        return $matches[0];
    }
}
