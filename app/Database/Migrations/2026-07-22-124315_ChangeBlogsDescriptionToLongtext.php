<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ChangeBlogsDescriptionToLongtext extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('blogs', [
            'description' => [
                'name' => 'description',
                'type' => 'LONGTEXT',
                'null' => false,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('blogs', [
            'description' => [
                'name' => 'description',
                'type' => 'TEXT',
                'null' => false,
            ],
        ]);
    }
}
