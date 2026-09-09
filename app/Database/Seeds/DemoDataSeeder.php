<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Demo Data Seeder
 *
 * Seeds all demo tables from SQL files in app/Database/Seeds/sql/.
 * Accepts a mode: 'additive' (INSERT IGNORE) or 'clean' (truncate + insert).
 *
 * FK integrity:
 *   - Both modes disable FK checks during seeding and re-enable after.
 *   - Original IDs are preserved in both modes so all FK references stay intact.
 *
 * Admin preservation:
 *   - clean mode:    snapshots the current admin (id=1) rows before truncating,
 *                    seeds everything, then overwrites id=1 with the saved data.
 *   - additive mode: uses INSERT IGNORE — the existing admin row at id=1 is
 *                    kept because the duplicate is silently skipped.
 */
class DemoDataSeeder extends Seeder
{
    private string $mode = 'additive';

    /**
     * All target tables in reverse-FK order (children first).
     * Used by clean mode to truncate before seeding.
     */
    private array $allTables = [
        'blog_tag_map',
        'blog_tags',
        'blogs',
        'blog_categories',
        'notification_templates',
        'sms_templates',
        'email_templates',
        'faqs',
        'services',
        'categories',
        'provider_shifts',
        'provider_slot_settings',
        'partner_subscriptions',
        'subscriptions',
        'partner_custom_fields',
        'partner_details',
        'user_permissions',
        'users_groups',
        'users',
        'taxes',
        'updates',
    ];

    /**
     * SQL files to execute in FK-dependency order (parents first).
     * Each file lives in app/Database/Seeds/sql/.
     */
    private array $sqlFiles = [
        'updates.sql',
        'taxes.sql',
        'users.sql',
        'users_groups.sql',
        'user_permissions.sql',
        'partner_details.sql',
        'provider_shifts.sql',
        'provider_slot_settings.sql',
        'partner_custom_fields.sql',
        'subscriptions.sql',
        'partner_subscriptions.sql',
        'categories.sql',
        'services.sql',
        'faqs.sql',
        'email_templates.sql',
        'sms_templates.sql',
        'notification_templates.sql',
        'blog_categories.sql',
        'blog_tags.sql',
        'blogs.sql',
        'blog_tag_map.sql',
    ];

    public function setMode(string $mode): void
    {
        if (!in_array($mode, ['additive', 'clean'], true)) {
            throw new \InvalidArgumentException("Invalid seed mode: {$mode}");
        }
        $this->mode = $mode;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function run()
    {
        $adminSnapshot = null;

        if ($this->mode === 'clean') {
            $adminSnapshot = $this->snapshotAdmin();
            $this->truncateAll();
        }

        // Disable FK checks so insert order doesn't cause constraint errors
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->sqlFiles as $file) {
            $this->runSqlFile($file);
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        if ($this->mode === 'clean' && $adminSnapshot !== null) {
            $this->restoreAdmin($adminSnapshot);
        }
    }

    // ─── Admin preservation ──────────────────────────────────────

    /**
     * Snapshot the current admin's rows before truncation.
     */
    private function snapshotAdmin(): ?array
    {
        $user = $this->db->table('users')->where('id', 1)->get()->getRowArray();
        if (empty($user)) {
            return null;
        }

        return [
            'user'        => $user,
            'groups'      => $this->db->table('users_groups')->where('user_id', 1)->get()->getResultArray(),
            'permissions' => $this->db->table('user_permissions')->where('user_id', 1)->get()->getResultArray(),
        ];
    }

    /**
     * Overwrite the seeded id=1 row with the real admin's data.
     */
    private function restoreAdmin(array $snapshot): void
    {
        $this->db->table('users')->where('id', 1)->delete();
        $this->db->table('users')->insert($snapshot['user']);

        $this->db->table('users_groups')->where('user_id', 1)->delete();
        foreach ($snapshot['groups'] as $row) {
            $this->db->table('users_groups')->insert($row);
        }

        $this->db->table('user_permissions')->where('user_id', 1)->delete();
        foreach ($snapshot['permissions'] as $row) {
            $this->db->table('user_permissions')->insert($row);
        }
    }

    // ─── SQL file execution ──────────────────────────────────────

    /**
     * Load a SQL file and execute its INSERT statements.
     *
     * - clean mode:    runs statements as-is (tables were already truncated).
     * - additive mode: rewrites INSERT INTO → INSERT IGNORE INTO so existing
     *                  rows (including admin id=1) are silently skipped.
     *
     * Original IDs are kept in both modes to preserve FK references.
     */
    private function runSqlFile(string $filename): void
    {
        $path = APPPATH . 'Database/Seeds/sql/' . $filename;

        if (!is_file($path)) {
            throw new \RuntimeException("Seed SQL file not found: {$path}");
        }

        $contents = file_get_contents($path);

        // Split on semicolons that end a statement
        $statements = array_filter(
            array_map('trim', explode(";\n", $contents)),
            fn($s) => $s !== ''
        );

        foreach ($statements as $sql) {
            $sql = rtrim($sql, ';') . ';';

            if ($this->mode === 'additive') {
                $sql = preg_replace('/^INSERT\s+INTO/i', 'INSERT IGNORE INTO', $sql);
            }

            $this->db->query($sql);
        }
    }

    /**
     * Truncate all target tables (children first) with FK checks disabled.
     */
    private function truncateAll(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->allTables as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->truncate();
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}
