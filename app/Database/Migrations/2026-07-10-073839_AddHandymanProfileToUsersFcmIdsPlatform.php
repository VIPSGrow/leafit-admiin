<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHandymanProfileToUsersFcmIdsPlatform extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('users_fcm_ids')) {
            return;
        }

        $db->query("
            ALTER TABLE `users_fcm_ids`
            MODIFY COLUMN `platform` ENUM('android','ios','web','admin_panel','provider_panel','handyman_panel') NOT NULL
        ");
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('users_fcm_ids')) {
            return;
        }

        $db->query("
            ALTER TABLE `users_fcm_ids`
            MODIFY COLUMN `platform` ENUM('android','ios','web','admin_panel','provider_panel') NOT NULL
        ");
    }
}
