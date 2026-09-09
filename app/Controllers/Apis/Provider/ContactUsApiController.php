<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;

class ContactUsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
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
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function contact_us_api()
    {
        $validation = \Config\Services::validation();
        $validation->setRules(
            [
                'name' => 'required',
                'subject' => 'required',
                'message' => 'required',
                'email' => 'required'
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
        $name = $_POST['name'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $email = $_POST['email'];
        $admin_contact_query = [
            'name' => $name,
            'subject' => $subject,
            'message' => $message,
            'email' => isset($email) ? $email : "0",
        ];
        insert_details($admin_contact_query, 'admin_contact_query');
        $response['error'] = false;
        $response['message'] = labels(QUERY_SEND_SUCCESSFULLY, "Query send successfully");
        $response['data'] = $admin_contact_query;
        return $this->response->setJSON($response);
    }
}
