<?php

namespace App\Controllers\Apis\Handyman;

use App\Controllers\BaseController;
use App\Models\BookingHandymenModel;
use App\Models\LiveTrackingModel;
use App\Models\Orders_model;
use App\Models\Transaction_model;
use App\Models\Users_model;

class BookingsApiController extends BaseController
{
    protected $user_details = [];

    // Next allowed status is driven by orders.status (single source of truth shared
    // across all assigned handymen), except for the pre-acceptance step which lives
    // only on the handyman's own booking_handymen row.
    private static function getNextStatuses(string $handymanStatus, string $orderStatus, bool $isAtStore): array
    {
        if ($handymanStatus === 'assigned') {
            return ['accepted' /*, 'rejected' */];
        }
        if (\in_array($handymanStatus, ['rejected', 'completed'], true)) {
            return [];
        }

        $orderTransitions = [
            'confirmed' => [$isAtStore ? 'started' : 'on_the_way'],
            'on_the_way' => ['arrived'],
            'arrived' => ['started'],
            'started' => ['booking_ended', 'completed'],
            'booking_ended' => ['completed'],
        ];

        return $orderTransitions[$orderStatus] ?? [];
    }

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

    public function update_status()
    {
        try {
            $handymanId = (int) $this->user_details['id'];
            $orderId = (int) $this->request->getPost('order_id');
            $newStatus = trim((string) $this->request->getPost('status'));

            if ($orderId <= 0) {
                return ApiError('data_not_found');
            }

            $allowed = ['accepted', 'rejected', 'on_the_way', 'arrived', 'started', 'booking_ended', 'completed'];
            if (!\in_array($newStatus, $allowed, true)) {
                return ApiError('invalid_status_passed');
            }

            $bookingHandymenModel = model(BookingHandymenModel::class);
            $row = $bookingHandymenModel
                ->where('order_id', $orderId)
                ->where('handyman_id', $handymanId)
                ->first();

            if (empty($row)) {
                return ApiError('data_not_found');
            }

            if (empty($row['is_lead'])) {
                return ApiError('only_lead_can_update_status');
            }

            $ordersModel = model(Orders_model::class);
            $order = $ordersModel->select('status, payment_method, address_id')->where('id', $orderId)->first();
            if (!empty($order) && ($order['payment_method'] ?? '') !== 'cod') {
                $transaction = model(Transaction_model::class)
                    ->select('status')
                    ->where('order_id', $orderId)
                    ->where('transaction_type', 'transaction')
                    ->first();
                if (($transaction['status'] ?? '') !== 'success') {
                    return ApiError('payment_pending');
                }
            }

            $isAtStore = !empty($order) && ((int) ($order['address_id'] ?? 1)) === 0;
            $current = $row['status'];
            $allowedNext = self::getNextStatuses($current, $order['status'] ?? '', $isAtStore);
            if (!\in_array($newStatus, $allowedNext, true)) {
                return ApiError('invalid_status_transition');
            }

            if ($newStatus === 'started') {
                $uploadedFiles = $this->request->getFiles() ?: null;
                $response = validate_order_status($orderId, 'started', '', '', null, $uploadedFiles, null, $handymanId, null, 'handyman');
                if (!empty($response['error'])) {
                    return ApiError($response['message']);
                }
            } elseif ($newStatus === 'booking_ended') {
                $additionalChargesRaw = $this->request->getPost('booking_ended_additional_charges') ?? '';
                $additionalCharges = is_array($additionalChargesRaw)
                    ? $additionalChargesRaw
                    : ($additionalChargesRaw !== '' ? json_decode($additionalChargesRaw, true) : null);
                $uploadedFiles = $this->request->getFiles() ?: null;
                $workProof = !empty($uploadedFiles['work_complete_files']) ? ['work_complete_files' => $uploadedFiles['work_complete_files']] : null;
                $response = validate_order_status($orderId, 'booking_ended', '', '', '', $workProof, $additionalCharges ?: null, $handymanId, null, 'handyman');
                if (!empty($response['error'])) {
                    return ApiError($response['message']);
                }
            } elseif ($newStatus === 'completed') {
                $otp = trim((string) $this->request->getPost('otp'));
                $response = validate_order_status($orderId, 'completed', '', '', $otp ?: null, null, null, $handymanId, null, 'handyman');
                if (!empty($response['error'])) {
                    return ApiError($response['message']);
                }
            }

            $update = ['status' => $newStatus];
            if ($newStatus === 'rejected') {
                $update['rejected_reason'] = trim((string) $this->request->getPost('rejected_reason'));
            }

            $db = \Config\Database::connect();
            $db->transStart();

            $bookingHandymenModel
                ->where('order_id', $orderId)
                ->set($update)
                ->update();

            if ($newStatus === 'on_the_way' || $newStatus === 'arrived') {
                $ordersModel->set(['status' => $newStatus, 'status_changed_by_type' => 'handyman', 'status_changed_by_id' => $handymanId])->where('id', $orderId)->update();
                send_booking_status_notifications($orderId, $newStatus, labels($newStatus), $order['status'] ?? '', get_current_language_from_request(), $handymanId);
            }

            if ($newStatus === 'on_the_way') {
                $latitude = trim((string) $this->request->getPost('latitude'));
                $longitude = trim((string) $this->request->getPost('longitude'));
                $this->recordLiveTrackingLocation($orderId, $handymanId, $latitude ?: null, $longitude ?: null);
            }

            $db->transComplete();
            if (!$db->transStatus()) {
                return ApiError('something_went_wrong');
            }

            return ApiSuccess('data_updated_successfully', null, ['new_status' => $newStatus]);
        } catch (\Throwable $th) {
            throw $th;
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/BookingsApiController.php - update_status()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }

    private function recordLiveTrackingLocation(int $orderId, int $userId, ?string $latitude = null, ?string $longitude = null): void
    {
        if (empty($latitude) || empty($longitude)) {
            $usersModel = model(Users_model::class);
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

    public function get_bookings()
    {
        try {
            $handymanId = (int) $this->user_details['id'];
            $limit = (int) ($this->request->getPost('limit') ?: 10);
            $offset = (int) ($this->request->getPost('offset') ?: 0);
            $search = trim((string) ($this->request->getPost('search') ?: ''));
            $sort = $this->request->getPost('sort') ?: 'orders.id';
            $order = strtoupper($this->request->getPost('order') ?: 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $status = trim((string) ($this->request->getPost('status') ?: ''));

            $allowedSorts = ['orders.id', 'orders.date_of_service', 'orders.final_total', 'orders.status'];
            if (!in_array($sort, $allowedSorts, true)) {
                $sort = 'orders.id';
            }

            $ordersModel = model(Orders_model::class);
            $ordersModel
                ->select('orders.id as order_id, orders.status AS order_status, orders.address_id, orders.date_of_service, orders.starting_time, orders.ending_time, orders.address, orders.final_total, orders.payment_method, t.status AS payment_status, bh.status AS handyman_status, bh.is_lead, u.id AS customer_id, u.username AS customer_name, u.phone AS customer_phone, u.country_code AS customer_country_code, pd.address AS provider_address')
                ->join('users u', 'u.id = orders.user_id', 'left')
                ->join('booking_handymen bh', 'bh.order_id = orders.id AND bh.handyman_id = ' . $handymanId, 'inner')
                ->join('transactions t', "t.order_id = orders.id AND t.transaction_type = 'transaction'", 'left')
                ->join('partner_details pd', 'pd.partner_id = orders.partner_id', 'left');

            if ($status !== '') {
                $ordersModel->where('orders.status', $status);
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
            $bookings = $ordersModel->orderBy($sort, $order)->limit($limit, $offset)->findAll();

            $currency = get_currency();
            $bookingHandymenModel = model(BookingHandymenModel::class);

            foreach ($bookings as &$row) {
                $row['invoice_no'] = 'INV-' . $row['order_id'];
                $row['translated_order_status'] = labels($row['order_status']);
                $row['translated_handyman_status'] = labels($row['handyman_status']);
                $row['currency'] = $currency;
                $row['payment_status'] = labels($ordersModel->getPaymentStatus($row['payment_method'], $row['order_status'], $row['payment_status'] ?? null));
                $row['is_lead'] = (bool) ($row['is_lead'] ?? false);
                $row['is_at_store'] = ((int) ($row['address_id'] ?? 1)) === 0;
                if ($row['is_at_store']) {
                    $row['address'] = $row['provider_address'] ?? $row['address'];
                }
                unset($row['address_id'], $row['provider_address']);
                $row['date_of_service'] = !empty($row['date_of_service'])
                    ? date('Y-m-d', strtotime($row['date_of_service']))
                    : null;

                $assigned_handymen = $bookingHandymenModel->getAssignedHandymen((int) $row['order_id']);
                usort($assigned_handymen, function($a, $b) {
                    return ($b['is_lead'] ?? 0) <=> ($a['is_lead'] ?? 0);
                });
                $row['assigned_handymen'] = $assigned_handymen;
            }
            unset($row);

            return ApiSuccess('data_fetched_successfully', $bookings, ['total' => $total]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/BookingsApiController.php - get_bookings()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }

    public function update_location()
    {
        try {
            $handymanId = (int) $this->user_details['id'];
            $orderId = (int) $this->request->getPost('order_id');
            $latitude = trim((string) $this->request->getPost('latitude'));
            $longitude = trim((string) $this->request->getPost('longitude'));

            if ($orderId <= 0) {
                return ApiError(labels('order_id_is_required', 'Order ID is required'));
            }
            if ($latitude === '' || $longitude === '') {
                return ApiError(labels('latitude_longitude_required', 'Latitude and longitude are required'));
            }

            $bookingHandymenModel = model(BookingHandymenModel::class);
            $assignment = $bookingHandymenModel
                ->where('order_id', $orderId)
                ->where('handyman_id', $handymanId)
                ->first();

            if (empty($assignment)) {
                return ApiError(labels(ORDER_NOT_FOUND, 'Order not found'));
            }

            if ((int) $assignment['is_lead'] !== 1) {
                return ApiError(labels('only_lead_handyman_can_update_location', 'Only the lead handyman can update location'));
            }

            if ($assignment['status'] !== 'on_the_way') {
                return ApiError(labels('order_not_on_the_way', 'Order is not on the way. Cannot track this order.'));
            }

            $liveTrackingModel = model(LiveTrackingModel::class);
            $existing = $liveTrackingModel->where('order_id', $orderId)->first();

            $trackingData = [
                'order_id' => $orderId,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];

            if (!empty($existing)) {
                $liveTrackingModel->where('order_id', $orderId)->set($trackingData)->update();
            } else {
                $liveTrackingModel->insert($trackingData);
            }

            cache()->delete('live_tracking_order_' . $orderId);

            return ApiSuccess(DATA_UPDATED_SUCCESSFULLY);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/BookingsApiController.php - update_location()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }

    public function get_booking_details()
    {
        try {
            $handymanId = (int) $this->user_details['id'];
            $orderId = (int) $this->request->getPost('order_id');

            if ($orderId <= 0) {
                return ApiError('data_not_found');
            }

            $ordersModel = model(Orders_model::class);
            $booking = $ordersModel
                ->select('orders.id as order_id, orders.status AS order_status, orders.address_id, orders.date_of_service, orders.starting_time, orders.ending_time, orders.address, orders.remarks, orders.total, orders.final_total, orders.payment_method, orders.work_started_proof, orders.work_completed_proof, orders.payment_method_of_additional_charge, orders.additional_charges, orders.total_additional_charge, orders.payment_status_of_additional_charge, orders.order_latitude AS latitude, orders.order_longitude AS longitude, orders.visiting_charges, t.status AS payment_status, u.id AS customer_id, u.username AS customer_name, u.phone AS customer_phone, u.country_code AS customer_country_code, u.email AS customer_email, bh.status AS handyman_status, bh.rejected_reason, bh.is_lead, pd.address AS provider_address')
                ->join('users u', 'u.id = orders.user_id', 'left')
                ->join('booking_handymen bh', 'bh.order_id = orders.id AND bh.handyman_id = ' . $handymanId, 'inner')
                ->join('transactions t', "t.order_id = orders.id AND t.transaction_type = 'transaction'", 'left')
                ->join('partner_details pd', 'pd.partner_id = orders.partner_id', 'left')
                ->where('orders.id', $orderId)
                ->first();

            if (empty($booking)) {
                return ApiError('data_not_found');
            }

            $isAtStore = ((int) ($booking['address_id'] ?? 1)) === 0;
            $isPaymentPending = ($booking['payment_status'] ?? '') !== 'success'
                && ($booking['payment_method'] ?? '') !== 'cod';

            $nextStatuses = ($isPaymentPending || empty($booking['is_lead']))
                ? []
                : self::getNextStatuses($booking['handyman_status'], $booking['order_status'], $isAtStore);

            $fileService = service('fileService');

            $workStartedProof = !empty($booking['work_started_proof'])
                ? json_decode($booking['work_started_proof'], true)
                : [];
            foreach ($workStartedProof as &$workStartedFile) {
                $workStartedFile = $fileService->url($workStartedFile, 'provider_work_evidence');
            }
            unset($workStartedFile);

            $workCompletedProof = !empty($booking['work_completed_proof'])
                ? json_decode($booking['work_completed_proof'], true)
                : [];
            foreach ($workCompletedProof as &$workCompletedFile) {
                $workCompletedFile = $fileService->url($workCompletedFile, 'provider_work_evidence');
            }
            unset($workCompletedFile);

            $additionalCharges = !empty($booking['additional_charges'])
                ? json_decode($booking['additional_charges'], true)
                : [];

            $assignedHandymen = model(BookingHandymenModel::class)->getAssignedHandymen($orderId);
            usort($assignedHandymen, function($a, $b) {
                return ($b['is_lead'] ?? 0) <=> ($a['is_lead'] ?? 0);
            });
            $services = $ordersModel->getBookingServices($orderId);

            $booking['is_lead'] = (bool) $booking['is_lead'];
            $booking['translated_order_status'] = labels($booking['order_status']);
            $booking['translated_handyman_status'] = labels($booking['handyman_status']);
            $booking['is_payment_pending'] = $isPaymentPending;
            $booking['total'] = (float) ($booking['total'] ?? 0);
            $booking['tax_amount'] = $ordersModel->getTotalTaxAmount($orderId);
            $booking['total_additional_charge'] = (float) ($booking['total_additional_charge'] ?? 0);
            $booking['visiting_charges'] = $isAtStore ? 0.0 : (float) ($booking['visiting_charges'] ?? 0);
            $booking['tax_type'] = $ordersModel->getOrderTaxType($orderId);
            $booking['currency'] = get_currency();
            $booking['invoice_no'] = 'INV-' . $booking['order_id'];
            $booking['date_of_service'] = !empty($booking['date_of_service'])
                ? date('Y-m-d', strtotime($booking['date_of_service']))
                : null;

            $booking['is_at_store'] = $isAtStore;
            if ($isAtStore) {
                $booking['address'] = $booking['provider_address'] ?? $booking['address'];
            }
            $booking['payment_status'] = labels($ordersModel->getPaymentStatus($booking['payment_method'], $booking['order_status'], $booking['payment_status'] ?? null));
            unset($booking['address_id'], $booking['provider_address']);

            unset($booking['work_started_proof'], $booking['work_completed_proof'], $booking['additional_charges']);
            $booking['next_statuses'] = $nextStatuses;
            $booking['work_started_proof'] = $workStartedProof;
            $booking['work_completed_proof'] = $workCompletedProof;
            $booking['additional_charges'] = $additionalCharges;
            $booking['assigned_handymen'] = $assignedHandymen;
            $booking['services'] = $services;

            return ApiSuccess('data_fetched_successfully', $booking);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/BookingsApiController.php - get_booking_details()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }
}
