<?php

namespace App\Controllers\Handyman;

use App\Models\Country_code_model;
use App\Models\Language_model;
use App\Models\HandymanCustomFieldValuesModel;
use App\Models\HandymanDetailsModel;
use App\Models\TranslatedHandymanDetailsModel;
use App\Models\Users_model;

class Profile extends Handyman
{
    protected Country_code_model $countryCodeModel;
    protected Language_model $languageModel;
    protected HandymanCustomFieldValuesModel $cfValuesModel;
    protected HandymanDetailsModel $handymanDetailsModel;
    protected TranslatedHandymanDetailsModel $translatedHandymanModel;
    protected Users_model $usersModel;

    public function __construct()
    {
        helper(['function', 'ResponceServices']);
        parent::__construct();
        $this->countryCodeModel = new Country_code_model();
        $this->languageModel = new Language_model();
        $this->cfValuesModel = new HandymanCustomFieldValuesModel();
        $this->handymanDetailsModel = new HandymanDetailsModel();
        $this->translatedHandymanModel = new TranslatedHandymanDetailsModel();
        $this->usersModel = new Users_model();
    }

    public function index()
    {
        $this->data['title'] = labels('profile', 'Profile');
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
            ['label' => labels('profile', 'Profile')],
        ];

        $user_details = $this->usersModel->find($this->userId);

        $fileService = service('fileService');
        $user_details['image_url'] = !empty($user_details['image']) ? $fileService->url($user_details['image'], 'profile') : '';
        $this->data['user_details'] = $user_details;

        $handyman_details = $this->handymanDetailsModel->where('handyman_id', $this->userId)->first();
        $this->data['handyman_details'] = !empty($handyman_details) ? $handyman_details : [];

        $country_codes = $this->countryCodeModel->findAll();
        $system_country_code = $this->countryCodeModel->where('is_default', 1)->first();
        $default_calling_code = !empty($system_country_code['calling_code']) ? $system_country_code['calling_code'] : '+91';
        $single_country_code = count($country_codes) === 1;

        $this->data['country_codes'] = $country_codes ?: [];
        $this->data['default_calling_code'] = $default_calling_code;
        $this->data['single_country_code'] = $single_country_code;
        $this->data['single_country_data'] = $single_country_code ? $country_codes[0] : null;

        $this->data['handyman_custom_fields'] = $this->getCustomFields();

        $cfValues = $this->cfValuesModel->where('handyman_id', $this->userId)->findAll();
        $cfValuesMap = [];
        foreach ($cfValues as $val) {
            $cfValuesMap[(int) $val['custom_field_id']] = $val['value'];
        }
        foreach ($this->data['handyman_custom_fields'] as $field) {
            if ($field['field_type'] === 'file') {
                $cfId = $field['id'];
                if (!empty($cfValuesMap[$cfId])) {
                    $cfValuesMap[$cfId] = $fileService->url($cfValuesMap[$cfId], 'custom_fields');
                }
            }
        }
        $this->data['custom_field_values'] = $cfValuesMap;

        $this->data['languages'] = $this->languageModel
            ->select('id, language, is_default, code')
            ->orderBy('id', 'ASC')
            ->findAll();

        $db = \Config\Database::connect();
        $translations = $db->table('translated_handyman_details thd')
            ->select('thd.handyman_id, l.code AS language_code, thd.username')
            ->join('languages l', 'l.id = thd.language_id')
            ->where('thd.handyman_id', $this->userId)
            ->where('thd.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $translationMap = [];
        foreach ($translations as $t) {
            $translationMap[$t['language_code']] = $t['username'];
        }
        $this->data['translations'] = $translationMap;

        helper('function');
        $this->data['password_rules'] = get_password_rules();

        $this->data['currency'] = get_currency();

        return view('backend/handyman/pages/profile', $this->data);
    }

