<?php

namespace App\Models;

use CodeIgniter\Model;

class Blog_category_model extends Model
{
    protected $table = 'blog_categories';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'slug',
        'name',          // Translatable: stores default language value in base table
        'status',
        'created_at',
        'updated_at'
    ];

    // Enable automatic timestamps using CI4 best practices
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Fix: Use 'datetime' string instead of accessing property
    protected $dateFormat = 'datetime';

    // Return type configuration
    protected $returnType = 'array';

    // Validation rules for blog categories following CI4 best practices
    // Note: 'name' validation is now handled in the controller for translations
    protected $validationRules = [
        'slug' => 'required|trim|max_length[255]|alpha_dash',
        'status' => 'permit_empty|in_list[0,1]'
    ];

    protected $validationMessages = [
        'slug' => [
            'required' => 'Please enter slug for category',
            'trim' => 'Category slug cannot be empty',
            'max_length' => 'Category slug cannot exceed 255 characters',
            'alpha_dash' => 'Slug can only contain alphanumeric characters, dashes and underscores'
        ],
        'status' => [
            'in_list' => 'Status must be either active (1) or inactive (0)'
        ]
    ];

    // Skip validation for certain operations
    protected $skipValidation = false;

    public function getValidationRules(array $options = []): array
    {
        return $this->validationRules;
    }

    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    /**
     * Search blog categories by translated name across all languages
     * This method is useful for finding categories by name in any language
     * 
     * @param string $searchTerm - The search term to look for in translated names
     * @param array $params - Additional parameters for filtering and pagination
     * @return array - Formatted response with total count and data
     */
    public function searchByTranslatedName($searchTerm, $params = [])
    {
        try {
            // Set default parameters
            $limit = (int) ($params['limit'] ?? 10);
            $offset = (int) ($params['offset'] ?? 0);
            $sort = esc($params['sort'] ?? 'id');
            $order = in_array(strtoupper($params['order'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($params['order']) : 'DESC';
            $status_filter = $params['status_filter'] ?? '';
            $language_code = $params['language_code'] ?? null;

            // Get current language for translations if not provided
            if ($language_code === null) {
                $language_code = function_exists('get_current_language') ? get_current_language() : 'en';
            }

            $db = \Config\Database::connect();

            // First, find all category IDs that have matching translated names
            $translationBuilder = $db->table('translated_blog_category_details');
            $translationBuilder->select('DISTINCT(blog_category_id)')
                ->like('name', $searchTerm);

            $matchingCategoryIds = $translationBuilder->get()->getResultArray();
            $categoryIds = array_column($matchingCategoryIds, 'blog_category_id');

            // If no matches found in translations, return empty result
            if (empty($categoryIds)) {
                return [
                    'total' => 0,
                    'data' => []
                ];
            }

            // Now get the main category data with filters
            $builder = $db->table('blog_categories bc');
            $builder->whereIn('bc.id', $categoryIds);

            // Add status filter if provided
            if ($status_filter !== '') {
                $builder->where('bc.status', $status_filter);
            }

            // Get total count for pagination
            $totalCount = $builder->countAllResults(false);

            // Build the main query with translations for the specified language
            $builder->select('bc.*, tbcd.name as translated_name')
                ->join('translated_blog_category_details tbcd', "tbcd.blog_category_id = bc.id AND tbcd.language_code = '$language_code'", 'left');

            // Apply sorting and pagination
            $builder->orderBy($sort, $order);
            $builder->limit($limit, $offset);

            // Get the results
            $results = $builder->get()->getResultArray();

            // Process results to handle fallback names
            foreach ($results as &$row) {
                // Use translated name if available, otherwise fallback to main table name
                $row['translated_name'] = !empty($row['translated_name']) ? $row['translated_name'] : ($row['name'] ?? '');
            }

            return [
                'total' => $totalCount,
                'data' => $results
            ];
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model searchByTranslatedName error: ' . $e->getMessage());
            return [
                'total' => 0,
                'data' => []
            ];
        }
    }

    /**
     * Get blog categories list with pagination, search and sorting using CI4 best practices
     * 
     * @param array $params - Parameters for pagination, search, sorting
     * @return array - Formatted response with total count and data
     */
    public function getBlogCategoriesList($params = [])
    {
        // Set default parameters with type casting
        $limit = (int) ($params['limit'] ?? 10);
        $offset = (int) ($params['offset'] ?? 0);
        $sort = esc($params['sort'] ?? 'id');
        $order = in_array(strtoupper($params['order'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($params['order']) : 'DESC';
        $search = $params['search'] ?? '';
        if (is_array($search) && isset($search['value'])) {
            $search = $search['value'];
        }
        $search = esc($search);
        $status_filter = $params['status_filter'] ?? '';
        $language_code = $params['language_code'] ?? null;

        // Get current language for translations if not provided
        if ($language_code === null) {
            $language_code = function_exists('get_current_language') ? get_current_language() : 'en';
        }

        // Get default language code for the name field
        $default_language = 'en'; // Default fallback
        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
        if (!empty($languages)) {
            $default_language = $languages[0]['code'];
        }

        // Build the query using database builder for translations
        $db = \Config\Database::connect();
        $builder = $db->table('blog_categories bc');

        // ENHANCED SEARCH: Now includes searching through translated names in all languages
        if (!empty($search)) {
            // Search through main table fields (slug, id)
            $builder->groupStart()
                ->like('bc.slug', $search)
                ->orLike('bc.id', $search)
                ->groupEnd();

            // Also search through translated names in all languages using subquery
            // This allows searching by name in any language while maintaining filters
            $subquery = $db->table('translated_blog_category_details')
                ->select('blog_category_id')
                ->like('name', $search)
                ->getCompiledSelect();

            $builder->orWhere("bc.id IN ($subquery)", null, false);
        }

        // IMPORTANT: All filters continue to work normally even when searching by translated names
        // Add status filter if provided (active/inactive categories)
        if ($status_filter !== '') {
            $builder->where('bc.status', $status_filter);
        }

        // Get total count for pagination (includes search results from both main table and translations)
        $totalCount = $builder->countAllResults(false);

        // Build the main query with translations for both default and requested languages
        // We need to get both default language (for name field) and requested language (for translated_name field)
        $builder->select('bc.*, 
                         tbcd_default.name as default_language_name,
                         tbcd_requested.name as translated_name')
            ->join('translated_blog_category_details tbcd_default', "tbcd_default.blog_category_id = bc.id AND tbcd_default.language_code = '$default_language'", 'left')
            ->join('translated_blog_category_details tbcd_requested', "tbcd_requested.blog_category_id = bc.id AND tbcd_requested.language_code = '$language_code'", 'left');

        // Apply sorting and pagination
        $builder->orderBy($sort, $order);
        $builder->limit($limit, $offset);

        // Get the results
        $results = $builder->get()->getResultArray();

        // Process results to handle fallback names properly
        foreach ($results as &$row) {
            // For the name field: use default language translation, fallback to main table name
            $row['name'] = !empty($row['default_language_name']) ? $row['default_language_name'] : ($row['name'] ?? '');

            // For the translated_name field: use requested language translation, fallback to default language, then main table
            if (!empty($row['translated_name'])) {
                // Requested language translation exists
                $row['translated_name'] = $row['translated_name'];
            } elseif (!empty($row['default_language_name'])) {
                // Fallback to default language translation
                $row['translated_name'] = $row['default_language_name'];
            } else {
                // Final fallback to main table name
                $row['translated_name'] = $row['name'] ?? '';
            }

            // Remove the temporary default_language_name field
            unset($row['default_language_name']);
        }

        return [
            'total' => $totalCount,
            'data' => $results
        ];
    }

    /**
     * Universal list method for fetching blog categories across all controllers
     * Supports both DataTables format and plain array format
     * Now includes enhanced search through translated names in all languages
     * 
     * @param array $params - Optional parameters array
     * @return mixed - JSON string for DataTables or array for other uses
     */
    public function list($params = [])
    {
        // Handle legacy parameters (backward compatibility)
        if (!is_array($params)) {
            // If called with old signature: list($search, $limit, $offset, $sort, $order, $where, $language_code)
            $args = func_get_args();
            $params = [
                'search' => $args[0] ?? '',
                'limit' => $args[1] ?? 10,
                'offset' => $args[2] ?? 0,
                'sort' => $args[3] ?? 'id',
                'order' => $args[4] ?? 'DESC',
                'where' => $args[5] ?? [],
                'language_code' => $args[6] ?? null
            ];
        }

        // Handle GET parameters for DataTables integration
        if (isset($_GET['offset'])) {
            $params['offset'] = (int) $_GET['offset'];
        }

        if (isset($_GET['search'])) {
            $search_param = $_GET['search'];
            if (is_array($search_param) && isset($search_param['value'])) {
                $params['search'] = $search_param['value'];
            } else if (!is_array($search_param) && $search_param != '') {
                $params['search'] = $search_param;
            }
        }

        if (isset($_GET['limit'])) {
            $params['limit'] = (int) $_GET['limit'];
        }

        if (isset($_GET['sort'])) {
            $params['sort'] = $_GET['sort'];
        }

        if (isset($_GET['order'])) {
            $params['order'] = $_GET['order'];
        }

        if (isset($_GET['category_filter'])) {
            $params['status_filter'] = $_GET['category_filter'];
        }

        // Set defaults
        $params = array_merge([
            'limit' => 10,
            'offset' => 0,
            'sort' => 'id',
            'order' => 'DESC',
            'search' => '',
            'status_filter' => '',
            'format' => 'datatable', // 'datatable', 'array', 'simple'
            'include_operations' => true,
            'search_type' => 'combined' // 'combined', 'translated_only', 'main_only'
        ], $params);

        // Get data using the main method - now includes search through translated names
        // The enhanced getBlogCategoriesList method will search through names in all languages
        // while maintaining filter functionality (status_filter, etc.)
        $result = $this->getBlogCategoriesList($params);

        // Format response based on requested format
        switch ($params['format']) {
            case 'simple':
                // Simple array format - just the data
                return $result['data'];

            case 'array':
                // Array format with total count
                return [
                    'total' => $result['total'],
                    'data' => $result['data']
                ];

            case 'datatable':
            default:
                // DataTables format (default for backward compatibility)
                return $this->formatForDataTable($result, $params['include_operations']);
        }
    }

    /**
     * Format data for DataTables display
     * 
     * @param array $result - Raw result from getBlogCategoriesList
     * @param bool $includeOperations - Whether to include operations column
     * @return string - JSON encoded response for DataTables
     */
    private function formatForDataTable($result, $includeOperations = true)
    {
        $bulkData = [
            'total' => $result['total'],
            'rows' => []
        ];

        // Format results for display
        foreach ($result['data'] as $row) {
            $tempRow = [
                'id' => $row['id'],
                'translated_name' => esc($row['translated_name'] ?? $row['name'] ?? ''),
                'slug' => esc($row['slug']),
                'status' => $row['status'] ?? 1,
                'created_at' => $row['created_at'] ?? ''
            ];

            // Add operations column for admin interface
            if ($includeOperations) {
                $tempRow['operations'] = $this->generateOperationsColumn($row['id']);
            }

            $bulkData['rows'][] = $tempRow;
        }

        return json_encode($bulkData);
    }

    /**
     * Create new blog category record using CI4 best practices
     * 
     * @param array $data - Blog category data
     * @return array - Response with success/error status
     */
    public function createCategory($data)
    {
        try {
            // Check for existing record with same name or slug
            $existingCheck = $this->checkExistingCategory($data);
            if ($existingCheck) {
                return [
                    'error' => true,
                    'message' => $existingCheck
                ];
            }

            // Set default status if not provided
            if (!isset($data['status'])) {
                $data['status'] = 1; // Active by default
            }

            // Remove timestamp fields if they exist in data (let CI4 handle them automatically)
            unset($data['created_at'], $data['updated_at']);

            // Insert the data using model's insert method with automatic validation
            $insertId = $this->insert($data);

            if ($insertId) {
                return [
                    'error' => false,
                    'message' => labels(DATA_SAVED_SUCCESSFULLY, 'Data saved successfully'),
                    'insert_id' => $insertId
                ];
            } else {
                return [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED, 'Error occurred'),
                    'validation_errors' => $this->errors()
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model createCategory error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')
            ];
        }
    }

    /**
     * Update existing blog category record using CI4 best practices
     * 
     * @param int $id - Record ID to update
     * @param array $data - Updated blog category data
     * @return array - Response with success/error status
     */
    public function updateCategory($id, $data)
    {

        try {
            // Check if record exists
            $existing = $this->find($id);
            if (!$existing) {
                return [
                    'error' => true,
                    'message' => labels(DATA_NOT_FOUND, 'Data not found')
                ];
            }

            // Check for duplicates when updating (excluding current record)
            $duplicateCheck = $this->checkDuplicateOnUpdate($id, $data, $existing);
            if ($duplicateCheck) {
                return [
                    'error' => true,
                    'message' => $duplicateCheck
                ];
            }

            // Remove timestamp fields if they exist in data (let CI4 handle them automatically)
            unset($data['created_at'], $data['updated_at']);

            // Update the record using CI4 model method with automatic validation
            $updated = $this->update($id, $data);

            if ($updated) {
                return [
                    'error' => false,
                    'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully')
                ];
            } else {
                return [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED, 'Error occurred'),
                    'validation_errors' => $this->errors()
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model updateCategory error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')
            ];
        }
    }

    /**
     * Delete blog category record using CI4 best practices
     * 
     * @param int $id - Record ID to delete
     * @return array - Response with success/error status
     */
    public function deleteCategory($id)
    {
        try {
            // Check if record exists
            $existing = $this->find($id);
            if (!$existing) {
                return [
                    'error' => true,
                    'message' => labels(DATA_NOT_FOUND, 'Data not found')
                ];
            }

            // Check if category is being used by any blog posts
            $isInUse = $this->isCategoryInUse($id);
            if ($isInUse) {
                return [
                    'error' => true,
                    'message' => labels(CANNOT_DELETE_CATEGORY_AS_IT_IS_BEING_USED_BY_BLOG_POSTS, 'Cannot delete category as it is being used by blog posts')
                ];
            }

            // Delete the record using CI4 model method
            $deleted = $this->delete($id);

            if ($deleted) {
                return [
                    'error' => false,
                    'message' => labels(DATA_DELETED_SUCCESSFULLY, 'Data deleted successfully'),
                    'deleted_record' => $existing
                ];
            } else {
                return [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED, 'Error occurred')
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model deleteCategory error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')
            ];
        }
    }

    /**
     * Get blog category by ID using CI4 best practices
     * 
     * @param int $id - Record ID
     * @return array|null - Blog category data or null if not found
     */
    public function getCategoryById($id)
    {
        try {
            return $this->find($id);
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model getCategoryById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get blog category by slug using CI4 best practices
     * 
     * @param string $slug - Category slug
     * @return array|null - Blog category data or null if not found
     */
    public function getCategoryBySlug($slug)
    {
        try {
            return $this->where('slug', $slug)->first();
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model getCategoryBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all active blog categories for dropdown/select options
     * 
     * @return array - Array of active categories
     */
    public function getActiveCategories()
    {
        try {
            return $this->where('status', 1)->orderBy('name', 'ASC')->findAll();
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model getActiveCategories error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if category name already exists
     * 
     * @param string $name - Category name
     * @param int $excludeId - ID to exclude from check (for updates)
     * @return bool - True if name exists, false otherwise
     */
    public function nameExists($name, $excludeId = null)
    {
        try {
            $builder = $this->where('name', $name);

            if ($excludeId) {
                $builder->where('id !=', $excludeId);
            }

            return $builder->first() !== null;
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model nameExists error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get formatted blog categories list for display in admin panel
     * Uses CI4 best practices for data formatting
     * 
     * @param array $params - Parameters for pagination, search, sorting
     * @return array - Formatted response ready for display
     */
    public function getFormattedCategoriesList($params = [])
    {
        helper('text');

        // Get raw data from the database
        $result = $this->getBlogCategoriesList($params);

        if (!$result || !isset($result['data'])) {
            return [
                'total' => 0,
                'rows' => []
            ];
        }

        // Format the results for display using CI4 string helpers
        $formattedResults = [];
        foreach ($result['data'] as $row) {
            $formattedResults[] = [
                'id' => $row['id'],
                'name' => esc($row['name']),
                'slug' => esc($row['slug']),
                'status' => $row['status'] == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>',
                'created_at' => date('M d, Y', strtotime($row['created_at'])),
                'updated_at' => date('M d, Y', strtotime($row['updated_at'])),
                'operations' => $this->generateOperationsColumn($row['id'])
            ];
        }

        return [
            'total' => $result['total'],
            'rows' => $formattedResults
        ];
    }

    /**
     * Check for existing category with same name or slug
     * 
     * @param array $data - Category data to check
     * @return string|false - Error message or false if no conflict
     */
    private function checkExistingCategory($data)
    {
        if (isset($data['name']) && $this->nameExists($data['name'])) {
            return labels(A_CATEGORY_WITH_THIS_NAME_ALREADY_EXISTS, 'A category with this name already exists. Please choose a different name.');
        }

        return false;
    }

    /**
     * Check for duplicates when updating
     * 
     * @param int $id - Current record ID
     * @param array $data - Data being updated
     * @param array $existing - Existing record data
     * @return string|false - Error message or false if no conflict
     */
    private function checkDuplicateOnUpdate($id, $data, $existing)
    {
        if (isset($data['name']) && $data['name'] !== $existing['name'] && $this->nameExists($data['name'], $id)) {
            return labels(A_CATEGORY_WITH_THIS_NAME_ALREADY_EXISTS, 'A category with this name already exists. Please choose a different name.');
        }

        return false;
    }

    /**
     * Check if category is being used by any blog posts
     * 
     * @param int $categoryId - Category ID to check
     * @return bool - True if category is in use, false otherwise
     */
    private function isCategoryInUse($categoryId)
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('blogs');
            $count = $builder->where('category_id', $categoryId)->countAllResults();

            return $count > 0;
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model isCategoryInUse error: ' . $e->getMessage());
            return false; // Assume not in use if we can't check
        }
    }

    /**
     * Generate operations column for admin interface
     * 
     * @param int $categoryId - Category ID
     * @return string - HTML for operations column
     */
    private function generateOperationsColumn($categoryId)
    {
        // Check if we're in admin context and have permissions
        $operations = '';

        if (isset($_SESSION['identity'])) {
            $db = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->whereIn('ug.group_id', [3, 1])
                ->where(['phone' => $_SESSION['identity']]);
            $user = $builder->get()->getResultArray();

            if (!empty($user)) {
                $permissions = function_exists('get_permission') ? get_permission($user[0]['id']) : [];

                $operations = '<div class="dropdown">
                    <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <button class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';

                if (isset($permissions['update']['blog']) && $permissions['update']['blog'] == 1) {
                    $operations .= '<a class="dropdown-item edit-category" data-id="' . $categoryId . '" data-toggle="modal" data-target="#update_modal" onclick="category_id(this)" title="' . labels('edit', 'Edit') . '">
                        <i class="fa fa-pen text-primary mr-1" aria-hidden="true"></i>' . labels('edit', 'Edit') . '</a>';
                }

                if (isset($permissions['delete']['blog']) && $permissions['delete']['blog'] == 1) {
                    $operations .= '<a class="dropdown-item delete-blog-category" data-id="' . $categoryId . '" onclick="category_id(this)">
                        <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                }

                $operations .= '</div></div>';
            }
        }

        return $operations;
    }

    /**
     * Before insert callback - CI4 best practice for data validation and manipulation
     * 
     * @param array $data
     * @return array
     */
    protected function beforeInsert(array $data)
    {
        // Sanitize and validate data before insertion
        if (isset($data['data']['name'])) {
            $data['data']['name'] = trim($data['data']['name']);
        }

        // Set default status if not provided
        if (!isset($data['data']['status'])) {
            $data['data']['status'] = 1;
        }

        return $data;
    }

    /**
     * Before update callback - CI4 best practice for data validation and manipulation
     * 
     * @param array $data
     * @return array
     */
    protected function beforeUpdate(array $data)
    {
        // Sanitize and validate data before update
        if (isset($data['data']['name'])) {
            $data['data']['name'] = trim($data['data']['name']);
        }

        return $data;
    }

    /**
     * Get category statistics for dashboard
     * 
     * @return array - Statistics data
     */
    public function getCategoryStats()
    {
        try {
            $totalCategories = $this->countAllResults();
            $activeCategories = $this->where('status', 1)->countAllResults();
            $inactiveCategories = $this->where('status', 0)->countAllResults();

            return [
                'total' => $totalCategories,
                'active' => $activeCategories,
                'inactive' => $inactiveCategories
            ];
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model getCategoryStats error: ' . $e->getMessage());
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0
            ];
        }
    }

    /**
     * Toggle category status (active/inactive)
     * 
     * @param int $id - Category ID
     * @return array - Response with success/error status
     */
    public function toggleStatus($id)
    {
        try {
            $category = $this->find($id);
            if (!$category) {
                return [
                    'error' => true,
                    'message' => labels(DATA_NOT_FOUND)
                ];
            }

            $newStatus = $category['status'] == 1 ? 0 : 1;
            $updated = $this->update($id, ['status' => $newStatus]);

            if ($updated) {
                $statusText = $newStatus == 1 ? 'activated' : 'deactivated';
                return [
                    'error' => false,
                    'message' => labels(DATA_UPDATED_SUCCESSFULLY),
                    'new_status' => $newStatus
                ];
            } else {
                return [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED)
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model toggleStatus error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG)
            ];
        }
    }

    /**
     * Get blog categories with translations for a specific language
     * 
     * @param string $languageCode The language code to get translations for
     * @param array $where Additional where conditions
     * @return array Array of categories with translated names
     */
    public function getCategoriesWithTranslations($languageCode = null, $where = [])
    {
        try {
            // If no language specified, get default language
            if (!$languageCode) {
                $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                $languageCode = $languages[0]['code'] ?? 'en';
            }

            // Get default language code for the name field
            $default_language = 'en'; // Default fallback
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $default_language = $languages[0]['code'];
            }

            // Get all categories
            $categories = $this->where($where)->findAll();

            // Get translations for both default and requested languages
            $translatedModel = new \App\Models\TranslatedBlogCategoryDetailsModel();
            $category_ids = array_column($categories, 'id');

            $default_translations = [];
            $requested_translations = [];

            if (!empty($category_ids)) {
                // Get default language translations
                $default_results = $translatedModel->whereIn('blog_category_id', $category_ids)
                    ->where('language_code', $default_language)
                    ->findAll();

                foreach ($default_results as $translation) {
                    $default_translations[$translation['blog_category_id']] = $translation['name'];
                }

                // Get requested language translations
                $requested_results = $translatedModel->whereIn('blog_category_id', $category_ids)
                    ->where('language_code', $languageCode)
                    ->findAll();

                foreach ($requested_results as $translation) {
                    $requested_translations[$translation['blog_category_id']] = $translation['name'];
                }
            }

            // Add translated names to categories following the same pattern
            foreach ($categories as &$category) {
                // For the name field: use default language translation, fallback to main table name
                $category['name'] = !empty($default_translations[$category['id']]) ? $default_translations[$category['id']] : ($category['name'] ?? '');

                // For the translated_name field: use requested language translation, fallback to default language, then main table
                if (!empty($requested_translations[$category['id']])) {
                    // Requested language translation exists
                    $category['translated_name'] = $requested_translations[$category['id']];
                } elseif (!empty($default_translations[$category['id']])) {
                    // Fallback to default language translation
                    $category['translated_name'] = $default_translations[$category['id']];
                } else {
                    // Final fallback to main table name
                    $category['translated_name'] = $category['name'] ?? '';
                }
            }

            return $categories;
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model getCategoriesWithTranslations error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single blog category with translation for a specific language
     * 
     * @param int $id The category ID
     * @param string $languageCode The language code to get translation for
     * @return array|null The category data with translated name or null if not found
     */
    public function getCategoryWithTranslation($id, $languageCode = null)
    {
        try {
            // Get the category data
            $category = $this->find($id);

            if (!$category) {
                return null;
            }

            // If no language specified, get default language
            if (!$languageCode) {
                $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                $languageCode = $languages[0]['code'] ?? 'en';
            }

            // Get default language code for the name field
            $default_language = 'en'; // Default fallback
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $default_language = $languages[0]['code'];
            }

            // Get translated name for requested language
            $translatedModel = new \App\Models\TranslatedBlogCategoryDetailsModel();
            $requested_translated_name = $translatedModel->getTranslation($id, $languageCode);

            // Get translated name for default language
            $default_translated_name = $translatedModel->getTranslation($id, $default_language);

            // For the name field: use default language translation, fallback to main table name
            $category['name'] = !empty($default_translated_name) ? $default_translated_name : ($category['name'] ?? '');

            // For the translated_name field: use requested language translation, fallback to default language, then main table
            if (!empty($requested_translated_name)) {
                // Requested language translation exists
                $category['translated_name'] = $requested_translated_name;
            } elseif (!empty($default_translated_name)) {
                // Fallback to default language translation
                $category['translated_name'] = $default_translated_name;
            } else {
                // Final fallback to main table name
                $category['translated_name'] = $category['name'] ?? '';
            }

            return $category;
        } catch (\Exception $e) {
            log_message('error', 'Blog Category Model getCategoryWithTranslation error: ' . $e->getMessage());
            return null;
        }
    }
}
