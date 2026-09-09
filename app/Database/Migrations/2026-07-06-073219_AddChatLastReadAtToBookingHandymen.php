<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddChatLastReadAtToBookingHandymen extends Migration
{
    public function up()
    {
        $this->forge->addColumn('booking_handymen', [
            'chat_last_read_at' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'default'    => null,
                'after'      => 'is_lead',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('booking_handymen', 'chat_last_read_at');
    }
}
