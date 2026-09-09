<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ProviderShifts_model (table: provider_shifts)
 *
 * Multi-shift weekly schedule per provider. Replaces partner_timings.
 * Unique on (partner_id, day, shift_number).
 */
class ProviderShifts_model extends Model
{
    protected $table         = 'provider_shifts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'partner_id',
        'day',
        'shift_number',
        'opening_time',
        'closing_time',
        'is_open',
    ];

    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function tableExists(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /**
     * All shifts for a partner, ordered by day-of-week then shift_number.
     */
    public function getByPartner(int $partnerId): array
    {
        return $this->where('partner_id', $partnerId)
            ->orderBy('day', 'ASC')
            ->orderBy('shift_number', 'ASC')
            ->findAll();
    }

    /**
     * Shifts for a partner grouped by day name.
     */
    public function getByPartnerGroupedByDay(int $partnerId): array
    {
        $grouped = [];
        foreach ($this->getByPartner($partnerId) as $row) {
            $grouped[$row['day']][] = $row;
        }
        return $grouped;
    }

    /**
     * Shifts for a partner on a specific day, only-open by default.
     */
    public function getByPartnerAndDay(int $partnerId, string $day, bool $openOnly = true): array
    {
        $builder = $this->where('partner_id', $partnerId)
            ->where('day', $day)
            ->orderBy('opening_time', 'ASC');
        if ($openOnly) {
            $builder->where('is_open', 1);
        }
        return $builder->findAll();
    }

    public function deleteByPartner(int $partnerId): bool
    {
        return (bool) $this->where('partner_id', $partnerId)->delete();
    }

    public function deleteByPartnerAndDay(int $partnerId, string $day): bool
    {
        return (bool) $this->where('partner_id', $partnerId)
            ->where('day', $day)
            ->delete();
    }

    public function insertBatchForPartner(int $partnerId, array $shifts): int
    {
        if (empty($shifts)) return 0;
        foreach ($shifts as &$s) {
            $s['partner_id'] = $partnerId;
        }
        return (int) $this->insertBatch($shifts);
    }
}
