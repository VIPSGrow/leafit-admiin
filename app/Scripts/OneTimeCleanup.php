<?php

namespace App\Scripts;

/**
 * Handles one-time cleanup tasks such as deleting legacy directories.
 * You can add more paths to the $targets list in run() when needed.
 * All completed cleanups are recorded in a single flag file (JSON).
 */
class OneTimeCleanup
{
    /** Single flag file that stores all completed cleanup entries (path => status). */
    private const FLAG_FILE = 'one_time_cleanup_done.json';

    /**
     * Entry point for running all one-time cleanups.
     * Add new entries to the $targets array to support more paths.
     */
    public static function run(): void
    {
        // list of cleanups that should run only once
        // each item: "relative" => path relative to APPPATH (used as key in the single flag file)
        $targets = [
            ['relative' => 'Controllers/api'],
            ['relative' => 'Controllers/Partner/api'],
            ['relative' => 'Controllers/Apis/Customer/LanguageApiController.php'],
            ['relative' => 'Jobs/BookingNotification.php'],
            ['relative' => 'Jobs/ChatNotification.php'],
            ['relative' => 'Jobs/NumberLoggerJob.php'],
            ['relative' => 'Scripts/cleanup_migrations_once.php'],
            ['relative' => 'Controllers/Apis/Provider/V1.php'],
            ['relative' => 'Controllers/Apis/Customer/V1.php'],
            ['relative' => 'Controllers/Webhooks/Webhooks.php'],
            ['relative' => 'Models/CustomIonAuthModel.php'],
            ['relative' => 'Controllers/admin'],
            ['relative' => 'Controllers/partner'],
            ['relative' => 'Services/provider'],
            ['relative' => 'Views/backend/admin/pages/add_service.php'],
            ['relative' => 'Views/backend/admin/pages/edit_service.php'],
            ['relative' => 'Views/backend/admin/pages/service_clone.php'],
            ['relative' => 'Controllers/Admin/Services.php'],
            ['relative' => 'Views/backend/partner/pages/forms/add_services.php'],
            ['relative' => 'Views/backend/partner/pages/forms/edit_service.php'],
            ['relative' => 'Views/backend/partner/pages/forms/duplicate_service.php'],
            ['relative' => 'Controllers/Admin/Settings.php'],
            ['relative' => 'Database/Seeds/sql/partner_timings.sql'],
            ['relative' => 'Database/Seeds/sql/migrations.sql'],
            ['relative' => 'Database/Seeds/sql/groups.sql'],
            ['relative' => 'Database/Migrations/2023-10-12-112040_AddQueueTables.php'],
            ['relative' => 'Database/Migrations/2023-11-05-064053_AddPriorityField.php'],
            ['relative' => 'Database/Migrations/2024-03-21-000000_CreateUserReportsAndBlockedUsersTables.php'],
            ['relative' => 'Database/Migrations/2024-12-27-110712_ChangePayloadFieldTypeInSqlsrv.php'],
            ['relative' => 'Views/frontend/retro/pages/auth/forgot_password.php'],
            ['relative' => 'Views/frontend/retro/pages/auth/create_partner.php'],
            ['relative' => 'Views/backend/admin/pages/terms_and_conditions.php'],
            ['relative' => 'Views/backend/admin/pages/privacy_policy.php'],
            ['relative' => 'Views/backend/admin/pages/customer_terms_and_conditions.php'],
            ['relative' => 'Views/backend/admin/pages/customer_privacy_policy.php'],
            ['relative' => 'Views/backend/admin/pages/customer_terms_and_condition_page.php'],
            ['relative' => 'Views/backend/admin/pages/provider_terms_and_condition_page.php'],
            ['relative' => 'Views/backend/admin/pages/customer_app_privacy_policy.php'],
            ['relative' => 'Views/backend/admin/pages/partner_app_privacy_policy.php'],
            ['relative' => 'Views/backend/admin/pages/refund_policy.php'],
            ['relative' => 'Views/backend/admin/pages/refund_policy_page.php'],
            ['relative' => 'Models/Chat_model.php']
            // you can add more entries here in future, for example:
            // ['relative' => 'Controllers/OldSomething'],
        ];

        foreach ($targets as $target) {
            self::deletePathOnce($target['relative']);
        }

        // list of public cleanups that should run only once
        // each item: "relative" => path relative to FCPATH (used as key in the single flag file under 'public/')
        $publicTargets = [
            ['relative' => 'backend/assets/css/provider-stepper.css'],
            ['relative' => 'fontawesome/svgs'],
            ['relative' => 'fontawesome/scss'],
            ['relative' => 'fontawesome/sprites']
        ];

        foreach ($publicTargets as $target) {
            self::deletePublicPathOnce($target['relative']);
        }
    }

