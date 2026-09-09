<?php

namespace App\Controllers\Handyman;

use App\Models\HandymanDetailsModel;
use App\Models\BookingHandymenModel;
use App\Models\HandymanCashCollectionModel;
use CodeIgniter\I18n\Time;

class Dashboard extends Handyman
{
    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
    }

    public function index()
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;

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
            $row['date_of_service'] = !empty($row['date_of_service']) ? date('M d, Y', strtotime($row['date_of_service'])) : '-';
            $row['time_range'] = trim(($row['starting_time'] ?? '') . ' - ' . ($row['ending_time'] ?? ''));
            
            $isAtStore = ((int) ($row['address_id'] ?? 1)) === 0;
            $row['address'] = $isAtStore ? ($row['provider_address'] ?? $row['address']) : ($row['address'] ?? '');
        }
        unset($row);

        $this->data['title'] = labels('handyman_dashboard', 'Handyman Dashboard');
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'icon' => 'fas fa-home-alt'],
        ];
        $this->data['currency'] = $currency;
        $this->data['salary'] = $detail['salary'] ?? null;
        $this->data['average_rating'] = (float) ($detail['average_rating'] ?? 0);
        $this->data['total_ratings'] = (int) ($detail['total_reviews'] ?? 0);
        $this->data['summary'] = $summary;
        $this->data['overall_cash_total'] = $cashModel->getOverallTotal($handymanId);
        $this->data['outstanding_cash_total'] = $cashModel->getOutstandingTotal($handymanId);
        $this->data['recent_completed_bookings'] = $recentCompleted;

        return view('backend/handyman/pages/dashboard', $this->data);
    }
}
