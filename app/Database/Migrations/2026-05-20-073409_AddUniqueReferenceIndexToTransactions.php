<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Enforce unique (type, reference) on transactions so each gateway intent
 * maps to at most one row. NULL references remain distinct (MySQL semantics),
 * preserving legacy rows that never set a gateway reference.
 */
class AddUniqueReferenceIndexToTransactions extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Make reference nullable so empty strings can be NULL (NULLs treated distinct in unique index).
        $fields = $db->getFieldData('transactions');
        $hasReference = false;
        foreach ($fields as $f) {
            if ($f->name === 'reference') {
                $hasReference = true;
                break;
            }
        }
        if (!$hasReference) {
            $this->forge->addColumn('transactions', [
                'reference' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'txn_id',
                ],
            ]);
        } else {
            $db->query("ALTER TABLE `transactions` MODIFY `reference` VARCHAR(255) NULL");
        }

        // Convert empty-string references to NULL so they don't collide on unique index.
        $db->query("UPDATE `transactions` SET `reference` = NULL WHERE `reference` = ''");

        // Dedupe: for any (type, reference) with multiple rows, keep the latest (max id)
        // and NULL the rest. NULL is treated distinct by MySQL unique index, so older rows
        // remain queryable by id but no longer participate in uniqueness.
        $db->query("
            UPDATE `transactions` t
            JOIN (
                SELECT `type`, `reference`, MAX(`id`) AS keep_id
                FROM `transactions`
                WHERE `reference` IS NOT NULL AND `reference` <> ''
                GROUP BY `type`, `reference`
                HAVING COUNT(*) > 1
            ) d ON d.`type` = t.`type` AND d.`reference` = t.`reference` AND t.`id` <> d.keep_id
            SET t.`reference` = NULL
        ");

        // Drop any pre-existing index with same name (idempotent re-run).
        $indexes = $db->query("SHOW INDEX FROM `transactions` WHERE Key_name = 'uniq_type_reference'")->getResultArray();
        if (!empty($indexes)) {
            $db->query("ALTER TABLE `transactions` DROP INDEX `uniq_type_reference`");
        }

        $db->query("ALTER TABLE `transactions` ADD UNIQUE KEY `uniq_type_reference` (`type`, `reference`)");
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $indexes = $db->query("SHOW INDEX FROM `transactions` WHERE Key_name = 'uniq_type_reference'")->getResultArray();
        if (!empty($indexes)) {
            $db->query("ALTER TABLE `transactions` DROP INDEX `uniq_type_reference`");
        }
    }
}
