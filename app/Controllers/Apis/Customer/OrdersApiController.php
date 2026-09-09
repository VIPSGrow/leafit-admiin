<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\Flutterwave;
use App\Libraries\JWT;
use App\Libraries\Cashfree;
use App\Libraries\Paypal;
use App\Libraries\Paystack;
use App\Libraries\Razorpay;
use App\Libraries\Stripe;
use App\Libraries\StripeMoney;
use App\Libraries\Xendit;
use App\Models\BookingHandymenModel;
use App\Models\LiveTrackingModel;
use App\Models\Orders_model;
use App\Models\Partner_subscription_model;
use App\Models\Partners_model;
use App\Models\Transaction_model;
use App\Models\Users_model;
use Razorpay\Api\Api;

class OrdersApiController extends BaseController
{
    protected $request, $trans, $db, $orders, $data;
    protected Paypal $paypal_lib;
    protected Flutterwave $flutterwave;
    protected Paystack $paystack;
    protected Razorpay $razorpay;
    protected Cashfree $cashfree;
    protected Stripe $stripe;
    protected Xendit $xendit;
    protected JWT $JWT;
    private $builder;
    protected $excluded_routes =
        [
            "api/v1/index",
            "api/v1",
            "api/v1/flutterwave",
            "api/v1/invoice-download",
            "api/v1/get_paypal_link",
            "api/v1/paypal_return",
            "api/v1/app_payment_status",
            "api/v1/capturePayment",
            "api/v1/paystack_transaction_webview",
            "api/v1/app_paystack_payment_status",
            "api/v1/flutterwave_webview",
            "api/v1/flutterwave_payment_status",
            "api/v1/xendit_payment_status",
        ];
    private $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->paypal_lib = new Paypal();
        $this->request = \Config\Services::request();
        $this->flutterwave = new Flutterwave();
        $this->paystack = new paystack();
        $this->razorpay = new Razorpay();
        $this->cashfree = new Cashfree();
        $this->stripe = new Stripe();
        $this->xendit = new Xendit();
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

