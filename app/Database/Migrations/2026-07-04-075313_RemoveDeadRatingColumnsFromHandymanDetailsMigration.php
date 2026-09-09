<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveDeadRatingColumnsFromHandymanDetailsMigration extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('handyman_details', ['ratings', 'number_of_ratings']);
    }

    public function down()
    {
        $this->forge->addColumn('handyman_details', [
            'ratings' => [
                'type' => 'DECIMAL',
                'constraint' => '10,1',
                'default' => '0.0',
                'after' => 'salary',
            ],
            'number_of_ratings' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
                'after' => 'ratings',
            ],
        ]);
    }
}
