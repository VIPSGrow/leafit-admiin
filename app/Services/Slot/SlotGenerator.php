<?php

namespace App\Services\Slot;

/**
 * SlotGenerator — pure slot algorithm. NO database access.
 *
 * Inputs are plain arrays (shifts, bookings, locks, settings). Outputs are
 * plain arrays. Unit-testable in isolation.
 *
 * Time inputs accept "HH:MM" or "HH:MM:SS"; all internal math uses unix
 * timestamps composed with the slot's date.
 */
class SlotGenerator
{
    /**
     * Generate candidate slot start times for the given shifts.
     *
     * @param array<int,array<string,mixed>> $shifts        Each: {id, shift_number, opening_time, closing_time}
     * @param array<int,array<string,mixed>> $breaks        Reserved for phase 2 (currently no-op when empty).
     * @param int                            $intervalMinutes
     * @param int                            $serviceDurationMinutes
     *
     * @return array<int,array{time:string, shift_id:int, shift_number:int, opening_time:string, closing_time:string}>
     */
    public function generateCandidateSlots(
        array $shifts,
        array $breaks,
        int $intervalMinutes,
        int $serviceDurationMinutes
    ): array {
        $out = [];
        if ($intervalMinutes <= 0) {
            $intervalMinutes = 30;
        }
        if ($serviceDurationMinutes <= 0) {
            $serviceDurationMinutes = $intervalMinutes;
        }

        foreach ($shifts as $shift) {
            $open  = $this->toSeconds((string) $shift['opening_time']);
            $close = $this->toSeconds((string) $shift['closing_time']);
            if ($close <= $open) {
                continue;
            }

            for ($t = $open; $t + ($serviceDurationMinutes * 60) <= $close; $t += $intervalMinutes * 60) {
                $startStr = $this->secondsToTime($t);
                $endStr   = $this->secondsToTime($t + $serviceDurationMinutes * 60);

                if ($this->overlapsBreak($startStr, $endStr, $breaks)) {
                    continue;
                }

                $out[] = [
                    'time'         => $startStr,
                    'shift_id'     => (int) ($shift['id'] ?? 0),
                    'shift_number' => (int) ($shift['shift_number'] ?? 1),
                    'opening_time' => (string) $shift['opening_time'],
                    'closing_time' => (string) $shift['closing_time'],
                ];
            }
        }

        return $out;
    }

