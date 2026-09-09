<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\ProviderLeaves_model;
use App\Models\ProviderShifts_model;
use App\Services\Provider\SlotSettingsService;

/**
 * Provider Slot Management API
 *
 * Unified endpoints for the provider mobile app to read and write the three
 * slot-management configurations stored against a provider:
 *   1. shifts                – provider_shifts (weekly working hours, multi-shift)
 *   2. slot_configurations   – provider_slot_settings (booking-window config)
 *   3. leaves                – provider_leaves (shift-based date leaves)
 *
 * Endpoints:
 *   POST partner/api/v1/get_slot_configurations
 *   POST partner/api/v1/manage_slot_configurations   (requires `type`)
 */
class SlotManagementApiController extends BaseController
{
    private const TYPE_SHIFTS  = 'shifts';
    private const TYPE_SLOTS   = 'slot_configurations';
    private const TYPE_LEAVES  = 'leaves';

    private const VALID_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    protected $request;
    protected \CodeIgniter\Validation\Validation $validation;
    protected array $user_details = [];

    protected ProviderShifts_model $shiftsModel;
    protected ProviderLeaves_model $leavesModel;
    protected SlotSettingsService $slotService;

    public function __construct()
    {
        helper('api');
        helper('function');
        helper('ResponceServices');

        $this->request    = \Config\Services::request();
        $this->validation = \Config\Services::validation();

        $this->shiftsModel = new ProviderShifts_model();
        $this->leavesModel = new ProviderLeaves_model();
        $this->slotService = new SlotSettingsService();

        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error'   => true,
                'message' => $token['message'],
                'status'  => 401,
            ]));
            die();
        }
    }

    public function get_slot_configurations()
    {
        try {
            $partnerId = (int) ($this->user_details['id'] ?? 0);
            if ($partnerId <= 0) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'),
                ]);
            }

            return $this->response->setJSON([
                'error'   => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                'data'    => [
                    self::TYPE_SHIFTS => $this->formatShifts($this->shiftsModel->getByPartner($partnerId)),
                    self::TYPE_SLOTS  => $this->formatSlotSettings($this->slotService->find($partnerId)),
                    self::TYPE_LEAVES => $this->formatLeaves(
                        $this->leavesModel->tableExists() ? $this->leavesModel->getByPartner($partnerId) : []
                    ),
                ],
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th,
                date('Y-m-d H:i:s') . '--> Apis/Provider/SlotManagementApiController - get_slot_configurations()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function manage_slot_configurations()
    {
        try {
            $partnerId = (int) ($this->user_details['id'] ?? 0);
            if ($partnerId <= 0) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'),
                ]);
            }

            $this->validation->setRules([
                'type' => [
                    'label' => 'type',
                    'rules' => 'required|in_list[' . implode(',', [self::TYPE_SHIFTS, self::TYPE_SLOTS, self::TYPE_LEAVES]) . ']',
                    'errors' => [
                        'required' => 'The type field is required.',
                        'in_list'  => 'The type must be one of: shifts, slot_configurations, leaves.',
                    ],
                ],
            ]);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => is_array($errors) ? (string) reset($errors) : (string) $errors,
                ]);
            }

            $type = (string) $this->request->getPost('type');

            switch ($type) {
                case self::TYPE_SHIFTS:
                    return $this->saveShifts($partnerId);
                case self::TYPE_SLOTS:
                    return $this->saveSlotSettings($partnerId);
                case self::TYPE_LEAVES:
                    return $this->saveLeaves($partnerId);
            }

            return $this->response->setJSON([
                'error'   => true,
                'message' => 'The type must be one of: shifts, slot_configurations, leaves.',
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th,
                date('Y-m-d H:i:s') . '--> Apis/Provider/SlotManagementApiController - manage_slot_configurations()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /* ----------------------------- Formatters ---------------------------- */

    private function formatShifts(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'day'          => (string) ($row['day'] ?? ''),
                'shift_number' => (int) ($row['shift_number'] ?? 1),
                'opening_time' => (string) ($row['opening_time'] ?? ''),
                'closing_time' => (string) ($row['closing_time'] ?? ''),
                'is_open'      => (int) ($row['is_open'] ?? 0),
            ];
        }
        return $out;
    }

    private function formatSlotSettings(array $row): array
    {
        return [
            'slot_interval'             => (int) ($row['slot_interval'] ?? 30),
            'allow_multiple_bookings'   => (int) ($row['allow_multiple_bookings'] ?? 0),
            'slot_capacity'             => (int) ($row['slot_capacity'] ?? 1),
            'min_advance_booking_value' => (int) ($row['min_advance_booking_value'] ?? 1),
            'min_advance_booking_unit'  => (string) ($row['min_advance_booking_unit'] ?? 'hours'),
            'max_advance_booking_days'  => (int) ($row['max_advance_booking_days'] ?? 30),
            'same_day_booking'          => (int) ($row['same_day_booking'] ?? 1),
            'buffer_before'             => (int) ($row['buffer_before'] ?? 0),
            'buffer_after'              => (int) ($row['buffer_after'] ?? 0),
        ];
    }

    private function formatLeaves(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'leave_date'   => (string) ($row['leave_date'] ?? ''),
                'day'          => (string) ($row['day'] ?? ''),
                'shift_number' => (int) ($row['shift_number'] ?? 1),
                'opening_time' => (string) ($row['opening_time'] ?? ''),
                'closing_time' => (string) ($row['closing_time'] ?? ''),
            ];
        }
        return $out;
    }

    /* -------------------------------- Save ------------------------------- */

    /**
     * Save shifts (full replace). Payload:
     *   shifts: JSON-encoded string OR array of:
     *     { day, shift_number, opening_time (HH:MM[:SS]), closing_time, is_open }
     *
     * Behaviour:
     *   - Wipes all existing shifts for partner, inserts the new set.
     *   - Auto-renumbers shift_number per day starting at 1 to preserve the
     *     unique (partner_id, day, shift_number) constraint.
     */
    private function saveShifts(int $partnerId)
    {
        $raw = $this->decodeArrayPayload($this->request->getPost('shifts'));
        if ($raw === null) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => 'The shifts field is required and must be a list of shifts.',
            ]);
        }

        $byDay = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $day = strtolower(trim((string) ($entry['day'] ?? '')));
            if (!in_array($day, self::VALID_DAYS, true)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => 'Invalid day in shifts. Must be monday through sunday.',
                ]);
            }
            $open  = $this->normalizeTime($entry['opening_time'] ?? '');
            $close = $this->normalizeTime($entry['closing_time'] ?? '');
            if ($open === null || $close === null) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => 'Invalid opening_time or closing_time. Expected HH:MM or HH:MM:SS.',
                ]);
            }
            if ($close <= $open) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('shift_end_after_start', 'End time must be after start time'),
                ]);
            }
            $isOpen = $this->toBoolInt($entry['is_open'] ?? 1);

            $byDay[$day][] = [
                'opening_time' => $open,
                'closing_time' => $close,
                'is_open'      => $isOpen,
            ];
        }

        foreach ($byDay as $day => $entries) {
            $entryCount = count($entries);
            for ($a = 0; $a < $entryCount; $a++) {
                for ($b = $a + 1; $b < $entryCount; $b++) {
                    if ($entries[$a]['opening_time'] < $entries[$b]['closing_time'] && $entries[$b]['opening_time'] < $entries[$a]['closing_time']) {
                        $msg = str_replace(
                            ['{shift1}', '{shift2}', '{day}'],
                            [$a + 1, $b + 1, ucfirst($day)],
                            labels('shift_overlap', 'Shift {shift1} overlaps with Shift {shift2} on {day}')
                        );
                        return $this->response->setJSON([
                            'error'   => true,
                            'message' => $msg,
                        ]);
                    }
                }
            }
        }

        $rowsToInsert = [];
        foreach (self::VALID_DAYS as $day) {
            $entries = $byDay[$day] ?? [];
            $shiftNumber = 1;
            foreach ($entries as $e) {
                $rowsToInsert[] = [
                    'day'          => $day,
                    'shift_number' => $shiftNumber++,
                    'opening_time' => $e['opening_time'],
                    'closing_time' => $e['closing_time'],
                    'is_open'      => $e['is_open'],
                ];
            }
        }

        $this->shiftsModel->deleteByPartner($partnerId);
        if (!empty($rowsToInsert)) {
            $this->shiftsModel->insertBatchForPartner($partnerId, $rowsToInsert);
        }

        return $this->successUpdated();
    }

    /**
     * Save slot configurations. Sanitization handled by SlotSettingsService.
     * Mirrors max_advance_booking_days into partner_details.advance_booking_days
     * for legacy callers (orders, customer APIs).
     */
    private function saveSlotSettings(int $partnerId)
    {
        $post = $this->request->getPost();

        $input = [
            'slot_interval'             => $post['slot_interval']             ?? null,
            'allow_multiple_bookings'   => $post['allow_multiple_bookings']   ?? null,
            'slot_capacity'             => $post['slot_capacity']             ?? null,
            'min_advance_booking_value' => $post['min_advance_booking_value'] ?? null,
            'min_advance_booking_unit'  => $post['min_advance_booking_unit']  ?? null,
            'max_advance_booking_days'  => $post['max_advance_booking_days']  ?? null,
            'same_day_booking'          => $post['same_day_booking']          ?? null,
            'buffer_before'             => $post['buffer_before']             ?? null,
            'buffer_after'              => $post['buffer_after']              ?? null,
        ];

        $saved = $this->slotService->save($partnerId, $input);

        update_details(
            ['advance_booking_days' => (int) ($saved['max_advance_booking_days'] ?? 30)],
            ['partner_id' => $partnerId],
            'partner_details'
        );

        return $this->successUpdated();
    }

    /**
     * Add new leaves (additive — never deletes existing entries). Payload:
     *   leaves: JSON-encoded string OR array of:
     *     { leave_date (Y-m-d), shift_number (int, 1-based) }
     *
     * Validations mirror the partner-panel `LeavesController::add()`:
     *   - All-closed-day check: every candidate date must fall on a weekday
     *     that has at least one is_open=1 shift.
     *   - Duplicate-date check: no candidate date may already have a stored
     *     leave for this partner (any shift). Conflicts are surfaced in the
     *     error message.
     *
     * Removal goes through `delete_leave` — this endpoint never wipes data.
     */
    private function saveLeaves(int $partnerId)
    {
        if (!$this->leavesModel->tableExists()) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('unable_to_save_leave', 'Unable to save leave'),
            ]);
        }

        $raw = $this->decodeArrayPayload($this->request->getPost('leaves'));
        if ($raw === null) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => 'The leaves field is required and must be a list of leaves.',
            ]);
        }

        // Build (day → shift_number → opening/closing) snapshot from current
        // shifts, filtered to is_open=1 rows so the closed-day check below
        // actually sees closed days as empty.
        $shiftIndex = [];
        foreach ($this->shiftsModel->getByPartnerGroupedByDay($partnerId) as $day => $rows) {
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

        $dayKeys = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];

        // Pass 1: parse + validate every input entry, grouping by date.
        // Bad shape/date → hard error so the caller can fix the payload.
        // Valid entries are kept as candidates regardless of closed-day or
        // duplicate state; those concerns are evaluated in Pass 2.
        $candidateDates = []; // isoDate => ['day_key' => ..., 'shift_numbers' => [int,...]]
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $isoDate = trim((string) ($entry['leave_date'] ?? ''));
            $ts = $isoDate !== '' ? strtotime($isoDate) : false;
            if ($ts === false || date('Y-m-d', $ts) !== $isoDate) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_leave_date', 'Invalid leave date'),
                ]);
            }
            $shiftNumber = (int) ($entry['shift_number'] ?? 0);
            if ($shiftNumber < 1) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => 'Invalid shift_number. Must be a positive integer.',
                ]);
            }

            $dayKey = $dayKeys[(int) date('w', $ts)];
            if (!isset($candidateDates[$isoDate])) {
                $candidateDates[$isoDate] = ['day_key' => $dayKey, 'shift_numbers' => []];
            }
            $candidateDates[$isoDate]['shift_numbers'][$shiftNumber] = true;
        }

        if (empty($candidateDates)) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('please_select_at_least_one_shift', 'Please select at least one shift to mark as leave'),
            ]);
        }

        // Validation 1: every candidate date falls on a closed weekday.
        $allClosed = true;
        foreach ($candidateDates as $info) {
            if (!empty($shiftIndex[$info['day_key']])) {
                $allClosed = false;
                break;
            }
        }
        if ($allClosed) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('cannot_add_leave_provider_closed', 'Cannot add leave, provider is closed on the selected date(s)'),
            ]);
        }

        // Validation 2: any candidate date already has a stored leave.
        $candidateDateList = array_keys($candidateDates);
        $existingRows = $this->leavesModel
            ->where('partner_id', $partnerId)
            ->whereIn('leave_date', $candidateDateList)
            ->findAll();
        if (!empty($existingRows)) {
            $conflicts = [];
            foreach ($existingRows as $r) {
                $iso = (string) ($r['leave_date'] ?? '');
                if ($iso === '' || isset($conflicts[$iso])) continue;
                $dayName = ucfirst((string) ($r['day'] ?? ''));
                $conflicts[$iso] = $iso . ($dayName !== '' ? ' (' . $dayName . ')' : '');
            }
            ksort($conflicts);
            $datesStr = implode(', ', $conflicts);
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(
                    'leave_already_applied_on_dates',
                    'Leave already applied on: ' . $datesStr,
                    ['dates' => $datesStr]
                ),
            ]);
        }

        // Pass 2: build insert rows. Skip closed-day dates (mixed selection)
        // and unknown shift_numbers silently — Validation 1/2 above already
        // covered the hard-error cases.
        $rowsToInsert = [];
        $seen = [];
        foreach ($candidateDates as $isoDate => $info) {
            $dayShifts = $shiftIndex[$info['day_key']] ?? [];
            if (empty($dayShifts)) {
                continue;
            }
            foreach (array_keys($info['shift_numbers']) as $shiftNumber) {
                if (!isset($dayShifts[$shiftNumber])) {
                    continue;
                }
                if (!empty($seen[$isoDate][$shiftNumber])) {
                    continue;
                }
                $seen[$isoDate][$shiftNumber] = true;

                $rowsToInsert[] = [
                    'leave_date'   => $isoDate,
                    'day'          => $info['day_key'],
                    'shift_number' => $shiftNumber,
                    'opening_time' => $dayShifts[$shiftNumber]['opening_time'],
                    'closing_time' => $dayShifts[$shiftNumber]['closing_time'],
                ];
            }
        }

        if (empty($rowsToInsert)) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('please_select_at_least_one_shift', 'Please select at least one shift to mark as leave'),
            ]);
        }

        // Validation 3: block save if active bookings exist for any of the selected shifts.
        $conflictingBookings = $this->getConflictingBookings($partnerId, $rowsToInsert);
        if (!empty($conflictingBookings)) {
            return $this->response->setJSON([
                'error'            => true,
                'message'          => labels('cannot_add_leave_active_bookings_exist', 'Cannot add leave. Provider has active bookings that must be managed first.'),
                'booking_conflict' => true,
                'bookings'         => $conflictingBookings,
            ]);
        }

        $this->leavesModel->insertBatchForPartner($partnerId, $rowsToInsert);

        return $this->response->setJSON([
            'error'   => false,
            'message' => labels(DATA_SAVED_SUCCESSFULLY, 'Data saved successfully'),
        ]);
    }

    /**
     * Delete all shift-leaves for the authenticated partner on a given date.
     * Payload: leave_date (Y-m-d).
     */
    public function delete_leave()
    {
        try {
            $partnerId = (int) ($this->user_details['id'] ?? 0);
            if ($partnerId <= 0) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'),
                ]);
            }

            if (!$this->leavesModel->tableExists()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('unable_to_delete_leave', 'Unable to delete leave'),
                ]);
            }

            $leaveDate = trim((string) $this->request->getPost('leave_date'));
            $ts = $leaveDate !== '' ? strtotime($leaveDate) : false;
            if ($ts === false || date('Y-m-d', $ts) !== $leaveDate) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_leave_date', 'Invalid leave date'),
                ]);
            }

            $this->leavesModel->deleteByPartnerOnDate($partnerId, $leaveDate);

            return $this->response->setJSON([
                'error'   => false,
                'message' => labels(DATA_DELETED_SUCCESSFULLY, 'Data deleted successfully'),
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th,
                date('Y-m-d H:i:s') . '--> Apis/Provider/SlotManagementApiController - delete_leave()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /* ------------------------------ Helpers ------------------------------ */

    private function successUpdated()
    {
        return $this->response->setJSON([
            'error'   => false,
            'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'),
        ]);
    }

    /**
     * Accept either a JSON-encoded string or an already-decoded array.
     * Returns the decoded list, or null when the input is missing/invalid.
     */
    private function decodeArrayPayload(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            return $decoded;
        }
        if (is_array($raw)) {
            return $raw;
        }
        return null;
    }

    /**
     * Coerce HH:MM or HH:MM:SS to HH:MM:SS. Returns null on invalid input.
     */
    private function normalizeTime(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $v)) {
            return $v . ':00';
        }
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/', $v)) {
            return $v;
        }
        return null;
    }

    private function toBoolInt(mixed $v): int
    {
        if (is_bool($v))    return $v ? 1 : 0;
        if (is_numeric($v)) return ((int) $v) === 1 ? 1 : 0;
        if (is_string($v)) {
            return in_array(strtolower(trim($v)), ['1', 'on', 'true', 'yes'], true) ? 1 : 0;
        }
        return 0;
    }

    /**
     * Query active bookings that fall within any of the requested leave shift windows.
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

        $shiftWindows = [];
        foreach ($rowsToInsert as $row) {
            $shiftWindows[$row['leave_date']][] = [
                'open'  => $row['opening_time'],
                'close' => $row['closing_time'],
            ];
        }

        $conflicts = [];
        foreach ($rows as $b) {
            $date  = (string) $b['date_of_service'];
            $start = (string) $b['starting_time'];
            foreach (($shiftWindows[$date] ?? []) as $window) {
                if ($start >= $window['open'] && $start < $window['close']) {
                    $conflicts[] = [
                        'order_id'      => (int) $b['id'],
                        'date'          => $date,
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
