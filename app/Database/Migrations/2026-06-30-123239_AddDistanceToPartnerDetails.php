<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDistanceToPartnerDetails extends Migration
{
    public function up()
    {
        $this->forge->addColumn('partner_details', [
            'max_serviceable_distance' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        $db = \Config\Database::connect();
        if ($db->fieldExists('service_range', 'partner_details')) {
            $this->forge->dropColumn('partner_details', 'service_range');
        }
    }

    public function down()
    {
        $this->forge->dropColumn('partner_details', 'max_serviceable_distance');

        $this->forge->addColumn('partner_details', [
            'service_range' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
        ]);
    }
}
