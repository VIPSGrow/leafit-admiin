<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddChatSettingsRow extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Check if chat_settings row already exists
        $existing = $db->table('settings')
            ->where('variable', 'chat_settings')
            ->get()
            ->getRowArray();

        if (!empty($existing)) {
            return;
        }

        // Copy chat keys from general_settings into a new chat_settings row
        $chatKeys = [
            'maxFilesOrImagesInOneMessage',
            'maxFileSizeInMBCanBeSent',
            'maxCharactersInATextMessage',
            'allow_pre_booking_chat',
            'allow_post_booking_chat',
            'enable_chat_image_upload',
            'enable_chat_file_upload',
        ];

        $chatData = [];
        $generalRow = $db->table('settings')
            ->where('variable', 'general_settings')
            ->get()
            ->getRowArray();

        if (!empty($generalRow['value'])) {
            $general = json_decode($generalRow['value'], true);
            foreach ($chatKeys as $key) {
                $chatData[$key] = $general[$key] ?? '';
            }
        } else {
            // Defaults if general_settings doesn't have them yet
            foreach ($chatKeys as $key) {
                $chatData[$key] = '';
            }
        }

        $db->table('settings')->insert([
            'variable' => 'chat_settings',
            'value'    => json_encode($chatData),
        ]);
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->table('settings')->where('variable', 'chat_settings')->delete();
    }
}
