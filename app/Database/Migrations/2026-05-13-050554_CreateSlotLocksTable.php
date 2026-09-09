<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSlotLocksTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('slot_locks')) {
            $db->query("
                CREATE TABLE `slot_locks` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT(11) UNSIGNED NOT NULL,
                    `lock_date` DATE NOT NULL,
                    `lock_start_time` TIME NOT NULL,
                    `lock_end_time` TIME NOT NULL,
                    `user_id` INT(11) UNSIGNED NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_partner_date` (`partner_id`, `lock_date`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Short-lived TTL locks held while a customer checks out a slot. Expired rows are swept lazily on next createLock for the same partner+date; reads filter expires_at > NOW().'
            ");

            if ($db->tableExists('users')) {
                try {
                    $db->query("
                        ALTER TABLE `slot_locks`
                        ADD CONSTRAINT `fk_slot_locks_partner`
                        FOREIGN KEY (`partner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                }
                try {
                    $db->query("
                        ALTER TABLE `slot_locks`
                        ADD CONSTRAINT `fk_slot_locks_user`
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('slot_locks')) {
            foreach (['fk_slot_locks_partner', 'fk_slot_locks_user'] as $fk) {
                try {
                    $db->query("ALTER TABLE `slot_locks` DROP FOREIGN KEY `{$fk}`");
                } catch (\Throwable $e) {
                }
            }
        }

        $db->query("DROP TABLE IF EXISTS `slot_locks`");
    }
}
