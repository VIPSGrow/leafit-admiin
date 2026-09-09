<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Services\utility\SlugService;

/**
 * Blog Model - Handles CRUD and listing for blogs using CI4 best practices
 *
 * Follows modular, clean, and well-documented code style.
 */
class Blog_model extends Model
{
    protected $table = 'blogs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'slug',           // Non-translatable: URL identifier
        'category_id',    // Non-translatable: foreign key reference  
        'image',          // Non-translatable: file path (same across languages)
        'title',          // Translatable: stores default language value in base table
        'short_description', // Translatable: stores default language value in base table
        'description',    // Translatable: stores default language value in base table
        'created_at',     // Non-translatable: timestamp
        'updated_at',     // Non-translatable: timestamp
        // Note: translatable fields (title, short_description, description) 
        // are stored in both:
        // 1. Base table (blogs): Contains default language values for quick access
        // 2. Translations table (translated_blog_details): Contains ALL languages including default
        // Tags are stored separately in blog_tags and blog_tag_map tables
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $dateFormat    = 'datetime';
    protected $returnType    = 'array';

    // Validation rules for non-translatable fields only
    // Translatable fields are validated separately in the controller
    protected $validationRules = [
        'slug' => 'required|trim|max_length[255]|alpha_dash',
        'category_id' => 'required|integer',
    ];
    protected $validationMessages = [
        'slug' => [
            'required' => 'Please enter slug for blog',
        ],
        'category_id' => [
            'required' => 'Please select category for blog',
            'integer' => 'Category ID must be an integer',
        ],
    ];
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
     * List blogs with pagination, search, and sorting
     *
     * @param array $params
     * @return array
     */
    public function list($params = [])
    {
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
            $params['category_filter'] = $_GET['category_filter'];
        }

        if (isset($_GET['tag_filter'])) {
            $params['tag_filter'] = $_GET['tag_filter'];
        }

        // Set default parameters
        $params = array_merge([
            'limit' => 10,
            'offset' => 0,
            'sort' => 'id',
            'order' => 'DESC',
            'search' => '',
            'category_filter' => '',
            'tag_filter' => '', // Add default tag filter parameter
            'format' => 'datatable', // 'datatable', 'array', 'simple'
            'include_operations' => true
        ], $params);

        // Get data using the main query method
        $result = $this->getBlogsList($params);

