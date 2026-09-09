<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHandymanReviewsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'order_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'handyman_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'rating' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
                'constraint' => 1,
            ],
            'review' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'images' => [
                'type' => 'JSON',
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['order_id', 'user_id', 'handyman_id']);
        $this->forge->addKey('handyman_id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('user_id');

        $this->forge->createTable('handyman_reviews');
    }

    public function down()
    {
        $this->forge->dropTable('handyman_reviews', true);
    }
}