    /**
     * Reads the single flag file and returns completed entries as [ path => status ].
     * Returns empty array if file missing or invalid.
     */
    private static function readCompletedEntries(): array
    {
        $flagFile = WRITEPATH . self::FLAG_FILE;
        if (!is_file($flagFile)) {
            return [];
        }
        $raw = @file_get_contents($flagFile);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Marks one entry as completed in the single flag file.
     * Merges with existing entries and writes with LOCK_EX for safe concurrent use.
     */
    private static function markEntryCompleted(string $relativePath, string $status): void
    {
        $flagFile = WRITEPATH . self::FLAG_FILE;
        $entries = self::readCompletedEntries();
        $entries[$relativePath] = $status;
        @file_put_contents($flagFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Deletes a file OR directory (relative to APPPATH) exactly once.
     * Completion is recorded in the single flag file (all entries in one place).
     * Works for any entry in the $targets list regardless of type.
     */
    private static function deletePathOnce(string $relativePath): void
    {
        try {
            $entries = self::readCompletedEntries();
            // if this path is already in the flag file, skip
            if (isset($entries[$relativePath])) {
                return;
            }

            // safety check: make sure APPPATH resolves
            $appRoot = realpath(APPPATH);
            if ($appRoot === false) {
                return;
            }

            // resolve the target case-sensitively, segment by segment
            // this differentiates e.g. "Controllers/api" from "Controllers/Api"
            // on case-insensitive filesystems (macOS default, Windows)
            $resolvedTarget = self::resolveCaseSensitive($appRoot, $relativePath);

            if ($resolvedTarget === null) {
                // path with exact case does not exist; record so we never retry
                self::markEntryCompleted($relativePath, 'already_missing:' . date('c'));
                return;
            }

            // safety check: resolved path must still live inside the app folder
            if (strpos($resolvedTarget, $appRoot . DIRECTORY_SEPARATOR) !== 0) {
                return;
            }

            // handle files and directories separately
            if (is_file($resolvedTarget)) {
                // single file — just unlink it
                @unlink($resolvedTarget);
            } elseif (is_dir($resolvedTarget)) {
                // directory — remove recursively
                self::deleteDirectoryRecursively($resolvedTarget);
            }

            // record in the single flag file so this cleanup never runs again for this path
            self::markEntryCompleted($relativePath, 'deleted:' . date('c'));
        } catch (\Throwable $e) {
            // log any error but do not break the normal request flow
            if (function_exists('log_message')) {
                log_message('error', '[OneTimeCleanup] ' . $e->getMessage());
            }
        }
    }

    /**
     * Resolves a relative path under $rootAbsolute case-sensitively.
     * Each segment must match an entry returned by scandir() exactly (byte-for-byte).
     * Returns the absolute path on success, or null if any segment has no exact-case match.
     */
    private static function resolveCaseSensitive(string $rootAbsolute, string $relativePath): ?string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $segments = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $normalized), 'strlen'));

        if ($segments === []) {
            return null;
        }

        $current = $rootAbsolute;
        foreach ($segments as $segment) {
            if (!is_dir($current)) {
                return null;
            }
            $entries = @scandir($current);
            if ($entries === false || !in_array($segment, $entries, true)) {
                return null;
            }
            $current .= DIRECTORY_SEPARATOR . $segment;
        }

        return $current;
    }

    /**
     * Recursively deletes a directory and all its contents.
     * Assumes path safety validation has already been performed.
     */
    private static function deleteDirectoryRecursively(string $directoryPath): void
    {
        if (!is_dir($directoryPath)) {
            return;
        }

        $items = scandir($directoryPath);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $directoryPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                self::deleteDirectoryRecursively($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($directoryPath);
    }

    /**
     * Deletes a file OR directory relative to FCPATH (public directory) exactly once.
     * Completion is recorded in the single flag file.
     */
    private static function deletePublicPathOnce(string $relativePath): void
    {
        try {
            $entries = self::readCompletedEntries();
            $flagKey = 'public/' . $relativePath;
            if (isset($entries[$flagKey])) {
                return;
            }

            $publicRoot = realpath(FCPATH);
            if ($publicRoot === false) {
                return;
            }

            $resolvedTarget = self::resolveCaseSensitive($publicRoot, $relativePath);

            if ($resolvedTarget === null) {
                self::markEntryCompleted($flagKey, 'already_missing:' . date('c'));
                return;
            }

            if (strpos($resolvedTarget, $publicRoot . DIRECTORY_SEPARATOR) !== 0) {
                return;
            }

            if (is_file($resolvedTarget)) {
                @unlink($resolvedTarget);
            } elseif (is_dir($resolvedTarget)) {
                self::deleteDirectoryRecursively($resolvedTarget);
            }

            self::markEntryCompleted($flagKey, 'deleted:' . date('c'));
        } catch (\Throwable $e) {
            if (function_exists('log_message')) {
                log_message('error', '[OneTimeCleanup] ' . $e->getMessage());
            }
        }
    }
}

