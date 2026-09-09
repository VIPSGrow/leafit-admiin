<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\Category_model;
use App\Models\Partners_model;
use App\Models\TranslatedCategoryDetails_model;

class CategoryApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $toDateTime;
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
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
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
            // Get language from Content-Language header for API requests
            $languageCode = get_current_language_from_request();

            $categories = new Category_model();
            $limit = !empty($this->request->getPost('limit')) ?  $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('sort'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where = [];
            if ($this->request->getPost('id')) {
                $where['id'] = $this->request->getPost('id');
            }
            if ($this->request->getPost('slug')) {
                $where['slug'] = $this->request->getPost('slug');
            }
            $where['parent_id'] = 0;

            // Get categories with translations for the specified language
            $data = $categories->list(true, $search, $limit, $offset, $sort, $order, $where, $languageCode);

            if (!empty($data['data'])) {
                // Apply translations to categories including parent names
                $data['data'] = apply_translations_to_categories_for_api($data['data'], ['name', 'parent_category_name']);
                return response_helper('Categories fetched successfully', false, $data['data'], 200, ['total' => $data['total']]);
            } else {
                return response_helper(labels(CATEGORIES_NOT_FOUND, 'categories not found'), false);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_categories()');
            return $this->response->setJSON($response);
        }
    }

    public function get_sub_categories()
    {
        try {
            // Get language from Content-Language header for API requests
            $languageCode = get_current_language_from_request();

            $validation =  \Config\Services::validation();
            $validation->setRules(
                [
                    'category_id' => 'required',
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
            $categories = new Category_model();
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $where = [];
            if ($this->request->getPost('id')) {
                $where['id'] = $this->request->getPost('id');
            }
            if ($this->request->getPost('slug')) {
                $where['slug'] = $this->request->getPost('slug');
            }
            if ($this->request->getPost('category_id')) {
                $where['parent_id'] = $this->request->getPost('category_id');
            }
            if (!exists(['parent_id' => $this->request->getPost('category_id')], 'categories')) {
                return response_helper(labels(NO_SUB_CATEGORIES_FOUND, 'no sub categories found'));
            }

            // Get sub-categories with translations for the specified language
            $data = $categories->list(true, $search, $limit, $offset, $sort, $order, $where, $languageCode);

            if (!empty($data['data'])) {
                // Apply translations to categories including parent names
                $data['data'] = apply_translations_to_categories_for_api($data['data'], ['name', 'parent_category_name']);
                return response_helper(labels(SUB_CATEGORIES_FETCHED_SUCCESSFULLY, "Sub Categories fetched successfully"), false, $data['data'], 200, ['total' => $data['total']]);
            } else {
                return response_helper(labels(SUB_CATEGORIES_NOT_FOUND, 'Sub categories not found'), false);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_sub_categories()');
            return $this->response->setJSON($response);
        }
    }

    public function get_all_categories()
    {
        try {
            $categories = new Category_model();


            $limit = !empty($this->request->getPost('limit')) ?  $this->request->getPost('limit') : '0';
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'DESC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where = [];
            if ($this->request->getPost('id')) {
                $where['id'] = $this->request->getPost('id');
            }
            if ($this->request->getPost('slug')) {
                $where['slug'] = $this->request->getPost('slug');
            }
            $data = $categories->list(true, $search, $limit, $offset, $sort, $order, $where);
            if (!empty($data['data'])) {
                // Apply translations to categories including parent names
                $data['data'] = apply_translations_to_categories_for_api($data['data'], ['name', 'parent_category_name']);
                return response_helper('Categories fetched successfully', false, $data['data'], 200, ['total' => $data['total']]);
            } else {
                return response_helper(labels(CATEGORIES_NOT_FOUND, 'categories not found'), false);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_all_categories()');
            return $this->response->setJSON($response);
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
     *       "id": 1,
     *       "category_name": "...",
     *       "translated_name": "...",
     *       "image": "...",
     *       "children": [...] or null
     *     }
     *   ]
     * }
     * 
     * Note: Slug is not included in partner API response
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
            // Include id, name, parent_id, image for response (no slug)
            $categoryModel = new Category_model();
            $allCategories = $categoryModel->select('id, name, parent_id, image, status')
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

            // Format final response - include id and other fields (no slug)
            $finalData = [];
            foreach ($rootCategories as $category) {
                $finalData[] = [
                    'id' => $category['id'],
                    'category_name' => $category['category_name'],
                    'translated_name' => $category['translated_name'],
                    'image' => $category['image'],
                    'status' => $category['status'],
                    'children' => $category['children'],
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
                date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/CategoryApiController.php - get_categories_hierarchical()'
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
            // Include id for each child (no slug)
            $children = [];
            if (isset($childrenMap[$currentId])) {
                foreach ($childrenMap[$currentId] as $childCategory) {
                    $childId = $childCategory['id'];
                    // Children should already be formatted (processed before parents)
                    if (isset($formattedMap[$childId])) {
                        $children[] = [
                            'id' => $formattedMap[$childId]['id'],
                            'name' => $formattedMap[$childId]['category_name'], // Use 'name' for children
                            'translated_name' => $formattedMap[$childId]['translated_name'],
                            'image' => $formattedMap[$childId]['image'],
                            'status' => $formattedMap[$childId]['status'],
                            'children' => $formattedMap[$childId]['children'],
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
     * Includes id, category_name, translated_name, and image.
     * Note: Slug is not included for partner API.
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
            'category_name' => $categoryName,
            'translated_name' => $translatedName,
            'image' => $imagePath,
            'status' => $category['status'],
            'children' => null, // Will be set later in hierarchical building
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
