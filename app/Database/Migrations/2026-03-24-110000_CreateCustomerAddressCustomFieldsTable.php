<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Customer Address Custom Fields — value-storage table + backfill.
 *
 * Creates:
 * - customer_address_custom_fields: one value row per address per custom field.
 *
 * Backfills:
 * - Copies existing `addresses.address` values into the new table for the
 *   seeded `address` field (field_key = 'address', field_group = 'customer_address').
 *   `address_2` is a new field so no backfill is needed.
 */
class CreateCustomerAddressCustomFieldsTable extends Migration
{
    private function foreignKeyExists($db, string $table, string $constraintName): bool
    {
        $result = $db->query(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
             LIMIT 1",
            [$table, $constraintName]
        )->getRowArray();

        return ! empty($result);
    }

    public function up(): void
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('customer_address_custom_fields')) {
            $db->query("
                CREATE TABLE `customer_address_custom_fields` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `address_id` INT(11) NOT NULL COMMENT 'References addresses.id',
                    `custom_field_id` INT(11) UNSIGNED NOT NULL COMMENT 'References custom_fields.id',
                    `value` TEXT NULL COMMENT 'Stored text value for this field',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_address_field` (`address_id`, `custom_field_id`),
                    KEY `idx_address_id` (`address_id`),
                    KEY `idx_custom_field_id` (`custom_field_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Values of customer address custom fields: one value per address per field.'
            ");

            // FK to addresses (cascade delete so removing an address cleans up its values).
            if ($db->tableExists('addresses') && ! $this->foreignKeyExists($db, 'customer_address_custom_fields', 'fk_cacf_address')) {
                try {
                    $db->query("
                        ALTER TABLE `customer_address_custom_fields`
                        ADD CONSTRAINT `fk_cacf_address`
                        FOREIGN KEY (`address_id`) REFERENCES `addresses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                    // Ignore: FK may not be supported or may already exist.
                }
            }

            // FK to custom_fields (cascade delete so removing a definition cleans up values).
            if ($db->tableExists('custom_fields') && ! $this->foreignKeyExists($db, 'customer_address_custom_fields', 'fk_cacf_custom_field')) {
                try {
                    $db->query("
                        ALTER TABLE `customer_address_custom_fields`
                        ADD CONSTRAINT `fk_cacf_custom_field`
                        FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ");
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
        }

        // -----------------------------------------------------------------
        // Backfill: copy existing addresses.address into the new table.
        // Only runs if both tables exist and custom_fields has the definition.
        // -----------------------------------------------------------------
        if (! $db->tableExists('addresses') || ! $db->tableExists('custom_fields')) {
            return;
        }

        $addressFieldDef = $db->table('custom_fields')
            ->select('id')
            ->where('field_key', 'address')
            ->where('field_group', 'customer_address')
            ->get()
            ->getRowArray();

        if (empty($addressFieldDef)) {
            return;
        }

        $addressCfId = (int) $addressFieldDef['id'];

        // Fetch all addresses that have a non-empty address value.
        $addresses = $db->table('addresses')
            ->select('id, address')
            ->where('address IS NOT NULL')
            ->where('address !=', '')
            ->get()
            ->getResultArray();

        $now = date('Y-m-d H:i:s');

        foreach ($addresses as $row) {
            $addrId = (int) $row['id'];

            // Skip if already backfilled (idempotent).
            $exists = $db->table('customer_address_custom_fields')
                ->where('address_id', $addrId)
                ->where('custom_field_id', $addressCfId)
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $db->table('customer_address_custom_fields')->insert([
                'address_id'      => $addrId,
                'custom_field_id' => $addressCfId,
                'value'           => $row['address'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('customer_address_custom_fields')) {
            // Drop FKs first to avoid constraint errors on some engines.
            if ($this->foreignKeyExists($db, 'customer_address_custom_fields', 'fk_cacf_address')) {
                try {
                    $db->query("ALTER TABLE `customer_address_custom_fields` DROP FOREIGN KEY `fk_cacf_address`");
                } catch (\Throwable $e) {
                }
            }
            if ($this->foreignKeyExists($db, 'customer_address_custom_fields', 'fk_cacf_custom_field')) {
                try {
                    $db->query("ALTER TABLE `customer_address_custom_fields` DROP FOREIGN KEY `fk_cacf_custom_field`");
                } catch (\Throwable $e) {
                }
            }

            $db->query("DROP TABLE `customer_address_custom_fields`");
        }
    }
}
