<?php

namespace App\Models;

use CodeIgniter\Model;

class Addresses_model  extends Model
{
    protected $table = 'addresses';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id ', 'type', 'address', 'area', 'mobile', 'alternate_mobile', 'pincode', 'city_id', 'landmark', 'state', 'country', 'lattitude', 'longitude', 'is_default'];

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('addresses a');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];
        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        $sort = "a.id";
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'a.id') {
                $sort = "a.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        $order = "ASC";
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`a.id`' => $search,
                '`a.type`' => $search,
                '`a.address`' => $search,
                '`a.area`' => $search,
                '`a.mobile`' => $search,
                '`a.alternate_mobile`' => $search,
                '`a.pincode`' => $search,
                '`a.state`' => $search,
                '`a.country`' => $search,
                '`u.username`' => $search,
            ];
        }
        $address_count = $builder->select('count(a.id) as total')
            ->join('users u', 'u.id=a.user_id');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $address_count = $builder->get()->getResultArray();
        $total = $address_count[0]['total'];
        $builder->select('a.id,a.user_id,a.type,a.address,a.area,a.mobile,a.alternate_mobile,a.pincode,a.city_id,a.landmark,a.state,a.country,a.lattitude,a.longitude,a.is_default,u.username')
            ->join('users u', 'u.id=a.user_id');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $address_record = [];
        $address_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $rows = [];
        foreach ($address_record as $row) {
            $tempRow = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'username' => $row['username'],
                'type' => $row['type'],
                'address' => $row['address'],
                'city_name' => '',
                'area' => $row['area'],
                'mobile' => $row['mobile'],
                'alternate_mobile' => $row['alternate_mobile'],
                'pincode' => $row['pincode'],
                'city_id' => $row['city_id'],
                'landmark' => $row['landmark'],
                'state' => $row['state'],
                'country' => $row['country'],
                'lattitude' => $row['lattitude'],
                'longitude' => $row['longitude'],
                'is_default' => $row['is_default']
            ];
            $rows[] = $tempRow;
        }

        // Rebuild address strings from custom fields.
        self::buildAddressFromCustomFields($rows);

        $bulkData['rows'] = $rows;
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            return json_encode($bulkData);
        }
    }

    /**
     * Overlay customer address custom field values onto address rows and
     * rebuild the 'address' string from custom field values in sort_order.
     *
     * Accepts a single address row (associative array with 'id' key) or a
     * list of address rows. Rows are modified in-place by reference.
     *
     * The row key used for the address ID lookup defaults to 'id' but can be
     * overridden via $addressIdKey (e.g. 'address_id' when working with
     * order rows that carry the address id under a different key).
     *
     * @param array<string,mixed>|array<int,array<string,mixed>> $rows
     * @param string $addressIdKey  Key holding the address id in each row
     */
    public static function buildAddressFromCustomFields(array &$rows, string $addressIdKey = 'id'): void
    {
        if (empty($rows)) {
            return;
        }

        // Detect whether a single row or a list was passed.
        $isSingle = isset($rows[$addressIdKey]) && !is_array($rows[$addressIdKey]);
        if ($isSingle) {
            $rows = [$rows];
        }

        $cfModel = new \App\Models\CustomFieldModel();
        if (!$cfModel->customerAddressCustomFieldsTableExists()) {
            if ($isSingle) {
                $rows = $rows[0];
            }
            return;
        }

        $db = \Config\Database::connect();

        // Load all customer_address field definitions so we can initialize
        // every field key on each row (ensures frontend can rely on the keys).
        $allFieldDefs = $db->table('custom_fields')
            ->select('id, sort_order')
            ->where('field_group', 'customer_address')
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        $fieldIds = array_column($allFieldDefs, 'id');
        $orderedFieldIds = array_values(array_unique(array_column($allFieldDefs, 'id')));

        // Initialize all custom field keys with null on every row.
        foreach ($rows as &$row) {
            foreach ($fieldIds as $cfId) {
                $cfKey = 'cf_' . $cfId;
                if (!array_key_exists($cfKey, $row)) {
                    $row[$cfKey] = null;
                }
            }
        }
        unset($row);

        $addressIds = array_filter(array_column($rows, $addressIdKey));
        if (empty($addressIds)) {
            if ($isSingle) {
                $rows = $rows[0];
            }
            return;
        }

        $cfRows = $db->table('customer_address_custom_fields cacf')
            ->select('cacf.address_id, cacf.custom_field_id, cacf.value')
            ->whereIn('cacf.address_id', $addressIds)
            ->get()
            ->getResultArray();

        // Index by address_id → custom_field_id → value for O(1) lookup.
        $byAddressId = [];
        foreach ($cfRows as $cfRow) {
            $byAddressId[(int) $cfRow['address_id']][(int) $cfRow['custom_field_id']] = $cfRow['value'];
        }

        foreach ($rows as &$row) {
            $aid = (int) ($row[$addressIdKey] ?? 0);
            $cfValues = $byAddressId[$aid] ?? [];

            foreach ($cfValues as $cfId => $value) {
                $row['cf_' . $cfId] = $value;
            }

            $addressParts = [];
            foreach ($orderedFieldIds as $cfId) {
                $val = $cfValues[$cfId] ?? ($row['cf_' . $cfId] ?? null);
                if ($val !== null && $val !== '') {
                    $addressParts[] = $val;
                }
            }
            if (!empty($addressParts)) {
                $row['address'] = implode(', ', $addressParts);
            }
        }
        unset($row);

        if ($isSingle) {
            $rows = $rows[0];
        }
    }

    /**
     * Rebuild the 'address' string on order rows using custom fields.
     *
     * Collects unique address_id values from the given rows, fetches the
     * corresponding address records, overlays custom fields on them, and
     * maps the rebuilt address string back onto each order row.
     *
     * @param array<int,array<string,mixed>> $rows  Order rows (must contain 'address_id')
     */
    public static function rebuildAddressOnOrderRows(array &$rows): void
    {
        if (empty($rows)) {
            return;
        }

        $addressIds = array_unique(array_filter(array_column($rows, 'address_id')));
        if (empty($addressIds)) {
            return;
        }

        // Fetch address records for all referenced address_ids.
        $db = \Config\Database::connect();
        $addressRows = $db->table('addresses')
            ->select('id, address, area, landmark, pincode, state, country')
            ->whereIn('id', $addressIds)
            ->get()
            ->getResultArray();

        if (empty($addressRows)) {
            return;
        }

        // Pre-build the full address string from base columns so that if no
        // custom field values exist for an address the fallback includes all
        // address parts, not just the bare 'address' column.
        foreach ($addressRows as &$ar) {
            $baseParts = array_filter([
                $ar['address'],
                $ar['area'],
                $ar['landmark'],
                $ar['pincode'],
                $ar['state'],
                $ar['country'],
            ]);
            $ar['address'] = implode(', ', $baseParts);
        }
        unset($ar);

        // Overlay custom fields — rebuilds 'address' when custom field values exist.
        self::buildAddressFromCustomFields($addressRows);

        // Index rebuilt addresses by id.
        $addressMap = [];
        foreach ($addressRows as $ar) {
            $addressMap[(int) $ar['id']] = $ar['address'];
        }

        // Map back onto order rows.
        foreach ($rows as &$row) {
            $aid = (int) ($row['address_id'] ?? 0);
            if ($aid && isset($addressMap[$aid])) {
                $row['address'] = $addressMap[$aid];
            }
        }
        unset($row);
    }
}
