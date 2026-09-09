<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterPaymentAddressInPaymentRequestTable extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('payment_request', [
            'payment_address' => [
                'type'       => 'TEXT',
                'null'       => false,
                'default'    => '',
            ],
        ]);
    }

    public function down()
    {
        // rollback version (adjust if you know original type)
        $this->forge->modifyColumn('payment_request', [
            'payment_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 1024,
                'null'       => false,
            ],
        ]);
    }
}
