<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterLoginTypeColumnTypeToEnumInUsersTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Clean data first
        $db->query("
            UPDATE `users`
            SET `loginType` = 'phone'
            WHERE `loginType` IS NULL OR `loginType` NOT IN ('phone','email','google','apple')
        ");

        // Then modify
        $db->query("
            ALTER TABLE `users`
            MODIFY `loginType`
            ENUM('phone','email','google','apple')
            NOT NULL
            DEFAULT 'phone'
        ");
    }


    public function down()
    {
        $fields = [
            'loginType' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'default' => null
            ]
        ];

        $this->forge->modifyColumn('users', $fields);
    }
}