<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use DateTime;

class ProviderAvailabilityApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/provider_check_availability",
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

    public function get_available_slots()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'partner_id' => [
                    'rules'  => 'required|numeric',
                    'errors' => [
                        'required' => labels(PARTNER_ID_IS_REQUIRED, 'Partner ID is required'),
                        'numeric'  => labels(PARTNER_ID_MUST_BE_A_NUMBER, 'Partner ID must be a number'),
                    ],
                ],
                'date' => [
                    'rules'  => 'required|valid_date[Y-m-d]',
                    'errors' => [
                        'required'   => labels(DATE_IS_REQUIRED, 'Date is required'),
                        'valid_date' => labels(DATE_MUST_BE_IN_THE_FORMAT_YYYY_MM_DD, 'Date must be in the format YYYY-MM-DD'),
                    ],
                ],
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => is_array($errors) ? (string) reset($errors) : (string) $errors,
                ]);
            }

            $partner_id = (int) $this->request->getPost('partner_id');
            $date       = (new DateTime((string) $this->request->getPost('date')))->format('Y-m-d');
            $user_id    = (int) ($this->user_details['id'] ?? 0);

            // Resolve the service duration the slot engine should fit:
            //   1. order_id  → sum of services attached to the order
            //   2. custom_job_request_id → matching partner bid's duration
            //   3. otherwise → current cart total_duration
            $total_duration = 0;
            if ($this->request->getPost('order_id')) {
                $order = fetch_details('order_services', ['order_id' => $this->request->getPost('order_id')]);
                foreach ($order as $row) {
                    $service_data = fetch_details('services', ['id' => $row['service_id']]);
                    if (!empty($service_data[0]['duration'])) {
                        $total_duration += (int) $service_data[0]['duration'];
                    }
                }
            } elseif ($this->request->getPost('custom_job_request_id')) {
                $custom_job_data = fetch_details('partner_bids', [
                    'partner_id'            => $partner_id,
                    'custom_job_request_id' => $this->request->getPost('custom_job_request_id'),
                ]);
                $total_duration = (int) ($custom_job_data[0]['duration'] ?? 0);
            } else {
                $cart_data = fetch_cart(true, $user_id);
                $total_duration = (int) ($cart_data['total_duration'] ?? 0);
            }

            $result = service('slot')->getAvailableSlots($partner_id, $date, $total_duration, $user_id);

            if ($result['error']) {
                return $this->response->setJSON(remove_null_values([
                    'error'   => true,
                    'message' => $result['message'],
                ]));
            }

            return $this->response->setJSON(remove_null_values([
                'error'   => false,
                'message' => $result['message'],
                'data'    => $result['data'],
            ]));
        } catch (\Exception $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> Apis/Customer/ProviderAvailabilityApiController - get_available_slots()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function check_available_slot()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'partner_id' => 'required|numeric',
                'date'       => 'required|valid_date[Y-m-d]',
                'time'       => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => is_array($errors) ? (string) reset($errors) : (string) $errors,
                ]);
            }

            $partner_id = (int) $this->request->getPost('partner_id');
            $date       = (string) $this->request->getPost('date');
            $time       = (string) $this->request->getPost('time');
            $user_id    = (int) ($this->user_details['id'] ?? 0);

            // Resolve service duration the same way as get_available_slots.
            $service_total_duration = 0;
            if ($this->request->getPost('custom_job_request_id')) {
                $custom_job_data = fetch_details('partner_bids', [
                    'partner_id'            => $partner_id,
                    'custom_job_request_id' => $this->request->getPost('custom_job_request_id'),
                ]);
                if (empty($custom_job_data)) {
                    return response_helper(labels(THERE_IS_NO_DATA, 'There is no data'), true);
                }
                $service_total_duration = (int) $custom_job_data[0]['duration'];
            } elseif ($this->request->getPost('order_id')) {
                $order = fetch_details('order_services', ['order_id' => $this->request->getPost('order_id')]);
                foreach ($order as $row) {
                    $service_data = fetch_details('services', ['id' => $row['service_id']]);
                    if (!empty($service_data[0]['duration'])) {
                        $service_total_duration += (int) $service_data[0]['duration'];
                    }
                }
            } else {
                $cart_data = ($this->request->getPost('is_reorder') == 1)
                    ? fetch_cart(true, $user_id, '', 0, 0, 'c.id', 'Desc', [], [], 'yes', $this->request->getPost('order_id'))
                    : fetch_cart(true, $user_id);
                if (empty($cart_data)) {
                    return response_helper(labels(PLEASE_ADD_SOME_SERVICE_IN_CART, 'Please add some service in cart'), true);
                }
                foreach (($cart_data['data'] ?? []) as $main_data) {
                    $service_total_duration += (int) ($main_data['servic_details']['duration'] ?? 0) * (int) ($main_data['qty'] ?? 1);
                }
            }

            $result = service('slot')->validateAndLockSlot($partner_id, $date, $time, $service_total_duration, $user_id);

            if ($result['error']) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $result['message'],
                ]);
            }

            return $this->response->setJSON(remove_null_values([
                'error'   => false,
                'message' => $result['message'],
                'data'    => $result['data'],
            ]));
        } catch (\Exception $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> Apis/Customer/ProviderAvailabilityApiController - check_available_slot()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Explicit lock release endpoint for the slot engine.
     * Called when a customer abandons checkout — frees the held slot
     * immediately instead of waiting for TTL expiry.
     */
    public function release_slot_lock()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'lock_id' => 'required|numeric',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => is_array($errors) ? (string) reset($errors) : (string) $errors,
                ]);
            }

            $lockId = (int) $this->request->getPost('lock_id');
            $userId = (int) ($this->user_details['id'] ?? 0);
            $result = service('slot')->releaseLock($lockId, $userId);

            return $this->response->setJSON($result);
        } catch (\Exception $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> Apis/Customer/ProviderAvailabilityApiController - release_slot_lock()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function provider_check_availability()
    {
        try {
            $db = \Config\Database::connect();
            $customer_latitude = $this->request->getPost('latitude');
            $customer_longitude = $this->request->getPost('longitude');
            $settings = get_settings('general_settings', true);
            $general_settings = fetch_details('settings', ['variable' => 'general_settings']);
            $fileService = service('fileService');
            $builder = $db->table('users u');
            $sql_distance = $having = '';
            $distance = $settings['max_serviceable_distance'];
            if ($this->request->getPost('is_checkout_process') == '1') {
                $limit = $this->request->getPost('limit') ?: 10;
                $offset = $this->request->getPost('offset') ?: 0;
                $sort = $this->request->getPost('sort') ?: 'id';
                $order = $this->request->getPost('order') ?: 'ASC';
                $search = $this->request->getPost('search') ?: '';
                $where = [];
                if (!empty($this->request->getPost('order_id'))) {
                    $order_details = fetch_details('orders', ['id' => ($this->request->getPost('order_id')), 'user_id' => $this->user_details['id']]);
                } else {
                    $cart_details = fetch_cart(true, $this->user_details['id'], $search, $limit, $offset, $sort, $order, $where);
                }

                if (!empty($this->request->getPost('order_id'))) {
                    $provider_data = fetch_details('users', ['id' => $order_details[0]['partner_id']]);
                } else if (!empty($this->request->getPost('custom_job_request_id'))) {
                    $provider_data = fetch_details('users', ['id' => $this->request->getPost('bidder_id')]);
                } else {
                    // print_r($cart_details);
                    // exit;
                    $provider_data = fetch_details('users', ['id' => $cart_details['provider_id']]);
                }
                $provider_latitude = !empty($provider_data[0]['latitude']) ? $provider_data[0]['latitude'] : 0;
                $provider_longitude = !empty($provider_data[0]['longitude']) ? $provider_data[0]['longitude'] : 0;
                $provider_id = !empty($provider_data[0]['id']) ? $provider_data[0]['id'] : 0;

                $customer_longitude = (float) $customer_longitude; // Ensure it's a float
                $customer_latitude = (float) $customer_latitude;   // Ensure it's a float
                $partners = $builder->select("
                                            u.username,
                                            u.city,
                                            u.latitude,
                                            u.longitude,
                                            u.id,
                                            p.company_name,
                                            p.max_serviceable_distance,
                                            u.image,
                                            " . get_provider_distance_sql($customer_latitude, $customer_longitude, 'u.id') . " AS distance
                                        ")
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->join('partner_details p', 'p.partner_id = u.id')
                    ->where('p.is_approved', '1')
                    ->where('ug.group_id', '3')
                    ->where('u.id', $provider_id)
                    ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'p.max_serviceable_distance', $distance))
                    ->orderBy('distance')
                    ->get()
                    ->getResultArray();

                foreach ($partners as &$partner) {
                    $partner['image'] = $fileService->url($partner['image'] ?? '', 'profile', 'public/uploads/profiles/default.png');
                }
                if (!empty($partners)) {
                    $response = [
                        'error' => false,
                        'message' => labels(PROVIDER_IS_AVAILABLE, "Provider is available"),
                        "data" => $partners
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(PROVIDER_IS_NOT_AVAILABLE, "Provider is not available"),
                    ];
                }
            } else {
                // Build the SELECT statement as a string to avoid Query Builder parsing issues with complex subqueries
                // This ensures the subquery is properly formatted and not mangled by CodeIgniter's Query Builder
                $selectString = "u.username, u.city, u.latitude, u.longitude, p.company_name, p.max_serviceable_distance, u.image, u.id,
                    " . get_provider_distance_sql($customer_latitude, $customer_longitude, 'u.id') . " as distance,
                    (SELECT COUNT(*) FROM orders o WHERE o.partner_id = u.id AND o.parent_id IS NULL AND o.created_at > ps.purchase_date AND (o.payment_status != 2 OR o.payment_status IS NULL)) as number_of_orders,
                    ps.max_order_limit, ps.order_type";

                $partners = $builder->select($selectString)
                    ->join('users_groups ug', 'ug.user_id=u.id')
                    ->join('partner_subscriptions ps', 'ps.partner_id = u.id', 'left')
                    ->join('partner_details p', 'p.partner_id=u.id')
                    ->where('ps.status', 'active')
                    ->where('ug.group_id', '3')
                    ->having('(number_of_orders < max_order_limit OR number_of_orders = 0 OR order_type = "unlimited")')
                    ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'p.max_serviceable_distance', $distance))
                    ->orderBy('distance')
                    ->get()->getResultArray();
                foreach ($partners as &$partner) {
                    // Add translation support for partner company names
                    if (!empty($partner['id'])) {
                        $partnerData = [
                            'company_name' => $partner['company_name'] ?? '',
                            'about' => '',
                            'long_description' => '',
                            'username' => $partner['username'] ?? ''
                        ];
                        $translatedData = get_translated_partner_data_for_api($partner['id'], $partnerData);
                        $partner['company_name'] = $translatedData['company_name'];
                        $partner['translated_company_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                        $partner['translated_username'] = $translatedData['translated_username'] ?? $translatedData['username'];
                    }

                    $partner['image'] = $fileService->url($partner['image'] ?? '', 'profile', 'public/uploads/profiles/default.png');
                }
                if (!empty($partners)) {
                    $response = [
                        'error' => false,
                        'message' => labels(PROVIDERS_ARE_AVAILABLE, "Providers are available"),
                        "data" => $partners
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(PROVIDERS_ARE_NOT_AVAILABLE, "Providers are not available"),
                    ];
                }
            }
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - provider_check_availability()');
            return $this->response->setJSON($response);
        }
    }
}
