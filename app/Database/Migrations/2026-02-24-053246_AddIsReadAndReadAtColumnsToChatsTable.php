<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsReadAndReadAtColumnsToChatsTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('chats', [
            'is_read' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'read_at' => [
                'type' => 'DATETIME',
                'default' => null,
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('chats', 'is_read');
        $this->forge->dropColumn('chats', 'read_at');
    }
}
