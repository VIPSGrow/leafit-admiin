<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFilesToCustomJobRequests extends Migration
{
    public function up()
    {
        $fields = [
            'files' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'status',
                'comment' => 'JSON array of file paths for the custom job request'
            ],
        ];
        $this->forge->addColumn('custom_job_requests', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('custom_job_requests', 'files');
    }
}
