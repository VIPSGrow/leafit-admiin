<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProviderLocationsTable extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('provider_locations')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'provider_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'address' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'city' => [
                    'type' => 'VARCHAR',
                    'constraint' => 191,
                    'null' => true,
                ],
                'latitude' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,7',
                    'null' => false,
                ],
                'longitude' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,7',
                    'null' => false,
                ],
                'is_default' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('provider_id');
            $this->forge->addKey(['provider_id', 'is_active']);
            $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('provider_locations');
        }

        $this->db->query(
            "INSERT INTO provider_locations
                (provider_id, address, city, latitude, longitude, is_default, is_active, created_at, updated_at)
             SELECT DISTINCT u.id, COALESCE(pd.address, ''), u.city, u.latitude, u.longitude, 1, 1, NOW(), NOW()
             FROM users u
             INNER JOIN users_groups ug ON ug.user_id = u.id AND ug.group_id = 3
             LEFT JOIN partner_details pd ON pd.partner_id = u.id
             LEFT JOIN provider_locations pl ON pl.provider_id = u.id
             WHERE u.latitude IS NOT NULL
               AND u.longitude IS NOT NULL
               AND pl.id IS NULL"
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('provider_locations', true);
    }
}