<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Bookmarks_model;
use App\Models\Partners_model;
use App\Libraries\JWT;

class BookmarkApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
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

    public function bookmark()
    {
        try {
            $book_marks = new Bookmarks_model();
            $validation = \Config\Services::validation();
            $user_id = $this->user_details['id'];
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('soft'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where = ['b.user_id' => $user_id];
            $rules = [
                'type' => [
                    "rules" => 'required|in_list[add,remove,list]',
                    "errors" => [
                        "required" => "Type is required",
                        "in_list" => "Type value is incorrect",
                    ],
                ],
            ];
            if ($this->request->getPost('type') == "list") {
                $rules['latitude'] = [
                    "rules" => 'required',
                ];
                $rules['longitude'] = [
                    "rules" => 'required',
                ];
            }
            $validation->setRules($rules);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $type = $this->request->getPost('type');
            if ($type == 'add' || $type == "remove") {
                $validation->setRules(
                    [
                        'partner_id' => 'required',
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
            }
            $partner_id = $this->request->getPost('partner_id');
            $is_booked = is_bookmarked($user_id, $partner_id)[0]['total'];
            $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id]);
            $data = [
                'user_id' => $user_id,
                'partner_id' => $partner_id,
            ];
            if ($type == 'add' && !empty($partner_details)) {
                if ($is_booked == 0) {
                    if ($book_marks->save($data)) {
                        return response_helper(labels(ADDED_TO_BOOK_MARKS, 'Added to book marks'), false, [], 200);
                    } else {
                        return response_helper(labels(COULD_NOT_ADD_TO_THE_BOOK_MARKS, 'Could not add to the book marks'), true, [], 200);
                    }
                } else {
                    return response_helper(labels(THIS_PARTNER_IS_ALREADY_BOOKMARKED, 'This partner is already bookmarked'), true, [], 200);
                }
            } else if ($type == 'remove' && !empty($partner_details)) {
                $remove = delete_bookmark($user_id, $partner_id);
                if ($is_booked > 0) {
                    if ($remove) {
                        return response_helper(labels(REMOVED_FROM_BOOK_MARKS, 'Removed from book marks'), false, [], 200);
                    } else {
                        return response_helper(labels(COULD_NOT_REMOVE_FROM_BOOK_MARKS, 'Could not remove form'), true, [], 200);
                    }
                } else {
                    return response_helper(labels(NO_PARTNER_SELECTED, 'No partner selected'), true, [], 200);
                }
            } elseif ($type == "list") {
                $Partners_model = new Partners_model();
                $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
                $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
                $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('sort'))) ? $this->request->getPost('sort') : 'id';
                $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
                $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
                $where = $additional_data = [];
                $where['is_approved'] = 1;
                $filter = ($this->request->getPost('filter') && !empty($this->request->getPost('filter'))) ? $this->request->getPost('filter') : '';
                $customer_id = $this->user_details['id'];
                $settings = get_settings('general_settings', true);
                if (($this->request->getPost('latitude') && !empty($this->request->getPost('latitude')) && ($this->request->getPost('longitude') && !empty($this->request->getPost('longitude')))) && $customer_id != '') {
                    $additional_data = [
                        'latitude' => $this->request->getPost('latitude'),
                        'longitude' => $this->request->getPost('longitude'),
                        'customer_id' => $customer_id,
                        'max_serviceable_distance' => $settings['max_serviceable_distance'],
                    ];
                }
                $partner_ids = favorite_list($user_id);
                if (!empty($partner_ids)) {
                    $data = $Partners_model->list(true, $search, $limit, $offset, $sort, $order, $where, 'pd.partner_id', $partner_ids, $additional_data);
                }
                $user = ['user_id' => $user_id];
                if (!empty($data['data'])) {
                    for ($i = 0; $i < count($data['data']); $i++) {
                        unset($data['data'][$i]['national_id'], $data['data'][$i]['admin_commission'], $data['data'][$i]['advance_booking_days'], $data['data'][$i]['passport'], $data['data'][$i]['tax_name'], $data['data'][$i]['tax_number'], $data['data'][$i]['bank_name'], $data['data'][$i]['account_number'], $data['data'][$i]['account_name'], $data['data'][$i]['bank_code'], $data['data'][$i]['swift_code'], $data['data'][$i]['type']);
                        array_merge($data['data'][$i], $user);
                    }
                    return response_helper(labels(BOOKMARKS_RETRIEVED_SUCCESSFULLY, 'Bookmarks Retrieved successfully'), false, remove_null_values($data['data']), 200, ['total' => $data['total']]);
                } else {
                    return response_helper(labels(NO_BOOKMARKS_FOUND, 'No Bookmarks found'), false);
                }
                $data = $book_marks->list(true, $search, $limit, $offset, $sort, $order, $where);
                return response_helper(labels(DATA_RETRIEVED_SUCCESSFULLY, 'Data Retrived successfully'), false, remove_null_values($data['data']), 200, ['total' => $data['total']]);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - book_mark()');
            return $this->response->setJSON($response);
        }
    }
}
