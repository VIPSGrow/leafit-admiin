<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCancellationFieldsToOrders extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('orders')) {
            return;
        }

        $fields = $db->getFieldNames('orders');

        if (! in_array('cancel_reason_id', $fields, true)) {
            $db->query("
                ALTER TABLE `orders`
                ADD COLUMN `cancel_reason_id` INT(11) UNSIGNED NULL DEFAULT NULL
                COMMENT 'References reasons.id where type = cancel'
            ");
        }

        if (! in_array('cancel_additional_info', $fields, true)) {
            $db->query("
                ALTER TABLE `orders`
                ADD COLUMN `cancel_additional_info` VARCHAR(500) NULL DEFAULT NULL
                COMMENT 'Free text supplied when reason.needs_additional_info = 1'
            ");
        }

        if (! in_array('cancelled_by', $fields, true)) {
            $db->query("
                ALTER TABLE `orders`
                ADD COLUMN `cancelled_by` ENUM('customer','provider','admin') NULL DEFAULT NULL
                COMMENT 'Actor that cancelled the order'
            ");
        }

        if ($db->tableExists('reasons')) {
            try {
                $db->query("
                    ALTER TABLE `orders`
                    ADD CONSTRAINT `fk_orders_cancel_reason`
                    FOREIGN KEY (`cancel_reason_id`) REFERENCES `reasons`(`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('orders')) {
            return;
        }

        try {
            $db->query("ALTER TABLE `orders` DROP FOREIGN KEY `fk_orders_cancel_reason`");
        } catch (\Throwable $e) {
        }

        $fields = $db->getFieldNames('orders');

        foreach (['cancel_reason_id', 'cancel_additional_info', 'cancelled_by'] as $col) {
            if (in_array($col, $fields, true)) {
                try {
                    $db->query("ALTER TABLE `orders` DROP COLUMN `{$col}`");
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
