<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class WebThemeColorSeeder extends Seeder
{
    public const LIGHT_DEFAULTS = [
        'light_primary_color'            => '#0277fa',
        'light_secondary_color'          => '#000000',
        'light_bg_color'                 => '#0277fa12',
        'light_text_color'               => '#000000',
        'light_card_bg_color'            => '#ffffff',
        'light_description_text_color'   => '#2121219e',
    ];

    public const DARK_DEFAULTS = [
        'dark_primary_color'             => '#51a7f3',
        'dark_secondary_color'           => '#0d1115',
        'dark_bg_color'                  => '#0d1115',
        'dark_text_color'                => '#ffffff',
        'dark_card_bg_color'             => '#0d131b',
        'dark_description_text_color'    => '#ffffff',
    ];

    /**
     * Seed all theme color defaults (both light and dark).
     */
    public function run()
    {
        $this->applyDefaults(array_merge(self::LIGHT_DEFAULTS, self::DARK_DEFAULTS));
    }

    /**
     * Reset only light theme colors to defaults.
     */
    public function resetLight()
    {
        $this->applyDefaults(self::LIGHT_DEFAULTS);
    }

    /**
     * Reset only dark theme colors to defaults.
     */
    public function resetDark()
    {
        $this->applyDefaults(self::DARK_DEFAULTS);
    }

    /**
     * Apply given color defaults into the web_settings JSON.
     */
    private function applyDefaults(array $colors)
    {
        foreach ($colors as $key => $default) {
            $sql = "
                UPDATE settings
                SET value = JSON_SET(value, '$.{$key}', ?)
                WHERE `variable` = 'web_settings'
            ";
            $this->db->query($sql, [$default]);
        }
    }
}
