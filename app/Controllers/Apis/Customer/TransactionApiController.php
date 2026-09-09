<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Transaction_model;
use App\Libraries\JWT;
use App\Libraries\Paystack;
use App\Libraries\Xendit;
use DateTime;

class TransactionApiController extends BaseController
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

    public function add_transaction()
    {
        // log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => ", date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - add_transaction()');
        try {
            $validation = service('validation');
            $validation->setRules([
                'order_id' => 'required|numeric',
                'status' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $transaction_model = new Transaction_model();
            $order_id = (int) $this->request->getVar('order_id');
            $status = $this->request->getVar('status');
            $data['status'] = $status;
            $user = fetch_details('users', ['id' => $this->user_details['id']]);

            if (empty($user)) {
                $response = [
                    'error' => true,
                    'message' => labels(USER_NOT_FOUND, "User not found!"),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $order = fetch_details('orders', ['id' => $this->request->getVar('order_id')]);
            if ($this->request->getVar('is_additional_charge') == "1") {
                $transaction_id = $this->request->getVar('transaction_id');
                $payment_method = $this->request->getVar('payment_method');
                // Amount = caller's intent (this payment), falling back to the order's
                // total_additional_charge when caller did not pass one. This row is the
                // source of truth the webhook validates against.
                $requested_amount = $this->request->getVar('amount');
                $additional_amount = (is_numeric($requested_amount) && (float) $requested_amount > 0)
                    ? (float) $requested_amount
                    : (float) ($order[0]['total_additional_charge'] ?? 0);

                // Fetch the latest additional-charge row regardless of status. We need
                // visibility into success/failed rows so the webhook-finalised row is
                // reused instead of inserting a duplicate when the app re-calls
                // add_transaction after the gateway returns.
                $latest_additional = fetch_details(
                    'transactions',
                    [
                        'order_id' => $order_id,
                        'user_id' => $this->user_details['id'],
                        'transaction_type' => 'transaction',
                        'message' => 'txn_additional_charges',
                    ],
                    [],
                    1,
                    0,
                    'id',
                    'DESC'
                );

                $online_gateways = ['razorpay', 'stripe', 'paypal', 'cashfree', 'paystack', 'flutterwave', 'xendit'];
                $is_online_gateway = in_array($payment_method, $online_gateways, true);

                // If the webhook already finalised the previous attempt as success for
                // an online gateway, do not insert a new row and do not re-initiate
                // the gateway. Return the finalised row as-is.
                if (
                    !empty($latest_additional)
                    && $is_online_gateway
                    && ($latest_additional[0]['status'] ?? '') === 'success'
                ) {
                    $response['error'] = false;
                    $response['message'] = labels(STATUS_UPDATED, 'Status Updated');
                    $response['data'] = $latest_additional[0];
                    return $this->response->setJSON($response);
                }

                $existing_pending = (!empty($latest_additional) && ($latest_additional[0]['status'] ?? '') === 'pending')
                    ? $latest_additional
                    : [];

                // A previously failed online-gateway attempt should be reused (retry on
                // the same row) instead of inserting a fresh pending row.
                $existing_failed_online = (
                    !empty($latest_additional)
                    && $is_online_gateway
                    && ($latest_additional[0]['status'] ?? '') === 'failed'
                ) ? $latest_additional : [];

                if (!empty($existing_pending)) {
                    $t_id = $existing_pending[0]['id'];
                    update_details(
                        [
                            'type' => $payment_method,
                            'amount' => $additional_amount,
                            'txn_id' => "",
                            'reference' => null,
                            'currency_code' => "",
                            'status' => 'pending',
                        ],
                        ['id' => $t_id],
                        'transactions'
                    );
                    $transaction_check_for_additional_charge = $existing_pending;
                } elseif (!empty($existing_failed_online)) {
                    $t_id = $existing_failed_online[0]['id'];
                    update_details(
                        [
                            'type' => $payment_method,
                            'amount' => $additional_amount,
                            'txn_id' => "",
                            'reference' => null,
                            'currency_code' => "",
                            'status' => 'pending',
                        ],
                        ['id' => $t_id],
                        'transactions'
                    );
                    $transaction_check_for_additional_charge = $existing_failed_online;
                } elseif ($transaction_id) {
                    $transaction_check_for_additional_charge = fetch_details('transactions', ['order_id' => $order_id, 'id' => $transaction_id]);
                    if (!empty($transaction_check_for_additional_charge)) {
                        $current_status = $transaction_check_for_additional_charge[0]['status'] ?? '';
                        $t_id = $transaction_check_for_additional_charge[0]['id'];
                        if (!($is_online_gateway && in_array($current_status, ['success', 'failed'], true))) {
                            update_details(['status' => $status, 'amount' => $additional_amount], ['id' => $t_id], 'transactions');
                        }
                    } else {
                        $t_id = null;
                    }
                } else {
                    // Insert pending row up-front for both online and offline gateways.
                    // Online: razorpay_create_order will stamp `reference` on this row, then
                    // the gateway webhook updates status. Offline: provider/admin marks paid.
                    $data = [
                        'transaction_type' => 'transaction',
                        'user_id' => $this->user_details['id'],
                        'partner_id' => "",
                        'order_id' => $order_id,
                        'type' => $payment_method,
                        'txn_id' => "",
                        'amount' => $additional_amount,
                        'status' => 'pending',
                        'currency_code' => "",
                        'message' => 'txn_additional_charges',
                    ];

                    $t_id = add_transaction($data);
                    $transaction_check_for_additional_charge = !empty($t_id)
                        ? fetch_details('transactions', ['id' => $t_id])
                        : [];
                }

                $fetch_transaction = !empty($t_id) ? fetch_details('transactions', ['id' => $t_id]) : [];
                if ($this->request->getVar('is_additional_charge') == 1) {
                    $payment_method = $this->request->getVar('payment_method');
                    if (in_array($payment_method, ['paystack', 'paypal', 'flutterwave', 'xendit'], true)) {
                        update_details(
                            ['payment_method_of_additional_charge' => $payment_method],
                            ['id' => $order_id],
                            'orders'
                        );
                    }
                    if ($payment_method == "paystack") {
                        $platform = $this->request->getVar('order_from');
                        $response['paystack_link'] = ($platform == 'app') ? $this->paystack_transaction_webview($this->user_details['id'], $order_id, $order[0]['total_additional_charge'], $order[0]['partner_id'], 'additional_charges', $platform, $t_id) : base_url() . '/api/v1/paystack_transaction_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $order_id . '&additional_charges_transaction_id=' . $t_id . '&amount=' . (number_format(strval($order[0]['total_additional_charge']), 2)) . '';
                    } else if ($payment_method == "paypal") {
                        // Create the PayPal Orders v2 checkout inline and hand the
                        // buyer-facing approval URL straight to the client.
                        $email = (string) ($this->user_details['email'] ?? '');
                        $checkout = (new \App\Services\PaypalPaymentService())->createCheckout([
                            'transaction_id' => $t_id,
                            'custom_id' => $this->user_details['id'] . '|' . $email . '|' . $t_id,
                            'item_name' => 'Additional charge - Order #' . $order_id,
                            'invoice_prefix' => 'addl',
                            'return_url' => base_url('api/v1/paypal_return'),
                            'cancel_url' => base_url('api/v1/paypal_return?cancelled=1'),
                        ]);
                        $response['paypal_link'] = !empty($checkout['error']) ? '' : $checkout['approval_url'];
                    } else if ($payment_method == "flutterwave") {
                        $response['flutterwave_link'] = ($payment_method == "flutterwave") ? base_url() . 'api/v1/flutterwave_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $order_id . '&additional_charges_transaction_id=' . $t_id . '&amount=' . number_format(strval($order[0]['total_additional_charge']), 2) . '' : "";
                    } else if ($payment_method == "xendit") {
                        $response['xendit_link'] = ($payment_method == "xendit") ? $this->xendit_transaction_webview($this->user_details['id'], $order_id, $order[0]['total_additional_charge'], $order[0]['partner_id'], 'additional_charges', $t_id) : "";
                    }
                }

                $response['data'] = $fetch_transaction[0] ?? [];
            }
            // If a payment gateway webview link was generated, return early.
            // The actual payment status will be updated by the gateway's webhook callback.
            if (isset($response['paystack_link']) || isset($response['paypal_link']) || isset($response['flutterwave_link']) || isset($response['xendit_link'])) {
                $response['error'] = false;
                $response['message'] = labels(STATUS_UPDATED, 'Status Updated');
                return $this->response->setJSON($response);
            }

            $transaction = fetch_details('transactions', ['order_id' => $this->request->getVar('order_id')]);
            if (!empty($order)) {
                $data['status'] = $status;
                $is_additional_charge = $this->request->getVar('is_additional_charge') == "1";
                $transaction = fetch_details('transactions', [
                    'order_id' => $order[0]['id'],
                    'id' => $transaction_check_for_additional_charge[0]['id'] ?? null,
                    'user_id' => $this->user_details['id']
                ]);
                // log_message('debug', 'Transaction --> ' . var_export($transaction, true));
                if ($is_additional_charge) {
                    if ($this->request->getVar('transaction_id')) {
                        $transaction = fetch_details('transactions', [
                            'order_id' => $order[0]['id'],
                            'id' => $this->request->getVar('transaction_id') ?? null,
                            'user_id' => $this->user_details['id']
                        ]);
                    } else {
                        // Update the transaction that was just created for additional charges
                        $transaction = fetch_details('transactions', [
                            'order_id' => $order[0]['id'],
                            'id' => $t_id,
                            'user_id' => $this->user_details['id']
                        ]);
                        // Update status only if not already finalized by the gateway webhook.
                        if (!empty($transaction)) {
                            $current_status = $transaction[0]['status'] ?? '';
                            if (!($is_online_gateway && in_array($current_status, ['success', 'failed'], true))) {
                                update_details(['status' => $status], ['id' => $t_id], 'transactions');
                            }
                        }
                    }
                    if ($this->request->getVar('payment_method') == "cod") {
                        update_details(['payment_status_of_additional_charge' => '0', 'payment_method_of_additional_charge' => 'cod'], ['id' => $order_id], 'orders');
                    } else {
                        update_details(['payment_method_of_additional_charge' => $this->request->getVar('payment_method')], ['id' => $order_id], 'orders');
                    }
                    $response['error'] = false;
                    $response['message'] = labels(STATUS_UPDATED, 'Status Updated');
                } else {
                    $update = update_details(['status' => "awaiting"], [
                        'id' => $order_id,
                        'status' => 'awaiting',
                        'user_id' => $this->user_details['id'],
                    ], 'orders');
                    if ($status == "success") {
                        if ($this->request->getPost('is_reorder') === '1') {
                            handleSuccessfulTransaction($transaction, $order, $order_id, $this->user_details['id'], $is_redorder = true);
                        } else {
                            handleSuccessfulTransaction($transaction, $order, $order_id, $this->user_details['id']);
                        }
                    } else {
                        handleFailedTransaction($transaction, $order, $order_id, $this->user_details['id']);
                    }
                    $response['error'] = false;
                    $response['message'] = labels(STATUS_UPDATED, 'Status Updated');
                }
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - add_transaction()');
        }
        return $this->response->setJSON($response);
    }


    public function get_transactions()
    {
        try {
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'DESC';
            $user_id = $this->user_details['id'];
            $status = $this->request->getPost('status');
            if (!exists(['id' => $user_id], 'users')) {
                $response = [
                    'error' => true,
                    'message' => labels(INVALID_USER_ID, 'Invalid User Id.'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $where['user_id'] = $user_id;
            if ($status) {
                $where['status'] = $status;
            }
            $res = fetch_details('transactions', $where, ['id', 'user_id', 'order_id', 'type', 'txn_id', 'reference', 'amount', 'status', 'message', 'transaction_date', 'status'], $limit, $offset, $sort, $order);
            $res_total = fetch_details('transactions', $where, ['id', 'user_id', 'order_id', 'type', 'txn_id', 'amount', 'status', 'message', 'transaction_date', 'status']);

            // Always return transaction_date in GTU/UTC format (e.g. 2026-03-16T12:34:56Z)
            // so apps can safely convert it to local time if needed.
            $utcTz = new \DateTimeZone('UTC');

            // $cash_collection = fetch_details('cash_collection', ['user_id' => $user_id]);
            foreach ($res as &$row) {
                $row['translated_status'] = labels($row['status']);
                $row['translated_message'] = labels($row['message'], $row['message']);

                // App/web display the txn_id as the transaction id. Online gateways only
                // stamp txn_id (gateway payment id) once a payment is captured/failed via
                // webhook — a placed-but-cancelled order has none. Fall back to `reference`
                // (the gateway order id set at intent) so the id column is never blank.
                if (empty($row['txn_id'])) {
                    $row['txn_id'] = $row['reference'] ?? '';
                }
                unset($row['reference']);

                // Convert transaction_date to GTU/UTC string with trailing Z (e.g. 2026-03-16T12:34:56Z).
                if (!empty($row['transaction_date'])) {
                    try {
                        $utcDate = new DateTime($row['transaction_date'], $utcTz);
                        $row['transaction_date'] = $utcDate->format('Y-m-d\TH:i:s\Z');
                    } catch (\Exception $e) {
                        // If parsing fails, keep original value to avoid breaking the response.
                    }
                }
            }

            $total = count($res_total);

            if (!empty($res)) {
                $response = [
                    'error' => false,
                    'message' => labels(TRANSACTIONS_RECIEVED_SUCCESSFULLY, 'Transactions recieved successfully.'),
                    'total' => $total,
                    'data' => $res,
                ];
                // return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'data' => [],
                ];
                // return $this->response->setJSON($response);
            }
            // foreach ($cash_collection as &$cc) {
            //     $cc = [
            //         'id' => $cc['id'],
            //         'user_id' => $cc['user_id'],
            //         'order_id' => $cc['order_id'] ?? null,
            //         'type' => 'cash_collection',
            //         'txn_id' => '', // you can fill with unique ref if needed
            //         'amount' => $cc['commison'],
            //         // fix the typo in status field to match “received”
            //         'status' => str_replace('recevied', 'received', $cc['status']),
            //         'message' => $cc['message'],
            //         'transaction_date' => $cc['date'],
            //         'translated_status' => labels($cc['status']),
            //         'translated_message' => labels($cc['status'], $cc['message']),
            //     ];
            // }
            // $merged_data = array_merge($res, $cash_collection);

            // usort($merged_data, function($a, $b) use ($order) {
            //     $a_date = strtotime($a['transaction_date']);
            //     $b_date = strtotime($b['transaction_date']);
            //     return $order === 'DESC' ? $b_date <=> $a_date : $a_date <=> $b_date;
            // });     

            // if (!empty($merged_data)) {
            //     $response = [
            //         'error' => false,
            //         'message' => labels(TRANSACTIONS_RECIEVED_SUCCESSFULLY, 'Transactions received successfully.'),
            //         'total' => count($merged_data),
            //         'data' => $merged_data,
            //     ];
            // } else {
            //     $response = [
            //         'error' => true,
            //         'message' => labels(NO_DATA_FOUND, 'No data found'),
            //         'data' => [],
            //     ];
            // }
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_transactions()');
            return $this->response->setJSON($response);
        }
    }

    //Private helper methods
    private function paystack_transaction_webview($user_id = null, $order_id = null, $amount = null, $partner_id = null, $type = null, $platform = null, $additional_charges_transaction_id = null)
    {
        try {
            if ($user_id == null && isset($_GET['user_id'])) {
                $user_id = $_GET['user_id'];
                $order_id = $_GET['order_id'];
                $amount = intval(str_replace(',', '', $_GET['amount']));
                $platform = 'web';
            }

            $user_data = fetch_details('users', ['id' => $user_id])[0];
            $paystack = new Paystack();
            $paystack_credentials = $paystack->get_credentials();
            $secret_key = $paystack_credentials['secret'];
            $url = "https://api.paystack.co/transaction/initialize";
            $encryption = order_encrypt($user_id, $amount, $order_id);

            if ($platform == 'web') {
                $callback_url = base_url('api/v1/app_paystack_payment_status?payment_status=Completed&platform=web');
                $cancel_url = base_url('api/v1/app_paystack_payment_status?order_id=' . $encryption . '&payment_status=Failed&platform=web');
            } else {
                $callback_url = base_url('api/v1/app_paystack_payment_status?payment_status=Completed&platform=' . $platform . '&order_id=' . $order_id);
                $cancel_url = base_url('api/v1/app_paystack_payment_status?order_id=' . $encryption . '&payment_status=Failed&platform=' . $platform);
            }

            $fields = [
                'email' => $user_data['email'],
                'amount' => $amount * 100,
                'currency' => $paystack_credentials['currency'],
                'callback_url' => $callback_url,
                'metadata' => [
                    'cancel_action' => $cancel_url,
                    'order_id' => $order_id,
                ]
            ];
            if ($type == 'additional_charges') {
                $fields['metadata']['additional_charges_transaction_id'] = $additional_charges_transaction_id;
            }
            $fields_string = http_build_query($fields);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Authorization: Bearer " . $secret_key,
                "Cache-Control: no-cache",
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            unset($ch);
            $result_data = json_decode($result, true);

            if (isset($result_data['data']['authorization_url'])) {
                // Stamp the Paystack reference on the pending additional-charge row so
                // the webhook can match by (type=paystack, reference) and finalise.
                $ps_reference = (string) ($result_data['data']['reference'] ?? '');
                if ($ps_reference !== '' && $type == 'additional_charges' && !empty($additional_charges_transaction_id)) {
                    update_details(
                        ['reference' => $ps_reference, 'type' => 'paystack'],
                        ['id' => $additional_charges_transaction_id],
                        'transactions'
                    );
                }
                return $result_data['data']['authorization_url'];
            } else {
                log_message('error', 'Error Creating Authorization URL: ' . json_encode($result_data, true));
                return "";
            }
        } catch (\Exception $th) {
            log_the_responce('Paystack Transaction Webview Error: ' . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/Apis/Customer/TransactionApiController.php - paystack_transaction_webview()');
            return "";
        }
    }

    private function xendit_transaction_webview($user_id, $order_id, $amount, $partner_id, $type, $additional_charges_transaction_id = null)
    {
        try {

            $user = fetch_details('users', ['id' => $user_id]);
            if (empty($user)) {
                echo labels(USER_NOT_FOUND, 'User not found');
                return false;
            }

            $order_res = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_res)) {
                echo labels(ORDER_NOT_FOUND, 'Order not found');
                return false;
            }

            $settings = get_settings('general_settings', true);
            $payment_gateways_settings = get_settings('payment_gateways_settings', true);

            if ($type == 'additional_charges') {
                $external_id = 'additionalCharges_' . $additional_charges_transaction_id . '_' . $user_id . '_' . time();
            } else {
                $external_id = 'order_' . $order_id . '_' . $user_id . '_' . time();
            }

            // Prepare success and failure URLs
            if (isset($payment_gateways_settings['xendit_website_url']) && !empty($payment_gateways_settings['xendit_website_url'])) {
                $success_url = $payment_gateways_settings['xendit_website_url'] . '/payment-status?status=successful&order_id=' . $order_id;
                $failure_url = $payment_gateways_settings['xendit_website_url'] . '/payment-status?status=failed&order_id=' . $order_id;
            } else {
                $success_url = base_url('api/v1/xendit_payment_status?status=successful&order_id=' . $order_id);
                $failure_url = base_url('api/v1/xendit_payment_status?status=failed&order_id=' . $order_id);
            }

            $company_title = getTranslatedSetting('general_settings', 'company_title');
            // Prepare invoice data for Xendit using SDK
            $invoice_data = [
                'external_id' => $external_id,
                'amount' => floatval($amount),
                'customer_name' => $user[0]['username'],
                'customer_email' => !empty($user[0]['email']) ? $user[0]['email'] : $settings['support_email'],
                'customer_phone' => $user[0]['phone'] ?? '',
                'success_url' => $success_url,
                'failure_url' => $failure_url,
                'description' => 'Payment for Order #' . $order_id . ' on ' . $company_title,
                'metadata' => [
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                ]
            ];

            // Create Xendit invoice using SDK
            $xendit = new Xendit();
            $invoice = $xendit->create_invoice($invoice_data);

            if ($invoice && isset($invoice['invoice_url'])) {

                if ($type == 'order') {
                    $transaction_data = [
                        'transaction_type' => 'transaction',
                        'user_id' => $user_id,
                        'partner_id' => $partner_id,
                        'order_id' => $order_id,
                        'type' => 'xendit',
                        'txn_id' => $external_id,
                        'status' => 'pending',
                        'amount' => 0,
                        'currency_code' => "",
                    ];

                    add_transaction($transaction_data);
                }


                // Log successful invoice creation
                log_the_responce('Xendit invoice created successfully for order: ' . $order_id . ' with external_id: ' . $external_id, 'app/Controllers/api/V1.php - xendit_transaction_webview()');

                // Return Xendit payment link
                return $invoice['invoice_url'];
            } else {
                log_the_responce('Failed to create Xendit invoice for order: ' . $order_id, 'app/Controllers/api/V1.php - xendit_transaction_webview()');
                return false;
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - xendit_transaction_webview()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
        }
    }
}
