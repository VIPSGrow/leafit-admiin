<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ProviderLeaves_model (table: provider_leaves)
 *
 * Shift-based provider leaves. Each row marks a single shift of a single date as on-leave.
 * Full-day leave = all shifts of that date marked. Unique on (partner_id, leave_date, shift_number).
 */
class ProviderLeaves_model extends Model
{
    protected $table         = 'provider_leaves';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'partner_id',
        'leave_date',
        'day',
        'shift_number',
        'opening_time',
        'closing_time',
    ];

    public function tableExists(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function getByPartner(int $partnerId): array
    {
        return $this->where('partner_id', $partnerId)
            ->orderBy('leave_date', 'ASC')
            ->orderBy('shift_number', 'ASC')
            ->findAll();
    }

    public function getByPartnerInRange(int $partnerId, string $fromDate, string $toDate): array
    {
        return $this->where('partner_id', $partnerId)
            ->where('leave_date >=', $fromDate)
            ->where('leave_date <=', $toDate)
            ->orderBy('leave_date', 'ASC')
            ->orderBy('shift_number', 'ASC')
            ->findAll();
    }

    public function deleteByPartnerInRange(int $partnerId, string $fromDate, string $toDate): bool
    {
        return (bool) $this->where('partner_id', $partnerId)
            ->where('leave_date >=', $fromDate)
            ->where('leave_date <=', $toDate)
            ->delete();
    }

    public function deleteByPartner(int $partnerId): bool
    {
        return (bool) $this->where('partner_id', $partnerId)->delete();
    }

    public function deleteByPartnerOnDate(int $partnerId, string $leaveDate): bool
    {
        return (bool) $this->where('partner_id', $partnerId)
            ->where('leave_date', $leaveDate)
            ->delete();
    }

    public function insertBatchForPartner(int $partnerId, array $rows): int
    {
        if (empty($rows)) return 0;
        foreach ($rows as &$r) {
            $r['partner_id'] = $partnerId;
        }
        return (int) $this->insertBatch($rows);
    }
}
