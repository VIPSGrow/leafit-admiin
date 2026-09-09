<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Tax_model;
use App\Libraries\JWT;

class TaxApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $toDateTime;
    protected $user_details = [];
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
        $this->JWT = new JWT();
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

    public function get_taxes()
    {
        try {
            $taxes = new Tax_model();
            $limit = $this->request->getPost('limit');
            $offset = $this->request->getPost('offset');
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'ASC';
            $search = $this->request->getPost('search') ?: '';
            $where = [];
            if ($this->request->getPost('id')) {
                $where['id'] = $this->request->getPost('id');
            }
            $data = $taxes->list(true, $search, $limit, $offset, $sort, $order, $where);
            if (!empty($data['data'])) {
                // Add translated_tax_title for each tax (current language) using existing helper
                if (function_exists('get_taxes_with_translated_names')) {
                    $whereForTranslated = array_merge($where, ['status' => 1]);
                    $translatedList = get_taxes_with_translated_names($whereForTranslated, ['id', 'title', 'percentage']);
                    $translatedTitleById = [];
                    foreach ($translatedList as $t) {
                        $translatedTitleById[(int) $t['id']] = $t['title'] ?? '';
                    }
                    foreach ($data['data'] as &$row) {
                        $row['translated_title'] = $translatedTitleById[(int) $row['id']] ?? $row['title'];
                    }
                    unset($row);
                } else {
                    foreach ($data['data'] as &$row) {
                        $row['translated_title'] = $row['title'];
                    }
                    unset($row);
                }
                return response_helper(labels(TAXES_FETCHED_SUCCESSFULLY, 'Taxes fetched successfully'), false, $data['data'], 200, ['total' => $data['total']]);
            } else {
                return response_helper(labels(TAXES_NOT_FOUND, 'Taxes not found'), false);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_taxes()');
            return $this->response->setJSON($response);
        }
    }
}
