<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaxTypeToOrderServices extends Migration
{
    public function up()
    {
        // 1. Add ENUM column
        $fields = [
            'tax_type' => [
                'type'       => "ENUM('included','excluded')",
                'null'       => true,
                'after'      => 'service_title',
            ],
        ];

        if (! $this->db->fieldExists('tax_type', 'order_services')) {
            $this->forge->addColumn('order_services', $fields);
        }

        // 2. Backfill old data using service_id → services.tax_type
        $sql = "
            UPDATE order_services os
            JOIN services s ON s.id = os.service_id
            SET os.tax_type = s.tax_type
            WHERE os.tax_type IS NULL
        ";

        $this->db->query($sql);
    }

    public function down()
    {
        if ($this->db->fieldExists('tax_type', 'order_services')) {
            $this->forge->dropColumn('order_services', 'tax_type');
        }
    }
}
