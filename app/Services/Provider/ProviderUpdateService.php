<?php

namespace App\Services\Provider;

use App\Models\Language_model;
use App\Models\Partners_model;
use App\Models\ProviderLocationsModel;
use App\Models\ProviderShifts_model;
use App\Models\Users_model;
use App\Services\PartnerService;
use App\Services\Provider\ProviderCustomFieldsService;
use App\Services\Provider\ProviderTranslationsService;
use App\Services\Provider\SlotSettingsService;
use App\Services\utility\FileService;
use App\Services\utility\SlugService;
use CodeIgniter\HTTP\IncomingRequest;
use Config\Database;
use Exception;

/**
 * Owns the provider-update (`update_partner`) workflow.
 *
 * Extracted from ProviderController as Phase 3 step 2 of the Provider refactor.
 * Behaviour is preserved verbatim — see docs/PROVIDER_REFACTOR_PLAN.md.
 *
 * CI4 form validation stays in the controller; this service receives the
 * already-validated request plus the pre-computed visible file custom fields.
 *
 * Helpers duplicated from ProviderCreateService — dedupe happens in Phase 3 step 6.
 */
class ProviderUpdateService
{
    private Users_model $users;
    private Partners_model $partner;
    private ProviderShifts_model $providerShifts;
    private Language_model $languages;
    private PartnerService $partnerService;
    private SlotSettingsService $slotSettingsService;
    private SlugService $slugService;
    private FileService $fileService;
    private ProviderTranslationsService $translations;
    private ProviderCustomFieldsService $customFields;

    public function __construct()
    {
        // function_helper still owns project-wide UI/i18n helpers (labels, get_default_language).
        helper('function');
        $this->users               = new Users_model();
        $this->partner             = new Partners_model();
        $this->providerShifts      = new ProviderShifts_model();
        $this->languages           = new Language_model();
        $this->partnerService      = new PartnerService();
        $this->slotSettingsService = new SlotSettingsService();
        $this->slugService         = new SlugService();
        $this->fileService         = service('fileService');
        $this->translations        = new ProviderTranslationsService();
        $this->customFields        = new ProviderCustomFieldsService();
    }

