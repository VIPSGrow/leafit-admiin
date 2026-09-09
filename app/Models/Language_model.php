<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Services\utility\FileService;

class Language_model extends Model
{
    public $admin_id;
    public function __construct()
    {
        parent::__construct();
        $ionAuth = new \App\Libraries\CustomIonAuth();
        $this->admin_id = ($ionAuth->isAdmin()) ? $ionAuth->user()->row()->id : 0;
    }
    protected $table = 'languages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['language', 'code', 'is_default', 'is_rtl', 'image', 'created_at', 'updated_at'];

    /**
     * Get language files information
     * 
     * @param string $languageCode Language code
     * @return array Language files data
     */
    public function getLanguageFilesInfo(string $languageCode): array
    {
        $languageFileService = new \App\Services\LanguageFileService();
        return $languageFileService->getLanguageFiles($languageCode);
    }

    /**
     * Check if language has any uploaded files
     * 
     * @param string $languageCode Language code
     * @return bool True if language has files
     */
    public function hasLanguageFiles(string $languageCode): bool
    {
        $files = $this->getLanguageFilesInfo($languageCode);
        foreach ($files as $category => $fileInfo) {
            if ($fileInfo['exists'] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a language file exists for a single specific platform.
     *
     * Used when the API caller specifies a platform via request header.
     * Only checks one platform file instead of all platforms, which is faster.
     *
     * @param string $languageCode Language code (e.g. 'en', 'ar')
     * @param string $platform     Platform to check: 'web' or 'customer_app'
     * @return bool True if the file for that platform exists
     */
    public function hasLanguageFileForPlatform(string $languageCode, string $platform): bool
    {
        $languageFileService = new \App\Services\LanguageFileService();
        return $languageFileService->hasLanguageFileForPlatform($languageCode, $platform);
    }

    /**
     * Check if language has files for provider app platform only
     * 
     * This method verifies that language files exist for provider_app platform
     * Used by the partner API endpoint
     * 
     * @param string $languageCode Language code
     * @return bool True if language has files for provider_app platform
     */
    public function hasLanguageFilesForProviderApp(string $languageCode): bool
    {
        $languageFileService = new \App\Services\LanguageFileService();
        return $languageFileService->hasLanguageFilesForProviderApp($languageCode);
    }

    /**
     * Check if language has panel JSON file and corresponding PHP translation file
     * 
     * This method verifies that both JSON file exists in panel directory
     * and corresponding PHP translation file exists for admin panel use
     * 
     * @param string $languageCode Language code
     * @return bool True if both JSON and PHP files exist
     */
    public function hasPanelLanguageFiles(string $languageCode): bool
    {
        $languageFileService = new \App\Services\LanguageFileService();
        return $languageFileService->hasPanelLanguageFiles($languageCode);
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        $db = \Config\Database::connect();
        $builder = $db->table('languages');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`id`' => $search,
                '`name`' => $search,
                '`code`' => $search
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
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $builder->select('COUNT(id) as `total` ');
        $order_count = $builder->get()->getResultArray();
        $total = $order_count[0]['total'];
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $builder->select('*');
        $category_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $settings = fetch_details('languages', ['is_default' => '1']);
        $default_language_value = $settings[0]['id'];
        $fileService = new FileService();

        foreach ($category_record as $row) {
            $tempRow['id'] = $row['id'];
            $tempRow['language'] = $row['language'];
            $tempRow['code'] = $row['code'];
            $tempRow['is_rtl'] = $row['is_rtl'];
            $tempRow['image'] = $fileService->url($row['image'], 'language_image');
            $is_rtl = ($row['is_rtl'] == 1) ?
                "<div class='  text-emerald-success  ml-3 mr-3 mx-5'>" . labels('yes', "Yes") . "
        </div>" :
                "<div class=' text-emerald-danger ml-3 mr-3 '>" . labels('no', "No") . "
        </div>";
            $operations = '';
            if ($this->admin_id != 0) {
                // Don't show delete button for default language or when only one language exists
                $isDefaultLanguage = ($default_language_value == $row['id']);
                $isOnlyLanguage = (count($category_record) == 1);

                if ($isOnlyLanguage) {
                    $operations = ' <button class="btn btn-success edit-language" title="' . labels('edit', "Edit") . '" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal" "> <i class="fa fa-pen" aria-hidden="true"></i> </button> ';
                } else {
                    // Only show delete button if it's not the default language
                    if (!$isDefaultLanguage) {
                        $operations = "<button class='btn btn-danger delete-language btn ml-2' title='" . labels('delete', "Delete") . "' onclick='language_id(this)'> <i class='fa fa-trash' aria-hidden='true'></i> </button>";
                    }
                    $operations .= ' <button class="btn btn-success edit-language" title="' . labels('edit', "Edit") . '" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal" "> <i class="fa fa-pen" aria-hidden="true"></i> </button> ';
                }
            }
            if ($from_app == false) {
                $tempRow['language'] = $row['language'];
                $tempRow['code'] = $row['code'];
                $tempRow['is_rtl'] = $is_rtl;
                $tempRow['is_rtl_og'] = $row['is_rtl'];
                $tempRow['operations'] = $operations;
                $tempRow['default'] = ($default_language_value == $row['id']) ?
                    '<span class="badge badge-secondary"><em class="fa fa-check"></em> ' . labels('default', "Default") . '</span>' :
                    '<a class="btn btn-icon btn-sm btn-info text-white store_default_language" data-id="' . $row['id'] . '"> ' . labels('set_as_default', "Set as Default") . '</a>';
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
}
