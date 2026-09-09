<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ProviderSlotSettings_model (table: provider_slot_settings)
 *
 * Per-provider slot generation + booking-window configuration.
 * One row per partner_id (UNIQUE constraint).
 */
class ProviderSlotSettings_model extends Model
{
    protected $table         = 'provider_slot_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'partner_id',
        'slot_interval',
        'allow_multiple_bookings',
        'slot_capacity',
        'min_advance_booking_value',
        'min_advance_booking_unit',
        'max_advance_booking_days',
        'same_day_booking',
        'buffer_before',
        'buffer_after',
    ];

    public function tableExists(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /**
     * Get settings row for a partner, or null if missing.
     */
    public function findByPartner(int $partnerId): ?array
    {
        $row = $this->where('partner_id', $partnerId)->first();
        return $row ?: null;
    }

    /**
     * Upsert by partner_id. Returns the persisted row.
     */
    public function upsertForPartner(int $partnerId, array $data): array
    {
        $data['partner_id'] = $partnerId;

        $existing = $this->where('partner_id', $partnerId)->first();
        if ($existing) {
            $this->where('partner_id', $partnerId)->set($data)->update();
        } else {
            $this->insert($data);
        }

        return $this->findByPartner($partnerId) ?? [];
    }

    public function deleteByPartner(int $partnerId): bool
    {
        return (bool) $this->where('partner_id', $partnerId)->delete();
    }
}
