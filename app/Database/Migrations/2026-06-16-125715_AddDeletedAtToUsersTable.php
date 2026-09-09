<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeletedAtToUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'deleted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'updated_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'deleted_at');
    }
}
