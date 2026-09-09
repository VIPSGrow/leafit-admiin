<?php

namespace App\Services\Provider;

use App\Models\Partners_model;
use App\Models\ProviderShifts_model;
use App\Models\Users_model;
use App\Services\PartnerService;
use App\Services\Provider\ProviderCustomFieldsService;
use App\Services\Provider\ProviderTranslationsService;
use App\Services\Provider\SlotSettingsService;
use App\Services\utility\SlugService;
use CodeIgniter\HTTP\IncomingRequest;
use Config\Database;
use Exception;
use IonAuth\Models\IonAuthModel;

/**
 * Owns the provider-creation (`insert_partner`) workflow.
 *
 * Extracted from ProviderController as Phase 3 step 1 of the Provider refactor.
 * Behaviour is preserved verbatim — see docs/PROVIDER_REFACTOR_PLAN.md.
 *
 * CI4 form validation stays in the controller; this service receives the
 * already-validated request plus the pre-computed visible file custom fields.
 */
class ProviderCreateService
{
    private Users_model $users;
    private Partners_model $partner;
    private ProviderShifts_model $providerShifts;
    private PartnerService $partnerService;
    private SlotSettingsService $slotSettingsService;
    private SlugService $slugService;
    private ProviderTranslationsService $translations;
    private ProviderCustomFieldsService $customFields;

    public function __construct()
    {
        helper('function');
        $this->users               = new Users_model();
        $this->partner             = new Partners_model();
        $this->providerShifts      = new ProviderShifts_model();
        $this->partnerService      = new PartnerService();
        $this->slotSettingsService = new SlotSettingsService();
        $this->slugService         = new SlugService();
        $this->translations        = new ProviderTranslationsService();
        $this->customFields        = new ProviderCustomFieldsService();
    }

