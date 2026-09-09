<?php

namespace App\Services\Slot;

use App\Models\SlotLocks_model;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * LockManager
 *
 * Transactional CRUD for slot_locks. The createLock path is the only place that
 * does SELECT ... FOR UPDATE — it doubles as the lazy sweep point for expired
 * locks scoped to the (partner_id, lock_date) being touched.
 *
 * No cron. Reads (here and in ScheduleRepository) always filter expires_at > NOW(),
 * so stale rows never affect correctness; the inline sweep is purely housekeeping.
 */
class LockManager
{
    /** Default lock TTL in seconds (10 minutes). */
    public const DEFAULT_TTL_SECONDS = 600;

    private SlotLocks_model $model;
    private BaseConnection $db;

    public function __construct(?SlotLocks_model $model = null, ?BaseConnection $db = null)
    {
        $this->model = $model ?? new SlotLocks_model();
        $this->db    = $db ?? Database::connect();
    }

    /**
     * Atomically create a lock on a slot range, re-checking capacity inside
     * the transaction with FOR UPDATE. Sweeps expired rows for the same
     * partner+date before re-checking — free, scoped, no global job.
     *
     * @throws SlotFullException When capacity is exhausted by the time the
     *                           transaction commits.
     */
    public function createLock(
        int $partnerId,
        string $date,
        string $startTime,
        string $endTime,
        int $userId,
        int $capacity,
        int $bufferBefore = 0,
        int $bufferAfter = 0,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ): array {
        $capacity = max(1, $capacity);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $this->db->transBegin();
        try {
            // 1) Lazy sweep: drop expired rows for this partner+date.
            $this->model->deleteExpiredForPartnerDate($partnerId, $date);

            // 1b) Release any prior active lock this user holds that overlaps the new slot.
            //     Prevents orphaned locks (e.g. check_available_slot followed by place_order
            //     without lock_id) from being counted against other users' capacity.
            $this->db->query(
                'DELETE FROM slot_locks
                 WHERE partner_id = ?
                   AND lock_date   = ?
                   AND user_id     = ?
                   AND expires_at  > NOW()
                   AND lock_start_time < ?
                   AND lock_end_time   > ?',
                [$partnerId, $date, $userId, $endTime, $startTime]
            );

            // 2) Lock active rows that could overlap this slot (raw FOR UPDATE — no CI4 builder shortcut).
            $lockRows = $this->db->query(
                'SELECT id, lock_start_time, lock_end_time, user_id
                 FROM slot_locks
                 WHERE partner_id = ?
                   AND lock_date = ?
                   AND expires_at > NOW()
                   AND user_id != ?
                 FOR UPDATE',
                [$partnerId, $date, $userId]
            )->getResultArray();

            // 3) Pull active bookings for capacity re-check (also FOR UPDATE so
            //    a concurrent INSERT INTO orders sees a consistent view).
            $bookingRows = $this->db->query(
                'SELECT id, starting_time, ending_time
                 FROM orders
                 WHERE partner_id = ?
                   AND date_of_service = ?
                   AND status IN ("awaiting","confirmed","rescheduled","started")
                 FOR UPDATE',
                [$partnerId, $date]
            )->getResultArray();

            $occupied = $this->overlapCount($startTime, $endTime, $bookingRows, $lockRows, $bufferBefore, $bufferAfter);
            if ($occupied >= $capacity) {
                $this->db->transRollback();
                throw new SlotFullException('Slot is full');
            }

            $this->db->table('slot_locks')->insert([
                'partner_id'      => $partnerId,
                'lock_date'       => $date,
                'lock_start_time' => $startTime,
                'lock_end_time'   => $endTime,
                'user_id'         => $userId,
                'expires_at'      => $expiresAt,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            $lockId = (int) $this->db->insertID();

            $this->db->transCommit();

            return [
                'lock_id'    => $lockId,
                'expires_at' => $expiresAt,
                'expires_in' => $ttlSeconds,
            ];
        } catch (SlotFullException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
            } else {
                $this->db->transRollback();
            }
            throw $e;
        }
    }

    /**
     * Validate that a lock exists, belongs to user, and is not expired.
     * Returns the lock row. Does NOT delete — callers consume separately.
     */
    public function getOwnedActive(int $lockId, int $userId): ?array
    {
        $row = $this->model->findOwnedById($lockId, $userId);
        if ($row === null) {
            return null;
        }
        if (strtotime((string) $row['expires_at']) <= time()) {
            return null;
        }
        return $row;
    }

    /**
     * Delete a lock that belongs to the user. Called on explicit release
     * or after order confirmation.
     */
    public function releaseLock(int $lockId, int $userId): bool
    {
        $row = $this->model->findOwnedById($lockId, $userId);
        if ($row === null) {
            return false;
        }
        return $this->model->deleteById($lockId);
    }

    /**
     * Validate + delete in one step. Returns the lock row pre-delete or null.
     */
    public function consumeLock(int $lockId, int $userId): ?array
    {
        $row = $this->getOwnedActive($lockId, $userId);
        if ($row === null) {
            return null;
        }
        $this->model->deleteById($lockId);
        return $row;
    }

    private function overlapCount(string $start, string $end, array $bookings, array $locks, int $bufBefore, int $bufAfter): int
    {
        $slotS = $this->toSec($start);
        $slotE = $this->toSec($end);
        $count = 0;
        foreach ($bookings as $b) {
            $bs = $this->toSec((string) $b['starting_time']) - $bufBefore * 60;
            $be = $this->toSec((string) $b['ending_time'])   + $bufAfter  * 60;
            if ($bs < $slotE && $be > $slotS) {
                $count++;
            }
        }
        foreach ($locks as $l) {
            $ls = $this->toSec((string) $l['lock_start_time']) - $bufBefore * 60;
            $le = $this->toSec((string) $l['lock_end_time'])   + $bufAfter  * 60;
            if ($ls < $slotE && $le > $slotS) {
                $count++;
            }
        }
        return $count;
    }

    private function toSec(string $hms): int
    {
        $p = explode(':', $hms);
        return ((int) ($p[0] ?? 0)) * 3600 + ((int) ($p[1] ?? 0)) * 60 + ((int) ($p[2] ?? 0));
    }
}
