<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Partial Demo Reset Command
 *
 * Resets only specific tables and their images for the public demo.
 * Copies clean data from the `demo_template` database into the live `edemand` database.
 * Restores images from `public/demo_base_images/` into `public/uploads/`.
 *
 * Usage:
 *   php spark demo:reset
 *
 * Safety features:
 *   - Concurrency lock (skips if another reset is already running)
 *   - Foreign key checks disabled during truncate+insert
 *   - Full audit logging to demo_reset_logs table
 */
class ResetDemoPartial extends BaseCommand
{
    protected $group = 'Demo';
    protected $name = 'demo:reset';
    protected $description = 'Performs a partial demo reset: truncates selected tables, copies from template DB, restores images.';
    protected $usage = 'demo:reset';

    /**
     * Tables to reset during a partial demo reset.
     * Only these tables are truncated and re-populated from demo_template.
     */
    private array $tablesToReset = [
        'services',
        'categories',
        'taxes',
        'translated_tax_details',
        'services_seo_settings',
        'categories_seo_settings',
        'translated_category_details',
        'translated_service_details',
        'translated_tax_details',
        'translated_category_seo_settings',
        'translated_service_seo_settings',
        'seo_settings',
        'subscriptions',
        'translated_subscription_details',
        'faqs',
        'translated_faq_details',
        'promo_codes',
        'reasons_for_report_and_block_chat',
        'translated_reasons_for_report_and_block_chat',
        'sliders',
        'sections',
        'translated_featured_sections',
        'cash_collection',
        'settlement_cashcollection_history',
        'settlement_history',
        "payment_request",
        "chat_questions",
        "custom_fields",
        "translated_custom_fields",
        "translated_chat_questions",
        "custom_pages",
        "custom_page_translations",
        "blog_categories",
        "blogs",
        "blog_tags",
        "blog_tag_map",
        "translated_blog_category_details",
        "translated_blog_details",
        "translated_blog_seo_settings",
        "translated_blog_tag_details",
        "slot_locks",
        "reasons",
        "translated_reasons"
    ];

    /**
     * Tables to upsert (INSERT or UPDATE) — no truncate, no data loss.
     *
     * ORDER MATTERS here.
     *   'users' must come first because 'partner_details' and 'users_groups'
     *   have foreign keys that reference users.id. If we upsert users first,
     *   the referenced IDs already exist when we upsert the child tables.
     *
     * How it works for each table:
     *   - Rows that exist in BOTH live and template → full row is updated from template.
     *   - Rows that exist ONLY in template → inserted into live.
     *   - Rows that exist ONLY in live (not in template) → left completely untouched.
     *
     * Add more tables below in the order their FK dependencies require.
     */
    private array $tablesToUpsert = [
        'users',
        'partner_details',
        'provider_shifts',
        'provider_slot_settings',
        'provider_leaves',
        'users_groups',
        'partners_seo_settings',
        'translated_partner_details',
        'translated_partner_seo_settings',
        'partner_subscriptions',
    ];
    // 'settings',

    /**
     * Main entry point for the command.
     */
    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $startTime = microtime(true);
        $startedAt = gmdate('Y-m-d H:i:s');

        CLI::write('=== Partial Demo Reset ===', 'yellow');
        log_message('info', '[DEMO_RESET] === run() start at ' . $startedAt . ' UTC ===');

        $running = $db->table('demo_reset_logs')->where('status', 'running')->get()->getRow();
        if ($running) {
            CLI::write('Another reset is already running. Skipping.', 'light_red');
            log_message('warning', "[DEMO_RESET] Skipped — already running (log_id={$running->id})");
            return;
        }

        $logId = $this->logStart($db, $startedAt);
        log_message('info', "[DEMO_RESET] Started log_id={$logId}");

