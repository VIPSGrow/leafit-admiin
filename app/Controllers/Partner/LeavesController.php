<?php

namespace App\Controllers\Partner;

use App\Models\ProviderLeaves_model;
use App\Models\ProviderShifts_model;

class LeavesController extends Partner
{
    protected ProviderLeaves_model $providerLeaves;
    protected ProviderShifts_model $providerShifts;

    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
        $this->providerLeaves = new ProviderLeaves_model();
        $this->providerShifts = new ProviderShifts_model();
    }

    public function index()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        setPageInfo(
            $this->data,
            labels('leaves', 'Leaves') . ' | ' . labels('provider_panel', 'Provider Panel'),
            'leaves'
        );

        $shifts_by_day = [
            'monday' => [],
            'tuesday' => [],
            'wednesday' => [],
            'thursday' => [],
            'friday' => [],
            'saturday' => [],
            'sunday' => [],
        ];
        $day_open_state = array_fill_keys(array_keys($shifts_by_day), false);

        if ($this->db->tableExists('provider_shifts')) {
            $rows = $this->db->table('provider_shifts')
                ->where('partner_id', $this->userId)
                ->orderBy('day', 'ASC')
                ->orderBy('shift_number', 'ASC')
                ->get()->getResultArray();

            foreach ($rows as $row) {
                $day = $row['day'] ?? '';
                if (!isset($shifts_by_day[$day])) {
                    continue;
                }
                if ((int) ($row['is_open'] ?? 0) === 1) {
                    $day_open_state[$day] = true;
                    $opening = (string) ($row['opening_time'] ?? '');
                    $closing = (string) ($row['closing_time'] ?? '');
                    $shifts_by_day[$day][] = [
                        'start' => $opening !== '' ? date('h:i A', strtotime($opening)) : '',
                        'end' => $closing !== '' ? date('h:i A', strtotime($closing)) : '',
                    ];
                }
            }
        }

        // Fallback to legacy partner_timings if provider_shifts not populated yet.
        $partner_timings = !empty(fetch_details('partner_timings', ['partner_id' => $this->userId]))
            ? fetch_details('partner_timings', ['partner_id' => $this->userId])
            : [];
        $legacyDayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($legacyDayOrder as $i => $day) {
            if (!empty($shifts_by_day[$day])) {
                continue;
            }
            $row = $partner_timings[$i] ?? null;
            if (!$row) {
                continue;
            }
            $opening = (string) ($row['opening_time'] ?? '');
            $closing = (string) ($row['closing_time'] ?? '');
            if ($opening !== '' && $closing !== '') {
                $day_open_state[$day] = true;
                $shifts_by_day[$day][] = [
                    'start' => date('h:i A', strtotime($opening)),
                    'end' => date('h:i A', strtotime($closing)),
                ];
            }
        }

        $this->data['shifts_by_day'] = $shifts_by_day;
        $this->data['day_open_state'] = $day_open_state;
        // Provider leaves are now loaded into a bootstrap-table via AJAX (leaves_list),
        // so no full prefill is needed on initial render.
        $this->data['provider_leaves'] = [];

        return view('backend/partner/template', $this->data);
    }

    /**
     * Server-side data source for the partner leaves bootstrap-table.
     * Groups rows by (leave_date, day) so each table row represents one
     * date with its shift count + shift details. Date-range filter only.
     */
    public function leaves_list()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return redirect('partner/login');
            }

            $response = ['total' => 0, 'totalNotFiltered' => 0, 'rows' => []];

            if (!$this->providerLeaves->tableExists()) {
                print_r(json_encode($response));
                return;
            }

            $partnerId = (int) $this->userId;
            $limit = (isset($_GET['limit']) && $_GET['limit'] !== '') ? (int) $_GET['limit'] : 20;
            $offset = (isset($_GET['offset']) && $_GET['offset'] !== '') ? (int) $_GET['offset'] : 0;
            $sort = $_GET['sort'] ?? 'leave_date';
            $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

            $sortable = [
                'leave_date' => 'pl.leave_date',
                'day' => 'pl.day',
            ];
            $sortColumn = $sortable[$sort] ?? 'pl.leave_date';

            // Default ordering: today first, then upcoming asc, then past (most recent first).
            // User clicking a column header sets user_sorted=1, which switches to plain column-order.
            $userSorted = (string) ($_GET['user_sorted'] ?? '0') === '1';
            $useBucketOrder = !$userSorted;

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

            $db = \Config\Database::connect();

            $totalNotFiltered = (int) $db->table('provider_leaves')
                ->select('1', false)
                ->where('partner_id', $partnerId)
                ->groupBy('leave_date')
                ->countAllResults();

            $applyFilters = function ($builder) use ($partnerId, $dateFrom, $dateTo) {
                $builder->where('pl.partner_id', $partnerId);
                if ($dateFrom !== '')
                    $builder->where('pl.leave_date >=', $dateFrom);
                if ($dateTo !== '')
                    $builder->where('pl.leave_date <=', $dateTo);
            };

            $countBuilder = $db->table('provider_leaves pl')
                ->select('1', false)
                ->groupBy('pl.leave_date');
            $applyFilters($countBuilder);
            $total = (int) $countBuilder->countAllResults();

            $dataBuilder = $db->table('provider_leaves pl')
                ->select("pl.leave_date, pl.day,
                          COUNT(*) AS shifts_count,
                          GROUP_CONCAT(CONCAT('Shift ', pl.shift_number, ' (', TIME_FORMAT(pl.opening_time, '%h:%i %p'), ' - ', TIME_FORMAT(pl.closing_time, '%h:%i %p'), ')') ORDER BY pl.shift_number SEPARATOR ', ') AS shifts", false)
                ->groupBy('pl.leave_date, pl.day');
            $applyFilters($dataBuilder);
            if ($useBucketOrder) {
                $dataBuilder->orderBy('CASE WHEN pl.leave_date = CURDATE() THEN 0 WHEN pl.leave_date > CURDATE() THEN 1 ELSE 2 END', 'ASC', false)
                    ->orderBy('ABS(DATEDIFF(pl.leave_date, CURDATE()))', 'ASC', false);
            } else {
                $dataBuilder->orderBy($sortColumn, $order);
            }
            $rows = $dataBuilder->limit($limit, $offset)->get()->getResultArray();

            $formatted = [];
            foreach ($rows as $row) {
                $leaveDate = (string) $row['leave_date'];
                $deleteBtn = '<button type="button" class="btn btn-sm btn-danger delete-leave-btn" data-date="' . htmlspecialchars($leaveDate, ENT_QUOTES) . '">'
                    . '<i class="fas fa-trash-alt"></i>'
                    . '</button>';

                $formatted[] = [
                    'leave_date' => $leaveDate,
                    'day' => ucfirst((string) $row['day']),
                    'shifts_count' => (int) $row['shifts_count'],
                    'shifts' => $row['shifts'],
                    'action' => $deleteBtn,
                ];
            }

            $response['total'] = $total;
            $response['totalNotFiltered'] = $totalNotFiltered;
            $response['rows'] = $formatted;

            print_r(json_encode($response));
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . ' --> app/Controllers/partner/LeavesController.php - leaves_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Delete all shift-leaves for this partner on a given date.
     */
    public function delete()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            if (!$this->providerLeaves->tableExists()) {
                return ErrorResponse(labels('unable_to_delete_leave', 'Unable to delete leave'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $leaveDate = trim((string) $this->request->getPost('leave_date'));
            $ts = strtotime($leaveDate);
            if ($leaveDate === '' || $ts === false || date('Y-m-d', $ts) !== $leaveDate) {
                return ErrorResponse(labels('invalid_leave_date', 'Invalid leave date'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $this->providerLeaves->deleteByPartnerOnDate((int) $this->userId, $leaveDate);
            return successResponse(labels(DATA_DELETED_SUCCESSFULLY, 'Data deleted successfully'), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . ' --> app/Controllers/partner/LeavesController.php - delete()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Add new leaves only. Update of existing leaves is not supported —
     * the duplicate-date validation below blocks any save that overlaps
     * already-stored leaves. Removal is handled exclusively by delete().
     */
    public function add()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            if (!$this->providerLeaves->tableExists()) {
                return ErrorResponse(labels('unable_to_save_leave', 'Unable to save leave'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $partnerId = (int) $this->userId;
            $postData = $this->request->getPost();

            $fromDate = trim((string) ($postData['leave_from_date'] ?? ''));
            $toDate = trim((string) ($postData['leave_to_date'] ?? ''));
            $leaveShifts = $postData['leave_shifts'] ?? [];

            // Build (day → shift_number → opening/closing) snapshot from current shifts.
            // Filter to is_open=1 rows ONLY — `getByPartnerGroupedByDay` returns every
            // row regardless of open state, so closed days would otherwise look like
            // they have shifts and the "provider closed" branch below would never fire.
            $shiftsByDay = $this->providerShifts->getByPartnerGroupedByDay($partnerId);
            $shiftIndex = [];
            foreach ($shiftsByDay as $day => $rows) {
                foreach ($rows as $row) {
                    if ((int) ($row['is_open'] ?? 0) !== 1) {
                        continue;
                    }
                    $shiftIndex[$day][(int) $row['shift_number']] = [
                        'opening_time' => $row['opening_time'],
                        'closing_time' => $row['closing_time'],
                    ];
                }
            }

            $dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

            // Empty-payload reason picker: if from→to range exists but every weekday
            // in it is closed, the user simply selected a closed window and gets a
            // tailored message. Otherwise they just didn't tick any shift.
            $emptyPayloadResponse = function () use ($fromDate, $toDate, $shiftIndex, $dayKeys) {
                if ($fromDate !== '' && $toDate !== '' && $fromDate <= $toDate) {
                    $hasOpen = false;
                    $ts = strtotime($fromDate);
                    $endTs = strtotime($toDate);
                    while ($ts !== false && $endTs !== false && $ts <= $endTs) {
                        $dk = $dayKeys[(int) date('w', $ts)];
                        if (!empty($shiftIndex[$dk])) {
                            $hasOpen = true;
                            break;
                        }
                        $ts = strtotime('+1 day', $ts);
                    }
                    if (!$hasOpen) {
                        return ErrorResponse(
                            labels('cannot_add_leave_provider_closed', 'Cannot add leave, provider is closed on the selected date(s)'),
                            true,
                            [],
                            [],
                            200,
                            csrf_token(),
                            csrf_hash()
                        );
                    }
                }
                return ErrorResponse(
                    labels('please_select_at_least_one_shift', 'Please select at least one shift to mark as leave'),
                    true,
                    [],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            };

            if (empty($leaveShifts) || !is_array($leaveShifts)) {
                return $emptyPayloadResponse();
            }

            // Pass 1: collect candidate (date → indices) entries that pass shape/range
            // checks. We then run the two business validations on this candidate set
            // BEFORE touching the DB.
            $candidateDates = []; // isoDate => ['day_key' => ..., 'indices' => [...]]
            foreach ($leaveShifts as $isoDate => $indices) {
                $isoDate = trim((string) $isoDate);
                if ($isoDate === '' || !is_array($indices) || empty($indices)) {
                    continue;
                }
                $ts = strtotime($isoDate);
                if ($ts === false || date('Y-m-d', $ts) !== $isoDate) {
                    continue;
                }
                if ($fromDate !== '' && $isoDate < $fromDate)
                    continue;
                if ($toDate !== '' && $isoDate > $toDate)
                    continue;

                $candidateDates[$isoDate] = [
                    'day_key' => $dayKeys[(int) date('w', $ts)],
                    'indices' => $indices,
                ];
            }

            if (empty($candidateDates)) {
                return $emptyPayloadResponse();
            }

            // Validation 1: every candidate date falls on a closed day (no shifts
            // configured for that weekday). Refuse — provider isn't open on any
            // selected date, so leave is meaningless.
            $allClosed = true;
            foreach ($candidateDates as $info) {
                if (!empty($shiftIndex[$info['day_key']])) {
                    $allClosed = false;
                    break;
                }
            }
            if ($allClosed) {
                return ErrorResponse(
                    labels('cannot_add_leave_provider_closed', 'Cannot add leave, provider is closed on the selected date(s)'),
                    true,
                    [],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            }

            // Validation 2: any candidate date already has at least one stored leave.
            // We block the entire save (per spec: "leave already applied for this day,
            // not allow saving"). Query existing rows for ONLY the candidate dates so
            // dates outside the picker range are also caught.
            $candidateDateList = array_keys($candidateDates);
            $existingRows = $this->providerLeaves
                ->where('partner_id', $partnerId)
                ->whereIn('leave_date', $candidateDateList)
                ->findAll();
            if (!empty($existingRows)) {
                // Collect unique (date, day) pairs and interpolate them into the
                // label via lang() placeholder so the user sees exactly which dates
                // already have a leave.
                $conflicts = [];
                foreach ($existingRows as $r) {
                    $iso = (string) ($r['leave_date'] ?? '');
                    if ($iso === '' || isset($conflicts[$iso]))
                        continue;
                    $dayName = ucfirst((string) ($r['day'] ?? ''));
                    $conflicts[$iso] = $iso . ($dayName !== '' ? ' (' . $dayName . ')' : '');
                }
                ksort($conflicts);
                $datesStr = implode(', ', $conflicts);
                $message = labels(
                    'leave_already_applied_on_dates',
                    'Leave already applied on: ' . $datesStr,
                    ['dates' => $datesStr]
                );
                return ErrorResponse($message, true, [], [], 200, csrf_token(), csrf_hash());
            }

            $rowsToInsert = [];
            $seen = [];

            foreach ($candidateDates as $isoDate => $info) {
                $dayKey = $info['day_key'];
                $indices = $info['indices'];
                $dayShifts = $shiftIndex[$dayKey] ?? [];
                if (empty($dayShifts)) {
                    // Skip closed-day dates when the rest of the selection is open.
                    continue;
                }

                foreach ($indices as $idx) {
                    $shiftNumber = (int) $idx + 1;
                    if (!isset($dayShifts[$shiftNumber])) {
                        continue;
                    }
                    if (!empty($seen[$isoDate][$shiftNumber])) {
                        continue;
                    }
                    $seen[$isoDate][$shiftNumber] = true;

                    $rowsToInsert[] = [
                        'leave_date' => $isoDate,
                        'day' => $dayKey,
                        'shift_number' => $shiftNumber,
                        'opening_time' => $dayShifts[$shiftNumber]['opening_time'],
                        'closing_time' => $dayShifts[$shiftNumber]['closing_time'],
                    ];
                }
            }

            if (empty($rowsToInsert)) {
                return ErrorResponse(
                    labels('please_select_at_least_one_shift', 'Please select at least one shift to mark as leave'),
                    true,
                    [],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            }

            // Validation 3: block save if active bookings exist for any of the selected shifts.
            $conflictingBookings = $this->getConflictingBookings($partnerId, $rowsToInsert);
            if (!empty($conflictingBookings)) {
                return ErrorResponse(
                    labels('cannot_add_leave_active_bookings_exist', 'Cannot add leave. Provider has active bookings that must be managed first.'),
                    true,
                    ['booking_conflict' => true, 'bookings' => $conflictingBookings],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            }

            $this->providerLeaves->insertBatchForPartner($partnerId, $rowsToInsert);

            return successResponse(labels(DATA_SAVED_SUCCESSFULLY, 'Data saved successfully'), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> app/Controllers/partner/LeavesController.php - add()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Query active bookings that fall within any of the requested leave shift windows.
     * Returns a flat array of conflict records for display in the UI modal.
     *
     * @param array $rowsToInsert Each row: {leave_date, shift_number, opening_time, closing_time}
     * @return array<int,array{order_id:int, date:string, starting_time:string, customer_name:string}>
     */
    private function getConflictingBookings(int $partnerId, array $rowsToInsert): array
    {
        $dateList = array_values(array_unique(array_column($rowsToInsert, 'leave_date')));
        if (empty($dateList)) {
            return [];
        }

        $db = \Config\Database::connect();
        $rows = $db->table('orders o')
            ->select('o.id, o.date_of_service, o.starting_time, u.username as customer_name')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->join('order_services os', 'os.order_id = o.id')
            ->join('services s', 's.id = os.service_id')
            ->where('o.partner_id', $partnerId)
            ->whereIn('o.date_of_service', $dateList)
            ->whereIn('o.status', ['awaiting', 'confirmed', 'rescheduled', 'started'])
            ->get()->getResultArray();

        // Build per-date shift windows from the insert rows.
        $shiftWindows = [];
        foreach ($rowsToInsert as $row) {
            $shiftWindows[$row['leave_date']][] = [
                'open' => $row['opening_time'],
                'close' => $row['closing_time'],
            ];
        }

        $conflicts = [];
        foreach ($rows as $b) {
            $date = (string) $b['date_of_service'];
            $start = (string) $b['starting_time'];
            foreach (($shiftWindows[$date] ?? []) as $window) {
                if ($start >= $window['open'] && $start < $window['close']) {
                    $conflicts[] = [
                        'order_id' => (int) $b['id'],
                        'date' => $date,
                        'starting_time' => date('h:i A', strtotime($start)),
                        'customer_name' => (string) ($b['customer_name'] ?? ''),
                    ];
                    break;
                }
            }
        }

        return $conflicts;
    }
}