    public function update()
    {
        try {
            $defaultLangCode = get_default_language();
            $defaultUsername = trim((string) ($this->request->getPost('username')[$defaultLangCode] ?? ''));

            $this->validation->setRules([
                'phone' => [
                    'rules' => 'required|numeric',
                    'errors' => [
                        'required' => labels('please_enter_phone_number', 'Please enter phone number'),
                        'numeric' => labels('please_enter_numeric_phone_number', 'Please enter numeric phone number'),
                    ],
                ],
                'email' => [
                    'rules' => 'permit_empty|valid_email',
                    'errors' => ['valid_email' => labels('invalid_email', 'Invalid email address')],
                ],
            ]);

            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return JsonError($errors);
            }

            if ($defaultUsername === '') {
                return JsonError(labels('please_enter_username'));
            }

            $currentUser = $this->usersModel->find($this->userId);

            if (empty($currentUser)) {
                return JsonError(labels('user_not_found'));
            }

            $fileService = service('fileService');

            $userData = [
                'username' => $defaultUsername,
                'phone' => trim((string) $this->request->getPost('phone')),
                'country_code' => trim((string) $this->request->getPost('country_code')),
                'email' => trim((string) $this->request->getPost('email')),
            ];

            $newImageUrl = null;
            $profileFile = $this->request->getFile('profile_image');
            if ($profileFile && $profileFile->isValid() && !$profileFile->hasMoved()) {
                $result = $fileService->replace($currentUser['image'] ?? null, $profileFile, 'profile');
                if (!empty($result['error'])) {
                    return JsonError($result['message'] ?: labels('error_occured'));
                }
                $userData['image'] = $result['path'];
                $newImageUrl = $fileService->url($result['path'], 'profile');
            }

            $phoneChanged = ((string) ($currentUser['phone'] ?? '') !== $userData['phone'])
                || ((string) ($currentUser['country_code'] ?? '') !== $userData['country_code']);

            $db = \Config\Database::connect();
            $db->transStart();

            $this->usersModel->update($this->userId, $userData);

            $address = (string) $this->request->getPost('address');
            $existingDetail = $this->handymanDetailsModel->where('handyman_id', $this->userId)->first();
            if ($existingDetail) {
                $this->handymanDetailsModel->where('handyman_id', $this->userId)->set(['address' => $address])->update();
            }

            $this->saveHandymanTranslations();
            $this->saveHandymanCustomFields();

            $db->transComplete();

            if (!$db->transStatus()) {
                return JsonError(labels('error_occured'));
            }

            if ($phoneChanged) {
                $db->table('users_tokens')->where('user_id', $this->userId)->delete();
                helper('session');
                safe_destroy_session();
                return JsonSuccess(labels('data_updated_successfully'), null, ['redirect_url' => base_url('handyman/login')]);
            }

            $extra = $newImageUrl ? ['image_url' => $newImageUrl] : [];
            return JsonSuccess(labels('data_updated_successfully'), null, $extra);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Profile.php - update()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels('something_went_wrong'));
        }
    }

    public function change_password()
    {
        try {
            helper('function');

            $this->validation->setRules([
                'old_password' => [
                    'rules' => 'required',
                    'errors' => ['required' => labels('current_password', 'Current Password') . ' ' . labels('is_required', 'is required')],
                ],
                'new_password' => [
                    'rules' => 'required',
                    'errors' => ['required' => labels('new_password', 'New Password') . ' ' . labels('is_required', 'is required')],
                ],
                'confirm_password' => [
                    'rules' => 'required|matches[new_password]',
                    'errors' => [
                        'required' => labels('confirm_new_password', 'Confirm New Password') . ' ' . labels('is_required', 'is required'),
                        'matches' => labels('password_mismatch', 'Password Mismatch'),
                    ],
                ],
            ]);

            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return JsonError(reset($errors));
            }

            $oldPwInput = (string) $this->request->getPost('old_password');
            $newPwInput = (string) $this->request->getPost('new_password');

            $strengthErrors = validate_password_strength($newPwInput);
            if (!empty($strengthErrors)) {
                return JsonError(labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.'));
            }

            $user = $this->usersModel->find($this->userId);

            if (!password_verify($oldPwInput, (string) $user['password'])) {
                return JsonError(labels('old_password_did_not_match', 'Old password did not match'));
            }

            if (password_verify($newPwInput, (string) $user['password'])) {
                return JsonError(labels('new_password_same_as_old', 'New password must be different from old password'));
            }

            $this->usersModel->update($this->userId, ['password' => password_hash($newPwInput, PASSWORD_BCRYPT)]);

            $db = \Config\Database::connect();
            $db->table('users_tokens')->where('user_id', $this->userId)->delete();
            helper('session');
            safe_destroy_session();

            return JsonSuccess(labels('password_updated_successfully', 'Password updated successfully'), null, ['redirect_url' => base_url('handyman/login')]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Profile.php - change_password()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    private function saveHandymanTranslations(): void
    {
        $usernamesByCode = (array) ($this->request->getPost('username') ?? []);
        $db = \Config\Database::connect();
        $langRows = $this->languageModel->select('id, code')->findAll();
        $langIdByCode = [];
        foreach ($langRows as $lang) {
            $langIdByCode[$lang['code']] = (int) $lang['id'];
        }

        foreach ($usernamesByCode as $code => $username) {
            $username = trim((string) $username);
            if ($username === '' || !isset($langIdByCode[$code])) {
                continue;
            }
            $langId = $langIdByCode[$code];
            $existing = $db->table('translated_handyman_details')
                ->where('handyman_id', $this->userId)
                ->where('language_id', $langId)
                ->where('deleted_at IS NULL')
                ->get()->getRowArray();

            if ($existing) {
                $db->table('translated_handyman_details')
                    ->where('id', $existing['id'])
                    ->update(['username' => $username, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                $db->table('translated_handyman_details')->insert([
                    'handyman_id' => $this->userId,
                    'language_id' => $langId,
                    'username' => $username,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function saveHandymanCustomFields(): void
    {
        $customFields = $this->getCustomFields();
        if (empty($customFields)) {
            return;
        }

        $fileService = service('fileService');
        $db = \Config\Database::connect();

        foreach ($customFields as $field) {
            $cfId = (int) $field['id'];
            $inputName = 'cf_' . $cfId;

            if ($field['field_type'] === 'file') {
                $file = $this->request->getFile($inputName);
                if (!$file || !$file->isValid() || $file->hasMoved()) {
                    continue;
                }
                $existing = $this->cfValuesModel
                    ->where('handyman_id', $this->userId)
                    ->where('custom_field_id', $cfId)
                    ->first();
                $result = $fileService->replace($existing['value'] ?? null, $file, 'custom_fields');
                if (!empty($result['error'])) {
                    continue;
                }
                $newValue = $result['path'];
            } else {
                $newValue = (string) ($this->request->getPost($inputName) ?? '');
            }

            $existing = $this->cfValuesModel
                ->where('handyman_id', $this->userId)
                ->where('custom_field_id', $cfId)
                ->first();

            if ($existing) {
                $this->cfValuesModel->update($existing['id'], ['value' => $newValue]);
            } else {
                $this->cfValuesModel->insert([
                    'handyman_id' => $this->userId,
                    'custom_field_id' => $cfId,
                    'value' => $newValue,
                ]);
            }
        }
    }

    private function getCustomFields(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $toBool = static function ($v): bool {
            if (is_bool($v)) {
                return $v;
            }
            if (is_int($v)) {
                return $v === 1;
            }
            $s = strtolower(trim((string) $v));
            return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
        };

        $rows = $db->table('custom_fields')
            ->select(['id', 'field_label', 'field_type', 'file_config', 'required', 'visible', 'sort_order'])
            ->where('field_group', 'handyman_details')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $fields = [];
        foreach ($rows as $row) {
            if (!$toBool($row['visible'] ?? 0)) {
                continue;
            }
            $fileConfigRaw = (string) ($row['file_config'] ?? '');
            $fields[] = [
                'id' => (int) $row['id'],
                'field_label' => (string) $row['field_label'],
                'field_type' => strtolower(trim((string) $row['field_type'])),
                'file_config' => $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [],
                'required' => $toBool($row['required'] ?? 0) ? 1 : 0,
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return $fields;
    }
}
