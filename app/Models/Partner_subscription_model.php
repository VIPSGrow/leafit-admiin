<?php

namespace App\Models;

use CodeIgniter\Model;
use PDO;

class Partner_subscription_model extends Model
{
    protected $table = 'partner_subscriptions';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'subscription_id',
        'status',
        'created_at',
        'updated_at',
        'id',
        'is_payment',
        'partner_id',
        'purchase_date',
        'expiry_date',
        'name',
        'description',
        'duration',
        'price',
        'discount_price',
        'publish',
        'order_type',
        'max_order_limit',
        'service_type',
        'max_service_limit',
        'tax_type',
        'tax_id',
        'is_commision',
        'commission_threshold',
        'commission_percentage',
        'transaction_id',
        'tax_percentage',
    ];
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        $db = \Config\Database::connect();
        $builder = $db->table('partner_subscriptions ps');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];

        // Get current language and default language for translations
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        $sort = "ps.id";
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'ps.id') {
                $sort = "ps.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        $order = "DESC";
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }

        // Build search conditions first
        // Search functionality should work for all languages, including subscription names from translated tables
        // This ensures users can search by subscription names regardless of their selected language
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`ps.id`' => $search,
                '`ps.name`' => $search,
                '`ps.description`' => $search,
                '`ps.status`' => $search,
                '`pd.company_name`' => $search,
            ];

            // Always include translated fields in search to allow searching by translated subscription names, descriptions, and company names
            // This ensures users can find subscriptions using their language-specific terms
            // Search in both current language and default language translation tables for comprehensive results
            // This allows searching subscription names even when they're stored in translation tables
            $multipleWhere['`tsd_current.name`'] = $search;
            $multipleWhere['`tsd_current.description`'] = $search;
            $multipleWhere['`tsd_default.name`'] = $search;
            $multipleWhere['`tsd_default.description`'] = $search;
            $multipleWhere['`tpd_current.company_name`'] = $search;
            $multipleWhere['`tpd_default.company_name`'] = $search;
        }

        // Count total subscriptions with translation joins for accurate count when searching translated fields
        // Create a separate builder instance for count to avoid state issues
        $countBuilder = $db->table('partner_subscriptions ps');
        $countBuilder->join('partner_details pd', 'pd.partner_id=ps.partner_id', 'left')
            ->join('translated_subscription_details tsd_current', "tsd_current.subscription_id = ps.subscription_id AND tsd_current.language_code = '$currentLang'", 'left')
            ->join('translated_subscription_details tsd_default', "tsd_default.subscription_id = ps.subscription_id AND tsd_default.language_code = '$defaultLang'", 'left')
            ->join('translated_partner_details tpd_current', "tpd_current.partner_id = ps.partner_id AND tpd_current.language_code = '$currentLang'", 'left')
            ->join('translated_partner_details tpd_default', "tpd_default.partner_id = ps.partner_id AND tpd_default.language_code = '$defaultLang'", 'left');
        $countBuilder->select('count(DISTINCT ps.id) as total');

        if (isset($where) && !empty($where)) {
            $countBuilder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $countBuilder->groupStart();
            $countBuilder->orLike($multipleWhere);
            $countBuilder->groupEnd();
        }
        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $countBuilder->where('ps.status', $_GET['subscription_filter']);
        }
        $subscription = $countBuilder->get()->getResultArray();
        $total = $subscription[0]['total'];

        // Build query with translations for subscription details and partner details
        // Create a fresh builder instance for the main query to ensure clean state
        $builder->join('partner_details pd', 'pd.partner_id=ps.partner_id', 'left')
            ->join('translated_subscription_details tsd_current', "tsd_current.subscription_id = ps.subscription_id AND tsd_current.language_code = '$currentLang'", 'left')
            ->join('translated_subscription_details tsd_default', "tsd_default.subscription_id = ps.subscription_id AND tsd_default.language_code = '$defaultLang'", 'left')
            ->join('translated_partner_details tpd_current', "tpd_current.partner_id = ps.partner_id AND tpd_current.language_code = '$currentLang'", 'left')
            ->join('translated_partner_details tpd_default', "tpd_default.partner_id = ps.partner_id AND tpd_default.language_code = '$defaultLang'", 'left');
        $builder->select('
            ps.*,
            pd.company_name,
            pd.banner,
            COALESCE(tsd_current.name, tsd_default.name, ps.name) as translated_name,
            COALESCE(tsd_current.description, tsd_default.description, ps.description) as translated_description,
            COALESCE(tpd_current.company_name, tpd_default.company_name, pd.company_name) as translated_company_name
        ');

        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $builder->where('ps.status', $_GET['subscription_filter']);
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $subscription_record = [];
        $subscription_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $operations = "";
        foreach ($subscription_record as $row) {
            $publish_badge = ($row['publish'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('yes', 'Yes') . "
                    </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('no', 'No') . "
                    </div>";
            $status_badge = "";
            switch ($row['status']) {
                case 'active':
                    $status_badge = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success  dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('active', 'Active') . "
                        </div>";
                    break;
                case 'deactive':
                    $status_badge = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('deactive', 'Deactive') . "
                        </div>";
                    break;
                default:
                    $status_badge = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('pending', 'Pending') . "
                        </div>";
                    ;
                    break;
            }

            $is_commision_badge = ($row['is_commision'] == "yes") ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('yes', 'Yes') . "
                            </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('no', 'No') . "
                            </div>";
            if (($row['is_payment'] == "1")) {
                $is_payment = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('success', 'Success') . "
                </div>";
            } elseif ($row['is_payment'] = "2") {
                $is_payment = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('failed', 'Failed') . "                </div>";
            }
            if (!empty($row['banner'])) {
                $row['banner'] = get_file_url('local_server', $row['banner'], 'public/uploads/profiles/default.png', 'banner');
                $tempRow['banner_image'] = $row['banner'];
            } else {
                $tempRow['banner_image'] = '';
            }
            if (isset($row['banner']) && !empty($row['banner']) && check_exists(base_url($row['banner']))) {
                $images = '<a  href="' . ($row['banner']) . '" data-lightbox="image-1"><img height="80px" width="80px" style="border-radius: 40px!important;" class="rounded p-1" src="' . ($row['banner']) . '" alt=""></a>';
            } else {
                $images = labels('nothing_found', 'Nothing found');
            }
            $operations = '<a href="' . base_url('/admin/partners/partner_subscription/' . $row['partner_id']) . '" class="btn btn-info ml-1 btn-sm"  title = "' . labels('view_partner', 'View partner') . '"> <i class="fa fa-eye" aria-hidden="true"></i> ' . labels('view', 'View') . ' </a> ';
            $tempRow['id'] = $row['id'];

            // Keep original fields unchanged
            $tempRow['name'] = $row['name'];
            $tempRow['description'] = $row['description'];

            // Add translated fields as separate keys
            $tempRow['translated_name'] = !empty($row['translated_name']) ? $row['translated_name'] : $row['name'];
            $tempRow['translated_description'] = !empty($row['translated_description']) ? $row['translated_description'] : $row['description'];

            $tempRow['duration'] = $row['duration'];
            $tempRow['price'] = $row['price'];
            $tempRow['discount_price'] = $row['discount_price'];
            $tempRow['publish'] = $row['publish'];
            $tempRow['publish_badge'] = $publish_badge;
            $tempRow['order_type'] = $row['order_type'];
            $tempRow['max_order_limit'] = $row['max_order_limit'];
            $tempRow['tax_type'] = $row['tax_type'];
            $tempRow['purchase_date'] = format_date($row['purchase_date'], 'd-m-Y');
            $tempRow['expiry_date'] = format_date($row['expiry_date'], 'd-m-Y');
            $tempRow['tax_id'] = $row['tax_id'];
            $tempRow['is_commision'] = $row['is_commision'];
            $tempRow['commission_threshold'] = $row['commission_threshold'];
            $tempRow['commission_percentage'] = $row['commission_percentage'];
            $tempRow['status'] = $row['status'];
            $tempRow['status_badge'] = $status_badge;
            $tempRow['is_commision_badge'] = $is_commision_badge;
            $tempRow['operations'] = $operations;
            $price = calculate_partner_subscription_price($row['partner_id'], $row['subscription_id'], $row['id']);
            $tempRow['tax_value'] = $price[0]['tax_value'];
            $tempRow['price_with_tax'] = $price[0]['price_with_tax'];
            $tempRow['original_price_with_tax'] = $price[0]['original_price_with_tax'];
            $tempRow['tax_percentage'] = $price[0]['tax_percentage'] . "%";
            $tempRow['banner_image'] = $images;

            // Keep original company name unchanged
            $tempRow['company_name'] = $row['company_name'];

            // Add translated company name as separate key
            $tempRow['translated_company_name'] = !empty($row['translated_company_name']) ? $row['translated_company_name'] : $row['company_name'];

            $tempRow['is_payment'] = $is_payment;
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
    public function subscriber_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        $db = \Config\Database::connect();
        $builder = $db->table('partner_subscriptions ps');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];

        // Get current language and default language for translations
        // This ensures proper fallback chain: selected language → default language → main table
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        if (isset($_GET['offset'])) {
            $offset = $_GET['offset'];
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $builder->where('ps.status', $_GET['subscription_filter']);
        }
        $sort = "ps.id";
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'ps.id') {
                $sort = "ps.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        $order = "DESC";
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }

        // Build search conditions first
        $search = '';
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $multipleWhere = [
                'ps.id' => $search,
                'ps.name' => $search,
                'ps.description' => $search,
                'ps.status' => $search,
                'pd.company_name' => $search,
                'u.username' => $search,
            ];

            // Include translated fields in search to allow searching by translated subscription names, descriptions, and company names
            // This ensures users can find subscriptions using their language-specific terms
            // Only add translated fields if not English and if we have a search term
            if ($currentLang !== 'en') {
                $multipleWhere['tsd.name'] = $search;
                $multipleWhere['tsd.description'] = $search;
                $multipleWhere['tpd.company_name'] = $search;
            }
        }

        // Get latest subscription IDs per partner first (needed for both count and main query)
        $subQuery = $db->table('partner_subscriptions sub')
            ->select('MAX(id) as latest_id, partner_id')
            ->groupBy('partner_id')
            ->get();
        $latestIds = [];
        foreach ($subQuery->getResult() as $row) {
            $latestIds[$row->partner_id] = $row->latest_id;
        }

        // Count total subscriptions with translation joins for accurate count when searching translated fields
        // Create a separate builder instance for count to avoid state issues
        // Include both current and default language joins for proper fallback support
        $countBuilder = $db->table('partner_subscriptions ps');
        $countBuilder->join('partner_details pd', 'pd.partner_id = ps.partner_id', 'left')
            ->join('users u', 'u.id = ps.partner_id', 'left')
            ->join('translated_partner_details tpd', "tpd.partner_id = ps.partner_id AND tpd.language_code = '$currentLang'", 'left')
            ->join('translated_partner_details tpd_default', "tpd_default.partner_id = ps.partner_id AND tpd_default.language_code = '$defaultLang'", 'left')
            ->join('translated_subscription_details tsd', "tsd.subscription_id = ps.subscription_id AND tsd.language_code = '$currentLang'", 'left')
            ->join('translated_subscription_details tsd_default', "tsd_default.subscription_id = ps.subscription_id AND tsd_default.language_code = '$defaultLang'", 'left');
        $countBuilder->select('count(DISTINCT ps.id) as total');

        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $countBuilder->where('ps.status', $_GET['subscription_filter']);
        }
        if (isset($where) && !empty($where)) {
            $countBuilder->where($where);
        }
        if (!empty($multipleWhere)) {
            $countBuilder->groupStart();
            $countBuilder->orLike($multipleWhere);
            $countBuilder->groupEnd();
        }
        $subscription = $countBuilder->whereIn('ps.id', $latestIds)->get()->getResultArray();
        $total = $subscription[0]['total'];

        // Build query with translations for both partner details and subscription details
        // Include user data for profile image display
        // Create a fresh builder instance for the main query to ensure clean state
        // Add both current and default language joins for proper fallback chain
        // Fallback chain: selected language → default language → main table
        $builder->join('partner_details pd', 'pd.partner_id = ps.partner_id', 'left')
            ->join('users u', 'u.id = ps.partner_id', 'left')
            ->join('translated_partner_details tpd_current', "tpd_current.partner_id = ps.partner_id AND tpd_current.language_code = '$currentLang'", 'left')
            ->join('translated_partner_details tpd_default', "tpd_default.partner_id = ps.partner_id AND tpd_default.language_code = '$defaultLang'", 'left')
            ->join('translated_subscription_details tsd_current', "tsd_current.subscription_id = ps.subscription_id AND tsd_current.language_code = '$currentLang'", 'left')
            ->join('translated_subscription_details tsd_default', "tsd_default.subscription_id = ps.subscription_id AND tsd_default.language_code = '$defaultLang'", 'left');
        $builder
            ->select('
                ps.*, 
                pd.company_name, 
                pd.banner,
                u.username as partner_name,
                u.phone,
                u.image,
                u.email,
                COALESCE(NULLIF(TRIM(tpd_current.company_name), ""), NULLIF(TRIM(tpd_default.company_name), ""), pd.company_name) as translated_company_name,
                COALESCE(NULLIF(TRIM(tpd_current.username), ""), NULLIF(TRIM(tpd_default.username), ""), u.username) as translated_username,
                COALESCE(NULLIF(TRIM(tsd_current.name), ""), NULLIF(TRIM(tsd_default.name), ""), ps.name) as translated_name,
                COALESCE(NULLIF(TRIM(tsd_current.description), ""), NULLIF(TRIM(tsd_default.description), ""), ps.description) as translated_description
            ');

        if (isset($_GET['subscription_filter']) && $_GET['subscription_filter'] != '') {
            $builder->where('ps.status', $_GET['subscription_filter']);
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (!empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $subscription_record = $builder->whereIn('ps.id', $latestIds)
            ->orderBy($sort, $order)
            ->groupBy('partner_id')
            ->limit($limit, $offset)
            ->get()->getResultArray();

        $allTranslations = [];
        if (!empty($subscription_record)) {
            $partnerIds = array_column($subscription_record, 'partner_id');
            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
            // Get all translations for all partners (not just current language)
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $operations = "";
        foreach ($subscription_record as $row) {
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

            $publish_badge = ($row['publish'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('yes', 'Yes') . "
                    </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('no', 'No') . "
                    </div>";
            $status_badge = ($row['status'] == 'active') ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('active', 'Active') . "
                        </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('deactive', 'Deactive') . "
                        </div>";
            $is_commision_badge = ($row['is_commision'] == "yes") ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('yes', 'Yes') . "
                            </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('no', 'No') . "
                            </div>";
            if (($row['is_payment'] == "1")) {
                $is_payment = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('success', 'Success') . "
                </div>";
            } elseif ($row['is_payment'] = "2") {
                $is_payment = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('failed', 'Failed') . "                </div>";
            }

            // Create user profile display similar to partners table
            $profile = "";
            $defaultImage = 'public/backend/assets/default.png';
            $disk = fetch_current_file_manager();

            // Handle user profile image with consistent fallback logic
            $imageSrc = get_file_url($disk, $row['image'], $defaultImage, 'profile');

            $profile = '<div class="o-media o-media--middle">
                        <a href="' . $imageSrc . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $partner_name . '">
                        </a>';

            $row_email = $row['email'] ?? '';
            $row_phone = $row['phone'] ?? '';
            if ($row_email != '' && $row_phone != "") {
                $contact_detail =
                    '<span>
                    ' . ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) ? 'wrteam.' . substr($row_email, 6) : $row_email) . '
                </span>';
            } elseif ($row_email != '') {
                $contact_detail = ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0)) ? 'wrteam.' . substr($row_email, 6) : $row_email;
            } else {
                $contact_detail = ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0)) ? 'XXX-' . substr($row_phone, 6) : $row_phone;
            }
            $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                <div class="provider_name_table">' . $partner_name . '</div>
                <div class="provider_email_table">' . $companyName . '</div>
                <div class="provider_email_table">' . $contact_detail . '(' . $row['phone'] . ')</div>
                </div>
                </div></a>';

            $operations = '<a href="' . base_url('/admin/partners/partner_subscription/' . $row['partner_id']) . '" class="btn btn-info ml-1 btn-sm"  title = "' . labels('view_partner', 'View partner') . '"> <i class="fa fa-eye" aria-hidden="true"></i> ' . labels('view', 'View') . ' </a> ';
            $tempRow['id'] = $row['id'];

            // Keep original fields unchanged
            $tempRow['name'] = $row['name'];
            $tempRow['description'] = $row['description'];

            // Add translated fields as separate keys with proper fallback
            // Fallback chain is already handled in SQL COALESCE, but ensure we use the translated value if available
            $tempRow['translated_name'] = !empty(trim($row['translated_name'] ?? '')) ? $row['translated_name'] : $row['name'];
            $tempRow['translated_description'] = !empty(trim($row['translated_description'] ?? '')) ? $row['translated_description'] : $row['description'];

            // Use translated username if available, otherwise fallback to partner_name (main table)
            // This ensures the username shown in the subscriber list image uses proper translation fallback
            $tempRow['provider_name'] = !empty(trim($row['translated_username'] ?? '')) ? $row['translated_username'] : $row['partner_name'];
            $tempRow['provider_phone'] = $row['phone'];
            $tempRow['duration'] = $row['duration'];
            $tempRow['price'] = $row['price'];
            $tempRow['discount_price'] = $row['discount_price'];
            $tempRow['publish'] = $row['publish'];
            $tempRow['publish_badge'] = $publish_badge;
            $tempRow['order_type'] = $row['order_type'];
            $tempRow['max_order_limit'] = $row['max_order_limit'];
            $tempRow['tax_type'] = $row['tax_type'];
            $tempRow['purchase_date'] = format_date($row['purchase_date'], 'd-m-Y');
            $tempRow['expiry_date'] = $row['duration'] == 'unlimited' ? '-' : format_date($row['expiry_date'], 'd-m-Y');
            $tempRow['tax_id'] = $row['tax_id'];
            $tempRow['is_commision'] = $row['is_commision'];
            $tempRow['commission_threshold'] = $row['commission_threshold'];
            $tempRow['commission_percentage'] = $row['commission_percentage'];
            $tempRow['status'] = $row['status'];
            $tempRow['status_badge'] = $status_badge;
            $tempRow['is_commision_badge'] = $is_commision_badge;
            $tempRow['operations'] = $operations;
            $price = calculate_partner_subscription_price($row['partner_id'], $row['subscription_id'], $row['id']);
            $tempRow['tax_value'] = $price[0]['tax_value'];
            $tempRow['price_with_tax'] = $price[0]['price_with_tax'];
            $tempRow['original_price_with_tax'] = $price[0]['original_price_with_tax'];
            $tempRow['tax_percentage'] = $price[0]['tax_percentage'] . "%";
            // $tempRow['banner_image'] is already set above with $profile

            // Keep original company name unchanged
            $tempRow['company_name'] = $row['company_name'];

            // Add translated company name as separate key with proper fallback
            // Fallback chain is already handled in SQL COALESCE, but ensure we use the translated value if available
            $tempRow['provider'] = $profile;

            $tempRow['is_payment'] = $is_payment;
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
     * Latest partner subscription with translated name/description resolved
     * at the DB layer (current → default → base snapshot).
     *
     * Pivoted derived table + single LEFT JOIN + COALESCE.
     */
    public function activeForPartnerWithTranslation(int $partnerId, string $currentLang, string $defaultLang): ?array
    {
        $db = $this->db;
        $cur = $db->escape($currentLang);
        $def = $db->escape($defaultLang);

        $pivot = "(
            SELECT subscription_id,
                   MAX(CASE WHEN language_code = $cur THEN NULLIF(name, '')        END) AS cur_name,
                   MAX(CASE WHEN language_code = $def THEN NULLIF(name, '')        END) AS def_name,
                   MAX(CASE WHEN language_code = $cur THEN NULLIF(description, '') END) AS cur_description,
                   MAX(CASE WHEN language_code = $def THEN NULLIF(description, '') END) AS def_description
            FROM translated_subscription_details
            WHERE language_code IN ($cur, $def)
            GROUP BY subscription_id
        ) t";

        return $db->table('partner_subscriptions ps')
            ->select('ps.*', false)
            ->select("COALESCE(t.cur_name, t.def_name, ps.name) AS name", false)
            ->select("COALESCE(t.cur_description, t.def_description, ps.description) AS description", false)
            ->join($pivot, 't.subscription_id = ps.subscription_id', 'left', false)
            ->where('ps.partner_id', $partnerId)
            ->orderBy('ps.id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();
    }
}
