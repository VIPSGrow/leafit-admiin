<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddChannelsToNotificationDispatches extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('notification_dispatches') || $db->fieldExists('channels', 'notification_dispatches')) {
            return;
        }

        $this->forge->addColumn('notification_dispatches', [
            'channels' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => '',
                'after'      => 'event_type',
                'comment'    => 'Comma-separated queued channels e.g. fcm,email,sms',
            ],
        ]);
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('notification_dispatches') || ! $db->fieldExists('channels', 'notification_dispatches')) {
            return;
        }

        $this->forge->dropColumn('notification_dispatches', 'channels');
    }
}
