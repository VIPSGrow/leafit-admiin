<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Service_ratings_model;

class RatingsApiController extends BaseController
{
    protected $request, $db;
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
        $this->db = \Config\Database::connect();
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

    public function get_service_ratings()
    {

        try {
            $partner_id = $this->user_details['id'];

            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $Service_id = ($this->request->getPost('service_id') != '') ? $this->request->getPost('service_id') : '';
            if (!empty($this->request->getPost('service_id'))) {
                $where = [" sr.service_id={$Service_id}"];
            } else {
                $where = [" s.user_id = {$partner_id}  OR  (pb.partner_id = {$partner_id} AND sr.custom_job_request_id IS NOT NULL)"];
            }
            $ratings = new Service_ratings_model();
            if ($partner_id != '') {
                $data = $ratings->ratings_list(true, $search, $limit, $offset, $sort, $order, $where);
            } else {
                $data = $ratings->ratings_list(true, $search, $limit, $offset, $sort, $order, $where);
            }
            $sort = (isset($_POST['sort']) && !empty($_POST['sort'])) ? $_POST['sort'] : 'id';
            usort($data['data'], function ($a, $b) use ($sort) {
                switch ($sort) {
                    case 'rating':
                        if ($a['rating'] === $b['rating']) {
                            return strtotime($b['rated_on']) - strtotime($a['rated_on']);
                        }
                        return $b['rating'] - $a['rating'];
                    case 'created_at':
                        return strtotime($b['rated_on']) - strtotime($a['rated_on']);
                    default:
                        return $a['id'] - $b['id'];
                }
            });
            if (!empty($Service_id)) {
                $rate_data = get_service_ratings($Service_id);
                $average_rating = $this->db->table('services s')
                    ->select(' 
                            (SUM(sr.rating) / count(sr.rating)) as average_rating
                            ')
                    ->join('services_ratings sr', 'sr.service_id = s.id')
                    ->where('s.id', $Service_id)
                    ->get()->getResultArray();
            } else {
                $rate_data = get_ratings($partner_id);

                $average_rating = $this->db->table('users p')
                    ->select('
                    (COALESCE(SUM(sr.rating), 0) + COALESCE(SUM(sr2.rating), 0)) / 
                    NULLIF((COUNT(sr.rating) + COUNT(sr2.rating)), 0) as average_rating,
                    MAX(GREATEST(COALESCE(sr.created_at, "1970-01-01"), 
                                COALESCE(sr2.created_at, "1970-01-01"))) as latest_rating_date
                ')
                    ->join('services s', 's.user_id = p.id', 'left')
                    ->join('services_ratings sr', 'sr.service_id = s.id', 'left')
                    // Custom job ratings
                    ->join('partner_bids pb', 'pb.partner_id = p.id', 'left')
                    ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id', 'left')
                    ->join('services_ratings sr2', 'sr2.custom_job_request_id = cj.id', 'left')
                    ->where('p.id', $partner_id)
                    ->orderBy('average_rating', 'desc')
                    ->orderBy('latest_rating_date', 'desc')
                    ->orderBy('p.id', 'asc')
                    ->get()->getResultArray();
            }
            $ratingData = array();
            $rows = array();
            $tempRow = array();
            foreach ($average_rating as $row) {
                $tempRow['average_rating'] = (isset($row['average_rating']) && $row['average_rating'] != "") ? $row['average_rating'] : 0;
            }
            foreach ($rate_data as $row) {
                $tempRow['total_ratings'] = (isset($row['total_ratings']) && $row['total_ratings'] != "") ? $row['total_ratings'] : 0;
                $tempRow['rating_5'] = (isset($row['rating_5']) && $row['rating_5'] != "") ? $row['rating_5'] : 0;
                $tempRow['rating_4'] = (isset($row['rating_4']) && $row['rating_4'] != "") ? $row['rating_4'] : 0;
                $tempRow['rating_3'] = (isset($row['rating_3']) && $row['rating_3'] != "") ? $row['rating_3'] : 0;
                $tempRow['rating_2'] = (isset($row['rating_2']) && $row['rating_2'] != "") ? $row['rating_2'] : 0;
                $tempRow['rating_1'] = (isset($row['rating_1']) && $row['rating_1'] != "") ? $row['rating_1'] : 0;
                $rows[] = $tempRow;
            }
            $ratingData = $rows;
            $response = [
                'error' => false,
                'message' => labels(DATA_RETRIEVED_SUCCESSFULLY, 'Data Retrieved successfully!'),
                'ratings' => $ratingData,
                'total' => $data['total'],
                'data' => remove_null_values($data['data']),
            ];
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_service_ratings()');
            return $this->response->setJSON($response);
        }
    }
}
