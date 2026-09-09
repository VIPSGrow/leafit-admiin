<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class InsertDefaultLoginSettings extends Migration
{
    /**
     * Insert default login settings into settings table
     * Gets OTP delivery method from general_settings authentication_mode
     * Only inserts if login_settings doesn't already exist
     */
    public function up()
    {
        $db = \Config\Database::connect();
        // Check if login_settings already exists
        $existing = $db->table('settings')->where('variable', 'login_settings')->get()->getRowArray();

        // Only insert if login_settings doesn't exist
        if (empty($existing)) {
            // Build default login settings JSON
            $loginSettings = [
                'phone_authentication_enabled' => 1,  // Phone login must be ON
                'email_authentication_enabled' => 0,  // Email login must be OFF
                'social_authentication_enabled' => 1, // Social login must be OFF
                'customer_password_login_enabled' => 1           // Customer password must be ON
            ];

            // Convert to JSON string
            $jsonValue = json_encode($loginSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Insert login_settings into settings table
            $insertData = [
                'variable' => 'login_settings',
                'value' => $jsonValue
            ];

            $db->table('settings')->insert($insertData);
        }
    }

    /**
     * Remove login_settings from settings table
     * This is the rollback operation
     */
    public function down()
    {
        // Remove login_settings entry
        $db = \Config\Database::connect();
        $db->table('settings')->where('variable', 'login_settings')->delete();
    }
}
