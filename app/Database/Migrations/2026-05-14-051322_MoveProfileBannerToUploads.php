<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Move legacy image storage from public/backend/assets/ into the
 * centralized public/uploads/ tree, and rewrite stored DB paths
 * (users.image, partner_details.banner, orders.work_started_proof,
 * orders.work_completed_proof) to match.
 *
 * Local-server disk: physically moves files and rewrites paths.
 * AWS S3 disk: skips file movement (S3 keys are folder-scoped, no path
 * prefix is stored); column rewrites still run but match no rows since
 * S3 rows store filename-only values.
 *
 * order_assignments proof columns store JSON-encoded arrays of paths
 * with a leading slash. REPLACE() substring rewrites those in place.
 */
class MoveProfileBannerToUploads extends Migration
{
    /**
     * Physical directory moves on local disk.
     */
    private const MOVES = [
        'public/backend/assets/profile/'                => 'public/uploads/profile/',
        'public/backend/assets/profiles/'               => 'public/uploads/profiles/',
        'public/backend/assets/banner/'                 => 'public/uploads/banner/',
        'public/backend/assets/provider_work_evidence/' => 'public/uploads/provider_work_evidence/',
    ];

    /**
     * Legacy KYC directories left behind by the 2026-04-03 centralization
     * migration (files already pooled into public/uploads/custom_fields/).
     * Removed only if empty.
     */
    private const LEGACY_EMPTY_DIRS = [
        'public/backend/assets/passport/',
        'public/backend/assets/national_id/',
        'public/backend/assets/address_id/',
    ];

    /**
     * Column rewrites: [table, column, fromString, toString].
     * Includes leading-slash variants where DB stores them that way.
     */
    private function rewrites(): array
    {
        $rules = [];

        foreach (self::MOVES as $from => $to) {
            $rules[] = ['users', 'image', $from, $to];
            $rules[] = ['partner_details', 'banner', $from, $to];

            // Proof columns store JSON with leading-slash paths.
            $rules[] = ['orders', 'work_started_proof',   '/' . $from, '/' . $to];
            $rules[] = ['orders', 'work_completed_proof', '/' . $from, '/' . $to];
        }

        return $rules;
    }

    public function up()
    {
        $db   = Database::connect();
        $disk = $this->currentDisk($db);

        if ($disk === 'local_server') {
            foreach (self::MOVES as $from => $to) {
                $this->moveLocalFiles($from, $to);
            }

            foreach (self::LEGACY_EMPTY_DIRS as $dir) {
                @rmdir(FCPATH . $dir);
            }
        }

        foreach ($this->rewrites() as [$table, $column, $from, $to]) {
            $this->rewriteColumn($db, $table, $column, $from, $to);
        }
    }

    public function down()
    {
        $db   = Database::connect();
        $disk = $this->currentDisk($db);

        if ($disk === 'local_server') {
            foreach (array_flip(self::MOVES) as $from => $to) {
                $this->moveLocalFiles($from, $to);
            }
        }

        foreach ($this->rewrites() as [$table, $column, $from, $to]) {
            // Reverse direction.
            $this->rewriteColumn($db, $table, $column, $to, $from);
        }
    }

    private function currentDisk($db): string
    {
        $row = $db->table('settings')
            ->select('value')
            ->where('variable', 'storage_disk')
            ->get()
            ->getRowArray();

        $value = $row['value'] ?? '';

        return $value === 'aws_s3' ? 'aws_s3' : 'local_server';
    }

    private function moveLocalFiles(string $fromRel, string $toRel): void
    {
        $fromDir = FCPATH . $fromRel;
        $toDir   = FCPATH . $toRel;

        if (!is_dir($fromDir)) {
            if (!is_dir($toDir)) {
                @mkdir($toDir, 0755, true);
            }
            return;
        }

        if (!is_dir($toDir) && !@mkdir($toDir, 0755, true) && !is_dir($toDir)) {
            return;
        }

        $entries = scandir($fromDir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $src = $fromDir . $entry;
            $dst = $toDir . $entry;

            if (!is_file($src)) {
                continue;
            }

            // Destination collision: keep the existing target untouched,
            // remove the source so the directory can be cleaned up.
            if (is_file($dst)) {
                @unlink($src);
                continue;
            }

            if (!@rename($src, $dst)) {
                if (@copy($src, $dst)) {
                    @unlink($src);
                }
            }
        }

        $this->removeDirRecursive($fromDir);
    }

    /**
     * Recursively remove a directory and any residual contents. Used to
     * guarantee the legacy source directory is gone after a move pass.
     */
    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = @scandir($dir);
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . $entry;
                if (is_dir($path) && !is_link($path)) {
                    $this->removeDirRecursive(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
                } else {
                    @unlink($path);
                }
            }
        }

        @rmdir($dir);
    }

    private function rewriteColumn($db, string $table, string $column, string $from, string $to): void
    {
        if (!$db->tableExists($table) || !$db->fieldExists($column, $table)) {
            return;
        }

        $db->table($table)
            ->set($column, "REPLACE({$column}, " . $db->escape($from) . ", " . $db->escape($to) . ")", false)
            ->like($column, $from)
            ->update();
    }
}