    /**
     * Update a partner from the submitted request.
     *
     * @param IncomingRequest $request                 The (already CI4-validated) request.
     * @param array           $visibleFileCustomFields  Rows from getVisibleFileCustomFields().
     *
     * @return array{error: bool, message?: string|array, partner_id?: int}
     *
     * @throws Exception On unexpected failure (caller logs + returns SOMETHING_WENT_WRONG).
     */
    public function update(IncomingRequest $request, array $visibleFileCustomFields): array
    {
        // Default language from DB
        $defaultLanguage = $this->resolveDefaultLanguageCode();

        // Validate default-language translated fields manually
        $postData = $request->getPost();
        $requiredDefaultLangFields = [
            'username'         => [USERNAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,         'Username is required for default language'],
            'company_name'     => [COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,     'Company name is required for default language'],
            'about_provider'   => [ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,   'About provider is required for default language'],
            'long_description' => [DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE,      'Description is required for default language'],
        ];

        $defaultLanguageErrors = [];
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
        $latitude  = number_format($request->getPost('partner_latitude'), 7, '.', '');
        $longitude = number_format($request->getPost('partner_longitude'), 7, '.', '');
        $this->validateCoordinates($latitude, $longitude);

        $fileService = $this->fileService;

        $partnerId = (int) $request->getPost('partner_id');
        $userRow   = $this->users->select(['image'])->find($partnerId) ?? [];
        $IdProofs  = $this->partner
            ->select(['other_images', 'banner', 'company_name', 'slug'])
            ->where('partner_id', $partnerId)
            ->first() ?? [];

        $fileFieldIds = array_map(fn ($cf) => (int) $cf['id'], $visibleFileCustomFields);
        $oldDocValues = !empty($fileFieldIds) ? $this->customFields->getValuesById($partnerId, $fileFieldIds) : [];
        $old_banner   = $IdProofs['banner'];
        $old_image    = $userRow['image'];

        // Replace single-file uploads (profile, banner, custom-field files).
        $singleUploads = [
            'image'        => ['folder' => 'profile', 'old' => $old_image],
            'banner_image' => ['folder' => 'banner',  'old' => $old_banner],
        ];
        foreach ($visibleFileCustomFields as $cfRow) {
            $cfId = (int) $cfRow['id'];
            $singleUploads['cf_' . $cfId] = [
                'folder' => 'custom_fields',
                'old'    => $oldDocValues[$cfId] ?? '',
            ];
        }

        $resolvedPaths = [];
        foreach ($singleUploads as $inputName => $cfg) {
            $file = $request->getFile($inputName);
            if (!$file || !$file->isValid()) {
                $resolvedPaths[$inputName] = $cfg['old'];
                continue;
            }
            $res = $fileService->replace($cfg['old'], $file, $cfg['folder']);
            if ($res['error']) {
                return ['error' => true, 'message' => $res['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')];
            }
            $resolvedPaths[$inputName] = $res['path'];
        }

        $image  = $resolvedPaths['image'];
        $banner = $resolvedPaths['banner_image'];

        // Other images: keep retained existing, delete removed, append newly uploaded.
        $existing_other_images     = $request->getPost('existing_other_images') ?: [];
        $remove_other_images_flags = $request->getPost('remove_other_images') ?: [];
        $uploadedOtherImages       = [];

        foreach ($existing_other_images as $index => $imagePath) {
            if (($remove_other_images_flags[$index] ?? '') === '1') {
                $fileService->delete('partner', $imagePath);
                continue;
            }
            $uploadedOtherImages[] = $imagePath;
        }

        // FilePond may deliver gallery files either under the original input
        // name (storeAsFile: true) or nested under a `filepond` namespace
        // (server-mode). Support both shapes.
        $allFiles      = $request->getFiles();
        $multipleFiles = $allFiles['filepond']['other_service_image_selector_edit']
            ?? $allFiles['other_service_image_selector_edit']
            ?? [];
        foreach ($multipleFiles as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }
            $res = $fileService->upload($file, 'partner');
            if (!empty($res['error'])) {
                return ['error' => true, 'message' => $res['message'] ?? labels(FAILED_TO_UPLOAD_OTHER_IMAGES, 'Failed to upload other images')];
            }
            $uploadedOtherImages[] = $res['path'];
        }

        $other_images = !empty($uploadedOtherImages) ? json_encode($uploadedOtherImages) : '[]';

        $uploadedFileCustomFieldValues = [];
        foreach ($visibleFileCustomFields as $cfRow) {
            $cfId = (int) $cfRow['id'];
            $uploadedFileCustomFieldValues[$cfId] = $resolvedPaths['cf_' . $cfId] ?? ($oldDocValues[$cfId] ?? '');
        }

        // Update banner ahead of the transaction (preserves legacy behaviour).
        $this->partner
            ->where('partner_id', $partnerId)
            ->set(['banner' => $banner])
            ->update();

        $phone        = $request->getPost('phone');
        $country_code = $request->getPost('country_code');

        // Enforce loginType-based identity: do not allow changing the field used for sign-in.
        $existingUser     = $this->users
            ->select(['loginType', 'phone', 'country_code', 'email'])
            ->find($partnerId) ?? [];
        $currentLoginType = (isset($existingUser['loginType']) && in_array($existingUser['loginType'], ['phone', 'email'], true))
            ? $existingUser['loginType']
            : 'phone';
        $email = $request->getPost('email');
        if ($currentLoginType === 'phone') {
            $phone        = $existingUser['phone'] ?? $phone;
            $country_code = $existingUser['country_code'] ?? $country_code;
        } else {
            $email = $existingUser['email'] ?? $email;
        }

        $db = Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            // Get default language username for users table
            $defaultUsername = $request->getPost('username[' . $defaultLanguage . ']') ?? $request->getPost('username') ?? '';

