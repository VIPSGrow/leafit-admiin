<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\CustomFieldModel;

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
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
        }
    }

    /**
     * GET /partner/api/v1/get_custom_fields
     *
     * Returns all visible custom field definitions along with the
     * authenticated partner's stored values.
     *
     * Response data:
     *   custom_fields        – full definition for every custom field, each entry
     *                          includes `original_label` (base table / default lang)
     *                          and `translated_label` (resolved for Content-Language).
     *   provider_custom_fields – partner's saved values keyed by custom_field_id.
     */
    public function get_custom_fields()
    {
        try {
            $db = \Config\Database::connect();
            $customFieldModel = new CustomFieldModel();

            // Guard: tables may not exist on fresh installs.
            if (!$customFieldModel->customFieldsTableExists()) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels('custom_fields_fetched_successfully', 'Custom fields fetched successfully'),
                    'data'    => ['custom_fields' => [], 'provider_custom_fields' => []],
                ]);
            }

            // --- 1. Fetch all custom fields (ordered by sort_order) ---
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
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            // --- 2. Resolve language for translations ---
            $requestedLang = get_current_language_from_request();
            $defaultLang   = get_default_language();

            // --- 3. Load translations for all fetched field IDs in one query ---
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

            // --- 4. Build custom_fields array with label resolution ---
            $customFields = [];
            foreach ($rawFields as $field) {
                $fid           = (int) $field['id'];
                $originalLabel = (string) $field['field_label'];
                $langMap       = $translationsByFieldId[$fid] ?? [];

                // Priority: requested language → default language translation → base label
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

            // --- 5. Fetch provider's saved values ---
            $providerCustomFields = [];

            $partnerId = (int) ($this->user_details['id'] ?? 0);

            if ($partnerId > 0 && $customFieldModel->partnerCustomFieldsTableExists()) {
                $partnerRows = $db->table('partner_custom_fields pcf')
                    ->select(['pcf.custom_field_id', 'pcf.value', 'cf.field_type'])
                    ->join('custom_fields cf', 'cf.id = pcf.custom_field_id')
                    ->where('pcf.partner_id', $partnerId)
                    ->get()
                    ->getResultArray();

                $fileService = service('fileService');

                foreach ($partnerRows as $row) {
                    $value = (string) $row['value'];

                    if ($row['field_type'] === 'file') {
                        $value = (!empty($value) && $fileService->exists('custom_fields', $value))
                            ? $fileService->url($value, 'custom_fields')
                            : '';
                    }

                    $providerCustomFields[] = [
                        'custom_field_id' => (int) $row['custom_field_id'],
                        'value'           => $value,
                    ];
                }
            }

            // --- 6. Return response ---
            return $this->response->setJSON([
                'error'   => false,
                'message' => labels('data_fetched_successfully', 'Data fetched successfully'),
                'data'    => [
                    'custom_fields'          => $customFields,
                    'provider_custom_fields' => $providerCustomFields,
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', 'CustomFieldsApiController::get_custom_fields() - ' . $e->getMessage());
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
            ]);
        }
    }
}
