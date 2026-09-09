<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTranslatedHandymanDetailsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        $languageIdUnsigned = false;
        if ($db->tableExists('languages')) {
            $row = $db->query(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'languages' AND COLUMN_NAME = 'id' LIMIT 1"
            )->getRowArray();
            $languageIdUnsigned = !empty($row['COLUMN_TYPE']) && stripos($row['COLUMN_TYPE'], 'unsigned') !== false;
        }

        $languageIdField = [
            'type'       => 'INT',
            'constraint' => 11,
            'null'       => false,
        ];
        if ($languageIdUnsigned) {
            $languageIdField['unsigned'] = true;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'handyman_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'language_id' => $languageIdField,
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['handyman_id', 'language_id']);
        $this->forge->addForeignKey('handyman_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('translated_handyman_details', true);

        if ($db->tableExists('languages')) {
            $db->query('
                ALTER TABLE `translated_handyman_details`
                ADD CONSTRAINT `fk_thd_language_id`
                FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ');
        }
    }

    public function down()
    {
        $this->forge->dropTable('translated_handyman_details', true);
    }
}