            $userData = [
                'username'     => $defaultUsername,
                'email'        => $email,
                'phone'        => $phone,
                'country_code' => $country_code,
                'image'        => $image,
                'latitude'     => $request->getPost('partner_latitude'),
                'longitude'    => $request->getPost('partner_longitude'),
                'city'         => $request->getPost('city'),
                'loginType'    => $currentLoginType,
            ];
            $userData = $this->sanitizeInput($userData);
            if ($userData) {
                $this->users->update($partnerId, $userData);
            }

            $is_approved = $request->getPost('is_approved') ? '1' : '0';

            // Get default language values for main table storage
            $defaultCompanyName     = $request->getPost('company_name[' . $defaultLanguage . ']') ?? $request->getPost('company_name') ?? '';
            $defaultAbout           = $request->getPost('about_provider[' . $defaultLanguage . ']') ?? $request->getPost('about_provider') ?? '';
            $defaultLongDescription = $request->getPost('long_description[' . $defaultLanguage . ']') ?? $request->getPost('long_description') ?? '';

            $existingSlug = $IdProofs['slug'] ?? '';
            $resolvedSlug = $this->slugService->resolve(
                currentSlug: $existingSlug,
                inputSlug: trim($request->getPost('provider_slug') ?? ''),
                fallbackName: $defaultCompanyName,
                table: 'partner_details',
                excludeId: $partnerId
            );

            // Partner details including default language values in main table
            $partner_details = [
                'company_name'                  => trim($defaultCompanyName),
                'about'                         => trim($defaultAbout),
                'long_description'              => trim($defaultLongDescription),
                'type'                          => $request->getPost('type'),
                'visiting_charges'              => $request->getPost('visiting_charges'),
                'advance_booking_days'          => $request->getPost('advance_booking_days'),
                'number_of_members'             => $request->getPost('number_of_members'),
                'max_serviceable_distance'      => $request->getPost('max_serviceable_distance') !== '' ? $request->getPost('max_serviceable_distance') : null,
                'is_approved'                   => $is_approved,
                'other_images'                  => $other_images,
                'address'                       => $request->getPost('address'),
                'slug'                          => $resolvedSlug,
                'at_store'                      => $request->getPost('at_store') ? 1 : 0,
                'at_doorstep'                   => $request->getPost('at_doorstep') ? 1 : 0,
                'need_approval_for_the_service' => $request->getPost('need_approval_for_the_service') ? 1 : 0,
                'chat'                          => $request->getPost('chat') ? 1 : 0,
                'pre_chat'                      => $request->getPost('pre_chat') ? 1 : 0,
            ];

            if (!empty($partner_details)) {
                $this->partner
                    ->where('partner_id', $partnerId)
                    ->set($partner_details)
                    ->update();
            }

            if ((string) $request->getPost('type') === '1') {
                $this->saveProviderLocations(
                    $partnerId,
                    $request->getPost('locations') ?? [],
                    $request->getPost('address'),
                    $request->getPost('city'),
                    $request->getPost('partner_latitude'),
                    $request->getPost('partner_longitude')
                );
            }

            // Persist all visible custom field values into `partner_custom_fields`.
            $customFieldValuesByKey = $this->customFields->collectFromRequest($request, $uploadedFileCustomFieldValues);
            $this->customFields->upsert($partnerId, $customFieldValuesByKey);

            // Handle translated fields using PartnerService
            $postData = $request->getPost();
            $translatedFields = $this->translations->transformFormDataToTranslatedFields($postData, $defaultLanguage);
            $postData['translated_fields'] = $translatedFields;

            $translationResult = $this->partnerService->handlePartnerUpdateWithTranslations($postData, $partner_details, $partnerId, $defaultLanguage);

            if (!$translationResult['success']) {
                log_message('error', 'Failed to save partner translations: ' . implode(', ', $translationResult['errors']));
            }

