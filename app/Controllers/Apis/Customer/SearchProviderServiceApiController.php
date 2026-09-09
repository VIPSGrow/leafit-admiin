<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;

class SearchProviderServiceApiController extends BaseController
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
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function search_services_providers()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'search' => 'required',
                    'latitude' => 'required',
                    'longitude' => 'required',
                    'type' => 'required'
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
            $search = $this->request->getPost('search') ?? '';
            $latitude = $this->request->getPost('latitude') ?? '';
            $longitude = $this->request->getPost('longitude') ?? '';
            $db = \Config\Database::connect();
            $limit = $this->request->getPost('limit') ?? '5';
            $offset = $this->request->getPost('offset') ?? '0';
            $type = $this->request->getPost('type');
            $data = [];
            if ($type == "provider") {
                $settings = get_settings('general_settings', true);
                if (($this->request->getPost('latitude') && !empty($this->request->getPost('latitude')) && ($this->request->getPost('longitude') && !empty($this->request->getPost('longitude'))))) {
                    $additional_data = [
                        'latitude' => $this->request->getPost('latitude'),
                        'longitude' => $this->request->getPost('longitude'),
                        'max_serviceable_distance' => $settings['max_serviceable_distance'],
                    ];
                }
                $is_latitude_set = "";
                if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
                    $latitude = $this->request->getPost('latitude');
                    $longitude = $this->request->getPost('longitude');
                    $is_latitude_set = get_provider_distance_sql($latitude, $longitude, 'p.id', 'p.latitude', 'p.longitude') . " as distance";
                }
                $builder1 = $db->table('users u1');
                $partners1 = $builder1->select("
                    u1.username,
                    u1.city,
                    u1.latitude,
                    u1.longitude,
                    u1.id,
                    pc.minimum_order_amount,
                    pc.discount,
                    COALESCE(tpd.company_name, pd.company_name) AS company_name,
                    u1.image,
                    pd.banner,
                    pc.discount_type,
                    u1.id as partner_id,
                    pd.number_of_ratings as number_of_rating,
                    pd.ratings AS average_rating,
                    pd.ratings as ratings,
                    pd.at_doorstep,
                    pd.at_store,
                    pd.visiting_charges as visiting_charges,
                    pd.slug as provider_slug,
                    pd.max_serviceable_distance,
                    (SELECT COUNT(*)
                        FROM orders o
                        WHERE o.partner_id = u1.id AND o.parent_id IS NULL AND o.status='completed'
                    ) as number_of_orders,
                    " . get_provider_distance_sql($latitude, $longitude, 'u1.id', 'u1.latitude', 'u1.longitude') . " as distance
                ")
                    ->join('users_groups ug1', 'ug1.user_id = u1.id')
                    ->join('partner_details pd', 'pd.partner_id = u1.id')
                    ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id', 'left')
                    ->join('languages l', 'l.code = tpd.language_code AND l.is_default = 1', 'left')
                    ->join('services s', 's.user_id = pd.partner_id', 'left')
                    ->join('services_ratings sr', 'sr.service_id = s.id', 'left')
                    ->join('partner_subscriptions ps', 'ps.partner_id = u1.id')
                    ->join('promo_codes pc', 'pc.partner_id = u1.id', 'left')
                    ->where('ps.status', 'active')
                    ->where('pd.is_approved', '1')
                    ->where('ug1.group_id', '3')
                    ->groupBy(['pd.partner_id', 'pd.id'])
                    ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']))
                    ->orderBy('distance')
                    ->limit($limit, $offset);
                if ($search and $search != '') {
                    $searchWhere = [
                        '`pd.id`' => $search,
                        '`tpd.company_name`' => $search,
                        '`pd.company_name`' => $search,
                        '`pd.created_at`' => $search,
                        '`pd.updated_at`' => $search,
                        '`u1.username`' => $search,
                        '`tpd.username`' => $search,
                    ];

                    if (isset($searchWhere) && !empty($searchWhere)) {
                        $builder1->groupStart();
                        $builder1->orLike($searchWhere);
                        $builder1->groupEnd();
                    }
                }
                $partners1 = $builder1->get()->getResultArray();

                $fileService = service('fileService');
                for ($i = 0; $i < count($partners1); $i++) {
                    $partners1[$i]['upto'] = $partners1[$i]['minimum_order_amount'];
                    if (!empty($partners1[$i]['image'])) {
                        $partners1[$i]['image'] = $fileService->url($partners1[$i]['image'], 'profile', 'public/uploads/profiles/default.png');
                        $partners1[$i]['banner_image'] = $fileService->url($partners1[$i]['banner'] ?? '', 'banner', 'public/uploads/profiles/default.png');
                        unset($partners1[$i]['banner']);
                        if ($partners1[$i]['discount_type'] == 'percentage') {
                            $upto = $partners1[$i]['minimum_order_amount'];
                            unset($partners1[$i]['discount_type']);
                        }
                    }
                    unset($partners1[$i]['minimum_order_amount']);
                    $total_services_of_providers = fetch_details('services', ['user_id' => $partners1[$i]['id'], 'at_store' => $partners1[$i]['at_store'], 'at_doorstep' => $partners1[$i]['at_doorstep']], ['id']);
                    $partners1[$i]['total_services'] = count($total_services_of_providers);
                }
                $ids = [];
                foreach ($partners1 as $key => $row1) {
                    $ids[] = $row1['id'];
                }
                foreach ($ids as $key => $id) {
                    $partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $id, 'status' => 'active']);
                    if ($partner_subscription) {
                        $subscription_purchase_date = $partner_subscription[0]['updated_at'];
                        // Ignore awaiting / cancelled bookings while checking quota.
                        $consumedOrders = count_orders_towards_subscription_limit($id, $subscription_purchase_date, [], $db);
                        $partners_subscription = $db->table('partner_subscriptions ps');
                        $partners_subscription_data = $partners_subscription->select('ps.*')->where('ps.status', 'active')
                            ->get()
                            ->getResultArray();
                        $subscription_order_limit = $partners_subscription_data[0]['max_order_limit'];
                        if ($partners_subscription_data[0]['order_type'] == "limited") {
                            if ($consumedOrders >= $subscription_order_limit) {
                                unset($ids[$key]);
                            }
                        }
                    } else {
                        unset($ids[$key]);
                    }
                }
                $parent_ids = array_values($ids);
                $parent_ids = implode(", ", $parent_ids);
                // Apply translations to provider data
                foreach ($partners1 as &$partner) {
                    if (!empty($partner['company_name'])) {
                        $partnerTranslations = $this->getPartnerTranslations($partner['id']);
                        if ($partnerTranslations) {
                            $partner['translated_company_name'] = $partnerTranslations['company_name'] ?? $partner['company_name'];
                            $partner['translated_username'] = $partnerTranslations['username'] ?? $partner['username'];
                        } else {
                            $partner['translated_company_name'] = $partner['company_name'];
                            $partner['translated_username'] = $partner['username'];
                        }
                    } else {
                        $partner['translated_company_name'] = '';
                        $partner['translated_username'] = '';
                    }
                }
                unset($partner);

                $data['providers'] = $partners1;
                // for total ------------------------------
                $builder1_total = $db->table('users u1');
                $partners1_total = $builder1_total->Select("u1.username,u1.city,u1.latitude,u1.longitude,u1.id,pc.minimum_order_amount,pc.discount,pd.company_name,pd.max_serviceable_distance,u1.image,pd.banner, pc.discount_type,
                   ( count(sr.rating)) as number_of_rating,
                    ( SUM(sr.rating)) as total_rating,
                    ((SUM(sr.rating) / count(sr.rating))) as average_rating,
                    (SELECT COUNT(*) FROM orders o WHERE o.partner_id = u1.id AND o.parent_id IS NULL AND o.status='completed' AND (o.payment_status != 2 OR o.payment_status IS NULL)) as number_of_orders," . get_provider_distance_sql($latitude, $longitude, 'u1.id', 'u1.latitude', 'u1.longitude') . " as distance")
                    ->join('users_groups ug1', 'ug1.user_id=u1.id')
                    ->join('partner_details pd', 'pd.partner_id=u1.id')
                    ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id', 'left')
                    ->join('services s', 's.user_id=pd.partner_id', 'left')
                    ->join('services_ratings sr', 'sr.service_id = s.id', 'left')
                    ->join('partner_subscriptions ps', 'ps.partner_id=u1.id')
                    ->join('promo_codes pc', 'pc.partner_id=u1.id', 'left')
                    ->where('ps.status', 'active')
                    ->where('ug1.group_id', '3')
                    ->groupBy(['pd.partner_id', 'pd.id'])
                    ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']))
                    ->orderBy('distance');
                if ($search and $search != '') {
                    $searchWhere = [
                        '`pd.id`' => $search,
                        '`pd.company_name`' => $search,
                        '`pd.created_at`' => $search,
                        '`pd.updated_at`' => $search,
                        '`u1.username`' => $search,
                        '`tpd.username`' => $search,
                        '`tpd.company_name`' => $search,
                    ];
                    if (isset($searchWhere) && !empty($searchWhere)) {
                        $builder1_total->groupStart();
                        $builder1_total->orLike($searchWhere);
                        $builder1_total->groupEnd();
                    }
                }
                $partners1_total = $builder1_total->get()->getResultArray();
                for ($i = 0; $i < count($partners1_total); $i++) {
                    $partners1_total[$i]['upto'] = $partners1_total[$i]['minimum_order_amount'];
                    if (!empty($partners1_total[$i]['image'])) {
                        $partners1_total[$i]['image'] = $fileService->url($partners1_total[$i]['image'], 'profile', 'public/uploads/profiles/default.png');
                        $partners1_total[$i]['banner_image'] = $fileService->url($partners1_total[$i]['banner'] ?? '', 'banner', 'public/uploads/profiles/default.png');
                        unset($partners1_total[$i]['banner']);
                        if ($partners1_total[$i]['discount_type'] == 'percentage') {
                            $upto = $partners1_total[$i]['minimum_order_amount'];
                            unset($partners1_total[$i]['discount_type']);
                        }
                    }
                    unset($partners1_total[$i]['minimum_order_amount']);
                }
                $ids = [];
                foreach ($partners1_total as $key => $row1) {
                    $ids[] = $row1['id'];
                }
                foreach ($ids as $key => $id) {
                    $partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $id, 'status' => 'active']);
                    if ($partner_subscription) {
                        $subscription_purchase_date = $partner_subscription[0]['updated_at'];
                        // Only count bookings that reached started / completed.
                        $consumedOrders = count_orders_towards_subscription_limit($id, $subscription_purchase_date, [], $db);
                        $partners_subscription = $db->table('partner_subscriptions ps');
                        $partners_subscription_data = $partners_subscription->select('ps.*')->where('ps.status', 'active')
                            ->get()
                            ->getResultArray();
                        $subscription_order_limit = $partners_subscription_data[0]['max_order_limit'];
                        if ($partners_subscription_data[0]['order_type'] == "limited") {
                            if ($consumedOrders >= $subscription_order_limit) {
                                unset($ids[$key]);
                            }
                        }
                    } else {
                        unset($ids[$key]);
                    }
                }
                $data['total'] = count($partners1_total);
                //end for total 
            } else if ($type == "service") {
                // services 
                $settings = get_settings('general_settings', true);
                if (($this->request->getPost('latitude') && !empty($this->request->getPost('latitude')) && ($this->request->getPost('longitude') && !empty($this->request->getPost('longitude'))))) {
                    $additional_data = [
                        'latitude' => $this->request->getPost('latitude'),
                        'longitude' => $this->request->getPost('longitude'),
                        'max_serviceable_distance' => $settings['max_serviceable_distance'],
                    ];
                }
                $is_latitude_set = "";
                if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
                    $latitude = $this->request->getPost('latitude');
                    $longitude = $this->request->getPost('longitude');
                    $is_latitude_set = get_provider_distance_sql($latitude, $longitude, 'p.id', 'p.latitude', 'p.longitude') . " as distance";
                }
                $multipleWhere = '';
                $db      = \Config\Database::connect();
                $builder = $db->table('services s');
                $services = $builder->select("s.*,s.image as service_image, c.name as category_name, p.username as partner_name, c.parent_id, pd.company_name, pd.slug as provider_slug, pd.max_serviceable_distance,
                     pd.at_store as provider_at_store, pd.at_doorstep as provider_at_doorstep, p.city,
                p.latitude, p.longitude, p.id as user_id, pd.banner, p.image as partner_image,
                COALESCE(COUNT(sr.rating), 0) as number_of_rating,
                COALESCE(SUM(sr.rating), 0) as provider_total_rating,
                    (SELECT COUNT(*) FROM orders o WHERE o.partner_id = p.id AND o.parent_id IS NULL AND o.status='completed') as number_of_orders, " . get_provider_distance_sql($latitude, $longitude, 'p.id', 'p.latitude', 'p.longitude') . " as distance, pc.discount, pc.discount_type, pc.minimum_order_amount")
                    ->join('users p', 'p.id=s.user_id', 'left')
                    ->join('partner_details pd', 'pd.partner_id=s.user_id')
                    ->join('partner_subscriptions ps', 'ps.partner_id=s.user_id')
                    ->join('services_ratings sr', 'sr.service_id = s.id', 'left')
                    ->join('promo_codes pc', 'pc.partner_id=p.id', 'left')
                    ->join('categories c', 'c.id=s.category_id', 'left')
                    ->join('translated_service_details tsd', 'tsd.service_id=s.id', 'left')
                    ->where('pd.at_store', 's.at_store', false)
                    ->where('pd.at_doorstep', 's.at_doorstep', false)
                    ->where('s.approved_by_admin', '1', false)
                    ->where('s.status', '1', false)
                    ->where('ps.status', 'active')
                    ->where('pd.is_approved', '1')
                    ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']))
                    ->groupBy(['s.id', 'pd.id']);
                if ($search and $search != '') {
                    $multipleWhere = [
                        '`s.id`' => $search,
                        '`s.title`' => $search,
                        '`s.description`' => $search,
                        '`s.status`' => $search,
                        '`s.tags`' => $search,
                        '`s.price`' => $search,
                        '`s.discounted_price`' => $search,
                        '`s.rating`' => $search,
                        '`s.number_of_ratings`' => $search,
                        '`s.max_quantity_allowed`' => $search,
                        '`tsd.title`' => $search,
                        '`tsd.description`' => $search,
                        '`tsd.tags`' => $search,
                        '`tsd.long_description`' => $search
                    ];
                    if (isset($multipleWhere) && !empty($multipleWhere)) {
                        $services->groupStart();
                        $services->orLike($multipleWhere);
                        $services->groupEnd();
                    }
                }
                $service_result = $services->get()->getResultArray();

                // print_r($db->getLastQuery());
                // die;

                $defaultLang = get_default_language();
                $requestLang = get_current_language_from_request();

                $fileService = service('fileService');
                $groupedServices = [];
                $groupedServices1 = [];
                $all_providers = [];
                foreach ($service_result as $row) {

                    $image = !empty($row['image']) ? $fileService->url($row['image'], 'services') : '';
                    $banner_image = !empty($row['banner']) ? $fileService->url($row['banner'], 'banner') : '';


                    $all_providers[] = $row['user_id'];
                    $providerId = $row['user_id'];
                    $average_rating = $db->table('services s')
                        ->select('(SUM(sr.rating) / COUNT(sr.rating)) as average_rating')
                        ->join('services_ratings sr', 'sr.service_id = s.id')
                        ->where('s.id', $row['id'])
                        ->get()->getRowArray();

                    $row['average_rating'] = isset($average_rating['average_rating']) ? number_format($average_rating['average_rating'], 2) : 0;
                    $rate_data = get_service_ratings($row['id']);
                    $row['total_ratings'] = $rate_data[0]['total_ratings'] ?? 0;
                    $row['rating_5'] = $rate_data[0]['rating_5'] ?? 0;
                    $row['rating_4'] = $rate_data[0]['rating_4'] ?? 0;
                    $row['rating_3'] = $rate_data[0]['rating_3'] ?? 0;
                    $row['rating_2'] = $rate_data[0]['rating_2'] ?? 0;
                    $row['rating_1'] = $rate_data[0]['rating_1'] ?? 0;
                    $images = !empty($row['service_image']) ? $fileService->url($row['service_image'], 'services') : '';
                    $row['image_of_the_service'] = $images;
                    $tax_data = fetch_details('taxes', ['id' => $row['tax_id']], ['title', 'percentage']);
                    $taxPercentageData = fetch_details('taxes', ['id' => $row['tax_id']], ['percentage']);
                    if (!empty($taxPercentageData)) {
                        $taxPercentage = $taxPercentageData[0]['percentage'];
                    } else {
                        $taxPercentage = 0;
                    }
                    if (empty($tax_data)) {
                        $row['tax_title'] = "";
                        $row['tax_percentage'] = "";
                    } else {
                        $row['tax_title'] = $tax_data[0]['title'];
                        $row['tax_percentage'] = $tax_data[0]['percentage'];
                    }
                    if ($row['discounted_price'] == "0") {
                        if ($row['tax_type'] == "excluded") {
                            $row['tax_value'] = number_format((intval(($row['price'] * ($taxPercentage) / 100))), 2);
                            $row['price_with_tax']  = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
                            $row['original_price_with_tax'] = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
                        } else {
                            $row['tax_value'] = "";
                            $row['price_with_tax']  = strval($row['price']);
                            $row['original_price_with_tax'] = strval($row['price']);
                        }
                    } else {
                        if ($row['tax_type'] == "excluded") {
                            $row['tax_value'] = number_format((intval(($row['discounted_price'] * ($taxPercentage) / 100))), 2);
                            $row['price_with_tax']  = strval($row['discounted_price'] + ($row['discounted_price'] * ($taxPercentage) / 100));
                            $row['original_price_with_tax'] = strval($row['price'] + ($row['discounted_price'] * ($taxPercentage) / 100));
                        } else {
                            $row['tax_value'] = "";
                            $row['price_with_tax']  = strval($row['discounted_price']);
                            $row['original_price_with_tax'] = strval($row['price']);
                        }
                    }

                    // original partner detail fields
                    $originalCompanyName = $row['company_name'];
                    $originalUsername = $row['partner_name'];
                    // start with original values
                    $defaultCompanyName    = $originalCompanyName;
                    $translatedCompanyName = $originalCompanyName;
                    $defaultUsername = $originalUsername;
                    $translatedUsername = $originalUsername;
                    $partnerTranslations = $db->table('translated_partner_details')
                        ->select('language_code, company_name, username')
                        ->where('partner_id', $providerId)
                        ->whereIn('language_code', [$defaultLang, $requestLang])
                        ->get()->getResultArray();
                    foreach ($partnerTranslations as $t) {
                        if ($t['language_code'] === $defaultLang && !empty($t['company_name'])) {
                            $defaultCompanyName = $t['company_name'];
                            $defaultUsername = $t['username'];
                        }
                        if ($t['language_code'] === $requestLang && !empty($t['company_name'])) {
                            $translatedCompanyName = $t['company_name'];
                            $translatedUsername = $t['username'];
                        }
                    }

                    // fallback logic
                    if (
                        $translatedCompanyName === $originalCompanyName
                        && $defaultCompanyName !== $originalCompanyName
                    ) {
                        $translatedCompanyName = $defaultCompanyName;
                        $translatedUsername = $defaultUsername;
                    }

                    if (!isset($groupedServices[$providerId])) {
                        $groupedServices[$providerId]['provider']['company_name'] = $defaultCompanyName;
                        $groupedServices[$providerId]['provider']['username'] = $defaultUsername;
                        $groupedServices[$providerId]['provider']['city'] = $row['city'];
                        $groupedServices[$providerId]['provider']['latitude'] = $row['latitude'];
                        $groupedServices[$providerId]['provider']['longitude'] = $row['longitude'];
                        $groupedServices[$providerId]['provider']['id'] = $row['user_id'];
                        $groupedServices[$providerId]['provider']['provider_slug'] = $row['provider_slug'];
                        $groupedServices[$providerId]['provider']['image'] = $image;
                        $groupedServices[$providerId]['provider']['banner_image'] = $banner_image;
                        $groupedServices[$providerId]['provider']['number_of_rating'] = $row['number_of_rating'];
                        $groupedServices[$providerId]['provider']['total_rating'] = $row['provider_total_rating'];
                        $groupedServices[$providerId]['provider']['average_rating'] = $row['average_rating'];
                        $groupedServices[$providerId]['provider']['number_of_orders'] = $row['number_of_orders'];
                        $groupedServices[$providerId]['provider']['distance'] = $row['distance'];
                        $groupedServices[$providerId]['provider']['discount_type'] = $row['discount_type'];
                        $groupedServices[$providerId]['provider']['discount'] = $row['discount'];
                        $groupedServices[$providerId]['provider']['upto'] = $row['minimum_order_amount'];
                        unset($row['minimum_order_amount']);
                        $groupedServices[$providerId]['provider']['services'] = [];
                        $total_services_of_providers = fetch_details('services', ['user_id' => $providerId, 'at_store' => $row['provider_at_store'], 'at_doorstep' => $row['provider_at_doorstep']], ['id']);
                        $groupedServices[$providerId]['provider']['total_services'] = count($total_services_of_providers);
                    }

                    // Add the service to the provider's services array
                    $groupedServices[$providerId]['provider']['services'][] = $row;
                }
                $all_providers = array_unique($all_providers);
                $all_providers = array_slice(($all_providers), $offset, $limit);
                foreach ($service_result as $key => $row) {
                    $providerId = $row['user_id'];
                    if (in_array($providerId, $all_providers)) {
                        $average_rating = $db->table('services s')
                            ->select('(SUM(sr.rating) / COUNT(sr.rating)) as average_rating')
                            ->join('services_ratings sr', 'sr.service_id = s.id')
                            ->where('s.id', $row['id'])
                            ->get()->getRowArray();
                        $row['average_rating'] = isset($average_rating['average_rating']) ? number_format($average_rating['average_rating'], 2) : 0;
                        $rate_data = get_service_ratings($row['id']);
                        $row['total_ratings'] = $rate_data[0]['total_ratings'] ?? 0;
                        $row['rating_5'] = $rate_data[0]['rating_5'] ?? 0;
                        $row['rating_4'] = $rate_data[0]['rating_4'] ?? 0;
                        $row['rating_3'] = $rate_data[0]['rating_3'] ?? 0;
                        $row['rating_2'] = $rate_data[0]['rating_2'] ?? 0;
                        $row['rating_1'] = $rate_data[0]['rating_1'] ?? 0;
                        $images = !empty($row['service_image']) ? $fileService->url($row['service_image'], 'services') : '';
                        if (!empty($row['other_images'])) {
                            $row['other_images'] = array_map(
                                fn($data) => $fileService->url($data, 'services'),
                                json_decode($row['other_images'], true) ?? []
                            );
                        } else {
                            $row['other_images'] = [];
                        }
                        if (!empty($row['files'])) {
                            $row['files'] = array_map(
                                fn($data) => $fileService->url($data, 'services'),
                                json_decode($row['files'], true) ?? []
                            );
                        } else {
                            $row['files'] = [];
                        }

                        if ($row['banner']) {
                            $row['banner'] = $fileService->url($row['banner'], 'banner');
                        }
                        if ($row['partner_image']) {
                            $row['partner_image'] = $fileService->url($row['partner_image'], 'profile');
                        }
                        $faqsData = json_decode($row['faqs'], true);

                        if (is_array($faqsData)) {
                            $normalizedFaqs = [];

                            foreach ($faqsData as $faq) {
                                // Skip if it’s totally invalid
                                if (!is_array($faq) || empty($faq)) {
                                    continue;
                                }

                                $question = '';
                                $answer   = '';

                                // Case 1: New format (associative)
                                if (isset($faq['question']) && isset($faq['answer'])) {
                                    $question = trim($faq['question']);
                                    $answer   = trim($faq['answer']);
                                }

                                // Case 2: Old format (numeric array like [0 => question, 1 => answer])
                                elseif (isset($faq[0]) && isset($faq[1])) {
                                    $question = trim($faq[0]);
                                    $answer   = trim($faq[1]);
                                }

                                // Case 3: Totally malformed, skip
                                else {
                                    continue;
                                }

                                // Skip blanks
                                if ($question !== '' && $answer !== '') {
                                    $normalizedFaqs[] = [
                                        'question' => $question,
                                        'answer' => $answer,
                                    ];
                                }
                            }

                            $row['faqs'] = $normalizedFaqs;
                        } else {
                            $row['faqs'] = [];
                        }
                        $row['image_of_the_service'] = $images;
                        $row['image'] = $images;
                        unset($row['service_image']);
                        $tax_data = fetch_details('taxes', ['id' => $row['tax_id']], ['title', 'percentage']);
                        $taxPercentageData = fetch_details('taxes', ['id' => $row['tax_id']], ['percentage']);
                        if (!empty($taxPercentageData)) {
                            $taxPercentage = $taxPercentageData[0]['percentage'];
                        } else {
                            $taxPercentage = 0;
                        }
                        if (empty($tax_data)) {
                            $row['tax_title'] = "";
                            $row['tax_percentage'] = "";
                        } else {
                            $row['tax_title'] = $tax_data[0]['title'];
                            $row['tax_percentage'] = $tax_data[0]['percentage'];
                        }
                        if ($row['discounted_price'] == "0") {
                            if ($row['tax_type'] == "excluded") {
                                $row['tax_value'] = number_format((intval(($row['price'] * ($taxPercentage) / 100))), 2);
                                $row['price_with_tax']  = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
                                $row['original_price_with_tax'] = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
                            } else {
                                $row['tax_value'] = "";
                                $row['price_with_tax']  = strval($row['price']);
                                $row['original_price_with_tax'] = strval($row['price']);
                            }
                        } else {
                            if ($row['tax_type'] == "excluded") {
                                $row['tax_value'] = number_format((intval(($row['discounted_price'] * ($taxPercentage) / 100))), 2);
                                $row['price_with_tax']  = strval($row['discounted_price'] + ($row['discounted_price'] * ($taxPercentage) / 100));
                                $row['original_price_with_tax'] = strval($row['price'] + ($row['discounted_price'] * ($taxPercentage) / 100));
                            } else {
                                $row['tax_value'] = "";
                                $row['price_with_tax']  = strval($row['discounted_price']);
                                $row['original_price_with_tax'] = strval($row['price']);
                            }
                        }


                        if (!isset($groupedServices1[$providerId])) {
                            $originalCompanyName    = $row['company_name'];
                            $defaultCompanyName     = $originalCompanyName;
                            $translatedCompanyName  = $originalCompanyName;
                            $originalUsername = $row['partner_name'];
                            $defaultUsername = $originalUsername;
                            $translatedUsername = $originalUsername;

                            $partnerTranslations = $db->table('translated_partner_details')
                                ->select('language_code, company_name, username')
                                ->where('partner_id', $providerId)
                                ->whereIn('language_code', [$defaultLang, $requestLang])
                                ->get()->getResultArray();

                            foreach ($partnerTranslations as $t) {
                                if ($t['language_code'] === $defaultLang && !empty($t['company_name'])) {
                                    $defaultCompanyName = $t['company_name'];
                                    $defaultUsername = $t['username'];
                                }
                                if ($t['language_code'] === $requestLang && !empty($t['company_name'])) {
                                    $translatedCompanyName = $t['company_name'];
                                    $translatedUsername = $t['username'];
                                }
                            }

                            // fallback if requested language missing
                            if (
                                $translatedCompanyName === $originalCompanyName
                                && $defaultCompanyName !== $originalCompanyName
                            ) {
                                $translatedCompanyName = $defaultCompanyName;
                                $translatedUsername = $defaultUsername;
                            }

                            $groupedServices1[$providerId]['provider']['company_name'] = $defaultCompanyName;
                            $groupedServices1[$providerId]['provider']['username'] = $defaultUsername;
                            $groupedServices1[$providerId]['provider']['city'] = $row['city'];
                            $groupedServices1[$providerId]['provider']['latitude'] = $row['latitude'];
                            $groupedServices1[$providerId]['provider']['longitude'] = $row['longitude'];
                            $groupedServices1[$providerId]['provider']['id'] = $row['user_id'];
                            $groupedServices1[$providerId]['provider']['provider_slug'] = $row['provider_slug'];
                            $groupedServices1[$providerId]['provider']['image'] = $row['partner_image'];
                            $groupedServices1[$providerId]['provider']['banner_image'] = $row['banner'];
                            $groupedServices1[$providerId]['provider']['number_of_rating'] = $row['number_of_rating'];
                            $groupedServices1[$providerId]['provider']['total_rating'] = $row['provider_total_rating'];
                            $groupedServices1[$providerId]['provider']['average_rating'] = $row['average_rating'];
                            $groupedServices1[$providerId]['provider']['number_of_orders'] = $row['number_of_orders'];
                            $groupedServices1[$providerId]['provider']['distance'] = $row['distance'];
                            $groupedServices1[$providerId]['provider']['discount_type'] = $row['discount_type'];
                            $groupedServices1[$providerId]['provider']['discount'] = $row['discount'];
                            $groupedServices1[$providerId]['provider']['upto'] = $row['minimum_order_amount'];
                            $total_services_of_providers = fetch_details('services', ['user_id' => $providerId, 'at_store' => $row['provider_at_store'], 'at_doorstep' => $row['provider_at_doorstep']], ['id']);

                            $groupedServices1[$providerId]['provider']['total_services'] = count($total_services_of_providers);

                            if ($row['discount_type'] == 'percentage') {
                                $groupedServices1[$providerId]['provider']['upto'] =  $row['minimum_order_amount'];
                                unset($groupedServices1[$providerId]['provider']['discount_type']);
                            }
                            unset($row['minimum_order_amount']);
                            $groupedServices1[$providerId]['provider']['services'] = [];
                        }
                        $price = $row['price'];
                        $discountedPrice = $row['discounted_price'];
                        // Calculating the percentage off
                        $percentageOff = (($price - $discountedPrice) / $price) * 100;
                        // Rounding the result to 0 decimal places
                        $percentageOff = round($percentageOff);
                        $row['discount'] = strval($percentageOff);

                        $groupedServices1[$providerId]['provider']['services'][] = $row;
                    }
                }
                // print_r($groupedServices1);
                // die;
                if (!empty($groupedServices1)) {
                    // Apply translations to services data
                    foreach ($groupedServices1 as &$providerGroup) {
                        if (isset($providerGroup['provider']['services']) && is_array($providerGroup['provider']['services'])) {
                            $providerGroup['provider']['services'] = apply_translations_to_services_for_api($providerGroup['provider']['services']);

                            // Update category names with translations
                            $providerGroup['provider']['services'] = update_category_names_in_query_results($providerGroup['provider']['services']);
                        }
                        // Apply translations to provider company name
                        if (!empty($providerGroup['provider']['company_name'])) {
                            $providerTranslations = $this->getPartnerTranslations($providerGroup['provider']['id']);
                            if ($providerTranslations) {
                                $providerGroup['provider']['translated_company_name'] = $providerTranslations['company_name'] ?? $providerGroup['provider']['company_name'];
                                $providerGroup['provider']['translated_username'] = $providerTranslations['username'] ?? $providerGroup['provider']['username'];
                            } else {
                                $providerGroup['provider']['translated_company_name'] = $providerGroup['provider']['company_name'];
                                $providerGroup['provider']['translated_username'] = $providerGroup['provider']['username'];
                            }

                            // Also add translated_company_name to each individual service
                            if (isset($providerGroup['provider']['services']) && is_array($providerGroup['provider']['services'])) {
                                foreach ($providerGroup['provider']['services'] as &$service) {
                                    $service['translated_company_name'] = $providerGroup['provider']['translated_company_name'];
                                    // log_message('info', 'Added translated_company_name to service ID ' . $service['id'] . ': ' . $service['translated_company_name']);
                                    $service['translated_partner_name'] = $providerGroup['provider']['translated_username'];
                                    // log_message('info', 'Added translated_username to service ID ' . $service['id'] . ': ' . $service['translated_username']);
                                }
                                unset($service);
                            }
                        } else {
                            $providerGroup['provider']['translated_company_name'] = '';

                            // Set empty translated_company_name for services if no company name
                            if (isset($providerGroup['provider']['services']) && is_array($providerGroup['provider']['services'])) {
                                foreach ($providerGroup['provider']['services'] as &$service) {
                                    $service['translated_company_name'] = '';
                                    $service['translated_partner_name'] = '';
                                }
                                unset($service);
                            }
                        }
                    }
                    unset($providerGroup);

                    $data['total'] = count($groupedServices);
                    $data['Services'] = array_values($groupedServices1);
                } else {
                    $data['total'] = 0;
                    $data['Services'] = [];
                }
            }
            $response = [
                'error' => false,
                "data" => $data
            ];
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - search_services_providers()');
            return $this->response->setJSON($response);
        }
    }

    //Private helper methods
    /**
     * Get partner translations for a specific language
     * 
     * @param int $partnerId Partner ID
     * @return array|null Partner translations or null if not found
     */
    private function getPartnerTranslations(int $partnerId): ?array
    {
        try {
            $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            $currentLanguage = get_current_language_from_request();
            $defaultLanguage = get_default_language();

            // Try to get translation for current language
            $currentTranslation = $translationModel->getTranslatedDetails($partnerId, $currentLanguage);
            if ($currentTranslation && !empty($currentTranslation['company_name'])) {
                return $currentTranslation;
            }

            // Fallback to default language if current language translation doesn't exist
            if ($currentLanguage !== $defaultLanguage) {
                $defaultTranslation = $translationModel->getTranslatedDetails($partnerId, $defaultLanguage);
                if ($defaultTranslation && !empty($defaultTranslation['company_name'])) {
                    return $defaultTranslation;
                }
            }

            return null;
        } catch (\Exception $e) {
            log_message('error', 'Error getting partner translations: ' . $e->getMessage());
            return null;
        }
    }
}