    /**
     * For each candidate, decide if a service of $serviceDurationMinutes
     * can start there given existing bookings + locks + settings.
     *
     * @param array<int,array> $candidates
     * @param array<int,array> $bookings  rows with starting_time, ending_time
     * @param array<int,array> $locks     rows with lock_start_time, lock_end_time
     * @param array $settings  must contain: slot_capacity, buffer_before, buffer_after,
     *                         min_advance_minutes, same_day_booking, max_advance_booking_days
     * @return array<int,array{time:string, is_available:int, remaining_capacity:int, shift_id:int}>
     */
    public function checkSlotAvailability(
        array $candidates,
        array $bookings,
        array $locks,
        array $shifts,
        array $settings,
        int $serviceDurationMinutes,
        string $date,
        string $nowDateTime
    ): array {
        $capacity     = max(1, (int) ($settings['slot_capacity'] ?? 1));
        $bufBefore    = max(0, (int) ($settings['buffer_before'] ?? 0));
        $bufAfter     = max(0, (int) ($settings['buffer_after'] ?? 0));
        $minAdvanceM  = max(0, (int) ($settings['min_advance_minutes'] ?? 0));
        $sameDayOk    = (int) ($settings['same_day_booking'] ?? 1) === 1;

        $today = date('Y-m-d', strtotime($nowDateTime));
        $isToday = ($date === $today);

        $result = [];
        foreach ($candidates as $cand) {
            $slotStart = $cand['time'];
            $slotEnd   = $this->secondsToTime(
                $this->toSeconds($slotStart) + $serviceDurationMinutes * 60
            );

            $available = 1;
            $remaining = $capacity;

            // a) same-day toggle
            if ($isToday && ! $sameDayOk) {
                $available = 0;
                $remaining = 0;
            }

            // b) past / min-advance cutoff
            if ($available === 1 && $this->isSlotInPast($date, $slotStart, $nowDateTime, $minAdvanceM)) {
                $available = 0;
                $remaining = 0;
            }

            // c) occupancy
            if ($available === 1) {
                $occupied = $this->countOccupancy($slotStart, $slotEnd, $bookings, $locks, $bufBefore, $bufAfter);
                if ($occupied >= $capacity) {
                    $available = 0;
                    $remaining = 0;
                } else {
                    $remaining = $capacity - $occupied;
                }
            }

            $result[] = [
                'time'               => $slotStart,
                'is_available'       => $available,
                'remaining_capacity' => $remaining,
                'shift_id'           => (int) ($cand['shift_id'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Count concurrent occupancy at slot range [slotStart, slotEnd).
     * Each booking / lock contributes 1 if its buffered range overlaps the slot.
     */
    public function countOccupancy(
        string $slotStart,
        string $slotEnd,
        array $bookings,
        array $locks,
        int $bufferBefore,
        int $bufferAfter
    ): int {
        $slotS = $this->toSeconds($slotStart);
        $slotE = $this->toSeconds($slotEnd);

        $count = 0;
        foreach ($bookings as $b) {
            $bs = $this->toSeconds((string) $b['starting_time']) - $bufferBefore * 60;
            $be = $this->toSeconds((string) $b['ending_time'])   + $bufferAfter  * 60;
            if ($bs < $slotE && $be > $slotS) {
                $count++;
            }
        }
        foreach ($locks as $l) {
            $ls = $this->toSeconds((string) $l['lock_start_time']) - $bufferBefore * 60;
            $le = $this->toSeconds((string) $l['lock_end_time'])   + $bufferAfter  * 60;
            if ($ls < $slotE && $le > $slotS) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * True if the slot is at/before now+minAdvanceMinutes for today.
     */
    public function isSlotInPast(string $date, string $time, string $nowDateTime, int $minAdvanceMinutes): bool
    {
        $slotTs = strtotime($date . ' ' . $time);
        $cutTs  = strtotime($nowDateTime) + $minAdvanceMinutes * 60;
        return $slotTs < $cutTs;
    }

    /**
     * Locate which shift a time belongs to (inclusive of opening, exclusive of closing).
     */
    public function findShiftForTime(string $time, array $shifts): ?array
    {
        $t = $this->toSeconds($time);
        foreach ($shifts as $s) {
            $open  = $this->toSeconds((string) $s['opening_time']);
            $close = $this->toSeconds((string) $s['closing_time']);
            if ($t >= $open && $t < $close) {
                return $s;
            }
        }
        return null;
    }

    /**
     * Check if a service started at $slotStart on $date can spill over.
     * Same-day cross-shift first, then next-day first shift.
     *
     * @param array $currentShift Active shift the slot belongs to.
     * @param array $allTodayShifts All effective shifts for today (sorted).
     * @param array $nextDayShifts  All effective shifts for next working day.
     *
     * @return array|null Continuation descriptor:
     *    {kind: 'cross_shift'|'multi_day', date, starting_time, ending_time, shift_id, shift_number}
     *    Returns null if no spillover possible.
     */
    public function checkSpillover(
        string $date,
        string $slotStart,
        int $serviceDurationMinutes,
        array $currentShift,
        array $allTodayShifts,
        array $sameDayBookings,
        ?string $nextDate,
        array $nextDayShifts,
        array $nextDayBookings,
        array $nextDayLocks,
        array $settings
    ): ?array {
        $shiftClose = $this->toSeconds((string) $currentShift['closing_time']);
        $slotS      = $this->toSeconds($slotStart);
        $needed     = $serviceDurationMinutes * 60;
        $consumedInCurrent = $shiftClose - $slotS;

        if ($consumedInCurrent >= $needed) {
            return null;
        }

        $remainingSec = $needed - $consumedInCurrent;

        $todayNext = $this->firstShiftAfter($currentShift, $allTodayShifts);
        if ($todayNext !== null) {
            $nextOpen  = $this->toSeconds((string) $todayNext['opening_time']);
            $nextClose = $this->toSeconds((string) $todayNext['closing_time']);
            if ($nextClose - $nextOpen >= $remainingSec) {
                $endSec = $nextOpen + $remainingSec;
                $contStart = $this->secondsToTime($nextOpen);
                $contEnd   = $this->secondsToTime($endSec);

                $occupied = $this->countOccupancy(
                    $contStart,
                    $contEnd,
                    $this->filterBookingsByShiftRange($sameDayBookings, $todayNext),
                    [],
                    (int) ($settings['buffer_before'] ?? 0),
                    (int) ($settings['buffer_after'] ?? 0)
                );
                $capacity = max(1, (int) ($settings['slot_capacity'] ?? 1));
                if ($occupied < $capacity) {
                    return [
                        'kind'          => 'cross_shift',
                        'date'          => $date,
                        'starting_time' => $contStart,
                        'ending_time'   => $contEnd,
                        'shift_id'      => (int) ($todayNext['id'] ?? 0),
                        'shift_number'  => (int) ($todayNext['shift_number'] ?? 1),
                    ];
                }
            }
        }

        if ($nextDate !== null && ! empty($nextDayShifts)) {
            $first = $nextDayShifts[0];
            $nOpen  = $this->toSeconds((string) $first['opening_time']);
            $nClose = $this->toSeconds((string) $first['closing_time']);
            if ($nClose - $nOpen >= $remainingSec) {
                $endSec = $nOpen + $remainingSec;
                $contStart = $this->secondsToTime($nOpen);
                $contEnd   = $this->secondsToTime($endSec);

                $occupied = $this->countOccupancy(
                    $contStart,
                    $contEnd,
                    $nextDayBookings,
                    $nextDayLocks,
                    (int) ($settings['buffer_before'] ?? 0),
                    (int) ($settings['buffer_after'] ?? 0)
                );
                $capacity = max(1, (int) ($settings['slot_capacity'] ?? 1));
                if ($occupied < $capacity) {
                    return [
                        'kind'          => 'multi_day',
                        'date'          => $nextDate,
                        'starting_time' => $contStart,
                        'ending_time'   => $contEnd,
                        'shift_id'      => (int) ($first['id'] ?? 0),
                        'shift_number'  => (int) ($first['shift_number'] ?? 1),
                    ];
                }
            }
        }

        return null;
    }

    /* --------------------------------- helpers --------------------------------- */

    private function firstShiftAfter(array $current, array $allShifts): ?array
    {
        $currClose = $this->toSeconds((string) $current['closing_time']);
        foreach ($allShifts as $s) {
            if ($this->toSeconds((string) $s['opening_time']) >= $currClose) {
                return $s;
            }
        }
        return null;
    }

    private function filterBookingsByShiftRange(array $bookings, array $shift): array
    {
        $open  = $this->toSeconds((string) $shift['opening_time']);
        $close = $this->toSeconds((string) $shift['closing_time']);
        return array_values(array_filter($bookings, function ($b) use ($open, $close) {
            $bs = $this->toSeconds((string) $b['starting_time']);
            $be = $this->toSeconds((string) $b['ending_time']);
            return $bs < $close && $be > $open;
        }));
    }

    private function overlapsBreak(string $start, string $end, array $breaks): bool
    {
        if (empty($breaks)) {
            return false;
        }
        $s = $this->toSeconds($start);
        $e = $this->toSeconds($end);
        foreach ($breaks as $b) {
            $bs = $this->toSeconds((string) ($b['break_start'] ?? ''));
            $be = $this->toSeconds((string) ($b['break_end']   ?? ''));
            if ($bs < $e && $be > $s) {
                return true;
            }
        }
        return false;
    }

    private function toSeconds(string $hms): int
    {
        $parts = explode(':', $hms);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);
        return $h * 3600 + $m * 60 + $s;
    }

    private function secondsToTime(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
