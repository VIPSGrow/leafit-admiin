<?php

namespace App\Controllers\Admin;

use App\Models\Featured_sections_model;
use App\Models\TranslatedFeaturedSections_model;
use App\Services\utility\FileService;
use Exception;

class Featured_sections extends Admin
{
    public $validation, $sections, $creator_id;
    protected $superadmin;
    protected $translatedSectionModel;
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        helper(['form', 'url', 'ResponceServices']);
        $this->sections = new Featured_sections_model();
        $this->validation = \Config\Services::validation();
        $this->translatedSectionModel = new TranslatedFeaturedSections_model();
        $this->fileService = new FileService();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('featured_section', 'Featured section') . ' | ' . labels('admin_panel', 'Admin Panel'), 'featured_sections');

        // Categories with translations
        $this->data['categories_name'] = get_categories_with_translated_names();

        // Get current + default language
        $currentLanguage = get_current_language();
        $defaultLanguage = get_default_language();

        $db = \Config\Database::connect();
        // Fetch partners with translated company_name
        $partners = $db->table('users u')
            ->select('
        u.id,
        u.username,
        pd.partner_id,
        pd.company_name,
        pd.number_of_members,
        pd.at_store,
        pd.at_doorstep,
        pd.need_approval_for_the_service,
        tpd_current.company_name as current_translated_name,
        tpd_default.company_name as default_translated_name
    ')
            ->join('partner_details pd', 'pd.partner_id = u.id')
            ->join(
                'translated_partner_details tpd_current',
                'tpd_current.partner_id = pd.partner_id 
         AND tpd_current.language_code = "' . $currentLanguage . '"',
                'left'
            )
            ->join(
                'translated_partner_details tpd_default',
                'tpd_default.partner_id = pd.partner_id 
         AND tpd_default.language_code = "' . $defaultLanguage . '"',
                'left'
            )
            ->where('pd.is_approved', '1')
            ->get()
            ->getResultArray();

        // Replace with translated names where available
        foreach ($partners as &$partner) {
            if (!empty($partner['current_translated_name'])) {
                $partner['display_company_name'] = $partner['current_translated_name'];
            } elseif (!empty($partner['default_translated_name'])) {
                $partner['display_company_name'] = $partner['default_translated_name'];
            } else {
                $partner['display_company_name'] = $partner['company_name'];
            }
        }

        // print_r($partners[0]);
        // die;
        $this->data['partners'] = $partners;

        // If you also want a separate provider_title (id, partner_id, company_name)
        $this->data['provider_title'] = array_map(function ($p) {
            return [
                'id' => $p['id'],
                'partner_id' => $p['partner_id'],
                'company_name' => $p['display_company_name'],
            ];
        }, $partners);

        // fetch languages
        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
        $this->data['languages'] = $languages;
        return view('backend/admin/template', $this->data);
    }
    public function add_featured_section()
    {
        try {
            // $result = checkModificationInDemoMode($this->superadmin);
            // if ($result !== true) {
            //     return $this->response->setJSON($result);
            // }
            $permission = is_permitted($this->creator_id, 'create', 'featured_section');
            if (!$permission) {
                return NoPermission();
            }
            $section_type = $this->request->getPost('section_type') ?? "";

            $common_rules = [
                'section_type' => [
                    "rules" => 'required|trim',
                    "errors" => ["required" => labels("please_select_type_for_feature_section", "Please select type for feature section")]
                ]
            ];

            // Get default language for validation
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
            $defaultLanguage = null;
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            // Default language validation is handled by validateDefaultLanguageFields method
            if ($section_type == 'banner') {
                // Add banner-specific validation rules to existing common_rules
                $common_rules['app_image'] = ["rules" => 'uploaded[app_image]', "errors" => ["uploaded" => labels(THE_APP_IMAGE_FIELD_IS_REQUIRED, "The App image field is required"),]];
                $common_rules['web_image'] = ["rules" => 'uploaded[web_image]', "errors" => ["uploaded" => labels(THE_WEB_IMAGE_FIELD_IS_REQUIRED, "The Web image field is required"),]];

                if (isset($_FILES['app_image']) && !empty($_FILES['app_image']['name'])) {
                    $common_rules['app_image_type'] = [
                        "rules" => 'is_image[app_image]',
                        "errors" => [
                            "is_image" => labels("uploaded_app_banner_file_must_be_an_image", "Uploaded app banner file must be an image")
                        ]
                    ];
                }

                if (isset($_FILES['web_image']) && !empty($_FILES['web_image']['name'])) {
                    $common_rules['web_image_type'] = [
                        "rules" => 'is_image[web_image]',
                        "errors" => [
                            "is_image" => labels("uploaded_web_banner_file_must_be_an_image", "Uploaded web banner file must be an image")
                        ]
                    ];
                }
            }
            // Build validation rules based on section type
            $rules = $common_rules;

            if ($section_type == 'categories') {
                // Validate that at least one category is selected
                if (empty($_POST['category_item']) || !is_array($_POST['category_item']) || count(array_filter($_POST['category_item'])) == 0) {
                    return ErrorResponse([labels("please_select_at_least_one", "Please select at least one") . " " . labels("category", "category")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'partners') {
                // Validate that at least one partner is selected
                if (empty($_POST['partners_ids']) || !is_array($_POST['partners_ids']) || count(array_filter($_POST['partners_ids'])) == 0) {
                    return ErrorResponse([labels("please_select_at_least_one", "Please select at least one") . " " . labels("provider", "provider")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'banner') {
                // Validate banner_type is selected
                $banner_type = $this->request->getPost('banner_type');
                if (empty($banner_type)) {
                    return ErrorResponse([labels("please_select_banner_type", "Please select banner type")], true, [], [], 200, csrf_token(), csrf_hash());
                }
                // Validate banner sub-type based on banner_type
                if ($banner_type == 'banner_category') {
                    if (empty($_POST['banner_category_item']) || trim($_POST['banner_category_item']) == '') {
                        return ErrorResponse([labels("please_select_category", "Please select a category")], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } elseif ($banner_type == 'banner_provider') {
                    if (empty($_POST['banner_providers']) || trim($_POST['banner_providers']) == '') {
                        return ErrorResponse([labels("please_select_provider", "Please select a provider")], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } elseif ($banner_type == 'banner_url') {
                    if (empty($_POST['url']) || trim($_POST['url']) == '') {
                        return ErrorResponse([labels("please_enter_url", "Please enter a URL")], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            } elseif ($section_type == 'top_rated_partner') {
                // Validate limit field
                $limit = $this->request->getPost('limit');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number_for_top_rated_providers", "Please enter a valid number for top rated providers")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'previous_order') {
                // Validate previous_order_limit field
                $limit = $this->request->getPost('previous_order_limit');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number_for_previous_bookings", "Please enter a valid number for previous bookings")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'ongoing_order') {
                // Validate ongoing_order_limit field
                $limit = $this->request->getPost('ongoing_order_limit');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number_for_ongoing_bookings", "Please enter a valid number for ongoing bookings")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'near_by_provider') {
                // Validate limit_for_near_by_providers field
                $limit = $this->request->getPost('limit_for_near_by_providers');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number_for_near_by_providers", "Please enter a valid number for near by providers")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            $this->validation->setRules($rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Additional validation for default language title and description
            $validationErrors = $this->validateDefaultLanguageFields($_POST, $defaultLanguage);
            if (!empty($validationErrors)) {
                return ErrorResponse($validationErrors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            $sections = fetch_details('sections');
            if (!empty($sections)) {
                foreach ($sections as $row) {
                    if ($section_type == $row['section_type']) {
                        if ($row['section_type'] == "ongoing_order" || $row['section_type'] == "previous_order") {
                            return ErrorResponse(labels("you_may_only_include_the", "You may only include the") . " " . labels($section_type) . " " . labels("section_once", "section once."), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                }
            }
            $data = [];
            $data = [
                'partners_ids' => null,
                'category_ids' => null,
                'banner_type' => null,
                'banner_url' => null,
                'limit' => 0
            ];
            if (isset($section_type)) {
                if ($section_type == 'partners') {
                    $data['partners_ids'] = implode(',', $_POST['partners_ids']);
                } elseif ($section_type == 'categories') {
                    $data['category_ids'] = implode(',', $_POST['category_item']);
                } elseif ($section_type == 'top_rated_partner') {
                    $data['limit'] = $this->request->getPost('limit');
                } elseif ($section_type == 'previous_order') {
                    $data['limit'] = $this->request->getPost('previous_order_limit');
                } elseif ($section_type == 'ongoing_order') {
                    $data['limit'] = $this->request->getPost('ongoing_order_limit');
                } elseif ($section_type == 'near_by_provider') {
                    $data['limit'] = $this->request->getPost('limit_for_near_by_providers');
                } elseif ($section_type == 'banner') {
                    $banner_type = $this->request->getPost('banner_type');
                    $data['banner_type'] = $banner_type;
                    if ($banner_type == "banner_category") {
                        $data['category_ids'] = $_POST['banner_category_item'];
                    } else if ($banner_type == "banner_provider") {
                        $data['partners_ids'] = $_POST['banner_providers'];
                    } else if ($banner_type == "banner_url") {
                        $data['banner_url'] = $_POST['url'];
                    }
                    foreach (['app_image' => 'app_banner_image', 'web_image' => 'web_banner_image'] as $field => $column) {
                        $file = $this->request->getFile($field);
                        if ($file && $file->isValid()) {
                            $result = $this->fileService->upload($file, 'feature_section');
                            if ($result['error']) {
                                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                            }
                            $data[$column] = $result['path'];
                        }
                    }
                }
            }



            $data['status'] = isset($_POST['status']) ? 1 : 0;

            // Process translated fields from form data
            $translatedFields = $this->processTranslatedFields($_POST);

            // Remove title and description from main table - they will only be stored in translations table
            // $data['title'] = $translatedFields['title'][$defaultLanguage] ?? "";
            // $data['description'] = $translatedFields['description'][$defaultLanguage] ?? "";

            $data['section_type'] = $section_type;
            $db = \Config\Database::connect();
            $builder = $db->table('sections');
            $builder->selectMax('rank');
            $order = $builder->get()->getResultArray();
            $data['rank'] = ($order[0]['rank']) + 1;

            // Save the main section data
            if ($this->sections->save($data)) {
                $sectionId = $this->sections->getInsertID();

                // Store translations for all languages
                if ($this->storeSectionTranslations($sectionId, $translatedFields)) {
                    return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    // If translation storage fails, delete the main record and return error
                    $this->sections->delete($sectionId);
                    return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse(labels("please_try_again", "Please try again...."), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Featured_sections.php - add_featured_section()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list()
    {
        try {
            $multipleWhere = '';
            $db = \Config\Database::connect();
            $builder = $db->table('sections s');
            // Map frontend field names to actual database column names
            $sortable_fields = [
                'id' => 's.id',
                'rank' => 's.rank',
                'section_type_badge' => 's.section_type',  // Map computed field to actual column
                'section_type' => 's.section_type',
                'status_badge' => 's.status',  // Map computed field to actual column
                'status' => 's.status',
                'created_at' => 's.created_at',
                'banner_type_badge' => 's.banner_type',  // Map computed field to actual column (if sortable in future)
                'banner_type' => 's.banner_type',
                'category_ids' => 's.category_ids',
                'partners_ids' => 's.partners_ids'
            ];
            $sort = 's.rank';
            $limit = 10;
            $condition = [];
            $offset = 0;
            if (isset($_GET['offset'])) {
                $offset = $_GET['offset'];
            }
            if (isset($_GET['limit'])) {
                $limit = $_GET['limit'];
            }
            if (isset($_GET['sort'])) {
                $requested_sort = $_GET['sort'];
                // Check if the requested sort field is in our mapping
                if (isset($sortable_fields[$requested_sort])) {
                    $sort = $sortable_fields[$requested_sort];
                } else {
                    // Default to rank if unknown field
                    $sort = 's.rank';
                }
            }
            $order = "ASC";
            if (isset($_GET['order'])) {
                $order = $_GET['order'];
            }
            // Handle search functionality - search in section_type, title, and description
            $searchTerm = '';
            if (isset($_GET['search']) and $_GET['search'] != '') {
                $searchTerm = $_GET['search'];
                // Join with translations table to search in title and description
                $builder->join('translated_featured_sections tfs', 'tfs.section_id = s.id', 'left');
                // Group search conditions: search in section_type, title, and description
                $builder->groupStart();

                // Map display names to database keys for section_type search
                // This allows users to search by display names like "Categories" or "Partners"
                // and it will match the corresponding database keys like "categories" or "partners"
                $sectionTypeKeys = get_section_type_keys_from_display_name($searchTerm);

                // Search by section_type: check both the search term and mapped keys
                if (!empty($sectionTypeKeys)) {
                    // If we found matching keys from display name, search for those keys
                    $builder->groupStart();
                    $builder->like('s.section_type', $searchTerm); // Search original term (supports partial matches)
                    foreach ($sectionTypeKeys as $key) {
                        // Search by mapped keys using LIKE for partial matches
                        // This supports searching "categ" and finding "categories"
                        $builder->orLike('s.section_type', $key);
                    }
                    $builder->groupEnd();
                } else {
                    // No display name match found, search by original term
                    $builder->like('s.section_type', $searchTerm);
                }

                // Search in translated title and description
                $builder->orLike('tfs.title', $searchTerm);
                $builder->orLike('tfs.description', $searchTerm);

                // Also allow searching by ID (exact match)
                if (is_numeric($searchTerm)) {
                    $builder->orWhere('s.id', $searchTerm);
                }
                $builder->groupEnd();
            }
            if (isset($_GET['feature_section_filter']) && $_GET['feature_section_filter'] != '') {
                $builder->where('s.status', $_GET['feature_section_filter']);
            }
            // Get total count for pagination
            $total = $builder->select(' COUNT(DISTINCT s.id) as `total` ');
            if (isset($_GET['id']) && $_GET['id'] != '') {
                $builder->where($condition);
            }
            if (isset($where) && !empty($where)) {
                $builder->where($where);
            }
            $offer_count = $builder->get()->getResultArray();
            $total = $offer_count[0]['total'];

            // Reset builder for data query
            $builder = $db->table('sections s');
            // Re-apply search conditions for data query
            if ($searchTerm != '') {
                $builder->join('translated_featured_sections tfs', 'tfs.section_id = s.id', 'left');
                $builder->groupStart();

                // Map display names to database keys for section_type search
                // This allows users to search by display names like "Categories" or "Partners"
                // and it will match the corresponding database keys like "categories" or "partners"
                $sectionTypeKeys = get_section_type_keys_from_display_name($searchTerm);

                // Search by section_type: check both the search term and mapped keys
                if (!empty($sectionTypeKeys)) {
                    // If we found matching keys from display name, search for those keys
                    $builder->groupStart();
                    $builder->like('s.section_type', $searchTerm); // Search original term (supports partial matches)
                    foreach ($sectionTypeKeys as $key) {
                        // Search by mapped keys using LIKE for partial matches
                        // This supports searching "categ" and finding "categories"
                        $builder->orLike('s.section_type', $key);
                    }
                    $builder->groupEnd();
                } else {
                    // No display name match found, search by original term
                    $builder->like('s.section_type', $searchTerm);
                }

                // Search in translated title and description
                $builder->orLike('tfs.title', $searchTerm);
                $builder->orLike('tfs.description', $searchTerm);

                if (is_numeric($searchTerm)) {
                    $builder->orWhere('s.id', $searchTerm);
                }
                $builder->groupEnd();
            }
            if (isset($where) && !empty($where)) {
                $builder->where($where);
            }
            if (isset($_GET['feature_section_filter']) && $_GET['feature_section_filter'] != '') {
                $builder->where('s.status', $_GET['feature_section_filter']);
            }
            // Group by section ID to avoid duplicate rows from join with translations
            // This ensures we get one row per section even if it has multiple language translations
            if ($searchTerm != '') {
                $builder->groupBy('s.id');
            }
            $offer_recored = $builder->select('s.*')->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();
            $tempRow = array();
            $user1 = fetch_details('users', ["phone" => $_SESSION['identity']], );
            $permissions = get_permission($user1[0]['id']);

            // Get all section IDs for batch translation lookup
            $sectionIds = array_column($offer_recored, 'id');

            // Get current language for display
            $currentLanguage = get_current_language();
            $defaultLanguage = get_default_language();

            // Get all translations for all sections in one query for efficiency
            $allTranslations = [];
            if (!empty($sectionIds)) {
                $translationBuilder = $db->table('translated_featured_sections');
                $translations = $translationBuilder->whereIn('section_id', $sectionIds)->get()->getResultArray();

                // Organize translations by section_id and language_code
                foreach ($translations as $translation) {
                    $allTranslations[$translation['section_id']][$translation['language_code']] = $translation;
                }
            }

            foreach ($offer_recored as $row) {
                $operations = "";
                $label = ($row['status'] == 1) ?
                    "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('active', 'Active') . "
                    </div>" :
                    "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('deactive', 'Deactive') . "
                </div>";

                $operations = '<div class="dropdown">
                <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                </a>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';

                $operations .= '<a class="dropdown-item update_featured_section "data-id="' . $row['id'] . '"  data-id="' . $row['id'] . '"  data-toggle="modal" data-target="#update_modal" onclick="feature_section_id(this)"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>';

                $operations .= '<a class="dropdown-item delete-featured_section" data-id="' . $row['id'] . '" onclick="feature_section_id(this)" data-toggle="modal" data-target="#delete_modal"> <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';

                $operations .= '</div></div>';

                if ($row['section_type'] == "banner") {
                    $app_banner_url = $this->fileService->url($row['app_banner_image'], 'feature_section');
                    $app_banner_image = '  <a  href="' . $app_banner_url . '" data-lightbox="image-1"><img class="o-media__img images_in_card" src="' . $app_banner_url . '" alt="' . $row['id'] . '"></a>';
                    $web_banner_url = $this->fileService->url($row['web_banner_image'], 'feature_section');
                    $web_banner_image = '  <a  href="' . $web_banner_url . '" data-lightbox="image-1"><img class="o-media__img images_in_card" src="' . $web_banner_url . '" alt="' . $row['id'] . '"></a>';
                } else {
                    $app_banner_image = '-';
                    $web_banner_image = '-';
                }

                $tempRow['id'] = $row['id'];

                // Get title with proper fallback logic:
                // 1. Current language translation
                // 2. Default language translation  
                // 3. Main table data (if available)
                // 4. Fallback to "-"
                $displayTitle = $this->getDisplayTitle($row, $allTranslations, $currentLanguage, $defaultLanguage);
                $tempRow['title'] = $displayTitle;

                $tempRow['category_ids'] = $row['category_ids'];
                $tempRow['section_type'] = $row['section_type'];
                $tempRow['section_type_badge'] = feature_section_type($row['section_type']);
                $tempRow['banner_type_badge'] = banner_type($row['banner_type']);
                $tempRow['partners_ids'] = $row['partners_ids'];
                $tempRow['created_at'] = format_date($row['created_at'], 'd-m-Y');
                $tempRow['status'] = $row['status'];
                $tempRow['status_badge'] = $label;
                $tempRow['rank'] = $row['rank'];
                $tempRow['limit'] = $row['limit'];
                $tempRow['app_banner_image'] = $app_banner_image;
                $tempRow['web_banner_image'] = $web_banner_image;
                $tempRow['banner_url'] = $row['banner_url'];
                $tempRow['banner_type'] = $row['banner_type'];

                // Get description with proper fallback logic:
                // 1. Current language translation
                // 2. Default language translation  
                // 3. Main table data (if available)
                // 4. Fallback to empty string
                $displayDescription = $this->getDisplayDescription($row, $allTranslations, $currentLanguage, $defaultLanguage);
                $tempRow['description'] = $displayDescription;

                $tempRow['icon'] = '<i class="fas fa-sort text-new-primary drag-handle"></i>';
                $tempRow['operations'] = $operations;
                $rows[] = $tempRow;
            }
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Featured_sections.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_featured_section()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = is_permitted($this->creator_id, 'delete', 'featured_section');
            if (!$permission) {
                return NoPermission();
            }
            $id = $this->request->getPost('id');
            $db = \Config\Database::connect();
            $fetch_old_data = fetch_details('sections', ['id' => $id]);
            if ($fetch_old_data[0]['section_type'] == "banner") {
                $this->fileService->delete('feature_section', $fetch_old_data[0]['app_banner_image']);
                $this->fileService->delete('feature_section', $fetch_old_data[0]['web_banner_image']);
            }
            // First, delete all translations for this section
            $translationBuilder = $db->table('translated_featured_sections');
            $translationBuilder->delete(['section_id' => $id]);

            // Then delete the main section
            $builder = $db->table('sections');
            $builder->delete(['id' => $id]);
            $builder = $db->table('sections');
            $builder->orderBy('rank', 'ASC');
            $sections = $builder->get()->getResultArray();
            foreach ($sections as $index => $section) {
                $newRank = $index + 1;
                $builder->where('id', $section['id']);
                $builder->update(['rank' => $newRank]);
            }
            return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Featured_sections.php - delete_featured_section()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update_featured_section()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            // $result = checkModificationInDemoMode($this->superadmin);
            // if ($result !== true) {
            //     return $this->response->setJSON($result);
            // }
            $id = $this->request->getPost('id');
            $db = \Config\Database::connect();
            $builder = $db->table('sections');
            $permission = is_permitted($this->creator_id, 'update', 'featured_section');
            if (!$permission) {
                return NoPermission();
            }
            $section_type = ($this->request->getPost('section_type')) ? $this->request->getPost('section_type') : "";
            $common_rules = [
                'section_type' => [
                    "rules" => 'required|trim',
                    "errors" => ["required" => labels("please_select_type_for_feature_section", "Please select type for feature section")]
                ]
            ];

            // Get default language for validation
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
            $defaultLanguage = null;
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            // Build validation rules based on section type
            $rules = $common_rules;

            if ($section_type == 'categories') {
                // Validate that at least one category is selected
                if (empty($_POST['edit_Category_item']) || !is_array($_POST['edit_Category_item']) || count(array_filter($_POST['edit_Category_item'])) == 0) {
                    return ErrorResponse([labels("please_select_at_least_one", "Please select at least one") . " " . labels("category", "category")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'partners') {
                // Validate that at least one partner is selected
                if (empty($_POST['edit_partners_ids']) || !is_array($_POST['edit_partners_ids']) || count(array_filter($_POST['edit_partners_ids'])) == 0) {
                    return ErrorResponse([labels("please_select_at_least_one", "Please select at least one") . " " . labels("provider", "provider")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'banner') {
                // Validate banner_type is selected
                $banner_type = $this->request->getPost('banner_type');
                if (empty($banner_type)) {
                    return ErrorResponse([labels("please_select_banner_type", "Please select banner type")], true, [], [], 200, csrf_token(), csrf_hash());
                }
                // Validate banner sub-type based on banner_type
                if ($banner_type == 'banner_category') {
                    if (empty($_POST['banner_category_item']) || trim($_POST['banner_category_item']) == '') {
                        return ErrorResponse([labels("please_select_category", "Please select a category")], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } elseif ($banner_type == 'banner_provider') {
                    if (empty($_POST['banner_providers']) || trim($_POST['banner_providers']) == '') {
                        return ErrorResponse([labels("please_select_provider", "Please select a provider")], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } elseif ($banner_type == 'banner_url') {
                    if (empty($_POST['url']) || trim($_POST['url']) == '') {
                        return ErrorResponse([labels("please_enter_url", "Please enter a URL")], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            } elseif ($section_type == 'top_rated_partner') {
                // Validate limit field
                $limit = $this->request->getPost('limit');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number", "Please enter a valid number") . " " . labels("for_top_rated_providers", "for top rated providers")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'previous_order') {
                // Validate previous_order_limit field
                $limit = $this->request->getPost('previous_order_limit');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number", "Please enter a valid number") . " " . labels("for_previous_bookings", "for previous bookings")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'ongoing_order') {
                // Validate ongoing_order_limit field
                $limit = $this->request->getPost('ongoing_order_limit');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number", "Please enter a valid number") . " " . labels("for_ongoing_bookings", "for ongoing bookings")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            } elseif ($section_type == 'near_by_provider') {
                // Validate limit_for_near_by_providers field
                $limit = $this->request->getPost('limit_for_near_by_providers');
                if (empty($limit) || !is_numeric($limit) || $limit <= 0) {
                    return ErrorResponse([labels("please_enter_valid_number", "Please enter a valid number") . " " . labels("for_near_by_providers", "for near by providers")], true, [], [], 200, csrf_token(), csrf_hash());
                }
            }
            $this->validation->setRules($rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Additional validation for default language title and description
            $validationErrors = $this->validateDefaultLanguageFields($_POST, $defaultLanguage);
            if (!empty($validationErrors)) {
                return ErrorResponse($validationErrors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check for duplicate section types (ongoing_order and previous_order can only exist once)
            $id = $this->request->getPost('id');
            if ($section_type == "ongoing_order" || $section_type == "previous_order") {
                $existing_sections = fetch_details('sections', ['section_type' => $section_type]);
                if (!empty($existing_sections)) {
                    // Check if there's another section with the same type (excluding current record)
                    foreach ($existing_sections as $row) {
                        if ($row['id'] != $id) {
                            return ErrorResponse(labels("you_may_only_include_the", "You may only include the") . " " . $section_type . " " . labels("section_once", "section once."), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                }
            }

            $partner_ids = $category_ids = null;

            // Process translated fields from form data
            $translatedFields = $this->processTranslatedFields($_POST);

            // Remove title and description from main table - they will only be stored in translations table
            // $data['title'] = $translatedFields['title'][$defaultLanguage] ?? "";
            // $data['description'] = $translatedFields['description'][$defaultLanguage] ?? "";

            $data['section_type'] = $_POST['section_type'];
            $data['category_ids'] = $category_ids;
            if ($_POST['section_type'] == 'partners') {
                $partner_ids = implode(',', $_POST['edit_partners_ids']);
                $data['partners_ids'] = $partner_ids;
            } elseif ($_POST['section_type'] == 'categories') {
                $category_ids = implode(',', $_POST['edit_Category_item']);
                $data['category_ids'] = $category_ids;
            } elseif ($_POST['section_type'] == 'previous_order') {
                $data['limit'] = $this->request->getPost('previous_order_limit');
                ;
            } else if (isset($section_type) && $section_type == 'ongoing_order') {
                $data['limit'] = $this->request->getPost('ongoing_order_limit');
                ;
            } else if (isset($section_type) && $section_type == 'near_by_provider') {
                $data['limit'] = $this->request->getPost('limit_for_near_by_providers');
                ;
            } elseif ($_POST['section_type'] == 'top_rated_partner') {
                // Handle top rated partner limit update
                $data['limit'] = $this->request->getPost('limit');
            } elseif ($section_type == 'banner') {
                // Remove title from main table - it will only be stored in translations table
                // $data['title'] = "";
                $banner_type = $this->request->getPost('banner_type');
                $data['banner_type'] = $banner_type;
                if ($banner_type == "banner_category") {
                    $data['category_ids'] = $_POST['banner_category_item'];
                } else if ($banner_type == "banner_provider") {
                    $data['partners_ids'] = $_POST['banner_providers'];
                } else if ($banner_type == "banner_url") {
                    $data['banner_url'] = $_POST['url'];
                }
                $old_data = fetch_details('sections', ['id' => $id]);
                $imageMap = [
                    'app_image' => ['column' => 'app_banner_image', 'old' => $old_data[0]['app_banner_image']],
                    'web_image' => ['column' => 'web_banner_image', 'old' => $old_data[0]['web_banner_image']],
                ];
                foreach ($imageMap as $field => $cfg) {
                    $file = $this->request->getFile($field);
                    if ($file->getName() !== '') {
                        $result = $this->fileService->replace($cfg['old'], $file, 'feature_section');
                        if ($result['error']) {
                            return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $data[$cfg['column']] = $result['path'];
                    } else {
                        $data[$cfg['column']] = $cfg['old'];
                    }
                }
            }
            $data['status'] = isset($_POST['edit_status']) ? 1 : 0;

            // Update the main section data
            if ($builder->update($data, ['id' => $id])) {
                // Store translations for all languages
                if ($this->storeSectionTranslations($id, $translatedFields)) {
                    return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    // If translation storage fails, return error but don't rollback main update
                    log_message('error', "Failed to save translations for section {$id}");
                    return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Featured_sections.php - update_featured_section()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    /**
     * Change the order/rank of featured sections
     * 
     * This function updates the rank field of sections based on the order
     * provided in the POST data. It validates input, checks permissions,
     * and updates each section's rank sequentially.
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with success or error message
     */
    public function change_order()
    {
        try {
            // Check if user is logged in and is admin
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            // Check permissions for update operation
            $permission = is_permitted($this->creator_id, 'update', 'featured_section');
            if (!$permission) {
                return NoPermission();
            }

            // Check if demo mode is enabled and prevent modifications
            // $result = checkModificationInDemoMode($this->superadmin);
            // if ($result !== true) {
            //     return $this->response->setJSON($result);
            // }

            // Get ids from POST data - expects JSON string array like ["102","106","89"]
            $idsInput = $this->request->getPost('ids');

            // Validate that ids parameter exists
            if (empty($idsInput)) {
                return ErrorResponse(labels("ids_parameter_is_required", "IDs parameter is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Decode HTML entities first (sanitizer encodes quotes as &quot;)
            // Then decode JSON string to array
            $decodedInput = html_entity_decode($idsInput, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $ids = json_decode($decodedInput, true);

            // Validate that JSON decoding was successful and result is an array
            if ($ids === null || !is_array($ids) || empty($ids)) {
                return ErrorResponse(labels("invalid_ids_format", "Invalid IDs format. Expected a JSON array"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Connect to database
            $db = \Config\Database::connect();
            $builder = $db->table('sections');

            // Update rank for each section based on its position in the array
            // The array index (0-based) + 1 becomes the new rank (1-based)
            foreach ($ids as $key => $id) {
                // Trim whitespace (sanitizer may add spaces)
                $id = trim($id);

                // Validate that ID is numeric
                if (!is_numeric($id) || $id <= 0) {
                    continue; // Skip invalid IDs
                }

                // Calculate new rank (array position + 1)
                $newRank = $key + 1;

                // Update the rank for this section
                // Reset builder for each iteration to avoid query builder state issues
                $builder->where('id', $id);
                $builder->update(['rank' => $newRank]);

                // Reset the builder to clear where clause for next iteration
                $builder->resetQuery();
            }

            // Return success response
            return successResponse(labels(ORDER_UPDATED_SUCCESSFULLY, "Order updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            // Log the error for debugging
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Featured_sections.php - change_order()');

            // Return error response
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Process translated fields from form data
     * 
     * This private helper function processes form data to extract translated fields
     * organized by field and language code
     * 
     * @param array $postData The POST data from the form
     * @return array Array containing translated data organized by field and language
     */
    private function processTranslatedFields(array $postData): array
    {
        $translatedFields = [
            'title' => [],
            'description' => [],
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['title']) && is_array($postData['title'])) {
            // Copy the data directly since it's already in the right structure
            $translatedFields['title'] = $postData['title'] ?? [];
            $translatedFields['description'] = $postData['description'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process title
            $titleField = 'title[' . $languageCode . ']';
            $titleValue = $postData[$titleField] ?? null;
            if (!empty($titleValue)) {
                $translatedFields['title'][$languageCode] = trim($titleValue);
            }

            // Process description
            $descriptionField = 'description[' . $languageCode . ']';
            $descriptionValue = $postData[$descriptionField] ?? null;
            if (!empty($descriptionValue)) {
                $translatedFields['description'][$languageCode] = trim($descriptionValue);
            }
        }

        return $translatedFields;
    }

    /**
     * Store section translations in the database
     * 
     * This private helper function uses the TranslatedFeaturedSections_model
     * to store multi-language translations for section fields
     * 
     * @param int $sectionId The section ID to store translations for
     * @param array $translatedFields Array containing translated data organized by field and language
     * @return bool Success status
     * @throws Exception If translation storage fails
     */
    private function storeSectionTranslations(int $sectionId, array $translatedFields): bool
    {
        try {
            // Validate that we have a valid section ID
            if (empty($sectionId)) {
                throw new Exception('Section ID is required for storing translations');
            }

            // First, delete all existing translations for this section
            // This ensures that removed translations are properly deleted
            // When a user removes translations (like clearing Hindi fields), 
            // this step ensures those translations are deleted from the database
            $deleteResult = $this->translatedSectionModel->deleteSectionTranslations($sectionId);
            if (!$deleteResult) {
                log_message('warning', "Failed to delete existing translations for section {$sectionId}");
                // Continue anyway - we'll try to insert new translations
            }

            // Get all available languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            if (empty($languages)) {
                throw new Exception('No languages found in database');
            }

            $successCount = 0;
            $totalLanguages = count($languages);

            // Process each language
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Prepare translation data for this language
                $translationData = [];

                // Add title translation if available
                if (isset($translatedFields['title'][$languageCode]) && !empty(trim($translatedFields['title'][$languageCode]))) {
                    $translationData['title'] = trim($translatedFields['title'][$languageCode]);
                }

                // Add description translation if available
                if (isset($translatedFields['description'][$languageCode]) && !empty(trim($translatedFields['description'][$languageCode]))) {
                    $translationData['description'] = trim($translatedFields['description'][$languageCode]);
                }

                // Only save if we have translation data for this language
                if (!empty($translationData)) {
                    try {
                        $result = $this->translatedSectionModel->saveTranslatedDetails(
                            $sectionId,
                            $languageCode,
                            $translationData
                        );

                        if ($result) {
                            $successCount++;
                        } else {
                            // Log the failure but continue with other languages
                            log_message('error', "Failed to save translation for section {$sectionId}, language {$languageCode}");
                        }
                    } catch (Exception $e) {
                        log_message('error', "Exception saving translation for section {$sectionId}, language {$languageCode}: " . $e->getMessage());
                    }
                } else {
                    // Count as success if no data to save (empty translations are valid)
                    $successCount++;
                }
            }

            // Return true if at least one language was processed successfully
            return $successCount > 0;
        } catch (Exception $e) {
            log_message('error', 'Error storing section translations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get section data with translations for edit modal
     * Since title and description are no longer stored in main table, only use translations from translation table
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function get_section_data()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $sectionId = $this->request->getPost('id');

            if (empty($sectionId)) {
                return ErrorResponse(labels(SECTION_ID_REQUIRED, "Section ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get section data from main table
            $sectionData = $this->sections->find($sectionId);

            if (!$sectionData) {
                return ErrorResponse(labels(SECTION_NOT_FOUND, "Section not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $sectionData['app_banner_image'] = $this->fileService->url(
                $sectionData['app_banner_image'] ?? null,
                'feature_section',
                'public/backend/assets/default.png'
            );
            $sectionData['web_banner_image'] = $this->fileService->url(
                $sectionData['web_banner_image'] ?? null,
                'feature_section',
                'public/backend/assets/default.png'
            );

            // Get all available languages
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            // Get all translations for this section
            $translations = $this->translatedSectionModel->getAllTranslationsForSection($sectionId);

            // Organize existing translations by language code for quick lookup
            $existingTranslations = [];
            foreach ($translations as $translation) {
                $existingTranslations[$translation['language_code']] = [
                    'title' => $translation['title'] ?? '',
                    'description' => $translation['description'] ?? ''
                ];
            }

            // Build complete translations array with fallback to main table title/description
            // For default language only: use main table title/description as fallback if no translation exists
            // For non-default languages: leave empty if no translation exists
            $translatedData = [];
            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefault = $language['is_default'] == 1;

                $translatedData[$languageCode] = [
                    'title' => $existingTranslations[$languageCode]['title'] ?? ($isDefault ? ($sectionData['title'] ?? '') : ''),
                    'description' => $existingTranslations[$languageCode]['description'] ?? ($isDefault ? ($sectionData['description'] ?? '') : '')
                ];
            }

            // Prepare response data
            $responseData = [
                'section_data' => $sectionData,
                'translations' => $translatedData,
                'languages' => $languages
            ];

            return successResponse(labels(DATA_RETRIEVED_SUCCESSFULLY, "Data retrieved successfully"), false, $responseData, [], 200, csrf_token(), csrf_hash());
        } catch (Exception $e) {
            log_message('error', 'Error getting section data: ' . $e->getMessage());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Validate that default language title and description are provided
     * 
     * @param array $postData The POST data
     * @param string $defaultLanguage The default language code
     * @return array Array of validation errors (empty if no errors)
     */
    private function validateDefaultLanguageFields(array $postData, string $defaultLanguage): array
    {
        $errors = [];

        // Check if we have translated fields in the new format (as arrays)
        if ($postData['section_type'] != "banner") {
            if (isset($postData['title']) && is_array($postData['title'])) {
                // New format: validate that default language title is provided
                if (!isset($postData['title'][$defaultLanguage]) || empty(trim($postData['title'][$defaultLanguage]))) {
                    $errors['title'] = labels("title_is_required_for_default_language", "Title is required for the default language");
                }
            } else {
                // Old format: validate individual language field
                $titleField = 'title[' . $defaultLanguage . ']';
                if (!isset($postData[$titleField]) || empty(trim($postData[$titleField]))) {
                    $errors[$titleField] = labels("title_is_required_for_default_language", "Title is required for the default language");
                }
            }
        }

        if (isset($postData['description']) && is_array($postData['description'])) {
            // New format: validate that default language description is provided
            if (!isset($postData['description'][$defaultLanguage]) || empty(trim($postData['description'][$defaultLanguage]))) {
                $errors['description'] = labels("description_is_required_for_default_language", "Description is required for the default language");
            }
        } else {
            // Old format: validate individual language field
            $descriptionField = 'description[' . $defaultLanguage . ']';
            if (!isset($postData[$descriptionField]) || empty(trim($postData[$descriptionField]))) {
                $errors[$descriptionField] = labels("description_is_required_for_default_language", "Description is required for the default language");
            }
        }

        return $errors;
    }

    /**
     * Get display title with proper fallback logic
     * 
     * @param array $row Section row data
     * @param array $allTranslations All translations organized by section_id and language_code
     * @param string $currentLanguage Current language code
     * @param string $defaultLanguage Default language code
     * @return string Display title with fallback
     */
    private function getDisplayTitle(array $row, array $allTranslations, string $currentLanguage, string $defaultLanguage): string
    {
        $sectionId = $row['id'];

        // 1. Try current language translation
        if (
            isset($allTranslations[$sectionId][$currentLanguage]['title']) &&
            !empty(trim($allTranslations[$sectionId][$currentLanguage]['title']))
        ) {
            return trim($allTranslations[$sectionId][$currentLanguage]['title']);
        }

        // 2. Try default language translation
        if (
            $currentLanguage !== $defaultLanguage &&
            isset($allTranslations[$sectionId][$defaultLanguage]['title']) &&
            !empty(trim($allTranslations[$sectionId][$defaultLanguage]['title']))
        ) {
            return trim($allTranslations[$sectionId][$defaultLanguage]['title']);
        }

        // 3. Try main table data (if available)
        if (isset($row['title']) && !empty(trim($row['title']))) {
            return trim($row['title']);
        }

        // 4. Fallback to "-"
        return "-";
    }

    /**
     * Get display description with proper fallback logic
     * 
     * @param array $row Section row data
     * @param array $allTranslations All translations organized by section_id and language_code
     * @param string $currentLanguage Current language code
     * @param string $defaultLanguage Default language code
     * @return string Display description with fallback
     */
    private function getDisplayDescription(array $row, array $allTranslations, string $currentLanguage, string $defaultLanguage): string
    {
        $sectionId = $row['id'];

        // 1. Try current language translation
        if (
            isset($allTranslations[$sectionId][$currentLanguage]['description']) &&
            !empty(trim($allTranslations[$sectionId][$currentLanguage]['description']))
        ) {
            return trim($allTranslations[$sectionId][$currentLanguage]['description']);
        }

        // 2. Try default language translation
        if (
            $currentLanguage !== $defaultLanguage &&
            isset($allTranslations[$sectionId][$defaultLanguage]['description']) &&
            !empty(trim($allTranslations[$sectionId][$defaultLanguage]['description']))
        ) {
            return trim($allTranslations[$sectionId][$defaultLanguage]['description']);
        }

        // 3. Try main table data (if available)
        if (isset($row['description']) && !empty(trim($row['description']))) {
            return trim($row['description']);
        }

        // 4. Fallback to empty string
        return "";
    }
}
