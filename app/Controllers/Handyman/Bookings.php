<?php

namespace App\Controllers\Handyman;

use App\Models\BookingHandymenModel;
use App\Models\LiveTrackingModel;
use App\Models\Orders_model;
use App\Models\Transaction_model;

class Bookings extends Handyman
{
    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
    }

    public function index()
    {
        $this->data['title'] = labels('my_bookings', 'My Bookings');
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
            ['label' => labels('my_bookings', 'My Bookings')],
        ];
        $this->data['currency'] = get_currency();
        return view('backend/handyman/pages/bookings', $this->data);
    }

    public function view(int $id)
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;

        $ordersModel = model(Orders_model::class);
        $booking = $ordersModel
            ->select('orders.id, orders.status, orders.address_id, orders.date_of_service, orders.starting_time, orders.ending_time, orders.address, orders.remarks, orders.final_total, orders.total, orders.visiting_charges, orders.promo_discount, orders.payment_method, orders.work_started_proof, orders.work_completed_proof, orders.additional_charges, orders.total_additional_charge, orders.payment_status_of_additional_charge, orders.payment_method_of_additional_charge, t.status AS payment_status, u.username AS customer_name, u.phone AS customer_phone, u.country_code AS customer_country_code, u.email AS customer_email, bh.status AS handyman_status, bh.rejected_reason, bh.is_lead, pd.address AS provider_address')
            ->join('users u', 'u.id = orders.user_id', 'left')
            ->join('booking_handymen bh', 'bh.order_id = orders.id AND bh.handyman_id = ' . $handymanId, 'inner')
            ->join('transactions t', "t.order_id = orders.id AND t.transaction_type = 'transaction'", 'left')
            ->join('partner_details pd', 'pd.partner_id = orders.partner_id', 'left')
            ->where('orders.id', $id)
            ->first();

        if (empty($booking)) {
            return redirect()->to(base_url('handyman/bookings'));
        }

        if (((int) ($booking['address_id'] ?? 1)) === 0) {
            $booking['address'] = $booking['provider_address'] ?? $booking['address'];
        }
        unset($booking['provider_address']);

        $booking['payment_status'] = $ordersModel->getPaymentStatus($booking['payment_method'], $booking['status'], $booking['payment_status'] ?? null);
        $booking['additional_charges'] = !empty($booking['additional_charges']) ? json_decode($booking['additional_charges'], true) : [];

        $db = \Config\Database::connect();
        $order_services = $db->table('order_services os')
            ->select('os.*, s.image as service_image')
            ->join('services s', 's.id = os.service_id', 'left')
            ->where('os.order_id', $id)
            ->where("os.status != 'cancelled'")
            ->get()->getResultArray();

        $currentLanguage = get_current_language();
        $order_tax_amount = 0;
        foreach ($order_services as &$service) {
            $service['service_title'] = $ordersModel->resolveServiceTitleFromSnapshot($service, $currentLanguage);
            $order_tax_amount += floatval($service['tax_amount'] ?? 0) * floatval($service['quantity'] ?? 0);
        }
        unset($service);
        $additional_charge_tax_amount = 0;
        if (!empty($booking['additional_charges'])) {
            $additional_charges = $booking['additional_charges'];
            if (is_array($additional_charges)) {
                foreach ($additional_charges as $charge) {
                    $additional_charge_tax_amount += floatval($charge['tax_amount'] ?? 0);
                }
            }
        }
        $this->data['order_services'] = $order_services;
        
        $booking['tax_amount'] = $order_tax_amount + $additional_charge_tax_amount;
        $order_total = floatval($booking['total'] ?? 0);
        $booking['subtotal'] = $order_total - $order_tax_amount;
        $booking['tax_type'] = !empty($order_services) ? ($order_services[0]['tax_type'] ?? '') : '';

        $settings = get_settings('general_settings', true);
        $this->data['title'] = labels('booking_details', 'Booking Details');
        $this->data['booking'] = $booking;
        $this->data['assigned_handymen'] = model(BookingHandymenModel::class)->getAssignedHandymen($id);
        $this->data['currency'] = get_currency();
        $this->data['is_otp_enable'] = (!empty($settings['otp_system']) && $settings['otp_system'] == 1) ? '1' : '0';
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
            ['label' => labels('my_bookings', 'My Bookings'), 'url' => base_url('handyman/bookings'), 'icon' => 'fas fa-list-alt'],
            ['label' => '#' . $id],
        ];

        return view('backend/handyman/pages/booking_details', $this->data);
    }

    public function update_status()
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;
        $orderId = (int) $this->request->getPost('order_id');
        $newStatus = trim((string) $this->request->getPost('status'));

        // 'accepted' and 'rejected' removed from allowed — handymen are auto-accepted on assignment
        $allowed = [/* 'accepted', 'rejected', */ 'on_the_way', 'arrived', 'started', 'booking_ended', 'completed'];
        if (!in_array($newStatus, $allowed, true)) {
            return JsonError(labels(INVALID_STATUS_PASSED, 'Invalid status'));
        }

        $bookingHandymenModel = model(BookingHandymenModel::class);
        $row = $bookingHandymenModel
            ->where('order_id', $orderId)
            ->where('handyman_id', $handymanId)
            ->first();

        if (empty($row)) {
            return JsonError(labels(DATA_NOT_FOUND, 'Booking not found'));
        }

        if (empty($row['is_lead'])) {
            return JsonError(labels('only_lead_can_update_status', 'Only the lead handyman can update the booking status'));
        }

        // Block status change if payment is pending for non-COD orders
        $ordersModel = model(Orders_model::class);
        $transactionModel = model(Transaction_model::class);

        $order = $ordersModel->select('payment_method, address_id')->where('id', $orderId)->first();
        if (!empty($order) && ($order['payment_method'] ?? '') !== 'cod') {
            $transaction = $transactionModel
                ->select('status')
                ->where('order_id', $orderId)
                ->where('transaction_type', 'transaction')
                ->first();
            if (($transaction['status'] ?? '') !== 'success') {
                return JsonError(payment_block_message($transaction['status'] ?? null));
            }
        }

        $isAtStore = !empty($order) && ((int) ($order['address_id'] ?? 1)) === 0;
        $transitions = $isAtStore
            ? [
                'assigned' => [], // was: ['accepted', 'rejected'] — auto-accepted on assignment
                'accepted' => ['started'], // was: ['started', 'rejected']
                'started' => ['booking_ended', 'completed'],
                'booking_ended' => ['completed'],
            ]
            : [
                'assigned' => [], // was: ['accepted', 'rejected'] — auto-accepted on assignment
                'accepted' => ['on_the_way'], // was: ['on_the_way', 'rejected']
                'on_the_way' => ['arrived'],
                'arrived' => ['started'],
                'started' => ['booking_ended', 'completed'],
                'booking_ended' => ['completed'],
            ];

        $current = $row['status'];
        if (!isset($transitions[$current]) || !in_array($newStatus, $transitions[$current], true)) {
            return JsonError(labels('invalid_status_transition', 'This status change is not allowed from the current booking state'));
        }

        try {
            if ($newStatus === 'started') {
                $uploadedFiles = $this->request->getFiles() ?: null;
                $response = validate_order_status($orderId, 'started', '', '', null, $uploadedFiles, null, $handymanId, null, 'handyman');
                if (!empty($response['error'])) {
                    return $this->response->setJSON($response);
                }
            } elseif ($newStatus === 'booking_ended') {
                $additionalCharges = $this->request->getPost('booking_ended_additional_charges') ?? '';
                $uploadedFiles = $this->request->getFiles() ?: null;
                $workProof = !empty($uploadedFiles['work_complete_files']) ? ['work_complete_files' => $uploadedFiles['work_complete_files']] : null;
                $response = validate_order_status($orderId, 'booking_ended', '', '', '', $workProof, $additionalCharges ?: null, $handymanId, null, 'handyman');
                if (!empty($response['error'])) {
                    return $this->response->setJSON($response);
                }
            } elseif ($newStatus === 'completed') {
                $otp = trim((string) $this->request->getPost('otp'));
                $response = validate_order_status($orderId, 'completed', '', '', $otp ?: null, null, null, $handymanId, null, 'handyman');
                if (!empty($response['error'])) {
                    return $this->response->setJSON($response);
                }
            }

            $update = ['status' => $newStatus];
            // Rejected reason preserved for future re-enable of manual reject
            // if ($newStatus === 'rejected') {
            //     $update['rejected_reason'] = trim((string) $this->request->getPost('rejected_reason'));
            // }

            $db = \Config\Database::connect();
            $db->transStart();

            $bookingHandymenModel
                ->where('order_id', $orderId)
                ->set($update)
                ->update();

            if ($newStatus === 'on_the_way' || $newStatus === 'arrived') {
                $ordersModel->set(['status' => $newStatus, 'status_changed_by_type' => 'handyman', 'status_changed_by_id' => $handymanId])->where('id', $orderId)->update();
            }

            if ($newStatus === 'on_the_way') {
                $latitude = trim((string) $this->request->getPost('latitude'));
                $longitude = trim((string) $this->request->getPost('longitude'));
                $this->recordLiveTrackingLocation($orderId, $handymanId, $latitude ?: null, $longitude ?: null);
            }

            $db->transComplete();
            if (!$db->transStatus()) {
                return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
            }

            return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, 'Status updated successfully'), null, ['new_status' => $newStatus]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Bookings.php - update_status()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    private function recordLiveTrackingLocation(int $orderId, int $userId, ?string $latitude = null, ?string $longitude = null): void
    {
        if (empty($latitude) || empty($longitude)) {
            $usersModel = model(\App\Models\Users_model::class);
            $user = $usersModel->select('latitude, longitude')->find($userId);
            $latitude = $user['latitude'] ?? null;
            $longitude = $user['longitude'] ?? null;
        }

        if (empty($latitude) || empty($longitude)) {
            return;
        }

        $liveTrackingModel = model(LiveTrackingModel::class);
        $trackingData = [
            'order_id' => $orderId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        $existing = $liveTrackingModel->where('order_id', $orderId)->first();
        if (!empty($existing)) {
            $liveTrackingModel->where('order_id', $orderId)->set($trackingData)->update();
        } else {
            $liveTrackingModel->insert($trackingData);
        }
    }

    public function booking_data(int $id)
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;

        $ordersModel = model(Orders_model::class);
        $booking = $ordersModel
            ->select('orders.status, orders.address_id, orders.payment_method, orders.work_started_proof, orders.work_completed_proof, orders.total_additional_charge, orders.payment_status_of_additional_charge, orders.payment_method_of_additional_charge, t.status AS payment_status, bh.status AS handyman_status, bh.rejected_reason, bh.is_lead')
            ->join('booking_handymen bh', 'bh.order_id = orders.id AND bh.handyman_id = ' . $handymanId, 'inner')
            ->join('transactions t', "t.order_id = orders.id AND t.transaction_type = 'transaction'", 'left')
            ->where('orders.id', $id)
            ->first();

        if (empty($booking)) {
            return JsonError(labels(DATA_NOT_FOUND, 'Booking not found'));
        }

        $isAtStore = ((int) ($booking['address_id'] ?? 1)) === 0;
        $transitions = $isAtStore
            ? [
                'assigned' => [], // was: ['accepted', 'rejected'] — auto-accepted on assignment
                'accepted' => ['started'], // was: ['started', 'rejected']
                'started' => ['booking_ended', 'completed'],
                'booking_ended' => ['completed'],
                'rejected' => [],
                'completed' => [],
            ]
            : [
                'assigned' => [], // was: ['accepted', 'rejected'] — auto-accepted on assignment
                'accepted' => ['on_the_way'], // was: ['on_the_way', 'rejected']
                'on_the_way' => ['arrived'],
                'arrived' => ['started'],
                'started' => ['booking_ended', 'completed'],
                'booking_ended' => ['completed'],
                'rejected' => [],
                'completed' => [],
            ];

        $isPaymentPending = ($booking['payment_status'] ?? '') !== 'success' && ($booking['payment_method'] ?? '') !== 'cod';
        $nextStatuses = ($isPaymentPending || empty($booking['is_lead'])) ? [] : ($transitions[$booking['handyman_status']] ?? []);
        // Mirrors validate_order_status() 'completed' guard in function_helper.php
        $isAdditionalChargePaymentPending = (empty($booking['payment_method_of_additional_charge']) || $booking['payment_method_of_additional_charge'] !== 'cod')
            && !empty($booking['total_additional_charge']) && $booking['total_additional_charge'] != 0
            && in_array($booking['payment_status_of_additional_charge'] ?? '', ['', '0'], true);
        $workStartedProof = !empty($booking['work_started_proof']) ? json_decode($booking['work_started_proof'], true) : [];
        $workCompletedProof = !empty($booking['work_completed_proof']) ? json_decode($booking['work_completed_proof'], true) : [];

        $assignedHandymen = model(BookingHandymenModel::class)->getAssignedHandymen($id);

        return $this->response->setJSON([
            'error' => false,
            'data' => [
                'handyman_status' => $booking['handyman_status'],
                'order_status' => $booking['status'],
                'is_lead' => (bool) $booking['is_lead'],
                'is_at_store' => $isAtStore,
                'is_payment_pending' => $isPaymentPending,
                'payment_pending_message' => $isPaymentPending ? payment_block_message($booking['payment_status'] ?? null) : null,
                'additional_charge_payment_pending' => $isAdditionalChargePaymentPending,
                'rejected_reason' => $booking['rejected_reason'] ?? null,
                'next_statuses' => $nextStatuses,
                'work_started_proof' => $workStartedProof,
                'work_completed_proof' => $workCompletedProof,
                'assigned_handymen' => $assignedHandymen,
            ],
        ]);
    }

    public function list_data()
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;

        $limit = (int) ($this->request->getGet('limit') ?: 10);
        $offset = (int) ($this->request->getGet('offset') ?: 0);
        $search = trim((string) ($this->request->getGet('search') ?: ''));
        $sort = $this->request->getGet('sort') ?: 'orders.id';
        $order = strtoupper($this->request->getGet('order') ?: 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $statusFilter = trim((string) ($this->request->getGet('status_filter') ?: ''));

        $allowedSorts = ['orders.id', 'orders.date_of_service', 'orders.final_total', 'orders.status'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'orders.id';
        }

        $allowedStatuses = ['assigned', 'accepted', 'rejected', 'on_the_way', 'arrived', 'started', 'booking_ended', 'completed'];

        $ordersModel = model(Orders_model::class);
        $ordersModel
            ->select('orders.id, orders.user_id, orders.date_of_service, orders.starting_time, orders.ending_time, orders.address, orders.address_id, orders.final_total, orders.payment_status, orders.remarks, bh.status AS handyman_status, u.username AS customer_name, u.phone AS customer_phone, pd.address AS provider_address')
            ->join('users u', 'u.id = orders.user_id', 'left')
            ->join('booking_handymen bh', 'bh.order_id = orders.id', 'inner')
            ->join('partner_details pd', 'pd.partner_id = orders.partner_id', 'left')
            ->where('bh.handyman_id', $handymanId);

        if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
            $ordersModel->where('bh.status', $statusFilter);
        }

        if ($search !== '') {
            $ordersModel->groupStart()
                ->like('orders.id', $search)
                ->orLike('u.username', $search)
                ->orLike('orders.address', $search)
                ->orLike('orders.status', $search)
                ->groupEnd();
        }

        $total = $ordersModel->countAllResults(false);
        $rows = $ordersModel->orderBy($sort, $order)->limit($limit, $offset)->findAll();

        $statusColors = [
            'assigned' => 'secondary',
            'accepted' => 'primary',
            'rejected' => 'danger',
            'on_the_way' => 'info',
            'arrived' => 'warning',
            'started' => 'warning',
            'booking_ended' => 'warning',
            'completed' => 'success',
        ];
        $statusLabels = [
            'assigned' => labels('assigned', 'Assigned'),
            'accepted' => labels('accepted', 'Accepted'),
            'rejected' => labels('rejected', 'Rejected'),
            'on_the_way' => labels('on_the_way', 'On The Way'),
            'arrived' => labels('arrived', 'Arrived'),
            'started' => labels('started', 'Started'),
            'booking_ended' => labels('booking_ended', 'Booking Ended'),
            'completed' => labels('completed', 'Completed'),
        ];

        foreach ($rows as &$row) {
            $status = $row['handyman_status'] ?? 'assigned';
            $color = $statusColors[$status] ?? 'secondary';
            $statusText = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));

            $row['status_badge'] = '<span class="badge bg-' . $color . '">' . $statusText . '</span>';
            $row['amount_display'] = number_format((float) $row['final_total'], 2);
            $row['service_date'] = !empty($row['date_of_service']) ? date('M d, Y', strtotime($row['date_of_service'])) : '-';
            $row['time_range'] = trim(($row['starting_time'] ?? '') . ' - ' . ($row['ending_time'] ?? ''));
            $isAtStore = ((int) ($row['address_id'] ?? 1)) === 0;
            $resolvedAddress = $isAtStore ? ($row['provider_address'] ?? $row['address']) : ($row['address'] ?? '');
            $row['address_short'] = mb_strimwidth($resolvedAddress, 0, 40, '...');
            $row['remarks_short'] = !empty($row['remarks']) ? esc(mb_strimwidth($row['remarks'], 0, 40, '...')) : '-';
            $row['service_type_badge'] = $isAtStore
                ? '<span class="badge bg-info">' . labels('at_store', 'At Store') . '</span>'
                : '<span class="badge bg-secondary">' . labels('doorstep', 'Doorstep') . '</span>';
            unset($row['address_id'], $row['provider_address'], $row['remarks']);
            $row['operations'] = '<button class="btn btn-sm btn-light border btn-view-booking" title="' . labels('view_details', 'View Details') . '">'
                . '<i class="fas fa-eye"></i></button> '
                . '<button class="btn btn-sm btn-light border btn-chat-booking" data-user-id="' . (int) ($row['user_id'] ?? 0) . '" title="' . labels('chat', 'Chat') . '">'
                . '<i class="fas fa-comment-dots"></i></button>';
            unset($row['user_id']);
        }
        unset($row);

        return $this->response->setJSON(['total' => $total, 'rows' => $rows]);
    }

    public function live_tracking($order_id = 0)
    {
        try {
            if (!$this->isLoggedIn) {
                return redirect()->to(base_url('handyman/login'));
            }

            $order_id = (int) $order_id;
            if ($order_id <= 0) {
                return redirect()->to(base_url('handyman/bookings'));
            }


            $order = model(Orders_model::class)
                ->select('id, status, address, order_latitude, order_longitude')
                ->where('id', $order_id)
                ->first();

            if (!empty($order)) {
                $order['invoice_no'] = 'INV-' . $order['id'];
            }

            if (empty($order)) {
                return redirect()->to(base_url('handyman/bookings'));
            }

            $liveTracking = model(LiveTrackingModel::class)
                ->where('order_id', $order_id)
                ->first();

            $this->data['title'] = labels('live_tracking', 'Live Tracking');
            $this->data['breadcrumbs'] = [
                ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
                ['label' => labels('bookings', 'Bookings'), 'url' => base_url('handyman/bookings'), 'icon' => 'fas fa-list-alt'],
                ['label' => labels('booking_details', 'Booking Details'), 'url' => base_url('handyman/bookings/veiw_bookings/' . $order_id), 'icon' => 'fas fa-receipt'],
                ['label' => labels('live_tracking', 'Live Tracking')]
            ];
            $this->data['order'] = $order;
            $this->data['live_tracking'] = $liveTracking;
            $this->data['tracker_type'] = 'handyman';
            $this->data['handyman_name'] = $this->data['user']['username'] ?? labels('handyman', 'Handyman');

            return view('backend/handyman/pages/live_tracking', $this->data);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Bookings.php - live_tracking()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return redirect()->to(base_url('handyman/bookings'));
        }
    }

    public function tracker_location($order_id = 0)
    {
        if (!$this->isLoggedIn) {
            return $this->response->setJSON(['error' => true])->setStatusCode(403);
        }

        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return $this->response->setJSON(['error' => true]);
        }

        $orderRow = model(Orders_model::class)
            ->select('status')
            ->where('id', $order_id)
            ->first();

        if (empty($orderRow)) {
            return $this->response->setJSON(['error' => true])->setStatusCode(403);
        }

        $orderStatus = $orderRow['status'] ?? '';

        $leadRow = model(BookingHandymenModel::class)
            ->select('status')
            ->where('order_id', $order_id)
            ->where('is_lead', 1)
            ->first();
        $handymanStatus = $leadRow['status'] ?? '';

        $cacheKey = 'live_tracking_order_' . $order_id;
        $cached = cache($cacheKey);
        if ($cached !== null) {
            $cached['handyman_status'] = $handymanStatus;
            $cached['tracker_type'] = 'handyman';
            $cached['order_status'] = $orderStatus;
            return $this->response->setJSON($cached);
        }

        $liveTracking = model(LiveTrackingModel::class)
            ->select('latitude, longitude, updated_at')
            ->where('order_id', $order_id)
            ->first();

        if (empty($liveTracking)) {
            return $this->response->setJSON(['error' => true, 'message' => 'no_data', 'handyman_status' => $handymanStatus, 'tracker_type' => 'handyman', 'order_status' => $orderStatus]);
        }

        $result = [
            'error' => false,
            'latitude' => $liveTracking['latitude'],
            'longitude' => $liveTracking['longitude'],
            'updated_at' => $liveTracking['updated_at'],
            'handyman_status' => $handymanStatus,
            'tracker_type' => 'handyman',
            'order_status' => $orderStatus
        ];

        cache()->save($cacheKey, $result, 30);
        return $this->response->setJSON($result);
    }
}
