<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed the "city" custom field into customer_address group and
 * backfill existing addresses.city values into customer_address_custom_fields.
 *
 * The addresses.city column is NOT dropped — it remains as a mirror
 * for backward compatibility with the orders flow.
 */
class SeedCityCustomFieldAndBackfill extends Migration
{
    private array $field = [
        'field_key'   => 'city',
        'field_label' => 'City',
        'field_type'  => 'text',
        'sort_order'  => 3,
        'required'    => 0,
        'visible'     => 1,
    ];

    public function up(): void
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('custom_fields')) {
            return;
        }

        // --- 1. Seed the city custom field definition (idempotent) ---
        $exists = $db->table('custom_fields')
            ->where('field_key', $this->field['field_key'])
            ->where('field_group', 'customer_address')
            ->countAllResults();

        if ($exists > 0) {
            $cityFieldId = (int) $db->table('custom_fields')
                ->select('id')
                ->where('field_key', $this->field['field_key'])
                ->where('field_group', 'customer_address')
                ->get()
                ->getRow()
                ->id;
        } else {
            $now = date('Y-m-d H:i:s');

            // Bump sort_order of any existing fields that are >= 3 to make room.
            $db->query(
                "UPDATE `custom_fields`
                 SET `sort_order` = `sort_order` + 1
                 WHERE `field_group` = 'customer_address' AND `sort_order` >= ?",
                [$this->field['sort_order']]
            );

            $db->table('custom_fields')->insert([
                'field_key'   => $this->field['field_key'],
                'field_label' => $this->field['field_label'],
                'field_type'  => $this->field['field_type'],
                'field_group' => 'customer_address',
                'file_config' => null,
                'required'    => $this->field['required'],
                'visible'     => $this->field['visible'],
                'sort_order'  => $this->field['sort_order'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $cityFieldId = (int) $db->insertID();

            // Seed translation rows for all active languages.
            if ($cityFieldId > 0 && $db->tableExists('translated_custom_fields')) {
                $languages = $db->table('languages')->select('code')->get()->getResultArray();
                foreach ($languages as $language) {
                    $code = (string) ($language['code'] ?? '');
                    if ($code === '') {
                        continue;
                    }
                    $db->table('translated_custom_fields')->insert([
                        'custom_field_id' => $cityFieldId,
                        'language_code'   => $code,
                        'field_label'     => $this->field['field_label'],
                    ]);
                }
            }
        }

        // --- 2. Backfill existing addresses.city into custom field values ---
        if ($cityFieldId <= 0 || !$db->tableExists('customer_address_custom_fields')) {
            return;
        }

        $db->query(
            "INSERT INTO `customer_address_custom_fields`
                (`address_id`, `custom_field_id`, `value`, `created_at`, `updated_at`)
             SELECT
                a.`id`,
                ?,
                a.`city`,
                NOW(),
                NOW()
             FROM `addresses` a
             LEFT JOIN `customer_address_custom_fields` cacf
                ON cacf.`address_id` = a.`id` AND cacf.`custom_field_id` = ?
             WHERE a.`city` IS NOT NULL
               AND a.`city` != ''
               AND cacf.`id` IS NULL",
            [$cityFieldId, $cityFieldId]
        );
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('custom_fields')) {
            return;
        }

        $row = $db->table('custom_fields')
            ->select('id')
            ->where('field_key', $this->field['field_key'])
            ->where('field_group', 'customer_address')
            ->get()
            ->getRow();

        if (!$row) {
            return;
        }

        $cityFieldId = (int) $row->id;

        if ($db->tableExists('customer_address_custom_fields')) {
            $db->table('customer_address_custom_fields')
                ->where('custom_field_id', $cityFieldId)
                ->delete();
        }

        if ($db->tableExists('translated_custom_fields')) {
            $db->table('translated_custom_fields')
                ->where('custom_field_id', $cityFieldId)
                ->delete();
        }

        $db->table('custom_fields')
            ->where('id', $cityFieldId)
            ->delete();

        $db->query(
            "UPDATE `custom_fields`
             SET `sort_order` = `sort_order` - 1
             WHERE `field_group` = 'customer_address' AND `sort_order` > ?",
            [$this->field['sort_order']]
        );
    }
}
