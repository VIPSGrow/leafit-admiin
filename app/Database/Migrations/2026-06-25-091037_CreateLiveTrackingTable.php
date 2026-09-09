<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLiveTrackingTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'latitude' => [
                'type' => 'DECIMAL',
                'constraint' => '10,8',
            ],
            'longitude' => [
                'type' => 'DECIMAL',
                'constraint' => '11,8',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('order_id');
        $this->forge->createTable('live_tracking');
    }

    public function down()
    {
        $this->forge->dropTable('live_tracking', true);
    }
}
