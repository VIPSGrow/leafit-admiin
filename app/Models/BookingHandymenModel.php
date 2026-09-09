<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingHandymenModel extends Model
{
    protected $table         = 'booking_handymen';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'order_id',
        'handyman_id',
        'status',
        'is_lead',
        'chat_last_read_at',
        'rejected_reason',
        'assigned_by',
        'assigned_at',
        'updated_at',
    ];

    /**
     * Bookings for one handyman, classified into ongoing / upcoming / completed
     * and sorted so ongoing shows first, then upcoming, then completed last.
     * Rejected assignments are excluded.
     */
    public function listForHandyman(int $handymanId, int $limit, int $offset, string $search = ''): array
    {
        $total = $this->listForHandymanBaseBuilder($handymanId, $search)->countAllResults(false);

        $rows = $this->listForHandymanBaseBuilder($handymanId, $search)
            ->select("bh.id AS booking_handyman_id, bh.order_id, bh.status AS handyman_status, o.status AS order_status,
                o.date_of_service, o.starting_time, o.ending_time, o.final_total, o.address, o.address_id,
                pd.address AS provider_address, u.username AS customer_name,
                CASE
                    WHEN bh.status IN ('on_the_way','arrived','started') THEN 0
                    WHEN bh.status IN ('assigned','accepted') THEN 1
                    ELSE 2
                END AS status_group", false)
            ->orderBy('status_group', 'ASC')
            ->orderBy('o.date_of_service', 'ASC')
            ->orderBy('o.starting_time', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        return ['total' => (int) $total, 'data' => $rows];
    }

    private function listForHandymanBaseBuilder(int $handymanId, string $search)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('booking_handymen bh')
            ->join('orders o', 'o.id = bh.order_id')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->join('partner_details pd', 'pd.partner_id = o.partner_id', 'left')
            ->where('bh.handyman_id', $handymanId)
            ->where('bh.status !=', 'rejected');

        if ($search !== '') {
            $builder->groupStart()
                ->like('bh.order_id', $search)
                ->orLike('u.username', $search)
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * Dashboard summary counts for a handyman: total assignments, lead-role
     * count, and today/tomorrow/upcoming active-order counts. Mirrors
     * Orders_model::bookingCountsForPartner()'s half-open date ranges and
     * status filter, joined through booking_handymen instead of partner_id.
     */
    public function getDashboardSummary(int $handymanId, string $today, string $tomorrow, string $dayAfterTomorrow): array
    {
        $db = \Config\Database::connect();
        $sql = "SELECT
                COUNT(*) AS total_bookings,
                SUM(CASE WHEN bh.is_lead = 1 THEN 1 ELSE 0 END) AS lead_bookings,
                SUM(CASE WHEN bh.status IN ('assigned','accepted')
                         AND o.status IN ('awaiting','confirmed','rescheduled')
                         AND o.date_of_service >= ? AND o.date_of_service < ? THEN 1 ELSE 0 END) AS today_bookings,
                SUM(CASE WHEN bh.status IN ('assigned','accepted')
                         AND o.status IN ('awaiting','confirmed','rescheduled')
                         AND o.date_of_service >= ? AND o.date_of_service < ? THEN 1 ELSE 0 END) AS tomorrow_bookings,
                SUM(CASE WHEN bh.status IN ('assigned','accepted')
                         AND o.status IN ('awaiting','confirmed','rescheduled')
                         AND o.date_of_service >= ? THEN 1 ELSE 0 END) AS upcoming_bookings
            FROM booking_handymen bh
            INNER JOIN orders o ON o.id = bh.order_id
            WHERE bh.handyman_id = ?";

        $row = $db->query($sql, [$today, $tomorrow, $tomorrow, $dayAfterTomorrow, $dayAfterTomorrow, $handymanId])->getRowArray();

        return [
            'total_bookings' => (int) ($row['total_bookings'] ?? 0),
            'lead_bookings' => (int) ($row['lead_bookings'] ?? 0),
            'today_bookings' => (int) ($row['today_bookings'] ?? 0),
            'tomorrow_bookings' => (int) ($row['tomorrow_bookings'] ?? 0),
            'upcoming_bookings' => (int) ($row['upcoming_bookings'] ?? 0),
        ];
    }

    /**
     * Last N completed bookings for a handyman, newest first.
     */
    public function getRecentCompleted(int $handymanId, int $limit = 5): array
    {
        $db = \Config\Database::connect();
        return $db->table('booking_handymen bh')
            ->select('bh.order_id, o.date_of_service, o.starting_time, o.ending_time, o.final_total, o.address, o.address_id, pd.address AS provider_address, u.username AS customer_name')
            ->join('orders o', 'o.id = bh.order_id')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->join('partner_details pd', 'pd.partner_id = o.partner_id', 'left')
            ->where('bh.handyman_id', $handymanId)
            ->where('bh.status', 'completed')
            ->orderBy('o.date_of_service', 'DESC')
            ->orderBy('o.starting_time', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Whether the given handyman is the current lead for the booking.
     * Only the lead may send chat messages; others are view-only.
     */
    public function isLeadForOrder(int $orderId, int $handymanId): bool
    {
        $db = \Config\Database::connect();
        $count = $db->table('booking_handymen')
            ->where('order_id', $orderId)
            ->where('handyman_id', $handymanId)
            ->where('is_lead', 1)
            ->countAllResults();

        return $count > 0;
    }

    /**
     * Returns user ids of all handymen assigned to a booking (excluding
     * rejected assignments). Used to route customer→provider chat push
     * notifications to every assigned handyman — they all share the
     * booking chat thread with the customer (lead can reply, others are
     * view-only, but all should be notified).
     */
    public function getAssignedHandymanIds(int $orderId): array
    {
        $db = \Config\Database::connect();
        $rows = $db->table('booking_handymen')
            ->select('handyman_id')
            ->where('order_id', $orderId)
            ->where('status !=', 'rejected')
            ->get()->getResultArray();

        return array_map(static fn($r) => (int) $r['handyman_id'], $rows);
    }

    /**
     * Marks this handyman's chat thread for a booking as read as of now.
     * Independent of the customer/provider `chats.is_read` flag — each party
     * tracks its own read progress against message timestamps.
     */
    public function markChatRead(int $orderId, int $handymanId): void
    {
        $db = \Config\Database::connect();
        $db->table('booking_handymen')
            ->where('order_id', $orderId)
            ->where('handyman_id', $handymanId)
            ->update(['chat_last_read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Distinct-booking count of unread thread messages for this handyman across
     * all non-rejected assignments. "Unread" = message created after this handyman's
     * chat_last_read_at (or every message, if never read) and not sent by the
     * handyman himself.
     */
    public function unreadChatBookingCountForHandyman(int $handymanId): int
    {
        $db = \Config\Database::connect();
        $rows = $db->table('booking_handymen bh')
            ->select('bh.order_id')
            ->join('chats c', "c.booking_id = bh.order_id AND c.sender_id != {$handymanId}"
                . " AND (bh.chat_last_read_at IS NULL OR c.created_at > bh.chat_last_read_at)")
            ->where('bh.handyman_id', $handymanId)
            ->where('bh.status !=', 'rejected')
            ->groupBy('bh.order_id')
            ->get()->getResultArray();

        return count($rows);
    }

    public function getAssignedHandymen(int $orderId, int $excludeHandymanId = 0): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('booking_handymen bh')
            ->select('bh.handyman_id AS id, u.username, u.image, bh.status AS handyman_status, bh.is_lead, hr.id AS rating_id, hr.rating, hr.review, hr.images AS review_images')
            ->join('users u', 'u.id = bh.handyman_id')
            ->join('handyman_reviews hr', 'hr.order_id = bh.order_id AND hr.handyman_id = bh.handyman_id AND hr.deleted_at IS NULL', 'left')
            ->where('bh.order_id', $orderId);

        if ($excludeHandymanId > 0) {
            $builder->where('bh.handyman_id !=', $excludeHandymanId);
        }

        $rows = $builder->get()->getResultArray();

        $fileService = service('fileService');
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['is_lead'] = (int) $row['is_lead'];
            $row['image'] = $fileService->url($row['image'], 'profile');
            $row['rating_id'] = isset($row['rating_id']) ? (int) $row['rating_id'] : null;
            $row['rating'] = isset($row['rating']) ? (int) $row['rating'] : null;
            $imagePaths = !empty($row['review_images']) ? (json_decode($row['review_images'], true) ?: []) : [];
            $row['review_images'] = array_map(fn($p) => $fileService->url($p, 'handyman_reviews'), $imagePaths);
        }
        unset($row);
        return $rows;
    }

    /**
     * Mirrors the order-level status onto every non-rejected assigned handyman
     * row when a provider (not the handyman) drives the status change, so the
     * handyman app doesn't show a stale status after the provider moves the
     * order forward or cancels it.
     */
    public function syncStatusForOrder(int $orderId, string $status): void
    {
        $this->where('order_id', $orderId)
            ->where('status !=', 'rejected')
            ->set(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')])
            ->update();
    }

    /**
     * Whether this handyman is assigned (non-rejected) to any booking whose
     * order has not yet ended — i.e. not completed and not cancelled.
     * Used to block handyman deletion while an in-progress booking exists.
     */
    public function hasActiveBookingAssignment(int $handymanId): bool
    {
        return count($this->getActiveBookingAssignments($handymanId)) > 0;
    }

    /**
     * Orders this handyman is assigned to (non-rejected) that have not yet
     * ended — i.e. not completed and not cancelled. Used to block deletion/
     * deactivation and to show the caller which bookings are blocking it.
     */
    public function getActiveBookingAssignments(int $handymanId): array
    {
        $db = \Config\Database::connect();
        return $db->table('booking_handymen bh')
            ->select('bh.order_id, bh.status AS handyman_status, o.status AS order_status, o.date_of_service, o.starting_time, o.ending_time, u.username AS customer_name')
            ->join('orders o', 'o.id = bh.order_id')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->where('bh.handyman_id', $handymanId)
            ->where('bh.status !=', 'rejected')
            ->whereNotIn('o.status', ['completed', 'cancelled'])
            ->orderBy('o.date_of_service', 'ASC')
            ->orderBy('o.starting_time', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getLeadHandyman(int $orderId): ?array
    {
        $db = \Config\Database::connect();
        $row = $db->table('booking_handymen bh')
            ->select('bh.handyman_id AS id, u.username, u.image')
            ->join('users u', 'u.id = bh.handyman_id')
            ->where('bh.order_id', $orderId)
            ->where('bh.is_lead', 1)
            ->get()->getRowArray();

        if (empty($row)) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['image'] = service('fileService')->url($row['image'], 'profile');
        return $row;
    }
}
