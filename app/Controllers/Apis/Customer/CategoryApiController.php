<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\Category_model;
use App\Models\Partners_model;
use App\Models\TranslatedCategoryDetails_model;

class CategoryApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/get_categories",
        "api/v1/get_sub_categories",
        "api/v1/get_parent_categories",
        "api/v1/get_parent_category_slug",
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

    public function get_categories()
    {
        try {
            $is_landing_page = !empty($this->request->getPost('is_landing_page')) ? $this->request->getPost('is_landing_page') : 0;
            if ($is_landing_page != 1) {
                $validation = \Config\Services::validation();
                $validation->setRules(
                    [
                        'latitude' => [
                            'rules' => 'required',
                            'errors' => [
                                'required' => labels(LATITUDE_IS_REQUIRED, 'Latitude is required')
                            ]
                        ],
                        'longitude' => [
                            'rules' => 'required',
                            'errors' => [
                                'required' => labels(LONGITUDE_IS_REQUIRED, 'Longitude is required')
                            ]
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
            }
            $categories = new Category_model();
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $limit = ($this->request->getPost('limit') && !empty($this->request->getPost('limit'))) ? $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $where = [];
            if ($this->request->getPost('id')) {
                $where['id'] = $this->request->getPost('id');
            }
            if ($this->request->getPost('slug')) {
                $where['slug'] = $this->request->getPost('slug');
            }
            $where['parent_id'] = 0;

            // Get language from Content-Language header for API requests
            $languageCode = get_current_language_from_request();

            $data = $categories->list(true, $search, null, null, $sort, $order, $where, $languageCode);
            $db = \Config\Database::connect();
            $customer_latitude = $this->request->getPost('latitude') ?? "";
            $customer_longitude = $this->request->getPost('longitude') ?? "";
            $settings = get_settings('general_settings', true);
            $builder = $db->table('users u');
            $distance = isset($settings['max_serviceable_distance']) ? $settings['max_serviceable_distance'] : "50";
            if ($is_landing_page == 1) {
                $partners = $builder->Select("u.username,u.city,u.latitude,u.longitude,u.id")
                    ->join('users_groups ug', 'ug.user_id=u.id')
                    ->where('ug.group_id', '3')
                    ->where('u.latitude is  NOT NULL')
                    ->where('u.longitude is  NOT NULL')
                    ->get()->getResultArray();
            } else {
                $partners = $builder->Select("u.username,u.city,u.latitude,u.longitude,u.id,pd.max_serviceable_distance," . get_provider_distance_sql($customer_latitude, $customer_longitude, 'u.id') . " as distance")
                    ->join('users_groups ug', 'ug.user_id=u.id')
                    ->join('partner_details pd', 'pd.partner_id=u.id', 'left')
                    ->where('ug.group_id', '3')
                    ->where('u.latitude is  NOT NULL')
                    ->where('u.longitude is  NOT NULL')
                    ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $distance))
                    ->orderBy('distance')
                    ->get()->getResultArray();
            }
            if (!empty($partners)) {
                if (!empty($data['data'])) {
                    foreach ($data['data'] as $index => $category) {
                        // Use the same n-level deep logic as get_providers
                        // This ensures category provider count matches providers returned by get_providers
                        $category_id = getAllDescendantCategoryIds([$category['id']]);
                        $formatted_ids = array_map('strval', $category_id);

                        $partner_ids = get_partner_ids('category', 'category_id', $formatted_ids, true);

                        if (!empty($partner_ids)) {
                            // Use Partners_model to get the actual count with all filters applied
                            $Partners_model = new Partners_model();
                            $where = [
                                'ps.status' => 'active',
                                'pd.is_approved' => 1
                            ];

                            $additional_data = [];
                            if ($is_landing_page != 1 && $customer_latitude && $customer_longitude) {
                                $additional_data = [
                                    'latitude' => $customer_latitude,
                                    'longitude' => $customer_longitude,
                                    'max_serviceable_distance' => $distance
                                ];
                            }

                            $partners_data = $Partners_model->list(true, '', 0, 0, 'pd.id', 'ASC', $where, 'pd.partner_id', $partner_ids, $additional_data, 'yes', $languageCode);
                            $total_providers = $partners_data['total'] ?? 0;
                        } else {
                            $total_providers = 0;
                        }

                        $data['data'][$index]['total_providers'] = $total_providers;
                    }

                    // Apply translations to categories using the helper function
                    $data['data'] = apply_translations_to_categories_for_api($data['data']);

                    return response_helper(labels(CATEGORIES_FETCHED_SUCCESSFULLY, 'Categories fetched successfully'), false, $data['data'], 200, ['total' => $data['total']]);
                } else {
                    return response_helper(labels(CATEGORIES_NOT_FOUND, 'categories not found'), false);
                }
            } else {
                return response_helper(labels(CATEGORIES_NOT_FOUND, 'categories not found'), false);
            }
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_categories()');
            return $this->response->setJSON($response);
        }
    }

    public function get_sub_categories()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'latitude' => [
                        'rules' => 'required',
                        'errors' => [
                            'required' => labels(LATITUDE_IS_REQUIRED, 'Latitude is required')
                        ]
                    ],
                    'longitude' => [
                        'rules' => 'required',
                        'errors' => [
                            'required' => labels(LONGITUDE_IS_REQUIRED, 'Longitude is required')
                        ]
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
            $categories = new Category_model();
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where = [];
            if ($this->request->getPost('id')) {
                $where['id'] = $this->request->getPost('id');
            }
            if ($this->request->getPost('id')) {
                $where['status'] = 1;
            }
            if ($this->request->getPost('slug')) {
                $slug = $this->request->getPost('slug');
                $category_details = fetch_details('categories', ['slug' => $slug]);

                if (!empty($category_details)) {
                    $where['parent_id'] = $category_details[0]['id'];
                } else {
                    return response_helper(labels(CATEGORY_NOT_FOUND_WITH_GIVEN_SLUG, 'Category not found with given slug'));
                }
            } else if ($this->request->getPost('category_id')) {
                $where['parent_id'] = $this->request->getPost('category_id');
            }

            // Get language from Content-Language header for API requests
            $languageCode = get_current_language_from_request();

            $data = $categories->list(true, $search, null, null, $sort, $order, $where, $languageCode);

            $db = \Config\Database::connect();
            $customer_latitude = $this->request->getPost('latitude');
            $customer_longitude = $this->request->getPost('longitude');
            $settings = get_settings('general_settings', true);
            $builder = $db->table('users u');
            $distance = $settings['max_serviceable_distance'];
            $partners = $builder->Select("u.username,u.city,u.latitude,u.longitude,u.id,pd.max_serviceable_distance," . get_provider_distance_sql($customer_latitude, $customer_longitude, 'u.id') . " as distance")
                ->join('users_groups ug', 'ug.user_id=u.id')
                ->join('partner_details pd', 'pd.partner_id=u.id', 'left')
                ->where('ug.group_id', '3')
                ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $distance))
                ->orderBy('distance')
                ->get()->getResultArray();

            if (!empty($partners)) {
                if (!empty($data['data'])) {
                    // Provider count per subcategory, using the same inference as get_providers:
                    // a provider only counts for a subcategory if it has a service in that
                    // subcategory subtree (n-levels deep), is approved, has an active
                    // subscription, and is within the serviceable distance.
                    //
                    // Done in two set-based queries (tree expansion + provider lookup) instead
                    // of one-per-subcategory, to avoid an N+1.
                    $descendantMap = $categories->getDescendantMap(array_column($data['data'], 'id'));
                    $allCategoryIds = !empty($descendantMap) ? array_merge(...array_values($descendantMap)) : [];

                    $additional_data = [];
                    if ($customer_latitude && $customer_longitude) {
                        $additional_data = [
                            'latitude' => $customer_latitude,
                            'longitude' => $customer_longitude,
                            'max_serviceable_distance' => $distance,
                        ];
                    }

                    $Partners_model = new Partners_model();
                    $providersByCategory = $Partners_model->getActiveProviderIdsByCategory($allCategoryIds, $additional_data);

                    foreach ($data['data'] as $index => $sub_category) {
                        // Distinct providers across the subcategory's whole subtree.
                        $partnerSet = [];
                        foreach ($descendantMap[$sub_category['id']] ?? [] as $cid) {
                            foreach ($providersByCategory[$cid] ?? [] as $partner_id) {
                                $partnerSet[$partner_id] = true;
                            }
                        }
                        $data['data'][$index]['provider_count'] = count($partnerSet);
                    }

                    // Apply translations to categories using the helper function
                    $data['data'] = apply_translations_to_categories_for_api($data['data']);

                    return response_helper(labels(SUB_CATEGORIES_FETCHED_SUCCESSFULLY, 'Sub Categories fetched successfully'), false, $data['data'], 200, ['total' => $data['total']]);
                } else {
                    return response_helper(labels(SUB_CATEGORIES_NOT_FOUND, 'Sub categories not found'), false);
                }
            } else {
                return response_helper(labels(SUB_CATEGORIES_NOT_FOUND, 'Sub categories not found'), false);
            }
        } catch (\Exception $th) {

            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_sub_categories()');
            return $this->response->setJSON($response);
        }
    }

    public function get_parent_categories()
    {
        try {
            $request = $this->request->getPost();
            $sub_category_id = $request['sub_category_id'] ?? '';
            $slug = $request['slug'] ?? '';
            if (!exists(['id' => $sub_category_id], 'categories')) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(NO_SUBCATEGORY_FOUND, 'No Subcategory found'),
                ]);
            }
            $sub_category = fetch_details('categories', ['id' => $sub_category_id]);
            $parent_id = $sub_category[0]['parent_id'];
            if (!exists(['id' => $parent_id], 'categories')) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(NO_CATEGORY_FOUND, 'No Category found'),
                ]);
            }
            $category = fetch_details('categories', ['id' => $parent_id])[0];

            // Get translated category data for API response
            $categoryData = ['name' => $category['name'] ?? ''];
            $translatedCategoryData = get_translated_category_data_for_api($parent_id, $categoryData);
            $category['name'] = $translatedCategoryData['name'];
            $category['translated_name'] = $translatedCategoryData['translated_name'];
            $category['image'] = !empty($category['image'])
                ? service('fileService')->url($category['image'], 'categories')
                : '';
            $response = [
                'error' => false,
                'message' => empty($category) ? labels(NO_DATA_FOUND, 'No data found') : labels(CATEGORY_RECEIVED_SUCCESSFULLY, 'Category received successfully'),
                'data' => $category,
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - fetch_parent_categories()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function get_all_categories()
    {
        $categories = fetch_details('categories', ['status' => '1'], ['id', 'name', 'image']);
        $fileService = service('fileService');
        foreach ($categories as &$category) {
            $category['image'] = !empty($category['image'])
                ? $fileService->url($category['image'], 'categories')
                : '';
        }
        unset($category);

        // Apply translations to categories using the helper function
        $categories = apply_translations_to_categories_for_api($categories);

        return $this->response->setJSON([
            'error' => empty($categories),
            'message' => empty($categories) ? labels(NO_DATA_FOUND, 'No data found') : labels(CATEGORIES_RETRIEVED_SUCCESSFULLY, 'Categories retrieved successfully'),
            'data' => $categories,
        ]);
    }

    public function get_parent_category_slug()
    {
        try {
            $slug = $this->request->getPost('slug');

            // Get current category details
            $category_details = fetch_details('categories', ['slug' => $slug], ['id', 'name', 'slug', 'parent_id', 'image']);

            if (empty($category_details)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(CATEGORY_NOT_FOUND, 'Category not found')
                ]);
            }

            // Get all parent categories
            $parent_categories = [];
            $current_parent_id = $category_details[0]['parent_id'];

            while ($current_parent_id != 0) {
                $parent = fetch_details('categories', ['id' => $current_parent_id], ['id', 'name', 'slug', 'parent_id', 'image']);
                if (!empty($parent)) {
                    $parent_categories[] = $parent[0];
                    $current_parent_id = $parent[0]['parent_id'];
                } else {
                    break;
                }
            }

            // Apply translations to the main category
            $translatedCategoryData = get_translated_category_data_for_api($category_details[0]['id'], $category_details[0]);
            $category_details[0] = array_merge($category_details[0], $translatedCategoryData);

            // Apply translations to all parent categories
            foreach ($parent_categories as &$parent_category) {
                $translatedParentData = get_translated_category_data_for_api($parent_category['id'], $parent_category);
                $parent_category = array_merge($parent_category, $translatedParentData);
            }

            $category_details[0]['parent_categories'] = array_reverse($parent_categories);

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(PARENT_CATEGORY_FETCHED_SUCCESSFULLY, 'Parent Category Fetched Successfully'),
                'data' => $category_details
            ]);
        } catch (\Throwable $th) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')
            ]);
        }
    }

    /**
     * Get all categories in hierarchical structure
     * 
     * This endpoint returns all active categories in a nested hierarchical structure.
     * Uses bottom-up approach for efficient processing.
     * 
     * Response format:
     * {
     *   "error": false,
     *   "message": "...",
     *   "data": [
     *     {
     *       "category_name": "...",
     *       "translated_name": "...",
     *       "image": "...",
     *       "children": [...] or null
     *     }
     *   ]
     * }
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response with hierarchical categories
     */
    public function get_categories_hierarchical()
    {
        try {
            // Get language from Content-Language header for API requests
            $languageCode = get_current_language_from_request();

            // Fetch all active categories using the model
            // Using model builder to get all categories with status = 1
            // Include id, name, slug, parent_id, image for response
            $categoryModel = new Category_model();
            $allCategories = $categoryModel->select('id, name, slug, parent_id, image, status')
                ->where('status', 1)
                ->orderBy('parent_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();

            // If no categories found, return empty response
            if (empty($allCategories)) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(CATEGORIES_RETRIEVED_SUCCESSFULLY, 'Categories retrieved successfully'),
                    'data' => []
                ]);
            }

            // Build hierarchical structure using bottom-up approach
            $formattedData = $this->buildHierarchicalStructure($allCategories, $languageCode);

            // Return only root categories (parent_id = 0)
            $rootCategories = array_filter($formattedData, function ($category) {
                return $category['parent_id'] == 0;
            });

            // Format final response - include id, slug, and other fields
            $finalData = [];
            foreach ($rootCategories as $category) {
                $finalData[] = [
                    'id' => $category['id'],
                    'slug' => $category['slug'],
                    'category_name' => $category['category_name'],
                    'translated_name' => $category['translated_name'],
                    'image' => $category['image'],
                    'children' => $category['children']
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(CATEGORIES_RETRIEVED_SUCCESSFULLY, 'Categories retrieved successfully'),
                'data' => array_values($finalData)
            ]);
        } catch (\Throwable $th) {
            log_message('error', 'Error in get_categories_hierarchical: ' . $th->getMessage());
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/CategoryApiController.php - get_categories_hierarchical()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
        }
    }

    /**
     * Build hierarchical structure from flat category list
     * 
     * Uses bottom-up approach: processes leaf nodes first, then their parents.
     * This ensures children are fully formatted before being attached to parents.
     * 
     * @param array $categories Flat array of all categories
     * @param string $languageCode Language code for translations
     * @return array Hierarchical structure with formatted categories indexed by ID
     */
    private function buildHierarchicalStructure(array $categories, string $languageCode): array
    {
        // Get all category IDs for batch translation lookup
        $categoryIds = array_column($categories, 'id');

        // Build base names map for fallback (from main categories table)
        $baseNames = [];
        foreach ($categories as $category) {
            $baseNames[$category['id']] = $category['name'];
        }

        // Fetch translations using TranslatedCategoryDetails_model
        // Implements fallback: requested language -> default language -> base table
        $translatedNames = $this->getTranslatedNamesWithFallback($categoryIds, $languageCode, $baseNames);

        // Build children map: parent_id => [child categories]
        // This allows efficient lookup of children for each parent
        $childrenMap = [];
        foreach ($categories as $category) {
            $parentId = $category['parent_id'];
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = $category;
        }

        // Build category map by ID for efficient lookup
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category['id']] = $category;
        }

        // Build formatted categories map (will store formatted data by category ID)
        $formattedMap = [];

        // Bottom-up processing: identify leaf nodes first
        // Leaf nodes are categories that have no children
        $leafNodeIds = [];
        foreach ($categories as $category) {
            $categoryId = $category['id'];
            // If this category has no children, it's a leaf node
            if (!isset($childrenMap[$categoryId]) || empty($childrenMap[$categoryId])) {
                $leafNodeIds[] = $categoryId;
            }
        }

        // Process queue: start with leaf nodes
        $processQueue = $leafNodeIds;
        $processedIds = [];
        $pendingChildrenCount = []; // Track how many children are still pending for each parent

        // Initialize pending children count for all categories that have children
        foreach ($categories as $category) {
            $categoryId = $category['id'];
            if (isset($childrenMap[$categoryId]) && !empty($childrenMap[$categoryId])) {
                $pendingChildrenCount[$categoryId] = count($childrenMap[$categoryId]);
            }
        }

        // Process categories bottom-up
        while (!empty($processQueue)) {
            $currentId = array_shift($processQueue);

            // Skip if already processed
            if (in_array($currentId, $processedIds)) {
                continue;
            }

            // Get category data
            if (!isset($categoryMap[$currentId])) {
                continue;
            }
            $category = $categoryMap[$currentId];

            // Format this category
            $formattedCategory = $this->formatCategory(
                $category,
                $translatedNames,
                $baseNames
            );

            // Get and format children for this category
            // Children use 'name' field (not 'category_name') as per API specification
            // Include id and slug for each child
            $children = [];
            if (isset($childrenMap[$currentId])) {
                foreach ($childrenMap[$currentId] as $childCategory) {
                    $childId = $childCategory['id'];
                    // Children should already be formatted (processed before parents)
                    if (isset($formattedMap[$childId])) {
                        $children[] = [
                            'id' => $formattedMap[$childId]['id'],
                            'slug' => $formattedMap[$childId]['slug'],
                            'name' => $formattedMap[$childId]['category_name'], // Use 'name' for children
                            'translated_name' => $formattedMap[$childId]['translated_name'],
                            'image' => $formattedMap[$childId]['image'],
                            'children' => $formattedMap[$childId]['children']
                        ];
                    }
                }
            }

            // Attach children (null if empty, otherwise array)
            $formattedCategory['children'] = !empty($children) ? $children : null;

            // Store formatted category
            $formattedMap[$currentId] = $formattedCategory;
            $processedIds[] = $currentId;

            // Notify parent that one of its children has been processed
            $parentId = $category['parent_id'];
            if ($parentId != 0 && isset($pendingChildrenCount[$parentId])) {
                $pendingChildrenCount[$parentId]--;

                // If all children of parent are processed, add parent to queue
                if ($pendingChildrenCount[$parentId] == 0 && !in_array($parentId, $processedIds) && !in_array($parentId, $processQueue)) {
                    $processQueue[] = $parentId;
                }
            }
        }

        // Return all formatted categories
        return $formattedMap;
    }

    /**
     * Format a single category with translations and image path
     * 
     * Includes id, slug, category_name, translated_name, and image.
     * 
     * @param array $category Category data from database
     * @param array $translatedNames Map of category_id => translated name
     * @param array $baseNames Map of category_id => base name
     * @return array Formatted category data
     */
    private function formatCategory(array $category, array $translatedNames, array $baseNames): array
    {
        $categoryId = $category['id'];

        // Get category name (base name from main table)
        $categoryName = $baseNames[$categoryId] ?? '';

        // Get translated name with fallback
        // If translation exists, use it; otherwise use base name
        $translatedName = $translatedNames[$categoryId] ?? $categoryName;

        // Format image path based on file manager
        $imagePath = $this->formatCategoryImage($category['image']);

        return [
            'parent_id' => $category['parent_id'],
            'id' => $category['id'],
            'slug' => $category['slug'] ?? '',
            'category_name' => $categoryName,
            'translated_name' => $translatedName,
            'image' => $imagePath,
            'children' => null // Will be set later in hierarchical building
        ];
    }

    /**
     * Format category image path based on file manager configuration
     * 
     * Handles both local server and AWS S3 storage.
     * Returns empty string if image is not available.
     * 
     * @param string $imageName Image filename from database
     * @return string Complete image URL or empty string
     */
    private function formatCategoryImage(?string $imageName): string
    {
        if (empty($imageName)) {
            return '';
        }

        return service('fileService')->url($imageName, 'categories');
    }

    /**
     * Get translated names for categories with fallback logic
     * 
     * Fallback strategy:
     * 1. Try to get translation for requested language
     * 2. If not found, try default language translation
     * 3. If still not found, fallback to base table name
     * 
     * Uses TranslatedCategoryDetails_model for translation lookups.
     * 
     * @param array $categoryIds Array of category IDs
     * @param string $languageCode Requested language code
     * @param array $baseNames Map of category_id => base name from main table
     * @return array Map of category_id => translated name (with fallback)
     */
    private function getTranslatedNamesWithFallback(array $categoryIds, string $languageCode, array $baseNames): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        try {
            // Initialize translation model
            $translationModel = new TranslatedCategoryDetails_model();

            // Get default language for fallback
            $defaultLanguage = get_default_language();

            // Step 1: Try to get translations for requested language
            $requestedTranslations = $translationModel->getTranslationsForMultipleCategories($categoryIds, $languageCode);

            // Build result map with requested language translations
            $translatedNames = [];
            foreach ($requestedTranslations as $categoryId => $translation) {
                // Only use translation if name is not empty
                if (!empty(trim($translation['name'] ?? ''))) {
                    $translatedNames[$categoryId] = trim($translation['name']);
                }
            }

            // Step 2: If requested language is not default, try default language for missing categories
            if ($languageCode !== $defaultLanguage) {
                $missingCategoryIds = array_diff($categoryIds, array_keys($translatedNames));

                if (!empty($missingCategoryIds)) {
                    $defaultTranslations = $translationModel->getTranslationsForMultipleCategories(
                        array_values($missingCategoryIds),
                        $defaultLanguage
                    );

                    // Add default language translations for missing categories
                    foreach ($defaultTranslations as $categoryId => $translation) {
                        if (!empty(trim($translation['name'] ?? ''))) {
                            $translatedNames[$categoryId] = trim($translation['name']);
                        }
                    }
                }
            }

            // Step 3: Final fallback to base table names for categories still missing translations
            $categoriesWithoutTranslation = array_diff($categoryIds, array_keys($translatedNames));
            foreach ($categoriesWithoutTranslation as $categoryId) {
                // Use base name from main table as final fallback
                if (isset($baseNames[$categoryId]) && !empty(trim($baseNames[$categoryId]))) {
                    $translatedNames[$categoryId] = trim($baseNames[$categoryId]);
                }
            }

            return $translatedNames;
        } catch (\Exception $e) {
            // Log error and fallback to base names
            log_message('error', 'Error fetching translated category names: ' . $e->getMessage());

            // Return base names as fallback
            $fallbackNames = [];
            foreach ($categoryIds as $categoryId) {
                if (isset($baseNames[$categoryId]) && !empty(trim($baseNames[$categoryId]))) {
                    $fallbackNames[$categoryId] = trim($baseNames[$categoryId]);
                }
            }

            return $fallbackNames;
        }
    }
}
