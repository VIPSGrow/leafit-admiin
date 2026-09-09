<?php

namespace App\Services\Slot;

use App\Models\ProviderLeaves_model;
use App\Models\ProviderShifts_model;
use App\Models\SlotLocks_model;
use App\Services\Provider\SlotSettingsService;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * ScheduleRepository
 *
 * Single source of truth for "what's this provider's schedule on date X".
 * Wraps the three Provider* models, SlotSettingsService, and direct queries
 * against orders + slot_locks. Returns plain arrays — never DB result objects.
 */
class ScheduleRepository
{
    /** Active order statuses considered to occupy a slot. */
    public const ACTIVE_ORDER_STATUSES = [
        'awaiting',
        'confirmed',
        'rescheduled',
        'started',
    ];

    private const DAYS = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    private ProviderShifts_model $shiftsModel;
    private ProviderLeaves_model $leavesModel;
    private SlotLocks_model $locksModel;
    private SlotSettingsService $settingsService;
    private BaseConnection $db;

    public function __construct(
        ?ProviderShifts_model $shiftsModel = null,
        ?ProviderLeaves_model $leavesModel = null,
        ?SlotLocks_model $locksModel = null,
        ?SlotSettingsService $settingsService = null,
        ?BaseConnection $db = null
    ) {
        $this->shiftsModel     = $shiftsModel ?? new ProviderShifts_model();
        $this->leavesModel     = $leavesModel ?? new ProviderLeaves_model();
        $this->locksModel      = $locksModel ?? new SlotLocks_model();
        $this->settingsService = $settingsService ?? new SlotSettingsService();
        $this->db              = $db ?? Database::connect();
    }

    /**
     * Open shifts for a partner on a given weekday name (lowercase).
     * Already ordered by opening_time inside the model.
     */
    public function getShifts(int $partnerId, string $dayName): array
    {
        return $this->shiftsModel->getByPartnerAndDay($partnerId, $dayName, true);
    }

    /**
     * Effective shifts on a specific date = open shifts minus those on leave.
     *
     * @return array{shifts: array<int,array<string,mixed>>, full_day_leave: bool}
     */
    public function getEffectiveShifts(int $partnerId, string $date): array
    {
        $dayName = $this->dayNameForDate($date);
        $shifts  = $this->getShifts($partnerId, $dayName);

        if (empty($shifts)) {
            return ['shifts' => [], 'full_day_leave' => false];
        }

        $leaveShiftNumbers = array_map(
            static fn($r) => (int) $r['shift_number'],
            $this->getLeaves($partnerId, $date)
        );

        if (! empty($leaveShiftNumbers) && count($leaveShiftNumbers) >= count($shifts)) {
            return ['shifts' => [], 'full_day_leave' => true];
        }

        $filtered = array_values(array_filter(
            $shifts,
            static fn($s) => ! in_array((int) $s['shift_number'], $leaveShiftNumbers, true)
        ));

        return [
            'shifts'         => $filtered,
            'full_day_leave' => empty($filtered),
        ];
    }

    /**
     * Slot settings for a partner. Falls back to defaults when no row exists.
     */
    public function getSettings(int $partnerId): array
    {
        $row = $this->settingsService->find($partnerId);

        $minAdvanceMinutes = $this->settingsService->toMinutes(
            (int) ($row['min_advance_booking_value'] ?? 0),
            (string) ($row['min_advance_booking_unit'] ?? 'hours')
        );

        $allowMulti = (int) ($row['allow_multiple_bookings'] ?? 0) === 1;
        $capacity   = (int) ($row['slot_capacity'] ?? 1);
        if (! $allowMulti || $capacity < 1) {
            $capacity = 1;
        }

        return [
            'slot_interval'             => (int) ($row['slot_interval'] ?? 30),
            'slot_capacity'             => $capacity,
            'allow_multiple_bookings'   => $allowMulti ? 1 : 0,
            'buffer_before'             => (int) ($row['buffer_before'] ?? 0),
            'buffer_after'              => (int) ($row['buffer_after'] ?? 0),
            'min_advance_minutes'       => $minAdvanceMinutes,
            'max_advance_booking_days'  => (int) ($row['max_advance_booking_days'] ?? 30),
            'same_day_booking'          => (int) ($row['same_day_booking'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    /**
     * Leaves rows for a single date.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getLeaves(int $partnerId, string $date): array
    {
        if (! $this->leavesModel->tableExists()) {
            return [];
        }

        return $this->leavesModel
            ->where('partner_id', $partnerId)
            ->where('leave_date', $date)
            ->findAll();
    }

    /**
     * Active bookings for a partner on a date.
     *
     * @return array<int,array{starting_time:string, ending_time:string, id:int}>
     */
    public function getBookings(int $partnerId, string $date, ?int $excludeOrderId = null): array
    {
        $builder = $this->db->table('orders')
            ->select('id, starting_time, ending_time')
            ->where('partner_id', $partnerId)
            ->where('date_of_service', $date)
            ->whereIn('status', self::ACTIVE_ORDER_STATUSES);

        if ($excludeOrderId !== null) {
            $builder->where('id !=', $excludeOrderId);
        }

        return $builder->orderBy('starting_time', 'ASC')->get()->getResultArray();
    }

    /**
     * Active (non-expired) locks for partner+date.
     */
    public function getActiveLocks(int $partnerId, string $date, ?int $excludeUserId = null): array
    {
        if (! $this->locksModel->tableExists()) {
            return [];
        }
        return $this->locksModel->getActive($partnerId, $date, $excludeUserId);
    }

    public function dayNameForDate(string $date): string
    {
        $w = (int) date('w', strtotime($date));
        return self::DAYS[$w] ?? 'monday';
    }

    public function db(): BaseConnection
    {
        return $this->db;
    }
}
