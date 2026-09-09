<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropTicketsRelatedTables extends Migration
{
    public function up()
    {
        $this->forge->dropTable('tickets', true);
        $this->forge->dropTable('ticket_messages', true);
        $this->forge->dropTable('ticket_types', true);
    }

    public function down()
    {
        //
    }
}
