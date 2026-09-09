<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUpdateAtToLanguagesTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $fields = $db->getFieldData('languages');
        $columnExists = false;

        foreach ($fields as $field) {
            if ($field->name === 'updated_at') {
                $columnExists = true;
                break;
            }
        }

        if (!$columnExists) {
            $this->forge->addColumn('languages', [
                'updated_at' => [
                    'type'       => 'DATETIME',
                    'null'       => true,
                    'after'      => 'created_at', // optional, adjust if needed
                ],
            ]);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->fieldExists('updated_at', 'languages')) {
            $this->forge->dropColumn('languages', 'updated_at');
        }
    }
}
