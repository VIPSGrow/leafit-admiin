<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add gateway_template_ids column to sms_templates (base table).
 *
 * Some SMS gateways (Fast2SMS DLT, MSG91) require sending a verified template ID
 * from the provider. Stored in base table so it works even when clients have
 * not edited/created translated_sms_templates rows.
 */
class AddGatewayTemplateIdsToSmsTemplates extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sms_templates', [
            'gateway_template_ids' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Template IDs per gateway, e.g. {"fast2sms":"123","msg91":"abc"}',
                'after'   => 'parameters',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sms_templates', 'gateway_template_ids');
    }
}
