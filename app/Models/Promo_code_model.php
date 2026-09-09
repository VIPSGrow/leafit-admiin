<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Services\utility\PermissionService;
use IonAuth\Libraries\IonAuth;

class Promo_code_model extends Model
{
    protected $table = 'promo_codes';
    protected IonAuth $ionAuth; // Declare the $ionAuth property explicitly
    protected $permissionService;

    protected $primaryKey = 'id';
    protected $allowedFields = ['partner_id', 'promo_code', 'start_date', 'end_date', 'no_of_users', 'minimum_order_amount', 'discount', 'discount_type', 'max_discount_amount', 'repeat_usage', 'no_of_repeat_usage', 'image', 'status', 'message']; // message: Translatable - stores default language value in base table
    public $admin_id;
    public function __construct()
    {
        parent::__construct();
        $ionAuth = new \App\Libraries\CustomIonAuth();
        $this->admin_id = ($ionAuth->isAdmin()) ? $ionAuth->user()->row()->id : 0;
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->permissionService = new PermissionService();
    }
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'DESC', $where = [], $language_code = null)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('promo_codes pc');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            // Include both original and translated fields in search
            // This allows searching in translated promocode messages and partner names
            $multipleWhere = [
                'pc.id' => $search,
                'pc.partner_id' => $search,
                'pc.promo_code' => $search,
                'pc.message' => $search,
                'pc.start_date' => $search,
                'pc.end_date' => $search,
                'pd.company_name' => $search,
                'tpcd.message' => $search,  // Search in translated promocode message
                'tpd.company_name' => $search,  // Search in translated partner company name
            ];
        }
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'pc.id') {
                $sort = "pc.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        // Get current language for translations
        // If language_code is provided (for API calls), use it; otherwise use session language
        if ($language_code !== null) {
            $currentLang = $language_code;
        } else {
            $currentLang = function_exists('get_current_language') ? get_current_language() : 'en';
        }

        $count = $builder->select(' COUNT(pc.id) as `total` ')
            ->join('partner_details pd', 'pd.partner_id = pc.partner_id', 'left')
            ->join('translated_partner_details tpd', "tpd.partner_id = pc.partner_id AND tpd.language_code = '$currentLang'", 'left')
            ->join('translated_promocode_details tpcd', "tpcd.promocode_id = pc.id AND tpcd.language_code = '$currentLang'", 'left');

        if (isset($_GET['promocode_filter']) && $_GET['promocode_filter'] != '') {
            $builder->where('pc.status', $_GET['promocode_filter']);
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $count = $builder->get()->getResultArray();
        $total = $count[0]['total'];

        // Create a fresh builder for the main query to avoid query state issues
        // This ensures joins are done before search conditions are applied
        $builder = $db->table('promo_codes pc');
        $builder->select('pc.*,p.username as partner_name,
                         pd.company_name,
                         tpd.company_name as translated_company_name,
                         tpcd.message as translated_message')
            ->join('users p', 'p.id=pc.partner_id', 'left')
            ->join('partner_details pd', 'pd.partner_id = pc.partner_id', 'left')
            ->join('translated_partner_details tpd', "tpd.partner_id = pc.partner_id AND tpd.language_code = '$currentLang'", 'left')
            ->join('translated_promocode_details tpcd', "tpcd.promocode_id = pc.id AND tpcd.language_code = '$currentLang'", 'left');

        // Apply where conditions before search
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Apply search conditions after joins are done
        // This allows searching in translated fields (tpcd.message, tpd.company_name)
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }

        // Apply status filter
        if (isset($_GET['promocode_filter']) && $_GET['promocode_filter'] != '') {
            $builder->where('pc.status', $_GET['promocode_filter']);
        }

        // Execute query with appropriate parameters
        if ($from_app) {
            $service_record = $builder->orderBy($sort, $order)->get()->getResultArray();
        } else {
            $service_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        }
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        if ($from_app == false) {
            $user_id = $this->ionAuth->user()->row()->id;
            $permissions = $this->permissionService->permissionsFor($user_id);

            // for partners the operations should be allowed by default
            if ($this->ionAuth->isPartner()) {
                $permissions['update']['promo_code'] = 1;
                $permissions['delete']['promo_code'] = 1;
            }
        }
        $fileService = service('fileService');
        foreach ($service_record as $row) {
            $image = "";
            $image_url = !empty($row['image'])
                ? $fileService->url($row['image'], 'promocodes', 'public/uploads/profiles/default.png')
                : base_url('public/uploads/profiles/default.png');



            $image = '<a  href="' . $image_url . '" data-lightbox="image-1"><img class="o-media__img images_in_card" src="' . $image_url . '" alt="' . $row['id'] . '"></a>';


            $operations = "";
            $operations = '<div class="dropdown">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
            </a>         <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
            if ($from_app == false) {
                $operations .= '<a class="dropdown-item"href="' . base_url('/partner/promo_codes/duplicate/' . $row['id']) . '"><i class="fas fa-copy text-info mr-1"></i>' . labels('duplicate_promocode', 'Duplicate Promocode') . '</a>';
                if ($this->ionAuth->isPartner() || $this->permissionService->can($user_id, 'update', 'promo_code')) {
                    $operations .= '<a class="dropdown-item edit " data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>';
                }
                if ($this->ionAuth->isPartner() || $this->permissionService->can($user_id, 'delete', 'promo_code')) {
                    $operations .= '<a class="dropdown-item delete-promo_codes delete" > <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                }
            }

            $translations = $db->table('translated_promocode_details')
                ->select('language_code, message')
                ->where('promocode_id', $row['id'])
                ->get()
                ->getResultArray();

            $formattedTranslations = [];

            // Build translations only if valid entries exist
            foreach ($translations as $t) {
                if (!empty($t['message'])) {
                    // initialize nested structure lazily
                    if (!isset($formattedTranslations['message'])) {
                        $formattedTranslations['message'] = [];
                    }
                    $formattedTranslations['message'][$t['language_code']] = $t['message'];
                }
            }

            // if we found nothing meaningful, send empty object
            if (empty($formattedTranslations)) {
                $formattedTranslations = (object) [];
            }

            $operations .= '</div></div>';
            if ($from_app) {

                $status = ($row['status'] == 1) ? labels('enable', 'Enable') : labels('disable', 'Disable');

                $image = !empty($row['image'])
                    ? $fileService->url($row['image'], 'promocodes', 'public/uploads/profiles/default.png')
                    : '';
            }
            $repeat_usage_badge = ($row['repeat_usage'] == 1) ?
                "<div class='  text-emerald-success  ml-3 mr-3 mx-5'>" . labels('yes', 'Yes') . "
            </div>" :
                "<div class=' text-emerald-danger ml-3 mr-3 '>" . labels('no', 'No') . "
            </div>";
            $status = ($row['status'] == 1) ? '1' : '0';
            $tempRow['id'] = $row['id'];
            $tempRow['partner_id'] = $row['partner_id'];
            // Keep original partner name (username or company name)
            $tempRow['partner_name'] = !empty($row['company_name']) ? $row['company_name'] : $row['partner_name'];
            $tempRow['promo_code'] = $row['promo_code'];
            // Keep original message
            $tempRow['message'] = $row['message'] ?? '';
            // Add translated fields as separate fields
            $tempRow['translated_partner_name'] = !empty($row['translated_company_name']) ? $row['translated_company_name'] : (!empty($row['company_name']) ? $row['company_name'] : $row['partner_name']);
            $tempRow['translated_message'] = !empty($row['translated_message']) ? $row['translated_message'] : ($row['message'] ?? '');
            // Format dates to show only date (not time) in the view
            $tempRow['start_date'] = format_date($row['start_date'], 'd-m-Y');
            $tempRow['end_date'] = format_date($row['end_date'], 'd-m-Y');
            $tempRow['no_of_users'] = $row['no_of_users'];
            $tempRow['minimum_order_amount'] = $row['minimum_order_amount'];
            $tempRow['discount'] = $row['discount'];
            $tempRow['discount_type'] = $row['discount_type'];
            $tempRow['max_discount_amount'] = $row['max_discount_amount'];
            $tempRow['repeat_usage'] = $row['repeat_usage'];
            $tempRow['repeat_usage_badge'] = $repeat_usage_badge;
            $tempRow['no_of_repeat_usage'] = $row['no_of_repeat_usage'];
            $tempRow['image'] = $image;
            $tempRow['status'] = $row['status'];
            $tempRow['status'] = $status;
            $tempRow['translated_fields'] = $formattedTranslations;


            $used_by = fetch_details('orders', ['promocode_id' => $row['id']], ['id']);

            $tempRow['total_used_number'] = count($used_by);


            if (!$from_app) {
                $tempRow['created_at'] = format_date($row['created_at'], 'd-m-Y');
                ;
            } else {
                $tempRow['created_at'] = $row['created_at'];
            }
            $status_badge = ($row['status'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('active', 'Active') . "
                </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('deactive', 'Deactive') . "
                </div>";
            $tempRow['status_badge'] = $status_badge;
            if (!$from_app) {
                $tempRow['operations'] = $operations;
            }
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            return json_encode($bulkData);
        }
    }
    public function admin_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'DESC', $where = [], $language_code = null)
    {
        $multipleWhere = '';
        $db = \Config\Database::connect();
        $builder = $db->table('promo_codes pc');

        // Get current language for translations
        // If language_code is provided (for API calls), use it; otherwise use session language
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        $values = ['7'];
        // Check for search parameter from both function argument and GET request
        // This ensures search works when called from frontend with GET parameters
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            // Include both original and translated fields in search
            // This allows searching in translated promocode messages and partner names
            $multipleWhere = [
                'pc.id' => $search,
                'pc.partner_id' => $search,
                'pc.promo_code' => $search,
                'pc.message' => $search,
                'p.username' => $search,
                'tpd.username' => $search,
                'pd.company_name' => $search,
                'pc.start_date' => $search,
                'pc.end_date' => $search,
                'tpcd.message' => $search,  // Search in translated promocode message
                'tpd.company_name' => $search,  // Search in translated partner company name
            ];
        }
        $builder->select('COUNT(pc.id) as `total` ')
            ->join('users p', 'p.id=pc.partner_id', 'left')
            ->join('partner_details pd', 'pd.partner_id = p.id', 'left')
            ->join('translated_partner_details tpd', "tpd.partner_id = p.id AND tpd.language_code = '$currentLang'", 'left')
            ->join('translated_promocode_details tpcd', "tpcd.promocode_id = pc.id AND tpcd.language_code = '$currentLang'", 'left');

        if (isset($_GET['promocode_filter']) && $_GET['promocode_filter'] != '') {
            $builder->where('pc.status', $_GET['promocode_filter']);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        $partner_count = $builder->get()->getResultArray();
        $total = $partner_count[0]['total'];

        if (isset($_GET['promocode_filter']) && $_GET['promocode_filter'] != '') {
            $builder->where('pc.status', $_GET['promocode_filter']);
        }
        $builder->select('pc.*,p.username as partner_name,
                         pd.company_name,
                         tpcd.message as translated_message,
                        p.email as partner_email,
                        p.image as partner_image,
                        p.phone as partner_phone')
            ->join('users p', 'p.id=pc.partner_id', 'left')
            ->join('partner_details pd', 'pd.partner_id = p.id', 'left')
            ->join('translated_partner_details tpd', "tpd.partner_id = p.id AND tpd.language_code = '$currentLang'", 'left')
            ->join('translated_promocode_details tpcd', "tpcd.promocode_id = pc.id AND tpcd.language_code = '$currentLang'", 'left');

        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        $partner_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();

        $allTranslations = [];
        if (!empty($partner_record)) {
            $partnerIds = array_column($partner_record, 'partner_id');
            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
            // Get all translations for all partners (not just current language)
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = array();
        $bulkData['total'] = $total;
        $permissions = [];
        if ($from_app == false) {
            $user_id = $this->ionAuth->user()->row()->id;
            $permissions = $this->permissionService->permissionsFor($user_id);

            // for partners the operations should be allowed by default
            if ($this->ionAuth->isPartner()) {
                $permissions['update']['promo_code'] = 1;
                $permissions['delete']['promo_code'] = 1;
            }
        }

        $rows = array();
        $tempRow = array();
        $fileService = service('fileService');

        foreach ($partner_record as $row) {
            $partner_details = fetch_details('partner_details', ['partner_id' => $row['partner_id']]);

            // Get translations for this specific partner
            $partnerTranslations = $allTranslations[$row['partner_id']] ?? [];

            // Re-index translations by language_code for easier access
            $translationsByLang = [];
            if (!empty($partnerTranslations)) {
                foreach ($partnerTranslations as $langCode => $translation) {
                    $translationsByLang[$langCode] = $translation;
                }
            }

            // Get translated company name with fallback logic
            // Priority: current language → default language → base table
            $companyName = $partner_details[0]['company_name'] ?? ''; // fallback to base table
            $partner_name = $row['partner_name'] ?? ''; // fallback to base table
            if (!empty($translationsByLang)) {
                // Try current language first
                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['company_name'])) {
                    $companyName = $translationsByLang[$currentLang]['company_name'];
                }
                // Fallback to default language
                elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['company_name'])) {
                    $companyName = $translationsByLang[$defaultLang]['company_name'];
                }

                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['username'])) {
                    $partner_name = $translationsByLang[$currentLang]['username'];
                } elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['username'])) {
                    $partner_name = $translationsByLang[$defaultLang]['username'];
                }
            }

            $image = "";
            $image_url = !empty($row['image'])
                ? $fileService->url($row['image'], 'promocodes', 'public/uploads/profiles/default.png')
                : base_url('public/uploads/profiles/default.png');

            $image = '<a  href="' . $image_url . '" data-lightbox="image-1"><img class="o-media__img images_in_card" src="' . $image_url . '" alt="' . $row['id'] . '"></a>';

            $operations = "";
            $operations = '<div class="dropdown">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
            </a>         <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
            if ($from_app == false) {
                $operations .= '<a class="dropdown-item"href="' . base_url('admin/promo_codes/duplicate/' . $row['id']) . '"><i class="fas fa-copy text-info mr-1"></i>' . labels('duplicate_promocode', 'Duplicate Promocode') . '</a>';
                if ($this->permissionService->can($user_id, 'update', 'promo_code')) {
                    $operations .= '<a class="dropdown-item edit "data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>';
                }
                if ($this->permissionService->can($user_id, 'delete', 'promo_code')) {
                    $operations .= '<a class="dropdown-item delete-promo_codes" > <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                }
            }
            $operations .= '</div></div>';
            if ($from_app) {
                $status = ($row['status'] == 1) ? labels('enable', 'Enable') : labels('disable', 'Disable');
                $image = !empty($row['image'])
                    ? $fileService->url($row['image'], 'promocodes', 'public/uploads/profiles/default.png')
                    : '';
            }
            $status_badge = ($row['status'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('active', 'Active') . "
            </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('deactive', 'Deactive') . "
            </div>";
            $status = ($row['status'] == 1) ? labels('active', 'Active') : labels('deactive', 'Deactive');

            // echo "<pre>";
            // print_r($row);
            // die();

            $imageSrc = $fileService->url($row['partner_image'], 'profile', 'public/backend/assets/default.png');

            $profile = '<div class="o-media o-media--middle">
                        <a href="' . $imageSrc . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $partner_name . '">
                        </a>';
            $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                <div class="provider_name_table" >' . $partner_name . '</span></div>
                <div class="provider_email_table">' . $companyName . '</div>
                <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
                </div>
                </div></a>';

            $tempRow['id'] = $row['id'];
            $tempRow['partner_id'] = $row['partner_id'];
            // Keep original partner name (username or company name)
            $tempRow['partner_name'] = !empty($row['company_name']) ? $row['company_name'] : $row['partner_name'];
            $tempRow['promo_code'] = $row['promo_code'];
            // Keep original message
            $tempRow['message'] = $row['message'] ?? '';
            // Add translated fields as separate fields
            // $tempRow['translated_partner_name'] = !empty($row['translated_company_name']) ? $row['translated_company_name'] : (!empty($row['company_name']) ? $row['company_name'] : $row['partner_name']);
            $tempRow['translated_partner_name'] = $profile;
            $tempRow['translated_message'] = !empty($row['translated_message']) ? $row['translated_message'] : ($row['message'] ?? '');
            $tempRow['start_date'] = format_date($row['start_date'], 'd-m-Y');
            $tempRow['end_date'] = format_date($row['end_date'], 'd-m-Y');
            $tempRow['no_of_users'] = $row['no_of_users'];
            $tempRow['minimum_order_amount'] = $row['minimum_order_amount'];
            $tempRow['discount'] = $row['discount'];
            $tempRow['discount_type'] = $row['discount_type'];
            $tempRow['max_discount_amount'] = $row['max_discount_amount'];
            $tempRow['repeat_usage'] = $row['repeat_usage'];
            $tempRow['no_of_repeat_usage'] = $row['no_of_repeat_usage'];
            $tempRow['image'] = $image;
            $tempRow['status'] = $status;
            $tempRow['status_badge'] = $status_badge;
            $tempRow['created_at'] = $row['created_at'];
            if (!$from_app) {
                $tempRow['operations'] = $operations;
            }
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $bulkData['rows'] = $rows;
        }
        return $bulkData;
    }

    /**
     * Get promocode with translated message for a specific language
     * 
     * @param int $id The promocode ID
     * @param string $language_code The language code (optional, defaults to default language)
     * @return array|null The promocode data with translated message
     */
    public function getWithTranslation($id, $language_code = null)
    {
        // Get the promocode data
        $promocode = $this->find($id);

        if (!$promocode) {
            return null;
        }

        // If no language specified, get default language
        if (!$language_code) {
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            $language_code = $languages[0]['code'] ?? 'en';
        }

        // Get translated message
        $translatedModel = new \App\Models\TranslatedPromocodeModel();
        $translated_message = $translatedModel->getTranslation($id, $language_code);

        // Add translated message to promocode data
        $promocode['message'] = $translated_message;

        return $promocode;
    }

    /**
     * Get all promocodes with translated messages for a specific language
     * 
     * @param string $language_code The language code (optional, defaults to default language)
     * @param array $where Additional where conditions
     * @return array Array of promocodes with translated messages
     */
    public function getAllWithTranslations($language_code = null, $where = [])
    {
        // If no language specified, get default language
        if (!$language_code) {
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            $language_code = $languages[0]['code'] ?? 'en';
        }

        // Get all promocodes
        $promocodes = $this->where($where)->findAll();

        // Get translations for all promocodes
        $translatedModel = new \App\Models\TranslatedPromocodeModel();
        $promocode_ids = array_column($promocodes, 'id');

        $translations = [];
        if (!empty($promocode_ids)) {
            $translation_results = $translatedModel->whereIn('promocode_id', $promocode_ids)
                ->where('language_code', $language_code)
                ->findAll();

            foreach ($translation_results as $translation) {
                $translations[$translation['promocode_id']] = $translation['message'];
            }
        }

        // Add translated messages to promocodes
        foreach ($promocodes as &$promocode) {
            $promocode['message'] = $translations[$promocode['id']] ?? '';
        }

        return $promocodes;
    }

    /**
     * Deactivate promo codes whose end_date has fully elapsed (UTC midnight boundary,
     * matching partner_subscriptions expiry cron logic).
     *
     * @return int Number of rows deactivated
     */
    public function expireEndedPromoCodes(): int
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $today = $now->format('Y-m-d');

        $builder = $this->builder();
        $builder->where('status', 1)
            ->where('end_date <', $today)
            ->update(['status' => 0]);

        return $this->db->affectedRows();
    }
}
