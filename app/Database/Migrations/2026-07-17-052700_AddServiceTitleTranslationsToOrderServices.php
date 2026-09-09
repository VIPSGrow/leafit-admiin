<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddServiceTitleTranslationsToOrderServices extends Migration
{
    public function up()
    {
        $fields = [
            'service_title_translations' => [
                'type'       => 'LONGTEXT',
                'null'       => true,
                'after'      => 'service_title',
            ],
        ];

        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if (! $db->fieldExists('service_title_translations', 'order_services')) {
            $this->forge->addColumn('order_services', $fields);
        }
    }

    public function down()
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->fieldExists('service_title_translations', 'order_services')) {
            $this->forge->dropColumn('order_services', 'service_title_translations');
        }
    }
}
