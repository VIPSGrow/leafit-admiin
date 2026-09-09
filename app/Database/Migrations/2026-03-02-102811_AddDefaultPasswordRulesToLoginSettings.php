<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds default password requirement keys to the login_settings JSON in the settings table.
 * Only sets keys that are not already present, so existing admin choices are preserved.
 * Defaults: minimum_password_length '8', all four character requirements enabled (1).
 */
class AddDefaultPasswordRulesToLoginSettings extends Migration
{
    /** Default password rule keys and values (Authentication Settings) */
    private const DEFAULT_PASSWORD_RULES = [
        'minimum_password_length'        => '8',
        'require_at_least_one_uppercase' => 1,
        'require_at_least_one_lowercase' => 1,
        'require_at_least_one_number'    => 1,
        'require_at_least_one_special_character' => 1,
    ];

    public function up()
    {
        $db = \Config\Database::connect();
        $row = $db->table('settings')
            ->where('variable', 'login_settings')
            ->get()
            ->getRowArray();

        if (empty($row) || empty($row['value'])) {
            return;
        }

        $loginSettings = json_decode($row['value'], true);
        if (!is_array($loginSettings)) {
            return;
        }

        $updated = false;
        foreach (self::DEFAULT_PASSWORD_RULES as $key => $defaultValue) {
            if (!array_key_exists($key, $loginSettings)) {
                $loginSettings[$key] = $defaultValue;
                $updated = true;
            }
        }

        if ($updated) {
            $db->table('settings')
                ->where('variable', 'login_settings')
                ->update([
                    'value' => json_encode($loginSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    /**
     * Removes the password rule keys from login_settings JSON.
     * Other login settings (e.g. phone_authentication_enabled) are left unchanged.
     */
    public function down()
    {
        $db = \Config\Database::connect();
        $row = $db->table('settings')
            ->where('variable', 'login_settings')
            ->get()
            ->getRowArray();

        if (empty($row) || empty($row['value'])) {
            return;
        }

        $loginSettings = json_decode($row['value'], true);
        if (!is_array($loginSettings)) {
            return;
        }

        foreach (array_keys(self::DEFAULT_PASSWORD_RULES) as $key) {
            unset($loginSettings[$key]);
        }

        $db->table('settings')
            ->where('variable', 'login_settings')
            ->update([
                'value' => json_encode($loginSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
    }
}
