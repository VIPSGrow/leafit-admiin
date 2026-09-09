<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddChannelsColumnIfMissingToNotificationDispatches extends Migration
{
    public function up()
    {
        // CI4's fieldExists() uses a metadata cache that can return a stale
        // true even when the column is absent, causing the earlier migration
        // (2026-04-07-051853) to skip the addColumn silently. Bypass the cache
        // entirely by querying INFORMATION_SCHEMA directly.
        $db     = \Config\Database::connect();
        $dbName = $db->getDatabase();

        $exists = $db->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME   = 'notification_dispatches'
               AND COLUMN_NAME  = 'channels'
             LIMIT 1",
            [$dbName]
        )->getRow();

        if (!$exists) {
            $db->query("
                ALTER TABLE `notification_dispatches`
                ADD COLUMN `channels` VARCHAR(100) NOT NULL DEFAULT ''
                    COMMENT 'Comma-separated queued channels e.g. fcm,email,sms'
                    AFTER `event_type`
            ");
        }

        $completedExists = $db->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME   = 'notification_dispatches'
               AND COLUMN_NAME  = 'completed_channels'
             LIMIT 1",
            [$dbName]
        )->getRow();

        if (!$completedExists) {
            $afterCol = $this->columnExistsRaw($db, $dbName, 'channels') ? 'channels' : 'event_type';
            $db->query("
                ALTER TABLE `notification_dispatches`
                ADD COLUMN `completed_channels` VARCHAR(100) NOT NULL DEFAULT ''
                    COMMENT 'Comma-separated successfully dispatched channels e.g. fcm,email,sms'
                    AFTER `{$afterCol}`
            ");
        }
    }

    public function down()
    {
        $db     = \Config\Database::connect();
        $dbName = $db->getDatabase();

        foreach (['completed_channels', 'channels'] as $column) {
            $exists = $db->query(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME   = 'notification_dispatches'
                   AND COLUMN_NAME  = ?
                 LIMIT 1",
                [$dbName, $column]
            )->getRow();

            if ($exists) {
                $db->query("ALTER TABLE `notification_dispatches` DROP COLUMN `{$column}`");
            }
        }
    }

    private function columnExistsRaw(\CodeIgniter\Database\BaseConnection $db, string $dbName, string $column): bool
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
