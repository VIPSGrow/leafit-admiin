<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Orders_model;
use App\Models\Transaction_model;
use App\Models\Users_model;
use App\Models\Partners_model;
use App\Models\HandymanDetailsModel;
use App\Models\BookingHandymenModel;
use App\Models\LiveTrackingModel;
use DateTime;

class OrdersApiController extends BaseController
{
    protected $request, $db, $data;
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->db = \Config\Database::connect();

        $token = verify_app_request();

        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error' => true,
                'message' => $token['message'],
                'status' => 401,
            ]));
            die();
        }
    }

    public function get_orders()
    {
        try {
            $orders_model = new Orders_model();
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $status = $this->request->getPost('status') ?: 0;
            $partner_id = $this->request->getPost('partner_id') ?: $this->user_details['id'];
            $download_invoice = ($this->request->getPost('download_invoice') && !empty($this->request->getPost('download_invoice'))) ? $this->request->getPost('download_invoice') : 1;

            // Fetch only Custom Job Request Orders
            if (!empty($this->request->getPost('custom_request_orders'))) {
                $where['o.custom_job_request_id !='] = "";
                $where['o.partner_id'] = $partner_id;
                if (!empty($this->request->getPost('status'))) {
                    $where['o.status'] = $status;
                }
                $orders = $orders_model->custom_booking_list(true, $search, $limit, $offset, $sort, $order, $where, $download_invoice);
            }
            // Fetch Both Custom Job Request Orders & Normal Bookings
            elseif (!empty($this->request->getPost('fetch_both_bookings'))) {
                // Fetch Custom Job Requests
                $custom_where = [
                    'o.custom_job_request_id !=' => '',
                    'o.partner_id' => $partner_id
                ];
                if (!empty($status)) {
                    $custom_where['o.status'] = $status;
                }
                $custom_orders = $orders_model->custom_booking_list(true, $search, $limit, $offset, $sort, $order, $custom_where, $download_invoice);

                // Fetch Normal Bookings
                $normal_where = [
                    'o.partner_id' => $partner_id,
                    'o.status' => $status,
                    'o.custom_job_request_id' => NULL
                ];
                $normal_orders = $orders_model->list(true, $search, $limit, $offset, $sort, $order, $normal_where, '', '', '', '', '', true);

                // Merge Results
                $orders['data'] = array_merge($custom_orders['data'] ?? [], $normal_orders['data'] ?? []);
                $total = ($custom_orders['total'] ?? 0) + ($normal_orders['total'] ?? 0);
            }
            // Fetch Only Normal Bookings
            else {
                $where = [
                    'o.partner_id' => $this->user_details['id'],
                    'o.status' => $status,
                    'o.custom_job_request_id' => NULL
                ];
                if ($this->request->getPost('id') && !empty($this->request->getPost('id'))) {
                    $where['o.id'] = $this->request->getPost('id');
                }

                $orders = $orders_model->list(true, $search, $limit, $offset, $sort, $order, $where, '', '', '', '', '', true);
            }


            // Remove total key if present
            if (isset($orders['total'])) {
                $total = $orders['total'];
                unset($orders['total']);
            }

            // Add translation support for service data in orders
            if (!empty($orders['data'])) {
                foreach ($orders['data'] as &$order) {
                    if (!empty($order['order_services'])) {
                        foreach ($order['order_services'] as &$service) {
                            // Get service details for translation fallback
                            $serviceFallbackData = [
                                'title' => $service['title'] ?? '',
                                'description' => $service['description'] ?? '',
                                'long_description' => $service['long_description'] ?? '',
                                'tags' => $service['tags'] ?? '',
                                'faqs' => $service['faqs'] ?? ''
                            ];

                            // Get translated data for this service based on Content-Language header
                            $translatedServiceData = get_translated_service_data_for_api($service['service_id'], $serviceFallbackData);

                            // Merge translated data with the service data
                            if (!empty($translatedServiceData)) {
                                $service = array_merge($service, $translatedServiceData);
                            }
                        }
                    }
                }
            }

            // Append assigned handymen + tax type to every order in the list
            if (!empty($orders['data'])) {
                $bookingHandymenModel = new BookingHandymenModel();
                foreach ($orders['data'] as &$order) {
                    $handymen = $bookingHandymenModel->getAssignedHandymen((int) $order['id']);
                    usort($handymen, function($a, $b) {
                        return $b['is_lead'] <=> $a['is_lead'];
                    });
                    $order['assigned_handymen'] = array_map(fn($h) => [
                        'id' => $h['id'],
                        'username' => $h['username'],
                        'profile_image' => $h['image'],
                        'is_lead' => $h['is_lead'],
                    ], $handymen);
                    $order['tax_type'] = $orders_model->getOrderTaxType((int) $order['id']);
                }
                unset($order);
            }

            // Single-order fetch: append assignable handymen
            if (!empty($this->request->getPost('id')) && !empty($orders['data'])) {
                $providerPartnerId = (int) $this->user_details['id'];
                $slotSettings = (new \App\Models\ProviderSlotSettings_model())->findByPartner($providerPartnerId) ?? [];
                $ord = &$orders['data'][0];

                $assignable = (new HandymanDetailsModel())->getAssignableHandymen(
                    $providerPartnerId,
                    (int) $ord['id'],
                    $ord['date_of_service'] ?? '',
                    $ord['starting_time'] ?? '',
                    $ord['ending_time'] ?? '',
                    (int) ($slotSettings['buffer_before'] ?? 0),
                    (int) ($slotSettings['buffer_after'] ?? 0)
                );
                $ord['assignable_handymen'] = !empty($assignable) ? $assignable : null;
                unset($ord);
            }

            // Response
            if (!empty($orders) && $total != 0) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(ORDERS_FETCHED_SUCCESSFULLY, 'Orders fetched successfully.'),
                    'total' => strval($total),
                    'data' => $orders['data']
                ]);
            } else {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'data' => []
                ]);
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_orders()');
            return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }

    public function delete_orders()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'order_id' => 'required|numeric',
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
            $order_id = $this->request->getPost('order_id');
            $partner_id = $this->user_details['id'];
            $orders = fetch_details('orders', ['id' => $order_id, 'partner_id' => $partner_id]);
            if (empty($orders)) {
                $response = [
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No, Order Found'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $db = \Config\Database::connect();
            $builder = $db->table('orders')->delete(['id' => $order_id, 'partner_id' => $partner_id]);
            if ($builder) {
                $builder = $db->table('order_services')->delete(['order_id' => $order_id]);
                if ($builder) {
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_DELETED_SUCCESSFULLY, 'Order deleted successfully!'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(ORDER_DOES_NOT_EXIST, 'Order does not exist!'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(ORDER_NOT_FOUND, 'Order Not Found'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - delete_orders()');
            return $this->response->setJSON($response);
        }
    }

    public function update_order_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'order_id' => 'required|numeric',
                    'customer_id' => 'required|numeric',
                    'status' => 'required',
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
            $order_id = $this->request->getPost('order_id');
            $status = $this->request->getPost('status');
            $customer_id = $this->request->getPost('customer_id');
            $date = $this->request->getPost('date');
            $selected_time = $this->request->getPost('time');
            $otp = $this->request->getPost('otp');
            $work_complete_files = $this->request->getFiles();
            $work_started_files = $this->request->getFiles();
            $fileService = service('fileService');
            $orders_model = new Orders_model();
            $transactionModel = new Transaction_model();

            $orderMeta = $orders_model->select('payment_method')->where('id', $order_id)->first();
            if (($orderMeta['payment_method'] ?? '') !== 'cod') {
                $transaction = $transactionModel->where('order_id', $order_id)
                    ->orderBy('created_at', 'DESC')
                    ->first();

                if (($transaction['status'] ?? '') !== 'success') {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => payment_block_message($transaction['status'] ?? null),
                    ]);
                }
            }

            if ($status == "rescheduled") {
                // Pass the actor (provider) user_id so notifications are routed correctly.
                // - Provider updates => notify admin + customer (not provider).
                $res = validate_order_status($order_id, $status, $date, $selected_time, null, null, null, $this->user_details['id'], get_current_language_from_request(), 'provider');
            } else {
                if ($status == "completed") {
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res = validate_order_status($order_id, $status, '', '', $otp, isset($work_complete_files) ? $work_complete_files : "", null, $this->user_details['id'], get_current_language_from_request(), 'provider');
                } elseif ($status == "started") {
                    $work_started_files_data = [];
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res = validate_order_status($order_id, $status, '', '', '', isset($work_started_files) ? $work_started_files : "", null, $this->user_details['id'], get_current_language_from_request(), 'provider');
                    $order_data = fetch_details('orders', ['id' => $order_id]);
                    if (!empty($order_data)) {
                        if (!empty($order_data[0]['work_started_proof'])) {
                            $work_started_files_data = json_decode($order_data[0]['work_started_proof'], true);
                            foreach ($work_started_files_data as &$data) {
                                $data = $fileService->url($data, 'provider_work_evidence');
                            }
                        }
                    }
                } else if ($status == "booking_ended") {
                    $additional_charges = $this->request->getPost('additional_charges');
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res = validate_order_status($order_id, $status, '', '', '', isset($work_complete_files) ? $work_complete_files : "", $additional_charges, $this->user_details['id'], get_current_language_from_request(), 'provider');
                    $work_completed_files_data = [];
                    $order_data = fetch_details('orders', ['id' => $order_id]);
                    if (!empty($order_data)) {
                        if (!empty($order_data[0]['work_completed_proof'])) {
                            $work_completed_files_data = json_decode($order_data[0]['work_completed_proof'], true);
                            foreach ($work_completed_files_data as &$data) {
                                $data = $fileService->url($data, 'provider_work_evidence');
                            }
                        }
                    }
                } else if ($status == "cancelled") {
                    $cancel_reason_id = $this->request->getPost('cancel_reason_id');
                    $additional_info = $this->request->getPost('additional_info');
                    $cancel_validation = validate_cancel_reason($cancel_reason_id, $additional_info);
                    if ($cancel_validation['error']) {
                        return $this->response->setJSON($cancel_validation);
                    }
                    $res = validate_order_status($order_id, $status, '', '', '', '', '', $this->user_details['id'], get_current_language_from_request(), 'provider');
                    if (!$res['error']) {
                        update_details([
                            'cancel_reason_id' => (int) $cancel_reason_id,
                            'cancel_additional_info' => $cancel_validation['additional_info'],
                            'cancelled_by' => 'provider',
                        ], ['id' => $order_id], 'orders');
                    }
                } else {
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res = validate_order_status($order_id, $status, null, null, null, null, null, $this->user_details['id'], get_current_language_from_request(), 'provider');
                    // validate_order_status validates the transition but does not persist
                    // location-only statuses (no side effects/notifications) — persist here.
                    if (!$res['error'] && in_array($status, ['on_the_way', 'arrived'], true)) {
                        $orders_model->where('id', $order_id)->set(['status' => $status, 'status_changed_by_type' => 'provider', 'status_changed_by_id' => (int) $this->user_details['id']])->update();
                    }
                }
            }

            if ($res['error']) {
                $response['error'] = true;
                $response['message'] = $res['message'];
                $response['data'] = array();
                return $this->response->setJSON($response);
            }
            if ($status == "rescheduled") {
                $user_no = fetch_details('users', ['id' => $customer_id], 'phone')[0]['phone'];
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_RESCHEDULED_SUCCESSFULLY, 'Order rescheduled successfully!'),
                    'contact' => labels("you_can_call_on") . ' ' . $user_no . ' ' . labels("number_to_reschedule"),
                ];
                return $this->response->setJSON($response);
            }
            // $custom_notification = fetch_details('notifications', ['type' => "customer_order_started"]);
            if ($status == "awaiting") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_IS_IN_AWAITING, 'Order is in Awaiting!'),
                ];
            }
            if ($status == "confirmed") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_IS_CONFIRMED, 'Order is Confirmed!'),
                ];
            }
            if ($status == "cancelled") {
                $response = [
                    'error' => false,
                    'message' => labels(BOOKING_IS_CANCELLED, 'Booking is cancelled!'),
                ];
            }
            if ($status == "completed") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_COMPLETED_SUCCESSFULLY, 'Order Completed successfully!'),
                ];
            }
            if ($status == "started") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_STARTED_SUCCESSFULLY, 'Order Started successfully!'),
                    'data' => $work_started_files_data,
                ];
            }
            if ($status == "booking_ended") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_ENDED_SUCCESSFULLY, 'Order ended successfully!'),
                    'data' => $work_completed_files_data
                ];
            }
            if ($status == "on_the_way") {
                $response = [
                    'error' => false,
                    'message' => labels('order_is_on_the_way', 'Order is on the way!'),
                ];
            }
            if ($status == "reached_destination") {
                $response = [
                    'error' => false,
                    'message' => labels('order_has_reached_destination', 'Order has reached destination!'),
                ];
            }
            if ($status == "arrived") {
                $response = [
                    'error' => false,
                    'message' => labels('arrived', 'Arrived'),
                ];
            }
            //custom notification message
            // if ($status == 'awaiting') {
            //     $type = ['type' => "customer_order_awaiting"];
            // } elseif ($status == 'confirmed') {
            //     $type = ['type' => "customer_order_confirmed"];
            // } elseif ($status == 'rescheduled') {
            //     $type = ['type' => "customer_order_rescheduled"];
            // } elseif ($status == 'cancelled') {
            //     $type = ['type' => "customer_order_cancelled"];
            // } elseif ($status == 'started') {
            //     $type = ['type' => "customer_order_started"];
            // } elseif ($status == 'completed') {
            //     $type = ['type' => "customer_order_completed"];
            // } elseif ($status == 'booking_ended') {
            //     $type = ['type' => "customer_order_completed"];
            // }

            // $settings = get_settings('general_settings', true);
            // $app_name = get_company_title_with_fallback($settings);
            // $user_res = fetch_details('users', ['id' => $customer_id], 'username,fcm_id,platform');
            // $customer_msg = (!empty($custom_notification)) ? $custom_notification[0]['message'] : 'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $status . ' for your order ID #' . $order_id . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '';
            // $fcm_ids = array();

            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - update_order_status()');
            return $this->response->setJSON($response);
        }
    }

    public function verify_booking_otp()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'user_id' => ['rules' => 'required', 'errors' => ['required' => labels('user_id_is_required', 'User ID is required')]],
                'otp' => ['rules' => 'required', 'errors' => ['required' => labels('otp_is_required', 'OTP is required')]],
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $user_id = $this->request->getPost('user_id');
            $otp = $this->request->getPost('otp');

            $ordersModel = new Orders_model();
            $orderData = $ordersModel->select('otp')->where('user_id', $user_id)->where('otp', $otp)->get()->getResultArray();

            if (!empty($orderData)) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(OTP_VERIFIED, 'OTP verified'),
                    'data' => ['status' => true, 'otp' => $otp]
                ]);
            }

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(OTP_NOT_VERIFIED, 'OTP not verified'),
                'data' => ['status' => false, 'otp' => $otp]
            ]);
        } catch (\Throwable $th) {
            log_message('error', 'Error in app/Controllers/partner/api/V1.php - verify_booking_otp():' . $th->getTraceAsString());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
        }
    }

    public function invoice_download()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'order_id' => ['rules' => 'required', 'errors' => ['required' => labels('order_id_is_required', 'Order ID is required')]],
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $order_id = $this->request->getPost('order_id');
            $partnerId = $this->user_details['id'];


            $ordersModel = new Orders_model();
            $partnersModel = new Partners_model();
            $usersModel = new Users_model();

            $orders = $ordersModel->where('id', $order_id)->where('partner_id', $partnerId)->get()->getResultArray();
            if (isset($orders) && empty($orders)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    'data' => []
                ]);
            }

            $orderDetails = $ordersModel->invoice($order_id)['order'];
            $partnerDetails = $partnersModel->from('partner_details pd')->select('pd.company_name, pd.address, u.email, u.phone, u.image')->join('users u', 'u.id = pd.partner_id')->where('pd.partner_id', $partnerId)->get()->getResultArray();

            // Add translation support for partner details in invoice
            if (!empty($partnerDetails[0])) {
                $translatedData = get_translated_partner_field(partnerId: $partnerId, fieldName: 'company_name', defaultValue: $partnerDetails[0]['company_name']);
                $partnerDetails[0]['translated_company_name'] = $translatedData;

                $partnerDetails[0]['image'] = !empty($partnerDetails[0]['image'])
                    ? service('fileService')->url(basename($partnerDetails[0]['image']), 'profile', 'public/uploads/profiles/default.png')
                    : '';
            }

            $userId = $orderDetails['user_id'];

            $userDetails = $usersModel->where('id', $userId)->get()->getResultArray();

            $settings = get_settings('general_settings', true);

            $this->data['currency'] = $settings['currency'];
            $this->data['order'] = $orderDetails;
            $this->data['partner_details'] = $partnerDetails[0];
            $this->data['user_details'] = $userDetails[0];
            $this->data['data'] = $settings;

            $currency = $settings['currency'];

            $services = $orderDetails['services'];
            $total = count($services);

            if (!empty($orderDetails)) {
                $i = 0;
                // Use the same stored per-unit tax calculation as the customer invoice.
                $sum_net_amount = 0;
                $sum_tax_amount = 0;
                $default_tax_percentage = (float) ($services[0]['tax_percentage'] ?? 0);

                foreach ($services as &$service) {
                    $original_price = (float) ($service['price'] ?? 0);
                    $discount_price = (float) ($service['discount_price'] ?? 0);
                    $qty = (int) ($service['quantity'] ?? 1);
                    $currency_symbol = $currency;

                    // Unit price (discounted or original); line net = unit price * qty
                    $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                    $line_net = $unitPrice * $qty;
                    $tax_percentage = (float) ($service['tax_percentage'] ?? $default_tax_percentage);
                    $tax_type = $service['tax_type'] ?? 'excluded';
                    $line_tax = $tax_type === 'included'
                        ? ($line_net - ($line_net / (1 + ($tax_percentage / 100))))
                        : ($line_net * $tax_percentage / 100);

                    $sum_net_amount += $line_net;
                    $sum_tax_amount += $line_tax;

                    $rows[$i] = [
                        'service_title' => ucwords($service['service_title']),
                        'price' => $currency_symbol . number_format($original_price, 2, '.', ''),
                        'discount' => ($discount_price == 0) ? $currency_symbol . "0.00" : $currency_symbol . number_format(($original_price - $discount_price), 2, '.', ''),
                        'net_amount' => $currency_symbol . number_format($unitPrice, 2, '.', ''),
                        'tax' => number_format($tax_percentage, 2, '.', '') . '%',
                        'tax_amount' => $currency_symbol . number_format($line_tax, 2, '.', ''),
                        'quantity' => (string) $qty,
                        'subtotal' => $currency_symbol . number_format($line_net + $line_tax, 2, '.', '')
                    ];
                    $i++;
                }

                $array['total'] = $total;
                $array['rows'] = $rows;
                $additional_charges = !empty($orderDetails['additional_charges'])
                    ? json_decode($orderDetails['additional_charges'], true)
                    : [];

                // Keep every summary value based on the same line values shown above.
                $this->data['order']['total'] = number_format($sum_net_amount, 2, '.', '');
                $this->data['order']['invoice_no'] = $this->data['order']['invoice_no'] ?? $orderDetails['id'];
                $this->data['order']['tax'] = number_format($sum_tax_amount, 2, '.', '');
                $sub_total_incl_tax = $sum_net_amount + $sum_tax_amount;
                $visiting_charges = (float) ($orderDetails['visiting_charges'] ?? 0);
                $additional_total = 0;
                if (is_array($additional_charges) && !empty($additional_charges)) {
                    foreach ($additional_charges as $additional_charge) {
                        $additional_total += (float) ($additional_charge['charge'] ?? 0);
                    }
                } else {
                    $additional_total = (float) ($orderDetails['total_additional_charge'] ?? 0);
                }
                $additional_tax = 0;
                $additional_tax_rate = $default_tax_percentage;
                if (is_array($additional_charges)) {
                    foreach ($additional_charges as &$additional_charge) {
                        $charge_amount = (float) ($additional_charge['charge'] ?? 0);
                        $charge_rate = (float) ($additional_charge['tax_percentage'] ?? $additional_tax_rate);
                        $charge_tax = $charge_amount * $charge_rate / 100;
                        $additional_charge['tax_percentage'] = $charge_rate;
                        $additional_charge['tax_amount'] = $charge_tax;
                        $additional_tax += $charge_tax;
                    }
                    unset($additional_charge);
                }
                $visiting_tax = $visiting_charges * $default_tax_percentage / 100;
                $sum_tax_amount += $additional_tax;
                $sum_tax_amount += $visiting_tax;
                $this->data['order']['visiting_tax_percentage'] = $default_tax_percentage;
                $this->data['order']['visiting_tax'] = number_format($visiting_tax, 2, '.', '');
                $sub_total_incl_tax = $sum_net_amount + $sum_tax_amount;
                $this->data['order']['tax'] = number_format($sum_tax_amount, 2, '.', '');
                if ($additional_total > 0 && empty($additional_charges)) {
                    $additional_charges = [['name' => 'Additional charge', 'charge' => $additional_total, 'tax_percentage' => $additional_tax_rate, 'tax_amount' => $additional_tax]];
                }
                $this->data['additional_charges'] = $additional_charges;
                $this->data['order']['total_additional_charge'] = number_format($additional_total, 2, '.', '');
                $this->data['order']['additional_charge_tax'] = number_format($additional_tax, 2, '.', '');
                $promo_discount = (float) ($orderDetails['promo_discount'] ?? 0);
                $this->data['order']['promo_discount'] = number_format($promo_discount, 2, '.', '');
                $this->data['order']['sub_total'] = number_format($sub_total_incl_tax, 2, '.', '');
                $this->data['order']['overall_amount'] = number_format($sub_total_incl_tax + $visiting_charges + $additional_total, 2, '.', '');
                $this->data['order']['final_total'] = number_format($sub_total_incl_tax + $visiting_charges + $additional_total - $promo_discount, 2, '.', '');
                $this->data['rows'] = $rows;
                $this->data['additional_charges'] = $additional_charges;
                $this->data['currency'] = $currency;
                try {
                    $html = view('backend/admin/pages/invoice_from_api', $this->data);
                    $path = "public/uploads/";
                    $mpdf = new \Mpdf\Mpdf([
                        'tempDir' => $path,
                        'defaultFont' => 'dejavusans',
                        'mode' => 'utf-8',
                    ]);
                    $stylesheet = file_get_contents('public/backend/assets/css/vendor/bootstrap-table.css');
                    $mpdf->WriteHTML($stylesheet, 1); // CSS Script goes here.
                    $mpdf->WriteHTML($html);
                    $this->response->setHeader("Content-Type", "application/pdf");
                    $mpdf->Output('order-ID-' . $orderDetails['id'] . "-invoice.pdf", 'I');
                } catch (\Mpdf\MpdfException $e) {
                    print "Creating an mPDF object failed";
                    log_message('error', 'Creating an mPDF object failed with: ' . $e->getMessage());
                }
            } else {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    'data' => []
                ]);
            }
        } catch (\Exception $th) {
            // throw $th;
            log_message('error', date('Y-m-d H:i:s') . 'Error in app/Controllers/partner/api/V1.php - invoice_download(): Authorization: ' . $this->request->header('Authorization') . ' Params Passed: ' . json_encode($_POST) . ' Issue: ' . $th->getTraceAsString());

            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            return $this->response->setJSON($response);
        }
    }

    public function assign_handyman()
    {
        try {
            $partner_id = (int) $this->user_details['id'];
            $order_id = (int) $this->request->getPost('order_id');
            $handyman_ids = $this->request->getPost('handyman_ids');

            if (empty($order_id)) {
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred')]);
            }

            $ordersModel = new Orders_model();
            $order = $ordersModel->select('id, status, date_of_service, starting_time, ending_time')
                ->where('id', $order_id)
                ->where('partner_id', $partner_id)
                ->first();

            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found')]);
            }

            if (!in_array($order['status'] ?? '', ['confirmed', 'started', 'rescheduled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status')]);
            }

            if (empty($handyman_ids) || !is_array($handyman_ids)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('select_handyman', 'Select Handyman')]);
            }

            $handymanModel = new HandymanDetailsModel();
            $validIds = array_column($handymanModel->getForAssignment($partner_id), 'id');

            $slotSettingsModel = new \App\Models\ProviderSlotSettings_model();
            $slotSettings = $slotSettingsModel->findByPartner($partner_id) ?? [];
            $bufferBefore = (int) ($slotSettings['buffer_before'] ?? 0);
            $bufferAfter = (int) ($slotSettings['buffer_after'] ?? 0);
            $conflicts = $handymanModel->getConflictsForOrder(
                $partner_id,
                $order_id,
                $order['date_of_service'] ?? '',
                $order['starting_time'] ?? '',
                $order['ending_time'] ?? '',
                $bufferBefore,
                $bufferAfter
            );
            $conflictIds = array_flip(array_column($conflicts, 'id'));

            $bookingHandymenModel = new BookingHandymenModel();
            $existingRows = $bookingHandymenModel->select('handyman_id, is_lead')->where('order_id', $order_id)->findAll();
            $existingIds = array_column($existingRows, 'handyman_id');
            $hasLeadAlready = !empty(array_filter($existingRows, fn($r) => (int) $r['is_lead'] === 1));

            $toInsert = [];
            $now = date('Y-m-d H:i:s');
            foreach ($handyman_ids as $hid) {
                $hid = (int) $hid;
                if (!in_array($hid, $validIds)) {
                    return $this->response->setJSON(['error' => true, 'message' => labels('invalid_handyman', 'Invalid handyman')]);
                }
                if (isset($conflictIds[$hid])) {
                    return $this->response->setJSON(['error' => true, 'message' => labels('handyman_has_conflicting_booking', 'Handyman has a conflicting booking at that date and time')]);
                }
                if (!in_array($hid, $existingIds)) {
                    //  $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $partner_id, 'updated_at' => $now];
                    $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'status' => 'accepted', 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $partner_id, 'updated_at' => $now];
                }
            }

            if (!empty($toInsert) && !$hasLeadAlready) {
                $toInsert[0]['is_lead'] = 1;
            }

            if (!empty($toInsert)) {
                $bookingHandymenModel->insertBatch($toInsert);
                notify_handyman_assignment_change($order_id, array_column($toInsert, 'handyman_id'), 'booking_assigned', get_current_language_from_request());
            }

            $assignedHandymen = [];
            $allHandymen = $handymanModel->getForAssignment($partner_id);
            foreach ($allHandymen as $h) {
                if (in_array((int) $h['id'], array_column($toInsert, 'handyman_id'))) {
                    $leadIds = array_column(array_filter($toInsert, fn($r) => (int) $r['is_lead'] === 1), 'handyman_id');
                    $assignedHandymen[] = [
                        'id' => (int) $h['id'],
                        'username' => $h['username'],
                        'image' => service('fileService')->url($h['image'], 'profile'),
                        'is_lead' => in_array((int) $h['id'], $leadIds) ? 1 : 0,
                    ];
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('handyman_assigned_successfully', 'Handyman assigned successfully'),
                'data' => $assignedHandymen,
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Provider/OrdersApiController.php - assign_handyman()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    public function unassign_handyman()
    {
        try {
            $partner_id = (int) $this->user_details['id'];
            $order_id = (int) $this->request->getPost('order_id');
            $handyman_id = (int) $this->request->getPost('handyman_id');

            if (empty($order_id) || empty($handyman_id)) {
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred')]);
            }

            $ordersModel = new Orders_model();
            $order = $ordersModel->select('id, status')
                ->where('id', $order_id)
                ->where('partner_id', $partner_id)
                ->first();

            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found')]);
            }

            if (!in_array($order['status'] ?? '', ['confirmed', 'rescheduled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status')]);
            }

            $bookingHandymenModel = new BookingHandymenModel();
            $bookingHandyman = $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $handyman_id)->first();

            if (empty($bookingHandyman)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_not_found', 'Handyman not found')]);
            }

            if (!in_array($bookingHandyman['status'] ?? '', ['assigned', 'accepted'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_cannot_be_unassigned_in_current_status', 'Handyman cannot be unassigned in their current status')]);
            }

            $wasLead = (int) ($bookingHandyman['is_lead'] ?? 0) === 1;

            $db = \Config\Database::connect();
            $db->transStart();

            $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $handyman_id)->delete();

            if ($wasLead) {
                $remaining = $bookingHandymenModel->select('handyman_id')->where('order_id', $order_id)->first();
                if (!empty($remaining)) {
                    $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $remaining['handyman_id'])->set(['is_lead' => 1])->update();
                }
            }

            $db->transComplete();
            if (!$db->transStatus()) {
                return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
            }

            notify_handyman_assignment_change($order_id, [$handyman_id], 'booking_unassigned', get_current_language_from_request());

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('handyman_unassigned_successfully', 'Handyman unassigned successfully'),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Provider/OrdersApiController.php - unassign_handyman()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    public function set_lead_handyman()
    {
        try {
            $partner_id = (int) $this->user_details['id'];
            $order_id = (int) $this->request->getPost('order_id');
            $handyman_id = (int) $this->request->getPost('handyman_id');

            if (empty($order_id) || empty($handyman_id)) {
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred')]);
            }

            $ordersModel = new Orders_model();
            $order = $ordersModel->select('id, status')
                ->where('id', $order_id)
                ->where('partner_id', $partner_id)
                ->first();

            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found')]);
            }

            if (in_array($order['status'] ?? '', ['on_the_way', 'arrived', 'started', 'completed', 'cancelled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status')]);
            }

            $bookingHandymenModel = new BookingHandymenModel();
            $row = $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $handyman_id)->first();

            if (empty($row)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_not_found', 'Handyman not found')]);
            }

            $db = \Config\Database::connect();
            $db->transStart();
            $bookingHandymenModel->where('order_id', $order_id)->set(['is_lead' => 0])->update();
            $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $handyman_id)->set(['is_lead' => 1])->update();
            $db->transComplete();
            if (!$db->transStatus()) {
                return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('lead_handyman_set_successfully', 'Lead handyman set successfully'),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Provider/OrdersApiController.php - set_lead_handyman()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    public function get_available_slots()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'date' => 'required|valid_date[Y-m-d]',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => is_array($errors) ? (string) reset($errors) : (string) $errors,
                ]);
            }

            $partner_id = (int) $this->user_details['id'];
            $date = (new DateTime((string) $this->request->getPost('date')))->format('Y-m-d');

            // Reschedule: slots must fit the relevant service duration.
            //   order_id            → orders.duration (normal + custom job orders already placed)
            //   custom_job_request_id → partner_bids.duration (pre-booking custom job flow)
            //   neither             → 0 (calendar self-view; no service-fits-check)
            $duration = 0;
            $order_id = $this->request->getPost('order_id');
            $custom_job_request_id = $this->request->getPost('custom_job_request_id');
            if (!empty($order_id)) {
                $order = fetch_details('orders', ['id' => (int) $order_id, 'partner_id' => $partner_id], ['duration']);
                if (empty($order)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    ]);
                }
                $duration = (int) ($order[0]['duration'] ?? 0);
            } elseif (!empty($custom_job_request_id)) {
                $bid = fetch_details('partner_bids', [
                    'partner_id' => $partner_id,
                    'custom_job_request_id' => $custom_job_request_id,
                ]);
                if (empty($bid)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(THERE_IS_NO_DATA, 'There is no data'),
                    ]);
                }
                $duration = (int) ($bid[0]['duration'] ?? 0);
            }

            $result = service('slot')->getAvailableSlots($partner_id, $date, $duration, $partner_id);

            if ($result['error']) {
                return $this->response->setJSON(remove_null_values([
                    'error' => true,
                    'message' => $result['message'],
                ]));
            }

            return $this->response->setJSON(remove_null_values([
                'error' => false,
                'message' => $result['message'],
                'data' => $result['data'],
            ]));
        } catch (\Exception $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> Apis/Provider/OrdersApiController - get_available_slots()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function get_live_tracking_data()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $orderId = (int) $this->request->getPost('order_id');

            if ($orderId <= 0) {
                return ApiError(ERROR_OCCURED);
            }

            $ordersModel = model(Orders_model::class);
            $order = $ordersModel
                ->select('id, order_latitude, order_longitude')
                ->where(['id' => $orderId, 'partner_id' => $partnerId])
                ->first();

            if (empty($order)) {
                return ApiError(DATA_NOT_FOUND);
            }

            $liveTrackingModel = model(LiveTrackingModel::class);
            $tracking = $liveTrackingModel->where('order_id', $orderId)->first();

            $data = [
                'handyman_location' => $tracking ? [
                    'latitude' => $tracking['latitude'],
                    'longitude' => $tracking['longitude'],
                ] : null,
                'order_location' => [
                    'latitude' => $order['order_latitude'],
                    'longitude' => $order['order_longitude'],
                ],
            ];

            return ApiSuccess(DATA_FETCHED_SUCCESSFULLY, $data);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Provider/OrdersApiController.php - get_live_tracking_data()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }

    public function update_location()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $orderId = (int) $this->request->getPost('order_id');
            $latitude = trim((string) $this->request->getPost('latitude'));
            $longitude = trim((string) $this->request->getPost('longitude'));

            if ($orderId <= 0) {
                return ApiError(labels('order_id_is_required', 'Order ID is required'));
            }
            if ($latitude === '' || $longitude === '') {
                return ApiError(labels('latitude_longitude_required', 'Latitude and longitude are required'));
            }

            $order = $this->db->table('orders')
                ->select('status')
                ->where('id', $orderId)
                ->where('partner_id', $partnerId)
                ->get()
                ->getRowArray();

            if (empty($order)) {
                return ApiError(ORDER_NOT_FOUND);
            }

            if ($order['status'] !== 'on_the_way') {
                return ApiError(labels('location_update_not_allowed', 'Location update is only allowed when the order is on the way'));
            }

            $liveTrackingModel = model(LiveTrackingModel::class);
            $existing = $liveTrackingModel->where('order_id', $orderId)->first();

            $trackingData = [
                'order_id' => $orderId,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];

            if (!empty($existing)) {
                $liveTrackingModel->where('order_id', $orderId)->set($trackingData)->update();
            } else {
                $liveTrackingModel->insert($trackingData);
            }

            cache()->delete('live_tracking_order_' . $orderId);

            return ApiSuccess(DATA_UPDATED_SUCCESSFULLY);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Provider/OrdersApiController.php - update_location()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }

    public function live_map_data()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $rows = model(LiveTrackingModel::class)->getActiveHandymenForPartner($partnerId);

            return ApiSuccess(DATA_FETCHED_SUCCESSFULLY, $rows);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Provider/OrdersApiController.php - live_map_data()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }
}
