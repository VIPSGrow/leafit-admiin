<?php

namespace App\Models;

use CodeIgniter\Model;

class Category_model extends Model
{
    public $admin_id;

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $dateFormat = 'datetime';
    public function __construct()
    {
        parent::__construct();
        $ionAuth = new \App\Libraries\CustomIonAuth();
        $this->admin_id = ($ionAuth->isAdmin()) ? $ionAuth->user()->row()->id : 0;
    }
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'image', 'parent_id', 'slug', 'admin_commission', 'status', 'dark_color', 'light_color'];

    /**
     * Get translated category name with fallback to base table
     * 
     * Fallback strategy:
     * 1. Try to get translation for requested language
     * 2. If not found, try default language translation
     * 3. If still not found, fallback to main table name
     * 
     * This ensures categories always have a name displayed, even when translations are missing
     * 
     * @param int $categoryId Category ID
     * @param string $languageCode Language code (optional, uses current language if not provided)
     * @return string Category name in the specified language with proper fallback
     */
    public function getTranslatedCategoryName(int $categoryId, ?string $languageCode = null): string
    {
        // If no language code provided, get current language
        if ($languageCode === null) {
            $languageCode = get_current_language();
        }

        try {
            $db = \Config\Database::connect();
            $defaultLanguage = get_default_language();

            // Step 1: Try to get translation for requested language
            $builder = $db->table('translated_category_details');
            $translation = $builder->select('name')
                ->where('category_id', $categoryId)
                ->where('language_code', $languageCode)
                ->get()
                ->getRowArray();

            // If translation exists and has a name, return it
            if ($translation && !empty(trim($translation['name']))) {
                return trim($translation['name']);
            }

            // Step 2: If requested language is not default, try default language translation
            if ($languageCode !== $defaultLanguage) {
                $defaultTranslation = $builder->select('name')
                    ->where('category_id', $categoryId)
                    ->where('language_code', $defaultLanguage)
                    ->get()
                    ->getRowArray();

                // If default language translation exists, return it
                if ($defaultTranslation && !empty(trim($defaultTranslation['name']))) {
                    return trim($defaultTranslation['name']);
                }
            }

            // Step 3: Final fallback to main table name
            // This ensures we always return a name, even if no translations exist
            $mainCategory = $this->select('name')->where('id', $categoryId)->first();
            return $mainCategory ? trim($mainCategory['name']) : '';
        } catch (\Exception $e) {
            // Log error and fallback to main table
            log_message('error', 'Error fetching translated category name: ' . $e->getMessage());

            // Final fallback: get name from main table
            try {
                $mainCategory = $this->select('name')->where('id', $categoryId)->first();
                return $mainCategory ? trim($mainCategory['name']) : '';
            } catch (\Exception $fallbackException) {
                log_message('error', 'Error in fallback to main table: ' . $fallbackException->getMessage());
                return '';
            }
        }
    }

    /**
     * Get translated names for multiple categories
     * 
     * @param array $categoryIds Array of category IDs
     * @param string $languageCode Language code (optional, uses current language if not provided)
     * @return array Array of category names indexed by category_id
     */
    public function getTranslatedCategoryNames(array $categoryIds, ?string $languageCode = null, bool $useFallback = true): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        // If no language code provided, get current language
        if ($languageCode === null) {
            $languageCode = get_current_language();
        }

        try {
            $db = \Config\Database::connect();
            $defaultLanguage = get_default_language();
            $needsDefaultFallback = $useFallback && ($languageCode !== $defaultLanguage);

            // Get translations for requested language
            $builder = $db->table('translated_category_details');
            $translations = $builder->select('category_id, name')
                ->whereIn('category_id', $categoryIds)
                ->where('language_code', $languageCode)
                ->get()
                ->getResultArray();

            // Create a map of category_id to translated name
            $translatedNames = [];
            foreach ($translations as $translation) {
                if (!empty(trim($translation['name']))) {
                    $translatedNames[$translation['category_id']] = trim($translation['name']);
                }
            }

            // Get default language translations for missing categories
            if ($needsDefaultFallback) {
                $missingCategoryIds = array_diff($categoryIds, array_keys($translatedNames));

                if (!empty($missingCategoryIds)) {
                    $defaultTranslations = $db->table('translated_category_details')
                        ->select('category_id, name')
                        ->whereIn('category_id', $missingCategoryIds)
                        ->where('language_code', $defaultLanguage)
                        ->get()
                        ->getResultArray();

                    foreach ($defaultTranslations as $translation) {
                        if (!empty(trim($translation['name']))) {
                            $translatedNames[$translation['category_id']] = trim($translation['name']);
                        }
                    }
                }
            }

            // Get names from main table for categories still missing translations
            $categoriesWithoutTranslation = array_diff($categoryIds, array_keys($translatedNames));
            if (!empty($categoriesWithoutTranslation)) {
                $mainCategories = $this->select('id, name')
                    ->whereIn('id', $categoriesWithoutTranslation)
                    ->findAll();

                foreach ($mainCategories as $category) {
                    if (!empty(trim($category['name']))) {
                        $translatedNames[$category['id']] = trim($category['name']);
                    }
                }
            }

            return $translatedNames;
        } catch (\Exception $e) {
            log_message('error', 'Error fetching translated category names: ' . $e->getMessage());

            // Fallback: get all names from main table
            $mainCategories = $this->select('id, name')
                ->whereIn('id', $categoryIds)
                ->findAll();

            $names = [];
            foreach ($mainCategories as $category) {
                if (!empty(trim($category['name']))) {
                    $names[$category['id']] = trim($category['name']);
                }
            }

            return $names;
        }
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], ?string $languageCode = null)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('categories');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];

        // Enhanced search: Search through both main table and translated names
        // This allows searching by category name in any language
        $hasSearch = false;
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $hasSearch = true;

            // Search fields in categories table
            $multipleWhere = [
                '`name`' => $search,
                '`id`' => $search
            ];
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'id') {
                $sort = "id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if ($from_app) {
            $where['status'] = 1;
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Lazy-tree parent_id filter (admin panel). Skip when searching so search spans full tree.
        // Accepts: 'null' or '0' or '' => roots only; numeric => children of that id.
        $treeParentApplied = false;
        $treeParentValue = null;
        if (!$hasSearch && !$from_app && empty($where['parent_id']) && array_key_exists('parent_id', $_GET)) {
            $rawParent = trim((string) $_GET['parent_id']);
            if ($rawParent === '' || $rawParent === 'null' || $rawParent === '0') {
                $builder->groupStart()->where('parent_id', null)->orWhere('parent_id', 0)->groupEnd();
                $treeParentApplied = true;
                $treeParentValue = 0;
            } elseif (ctype_digit($rawParent)) {
                $builder->where('parent_id', (int) $rawParent);
                $treeParentApplied = true;
                $treeParentValue = (int) $rawParent;
            }
        }

        // Apply search conditions: translations table first, then base table
        if ($hasSearch) {
            $builder->groupStart();

            // 1. Search through translated names in all languages using subquery
            $subquery = $db->table('translated_category_details')
                ->select('category_id')
                ->like('name', $search)
                ->getCompiledSelect();
            $builder->where("id IN ($subquery)", null, false);

            // 2. Then search in base table fields (name and id)
            if (!empty($multipleWhere)) {
                $builder->orLike($multipleWhere);
            }

            $builder->groupEnd();
        }

        $builder->select('COUNT(id) as `total` ');
        if (isset($_GET['category_filter']) && $_GET['category_filter'] != '') {
            $builder->where('status', $_GET['category_filter']);
        }
        $order_count = $builder->get()->getResultArray();
        $total = $order_count[0]['total'];

        // Reset builder for data query
        $builder = $db->table('categories');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Re-apply lazy-tree parent_id filter on data query
        if ($treeParentApplied) {
            if ($treeParentValue === 0) {
                $builder->groupStart()->where('parent_id', null)->orWhere('parent_id', 0)->groupEnd();
            } else {
                $builder->where('parent_id', $treeParentValue);
            }
        }

        // Apply same search conditions for data query
        if ($hasSearch) {
            $builder->groupStart();

            // 1. Search through translated names in all languages using subquery
            $subquery = $db->table('translated_category_details')
                ->select('category_id')
                ->like('name', $search)
                ->getCompiledSelect();
            $builder->where("id IN ($subquery)", null, false);

            // 2. Then search in base table fields (name and id)
            if (!empty($multipleWhere)) {
                $builder->orLike($multipleWhere);
            }

            $builder->groupEnd();
        }

        $builder->select('*');
        if (isset($_GET['category_filter']) && $_GET['category_filter'] != '') {
            $builder->where('status', $_GET['category_filter']);
        }
        $category_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        if ($from_app == false) {
            $db = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->whereIn('ug.group_id', [3, 1])
                ->where(['phone' => $_SESSION['identity']]);
            $user1 = $builder->get()->getResultArray();
            $permissions = get_permission($user1[0]['id']);
        }
        $fileService = new \App\Services\utility\FileService();

        // Get all category IDs for batch translation lookup
        $categoryIds = array_column($category_record, 'id');

        // Batch-fetch children counts for the returned categories
        $childrenCounts = [];
        if (!empty($categoryIds)) {
            $countRows = $db->table('categories')
                ->select('parent_id, COUNT(id) as cnt')
                ->whereIn('parent_id', $categoryIds)
                ->groupBy('parent_id')
                ->get()
                ->getResultArray();
            foreach ($countRows as $cr) {
                $childrenCounts[(int) $cr['parent_id']] = (int) $cr['cnt'];
            }
        }

        // Get default language for main name field
        $defaultLanguage = get_default_language();
        $defaultLanguageNames = $this->getTranslatedCategoryNames($categoryIds, $defaultLanguage);

        // Get current language names for display
        $currentLanguage = $languageCode ?: get_current_language();
        $currentLanguageNames = $this->getTranslatedCategoryNames($categoryIds, $currentLanguage);

        // Get requested language for translated name field
        $requestedLanguageNames = [];
        if ($languageCode && $languageCode !== $defaultLanguage) {
            $requestedLanguageNames = $this->getTranslatedCategoryNames($categoryIds, $languageCode);
        }

        foreach ($category_record as $row) {
            if ($from_app == false) {

                $imageTag = '<img height="60px" width="70px" style="padding:2px" class="rounded" src="%s" alt="">';
                $linkTag = '<a href="%s" data-lightbox="image-1">' . $imageTag . '</a>';
                $defaultImage = base_url('public/backend/assets/default.png');
                $imageUrl = $fileService->url($row['image'], 'categories');
                if ($imageUrl !== $defaultImage) {
                    $category_image = sprintf($linkTag, $imageUrl, $imageUrl);
                } else {
                    $category_image = sprintf($imageTag, $defaultImage);
                }

                $operations = '';
                if ($from_app == false) {
                    if ($this->admin_id != 0) {
                        if ($permissions['update']['categories'] == 1) {
                            // Edit button - Category_events handler will fetch data and populate modal
                            $operations .= '<button type="button" class="btn btn-sm btn-outline-primary edite-Category mr-1" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal" title="' . labels('edit', 'Edit') . '"><i class="fa fa-pen" aria-hidden="true"></i></button>';
                        }
                        if ($permissions['delete']['categories'] == 1) {
                            $operations .= '<button type="button" class="btn btn-sm btn-outline-danger delete_orders delete-Category" data-id="' . $row['id'] . '" onclick="category_id(this)" title="' . labels('delete', 'Delete') . '"><i class="fa fa-trash"></i></button>';
                        }
                    }
                }
            } else {
                $imageUrl = $fileService->url($row['image'], 'categories');
                $category_image = ($imageUrl !== base_url('public/backend/assets/default.png')) ? $imageUrl : '';
            }
            $status = ($row['status'] == 1) ? 'Enable' : 'Disable';

            // Get current language name for main field with fallback logic
            $currentName = '';
            if (isset($currentLanguageNames[$row['id']]) && !empty($currentLanguageNames[$row['id']])) {
                // Use current language translation if available
                $currentName = $currentLanguageNames[$row['id']];
            } else if (isset($defaultLanguageNames[$row['id']]) && !empty($defaultLanguageNames[$row['id']])) {
                // Fallback to default language translation
                $currentName = $defaultLanguageNames[$row['id']];
            } else {
                // Final fallback to main table name (for backward compatibility)
                $currentName = $row['name'];
            }

            // Get requested language name for translated field
            $translatedName = '';
            if ($languageCode && $languageCode !== $currentLanguage && isset($requestedLanguageNames[$row['id']])) {
                $translatedName = $requestedLanguageNames[$row['id']];
            } else if ($languageCode === $currentLanguage) {
                // For same language, both fields should have the same value
                $translatedName = $currentName;
            }

            $parent_category_name = '';
            $parent_translated_name = '';
            if (!empty($row['parent_id'])) {
                // Get current language parent category name with fallback
                $parentCurrentName = $this->getTranslatedCategoryName($row['parent_id'], $currentLanguage);
                if (empty($parentCurrentName)) {
                    $parentCurrentName = $this->getTranslatedCategoryName($row['parent_id'], $defaultLanguage);
                }
                if (empty($parentCurrentName)) {
                    // Final fallback to main table
                    $parentCategory = $this->select('name')->where('id', $row['parent_id'])->first();
                    $parentCurrentName = $parentCategory ? $parentCategory['name'] : '';
                }
                $parent_category_name = $parentCurrentName;

                // Get requested language parent category name
                if ($languageCode && $languageCode !== $currentLanguage) {
                    $parentRequestedName = $this->getTranslatedCategoryName($row['parent_id'], $languageCode);
                    $parent_translated_name = !empty($parentRequestedName) ? $parentRequestedName : '';
                } else if ($languageCode === $currentLanguage) {
                    // For same language, both fields should have the same value
                    $parent_translated_name = $parent_category_name;
                }
            }
            $childCount = $childrenCounts[(int) $row['id']] ?? 0;
            $tempRow['id'] = $row['id'];
            $tempRow['children_count'] = $childCount;
            $tempRow['has_children'] = $childCount > 0;
            $tempRow['name'] = $currentName;
            $tempRow['translated_name'] = $translatedName;
            $tempRow['slug'] = $row['slug'];
            $tempRow['parent_id'] = $row['parent_id'];
            $tempRow['parent_category_name'] = ($parent_category_name != '') ? $parent_category_name : 'No Parent found';
            $tempRow['parent_translated_name'] = $parent_translated_name;
            $tempRow['category_image'] = $category_image;
            $tempRow['image'] = $row['image'] ?? '';
            $tempRow['admin_commission'] = $row['admin_commission'];
            $tempRow['status'] = $row['status'];
            $tempRow['dark_color'] = $row['dark_color'];
            $tempRow['light_color'] = $row['light_color'];
            if ($from_app == false) {
                $tempRow['admin_commission'] = $row['admin_commission'];
                $tempRow['created_at'] = $row['created_at'];
                $tempRow['dark_color'] = $row['dark_color'];
                $tempRow['light_color'] = $row['light_color'];
                $tempRow['dark_color_format'] = ($row['dark_color'] == "") ? 'No color' : ' <div style="border-radius: 30px;width: 80px; height: 20px;background-color: ' . $row['dark_color'] . '"> </div>';
                $tempRow['light_color_format'] = ($row['light_color'] == "") ? 'No color' : ' <div style="border-radius: 30px;width: 80px; height: 20px;background-color: ' . $row['light_color'] . '"> </div>';
                $tempRow['status'] = ($row['status'] == 1) ? "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>Active
                </div>" : "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>Deactive
                </div>";
                $tempRow['og_status'] = $row['status'] == 1;
                $tempRow['operations'] = $operations;
            }
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        }
    }

    /**
     * Resolve each root category id to its full subtree (itself + all descendants,
     * n-levels deep). Loads the whole category tree in a single query and walks it
     * in PHP, so resolving many roots costs one query instead of one per root.
     *
     * @param array $rootIds Category ids to expand.
     * @return array Map of [root_id => [subtree_id, ...]] (root included).
     */
    public function getDescendantMap(array $rootIds): array
    {
        $rootIds = array_values(array_unique(array_map('intval', $rootIds)));
        if (empty($rootIds)) {
            return [];
        }

        $rows = $this->select('id, parent_id')->findAll();
        $childrenByParent = [];
        foreach ($rows as $row) {
            $childrenByParent[(int) $row['parent_id']][] = (int) $row['id'];
        }

        $map = [];
        foreach ($rootIds as $root) {
            $stack = [$root];
            $collected = [];
            while (!empty($stack)) {
                $current = array_pop($stack);
                if (isset($collected[$current])) {
                    continue;
                }
                $collected[$current] = true;
                foreach ($childrenByParent[$current] ?? [] as $childId) {
                    $stack[] = $childId;
                }
            }
            $map[$root] = array_keys($collected);
        }
        return $map;
    }
}
