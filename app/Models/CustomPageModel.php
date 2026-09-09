<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Custom Page Model - Handles CRUD and listing for custom pages using CI4 best practices
 *
 * Follows modular, clean, and well-documented code style.
 * Based on Blog_model.php pattern with multilanguage fallback support.
 */
class CustomPageModel extends Model
{
    protected $table = 'custom_pages';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'title',          // Translatable: stores default language value in base table
        'slug',           // Non-translatable: URL identifier
        'content',        // Translatable: stores default language value in base table
        'status',         // Non-translatable: 1 = active, 0 = inactive
        'created_at',     // Non-translatable: timestamp
        'updated_at',     // Non-translatable: timestamp
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $dateFormat    = 'datetime';
    protected $returnType    = 'array';

    protected $validationRules = [
        'slug'    => 'required|trim|max_length[255]|alpha_dash',
        'title'   => 'required|max_length[255]',
        'content' => 'required',
    ];
    protected $validationMessages = [
        'slug' => [
            'required' => 'Please enter a slug for the custom page',
            'alpha_dash' => 'Slug may only contain letters, numbers, dashes, and underscores',
        ],
        'title' => [
            'required' => 'Please enter a title for the custom page',
        ],
        'content' => [
            'required' => 'Please enter content for the custom page',
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
     * List custom pages with pagination, search, and sorting (DataTable format)
     *
     * @param array $params
     * @return string JSON encoded response for DataTables
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

        // Set default parameters
        $params = array_merge([
            'limit' => 10,
            'offset' => 0,
            'sort' => 'id',
            'order' => 'DESC',
            'search' => '',
            'include_operations' => true,
        ], $params);

        // Get data using the main query method
        $result = $this->getCustomPagesList($params);

        // Format for DataTable
        $permissions = $params['permissions'] ?? ['update' => false, 'delete' => false];
        return $this->formatForDataTable($result, $params['include_operations'], $permissions);
    }

    /**
     * Get custom page by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getById($id)
    {
        try {
            return $this->find($id);
        } catch (\Exception $e) {
            log_message('error', 'CustomPageModel getById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get custom page by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function getBySlug($slug)
    {
        try {
            return $this->where('slug', $slug)->first();
        } catch (\Exception $e) {
            log_message('error', 'CustomPageModel getBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all active custom pages (status = 1)
     *
     * @return array
     */
    public function getActivePages()
    {
        try {
            return $this->where('status', 1)->orderBy('title', 'ASC')->findAll();
        } catch (\Exception $e) {
            log_message('error', 'CustomPageModel getActivePages error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active pages with translated titles resolved via multilanguage fallback.
     * Fallback: requested language -> default language -> base table.
     *
     * @param string $requestedLanguage
     * @param string $defaultLanguage
     * @return array
     */
    public function getActivePagesWithTranslations($requestedLanguage, $defaultLanguage)
    {
        try {
            $reqLang = $this->escapeLang($requestedLanguage);
            $defLang = $this->escapeLang($defaultLanguage);
            $builder = $this->builder('custom_pages cp');
            $builder->select("cp.id, cp.slug,
                COALESCE(cpt_requested.title, cpt_default.title, cp.title) as translated_title")
                ->join('custom_page_translations cpt_requested', "cpt_requested.custom_page_id = cp.id AND cpt_requested.language_code = {$reqLang}", 'left')
                ->join('custom_page_translations cpt_default', "cpt_default.custom_page_id = cp.id AND cpt_default.language_code = {$defLang}", 'left')
                ->where('cp.status', 1)
                ->orderBy('cp.title', 'ASC');

            return $builder->get()->getResultArray();
        } catch (\Exception $e) {
            log_message('error', 'CustomPageModel getActivePagesWithTranslations error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single page by ID with translated title and content via multilanguage fallback.
     * Fallback: requested language -> default language -> base table.
     *
     * @param int $id
     * @param string $requestedLanguage
     * @param string $defaultLanguage
     * @return array|null
     */
    public function getByIdWithTranslations($id, $requestedLanguage, $defaultLanguage)
    {
        try {
            $reqLang = $this->escapeLang($requestedLanguage);
            $defLang = $this->escapeLang($defaultLanguage);
            $builder = $this->builder('custom_pages cp');
            $builder->select('cp.id, cp.title, cp.slug, cp.content, cp.status, cp.created_at, cp.updated_at,
                COALESCE(cpt_requested.title, cpt_default.title, cp.title) as translated_title,
                COALESCE(cpt_requested.content, cpt_default.content, cp.content) as translated_content')
                ->join('custom_page_translations cpt_requested', "cpt_requested.custom_page_id = cp.id AND cpt_requested.language_code = {$reqLang}", 'left')
                ->join('custom_page_translations cpt_default', "cpt_default.custom_page_id = cp.id AND cpt_default.language_code = {$defLang}", 'left')
                ->where('cp.id', $id);

            $result = $builder->get()->getRowArray();
            return $result ?: null;
        } catch (\Exception $e) {
            log_message('error', 'CustomPageModel getByIdWithTranslations error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get a single page by slug with translated title and content via multilanguage fallback.
     * Fallback: requested language -> default language -> base table.
     *
     * @param string $slug
     * @param string $requestedLanguage
     * @param string $defaultLanguage
     * @return array|null
     */
    public function getBySlugWithTranslations($slug, $requestedLanguage, $defaultLanguage)
    {
        try {
            $reqLang = $this->escapeLang($requestedLanguage);
            $defLang = $this->escapeLang($defaultLanguage);
            $builder = $this->builder('custom_pages cp');
            $builder->select('cp.id, cp.title, cp.slug, cp.content, cp.status, cp.created_at, cp.updated_at,
                COALESCE(cpt_requested.title, cpt_default.title, cp.title) as translated_title,
                COALESCE(cpt_requested.content, cpt_default.content, cp.content) as translated_content')
                ->join('custom_page_translations cpt_requested', "cpt_requested.custom_page_id = cp.id AND cpt_requested.language_code = {$reqLang}", 'left')
                ->join('custom_page_translations cpt_default', "cpt_default.custom_page_id = cp.id AND cpt_default.language_code = {$defLang}", 'left')
                ->where('cp.slug', $slug);

            $result = $builder->get()->getRowArray();
            return $result ?: null;
        } catch (\Exception $e) {
            log_message('error', 'CustomPageModel getBySlugWithTranslations error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Escape a language code for safe use in SQL join conditions.
     */
    private function escapeLang(string $lang): string
    {
        return \Config\Database::connect()->escape($lang);
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    /**
     * Core query method: fetches custom pages with translation fallback, search, sort, pagination
     *
     * @param array $params
     * @return array ['total' => int, 'data' => array]
     */
    private function getCustomPagesList($params = [])
    {
        $limit = (int)($params['limit'] ?? 10);
        $offset = (int)($params['offset'] ?? 0);
        $allowedSorts = ['id', 'title', 'slug', 'status', 'created_at', 'updated_at'];
        $sort = in_array($params['sort'] ?? 'id', $allowedSorts) ? $params['sort'] : 'id';
        $order = in_array(strtoupper($params['order'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($params['order']) : 'DESC';
        $search = $params['search'] ?? '';
        if (is_array($search)) {
            $search = isset($search['value']) ? $search['value'] : '';
        }
        $search = is_string($search) ? esc($search) : '';

        // Get current language for translations
        $language_code = $params['language_code'] ?? get_current_language();

        // Get default language for fallback
        $default_language = 'en';
        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
        if (!empty($languages)) {
            $default_language = $languages[0]['code'];
        }

        // Build query joining translations for requested + default language
        $escapedLangCode = $this->escapeLang($language_code);
        $escapedDefaultLang = $this->escapeLang($default_language);
        $builder = $this->builder('custom_pages cp');
        $builder->select('cp.id, cp.slug, cp.status, cp.created_at, cp.updated_at,
            cp.title as base_title,
            cpt_default.title as default_title,
            cpt_requested.title as translated_title,
            COALESCE(cpt_requested.title, cpt_default.title, cp.title) as title,
            COALESCE(cpt_requested.content, cpt_default.content, cp.content) as content')
            ->join('custom_page_translations cpt_default', "cpt_default.custom_page_id = cp.id AND cpt_default.language_code = {$escapedDefaultLang}", 'left')
            ->join('custom_page_translations cpt_requested', "cpt_requested.custom_page_id = cp.id AND cpt_requested.language_code = {$escapedLangCode}", 'left');

        // Apply search across title and slug (including translated fields)
        if (!empty($search)) {
            $builder->groupStart()
                ->like('cp.title', $search)
                ->orLike('cp.slug', $search)
                ->orLike('cpt_default.title', $search)
                ->orLike('cpt_requested.title', $search)
                ->groupEnd();
        }

        // Get total count
        $total = $builder->countAllResults(false);

        // Sorting and pagination
        $builder->orderBy($sort, $order)->limit($limit, $offset);
        $results = $builder->get()->getResultArray();

        // Process results: apply translation fallback
        foreach ($results as &$result) {
            $result['translated_title'] = !empty($result['translated_title'])
                ? $result['translated_title']
                : (!empty($result['default_title']) ? $result['default_title'] : ($result['base_title'] ?? ''));

            // Clean up temporary fields
            unset($result['base_title'], $result['default_title']);
        }

        return [
            'total' => $total,
            'data' => $results
        ];
    }

    /**
     * Format data for DataTables display
     *
     * @param array $result Raw result from getCustomPagesList
     * @param bool $includeOperations Whether to include operations column
     * @return string JSON encoded response for DataTables
     */
    private function formatForDataTable($result, $includeOperations = true, $permissions = [])
    {
        $bulkData = [
            'total' => $result['total'],
            'rows' => []
        ];

        foreach ($result['data'] as $row) {
            $tempRow = [
                'id' => $row['id'],
                'translated_title' => esc($row['translated_title'] ?? ''),
                'title' => esc($row['translated_title'] ?? ''),
                'slug' => esc($row['slug']),
                'content' => $row['content'] ?? '',
                'status' => $row['status'],
                'created_at' => date('M d, Y', strtotime($row['created_at'])),
                'updated_at' => date('M d, Y', strtotime($row['updated_at'])),
            ];

            if ($includeOperations) {
                $tempRow['operations'] = $this->generateOperationsColumn($row['id'], $permissions);
            }

            $bulkData['rows'][] = $tempRow;
        }

        return json_encode($bulkData);
    }

    /**
     * Generate operations column (edit/delete buttons) for admin interface
     *
     * @param int $pageId Custom page ID
     * @return string HTML for operations column
     */
    private function generateOperationsColumn($pageId, $permissions = [])
    {
        $canUpdate = !empty($permissions['update']);
        $canDelete = !empty($permissions['delete']);

        if (!$canUpdate && !$canDelete) {
            return '';
        }

        $operations = '<div class="dropdown">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ellipsis-v"></i></button>
            </a>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';

        if ($canUpdate) {
            $operations .= '<a class="dropdown-item edit-custom-page" data-id="' . $pageId . '" href="' . base_url('admin/custom-pages/edit/' . $pageId) . '" title="' . labels('edit', 'Edit') . '">
                <i class="fa fa-pen text-primary mr-1" aria-hidden="true"></i>' . labels('edit', 'Edit') . '</a>';
        }

        if ($canDelete) {
            $operations .= '<a class="dropdown-item delete-custom-page" data-id="' . $pageId . '" onclick="custom_page_id(this)">
                <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
        }

        $operations .= '</div></div>';

        return $operations;
    }
}
