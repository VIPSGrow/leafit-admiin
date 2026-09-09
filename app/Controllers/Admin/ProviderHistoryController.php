<?php

namespace App\Controllers\Admin;

use App\Models\Settlement_CashCollection_history_model;

class ProviderHistoryController extends Admin
{
    public $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect();
        helper('ResponceServices');
    }

    public function provider_ledger()
    {
        $permissionService = new \App\Services\utility\PermissionService();
        if (!$this->isLoggedIn || !$this->userIsAdmin || !$permissionService->isSuperAdmin((int) $this->userId)) {
            return redirect('admin/login');
        }

        setPageInfo($this->data, labels('provider_ledger', 'Provider Ledger') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'provider_ledger');
        $this->data['currency'] = get_settings('general_settings', true)['currency'];
        $this->data['providers'] = (new \App\Models\Partners_model())->getApprovedProviders();

        return view('backend/admin/template', $this->data);
    }

    public function provider_ledger_details(int $providerId)
    {
        $permissionService = new \App\Services\utility\PermissionService();
        if (!$this->isLoggedIn || !$this->userIsAdmin || !$permissionService->isSuperAdmin((int) $this->userId)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => true, 'message' => 'Unauthorized']);
        }

        $provider = $this->db->table('users u')
            ->select('u.id, u.username, u.email, u.phone, u.balance, u.payable_commision, pd.company_name, pd.admin_commission')
            ->join('partner_details pd', 'pd.partner_id = u.id', 'inner')
            ->join('users_groups ug', 'ug.user_id = u.id', 'inner')
            ->where('u.id', $providerId)
            ->where('ug.group_id', 3)
            ->get()->getRowArray();

        if (empty($provider)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => true, 'message' => 'Provider not found']);
        }

        $pending = $this->db->table('payment_request')
            ->select('COUNT(id) as count, COALESCE(SUM(amount), 0) as amount')
            ->where('user_id', $providerId)
            ->where('status', '0')
            ->get()->getRowArray();

        return $this->response->setJSON([
            'error' => false,
            'provider' => $provider,
            'pending_requests' => [
                'count' => (int) ($pending['count'] ?? 0),
                'amount' => (float) ($pending['amount'] ?? 0),
            ],
            'bookings' => $this->getProviderBookingSummary($providerId),
        ]);
    }

    public function provider_ledger_list(int $providerId)
    {
        $permissionService = new \App\Services\utility\PermissionService();
        if (!$this->isLoggedIn || !$this->userIsAdmin || !$permissionService->isSuperAdmin((int) $this->userId)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => true, 'message' => 'Unauthorized']);
        }

        $limit = max(1, (int) ($this->request->getGet('limit') ?: 10));
        $offset = max(0, (int) ($this->request->getGet('offset') ?: 0));
        $search = trim((string) ($this->request->getGet('search') ?: ''));
        $settlementModel = new Settlement_CashCollection_history_model();
        $settlementResponse = json_decode($settlementModel->list(
            ['sc.provider_id' => $providerId], 'no', false, 10000, 0, 'id', 'DESC', $search
        ), true) ?: ['rows' => []];

        $bookingBuilder = $this->db->table('orders o')
            ->select('o.id, o.status, o.payment_method, o.payment_status, o.total, o.final_total, o.partner_earnings, o.admin_earnings, o.date_of_service, o.created_at')
            ->where('o.partner_id', $providerId)
            ->where('o.parent_id', null);
        if ($search !== '') {
            $bookingBuilder->groupStart()
                ->like('o.id', $search)
                ->orLike('o.status', $search)
                ->orLike('o.payment_method', $search)
                ->groupEnd();
        }
        $bookingRows = $bookingBuilder->orderBy('o.id', 'DESC')->limit(10000)->get()->getResultArray();
        $rows = $settlementResponse['rows'] ?? [];
        foreach ($bookingRows as $booking) {
            $status = (string) ($booking['status'] ?? 'pending');
            $paymentStatus = (string) ($booking['payment_status'] ?: 'pending');
            $rows[] = [
                'id' => 'B-' . (int) $booking['id'],
                'message' => 'Booking #' . (int) $booking['id'] . ' (' . ucfirst(str_replace('_', ' ', $booking['payment_method'] ?? '')) . ')',
                'type_badge' => '<span class="badge badge-info">Booking Payment</span>',
                'date' => !empty($booking['date_of_service']) ? format_date($booking['date_of_service'], 'd-m-Y') : '',
                'total_amount' => $booking['total'] ?? 0,
                'amount' => $booking['final_total'] ?? 0,
                'commission_amount' => $booking['admin_earnings'] ?? 0,
                'status_badge' => '<span class="badge badge-' . ($status === 'completed' ? 'success' : ($status === 'cancelled' ? 'danger' : 'warning')) . '">' . esc(ucfirst(str_replace('_', ' ', $status))) . '</span>',
                'payment_status' => ucfirst($paymentStatus),
                '_sort_id' => (int) $booking['id'],
            ];
        }

        usort($rows, static fn ($first, $second) => ($second['_sort_id'] ?? 0) <=> ($first['_sort_id'] ?? 0));
        foreach ($rows as &$row) {
            unset($row['_sort_id']);
        }
        unset($row);

        return $this->response->setJSON([
            'total' => count($rows),
            'rows' => array_slice($rows, $offset, $limit),
        ]);
    }

    private function getProviderBookingSummary(int $providerId): array
    {
        $summary = $this->db->table('orders')
            ->select("COUNT(id) as total, SUM(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled", false)
            ->where('partner_id', $providerId)
            ->where('parent_id', null)
            ->get()->getRowArray();

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'pending' => (int) ($summary['pending'] ?? 0),
            'completed' => (int) ($summary['completed'] ?? 0),
            'cancelled' => (int) ($summary['cancelled'] ?? 0),
        ];
    }

    public function partner_settlement_and_cash_collection_history()
    {
        try {
            helper('function');
            $uri = service('uri');
            $db      = \Config\Database::connect();
            $builder = $db->table('settlement_cashcollection_history ps');
            $partner_id = $uri->getSegments()[3];
            $active_subscription_details = fetch_details('settlement_cashcollection_history', ['provider_id' => $partner_id]);
            $symbol =   get_currency();
            $this->data['currency'] = $symbol;
            $this->data['active_subscription_details'] = $active_subscription_details;
            $this->data['partner_id'] = $partner_id;
            $subscription_details = fetch_details('subscriptions', ['status' => 1]);
            $this->data['subscription_details'] = $subscription_details;
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('settlement_and_cash_collection_history', 'Settlement And Cash Collection History') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_settlement_cashcollection_history');
                $partner_data = $this->db->table('users u')
                    ->select('u.id,u.username,pd.company_name')
                    ->join('partner_details pd', 'pd.partner_id = u.id')
                    ->where('u.id',  $partner_id)
                    ->get()->getResultArray();
                $this->data['partner'] = $partner_data;
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_settlement_and_cash_collection_history()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function partner_settlement_and_cash_collection_history_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $Settlement_CashCollection_history_model = new Settlement_CashCollection_history_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = ['sc.provider_id' => $partner_id];
            $data = $Settlement_CashCollection_history_model->list($where, 'no', false, $limit, $offset, $sort, $order, $search);
            return $data;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_settlement_and_cash_collection_history_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function all_settlement_cashcollection_history()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('booking_payment_management', 'Booking Payment Management') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'all_settlement_cashcollection_history');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }
    public function all_settlement_cashcollection_history_list()
    {
        try {
            $Settlement_CashCollection_history_model = new Settlement_CashCollection_history_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = [];
            $data = $Settlement_CashCollection_history_model->list($where, 'yes', false, $limit, $offset, $sort, $order, $search);
            return $data;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - all_settlement_cashcollection_history_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
}
