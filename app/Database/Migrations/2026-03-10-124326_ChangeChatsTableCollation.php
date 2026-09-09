<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ChangeChatsTableCollation extends Migration
{
    public function up()
    {
        $this->db->query("
        ALTER TABLE `chats`
        CONVERT TO CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
        ");
    }

    public function down()
    {
        $this->db->query("
        ALTER TABLE `chats`
        CONVERT TO CHARACTER SET utf8mb4
        COLLATE utf8mb4_general_ci
        ");
    }
}
