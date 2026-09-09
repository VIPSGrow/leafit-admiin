<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomJobSettings extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        $db->query("ALTER TABLE settings ADD UNIQUE (`variable`)");

        $defaultSettings = [
            'max_files_allowed' => '5',
            'max_file_size_images' => '5',
            'max_file_size_video' => '100',
            'max_file_size_audio' => '5',
            'max_file_size_other' => '10',
            'allow_image_uploads' => 1,
            'allow_video_uploads' => 1,
            'allow_document_uploads' => 1,
        ];

        $existing = $db->table('settings')->where('variable', 'custom_job_settings')->get()->getRowArray();

        if (empty($existing)) {
            $db->table('settings')->insert([
                'variable' => 'custom_job_settings',
                'value' => json_encode($defaultSettings),
            ]);
        } else {
            $currentValue = json_decode($existing['value'] ?? '', true);
            if (!is_array($currentValue)) {
                $currentValue = [];
            }

            $missingKeys = array_diff_key($defaultSettings, $currentValue);

            if (!empty($missingKeys)) {
                $mergedValue = array_merge($defaultSettings, $currentValue);

                $db->table('settings')
                    ->where('variable', 'custom_job_settings')
                    ->update(['value' => json_encode($mergedValue)]);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->table('settings')->where('variable', 'custom_job_settings')->delete();
    }
}
