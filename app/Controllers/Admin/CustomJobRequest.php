<?php

namespace App\Controllers\Admin;

use App\Services\utility\FileService;

class CustomJobRequest extends Admin
{
    // Category model instance for fetching translated category names
    protected $categoryModel;
    protected FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
        $this->categoryModel = new \App\Models\Category_model();
        $this->fileService = new FileService();
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('custom_job_requests', 'Custom Job Requests') . ' | ' . labels('admin_panel', 'Admin Panel'), 'custom_job_requests');
        return view('backend/admin/template', $this->data);
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        try {
            $db      = \Config\Database::connect();
            $builder = $db->table('custom_job_requests cj');
            $sortable_fields = ['id' => 'cj.id'];
            $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
            $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
            // Default to fully-qualified column to avoid ambiguity with joins
            $sort = 'cj.id';
            if (isset($_GET['sort']) && $_GET['sort'] !== '') {
                $sort = $_GET['sort'] === 'id' ? 'cj.id' : $_GET['sort'];
            }
            $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';
            $search = isset($_GET['search']) ? $_GET['search'] : '';

            // // Debug: Log received parameters
            // log_message('debug', 'Custom Job Request - Received GET params: ' . json_encode($_GET));
            // log_message('debug', 'Custom Job Request - Final sort: ' . $sort . ', order: ' . $order);

            $multipleWhere = [];
            if (!empty($search)) {
                $multipleWhere = [
                    'cj.id' => $search,
                    'c.name' => $search,
                    'u.username' => $search,
                ];
            }

            $count_builder = $db->table('custom_job_requests cj');
            $count_builder->select('COUNT(cj.id) as `total`');
            $count_builder->join('categories c', 'c.id = cj.category_id', 'left');
            $count_builder->join('users u', 'u.id = cj.user_id', 'left');
            if ($multipleWhere) {
                $count_builder->groupStart();
                foreach ($multipleWhere as $field => $value) {
                    $count_builder->orLike($field, $value);
                }
                $count_builder->groupEnd();
            }
            if ($where) {
                $count_builder->where($where);
            }
            $offer_count = $count_builder->get()->getRowArray();
            $total = $offer_count['total'];


            $builder->select('cj.*, c.name as category_name, u.username, u.image');
            $builder->join('categories c', 'c.id = cj.category_id', 'left');
            $builder->join('users u', 'u.id = cj.user_id', 'left');
            if ($multipleWhere) {
                $builder->groupStart();
                foreach ($multipleWhere as $field => $value) {
                    $builder->orLike($field, $value);
                }
                $builder->groupEnd();
            }
            if ($where) {
                $builder->where($where);
            }

            $offer_recored = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();

            // Collect all unique category IDs from the results
            // This allows us to fetch all translated category names at once for better performance
            $categoryIds = [];
            foreach ($offer_recored as $row) {
                if (!empty($row['category_id'])) {
                    $categoryIds[] = (int)$row['category_id'];
                }
            }
            $categoryIds = array_unique($categoryIds);

            // Get translated category names for all categories at once
            // This method handles fallback: current language -> default language -> main table
            $translatedCategoryNames = [];
            if (!empty($categoryIds)) {
                $translatedCategoryNames = $this->categoryModel->getTranslatedCategoryNames($categoryIds);
            }

            // Bulk-resolve cancellation reasons with translation fallback:
            // requested language → default language → base reasons.reason.
            $cancelReasonIds = [];
            foreach ($offer_recored as $row) {
                if (!empty($row['cancel_reason_id'])) {
                    $cancelReasonIds[] = (int) $row['cancel_reason_id'];
                }
            }
            $cancelReasonIds = array_values(array_unique($cancelReasonIds));
            $resolvedCancelReasons = [];
            if (!empty($cancelReasonIds)) {
                $baseReasons = $db->table('reasons')
                    ->select('id, reason')
                    ->whereIn('id', $cancelReasonIds)
                    ->get()
                    ->getResultArray();
                $baseReasonsById = [];
                foreach ($baseReasons as $br) {
                    $baseReasonsById[(int) $br['id']] = $br['reason'];
                }

                $translationsByReasonId = [];
                if ($db->tableExists('translated_reasons')) {
                    $translations = $db->table('translated_reasons tr')
                        ->select('tr.reason_id, tr.reason as translated_reason, l.code as language_code')
                        ->join('languages l', 'l.id = tr.language_id')
                        ->whereIn('tr.reason_id', $cancelReasonIds)
                        ->get()
                        ->getResultArray();
                    foreach ($translations as $t) {
                        $translationsByReasonId[(int) $t['reason_id']][$t['language_code']] = $t['translated_reason'];
                    }
                }

                $currentLang = get_current_language();
                $defaultLang = get_default_language();
                foreach ($cancelReasonIds as $rid) {
                    $resolvedCancelReasons[$rid] = $translationsByReasonId[$rid][$currentLang]
                        ?? $translationsByReasonId[$rid][$defaultLang]
                        ?? ($baseReasonsById[$rid] ?? null);
                }
            }

            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();
            foreach ($offer_recored as $row) {
                $tempRow['id'] = $row['id'];
                $tempRow['user_id'] = $row['user_id'];
                $tempRow['category_id'] = $row['category_id'];
                $tempRow['service_title'] = $row['service_title'];
                $tempRow['service_short_description'] = $row['service_short_description'];
                $tempRow['truncateWords_service_short_description'] =  truncateWords($row['service_short_description'], $limit = 5);
                $tempRow['min_price'] = $row['min_price'];
                $tempRow['max_price'] = $row['max_price'];
                $tempRow['requested_start_date'] = $row['requested_start_date'];
                $tempRow['requested_start_time'] = $row['requested_start_time'];
                $tempRow['requested_end_date'] = $row['requested_end_date'];
                $tempRow['requested_end_time'] = $row['requested_end_time'];
                $tempRow['status'] = labels($row['status'], ucfirst($row['status']));
                $cancelReasonId = !empty($row['cancel_reason_id']) ? (int) $row['cancel_reason_id'] : null;
                $tempRow['cancel_reason_id'] = $cancelReasonId;
                $tempRow['cancel_reason'] = $cancelReasonId !== null
                    ? ($resolvedCancelReasons[$cancelReasonId] ?? '-')
                    : '-';
                $tempRow['cancel_additional_info'] = $row['cancel_additional_info'] ?? null;
                $tempRow['created_at'] = $row['created_at'];
                // Add human-readable time difference for created_at
                $tempRow['time_ago'] = $this->getHumanTimeDiff($row['created_at']);
                $tempRow['username'] = $row['username'];

                // Get translated category name with fallback to default language
                // Priority: current language translation -> default language translation -> main table name
                if (!empty($row['category_id']) && isset($translatedCategoryNames[$row['category_id']])) {
                    $tempRow['category_name'] = $translatedCategoryNames[$row['category_id']];
                } else {
                    // Fallback to original category_name from query if translation not found
                    $tempRow['category_name'] = $row['category_name'] ?? '';
                }

                $totalBuilder = $db->table('partner_bids pb')
                    ->select('COUNT(pb.id) as total_bidders')
                    ->where('pb.custom_job_request_id', $row['id'])
                    ->get()
                    ->getRowArray();
                $tempRow['total_bids'] = $totalBuilder['total_bidders'];

                // If job was cancelled and there were no bids, show disabled greyed-out button (no link)
                // Otherwise show active "View Details" link to bidders page
                $statusLower = strtolower((string)($row['status'] ?? ''));
                $isCancelledNoBids = (($statusLower === 'cancelled' || $statusLower === 'canceled') && (int)$tempRow['total_bids'] === 0);
                if ($isCancelledNoBids) {
                    $operations = '<span class="btn btn-secondary btn-sm disabled text-muted" aria-disabled="true" title="' . labels('no_bids_cancelled', 'No bids; job was cancelled') . '">' . labels('view_details', 'View Details') . '</span>';
                } else {
                    $operations = '<a href="' . base_url('/admin/custom-job/bidders/' . $row['id']) . '" class="btn btn-secondary btn-sm pay-out">' . labels('view_details', 'View Details') . '</a>';
                }

                $tempRow['operation'] = $operations;

                $rows[] = $tempRow;
            }
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Faqs.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function bidders_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        $uri = service('uri');
        $segments = $uri->getSegments();
        $custom_job_request_id = $segments[3];
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('partner_bids pb');
            $sortable_fields = ['id' => 'pb.id'];

            // Get current language and default language for translation fallback
            // Priority: current language -> default language -> base table
            $currentLang = get_current_language();
            $defaultLang = get_default_language();

            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            // Default to fully-qualified column
            $sort = 'pb.id';
            if (isset($_GET['sort']) && $_GET['sort'] !== '') {
                $sort = $_GET['sort'] === 'id' ? 'pb.id' : $_GET['sort'];
            }
            $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'ASC';
            $search = isset($_GET['search']) ? $_GET['search'] : '';

            $multipleWhere = [];
            if (!empty($search)) {
                // Search in base table company_name and translated company names
                $multipleWhere = [
                    'pb.id' => $search,
                    'pd.company_name' => $search,
                    'tpd_current.company_name' => $search,
                    'tpd_default.company_name' => $search,
                ];
            }

            // Count query with translation joins for accurate search results
            $count_builder = $db->table('partner_bids pb');
            $count_builder->select('COUNT(pb.id) as total');
            $count_builder->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left');
            // Join translated partner details for current language
            $count_builder->join('translated_partner_details tpd_current', "tpd_current.partner_id = pb.partner_id AND tpd_current.language_code = '$currentLang'", 'left');
            // Join translated partner details for default language
            $count_builder->join('translated_partner_details tpd_default', "tpd_default.partner_id = pb.partner_id AND tpd_default.language_code = '$defaultLang'", 'left');
            if (!empty($multipleWhere)) {
                $count_builder->groupStart();
                foreach ($multipleWhere as $field => $value) {
                    $count_builder->orLike($field, $value);
                }
                $count_builder->groupEnd();
            }
            if (!empty($where)) {
                $count_builder->where($where);
            }
            $count_builder->where('pb.custom_job_request_id', $custom_job_request_id);
            $offer_count = $count_builder->get()->getRowArray();
            $total = $offer_count['total'] ?? 0;

            // Main query with translation fallback using COALESCE
            // Priority: current language translation -> default language translation -> base table company_name
            $builder->select('pb.*, 
                pd.company_name as base_company_name,
                pd.id as partner_detail_id, 
                pd.banner as provider_image,
                COALESCE(
                    NULLIF(TRIM(tpd_current.company_name), ""), 
                    NULLIF(TRIM(tpd_default.company_name), ""), 
                    pd.company_name
                ) as provider_name,
                u.username as partner_name,
                u.email as partner_email,
                u.phone as partner_phone,
                u.image as partner_image')
                ->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left')
                ->join('users u', 'u.id = pb.partner_id', 'left')
                // Join translated partner details for current language
                ->join('translated_partner_details tpd_current', "tpd_current.partner_id = pb.partner_id AND tpd_current.language_code = '$currentLang'", 'left')
                // Join translated partner details for default language
                ->join('translated_partner_details tpd_default', "tpd_default.partner_id = pb.partner_id AND tpd_default.language_code = '$defaultLang'", 'left');
            if (!empty($multipleWhere)) {
                $builder->groupStart();
                foreach ($multipleWhere as $field => $value) {
                    $builder->orLike($field, $value);
                }
                $builder->groupEnd();
            }
            if (!empty($where)) {
                $builder->where($where);
            }
            $builder->where('pb.custom_job_request_id', $custom_job_request_id);

            $builder->orderBy($sort, $order)
                ->limit($limit, $offset);

            $offer_records = $builder->get()->getResultArray();

            $bulkData = [];
            $bulkData['total'] = $total;
            $rows = [];

            $allTranslations = [];
            if (!empty($offer_records)) {
                $partnerIds = array_column($offer_records, 'partner_id');
                $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
                // Get all translations for all partners (not just current language)
                $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
            }

            foreach ($offer_records as $row) {
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

                $imageSrc = $this->fileService->url($row['partner_image'], 'profile');

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

                $tempRow = [
                    'id' => $row['id'],
                    'partner_id' => $row['partner_id'],
                    'counter_price' => $row['counter_price'],
                    // 'truncateWords_note' => $row['note'],
                    'note' => $row['note'],
                    'duration' => $row['duration'],
                    'created_at' => $row['created_at'],
                    // Add human-readable time difference for created_at
                    'time_ago' => $this->getHumanTimeDiff($row['created_at']),
                    'status' => $row['status'],
                    'provider_name' => $profile,
                    'provider_image' => $row['provider_image'],
                ];
                $rows[] = $tempRow;
            }

            $bulkData['rows'] = $rows;

            return json_encode($bulkData);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Faqs.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function bidders_list_page()
    {

        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        $uri = service('uri');
        $segments = $uri->getSegments();
        $custom_job_request_id = $segments[3];

        $custom_job = fetch_details('custom_job_requests', ['id' => $custom_job_request_id]);
        $this->data['custom_job'] = $custom_job[0];

        $rawFiles = !empty($custom_job[0]['files']) ? json_decode($custom_job[0]['files'], true) : [];
        $processedFiles = [];
        if (is_array($rawFiles)) {
            foreach ($rawFiles as $file) {
                $path = is_array($file) && isset($file['data']) ? $file['data'] : (is_string($file) ? $file : null);
                if ($path === null) {
                    continue;
                }
                $url  = $this->fileService->url($path, 'custom_job_requests');
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'])) {
                    $type = 'image';
                } elseif (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp', 'flv'])) {
                    $type = 'video';
                } else {
                    $type = 'document';
                }
                $processedFiles[] = ['url' => $url, 'type' => $type, 'name' => basename($path)];
            }
        }
        $this->data['custom_job_files'] = $processedFiles;

        setPageInfo($this->data, labels('custom_job_requests_bids', 'Custom Job Requests Bids') . ' | ' . labels('admin_panel', 'Admin Panel'), 'partners_bids');
        return view('backend/admin/template', $this->data);
    }

    /**
     * Calculate human-readable time difference from UTC database timestamp.
     * 
     * This method properly handles:
     * - Database timestamps stored in UTC
     * - Conversion to local timezone
     * - Accurate time difference calculation
     * - Translatable labels for better UX
     * 
     * @param string|null $date UTC timestamp from database
     * @return string Human-readable time difference (e.g., "2 hours ago", "just now")
     */
    private function getHumanTimeDiff(?string $date): string
    {
        if (!$date) return '';

        try {
            // Parse date as UTC (database stores timestamps in UTC)
            $utcDate = new \DateTime($date, new \DateTimeZone('UTC'));

            // Convert to local timezone for accurate difference calculation
            $localTz = new \DateTimeZone(date_default_timezone_get());
            $utcDate->setTimezone($localTz);

            // Get current time in local timezone
            $now = new \DateTime('now', $localTz);

            // Calculate difference in seconds
            $diff = $now->getTimestamp() - $utcDate->getTimestamp();

            // Handle edge cases
            if ($diff < 0) return labels('just_now', 'just now');
            if ($diff < 10) return labels('just_now', 'just now');
            if ($diff < 60) return labels('a_few_seconds_ago', 'a few seconds ago');
            if ($diff < 120) return labels('a_minute_ago', 'a minute ago');
            if ($diff < 3600) return $this->formatTimeAgo(floor($diff / 60), labels('minute', 'minute'));
            if ($diff < 7200) return labels('an_hour_ago', 'an hour ago');
            if ($diff < 86400) return $this->formatTimeAgo(floor($diff / 3600), labels('hour', 'hour'));
            if ($diff < 172800) return labels('yesterday', 'yesterday');
            if ($diff < 604800) return $this->formatTimeAgo(floor($diff / 86400), labels('day', 'day'));
            if ($diff < 2592000) return $this->formatTimeAgo(floor($diff / 604800), labels('week', 'week'));
            if ($diff < 31536000) return $this->formatTimeAgo(floor($diff / 2592000), labels('month', 'month'));

            return $this->formatTimeAgo(floor($diff / 31536000), labels('year', 'year'));
        } catch (\Exception $e) {
            // Log error and return empty string on failure
            log_message('error', 'getHumanTimeDiff error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Format time value with proper singular/plural handling.
     * 
     * @param int $value Time value (e.g., 2, 5, 1)
     * @param string $unit Time unit label (e.g., "hour", "minute")
     * @return string Formatted string (e.g., "1 hour ago", "5 hours ago")
     */
    private function formatTimeAgo(int $value, string $unit): string
    {
        return $value === 1
            ? "1 $unit ago"
            : "$value {$unit}s ago";
    }
}
