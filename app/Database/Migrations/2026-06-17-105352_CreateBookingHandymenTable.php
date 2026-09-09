<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBookingHandymenTable extends Migration
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
                'null' => false,
            ],
            'handyman_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['assigned', 'accepted', 'rejected', 'on_the_way', 'arrived', 'started', 'completed'],
                'default' => 'assigned',
                'null' => false,
            ],
            'rejected_reason' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'assigned_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
            'assigned_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['order_id', 'handyman_id']);
        $this->forge->createTable('booking_handymen');
        $this->db->query('ALTER TABLE `booking_handymen` MODIFY `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE `booking_handymen` MODIFY `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    public function down()
    {
        $this->forge->dropTable('booking_handymen', true);
    }
}
