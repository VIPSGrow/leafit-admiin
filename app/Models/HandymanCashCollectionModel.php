<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Debit/credit ledger for cash a handyman personally collects from customers
 * on COD bookings. Two row types share this table (mirrors the existing
 * settlement_cashcollection_history precedent of one audit table with a
 * `type` column instead of separate collection/settlement tables):
 *
 *  - 'collection' — +amount, written when a handyman completes a COD booking.
 *  - 'settlement' — -amount, written when the partner confirms they've
 *     physically received (part of) that cash back from the handyman.
 *
 * Both totals are always derived from these rows, never a stored counter:
 * overall_cash_collected = SUM(collection rows); outstanding_balance =
 * SUM(collection rows) - SUM(settlement rows). No read-modify-write race,
 * no reset job, and settling never edits/closes the original per-order
 * collection rows — a handyman's order history stays intact.
 */
class HandymanCashCollectionModel extends Model
{
    protected $table          = 'handyman_cash_collections';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $allowedFields = [
        'handyman_id',
        'partner_id',
        'order_id',
        'type',
        'amount',
        'message',
    ];

    public function recordCollection(int $orderId, int $handymanId, int $partnerId, float $amount): void
    {
        $this->insert([
            'handyman_id' => $handymanId,
            'partner_id' => $partnerId,
            'order_id' => $orderId,
            'type' => 'collection',
            'amount' => $amount,
        ]);
    }

    public function recordSettlement(int $handymanId, int $partnerId, float $amount, ?string $message, ?int $orderId = null): void
    {
        $this->insert([
            'handyman_id' => $handymanId,
            'partner_id' => $partnerId,
            'order_id' => $orderId,
            'type' => 'settlement',
            'amount' => $amount,
            'message' => $message,
        ]);
    }

