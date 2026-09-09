<?php

namespace App\Services\Slot;

/**
 * SlotService — public API. The only class controllers should talk to.
 *
 * Responsibilities:
 *   - Orchestrate ScheduleRepository (reads), SlotGenerator (math), LockManager (writes).
 *   - Apply provider-level booking-window rules (max_advance_booking_days, same_day_booking).
 *   - Translate algorithm output into a consistent response envelope.
 *
 * All responses use the same shape:
 *   { error: bool, message: string, data?: array }
 */
class SlotService
{
    private ScheduleRepository $repo;
    private SlotGenerator $generator;
    private LockManager $locks;

    public function __construct(
        ?ScheduleRepository $repo = null,
        ?SlotGenerator $generator = null,
        ?LockManager $locks = null
    ) {
        $this->repo      = $repo ?? new ScheduleRepository();
        $this->generator = $generator ?? new SlotGenerator();
        $this->locks     = $locks ?? new LockManager();
    }

    /**
     * Available slots for a partner on a date.
     *
     * @return array{
     *   error: bool, message: string,
     *   data?: array{all_slots: array<int,array{time:string, is_available:int, remaining_capacity:int, shift_id:int, message?:string}>}
     * }
     */
    public function getAvailableSlots(int $partnerId, string $date, int $serviceDurationMinutes, ?int $userId = null): array
    {
        $settings = $this->repo->getSettings($partnerId);

        // Booking window
        $windowError = $this->checkBookingWindow($date, $settings);
        if ($windowError !== null) {
            return ['error' => true, 'message' => $windowError];
        }

        $effective = $this->repo->getEffectiveShifts($partnerId, $date);
        if ($effective['full_day_leave']) {
            return [
                'error'   => false,
                'message' => labels(PROVIDER_IS_ON_LEAVE_LBL, 'Provider is on leave'),
                'data'    => ['all_slots' => []],
            ];
        }
        $shifts = $effective['shifts'];
        if (empty($shifts)) {
            return [
                'error'   => true,
                'message' => labels(PROVIDER_IS_CLOSED, 'Provider is closed!'),
            ];
        }

        $interval = max(5, (int) $settings['slot_interval']);
        // duration=0 means "calendar self-view" (provider, no specific service).
        // Use slot_interval so the occupancy window matches the minimum booking unit
        // and buffer bleed-back from adjacent bookings is applied correctly.
        $duration = $serviceDurationMinutes > 0 ? $serviceDurationMinutes : $interval;

        $candidates = $this->generator->generateCandidateSlots($shifts, [], $interval, $duration);

        $bookings = $this->repo->getBookings($partnerId, $date);
        $locks    = $this->repo->getActiveLocks($partnerId, $date, $userId);

        $now = date('Y-m-d H:i:s');
        $checked = $this->generator->checkSlotAvailability(
            $candidates,
            $bookings,
            $locks,
            $shifts,
            $settings,
            $duration,
            $date,
            $now
        );

        // Spillover annotation — when a slot needs cross-shift/multi-day to fit,
        // mark it with a "multiple days" message so the UI can show a hint.
        $checked = $this->annotateSpillover(
            $checked,
            $candidates,
            $partnerId,
            $date,
            $duration,
            $shifts,
            $settings
        );

        return [
            'error'   => false,
            'message' => labels(FOUND_TIME_SLOTS, 'Found Time slots'),
            'data'    => ['all_slots' => $checked],
        ];
    }

