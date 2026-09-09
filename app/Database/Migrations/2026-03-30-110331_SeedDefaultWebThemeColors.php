<?php

namespace App\Database\Migrations;

use App\Database\Seeds\WebThemeColorSeeder;
use CodeIgniter\Database\Migration;

class SeedDefaultWebThemeColors extends Migration
{
    public function up()
    {
        $allDefaults = array_merge(WebThemeColorSeeder::LIGHT_DEFAULTS, WebThemeColorSeeder::DARK_DEFAULTS);

        foreach ($allDefaults as $key => $default) {
            $sql = "
                UPDATE settings
                SET value = JSON_SET(
                    value,
                    '$.{$key}',
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(value, '$.{$key}')), ?)
                )
                WHERE `variable` = 'web_settings'
                AND JSON_EXTRACT(value, '$.{$key}') IS NULL
            ";
            $this->db->query($sql, [$default]);
        }
    }

    public function down()
    {
        $allDefaults = array_merge(WebThemeColorSeeder::LIGHT_DEFAULTS, WebThemeColorSeeder::DARK_DEFAULTS);

        foreach (array_keys($allDefaults) as $key) {
            $sql = "
                UPDATE settings
                SET value = JSON_REMOVE(value, '$.{$key}')
                WHERE `variable` = 'web_settings'
                AND JSON_EXTRACT(value, '$.{$key}') IS NOT NULL
            ";
            $this->db->query($sql);
        }
    }
}