    public function list(int $handymanId, int $limit, int $offset, string $search, string $sort, string $order, string $type = '', string $dateFrom = '', string $dateTo = ''): array
    {
        $allowedSorts = ['hcc.id', 'hcc.amount', 'hcc.created_at', 'hcc.type'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'hcc.id';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        return $this->listBaseBuilder($handymanId, $search, $type, $dateFrom, $dateTo)
            ->select('hcc.id, hcc.order_id, hcc.type, hcc.amount, hcc.message, hcc.created_at, o.final_total, o.date_of_service, u.username AS customer_name')
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    public function countList(int $handymanId, string $search, string $type = '', string $dateFrom = '', string $dateTo = ''): int
    {
        return $this->listBaseBuilder($handymanId, $search, $type, $dateFrom, $dateTo)->countAllResults();
    }

    private function listBaseBuilder(int $handymanId, string $search, string $type = '', string $dateFrom = '', string $dateTo = '')
    {
        $db = \Config\Database::connect();
        $builder = $db->table('handyman_cash_collections hcc')
            ->join('orders o', 'o.id = hcc.order_id', 'left')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->where('hcc.handyman_id', $handymanId);

        if ($search !== '') {
            $builder->groupStart()
                ->like('hcc.order_id', $search)
                ->orLike('u.username', $search)
                ->groupEnd();
        }

        if (in_array($type, ['collection', 'settlement'], true)) {
            $builder->where('hcc.type', $type);
        }

        if ($dateFrom !== '') {
            $builder->where('hcc.created_at >=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo !== '') {
            $builder->where('hcc.created_at <=', $dateTo . ' 23:59:59');
        }

        return $builder;
    }

    public function getOverallTotal(int $handymanId): float
    {
        $row = $this->selectSum('amount')
            ->where('handyman_id', $handymanId)
            ->where('type', 'collection')
            ->first();

        return (float) ($row['amount'] ?? 0);
    }

    /**
     * Settlement isn't tied to a specific order (see class docblock) — this
     * is only used to give the settlement_cashcollection_history audit row
     * a representative order_id: the handyman's most recent collection.
     */
    public function getLatestCollectionOrderId(int $handymanId): ?int
    {
        $row = $this->select('order_id')
            ->where('handyman_id', $handymanId)
            ->where('type', 'collection')
            ->orderBy('id', 'DESC')
            ->first();

        return isset($row['order_id']) ? (int) $row['order_id'] : null;
    }

    public function getOutstandingTotal(int $handymanId): float
    {
        $db = \Config\Database::connect();
        $row = $db->table('handyman_cash_collections')
            ->select("SUM(CASE WHEN type = 'collection' THEN amount ELSE -amount END) AS balance", false)
            ->where('handyman_id', $handymanId)
            ->get()
            ->getRowArray();

        return (float) ($row['balance'] ?? 0);
    }

    public function getOutstandingByHandyman(int $partnerId, string $search = ''): array
    {
        $db = \Config\Database::connect();
        [$currentLangId, $defaultLangId] = $this->resolveLanguageIds();

        $builder = $db->table('handyman_cash_collections hcc')
            ->select("
                hcc.handyman_id,
                COALESCE(NULLIF(TRIM(thd_current.username), ''), NULLIF(TRIM(thd_default.username), ''), u.username) AS handyman_name,
                u.image AS handyman_image,
                u.email AS handyman_email,
                u.phone AS handyman_phone,
                u.country_code AS handyman_country_code,
                SUM(CASE WHEN hcc.type = 'collection' THEN hcc.amount ELSE -hcc.amount END) AS outstanding_amount
            ", false)
            ->join('users u', 'u.id = hcc.handyman_id', 'left')
            // Joined on an exact language_id per handyman (at most one matching row each),
            // so — unlike the broad, unfiltered search join below — this can't fan out the
            // aggregate rows or inflate the SUM.
            ->join('translated_handyman_details thd_current', "thd_current.handyman_id = hcc.handyman_id AND thd_current.language_id = {$currentLangId} AND thd_current.deleted_at IS NULL", 'left')
            ->join('translated_handyman_details thd_default', "thd_default.handyman_id = hcc.handyman_id AND thd_default.language_id = {$defaultLangId} AND thd_default.deleted_at IS NULL", 'left')
            ->where('hcc.partner_id', $partnerId);

        if ($search !== '') {
            // Matched via a separate id lookup (not joined into the aggregate query above) —
            // joining translated_handyman_details unfiltered here would multiply hcc rows per
            // language and inflate the SUM.
            $matchedIds = array_column(
                $db->table('users u')
                    ->select('u.id')
                    ->join('translated_handyman_details thd', 'thd.handyman_id = u.id AND thd.deleted_at IS NULL', 'left')
                    ->groupStart()
                        ->like('u.id', $search)
                        ->orLike('u.username', $search)
                        ->orLike('thd.username', $search)
                    ->groupEnd()
                    ->get()
                    ->getResultArray(),
                'id'
            );

            if (empty($matchedIds)) {
                return [];
            }
            $builder->whereIn('hcc.handyman_id', array_unique($matchedIds));
        }

        return $builder
            ->groupBy('hcc.handyman_id')
            ->having('outstanding_amount >', 0)
            ->get()
            ->getResultArray();
    }

    /**
     * Individual pending 'collection' rows for the given handymen (their
     * currently outstanding-balance handymen) — per-order/customer detail
     * backing the partner-facing list. Settlement amounts are never tied to
     * specific orders (see class docblock), so this lists every collection
     * event those handymen have logged, not a strict "unsettled orders" set.
     */
    public function listOutstandingCollections(int $partnerId, array $handymanIds): array
    {
        if (empty($handymanIds)) {
            return [];
        }

        $db = \Config\Database::connect();

        return $db->table('handyman_cash_collections hcc')
            ->select('hcc.id, hcc.handyman_id, hcc.order_id, hcc.amount, hcc.created_at, cu.username AS customer_name')
            ->join('orders o', 'o.id = hcc.order_id', 'left')
            ->join('users cu', 'cu.id = o.user_id', 'left')
            ->where('hcc.partner_id', $partnerId)
            ->where('hcc.type', 'collection')
            ->whereIn('hcc.handyman_id', $handymanIds)
            ->orderBy('hcc.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Settlement rows shaped to match the `cash_collection` table's columns so the
     * partner API can merge them into that endpoint's provider_cash_recevied listing.
     */
    public function listSettlementsForProvider(int $partnerId): array
    {
        return $this->select("
                id,
                handyman_id AS user_id,
                order_id,
                message,
                'provider_cash_recevied' AS status,
                amount AS commison,
                partner_id,
                DATE(created_at) AS date,
                created_at
            ", false)
            ->where('partner_id', $partnerId)
            ->where('type', 'settlement')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    private function resolveLanguageIds(): array
    {
        $languageModel = model(\App\Models\Language_model::class);
        $current = $languageModel->select('id')->where('code', get_current_language())->first();
        $default = $languageModel->select('id')->where('code', get_default_language())->first();

        return [(int) ($current['id'] ?? 0), (int) ($default['id'] ?? 0)];
    }
}