    /**
     * Validate that a slot is bookable + create a TTL lock.
     *
     * @return array{
     *   error: bool, message: string,
     *   data?: array{lock_id:int, expires_in:int, shift_id:int, ending_time:string, continuation?:array|null}
     * }
     */
    public function validateAndLockSlot(int $partnerId, string $date, string $time, int $serviceDurationMinutes, int $userId): array
    {
        $time = $this->normalizeTime($time);
        if ($time === null) {
            return ['error' => true, 'message' => labels(INVALID_TIME_FORMAT, 'Invalid time format')];
        }

        $settings = $this->repo->getSettings($partnerId);

        $windowError = $this->checkBookingWindow($date, $settings);
        if ($windowError !== null) {
            return ['error' => true, 'message' => $windowError];
        }

        $effective = $this->repo->getEffectiveShifts($partnerId, $date);
        if ($effective['full_day_leave']) {
            return ['error' => true, 'message' => labels(PROVIDER_IS_ON_LEAVE_LBL, 'Provider is on leave')];
        }
        $shifts = $effective['shifts'];
        if (empty($shifts)) {
            return ['error' => true, 'message' => labels(PROVIDER_IS_CLOSED, 'Provider is closed!')];
        }

        $shift = $this->generator->findShiftForTime($time, $shifts);
        if ($shift === null) {
            return ['error' => true, 'message' => labels(PROVIDER_IS_CLOSED_AT_THIS_TIME, 'Provider is closed at this time')];
        }

        $duration = max(1, $serviceDurationMinutes);
        $endTime  = $this->addMinutes($time, $duration);

        // Min-advance + same-day rules
        $now = date('Y-m-d H:i:s');
        if ($this->generator->isSlotInPast($date, $time, $now, (int) $settings['min_advance_minutes'])) {
            return ['error' => true, 'message' => labels(SLOT_IS_IN_THE_PAST, 'Selected slot is no longer bookable')];
        }
        if ($date === date('Y-m-d') && (int) $settings['same_day_booking'] !== 1) {
            return ['error' => true, 'message' => labels('provider_is_not_available', 'Provider is not available')];
        }

        // Duration fit + spillover
        $continuation = null;
        if (strtotime($endTime) > strtotime($shift['closing_time'])) {
            $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
            $nextEffective = $this->repo->getEffectiveShifts($partnerId, $nextDate);
            $nextDayBookings = $this->repo->getBookings($partnerId, $nextDate);
            $nextDayLocks    = $this->repo->getActiveLocks($partnerId, $nextDate, $userId);
            $sameDayBookings = $this->repo->getBookings($partnerId, $date);

            $continuation = $this->generator->checkSpillover(
                $date,
                $time,
                $duration,
                $shift,
                $shifts,
                $sameDayBookings,
                $nextEffective['full_day_leave'] ? null : $nextDate,
                $nextEffective['shifts'],
                $nextDayBookings,
                $nextDayLocks,
                $settings
            );
            if ($continuation === null) {
                return ['error' => true, 'message' => labels(SERVICE_DURATION_EXCEEDS_AVAILABLE_TIME, 'Service duration exceeds available time')];
            }
            // For cross_shift continuation we truncate the primary slot to current shift's closing_time.
            if ($continuation['kind'] === 'cross_shift' || $continuation['kind'] === 'multi_day') {
                $endTime = $shift['closing_time'];
            }
        }

        // Capacity check + atomic lock
        try {
            $lock = $this->locks->createLock(
                $partnerId,
                $date,
                $time,
                $endTime,
                $userId,
                (int) $settings['slot_capacity'],
                (int) $settings['buffer_before'],
                (int) $settings['buffer_after']
            );
        } catch (SlotFullException $e) {
            return ['error' => true, 'message' => labels(SLOT_IS_FULL, 'Slot is full')];
        }

        return [
            'error'   => false,
            'message' => labels(SLOT_IS_AVAILABLE_AT_THIS_TIME, 'Slot is available at this time'),
            'data'    => [
                'lock_id'       => $lock['lock_id'],
                'expires_in'    => $lock['expires_in'],
                'shift_id'      => (int) ($shift['id'] ?? 0),
                'starting_time' => $time,
                'ending_time'   => $endTime,
                'continuation'  => $continuation,
            ],
        ];
    }

    /**
     * Validate a lock, return booking shape, delete lock atomically.
     *
     * @return array{
     *   error: bool, message: string,
     *   data?: array{starting_time:string, ending_time:string, duration:int, shift_id:int, continuation:?array}
     * }
     */
    public function confirmBooking(int $lockId, int $partnerId, string $date, string $time, int $serviceDurationMinutes, int $userId): array
    {
        $lock = $this->locks->getOwnedActive($lockId, $userId);
        if ($lock === null) {
            return ['error' => true, 'message' => labels(SLOT_LOCK_EXPIRED_OR_INVALID, 'Slot reservation expired. Please reselect.')];
        }
        if ((int) $lock['partner_id'] !== $partnerId || (string) $lock['lock_date'] !== $date) {
            return ['error' => true, 'message' => labels(SLOT_LOCK_MISMATCH, 'Slot reservation does not match request')];
        }

        $time = $this->normalizeTime($time) ?? $time;

        // Build response from lock data (authoritative).
        $startingTime = (string) $lock['lock_start_time'];
        $endingTime   = (string) $lock['lock_end_time'];
        $shiftId      = null;

        $effective = $this->repo->getEffectiveShifts($partnerId, $date);
        $shifts    = $effective['shifts'];
        $shift     = $this->generator->findShiftForTime($startingTime, $shifts);
        if ($shift !== null) {
            $shiftId = (int) $shift['id'];
        }

        // Re-derive continuation (in case service duration triggers spillover).
        $duration = max(1, $serviceDurationMinutes);
        $continuation = null;
        if ($shift !== null && strtotime($this->addMinutes($startingTime, $duration)) > strtotime($shift['closing_time'])) {
            $settings = $this->repo->getSettings($partnerId);
            $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
            $nextEffective = $this->repo->getEffectiveShifts($partnerId, $nextDate);
            $continuation = $this->generator->checkSpillover(
                $date,
                $startingTime,
                $duration,
                $shift,
                $shifts,
                $this->repo->getBookings($partnerId, $date),
                $nextEffective['full_day_leave'] ? null : $nextDate,
                $nextEffective['shifts'],
                $this->repo->getBookings($partnerId, $nextDate),
                $this->repo->getActiveLocks($partnerId, $nextDate, $userId),
                $settings
            );
            if ($continuation !== null) {
                $endingTime = (string) $shift['closing_time'];
            }
        }

        // Consume the lock — booking is about to be inserted by the caller.
        $this->locks->consumeLock($lockId, $userId);

        return [
            'error'   => false,
            'message' => labels(SLOT_CONFIRMED, 'Slot confirmed'),
            'data'    => [
                'starting_time' => $startingTime,
                'ending_time'   => $endingTime,
                'duration'      => $duration,
                'shift_id'      => $shiftId,
                'continuation'  => $continuation,
            ],
        ];
    }

