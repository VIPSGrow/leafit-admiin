<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Slider_model;
use App\Models\Category_model;
use App\Models\Orders_model;

class HomeScreenApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();

        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_home_screen_data()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'latitude' => 'required',
                'longitude' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return ApiErrorResponse($errors, false, []);
            }

            // Initialize variables needed for the method
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'ASC';
            $search = $this->request->getPost('search') ?: '';

            // Get location and distance settings for provider availability check
            // If no providers exist at the location, return empty sections (same logic as sliders)
            $latitude = $this->request->getPost('latitude');
            $longitude = $this->request->getPost('longitude');
            $settings = get_settings('general_settings', true);
            $max_distance = isset($settings['max_serviceable_distance']) ? $settings['max_serviceable_distance'] : null;
            $db = \Config\Database::connect();

            // Check if there are ANY providers available at this location
            // If no providers exist at all within max serviceable distance, return empty sections
            // This ensures users only see sections when providers are available at their location
            if (!empty($latitude) && !empty($longitude) && is_numeric($latitude) && is_numeric($longitude) && !empty($max_distance) && is_numeric($max_distance)) {
                $latitude = (float) $latitude;
                $longitude = (float) $longitude;
                $max_distance = (float) $max_distance;

                // Check if any providers exist at this location
                $has_any_provider_at_location = $this->hasAnyProviderAtLocation($db, $latitude, $longitude, $max_distance);

                if (!$has_any_provider_at_location) {
                    // No providers exist at this location, return empty sections
                    $data = [
                        'sections' => [],
                        'sliders' => $this->getSliders($sort, $order, $search),
                        'categories' => $this->getCategoriesList($db, $sort, $order, $search)
                    ];
                    return response_helper(labels(DATA_NOT_FOUND, 'data not found'), false, $data, 200);
                }
            }

            $where = [];
            $builder = $db->table('sections');
            if ($search) {
                $builder->orWhere(['id' => $search, 'title' => $search]);
            }
            if ($this->request->getPost('id')) {
                $where['id'] = $this->request->getPost('id');
            }
            if ($where) {
                $builder->where($where);
            }
            $total = $builder->select('COUNT(id) as total')->get()->getRowArray()['total'];
            $sections = $builder->select()->where('status', 1)->orderBy('rank', $order)->get()->getResultArray();

            // Get all section translations in one query for efficiency
            $sectionIds = array_column($sections, 'id');
            $allTranslations = get_all_section_translations($sectionIds);

            $fileService = service('fileService');
            $rows = [];
            foreach ($sections as $row) {
                $partners = [];
                $type = $row['section_type'];
                $description = $row['description'];
                $limit = $row['limit'] ?: 10;
                $offset = $this->request->getPost('offset') ?: 0;
                switch ($type) {
                    case 'categories':
                        $partners = $this->getCategories($row, $db, $fileService);
                        $type = 'sub_categories';
                        break;
                    case 'previous_order':
                        $partners = $this->getOrders($row, 'completed', $limit, $offset, $sort, $search);
                        $type = 'previous_order';
                        break;
                    case 'ongoing_order':
                        $partners = $this->getOrders($row, 'started', $limit, $offset, $sort, $search);
                        $type = 'ongoing_order';
                        break;
                    case 'top_rated_partner':
                        $partners = $this->getTopRatedPartners($row, $db, $fileService);
                        $type = 'top_rated_partner';
                        break;
                    case 'near_by_provider':
                        $partners = $this->getNearByProviders($row, $db, $fileService);
                        $type = 'near_by_provider';
                        break;
                    case 'banner':
                        $partners = $this->getBanners($row, $db, $fileService, $sort, $order, $limit, $offset);
                        $type = 'banner';
                        break;
                    default:
                        $partners = $this->getDefaultPartners($row, $db, $fileService);
                        $type = 'partners';
                        break;
                }
                $rows[] = $this->formatRow($row, $type, $partners, $description, $allTranslations);
            }
            $data = [
                'sections' => remove_null_values($rows),
                'sliders' => $this->getSliders($sort, $order, $search),
                'categories' => $this->getCategoriesList($db, $sort, $order, $search)
            ];
            $hasData = !empty($rows) || !empty($data['sliders']) || !empty($data['categories']);
            $message = $hasData ? labels(DATA_FETCHED_SUCCESSFULLY, 'data fetched successfully') : labels(DATA_NOT_FOUND, 'data not found');
            $error = !$hasData;

            return response_helper($message, $error, $data, 200);
        } catch (\Exception $th) {
            throw $th;
            log_the_responce($this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_home_screen_data()');
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')]);
        }
    }

    //Private Helper Methods
    /**
     * Check if there are ANY providers available at the given location
     * 
     * This method efficiently checks if at least one provider exists within the max serviceable distance
     * Uses database-level distance calculation for efficiency
     * 
     * @param object $db Database connection object
     * @param float $latitude Customer latitude
     * @param float $longitude Customer longitude
     * @param float $max_distance Maximum serviceable distance in km
     * @return bool True if at least one provider exists at location, false otherwise
     */
    private function hasAnyProviderAtLocation($db, $latitude, $longitude, $max_distance)
    {
        // Query to check if any provider exists within max serviceable distance
        // This uses database-level distance calculation for efficiency
        // Only checks for existence (limit 1) to minimize database load
        // Values are already validated as float, so safe for query
        $settings = get_settings('general_settings', true);
        $provider_count = $db->table('partner_details pd')
            ->select('pd.partner_id, pd.max_serviceable_distance, ' . get_provider_distance_sql($latitude, $longitude, 'u.id') . ' as distance')
            ->join('users u', 'u.id = pd.partner_id', 'left')
            ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
            ->join('users_groups ug', 'ug.user_id = pd.partner_id', 'left')
            ->where('pd.is_approved', 1)
            ->where('ps.status', 'active')
            ->where('ug.group_id', 3)
            ->where('u.latitude IS NOT NULL')
            ->where('u.longitude IS NOT NULL')
            ->where('u.latitude !=', 0)
            ->where('u.longitude !=', 0)
            ->groupBy(['pd.partner_id', 'pd.id'])
            ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $max_distance))
            ->limit(1)
            ->get()
            ->getNumRows();

        // Return true if at least one provider exists at this location
        return $provider_count > 0;
    }

    private function getSliders($sort, $order, $search)
    {
        $slider = new Slider_model();
        $limit = $this->request->getPost('limit') ?: 50;
        $offset = $this->request->getPost('offset') ?: 0;
        $where = [];
        if ($this->request->getPost('id')) {
            $where['id'] = $this->request->getPost('id');
        }
        if ($this->request->getPost('type')) {
            $where['type'] = $this->request->getPost('type');
        }
        if ($this->request->getPost('type_id')) {
            $where['type_id'] = $this->request->getPost('type_id');
        }
        $data = $slider->list(true, $search, $limit, $offset, $sort, $order, $where)['data'];

        // Get location and distance settings for provider availability check
        // This allows filtering providers based on whether they're available at the user's location
        $latitude = $this->request->getPost('latitude');
        $longitude = $this->request->getPost('longitude');
        $settings = get_settings('general_settings', true);
        $max_distance = isset($settings['max_serviceable_distance']) ? $settings['max_serviceable_distance'] : null;
        $db = \Config\Database::connect();

        // If location coordinates are provided, first check if ANY providers exist at this location
        // If no providers exist at all within max serviceable distance, return empty array (no sliders)
        // This ensures users only see sliders when providers are available at their location
        // Validate that latitude and longitude are numeric and not empty
        if (!empty($latitude) && !empty($longitude) && is_numeric($latitude) && is_numeric($longitude) && !empty($max_distance) && is_numeric($max_distance)) {
            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
            $max_distance = (float) $max_distance;

            // Check if there are ANY providers available at this location
            // If no providers exist at all within max serviceable distance, return empty array
            // This ensures users only see sliders when providers are available at their location
            $has_any_provider_at_location = $this->hasAnyProviderAtLocation($db, $latitude, $longitude, $max_distance);

            if (!$has_any_provider_at_location) {
                // No providers exist at this location, return empty array
                return [];
            }
        }

        // Process all sliders normally if all providers are available (or location check not needed)
        // Filter out provider sliders for providers without active subscriptions
        foreach ($data as $index => $row) {
            if ($row['type'] == "provider") {

                // Only include provider sliders if provider has active subscription
                // This ensures only providers with valid subscriptions are shown in sliders
                $hasActiveSubscription = $db->table('partner_subscriptions')
                    ->where('partner_id', $row['type_id'])
                    ->where('status', 'active')
                    ->countAllResults() > 0;

                // If provider doesn't have active subscription, remove this slider
                if (!$hasActiveSubscription) {
                    unset($data[$index]);
                    continue;
                }

                // Fetch provider details for slug and translation
                $provider = fetch_details('partner_details', ['partner_id' => $row['type_id']], ['slug']);
                $data[$index]['provider_slug'] = $provider[0]['slug'] ?? ''; // Handle possible empty result

                // Add translation support for provider sliders
                if (!empty($provider[0])) {
                    $partnerData = [
                        'company_name' => '',
                        'about' => '',
                        'long_description' => ''
                    ];

                    // Validate partner ID before calling translation function
                    if (!empty($row['type_id']) && is_numeric($row['type_id'])) {
                        $translatedData = get_translated_partner_data_for_api((int) $row['type_id'], $partnerData);
                        $data[$index]['translated_company_name'] = $translatedData['translated_company_name'] ?? '';
                    } else {
                        $data[$index]['translated_company_name'] = '';
                    }
                }
                $data[$index]['type'] = "Provider";
                $data[$index]['original_type'] = $row['type'];
            }

            if ($row['type'] == "Category" || $row['type'] == "Sub Category") {
                $category_data = fetch_details('categories', ['id' => $row['type_id']], ['slug', 'parent_id']);
                if (!empty($category_data)) {
                    $data[$index]['category_slug'] = $category_data[0]['slug'] ?? '';
                    // Get all parent category slugs recursively
                    $parent_id = $category_data[0]['parent_id'];
                    $parent_slugs = [];

                    if ($data[$index]['category_parent_id'] != "0") {
                        $data[$index]['type'] = "Sub Category";
                        $data[$index]['original_type'] = "Sub Category";
                    }
                    $this->getParentSlugs($parent_id, $parent_slugs);
                    if (!empty($parent_slugs)) {
                        $data[$index]['parent_category_slugs'] = array_reverse($parent_slugs);
                    }
                    $data[$index]['original_type'] = $row['type'];
                }
            }

            if ($row['type'] == "url") {
                $data[$index]['original_type'] = $row['type'];
            }
        }

        // Re-index array after unsetting elements to ensure proper array structure
        $data = array_values($data);

        return remove_null_values($data);
    }

    private function getCategoriesList($db, $sort, $order, $search)
    {
        $categories = new Category_model();
        $limit = $this->request->getPost('limit') ?: 10;
        $offset = $this->request->getPost('offset') ?: 0;
        $where = ['parent_id' => 0];
        if ($this->request->getPost('id')) {
            $where['id'] = $this->request->getPost('id');
        }
        if ($this->request->getPost('slug')) {
            $where['slug'] = $this->request->getPost('slug');
        }

        // Get language from Content-Language header for API requests
        $languageCode = get_current_language_from_request();

        $category_data = $categories->list(true, $search, null, null, $sort, $order, $where, $languageCode);
        foreach ($category_data['data'] as $index => $category) {
            $category_data['data'][$index]['total_providers'] = $this->getTotalProviders($category['id'], $db);
            if ($category_data['data'][$index]['total_providers'] == 0) {
                unset($category_data['data'][$index]);
            }
        }
        $category_data['data'] = array_values($category_data['data']);

        // Apply translations to categories using the helper function
        $category_data['data'] = apply_translations_to_categories_for_api($category_data['data']);

        return remove_null_values($category_data['data']);
    }

    private function getCategories($row, $db, $fileService)
    {
        $category_ids = explode(',', $row['category_ids']);
        $partners = $db->table('categories c')
            ->select('c.*')
            ->whereIn('c.id', $category_ids)
            ->where('c.status', 1)
            ->get()
            ->getResultArray();
        foreach ($partners as &$partner) {
            $partner['image'] = !empty($partner['image'])
                ? $fileService->url($partner['image'], 'categories')
                : '';
            $partner['discount'] = $partner['upto'] = "";
            $partner['total_providers'] = $this->getTotalProviders($partner['id'], $db);
            $this->unsetFields($partner, ['created_at', 'updated_at', 'deleted_at', 'admin_commission', 'status']);
        }

        // Apply translations to categories using the helper function
        $partners = apply_translations_to_categories_for_api($partners);

        return $partners;
    }

    private function getOrders($row, $status, $limit, $offset, $sort, $search)
    {
        if (empty($this->user_details['id'])) {
            return [];
        }
        $orders = new Orders_model();
        $where = ['o.status' => $status, 'o.user_id' => $this->user_details['id']];
        $order_data = $orders->list(true, $search, $limit, $offset, $sort, "DESC", $where, '', '', '', '', '', false);
        return $order_data['data'] ?? [];
    }

    private function getTopRatedPartners($row, $db, $fileService)
    {
        $settings = get_settings('general_settings', true);
        $latitude = $this->request->getPost('latitude');
        $longitude = $this->request->getPost('longitude');
        $max_distance = $settings['max_serviceable_distance'];
        $limit = $row['limit'] ?: 10;
        $is_latitude_set1 = $latitude ? get_provider_distance_sql($latitude, $longitude, 'p.id', 'p.latitude', 'p.longitude') . " as distance" : "";
        $rating_data = $db->table('partner_details pd')
            ->select('p.id, p.username, p.company, pc.minimum_order_amount, p.image,
                    pd.banner, pc.discount, pc.discount_type, pd.company_name,pd.slug, pd.max_serviceable_distance,
                    ps.status as subscription_status,' . $is_latitude_set1 . ', COUNT(sr.rating) as number_of_rating,
                    SUM(sr.rating) as total_rating,
                    (SUM(sr.rating) / COUNT(sr.rating)) as average_rating')

            ->join('users p', 'p.id=pd.partner_id')
            ->join('partner_subscriptions ps', 'ps.partner_id=pd.partner_id')
            ->join('users_groups ug', 'ug.user_id = p.id')
            ->join('promo_codes pc', 'pc.partner_id=pd.id', 'left')
            // Services ratings
            ->join('services s', 's.user_id=pd.partner_id', 'left')
            ->join('services_ratings sr', 'sr.service_id = s.id', 'left')
            // Custom services ratings
            ->join('partner_bids pb', 'pb.partner_id=pd.partner_id', 'left')
            ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id', 'left')
            ->join('services_ratings sr2', 'sr2.custom_job_request_id = cj.id', 'left')
            ->where('ps.status', 'active')->where('pd.is_approved', '1')
            ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $max_distance))
            ->orderBy('pd.ratings', 'desc')
            ->groupBy(['p.id', 'pd.id'])
            ->limit($limit)
            ->get()->getResultArray();

        $rating_data = $this->filterPartnersBySubscription($rating_data, $db);
        foreach ($rating_data as &$partner) {
            // Skip partners without valid ID to prevent errors
            if (empty($partner['id'])) {
                continue;
            }

            // Get translated partner data including company_name, about, and long_description
            $translatedData = get_translated_partner_data_for_api($partner['id'], $partner);
            // Merge translated data with original partner data to preserve all fields
            $partner = array_merge($partner, $translatedData);

            $partner['image'] = $this->getImagePath($partner['image'] ?? '', 'profile', $fileService);
            $partner['banner_image'] = $this->getImagePath($partner['banner'] ?? '', 'banner', $fileService);
            $partner['total_services'] = $this->getTotalServices($partner['id'], $db);
            $this->unsetFields($partner, ['minimum_order_amount', 'banner']);
            if (!empty($this->user_details['id'])) {
                $is_bookmarked = is_bookmarked($this->user_details['id'], $partner['id'])[0]['total'];
                if (isset($is_bookmarked) && $is_bookmarked == 1) {
                    $partner['is_bookmarked'] = '1';
                } else if (isset($is_bookmarked) && $is_bookmarked == 0) {
                    $partner['is_bookmarked'] = '0';
                } else {
                    $partner['is_bookmarked'] = '0';
                }
                $rating_data_new = $db->table('services_ratings sr')
                    ->select('
                        COUNT(sr.rating) as number_of_rating,
                        SUM(sr.rating) as total_rating,
                        (SUM(sr.rating) / COUNT(sr.rating)) as average_rating
                    ')
                    ->join('services s', 'sr.service_id = s.id', 'left')
                    ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
                    ->join('partner_bids pd', 'pd.custom_job_request_id = cj.id', 'left')
                    ->where("(s.user_id = {$partner['id']}) OR (pd.partner_id = {$partner['id']})")
                    ->get()->getResultArray();
                if (!empty($rating_data_new)) {
                    $partner['ratings'] = (($rating_data_new[0]['average_rating'] != "") ? sprintf('%0.1f', $rating_data_new[0]['average_rating']) : '0.0');
                }
                $rate_data = get_ratings($partner['id']);
                $partner['1_star'] = $rate_data[0]['rating_1'];
                $partner['2_star'] = $rate_data[0]['rating_2'];
                $partner['3_star'] = $rate_data[0]['rating_3'];
                $partner['4_star'] = $rate_data[0]['rating_4'];
                $partner['5_star'] = $rate_data[0]['rating_5'];
            }
        }
        return $rating_data;
    }

    private function getNearByProviders($row, $db, $fileService)
    {
        $settings = get_settings('general_settings', true);
        $latitude = $this->request->getPost('latitude');
        $longitude = $this->request->getPost('longitude');
        $max_distance = $settings['max_serviceable_distance'];
        $limit = $row['limit'] ?: 10;
        $is_latitude_set = $latitude ? get_provider_distance_sql($latitude, $longitude, 'p.id', 'p.latitude', 'p.longitude') . " as distance" : "";
        $rated_provider_limit = !empty($row['limit']) ? $row['limit'] : 10;
        $rating_data = $db->table('partner_details pd')->select('p.id,p.username,p.company,pc.minimum_order_amount,p.image,pd.banner,pc.discount,pc.discount_type,pd.company_name, pd.slug,pd.about,pd.long_description,pd.max_serviceable_distance,
                        ps.status as subscription_status,' . $is_latitude_set . ', COUNT(sr.rating) as number_of_rating,
                    SUM(sr.rating) as total_rating,
                    (SUM(sr.rating) / COUNT(sr.rating)) as average_rating')

            ->join('users p', 'p.id=pd.partner_id')
            ->join('partner_subscriptions ps', 'ps.partner_id=pd.partner_id')
            ->join('users_groups ug', 'ug.user_id = p.id')
            ->join('promo_codes pc', 'pc.partner_id=pd.id', 'left')
            // Services ratings
            ->join('services s', 's.user_id=pd.partner_id', 'left')
            ->join('services_ratings sr', 'sr.service_id = s.id', 'left')

            ->where('ps.status', 'active')->where('pd.is_approved', '1')
            ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $max_distance))
            ->orderBy('pd.ratings', 'desc')
            ->groupBy(['p.id', 'pd.id'])
            ->limit($rated_provider_limit)->get()->getResultArray();

        $rating_data = $this->filterPartnersBySubscription($rating_data, $db);
        foreach ($rating_data as &$partner) {
            // Skip partners without valid ID to prevent errors
            if (empty($partner['id'])) {
                continue;
            }

            // Get translated partner data including company_name, about, and long_description
            $translatedData = get_translated_partner_data_for_api($partner['id'], $partner);
            // Merge translated data with original partner data to preserve all fields
            $partner = array_merge($partner, $translatedData);

            $partner['translated_subscription_status'] = getTranslatedValue($partner['subscription_status'], 'panel');

            $partner['image'] = $this->getImagePath($partner['image'] ?? '', 'profile', $fileService);
            $partner['banner_image'] = $this->getImagePath($partner['banner'] ?? '', 'banner', $fileService);
            $partner['total_services'] = $this->getTotalServices($partner['id'], $db);
            $this->unsetFields($partner, ['minimum_order_amount', 'banner']);
            if (!empty($this->user_details['id'])) {
                $is_bookmarked = is_bookmarked($this->user_details['id'], $partner['id'])[0]['total'];
                if (isset($is_bookmarked) && $is_bookmarked == 1) {
                    $partner['is_bookmarked'] = '1';
                } else if (isset($is_bookmarked) && $is_bookmarked == 0) {
                    $partner['is_bookmarked'] = '0';
                } else {
                    $partner['is_bookmarked'] = '0';
                }
            }
            $rating_data_new = $db->table('services_ratings sr')
                ->select('
                COUNT(sr.rating) as number_of_rating,
                SUM(sr.rating) as total_rating,
                (SUM(sr.rating) / COUNT(sr.rating)) as average_rating
            ')
                ->join('services s', 'sr.service_id = s.id', 'left')
                ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
                ->join('partner_bids pd', 'pd.custom_job_request_id = cj.id', 'left')
                ->where("(s.user_id = {$partner['id']}) OR (pd.partner_id = {$partner['id']})")
                ->get()->getResultArray();
            if (!empty($rating_data_new)) {
                $partner['ratings'] = (($rating_data_new[0]['average_rating'] != "") ? sprintf('%0.1f', $rating_data_new[0]['average_rating']) : '0.0');
            }
            $rate_data = get_ratings($partner['id']);
            $partner['1_star'] = $rate_data[0]['rating_1'];
            $partner['2_star'] = $rate_data[0]['rating_2'];
            $partner['3_star'] = $rate_data[0]['rating_3'];
            $partner['4_star'] = $rate_data[0]['rating_4'];
            $partner['5_star'] = $rate_data[0]['rating_5'];
        }
        return $rating_data;
    }

    private function getBanners($row, $db, $fileService, $sort, $order, $limit, $offset)
    {

        // Handle banner section based on banner_type
        if ($row['banner_type'] == "banner_category") {
            // For category banners, check if category is active
            if (empty($row['category_ids'])) {
                return [];
            }

            $category_ids = explode(',', $row['category_ids']);
            $active_categories = $db->table('categories')
                ->select('id')
                ->whereIn('id', $category_ids)
                ->where('status', 1)
                ->get()
                ->getResultArray();

            // If no active categories found, return empty array
            if (empty($active_categories)) {
                return [];
            }

            // Update category_ids with only active categories
            $active_category_ids = array_column($active_categories, 'id');
            $row['category_ids'] = implode(',', $active_category_ids);
        } else if ($row['banner_type'] == "banner_provider") {
            // For provider banners, check if provider is active and has active subscription
            if (empty($row['partners_ids'])) {
                return [];
            }

            $partner_ids = explode(',', $row['partners_ids']);

            // First get all active partners
            $active_partners = $db->table('users u')
                ->select('u.id')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->whereIn('u.id', $partner_ids)
                ->where('pd.is_approved', '1')
                ->get()
                ->getResultArray();

            // If no active partners found, return empty array
            if (empty($active_partners)) {
                return [];
            }

            // Get partners with active subscriptions
            $active_partner_ids = array_column($active_partners, 'id');
            $partners_with_subscription = [];

            foreach ($active_partner_ids as $partner_id) {
                $partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $partner_id, 'status' => 'active']);
                if (!empty($partner_subscription)) {
                    $partners_with_subscription[] = $partner_id;
                }
            }

            // If no partners with active subscriptions found, return empty array
            if (empty($partners_with_subscription)) {
                return [];
            }

            // Update partners_ids with only active partners who have valid subscriptions
            $row['partners_ids'] = implode(',', $partners_with_subscription);
        }

        // Now retrieve banner data with filtered ids
        $builder = $db->table('sections fs');
        $feature_section_record = $builder
            ->select('fs.*, c.name as category_name, c.slug as category_slug, c.parent_id as category_parent_id, pc.slug as parent_category_slug, pd.company_name as provider_name,pd.slug, pd.slug as provider_slug')
            ->join('categories c', 'c.id = fs.category_ids', 'left')
            ->join('categories pc', 'pc.id = c.parent_id', 'left')
            ->join('partner_details pd', 'pd.partner_id = fs.partners_ids', 'left')
            ->where('fs.id', $row['id'])
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        // Process each record to add image paths
        foreach ($feature_section_record as &$record) {
            // Add translation support for provider banners
            if ($record['banner_type'] == "banner_provider" && !empty($record['partners_ids'])) {
                // Get translated partner data for provider banners
                $partnerData = [
                    'company_name' => $record['provider_name'] ?? '',
                    'about' => '',
                    'long_description' => ''
                ];

                // Handle case where partners_ids might be a comma-separated string
                $partnerId = $record['partners_ids'];
                if (strpos($partnerId, ',') !== false) {
                    // If it's a comma-separated list, take the first partner ID
                    $partnerIds = explode(',', $partnerId);
                    $partnerId = trim($partnerIds[0]); // Use the first partner ID
                }

                // Validate partner ID before calling translation function
                if (!empty($partnerId) && is_numeric($partnerId)) {
                    $translatedData = get_translated_partner_data_for_api((int) $partnerId, $partnerData);
                    $record['provider_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                    $record['translated_provider_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                } else {
                    // Fallback to original data if partner ID is invalid
                    $record['provider_name'] = $partnerData['company_name'];
                    $record['translated_provider_name'] = $partnerData['company_name'];
                }
            }
            $record['app_banner_image'] = !empty($record['app_banner_image'])
                ? $fileService->url($record['app_banner_image'], 'feature_section', 'public/uploads/profiles/default.png')
                : base_url('public/uploads/profiles/default.png');
            $record['web_banner_image'] = !empty($record['web_banner_image'])
                ? $fileService->url($record['web_banner_image'], 'feature_section', 'public/uploads/profiles/default.png')
                : base_url('public/uploads/profiles/default.png');
            $record['type'] = $record['banner_type'];

            if ($record['banner_type'] == "banner_category") {
                $record['type_id'] = $record['category_ids'];
                $record['category_slug'] = $record['category_slug'];
                // Get all parent category slugs
                $parent_slugs = [];
                if (!empty($record['category_parent_id'])) {
                    $this->getParentSlugs($record['category_parent_id'], $parent_slugs);
                }

                $record['parent_category_slugs'] = array_reverse($parent_slugs) ?? [];
            } else if ($record['banner_type'] == "banner_provider") {
                $record['type_id'] = $record['partners_ids'];
                $record['provider_slug'] = $record['provider_slug'];
            } else {
                $record['type_id'] = '';
                $record['slug'] = '';
            }
            $record['category_name'] = $record['category_name'] ?? '';
            $record['provider_name'] = $record['provider_name'] ?? '';
        }

        return $feature_section_record;
    }

    private function getDefaultPartners($row, $db, $fileService)
    {
        $partners_ids = explode(',', $row['partners_ids']);
        if (empty($partners_ids)) {
            return [];
        }

        $settings = get_settings('general_settings', true);
        $latitude = $this->request->getPost('latitude');
        $longitude = $this->request->getPost('longitude');
        $max_distance = $settings['max_serviceable_distance'];

        // Distance calculation only if lat/lng is provided
        $distance_sql = $latitude && $longitude
            ? get_provider_distance_sql($latitude, $longitude, 'p.id', 'p.latitude', 'p.longitude') . " AS distance"
            : "NULL as distance";

        // Base query: get partners
        // Only include partners with active subscriptions
        // This ensures only providers with valid subscriptions are returned
        $partners = $db->table('users p')
            ->select("p.id, p.username, p.company, p.image, pd.banner, pd.slug, pd.company_name, pd.about, pd.long_description, pd.max_serviceable_distance, pc.minimum_order_amount, pc.discount, pc.discount_type, pd.at_store, pd.at_doorstep, (SELECT COUNT(*) FROM orders o WHERE o.partner_id = p.id AND o.parent_id IS NULL AND o.status='completed' AND (o.payment_status != 2 OR o.payment_status IS NULL)) as number_of_orders, $distance_sql")
            ->join('partner_details pd', 'pd.partner_id = p.id', 'left')
            ->join('partner_subscriptions ps', 'ps.partner_id = p.id', 'inner')
            ->join('promo_codes pc', 'pc.partner_id = p.id', 'left')
            ->whereIn('p.id', $partners_ids)
            ->where('pd.is_approved', '1')
            ->where('ps.status', 'active')
            ->groupBy(['p.id', 'pd.id']);

        if ($latitude && $longitude) {
            $partners->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $max_distance))->orderBy('distance');
        }

        $partners = $partners->get()->getResultArray();

        if (empty($partners)) {
            return [];
        }

        // Filter by subscription in one shot
        $partners = $this->filterPartnersBySubscription($partners, $db);

        // Collect partner IDs for bulk queries
        $partnerIds = array_column($partners, 'id');

        /** ----------------------------------
         *  Bulk Queries for Enrichment
         * ---------------------------------*/

        // Bulk ratings (avg, total, count)
        $ratings = $db->table('services_ratings r')
            ->select('s.user_id as partner_id, COUNT(r.rating) as number_of_rating, SUM(r.rating) as total_rating, (SUM(r.rating) / COUNT(r.rating)) as average_rating')
            ->join('services s', 'r.service_id = s.id', 'left')
            ->groupBy('s.user_id');


        if (!empty($partnerIds)) {
            $ratings->whereIn('s.user_id', $partnerIds);
        }
        $ratings = $ratings->get()->getResultArray();

        $ratingsMap = array_column($ratings, null, 'partner_id');

        // Bulk rating breakdown (1–5 stars)
        $stars = $db->table('services_ratings r')
            ->select('s.user_id as partner_id,
                SUM(r.rating = 1) as rating_1,
                SUM(r.rating = 2) as rating_2,
                SUM(r.rating = 3) as rating_3,
                SUM(r.rating = 4) as rating_4,
                SUM(r.rating = 5) as rating_5')
            ->join('services s', 's.id = r.service_id', 'left')
            ->groupBy('s.user_id');

        if (!empty($partnerIds)) {
            $stars->whereIn('s.user_id', $partnerIds);
        }
        $stars = $stars->get()->getResultArray();

        $starsMap = array_column($stars, null, 'partner_id');

        // Bulk bookmarks for current user
        $bookmarksMap = [];
        if (!empty($this->user_details['id'])) {
            $bookmarks = $db->table('bookmarks')
                ->select('partner_id')
                ->where('user_id', $this->user_details['id'])
                ->whereIn('partner_id', $partnerIds)
                ->get()
                ->getResultArray();
            $bookmarksMap = array_flip(array_column($bookmarks, 'partner_id'));
        }

        // Bulk total services count
        // Note: We need to count services per partner matching their at_store and at_doorstep settings
        // This matches the logic used in get_providers API (Partners_model->list() method)
        // Since each partner may have different at_store/at_doorstep values, we calculate individually
        $servicesMap = [];
        foreach ($partners as $partner) {
            $pid = $partner['id'];
            // Get partner's at_store and at_doorstep settings from the partner data
            $at_store = $partner['at_store'] ?? 0;
            $at_doorstep = $partner['at_doorstep'] ?? 0;

            // Count services matching the partner's settings and status requirements
            // This matches the logic in Partners_model->list() method (line 613-620)
            $service_count = $db->table('services')
                ->where('user_id', $pid)
                ->where('at_store', $at_store)
                ->where('at_doorstep', $at_doorstep)
                ->where('status', 1)  // Only active services
                ->where('approved_by_admin', 1)  // Only approved services
                ->countAllResults();

            $servicesMap[$pid] = $service_count;
        }

        /** ----------------------------------
         *  Merge all the data into partners
         * ---------------------------------*/
        foreach ($partners as &$partner) {
            $pid = $partner['id'];

            // Skip partners without valid ID to prevent errors
            if (empty($pid)) {
                continue;
            }

            // Translation (company_name, about, long_description)
            $translatedData = get_translated_partner_data_for_api($pid, $partner);
            // Merge translated data with original partner data to preserve all fields
            $partner = array_merge($partner, $translatedData);

            // Images
            $partner['image'] = $this->getImagePath($partner['image'] ?? '', 'profile', $fileService);
            $partner['banner_image'] = $this->getImagePath($partner['banner'] ?? '', 'banner', $fileService);

            // Total services
            $partner['total_services'] = $servicesMap[$pid] ?? 0;

            // Bookmarked?
            $partner['is_bookmarked'] = isset($bookmarksMap[$pid]) ? '1' : '0';

            // Ratings
            if (isset($ratingsMap[$pid])) {
                $partner['ratings'] = sprintf('%0.1f', $ratingsMap[$pid]['average_rating']);
            } else {
                $partner['ratings'] = '0.0';
            }

            // Star breakdown
            $partner['1_star'] = $starsMap[$pid]['rating_1'] ?? 0;
            $partner['2_star'] = $starsMap[$pid]['rating_2'] ?? 0;
            $partner['3_star'] = $starsMap[$pid]['rating_3'] ?? 0;
            $partner['4_star'] = $starsMap[$pid]['rating_4'] ?? 0;
            $partner['5_star'] = $starsMap[$pid]['rating_5'] ?? 0;

            // Cleanup
            $this->unsetFields($partner, ['minimum_order_amount', 'banner']);
        }

        return $partners;
    }

    private function formatRow($row, $type, $partners, $description, $allTranslations = [])
    {
        // Apply translation logic to this section using the pre-fetched translations
        $sectionData = [
            'id' => $row['id'],
            'title' => $row['title'] ?? '',
            'description' => $description ?? ''
        ];

        // Get languages for translation logic
        $requestedLanguage = get_current_language_from_request();
        $defaultLanguage = get_default_language();

        // Apply translation logic using the efficient method
        $translatedData = apply_section_translation_logic(
            $sectionData,
            $allTranslations,
            $row['id'],
            $requestedLanguage,
            $defaultLanguage
        );

        return [
            'id' => $row['id'],
            'title' => $translatedData['title'],
            'section_type' => $type,
            'description' => $translatedData['description'],
            'translated_title' => $translatedData['translated_title'],
            'translated_description' => $translatedData['translated_description'],
            'parent_ids' => ($type == 'partners' || $type == "sub_categories" || $type == "near_by_provider" || $type == "top_rated_provider" || $type == "categories" || $type == "previous_order" || $type == "ongoing_order" || $type == "banner") ? implode(", ", array_column($partners, 'id')) : '',
            'partners' => ($type == 'partners' || $type == "near_by_provider" || $type == "top_rated_partner") ? $partners : [],
            'sub_categories' => $type == 'sub_categories' ? $partners : [],
            'previous_order' => $type == 'previous_order' ? $partners : [],
            'ongoing_order' => $type == 'ongoing_order' ? $partners : [],
            'banner' => $type == 'banner' ? $partners : [],
        ];
    }

    private function getParentSlugs($parent_id, &$parent_slugs)
    {
        $parent_category = fetch_details('categories', ['id' => $parent_id], ['slug', 'parent_id']);
        if (!empty($parent_category)) {
            $parent_slugs[] = $parent_category[0]['slug'];
            $this->getParentSlugs($parent_category[0]['parent_id'], $parent_slugs);
        }
    }

    private function getTotalProviders($category_id, $db)
    {
        // Get user location and max serviceable distance
        $settings = get_settings('general_settings', true);
        $latitude = $this->request->getPost('latitude');
        $longitude = $this->request->getPost('longitude');
        $max_distance = $settings['max_serviceable_distance'];

        $subcategory_data = fetch_details('categories', ['parent_id' => $category_id], ['id']);
        $subcategory_ids = array_column($subcategory_data, 'id');
        $subcategory_ids[] = $category_id;

        // Build the base query
        // Use INNER join with partner_subscriptions to ensure only providers with active subscriptions are counted
        // This ensures only providers with valid subscriptions are included in category provider counts
        $query = $db->table('services as s')
            ->whereIn('s.category_id', $subcategory_ids)
            ->where('pd.is_approved', 1)
            ->where('ps.status', 'active')
            ->join('partner_details pd', 'pd.partner_id = s.user_id')
            ->join('partner_subscriptions ps', 'ps.partner_id = s.user_id', 'inner')
            ->join('users u', 'u.id = s.user_id', 'left');

        // Add distance calculation and filtering if coordinates are provided
        if ($latitude && $longitude) {
            $distance_calculation = get_provider_distance_sql($latitude, $longitude, 'u.id') . " as distance";
            $query->select('s.id as service_id, s.user_id as service_partner_id, pd.max_serviceable_distance, ' . $distance_calculation)
                ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $max_distance));
        } else {
            $query->select('s.id as service_id, s.user_id as service_partner_id');
        }

        $services = $query->distinct()->get()->getResultArray();
        return count(array_unique(array_column($services, 'service_partner_id')));
    }

    private function unsetFields(&$array, $fields)
    {
        foreach ($fields as $field) {
            unset($array[$field]);
        }
    }

    private function filterPartnersBySubscription($partners, $db)
    {
        foreach ($partners as $key => $partner) {
            $partner_subscription = $db->table('partner_subscriptions')
                ->where('partner_id', $partner['id'])
                ->where('status', 'active')
                ->orderBy('updated_at', 'DESC')
                ->get()
                ->getRowArray();

            if (!$partner_subscription) {
                // log_message('debug', "Partner {$partner['id']} removed: no active subscription");
                unset($partners[$key]);
                continue;
            }

            if ($partner_subscription['order_type'] === 'unlimited') {
                continue;
            }

            $subscription_purchase_date = $partner_subscription['start_date'] ?? $partner_subscription['updated_at'];
            $subscription_order_limit = $partner_subscription['max_order_limit'] ?? 0;

            // Count only progressed bookings so failed payments keep the slot available.
            $partner_order_count = count_orders_towards_subscription_limit($partner['id'], $subscription_purchase_date, [], $db);

            if ($partner_order_count >= $subscription_order_limit) {
                // log_message('debug', "Partner {$partner['id']} removed: order limit reached ($partner_order_count / $subscription_order_limit)");
                unset($partners[$key]);
            }
        }

        return array_values($partners);
    }

    private function getImagePath($image, $folder, $fileService)
    {
        return !empty($image)
            ? $fileService->url($image, $folder, 'public/uploads/profiles/default.png')
            : base_url('public/uploads/profiles/default.png');
    }

    /**
     * Get total services count for a partner
     * 
     * This method matches the service counting logic used in get_providers API
     * It filters services by:
     * - user_id (partner_id)
     * - at_store (must match partner's at_store setting)
     * - at_doorstep (must match partner's at_doorstep setting)
     * - status = 1 (only active services)
     * - approved_by_admin = 1 (only approved services)
     * 
     * @param int $user_id The partner's user ID
     * @param object $db Database connection object
     * @return int Total count of services matching the criteria
     */
    private function getTotalServices($user_id, $db)
    {
        // Get partner's at_store and at_doorstep settings
        // These values determine which services should be counted for this partner
        $partner_detail = $db->table('partner_details')
            ->select('at_store, at_doorstep')
            ->where('partner_id', $user_id)
            ->get()
            ->getRowArray();

        // If partner details not found, return 0
        if (empty($partner_detail)) {
            return 0;
        }

        // Count services matching the partner's settings and status requirements
        // This matches the logic in Partners_model->list() method (line 613-620)
        $service_count = $db->table('services')
            ->where('user_id', $user_id)
            ->where('at_store', $partner_detail['at_store'])
            ->where('at_doorstep', $partner_detail['at_doorstep'])
            ->where('status', 1)  // Only active services
            ->where('approved_by_admin', 1)  // Only approved services
            ->countAllResults();

        return $service_count;
    }
}