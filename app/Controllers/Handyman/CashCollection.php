<?php

namespace App\Controllers\Handyman;

use App\Models\HandymanCashCollectionModel;

class CashCollection extends Handyman
{
    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
    }

    public function index()
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;
        $model = model(HandymanCashCollectionModel::class);

        $this->data['title'] = labels('cash_collection', 'Cash Collection');
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
            ['label' => labels('cash_collection', 'Cash Collection')],
        ];
        $this->data['currency'] = get_currency();
        $this->data['overall_total'] = $model->getOverallTotal($handymanId);
        $this->data['outstanding_total'] = $model->getOutstandingTotal($handymanId);

        return view('backend/handyman/pages/cash_collection', $this->data);
    }

    public function list_data()
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;

        $limit = (int) ($this->request->getGet('limit') ?: 10);
        $offset = (int) ($this->request->getGet('offset') ?: 0);
        $search = trim((string) ($this->request->getGet('search') ?: ''));
        $sort = $this->request->getGet('sort') ?: 'hcc.id';
        $order = strtoupper($this->request->getGet('order') ?: 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $type = trim((string) ($this->request->getGet('type') ?: ''));
        $dateFrom = trim((string) ($this->request->getGet('date_from') ?: ''));
        $dateTo = trim((string) ($this->request->getGet('date_to') ?: ''));

        $model = model(HandymanCashCollectionModel::class);
        $total = $model->countList($handymanId, $search, $type, $dateFrom, $dateTo);
        $rows = $model->list($handymanId, $limit, $offset, $search, $sort, $order, $type, $dateFrom, $dateTo);

        $typeColors = [
            'collection' => 'success',
            'settlement' => 'warning',
        ];

        foreach ($rows as &$row) {
            $type = $row['type'] ?? 'collection';
            $color = $typeColors[$type] ?? 'secondary';
            $label = $type === 'settlement' ? labels('settlement', 'Settlement') : labels('collection', 'Collection');

            $row['status_badge'] = '<span class="badge bg-' . $color . '">' . $label . '</span>';
            $row['amount_display'] = number_format((float) $row['amount'], 2);
            $row['service_date'] = !empty($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : '-';
            $row['order_id'] = $row['order_id'] ?? '-';
        }
        unset($row);

        return $this->response->setJSON(['total' => $total, 'rows' => $rows]);
    }
}
