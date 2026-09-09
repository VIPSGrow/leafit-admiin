<?php

namespace App\Models;

use CodeIgniter\Model;

class HandymanReviewModel extends Model
{
    protected $table            = 'handyman_reviews';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';

    protected $allowedFields = [
        'order_id',
        'user_id',
        'handyman_id',
        'rating',
        'review',
        'images',
    ];

    protected $validationRules = [
        'order_id'    => 'required|integer',
        'user_id'     => 'required|integer',
        'handyman_id' => 'required|integer',
        'rating'      => 'required|integer|greater_than[0]|less_than_equal_to[5]',
    ];

    public function listForHandyman(int $handymanId, int $limit, int $offset, string $search): array
    {
        $builder = $this->db->table('handyman_reviews hr')
            ->select('hr.id, hr.order_id, hr.user_id, hr.rating, hr.review, hr.images, hr.created_at, u.username AS customer_name, u.image AS customer_image')
            ->join('users u', 'u.id = hr.user_id', 'left')
            ->where('hr.handyman_id', $handymanId)
            ->where('hr.deleted_at IS NULL', null, false);

        if ($search !== '') {
            $builder->groupStart()
                ->like('u.username', $search)
                ->orLike('hr.review', $search)
                ->orWhere('hr.user_id', is_numeric($search) ? (int) $search : 0)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $rows  = $builder->orderBy('hr.id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        return ['total' => (int) $total, 'data' => $rows];
    }

    public function listForCustomer(int $handymanId, int $orderId, int $limit, int $offset): array
    {
        $builder = $this->db->table('handyman_reviews hr')
            ->select('hr.id, hr.order_id, hr.user_id, hr.handyman_id, hr.rating, hr.review, hr.images, hr.created_at, hr.updated_at, u.username AS customer_name, u.image AS customer_image')
            ->join('users u', 'u.id = hr.user_id', 'left')
            ->where('hr.deleted_at IS NULL', null, false);

        if ($handymanId > 0) {
            $builder->where('hr.handyman_id', $handymanId);
        }
        if ($orderId > 0) {
            $builder->where('hr.order_id', $orderId);
        }

        $total = (clone $builder)->countAllResults(false);
        $rows  = $builder->orderBy('hr.id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        return ['total' => (int) $total, 'data' => $rows];
    }

    public function recalculateAggregates(int $handymanId): void
    {
        $row = $this->db->table('handyman_reviews')
            ->selectCount('id', 'total_reviews')
            ->selectAvg('rating', 'average_rating')
            ->where('handyman_id', $handymanId)
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        $total   = (int) ($row['total_reviews']   ?? 0);
        $average = round((float) ($row['average_rating'] ?? 0), 2);

        $detailsModel = new HandymanDetailsModel();
        $detailsModel->where('handyman_id', $handymanId)
            ->set(['total_reviews' => $total, 'average_rating' => $average])
            ->update();
    }
}