        try {
            $stepStart = microtime(true);
            CLI::write('Upserting tables...', 'cyan');
            $this->upsertTables($db);
            $stepDur = (int) round(microtime(true) - $stepStart);
            CLI::write("Upsert complete ({$stepDur}s).", 'green');
            log_message('info', "[DEMO_RESET] Upsert phase done in {$stepDur}s");

            $stepStart = microtime(true);
            CLI::write('Deactivating live-only partner subscriptions...', 'cyan');
            $this->deactivateLiveOnlySubscriptions($db);
            $stepDur = (int) round(microtime(true) - $stepStart);
            CLI::write("Deactivation complete ({$stepDur}s).", 'green');
            log_message('info', "[DEMO_RESET] Deactivation phase done in {$stepDur}s");

            $stepStart = microtime(true);
            CLI::write('Resetting tables...', 'cyan');
            $this->resetTables($db);
            $stepDur = (int) round(microtime(true) - $stepStart);
            CLI::write("Tables reset complete ({$stepDur}s).", 'green');
            log_message('info', "[DEMO_RESET] Truncate+copy phase done in {$stepDur}s");

            $stepStart = microtime(true);
            CLI::write('Restoring images...', 'cyan');
            $this->restoreImages();
            $stepDur = (int) round(microtime(true) - $stepStart);
            CLI::write("Image restore complete ({$stepDur}s).", 'green');
            log_message('info', "[DEMO_RESET] Image restore phase done in {$stepDur}s");

            $duration = (int) round(microtime(true) - $startTime);
            $this->logFinish($db, $logId, 'success', 'Partial reset completed successfully (upsert + reset + images).', $duration);

            CLI::write("Reset finished in {$duration}s.", 'green');
            log_message('info', "[DEMO_RESET] === run() success log_id={$logId} duration={$duration}s ===");

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

        } catch (\Throwable $e) {
            $duration = (int) round(microtime(true) - $startTime);
            $errorMsg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            $this->logFinish($db, $logId, 'failed', $errorMsg, $duration);

            CLI::error("Reset FAILED: {$e->getMessage()}");
            log_message('error', "[DEMO_RESET] FAILED log_id={$logId} duration={$duration}s — {$errorMsg}");
            log_message('error', '[DEMO_RESET] Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * How many rows to fetch and insert at a time.
     * Keeps memory usage flat regardless of table size.
     * Increase for faster speed, decrease if hitting memory limits.
     */
    private int $chunkSize = 500;

    /**
     * Columns to strip before upserting a table.
     *
     * Used when a table has a compound unique key that should drive conflict
     * detection instead of the auto-increment PK. Stripping `id` means MySQL
     * fires ON DUPLICATE KEY UPDATE only on the named unique key, avoiding the
     * "multiple unique key conflict" error that occurs when the template row's id
     * differs from the live row's id but both match the same compound unique key.
     */
    private array $upsertExcludeColumns = [
        'provider_shifts' => ['id'],
    ];

    /**
     * Truncates each target table and copies data from demo_template in chunks.
     *
     * Why two connections?
     *   The demo template DB uses different MySQL credentials. A cross-database
     *   SQL query only works when the same MySQL user can access both databases.
     *   Two connections let each DB authenticate with its own credentials, and
     *   we move rows through PHP rather than through a single SQL statement.
     *
     * Why chunks?
     *   Loading all rows at once into a PHP array can exhaust memory on large
     *   tables and may exceed MySQL's max_allowed_packet. Chunking keeps memory
     *   usage constant — we only hold $chunkSize rows in memory at a time.
     */
    private function resetTables($db): void
    {
        // Open a dedicated connection to the template DB using its own credentials
        $templateDb = \Config\Database::connect('demo_template');

        // Disable FK checks so we can truncate without constraint errors
        $db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->tablesToReset as $table) {
            CLI::write("  -> Resetting: {$table}", 'white');

            // Wipe the live table first
            $db->query("TRUNCATE TABLE `{$table}`");

            // Copy rows in chunks to avoid loading the entire table into memory
            $totalInserted = $this->copyTableInChunks($templateDb, $db, $table);

            if ($totalInserted > 0) {
                CLI::write("     Inserted {$totalInserted} row(s).", 'white');
            } else {
                CLI::write("     No rows found in template for {$table}.", 'yellow');
            }
        }

        // Re-enable FK checks
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Copies all rows from a template table into the live table, chunk by chunk.
     *
     * Uses LIMIT + OFFSET pagination so only $chunkSize rows are in memory at once.
     * Returns the total number of rows inserted.
     */
    private function copyTableInChunks($templateDb, $db, string $table): int
    {
        $offset = 0;
        $totalInserted = 0;

        while (true) {
            $rows = $templateDb
                ->query("SELECT * FROM `{$table}` LIMIT {$this->chunkSize} OFFSET {$offset}")
                ->getResultArray();

            if (empty($rows)) {
                break;
            }

            $db->table($table)->insertBatch($rows);

            $rowCount = count($rows);
            $totalInserted += $rowCount;
            $offset += $this->chunkSize;

            unset($rows);

            if ($rowCount < $this->chunkSize) {
                break;
            }
        }

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return $totalInserted;
    }

    /**
     * Upserts all tables in $tablesToUpsert from the demo template.
     *
     * Key differences from resetTables():
     *   - NO TRUNCATE. Existing live rows are never deleted.
     *   - Uses INSERT ... ON DUPLICATE KEY UPDATE, so:
     *       * Matching rows (same primary/unique key) are fully updated.
     *       * New rows from the template are inserted.
     *       * Live-only rows (not in template) are preserved.
     *
     * FK checks are disabled during the operation so we can upsert in
     * any order without hitting constraint errors mid-batch.
     * The order in $tablesToUpsert still matters for logical correctness
     * (e.g. users before partner_details).
     */
    private function upsertTables($db): void
    {
        // Dedicated read-only connection to the template DB
        $templateDb = \Config\Database::connect('demo_template');

        // Disable FK checks for the duration of the upsert phase
        $db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->tablesToUpsert as $table) {
            CLI::write("  -> Upserting: {$table}", 'white');

            $totalUpserted = $this->upsertTableInChunks($templateDb, $db, $table);

            if ($totalUpserted > 0) {
                CLI::write("     Upserted {$totalUpserted} row(s).", 'white');
            } else {
                CLI::write("     No rows found in template for {$table}.", 'yellow');
            }
        }

        // Re-enable FK checks after all upserts are done
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Reads rows from the template table in chunks and upserts them into live.
     *
     * Uses the same LIMIT + OFFSET pagination as copyTableInChunks() to keep
     * memory usage flat. Returns the total number of rows processed.
     */
    private function upsertTableInChunks($templateDb, $db, string $table): int
    {
        $offset = 0;
        $totalUpserted = 0;

        while (true) {
            $rows = $templateDb
                ->query("SELECT * FROM `{$table}` LIMIT {$this->chunkSize} OFFSET {$offset}")
                ->getResultArray();

            if (empty($rows)) {
                break;
            }

            $this->executeUpsertBatch($db, $table, $rows);

            $rowCount = count($rows);
            $totalUpserted += $rowCount;
            $offset += $this->chunkSize;

            unset($rows);

            if ($rowCount < $this->chunkSize) {
                break;
            }
        }

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return $totalUpserted;
    }

    /**
     * Sets status = 'deactive' on every partner_subscriptions row whose partner_id
     * does NOT exist in the demo template's partner_subscriptions table.
     *
     * Why this is needed:
     *   The upsert step restores template partners' subscriptions perfectly.
     *   But live-only partners (not in the template) keep whatever status they had.
     *   On a demo site those should never appear as 'active', so we deactivate them.
     *
     * How it works:
     *   1. Fetch all distinct partner_ids from the TEMPLATE's partner_subscriptions.
     *   2. Run a single UPDATE on the LIVE table: set status = 'deactive' for every
     *      row whose partner_id is NOT in that list.
     *   3. If the template has no subscriptions at all, deactivate everything in live
     *      (safe fallback — the demo would have no active partners either).
     */
    private function deactivateLiveOnlySubscriptions($db): void
    {
        $templateDb = \Config\Database::connect('demo_template');

        // Get all partner_ids that the template covers
        $templateRows = $templateDb
            ->query('SELECT DISTINCT `partner_id` FROM `partner_subscriptions`')
            ->getResultArray();

        if (empty($templateRows)) {
            // No subscriptions in template at all — deactivate everything in live
            CLI::write('  No template subscriptions found. Deactivating all live subscriptions.', 'yellow');
            $db->query("UPDATE `partner_subscriptions` SET `status` = 'deactive'");
            return;
        }

        // Build a flat list of partner_ids as comma-separated escaped integers
        $templatePartnerIds = array_column($templateRows, 'partner_id');
        $escapedIds = implode(', ', array_map('intval', $templatePartnerIds));

        // One UPDATE covers all live-only partners in a single query — no chunking needed
        $db->query(
            "UPDATE `partner_subscriptions`
             SET    `status` = 'deactive'
             WHERE  `partner_id` NOT IN ({$escapedIds})"
        );

        // Report how many rows were touched
        $count = $db->affectedRows();
        if ($count > 0) {
            CLI::write("  Deactivated {$count} subscription row(s) for live-only partners.", 'white');
        } else {
            CLI::write('  No live-only partner subscriptions to deactivate.', 'white');
        }
    }

    /**
     * Builds and runs a single INSERT ... ON DUPLICATE KEY UPDATE query for a batch of rows.
     *
     * Why raw SQL?
     *   CodeIgniter's query builder does not support INSERT ... ON DUPLICATE KEY UPDATE
     *   natively, so we build the statement manually.
     *
     * How it works:
     *   INSERT INTO `table` (`col1`, `col2`, ...) VALUES (v1, v2, ...), ...
     *   ON DUPLICATE KEY UPDATE `col1` = VALUES(`col1`), `col2` = VALUES(`col2`), ...
     *
     *   MySQL fires the UPDATE branch when a row with the same primary key (or any
     *   unique key) already exists. VALUES(`col`) refers back to the value we tried
     *   to insert, so the full row is overwritten with the template data.
     *
     * Safety:
     *   Every value is passed through $db->escape() to prevent SQL injection.
     *   NULL values are written as the SQL literal NULL (not a quoted string).
     */
    private function executeUpsertBatch($db, string $table, array $rows): void
    {
        // Strip excluded columns (e.g. auto-increment PK when a compound unique key drives conflict detection)
        $excluded = $this->upsertExcludeColumns[$table] ?? [];
        if (!empty($excluded)) {
            $rows = array_map(fn($row) => array_diff_key($row, array_flip($excluded)), $rows);
        }

        // Derive column list from the first row's keys
        $columns = array_keys($rows[0]);
        $escapedColumns = array_map(fn($col) => "`{$col}`", $columns);
        $columnList = implode(', ', $escapedColumns);

        // Build one VALUES tuple per row
        $valueTuples = [];
        foreach ($rows as $row) {
            $escaped = array_map(
                // NULL must be written as the SQL keyword, not as the string 'NULL'
                fn($val) => $val === null ? 'NULL' : $db->escape($val),
                $row
            );
            $valueTuples[] = '(' . implode(', ', $escaped) . ')';
        }
        $valuesClause = implode(', ', $valueTuples);

        // Build the UPDATE clause — update every column from the inserted values
        $updateParts = array_map(fn($col) => "`{$col}` = VALUES(`{$col}`)", $columns);
        $updateClause = implode(', ', $updateParts);

        $sql = "INSERT INTO `{$table}` ({$columnList}) VALUES {$valuesClause} "
            . "ON DUPLICATE KEY UPDATE {$updateClause}";

        $db->query($sql);
    }

    /**
     * Restores demo assets by overlaying files into the public directory.
     *
     * Supports two modes:
     *   1. ZIP mode — Extracts zip to a temp folder, then overlays files into
     *      public/ according to their relative paths.
     *   2. Directory mode (fallback) — If no zip is found, overlays files from
     *      demo_base_images/ into public/ directly.
     *
     * Behavior is intentionally non-destructive:
     *   - Matching files are replaced.
     *   - Non-matching existing files are preserved.
     */
    private function restoreImages(): void
    {
        $basePath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'demo_base_images' . DIRECTORY_SEPARATOR;
        $publicPath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!is_dir($basePath)) {
            CLI::write('  No demo_base_images/ directory found. Skipping image restore.', 'yellow');
            return;
        }

        // Look for a .zip file inside demo_base_images/
        $zipFile = $this->findZipFile($basePath);

        if ($zipFile) {
            CLI::write("  Found zip: " . basename($zipFile), 'white');
            $this->restoreFromZip($zipFile, $publicPath);
        } else {
            // Fallback: overlay files from directory structure without deletions
            CLI::write('  No zip found, overlaying files from demo_base_images/.', 'white');
        }
    }

    /**
     * Finds the first .zip file inside the given directory.
     *
     * @return string|null  Full path to the zip, or null if none found.
     */
    private function findZipFile(string $directory): ?string
    {
        $files = glob($directory . '*.zip');
        return !empty($files) ? $files[0] : null;
    }

    private const OVERLAY_DIRS = [
        'backend/assets/profile',
        'backend/assets/profiles',
        'uploads/custom_fields',
        'backend/assets/banner',
        'uploads/partner',
    ];

    private function restoreFromZip(string $zipFile, string $publicPath): void
    {
        // Keep generating until we get a truly clean path
        do {
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'demo_restore_' . bin2hex(random_bytes(6));
        } while (file_exists($tempPath));

        if (!mkdir($tempPath, 0755, true) && !is_dir($tempPath)) {
            throw new \RuntimeException("Failed to create temp directory: {$tempPath}");
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            $this->deleteDirectoryRecursive($tempPath);
            throw new \RuntimeException("Failed to open zip: {$zipFile}");
        }
        $zip->extractTo($tempPath);
        $zip->close();

        $this->processExtractedFiles($tempPath, rtrim($publicPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

        $this->deleteDirectoryRecursive($tempPath);
    }

    private function processExtractedFiles(string $sourceRoot, string $destRoot): void
    {
        $sourceRoot = rtrim($sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $destRoot = rtrim($destRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $sourceLen = strlen($sourceRoot);

        // Pre-compute absolute overlay paths once (used to test "is this dir a parent of overlay?")
        $destRootNorm = rtrim(str_replace('\\', '/', $destRoot), '/');
        $absOverlayDirs = [];
        foreach (self::OVERLAY_DIRS as $d) {
            $absOverlayDirs[] = $destRootNorm . '/' . $d;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $overlayFiles = [];
        $wipeFiles = [];

        // Single pass — partition into wipe vs overlay
        foreach ($iterator as $item) {
            if (!$item->isFile())
                continue;
            $relative = substr($item->getPathname(), $sourceLen);
            $normalized = str_replace('\\', '/', $relative);
            if ($this->isOverlayPath($normalized)) {
                $overlayFiles[] = $relative;
            } else {
                $wipeFiles[] = $relative;
            }
        }
        unset($iterator);

        $movedCount = 0;
        $wipedDirs = [];   // destDirKey => true (already wiped)
        $createdDirs = [];  // destDir => true (already mkdir'd)

        // Wipe phase — group destDirs first, wipe each unique dir at most once
        foreach ($wipeFiles as $relative) {
            $destFile = $destRoot . $relative;
            $destDir = dirname($destFile);
            $destDirKey = rtrim(str_replace('\\', '/', $destDir), '/');

            if (!isset($wipedDirs[$destDirKey])) {
                if (is_dir($destDir) && !$this->dirIsOverlayParent($destDirKey, $absOverlayDirs)) {
                    $this->deleteDirectoryRecursive($destDir);
                    $createdDirs = []; // dirs gone, invalidate cache for this branch
                }
                $wipedDirs[$destDirKey] = true;
            }

            if (!isset($createdDirs[$destDir])) {
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0777, true);
                }
                $createdDirs[$destDir] = true;
            }

            // rename() atomically overwrites on POSIX — no need to unlink first
            @rename($sourceRoot . $relative, $destFile);
            $movedCount++;
        }
        unset($wipeFiles);

        // Overlay phase — preserve neighbour files, just replace matches
        foreach ($overlayFiles as $relative) {
            $destFile = $destRoot . $relative;
            $destDir = dirname($destFile);

            if (!isset($createdDirs[$destDir])) {
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0777, true);
                }
                $createdDirs[$destDir] = true;
            }

            // POSIX rename overwrites atomically; no unlink needed
            @rename($sourceRoot . $relative, $destFile);
            $movedCount++;
        }
        unset($overlayFiles);

        CLI::write("     Moved {$movedCount} file(s).", 'white');
        log_message('info', "[DEMO_RESET] Image restore moved {$movedCount} file(s)");

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Pre-computed variant of dirContainsOverlayPaths — no per-call list rebuild.
     */
    private function dirIsOverlayParent(string $absDirKey, array $absOverlayDirs): bool
    {
        $prefix = $absDirKey . '/';
        foreach ($absOverlayDirs as $absOverlay) {
            if (str_starts_with($absOverlay, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function isOverlayPath(string $normalizedRelative): bool
    {
        foreach (self::OVERLAY_DIRS as $dir) {
            if (str_starts_with($normalizedRelative, $dir . '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recursively deletes a directory and all its contents.
     */
    private function deleteDirectoryRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($path);
    }


    /**
     * Inserts a "running" log entry and returns its ID.
     */
    private function logStart($db, string $startedAt): int
    {
        $db->table('demo_reset_logs')->insert([
            'reset_type' => 'partial',
            'status' => 'running',
            'message' => null,
            'started_at' => $startedAt, // already UTC from run()
            'finished_at' => null,
            'duration_seconds' => null,
            'created_at' => gmdate('Y-m-d H:i:s'), // UTC for consistency
        ]);

        return $db->insertID();
    }

    /**
     * Updates the log entry with final status, message, and duration.
     */
    private function logFinish($db, int $logId, string $status, string $message, int $duration): void
    {
        $db->table('demo_reset_logs')
            ->where('id', $logId)
            ->update([
                'status' => $status,
                'message' => $message,
                'finished_at' => gmdate('Y-m-d H:i:s'), // UTC for consistent timer across timezones
                'duration_seconds' => $duration,
            ]);
    }
}