    /**
     * Create a partner from the submitted request.
     *
     * @param IncomingRequest $request                 The (already CI4-validated) request.
     * @param array           $visibleFileCustomFields  Rows from getVisibleFileCustomFields().
     *
     * @return array{error: bool, message?: string|array, partner_id?: int}
     *               On expected business failure: ['error' => true, 'message' => ...].
     *               On success: ['error' => false, 'partner_id' => int].
     *
     * @throws Exception On unexpected failure (caller logs + returns SOMETHING_WENT_WRONG).
     */
    public function create(IncomingRequest $request, array $visibleFileCustomFields): array
    {
        // Password strength: must match frontend indicator (number, uppercase, special, min length)
        $passwordStrengthErrors = validate_password_strength($request->getPost('password'));
        if (!empty($passwordStrengthErrors)) {
            return ['error' => true, 'message' => $passwordStrengthErrors];
        }

        // Extract loginType early to determine which field must be unique
        $loginType = strtolower(trim((string) $request->getPost('login_type')));
        if (!in_array($loginType, ['phone', 'email'], true)) {
            $loginType = 'phone';
        }

        $db = Database::connect();
        if ($loginType == 'phone') {
            // Check for duplicate phone number
            $builder = $db->table('users u')
                ->select('u.*, ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('phone', $request->getPost('phone'))
                ->where('country_code', $request->getPost('country_code'));
            if ($builder->countAllResults() > 0) {
                return ['error' => true, 'message' => labels(PHONE_NUMBER_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, 'Phone number already exists please use another one')];
            }
        } else {
            // Check for duplicate email address
            $builder = $db->table('users u')
                ->select('u.*, ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('email', strtolower($request->getPost('email')));
            if ($builder->countAllResults() > 0) {
                return ['error' => true, 'message' => labels(EMAIL_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, 'Email already exists please use another one')];
            }
        }

        // Get the default language from database for validation
        $defaultLanguage = 'en'; // fallback
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            if ($language['is_default'] == 1) {
                $defaultLanguage = $language['code'];
                break;
            }
        }

        // Validate default language translated fields manually
        $postData = $request->getPost();
        $defaultLanguageErrors = [];

        // Required default-language fields: input name => [label key, fallback text].
        // Accepts either new format ($postData[field][lang]) or old format
        // ($postData["field[lang]"]) transparently per field.
        $requiredDefaultLangFields = [
            'username'         => [USERNAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,         'Username is required for default language'],
            'company_name'     => [COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,     'Company name is required for default language'],
            'about_provider'   => [ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,   'About provider is required for default language'],
            'long_description' => [DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,      'Description is required for default language'],
        ];

        foreach ($requiredDefaultLangFields as $field => [$labelKey, $labelDefault]) {
            $value = is_array($postData[$field] ?? null)
                ? ($postData[$field][$defaultLanguage] ?? null)
                : ($postData["{$field}[{$defaultLanguage}]"] ?? null);

            if (empty($value)) {
                $defaultLanguageErrors[] = labels($labelKey, $labelDefault);
            }
        }

        if (!empty($defaultLanguageErrors)) {
            return ['error' => true, 'message' => $defaultLanguageErrors];
        }

        // Preserve up to 7 decimal places to match register method validation
        $latitude = number_format($request->getPost('partner_latitude'), 7, '.', '');
        $longitude = number_format($request->getPost('partner_longitude'), 7, '.', '');

        // Validate coordinates
        $this->validateCoordinates($latitude, $longitude);

        // Handle file uploads via FileService.
        $fileService = service('fileService');

        $uploadInputs = [
            'profile' => ['file' => $request->getFile('image'),        'folder' => 'profile'],
            'banner'  => ['file' => $request->getFile('banner_image'), 'folder' => 'banner'],
        ];

        foreach ($visibleFileCustomFields as $cfRow) {
            $inputName = 'cf_' . (int) $cfRow['id'];
            $uploadInputs[$inputName] = [
                'file'   => $request->getFile($inputName),
                'folder' => 'custom_fields',
            ];
        }

        $uploadedFiles = [];
        foreach ($uploadInputs as $key => $config) {
            $file = $config['file'];
            if (!$file || !$file->isValid()) {
                $uploadedFiles[$key] = ['path' => '', 'disk' => ''];
                continue;
            }
            $res = $fileService->upload($file, $config['folder']);
            if ($res['error']) {
                throw new Exception($res['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
            }
            $uploadedFiles[$key] = ['path' => $res['path'], 'disk' => $res['disk']];
        }

        // Prefer freshly-uploaded path; fall back to copying the existing file
        // posted from the duplicate-provider flow (copy gives each provider its
        // own independent file so deleting the original doesn't break the copy).
        $resolveStoredPath = function (string $key, string $existingPostKey, string $folder) use ($uploadedFiles, $request, $fileService): string {
            $uploaded = $uploadedFiles[$key]['path'] ?? '';
            if ($uploaded !== '') {
                return $uploaded;
            }
            $existing = (string) ($request->getPost($existingPostKey) ?? '');
            if ($existing === '') {
                return '';
            }
            return $fileService->copy($folder, $existing);
        };

        $image = $resolveStoredPath('profile', 'existing_image', 'profile');

        // Get default language values for main table storage
        $defaultUsername = $request->getPost('username[' . $defaultLanguage . ']') ?? $request->getPost('username') ?? '';
        $defaultCompanyName = $request->getPost('company_name[' . $defaultLanguage . ']') ?? $request->getPost('company_name') ?? '';
        $defaultAbout = $request->getPost('about_provider[' . $defaultLanguage . ']') ?? $request->getPost('about_provider') ?? '';
        $defaultLongDescription = $request->getPost('long_description[' . $defaultLanguage . ']') ?? $request->getPost('long_description') ?? '';

        $userData = [
            'username' => $defaultUsername,
            'password' => $request->getPost('password'),
            'email' => strtolower($request->getPost('email')),
            'latitude' => $request->getPost('partner_latitude'),
            'longitude' => $request->getPost('partner_longitude'),
            'phone' => $request->getPost('phone'),
            'country_code' => $request->getPost('country_code'),
            'city' => $request->getPost('city'),
            'image' => $image,
            'ip_address' => $request->getIPAddress(),
            'created_on' => time(),
            'api_key' => bin2hex(random_bytes(16)),
            'is_approved' => $request->getPost('is_approved') ? 1 : 0,
            'active' => 1,
            'loginType' => $loginType,
        ];

        $db->transException(true);
        $db->transStart();
        try {
            // Save user
            $partnerId = $this->saveUser($userData);

            // Handle other images
            $existingImages = $request->getPost('existing_other_images') ?? [];
            $removeFlags = $request->getPost('remove_other_images') ?? [];
            $uploadedOtherImages = array_filter($existingImages, function ($index) use ($removeFlags) {
                return !isset($removeFlags[$index]) || $removeFlags[$index] !== '1';
            }, ARRAY_FILTER_USE_KEY);

            // FilePond may deliver gallery files either under the original
            // input name (storeAsFile: true) or nested under a `filepond`
            // namespace (server-mode). Support both shapes.
            $allFiles      = $request->getFiles();
            $multipleFiles = $allFiles['filepond']['other_service_image_selector']
                ?? $allFiles['other_service_image_selector']
                ?? [];

            foreach ($multipleFiles as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                $res = $fileService->upload($file, 'partner');
                if (!empty($res['error'])) {
                    throw new Exception($res['message'] ?? labels(FAILED_TO_UPLOAD_OTHER_IMAGES, 'Failed to upload other images'));
                }
                $uploadedOtherImages[] = $res['path'];
            }

            $banner = $resolveStoredPath('banner', 'existing_banner_image', 'banner');

            // Resolve uploaded file paths for all visible file-type custom fields.
            $uploadedFileCustomFieldValues = [];
            foreach ($visibleFileCustomFields as $cfRow) {
                $cfId = (int) $cfRow['id'];
                $inputName = 'cf_' . $cfId;
                $uploadedFileCustomFieldValues[$cfId] = $uploadedFiles[$inputName]['path'] ?? '';
            }

            $inputSlug = $request->getPost('provider_slug');
            $resolvedSlug = $this->slugService->resolve(
                currentSlug: null,
                inputSlug: $inputSlug,
                fallbackName: $defaultCompanyName,
                table: 'partner_details'
            );

            $partnerData = [
                'partner_id' => $partnerId,
                'company_name' => trim($defaultCompanyName), // Store default language value in main table
                'about' => trim($defaultAbout), // Store default language value in main table
                'long_description' => trim($defaultLongDescription), // Store default language value in main table
                'banner' => $banner,
                'address' => trim($request->getPost('address')),
                'advance_booking_days' => $request->getPost('advance_booking_days'),
                'admin_commission' => 0,
                'type' => $request->getPost('type'),
                'number_of_members' => $request->getPost('number_of_members'),
                'visiting_charges' => $request->getPost('visiting_charges'),
                'max_serviceable_distance' => $request->getPost('max_serviceable_distance') !== '' ? $request->getPost('max_serviceable_distance') : null,
                'is_approved' => $request->getPost('is_approved') ? 1 : 0,
                'other_images' => !empty($uploadedOtherImages) ? json_encode($uploadedOtherImages) : '',
                'at_store' => $request->getPost('at_store') ? 1 : 0,
                'at_doorstep' => $request->getPost('at_doorstep') ? 1 : 0,
                'need_approval_for_the_service' => $request->getPost('need_approval_for_the_service') ? 1 : 0,
                'chat' => $request->getPost('chat') ? 1 : 0,
                'pre_chat' => $request->getPost('pre_chat') ? 1 : 0,
                'slug' => $resolvedSlug,
            ];

            // Save partner
            $this->savePartner($partnerId, $partnerData);

            // Persist all visible custom field values into `partner_custom_fields`.
            $customFieldValuesByKey = $this->customFields->collectFromRequest($request, $uploadedFileCustomFieldValues);
            $this->customFields->upsert($partnerId, $customFieldValuesByKey);

            // Handle translated fields using PartnerService
            $postData = $request->getPost();

            // Transform form data to translated_fields structure
            $translatedFields = $this->translations->transformFormDataToTranslatedFields($postData, $defaultLanguage);

            // Add translated_fields to postData for PartnerService
            $postData['translated_fields'] = $translatedFields;

            $translationResult = $this->partnerService->handlePartnerCreationWithTranslations($postData, $partnerData, $partnerId, $defaultLanguage);

            if (!$translationResult['success']) {
                // Log the error but continue with the process (matches legacy behaviour).
                log_message('error', 'Failed to save partner translations: ' . implode(', ', $translationResult['errors']));
            }

            // Save SEO settings (validation handled by Seo_model)
            $this->translations->saveSeoSettings($request, $partnerId);

            // Save partner timings
            $this->savePartnerTimings(
                $partnerId,
                $request->getPost('start_time'),
                $request->getPost('end_time'),
                $request->getPost()
            );

            // Save provider slot settings (scheduling configuration)
            $this->slotSettingsService->save(
                (int) $partnerId,
                $this->slotSettingsService->extractFromPost($request->getPost())
            );

            // Assign user to group
            $this->assignUserGroup($partnerId);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return ['error' => false, 'partner_id' => $partnerId];
    }

    private function validateCoordinates(string $latitude, string $longitude): void
    {
        // Match register method: latitude -90 to 90, longitude -180 to 180, max 7 decimal places
        if (!preg_match('/^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$/', $latitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LATITUDE, 'Please enter valid latitude'));
        }
        if (!preg_match('/^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$/', $longitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LONGITUDE, 'Please enter a valid longitude'));
        }
    }

    private function saveUser(array $userData): int
    {
        // Hash the password before inserting
        $ion_auth = new IonAuthModel();
        $userData['password'] = $ion_auth->hashPassword($userData['password']);

        // Insert user into database using Users model
        $insert_id = $this->users->insert($userData);

        // Verify insertion was successful
        if (!$insert_id) {
            log_message('error', "Failed to insert user: " . json_encode($userData) . " Model Error: " . json_encode($this->users->errors()));
            throw new Exception(labels(USER_CREATION_FAILED_PLEASE_TRY_AGAIN, 'User creation failed! Please try again'));
        }

        return $insert_id;
    }

    private function savePartner(int $partnerId, array $partnerData): void
    {
        if (!$this->partner->insert($partnerData)) {
            throw new Exception(labels(PARTNER_CREATION_FAILED_PLEASE_TRY_AGAIN, 'Partner creation failed! Please try again'));
        }
    }

    private function savePartnerTimings(int $partnerId, array $startTimes, array $endTimes, array $postData): void
    {
        $extraShifts = $postData['extra_shifts'] ?? [];
        $rows = [];

        foreach (ProviderShifts_model::DAYS as $i => $day) {
            $isOpen = isset($postData[$day]) ? 1 : 0;
            $shiftNumber = 1;

            $rows[] = [
                'day'          => $day,
                'shift_number' => $shiftNumber,
                'opening_time' => $startTimes[$i] ?? '',
                'closing_time' => $endTimes[$i] ?? '',
                'is_open'      => $isOpen,
            ];

            $dayExtras = $extraShifts[$day] ?? [];
            $extraStarts = $dayExtras['start'] ?? [];
            $extraEnds = $dayExtras['end'] ?? [];
            $count = min(count($extraStarts), count($extraEnds));

            for ($j = 0; $j < $count; $j++) {
                $start = trim((string) $extraStarts[$j]);
                $end = trim((string) $extraEnds[$j]);
                if ($start === '' || $end === '') {
                    continue;
                }
                $shiftNumber++;
                $rows[] = [
                    'day'          => $day,
                    'shift_number' => $shiftNumber,
                    'opening_time' => $start,
                    'closing_time' => $end,
                    'is_open'      => $isOpen,
                ];
            }
        }

        $this->providerShifts->insertBatchForPartner($partnerId, $rows);
    }

    private function assignUserGroup(int $partnerId): void
    {
        if (!exists(['user_id' => $partnerId, 'group_id' => 3], 'users_groups')) {
            insert_details(['user_id' => $partnerId, 'group_id' => 3], 'users_groups');
        }
    }
}
