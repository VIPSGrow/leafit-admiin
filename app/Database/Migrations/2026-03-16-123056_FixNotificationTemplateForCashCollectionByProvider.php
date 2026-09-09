<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixNotificationTemplateForCashCollectionByProvider extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Update notification template body for cash collection by provider event
        $db->table('notification_templates')
            ->where('event_key', 'cash_collection_by_provider')
            ->set('body', "Provider [[provider_name]] (ID: [[provider_id]]) has completed COD booking #[[booking_id]]. Admin commission of [[currency]][[amount]] is due.")
            ->update();
    }

    public function down()
    {
        //
    }
}
