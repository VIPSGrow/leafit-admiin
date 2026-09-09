<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHandymanCustomFieldValuesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'handyman_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'custom_field_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addUniqueKey(['handyman_id', 'custom_field_id']);
        $this->forge->addForeignKey('handyman_id',       'users',         'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('custom_field_id',   'custom_fields', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('handyman_custom_field_values', true);
    }

    public function down()
    {
        $this->forge->dropTable('handyman_custom_field_values', true);
    }
}