    public function releaseLock(int $lockId, int $userId): array
    {
        $ok = $this->locks->releaseLock($lockId, $userId);
        return [
            'error'   => ! $ok,
            'message' => $ok
                ? labels(LOCK_RELEASED, 'Lock released')
                : labels(LOCK_NOT_FOUND_OR_EXPIRED, 'Lock not found or already expired'),
        ];
    }

    /* ------------------------------- helpers ------------------------------- */

    private function checkBookingWindow(string $date, array $settings): ?string
    {
        $today    = date('Y-m-d');
        $maxDays  = (int) ($settings['max_advance_booking_days'] ?? 30);
        $sameDay  = (int) ($settings['same_day_booking'] ?? 1) === 1;

        if ($date < $today) {
            return labels(PLEASE_SELECT_UPCOMING_DATE, 'Please select an upcoming date');
        }
        if ($date === $today && ! $sameDay) {
            return labels('provider_is_not_available', 'Provider is not available');
        }

        $maxDate = date('Y-m-d', strtotime("+{$maxDays} day"));
        if ($date > $maxDate) {
            return labels(YOU_CAN_NOT_CHOOSE_DATE_BEYOND_AVAILABLE_BOOKING_DAYS_WHICH_IS, "You can not choose date beyond available booking days which is ")
                . $maxDays . labels(DAYS, ' days');
        }
        return null;
    }

    private function annotateSpillover(
        array $checked,
        array $candidates,
        int $partnerId,
        string $date,
        int $duration,
        array $todayShifts,
        array $settings
    ): array {
        $byTime = [];
        foreach ($candidates as $c) {
            $byTime[$c['time']] = $c;
        }

        $nextDate      = date('Y-m-d', strtotime($date . ' +1 day'));
        $nextEffective = $this->repo->getEffectiveShifts($partnerId, $nextDate);
        $nextDayBookings = $this->repo->getBookings($partnerId, $nextDate);
        $sameDayBookings = $this->repo->getBookings($partnerId, $date);

        foreach ($checked as &$row) {
            $cand = $byTime[$row['time']] ?? null;
            if ($cand === null) {
                continue;
            }
            $endTs = strtotime($this->addMinutes($row['time'], $duration));
            $shiftClose = strtotime((string) $cand['closing_time']);
            if ($endTs <= $shiftClose) {
                continue;
            }
            $shift = [
                'id'           => $cand['shift_id'],
                'shift_number' => $cand['shift_number'],
                'opening_time' => $cand['opening_time'],
                'closing_time' => $cand['closing_time'],
            ];
            $continuation = $this->generator->checkSpillover(
                $date,
                $row['time'],
                $duration,
                $shift,
                $todayShifts,
                $sameDayBookings,
                $nextEffective['full_day_leave'] ? null : $nextDate,
                $nextEffective['shifts'],
                $nextDayBookings,
                [],
                $settings
            );
            if ($continuation === null) {
                $row['is_available'] = 0;
                $row['remaining_capacity'] = 0;
            } else {
                $row['message'] = labels(ORDER_SCHEDULED_FOR_THE_MULTIPLE_DAYS, 'Order scheduled for the multiple days');
                $row['continuation'] = $continuation;
            }
        }
        unset($row);

        return $checked;
    }

    private function normalizeTime(string $time): ?string
    {
        $t = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})-(\d{2})$/', $t, $m)) {
            $t = sprintf('%02d:%s:%s', (int) $m[1], $m[2], $m[3]);
        } elseif (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $t, $m)) {
            $t = sprintf('%02d:%s:%s', (int) $m[1], $m[2], $m[3]);
        } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $t = sprintf('%02d:%s:00', (int) $m[1], $m[2]);
        } else {
            return null;
        }
        return $t;
    }

    private function addMinutes(string $hms, int $minutes): string
    {
        $ts = strtotime('2000-01-01 ' . $hms);
        $ts += $minutes * 60;
        return date('H:i:s', $ts);
    }
}
