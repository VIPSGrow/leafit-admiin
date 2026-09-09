<?php

namespace App\Controllers\Apis\Handyman;

use App\Controllers\BaseController;
use App\Models\HandymanCashCollectionModel;

class CashCollectionApiController extends BaseController
{
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper('function');
        helper('ResponceServices');
        $this->request = \Config\Services::request();

        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            ApiError($token['message'])->setStatusCode($token['status'] ?? 403)->send();
            exit;
        }
    }

    public function get_list()
    {
        try {
            $handymanId = (int) $this->user_details['id'];

            $limit = (int) ($this->request->getPost('limit') ?: 10);
            $offset = (int) ($this->request->getPost('offset') ?: 0);
            $search = trim((string) ($this->request->getPost('search') ?: ''));
            $sort = $this->request->getPost('sort') ?: 'hcc.id';
            $order = strtoupper($this->request->getPost('order') ?: 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $type = trim((string) ($this->request->getPost('type') ?: ''));
            $dateFrom = trim((string) ($this->request->getPost('date_from') ?: ''));
            $dateTo = trim((string) ($this->request->getPost('date_to') ?: ''));

            $model = model(HandymanCashCollectionModel::class);
            $total = $model->countList($handymanId, $search, $type, $dateFrom, $dateTo);
            $rows = $model->list($handymanId, $limit, $offset, $search, $sort, $order, $type, $dateFrom, $dateTo);

            $currency = get_currency();
            foreach ($rows as &$row) {
                $row['currency'] = $currency;
                $row['amount'] = (float) $row['amount'];
                $row['created_at'] = !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : null;
                $row['date_of_service'] = !empty($row['date_of_service']) ? date('Y-m-d', strtotime($row['date_of_service'])) : null;
            }
            unset($row);

            $summary = [
                'currency' => $currency,
                'overall_total' => $model->getOverallTotal($handymanId),
                'outstanding_total' => $model->getOutstandingTotal($handymanId),
            ];

            return ApiSuccess('data_fetched_successfully', $rows, ['total' => $total, 'summary' => $summary]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/CashCollectionApiController.php - get_list()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }
}
