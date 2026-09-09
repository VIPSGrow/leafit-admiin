<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsLeadToBookingHandymen extends Migration
{
    public function up()
    {
        $this->forge->addColumn('booking_handymen', [
            'is_lead' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'status',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('booking_handymen', 'is_lead');
    }
}
