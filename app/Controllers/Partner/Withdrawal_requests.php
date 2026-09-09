<?php

namespace App\Controllers\Partner;

use App\Models\Payment_request_model;
use App\Services\NotificationService;

class Withdrawal_requests  extends Partner
{
    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        helper('ResponceServices');
    }
    public function index()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('partner/login');
        } else {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            setPageInfo($this->data, labels('payment_request', 'Payment Request') . ' | ' . labels('provider_panel', 'Provider Panel'), 'withdrawal_requests');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/partner/template', $this->data);
        }
    }
    public function send()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('partner/login');
        } else {
            setPageInfo($this->data, labels('withdrawal_request', 'Withdrawal Request') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'send_withdrawal_request');

            // Use logged-in partner id
            $user_id = $this->ionAuth->getUserId();

            // Get current balance
            $balance = fetch_details('users', ['id' => $user_id], 'balance');
            $this->data['balance'] = $balance[0]['balance'] ?? 0;

            // Get currency symbol
            $settings = get_settings('general_settings', true);
            $this->data['currency'] = $settings['currency'];

            // Fetch bank details from custom fields system
            $bankFields = $this->getBankDetailsCustomFields($user_id);

            $formattedBankDetails = '';

            if (!empty($bankFields)) {
                $lines = [];
                foreach ($bankFields as $field) {
                    $value = trim((string) ($field['value'] ?? ''));
                    if ($value !== '') {
                        $lines[] = $field['label'] . ': ' . $value;
                    }
                }
                $formattedBankDetails = implode("\n", $lines);
            }

            if ($formattedBankDetails === '') {
                $formattedBankDetails = labels(
                    'please_update_bank_details_in_your_profile',
                    'No bank details found. Please update your bank details in your profile before sending a withdrawal request.'
                );
            }

            $this->data['bankDetailsFormatted'] = $formattedBankDetails;
            $this->data['partnerId'] = $user_id;

            return view('backend/partner/template', $this->data);
        }
    }
    public function save()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('partner/login');
            } else {
                // Fetch bank details from custom fields system.
                // We validate that all required bank fields are filled before allowing a withdrawal request.
                $bankFields = $this->getBankDetailsCustomFields($this->userId);

                if (empty($bankFields)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => [
                            'bank_details' => labels(
                                'please_update_bank_details_in_your_profile',
                                'No bank details found. Please update your bank details in your profile before sending a withdrawal request.'
                            ),
                        ],
                        'data' => [],
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                    ]);
                }

                // Check that all required custom fields have values.
                $missingRequired = [];
                foreach ($bankFields as $field) {
                    if ((int) ($field['required'] ?? 0) === 1 && trim((string) ($field['value'] ?? '')) === '') {
                        $missingRequired[] = $field['label'];
                    }
                }
                if (!empty($missingRequired)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => [
                            'bank_details' => labels(
                                'please_update_bank_details_in_your_profile',
                                'No bank details found. Please update your bank details in your profile before sending a withdrawal request.'
                            ),
                        ],
                        'data' => [],
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                    ]);
                }

                $balance = intval(fetch_details('users', ['id' => $this->userId], ['balance'])[0]['balance']);
                $this->validation->setRules(
                    [
                        'amount' => [
                            "rules" => 'required|numeric|less_than_equal_to[' . $balance . ']|greater_than[0]',
                            "errors" => [
                                "required" => labels(PLEASE_ENTER_AMOUNT, "Please enter amount"),
                                "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_AMOUNT, "Please enter numeric value for amount"),
                                "greater_than" => labels(AMOUNT_MUST_BE_GREATER_THAN_0, "amount must be greater than 0"),
                                "less_than_equal_to" => labels(AMOUNT_MUST_BE_LESS_THAN_OR_EQUAL_TO_BALANCE, "amount must be less than or equal to balance"),
                            ]
                        ],
                    ],
                );
                if (!$this->validation->withRequest($this->request)->run()) {
                    $errors = $this->validation->getErrors();
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => $errors,
                        'data' => [],
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash()
                    ]);
                } else {
                    $payment_request_model = new Payment_request_model();
                    if ($this->userIsPartner) {
                        $userType = "partner";
                    } else {
                        $userType = "customer";
                    }
                    if (isset($_POST['request_id']) && !empty($_POST['request_id'])) {
                        $rquest_id = $this->request->getVar('request_id');
                    } else {
                        $rquest_id = '';
                    }
                    // Store bank details snapshot from custom fields as JSON so they remain unchanged
                    // if provider later changes or removes bank details in profile.
                    $bankSnapshot = [];
                    foreach ($bankFields as $field) {
                        $bankSnapshot[] = [
                            'custom_field_id' => $field['custom_field_id'],
                            'label'           => $field['label'],
                            'value'           => $field['value'] ?? '',
                        ];
                    }
                    $data = array(
                        'id' => $rquest_id,
                        'user_id' => $this->request->getVar('user_id'),
                        'user_type' => $userType,
                        'payment_address' => json_encode($bankSnapshot),
                        'amount' => $this->request->getVar('amount'),
                        'remarks' => $this->request->getVar('remarks'),
                        'status' => 0,
                        // Store creation time explicitly in UTC. The DB column default uses
                        // the server timezone, but the display layer assumes UTC — so we
                        // pin it to UTC here to keep storage and rendering consistent.
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    );
                    if ($payment_request_model->save($data)) {
                        update_balance($this->request->getVar('amount'), $this->request->getVar('user_id'), 'deduct');

                        $providerName = get_translated_partner_field($this->userId, 'user_name');

                        if (empty($providerName)) {
                            $providerName = fetch_details('users', ['id' => $this->userId], 'username')[0]['username'];
                        }

                        $currency = get_settings('general_settings', true)['currency'];

                        // Queue notification to admin users about the new withdrawal request
                        // The FCM template with key 'withdraw_request_received' is already configured

                        // Prepare context data for the notification template
                        // This includes provider_id and amount which can be used in template variables
                        $context = [
                            'provider_name' => $providerName,
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

                        // Get remaining balance for event tracking
                        $remainingBalance = fetch_details('users', ['id' => $this->userId], ['balance']);
                        $remainingBalance = !empty($remainingBalance) ? $remainingBalance[0]['balance'] : 0;

                        // Prepare event data
                        $eventData = [
                            'clarity_event' => 'withdrawal_request_sent',
                            'amount' => $this->request->getVar('amount'),
                            'payment_address' => $this->request->getVar('payment_address'),
                            'remaining_balance' => $remainingBalance
                        ];

                        return successResponse(labels(REQUEST_SENT, "Request Sent!"), false, $eventData, [], 200, csrf_token(), csrf_hash());
                    }
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Withdrawal_requests.php - save()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list()
    {
        try {
            // Use the logged-in partner ID directly instead of raw $_SESSION access.
            // This keeps the logic consistent with the rest of the partner controllers.
            $model = new Payment_request_model();
            $where = ['p.user_id' => $this->userId];

            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'p.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Model returns a JSON-encoded string (for bootstrap-table).
            // Decode and re-encode with setJSON so the response is always valid JSON
            // and bootstrap-table does not get stuck in loading state on type mismatches.
            $data = $model->list(false, $search, $limit, $offset, $sort, $order, $where);
            // $decoded = json_decode($data, true);

            return $this->response->setJSON($data);
        } catch (\Throwable $th) {
            // throw $th;
            // Log the error for debugging and return a safe empty dataset
            // so the table stops loading instead of spinning forever.
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Withdrawal_requests.php - list()');

            return $this->response->setJSON([
                'total' => 0,
                'rows' => [],
            ]);
        }
    }
    public function delete()
    {
        try {
            if ($this->isLoggedIn) {
                $id = $this->request->getPost('id');
                $db      = \Config\Database::connect();
                $builder = $db->table('payment_request')->delete(['id' => $id]);
                if ($builder) {
                    return successResponse(labels(PAYMENT_REQUEST_DELETED_SUCCESSFULLY, "Payment Request deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(PAYMENT_REQUEST_CANNOT_BE_DELETED, "Payment Request can not be deleted!"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Withdrawal_requests.php - delete()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Fetch visible bank_details custom field definitions and partner values.
     *
     * Returns an array of fields with: custom_field_id, label (translated), value, required.
     */
    private function getBankDetailsCustomFields(int $partnerId): array
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Fetch visible bank_details field definitions.
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

        // Fetch translations for these fields.
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

        // Fetch partner values for these custom fields.
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

            // Resolve translated label: current lang → default lang → base table.
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
