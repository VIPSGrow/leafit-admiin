<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Payment_request_model;
use App\Libraries\JWT;

class WithdrawRequestsApiController extends BaseController
{
    protected $request, $trans, $db, $data, $validation;
    protected JWT $JWT;
    protected $toDateTime;
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

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_withdrawal_request()
    {
        try {
            $model = new Payment_request_model();

            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'p.id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $status_filter = (string)$this->request->getPost('status_filter');
            $where = [];
            if ($this->user_details['id'] !== '') {
                $where['user_id'] = $this->user_details['id'];
            }
            //0 was added in comparison because !empty was working with 0 in value
            if (!empty($status_filter) || $status_filter == 0) {
                $where['status'] = $status_filter;
            }
            $data = $model->list(true, $search, $limit, $offset, $sort, $order, $where);
            $balance = fetch_details("users", ["id" => $this->user_details['id']], ['balance', 'payable_commision']);
            if (!empty($data['data'])) {
                return response_helper(labels(PAYMENT_REQUEST_FETCHED_SUCCESSFULLY, 'Payment Request fetched successfully'), false, $data['data'], 200, ['total' => $data['total'], 'balance' => $balance[0]['balance']]);
            } else {
                return response_helper(labels(PAYMENT_REQUEST_NOT_FOUND, 'Payment Request not found'), false, [], 200, ['total' => $data['total'], 'balance' => $balance[0]['balance']]);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_withdrawal_request()');
            return $this->response->setJSON($response);
        }
    }

    public function send_withdrawal_request()
    {
        try {
            $this->validation =  \Config\Services::validation();
            $this->validation->setRules(
                [
                    'amount' => [
                        'rules' => 'required|numeric|greater_than[0]',
                        'errors' => [
                            'required' => labels(PLEASE_ENTER_AMOUNT, 'Please enter amount'),
                            'numeric' => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_AMOUNT, 'Please enter numeric value for amount'),
                            'greater_than' => labels(AMOUNT_MUST_BE_GREATER_THAN_0, 'Amount must be greater than 0')
                        ]
                    ],
                    'user_type' => [
                        'rules' => 'required',
                        'errors' => [
                            'required' => labels('user_type_is_required', 'User type is required')
                        ]
                    ]
                ]
            );
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            } else {
                $model = new Payment_request_model();
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    $request_id = $_POST['id'];
                } else {
                    $request_id = '';
                }
                $user_id = ($this->request->getVar('user_id') != '') ? $this->request->getVar('user_id') : $this->user_details['id'];
                $amount = $this->request->getVar('amount');

                // Fetch bank details from custom fields system and validate.
                $bankFields = $this->getBankDetailsCustomFields((int) $user_id);

                if (empty($bankFields)) {
                    $response = [
                        'error' => true,
                        'message' => labels('please_update_bank_details_in_your_profile', 'No bank details found. Please update your bank details in your profile before sending a withdrawal request.'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }

                // Check that all required custom fields have values.
                $missingRequired = false;
                foreach ($bankFields as $field) {
                    if ((int) ($field['required'] ?? 0) === 1 && trim((string) ($field['value'] ?? '')) === '') {
                        $missingRequired = true;
                        break;
                    }
                }
                if ($missingRequired) {
                    $response = [
                        'error' => true,
                        'message' => labels('please_update_bank_details_in_your_profile', 'No bank details found. Please update your bank details in your profile before sending a withdrawal request.'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }

                // Store bank details snapshot from custom fields as JSON.
                $bankSnapshot = [];
                foreach ($bankFields as $field) {
                    $bankSnapshot[] = [
                        'custom_field_id' => $field['custom_field_id'],
                        'label'           => $field['label'],
                        'value'           => $field['value'] ?? '',
                    ];
                }
                $payment_request = array(
                    'id' => $request_id,
                    'user_id' => $user_id,
                    'user_type' => $this->request->getVar('user_type'),
                    'payment_address' => json_encode($bankSnapshot),
                    'amount' => $amount,
                    'remarks' => $this->request->getVar('remarks'),
                    'status' => 0,
                );
                $current_balance =  fetch_details('users', ['id' => $user_id], ['balance', 'username']);
                if ($current_balance[0]['balance'] >= $amount) {
                    $model->save($payment_request);
                    update_balance($this->request->getVar('amount'), $user_id, 'deduct');
                    $balance = fetch_details("users", ["id" => $this->user_details['id']], ['balance']);
                    $response = [
                        'error' => false,
                        'message' => labels(PAYMENT_REQUEST_SENT, 'payment request sent!'),
                        'balance' => $balance[0]['balance'],
                        'data' => []
                    ];

                    $currency = get_settings('general_settings', true)['currency'];

                    // Queue notification to admin users about the new withdrawal request
                    // The FCM template with key 'withdraw_request_received' is already configured

                    // Prepare context data for the notification template
                    // This includes provider_id and amount which can be used in template variables
                    $context = [
                        'provider_name' => $current_balance[0]['username'],
                        'provider_id' => $this->userId,
                        'amount' => $this->request->getVar('amount'),
                        'currency' => $currency
                    ];

                    // Queue notification to admin users (group_id = 1) via all channels
                    // The service will check preferences and configurations to determine which channels to actually send
                    // The service will use the 'withdraw_request_received' template
                    try {

                        queue_notification_service(
                            eventType: 'withdraw_request_received',
                            recipients: [],
                            context: $context,
                            options: [
                                'user_groups' => [1], // Admin user group
                                'channels' => ['fcm', 'email', 'sms'], // All channels - service will check preferences
                                'platforms' => ['admin_panel'] // Admin panel platform for FCM notifications
                            ]
                        );
                    } catch (\Throwable $notificationError) {
                        log_message('error', '[WITHDRAWAL_REQUEST] Notification error trace: ' . $notificationError->getTraceAsString());
                    }

                    return $this->response->setJSON($response);
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(INSUFFICIENT_BALANCE, 'Insufficient Balance!'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }
            }
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - send_withdrawal_request()');
            return $this->response->setJSON($response);
        }
    }

    public function delete_withdrawal_request()
    {
        try {
            $validation =  \Config\Services::validation();
            $validation->setRules(
                [
                    'id' => 'required|numeric',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $id = $this->request->getPost('id');
            $is_exist = fetch_details('payment_request', ['id' => $id, 'user_id' => $this->user_details['id']]);
            if (!empty($is_exist)) {
                $db      = \Config\Database::connect();
                $builder = $db->table('payment_request')->delete(['id' => $id]);
                $response = [
                    'error' => false,
                    'message' => labels(PAYMENT_REQUEST_DELETED_SUCCESSFULLY, 'Payment request deleted successfully!'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(PAYMENT_REQUEST_DOES_NOT_EXIST, 'Payment request does not exist!'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - delete_withdrawal_request()');
            return $this->response->setJSON($response);
        }
    }

    /**
     * Fetch visible bank_details custom field definitions and partner values.
     */
    private function getBankDetailsCustomFields(int $partnerId): array
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        // Resolve language from Content-Language header with default fallback.
        $requestedLang = $this->request->getHeaderLine('Content-Language');
        $requestedLang = !empty($requestedLang) ? trim($requestedLang) : '';
        $defaultLang = get_default_language();
        $currentLang = $requestedLang !== '' ? $requestedLang : $defaultLang;

        $fields = $db->table('custom_fields')
            ->select(['id', 'field_label', 'required'])
            ->where('field_group', 'bank_details')
            ->where('visible', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($fields)) {
            return [];
        }

        $fieldIds = array_column($fields, 'id');

        // Fetch translations.
        $translations = [];
        if ($db->tableExists('translated_custom_fields')) {
            $translationRows = $db->table('translated_custom_fields tcf')
                ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                ->join('languages l', 'l.id = tcf.language_id')
                ->whereIn('tcf.custom_field_id', $fieldIds)
                ->get()
                ->getResultArray();

            foreach ($translationRows as $tr) {
                $translations[(int) $tr['custom_field_id']][$tr['language_code']] = $tr['field_label'];
            }
        }

        // Fetch partner values.
        $values = [];
        if ($db->tableExists('partner_custom_fields')) {
            $valueRows = $db->table('partner_custom_fields')
                ->select(['custom_field_id', 'value'])
                ->where('partner_id', $partnerId)
                ->whereIn('custom_field_id', $fieldIds)
                ->get()
                ->getResultArray();

            foreach ($valueRows as $vr) {
                $values[(int) $vr['custom_field_id']] = $vr['value'];
            }
        }

        $result = [];
        foreach ($fields as $field) {
            $fieldId = (int) $field['id'];
            $label = $translations[$fieldId][$currentLang]
                ?? $translations[$fieldId][$defaultLang]
                ?? $field['field_label'];

            $result[] = [
                'custom_field_id' => (int) $field['id'],
                'label'           => $label,
                'value'           => $values[$fieldId] ?? '',
                'required'        => (int) $field['required'],
            ];
        }

        return $result;
    }
}
