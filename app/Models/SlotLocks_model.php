<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SlotLocks_model (table: slot_locks)
 *
 * Short-lived TTL locks held while a customer is checking out a slot.
 * Correctness relies on `expires_at > NOW()` filter in reads; expired rows are
 * swept lazily inside LockManager::createLock for the same partner+date scope.
 */
class SlotLocks_model extends Model
{
    protected $table         = 'slot_locks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'partner_id',
        'lock_date',
        'lock_start_time',
        'lock_end_time',
        'user_id',
        'expires_at',
        'created_at',
    ];

    public function tableExists(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /**
     * Active (non-expired) locks for a partner+date. Optionally exclude one user
     * so a customer doesn't block their own existing lock when re-checking.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getActive(int $partnerId, string $date, ?int $excludeUserId = null): array
    {
        $builder = $this->where('partner_id', $partnerId)
            ->where('lock_date', $date)
            ->where('expires_at >', date('Y-m-d H:i:s'));

        if ($excludeUserId !== null) {
            $builder->where('user_id !=', $excludeUserId);
        }

        return $builder->findAll();
    }

    /**
     * Delete expired rows scoped to a partner+date. Cheap, runs inside
     * LockManager::createLock before the FOR UPDATE re-check.
     */
    public function deleteExpiredForPartnerDate(int $partnerId, string $date): int
    {
        $this->where('partner_id', $partnerId)
            ->where('lock_date', $date)
            ->where('expires_at <', date('Y-m-d H:i:s'))
            ->delete();
        return (int) $this->db->affectedRows();
    }

    public function findOwnedById(int $lockId, int $userId): ?array
    {
        $row = $this->where('id', $lockId)
            ->where('user_id', $userId)
            ->first();
        return $row ?: null;
    }

    public function deleteById(int $lockId): bool
    {
        return (bool) $this->delete($lockId);
    }
}
