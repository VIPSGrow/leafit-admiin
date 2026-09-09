<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStatusActorColumnsToOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('orders', [
            'status_changed_by_type' => [
                'type' => 'ENUM',
                'constraint' => ['admin', 'customer', 'provider', 'handyman'],
                'null' => true,
                'after' => 'status',
            ],
            'status_changed_by_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'status_changed_by_type',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', ['status_changed_by_type', 'status_changed_by_id']);
    }
}
