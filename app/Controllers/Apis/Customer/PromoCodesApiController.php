<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Promo_code_model;
use App\Libraries\JWT;

class PromoCodesApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/get_promo_codes",
    ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        } else {
            $token = verify_app_request();
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_promo_codes()
    {
        try {
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where = [];
            $partner_id = $this->request->getPost('partner_id');
            $slug = $this->request->getPost('provider_slug');

            if (empty($partner_id) && empty($slug)) {
                return response_helper(labels(PARTNER_ID_OR_PROVIDER_SLUG_IS_REQUIRED, 'Either partner_id or provider_slug is required'));
            }
            if (!empty($partner_id) && $this->request->getPost('partner_id')) {
                $where = ['pc.partner_id' => $partner_id, 'pc.status' => 1, ' start_date <= ' => date('Y-m-d'), '  end_date >= ' => date('Y-m-d')];
            }
            if (!empty($slug) && $this->request->getPost('provider_slug')) {
                $where = ['pd.slug' => $slug, 'pc.status' => 1, ' start_date <= ' => date('Y-m-d'), '  end_date >= ' => date('Y-m-d')];
            }

            // Get current language from request for translations
            $languageCode = get_current_language_from_request();

            $promo_codes_model = new Promo_code_model();
            $promo_codes = $promo_codes_model->list(true, $search, null, null, $limit, $order, $where, $languageCode);

            if (!empty($promo_codes['data'])) {
                // The model now provides translated fields as separate fields
                return response_helper(labels(PROMO_CODES_FETCHED_SUCCESSFULLY, 'promo codes fetched successfully'), false, remove_null_values($promo_codes['data']), 200, ['total' => $promo_codes['total']]);
            } else {
                return response_helper(labels(DATA_NOT_FOUND, 'Data Not Found'));
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_promo_codes()');
            return $this->response->setJSON($response);
        }
    }

    public function validate_promo_code()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'promo_code_id' => 'required',
                    'final_total' => 'required|numeric',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $promo_code = $this->request->getPost('promo_code_id');
            $final_total = $this->request->getPost('final_total');

            $fetch_promococde = fetch_details('promo_codes', ['id' => $promo_code]);
            $promo_code = validate_promo_code($this->user_details['id'], $fetch_promococde[0]['id'], $final_total);
            if ($promo_code['error'] == false) {
                return response_helper($promo_code['message'], false, remove_null_values($promo_code['data']));
            } else {
                return response_helper($promo_code['message']);
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            return $this->response->setJSON($response);
        }
    }
}
