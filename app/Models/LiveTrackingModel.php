<?php

namespace App\Models;

use CodeIgniter\Model;

class LiveTrackingModel extends Model
{
    protected $table = 'live_tracking';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'order_id',
        'latitude',
        'longitude',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $dateFormat = 'datetime';

    public function hasStarted(int $orderId): bool
    {
        return $this->where('order_id', $orderId)->countAllResults() > 0;
    }

    public function getActiveHandymenForPartner(int $partnerId): array
    {
        $rows = $this->db->table('live_tracking lt')
            ->select('lt.order_id, lt.latitude, lt.longitude, lt.updated_at, bh.handyman_id, u.username as handyman_name, u.image as handyman_image, COALESCE(hd.average_rating, 0) as average_rating, COALESCE(hd.total_reviews, 0) as total_reviews')
            ->join('orders o', 'o.id = lt.order_id')
            ->join('booking_handymen bh', 'bh.order_id = lt.order_id AND bh.is_lead = 1')
            ->join('users u', 'u.id = bh.handyman_id')
            ->join('handyman_details hd', 'hd.handyman_id = bh.handyman_id', 'left')
            ->where('o.partner_id', $partnerId)
            ->where('o.status', 'on_the_way')
            ->where('bh.status', 'on_the_way')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['order_id'] = (int) $row['order_id'];
            $row['handyman_id'] = (int) $row['handyman_id'];
            $row['latitude'] = (float) $row['latitude'];
            $row['longitude'] = (float) $row['longitude'];
            $row['average_rating'] = (float) $row['average_rating'];
            $row['total_reviews'] = (int) $row['total_reviews'];
            $row['handyman_image'] = service('FileService')->url($row['handyman_image'], 'profile', null);
        }

        return $rows;
    }
}
