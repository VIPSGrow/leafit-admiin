<?php

namespace App\Services\provider;

use App\Models\ProviderSlotSettings_model;

/**
 * SlotSettingsService
 *
 * Business-logic layer over ProviderSlotSettings_model.
 * Responsibilities:
 *   - Provide defaults for missing rows (no DB write side-effect on read).
 *   - Sanitize/clamp/coerce raw form input before persistence.
 *   - Map UI field names to model column names.
 *   - Convert min_advance value+unit to minutes/hours for algorithm callers.
 *
 * All DB I/O delegated to ProviderSlotSettings_model.
 *
 * Reused across:
 *   - admin Partners controller (insert/update/duplicate)
 *   - partner self-service controller (when added)
 *   - provider register/update API
 */
class SlotSettingsService
{
    /** Slot interval is free-form minutes: a multiple of 5 within [5, 240]. */
    private const MIN_INTERVAL  = 5;
    private const MAX_INTERVAL  = 240;
    private const INTERVAL_STEP = 5;

    private const VALID_UNITS = ['minutes', 'hours', 'days'];

    private ProviderSlotSettings_model $model;

    public function __construct(?ProviderSlotSettings_model $model = null)
    {
        $this->model = $model ?? new ProviderSlotSettings_model();
    }

    /**
     * Defaults match the migration's column defaults.
     */
    public function getDefaults(): array
    {
        return [
            'slot_interval'             => 30,
            'allow_multiple_bookings'   => 0,
            'slot_capacity'             => 1,
            'min_advance_booking_value' => 1,
            'min_advance_booking_unit'  => 'hours',
            'max_advance_booking_days'  => 30,
            'same_day_booking'          => 1,
            'buffer_before'             => 0,
            'buffer_after'              => 0,
        ];
    }

    /**
     * Fetch settings for a partner; returns defaults if no row exists.
     */
    public function find(int $partnerId): array
    {
        $row = $this->model->findByPartner($partnerId);
        if ($row === null) {
            return array_merge(['partner_id' => $partnerId], $this->getDefaults());
        }
        return $row;
    }

    /**
     * Upsert settings for a partner. Returns the persisted row.
     *
     * @param array $input  Raw input (typically from $request->getPost()).
     */
    public function save(int $partnerId, array $input): array
    {
        return $this->model->upsertForPartner($partnerId, $this->sanitize($input));
    }

    /**
     * Pluck the relevant subset out of a wider POST payload, matching the
     * field names used in the add_partner / partner profile forms.
     */
    public function extractFromPost(array $post): array
    {
        return [
            'slot_interval'             => $post['slot_interval']             ?? null,
            'allow_multiple_bookings'   => $post['allow_multiple_bookings']   ?? 0,
            'slot_capacity'             => $post['slot_capacity']             ?? null,
            'min_advance_booking_value' => $post['min_advance_booking']       ?? null,
            'min_advance_booking_unit'  => $post['min_advance_booking_unit']  ?? null,
            'max_advance_booking_days'  => $post['advance_booking_days']      ?? null,
            'same_day_booking'          => $post['same_day_booking']          ?? 0,
            'buffer_before'             => $post['buffer_before']             ?? null,
            'buffer_after'              => $post['buffer_after']              ?? null,
        ];
    }

    /**
     * Convert min_advance pair to total minutes (for slot algorithm).
     */
    public function toMinutes(int $value, string $unit): int
    {
        return match ($unit) {
            'minutes' => $value,
            'hours'   => $value * 60,
            'days'    => $value * 60 * 24,
            default   => $value * 60,
        };
    }

    public function toHours(int $value, string $unit): int
    {
        return (int) ceil($this->toMinutes($value, $unit) / 60);
    }

    /**
     * Coerce + clamp + default any partial input. Falls back to defaults
     * for missing/invalid keys so a partial save never produces garbage.
     */
    private function sanitize(array $input): array
    {
        $defaults = $this->getDefaults();

        // Slot interval: integer minutes, multiple of 5, within [5, 240].
        // Invalid input falls back to the default — JS already blocks bad
        // submits, this guards tampered requests and bulk imports.
        $interval = (int) ($input['slot_interval'] ?? $defaults['slot_interval']);
        if ($interval < self::MIN_INTERVAL
            || $interval > self::MAX_INTERVAL
            || $interval % self::INTERVAL_STEP !== 0) {
            $interval = $defaults['slot_interval'];
        }

        $allowMulti = $this->toBool($input['allow_multiple_bookings'] ?? $defaults['allow_multiple_bookings']);

        $capacity = (int) ($input['slot_capacity'] ?? $defaults['slot_capacity']);
        if ($capacity < 1)   { $capacity = 1; }
        if ($capacity > 100) { $capacity = 100; }
        if (! $allowMulti)   { $capacity = 1; } // capacity meaningless when toggle off

        $minValue = (int) ($input['min_advance_booking_value'] ?? $defaults['min_advance_booking_value']);
        if ($minValue < 0) { $minValue = 0; }

        $minUnit = (string) ($input['min_advance_booking_unit'] ?? $defaults['min_advance_booking_unit']);
        if (! in_array($minUnit, self::VALID_UNITS, true)) {
            $minUnit = $defaults['min_advance_booking_unit'];
        }

        $maxDays = (int) ($input['max_advance_booking_days'] ?? $defaults['max_advance_booking_days']);
        if ($maxDays < 1)    { $maxDays = 1; }
        if ($maxDays > 3650) { $maxDays = 3650; }

        $sameDay = $this->toBool($input['same_day_booking'] ?? $defaults['same_day_booking']);

        $bufBefore = (int) ($input['buffer_before'] ?? $defaults['buffer_before']);
        if ($bufBefore < 0)    { $bufBefore = 0; }
        if ($bufBefore > 1440) { $bufBefore = 1440; }

        $bufAfter = (int) ($input['buffer_after'] ?? $defaults['buffer_after']);
        if ($bufAfter < 0)    { $bufAfter = 0; }
        if ($bufAfter > 1440) { $bufAfter = 1440; }

        return [
            'slot_interval'             => $interval,
            'allow_multiple_bookings'   => $allowMulti ? 1 : 0,
            'slot_capacity'             => $capacity,
            'min_advance_booking_value' => $minValue,
            'min_advance_booking_unit'  => $minUnit,
            'max_advance_booking_days'  => $maxDays,
            'same_day_booking'          => $sameDay ? 1 : 0,
            'buffer_before'             => $bufBefore,
            'buffer_after'              => $bufAfter,
        ];
    }

    private function toBool(mixed $v): bool
    {
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return ((int) $v) === 1;
        if (is_string($v)) {
            $v = strtolower(trim($v));
            return in_array($v, ['1', 'on', 'true', 'yes'], true);
        }
        return false;
    }
}
