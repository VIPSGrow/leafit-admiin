<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAdminCommissionToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'admin_commission' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0.00,
                'null'       => false,
                'after'      => 'payable_commision' // optional
            ],
        ];

        $this->forge->addColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'admin_commission');
    }
}
