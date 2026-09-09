<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAggregateColumnsToHandymanDetails extends Migration
{
    public function up()
    {
        $this->forge->addColumn('handyman_details', [
            'total_reviews' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 0,
                'after'      => 'number_of_ratings',
            ],
            'average_rating' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'unsigned'   => true,
                'default'    => 0.00,
                'after'      => 'total_reviews',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('handyman_details', ['total_reviews', 'average_rating']);
    }
}
