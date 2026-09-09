<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\CustomFieldModel;

/**
 * Customer Custom Fields API
 *
 * Returns custom field definitions for the `customer_address` group along with
 * the stored values for a given address (when address_id is provided).
 *
 * The client calls this endpoint before rendering the add/edit address form so
 * it can dynamically build inputs, enforce required/visible rules, and display
 * translated labels.
 */
class CustomFieldsApiController extends BaseController
{
    protected $request;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes = [
        "api/v1/index",
        "api/v1",
    ];

    public function __construct()
    {
        helper('api');
        helper('function');
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

    /**
     * POST /api/v1/get_address_custom_fields
     *
     * Returns all visible custom field definitions for the `customer_address`
     * group along with the authenticated customer's stored values for a specific
     * address (when address_id is provided in the request body).
     *
     * Response data:
     *   custom_fields                    – definition for every customer_address
     *                                      field, each entry includes
     *                                      `original_label` (base / default lang)
     *                                      and `translated_label` (resolved for
     *                                      Content-Language header).
     *   customer_address_custom_fields   – stored values for the given address_id.
     *                                      Empty array when address_id is not sent
     *                                      or no values have been saved yet.
     */
    public function get_address_custom_fields()
    {
        try {
            $db               = \Config\Database::connect();
            $customFieldModel = new CustomFieldModel();

            // Guard: tables may not exist on fresh installs.
            if (!$customFieldModel->customFieldsTableExists()) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels('data_fetched_successfully', 'Data fetched successfully'),
                    'data'    => [
                        'custom_fields'                  => [],
                        'customer_address_custom_fields' => [],
                    ],
                ]);
            }

            // --- 1. Fetch customer_address field definitions ---
            $rawFields = $db->table('custom_fields')
                ->select([
                    'id',
                    'field_label',
                    'field_type',
                    'field_group',
                    'file_config',
                    'required',
                    'visible',
                    'sort_order',
                ])
                ->where('field_group', 'customer_address')
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            // --- 2. Resolve labels for requested language ---
            $requestedLang = get_current_language_from_request();
            $defaultLang   = get_default_language();

            // --- 3. Load translations in one query (no N+1) ---
            $translationsByFieldId = [];

            if (!empty($rawFields) && $customFieldModel->translatedTableExists()) {
                $fieldIds = array_column($rawFields, 'id');

                $translationRows = $db->table('translated_custom_fields tcf')
                    ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                    ->join('languages l', 'l.id = tcf.language_id')
                    ->whereIn('tcf.custom_field_id', $fieldIds)
                    ->get()
                    ->getResultArray();

                foreach ($translationRows as $row) {
                    $fid  = (int) $row['custom_field_id'];
                    $code = (string) $row['language_code'];
                    $translationsByFieldId[$fid][$code] = (string) $row['field_label'];
                }
            }

            // --- 4. Build definitions array with resolved labels ---
            $customFields = [];
            foreach ($rawFields as $field) {
                $fid           = (int) $field['id'];
                $originalLabel = (string) $field['field_label'];
                $langMap       = $translationsByFieldId[$fid] ?? [];

                // Priority: requested language → default language → base label
                if (!empty($langMap[$requestedLang])) {
                    $translatedLabel = $langMap[$requestedLang];
                } elseif (!empty($langMap[$defaultLang])) {
                    $translatedLabel = $langMap[$defaultLang];
                } else {
                    $translatedLabel = $originalLabel;
                }

                $fileConfig = null;
                if (!empty($field['file_config'])) {
                    $decoded = json_decode($field['file_config'], true);
                    if (is_array($decoded)) {
                        $fileConfig = $decoded;
                    }
                }

                $customFields[] = [
                    'id'               => $fid,
                    'original_label'   => $originalLabel,
                    'translated_label' => $translatedLabel,
                    'field_type'       => (string) $field['field_type'],
                    'field_group'      => (string) $field['field_group'],
                    'file_config'      => $fileConfig,
                    'required'         => (int) $field['required'],
                    'visible'          => (int) $field['visible'],
                    'sort_order'       => (int) $field['sort_order'],
                ];
            }

            // --- 5. Fetch stored values for the given address (if provided) ---
            $customerAddressCustomFields = [];
            $addressId = (int) ($this->request->getPost('address_id') ?? 0);

            if ($addressId > 0 && $customFieldModel->customerAddressCustomFieldsTableExists()) {
                // Verify the address belongs to the authenticated customer.
                $addressBelongsToUser = $db->table('addresses')
                    ->where('id', $addressId)
                    ->where('user_id', (int) ($this->user_details['id'] ?? 0))
                    ->countAllResults() > 0;

                if ($addressBelongsToUser) {
                    $valueRows = $db->table('customer_address_custom_fields')
                        ->select(['custom_field_id', 'value'])
                        ->where('address_id', $addressId)
                        ->get()
                        ->getResultArray();

                    foreach ($valueRows as $row) {
                        $customerAddressCustomFields[] = [
                            'custom_field_id' => (int) $row['custom_field_id'],
                            'value'           => (string) $row['value'],
                        ];
                    }
                }
            }

            // --- 6. Return response ---
            return $this->response->setJSON([
                'error'   => false,
                'message' => labels('data_fetched_successfully', 'Data fetched successfully'),
                'data'    => [
                    'custom_fields'                  => $customFields,
                    'customer_address_custom_fields' => $customerAddressCustomFields,
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Customer\CustomFieldsApiController::get_address_custom_fields() - ' . $e->getMessage());
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
            ]);
        }
    }
}
