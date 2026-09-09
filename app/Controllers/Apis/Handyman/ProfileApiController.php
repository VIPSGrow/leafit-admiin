<?php

namespace App\Controllers\Apis\Handyman;

use App\Controllers\BaseController;
use App\Models\Language_model;
use App\Models\HandymanCustomFieldValuesModel;
use App\Models\HandymanDetailsModel;
use App\Models\TranslatedHandymanDetailsModel;
use App\Models\Users_model;

class ProfileApiController extends BaseController
{
    protected $user_details = [];

    protected Language_model $languageModel;
    protected HandymanCustomFieldValuesModel $cfValuesModel;
    protected HandymanDetailsModel $handymanDetailsModel;
    protected TranslatedHandymanDetailsModel $translatedHandymanModel;
    protected Users_model $usersModel;

    public function __construct()
    {
        helper('api');
        helper('function');
        helper('ResponceServices');
        $this->request = \Config\Services::request();

        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            ApiError($token['message'])->setStatusCode($token['status'] ?? 403)->send();
            exit;
        }

        $this->languageModel = new Language_model();
        $this->cfValuesModel = new HandymanCustomFieldValuesModel();
        $this->handymanDetailsModel = new HandymanDetailsModel();
        $this->translatedHandymanModel = new TranslatedHandymanDetailsModel();
        $this->usersModel = new Users_model();
    }

    public function get_profile()
    {
        try {
            $userId = (int) $this->user_details['id'];
            $profileData = $this->getFullProfile($userId);
            if (empty($profileData)) {
                return ApiError('user_not_found');
            }
            return ApiSuccess('data_fetched_successfully', $profileData);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/ProfileApiController.php - get_profile()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }

    public function update_profile()
    {
        try {
            $userId = (int) $this->user_details['id'];
            $validation = \Config\Services::validation();

            $validation->setRules([
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

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return ApiError(implode(', ', $errors));
            }

            $defaultLangCode = get_default_language();
            $usernamesByCode = $this->request->getPost('username');
            if (is_array($usernamesByCode)) {
                $defaultUsername = trim((string) ($usernamesByCode[$defaultLangCode] ?? ''));
            } else {
                $defaultUsername = trim((string) $usernamesByCode);
                $usernamesByCode = [$defaultLangCode => $defaultUsername];
            }

            if ($defaultUsername === '') {
                return ApiError(labels('please_enter_username', 'Please enter username'));
            }

            $currentUser = $this->usersModel->find($userId);
            if (empty($currentUser)) {
                return ApiError(labels('user_not_found', 'User not found'));
            }

            $phone = trim((string) $this->request->getPost('phone'));
            $country_code = trim((string) $this->request->getPost('country_code'));
            $email = trim((string) $this->request->getPost('email'));

            if ($email !== '') {
                $existingEmail = $this->usersModel->where('email', $email)->where('id !=', $userId)->first();
                if (!empty($existingEmail)) {
                    return ApiError(labels('email_already_registered', 'Email already registered'));
                }
            }

            if ($phone !== '') {
                $existingPhone = $this->usersModel->where('phone', $phone)->where('country_code', $country_code)->where('id !=', $userId)->first();
                if (!empty($existingPhone)) {
                    return ApiError(labels('phone_already_registered', 'Phone number already registered'));
                }
            }

            // Custom fields validation
            $customFields = $this->getCustomFields();
            foreach ($customFields as $field) {
                if ($field['required']) {
                    $cfId = $field['id'];
                    $inputName = 'cf_' . $cfId;

                    if ($field['field_type'] === 'file') {
                        $file = $this->request->getFile($inputName);
                        $existing = $this->cfValuesModel
                            ->where('handyman_id', $userId)
                            ->where('custom_field_id', $cfId)
                            ->first();

                        if ((!$file || !$file->isValid()) && empty($existing['value'])) {
                            return ApiError(sprintf(labels('field_is_required', '%s is required'), $field['field_label']));
                        }
                    } else {
                        $val = $this->request->getPost($inputName);
                        $existing = $this->cfValuesModel
                            ->where('handyman_id', $userId)
                            ->where('custom_field_id', $cfId)
                            ->first();

                        if ($val === null) {
                            if (empty($existing['value'])) {
                                return ApiError(sprintf(labels('field_is_required', '%s is required'), $field['field_label']));
                            }
                        } else if (trim((string) $val) === '') {
                            return ApiError(sprintf(labels('field_is_required', '%s is required'), $field['field_label']));
                        }
                    }
                }
            }

            $fileService = service('fileService');

            $userData = [
                'username' => $defaultUsername,
                'phone' => $phone,
                'country_code' => $country_code,
                'email' => $email !== '' ? $email : null,
            ];

            $profileFile = $this->request->getFile('profile_image');
            if ($profileFile && $profileFile->isValid() && !$profileFile->hasMoved()) {
                $result = $fileService->replace($currentUser['image'] ?? null, $profileFile, 'profile');
                if (!empty($result['error'])) {
                    return ApiError($result['message'] ?: labels('error_occured', 'Error occurred'));
                }
                $userData['image'] = $result['path'];
            }

            $phoneChanged = ((string) ($currentUser['phone'] ?? '') !== $userData['phone'])
                || ((string) ($currentUser['country_code'] ?? '') !== $userData['country_code']);

            $db = \Config\Database::connect();
            $db->transStart();

            $this->usersModel->update($userId, $userData);

            $address = (string) $this->request->getPost('address');
            $existingDetail = $this->handymanDetailsModel->where('handyman_id', $userId)->first();
            if ($existingDetail) {
                $this->handymanDetailsModel->where('handyman_id', $userId)->set(['address' => $address])->update();
            }
            // No else: handyman_details row requires partner_id (set at onboarding); missing row is an abnormal state, not created here.

            $this->saveHandymanTranslations($userId, $usernamesByCode);
            $this->saveHandymanCustomFields($userId, $customFields);

            $db->transComplete();

            if (!$db->transStatus()) {
                if (isset($userData['image'])) {
                    $fileService->delete($userData['image'], 'profile');
                }
                return ApiError(labels('error_occured', 'Error occurred'));
            }

            if ($phoneChanged) {
                $db->table('users_tokens')->where('user_id', $userId)->delete();
                return ApiSuccess('data_updated_successfully', null, ['is_logged_out' => true]);
            }

            $profileData = $this->getFullProfile($userId);
            return ApiSuccess('data_updated_successfully', $profileData);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/ProfileApiController.php - update_profile()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError('something_went_wrong');
        }
    }

    // public function update_availability()
    // {
    //     try {
    //         $userId = (int) $this->user_details['id'];

    //         $isAvailable = $this->request->getPost('is_available');
    //         if ($isAvailable === null) {
    //             return ApiError('is_available_required');
    //         }

    //         $isAvailable = (int) $isAvailable;
    //         if ($isAvailable !== 0 && $isAvailable !== 1) {
    //             return ApiError('invalid_is_available_value');
    //         }

    //         $detail = $this->handymanDetailsModel->where('handyman_id', $userId)->first();
    //         if (empty($detail)) {
    //             return ApiError('data_not_found');
    //         }

    //         $this->handymanDetailsModel
    //             ->where('handyman_id', $userId)
    //             ->set(['is_available' => $isAvailable])
    //             ->update();

    //         $message = $isAvailable === 1 ? 'marked_available' : 'marked_unavailable';

    //         return ApiSuccess($message, ['is_available' => $isAvailable]);
    //     } catch (\Throwable $th) {
    //         log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/ProfileApiController.php - update_availability()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
    //         return ApiError('something_went_wrong');
    //     }
    // }

    private function getFullProfile(int $userId): ?array
    {
        $user = $this->usersModel->find($userId);
        if (empty($user)) {
            return null;
        }

        $fileService = service('fileService');
        $handymanDetail = $this->handymanDetailsModel->where('handyman_id', $userId)->first() ?: [];

        // Username translations (soft-delete filter applied automatically by model)
        $rawTranslations = $this->translatedHandymanModel
            ->select('languages.code AS language_code, translated_handyman_details.username')
            ->join('languages', 'languages.id = translated_handyman_details.language_id')
            ->where('translated_handyman_details.handyman_id', $userId)
            ->findAll();

        $translationMap = [];
        foreach ($rawTranslations as $t) {
            $translationMap[$t['language_code']] = $t['username'];
        }

        // Resolve username: requested lang → default lang → base table
        $requestedLang = get_current_language_from_request();
        $defaultLang = get_default_language();
        $resolvedUsername = $translationMap[$requestedLang]
            ?? $translationMap[$defaultLang]
            ?? $user['username'];

        // Custom fields
        $customFields = $this->getCustomFields();
        $cfValues = $this->cfValuesModel->where('handyman_id', $userId)->findAll();
        $cfValuesMap = [];
        foreach ($cfValues as $val) {
            $cfValuesMap[(int) $val['custom_field_id']] = $val['value'];
        }
        foreach ($customFields as &$field) {
            $cfId = $field['id'];
            $field['value'] = $cfValuesMap[$cfId] ?? '';
            if ($field['field_type'] === 'file' && $field['value'] !== '') {
                $field['value'] = $fileService->url($field['value'], 'custom_fields');
            }
        }
        unset($field);

        return [
            'id' => (int) $user['id'],
            'username' => $resolvedUsername,
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? '',
            'country_code' => $user['country_code'] ?? '',
            'image' => !empty($user['image']) ? $fileService->url($user['image'], 'profile') : '',
            'partner_id' => isset($handymanDetail['partner_id']) ? (int) $handymanDetail['partner_id'] : null,
            'address' => $handymanDetail['address'] ?? '',
            'salary' => $handymanDetail['salary'] ?? null,
            'average_rating' => $handymanDetail['average_rating'] ?? null,
            'total_ratings' => isset($handymanDetail['total_reviews']) ? (int) $handymanDetail['total_reviews'] : 0,
            // 'is_available'      => isset($handymanDetail['is_available']) ? (int) $handymanDetail['is_available'] : 0,
            'custom_fields' => $customFields,
            'translations' => $translationMap,
        ];
    }

    private function saveHandymanTranslations(int $userId, array $usernamesByCode): void
    {
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
            $existing = $this->translatedHandymanModel
                ->where('handyman_id', $userId)
                ->where('language_id', $langId)
                ->first();

            if ($existing) {
                $this->translatedHandymanModel->update($existing['id'], ['username' => $username]);
            } else {
                $this->translatedHandymanModel->insert([
                    'handyman_id' => $userId,
                    'language_id' => $langId,
                    'username' => $username,
                ]);
            }
        }
    }

    private function saveHandymanCustomFields(int $userId, array $customFields): void
    {
        if (empty($customFields)) {
            return;
        }

        $fileService = service('fileService');

        foreach ($customFields as $field) {
            $cfId = (int) $field['id'];
            $inputName = 'cf_' . $cfId;

            $existing = $this->cfValuesModel
                ->where('handyman_id', $userId)
                ->where('custom_field_id', $cfId)
                ->first();

            if ($field['field_type'] === 'file') {
                $file = $this->request->getFile($inputName);
                if (!$file || !$file->isValid() || $file->hasMoved()) {
                    continue;
                }
                $result = $fileService->replace($existing['value'] ?? null, $file, 'custom_fields');
                if (!empty($result['error'])) {
                    continue;
                }
                $newValue = $result['path'];
            } else {
                if ($this->request->getPost($inputName) === null) {
                    continue;
                }
                $newValue = (string) $this->request->getPost($inputName);
            }

            if ($existing) {
                $this->cfValuesModel->update($existing['id'], ['value' => $newValue]);
            } else {
                $this->cfValuesModel->insert([
                    'handyman_id' => $userId,
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
