<?php

namespace App\Controllers\Apis;

use App\Controllers\BaseController;

class CountryCodesApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_country_codes()
    {
        try {
            $country_codes = fetch_details('country_codes');
            $fileService = service('fileService');
            $is_demo_mode = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;
            foreach ($country_codes as $key => $country_code) {
                $country_codes[$key]['flag_image'] = $fileService->url($country_code['flag_image'] ?? '', 'country_flags');
                // In demo mode, force default country code to +91 in the API response (for consistent demo experience)
                if ($is_demo_mode) {
                    $country_codes[$key]['is_default'] = (isset($country_code['calling_code']) && trim((string) $country_code['calling_code']) === '+91') ? 1 : 0;
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(COUNTRY_CODES_FETCHED_SUCCESSFULLY, 'Country codes fetched successfully'),
                'data' => $country_codes,
            ]);
        } catch (\Throwable $th) {
            log_message('error', "CountryCodesApiController::get_country_codes() - \n Authorization: " . $this->request->header('Authorization') . " \n Params: " . json_encode($_POST) . " \n Issue: " . $th->getMessage() . " \n Trace: " . $th->getTraceAsString());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }
}
