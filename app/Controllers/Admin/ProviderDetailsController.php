<?php

namespace App\Controllers\Admin;

use App\Models\Orders_model;
use App\Models\Partners_model;
use App\Models\Promo_code_model;
use App\Models\ProviderLeaves_model;
use App\Models\ProviderShifts_model;
use App\Models\Seo_model;
use App\Models\Service_model;
use App\Models\Service_ratings_model;

class ProviderDetailsController extends Admin
{
    public $partner, $providerShifts, $providerLeaves, $seoModel, $db, $creator_id, $superadmin;

    public function __construct()
    {
        parent::__construct();
        $this->partner = new Partners_model();
        $this->providerShifts = new ProviderShifts_model();
        $this->providerLeaves = new ProviderLeaves_model();
        $this->seoModel = new Seo_model();
        $this->db = \Config\Database::connect();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }

    public function provider_leaves()
    {
        setPageInfo($this->data, labels('provider_leaves', 'Provider Leaves') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'provider_leaves');
        return view('backend/admin/template', $this->data);
    }

    public function provider_leaves_list()
    {
        try {
            $limit = (isset($_GET['limit']) && $_GET['limit'] !== '') ? (int) $_GET['limit'] : 20;
            $offset = (isset($_GET['offset']) && $_GET['offset'] !== '') ? (int) $_GET['offset'] : 0;
            $sort = $_GET['sort'] ?? 'pl.leave_date';
            $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
            $search = $_GET['search'] ?? '';

            $sortable = [
                'leave_date' => 'pl.leave_date',
                'day' => 'pl.day',
                'partner_id' => 'pl.partner_id',
            ];
            $sortColumn = $sortable[$sort] ?? 'pl.leave_date';

            // Default ordering: today first, then upcoming (ascending), then past (most recent past first).
            // The view flips user_sorted=1 once the user clicks any column header so the click
            // takes precedence over the bucket order.
            $userSorted = (string) ($_GET['user_sorted'] ?? '0') === '1';
            $useBucketOrder = !$userSorted;

            $response = ['total' => 0, 'totalNotFiltered' => 0, 'rows' => []];

            if (!$this->providerLeaves->tableExists()) {
                print_r(json_encode($response));
                return;
            }

            $db = \Config\Database::connect();

            $totalNotFiltered = (int) $db->table('provider_leaves')
                ->select('1', false)
                ->groupBy('partner_id, leave_date')
                ->countAllResults();

            // Date range filter (inclusive). Accepts single date (from == to) or range.
            $isValidDate = static function (string $value): bool {
                if ($value === '')
                    return false;
                $ts = strtotime($value);
                return $ts !== false && date('Y-m-d', $ts) === $value;
            };
            $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
            $dateTo = trim((string) ($_GET['date_to'] ?? ''));
            if (!$isValidDate($dateFrom))
                $dateFrom = '';
            if (!$isValidDate($dateTo))
                $dateTo = '';
            if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            $currentLang = $db->escape(get_current_language());
            $defaultLang = $db->escape(get_default_language());

            // Pivoted translation derived table: ONE row per partner with cur/def values
            // for each translatable field. Lets us LEFT JOIN once and resolve the
            // fallback (current → default → base) entirely at the DB layer via COALESCE.
            $tpdPivot = "(
                SELECT partner_id,
                       MAX(CASE WHEN language_code = $currentLang THEN NULLIF(username, '')     END) AS cur_username,
                       MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(username, '')     END) AS def_username,
                       MAX(CASE WHEN language_code = $currentLang THEN NULLIF(company_name, '') END) AS cur_company,
                       MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(company_name, '') END) AS def_company
                FROM translated_partner_details
                WHERE language_code IN ($currentLang, $defaultLang)
                GROUP BY partner_id
            ) tpd";

            $partnerIdFilter = (int) ($_GET['partner_id'] ?? 0);

            $searchFilter = function ($builder) use ($db, $search, $dateFrom, $dateTo, $partnerIdFilter) {
                if ($partnerIdFilter > 0)
                    $builder->where('pl.partner_id', $partnerIdFilter);
                if ($dateFrom !== '')
                    $builder->where('pl.leave_date >=', $dateFrom);
                if ($dateTo !== '')
                    $builder->where('pl.leave_date <=', $dateTo);

                if ($search === '')
                    return;
                $escapedSearch = $db->escapeLikeString($search);
                $builder->groupStart()
                    ->like('u.username', $escapedSearch)
                    ->orLike('u.email', $escapedSearch)
                    ->orLike('u.phone', $escapedSearch)
                    ->orLike('pd.company_name', $escapedSearch)
                    ->orLike('tpd.cur_username', $escapedSearch)
                    ->orLike('tpd.def_username', $escapedSearch)
                    ->orLike('tpd.cur_company', $escapedSearch)
                    ->orLike('tpd.def_company', $escapedSearch)
                    ->orLike('pl.leave_date', $escapedSearch)
                    ->orLike('pl.day', $escapedSearch)
                    ->groupEnd();
            };

            $countBuilder = $db->table('provider_leaves pl')
                ->select('1', false)
                ->join('users u', 'u.id = pl.partner_id', 'left')
                ->join('partner_details pd', 'pd.partner_id = pl.partner_id', 'left')
                ->join($tpdPivot, 'tpd.partner_id = pl.partner_id', 'left', false)
                ->groupBy('pl.partner_id, pl.leave_date');
            $searchFilter($countBuilder);
            $total = (int) $countBuilder->countAllResults();

            $dataBuilder = $db->table('provider_leaves pl')
                ->select("pl.partner_id, pl.leave_date, pl.day,
                          COUNT(*) AS shifts_count,
                          GROUP_CONCAT(CONCAT('Shift ', pl.shift_number, ' (', TIME_FORMAT(pl.opening_time, '%h:%i %p'), ' - ', TIME_FORMAT(pl.closing_time, '%h:%i %p'), ')') ORDER BY pl.shift_number SEPARATOR ', ') AS shifts,
                          COALESCE(tpd.cur_username, tpd.def_username, u.username) AS partner_name,
                          u.email, u.phone, u.country_code, u.image,
                          COALESCE(tpd.cur_company, tpd.def_company, pd.company_name) AS company_name", false)
                ->join('users u', 'u.id = pl.partner_id', 'left')
                ->join('partner_details pd', 'pd.partner_id = pl.partner_id', 'left')
                ->join($tpdPivot, 'tpd.partner_id = pl.partner_id', 'left', false)
                ->groupBy('pl.partner_id, pl.leave_date, pl.day, u.username, u.email, u.phone, u.country_code, u.image, pd.company_name, tpd.cur_username, tpd.def_username, tpd.cur_company, tpd.def_company');
            $searchFilter($dataBuilder);
            if ($useBucketOrder) {
                $dataBuilder->orderBy('CASE WHEN pl.leave_date = CURDATE() THEN 0 WHEN pl.leave_date > CURDATE() THEN 1 ELSE 2 END', 'ASC', false)
                    ->orderBy('ABS(DATEDIFF(pl.leave_date, CURDATE()))', 'ASC', false);
            } else {
                $dataBuilder->orderBy($sortColumn, $order);
            }
            $rows = $dataBuilder->limit($limit, $offset)
                ->get()
                ->getResultArray();

            $sessionEmail = $_SESSION['email'] ?? '';
            $isMasked = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $sessionEmail !== 'superadmin@gmail.com';
            $maskEmail = static function ($value) {
                return strlen($value) > 6 ? 'wrteam.' . substr($value, 6) : 'wrteam.***';
            };
            $maskPhone = static function ($value) {
                return strlen($value) > 6 ? 'XXXXX' . substr($value, 6) : 'XXXXX';
            };
            $disk = fetch_current_file_manager();

            $formatted = [];
            foreach ($rows as $row) {
                $partnerName = trim((string) ($row['partner_name'] ?? ''));
                $companyName = (string) ($row['company_name'] ?? '');
                $displayName = $partnerName !== '' ? $partnerName : $companyName;

                $email = (string) ($row['email'] ?? '');
                $phone = (string) ($row['phone'] ?? '');
                $countryCode = trim((string) ($row['country_code'] ?? ''));
                $displayPhone = ($countryCode !== '' && $phone !== '')
                    ? (strpos($countryCode, '+') === 0 ? $countryCode : '+' . $countryCode) . $phone
                    : $phone;

                $partnerEmail = ($isMasked && $email !== '') ? $maskEmail($email) : $email;
                $partnerMobile = ($isMasked && $displayPhone !== '') ? $maskPhone($displayPhone) : $displayPhone;

                $imageSrc = get_file_url($disk, $row['image'] ?? '', 'public/backend/assets/default.png', 'profile');

                $profile = '
                <div class="o-media o-media--middle">
                    <a href="' . htmlspecialchars($imageSrc, ENT_QUOTES) . '" data-lightbox="image-1">
                        <img class="o-media__img images_in_card" src="' . htmlspecialchars($imageSrc, ENT_QUOTES) . '" alt="' . htmlspecialchars($displayName, ENT_QUOTES) . '">
                    </a>
                    <a href="' . base_url('/admin/partners/general_outlook/' . (int) $row['partner_id']) . '">
                        <div class="o-media__body">
                            <div class="provider_name_table">' . htmlspecialchars($displayName, ENT_QUOTES) . '</div>
                            <div class="provider_email_table">' . htmlspecialchars($companyName, ENT_QUOTES) . '</div>
                            <div class="provider_email_table">
                                ' . htmlspecialchars($partnerEmail, ENT_QUOTES) . ' (' . htmlspecialchars($partnerMobile, ENT_QUOTES) . ')
                            </div>
                        </div>
                    </a>
                </div>';

                $formatted[] = [
                    'partner_id' => (int) $row['partner_id'],
                    'partner_profile' => $profile,
                    'leave_date' => $row['leave_date'],
                    'day' => ucfirst((string) $row['day']),
                    'shifts_count' => (int) $row['shifts_count'],
                    'shifts' => $row['shifts'],
                ];
            }

            $response['total'] = $total;
            $response['totalNotFiltered'] = $totalNotFiltered;
            $response['rows'] = $formatted;

            print_r(json_encode($response));
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . ' --> app/Controllers/admin/Partners.php - provider_leaves_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function provider_leaves_calendar()
    {
        try {
            $response = [];

            if (!$this->providerLeaves->tableExists()) {
                print_r(json_encode($response));
                return;
            }

            $isValidDate = static function (string $value): bool {
                if ($value === '')
                    return false;
                $ts = strtotime($value);
                return $ts !== false && date('Y-m-d', $ts) === $value;
            };
            $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
            $dateTo = trim((string) ($_GET['date_to'] ?? ''));
            if (!$isValidDate($dateFrom))
                $dateFrom = '';
            if (!$isValidDate($dateTo))
                $dateTo = '';
            if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }
            $search = trim((string) ($_GET['search'] ?? ''));
            $partnerIdFilter = (int) ($_GET['partner_id'] ?? 0);

            $db = \Config\Database::connect();

            $currentLang = $db->escape(get_current_language());
            $defaultLang = $db->escape(get_default_language());

            $tpdPivot = "(
                SELECT partner_id,
                       MAX(CASE WHEN language_code = $currentLang THEN NULLIF(username, '')     END) AS cur_username,
                       MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(username, '')     END) AS def_username,
                       MAX(CASE WHEN language_code = $currentLang THEN NULLIF(company_name, '') END) AS cur_company,
                       MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(company_name, '') END) AS def_company
                FROM translated_partner_details
                WHERE language_code IN ($currentLang, $defaultLang)
                GROUP BY partner_id
            ) tpd";

            $builder = $db->table('provider_leaves pl')
                ->select("pl.partner_id, pl.leave_date, pl.day, pl.shift_number,
                          pl.opening_time, pl.closing_time,
                          COALESCE(tpd.cur_username, tpd.def_username, u.username) AS partner_name,
                          u.email, u.phone, u.country_code,
                          COALESCE(tpd.cur_company, tpd.def_company, pd.company_name) AS company_name", false)
                ->join('users u', 'u.id = pl.partner_id', 'left')
                ->join('partner_details pd', 'pd.partner_id = pl.partner_id', 'left')
                ->join($tpdPivot, 'tpd.partner_id = pl.partner_id', 'left', false);

            if ($partnerIdFilter > 0)
                $builder->where('pl.partner_id', $partnerIdFilter);
            if ($dateFrom !== '')
                $builder->where('pl.leave_date >=', $dateFrom);
            if ($dateTo !== '')
                $builder->where('pl.leave_date <=', $dateTo);

            if ($search !== '') {
                $escapedSearch = $db->escapeLikeString($search);
                $builder->groupStart()
                    ->like('u.username', $escapedSearch)
                    ->orLike('u.email', $escapedSearch)
                    ->orLike('u.phone', $escapedSearch)
                    ->orLike('pd.company_name', $escapedSearch)
                    ->orLike('tpd.cur_username', $escapedSearch)
                    ->orLike('tpd.def_username', $escapedSearch)
                    ->orLike('tpd.cur_company', $escapedSearch)
                    ->orLike('tpd.def_company', $escapedSearch)
                    ->orLike('pl.leave_date', $escapedSearch)
                    ->orLike('pl.day', $escapedSearch)
                    ->groupEnd();
            }

            $rows = $builder->orderBy('pl.leave_date', 'ASC')
                ->orderBy('pl.partner_id', 'ASC')
                ->orderBy('pl.shift_number', 'ASC')
                ->get()
                ->getResultArray();

            // Group raw shift rows into one event per (partner, leave_date).
            // Each event carries the full structured shifts list for the modal.
            $sessionEmail = $_SESSION['email'] ?? '';
            $isMasked = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $sessionEmail !== 'superadmin@gmail.com';
            $maskEmail = static function ($value) {
                return strlen($value) > 6 ? 'wrteam.' . substr($value, 6) : 'wrteam.***';
            };
            $maskPhone = static function ($value) {
                return strlen($value) > 6 ? 'XXXXX' . substr($value, 6) : 'XXXXX';
            };

            $groups = [];
            foreach ($rows as $row) {
                $key = $row['partner_id'] . '|' . $row['leave_date'];
                if (!isset($groups[$key])) {
                    $partnerName = trim((string) ($row['partner_name'] ?? ''));
                    $companyName = (string) ($row['company_name'] ?? '');
                    $displayName = $partnerName !== '' ? $partnerName : $companyName;

                    $email = (string) ($row['email'] ?? '');
                    $phone = (string) ($row['phone'] ?? '');
                    $countryCode = trim((string) ($row['country_code'] ?? ''));
                    $displayPhone = ($countryCode !== '' && $phone !== '')
                        ? (strpos($countryCode, '+') === 0 ? $countryCode : '+' . $countryCode) . $phone
                        : $phone;
                    $partnerEmail = ($isMasked && $email !== '') ? $maskEmail($email) : $email;
                    $partnerMobile = ($isMasked && $displayPhone !== '') ? $maskPhone($displayPhone) : $displayPhone;

                    $groups[$key] = [
                        'partner_id' => (int) $row['partner_id'],
                        'leave_date' => $row['leave_date'],
                        'day' => ucfirst((string) $row['day']),
                        'partner_name' => $displayName,
                        'company_name' => $companyName,
                        'email' => $partnerEmail,
                        'phone' => $partnerMobile,
                        'shifts' => [],
                    ];
                }
                $groups[$key]['shifts'][] = [
                    'number' => (int) $row['shift_number'],
                    'start' => date('h:i A', strtotime((string) $row['opening_time'])),
                    'end' => date('h:i A', strtotime((string) $row['closing_time'])),
                ];
            }

            foreach ($groups as $g) {
                $shiftsCount = count($g['shifts']);
                $response[] = [
                    'title' => $g['partner_name'] . ' — ' . $shiftsCount . ' ' . ($shiftsCount === 1
                        ? labels('shift', 'Shift')
                        : labels('shifts', 'Shifts')),
                    'start' => $g['leave_date'],
                    'allDay' => true,
                    'extendedProps' => [
                        'partner_id' => $g['partner_id'],
                        'partner_name' => $g['partner_name'],
                        'company_name' => $g['company_name'],
                        'email' => $g['email'],
                        'phone' => $g['phone'],
                        'leave_date' => $g['leave_date'],
                        'day' => $g['day'],
                        'shifts_count' => $shiftsCount,
                        'shifts' => $g['shifts'],
                    ],
                ];
            }

            // Wrap in object so csrf_response after-filter doesn't mangle the
            // indexed array into {0:..., 1:..., csrfName, csrfHash}.
            return $this->response->setJSON(['events' => $response]);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/Admin/ProviderDetailsController.php - provider_leaves_calendar(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return $this->response->setJSON(['events' => []]);
        }
    }

    public function partner_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            print_r(json_encode($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function timing_details()
    {
        try {
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $db = \Config\Database::connect();

            $shiftsByDay = $this->providerShifts->tableExists()
                ? $this->providerShifts->getByPartnerGroupedByDay((int) $partner_id)
                : [];

            $orderedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $rows = [];
            foreach ($orderedDays as $day) {
                $shifts = $shiftsByDay[$day] ?? [];
                if (empty($shifts)) {
                    continue;
                }

                $isOpen = (int) ($shifts[0]['is_open'] ?? 0) === 1;

                $shiftChips = [];
                foreach ($shifts as $i => $shift) {
                    $start = substr((string) $shift['opening_time'], 0, 5);
                    $end = substr((string) $shift['closing_time'], 0, 5);
                    $shiftLabel = labels('shift', 'Shift') . ' ' . ($i + 1);
                    $shiftChips[] = '<span class="badge badge-light border mr-1 mb-1 p-2" style="font-size:0.8rem;">'
                        . '<i class="far fa-clock text-primary mr-1"></i>'
                        . '<span class="text-muted mr-1">' . htmlspecialchars($shiftLabel, ENT_QUOTES, 'UTF-8') . ':</span>'
                        . '<span class="text-dark font-weight-medium">' . $start . ' — ' . $end . '</span>'
                        . '</span>';
                }
                $shiftsHtml = '<div class="d-flex flex-wrap">' . implode('', $shiftChips) . '</div>';

                $statusBadge = $isOpen
                    ? "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100'>" . labels('open', 'Open') . "</div>"
                    : "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100'>" . labels('closed', 'Closed') . "</div>";

                $rows[] = [
                    'partner_id' => $partner_id,
                    'day' => labels($day, ucfirst($day)),
                    'shifts' => $shiftsHtml,
                    'is_open_new' => $statusBadge,
                ];
            }

            $bulkData['total'] = count($rows);
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - timing_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function service_details()
    {
        try {
            $uri = service('uri');
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $service_model = new Service_model();
            $where['s.user_id'] = $uri->getSegments()[3];
            $services = $service_model->list(false, $search, $limit, $offset, $sort, $order, $where);
            return ($services);
        } catch (\Exception $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - service_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function view_ratings()
    {
        try {
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $ratings_model = new Service_ratings_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            return json_encode($ratings_model->ratings_list(false, $search, $limit, $offset, $sort, $order, ['s.user_id' => $partner_id]));
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - view_ratings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function delete_rating()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }
        try {
            $id = $this->request->getPost('id');
            $data = $this->db->table('services_ratings')->delete(['id' => $id]);
            if ($data) {
                return JsonSuccess(labels(RATING_DELETED_SUCCESSFULLY, "Rating deleted successfully"));
            } else {
                return JsonError(labels(UNSUCCESSFUL_IN_DELETION_OF_RATING, "Unsuccessful in deletion of rating"));
            }
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - delete_rating()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function provider_details()
    {
        helper('function');
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('provider_details', 'Provider Details') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'provider_details');
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }
    public function general_outlook()
    {
        try {
            $uri = service('uri');
            helper('function');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $language = get_current_language();
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id], 'pd.id', [], [], null, $language)));

            $db = \Config\Database::connect();
            $id = $uri->getSegments()[3];
            // Total bookings count — must match the booking-list total (Orders_model::list()).
            // Same joins + where so the stat card and the list badge never diverge.
            $builder = $db->table('orders o');
            $order_count = $builder->select('count(DISTINCT(o.id)) as total')
                ->join('order_services os', 'os.order_id=o.id')
                ->join('users u', 'u.id=o.user_id')
                ->join('users up', 'up.id=o.partner_id')
                ->join('partner_details pd', 'o.partner_id = pd.partner_id')
                ->where(['o.partner_id' => $id])
                ->get()->getResultArray();
            $total_services = $db->table('services s')->select('count(s.id) as `total`')->where(['user_id' => $id])->get()->getResultArray()[0]['total'];
            $total_balance = unsettled_commision($id);
            $total_promocodes = $db->table('promo_codes p')->select('count(p.id) as `total`')->where(['partner_id' => $id])->get()->getResultArray()[0]['total'];
            $provider_total_earning_chart = provider_total_earning_chart($id);
            $provider_already_withdraw_chart = provider_already_withdraw_chart($id);
            $provider_pending_withdraw_chart = provider_pending_withdraw_chart($id);
            $provider_withdraw_chart = provider_withdraw_chart($id);
            $where['partner_id'] = $uri->getSegments()[3];
            $db = \Config\Database::connect();
            $id = $partner_id;
            $promo_codes = $db->table('promo_codes')->where(['partner_id' => $id])->where('start_date >', date('Y-m-d'))->orderBy('id', 'DESC')->limit(5, 0)->get()->getResultArray();
            $promocode_dates = array();
            $tempRow = array();
            $promocode_dates = array();
            foreach ($promo_codes as $promo_code) {
                $date = explode('-', $promo_code['start_date']);
                $newDate = $date[1] . '-' . $date[2];
                $newDate = explode(' ', $newDate);
                $newDate = $newDate[0];
                $tempRow['start_date'] = $newDate;
                $tempRow['promo_code'] = $promo_code['promo_code'];
                $tempRow['end_date'] = $promo_code['end_date'];
                $promocode_dates[] = $tempRow;
            }
            $ratings = new Service_ratings_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 0;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $partner_id_for_rating = $uri->getSegments()[3];
            $where_for_rating = ["(s.user_id = {$partner_id_for_rating}) OR (pb.partner_id = {$partner_id_for_rating} AND sr.custom_job_request_id IS NOT NULL)"];
            $data = $ratings->ratings_list(true, $search, $limit, $offset, $sort, $order, $where_for_rating);
            $total_review = $data['total'];
            $total_ratings = $db->table('partner_details p')->select('count(p.ratings) as `total`')->where(['id' => $id])->get()->getResultArray()[0]['total'];
            $already_withdraw = $db->table('payment_request p')->select('sum(p.amount) as total')->where(['user_id' => $id, "status" => 1])->get()->getResultArray()[0]['total'];
            $pending_withdraw = $db->table('payment_request p')->select('sum(p.amount) as total')->where(['user_id' => $id, "status" => 0])->get()->getResultArray()[0]['total'];
            $total_withdraw_request = $db->table('payment_request p')->select('count(p.id) as `total`')->where(['user_id' => $id])->get()->getResultArray()[0]['total'];
            $number_or_ratings = $db->table('partner_details p')->select('count(p.number_of_ratings) as `total`')->where(['id' => $id])->get()->getResultArray()[0]['total'];
            $income = $db->table('orders o')->select('count(o.id) as `total`')->where(['user_id' => $id])->where("created_at >= DATE(now()) - INTERVAL 7 DAY")->get()->getResultArray()[0]['total'];
            $symbol = get_currency();
            $this->data['total_services'] = $total_services;
            $this->data['total_orders'] = $order_count[0]['total'];
            $this->data['total_balance'] = number_format($total_balance, 2, ".", "");
            $this->data['total_ratings'] = $total_ratings;
            $this->data['total_review'] = $total_review;
            $this->data['number_of_ratings'] = $number_or_ratings;
            $this->data['currency'] = $symbol;
            $this->data['total_promocodes'] = $total_promocodes;
            $this->data['already_withdraw'] = $already_withdraw;
            $this->data['pending_withdraw'] = $pending_withdraw;
            $this->data['total_withdraw_request'] = $total_withdraw_request;
            $this->data['promocode_dates'] = $promocode_dates;
            $this->data['provider_total_earning_chart'] = $provider_total_earning_chart;
            $this->data['provider_already_withdraw_chart'] = $provider_already_withdraw_chart;
            $this->data['provider_pending_withdraw_chart'] = $provider_pending_withdraw_chart;
            $this->data['provider_withdraw_chart'] = $provider_withdraw_chart;
            $this->data['income'] = number_format($income, 2, ".", "");
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_general_outlook', 'Provider General Outlook') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_general_outlook');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - general_outlook()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function partner_service_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_service_list', 'Provider Service List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_service_list');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_service_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_order_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $segments = $uri->getSegments();
            $partner_id = end($segments);
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_booking_list', 'Provider Booking List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_order_list');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_order_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_order_details_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $orders_model = new Orders_model();
            $where = ['o.partner_id' => $partner_id];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'o.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            return $orders_model->list(false, $search, $limit, $offset, $sort, $order, $where, '', '', '', '', '', '');
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_order_details_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_promocode_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
            $partner_data = $this->db->table('users u')
                ->select('u.id,u.username,pd.company_name')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->where('is_approved', '1')
                ->get()->getResultArray();
            $this->data['partner_name'] = $partner_data;
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_promocode_list', 'Provider Promocode List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_promocode_details');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_promocode_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_promocode_details_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $promocode_model = new Promo_code_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pc.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where['pc.partner_id'] = $partner_id;

            // Get current language for translations
            $current_language = get_current_language();

            // Fetch promocodes with translations for current language
            $promo_codes = $promocode_model->list(false, $search, $limit, $offset, $sort, $order, $where, $current_language);

            return $promo_codes;
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_promocode_details_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_review_details()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                helper('function');
                $uri = service('uri');
                $partner_id = $uri->getSegments()[3];
                $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
                $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
                $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
                $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
                $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
                $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
                // Single source of truth: same EXISTS criteria as the review table
                // (partner_review_details_list) so the summary card and table always agree.
                $ratings_model = new Service_ratings_model();
                $this->data['ratingData'] = [$ratings_model->partner_ratings_summary($partner_id)];
                setPageInfo($this->data, labels('provider_review_list', 'Provider Review List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_review_details');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_review_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_review_details_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $ratings_model = new Service_ratings_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            // Use the parameterized EXISTS partner filter (no partner_bids JOIN fan-out)
            // so this table matches partner_ratings_summary() exactly.
            return json_encode($ratings_model->ratings_list(false, $search, $limit, $offset, $sort, $order, [], 'id', [], ['partner_id' => $partner_id]));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_review_details_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_fetch_sales()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            if (!$this->isLoggedIn) {
                return redirect('admin/login');
            } else {
                $sales[] = array();
                $db = \Config\Database::connect();
                $month_res = $db->table('orders')
                    ->select('SUM(final_total) AS total_sale,DATE_FORMAT(created_at,"%b") AS month_name ')
                    ->where('partner_id', $partner_id)
                    ->where('status', 'completed')
                    ->groupBy('year(CURDATE()),MONTH(created_at)')
                    ->orderBy('year(CURDATE()),MONTH(created_at)')
                    ->get()->getResultArray();
                $month_wise_sales['total_sale'] = array_map('intval', array_column($month_res, 'total_sale'));
                $month_wise_sales['month_name'] = array_column($month_res, 'month_name');
                $sales = $month_wise_sales;
                print_r(json_encode($sales));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_fetch_sales()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return JsonError(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"));
            }

            $partnerId = $this->request->getPost('partner_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$partnerId) {
                return JsonError(labels(PARTNER_ID_IS_REQUIRED, "Partner ID is required"));
            }

            // Set SEO model context for providers
            $this->seoModel->setTableContext('providers');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

            if (!$existingSettings) {
                return JsonError(labels(SEO_SETTINGS_NOT_FOUND_FOR_THIS_PARTNER, "SEO settings not found for this partner"));
            }

            // Check if there's an image to remove
            if (empty($existingSettings['image'])) {
                return JsonError(labels(NO_SEO_IMAGE_FOUND_TO_REMOVE, "No SEO image found to remove"));
            }

            // Store the image name for cleanup
            $imageToDelete = $existingSettings['image'];

            // Prepare update data - remove image but keep other fields
            $updateData = [
                'title' => $existingSettings['title'] ?? '',
                'description' => $existingSettings['description'] ?? '',
                'keywords' => $existingSettings['keywords'] ?? '',
                'schema_markup' => $existingSettings['schema_markup'] ?? '',
                'image' => '', // Clear the image field
                'partner_id' => $partnerId
            ];

            // Check if all other SEO fields are empty
            $hasOtherSeoData = !empty($updateData['title']) ||
                !empty($updateData['description']) ||
                !empty($updateData['keywords']) ||
                !empty($updateData['schema_markup']);

            // If all other fields are empty, we should NOT delete the record
            // Instead, we keep the record with empty image but preserve the structure
            // This ensures the SEO record exists for future use
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $updateData);

            if (!empty($result['error'])) {
                return JsonError($result['message']);
            }

            // Clean up the image file from storage
            if (!empty($imageToDelete)) {
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('provider_seo_settings', $imageToDelete, $disk);
            }

            return JsonSuccess(labels(SEO_IMAGE_REMOVED_SUCCESSFULLY, "SEO image removed successfully"));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partners.php - remove_seo_image()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something went wrong"));
        }
    }

    public function partner_handyman_list()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                helper('function');
                $uri = service('uri');
                $partner_id = $uri->getSegments()[3];
                $this->data['partner'] = $this->partner->list(false, '', 1, 0, 'pd.id', 'ASC', ['pd.partner_id ' => $partner_id]);
                $this->data['partner_id'] = $partner_id;
                $this->data['currency'] = get_currency();
                setPageInfo($this->data, labels('handymen', 'Handymen') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_handyman_list');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/Admin/ProviderDetailsController.php - partner_handyman_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function partner_handyman_list_data()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['total' => 0, 'rows' => []]);
            }

            $uri = service('uri');
            $partnerId = (int) $uri->getSegments()[3];

            $limit = (int) ($this->request->getGet('limit') ?: 10);
            $offset = (int) ($this->request->getGet('offset') ?: 0);
            $search = trim((string) ($this->request->getGet('search') ?: ''));
            $statusRaw = $this->request->getGet('status');
            $statusFilter = ($statusRaw !== null && $statusRaw !== '') ? (int) $statusRaw : null;

            $sort = $this->request->getGet('sort') ?: 'u.id';
            $order = $this->request->getGet('order') ?: 'DESC';

            $allowedSorts = ['u.id', 'u.username', 'u.phone', 'u.email'];
            if (!in_array($sort, $allowedSorts, true)) {
                $sort = 'u.id';
            }
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

            $handymanModel = new \App\Models\HandymanDetailsModel();
            $result = $handymanModel->list($partnerId, false, $search, $limit, $offset, $sort, $order, $statusFilter);

            $permissionService = new \App\Services\utility\PermissionService();
            $canUpdate = $permissionService->can($this->userId, 'update', 'handyman');

            foreach ($result['rows'] as &$row) {
                $isActive = (int) $row['active'] === 1;
                $id = (int) $row['id'];
                $statusLabel = $isActive ? labels('deactivate', 'Deactivate') : labels('activate', 'Activate');
                $statusIcon = $isActive ? 'fa-ban' : 'fa-check';
                $statusBtnClass = $isActive ? 'btn-outline-warning' : 'btn-outline-success';
                $viewUrl = base_url('admin/handymen/view/' . $id);

                if (!empty($row['image_url'])) {
                    $profileThumb = '<img class="o-media__img images_in_card" src="' . $row['image_url'] . '" alt="' . $id . '">';
                } else {
                    $initial = strtoupper(substr($row['username'] ?? '', 0, 1) ?: 'H');
                    $bgColor = '#' . substr(md5($id), 0, 6);
                    $profileThumb = '<div class="o-media__img images_in_card fallback-initial" '
                        . 'style="width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;'
                        . 'background-color:' . $bgColor . ';color:#fff;font-weight:bold;font-size:20px;" '
                        . 'title="' . esc($row['username'] ?? '') . '">' . $initial . '</div>';
                }

                $row['profile_image'] = '<a href="' . $viewUrl . '" title="' . labels('view_handyman', 'View Handyman') . '">' . $profileThumb . '</a>';
                $row['profile'] = '<a href="' . $viewUrl . '" class="o-media o-media--middle text-decoration-none">'
                    . $profileThumb
                    . '<div class="o-media__body">'
                    . '<div class="provider_name_table">' . esc($row['username'] ?? '') . '</div>'
                    . '<div class="provider_email_table">' . esc(trim(($row['country_code'] ?? '') . ' ' . ($row['phone_number'] ?? ''))) . '</div>'
                    . (!empty($row['email']) ? '<div class="provider_email_table">' . esc($row['email']) . '</div>' : '')
                    . '</div></a>';
                $row['username'] = '<a href="' . $viewUrl . '" class="text-decoration-none hm-admin-username-link">' . esc($row['username'] ?? '') . '</a>';

                $toggleBtn = $canUpdate
                    ? '<a href="#" class="btn ' . $statusBtnClass . ' btn-sm hm-admin-toggle-status mr-1" '
                    . 'data-active="' . (int) $isActive . '" title="' . $statusLabel . '">'
                    . '<i class="fas ' . $statusIcon . '"></i></a>'
                    : '';

                $row['operations'] = $toggleBtn
                    . '<a href="' . $viewUrl . '" class="btn btn-primary btn-sm" title="' . labels('view_handyman', 'View Handyman') . '">'
                    . '<i class="fa fa-eye"></i></a>';
            }
            unset($row);

            return $this->response->setJSON($result);

        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> ProviderDetailsController::partner_handyman_list_data()');
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }
    }

    public function partner_handyman_toggle_status()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return JsonError(labels('unauthorized'));
            }

            $handymanId = (int) ($this->request->getPost('id') ?: 0);
            $partnerId = (int) ($this->request->getPost('partner_id') ?: 0);

            if ($handymanId <= 0 || $partnerId <= 0) {
                return JsonError(labels('invalid_id'));
            }

            $handymanModel = new \App\Models\HandymanDetailsModel();
            $detail = $handymanModel->where('handyman_id', $handymanId)->where('partner_id', $partnerId)->first();
            if (empty($detail)) {
                return JsonError(labels('data_not_found'));
            }

            $usersModel = new \App\Models\Users_model();
            $user = $usersModel->find($handymanId);
            $newActive = ((int) $user['active'] === 1) ? 0 : 1;
            $usersModel->update($handymanId, ['active' => $newActive]);

            return JsonSuccess($newActive === 1 ? labels('activated_successfully') : labels('deactivated_successfully'));

        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> ProviderDetailsController::partner_handyman_toggle_status()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }
}
