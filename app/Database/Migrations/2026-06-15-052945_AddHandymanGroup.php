<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHandymanGroup extends Migration
{
    public function up()
    {
        // Upsert: group 4 exists as 'superadmin' (zero users, legacy seed) — repurpose as handyman.
        $exists = $this->db->table('groups')->where('id', 4)->countAllResults();
        if ($exists) {
            $this->db->table('groups')->where('id', 4)->update([
                'name'        => 'handyman',
                'description' => 'Handyman panel user',
            ]);
        } else {
            $this->db->table('groups')->insert([
                'id'          => 4,
                'name'        => 'handyman',
                'description' => 'Handyman panel user',
            ]);
        }
    }

    public function down()
    {
        $this->db->table('groups')->where('id', 4)->update([
            'name'        => 'superadmin',
            'description' => 'Super Admin',
        ]);
    }
}