    public function place_order()
    {
        try {
            $validation = \Config\Services::validation();
            $rules = [
                'promo_code_id' => 'permit_empty',
                'payment_method' => 'required',
                'status' => 'required',
                'date_of_service' => 'required|valid_date[Y-m-d]',
                'starting_time' => 'required',
            ];
            $at_store = $this->request->getVar('at_store');
            $platform = $this->request->getVar('order_from') ?? 'web';
            if ($at_store == 1) {
                $rules['address_id'] = 'permit_empty|numeric';
            } else {
                $rules['address_id'] = 'required|numeric';
            }
            $validation->setRules($rules);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => ['type' => 'neworder'],
                ];
                return $this->response->setJSON($response);
            }
            if (empty($this->request->getVar('order_id')) || empty($this->request->getVar('custom_job_request_id'))) {
                $cart_data = fetch_cart(true, $this->user_details['id'], '', 0, 0, 'c.id', 'Desc', [], [], null, null, $at_store);
                if (!empty($cart_data)) {
                    $disabled_services = [];
                    $services_to_remove = [];
                    foreach ($cart_data['data'] as $item) {
                        $service_status = fetch_details('services', ['id' => $item['service_id']], ['status', 'title']);
                        if (!empty($service_status) && $service_status[0]['status'] == 0) {
                            $disabled_services[] = $service_status[0]['title'];
                            $services_to_remove[] = $item['service_id'];
                        }
                    }

                    if (!empty($disabled_services)) {
                        // Remove disabled services from cart
                        foreach ($services_to_remove as $service_id) {
                            delete_details(['service_id' => $service_id, 'user_id' => $this->user_details['id']], 'cart');
                        }

                        // Fetch updated cart data
                        $cart_data = fetch_cart(true, $this->user_details['id'], '', 0, 0, 'c.id', 'Desc', [], [], null, null, $at_store);

                        // Return error if all services were disabled
                        if (empty($cart_data)) {
                            return response_helper(labels(THE_FOLLOWING_SERVICES_ARE_NOT_AVAILABLE_AND_HAVE_BEEN_REMOVED_FROM_CART, 'The following services are not available and have been removed from cart: ' . implode(', ', $disabled_services)), true);
                        }

                        // Return warning that some services were removed
                        return response_helper(labels(THE_FOLLOWING_SERVICES_WERE_REMOVED_FROM_CART_AS_THEY_ARE_NO_LONGER_AVAILABLE, 'The following services were removed from cart as they are no longer available: ' . implode(', ', $disabled_services)), true);
                    }
                }
            }
            if (empty($this->request->getVar('order_id')) && empty($this->request->getVar('custom_job_request_id'))) {
                if (empty($cart_data)) {
                    return response_helper(labels(PLEASE_ADD_SOME_SERVICE_IN_CART, 'Please add some service in cart'), true);
                }
            }
            if (!empty($this->request->getVar('custom_job_request_id'))) {
                $db = \Config\Database::connect();
                $custom_job_data = $db->table('partner_bids pb')
                    ->select('pb.*, cj.*, cj.id as custom_job_id,pd.visiting_charges, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                    ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id')
                    ->join('users u', 'u.id = cj.user_id')
                    ->join('partner_details pd', 'pd.partner_id = pb.partner_id')
                    ->join('categories c', 'c.id = cj.category_id')
                    ->where('pb.partner_id', $this->request->getVar('bidder_id'))
                    ->where('cj.id', $this->request->getVar('custom_job_request_id'))
                    ->orderBy('pb.id', 'DESC')
                    ->get()
                    ->getResultArray();
            }
            $db = \Config\Database::connect();
            if ((empty($this->request->getVar('order_id'))) && empty($this->request->getVar('custom_job_request_id'))) {
                $service_ids = $cart_data['service_ids'];
                $quantity = $cart_data['qtys'];
                $sub_total = (float) ($cart_data['sub_total_without_tax'] ?? $cart_data['sub_total']);
                $tax_value = (float) ($cart_data['tax_value'] ?? 0);
                $total = $sub_total + $tax_value;
            } else if (!empty($this->request->getVar('custom_job_request_id'))) {
                $sub_total = (float) $custom_job_data[0]['counter_price'];
                $tax_value = (float) ($custom_job_data[0]['tax_amount'] ?? 0);
                $total = $sub_total + $tax_value;
            } else {
                $order = fetch_details('order_services', ['order_id' => $this->request->getPost('order_id')]);
                $service_ids = [];
                foreach ($order as $row) {
                    $service_ids[] = $row['service_id'];
                }
                $all_service_data = array();
                foreach ($service_ids as $row2) {
                    $service_data_array = fetch_details('services', ['id' => $row2]);
                    $service_data = $service_data_array[0];
                    $all_service_data[] = $service_data;
                }
                $quantities = [];
                foreach ($order as $row) {
                    $quantities[] = $row['quantity'];
                }
                $quantity = implode(',', $quantities);
                $total = 0;
                $tax_value = 0;
                $sub_total = 0;
                $duartion = 0;
                // Rebooking: compute total from existing order_services. Use os.quantity as order_quantity
                // so it is not overwritten by s.* (services table may have a quantity column).
                // When service join fails (e.g. custom job service_id '-'), use order line sub_total.
                $builder = $db->table('order_services os');
                $service_record = $builder
                    ->select('os.id as order_service_id, os.service_id, os.quantity as order_quantity, os.sub_total as order_sub_total, s.*, s.title as service_name, p.username as partner_name, pd.visiting_charges as visiting_charges, cat.name as category_name')
                    ->join('services s', 'os.service_id=s.id', 'left')
                    ->join('users p', 'p.id=s.user_id', 'left')
                    ->join('categories cat', 'cat.id=s.category_id', 'left')
                    ->join('partner_details pd', 'pd.partner_id=s.user_id', 'left')
                    ->where('os.order_id', $this->request->getPost('order_id'))->get()->getResultArray();
                foreach ($service_record as $s1) {
                    // When service join failed (custom job or deleted service), use stored line total.
                    $order_qty = isset($s1['order_quantity']) ? (int) $s1['order_quantity'] : 1;
                    if (empty($s1['id']) || $s1['service_id'] === '-') {
                        $line_total = (float) str_replace(',', '', $s1['order_sub_total'] ?? '0');
                        $sub_total += $line_total;
                        $duartion += (float) ($s1['duration'] ?? 0) * $order_qty;
                        continue;
                    }
                    $taxPercentageData = fetch_details('taxes', ['id' => $s1['tax_id']], ['percentage']);
                    if (!empty($taxPercentageData)) {
                        $taxPercentage = $taxPercentageData[0]['percentage'];
                    } else {
                        $taxPercentage = 0;
                    }
                    if ($s1['discounted_price'] == "0") {
                        $line_tax = ($s1['tax_type'] == "excluded") ? calculate_tax_amount((float) $s1['price'], (float) $taxPercentage, 'excluded') : calculate_tax_amount((float) $s1['price'], (float) $taxPercentage, 'included');
                        $price = (float) $s1['price'];
                    } else {
                        $line_tax = ($s1['tax_type'] == "excluded") ? calculate_tax_amount((float) $s1['discounted_price'], (float) $taxPercentage, 'excluded') : calculate_tax_amount((float) $s1['discounted_price'], (float) $taxPercentage, 'included');
                        $price = (float) $s1['discounted_price'];
                    }
                    // Use numeric values so tax is included correctly (number_format with commas would break addition).
                    $base_price = ($s1['tax_type'] === 'included') ? $price - $line_tax : $price;
                    $sub_total += $base_price * $order_qty;
                    $tax_value += $line_tax * $order_qty;
                    $duartion = $duartion + (float) ($s1['duration'] ?? 0) * $order_qty;
                }
                $total = $sub_total;
            }
            if ($at_store == "1") {
                $visiting_charges = 0;
            } else {
                if (empty($this->request->getPost('order_id')) && (empty($this->request->getVar('custom_job_request_id')))) {
                    $visiting_charges = $cart_data['visiting_charges'];
                } else if (!empty($this->request->getVar('custom_job_request_id'))) {
                    $visiting_charges = $custom_job_data[0]['visiting_charges'];
                } else {
                    $builder = $db->table('services s');
                    $extra_data = $builder
                        ->select('SUM(IF(s.discounted_price  > 0 , (s.discounted_price * os1.quantity) , (s.price *  os1.quantity))) as subtotal,
                    SUM( os1.quantity) as total_quantity,pd.visiting_charges as visiting_charges,SUM(s.duration *  os1.quantity) as total_duration,pd.advance_booking_days as advance_booking_days,
                    pd.company_name as company_name')
                        ->join('order_services os1', 'os1.service_id = s.id')
                        ->join('partner_details pd', 'pd.partner_id=s.user_id')
                        ->where('os1.order_id', $this->request->getPost('order_id'))
                        ->whereIn('s.id', $service_ids)->get()->getResultArray();
                    $visiting_charges = $extra_data[0]['visiting_charges'];
                }
            }
            $promo_code = $this->request->getVar('promo_code_id');
            $payment_method = $this->request->getVar('payment_method');
            $address_id = ($at_store == 1) ? 0 : $this->request->getVar('address_id');

            $status = "awaiting";
            $date_of_service = $this->request->getVar('date_of_service');
            $starting_time = ($this->request->getVar('starting_time'));
            // Normalize time string to HH:MM:SS so it matches availability checks everywhere.
            if (preg_match('/^(\d{1,2}):(\d{2})-(\d{2})$/', $starting_time, $matches)) {
                $starting_time = $matches[1] . ':' . $matches[2] . ':' . $matches[3];
            } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $starting_time, $matches)) {
                $starting_time = $matches[1] . ':' . $matches[2] . ':00';
            }
            $order_note = ($this->request->getVar('order_note')) ? $this->request->getVar('order_note') : "";
            if (empty($this->request->getPost('order_id')) && empty($this->request->getPost('custom_job_request_id'))) {
                $minutes = strtotime($starting_time) + ($cart_data['total_duration'] * 60);
            } else if (!empty($this->request->getPost('custom_job_request_id'))) {
                $minutes = strtotime($starting_time) + ($custom_job_data[0]['duration'] * 60);
            } else {
                $minutes = strtotime($starting_time) + ($duartion * 60);
            }
            $ending_time = date('H:i:s', $minutes);
            if ($at_store != 1) {
                if (!exists(['id' => $address_id], 'addresses')) {
                    return response_helper(labels(ADDRESS_NOT_EXIST, 'Address not exist'));
                }
            }
            $visiting_tax = 0;
            if ((float) $visiting_charges > 0) {
                $tax_percentage = 0;
                if (!empty($custom_job_data[0]['tax_percentage'])) {
                    $tax_percentage = (float) $custom_job_data[0]['tax_percentage'];
                } else {
                    $tax_service_ids = is_array($service_ids ?? null)
                        ? ($service_ids ?? [])
                        : explode(',', (string) ($service_ids ?? ''));
                    $tax_service_id = (int) ($tax_service_ids[0] ?? 0);
                    $service_tax_row = !empty($tax_service_id)
                        ? fetch_details('services', ['id' => $tax_service_id], ['tax_id'])
                        : [];
                    $tax_data = !empty($service_tax_row)
                        ? fetch_details('taxes', ['id' => $service_tax_row[0]['tax_id'] ?? 0], ['percentage'])
                        : [];
                    $tax_percentage = !empty($tax_data) ? (float) $tax_data[0]['percentage'] : 0;
                }
                $visiting_tax = calculate_tax_amount((float) $visiting_charges, $tax_percentage, 'excluded');
            }
            $tax_amount = (float) ($tax_value ?? 0);
            $tax_amount += $visiting_tax;
            $gross_total = (float) ($sub_total ?? $total) + $tax_amount + (float) $visiting_charges;
            $final_total = $gross_total;

            // Prepare service IDs only for normal service orders.
            // Custom job request orders do not use $service_ids at all.
            $ids = [];
            if (empty($this->request->getPost('custom_job_request_id'))) {
                if (empty($this->request->getPost('order_id'))) {
                    // New normal order from cart: $service_ids comes from $cart_data.
                    $ids = explode(',', $service_ids ?? '');
                } else {
                    // Rebooking / existing normal order: $service_ids is an array built from order_services.
                    $ids = $service_ids;
                }
            }
            if (!empty($this->request->getPost('custom_job_request_id'))) {
                $qtys = 1;
                $partner_id = $custom_job_data[0]['partner_id'];
                $current_date = date('Y-m-d');
                $service_total_duration = $custom_job_data[0]['duration'];
                $duartion = $custom_job_data[0]['duration'];
            } else {
                $qtys = explode(',', $quantity ?? '');
                $service_data = fetch_details('services', [], '', '', '', '', '', 'id', $ids);
                $partner_id = $service_data[0]['user_id'];
                $current_date = date('Y-m-d');
                $service_total_duration = 0;
                $service_duration = 0;
                if (empty($this->request->getPost('order_id'))) {
                    foreach ($cart_data['data'] as $main_data) {
                        $service_duration = ($main_data['servic_details']['duration']) * $main_data['qty'];
                        $service_total_duration = $service_total_duration + $service_duration;
                    }
                } else {
                    $service_total_duration = $duartion;
                }
            }
            // Slot reservation via the slot engine. If the caller already locked the slot
            // (check_available_slot returned a lock_id), consume it; otherwise lock + consume
            // here so a single-step place_order still works for legacy callers.
            $slotSvc = service('slot');
            $incomingLock = $this->request->getPost('lock_id');
            if (!empty($incomingLock)) {
                $slotResult = $slotSvc->confirmBooking(
                    (int) $incomingLock,
                    (int) $partner_id,
                    $date_of_service,
                    $starting_time,
                    (int) $service_total_duration,
                    (int) $this->user_details['id']
                );
            } else {
                $lockResult = $slotSvc->validateAndLockSlot(
                    (int) $partner_id,
                    $date_of_service,
                    $starting_time,
                    (int) $service_total_duration,
                    (int) $this->user_details['id']
                );
                if ($lockResult['error']) {
                    return response_helper($lockResult['message'], true);
                }
                $slotResult = $slotSvc->confirmBooking(
                    (int) $lockResult['data']['lock_id'],
                    (int) $partner_id,
                    $date_of_service,
                    $starting_time,
                    (int) $service_total_duration,
                    (int) $this->user_details['id']
                );
            }
            $availability = ['error' => $slotResult['error'], 'message' => $slotResult['message']];
            $insert_order = "";
            if ($slotResult['error'] === false) {
                $starting_time = $slotResult['data']['starting_time'] ?? $starting_time;
                $ending_time = $slotResult['data']['ending_time'] ?? $ending_time;
                $shift_id = $slotResult['data']['shift_id'] ?? null;
                $continuation = $slotResult['data']['continuation'] ?? null;
                $location_data = fetch_details('addresses', ['id' => $address_id]);
                $address['mobile'] = isset($location_data) && !empty($location_data) ? $location_data[0]['mobile'] : '';
                $address['address'] = isset($location_data) && !empty($location_data) ? $location_data[0]['address'] : '';
                $address['area'] = isset($location_data) && !empty($location_data) ? $location_data[0]['area'] : '';
                $address['state'] = isset($location_data) && !empty($location_data) ? $location_data[0]['state'] : '';
                $address['country'] = isset($location_data) && !empty($location_data) ? $location_data[0]['country'] : '';
                $address['pincode'] = isset($location_data) && !empty($location_data) ? $location_data[0]['pincode'] : '';
                // City is stored as a custom field; fetch it directly.
                $city_id = '';
                if (!empty($address_id)) {
                    $db = \Config\Database::connect();
                    $cityRow = $db->query(
                        "SELECT cacf.value
                         FROM customer_address_custom_fields cacf
                         WHERE cacf.address_id = ?
                           AND cacf.custom_field_id = (
                               SELECT id FROM custom_fields
                               WHERE field_label = 'City' AND field_group = 'customer_address'
                               LIMIT 1
                           )
                         LIMIT 1",
                        [(int) $address_id]
                    )->getRowArray();
                    $city_id = $cityRow['value'] ?? '';
                }
                $address['city'] = $city_id;

                // Build address string from custom fields if available.
                if (!empty($location_data[0])) {
                    $addrRow = $location_data[0];
                    \App\Models\Addresses_model::buildAddressFromCustomFields($addrRow);
                    $finaladdress = $addrRow['address'] ?? '';
                } else {
                    $outputArray = array(
                        $address['address'],
                        $address['area'],
                        $address['city'],
                        $address['state'],
                        $address['country'],
                        $address['pincode'],
                        $address['mobile']
                    );
                    $finaladdress = implode(',', $outputArray);
                }
                $service_total_duration = 0;
                $service_duration = 0;
                if (!empty($this->request->getPost('custom_job_request_id'))) {
                    $service_total_duration = $custom_job_data[0]['duration'];
                    $duartion = $custom_job_data[0]['duration'];
                } else {
                    if (empty($this->request->getPost('order_id'))) {
                        foreach ($cart_data['data'] as $main_data) {
                            $service_duration = ($main_data['servic_details']['duration']) * $main_data['qty'];
                            $service_total_duration = $service_total_duration + $service_duration;
                        }
                    } else {
                        $service_total_duration = $duartion;
                    }
                }
                // Slot engine returned authoritative timings and (optional) continuation.
                $timestamp = date('Y-m-d H:i:s');
                $hasContinuation = !empty($continuation);
                // Primary order's duration = the time spent in the originating shift only.
                // For non-spillover bookings this equals service_total_duration.
                $start_timestamp = strtotime($starting_time);
                $end_timestamp = strtotime($ending_time);
                $duration_minutes = max(1, (int) (($end_timestamp - $start_timestamp) / 60));
                if (!$hasContinuation) {
                    $duration_minutes = (int) $service_total_duration;
                } {
                    $order = [
                        'partner_id' => $partner_id,
                        'user_id' => $this->user_details['id'],
                        'city' => $city_id,
                        'total' => $gross_total,
                        'payment_method' => $payment_method,
                        'address_id' => isset($address_id) ? $address_id : "0",
                        'visiting_charges' => $visiting_charges,
                        'address' => isset($finaladdress) ? $finaladdress : "",
                        'date_of_service' => $date_of_service,
                        'starting_time' => $starting_time,
                        'ending_time' => $ending_time,
                        'duration' => $duration_minutes,
                        'shift_id' => $shift_id,
                        'status' => $status,
                        'remarks' => $order_note,
                        'otp' => random_int(100000, 999999),
                        'order_latitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['lattitude'] : $this->user_details['latitude'],
                        'order_longitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['longitude'] : $this->user_details['longitude'],
                        'created_at' => $timestamp,
                        'promo_code' => '',
                        'promo_discount' => 0,
                        'promocode_id' => null,
                        'admin_earnings' => 0,
                        'partner_earnings' => 0,
                        'is_commission_settled' => 0,
                        'payment_status' => 0,
                        'updated_at' => $timestamp,
                        'isRefunded' => 0,
                    ];
                    if (!empty($this->request->getPost('custom_job_request_id'))) {
                        $order['custom_job_request_id'] = $custom_job_data[0]['id'];
                    }
                    if (!empty($promo_code)) {
                        $fetch_promococde = fetch_details('promo_codes', ['id' => $promo_code]);
                        $promo_code = validate_promo_code($this->user_details['id'], $fetch_promococde[0]['id'], $gross_total);
                        if ($promo_code['error']) {
                            return $response['message'] = ($promo_code['message']);
                        }
                        $final_total = max(0, $gross_total - (float) $promo_code['data'][0]['final_discount']);
                        $order['promo_code'] = $promo_code['data'][0]['promo_code'] ?? '';
                        $order['promo_discount'] = $promo_code['data'][0]['final_discount'];
                        $order['promocode_id'] = $fetch_promococde[0]['id'];
                    }
                    $order['final_total'] = $final_total;
                    $insert_order = insert_details($order, 'orders');
                }
                if ($hasContinuation && !empty($insert_order)) {
                    $cont_start = (string) $continuation['starting_time'];
                    $cont_end = (string) $continuation['ending_time'];
                    $cont_duration = max(1, (int) ((strtotime($cont_end) - strtotime($cont_start)) / 60));
                    $sub_order = [
                        'partner_id' => $partner_id,
                        'user_id' => $this->user_details['id'],
                        'city' => $city_id,
                        'total' => $gross_total,
                        'payment_method' => $payment_method,
                        'address_id' => isset($address_id) ? $address_id : "",
                        'visiting_charges' => $visiting_charges,
                        'address' => isset($finaladdress) ? $finaladdress : "",
                        'date_of_service' => (string) $continuation['date'],
                        'starting_time' => $cont_start,
                        'ending_time' => $cont_end,
                        'duration' => $cont_duration,
                        'shift_id' => $continuation['shift_id'] ?? null,
                        'status' => $status,
                        'remarks' => "sub_order",
                        'otp' => random_int(100000, 999999),
                        'parent_id' => $insert_order['id'],
                        'order_latitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['lattitude'] : $this->user_details['latitude'],
                        'order_longitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['longitude'] : $this->user_details['longitude'],
                        'created_at' => $timestamp,
                        'promo_code' => '',
                        'promo_discount' => 0,
                        'promocode_id' => null,
                        'admin_earnings' => 0,
                        'partner_earnings' => 0,
                        'is_commission_settled' => 0,
                        'payment_status' => 0,
                        'updated_at' => $timestamp,
                        'isRefunded' => 0,
                    ];
                    if (!empty($this->request->getPost('custom_job_request_id'))) {
                        $sub_order['custom_job_request_id'] = $custom_job_data[0]['id'];
                    }
                    if (!empty($this->request->getVar('promo_code'))) {
                        $fetch_promococde = fetch_details('promo_codes', ['id' => $this->request->getVar('promo_code_id')]);
                        $promo_code = validate_promo_code($this->user_details['id'], $fetch_promococde[0]['id'], $gross_total);
                        if ($promo_code['error']) {
                            return $response['message'] = ($promo_code['message']);
                        }
                        $final_total = max(0, $gross_total - (float) $promo_code['data'][0]['final_discount']);
                        $sub_order['promo_code'] = $promo_code['data'][0]['promo_code'];
                        $sub_order['promo_discount'] = $promo_code['data'][0]['final_discount'];
                    }
                    $sub_order['final_total'] = $final_total;
                    $sub_order = insert_details($sub_order, 'orders');
                }
                if ($insert_order) {
                    if (!empty($this->request->getPost('custom_job_request_id'))) {
                        if ($custom_job_data[0]['tax_amount'] == "" || $custom_job_data[0]['tax_amount'] == null) {
                            $tax_amount = 0;
                        } else {
                            $tax_amount = $custom_job_data[0]['tax_amount'];
                        }
                        $data = [
                            'order_id' => $insert_order['id'],
                            'service_id' => '-',
                            'service_title' => $custom_job_data[0]['service_title'],
                            // Custom job titles are freeform text, not catalog services -
                            // no translated_service_details rows exist to snapshot.
                            'service_title_translations' => null,
                            'tax_percentage' => $custom_job_data[0]['tax_percentage'] ?? 0,
                            'tax_amount' => (float) ($custom_job_data[0]['tax_amount'] ?? 0) + $visiting_tax,
                            'price' => $custom_job_data[0]['counter_price'],
                            'discount_price' => 0,
                            'quantity' => 1,
                            'sub_total' => strval(str_replace(',', '', number_format(strval(($custom_job_data[0]['counter_price'] * (1) + $tax_amount)), 2))),
                            'status' => $status,
                            'custom_job_request_id' => $custom_job_data[0]['id'],
                            'updated_at' => $timestamp,
                        ];
                        insert_details($data, 'order_services');
                        $orderId['order_id'] = $insert_order['id'];
                        $paystack_webview_url = base_url() . '/api/v1/paystack_transaction_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . (number_format(strval($final_total), 2)) . '';
                        $orderId['paystack_link'] = ($payment_method == "paystack") ? (($platform == 'web') ? $paystack_webview_url : $this->paystack_transaction_webview($this->user_details['id'], $insert_order['id'], $final_total, $partner_id, 'order', $platform)) : "";
                        $orderId['paypal_link'] = ($payment_method == "paypal") ? $this->buildPaypalLink($insert_order['id'], null, $platform) : "";

                        $orderId['flutterwave'] = ($payment_method == "flutterwave") ? base_url() . '/api/v1/flutterwave_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . (number_format(strval($final_total), 2)) . '' : "";

                        $orderId['xendit'] = ($payment_method == "xendit") ? $this->xendit_transaction_webview($this->user_details['id'], $insert_order['id'], $final_total, $partner_id, 'order') : "";
                    } else {
                        $orders_model_for_snapshot = new Orders_model();
                        for ($i = 0; $i < count($ids); $i++) {
                            $service_details = get_taxable_amount($ids[$i]);
                            // Safe access to quantity array - handles cases where array indices may not match
                            // This prevents "Undefined array key" errors when $qtys array size doesn't match $ids array
                            $quantity_value = isset($qtys[$i]) ? $qtys[$i] : 1;

                            // Store service's tax_type in order_services for this booking (from services.tax_type).
                            $data = [
                                'order_id' => $insert_order['id'],
                                'service_id' => $ids[$i],
                                'service_title' => $service_details['title'],
                                'service_title_translations' => $orders_model_for_snapshot->buildServiceTitleTranslationsSnapshot((int) $ids[$i]),
                                'tax_percentage' => $service_details['tax_percentage'],
                                'tax_amount' => number_format(
                                    (float) $service_details['tax_amount'] + ($i === 0 ? $visiting_tax / max(1, (float) $quantity_value) : 0),
                                    2
                                ),
                                'price' => $service_details['price'],
                                'discount_price' => $service_details['discounted_price'],
                                'quantity' => $quantity_value,
                                'sub_total' => strval(str_replace(',', '', number_format(strval(($service_details['taxable_amount'] * ($quantity_value))), 2))),
                                'status' => $status,
                                'tax_type' => $service_details['tax_type'] ?? null,
                                'updated_at' => $timestamp,
                            ];
                            insert_details($data, 'order_services');
                            $orderId['order_id'] = $insert_order['id'];
                            $paystack_webview_url = base_url() . '/api/v1/paystack_transaction_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . (number_format(strval($final_total), 2)) . '';
                            $orderId['paystack_link'] = ($payment_method == "paystack") ? (($platform == 'web') ? $paystack_webview_url : $this->paystack_transaction_webview($this->user_details['id'], $insert_order['id'], $final_total, $partner_id, 'order', $platform)) : "";
                            $orderId['paypal_link'] = ($payment_method == "paypal") ? $this->buildPaypalLink($insert_order['id'], null, $platform) : "";
                            $orderId['flutterwave'] = ($payment_method == "flutterwave") ? base_url() . '/api/v1/flutterwave_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . (number_format(strval($final_total), 2)) . '' : "";

                            $orderId['xendit'] = ($payment_method == "xendit") ? $this->xendit_transaction_webview($this->user_details['id'], $insert_order['id'], $final_total, $partner_id, 'order') : "";
                        }
                    }
                    $orderId['sub_total'] = number_format((float) ($sub_total ?? 0), 2, '.', '');
                    $orderId['visiting_charges'] = number_format((float) $visiting_charges, 2, '.', '');
                    $orderId['tax_amount'] = number_format((float) $tax_amount, 2, '.', '');
                    $orderId['total'] = number_format((float) $gross_total, 2, '.', '');
                    $orderId['promo_discount'] = number_format((float) ($order['promo_discount'] ?? 0), 2, '.', '');
                    $orderId['final_total'] = number_format((float) $final_total, 2, '.', '');
                    if ($payment_method === 'cod') {
                        // Update custom job status if needed
                        if (!empty($this->request->getPost('custom_job_request_id'))) {
                            update_custom_job_status($insert_order['id'], 'booked');
                        }

                        // Prepare context for notification templates
                        $language = get_current_language_from_request();

                        // Prepare context data for notification templates
                        // This context will be used by NotificationService to extract variables
                        // Service names are explicitly included to ensure they're available for templates
                        $notificationContext = [
                            'provider_id' => $partner_id,
                            'user_id' => $this->user_details['id'],
                            'booking_id' => $insert_order['id'],
                            'amount' => $final_total,
                        ];

                        // Send notifications using NotificationService for all channels (FCM, Email, SMS)
                        // This unified approach handles all notification channels consistently
                        // Notifications are queued for background processing
                        try {
                            // Queue notifications to provider (FCM, Email, SMS)
                            // NotificationService automatically checks notification settings and unsubscribe status
                            queue_notification_service(
                                eventType: 'new_booking_received_for_provider',
                                recipients: ['user_id' => $partner_id],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels
                                    'language' => $language,
                                    'platforms' => ['android', 'ios', 'web', 'provider_panel'] // Provider platforms
                                ]
                            );
                            // log_message('info', '[NEW_BOOKING] Provider notification queued, job ID: ' . ($result ?: 'N/A'));

                            // Queue notifications to admin users (group_id = 1) (FCM, Email, SMS)
                            // Admin users should also be notified about new bookings
                            // queue_notification_service(
                            //     eventType: 'new_booking_received_for_provider',
                            //     recipients: [],
                            //     context: $notificationContext,
                            //     options: [
                            //         'user_groups' => [1], // Admin user group
                            //         'channels' => ['fcm', 'email', 'sms'], // All channels
                            //         'language' => $language,
                            //         'platforms' => ['admin_panel'] // Admin panel platform
                            //     ]
                            // );
                            // log_message('info', '[NEW_BOOKING] Admin notification queued, job ID: ' . ($result ?: 'N/A'));

                            // Queue notifications to customer (FCM, Email, SMS)
                            // NotificationService automatically checks notification settings and unsubscribe status
                            queue_notification_service(
                                eventType: 'new_booking_confirmation_to_customer',
                                recipients: ['user_id' => $this->user_details['id']],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels
                                    'language' => $language,
                                    'platforms' => ['android', 'ios', 'web'] // Customer platforms
                                ]
                            );
                            //  log_message('info', '[NEW_BOOKING] Customer notification result: ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            // Log error but don't fail the order placement
                            log_message('error', '[NEW_BOOKING] Notification error trace: ' . $notificationError->getTraceAsString());
                        }
                    }


                    $this->checkAndUpdateSubscriptionStatus($partner_id);
                    return response_helper(labels(ORDER_PLACED_SUCCESSFULLY, 'Order Placed successfully'), false, remove_null_values($orderId));
                } else {
                    return response_helper(labels(ORDER_NOT_PLACED, 'order not placed'));
                }
            } else {
                return response_helper($availability['message'], true);
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - place_order()');
            return $this->response->setJSON($response);
        }
    }

    public function get_orders()
    {
        try {
            $limit = !empty($this->request->getPost('limit')) ? (int) $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? (int) $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('sort'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'DESC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $download_invoice = ($this->request->getPost('download_invoice') && !empty($this->request->getPost('download_invoice'))) ? $this->request->getPost('download_invoice') : 1;
            $where = $additional_data = [];

            // Get the custom_request_order parameter (singular, as sent in the API request)
            $custom_request_order = $this->request->getPost('custom_request_order');

            // Handle custom job request orders (when custom_request_order = 1)
            if ($custom_request_order !== null && $custom_request_order == "1") {
                // Filter for custom job request orders (where custom_job_request_id is not empty)
                $where['o.custom_job_request_id !='] = "";

                // Add optional filters
                if ($this->request->getPost('id') && !empty($this->request->getPost('id'))) {
                    $where['o.id'] = $this->request->getPost('id');
                }
                if ($this->request->getPost('status') && !empty($this->request->getPost('status'))) {
                    $where['o.status'] = $this->request->getPost('status');
                }
                if ($this->user_details['id'] != '') {
                    $where['o.user_id'] = $this->user_details['id'];
                }
                if ($this->request->getPost('slug') && !empty($this->request->getPost('slug'))) {
                    $slug = $this->request->getPost('slug');
                    $get_id = explode('-', $slug);
                    if (count($get_id) == 2 && strtolower($get_id[0]) === 'inv') {
                        $where['o.id'] = $get_id[1];
                    }
                }

                // Fetch custom booking orders
                $orders = new Orders_model();
                $order_detail = $orders->custom_booking_list(true, $search, $limit, $offset, $sort, $order, $where, $download_invoice, '', '', '', '', false);
                if (!empty($order_detail['data'])) {
                    $bookingHandymenModel = new BookingHandymenModel();
                    $liveTrackingModel = new LiveTrackingModel();
                    foreach ($order_detail['data'] as &$booking) {
                        $handymen = $bookingHandymenModel->getAssignedHandymen((int) $booking['id']);
                        usort($handymen, function($a, $b) {
                            return $b['is_lead'] <=> $a['is_lead'];
                        });
                        $booking['assigned_handymen'] = array_map(fn($h) => [
                            'id' => $h['id'],
                            'username' => $h['username'],
                            'profile_image' => $h['image'],
                            'is_lead' => $h['is_lead'],
                            'rating_id' => $h['rating_id'],
                            'rating' => $h['rating'],
                            'review' => $h['review'],
                            'review_images' => $h['review_images']
                        ], $handymen);
                        $booking['tax_type'] = $orders->getOrderTaxType((int) $booking['id']);
                        $booking['live_tracking_started'] = $liveTrackingModel->hasStarted((int) $booking['id']) ? '1' : '0';
                    }
                    unset($booking);
                    // Translations are now handled in the Orders model
                    return response_helper(labels(CUSTOM_BOOKING_FETCHED_SUCCESSFULLY, 'Custom booking fetched successfully'), false, remove_null_values($order_detail['data']), 200, ['total' => $order_detail['total']]);
                } else {
                    return response_helper(labels(ORDER_NOT_FOUND, 'Order not found'), false, [], 200, ['total' => "0"]);
                }
            }
            // Handle normal orders (when custom_request_order = 0 or not provided)
            else {
                // Build where conditions for normal orders
                if ($this->request->getPost('id') && !empty($this->request->getPost('id'))) {
                    $where['o.id'] = $this->request->getPost('id');
                } else {
                    // Only filter out custom job requests if slug is not provided
                    // When custom_request_order=0, explicitly filter for normal orders
                    if (empty($this->request->getPost('slug'))) {
                        if ($custom_request_order !== null && $custom_request_order == "0") {
                            // Explicitly filter for normal orders (custom_job_request_id is NULL)
                            $where['o.custom_job_request_id'] = NULL;
                        } elseif ($custom_request_order === null) {
                            // If parameter not provided, default to normal orders
                            $where['o.custom_job_request_id'] = NULL;
                        }
                    }
                }

                // Add optional filters
                if ($this->request->getPost('status') && !empty($this->request->getPost('status'))) {
                    $where['o.status'] = $this->request->getPost('status');
                }
                if ($this->user_details['id'] != '') {
                    $where['o.user_id'] = $this->user_details['id'];
                }
                if ($this->request->getPost('slug') && !empty($this->request->getPost('slug'))) {
                    $slug = $this->request->getPost('slug');
                    $get_id = explode('-', $slug);
                    if (count($get_id) == 2 && strtolower($get_id[0]) === 'inv') {
                        $where['o.id'] = $get_id[1];
                    }
                }

                // Fetch normal orders
                $orders = new Orders_model();
                $order_detail = $orders->list(true, $search, $limit, $offset, $sort, $order, $where, $download_invoice, '', '', '', '', false);

                if (!empty($order_detail['data'])) {
                    $bookingHandymenModel = new BookingHandymenModel();
                    $liveTrackingModel = new LiveTrackingModel();
                    foreach ($order_detail['data'] as &$booking) {
                        $handymen = $bookingHandymenModel->getAssignedHandymen((int) $booking['id']);
                        usort($handymen, function($a, $b) {
                            return $b['is_lead'] <=> $a['is_lead'];
                        });
                        $booking['assigned_handymen'] = array_map(fn($h) => [
                            'id' => $h['id'],
                            'username' => $h['username'],
                            'profile_image' => $h['image'],
                            'is_lead' => $h['is_lead'],
                            'rating_id' => $h['rating_id'],
                            'rating' => $h['rating'],
                            'review' => $h['review'],
                            'review_images' => $h['review_images']
                        ], $handymen);
                        $booking['tax_type'] = $orders->getOrderTaxType((int) $booking['id']);
                        $booking['live_tracking_started'] = $liveTrackingModel->hasStarted((int) $booking['id']) ? '1' : '0';
                    }
                    unset($booking);
                    // Translations are now handled in the Orders model
                    return response_helper(labels(ORDER_FETCHED_SUCCESSFULLY, 'Order fetched successfully'), false, remove_null_values($order_detail['data']), 200, ['total' => $order_detail['total']]);
                } else {
                    return response_helper(labels(ORDER_NOT_FOUND, 'Order not found'), false, [], 200, ['total' => "0"]);
                }
            }
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_orders()');
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
                    'status' => 'required',
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
            $customer_id = $this->user_details['id'];
            $status = $this->request->getPost('status');
            $date = $this->request->getPost('date');
            $selected_time = $this->request->getPost('time');

            if ($status == "rescheduled") {

                // Pass the actor (customer) user_id so notifications are routed correctly.
                // - Customer updates => notify admin + provider (not customer).
                $validate = validate_order_status($order_id, $status, $date, $selected_time, null, null, null, $customer_id, get_current_language_from_request(), 'customer');
                $where['o.id'] = $order_id;
                $orders = new Orders_model();
                $order_detail = $orders->list(true, '', 10, 0, 'o.id', 'DESC', $where, '', '', '', '', '', false);
                $response['error'] = $validate['error'];
                $response['message'] = $validate['message'];
                $response['data'] = $order_detail;
                return $this->response->setJSON($response);
            } else {
                if ($status == "cancelled") {
                    $cancel_reason_id = $this->request->getPost('cancel_reason_id');
                    $additional_info = $this->request->getPost('additional_info');
                    $cancel_validation = validate_cancel_reason($cancel_reason_id, $additional_info);
                    if ($cancel_validation['error']) {
                        return $this->response->setJSON($cancel_validation);
                    }
                    // Block cancellation of a multi-day/cross-shift parent when any
                    // child segment is already in progress or done — partial cancel is unsupported.
                    $blocking_child = \Config\Database::connect()
                        ->table('orders')
                        ->select('id, date_of_service, status')
                        ->where('parent_id', $order_id)
                        ->whereIn('status', ['started', 'completed'])
                        ->limit(1)
                        ->get()
                        ->getFirstRow('array');
                    if (!empty($blocking_child)) {
                        return $this->response->setJSON([
                            'error' => true,
                            'message' => labels(CANNOT_CANCEL_SERVICE_IN_PROGRESS, 'Cannot cancel — service is already in progress'),
                        ]);
                    }
                }
                // Pass the actor (customer) user_id so notifications are routed correctly.
                // - Customer updates => notify admin + provider (not customer).
                $validate = validate_order_status($order_id, $status, null, null, null, null, null, $customer_id, get_current_language_from_request(), 'customer');
                if ($status == "cancelled" && !$validate['error']) {
                    update_details([
                        'cancel_reason_id' => (int) $cancel_reason_id,
                        'cancel_additional_info' => $cancel_validation['additional_info'],
                        'cancelled_by' => 'customer',
                    ], ['id' => $order_id], 'orders');
                }
            }
            if ($validate['error']) {
                $response['error'] = true;
                $response['message'] = $validate['message'];
                return $this->response->setJSON($response);
            } else {
                if ($validate['error']) {
                    $response['error'] = true;
                    $response['message'] = $validate['message'];
                    $response['csrfName'] = csrf_token();
                    $response['csrfHash'] = csrf_hash();
                    $response['data'] = array();
                    return $this->response->setJSON($response);
                }
                if ($status == "awaiting") {
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_IS_IN_AWAITING, 'Order is in Awaiting!'),
                    ];
                    return $this->response->setJSON($response);
                }
                if ($status == "confirmed") {
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_IS_CONFIRMED, 'Order is Confirmed!'),
                    ];
                    return $this->response->setJSON($response);
                }
                if ($status == "cancelled") {
                    $orders = new Orders_model();
                    $where['o.id'] = $order_id;
                    $order_detail = $orders->list(true, '', 10, 0, 'o.id', 'DESC', $where, '', '', '', '', '', false);
                    $response = [
                        'error' => false,
                        'message' => labels(BOOKING_IS_CANCELLED, 'Booking is cancelled!'),
                        'data' => $order_detail,
                    ];
                    return $this->response->setJSON($response);
                }
                if ($status == "completed") {
                    $commision = unsettled_commision($this->userId);
                    update_details(['balance' => $commision], ['id' => $this->userId], 'users');
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_COMPLETED_SUCCESSFULLY, 'Order Completed successfully!'),
                    ];
                    return $this->response->setJSON($response);
                }
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - update_order_status()');
            return $this->response->setJSON($response);
        }
    }

    public function razorpay_create_order()
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
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $order_id = $this->request->getPost('order_id');
            if ($this->request->getPost('order_id') && !empty($this->request->getPost('order_id'))) {
                $where['o.id'] = $this->request->getPost('order_id');
            }
            $orders = new Orders_model();
            $order_detail = $orders->list(true, "", null, null, "", "", $where);
            $settings = get_settings('payment_gateways_settings', true);
            if (!empty($order_detail) && !empty($settings)) {
                $is_additional_charge = $this->request->getVar('is_additional_charge') == 1;
                $pending_id = '';
                if ($is_additional_charge) {
                    // Source of truth for additional-charge amount = the pending transactions row
                    // created by add_transaction. orders.total_additional_charge can be cumulative
                    // or stale and would cause amount mismatch at the webhook.
                    $pending_additional = fetch_details(
                        'transactions',
                        [
                            'order_id' => $order_id,
                            'user_id' => $this->user_details['id'] ?? 0,
                            'transaction_type' => 'transaction',
                            'status' => 'pending',
                            'message' => 'txn_additional_charges',
                        ],
                        ['id', 'amount'],
                        1,
                        0,
                        'id',
                        'DESC'
                    );
                    if (empty($pending_additional)) {
                        return $this->response->setJSON([
                            'error' => true,
                            'message' => labels(DETAILS_NOT_FOUND, 'No pending additional charge to pay. Call add_transaction first.'),
                            'data' => [],
                        ]);
                    }
                    $pending_id = (string) $pending_additional[0]['id'];
                    $price = (float) $pending_additional[0]['amount'];
                } else {
                    $price = $order_detail['data'][0]['final_total'];
                }
                $currency = $settings['razorpay_currency'];

                $amount = intval($price * 100);

                $notes = ['order_id' => (string) $order_id];
                if ($is_additional_charge) {
                    $notes['additional_charges_transaction_id'] = $pending_id;
                }

                $create_order = $this->razorpay->create_order($amount, $order_id, $currency, $notes);
                if (!empty($create_order) && !empty($create_order['id'])) {
                    // Persist intent: pending transactions row carrying the Razorpay order id
                    // as `reference`. Webhook becomes pure UPDATE keyed by (type=razorpay, reference)
                    // — no insert race possible. See unique index uniq_type_reference.
                    $rzp_order_id = (string) $create_order['id'];
                    $user_id = (int) ($this->user_details['id'] ?? 0);
                    $partner_id = (int) ($order_detail['data'][0]['partner_id'] ?? 0);
                    $base_currency = (string) ($order_detail['data'][0]['currency_code'] ?? '');

                    if ($is_additional_charge) {
                        // Tag the existing pending additional-charge row with this reference.
                        if (!empty($pending_id)) {
                            update_details(
                                ['reference' => $rzp_order_id, 'type' => 'razorpay'],
                                ['id' => $pending_id],
                                'transactions'
                            );
                        }
                    } else {
                        // Reuse a pre-existing pending razorpay row for this order/user when present
                        // (covers client retries hitting create_order m777ore than once).
                        $existing_pending = fetch_details(
                            'transactions',
                            [
                                'order_id' => $order_id,
                                'user_id' => $user_id,
                                'transaction_type' => 'transaction',
                                'type' => 'razorpay',
                                'status' => 'pending',
                            ],
                            ['id'],
                            1,
                            0,
                            'id',
                            'DESC'
                        );

                        if (!empty($existing_pending[0]['id'])) {
                            update_details(
                                ['reference' => $rzp_order_id, 'amount' => $price, 'currency_code' => $base_currency ?: strtoupper((string) $currency)],
                                ['id' => $existing_pending[0]['id']],
                                'transactions'
                            );
                        } else {
                            add_transaction([
                                'transaction_type' => 'transaction',
                                'user_id' => $user_id,
                                'partner_id' => $partner_id,
                                'order_id' => $order_id,
                                'type' => 'razorpay',
                                'txn_id' => '',
                                'reference' => $rzp_order_id,
                                'amount' => $price,
                                'status' => 'pending',
                                'currency_code' => $base_currency ?: strtoupper((string) $currency),
                                'message' => 'txn_order_placed',
                            ]);
                        }
                    }

                    $response = [
                        'error' => false,
                        'message' => labels(RAZORPAY_ORDER_CREATED, 'razorpay order created'),
                        'data' => $create_order,
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(RAZORPAY_ORDER_NOT_CREATED, 'razorpay order not created'),
                        'data' => [],
                    ];
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ];
            }
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - razorpay_create_order()');
            return $this->response->setJSON($response);
        }
    }

    public function cashfree_create_order()
    {
        try {
            $validation = \Config\Services::validation();

            $rules = [
                'order_id' => 'required|numeric',
                'is_additional_charge' => 'permit_empty|in_list[0,1]',
            ];


            $validation->setRules($rules);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $order_id = (int) $this->request->getPost('order_id');

            $orders = new Orders_model();
            $where = [
                'o.id' => $order_id,
                'o.user_id' => $this->user_details['id'] ?? 0,
            ];
            $order_detail = $orders->list(true, "", null, null, "", "", $where);

            if (empty($order_detail) || empty($order_detail['data'][0])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $settings = get_settings('payment_gateways_settings', true);
            $credentials = $this->cashfree->get_credentials();
            if (
                empty($settings) ||
                ($credentials['status'] ?? 'disable') !== 'enable' ||
                empty($credentials['app_id']) ||
                empty($credentials['secret_key'])
            ) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $is_additional_charge = $this->request->getVar('is_additional_charge') == 1;
            $pending_additional_id = null;

            if ($is_additional_charge) {
                // Source of truth for additional-charge amount = pending transactions row
                // created by add_transaction. orders.total_additional_charge can be cumulative
                // or stale and would cause amount mismatch at the webhook.
                $pending_additional = fetch_details(
                    'transactions',
                    [
                        'order_id' => $order_id,
                        'user_id' => $this->user_details['id'] ?? 0,
                        'transaction_type' => 'transaction',
                        'status' => 'pending',
                        'message' => 'txn_additional_charges',
                    ],
                    ['id', 'amount'],
                    1,
                    0,
                    'id',
                    'DESC'
                );
                if (empty($pending_additional)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DETAILS_NOT_FOUND, 'No pending additional charge to pay. Call add_transaction first.'),
                        'data' => [],
                    ]);
                }
                $pending_additional_id = (int) $pending_additional[0]['id'];
                $price = (float) $pending_additional[0]['amount'];
            } else {
                $price = $order_detail['data'][0]['final_total'] ?? 0;
            }

            if (!is_numeric($price) || (float) $price <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $currency = $settings['cashfree_currency'] ?? ($credentials['currency'] ?? 'INR');
            $user_id = (int) ($this->user_details['id'] ?? 0);
            $user = fetch_details('users', ['id' => $user_id]);
            $user = $user[0] ?? [];

            $customer_name = trim($user['username'] ?? '');
            if ($customer_name !== '') {
                $customer_name = (strlen($customer_name) < 3) ? $user['username'] . '_' . $user_id : $customer_name;
            } else {
                $customer_name = 'Customer_' . $user_id;
            }

            $customer_email = $this->user_details['email'] ?? ($user['email'] ?? '');
            if (empty($customer_email)) {
                $customer_email = 'customer' . $user_id . '@example.com';
            }

            $customer_phone_raw = $this->user_details['phone'] ?? ($user['phone'] ?? '');
            $customer_phone = preg_replace('/\D+/', '', (string) $customer_phone_raw);
            if (empty($customer_phone)) {
                $customer_phone = '9999999999';
            }

            $gateway_order_id = $is_additional_charge
                ? ('additional_charges_' . $order_id . '_' . time())
                : ('order_' . $order_id . '_' . time());

            $return_url_base = !empty($credentials['website_url'])
                ? rtrim($credentials['website_url'], '/')
                : rtrim(base_url(), '/');

            $payload = [
                'order_id' => $gateway_order_id,
                'order_amount' => (float) number_format((float) $price, 2, '.', ''),
                'order_currency' => strtoupper($currency),
                'customer_details' => [
                    'customer_id' => 'user_' . $user_id,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'customer_phone' => $customer_phone,
                ],
                'order_meta' => [
                    'return_url' => $return_url_base . '/payment-status?order_id=' . $order_id . '&payment_status=pending',
                ],
                'order_note' => $is_additional_charge
                    ? ('Additional charges payment for order #' . $order_id)
                    : ('Booking payment for order #' . $order_id),
                'order_tags' => [
                    'order_id' => (string) $order_id,
                    'user_id' => (string) $user_id,
                    'payment_for' => $is_additional_charge ? 'additional_charges' : 'booking',
                ],
            ];

            if ($is_additional_charge && $pending_additional_id) {
                $payload['order_tags']['additional_charges_transaction_id'] = (string) $pending_additional_id;
            }

            $create_order = $this->cashfree->create_order($payload);

            if (!empty($create_order['error']) || empty($create_order['payment_session_id'])) {
                $message = $create_order['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $create_order,
                ]);
            }

            $base_currency = (string) ($order_detail['data'][0]['currency_code'] ?? '');
            $stored_currency = $base_currency !== '' ? strtoupper($base_currency) : strtoupper((string) $currency);

            if ($is_additional_charge) {
                // Stamp Cashfree reference on the existing pending additional-charge row so the
                // webhook can resolve by (type=cashfree, reference) and finalise the txn row.
                if ($pending_additional_id) {
                    update_details(
                        ['reference' => $gateway_order_id, 'type' => 'cashfree'],
                        ['id' => $pending_additional_id],
                        'transactions'
                    );
                }
            } else {
                // Persist intent: pending transactions row carrying the Cashfree gateway order id
                // as `reference`. Webhook becomes pure UPDATE keyed by (type=cashfree, reference).
                $partner_id_local = (int) ($order_detail['data'][0]['partner_id'] ?? 0);
                $existing_pending = fetch_details(
                    'transactions',
                    [
                        'order_id' => $order_id,
                        'user_id' => $user_id,
                        'transaction_type' => 'transaction',
                        'type' => 'cashfree',
                        'status' => 'pending',
                    ],
                    ['id'],
                    1,
                    0,
                    'id',
                    'DESC'
                );

                if (!empty($existing_pending[0]['id'])) {
                    update_details(
                        ['reference' => $gateway_order_id, 'amount' => $price, 'currency_code' => $stored_currency],
                        ['id' => $existing_pending[0]['id']],
                        'transactions'
                    );
                } else {
                    add_transaction([
                        'transaction_type' => 'transaction',
                        'user_id' => $user_id,
                        'partner_id' => $partner_id_local,
                        'order_id' => $order_id,
                        'type' => 'cashfree',
                        'txn_id' => '',
                        'reference' => $gateway_order_id,
                        'amount' => $price,
                        'status' => 'pending',
                        'currency_code' => $stored_currency,
                        'message' => 'txn_order_placed',
                    ]);
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Cashfree order created'),
                'data' => $create_order,
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') .
                ' Params :: ' . json_encode($_POST) .
                ' Issue => ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - cashfree_create_order()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Get the status of a Cashfree order.
     *
     * Required:
     * - order_id (POST) — the Cashfree gateway order ID (the string passed to
     *   Cashfree when creating the order, stored in `transactions.reference`).
     *
     * Flow:
     * 1. Look up the local transaction row by `reference = order_id`.
     * 2. If found with a terminal status (success / failed), return it immediately.
     * 3. Otherwise call the Cashfree SDK (`GET /pg/orders/{order_id}`) to fetch
     *    the live status and return the API response.
     */
    public function get_cashfree_order_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'order_id' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $order_id = (string) $this->request->getPost('order_id');

            // 1. Try to find the transaction locally
            $transaction = fetch_details(
                'transactions',
                ['reference' => $order_id],
                ['id', 'order_id', 'user_id', 'type', 'txn_id', 'amount', 'status', 'message', 'transaction_date', 'currency_code', 'reference'],
                1,
                0,
                'id',
                'DESC'
            );

            if (!empty($transaction[0]) && in_array($transaction[0]['status'], ['success', 'failed'])) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Transaction fetched successfully'),
                    'data' => [
                        'source' => 'local',
                        'transaction' => $transaction[0],
                    ],
                ]);
            }

            // 2. Transaction not found or still pending — query Cashfree API
            $credentials = $this->cashfree->get_credentials();
            if (
                ($credentials['status'] ?? 'disable') !== 'enable' ||
                empty($credentials['app_id']) ||
                empty($credentials['secret_key'])
            ) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'Payment gateway not configured'),
                    'data' => [],
                ]);
            }

            $cf_order = $this->cashfree->fetch_order($order_id);

            if (!empty($cf_order['error'])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $cf_order['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                    'data' => $cf_order,
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Cashfree order status fetched'),
                'data' => [
                    'source' => 'cashfree',
                    'order' => $cf_order,
                    'transaction' => $transaction[0] ?? null,
                ],
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') .
                ' Params :: ' . json_encode($_POST) .
                ' Issue => ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_cashfree_order_status()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Create Stripe PaymentIntent for an order (and optionally for additional charges).
     *
     * Required:
     * - order_id (POST)
     *
     * Optional:
     * - transaction_id (POST) => when present, we treat this as "additional charges" payment
     *   and we attach `additional_charges_transaction_id` into Stripe metadata so the Stripe
     *   webhook can update the correct `transactions` row (same flow as Xendit/Paystack/etc).
     *
     * Notes:
     * - Stripe amount must be in minor units; use `StripeMoney` for correct conversion.
     * - Currency is taken from `payment_gateways_settings.stripe_currency`.
     */
    public function create_stripe_payment_intent()
    {
        try {
            $validation = \Config\Services::validation();

            $transaction_id_raw = $this->request->getPost('transaction_id');

            // Conditional validation
            $rules = [
                'transaction_id' => 'permit_empty|numeric',
            ];

            if (empty($transaction_id_raw)) {
                $rules['order_id'] = 'required|numeric';
            } else {
                $rules['order_id'] = 'permit_empty|numeric';
            }

            $validation->setRules($rules);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $transaction_id = !empty($transaction_id_raw) ? (int) $transaction_id_raw : null;

            // =========================
            // If transaction_id exists → derive order_id
            // =========================
            if (!empty($transaction_id)) {
                $tx = fetch_details('transactions', [
                    'id' => $transaction_id,
                    'user_id' => $this->user_details['id'] ?? 0,
                ]);

                if (empty($tx)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                        'data' => [],
                    ]);
                }

                $order_id = (int) $tx[0]['order_id'];
            }

            // =========================
            // Fetch order
            // =========================
            $orders = new Orders_model();
            $where = [
                'o.id' => $order_id,
                'o.user_id' => $this->user_details['id'] ?? 0,
            ];

            $order_detail = $orders->list(true, "", null, null, "", "", $where);

            $settings = get_settings('payment_gateways_settings', true);

            if (empty($order_detail) || empty($order_detail['data'][0]) || empty($settings)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            // =========================
            // Currency
            // =========================
            $currency = $settings['stripe_currency'] ?? 'USD';

            // =========================
            // Decide amount
            // =========================
            $is_additional_charge = !empty($transaction_id);
            $pending_id = '';

            if ($is_additional_charge) {
                // Source of truth for additional-charge amount = the pending transactions row
                // created by add_transaction. orders.total_additional_charge can be cumulative
                // or stale and would cause amount mismatch at the webhook.
                $pending_additional = fetch_details(
                    'transactions',
                    [
                        'id' => $transaction_id,
                        'user_id' => $this->user_details['id'] ?? 0,
                        'transaction_type' => 'transaction',
                        'status' => 'pending',
                        'message' => 'txn_additional_charges',
                    ],
                    ['id', 'amount'],
                    1,
                    0,
                    'id',
                    'DESC'
                );
                if (empty($pending_additional)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DETAILS_NOT_FOUND, 'No pending additional charge to pay. Call add_transaction first.'),
                        'data' => [],
                    ]);
                }
                $pending_id = (string) $pending_additional[0]['id'];
                $price = (float) $pending_additional[0]['amount'];
            } else {
                $price = $order_detail['data'][0]['final_total'] ?? 0;
            }

            if (!is_numeric($price) || (float) $price <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $amount_minor = StripeMoney::toMinorUnits($price, $currency);

            // =========================
            // Metadata
            // =========================
            $metadata = [
                'order_id' => (string) $order_id,
                'user_id' => (string) ($this->user_details['id'] ?? 0),
            ];

            if ($is_additional_charge) {
                $metadata['additional_charges_transaction_id'] = (string) $transaction_id;
            }

            $company_title = getTranslatedSetting('general_settings', 'company_title');

            $description = $is_additional_charge
                ? 'Payment for additional charges - Order #' . $order_id . ' on ' . $company_title
                : 'Payment for Order #' . $order_id . ' on ' . $company_title;

            // =========================
            // Create Stripe Intent
            // =========================
            $intent = $this->stripe->create_payment_intent([
                'amount' => $amount_minor,
                'metadata' => $metadata,
                'description' => $description,
            ]);

            if (isset($intent['error']) || empty($intent['client_secret'])) {
                $message = $intent['error']['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');

                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $intent,
                ]);
            }

            // Persist intent: pending transactions row carrying the Stripe PaymentIntent id
            // as `reference`. Webhook becomes pure UPDATE keyed by (type=stripe, reference)
            // — no insert race possible.
            $intent_id = (string) ($intent['id'] ?? '');
            $user_id = (int) ($this->user_details['id'] ?? 0);
            $partner_id = (int) ($order_detail['data'][0]['partner_id'] ?? 0);
            $base_currency = (string) ($order_detail['data'][0]['currency_code'] ?? '');

            if ($intent_id !== '') {
                if ($is_additional_charge) {
                    if (!empty($pending_id)) {
                        update_details(
                            ['reference' => $intent_id, 'type' => 'stripe'],
                            ['id' => $pending_id],
                            'transactions'
                        );
                    }
                } else {
                    // Reuse pre-existing pending stripe row for this order/user when present
                    // (covers client retries hitting create_payment_intent more than once).
                    $existing_pending = fetch_details(
                        'transactions',
                        [
                            'order_id' => $order_id,
                            'user_id' => $user_id,
                            'transaction_type' => 'transaction',
                            'type' => 'stripe',
                            'status' => 'pending',
                        ],
                        ['id'],
                        1,
                        0,
                        'id',
                        'DESC'
                    );

                    if (!empty($existing_pending[0]['id'])) {
                        update_details(
                            ['reference' => $intent_id, 'amount' => $price, 'currency_code' => $base_currency ?: strtoupper((string) $currency)],
                            ['id' => $existing_pending[0]['id']],
                            'transactions'
                        );
                    } else {
                        add_transaction([
                            'transaction_type' => 'transaction',
                            'user_id' => $user_id,
                            'partner_id' => $partner_id,
                            'order_id' => $order_id,
                            'type' => 'stripe',
                            'txn_id' => '',
                            'reference' => $intent_id,
                            'amount' => $price,
                            'status' => 'pending',
                            'currency_code' => $base_currency ?: strtoupper((string) $currency),
                            'message' => 'txn_order_placed',
                        ]);
                    }
                }
            }

            // Billing details for client-side SDK prefill.
            $billing_details = [
                'name' => $this->user_details['username'] ?? '',
                'email' => $this->user_details['email'] ?? '',
                'phone' => $this->user_details['phone'] ?? '',
            ];

            // Include address from the order (rebuilt from custom fields).
            $order_address = $order_detail['data'][0]['address'] ?? '';
            if (!empty($order_address)) {
                $billing_details['address'] = [
                    'line1' => $order_address,
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Stripe payment intent created'),
                'data' => $intent,
                'billing_details' => $billing_details,
            ]);
        } catch (\Throwable $th) {

            log_the_responce(
                $this->request->header('Authorization') .
                ' Params :: ' . json_encode($_POST) .
                ' Issue => ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - create_stripe_payment_intent()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Get Stripe PaymentIntent status/details.
     *
     * This is an SDK + library based alternative to:
     * POST https://api.stripe.com/v1/payment_intents/{payment_intent_id}
     *
     * Required:
     * - payment_intent_id (POST)
     *
     * Returns:
     * - Full Stripe PaymentIntent payload as returned by the SDK wrapper.
     */
    public function get_stripe_payment_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'payment_intent_id' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ]);
            }

            $payment_intent_id = (string) $this->request->getPost('payment_intent_id');
            $intent = $this->stripe->get_payment_intent($payment_intent_id);

            if (isset($intent['error'])) {
                $message = $intent['error']['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $intent,
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Stripe payment intent fetched'),
                'data' => $intent,
            ]);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_stripe_payment_status()'
            );
            return $this->response->setJSON($response);
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
            $userId = $this->user_details['id'] ?? null;

            $ordersModel = new Orders_model();
            $partnersModel = new Partners_model();
            $usersModel = new Users_model();

            $orderQuery = $ordersModel->where('id', $order_id);
            if (!empty($userId)) {
                $orderQuery->where('user_id', $userId);
            } elseif ($this->isLoggedIn && $this->userIsPartner) {
                $orderQuery->where('partner_id', $this->userId);
            } elseif (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    'data' => []
                ]);
            }
            $orders = $orderQuery->get()->getResultArray();
            if (isset($orders) && empty($orders)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    'data' => []
                ]);
            }
            $orderDetails = $ordersModel->invoice($order_id)['order'];
            $partnerId = $orderDetails['partner_id'];
            $hideCustomerContact = empty($userId) && $this->isLoggedIn && $this->userIsPartner;
            $partnerDetails = $partnersModel->from('partner_details pd')->select('pd.company_name, pd.address, u.email, u.phone, u.image, u.gstin')->join('users u', 'u.id = pd.partner_id')->where('pd.partner_id', $partnerId)->get()->getResultArray();

            // Add translation support for partner details in invoice
            if (!empty($partnerDetails[0])) {
                $translatedData = get_translated_partner_field(partnerId: $partnerId, fieldName: 'company_name', defaultValue: $partnerDetails[0]['company_name']);
                $partnerDetails[0]['translated_company_name'] = $translatedData;

                $partnerDetails[0]['image'] = !empty($partnerDetails[0]['image'])
                    ? service('fileService')->url(basename($partnerDetails[0]['image']), 'profile', 'public/uploads/profiles/default.png')
                    : '';
            }

            // Partner-panel requests have no customer API token; use the order owner for invoice details.
            $userDetails = $usersModel->where('id', $orderDetails['user_id'])->get()->getResultArray();

            $settings = get_settings('general_settings', true);

            $this->data['currency'] = $settings['currency'];
            $this->data['order'] = $orderDetails;
            $this->data['partner_details'] = $partnerDetails[0];
            $this->data['user_details'] = $userDetails[0];
            $this->data['hide_customer_contact'] = $hideCustomerContact;
            $this->data['data'] = $settings;

            $currency = $settings['currency'];
            $services = $orderDetails['services'];
            $total = count($services);

            if (!empty($orderDetails)) {
                $i = 0;
                // Use stored values from order_services: tax_amount stored per unit, no recalculation
                $sum_net_amount = 0;
                $sum_tax_amount = 0;

                foreach ($services as &$service) {
                    $original_price = (float) ($service['price'] ?? 0);
                    $discount_price = (float) ($service['discount_price'] ?? 0);
                    $qty = (int) ($service['quantity'] ?? 1);
                    $currency_symbol = $currency;

                    // tax_amount is stored per unit; expand it to the invoice line quantity.
                    $stored_tax = (float) ($service['tax_amount'] ?? 0);
                    $line_tax = $stored_tax * $qty;

                    // Unit price (discounted or original); line net = unit price * qty
                    $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                    $line_net = $unitPrice * $qty;

                    $sum_net_amount += $line_net;
                    $sum_tax_amount += $line_tax;

                    $rows[$i] = [
                        'service_title' => ucwords($service['service_title']),
                        'price' => $currency_symbol . number_format($original_price, 2, '.', ''),
                        'discount' => ($discount_price == 0) ? $currency_symbol . "0.00" : $currency_symbol . number_format(($original_price - $discount_price), 2, '.', ''),
                        'net_amount' => $currency_symbol . number_format($unitPrice, 2, '.', ''),
                        'tax' => ($service['tax_percentage'] ?? '') . '%',
                        'tax_amount' => $currency_symbol . number_format($line_tax, 2, '.', ''),
                        'subtotal' => $currency_symbol . number_format($service['sub_total'], 2, '.', '')
                    ];
                    $i++;
                }
                $additional_charges = !empty($orderDetails['additional_charges'])
                    ? json_decode($orderDetails['additional_charges'], true)
                    : [];

                $array['total'] = $total;
                $array['rows'] = $rows;

                // Keep every summary value based on the same line values shown above.
                $this->data['order']['total'] = number_format($sum_net_amount, 2, '.', '');
                $this->data['order']['invoice_no'] = $this->generateInvoiceNumber($orderDetails['id'], $orderDetails['created_at'] ?? null);
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
                if (is_array($additional_charges)) {
                    foreach ($additional_charges as $additional_charge) {
                        $additional_tax += (float) ($additional_charge['tax_amount'] ?? 0);
                    }
                }
                if ($additional_total > 0 && $additional_tax == 0 && !empty($orderDetails['final_total'])) {
                    $additional_tax = max(0, (float) $orderDetails['final_total'] - $sum_net_amount - $sum_tax_amount - $visiting_charges - $additional_total + (float) ($orderDetails['promo_discount'] ?? 0));
                }
                $sum_tax_amount += $additional_tax;
                $sub_total_incl_tax = $sum_net_amount + $sum_tax_amount;
                $this->data['order']['tax'] = number_format($sum_tax_amount, 2, '.', '');
                if ($additional_total > 0 && empty($additional_charges)) {
                    $additional_charges = [['name' => 'Additional charge', 'charge' => $additional_total, 'tax_amount' => $additional_tax]];
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
                    $mpdf->Output('order-ID-' . $orderDetails['id'] . "-invoice.pdf", 'D');
                } catch (\Mpdf\MpdfException $e) {
                    print "Creating an mPDF object failed";
                    log_message('error', 'Creating an mPDF object failed with: ' . $e->getMessage());
                }
            } else {
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - invoice_download()');
            return $this->response->setJSON($response);
        }
    }


    
    function generateInvoiceNumber($orderId, $orderDate = null) {
        // Use order creation date if available, otherwise current date
        $time = $orderDate ? strtotime($orderDate) : time();
        
        $year  = (int)date('Y', $time);
        $month = (int)date('n', $time);

        // Indian Financial Year: April 1 to March 31
        if ($month >= 4) {
            $fyStart = date('y', strtotime("$year-01-01"));
            $fyEnd   = date('y', strtotime(($year + 1) . "-01-01"));
        } else {
            $fyStart = date('y', strtotime(($year - 1) . "-01-01"));
            $fyEnd   = date('y', strtotime("$year-01-01"));
        }

        $fy = "{$fyStart}-{$fyEnd}";
        
        // Pad ID with leading zeros (e.g., 1 -> 001, 25 -> 025)
        $sequence = str_pad($orderId, 3, '0', STR_PAD_LEFT);

        return "ATPL/E-{$sequence}/{$fy}";
    }



    public function get_paypal_link()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'user_id' => 'required|numeric',
                    'order_id' => 'required',
                    'amount' => 'required',
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
            $order_id = (int) $_POST['order_id'];
            $paypal_link = $this->buildPaypalLink($order_id);
            if ($paypal_link === '') {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('error_occured', 'An error occurred'),
                    'data' => [],
                ]);
            }
            $response = [
                'error' => false,
                'message' => labels(ORDER_DETAIL_FOUNDED, 'Order Detail Founded !'),
                'data' => $paypal_link,
            ];
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_paypal_link()');
            return $this->response->setJSON($response);
        }
    }

    /**
     * Build a PayPal Orders v2 checkout and return the buyer-facing approval URL.
     *
     * Called inline by place_order / get_paypal_link so the client receives the
     * PayPal approval URL directly — no public webview endpoint round-trip. A
     * pending `transactions` row (type=paypal) carries the PayPal order id in
     * `reference` for the return handler and webhook to finalise idempotently.
     *
     * @param int      $order_id                 Local order id.
     * @param int|null $additional_charge_txn_id Pending additional-charge txn row id.
     *
     * @return string Approval URL, or '' on failure.
     */
    private function buildPaypalLink(int $order_id, ?int $additional_charge_txn_id = null, ?string $platform = 'web'): string
    {
        $order = fetch_details('orders', ['id' => $order_id]);
        if (empty($order)) {
            return '';
        }

        $user_id = (int) ($order[0]['user_id'] ?? 0);
        $partner_id = (int) ($order[0]['partner_id'] ?? 0);
        $currency = (string) ($order[0]['currency_code'] ?? '');
        $email = (string) ($this->user_details['email'] ?? '');

        if ($platform === 'web') {
            $paypal_settings = get_settings('payment_gateways_settings', true);
            $website_url = !empty($paypal_settings['paypal_website_url'])
                ? rtrim($paypal_settings['paypal_website_url'], '/')
                : rtrim(base_url(), '/');
            $return_url = $website_url . '/payment-status?order_id=' . $order_id . '&payment_status=pending';
            $cancel_url = $website_url . '/payment-status?order_id=' . $order_id . '&payment_status=cancelled';
        } else {
            $return_url = base_url('api/v1/paypal_return');
            $cancel_url = base_url('api/v1/paypal_return?cancelled=1');
        }

        $params = [
            'currency' => $currency,
            'return_url' => $return_url,
            'cancel_url' => $cancel_url,
        ];

        if ($additional_charge_txn_id) {
            // Additional-charge row is created earlier by TransactionApiController::add_transaction.
            $params['transaction_id'] = $additional_charge_txn_id;
            $params['custom_id'] = $user_id . '|' . $email . '|' . $additional_charge_txn_id;
            $params['item_name'] = 'Additional charge - Order #' . $order_id;
            $params['invoice_prefix'] = 'addl';
        } else {
            $amount = (float) ($order[0]['final_total'] ?? $order[0]['total'] ?? 0);
            $params['amount'] = $amount;
            $params['custom_id'] = $user_id . '|' . $email;
            $params['item_name'] = 'Order #' . $order_id;
            $params['invoice_prefix'] = 'order';

            // Reuse an existing pending paypal row for this order, else create one.
            $existing = fetch_details(
                'transactions',
                [
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'transaction_type' => 'transaction',
                    'type' => 'paypal',
                    'status' => 'pending',
                ],
                ['id'],
                1,
                0,
                'id',
                'DESC'
            );
            if (!empty($existing[0]['id'])) {
                $params['transaction_id'] = (int) $existing[0]['id'];
            } else {
                $params['txn_data'] = [
                    'transaction_type' => 'transaction',
                    'user_id' => $user_id,
                    'partner_id' => $partner_id,
                    'order_id' => $order_id,
                    'type' => 'paypal',
                    'txn_id' => '',
                    'amount' => $amount,
                    'status' => 'pending',
                    'currency_code' => strtoupper($currency),
                    'message' => 'txn_order_placed',
                ];
            }
        }

        $checkout = (new \App\Services\PaypalPaymentService())->createCheckout($params);
        return !empty($checkout['error']) ? '' : $checkout['approval_url'];
    }

    /**
     * PayPal return handler — capture-on-return.
     *
     * PayPal redirects the buyer here with `?token=<order id>` after approval
     * (or with `cancelled=1` from the cancel URL). The order is captured
     * server-side and the payment finalised via PaypalPaymentService.
     */
    public function paypal_return()
    {
        $order_id = 0;
        try {
            $paypal_order_id = (string) ($_GET['token'] ?? '');
            $cancelled = isset($_GET['cancelled']);

            if ($paypal_order_id === '') {
                return $this->redirectToPaymentStatus('failed');
            }

            $transaction = fetch_details('transactions', ['type' => 'paypal', 'reference' => $paypal_order_id]);
            if (empty($transaction)) {
                return $this->redirectToPaymentStatus('failed');
            }
            $transaction = $transaction[0];
            $order_id = (int) ($transaction['order_id'] ?? 0);

            $service = new \App\Services\PaypalPaymentService();

            if ($cancelled) {
                $service->finalizeFailure($transaction);
                return $this->redirectToPaymentStatus('cancelled', $order_id);
            }

            // Idempotency: if the webhook already finalised this payment, just report it.
            if (($transaction['status'] ?? '') === 'success') {
                return $this->redirectToPaymentStatus('success', $order_id);
            }

            $capture_response = $this->paypal_lib->captureOrder($paypal_order_id);
            $capture = \App\Libraries\Paypal::getCaptureDetails($capture_response);

            // If the capture call did not clearly complete (e.g. the order was
            // already captured on a duplicate return hit), confirm the order's
            // real state with PayPal before treating it as a failure.
            if (!empty($capture_response['error']) || $capture['status'] !== 'COMPLETED') {
                $order_state = $this->paypal_lib->getOrder($paypal_order_id);
                if (empty($order_state['error']) && ($order_state['status'] ?? '') === 'COMPLETED') {
                    $capture = \App\Libraries\Paypal::getCaptureDetails($order_state);
                }
            }

            if ($capture['status'] === 'COMPLETED' && $capture['capture_id'] !== '') {
                $service->finalizeSuccess($transaction, $capture);
                return $this->redirectToPaymentStatus('success', $order_id);
            }

            $service->finalizeFailure($transaction, $capture['capture_id']);
            return $this->redirectToPaymentStatus('failed', $order_id);
        } catch (\Throwable $th) {
            log_the_responce(
                'paypal_return Params :: ' . json_encode($_GET) . ' Issue => ' . $th,
                date("Y-m-d H:i:s") . '--> OrdersApiController - paypal_return()'
            );
            return $this->redirectToPaymentStatus('failed', $order_id);
        }
    }

    /**
     * Redirect the PayPal return to the unified status endpoint, carrying the
     * resolved status in the query string. Both the app (which intercepts the
     * URL inside its webview) and the web (which renders the status page) key
     * off `payment_status`.
     *
     * @param string $status   success | failed | cancelled
     * @param int    $order_id Local order id, included for the app's convenience.
     */
    private function redirectToPaymentStatus(string $status, int $order_id = 0)
    {
        return redirect()->to(
            base_url('api/v1/app_payment_status')
            . '?payment_status=' . rawurlencode($status)
            . '&order_id=' . $order_id
        );
    }

    /**
     * Unified PayPal payment-status landing endpoint.
     *
     * `paypal_return` redirects here after capture with `?payment_status=...`
     * (and `order_id`) in the query string. This renders no page — it just
     * echoes the status back as JSON. The app reads `payment_status` straight
     * from the redirect URL it intercepts inside its webview.
     */
    public function app_payment_status()
    {
        $payment_status = strtolower((string) ($_GET['payment_status'] ?? ''));

        $outcomes = [
            'success' => [false, labels(PAYMENT_COMPLETED_SUCCESSFULLY, 'Payment Completed Successfully')],
            'cancelled' => [true, labels(PAYMENT_CANCELLED_DECLINED, 'Payment Cancelled / Declined')],
            'failed' => [true, labels(PAYMENT_CANCELLED_DECLINED, 'Payment Cancelled / Declined')],
        ];
        [$error, $message] = $outcomes[$payment_status]
            ?? [true, labels('error_occured', 'An error occurred')];

        return $this->response->setJSON([
            'error' => $error,
            'message' => $message,
            'payment_status' => $payment_status,
            'order_id' => (int) ($_GET['order_id'] ?? 0),
        ]);
    }

    public function checkAndUpdateSubscriptionStatus($partnerId)
    {
        try {
            $partnerSubscriptionModel = new Partner_subscription_model();
            $subscriptionData = $partnerSubscriptionModel
                ->where('partner_id', $partnerId)
                ->where('status', 'active')
                ->where('order_type', 'limited')
                ->where('price !=', 0)
                ->first();
            if (!$subscriptionData) {
                return;
            }
            // Use the proper counting function that excludes failed payments and cancelled orders
            // This function only counts orders with status 'started' or 'completed'
            // Failed payment orders (status='cancelled' or payment_status=2) are excluded
            $subscriptionCount = count_orders_towards_subscription_limit($partnerId, $subscriptionData['updated_at'], [], null);
            if ($subscriptionCount >= $subscriptionData['max_order_limit']) {
                $data['status'] = 'deactive';
                $where['partner_id'] = $partnerId;
                $where['status'] = 'active';
                update_details($data, $where, 'partner_subscriptions');
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - checkAndUpdateSubscriptionStatus()');
            return $this->response->setJSON($response);
        }
    }

    public function verify_transaction()
    {
        $validation = service('validation');
        $validation->setRules([
            'order_id' => 'required|numeric',
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
        $transaction = fetch_details('transactions', ['order_id' => $order_id, 'user_id' => $this->user_details['id']]);
        $settings = get_settings('payment_gateways_settings', true);
        if (!empty($transaction)) {
            $transaction_id = $transaction[0]['txn_id'];
            $payment_gateways = $transaction[0]['type'];
            if ($payment_gateways == 'razorpay') {
                $razorpay = new Razorpay;
                $credentials = $razorpay->get_credentials();
                $secret = $credentials['secret'];
                $api = new Api($credentials['key'], $secret);
                $data = $api->payment->fetch($transaction_id);
                $status = $data->status;
                if ($status == "captured") {
                    $cart_data = fetch_cart(true, $this->user_details['id']);
                    if (!empty($cart_data)) {
                        foreach ($cart_data['data'] as $row) {
                            delete_details(['id' => $row['id']], 'cart');
                        }
                    }
                    $response = [
                        'error' => true,
                        'message' => labels(VERIFIED, 'verified'),
                        'data' => [],
                    ];
                    return $this->response->setJSON($response);
                }
            }
            if ($payment_gateways == "cashfree") {
                $gateway_order_id = $transaction[0]['reference'] ?? '';
                if (empty($gateway_order_id)) {
                    $response = [
                        'error' => true,
                        'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                        'data' => [],
                    ];
                    return $this->response->setJSON($response);
                }

                $order_response = $this->cashfree->fetch_order($gateway_order_id);
                $payments_response = $this->cashfree->fetch_order_payments($gateway_order_id);

                $response = [
                    'error' => false,
                    'message' => labels(VERIFIED, 'verified'),
                    'data' => [
                        'order' => $order_response,
                        'payments' => $payments_response,
                    ],
                ];
                return $this->response->setJSON($response);
            }
            if ($payment_gateways == "paystack") {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $transaction[0]['reference'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Bearer " . $settings['paystack_secret'],
                        "Cache-Control: no-cache",
                    ),
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                unset($curl);
                $response = [
                    'error' => false,
                    'message' => labels(VERIFIED, 'verified'),
                    'data' => json_decode($response),
                ];
                return $this->response->setJSON($response);
            }
        }
    }

    public function capturePayment()
    {
        try {
            $apiEndpoint = 'https://api-m.sandbox.paypal.com';
            $requestData = json_encode([
                "intent" => "CAPTURE",
                "purchase_units" => [],
                "application_context" => [
                    "return_url" => "https://example.com/return",
                    "cancel_url" => "https://example.com/cancel"
                ]
            ]);
            $options = [
                CURLOPT_URL => $apiEndpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ];
            $ch = curl_init();
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            unset($ch);
            echo $response;
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            return $this->response->setJSON($response);
        }
    }

    public function paystack_transaction_webview($user_id = null, $order_id = null, $amount = null, $partner_id = null, $type = null, $platform = null, $additional_charges_transaction_id = null)
    {
        try {
            if ($user_id == null && isset($_GET['user_id'])) {
                $user_id = $_GET['user_id'];
                $order_id = $_GET['order_id'];
                $amount = intval(str_replace(',', '', $_GET['amount']));
                $platform = 'web';
                if (!empty($_GET['additional_charges_transaction_id'])) {
                    $additional_charges_transaction_id = (int) $_GET['additional_charges_transaction_id'];
                    $type = 'additional_charges';
                }
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
                // Persist intent: pending transactions row carrying the Paystack
                // `reference` so the webhook becomes a pure UPDATE keyed by
                // (type=paystack, reference). Mirrors razorpay/stripe/flutterwave.
                $ps_reference = (string) ($result_data['data']['reference'] ?? '');
                if ($ps_reference !== '') {
                    $order_row = fetch_details('orders', ['id' => $order_id]);
                    $base_currency = (string) ($order_row[0]['currency_code'] ?? '');
                    $partner_for_row = $partner_id !== null
                        ? (int) $partner_id
                        : (int) ($order_row[0]['partner_id'] ?? 0);

                    if ($type === 'additional_charges' && !empty($additional_charges_transaction_id)) {
                        update_details(
                            ['reference' => $ps_reference, 'type' => 'paystack'],
                            ['id' => $additional_charges_transaction_id],
                            'transactions'
                        );
                    } else {
                        $existing_pending = fetch_details(
                            'transactions',
                            [
                                'order_id' => $order_id,
                                'user_id' => $user_id,
                                'transaction_type' => 'transaction',
                                'type' => 'paystack',
                                'status' => 'pending',
                            ],
                            ['id'],
                            1,
                            0,
                            'id',
                            'DESC'
                        );

                        if (!empty($existing_pending[0]['id'])) {
                            update_details(
                                ['reference' => $ps_reference, 'amount' => $amount, 'currency_code' => $base_currency ?: strtoupper((string) $paystack_credentials['currency'])],
                                ['id' => $existing_pending[0]['id']],
                                'transactions'
                            );
                        } else {
                            add_transaction([
                                'transaction_type' => 'transaction',
                                'user_id' => (int) $user_id,
                                'partner_id' => $partner_for_row,
                                'order_id' => (int) $order_id,
                                'type' => 'paystack',
                                'txn_id' => '',
                                'reference' => $ps_reference,
                                'amount' => $amount,
                                'status' => 'pending',
                                'currency_code' => $base_currency ?: strtoupper((string) $paystack_credentials['currency']),
                                'message' => 'txn_order_placed',
                            ]);
                        }
                    }
                } else {
                    log_message('warning', '[PAYSTACK] initialize returned no reference for order=' . $order_id);
                }

                return $result_data['data']['authorization_url'];
            } else {
                log_message('error', 'Error Creating Authorization URL: ' . json_encode($result_data, true));
                return "";
            }
        } catch (\Exception $th) {
            log_the_responce('Paystack Transaction Webview Error: ' . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - paystack_transaction_webview()');
            return "";
        }
    }

    public function app_paystack_payment_status()
    {
        $data = $_GET;
        $response = [];
        $platform = $data['platform'] ?? 'app';
        $general_settings = get_settings('general_settings', true);
        $app_settings = get_settings('app_settings', true);
        $app_scheme = $general_settings['schema_for_deeplink'] ?? 'tasko';

        if (isset($data['reference']) && isset($data['trxref']) && isset($data['payment_status'])) {
            $response['error'] = false;
            $response['message'] = labels(PAYMENT_COMPLETED_SUCCESSFULLY, 'Payment Completed Successfully');
            $response['payment_status'] = "Completed";
            $response['data'] = $data;
        } elseif (isset($data['order_id']) && isset($data['payment_status'])) {
            // Cancel branch: webhook owns failure finalisation. Here we only tag the
            // pending paystack row as failed if one exists (covers users abandoning
            // before the webhook lands). No duplicate INSERT.
            $order_id_decrypted = order_decrypt($_GET['order_id']);
            $cancelled_order_id = $order_id_decrypted[2] ?? null;
            $cancelled_user_id = $order_id_decrypted[0] ?? null;
            if (!empty($cancelled_order_id)) {
                $order_row = fetch_details('orders', ['id' => $cancelled_order_id]);
                $already_cancelled = !empty($order_row)
                    && (((int) ($order_row[0]['payment_status'] ?? 0)) === 2
                        || ($order_row[0]['status'] ?? '') === 'cancelled');

                if (!$already_cancelled) {
                    update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $cancelled_order_id], 'orders');
                }

                $pending = fetch_details(
                    'transactions',
                    [
                        'order_id' => $cancelled_order_id,
                        'user_id' => $cancelled_user_id,
                        'transaction_type' => 'transaction',
                        'type' => 'paystack',
                        'status' => 'pending',
                    ],
                    ['id'],
                    1,
                    0,
                    'id',
                    'DESC'
                );
                if (!empty($pending[0]['id'])) {
                    update_details(
                        ['status' => 'failed', 'message' => 'txn_booking_cancelled'],
                        ['id' => $pending[0]['id']],
                        'transactions'
                    );
                }
            }
            $response['error'] = true;
            $response['message'] = labels(PAYMENT_CANCELLED_DECLINED, 'Payment Cancelled / Declined');
            $response['payment_status'] = "Failed";
            $response['data'] = $_GET;
        } else {
            $response['error'] = true;
            $response['message'] = 'Invalid request';
            $response['payment_status'] = 'Error';
        }

        // Prepare deeplink using the same pattern as deep_link.php
        $deeplink = $app_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

        $view_data = [
            'error' => $response['error'],
            'message' => $response['message'],
            'payment_status' => $response['payment_status'],
            'platform' => $platform,
            'deeplink' => $deeplink,
            'appName' => $app_scheme,
            'customerPlaystoreUrl' => $app_settings['customer_playstore_url'] ?? '',
            'customerAppStoreUrl' => $app_settings['customer_appstore_url'] ?? ''
        ];

        return view('api/payment_status', $view_data);
    }

    public function flutterwave_webview()
    {
        try {
            header("Content-Type: application/json");
            $validation = \Config\Services::validation();
            $validation->setRules([
                'user_id' => 'required|numeric',
                'order_id' => 'required|numeric',
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
            $settings = get_settings('general_settings', true);
            $logo = service('fileService')->url($settings['logo'] ?? '', 'site');
            $user_id = (int) $this->request->getVar('user_id');
            $order_id = (int) $this->request->getVar('order_id');
            $user = fetch_details('users', ['id' => $user_id]);
            if (empty($user)) {
                $response = [
                    'error' => true,
                    'message' => labels(USER_NOT_FOUND, 'User not found!'),
                ];
                return $this->response->setJSON($response);
            }

            // Server-side derive amount. Never trust client-supplied $_GET['amount'].
            $order = fetch_details('orders', ['id' => $order_id, 'user_id' => $user_id]);
            if (empty($order)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ORDER_NOT_FOUND, 'Order not found'),
                ]);
            }

            $additional_charges_transaction_id = isset($_GET['additional_charges_transaction_id'])
                ? (int) $_GET['additional_charges_transaction_id']
                : 0;
            $is_additional_charge = $additional_charges_transaction_id > 0;

            $pending_id = 0;
            $server_amount = 0.0;

            if ($is_additional_charge) {
                $pending_additional = fetch_details(
                    'transactions',
                    [
                        'id' => $additional_charges_transaction_id,
                        'user_id' => $user_id,
                        'order_id' => $order_id,
                        'transaction_type' => 'transaction',
                        'status' => 'pending',
                        'message' => 'txn_additional_charges',
                    ],
                    ['id', 'amount']
                );
                if (empty($pending_additional)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DETAILS_NOT_FOUND, 'No pending additional charge to pay. Call add_transaction first.'),
                    ]);
                }
                $pending_id = (int) $pending_additional[0]['id'];
                $server_amount = (float) $pending_additional[0]['amount'];
            } else {
                $server_amount = (float) (($order[0]['final_total'] ?? 0) > 0 ? $order[0]['final_total'] : ($order[0]['total'] ?? 0));
            }

            if ($server_amount <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                ]);
            }

            $flutterwave = new Flutterwave();
            $flutterwave_credentials = $flutterwave->get_credentials();
            $payment_gateways_settings = get_settings('payment_gateways_settings', true);


            if (!empty($payment_gateways_settings['flutterwave_website_url'])) {
                $return_url = $payment_gateways_settings['flutterwave_website_url'] . "/payment-status?order_id=" . $order_id;
            } else {
                $return_url = base_url('/api/v1/flutterwave_payment_status');
            }
            $currency = $flutterwave_credentials['currency_code'] ?? "NGN";
            $meta_data = [
                'user_id' => $user_id,
                'order_id' => $order_id,
            ];
            if ($is_additional_charge) {
                $meta_data['additional_charges_transaction_id'] = $pending_id;
            }
            $company_title = getTranslatedSetting('general_settings', 'company_title');
            $tx_ref = "eDemand-" . time() . "-" . rand(1000, 9999);
            $data = [
                'tx_ref' => $tx_ref,
                'amount' => $server_amount,
                'currency' => $currency,
                'redirect_url' => $return_url,
                'payment_options' => 'card',
                'meta' => $meta_data,
                'customer' => [
                    'email' => (!empty($user[0]['email'])) ? $user[0]['email'] : $settings['support_email'],
                    'phonenumber' => $user[0]['phone'] ?? '',
                    'name' => $user[0]['username'] ?? '',
                ],
                'customizations' => [
                    'title' => $company_title . " Payments",
                    'description' => "Online payments on " . $company_title,
                    'logo' => (!empty($logo)) ? $logo : "",
                ],
            ];
            $payment = $flutterwave->create_payment($data);
            if (!empty($payment)) {
                $payment = json_decode($payment, true);
                if (isset($payment['status']) && $payment['status'] == 'success' && isset($payment['data']['link'])) {
                    // Persist intent: pending transactions row carrying the Flutterwave tx_ref
                    // as `reference`. Webhook becomes pure UPDATE keyed by (type=flutterwave, reference)
                    // — no insert race possible.
                    $partner_id = (int) ($order[0]['partner_id'] ?? 0);
                    $base_currency = (string) ($order[0]['currency_code'] ?? '');

                    if ($is_additional_charge) {
                        if ($pending_id > 0) {
                            update_details(
                                ['reference' => $tx_ref, 'type' => 'flutterwave'],
                                ['id' => $pending_id],
                                'transactions'
                            );
                        }
                    } else {
                        $existing_pending = fetch_details(
                            'transactions',
                            [
                                'order_id' => $order_id,
                                'user_id' => $user_id,
                                'transaction_type' => 'transaction',
                                'type' => 'flutterwave',
                                'status' => 'pending',
                            ],
                            ['id'],
                            1,
                            0,
                            'id',
                            'DESC'
                        );

                        if (!empty($existing_pending[0]['id'])) {
                            update_details(
                                ['reference' => $tx_ref, 'amount' => $server_amount, 'currency_code' => $base_currency ?: strtoupper((string) $currency)],
                                ['id' => $existing_pending[0]['id']],
                                'transactions'
                            );
                        } else {
                            add_transaction([
                                'transaction_type' => 'transaction',
                                'user_id' => $user_id,
                                'partner_id' => $partner_id,
                                'order_id' => $order_id,
                                'type' => 'flutterwave',
                                'txn_id' => '',
                                'reference' => $tx_ref,
                                'amount' => $server_amount,
                                'status' => 'pending',
                                'currency_code' => $base_currency ?: strtoupper((string) $currency),
                                'message' => 'txn_order_placed',
                            ]);
                        }
                    }

                    $response = [
                        'error' => false,
                        'message' => labels(PAYMENT_LINK_GENERATED_FOLLOW_THE_LINK_TO_MAKE_THE_PAYMENT, 'Payment link generated. Follow the link to make the payment!'),
                        'link' => $payment['data']['link'],
                    ];
                    header('Location: ' . $payment['data']['link']);
                    exit;
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(COULD_NOT_INITIATE_PAYMENT, 'Could not initiate payment. ' . $payment['message']),
                        'link' => "",
                    ];
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(COULD_NOT_INITIATE_PAYMENT_TRY_AGAIN_LATER, 'Could not initiate payment. Try again later!'),
                    'link' => "",
                ];
            }
            print_r(json_encode($response));
        } catch (\Throwable $th) {

            log_message('error', 'Error in Flutterwave Webview: ' . $th->getMessage() . "\n" . $th->getTraceAsString());

            $response = [
                'error' => true,
                'message' => labels(AN_ERROR_OCCURRED_PLEASE_TRY_AGAIN_LATER, 'An error occurred. Please try again later.'),
            ];
            // If you're in development mode, show the exact error message
            if (ENVIRONMENT === 'development') {
                $response['error_message'] = $th->getMessage();
                $response['error_trace'] = $th->getTraceAsString();
            }
            return $this->response->setJSON($response);
        }
    }

    public function flutterwave_payment_status()
    {
        if (isset($_GET['transaction_id']) && !empty($_GET['transaction_id'])) {
            $transaction_id = $_GET['transaction_id'];
            $flutterwave = new Flutterwave();
            $transaction = $flutterwave->verify_transaction($transaction_id);
            if (!empty($transaction)) {
                $transaction = json_decode($transaction, true);
                if ($transaction['status'] == 'error') {
                    $response['error'] = true;
                    $response['message'] = $transaction['message'];
                    $response['amount'] = 0;
                    $response['status'] = "failed";
                    $response['currency'] = "NGN";
                    $response['transaction_id'] = $transaction_id;
                    $response['reference'] = "";
                    print_r(json_encode($response));
                    return false;
                }
                if ($transaction['status'] == 'success' && $transaction['data']['status'] == 'successful') {
                    $response['error'] = false;
                    $response['message'] = labels(PAYMENT_HAS_BEEN_COMPLETED_SUCCESSFULLY, 'Payment has been completed successfully');
                    $response['amount'] = $transaction['data']['amount'];
                    $response['currency'] = $transaction['data']['currency'];
                    $response['status'] = $transaction['data']['status'];
                    $response['transaction_id'] = $transaction['data']['id'];
                    $response['reference'] = $transaction['data']['tx_ref'];
                    print_r(json_encode($response));
                    return false;
                } else if ($transaction['status'] == 'success' && $transaction['data']['status'] != 'successful') {
                    // Display-only. Webhook is the source of truth for DB state.
                    $response['error'] = true;
                    $response['message'] = labels(PAYMENT_IS, "Payment is ") . $transaction['data']['status'];
                    $response['amount'] = $transaction['data']['amount'];
                    $response['currency'] = $transaction['data']['currency'];
                    $response['status'] = $transaction['data']['status'];
                    $response['transaction_id'] = $transaction['data']['id'];
                    $response['reference'] = $transaction['data']['tx_ref'];
                    print_r(json_encode($response));
                    return false;
                }
            } else {
                $response['error'] = true;
                $response['message'] = labels(TRANSACTION_NOT_FOUND, 'Transaction not found');
                print_r(json_encode($response));
            }
        } else {
            $response['error'] = true;
            $response['message'] = labels(INVALID_REQUEST, 'Invalid request!');
            print_r(json_encode($response));
            return false;
        }
    }

    public function xendit_payment_status()
    {
        try {
            $status = $_GET['status'] ?? 'failed';
            $order_id = $_GET['order_id'] ?? '';
            if ($status === 'successful') {
                $response = [
                    'error' => false,
                    'message' => labels(PAYMENT_COMPLETED_SUCCESSFULLY, 'Payment Completed Successfully'),
                    'payment_status' => "Completed",
                    'data' => $_GET
                ];
            } else {
                // Handle failed payment
                if (!empty($order_id)) {
                    update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $order_id], 'orders');
                }

                $response = [
                    'error' => true,
                    'message' => labels(PAYMENT_FAILED_OR_CANCELLED, 'Payment Failed or Cancelled'),
                    'payment_status' => "Failed",
                    'data' => $_GET
                ];
            }

            print_r(json_encode($response));
        } catch (\Exception $th) {
            $response = [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'payment_status' => 'Failed'
            ];
            log_the_responce('Xendit Payment Status Error: ' . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - xendit_payment_status()');
            print_r(json_encode($response));
        }
    }

    // Helper functions
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

                $order_currency = (string) ($order_res[0]['currency_code'] ?? '');
                $stored_currency = $order_currency !== ''
                    ? strtoupper($order_currency)
                    : strtoupper((string) ($payment_gateways_settings['xendit_currency'] ?? 'IDR'));

                if ($type == 'additional_charges') {
                    // Stamp Xendit external_id on the pending additional-charge row created by
                    // add_transaction so the webhook can resolve by (type=xendit, reference).
                    if (!empty($additional_charges_transaction_id)) {
                        update_details(
                            ['reference' => $external_id, 'type' => 'xendit', 'currency_code' => $stored_currency, 'amount' => (float) $amount],
                            ['id' => $additional_charges_transaction_id],
                            'transactions'
                        );
                    }
                } elseif ($type == 'order') {
                    // Persist intent: pending transactions row carrying external_id as `reference`.
                    // Webhook becomes pure UPDATE keyed by (type=xendit, reference) — no insert race.
                    $existing_pending = fetch_details(
                        'transactions',
                        [
                            'order_id' => $order_id,
                            'user_id' => $user_id,
                            'transaction_type' => 'transaction',
                            'type' => 'xendit',
                            'status' => 'pending',
                        ],
                        ['id'],
                        1,
                        0,
                        'id',
                        'DESC'
                    );

                    if (!empty($existing_pending[0]['id'])) {
                        update_details(
                            ['reference' => $external_id, 'amount' => (float) $amount, 'currency_code' => $stored_currency, 'txn_id' => ''],
                            ['id' => $existing_pending[0]['id']],
                            'transactions'
                        );
                    } else {
                        add_transaction([
                            'transaction_type' => 'transaction',
                            'user_id' => $user_id,
                            'partner_id' => $partner_id,
                            'order_id' => $order_id,
                            'type' => 'xendit',
                            'txn_id' => '',
                            'reference' => $external_id,
                            'amount' => (float) $amount,
                            'status' => 'pending',
                            'currency_code' => $stored_currency,
                            'message' => 'txn_order_placed',
                        ]);
                    }
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

    public function get_live_tracking_data()
    {
        try {
            $customerId = (int) $this->user_details['id'];
            $orderId = (int) $this->request->getPost('order_id');

            if ($orderId <= 0) {
                return ApiError(ERROR_OCCURED);
            }

            $ordersModel = model(Orders_model::class);
            $order = $ordersModel->select('id')->where(['id' => $orderId, 'user_id' => $customerId])->first();

            if (empty($order)) {
                return ApiError(DATA_NOT_FOUND);
            }

            $liveTrackingModel = model(LiveTrackingModel::class);
            $trackingData = $liveTrackingModel->where('order_id', $orderId)->first();

            if (empty($trackingData)) {
                return ApiError('live_tracking_not_available');
            }

            return ApiSuccess(DATA_FETCHED_SUCCESSFULLY, $trackingData);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Customer/OrdersApiController.php - get_live_tracking_data()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }
}