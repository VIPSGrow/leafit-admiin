<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Remove Vonage from SMS gateway settings.
 *
 * Vonage is no longer supported. This migration removes the vonage key
 * from sms_gateway_setting JSON in the settings table.
 */
class RemoveVonageFromSmsGatewaySettings extends Migration
{
    public function up()
    {
        // Remove vonage key from sms_gateway_setting JSON
        $sql = "
            UPDATE settings
            SET value = JSON_REMOVE(value, '$.vonage')
            WHERE variable = 'sms_gateway_setting'
            AND JSON_EXTRACT(value, '$.vonage') IS NOT NULL
        ";

        $this->db->query($sql);
    }

    public function down()
    {
        // Restore empty vonage structure for rollback (original data cannot be recovered)
        $sql = "
            UPDATE settings
            SET value = JSON_SET(
                value,
                '$.vonage',
                JSON_OBJECT(
                    'vonage_status', '0',
                    'vonage_api_key', '',
                    'vonage_api_secret', ''
                )
            )
            WHERE variable = 'sms_gateway_setting'
        ";

        $this->db->query($sql);
    }
}
