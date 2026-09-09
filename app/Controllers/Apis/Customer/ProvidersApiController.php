<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Partners_model;
use App\Models\Promo_code_model;

class ProvidersApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected $user_details = [];


    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    /**
     * Get providers/partners with language translation support
     * 
     * This function retrieves provider data and applies translations based on the 
     * Content-Language header. The translation system works as follows:
     * 
     * 1. Reads the Content-Language header from the request
     * 2. Falls back to 'en' if no language is specified
     * 3. Applies translations to translatable fields (company_name, about, long_description)
     * 4. Preserves original values while adding translated versions as additional fields
     * 
     * Translatable fields:
     * - company_name -> translated_company_name
     * - about -> translated_about  
     * - long_description -> translated_long_description
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with translated provider data
     */
    public function get_providers()
    {
        try {
            // Latitude and longitude are now optional
            // If not provided or null (as string), is_Available_at_location will be set to 0
            // If slug or partner_id is passed, data will be returned even if outside max_serviceable_distance

            // Get language code from Content-Language header for translations
            // This allows the API to return translated fields based on the requested language
            $languageCode = get_current_language_from_request();

            $Partners_model = new Partners_model();
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 0;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('sort'))) ? $this->request->getPost('sort') : 'pd.id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $filter = ($this->request->getPost('filter') && !empty($this->request->getPost('filter'))) ? $this->request->getPost('filter') : '';
            $where = $additional_data = [];
            $customer_id = '';
            $city_id = '';
            $token = verify_app_request();
            $settings = get_settings('general_settings', true);
            if (empty($settings)) {
                $response = [
                    'error' => true,
                    'message' => labels(FINISH_THE_GENERAL_SETTINGS_IN_PANEL, 'Finish the general settings in panel'),
                ];
                return $this->response->setJSON($response);
            }

            // Helper function to check if latitude/longitude are valid
            // Returns true if both are provided and not null/empty/"null" string
            $isValidLocation = function ($lat, $lng) {
                return !empty($lat) && $lat !== 'null' && $lat !== null &&
                    !empty($lng) && $lng !== 'null' && $lng !== null;
            };

            // Get latitude and longitude from request
            $latitude = $this->request->getPost('latitude');
            $longitude = $this->request->getPost('longitude');
            $hasValidLocation = $isValidLocation($latitude, $longitude);

            if ($token['error'] == 0) {
                $customer_id = $token['data']['id'];
                $additional_data = [
                    'customer_id' => $customer_id,
                ];
                $settings = get_settings('general_settings', true);
                if (empty($settings)) {
                    $response = [
                        'error' => true,
                        'message' => labels(FINISH_THE_GENERAL_SETTINGS_IN_PANEL, 'Finish the general settings in panel'),
                    ];
                    return $this->response->setJSON($response);
                }
                // Only require max_serviceable_distance if location is provided
                // When slug or partner_id is passed, we'll still return data even without distance check
                if ($hasValidLocation && empty($settings['max_serviceable_distance'])) {
                    $response = [
                        'error' => true,
                        'message' => labels(FIRST_SET_MAX_SERVICEABLE_DISTANCE_IN_PANEL, 'First set Max serviceable distance in panel'),
                    ];
                    return $this->response->setJSON($response);
                }
                // Only add location data if valid
                if ($hasValidLocation) {
                    $additional_data = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'max_serviceable_distance' => $settings['max_serviceable_distance'],
                    ];
                    if (isset($customer_id)) {
                        // Merge customer_id into $additional_data correctly
                        $additional_data['customer_id'] = $customer_id;
                    }
                }
            }
            $settings = get_settings('general_settings', true);

            // Only add location data if valid and not already set above
            if ($hasValidLocation && empty($additional_data['latitude'])) {
                if (empty($settings)) {
                    $response = [
                        'error' => true,
                        'message' => labels(FINISH_THE_GENERAL_SETTINGS_IN_PANEL, 'Finish the general settings in panel'),
                    ];
                    return $this->response->setJSON($response);
                }
                if (empty($settings['max_serviceable_distance'])) {
                    $response = [
                        'error' => true,
                        'message' => labels(FIRST_SET_MAX_SERVICEABLE_DISTANCE_IN_PANEL, 'First set Max serviceable distance in panel'),
                    ];
                    return $this->response->setJSON($response);
                }
                $additional_data = [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'max_serviceable_distance' => $settings['max_serviceable_distance'],
                ];
                if (isset($customer_id)) {
                    // Merge customer_id into $additional_data correctly
                    $additional_data['customer_id'] = $customer_id;
                }
            }

            // Check if slug or partner_id is passed
            // When these are passed, data should be returned even if outside max_serviceable_distance
            if ($this->request->getPost('partner_id') && !empty($this->request->getPost('partner_id'))) {
                $where['pd.partner_id'] = $this->request->getPost('partner_id');
                $where_condition_for_max_order_limit = '';
                $where['ps.status'] = 'active';
            }
            if ($this->request->getPost('slug') && !empty($this->request->getPost('slug'))) {
                $where['pd.slug'] = $this->request->getPost('slug');
                $where['ps.status'] = 'active';
            }


            $where['ps.status'] = 'active';
            $where['pd.is_approved'] = "1";

            if ($this->request->getPost('category_slug') && !empty($this->request->getPost('category_slug'))) {
                // Fetch category details by slug
                // This handles category lookup by slug instead of ID
                $category_details = fetch_details(
                    table: 'categories',
                    where: ['slug' => $this->request->getPost('category_slug')]
                );

                if (!empty($category_details)) {
                    // Build category ID array including the category and all descendants (n-levels deep)
                    // This ensures we get providers from the main category and all nested subcategories
                    $category_id = getAllDescendantCategoryIds([$category_details[0]['id']]);
                    // Convert category IDs to string format for get_partner_ids function
                    $formatted_ids = array_map('strval', $category_id);

                    // Get partner IDs using the complete category ID array
                    $partner_ids = get_partner_ids('category', 'category_id', $formatted_ids, true);
                    $where['ps.status'] = 'active';
                    if (!empty($partner_ids)) {
                        $partner_ids = array_unique($partner_ids);
                        if ($filter == 'ratings') {
                            $data = $Partners_model->list(true, $search, $limit, $offset, 'pd.ratings', 'desc', $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                        } else if ($filter == 'discount') {
                            $data = $Partners_model->list(true, $search, $limit, $offset, 'maximum_discount_up_to', 'desc', $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                        } else if ($filter == 'popularity') {
                            $data = $Partners_model->list(true, $search, $limit, $offset, 'number_of_orders', 'desc', $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                        } else {
                            $data = $Partners_model->list(true, $search, $limit, $offset, $sort, $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                        }
                    } else {
                        $data = [];
                    }
                } else {
                    return response_helper(labels(CATEGORY_NOT_FOUND, 'Category not found'), true);
                }
            } else if ($this->request->getPost('category_id') && !empty($this->request->getPost('category_id'))) {
                // Build category ID array including the category and all descendants (n-levels deep)
                // This ensures we get providers from the main category and all nested subcategories
                $category_id = getAllDescendantCategoryIds([$this->request->getPost('category_id')]);
                // Convert category IDs to string format for get_partner_ids function
                $formatted_ids = array_map('strval', $category_id);

                // Get partner IDs using the complete category ID array
                $partner_ids = get_partner_ids('category', 'category_id', $formatted_ids, true);
                $where['ps.status'] = 'active';
                $data = (!empty($partner_ids)) ? $Partners_model->list(true, $search, $limit, $offset, $sort, $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode) : [];
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'ratings')) {
                    $where['ps.status'] = 'active';
                    $data = $Partners_model->list(true, $search, $limit, $offset, ' pd.ratings', 'desc', $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'discount')) {
                    $where['ps.status'] = 'active';
                    $data = $Partners_model->list(true, $search, $limit, $offset, ' maximum_discount_up_to', 'desc', $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'popularity')) {
                    $where['ps.status'] = 'active';
                    $data = $Partners_model->list(true, $search, $limit, $offset, ' number_of_orders', 'desc', $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                $where_condition_for_max_order_limit = '';
            } else if ($this->request->getPost('service_id') && !empty($this->request->getPost('service_id'))) {
                $where['ps.status'] = 'active';
                $service_id[] = $this->request->getPost('service_id');
                $partner_ids = get_partner_ids('service', 'id', $service_id, true);
                $data = (!empty($partner_ids)) ? $Partners_model->list(true, $search, $limit, $offset, $sort, $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode) :
                    [];
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'ratings')) {
                    $data = $Partners_model->list(true, $search, $limit, $offset, ' pd.ratings', $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'discount')) {
                    $data = $Partners_model->list(true, $search, $limit, $offset, ' maximum_discount_up_to', $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'popularity')) {
                    $data = $Partners_model->list(true, $search, $limit, $offset, ' number_of_orders', $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                $where_condition_for_max_order_limit = '';
                $where['ps.status'] = 'active';
            } else if ($this->request->getPost('sub_category_id') && !empty($this->request->getPost('sub_category_id'))) {
                $where['ps.status'] = 'active';
                // Build category ID array including the subcategory and all descendants (n-levels deep)
                // This ensures consistent behavior with category_slug and category_id parameters
                $sub_category_id = getAllDescendantCategoryIds([$this->request->getPost('sub_category_id')]);
                // Convert category IDs to string format for get_partner_ids function
                $formatted_ids = array_map('strval', $sub_category_id);

                // Get partner IDs using the complete category ID array
                $partner_ids = get_partner_ids('category', 'category_id', $formatted_ids, true);
                $data = (!empty($partner_ids)) ? $Partners_model->list(true, $search, $limit, $offset, $sort, $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode) : [];
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'ratings')) {
                    $data = $Partners_model->list(true, $search, $limit, $offset, 'pd.ratings', $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'discount')) {
                    $data = $Partners_model->list(true, $search, $limit, $offset, 'maximum_discount_up_to', $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                if ((!empty($partner_ids)) && ($filter != '' && $filter == 'popularity')) {
                    $data = $Partners_model->list(true, $search, $limit, $offset, 'number_of_orders', $order, $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                }
                $where_condition_for_max_order_limit = '';
                $where['ps.status'] = 'active';
            } elseif ($filter != '' && $filter == 'popularity') {
                $where['ps.status'] = 'active';
                $data = $Partners_model->list(true, $search, $limit, $offset, 'number_of_orders', 'desc', $where, 'partner_id', [], $additional_data, 'yes', $languageCode);
            } elseif ($filter != '' && $filter == 'ratings') {
                $where['ps.status'] = 'active';
                $data = $Partners_model->list(true, $search, $limit, $offset, ' pd.ratings', 'desc', $where, 'pd.partner_id', [], $additional_data, 'yes', $languageCode);
            } elseif ($filter != '' && $filter == 'discount') {
                $data = $Partners_model->list(true, $search, $limit, $offset, 'maximum_discount_up_to', 'desc', $where, 'pd.partner_id', [], $additional_data, 'yes', $languageCode);
            } else {
                // Only add location data if valid
                // If location is invalid, additional_data will remain empty or only contain customer_id
                if ($hasValidLocation) {
                    $additional_data = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'max_serviceable_distance' => $settings['max_serviceable_distance'],
                    ];
                }
                $where_condition_for_max_order_limit = '';
                $where['ps.status'] = 'active';
                if (isset($customer_id)) {
                    $additional_data['customer_id'] = $customer_id;
                }
                $data = $Partners_model->list(true, $search, $limit, $offset, $sort, $order, $where, 'pd.id', [], $additional_data, 'yes', $languageCode);
            }
            $where['ps.status'] = 'active';

            if (!empty($data['data'])) {
                foreach ($data['data'] as &$item) {
                    foreach (['national_id', 'passport', 'tax_name', 'tax_number', 'bank_name', 'account_number', 'account_name', 'bank_code', 'swift_code', 'type', 'admin_commission'] as $key) {
                        unset($item[$key]);
                    }

                    // Set is_Available_at_location to "0" (as string) if latitude/longitude were not provided or were null
                    // This ensures that when location data is missing, the field is explicitly set to "0" as a string
                    if (!$hasValidLocation) {
                        $item['is_Available_at_location'] = "0";
                    } else {
                        // Ensure is_Available_at_location is always a string ("0" or "1")
                        $item['is_Available_at_location'] = (string) $item['is_Available_at_location'];
                    }

                    // Translated fields are now handled by the Partners_model
                    // The model preserves original fields and adds translated fields as separate fields
                }
                unset($item);
                if ($this->request->getPost('get_promocode') && $this->request->getPost('get_promocode') == "1") {
                    if (!isset($data['data']) || !is_array($data['data'])) {
                        log_message('error', 'Data array is missing or not an array');
                        return ApiError(labels(DATA_ARRAY_MISSING_OR_NOT_AN_ARRAY, 'Data array is missing or not an array'));
                    }
                    foreach ($data['data'] as $key => $provider) {
                        $partner_id = $provider['partner_id'];
                        $where_for_pc = [
                            'pc.partner_id' => $partner_id,
                            'pc.status' => 1,
                            'pc.start_date <=' => date('Y-m-d'),
                            'pc.end_date >=' => date('Y-m-d')
                        ];
                        $promo_codes_model = new Promo_code_model();
                        $promo_codes = $promo_codes_model->list(true, $search, null, null, '', 'DESC', $where_for_pc);
                        if (is_object($data['data'][$key])) {
                            $data['data'][$key] = (array) $data['data'][$key];
                        }
                        $data['data'][$key]['promocode'] = $promo_codes['data'];
                    }
                }
                $response = response_helper(labels(PARTNERS_FETCHED_SUCCESSFULLY, 'partners fetched successfully'), false, remove_null_values($data['data']), 200, ['total' => $data['total']]);
            } else {
                if ($this->request->getPost('get_promocode') && $this->request->getPost('get_promocode') == "1") {
                    if (!isset($data['data']) || !is_array($data['data'])) {
                        log_message('error', 'Data array is missing or not an array');
                        return ApiError(labels(DATA_ARRAY_MISSING_OR_NOT_AN_ARRAY, 'Data array is missing or not an array'));
                    }
                    foreach ($data['data'] as $key => $provider) {
                        $partner_id = $provider['partner_id'];
                        $where_for_pc = [
                            'pc.partner_id' => $partner_id,
                            'pc.status' => 1,
                            'pc.start_date <=' => date('Y-m-d'),
                            'pc.end_date >=' => date('Y-m-d')
                        ];
                        $promo_codes_model = new Promo_code_model();
                        $promo_codes = $promo_codes_model->list(true, $search, null, null, '', 'DESC', $where_for_pc);
                        if (is_object($data['data'][$key])) {
                            $data['data'][$key] = (array) $data['data'][$key];
                        }
                        $data['data'][$key]['promocode'] = $promo_codes;
                    }
                }
                $response = response_helper(labels(PARTNERS_FETCHED_SUCCESSFULLY, 'partners fetched successfully'), false, remove_null_values(isset($data['data']) ? $data['data'] : array()), 200, ['total' => isset($data['total']) ? $data['total'] : 0]);
            }
            return $response;
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_providers()');
            return $this->response->setJSON($response);
        }
    }

    public function get_providers_on_map()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'latitude' => 'required|numeric|greater_than_equal_to[-90]|less_than_equal_to[90]',
                'longitude' => 'required|numeric|greater_than_equal_to[-180]|less_than_equal_to[180]'
            ], [
                'latitude.required' => labels(LATITUDE_IS_REQUIRED, 'Latitude is required'),
                'latitude.numeric' => labels(LATITUDE_MUST_BE_A_NUMBER, 'Latitude must be a number'),
                'latitude.greater_than_equal_to' => labels(LATITUDE_MUST_BE_GREATER_THAN_OR_EQUAL_TO, 'Latitude must be greater than or equal to -90'),
                'latitude.less_than_equal_to' => labels(LATITUDE_MUST_BE_LESS_THAN_OR_EQUAL_TO, 'Latitude must be less than or equal to 90'),
                'longitude.required' => labels(LONGITUDE_IS_REQUIRED, 'Longitude is required'),
                'longitude.numeric' => labels(LONGITUDE_MUST_BE_A_NUMBER, 'Longitude must be a number'),
                'longitude.greater_than_equal_to' => labels(LONGITUDE_MUST_BE_GREATER_THAN_OR_EQUAL_TO, 'Longitude must be greater than or equal to -180'),
                'longitude.less_than_equal_to' => labels(LONGITUDE_MUST_BE_LESS_THAN_OR_EQUAL_TO, 'Longitude must be less than or equal to 180')
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors()
                ]);
            }

            $fileService = service('fileService');

            $latitude = (float) $this->request->getPost('latitude');
            $longitude = (float) $this->request->getPost('longitude');

            // Get max serviceable distance from settings
            $settings = get_settings('general_settings', true);
            $max_distance = $settings['max_serviceable_distance'];

            $db = \Config\Database::connect();

            // Fetch basic partner details for all approved providers with same conditions as get_providers
            $query = $db->table('partner_details pd')
                ->select('
                pd.id,
                pd.partner_id,
                pd.company_name,
                pd.ratings,
                pd.max_serviceable_distance,
                u.latitude,
                u.longitude,
                pd.slug,
                u.username as provider_name,
                u.image
            ')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id')
                ->join('users_groups ug', 'ug.user_id = pd.partner_id')
                ->join('users u', 'u.id = pd.partner_id')
                ->where('ps.status', 'active')
                ->where('pd.is_approved', 1)
                ->where('ug.group_id', 3) // Add user group filter to match get_providers conditions
                ->groupBy('pd.partner_id')
                ->orderBy('pd.ratings', 'DESC');

            $partners_data = $query->get()->getResultArray();

            // Fetch all service counts in one optimized query with proper filtering
            if (!empty($partners_data)) {
                $partner_ids = array_column($partners_data, 'partner_id');

                // Get partner details to access at_store and at_doorstep values
                $partner_details = $db->table('partner_details')
                    ->select('partner_id, at_store, at_doorstep')
                    ->whereIn('partner_id', $partner_ids)
                    ->get()
                    ->getResultArray();

                // Create lookup for partner details
                $partner_lookup = [];
                foreach ($partner_details as $detail) {
                    $partner_lookup[$detail['partner_id']] = $detail;
                }

                // Count services for each partner with proper filtering (matching get_providers logic)
                // This ensures only services that match the partner's at_store and at_doorstep settings are counted
                $service_lookup = [];
                foreach ($partner_lookup as $partner_id => $partner_detail) {
                    $service_count = $db->table('services')
                        ->where('user_id', $partner_id)
                        ->where('at_store', $partner_detail['at_store'])
                        ->where('at_doorstep', $partner_detail['at_doorstep'])
                        ->where('status', 1)
                        ->where('approved_by_admin', 1)
                        ->countAllResults();

                    $service_lookup[$partner_id] = (string) $service_count;
                }

                // Add dependent fields to each partner and filter by distance and service count
                $filtered_partners = [];
                foreach ($partners_data as &$partner) {
                    // Get translated partner data including company_name, about, and long_description
                    $partnerData = [
                        'company_name' => $partner['company_name'] ?? '',
                        'about' => '',
                        'long_description' => '',
                    ];
                    $translatedData = get_translated_partner_data_for_api($partner['partner_id'], $partnerData);
                    $partner['company_name'] = $translatedData['company_name'];
                    $partner['translated_company_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                    $partner['translated_provider_name'] = get_translated_partner_field($partner['partner_id'], 'username', $partner['provider_name']);
                    $partner['image'] = $fileService->url($partner['image'] ?? '', 'profile', 'public/backend/assets/default.png');
                    $partner['total_services'] = $service_lookup[$partner['partner_id']] ?? 0;
                    $partner['ratings'] = number_format((float) $partner['ratings'], 1);
                    $partner['distance'] = $this->calculateDistance(
                        (float) $partner['latitude'] ?? 0,
                        (float) $partner['longitude'] ?? 0,
                        (float) $latitude,
                        (float) $longitude,
                        2
                    );

                    // Filter providers based on max serviceable distance and service availability
                    // Only include providers within the serviceable distance AND with at least one service
                    // Also ensure the partner has at least one service delivery option enabled
                    $partner_detail = $partner_lookup[$partner['partner_id']] ?? null;
                    $has_service_option = $partner_detail && ($partner_detail['at_store'] == 1 || $partner_detail['at_doorstep'] == 1);
                    $effective_max_distance = resolve_max_serviceable_distance($settings, $partner['max_serviceable_distance'] ?? null);

                    if ($partner['distance'] <= $effective_max_distance && $partner['total_services'] > 0 && $has_service_option) {
                        $filtered_partners[] = $partner;
                    }
                }

                // Use filtered partners instead of original data
                $partners_data = $filtered_partners;
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(PROVIDERS_FETCHED_SUCCESSFULLY, 'Providers fetched successfully'),
                'data' => $partners_data
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_providers_on_map()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')
            ]);
        }
    }

    /**
     * Calculate distance between two points using Haversine formula, matching ST_Distance_Sphere.
     *
     * @param float $lat1 Latitude of the first point (user)
     * @param float $lon1 Longitude of the first point (user)
     * @param float $lat2 Latitude of the second point (partner)
     * @param float $lon2 Longitude of the second point (partner)
     * @param int $decimals Number of decimal places for the result
     * @return float|null Distance in kilometers, or null if invalid coords
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2, $decimals = 2): string
    {
        // Validate coordinates
        if (
            !is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2) ||
            $lat1 < -90 || $lat1 > 90 || $lon1 < -180 || $lon1 > 180 ||
            $lat2 < -90 || $lat2 > 90 || $lon2 < -180 || $lon2 > 180
        ) {
            return null; // Invalid coords, skip calc
        }

        if ($lat1 == 0 || $lon1 == 0 || $lat2 == 0 || $lon2 == 0) {
            return "0.00";
        }

        // Earth's mean radius in meters (matches ST_Distance_Sphere)
        $earthRadius = 6371000;

        // Convert degrees to radians
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        // Haversine formula
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1) * cos($lat2) * sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        // Convert to kilometers and round
        return (string) ($distance / 1000);
    }

}
