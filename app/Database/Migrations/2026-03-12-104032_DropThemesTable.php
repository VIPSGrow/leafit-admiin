<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropThemesTable extends Migration
{
    public function up()
    {
        $this->forge->dropTable('themes', true);
    }

    public function down()
    {
        //
    }
}
