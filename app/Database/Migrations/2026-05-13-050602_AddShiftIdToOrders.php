<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShiftIdToOrders extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('orders')) {
            return;
        }

        $fields = $db->getFieldNames('orders');
        if (in_array('shift_id', $fields, true)) {
            return;
        }

        $db->query("ALTER TABLE `orders` ADD COLUMN `shift_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `duration`");
        try {
            $db->query("ALTER TABLE `orders` ADD INDEX `idx_orders_shift_id` (`shift_id`)");
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('orders')) {
            return;
        }

        try {
            $db->query("ALTER TABLE `orders` DROP INDEX `idx_orders_shift_id`");
        } catch (\Throwable $e) {
        }

        $fields = $db->getFieldNames('orders');
        if (in_array('shift_id', $fields, true)) {
            $db->query("ALTER TABLE `orders` DROP COLUMN `shift_id`");
        }
    }
}
