<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\CustomIonAuth;

class Admin extends BaseController
{
    protected $ionAuth, $session, $data, $validation;
    public function __construct()
    {
        helper('function');
        $this->ionAuth = new CustomIonAuth();
        $this->session = \Config\Services::session();
        $this->validation = \Config\Services::validation();
        $this->updateUser();
        $this->data['admin'] = $this->userIsAdmin;
        $this->data['user'] = $this->user;
        $this->data['userId'] = $this->userId;
        $this->data['userIdentity'] = $this->userIdentity;
        $this->data['data'] = get_settings('general_settings', true);
        $session = session();
        $lang = $session->get('lang');
        if (empty($lang)) {
            $lang = 'en';
        }
        $this->data['current_lang'] = $lang;
        $languages_locale = fetch_details('languages', [], [], null, '0', 'id', 'ASC');
        $available_languages = [];
        $languageModel = new \App\Models\Language_model();

        foreach ($languages_locale as $row) {
            $code = $row['code'];
            // Check if language has both panel JSON file and PHP translation file
            if ($languageModel->hasPanelLanguageFiles($code)) {
                $available_languages[] = $row;
            }
        }
        $this->data['languages_locale'] = $available_languages;
        $data = fetch_details('users', ["id" => $this->userId]);
        $profile = '';
        if (!empty($data)) {
            $data = $data[0];
            if (!empty($data['image'])) {
                $profile = '<img alt="image" src="' . service('fileService')->url($data['image'], 'profile') . '" class="rounded-circle mr-1">';
            } elseif (isset($data['first_name'][0]) && !empty($data['first_name'][0]) && isset($data['last_name'][0]) && !empty($data['last_name'][0])) {
                $profile = '<figure class="avatar mb-2 avatar-sm mt-1" data-initial="' . strtoupper($data['first_name'][0]) . strtoupper($data['last_name'][0]) . '"></figure>';
            }
            $this->data['profile_picture'] = $profile;
        }
        $this->data['profile_picture'] = $profile;
    }
    public function delete_details()
    {
        $this->validation = \Config\Services::validation();
        $this->validation->setRules(
            [
                'id' => 'required|',
                'table' => 'required',
            ]
        );
        if (!$this->validation->withRequest($this->request)->run()) {
            $errors  = $this->validation->getErrors();
            $response['error'] = true;
            foreach ($errors as $e) {
                $response['message'] = $e;
            }
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = [];
            return $this->response->setJSON($response);
        }
        $data = [
            'id' => $_POST['id']
        ];
        if (delete_details($data, $_POST['table'])) {
            $response = [
                'error' => false,
                'message' => "data deleted successfully",
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ];
            return $this->response->setJSON($response);
        } else {
            $response = [
                'error' => true,
                'message' => "some error while delete data",
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ];
            return $this->response->setJSON($response);
        }
    }
}
