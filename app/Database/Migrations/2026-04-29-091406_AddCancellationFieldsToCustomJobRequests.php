<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCancellationFieldsToCustomJobRequests extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('custom_job_requests')) {
            return;
        }

        $fields = $db->getFieldNames('custom_job_requests');

        if (! in_array('cancel_reason_id', $fields, true)) {
            $db->query("
                ALTER TABLE `custom_job_requests`
                ADD COLUMN `cancel_reason_id` INT(11) UNSIGNED NULL DEFAULT NULL
                COMMENT 'References reasons.id where type = cancel'
            ");
        }

        if (! in_array('cancel_additional_info', $fields, true)) {
            $db->query("
                ALTER TABLE `custom_job_requests`
                ADD COLUMN `cancel_additional_info` VARCHAR(500) NULL DEFAULT NULL
                COMMENT 'Free text supplied when reason.needs_additional_info = 1'
            ");
        }

        if ($db->tableExists('reasons')) {
            try {
                $db->query("
                    ALTER TABLE `custom_job_requests`
                    ADD CONSTRAINT `fk_custom_job_requests_cancel_reason`
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

        if (! $db->tableExists('custom_job_requests')) {
            return;
        }

        try {
            $db->query("ALTER TABLE `custom_job_requests` DROP FOREIGN KEY `fk_custom_job_requests_cancel_reason`");
        } catch (\Throwable $e) {
        }

        $fields = $db->getFieldNames('custom_job_requests');

        foreach (['cancel_reason_id', 'cancel_additional_info', 'cancelled_by'] as $col) {
            if (in_array($col, $fields, true)) {
                try {
                    $db->query("ALTER TABLE `custom_job_requests` DROP COLUMN `{$col}`");
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
