<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddResetTokenToUsersTable extends Migration
{
    public function up()
    {
        $fields = [
            'password_reset_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'email',
            ],
            'password_reset_expires' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'password_reset_token',
            ],
        ];

        $this->forge->addColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['password_reset_token', 'password_reset_expires']);
    }
}
