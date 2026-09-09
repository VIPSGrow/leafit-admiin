<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropLegacyColumnsFromPartnerAndAddressTables extends Migration
{
    public function up()
    {
        // Drop city column from addresses
        // (data already migrated to customer_address_custom_fields in 2026-03-31-063708)
        $this->forge->dropColumn('addresses', ['city']);

        // Drop legacy document/banking columns from partner_details
        // (data already migrated to partner_custom_fields in 2026-03-17-100000)
        $this->forge->dropColumn('partner_details', [
            'national_id',
            'address_id',
            'passport',
            'tax_name',
            'tax_number',
            'bank_name',
            'account_number',
            'account_name',
            'bank_code',
            'swift_code',
        ]);
    }

    public function down()
    {
        // Restore city on addresses
        $this->forge->addColumn('addresses', [
            'city' => ['type' => 'VARCHAR', 'constraint' => 252, 'null' => false, 'after' => 'city_id'],
        ]);

        // Restore dropped columns on partner_details
        $this->forge->addColumn('partner_details', [
            'national_id' => ['type' => 'VARCHAR', 'constraint' => 1024, 'null' => true, 'after' => 'about'],
            'address_id' => ['type' => 'VARCHAR', 'constraint' => 1024, 'null' => true, 'after' => 'national_id'],
            'passport' => ['type' => 'VARCHAR', 'constraint' => 1024, 'null' => true, 'after' => 'address_id'],
            'tax_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'passport'],
            'tax_number' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'tax_name'],
            'bank_name' => ['type' => 'VARCHAR', 'constraint' => 256, 'null' => true, 'after' => 'tax_number'],
            'account_number' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false, 'after' => 'bank_name'],
            'account_name' => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true, 'after' => 'account_number'],
            'bank_code' => ['type' => 'VARCHAR', 'constraint' => 256, 'null' => true, 'after' => 'account_name'],
            'swift_code' => ['type' => 'VARCHAR', 'constraint' => 256, 'null' => true, 'after' => 'bank_code'],
        ]);
    }
}
