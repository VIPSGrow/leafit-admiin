<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHandymanCashCollectionsTable extends Migration
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
                'unsigned' => true,
                'null' => false,
            ],
            'order_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
            'type' => [
                'type' => 'ENUM',
                'constraint' => ['collection', 'settlement'],
                'null' => false,
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => false,
                'default' => 0,
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'created_at' => [
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
        $this->forge->createTable('handyman_cash_collections');
        $this->db->query('ALTER TABLE `handyman_cash_collections` MODIFY `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE `handyman_cash_collections` MODIFY `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE `handyman_cash_collections` ADD INDEX `idx_handyman_type` (`handyman_id`, `type`)');
        $this->db->query('ALTER TABLE `handyman_cash_collections` ADD INDEX `idx_partner_type` (`partner_id`, `type`)');
        $this->db->query("ALTER TABLE `handyman_cash_collections` ADD COLUMN `collection_order_id` INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `type` = 'collection' THEN `order_id` END) STORED");
        $this->db->query('ALTER TABLE `handyman_cash_collections` ADD UNIQUE KEY `uq_collection_order_id` (`collection_order_id`)');
    }

    public function down()
    {
        $this->forge->dropTable('handyman_cash_collections', true);
    }
}
