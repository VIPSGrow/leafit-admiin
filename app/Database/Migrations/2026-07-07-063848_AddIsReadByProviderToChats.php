<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsReadByProviderToChats extends Migration
{
    public function up()
    {
        $this->forge->addColumn('chats', [
            'is_read_by_provider' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
                // Default 1 ("not applicable / already seen") so every existing row
                // is backfilled automatically by the ALTER TABLE — only messages the
                // customer/handyman send on a booking after this migration are
                // explicitly inserted with 0 (see insert_chat_message_for_chat()).
                'default'    => 1,
                'after'      => 'is_read',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('chats', 'is_read_by_provider');
    }
}
