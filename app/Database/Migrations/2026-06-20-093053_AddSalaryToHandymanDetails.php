<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSalaryToHandymanDetails extends Migration
{
    public function up()
    {
        $this->forge->addColumn('handyman_details', [
            'salary' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'address',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('handyman_details', 'salary');
    }
}
