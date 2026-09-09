<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Centralizes all custom-field file uploads into a single
 * `public/uploads/custom_fields/` directory by mass-migrating files
 * from legacy source folders, and drops the now-unnecessary
 * `field_key` column from `custom_fields`.
 */
class RemoveFieldKeyAndCentralizeFiles extends Migration
{
    private const NEW_DIR = 'public/uploads/custom_fields/';

    /**
     * Known legacy source folders to move files from.
     */
    private const LEGACY_DIRS = [
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
    
        if (!$db->tableExists('custom_fields')) {
            return;
        }

        // --- 1. Ensure target directory exists ---
        $targetDir = rtrim(ROOTPATH . self::NEW_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!is_dir($targetDir)) {
            return;
        }
        
        // --- 2. Mass-migrate all files from legacy source folders ---
        $this->migrateLegacyDirectories($targetDir);

        // --- 3. Drop field_key column ---
        if ($db->fieldExists('field_key', 'custom_fields')) {
            try {
                $this->forge->dropKey('custom_fields', 'uk_field_key');
            } catch (\Throwable $e) {
               
            }

            $this->forge->dropColumn('custom_fields', 'field_key');
        }
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        // Add field_key column back (data loss — field_key values cannot be restored)
        if ($db->tableExists('custom_fields') && !$db->fieldExists('field_key', 'custom_fields')) {
            $this->forge->addColumn('custom_fields', [
                'field_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => false,
                    'default'    => '',
                    'after'      => 'id',
                ],
            ]);
        }
    }

    private function migrateLegacyDirectories(string $targetDir): void
    {
        $migrated = 0;
        $skipped = 0;
        $missingDirs = 0;

        foreach ($this->legacySourceDirectories() as $sourceDir) {
            if (!is_dir($sourceDir)) {
                $missingDirs++;
                continue;
            }

            $entries = @scandir($sourceDir);
            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $oldFullPath = $sourceDir . $entry;
                if (!is_file($oldFullPath)) {
                    continue;
                }

                [$newPath, $newFullPath] = $this->resolveTargetPath($entry, $oldFullPath, $targetDir);
                $moved = $this->moveFile($oldFullPath, $newFullPath);

                if ($moved) {
                    $migrated++;
                } else {
                    $skipped++;
                }
            }
        }
    }

    private function moveFile(string $oldFullPath, string $newFullPath): bool
    {
        if ($oldFullPath === $newFullPath) {
            return true;
        }

        // Prefer rename (fast). If it fails (cross-device/permissions), fallback to copy+unlink.
        $moved = @rename($oldFullPath, $newFullPath);
        if (! $moved) {
            $copied = @copy($oldFullPath, $newFullPath);
            if ($copied) {
                @unlink($oldFullPath);
                $moved = true;
            }
        }

        return $moved;
    }

    /**
     * @return list<string>
     */
    private function legacySourceDirectories(): array
    {
        $dirs = [];
        foreach (self::LEGACY_DIRS as $legacyDir) {
            $normalized = str_replace('/', DIRECTORY_SEPARATOR, $legacyDir);
            $dirs[] = rtrim(ROOTPATH . $normalized, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $dirs[] = rtrim(FCPATH . $normalized, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return array_values(array_unique($dirs));
    }

    /**
     * @return array{0:string,1:string} [newRelativePath, newAbsolutePath]
     */
    private function resolveTargetPath(string $filename, string $oldFullPath, string $targetDir): array
    {
        $basePath = self::NEW_DIR . $filename;
        $baseFullPath = $targetDir . $filename;

        if (! is_file($baseFullPath)) {
            return [$basePath, $baseFullPath];
        }

        // If destination already exists with same content, re-use it.
        if ($this->filesMatch($oldFullPath, $baseFullPath)) {
            return [$basePath, $baseFullPath];
        }

        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $name = $name !== '' ? $name : 'file';
        $ext  = $ext !== '' ? '.' . $ext : '';

        $counter = 1;
        do {
            $candidateName = $name . '_' . $counter . $ext;
            $candidatePath = self::NEW_DIR . $candidateName;
            $candidateFullPath = $targetDir . $candidateName;

            if (! is_file($candidateFullPath)) {
                return [$candidatePath, $candidateFullPath];
            }

            if ($this->filesMatch($oldFullPath, $candidateFullPath)) {
                return [$candidatePath, $candidateFullPath];
            }

            $counter++;
        } while ($counter < 1000);

        $fallbackName = $name . '_' . time() . $ext;
        return [self::NEW_DIR . $fallbackName, $targetDir . $fallbackName];
    }

    private function filesMatch(string $a, string $b): bool
    {
        if (! is_file($a) || ! is_file($b)) {
            return false;
        }

        $sizeA = @filesize($a);
        $sizeB = @filesize($b);
        if ($sizeA === false || $sizeB === false || $sizeA !== $sizeB) {
            return false;
        }

        $hashA = @md5_file($a);
        $hashB = @md5_file($b);

        return $hashA !== false && $hashA === $hashB;
    }
}
