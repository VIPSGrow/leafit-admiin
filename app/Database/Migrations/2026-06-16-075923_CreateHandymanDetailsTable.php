<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHandymanDetailsTable extends Migration
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
            'handyman_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'partner_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ratings' => [
                'type' => 'DECIMAL',
                'constraint' => '10,1',
                'default' => '0.0',
            ],
            'number_of_ratings' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'is_available' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('handyman_id');
        $this->forge->addKey('partner_id');
        $this->forge->addForeignKey('handyman_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('handyman_details', true);

        // Guard FK to partner_details — may not exist on fresh installs
        if ($this->db->tableExists('partner_details')) {
            $this->db->query('
                ALTER TABLE `handyman_details`
                ADD CONSTRAINT `fk_handyman_details_partner_id`
                FOREIGN KEY (`partner_id`) REFERENCES `partner_details`(`partner_id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            ');
        }
    }

    public function down()
    {
        $this->forge->dropTable('handyman_details', true);
    }
}
