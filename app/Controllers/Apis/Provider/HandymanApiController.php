<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\BookingHandymenModel;
use App\Models\HandymanCashCollectionModel;
use App\Models\HandymanCustomFieldValuesModel;
use App\Models\HandymanDetailsModel;
use App\Models\HandymanReviewModel;
use App\Models\Language_model;
use App\Models\LiveTrackingModel;
use App\Models\TranslatedHandymanDetailsModel;
use App\Models\UsersGroupsModel;
use App\Models\Users_model;
use App\Services\utility\FileService;
use CodeIgniter\I18n\Time;
use Throwable;

class HandymanApiController extends BaseController
{
    protected $user_details = [];
    private HandymanDetailsModel $handymanDetailsModel;
    private HandymanCustomFieldValuesModel $cfValuesModel;
    private TranslatedHandymanDetailsModel $translatedModel;
    private Users_model $usersModel;
    private UsersGroupsModel $usersGroupsModel;
    private Language_model $languageModel;
    private FileService $fileService;

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
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode(['error' => true, 'message' => $token['message']]));
            die();
        }

        $this->handymanDetailsModel = new HandymanDetailsModel();
        $this->cfValuesModel = new HandymanCustomFieldValuesModel();
        $this->translatedModel = new TranslatedHandymanDetailsModel();
        $this->usersModel = new Users_model();
        $this->usersGroupsModel = new UsersGroupsModel();
        $this->languageModel = new Language_model();
        $this->fileService = new FileService();
    }

    public function get_handymen()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $limit = (int) ($this->request->getPost('limit') ?: 10);
            $offset = (int) ($this->request->getPost('offset') ?: 0);
            $search = trim((string) ($this->request->getPost('search') ?: ''));

            $result = $this->handymanDetailsModel->list($partnerId, true, $search, $limit, $offset, 'u.id', 'DESC');
            $handymen = $result['data'];

            if (!empty($handymen)) {
                $handymanIds = array_column($handymen, 'id');
                $cfMap = $this->buildCustomFieldMap($handymanIds);
                $translationMap = $this->buildTranslationMap($handymanIds);
                foreach ($handymen as &$row) {
                    $row['custom_fields'] = $cfMap[(int) $row['id']] ?? null;
                    $row['translations'] = $translationMap[(int) $row['id']] ?? $this->emptyTranslations;
                }
                unset($row);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('data_retrieved_successfully', 'Data retrieved successfully'),
                'total' => $result['total'],
                'data' => $handymen,
            ]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::get_handymen] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    /**
     * All languages keyed by code, pre-built on first call.
     * Shape: [ ['language_id' => int, 'language' => string, 'code' => string, 'is_default' => bool], ... ]
     */
    private array $allLanguages = [];

    /**
     * Skeleton translations array (all languages, username = null).
     * Populated once alongside $allLanguages.
     */
    private array $emptyTranslations = [];

    private function loadLanguages(): void
    {
        if (!empty($this->allLanguages)) {
            return;
        }
        $rows = $this->languageModel
            ->select('id, language, code, is_default')
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($rows as $row) {
            $this->allLanguages[$row['code']] = $row;
            $this->emptyTranslations[] = [
                'language_id' => (int) $row['id'],
                'language' => $row['language'],
                'language_code' => $row['code'],
                'is_default' => (bool) $row['is_default'],
                'username' => null,
            ];
        }
    }

    /**
     * Batch-fetches username translations for a set of handyman IDs.
     * Returns a map: handyman_id => [ ['language_id', 'language', 'language_code', 'is_default', 'username'], ... ]
     * Every language is present for every handyman; missing translations get username = null.
     */
    private function buildTranslationMap(array $handymanIds): array
    {
        $this->loadLanguages();

        if (empty($this->allLanguages)) {
            return [];
        }

        $db = \Config\Database::connect();
        $rows = $db->table('translated_handyman_details thd')
            ->select('thd.handyman_id, thd.language_id, thd.username')
            ->join('languages l', 'l.id = thd.language_id')
            ->whereIn('thd.handyman_id', $handymanIds)
            ->where('thd.deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();

        // Index fetched rows: handyman_id => language_id => username
        $fetched = [];
        foreach ($rows as $row) {
            $fetched[(int) $row['handyman_id']][(int) $row['language_id']] = $row['username'];
        }

        $map = [];
        foreach ($handymanIds as $hid) {
            $hid = (int) $hid;
            $entry = [];
            foreach ($this->allLanguages as $lang) {
                $langId = (int) $lang['id'];
                $username = $fetched[$hid][$langId] ?? null;
                $entry[] = [
                    'language_id' => $langId,
                    'language_code' => $lang['code'],
                    'username' => $username !== '' ? $username : null,
                ];
            }
            $map[$hid] = $entry;
        }

        return $map;
    }

    /**
     * Batch-fetches custom field values for a set of handyman IDs.
     * Returns a map: handyman_id => [ ['id', 'field_label', 'field_type', 'value'], ... ]
     */
    private function buildCustomFieldMap(array $handymanIds): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields') || !$db->tableExists('handyman_custom_field_values')) {
            return [];
        }

        $rows = $db->table('handyman_custom_field_values hcfv')
            ->select('hcfv.handyman_id, hcfv.custom_field_id, hcfv.value, cf.field_label, cf.field_type')
            ->join('custom_fields cf', 'cf.id = hcfv.custom_field_id')
            ->whereIn('hcfv.handyman_id', $handymanIds)
            ->where('hcfv.deleted_at IS NULL', null, false)
            ->where('cf.field_group', 'handyman_details')
            ->where('cf.visible', 1)
            ->orderBy('cf.sort_order', 'ASC')
            ->orderBy('cf.id', 'ASC')
            ->get()
            ->getResultArray();

        $fileService = service('fileService');
        $map = [];

        foreach ($rows as $row) {
            $hid = (int) $row['handyman_id'];
            $fieldType = strtolower(trim((string) $row['field_type']));
            $value = $row['value'];

            if ($fieldType === 'file' && !empty($value)) {
                $value = $fileService->exists('custom_fields', $value)
                    ? $fileService->url($value, 'custom_fields')
                    : null;
            }

            $map[$hid][] = [
                'id' => (int) $row['custom_field_id'],
                'field_label' => $row['field_label'],
                'field_type' => $fieldType,
                'value' => $value,
            ];
        }

        return $map;
    }

    public function store_handyman()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $post = $this->request->getPost();

            $usernamesByLang = is_array($post['username'] ?? null) ? $post['username'] : [];
            $countryCode = trim((string) ($post['country_code'] ?? ''));
            $phone = trim((string) ($post['phone'] ?? ''));
            $password = (string) ($post['password'] ?? '');
            $email = strtolower(trim((string) ($post['email'] ?? '')));
            $address = trim((string) ($post['address'] ?? ''));
            $salary = trim((string) ($post['salary'] ?? ''));
            $status = isset($post['status']) ? (int) $post['status'] : 1;

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $defaultLangCode = '';
            foreach ($languages as $l) {
                if (!empty($l['is_default'])) {
                    $defaultLangCode = $l['code'];
                    break;
                }
            }

            // If no language array sent, fall back to plain 'username' field
            if (empty($usernamesByLang) && !empty($post['username']) && !is_array($post['username'])) {
                $usernamesByLang[$defaultLangCode] = $post['username'];
            }

            $username = trim((string) ($usernamesByLang[$defaultLangCode] ?? ''));

            if ($username === '') {
                return $this->response->setJSON(['error' => true, 'message' => labels('username_required', 'Username is required')]);
            }
            if ($phone === '') {
                return $this->response->setJSON(['error' => true, 'message' => labels('enter_mobile_number')]);
            }
            if ($password === '') {
                return $this->response->setJSON(['error' => true, 'message' => labels('password_required', 'Password is required')]);
            }

            $pwdErrors = validate_password_strength($password);
            if (!empty($pwdErrors)) {
                $msg = is_array($pwdErrors) ? reset($pwdErrors) : $pwdErrors;
                return $this->response->setJSON(['error' => true, 'message' => (string) $msg]);
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('please_enter_valid_email')]);
            }

            if ($this->usersModel->where('phone', $phone)->where('country_code', $countryCode)->countAllResults() > 0) {
                return $this->response->setJSON(['error' => true, 'message' => labels('phone_already_registered')]);
            }
            if ($email !== '' && $this->usersModel->where('email', $email)->countAllResults() > 0) {
                return $this->response->setJSON(['error' => true, 'message' => labels('email_already_registered')]);
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            if (!$hashedPassword) {
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
            }

            $profileImageFile = $this->request->getFile('profile_image');
            if (!$profileImageFile || !$profileImageFile->isValid()) {
                return $this->response->setJSON(['error' => true, 'message' => labels('image_required')]);
            }

            $profileUpload = $this->fileService->upload($profileImageFile, 'profile');
            if ($profileUpload['error']) {
                return $this->response->setJSON(['error' => true, 'message' => labels('failed_to_upload_image')]);
            }
            $profilePath = $profileUpload['path'];

            $customFields = $this->getCustomFields();
            $cfFilePaths = [];
            foreach ($customFields as $field) {
                if ($field['field_type'] !== 'file') {
                    continue;
                }
                $cfFile = $this->request->getFile('cf_' . $field['id']);
                if ($cfFile && $cfFile->isValid()) {
                    $cfUpload = $this->fileService->upload($cfFile, 'custom_fields');
                    $cfFilePaths[$field['id']] = $cfUpload['error'] ? null : $cfUpload['path'];
                } else {
                    $cfFilePaths[$field['id']] = null;
                }
            }

            $db = \Config\Database::connect();
            $db->transStart();

            $newUserId = $this->usersModel->insert([
                'username' => $username,
                'phone' => $phone,
                'country_code' => $countryCode,
                'email' => $email !== '' ? $email : null,
                'password' => $hashedPassword,
                'active' => $status,
                'ip_address' => $this->request->getIPAddress(),
                'created_on' => time(),
                'image' => $profilePath,
                'api_key' => bin2hex(random_bytes(16)),
            ]);

            if ($newUserId) {
                $this->usersGroupsModel->insert(['user_id' => $newUserId, 'group_id' => 4]);

                $this->handymanDetailsModel->insert([
                    'handyman_id' => $newUserId,
                    'partner_id' => $partnerId,
                    'address' => $address !== '' ? $address : null,
                    'salary' => $salary !== '' ? $salary : null,
                    'is_available' => 1,
                ]);

                foreach ($languages as $lang) {
                    $isDefault = !empty($lang['is_default']);
                    $value = $isDefault ? $username : trim((string) ($usernamesByLang[$lang['code']] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $this->translatedModel->insert([
                        'handyman_id' => $newUserId,
                        'language_id' => (int) $lang['id'],
                        'username' => $value,
                    ]);
                }

                foreach ($customFields as $field) {
                    $cfId = (int) $field['id'];
                    if ($field['field_type'] === 'file') {
                        $value = $cfFilePaths[$cfId] ?? null;
                    } else {
                        $raw = isset($post['cf_' . $cfId]) ? trim((string) $post['cf_' . $cfId]) : '';
                        $value = $raw !== '' ? $raw : null;
                    }
                    if ($value !== null || !empty($field['required'])) {
                        $this->cfValuesModel->insert([
                            'handyman_id' => $newUserId,
                            'custom_field_id' => $cfId,
                            'value' => $value,
                        ]);
                    }
                }
            }

            $db->transComplete();

            if (!$db->transStatus()) {
                $this->fileService->delete('profile', $profilePath);
                foreach ($cfFilePaths as $path) {
                    if ($path !== null) {
                        $this->fileService->delete('custom_fields', $path);
                    }
                }
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
            }

            return $this->response->setJSON(['error' => false, 'message' => labels(DATA_SAVED_SUCCESSFULLY)]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::store_handyman] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    public function update_handyman()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $post = $this->request->getPost();
            $handymanId = (int) ($post['handyman_id'] ?? 0);

            if ($handymanId <= 0) {
                return $this->response->setJSON(['error' => true, 'message' => labels('invalid_id')]);
            }

            $detail = $this->handymanDetailsModel
                ->where('handyman_id', $handymanId)
                ->where('partner_id', $partnerId)
                ->first();

            if (empty($detail)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }

            $usernamesByLang = is_array($post['username'] ?? null) ? $post['username'] : [];
            $countryCode = trim((string) ($post['country_code'] ?? ''));
            $phone = trim((string) ($post['phone'] ?? ''));
            $email = strtolower(trim((string) ($post['email'] ?? '')));
            $address = trim((string) ($post['address'] ?? ''));
            $salary = trim((string) ($post['salary'] ?? ''));
            $status = isset($post['status']) ? (int) $post['status'] : 1;

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $defaultLangCode = '';
            foreach ($languages as $l) {
                if (!empty($l['is_default'])) {
                    $defaultLangCode = $l['code'];
                    break;
                }
            }

            if (empty($usernamesByLang) && !empty($post['username']) && !is_array($post['username'])) {
                $usernamesByLang[$defaultLangCode] = $post['username'];
            }

            $username = trim((string) ($usernamesByLang[$defaultLangCode] ?? ''));

            if ($username === '') {
                return $this->response->setJSON(['error' => true, 'message' => labels('username_required', 'Username is required')]);
            }
            if ($phone === '') {
                return $this->response->setJSON(['error' => true, 'message' => labels('enter_mobile_number')]);
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('please_enter_valid_email')]);
            }

            $phoneTaken = $this->usersModel
                ->where('phone', $phone)
                ->where('country_code', $countryCode)
                ->where('id !=', $handymanId)
                ->countAllResults() > 0;
            if ($phoneTaken) {
                return $this->response->setJSON(['error' => true, 'message' => labels('phone_already_registered')]);
            }

            if ($email !== '') {
                $emailTaken = $this->usersModel
                    ->where('email', $email)
                    ->where('id !=', $handymanId)
                    ->countAllResults() > 0;
                if ($emailTaken) {
                    return $this->response->setJSON(['error' => true, 'message' => labels('email_already_registered')]);
                }
            }

            $existingUser = $this->usersModel->withDeleted()->find($handymanId);
            $oldProfilePath = $existingUser['image'] ?? null;
            $profilePath = $oldProfilePath;
            $newProfilePath = null;

            $profileImageFile = $this->request->getFile('profile_image');
            if ($profileImageFile && $profileImageFile->isValid()) {
                $profileUpload = $this->fileService->upload($profileImageFile, 'profile');
                if ($profileUpload['error']) {
                    return $this->response->setJSON(['error' => true, 'message' => labels('failed_to_upload_image')]);
                }
                $newProfilePath = $profileUpload['path'];
                $profilePath = $newProfilePath;
            }

            $customFields = $this->getCustomFields();
            $cfFilePaths = [];
            foreach ($customFields as $field) {
                if ($field['field_type'] !== 'file') {
                    continue;
                }
                $cfFile = $this->request->getFile('cf_' . $field['id']);
                if ($cfFile && $cfFile->isValid()) {
                    $cfUpload = $this->fileService->upload($cfFile, 'custom_fields');
                    $cfFilePaths[$field['id']] = $cfUpload['error'] ? null : $cfUpload['path'];
                }
            }

            $db = \Config\Database::connect();
            $db->transStart();

            $this->usersModel->update($handymanId, [
                'username' => $username,
                'phone' => $phone,
                'country_code' => $countryCode,
                'email' => $email !== '' ? $email : null,
                'active' => $status,
                'image' => $profilePath,
            ]);

            $this->handymanDetailsModel
                ->where('handyman_id', $handymanId)
                ->set([
                    'address' => $address !== '' ? $address : null,
                    'salary' => $salary !== '' ? $salary : null,
                ])
                ->update();

            foreach ($languages as $lang) {
                $isDefault = !empty($lang['is_default']);
                $value = $isDefault ? $username : trim((string) ($usernamesByLang[$lang['code']] ?? ''));
                if ($value === '') {
                    continue;
                }
                $existing = $this->translatedModel
                    ->where('handyman_id', $handymanId)
                    ->where('language_id', (int) $lang['id'])
                    ->first();
                if ($existing) {
                    $this->translatedModel->update($existing['id'], ['username' => $value]);
                } else {
                    $this->translatedModel->insert([
                        'handyman_id' => $handymanId,
                        'language_id' => (int) $lang['id'],
                        'username' => $value,
                    ]);
                }
            }

            foreach ($customFields as $field) {
                $cfId = (int) $field['id'];
                if ($field['field_type'] === 'file') {
                    if (!isset($cfFilePaths[$cfId])) {
                        continue;
                    }
                    $value = $cfFilePaths[$cfId];
                } else {
                    $raw = isset($post['cf_' . $cfId]) ? trim((string) $post['cf_' . $cfId]) : '';
                    $value = $raw !== '' ? $raw : null;
                }
                $existingCf = $this->cfValuesModel
                    ->where('handyman_id', $handymanId)
                    ->where('custom_field_id', $cfId)
                    ->first();
                if ($existingCf) {
                    $this->cfValuesModel->update($existingCf['id'], ['value' => $value]);
                } elseif ($value !== null) {
                    $this->cfValuesModel->insert([
                        'handyman_id' => $handymanId,
                        'custom_field_id' => $cfId,
                        'value' => $value,
                    ]);
                }
            }

            $db->transComplete();

            if (!$db->transStatus()) {
                if ($newProfilePath !== null) {
                    $this->fileService->delete('profile', $newProfilePath);
                }
                foreach ($cfFilePaths as $path) {
                    if ($path !== null) {
                        $this->fileService->delete('custom_fields', $path);
                    }
                }
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
            }

            if ($newProfilePath !== null && $oldProfilePath !== null) {
                $this->fileService->delete('profile', $oldProfilePath);
            }

            return $this->response->setJSON(['error' => false, 'message' => labels(DATA_UPDATED_SUCCESSFULLY)]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::update_handyman] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    public function delete_handyman()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $handymanId = (int) ($this->request->getPost('handyman_id') ?: 0);

            if ($handymanId <= 0) {
                return $this->response->setJSON(['error' => true, 'message' => labels('invalid_id')]);
            }

            $detail = $this->handymanDetailsModel
                ->where('handyman_id', $handymanId)
                ->where('partner_id', $partnerId)
                ->first();

            if (empty($detail)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }

            $bookingModel = model(BookingHandymenModel::class);
            if ($bookingModel->hasActiveBookingAssignment($handymanId)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('handyman_has_active_booking', 'This handyman is assigned to a booking that is still in progress and cannot be deleted until it is completed or cancelled.'),
                ]);
            }

            $userData = $this->usersModel->withDeleted()->find($handymanId);

            $db = \Config\Database::connect();
            $db->transStart();

            $this->handymanDetailsModel->where('handyman_id', $handymanId)->delete();
            $this->translatedModel->where('handyman_id', $handymanId)->delete();
            $this->cfValuesModel->where('handyman_id', $handymanId)->delete();
            $this->usersModel->delete($handymanId);

            $db->transComplete();

            if (!$db->transStatus()) {
                return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
            }

            if (!empty($userData['image'])) {
                $this->fileService->delete('profile', $userData['image']);
            }

            return $this->response->setJSON(['error' => false, 'message' => labels('data_deleted_successfully')]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::delete_handyman] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    public function toggle_handyman_status()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $handymanId = (int) ($this->request->getPost('handyman_id') ?: 0);

            if ($handymanId <= 0) {
                return $this->response->setJSON(['error' => true, 'message' => labels('invalid_id')]);
            }

            $detail = $this->handymanDetailsModel
                ->where('handyman_id', $handymanId)
                ->where('partner_id', $partnerId)
                ->first();

            if (empty($detail)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }

            $user = $this->usersModel->withDeleted()->find($handymanId);
            if (empty($user)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }
            $newActive = ((int) $user['active'] === 1) ? 0 : 1;

            if ($newActive === 0) {
                $bookingModel = model(BookingHandymenModel::class);
                if ($bookingModel->hasActiveBookingAssignment($handymanId)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels('handyman_has_active_booking', 'This handyman is assigned to a booking that is still in progress and cannot be deleted until it is completed or cancelled.'),
                    ]);
                }
            }

            $this->usersModel->update($handymanId, ['active' => $newActive]);

            return $this->response->setJSON([
                'error' => false,
                'message' => $newActive === 1 ? labels('activated_successfully') : labels('deactivated_successfully')
            ]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::toggle_handyman_status] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    // public function toggle_handyman_availability()
    // {
    //     try {
    //         $partnerId = (int) $this->user_details['id'];
    //         $handymanId = (int) ($this->request->getPost('handyman_id') ?: 0);

    //         if ($handymanId <= 0) {
    //             return $this->response->setJSON(['error' => true, 'message' => labels('invalid_id')]);
    //         }

    //         $detail = $this->handymanDetailsModel
    //             ->where('handyman_id', $handymanId)
    //             ->where('partner_id', $partnerId)
    //             ->first();

    //         if (empty($detail)) {
    //             return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
    //         }

    //         $newAvailable = ((int) $detail['is_available'] === 1) ? 0 : 1;

    //         $this->handymanDetailsModel
    //             ->where('handyman_id', $handymanId)
    //             ->set(['is_available' => $newAvailable])
    //             ->update();

    //         return $this->response->setJSON([
    //             'error' => false,
    //             'message' => $newAvailable === 1 ? labels('marked_available') : labels('marked_unavailable')
    //         ]);
    //     } catch (Throwable $e) {
    //         log_message('error', sprintf(
    //             '[HandymanApiController::toggle_handyman_availability] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
    //             $e->getMessage(),
    //             $e->getFile(),
    //             $e->getLine(),
    //             $this->request->getMethod(),
    //             uri_string(),
    //             json_encode($this->request->getPost()),
    //             $e->getTraceAsString()
    //         ));
    //         return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
    //     }
    // }

    public function get_handyman_details()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $handymanId = (int) ($this->request->getPost('handyman_id') ?: 0);

            $detail = $this->getOwnedHandyman($partnerId, $handymanId);
            if (empty($detail)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }

            $user = $this->usersModel->find($handymanId);
            $cashModel = model(HandymanCashCollectionModel::class);
            $bookingModel = model(BookingHandymenModel::class);

            $db = \Config\Database::connect();
            $completedCount = $db->table('booking_handymen bh')
                ->whereIn('bh.status', ['booking_ended', 'completed'])
                ->where('bh.handyman_id', $handymanId)
                ->countAllResults();

            $recentCompleted = $bookingModel->listForHandyman($handymanId, 5, 0, '')['data'];
            $recentCompleted = array_values(array_filter($recentCompleted, fn($row) => (int) $row['status_group'] === 2));
            foreach ($recentCompleted as &$row) {
                $row['final_total'] = (float) $row['final_total'];
                $row['date_of_service'] = !empty($row['date_of_service']) ? date('Y-m-d', strtotime($row['date_of_service'])) : null;
            }
            unset($row);

            $today = Time::today();
            $bookingSummary = $bookingModel->getDashboardSummary(
                $handymanId,
                $today->toDateString(),
                $today->addDays(1)->toDateString(),
                $today->addDays(2)->toDateString()
            );

            $activeBooking = $db->table('booking_handymen bh')
                ->select('bh.order_id, o.order_latitude, o.order_longitude, o.address')
                ->join('orders o', 'o.id = bh.order_id')
                ->where('bh.handyman_id', $handymanId)
                ->where('bh.status', 'on_the_way')
                ->orderBy('bh.id', 'DESC')
                ->get()->getRowArray();

            $map = ['available' => false];
            if (!empty($activeBooking) && !empty($activeBooking['order_latitude']) && !empty($activeBooking['order_longitude'])) {
                $liveTracking = model(LiveTrackingModel::class)
                    ->select('latitude, longitude, updated_at')
                    ->where('order_id', $activeBooking['order_id'])
                    ->first();

                if (!empty($liveTracking)) {
                    $map = [
                        'available' => true,
                        'order_id' => (int) $activeBooking['order_id'],
                        'order_latitude' => (float) $activeBooking['order_latitude'],
                        'order_longitude' => (float) $activeBooking['order_longitude'],
                        'order_address' => $activeBooking['address'],
                        'handyman_latitude' => (float) $liveTracking['latitude'],
                        'handyman_longitude' => (float) $liveTracking['longitude'],
                    ];
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('data_retrieved_successfully', 'Data retrieved successfully'),
                'data' => [
                    'id' => $handymanId,
                    'username' => $user['username'] ?? '',
                    'phone' => $user['phone'] ?? '',
                    'country_code' => $user['country_code'] ?? '',
                    'email' => $user['email'] ?? '',
                    'image' => $this->fileService->url($user['image'] ?? '', 'profile'),
                    'active' => (int) ($user['active'] ?? 0),
                    // 'is_available' => (int) $detail['is_available'],
                    'address' => $detail['address'] ?? '',
                    'salary' => $detail['salary'] ?? null,
                    'total_bookings' => $bookingSummary['total_bookings'],
                    'lead_bookings' => $bookingSummary['lead_bookings'],
                    'bookings' => [
                        'today_bookings' => $bookingSummary['today_bookings'],
                        'tomorrow_bookings' => $bookingSummary['tomorrow_bookings'],
                        'upcoming_bookings' => $bookingSummary['upcoming_bookings'],
                    ],
                    'total_cash_collected' => $cashModel->getOverallTotal($handymanId),
                    'outstanding_balance' => $cashModel->getOutstandingTotal($handymanId),
                    'completed_bookings_count' => (int) $completedCount,
                    'recent_completed_bookings' => $recentCompleted,
                    'map' => $map,
                ],
            ]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::get_handyman_details] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    public function get_handyman_bookings()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $handymanId = (int) ($this->request->getPost('handyman_id') ?: 0);

            $detail = $this->getOwnedHandyman($partnerId, $handymanId);
            if (empty($detail)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }

            $limit = (int) ($this->request->getPost('limit') ?: 10);
            $offset = (int) ($this->request->getPost('offset') ?: 0);
            $search = trim((string) ($this->request->getPost('search') ?: ''));

            $bookingModel = model(BookingHandymenModel::class);
            $result = $bookingModel->listForHandyman($handymanId, $limit, $offset, $search);

            $rows = [];
            foreach ($result['data'] as $row) {
                $isAtStore = (int) ($row['address_id'] ?? 1) === 0;
                $address = $isAtStore ? ($row['provider_address'] ?? $row['address'] ?? '') : ($row['address'] ?? '');

                $rows[] = [
                    'order_id' => (int) $row['order_id'],
                    'customer_name' => $row['customer_name'] ?? '-',
                    'handyman_status' => $row['handyman_status'],
                    'order_status' => $row['order_status'],
                    'status_group' => (int) $row['status_group'],
                    'is_at_store' => $isAtStore,
                    'date_of_service' => !empty($row['date_of_service']) ? date('Y-m-d', strtotime($row['date_of_service'])) : null,
                    'starting_time' => $row['starting_time'],
                    'ending_time' => $row['ending_time'],
                    'final_total' => (float) $row['final_total'],
                    'address' => $address,
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('data_retrieved_successfully', 'Data retrieved successfully'),
                'total' => $result['total'],
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::get_handyman_bookings] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    public function get_handyman_cash_collection_list()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $handymanId = (int) ($this->request->getPost('handyman_id') ?: 0);

            $detail = $this->getOwnedHandyman($partnerId, $handymanId);
            if (empty($detail)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }

            $limit = (int) ($this->request->getPost('limit') ?: 10);
            $offset = (int) ($this->request->getPost('offset') ?: 0);
            $search = trim((string) ($this->request->getPost('search') ?: ''));
            $sort = $this->request->getPost('sort') ?: 'hcc.id';
            $order = $this->request->getPost('order') ?: 'DESC';

            $cashModel = model(HandymanCashCollectionModel::class);
            $rows = $cashModel->list($handymanId, $limit, $offset, $search, $sort, $order);
            $total = $cashModel->countList($handymanId, $search);

            $formatted = [];
            foreach ($rows as $row) {
                $formatted[] = [
                    'order_id' => $row['order_id'] ? (int) $row['order_id'] : null,
                    'customer_name' => $row['customer_name'] ?? '-',
                    'type' => $row['type'],
                    'amount' => (float) $row['amount'],
                    'message' => $row['message'] ?? '',
                    'created_at' => $row['created_at'],
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('data_retrieved_successfully', 'Data retrieved successfully'),
                'total' => $total,
                'data' => $formatted,
            ]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::get_handyman_cash_collection_list] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    public function get_handyman_reviews_list()
    {
        try {
            $partnerId = (int) $this->user_details['id'];
            $handymanId = (int) ($this->request->getPost('handyman_id') ?: 0);

            $detail = $this->getOwnedHandyman($partnerId, $handymanId);
            if (empty($detail)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('data_not_found')]);
            }

            $limit = (int) ($this->request->getPost('limit') ?: 10);
            $offset = (int) ($this->request->getPost('offset') ?: 0);
            $search = trim((string) ($this->request->getPost('search') ?: ''));

            $reviewModel = model(HandymanReviewModel::class);
            $result = $reviewModel->listForHandyman($handymanId, $limit, $offset, $search);

            $rows = array_map(fn($row) => $this->formatReview($row), $result['data']);

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('data_retrieved_successfully', 'Data retrieved successfully'),
                'total' => $result['total'],
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            log_message('error', sprintf(
                '[HandymanApiController::get_handyman_reviews_list] %s in %s:%d | Method: %s | URI: %s | POST: %s | Trace: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $this->request->getMethod(),
                uri_string(),
                json_encode($this->request->getPost()),
                $e->getTraceAsString()
            ));
            return $this->response->setJSON(['error' => true, 'message' => labels(ERROR_OCCURED)]);
        }
    }

    private function formatReview(array $row): array
    {
        $images = [];
        if (!empty($row['images'])) {
            $paths = is_string($row['images']) ? (json_decode($row['images'], true) ?: []) : (array) $row['images'];
            $images = array_map(fn($p) => $this->fileService->url($p, 'handyman_reviews'), $paths);
        }

        return [
            'id' => (int) $row['id'],
            'customer_name' => $row['customer_name'] ?? '-',
            'customer_image' => $this->fileService->url($row['customer_image'] ?? '', 'profile'),
            'rating' => (int) $row['rating'],
            'review' => $row['review'] ?? '',
            'images' => $images,
            'created_at' => $row['created_at'],
        ];
    }

    private function getOwnedHandyman(int $partnerId, int $handymanId): ?array
    {
        if ($handymanId <= 0) {
            return null;
        }

        return $this->handymanDetailsModel
            ->where('handyman_id', $handymanId)
            ->where('partner_id', $partnerId)
            ->first();
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
