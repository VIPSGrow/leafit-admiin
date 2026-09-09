<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;

class HomeScreenApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    private  $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1"
    ];
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_home_data()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $months    = (int) ($this->request->getPost('last_monthly_sales') ?: 12);

            $home = service('providerHomeScreenService');

            return $this->response->setJSON([
                'error'   => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'data fetched successfully'),
                'data'    => [
                    'subscription_information' => $home->activeSubscription($partnerId),
                    'bookings'                 => $home->bookingCounts($partnerId),
                    'earning_report'           => $home->earningReport($partnerId),
                    'custom_jobs'              => $home->openCustomJobs($partnerId),
                    'sales_data'               => $home->salesCharts($partnerId, $months),
                ],
            ]);
        } catch (\Throwable $th) {
            log_message('error', 'HomeScreenApiController::get_home_data Params: {params} Exception: {ex}', [
                'params' => json_encode($this->request->getPost()),
                'ex'     => (string) $th,
            ]);
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }
}
