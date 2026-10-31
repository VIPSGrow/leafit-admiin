<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateServiceProvidersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'service_id' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'provider_id' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
        ]);
        $this->forge->addKey(['service_id', 'provider_id'], true);
        $this->forge->addKey('provider_id');
        $this->forge->createTable('service_providers', true);

        $serviceProvidersTable = $this->db->prefixTable('service_providers');
        $servicesTable = $this->db->prefixTable('services');
        $this->db->query(
            "INSERT INTO {$serviceProvidersTable} (service_id, provider_id)
             SELECT id, user_id FROM {$servicesTable}
             WHERE user_id IS NOT NULL AND user_id > 0"
        );
    }

    public function down()
    {
        $this->forge->dropTable('service_providers', true);
    }
}
