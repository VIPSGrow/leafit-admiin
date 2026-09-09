<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddChannelsColumnIfMissingToNotificationDispatches extends Migration
{
    public function up()
    {
        $db     = \Config\Database::connect();
        $dbName = $db->getDatabase();

        if (!$this->columnExists($db, $dbName, 'channels')) {
            $db->query("
                ALTER TABLE `notification_dispatches`
                ADD COLUMN `channels` VARCHAR(100) NOT NULL DEFAULT ''
                    COMMENT 'Comma-separated queued channels e.g. fcm,email,sms'
                    AFTER `event_type`
            ");
        }

        if (!$this->columnExists($db, $dbName, 'completed_channels')) {
            $db->query("
                ALTER TABLE `notification_dispatches`
                ADD COLUMN `completed_channels` VARCHAR(100) NOT NULL DEFAULT ''
                    COMMENT 'Comma-separated successfully dispatched channels e.g. fcm,email,sms'
                    AFTER `channels`
            ");
        }
    }

    public function down()
    {
        $db     = \Config\Database::connect();
        $dbName = $db->getDatabase();

        if ($this->columnExists($db, $dbName, 'completed_channels')) {
            $db->query("ALTER TABLE `notification_dispatches` DROP COLUMN `completed_channels`");
        }

        if ($this->columnExists($db, $dbName, 'channels')) {
            $db->query("ALTER TABLE `notification_dispatches` DROP COLUMN `channels`");
        }
    }

    // -------------------------------------------------------------------

    private function columnExists(\CodeIgniter\Database\BaseConnection $db, string $dbName, string $column): bool
    {
        return (bool) $db->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME   = 'notification_dispatches'
               AND COLUMN_NAME  = ?
             LIMIT 1",
            [$dbName, $column]
        )->getRow();
    }
}