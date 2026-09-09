<?php

namespace App\Models;

use \Config\Database;
use CodeIgniter\Model;
use  app\Controllers\BaseController;

class Subscription_model  extends Model
{
    protected $table = 'subscriptions';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'description', 'duration', 'price', 'discount_price', 'publish', 'order_type', 'max_order_limit', 'service_type', 'max_service_limit', 'tax_type', 'tax_id', 'is_commision', 'commission_threshold', 'commission_percentage', 'status'];
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('subscriptions s');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];
        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        $sort = "s.id";
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 's.id') {
                $sort = "s.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        $order = "DESC";
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`s.id`' => $search,
                '`s.name`' => $search,
                '`s.description`' => $search,
                '`s.duration`' => $search,
                '`s.price`' => $search,
                '`s.discount_price`' => $search,
                '`s.publish`' => $search,
                '`s.order_type`' => $search,
                '`s.max_order_limit`' => $search,
                '`s.service_type`' => $search,
                '`s.max_service_limit`' => $search,
                '`s.tax_type`' => $search,
                '`s.tax_id`' => $search,
                '`s.is_commision`' => $search,
                '`s.commission_threshold`' => $search,
                '`s.commission_percentage`' => $search,
                '`s.status`' => $search,
            ];
        }
        $subscription = $builder->select('count(s.id) as total');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $builder->where('s.status',  $_GET['subscription_filter']);
        }
        $subscription = $builder->get()->getResultArray();
        $total = $subscription[0]['total'];
        $builder->select('s.*');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $builder->where('s.status',  $_GET['subscription_filter']);
        }
        $subscription_record = [];
        $subscription_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        if ($from_app == false) {
            $db      = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 1)
                ->where(['phone' => $_SESSION['identity']]);
            $user1 = $builder->get()->getResultArray();
            $permissions = get_permission($user1[0]['id']);
        }
        $operations = "";
        foreach ($subscription_record as $row) {
            if ($from_app == false) {
                $operations = '<div class="dropdown">
                <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                </a><div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
                if ($permissions['update']['subscription'] == 1) {
                    $operations .= '<a class="dropdown-item" href="' . base_url('/admin/subscription/edit_subscription_page/' . $row['id']) . '" ><i class="fa fa-pen mr-1 text-primary"></i> Edit</a>';
                }
                if ($permissions['delete']['subscription'] == 1) {
                    $operations .= '<a class="dropdown-item delete" data-id="' . $row['id'] . '"> <i class="fa fa-trash text-danger mr-1"></i> Delete</a>';
                }
                $operations .= '</div></div>';
            }
            $publish_badge = ($row['publish'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>Yes
                </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>No
                </div>";
            $status_badge = ($row['status'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>Active
                    </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>Deactive
                    </div>";
            $is_commision_badge = ($row['is_commision'] == "yes") ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>Yes
                        </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>No
                        </div>";
            $tempRow['id'] = $row['id'];
            $tempRow['name'] = $row['name'];
            $tempRow['description'] = $row['description'];
            $tempRow['duration'] = $row['duration'];
            $tempRow['price'] = $row['price'];
            $tempRow['discount_price'] = $row['discount_price'];
            $tempRow['publish'] = $row['publish'];
            $tempRow['publish_badge'] = $publish_badge;
            $tempRow['order_type'] = $row['order_type'];
            $tempRow['max_order_limit'] = ($row['order_type'] == "limited") ? $row['max_order_limit'] : "-";
            $tempRow['service_type'] = $row['service_type'];
            $tempRow['max_service_limit'] = $row['max_service_limit'];
            $tempRow['tax_type'] = $row['tax_type'];
            $tempRow['tax_id'] = $row['tax_id'];
            $tempRow['is_commision'] = $row['is_commision'];
            $tempRow['commission_threshold'] = $row['commission_threshold'];
            $tempRow['commission_percentage'] = $row['commission_percentage'];
            $tempRow['status'] = $row['status'];
            $tempRow['status_badge'] = $status_badge;
            $tempRow['is_commision_badge'] = $is_commision_badge;
            $tempRow['operations'] = $operations;
            $price = calculate_subscription_price($row['id']);
            $tempRow['tax_value'] = $price[0]['tax_value'];
            $tempRow['tax_percentage'] = $price[0]['tax_percentage'];
            $tempRow['price_with_tax']  = $price[0]['price_with_tax'];
            $tempRow['original_price_with_tax'] = $price[0]['original_price_with_tax'];
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $tempRow['operations'] = $operations;
            $bulkData['rows'] = $rows;
        }
        return $bulkData;
    }

    /**
     * Get subscription with translated name and description for a specific language
     * Implements fallback chain: current language → default language → base table
     * 
     * @param int $id The subscription ID
     * @param string $language_code The language code (optional, defaults to current language)
     * @return array|null The subscription data with translated name and description
     */
    public function getWithTranslation($id, $language_code = null)
    {
        // Get the subscription data
        $subscription = $this->find($id);

        if (!$subscription) {
            return null;
        }

        // Get current language if not specified
        // This is the language the user has selected
        if (!$language_code) {
            $language_code = function_exists('get_current_language') ? get_current_language() : 'en';
        }

        // Get default language for fallback
        // This is used when current language translation is missing
        $defaultLang = function_exists('get_default_language') ? get_default_language() : 'en';

        // Get translated name and description
        $translatedModel = new \App\Models\TranslatedSubscriptionModel();
        
        // Try to get current language translation first
        $currentTranslation = $translatedModel->getTranslation($id, $language_code);
        
        // Try to get default language translation if current language is different and current translation is missing
        $defaultTranslation = null;
        if ($language_code !== $defaultLang) {
            $defaultTranslation = $translatedModel->getTranslation($id, $defaultLang);
        }

        // Implement fallback chain for name: current language → default language → base table
        if ($currentTranslation && !empty(trim($currentTranslation['name'] ?? ''))) {
            // Current language translation exists and is not empty
            $subscription['translated_name'] = $currentTranslation['name'];
        } elseif ($defaultTranslation && !empty(trim($defaultTranslation['name'] ?? ''))) {
            // Default language translation exists and is not empty
            $subscription['translated_name'] = $defaultTranslation['name'];
        } else {
            // Fallback to base table
            $subscription['translated_name'] = $subscription['name'];
        }

        // Implement fallback chain for description: current language → default language → base table
        if ($currentTranslation && !empty(trim($currentTranslation['description'] ?? ''))) {
            // Current language translation exists and is not empty
            $subscription['translated_description'] = $currentTranslation['description'];
        } elseif ($defaultTranslation && !empty(trim($defaultTranslation['description'] ?? ''))) {
            // Default language translation exists and is not empty
            $subscription['translated_description'] = $defaultTranslation['description'];
        } else {
            // Fallback to base table
            $subscription['translated_description'] = $subscription['description'];
        }

        return $subscription;
    }

    /**
     * Get all subscriptions with translated names and descriptions for a specific language
     * Implements fallback chain: current language → default language → base table
     * 
     * @param string $language_code The language code (optional, defaults to default language)
     * @param array $where Additional where conditions
     * @return array Array of subscriptions with translated names and descriptions
     */
    public function getAllWithTranslations($language_code = null, $where = [])
    {
        // Get current language if not specified
        // This is the language the user has selected
        if (!$language_code) {
            $language_code = function_exists('get_current_language') ? get_current_language() : 'en';
        }

        // Get default language for fallback
        // This is used when current language translation is missing
        $defaultLang = function_exists('get_default_language') ? get_default_language() : 'en';

        // Get all subscriptions
        $subscriptions = $this->where($where)->findAll();

        // Get translations for all subscriptions
        // Fetch both current language and default language translations
        $translatedModel = new \App\Models\TranslatedSubscriptionModel();
        $subscription_ids = array_column($subscriptions, 'id');

        // Store translations for both current language and default language
        $currentTranslations = [];
        $defaultTranslations = [];

        if (!empty($subscription_ids)) {
            // Fetch current language translations
            $currentTranslationResults = $translatedModel->whereIn('subscription_id', $subscription_ids)
                ->where('language_code', $language_code)
                ->findAll();

            foreach ($currentTranslationResults as $translation) {
                $currentTranslations[$translation['subscription_id']] = [
                    'name' => $translation['name'],
                    'description' => $translation['description']
                ];
            }

            // Fetch default language translations only if current language is different from default
            // This avoids duplicate queries when current language is already the default
            if ($language_code !== $defaultLang) {
                $defaultTranslationResults = $translatedModel->whereIn('subscription_id', $subscription_ids)
                    ->where('language_code', $defaultLang)
                    ->findAll();

                foreach ($defaultTranslationResults as $translation) {
                    $defaultTranslations[$translation['subscription_id']] = [
                        'name' => $translation['name'],
                        'description' => $translation['description']
                    ];
                }
            }
        }

        // Add translated data to subscriptions while preserving original fields
        // Implement fallback chain: current language → default language → base table
        foreach ($subscriptions as &$subscription) {
            // For name: try current language, then default language, then base table
            if (isset($currentTranslations[$subscription['id']]) && !empty(trim($currentTranslations[$subscription['id']]['name'] ?? ''))) {
                // Current language translation exists and is not empty
                $subscription['translated_name'] = $currentTranslations[$subscription['id']]['name'];
            } elseif (isset($defaultTranslations[$subscription['id']]) && !empty(trim($defaultTranslations[$subscription['id']]['name'] ?? ''))) {
                // Default language translation exists and is not empty
                $subscription['translated_name'] = $defaultTranslations[$subscription['id']]['name'];
            } else {
                // Fallback to base table
                $subscription['translated_name'] = $subscription['name'];
            }

            // For description: try current language, then default language, then base table
            if (isset($currentTranslations[$subscription['id']]) && !empty(trim($currentTranslations[$subscription['id']]['description'] ?? ''))) {
                // Current language translation exists and is not empty
                $subscription['translated_description'] = $currentTranslations[$subscription['id']]['description'];
            } elseif (isset($defaultTranslations[$subscription['id']]) && !empty(trim($defaultTranslations[$subscription['id']]['description'] ?? ''))) {
                // Default language translation exists and is not empty
                $subscription['translated_description'] = $defaultTranslations[$subscription['id']]['description'];
            } else {
                // Fallback to base table
                $subscription['translated_description'] = $subscription['description'];
            }
        }

        return $subscriptions;
    }

    /**
     * List subscriptions with translations for a specific language
     * 
     * @param bool $from_app Whether called from app or admin panel
     * @param string $search Search term
     * @param int $limit Limit of results
     * @param int $offset Offset for pagination
     * @param string $sort Sort field
     * @param string $order Sort order
     * @param array $where Additional where conditions
     * @param string $language_code Language code for translations
     * @return array Array of subscriptions with translations
     */
    public function listWithTranslations($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $language_code = null)
    {
        // Get current language for translations
        // If language_code is provided (for API calls), use it; otherwise use session language
        if ($language_code !== null) {
            $currentLang = $language_code;
        } else {
            $currentLang = function_exists('get_current_language') ? get_current_language() : 'en';
        }

        $db      = \Config\Database::connect();
        $builder = $db->table('subscriptions s');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];

        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        $sort = "s.id";
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 's.id') {
                $sort = "s.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        $order = "DESC";
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }

        // Build search conditions first
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`s.id`' => $search,
                '`s.name`' => $search,
                '`s.description`' => $search,
                '`s.duration`' => $search,
                '`s.price`' => $search,
                '`s.discount_price`' => $search,
                '`s.publish`' => $search,
                '`s.order_type`' => $search,
                '`s.max_order_limit`' => $search,
                '`s.service_type`' => $search,
                '`s.max_service_limit`' => $search,
                '`s.tax_type`' => $search,
                '`s.tax_id`' => $search,
                '`s.is_commision`' => $search,
                '`s.commission_threshold`' => $search,
                '`s.commission_percentage`' => $search,
                '`s.status`' => $search,
            ];

            // Include translated fields in search to allow searching by translated subscription names and descriptions
            // This ensures users can find subscriptions using their language-specific names
            // Only add translated fields if not English and if we have a search term
            if ($currentLang !== 'en') {
                $multipleWhere['`tsd.name`'] = $search;
                $multipleWhere['`tsd.description`'] = $search;
            }
        }

        // Count total subscriptions with translation join for accurate count when searching translated fields
        // Create a separate builder instance for count to avoid state issues
        $countBuilder = $db->table('subscriptions s');
        $countBuilder->join('translated_subscription_details tsd', "tsd.subscription_id = s.id AND tsd.language_code = '$currentLang'", 'left');
        $countBuilder->select('count(DISTINCT s.id) as total');

        if (isset($where) && !empty($where)) {
            $countBuilder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $countBuilder->groupStart();
            $countBuilder->orLike($multipleWhere);
            $countBuilder->groupEnd();
        }
        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $countBuilder->where('s.status',  $_GET['subscription_filter']);
        }
        $subscription = $countBuilder->get()->getResultArray();
        $total = $subscription[0]['total'];

        // Get subscriptions with translations
        // Create a fresh builder instance for the main query to ensure clean state
        $builder->join('translated_subscription_details tsd', "tsd.subscription_id = s.id AND tsd.language_code = '$currentLang'", 'left');
        $builder->select('s.*, tsd.name as translated_name, tsd.description as translated_description');

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $builder->where('s.status',  $_GET['subscription_filter']);
        }

        $subscription_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();

        if ($from_app == false) {
            $db      = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 1)
                ->where(['phone' => $_SESSION['identity']]);
            $user1 = $builder->get()->getResultArray();
            $permissions = get_permission($user1[0]['id']);
        }

        $operations = "";
        foreach ($subscription_record as $row) {
            if ($from_app == false) {
                $operations = '<div class="dropdown">
                <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                </a><div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
                if ($permissions['update']['subscription'] == 1) {
                    $operations .= '<a class="dropdown-item" href="' . base_url('/admin/subscription/edit_subscription_page/' . $row['id']) . '" ><i class="fa fa-pen mr-1 text-primary"></i> ' . labels('edit', 'Edit') . '</a>';
                }
                if ($permissions['delete']['subscription'] == 1) {
                    $operations .= '<a class="dropdown-item delete" data-id="' . $row['id'] . '"> <i class="fa fa-trash text-danger mr-1"></i> ' . labels('delete', 'Delete') . '</a>';
                }
                $operations .= '</div></div>';
            }

            $publish_badge = ($row['publish'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('yes', 'Yes') . "
                </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('no', 'No') . "
                </div>";
            $status_badge = ($row['status'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('active', 'Active') . "
                    </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('deactive', 'Deactive') . "
                    </div>";
            $is_commision_badge = ($row['is_commision'] == "yes") ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('yes', 'Yes') . "
                        </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('no', 'No') . "
                        </div>";

            $tempRow['id'] = $row['id'];
            // Use translated name and description if available, otherwise fall back to original
            $tempRow['name'] = !empty($row['translated_name']) ? $row['translated_name'] : $row['name'];
            $tempRow['description'] = !empty($row['translated_description']) ? $row['translated_description'] : $row['description'];
            $tempRow['duration'] = $row['duration'] == 'unlimited' ? labels('unlimited', 'Unlimited') : $row['duration'];
            $tempRow['price'] = $row['price'];
            $tempRow['discount_price'] = $row['discount_price'];
            $tempRow['publish'] = $row['publish'];
            $tempRow['publish_badge'] = $publish_badge;
            $tempRow['order_type'] = labels($row['order_type']);
            $tempRow['max_order_limit'] = ($row['order_type'] == "limited") ? $row['max_order_limit'] : "-";
            $tempRow['service_type'] = $row['service_type'];
            $tempRow['max_service_limit'] = $row['max_service_limit'];
            $tempRow['tax_type'] = $row['tax_type'];
            $tempRow['tax_id'] = $row['tax_id'];
            $tempRow['is_commision'] = $row['is_commision'];
            $tempRow['commission_threshold'] = $row['commission_threshold'];
            $tempRow['commission_percentage'] = $row['commission_percentage'];
            $tempRow['status'] = $row['status'];
            $tempRow['status_badge'] = $status_badge;
            $tempRow['is_commision_badge'] = $is_commision_badge;
            $tempRow['operations'] = $operations;
            $price = calculate_subscription_price($row['id']);
            $tempRow['tax_value'] = $price[0]['tax_value'];
            $tempRow['tax_percentage'] = $price[0]['tax_percentage'];
            $tempRow['price_with_tax']  = $price[0]['price_with_tax'];
            $tempRow['original_price_with_tax'] = $price[0]['original_price_with_tax'];
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $tempRow['operations'] = $operations;
            $bulkData['rows'] = $rows;
        }
        return $bulkData;
    }
}