        // Format response based on requested format
        switch ($params['format']) {
            case 'simple':
                return $result['data'];

            case 'array':
                return [
                    'total' => $result['total'],
                    'data' => $result['data']
                ];
            case 'datatable':
            default:
                return $this->formatForDataTable($result, $params['include_operations']);
        }
    }

    /**
     * Create a new blog post
     * @param array $data
     * @return array
     */
    public function createBlog($data)
    {
        try {
            // Remove timestamps if present
            unset($data['created_at'], $data['updated_at']);
            // Ensure short_description is set (for backward compatibility)
            if (!isset($data['short_description'])) {
                $data['short_description'] = null; // Added: default null
            }
            $insertId = $this->insert($data);
            if ($insertId) {
                // Process tags and map them to this blog
                if (isset($data['tags'])) {
                    $this->processTags($data['tags'], $insertId);
                }
                return [
                    'error' => false,
                    'message' => 'Blog created successfully',
                    'insert_id' => $insertId
                ];
            } else {
                return [
                    'error' => true,
                    'message' => 'Failed to create blog',
                    'validation_errors' => $this->errors()
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Blog Model createBlog error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Database error occurred while creating blog'
            ];
        }
    }

    /**
     * Update an existing blog post, including validation and file upload
     * @param int $id
     * @param array $data
     * @param array $files
     * @return array
     */
    public function updateBlog($id, $data, $files = [])
    {
        try {
            $disk = fetch_current_file_manager();
            $existing = $this->find($id);
            if (!$existing) {
                return [
                    'error' => true,
                    'message' => 'Blog record not found'
                ];
            }

            // Handle image upload if present
            if (isset($files['image']) && $files['image'] && $files['image']->isValid()) {
                // Delete old image if exists
                if (!empty($existing['image'])) {
                    delete_file_based_on_server('blogs', $existing['image'], $disk);
                }
                // Upload new image
                if (!is_dir(FCPATH . 'public/uploads/blogs/images/')) {
                    mkdir(FCPATH . 'public/uploads/blogs/images/', 0775, true);
                }
                $result = upload_file($files['image'], 'public/uploads/blogs/images/', 'Failed to create blogs/images folders', 'blogs/images');
                if ($result['error'] == false) {
                    $data['image'] = $result['file_name'];
                } else {
                    return [
                        'error' => true,
                        'message' => $result['message']
                    ];
                }
            } else {
                // Keep old image if not replaced
                $data['image'] = $existing['image'];
            }
            // Ensure short_description is set (for backward compatibility)
            if (!isset($data['short_description'])) {
                $data['short_description'] = $existing['short_description'] ?? null; // Added: keep old value if not set
            }
            
            unset($data['created_at'], $data['updated_at']);
            $updated = $this->update($id, $data);
            if ($updated) {
                // Process tags and update mapping
                if (isset($data['tags'])) {
                    $this->processTags($data['tags'], $id);
                }
                return [
                    'error' => false,
                    'message' => 'Blog updated successfully'
                ];
            } else {
                return [
                    'error' => true,
                    'message' => 'Failed to update blog',
                    'validation_errors' => $this->errors()
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Blog Model updateBlog error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Database error occurred while updating blog'
            ];
        }
    }

    /**
     * Delete a blog post
     * @param int $id
     * @return array
     */
    public function deleteBlog($id)
    {
        try {
            $existing = $this->find($id);
            if (!$existing) {
                return [
                    'error' => true,
                    'message' => 'Blog record not found'
                ];
            }
            $deleted = $this->delete($id);
            if ($deleted) {
                return [
                    'error' => false,
                    'message' => 'Blog deleted successfully',
                    'deleted_record' => $existing
                ];
            } else {
                return [
                    'error' => true,
                    'message' => 'Failed to delete blog'
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Blog Model deleteBlog error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Database error occurred while deleting blog'
            ];
        }
    }

    /**
     * Get blog by ID
     * @param int $id
     * @return array|null
     */
    public function getBlogById($id)
    {
        try {
            return $this->find($id);
        } catch (\Exception $e) {
            log_message('error', 'Blog Model getBlogById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get blog by slug
     * @param string $slug
     * @return array|null
     */
    public function getBlogBySlug($slug)
    {
        try {
            return $this->where('slug', $slug)->first();
        } catch (\Exception $e) {
            log_message('error', 'Blog Model getBlogBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get blog by ID with category details and translations
     * @param int $id
     * @param string $language_code Language code for translations (default: 'en')
     * @return array|null
     */
    public function getBlogByIdWithCategory($id, $language_code = 'en')
    {
        try {
            $result = $this->select('blogs.*, blog_categories.name as category_name, blog_categories.slug as category_slug')
                ->join('blog_categories', 'blog_categories.id = blogs.category_id', 'left')
                ->where('blogs.id', $id)
                ->first();

            // Add translated category name if result exists
            if ($result && isset($result['category_id'])) {
                $translationModel = new \App\Models\TranslatedBlogCategoryDetailsModel();
                $translation = $translationModel->getTranslation($result['category_id'], $language_code);
                $result['translated_category_name'] = $translation ?: $result['category_name'];
            }

            // Add translated blog fields if result exists
            if ($result) {
                $result = $this->addBlogTranslations($result, $language_code);
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Blog Model getBlogByIdWithCategory error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get blog by slug with category details and translations
     * @param string $slug
     * @param string $language_code Language code for translations (default: 'en')
     * @return array|null
     */
    public function getBlogBySlugWithCategory($slug, $language_code = 'en')
    {
        try {
            $result = $this->select('blogs.*, blog_categories.name as category_name, blog_categories.slug as category_slug')
                ->join('blog_categories', 'blog_categories.id = blogs.category_id', 'left')
                ->where('blogs.slug', $slug)
                ->first();

            // Add translated category name if result exists
            if ($result && isset($result['category_id'])) {
                $translationModel = new \App\Models\TranslatedBlogCategoryDetailsModel();
                $translation = $translationModel->getTranslation($result['category_id'], $language_code);
                $result['translated_category_name'] = $translation ?: $result['category_name'];
            }

            // Add translated blog fields if result exists
            if ($result) {
                $result = $this->addBlogTranslations($result, $language_code);
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Blog Model getBlogBySlugWithCategory error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all active blogs (for dropdowns, etc.)
     * @return array
     */
    public function getActiveBlogs()
    {
        try {
            return $this->orderBy('title', 'ASC')->findAll();
        } catch (\Exception $e) {
            log_message('error', 'Blog Model getActiveBlogs error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all tags (name and slug) for a given blog with multi-language support
     * This method returns ONLY tags that are associated with the specific blog ID
     * and filters translations STRICTLY by the requested language code
     * 
     * IMPORTANT: This method returns ONLY tags in the requested language.
     * No fallback to default language - if no tags exist in requested language, returns empty array.
     * 
     * @param int $blogId Blog ID (will be cast to integer for safety)
     * @param string $language_code Language code for translations (default: 'en')
     * @return array Array of tags with translated names (only tags for this blog in requested language)
     */
    public function getTagsForBlog($blogId, $language_code = 'en')
    {
        // Ensure blog_id is an integer to prevent type mismatch issues
        $blogId = (int) $blogId;

        // Validate that blog_id is a positive integer
        if ($blogId <= 0) {
            return [];
        }

        // Validate and sanitize language code
        // Convert to lowercase and trim to ensure consistent comparison
        // Also validate that it only contains alphanumeric characters for security
        if (empty($language_code)) {
            $language_code = 'en';
        }
        $language_code = strtolower(trim($language_code));
        // Additional validation: only allow alphanumeric characters and underscore
        // This prevents SQL injection while allowing valid language codes like 'en', 'hi', 'gu', etc.
        if (!preg_match('/^[a-z0-9_]+$/', $language_code)) {
            $language_code = 'en'; // Fallback to 'en' if invalid format
        }

        $db = \Config\Database::connect();

        // First, get all tag IDs that are mapped to this blog
        $tagIds = $db->table('blog_tag_map')
            ->where('blog_id', $blogId)
            ->select('tag_id')
            ->get()
            ->getResultArray();

        if (empty($tagIds)) {
            return [];
        }

        // Extract tag IDs into a simple array
        $tagIdArray = array_column($tagIds, 'tag_id');

        // Get tags ONLY in the requested language
        // Use INNER JOIN to ensure we ONLY get tags that have translations in the requested language
        // This is critical - we must use INNER JOIN, not LEFT JOIN, to exclude tags without translations
        // Filter by language_code in the JOIN condition itself (consistent with other models in codebase)
        // This ensures only tags with translations in the requested language are included
        // Language code is already sanitized (lowercased and trimmed) above, so safe for direct use
        $requestedTagsQuery = $db->table('blog_tags bt')
            ->select('bt.id, bt.name, bt.slug, tbtd.name as translated_name')
            ->join('translated_blog_tag_details tbtd', "tbtd.tag_id = bt.id AND tbtd.language_code = '{$language_code}'", 'inner')
            ->whereIn('bt.id', $tagIdArray)
            ->where('tbtd.name !=', '')
            ->where('tbtd.name IS NOT NULL')
            ->distinct() // Ensure no duplicate tags
            ->get()
            ->getResultArray();

        // If no tags found in requested language, return empty array (no fallback)
        if (empty($requestedTagsQuery)) {
            return [];
        }

        // Format the response with only requested language translations
        // IMPORTANT: Use translated_name as the primary name field to avoid returning cryptic identifiers
        // Cryptic identifiers like "tag_68c25d4434aa5" are internal identifiers and should not be exposed in API
        $filteredTags = [];
        foreach ($requestedTagsQuery as $tag) {
            $translatedName = trim($tag['translated_name'] ?? '');
            $baseName = trim($tag['name'] ?? '');

            // Only add tag if we have a valid translation in requested language
            if (!empty($translatedName)) {
                // Check if base name is a cryptic identifier (starts with "tag_" followed by alphanumeric)
                // If it's a cryptic identifier, use only translated_name; otherwise use translated_name as primary
                $isCrypticIdentifier = preg_match('/^tag_[a-z0-9]+$/i', $baseName);

                // Use translated_name as the primary name field
                // Only include base name if it's not a cryptic identifier (for backward compatibility)
                $tagData = [
                    'id' => (int) $tag['id'],
                    'name' => $translatedName, // Use translated name as primary name (not cryptic identifier)
                    'slug' => $tag['slug'],
                ];

                // Only include base name if it's not a cryptic identifier
                // This helps with backward compatibility while avoiding cryptic identifiers
                if (!$isCrypticIdentifier && !empty($baseName) && $baseName !== $translatedName) {
                    $tagData['base_name'] = $baseName;
                }

                // Include translated_name for clarity (though it's same as name now)
                $tagData['translated_name'] = $translatedName;

                $filteredTags[] = $tagData;
            }
        }

        return $filteredTags;
    }

    /**
     * Batch-resolve a comma-separated tag string per blog from blog_tag_map.
     *
     * Used as the last-resort fallback in getBlogsList() for old blogs that
     * predate the denormalized translated_blog_details.tags column. Resolves
     * each tag name with cascading fallback: requested language -> default
     * language -> base blog_tags.name (skipping cryptic "tag_xxx" identifiers).
     *
     * One query for the whole page (no N+1).
     *
     * @param int[]  $blogIds          Blog IDs on the current page
     * @param string $language_code    Requested (already-sanitized) language
     * @param string $default_language Default language code
     * @return array<int,string>       [blog_id => "tag1, tag2"]
     */
    private function getTagStringsForBlogs(array $blogIds, $language_code, $default_language)
    {
        $blogIds = array_values(array_unique(array_filter(array_map('intval', $blogIds))));
        if (empty($blogIds)) {
            return [];
        }

        // Sanitize language codes — interpolated into JOIN conditions below
        $language_code = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $language_code))) ?: 'en';
        $default_language = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $default_language))) ?: 'en';

        $db = \Config\Database::connect();
        $rows = $db->table('blog_tag_map btm')
            ->select('btm.blog_id, bt.name AS base_name,
                tbtd_req.name AS requested_name,
                tbtd_def.name AS default_name')
            ->join('blog_tags bt', 'bt.id = btm.tag_id', 'inner')
            ->join('translated_blog_tag_details tbtd_req', "tbtd_req.tag_id = bt.id AND tbtd_req.language_code = '{$language_code}'", 'left')
            ->join('translated_blog_tag_details tbtd_def', "tbtd_def.tag_id = bt.id AND tbtd_def.language_code = '{$default_language}'", 'left')
            ->whereIn('btm.blog_id', $blogIds)
            ->orderBy('btm.id', 'asc')
            ->get()
            ->getResultArray();

        $tagsByBlog = [];
        foreach ($rows as $row) {
            $blogId = (int) $row['blog_id'];

            $name = trim($row['requested_name'] ?? '');
            if ($name === '') {
                $name = trim($row['default_name'] ?? '');
            }
            if ($name === '') {
                $base = trim($row['base_name'] ?? '');
                // Skip cryptic internal identifiers like "tag_68c25d4434aa5"
                if ($base !== '' && !preg_match('/^tag_[a-z0-9]+$/i', $base)) {
                    $name = $base;
                }
            }
            if ($name === '') {
                continue;
            }

            $tagsByBlog[$blogId][] = $name;
        }

        $result = [];
        foreach ($tagsByBlog as $blogId => $names) {
            $result[$blogId] = implode(', ', array_unique($names));
        }

        return $result;
    }

    /**
     * Fetch all unique tags from the blog_tags table with multi-language support
     * Returns an array of unique tags with id, name, slug, and translated names
     * @param string $language_code Language code for translations (default: 'en')
     * @return array Array of tags with translated names
     */
    public function getAllTags($language_code = 'en')
    {
        $db = \Config\Database::connect();

        // Get all tags with their translations
        $builder = $db->table('blog_tags bt');
        $builder->select('bt.id, bt.name, bt.slug, tbtd.name as translated_name')
            ->join('translated_blog_tag_details tbtd', 'tbtd.tag_id = bt.id AND tbtd.language_code = "' . $language_code . '"', 'left')
            ->orderBy('bt.name', 'ASC');

        $tags = $builder->get()->getResultArray();

        // Apply fallback logic for each tag
        $translationModel = new \App\Models\TranslatedBlogTagDetailsModel();
        $defaultLang = get_default_language();

        foreach ($tags as &$tag) {
            if (empty($tag['translated_name'])) {
                // If no translation found for requested language, try default language
                if ($language_code !== $defaultLang) {
                    $defaultTranslation = $translationModel->getTranslation($tag['id'], $defaultLang);
                    $tag['translated_name'] = $defaultTranslation ?: $tag['name'];
                } else {
                    $tag['translated_name'] = $tag['name'];
                }
            }
        }

        return $tags;
    }

    /**
     * Get blogs by tag ID
     * @param int $tagId
     * @return array
     */
    public function getBlogsByTag($tagId)
    {
        $db = \Config\Database::connect();
        return $db->table('blog_tag_map btm')
            ->select('b.*')
            ->join('blogs b', 'b.id = btm.blog_id')
            ->where('btm.tag_id', $tagId)
            ->get()->getResultArray();
    }

    /**
     * Get related blogs by category with translations
     * @param int $categoryId
     * @param int $excludeBlogId
     * @param int $limit
     * @param string $language_code Language code for translations (default: 'en')
     * @return array
     */
    public function getRelatedBlogsWithTranslations($categoryId, $excludeBlogId, $limit = 5, $language_code = 'en')
    {
        try {
            $results = $this->select('blogs.*, blog_categories.name as category_name, blog_categories.slug as category_slug')
                ->join('blog_categories', 'blog_categories.id = blogs.category_id', 'left')
                ->where('blogs.category_id', $categoryId)
                ->where('blogs.id !=', $excludeBlogId)
                ->orderBy('blogs.created_at', 'DESC')
                ->limit($limit)
                ->findAll();

            // Add translated category names and blog translations if results exist
            if (!empty($results)) {
                $translationModel = new \App\Models\TranslatedBlogCategoryDetailsModel();
                $translation = $translationModel->getTranslation($categoryId, $language_code);

                foreach ($results as &$result) {
                    // Add translated category name
                    $result['translated_category_name'] = $translation ?: $result['category_name'];

                    // Add translated blog fields (title, description, short_description, tags)
                    // This ensures related blogs include all translated content
                    $result = $this->addBlogTranslations($result, $language_code);
                }
            }

            return $results;
        } catch (\Exception $e) {
            log_message('error', 'Blog Model getRelatedBlogsWithTranslations error: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Get blogs list with filtering, pagination, and translations
     * 
     * @param array $params
     * @return array
     */
    private function getBlogsList($params = [])
    {
        $limit = (int)($params['limit'] ?? 10);
        $offset = (int)($params['offset'] ?? 0);
        $sort = esc($params['sort'] ?? 'id');
        $order = in_array(strtoupper($params['order'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($params['order']) : 'DESC';
        $search = $params['search'] ?? '';
        if (is_array($search) && isset($search['value'])) {
            $search = $search['value'];
        }
        $search = esc($search);
        // Category filtering parameters
        $category_id = $params['category_id'] ?? null; // Admin interface uses this for category ID filtering
        $category_slug = $params['category_slug'] ?? ''; // API interface uses this for category slug filtering
        $tag_filter = $params['tag_filter'] ?? '';
        // Get current language for translations
        $language_code = $params['language_code'] ?? get_current_language();

        // Get default language for fallback
        $default_language = 'en'; // Default fallback
        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
        if (!empty($languages)) {
            $default_language = $languages[0]['code'];
        }

        // Build query for blogs, joining categories, SEO settings, and translations
        $builder = $this->builder('blogs b');
        $builder->select('b.id, b.slug, b.category_id, b.image, b.created_at, b.updated_at,
            c.name as category_name, c.slug as category_slug, 
            seo.title as seo_title, 
            seo.description as seo_description, 
            seo.keywords as seo_keywords, 
            seo.image as seo_image, 
            seo.schema_markup as seo_schema_markup, 
            seo.created_at as seo_created_at, 
            seo.updated_at as seo_updated_at,
            tbd_default.title as default_title,
            tbd_default.short_description as default_short_description,
            tbd_default.description as default_description,
            tbd_default.tags as default_tags,
            tbd_requested.title as translated_title,
            tbd_requested.short_description as translated_short_description,
            tbd_requested.description as translated_description,
            tbd_requested.tags as translated_tags,
            tbcd_default.name as default_category_name,
            tbcd_requested.name as translated_category_name,
            COALESCE(tbd_default.title, b.title) as title,
            COALESCE(tbd_default.short_description, b.short_description) as short_description,
            COALESCE(tbd_default.description, b.description) as description
        ')
            ->join('blog_categories c', 'c.id = b.category_id', 'left')
            ->join('blogs_seo_settings seo', 'seo.blog_id = b.id', 'left')
            ->join('translated_blog_details tbd_default', "tbd_default.blog_id = b.id AND tbd_default.language_code = '$default_language'", 'left')
            ->join('translated_blog_details tbd_requested', "tbd_requested.blog_id = b.id AND tbd_requested.language_code = '$language_code'", 'left')
            ->join('translated_blog_category_details tbcd_default', "tbcd_default.blog_category_id = c.id AND tbcd_default.language_code = '$default_language'", 'left')
            ->join('translated_blog_category_details tbcd_requested', "tbcd_requested.blog_category_id = c.id AND tbcd_requested.language_code = '$language_code'", 'left');

        // Always join tags tables if we have search or tag filter
        $needsTagJoin = !empty($search) || !empty($tag_filter);
        if ($needsTagJoin) {
            $builder->join('blog_tag_map btm', 'btm.blog_id = b.id', 'left')
                ->join('blog_tags bt', 'bt.id = btm.tag_id', 'left');
        }

        // Apply search conditions including translated fields
        if (!empty($search)) {
            $builder->groupStart()
                // Search in translated fields for both default and requested languages
                ->like('tbd_default.title', $search)
                ->orLike('tbd_default.short_description', $search)
                ->orLike('tbd_default.description', $search)
                ->orLike('tbd_default.tags', $search)
                ->orLike('tbd_requested.title', $search)
                ->orLike('tbd_requested.short_description', $search)
                ->orLike('tbd_requested.description', $search)
                ->orLike('tbd_requested.tags', $search)
                // Also search in category translations
                ->orLike('tbcd_default.name', $search)
                ->orLike('tbcd_requested.name', $search);
            if ($needsTagJoin) {
                $builder->orLike('bt.name', $search); // search in tag names
            }
            $builder->groupEnd();
        }

        // Filter by category ID if provided (from admin interface)
        if (!empty($category_id)) {
            $builder->where('b.category_id', $category_id);
        }

        // Filter by category slug if provided (from API interface)
        if (!empty($category_slug)) {
            $builder->where('c.slug', $category_slug);
        }

        // Filter by tag(s) if provided
        if (!empty($tag_filter)) {
            if (is_array($tag_filter)) {
                // If tag_filter is an array, use WHERE IN
                $builder->whereIn('bt.slug', $tag_filter);
            } else {
                // Otherwise, filter by single tag slug
                $builder->where('bt.slug', $tag_filter);
            }
        }

        // Group by blog id to avoid duplicate blogs when joining tags
        if ($needsTagJoin || !empty($tag_filter)) {
            $builder->groupBy('b.id');
        }

        // Get total count
        $total = $builder->countAllResults(false);
        // Sorting and pagination
        $builder->orderBy($sort, $order)->limit($limit, $offset);
        $results = $builder->get()->getResultArray();

        // Last-resort tag fallback for old blogs that predate the denormalized
        // translated_blog_details.tags column: resolve from blog_tag_map in one
        // batched query (no N+1) keyed by blog id.
        $tagStringsByBlog = $this->getTagStringsForBlogs(
            array_column($results, 'id'),
            $language_code,
            $default_language
        );

        // Process results to handle translation fallback logic (similar to blog categories)
        foreach ($results as &$result) {
            // Handle blog title translation with fallback: requested language → default language → main table
            $result['translated_title'] = !empty($result['translated_title'])
                ? $result['translated_title']
                : (!empty($result['default_title']) ? $result['default_title'] : ($result['title'] ?? ''));

            // Handle blog short description translation with fallback: requested language → default language → base table
            // Base table (blogs.short_description) holds the default-language snapshot for old blogs
            // that predate translation rows; $result['short_description'] is COALESCE(tbd_default, b)
            $result['translated_short_description'] = !empty($result['translated_short_description'])
                ? $result['translated_short_description']
                : (!empty($result['default_short_description'])
                    ? $result['default_short_description']
                    : ($result['short_description'] ?? ''));

            // Handle blog description translation with fallback: requested language → default language → main table
            $result['translated_description'] = !empty($result['translated_description'])
                ? $result['translated_description']
                : (!empty($result['default_description']) ? $result['default_description'] : ($result['description'] ?? ''));

            // Handle blog tags translation with fallback: requested language →
            // default language → blog_tag_map (for old blogs with no tbd.tags row)
            $result['translated_tags'] = !empty($result['translated_tags'])
                ? $result['translated_tags']
                : (!empty($result['default_tags'])
                    ? $result['default_tags']
                    : ($tagStringsByBlog[(int) ($result['id'] ?? 0)] ?? ''));

            // Handle category name translation with fallback (similar to blog categories pattern)
            $result['translated_category_name'] = !empty($result['translated_category_name'])
                ? $result['translated_category_name']
                : (!empty($result['default_category_name']) ? $result['default_category_name'] : ($result['category_name'] ?? ''));

            // Clean up temporary fields to avoid confusion
            unset(
                $result['default_title'],
                $result['default_short_description'],
                $result['default_description'],
                $result['default_tags'],
                $result['default_category_name']
            );
        }

        return [
            'total' => $total,
            'data' => $results
        ];
    }

    /**
     * Format data for DataTables display
     *
     * @param array $result - Raw result from getBlogsList
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
            // Format image with lightbox link for preview functionality
            $imageField = $this->formatImageWithLightbox($row['image']);

            $tempRow = [
                'id' => $row['id'],
                // Use translated fields for display in the selected language
                'translated_title' => esc($row['translated_title'] ?? ''),
                'title' => esc($row['translated_title'] ?? ''), // Keep for backward compatibility
                'slug' => esc($row['slug']),
                'category_id' => esc($row['category_id']),
                'translated_short_description' => esc($row['translated_short_description'] ?? ''),
                'short_description' => esc($row['translated_short_description'] ?? ''), // Keep for backward compatibility
                // Use translated tags from the translations table
                'translated_tags' => esc($row['translated_tags'] ?? ''),
                'tags' => esc($row['translated_tags'] ?? ''), // Keep for backward compatibility
                'image' => $imageField,
                'translated_description' => esc($this->stripHtmlTags($row['translated_description'] ?? '')),
                'description' => esc($this->stripHtmlTags($row['translated_description'] ?? '')), // Keep for backward compatibility
                'translated_category_name' => esc($row['translated_category_name'] ?? 'Uncategorized'),
                'category_name' => esc($row['translated_category_name'] ?? 'Uncategorized'), // Keep for backward compatibility
                'created_at' => date('M d, Y', strtotime($row['created_at'])),
                'updated_at' => date('M d, Y', strtotime($row['updated_at'])),
                // Add SEO fields to output (may be null if not set)
                'seo_title' => esc($row['seo_title'] ?? ''),
                'seo_description' => esc($row['seo_description'] ?? ''),
                'seo_keywords' => esc($row['seo_keywords'] ?? ''),
                'seo_image' => esc($row['seo_image'] ?? ''),
                'seo_schema_markup' => esc($row['seo_schema_markup'] ?? ''),
                'seo_created_at' => $row['seo_created_at'] ?? '',
                'seo_updated_at' => $row['seo_updated_at'] ?? ''
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
     * Generate operations column for admin interface
     *
     * @param int $blogId - Blog ID
     * @return string - HTML for operations column
     */
    private function generateOperationsColumn($blogId)
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
                    <button class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ellipsis-v"></i></button>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';

                if (isset($permissions['update']['blog']) && $permissions['update']['blog'] == 1) {
                    $operations .= '<a class="dropdown-item edit-blog" data-id="' . $blogId . '" href="' . base_url('admin/blog/edit_blog/' . $blogId) . '" title="' . labels('edit', 'Edit') . '">
                        <i class="fa fa-pen text-primary mr-1" aria-hidden="true"></i>' . labels('edit', 'Edit') . '</a>';
                }

                if (isset($permissions['delete']['blog']) && $permissions['delete']['blog'] == 1) {
                    $operations .= '<a class="dropdown-item delete-blog" data-id="' . $blogId . '" onclick="blog_id(this)">
                        <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                }

                $operations .= '</div></div>';
            }
        }

        return $operations;
    }

    /**
     * Format image with lightbox link for preview functionality
     * @param string $imageName
     * @return string
     */
    private function formatImageWithLightbox($imageName)
    {
        $defaultImageUrl = base_url('/public/backend/assets/default.png');

        if (empty($imageName)) {
            return '<img height="60px" width="70px" style="padding:2px" class="rounded" src="' . $defaultImageUrl . '" alt="Default Image">';
        }

        // Use the get_file_url function to get the proper image URL
        $disk = function_exists('fetch_current_file_manager') ? fetch_current_file_manager() : 'local_server';

        // Construct the full path for the image since get_file_url expects the full path
        $imagePath = 'public/uploads/blogs/images/' . $imageName;
        $imageUrl = function_exists('get_file_url') ? get_file_url($disk, $imagePath, 'public/backend/assets/default.png', 'blogs') : base_url('/public/uploads/blogs/images/' . $imageName);

        // The get_file_url function already handles file existence checking
        // If the file exists, it returns the actual URL; otherwise, it returns the default image URL
        return '<a href="' . $imageUrl . '" data-lightbox="image-1"><img height="60px" width="70px" style="padding:2px" class="rounded" src="' . $imageUrl . '" alt="Blog Image"></a>';
    }

    /**
     * Process tags for a blog: ensure each tag exists in blog_tags with a unique slug, and map to blog_tag_map
     * @param string $tags (comma-separated)
     * @param int $blogId
     */
    /**
     * Process tags for a blog with multi-language support
     * This method maintains backward compatibility while adding translation support
     * 
     * @param string|array $tags Tags string (comma-separated) or array of tag data
     * @param int $blogId Blog ID
     * @param array $tagTranslations Optional array of translations [tag_name => [lang => translated_name]]
     */
    private function processTags($tags, $blogId, $tagTranslations = [])
    {


        // Handle both string and array input for backward compatibility
        if (is_string($tags)) {
            // Split tags by comma, trim whitespace, remove empty
            $tagArr = array_filter(array_map('trim', explode(',', $tags)));
        } elseif (is_array($tags)) {
            $tagArr = $tags;
        } else {

            return;
        }

        if (empty($tagArr)) {

            return;
        }



        $db = \Config\Database::connect();
        $tagIds = [];
        $translationModel = new \App\Models\TranslatedBlogTagDetailsModel();

        foreach ($tagArr as $tagName) {
            if ($tagName === '') continue;



            // Check if tag exists by looking for translations first
            // This ensures we don't create duplicate tags when the same logical tag exists in different languages
            $translationModel = new \App\Models\TranslatedBlogTagDetailsModel();

            // First, try to find existing tag by checking translations
            $existingByTranslation = $db->table('translated_blog_tag_details tbtd')
                ->select('tbtd.tag_id, bt.name, bt.slug')
                ->join('blog_tags bt', 'bt.id = tbtd.tag_id', 'inner')
                ->where('LOWER(tbtd.name)', strtolower($tagName))
                ->get()->getRowArray();

            if ($existingByTranslation) {
                $tagIds[] = $existingByTranslation['tag_id'];
                $tagId = $existingByTranslation['tag_id'];

                // Best Practice: Fix legacy slugs for existing tags if encountered
                if ($this->getSlugService()->isLegacySlug($existingByTranslation['slug'])) {
                    $newSlug = $this->getSlugService()->generate($existingByTranslation['name'], $existingByTranslation['name'], 'blog_tags', $tagId);
                    $db->table('blog_tags')->where('id', $tagId)->update(['slug' => $newSlug]);
                }
            } else {
                // Check if tag exists in main table (for backward compatibility)
                $builder = $db->table('blog_tags');
                $builder->where('LOWER(name)', strtolower($tagName));
                $tag = $builder->get()->getRowArray();

                if ($tag) {
                    $tagIds[] = $tag['id'];
                    $tagId = $tag['id'];

                    // Best Practice: Fix legacy slugs for existing tags if encountered
                    if ($this->getSlugService()->isLegacySlug($tag['slug'])) {
                        $newSlug = $this->getSlugService()->generate($tag['name'], $tag['name'], 'blog_tags', $tagId);
                        $db->table('blog_tags')->where('id', $tagId)->update(['slug' => $newSlug]);
                    }
                } else {
                    // Create new tag with English name as identifier and unique slug
                    // The name field should contain a non-translatable identifier (English)
                    // If the tag name is not in English, we'll use a generated identifier

                    $slug = $this->getSlugService()->resolve(
                        currentSlug:null,
                        inputSlug:$tagName,
                        fallbackName:$tagName,
                        table:'blog_tags',
                        excludeId:null
                    );

                    $builder->insert([
                        'name' => $tagName,
                        'slug' => $slug,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $tagId = $db->insertID();
                    $tagIds[] = $tagId;
                }
            }

            // Handle translations - always save the original tag name as a translation
            $translationsToSave = [];

            // Determine the language of the original tag name
            $defaultLang = get_default_language();
            if (mb_check_encoding($tagName, 'ASCII')) {
                // ASCII characters - likely English
                $translationsToSave[$defaultLang] = $tagName;
            } else {
                // Non-ASCII characters - determine language or use current language context
                $currentLang = get_current_language_from_request();
                $translationsToSave[$currentLang] = $tagName;
            }

            // Add any additional translations provided
            if (!empty($tagTranslations[$tagName])) {
                $translationsToSave = array_merge($translationsToSave, $tagTranslations[$tagName]);
            }

            // Save translations for this tag
            try {
                $translationModel->saveTranslationsOptimized($tagId, $translationsToSave);
            } catch (\Exception $e) {
                log_message('error', 'Error saving tag translations for tag ID ' . $tagId . ': ' . $e->getMessage());
            }
        }

        // Remove old mappings for this blog
        $db->table('blog_tag_map')->where('blog_id', $blogId)->delete();

        // Insert new mappings
        foreach ($tagIds as $tagId) {
            $db->table('blog_tag_map')->insert([
                'blog_id' => $blogId,
                'tag_id' => $tagId
            ]);
        }
    }

    /**
     * Process tags with translations from admin panel
     * This method is specifically for handling tag translations
     * 
     * @param array $tagData Array of tag data with translations
     * @param int $blogId Blog ID
     */
    public function processTagsWithTranslations($tagData, $blogId)
    {


        if (empty($tagData)) {

            return;
        }

        $tagNames = [];
        $translations = [];

        // Extract tag names and their translations
        foreach ($tagData as $tag) {
            if (!empty($tag['name'])) {
                $tagNames[] = $tag['name'];
                if (!empty($tag['translations'])) {
                    $translations[$tag['name']] = $tag['translations'];
                }
            }
        }



        // Use the existing processTags method with translations
        $this->processTags($tagNames, $blogId, $translations);
    }

    /**
     * Generate English identifier for tag name
     * If the tag name is already in English, use it as is
     * If the tag name is in another language, generate a transliterated or generic identifier
     * 
     * @param string $tagName Original tag name
     * @return string English identifier for the tag
     */
    private function generateEnglishTagIdentifier($tagName)
    {
        // Check if the tag name contains only ASCII characters (likely English)
        if (mb_check_encoding($tagName, 'ASCII')) {
            return $tagName;
        }

        // For non-ASCII characters (Hindi, Arabic, etc.), generate a generic identifier
        // This ensures the main table only contains English identifiers
        return 'tag_' . uniqid();
    }

    /**
     * Strip HTML tags from text content for clean display
     * More comprehensive approach to remove all HTML content
     * 
     * @param string $content - Content that may contain HTML tags
     * @return string - Clean text without HTML tags
     */
    private function stripHtmlTags($content)
    {
        // Return empty string if content is null or empty
        if (empty($content)) {
            return '';
        }

        // First decode any HTML entities that might be encoded
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Remove HTML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        // Remove script and style tags and their content
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);

        // Strip all HTML tags
        $cleanText = strip_tags($content);

        // Decode HTML entities again (in case some were nested)
        $cleanText = html_entity_decode($cleanText, ENT_QUOTES, 'UTF-8');

        // Remove any remaining HTML entity references that weren't decoded
        $cleanText = preg_replace('/&[a-zA-Z][a-zA-Z0-9]*;/', '', $cleanText);
        $cleanText = preg_replace('/&#[0-9]+;/', '', $cleanText);
        $cleanText = preg_replace('/&#x[0-9a-fA-F]+;/', '', $cleanText);

        // Replace multiple whitespace characters with single space
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        // Remove any remaining control characters
        $cleanText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanText);

        // Trim leading and trailing whitespace
        return trim($cleanText);
    }

    /**
     * Add blog translations to a blog record with main table fallback
     * Uses translation tables first, then falls back to main table values for legacy support
     * 
     * @param array $blog The blog record
     * @param string $language_code The language code
     * @return array The blog record with translated fields
     */
    private function addBlogTranslations($blog, $language_code = 'en')
    {
        try {
            // Get default language code for fallback
            $default_language = 'en'; // Default fallback
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $default_language = $languages[0]['code'];
            }

            // Get translated blog details for requested language
            $translatedModel = new \App\Models\TranslatedBlogDetailsModel();
            $requestedTranslation = $translatedModel->getTranslation($blog['id'], $language_code);

            // Get translated blog details for default language
            $defaultTranslation = $translatedModel->getTranslation($blog['id'], $default_language);

            // For main translatable fields: use default language translation, fallback to main table
            foreach (['title', 'short_description', 'description', 'tags'] as $field) {
                if (!empty($defaultTranslation[$field])) {
                    $blog[$field] = $defaultTranslation[$field];
                } else {
                    // Fallback to main table if available (only for title and description)
                    if (in_array($field, ['title', 'description']) && isset($blog[$field])) {
                        $blog[$field] = $blog[$field]; // Keep main table value
                    } else {
                        $blog[$field] = ''; // No fallback available for short_description and tags
                    }
                }
            }

            // For translated fields: use requested language translation, fallback to default language, then main table
            foreach (['title', 'short_description', 'description'] as $field) {
                $translatedField = 'translated_' . $field;

                if (!empty($requestedTranslation[$field])) {
                    // Requested language translation exists
                    $blog[$translatedField] = $requestedTranslation[$field];
                } elseif (!empty($defaultTranslation[$field])) {
                    // Fallback to default language translation
                    $blog[$translatedField] = $defaultTranslation[$field];
                } else {
                    // Final fallback to main table if available (only for title and description)
                    if (in_array($field, ['title', 'description']) && isset($blog[$field])) {
                        $blog[$translatedField] = $blog[$field];
                    } else {
                        $blog[$translatedField] = ''; // No fallback available for short_description and tags
                    }
                }
            }

            return $blog;
        } catch (\Exception $e) {
            log_message('error', 'Error adding blog translations: ' . $e->getMessage());
            // Return original blog record if translation fails
            return $blog;
        }
    }

    /**
     * Get blogs with translations for listing
     * Extends the existing list method to include translation support
     * 
     * @param array $params Parameters for listing
     * @param string $language_code Language code for translations
     * @return array List of blogs with translations
     */
    public function getBlogsWithTranslations($language_code = 'en', $params = [])
    {
        try {
            // Get blogs using existing list method
            $result = $this->list($params);

            if (!empty($result['data'])) {
                // Add translations to each blog
                foreach ($result['data'] as &$blog) {
                    $blog = $this->addBlogTranslations($blog, $language_code);
                }
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Error getting blogs with translations: ' . $e->getMessage());
            // Return original result if translation fails
            return $this->list($params);
        }
    }

    /**
     * Get blog by ID with translations
     * Extends the existing getBlogById method to include translation support
     * 
     * @param int $id Blog ID
     * @param string $language_code Language code for translations
     * @return array|null Blog with translations or null if not found
     */
    public function getBlogByIdWithTranslations($id, $language_code = 'en')
    {
        try {
            $blog = $this->getBlogById($id);

            if ($blog) {
                $blog = $this->addBlogTranslations($blog, $language_code);
            }

            return $blog;
        } catch (\Exception $e) {
            log_message('error', 'Error getting blog by ID with translations: ' . $e->getMessage());
            // Return original blog if translation fails
            return $this->getBlogById($id);
        }
    }

    /**
     * Get blog by slug with translations
     * Extends the existing getBlogBySlug method to include translation support
     * 
     * @param string $slug Blog slug
     * @param string $language_code Language code for translations
     * @return array|null Blog with translations or null if not found
     */
    public function getBlogBySlugWithTranslations($slug, $language_code = 'en')
    {
        try {
            $blog = $this->getBlogBySlug($slug);

            if ($blog) {
                $blog = $this->addBlogTranslations($blog, $language_code);
            }

            return $blog;
        } catch (\Exception $e) {
            log_message('error', 'Error getting blog by slug with translations: ' . $e->getMessage());
            // Return original blog if translation fails
            return $this->getBlogBySlug($slug);
        }
    }
}
