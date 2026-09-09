<?php

namespace App\Controllers\Apis\Handyman;

use App\Controllers\BaseController;
use App\Models\HandymanDetailsModel;
use App\Models\BookingHandymenModel;
use App\Models\HandymanCashCollectionModel;
use CodeIgniter\I18n\Time;

class DashboardApiController extends BaseController
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

    public function get_dashboard()
    {
        try {
            $handymanId = (int) $this->user_details['id'];

            $today = Time::today();
            $tomorrow = $today->addDays(1);
            $dayAfterTomorrow = $today->addDays(2);

            $handymanModel = model(HandymanDetailsModel::class);
            $bookingHandymenModel = model(BookingHandymenModel::class);
            $cashModel = model(HandymanCashCollectionModel::class);

            $detail = $handymanModel->where('handyman_id', $handymanId)->first();
            $summary = $bookingHandymenModel->getDashboardSummary(
                $handymanId,
                $today->toDateString(),
                $tomorrow->toDateString(),
                $dayAfterTomorrow->toDateString()
            );
            $recentCompleted = $bookingHandymenModel->getRecentCompleted($handymanId, 5);

            $currency = get_currency();

            foreach ($recentCompleted as &$row) {
                $row['final_total'] = (float) $row['final_total'];
                $row['date_of_service'] = !empty($row['date_of_service']) ? date('Y-m-d', strtotime($row['date_of_service'])) : null;
            }
            unset($row);

            $data = [
                'salary' => $detail['salary'] ?? null,
                'currency' => $currency,
                'total_bookings' => $summary['total_bookings'],
                'lead_bookings' => $summary['lead_bookings'],
                'bookings' => [
                    'today_bookings' => $summary['today_bookings'],
                    'tomorrow_bookings' => $summary['tomorrow_bookings'],
                    'upcoming_bookings' => $summary['upcoming_bookings'],
                ],
                'cash_collection' => [
                    'currency' => $currency,
                    'overall_total' => $cashModel->getOverallTotal($handymanId),
                    'outstanding_total' => $cashModel->getOutstandingTotal($handymanId),
                ],
                'recent_completed_bookings' => $recentCompleted,
            ];

            return ApiSuccess('data_fetched_successfully', $data);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/DashboardApiController.php - get_dashboard()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }
}
