<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMaxServiceableDistanceTypeToGeneralSettings extends Migration
{
    public function up()
    {
        $sql = "
            UPDATE settings
            SET value = JSON_SET(
                value,
                '$.max_serviceable_distance_type',
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(value, '$.max_serviceable_distance_type')), 'global')
            )
            WHERE `variable` = 'general_settings'
        ";

        $this->db->query($sql);
    }

    public function down()
    {
        $sql = "
            UPDATE settings
            SET value = JSON_REMOVE(value, '$.max_serviceable_distance_type')
            WHERE `variable` = 'general_settings'
        ";

        $this->db->query($sql);
    }
}
