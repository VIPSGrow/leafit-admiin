<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;

class CartApiController extends BaseController
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

    public function get_cart()
    {
        try {
            $user_id = $this->user_details['id'];
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 0;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where = [];
            $cart_data = fetch_details('cart', ['user_id' => $user_id]);

            $selected_at_store = $this->request->getPost('at_store');
            $is_checkout = in_array((string) $this->request->getPost('is_checkout'), ['1', 'true'], true)
                || (string) $this->request->getPost('is_checkout_process') === '1';
            $reorder_details = fetch_cart(true, $this->user_details['id'], $search, $limit, $offset, $sort, $order, $where, null, 'yes', $this->request->getPost('order_id'), $selected_at_store, $is_checkout);
            if (empty($cart_data) && empty($reorder_details)) {
                return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'), false);
            } else {
                $cart_details = fetch_cart(true, $this->user_details['id'], $search, $limit, $offset, $sort, $order, $where, [], null, null, $selected_at_store, $is_checkout);

                if (!empty($cart_details)) {

                    foreach ($cart_details['data'] as $key => $row) {
                        $check_service_status = fetch_details('services', ['id' => $row['service_id'], 'approved_by_admin' => 1], ['status']);
                        if (empty($check_service_status) || $check_service_status[0]['status'] == 0) {
                            unset($cart_details['data'][$key]);
                        }
                    }
                    $providerIdForCheck = !empty($cart_details['provider_id']) ? (int) explode(',', $cart_details['provider_id'])[0] : 0;
                    $check_provider_status = !empty($providerIdForCheck) ? fetch_details('partner_details', ['partner_id' => $providerIdForCheck], ['is_approved']) : [];
                    if (!empty($check_provider_status) && $check_provider_status[0]['is_approved'] == 0) {
                        return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'), false);
                    }
                    $is_already_subscribe = !empty($providerIdForCheck) ? fetch_details('partner_subscriptions', ['partner_id' => $providerIdForCheck]) : [];
                    if (isset($is_already_subscribe[0]['status']) && $is_already_subscribe[0]['status'] != "active") {
                        return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'), false);
                    }
                    if (!empty($this->request->getPost('order_id'))) {
                        $reorder_details = fetch_cart(true, $this->user_details['id'], $search, $limit, $offset, $sort, $order, $where, null, 'yes', $this->request->getPost('order_id'), $selected_at_store, $is_checkout);

                        if (!empty($check_provider_status) && $check_provider_status[0]['is_approved'] == 0) {
                            return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'), false);
                        }
                        if (empty($reorder_details)) {
                            $response['error'] = false;
                            $response['message'] = labels(ORDER_NOT_FOUND, 'order not found');
                            return $this->response->setJSON($response);
                        }
                    }
                }

                $data = array();

                // Get company name with proper fallback logic
                // company_name should contain default language data
                // translated_company_name should contain requested language data (from header)
                $baseCompanyName = (!empty($cart_details) && isset($cart_details)) ? ($cart_details['company_name'] ?? '') : '';
                $providerId = (!empty($cart_details) && isset($cart_details)) ? $cart_details['provider_id'] : '';

                // Extract first provider ID if multiple (comma-separated)
                $firstProviderId = !empty($providerId) ? (int)explode(',', $providerId)[0] : 0;

                // Get company name with default language fallback
                $companyName = '';
                if (!empty($firstProviderId) && !empty($baseCompanyName)) {
                    $companyName = get_company_name_with_default_language_fallback($firstProviderId, $baseCompanyName);
                } else {
                    $companyName = $baseCompanyName;
                }

                // Get translated company name with requested language fallback
                $translatedCompanyName = '';
                if (!empty($firstProviderId) && !empty($baseCompanyName)) {
                    $translatedCompanyName = get_translated_company_name_with_fallback($firstProviderId, $baseCompanyName);
                } else {
                    $translatedCompanyName = $baseCompanyName;
                }

                $data['cart_data'] = [
                    "data" => (!empty($cart_details) && isset($cart_details)) ? remove_null_values($cart_details['data']) : "",
                    "provider_id" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['provider_id'] : "",
                    "provider_names" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['provider_names'] : "",
                    "translated_provider_names" => (!empty($cart_details) && isset($cart_details)) ? get_translated_partner_field($cart_details['provider_id'], 'username', $cart_details['provider_names']) : "",
                    "service_ids" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['service_ids'] : "",
                    "qtys" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['qtys'] : "",
                    "visiting_charges" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['visiting_charges'] : "",
                    "advance_booking_days" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['advance_booking_days'] : "",
                    "company_name" => $companyName,
                    "translated_company_name" => $translatedCompanyName,
                    "total_duration" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['total_duration'] : "",
                    "is_pay_later_allowed" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['is_pay_later_allowed'] : "",
                    "total_quantity" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['total_quantity'] : "",
                    "sub_total" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['sub_total'] : "",
                    "tax_value" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['tax_value'] : "",
                    "overall_amount" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['overall_amount'] : "",
                    "total" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['total'] : "",
                    "at_store" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['at_store'] : "0",
                    "at_doorstep" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['at_doorstep'] : "0",
                    "is_online_payment_allowed" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['is_online_payment_allowed'] : "0",
                    "sub_total_without_tax" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['sub_total_without_tax'] : "",
                ];
                if ($this->request->getPost('order_id')) {
                    // Get company name with proper fallback logic for reorder data
                    $reorderBaseCompanyName = (!empty($reorder_details) && isset($reorder_details)) ? ($reorder_details['company_name'] ?? '') : '';
                    $reorderProviderId = (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['provider_id'] : '';

                    // Extract first provider ID if multiple (comma-separated)
                    $reorderFirstProviderId = !empty($reorderProviderId) ? (int)explode(',', $reorderProviderId)[0] : 0;

                    // Get company name with default language fallback
                    $reorderCompanyName = '';
                    if (!empty($reorderFirstProviderId) && !empty($reorderBaseCompanyName)) {
                        $reorderCompanyName = get_company_name_with_default_language_fallback($reorderFirstProviderId, $reorderBaseCompanyName);
                    } else {
                        $reorderCompanyName = $reorderBaseCompanyName;
                    }

                    // Get translated company name with requested language fallback
                    $reorderTranslatedCompanyName = '';
                    if (!empty($reorderFirstProviderId) && !empty($reorderBaseCompanyName)) {
                        $reorderTranslatedCompanyName = get_translated_company_name_with_fallback($reorderFirstProviderId, $reorderBaseCompanyName);
                    } else {
                        $reorderTranslatedCompanyName = $reorderBaseCompanyName;
                    }

                    // print_r($reorder_details);
                    // die;
                    $data['reorder_data'] = [
                        "data" => (!empty($reorder_details) && isset($reorder_details)) ? remove_null_values($reorder_details['data']) : "",
                        "provider_id" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['provider_id'] : "",
                        "provider_names" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['provider_names'] : "",
                        "translated_provider_names" => (!empty($reorder_details) && isset($reorder_details)) ? get_translated_partner_field($reorder_details['provider_id'], 'username', $reorder_details['provider_names']) : "",
                        "service_ids" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['service_ids'] : "",
                        "qtys" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['qtys'] : "",
                        "visiting_charges" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['visiting_charges'] : "",
                        "advance_booking_days" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['advance_booking_days'] : "",
                        "company_name" => $reorderCompanyName,
                        "translated_company_name" => $reorderTranslatedCompanyName,
                        "total_duration" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['total_duration'] : "",
                        "is_pay_later_allowed" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['is_pay_later_allowed'] : "",
                        "total_quantity" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['total_quantity'] : "",
                        "sub_total" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['sub_total'] : "",
                        "tax_value" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['tax_value'] : "",
                        "overall_amount" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['overall_amount'] : "",
                        "total" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['total'] : "",
                        "at_store" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['at_store'] : "0",
                        "at_doorstep" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['at_doorstep'] : "0",
                        "is_online_payment_allowed" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['is_online_payment_allowed'] : "0",
                        "sub_total_without_tax" => (!empty($reorder_details) && isset($reorder_details)) ? $reorder_details['sub_total_without_tax'] : "",
                    ];
                } else {
                    $data['reorder_data'] = (object)[];
                }
                return response_helper(
                    labels(CART_FETCHED_SUCCESSFULLY, 'cart fetched successfully'),
                    false,
                    $data,
                    200,
                );
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_cart()');
            return $this->response->setJSON($response);
        }
    }

    public function manage_cart()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'service_id' => [
                        'rules' => 'required|numeric',
                        'errors' => [
                            'required' => labels(SERVICE_ID_IS_REQUIRED, 'Service ID is required'),
                            'numeric'  => labels(SERVICE_ID_MUST_BE_A_NUMBER, 'Service ID must be a number'),
                        ],
                    ],
                    'qty' => [
                        'rules' => 'required|numeric|greater_than[0]',
                        'errors' => [
                            'required'      => labels(QUANTITY_IS_REQUIRED, 'Quantity is required'),
                            'numeric'       => labels(QUANTITY_MUST_BE_A_NUMBER, 'Quantity must be a number'),
                            'greater_than'  => labels(QUANTITY_MUST_BE_GREATER_THAN_0, 'Quantity must be greater than 0'),
                        ],
                    ],
                    'is_saved_for_later' => [
                        'rules' => 'permit_empty|numeric',
                        'errors' => [
                            'numeric' => labels(SAVED_FOR_LATER_MUST_BE_A_NUMBER, 'Saved for later must be a number'),
                        ],
                    ],
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
            $service = fetch_details('services', ['id' => $this->request->getPost('service_id')], ['max_quantity_allowed']);
            if (empty($service)) {
                return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'));
            }
            if ($service[0]['max_quantity_allowed'] < $this->request->getPost('qty')) {
                return response_helper(labels(MAX_QUANTITY_ALLOWED, 'max quanity allowed ' . $service[0]['max_quantity_allowed']));
            }
            $current_service_id = $this->request->getPost('service_id');
            $get_service_id = fetch_details('services', ['id' => $current_service_id]);
            $has_booked_before = fetch_details('cart', ['user_id' => $this->user_details['id']], ['id', 'service_id']);
            $cart_data = fetch_details('cart', ['service_id' => $this->request->getPost('service_id'), 'user_id' => $this->user_details['id']], ['id', 'is_saved_for_later']);
            if (exists(['service_id' => $this->request->getPost('service_id'), 'user_id' => $this->user_details['id']], 'cart')) {
                if (update_details(
                    [
                        'qty' => $this->request->getPost('qty'),
                        'is_saved_for_later' => ($this->request->getPost('is_saved_for_later') == '') ? $cart_data[0]['is_saved_for_later']
                            : $this->request->getPost('is_saved_for_later'),
                    ],
                    ['service_id' => $this->request->getPost('service_id'), 'user_id' => $this->user_details['id']],
                    'cart'
                )) {
                    $error = false;
                    $message = labels(CART_UPDATED_SUCCESSFULLY, 'cart updated successfully');
                    $user_id = $this->user_details['id'];
                    $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 0;
                    $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
                    $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
                    $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
                    $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
                    $where = [];
                    $cart_data = fetch_details('cart', ['user_id' => $user_id]);
                    if (empty($cart_data)) {
                        return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'));
                    } else {
                        $cartData = get_cart_formatted_data($this->user_details['id'], $search, $limit, $offset, $sort, $order, $where, $message, $error);
                        return $cartData;
                    }
                } else {
                    $error = true;
                    $message = labels(CART_NOT_UPDATED, 'cart not updated');
                    return response_helper($message, $error);
                }
            } else {
                if (sizeof($has_booked_before) > 0) {
                    $current_partner_id = $get_service_id[0]['user_id'];
                    $pervious_service_id = $has_booked_before[0]['service_id'];
                    $pervious_user_id = fetch_details('services', ['id' => $pervious_service_id], ['user_id']);
                    if (empty($pervious_user_id)) {
                        $pervious_user_id = 0;
                    } else {
                        $pervious_user_id = fetch_details('services', ['id' => $pervious_service_id], ['user_id'])[0]['user_id'];
                    }
                    if ($current_partner_id == $pervious_user_id) {
                        if (insert_details(['service_id' => $this->request->getPost('service_id'), 'qty' => $this->request->getPost('qty'), 'is_saved_for_later' => ($this->request->getPost('is_saved_for_later' != '')) ? $this->request->getPost('is_saved_for_later') : 0, 'user_id' => $this->user_details['id']], 'cart')) {
                            $error = false;
                            $message = labels(CART_ADDED_SUCCESSFULLY, 'cart added successfully');
                            $user_id = $this->user_details['id'];
                            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 0;
                            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
                            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
                            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
                            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
                            $where = [];
                            $cart_data = fetch_details('cart', ['user_id' => $user_id]);
                            if (empty($cart_data)) {
                                return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'));
                            } else {
                                $cartData = get_cart_formatted_data($this->user_details['id'], $search, $limit, $offset, $sort, $order, $where, $message, $error);
                                return $cartData;
                            }
                        } else {
                            $error = true;
                            $message = labels(CART_NOT_ADDED, 'cart not added');
                            return response_helper($message, $error);
                        }
                    } else {
                        $user_id = $this->user_details['id'];
                        delete_details(['user_id' => $user_id], 'cart');
                        insert_details(['service_id' => $this->request->getPost('service_id'), 'qty' => $this->request->getPost('qty'), 'is_saved_for_later' => ($this->request->getPost('is_saved_for_later' != '')) ? $this->request->getPost('is_saved_for_later') : 0, 'user_id' => $this->user_details['id']], 'cart');
                        $error = false;
                        $message = labels(CART_ADDED_SUCCESSFULLY, 'cart added successfully');
                        $cartData = get_cart_formatted_data($this->user_details['id'], '', 10, 0, '', '', '', $message, $error);
                        return $cartData;
                    }
                } else {
                    if (insert_details(
                        [
                            'service_id' => $this->request->getPost('service_id'),
                            'qty' => $this->request->getPost('qty'),
                            'is_saved_for_later' => ($this->request->getPost('is_saved_for_later') != '') ? $this->request->getPost('is_saved_for_later') : '0',
                            'user_id' => $this->user_details['id'],
                        ],
                        'cart'
                    )) {
                        $error = false;
                        $message = labels(CART_ADDED_SUCCESSFULLY, 'cart added successfully');
                        $user_id = $this->user_details['id'];
                        $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
                        $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
                        $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
                        $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
                        $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
                        $where = [];
                        $cart_data = fetch_details('cart', ['user_id' => $user_id]);
                        if (empty($cart_data)) {
                            return response_helper(labels(SERVICE_NOT_FOUND, 'service not found'));
                        } else {
                            $cartData = get_cart_formatted_data($this->user_details['id'], $search, $limit, $offset, $sort, $order, $where, $message, $error);
                            return $cartData;
                        }
                    } else {
                        $error = true;
                        $message = labels(CART_NOT_ADDED, 'cart not added');
                        return response_helper($message, $error);
                    }
                }
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - manage_cart()');
            return $this->response->setJSON($response);
        }
    }

    public function remove_from_cart()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'cart_id' => [
                        'rules'  => 'permit_empty',
                        'errors' => [
                            // no actual "failing" rule here, so nothing needed
                        ],
                    ],
                    'service_id' => [
                        'rules'  => 'permit_empty|numeric',
                        'errors' => [
                            'numeric' => labels(SERVICE_ID_MUST_BE_A_NUMBER, 'Service ID must be a number'),
                        ],
                    ],
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
            $db = \Config\Database::connect();
            if (!empty($this->request->getPost('provider_id')) && empty($this->request->getPost('service_id'))) {
                $user_id = $this->user_details['id'];
                $providerid = $this->request->getPost('provider_id');
                $cart = fetch_details('cart', ['user_id' => $user_id]);
                $is_provider = true;
                $error = false;
                $message = '';
                foreach ($cart as $row) {
                    $check_service_provider = fetch_details('services', ['id' => $row['service_id']], ['user_id']);
                    if ($check_service_provider[0]['user_id'] != $providerid) {
                        $is_provider = false;
                        $db = \Config\Database::connect();
                        $builder = $db->table('cart');
                        $builder->delete(['id' => $row['id']]);
                    }
                }
                // If all services are from the specified provider, delete the entire cart
                if ($is_provider) {
                    $db = \Config\Database::connect();
                    $builder = $db->table('cart');
                    $builder->delete(['user_id' => $user_id]); // Assuming 'user_id' is the field for identifying the user's cart
                    $message = labels(CART_DELETED_SUCCESSFULLY, 'Cart deleted successfully!');
                } else {
                    $error = true;
                    $message = labels(SOME_ITEMS_WERE_NOT_FROM_THE_SPECIFIED_PROVIDER_AND_HAVE_BEEN_REMOVED_FROM_THE_CART, 'Some items were not from the specified provider and have been removed from the cart!');
                }
                return response_helper($message, $error);
            } else {
                $cart_id = $this->request->getPost('cart_id');
                $service_id = $this->request->getPost('service_id');
                $user_id = $this->user_details['id'];

                if (!empty($cart_id)) {
                    $where = ['id' => $cart_id, 'user_id' => $user_id];
                } elseif (!empty($service_id)) {
                    $where = ['service_id' => $service_id, 'user_id' => $user_id];
                } else {
                    return response_helper(labels(SERVICE_NOT_EXIST_IN_CART, 'service not exist in cart'));
                }

                if (!exists($where, 'cart')) {
                    return response_helper(labels(SERVICE_NOT_EXIST_IN_CART, 'service not exist in cart'));
                }
                if (delete_details($where, 'cart')) {
                    $error = false;
                    $message = labels(SERVICE_REMOVED_FROM_CART, 'service removed from cart');
                    $user_id = $this->user_details['id'];
                    $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 0;
                    $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
                    $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
                    $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
                    $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
                    $where = [];
                    $cart_data = fetch_details('cart', ['user_id' => $user_id]);
                    if (empty($cart_data)) {
                        return response_helper($message, $error);
                    } else {
                        $cartData = get_cart_formatted_data($this->user_details['id'], $search, $limit, $offset, $sort, $order, $where, $message, $error);
                        return $cartData;
                    }
                } else {
                    $error = true;
                    $message = labels(SERVICE_NOT_REMOVED_FROM_CART, 'service not removed from cart');
                    return response_helper($message, $error);
                }
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - remove_from_cart()');
            return $this->response->setJSON($response);
        }
    }
}
