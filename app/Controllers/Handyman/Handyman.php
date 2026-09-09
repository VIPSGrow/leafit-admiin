<?php

namespace App\Controllers\Handyman;

use App\Controllers\BaseController;

class Handyman extends BaseController
{
    protected $validation;
    protected $data;
    protected $userId;

    public function __construct()
    {
        helper('function, form, url, ResponceServices');
        $this->validation = \Config\Services::validation();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->data['settings'] = $this->settings;

        $session = session();
        $lang = $session->get('lang') ?: 'en';
        $this->data['current_lang'] = $lang;

        $languageModel = new \App\Models\Language_model();
        $allLanguages = fetch_details('languages', [], [], null, '0', 'id', 'ASC');
        $availableLanguages = [];
        foreach ($allLanguages as $row) {
            if ($languageModel->hasLanguageFilesForProviderApp($row['code'])) {
                $availableLanguages[] = $row;
            }
        }
        $this->data['languages_locale'] = $availableLanguages;

        $this->userId = $session->get('user_id');
    }
}
