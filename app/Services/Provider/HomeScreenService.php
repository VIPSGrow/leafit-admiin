<?php

namespace App\Services\Provider;

use App\Models\Custom_job_request_model;
use App\Models\Orders_model;
use App\Models\Partner_subscription_model;
use App\Models\Partners_model;
use App\Models\Users_model;
use CodeIgniter\I18n\Time;

class HomeScreenService
{
    protected Orders_model $orders;
    protected Custom_job_request_model $customJobs;
    protected Partner_subscription_model $partnerSubscriptions;
    protected Partners_model $partners;
    protected Users_model $users;

    public function __construct()
    {
        $this->orders               = new Orders_model();
        $this->customJobs           = new Custom_job_request_model();
        $this->partnerSubscriptions = new Partner_subscription_model();
        $this->partners             = new Partners_model();
        $this->users                = new Users_model();
    }

    public function activeSubscription(int $partnerId): ?array
    {
        $row = $this->partnerSubscriptions->activeForPartnerWithTranslation(
            $partnerId,
            get_current_language_from_request(),
            get_default_language()
        );

        if (empty($row) || ($row['status'] ?? 'deactive') !== 'active') {
            return null;
        }

        $price = calculate_partner_subscription_price(
            $row['partner_id'],
            $row['subscription_id'],
            $row['id']
        );

        return [
            'subscription_id'         => $row['subscription_id'] ?? '',
            'isSubscriptionActive'    => $row['status']          ?? 'deactive',
            'created_at'              => $row['created_at']      ?? '',
            'updated_at'              => $row['updated_at']      ?? '',
            'is_payment'              => $row['is_payment']      ?? '',
            'id'                      => $row['id']              ?? '',
            'partner_id'              => $row['partner_id']      ?? '',
            'purchase_date'           => $row['purchase_date']   ?? '',
            'expiry_date'             => $row['expiry_date']     ?? '',
            'name'                    => $row['name']            ?? '',
            'description'             => $row['description']     ?? '',
            'duration'                => $row['duration']        ?? '',
            'price'                   => $row['price']           ?? '',
            'discount_price'          => $row['discount_price']  ?? '',
            'order_type'              => $row['order_type']      ?? '',
            'max_order_limit'         => $row['max_order_limit'] ?? '',
            'is_commision'            => $row['is_commision']    ?? '',
            'commission_threshold'    => $row['commission_threshold']    ?? '',
            'commission_percentage'   => $row['commission_percentage']   ?? '',
            'publish'                 => $row['publish']  ?? '',
            'tax_id'                  => $row['tax_id']   ?? '',
            'tax_type'                => $row['tax_type'] ?? '',
            'tax_value'               => $price[0]['tax_value']               ?? '',
            'price_with_tax'          => $price[0]['price_with_tax']          ?? '',
            'original_price_with_tax' => $price[0]['original_price_with_tax'] ?? '',
            'tax_percentage'          => $price[0]['tax_percentage']          ?? '',
        ];
    }

    public function bookingCounts(int $partnerId): array
    {
        $today = Time::today();

        return $this->orders->bookingCountsForPartner(
            $partnerId,
            $today->toDateString(),
            $today->addDays(1)->toDateString(),
            $today->addDays(2)->toDateString()
        );
    }

    public function earningReport(int $partnerId): array
    {
        $earningSummary = $this->users->getEarningSummary($partnerId);

        $unsettledTotal = $this->orders->unsettledAwaitingTotalForPartner($partnerId);
        $commissionPct  = (float) get_admin_commision($partnerId);
        $futureEarning  = $unsettledTotal - ($unsettledTotal * ($commissionPct / 100));

        return [
            'admin_commission'             => $earningSummary['admin_commission'],
            'my_income'                    => strval(unsettled_commision($partnerId)),
            'remaining_income'             => $earningSummary['balance'],
            'future_earning_from_bookings' => (float) $futureEarning,
        ];
    }

    public function openCustomJobs(int $partnerId): array
    {
        $preferences = $this->partners->getCustomJobPreferences($partnerId);
        $categoryIds = $preferences['custom_job_categories'];

        $jobs        = $this->customJobs->openForPartner($partnerId, $categoryIds);
        $fileService = service('fileService');
        
        foreach ($jobs as &$job) {
            $job['image'] = !empty($job['image'])
                ? $fileService->url($job['image'], 'profile')
                : '';

            $job['category_image'] = !empty($job['category_image'])
                ? $fileService->url($job['category_image'], 'categories')
                : '';

            $files = [];
            if (!empty($job['files'])) {
                $decoded = json_decode($job['files'], true);
                if (is_array($decoded)) {
                    $files = array_map(fn($f) => $fileService->url($f, 'custom_job_requests'), $decoded);
                }
            }
            $job['files'] = $files;
        }
        unset($job);

        return [
            'total_open_jobs' => count($jobs),
            'open_jobs'       => array_slice($jobs, 0, 2),
        ];
    }

    public function salesCharts(int $partnerId, int $months): array
    {
        $months = $months > 0 ? $months : 12;

        $monthly = $this->orders->monthlySalesForPartner($partnerId, $months);
        foreach ($monthly as &$sale) {
            $sale['month'] = labels(strtolower($sale['month']), $sale['month']);
        }
        unset($sale);

        return [
            'monthly_sales' => $monthly,
            'yearly_sales'  => $this->orders->yearlySalesForPartner($partnerId),
            'weekly_sales'  => $this->orders->weeklySalesForPartner($partnerId),
        ];
    }
}
