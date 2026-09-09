<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatDeletionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'e_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'booking_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'chat_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'e_id', 'booking_id'], false, false, 'idx_chat_deletions_user_thread');
        $this->forge->addKey(['user_id', 'chat_id'], false, false, 'idx_chat_deletions_user_chat');
        $this->forge->createTable('chat_deletions');
    }

    public function down()
    {
        $this->forge->dropTable('chat_deletions', true);
    }
}
