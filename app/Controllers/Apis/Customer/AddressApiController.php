<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Addresses_model;
use App\Libraries\JWT;
use App\Models\CustomFieldModel;

class AddressApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1"
    ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        } else {
            $token = verify_app_request();
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function add_address()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'address_id'       => 'permit_empty',
                'mobile'           => 'permit_empty|numeric',
                'city_name'        => 'permit_empty',
                'lattitude'        => 'permit_empty|numeric',
                'longitude'        => 'permit_empty|numeric',
                'area'             => 'permit_empty',
                'type'             => 'permit_empty',
                'country_code'     => 'permit_empty',
                'alternate_mobile' => 'permit_empty|numeric',
                'landmark'         => 'permit_empty',
                'pincode'          => 'permit_empty|numeric',
                'state'            => 'permit_empty',
                'country'          => 'permit_empty',
                'is_default'       => 'permit_empty',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $errorMessages = [];
                foreach ($errors as $field => $message) {
                    $errorMessages[] = $message;
                }
                $response = [
                    'error'   => true,
                    'message' => implode(', ', $errorMessages)
                ];
                return $this->response->setJSON($response);
            }

            $postData = $this->request->getPost();

            $data = ['user_id' => $this->user_details['id']];

            $fieldMapping = [
                'type'             => 'type',
                'area'             => 'area',
                'mobile'           => 'mobile',
                'city_name'        => 'city',
                'lattitude'        => 'lattitude',
                'longitude'        => 'longitude',
                'alternate_mobile' => 'alternate_mobile',
                'pincode'          => 'pincode',
                'landmark'         => 'landmark',
                'state'            => 'state',
                'country'          => 'country',
                'is_default'       => 'is_default',
            ];

            foreach ($fieldMapping as $postField => $dbField) {
                if (isset($postData[$postField])) {
                    $data[$dbField] = $postData[$postField];
                }
            }

            if (!isset($data['is_default'])) {
                $data['is_default'] = 0;
            }

            // Parse dynamic custom_fields array from the request.
            // Expected format: custom_fields = [{"custom_field_id": 1, "value": "..."}, ...]
            // Accepts both JSON string and form-encoded array.
            $customFieldValues = [];
            $rawCustomFields = $postData['custom_fields'] ?? null;
            if ($rawCustomFields !== null) {
                if (is_string($rawCustomFields)) {
                    $rawCustomFields = json_decode($rawCustomFields, true);
                }
                if (is_array($rawCustomFields)) {
                    $validationError = $this->validateCustomFields($rawCustomFields);
                    if ($validationError !== null) {
                        return $this->response->setJSON([
                            'error'   => true,
                            'message' => $validationError,
                        ]);
                    }
                    foreach ($rawCustomFields as $entry) {
                        $cfId  = (int) $entry['custom_field_id'];
                        // Preserve explicit empty string; null means "not sent".
                        $value = array_key_exists('value', $entry) ? $entry['value'] : null;
                        $customFieldValues[$cfId] = $value;
                    }
                }
            }

            // Auto-inject city_name POST value into custom field values so it
            // is persisted in customer_address_custom_fields. The addresses.city
            // column is still written via $fieldMapping as a mirror for orders.
            if (isset($postData['city_name']) && $postData['city_name'] !== '') {
                $cfModel = new CustomFieldModel();
                if ($cfModel->customerAddressCustomFieldsTableExists()) {
                    $db = \Config\Database::connect();
                    $cityField = $db->table('custom_fields')
                        ->select('id')
                        ->where('field_label', 'City')
                        ->where('field_group', 'customer_address')
                        ->get()
                        ->getRow();
                    if ($cityField) {
                        $cityFieldId = (int) $cityField->id;
                        // Only inject if the client didn't already include it in custom_fields.
                        if (!array_key_exists($cityFieldId, $customFieldValues)) {
                            $customFieldValues[$cityFieldId] = $postData['city_name'];
                        }
                    }
                }
            }

            // --- Update existing address ---
            if (isset($postData['address_id']) && !empty($postData['address_id'])) {
                if (!exists(['id' => $postData['address_id']], 'addresses')) {
                    return response_helper(labels(ADDRESS_NOT_EXIST, 'address not exist'));
                }

                $address_id = $postData['address_id'];

                if (isset($data['is_default']) && $data['is_default'] == 1) {
                    update_details(['is_default' => '0'], ['user_id' => $this->user_details['id']], 'addresses');
                }

                if (update_details($data, ['id' => $address_id], 'addresses')) {
                    $this->upsertAddressCustomFields((int) $address_id, $customFieldValues);
                    $updated_address = fetch_details('addresses', ['id' => $address_id])[0];
                    $this->overlayAddressCustomFieldsBulk($updated_address);
                    return response_helper(labels(ADDRESS_UPDATED_SUCCESSFULLY, 'address updated successfully'), false, $updated_address);
                }

                return response_helper(labels(ADDRESS_NOT_UPDATED, 'address not updated'), true);
            }

            // --- Add new address ---
            if ($address = insert_details($data, 'addresses')) {
                if (isset($data['is_default']) && $data['is_default'] == 1) {
                    update_details(['is_default' => '0'], ['user_id' => $data['user_id'], 'id !=' => $address['id']], 'addresses');
                }

                $this->upsertAddressCustomFields((int) $address['id'], $customFieldValues);
                $new_address = fetch_details('addresses', ['id' => $address['id']])[0];
                $this->overlayAddressCustomFieldsBulk($new_address);
                return response_helper(labels(ADDRESS_ADDED_SUCCESSFULLY, 'address added successfully'), false, $new_address);
            }

            return response_helper(labels(ADDRESS_NOT_ADDED, 'address not added'), true);
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - add_address()');
            return response_helper(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true);
        }
    }

    public function get_address($address_id = 0)
    {
        try {
            $limit  = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 20;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort   = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order  = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where  = [];
            $where['a.user_id'] = $this->user_details['id'];
            if ($this->request->getPost('address_id')) {
                $where['a.id'] = $this->request->getPost('address_id');
            }
            if (!empty($address_id)) {
                $where['a.id'] = $address_id;
            }
            $address_model = new Addresses_model();
            $address = $address_model->list(true, $search, $limit, $offset, $sort, $order, $where);
            $is_default_counter = array_count_values(array_column($address['data'], 'is_default'));
            if (!isset($is_default_counter['1']) && !empty($address['data'])) {
                update_details(['is_default' => '1'], ['id' => $address['data'][0]['id']], 'addresses');
            }

            if (!empty($address_id)) {
                return remove_null_values($address['data']);
            }
            if (!empty($address['data'])) {
                return response_helper(labels(ADDRESSES_FETCHED_SUCCESSFULLY, 'addresses fetched successfully'), false, remove_null_values($address['data']), 200, ['total' => $address['total']]);
            } else {
                return response_helper(labels(ADDRESS_NOT_FOUND, 'address not found'), false);
            }
        } catch (\Exception $th) {
            $response['error']   = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_address()');
            return $this->response->setJSON($response);
        }
    }

    public function delete_address()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'address_id' => 'required',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $errorMessages = [];
                foreach ($errors as $field => $message) {
                    $errorMessages[] = $message;
                }
                $response = [
                    'error'   => true,
                    'message' => implode(', ', $errorMessages),
                    'data'    => [],
                ];
                return $this->response->setJSON($response);
            }
            $address_id = $this->request->getPost('address_id');
            $data1 = [];
            if (!exists(['id' => $this->request->getPost('address_id'), 'user_id' => $this->user_details['id']], 'addresses')) {
                return response(labels(ADDRESS_NOT_EXIST, 'address not exist'));
            }
            if (delete_details(['id' => $address_id], 'addresses')) {
                $is_default_counter = fetch_details('addresses', ['user_id' => $this->user_details['id'], 'is_default' => '1']);
                if (empty($is_default_counter)) {
                    $data = fetch_details('addresses', ['user_id' => $this->user_details['id']]);
                    if (!empty($data[0])) {
                        update_details(['is_default' => '1'], ['id' => $data[0]['id']], 'addresses');
                    }
                    $data1 = fetch_details('addresses', ['user_id' => $this->user_details['id']]);
                }
                return response_helper(labels(ADDRESS_DELETED_SUCCESSFULLY, 'Address Deleted successfully'), false, $data1);
            } else {
                return response_helper(labels(ADDRESS_NOT_DELETED, 'Address not deleted'));
            }
        } catch (\Exception $th) {
            $response['error']   = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - delete_address()');
            return $this->response->setJSON($response);
        }
    }

    /**
     * Validate the custom_fields array from the request.
     *
     * Checks that:
     * - Each entry has a valid custom_field_id belonging to `customer_address` group
     * - All fields marked required=1 are present with a non-empty value
     * - No duplicate custom_field_id entries
     *
     * @param  array $customFields  Raw array of ['custom_field_id' => int, 'value' => string]
     * @return string|null          Error message on failure, null on success
     */
    private function validateCustomFields(array $customFields): ?string
    {
        if (empty($customFields)) {
            return null;
        }

        $cfModel = new CustomFieldModel();
        if (!$cfModel->customFieldsTableExists()) {
            return null;
        }

        $db = \Config\Database::connect();

        // Load all customer_address field definitions in one query.
        $allDefs = $db->table('custom_fields')
            ->select(['id', 'field_label', 'required'])
            ->where('field_group', 'customer_address')
            ->get()
            ->getResultArray();

        $validIds = [];
        $requiredIds = [];
        foreach ($allDefs as $def) {
            $id = (int) $def['id'];
            $validIds[$id] = $def['field_label'];
            if ((int) $def['required'] === 1) {
                $requiredIds[$id] = $def['field_label'];
            }
        }

        $seenIds = [];
        $providedIds = [];
        foreach ($customFields as $entry) {
            if (!isset($entry['custom_field_id'])) {
                return labels('custom_field_id_required', 'Each custom field entry must include custom_field_id');
            }
            $cfId = (int) $entry['custom_field_id'];

            if (!isset($validIds[$cfId])) {
                return labels('invalid_custom_field_id', 'Invalid custom_field_id') . ': ' . $cfId;
            }

            if (isset($seenIds[$cfId])) {
                return labels('duplicate_custom_field_id', 'Duplicate custom_field_id') . ': ' . $cfId;
            }
            $seenIds[$cfId] = true;

            $value = array_key_exists('value', $entry) ? $entry['value'] : null;
            if ($value !== null && $value !== '') {
                $providedIds[$cfId] = true;
            }
        }

        // Check required fields have non-empty values.
        foreach ($requiredIds as $reqId => $fieldKey) {
            if (empty($providedIds[$reqId])) {
                return labels('custom_field_required', 'Required custom field is missing or empty') . ': ' . $fieldKey;
            }
        }

        return null;
    }

    /**
     * Upsert custom field values into customer_address_custom_fields.
     *
     * Accepts an array keyed by custom_field_id (int) with string|null values.
     * Null values are skipped (omitted fields don't overwrite existing data).
     * An explicit empty string IS written (to clear a previously saved value).
     *
     * @param int   $addressId
     * @param array<int, string|null> $values  e.g. [1 => '123 Main St', 2 => 'Apt 4B']
     */
    private function upsertAddressCustomFields(int $addressId, array $values): void
    {
        if ($addressId <= 0 || empty($values)) {
            return;
        }

        $cfModel = new CustomFieldModel();
        if (!$cfModel->customerAddressCustomFieldsTableExists()) {
            return;
        }

        $db = \Config\Database::connect();

        foreach ($values as $cfId => $value) {
            // Skip null (field not sent by client). Empty string is allowed to clear.
            if ($value === null) {
                continue;
            }
            $db->query(
                "INSERT INTO `customer_address_custom_fields`
                    (`address_id`, `custom_field_id`, `value`, `created_at`, `updated_at`)
                 VALUES (?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()",
                [$addressId, (int) $cfId, (string) $value]
            );
        }
    }

    /**
     * Overlay customer-address custom field values onto address rows.
     * Delegates to the shared Addresses_model::buildAddressFromCustomFields().
     *
     * @param array<string,mixed>|array<int,array<string,mixed>> $rows
     */
    private function overlayAddressCustomFieldsBulk(array &$rows): void
    {
        Addresses_model::buildAddressFromCustomFields($rows);
    }
}
