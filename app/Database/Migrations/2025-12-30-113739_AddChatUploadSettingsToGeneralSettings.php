<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddChatUploadSettingsToGeneralSettings extends Migration
{
    public function up()
    {
        $sql = "
            UPDATE settings
            SET value = JSON_SET(
                value,
                '$.enable_chat_image_upload',
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(value, '$.enable_chat_image_upload')), '1'),
                '$.enable_chat_file_upload',
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(value, '$.enable_chat_file_upload')), '1')
            )
            WHERE `variable` = 'general_settings'
        ";

        $this->db->query($sql);
    }

    public function down()
    {
        $sql = "
            UPDATE settings
            SET value = JSON_REMOVE(value, '$.enable_chat_image_upload', '$.enable_chat_file_upload')
            WHERE `variable` = 'general_settings'
        ";

        $this->db->query($sql);
    }
}