            $this->providerShifts->deleteByPartner($partnerId);
            $this->savePartnerTimings(
                $partnerId,
                $request->getPost('start_time') ?? [],
                $request->getPost('end_time') ?? [],
                $request->getPost()
            );

            $this->translations->saveSeoSettings($request, $partnerId);

            // Save provider slot settings (scheduling configuration)
            $this->slotSettingsService->save(
                $partnerId,
                $this->slotSettingsService->extractFromPost($request->getPost())
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return ['error' => false, 'partner_id' => $partnerId];
    }

    /**
     * Default language code (`en` fallback if `languages` row missing/misconfigured).
     */
    private function resolveDefaultLanguageCode(): string
    {
        $row = $this->languages
            ->select('code')
            ->where('is_default', 1)
            ->first();

        return !empty($row['code']) ? (string) $row['code'] : 'en';
    }

    /**
     * Recursive HTML-escape + trim for plain scalar payloads. Replaces the
     * legacy `sanitizeInput()` function-helper call with a service-local helper.
     */
    private function sanitizeInput(array $data): array
    {
        $clean = static function ($value) use (&$clean) {
            if (is_array($value)) {
                return array_map($clean, $value);
            }
            return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
        };

        return array_map($clean, $data);
    }

    private function validateCoordinates(string $latitude, string $longitude): void
    {
        if (!preg_match('/^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$/', $latitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LATITUDE, 'Please enter valid latitude'));
        }
        if (!preg_match('/^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$/', $longitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LONGITUDE, 'Please enter a valid longitude'));
        }
    }

    private function saveProviderLocations(int $partnerId, array $locations, $fallbackAddress, $fallbackCity, $fallbackLatitude, $fallbackLongitude): void
    {
        $validLocations = [];
        foreach ($locations as $location) {
            if (!is_array($location) || trim((string) ($location['address'] ?? '')) === '') {
                continue;
            }
            $latitude = trim((string) ($location['latitude'] ?? ''));
            $longitude = trim((string) ($location['longitude'] ?? ''));
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                throw new Exception(labels(PLEASE_ENTER_VALID_LATITUDE, 'Please enter valid location coordinates'));
            }
            $latitude = number_format((float) $latitude, 7, '.', '');
            $longitude = number_format((float) $longitude, 7, '.', '');
            $this->validateCoordinates($latitude, $longitude);
            $validLocations[] = [
                'provider_id' => $partnerId,
                'address' => trim((string) $location['address']),
                'city' => trim((string) ($location['city'] ?? '')),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'is_default' => !empty($location['is_default']) ? 1 : 0,
                'is_active' => isset($location['is_active']) ? (int) $location['is_active'] : 1,
            ];
        }

        if (empty($validLocations)) {
            $validLocations[] = [
                'provider_id' => $partnerId,
                'address' => trim((string) $fallbackAddress),
                'city' => trim((string) $fallbackCity),
                'latitude' => number_format((float) $fallbackLatitude, 7, '.', ''),
                'longitude' => number_format((float) $fallbackLongitude, 7, '.', ''),
                'is_default' => 1,
                'is_active' => 1,
            ];
        }

        $defaultIndex = 0;
        foreach ($validLocations as $index => $location) {
            if ($location['is_default'] === 1) {
                $defaultIndex = $index;
                break;
            }
        }
        foreach ($validLocations as $index => &$location) {
            $location['is_default'] = $index === $defaultIndex ? 1 : 0;
        }
        unset($location);

        $model = new ProviderLocationsModel();
        $model->where('provider_id', $partnerId)->delete();
        $model->insertBatch($validLocations);
        $defaultLocation = $validLocations[$defaultIndex];

        $this->users->update($partnerId, [
            'latitude' => $defaultLocation['latitude'],
            'longitude' => $defaultLocation['longitude'],
            'city' => $defaultLocation['city'],
        ]);
        $this->partner
            ->where('partner_id', $partnerId)
            ->set(['address' => $defaultLocation['address']])
            ->update();
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
}
