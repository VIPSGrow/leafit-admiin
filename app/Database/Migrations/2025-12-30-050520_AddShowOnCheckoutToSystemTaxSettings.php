<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShowOnCheckoutToSystemTaxSettings extends Migration
{
    public function up()
    {
         // Add new JSON key only if it does not exist
        $sql = "
            UPDATE settings
            SET value = JSON_SET(
                value,
                '$.show_on_checkout',
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(value, '$.show_on_checkout')), '1')
            )
            WHERE `variable` = 'system_tax_settings'
            AND JSON_EXTRACT(value, '$.show_on_checkout') IS NULL
        ";

        $this->db->query($sql);
    }

    public function down()
    {
        // Remove the key if it exists
        $sql = "
            UPDATE settings
            SET value = JSON_REMOVE(value, '$.show_on_checkout')
            WHERE `variable` = 'system_tax_settings'
            AND JSON_EXTRACT(value, '$.show_on_checkout') IS NOT NULL
        ";

        $this->db->query($sql);
    }
}
