<?php

namespace App\Controllers\Partner;

use App\Models\ProviderLocationsModel;
use App\Services\PartnerService;
use App\Services\utility\SlugService;
use Exception;

class Profile extends Partner
{
    protected $validationListTemplate = 'list';
    protected $partnerService;
    protected SlugService $slugService;

    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
        $this->partnerService = new PartnerService();
        $this->slugService = new SlugService();
    }
    public function index()
    {
        if ($this->isLoggedIn) {
            setPageInfo($this->data, labels('profile', 'Profile') . ' | ' . labels('provider_panel', 'Provider Panel'), 'profile');
            $partner_details = !empty(fetch_details('partner_details', ['partner_id' => $this->userId])) ? fetch_details('partner_details', ['partner_id' => $this->userId])[0] : [];
            $disk = fetch_current_file_manager();

            $partner_details['banner'] = get_file_url($disk, $partner_details['banner'], 'public/backend/assets/default.png', 'banner');

            $this->data['provider_locations'] = (new ProviderLocationsModel())
                ->where('provider_id', $this->userId)
                ->where('is_active', 1)
                ->orderBy('is_default', 'DESC')
                ->orderBy('id', 'ASC')
                ->findAll();

            // fetch languages early so loadCustomFieldDefinitions can use them
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            $cfDefs = $this->loadCustomFieldDefinitions($languages);

            // Build the full list of custom-field ids from both groups dynamically.
            $allCfIds = array_map(static fn($f) => $f['id'], array_merge($cfDefs['documents'], $cfDefs['bank_details']));

            // Fetch custom field values keyed by custom_field_id.
            $customFieldValues = $this->getPartnerCustomFieldValuesById($this->userId, $allCfIds);

            // Resolve file URLs generically for all file-type custom fields.
            foreach (array_merge($cfDefs['documents'], $cfDefs['bank_details']) as $cfField) {
                if ($cfField['field_type'] === 'file') {
                    $cfId = $cfField['id'];
                    $rawVal = $customFieldValues[$cfId] ?? '';
                    if ($rawVal !== '') {
                        $customFieldValues[$cfId] = get_file_url($disk, $rawVal, 'public/backend/assets/default.png', 'custom_fields');
                    }
                }
            }

            $this->data['custom_field_values'] = $customFieldValues;

            // Process other images
            if (!empty($partner_details['other_images'])) {
                $decodedImages = json_decode($partner_details['other_images'], true);
                $updatedImages = [];
                foreach ($decodedImages as $data) {
                    // Ensure we're not adding base URL to a path that already has it
                    if (strpos($data, 'http') === 0) {
                        $updatedImages[] = $data;
                    } else {
                        $updatedImages[] = get_file_url($disk, $data, '', 'partner');
                    }
                }
                $partner_details['other_images'] = $updatedImages;
            } else {
                $partner_details['other_images'] = [];
            }


            // Process user details
            // NOTE: We now also pass loginType to the view so that the UI
            // can decide which identity fields (email / phone / country_code)
            // should be readonly based on how the provider originally registered.
            $user_details = fetch_details('users', ['id' => $this->userId])[0];
            $user_details['image'] = get_file_url($disk, $user_details['image'], '', 'profile');
            $this->data['data'] = $user_details;
            // Expose loginType separately for clearer usage in views.
            $this->data['loginType'] = $user_details['loginType'] ?? null;

            // Don't assign partner_details to data yet - we need to add translations first
            $settings = get_settings('general_settings', true);
            $user_id = $this->ionAuth->getUserId();
            $admin_commission = fetch_details('partner_details', ['partner_id' => $user_id], 'admin_commission');
            $this->data['city_id'] = fetch_details('users', ['id' => $user_id], 'city')[0]['city'];
            $this->data['city'] = $this->data['city_id'];
            $this->data['admin_commission'] = $admin_commission[0]['admin_commission'];
            $this->data['currency'] = $settings['currency'];
            $this->data['city_name'] = $this->data['city_id'];
            $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
            $this->data['national_id_verification_status'] = $settings['national_id_verification_status'] ?? 0;
            $this->data['address_id_verification_status'] = $settings['address_id_verification_status'] ?? 0;
            $this->data['passport_required_status'] = $settings['passport_required_status'] ?? 0;
            $this->data['national_id_required_status'] = $settings['national_id_required_status'] ?? 0;
            $this->data['address_id_required_status'] = $settings['address_id_required_status'] ?? 0;

            $this->data['allow_pre_booking_chat'] = $settings['allow_pre_booking_chat'] ?? 0;
            $this->data['allow_post_booking_chat'] = $settings['allow_post_booking_chat'] ?? 0;
            $this->data['max_serviceable_distance_type'] = $settings['max_serviceable_distance_type'] ?? 'global';

            // Prepare country code data for the view
            $user_country_code = $this->data['data']['country_code'] ?? '';
            $country_code_data = prepare_country_code_data($user_country_code);
            $this->data['country_codes'] = $country_code_data['country_codes'];
            $this->data['selected_country_code'] = $country_code_data['selected_country_code'];

            // ($this->data['languages'] already set above)

            // Pass custom-field definitions and labels to the view.
            $this->data['documents_custom_fields'] = $cfDefs['documents'];
            $this->data['bank_details_custom_fields'] = $cfDefs['bank_details'];
            $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

            // Load translated partner details using PartnerService
            if (!empty($partner_details)) {
                $translatedData = $this->partnerService->getPartnerWithTranslations($this->userId);

                if ($translatedData['success']) {
                    // Merge translated data with partner details for each language
                    foreach ($languages as $language) {
                        $languageCode = $language['code'];
                        if (isset($translatedData['translated_data'][$languageCode])) {
                            $translation = $translatedData['translated_data'][$languageCode];

                            // Create language-specific partner details
                            $partner_details['translated_' . $languageCode] = [
                                'username' => $translation['username'] ?? $data['username'],
                                'company_name' => $translation['company_name'] ?? $partner_details['company_name'],
                                'about' => $translation['about'] ?? $partner_details['about'],
                                'long_description' => $translation['long_description'] ?? $partner_details['long_description']
                            ];
                        } else {
                            // If no translation exists, create default structure
                            $partner_details['translated_' . $languageCode] = [
                                'username' => $user_details['username'],
                                'company_name' => $partner_details['company_name'],
                                'about' => $partner_details['about'],
                                'long_description' => $partner_details['long_description']
                            ];
                        }
                    }
                }
            }

            // Now assign the partner_details with translations to the data array
            $this->data['partner_details'] = $partner_details;

            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function update_profile()
    {
        try {
            if (isset($_POST) && !empty($_POST)) {
                helper('function');
                try {
                    $config = new \Config\IonAuth();
                    $tables = $config->tables;
                    $postData = $this->request->getPost();

                    // Dynamically fetch all visible file-type custom fields for validation and upload.
                    $visibleFileCustomFields = $this->getVisibleFileCustomFields();
                    $fileFieldIds = array_map(fn($cf) => (int) $cf['id'], $visibleFileCustomFields);
                    $existingDocValues = !empty($fileFieldIds) ? $this->getPartnerCustomFieldValuesById($this->userId, $fileFieldIds) : [];

                    // Base validation rules that are always required
                    $validationRules = [
                        'email' => [
                            "rules" => 'required|trim',
                            "errors" => [
                                "required" => labels(PLEASE_ENTER_PROVIDERS_EMAIL, "Please enter providers email"),
                            ]
                        ],
                        'phone' => [
                            "rules" => 'required|numeric|',
                            "errors" => [
                                "required" => labels(PLEASE_ENTER_PROVIDERS_PHONE_NUMBER, "Please enter providers phone number"),
                                "numeric" => labels(PLEASE_ENTER_NUMERIC_PHONE_NUMBER, "Please enter numeric phone number"),
                                "is_unique" => labels(THIS_PHONE_NUMBER_IS_ALREADY_REGISTERED, "This phone number is already registered")
                            ]
                        ],
                        'address' => [
                            "rules" => 'required|trim',
                            "errors" => [
                                "required" => labels(PLEASE_ENTER_ADDRESS, "Please enter address"),
                            ]
                        ],
                        'latitude' => [
                            "rules" => 'required|trim',
                            "errors" => [
                                "required" => labels(PLEASE_CHOOSE_PROVIDER_LOCATION, "Please choose provider location"),
                            ]
                        ],
                        'longitude' => [
                            "rules" => 'required|trim',
                            "errors" => [
                                "required" => labels(PLEASE_CHOOSE_PROVIDER_LOCATION, "Please choose provider location"),
                            ]
                        ],
                        'type' => [
                            "rules" => 'required',
                            "errors" => [
                                "required" => labels(PLEASE_SELECT_PROVIDERS_TYPE, "Please select providers type"),
                            ]
                        ],
                        'visiting_charges' => [
                            "rules" => 'required|numeric',
                            "errors" => [
                                "required" => labels(PLEASE_ENTER_VISITING_CHARGES, "Please enter visiting charges"),
                                "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_VISITING_CHARGES, "Please enter numeric value for visiting charges")
                            ]
                        ],
                        'gstin' => [
                            "rules" => 'permit_empty|trim|regex_match[/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/]',
                            "errors" => [
                                "regex_match" => labels('please_enter_a_valid_gstin_number', "Please enter a valid 15-digit GSTIN number"),
                            ]
                        ],
                    ];

                    // Number of members: always an integer >= 1. An "individual"
                    // provider (type 0) is restricted to exactly 1 member.
                    $postedType = (string) $this->request->getPost('type');
                    $membersRules = 'required|is_natural_no_zero';
                    $membersErrors = [
                        "required" => labels(NUMBER_OF_MEMBERS_IS_REQUIRED, "Number of members is required"),
                        "is_natural_no_zero" => labels('number_of_members_minimum_one', "Number of members must be at least 1"),
                    ];
                    if ($postedType === '0') {
                        $membersRules .= '|in_list[1]';
                        $membersErrors["in_list"] = labels('individual_provider_members_must_be_one', "An individual provider can have only 1 member");
                    }
                    $validationRules['number_of_members'] = [
                        "rules" => $membersRules,
                        "errors" => $membersErrors,
                    ];

                    // Dynamically add validation rules for all visible + required file-type custom fields.
                    // For updates: if document already exists in DB, permit_empty; otherwise require upload.
                    foreach ($visibleFileCustomFields as $cfRow) {
                        if (!$cfRow['required']) {
                            continue;
                        }
                        $cfId = (int) $cfRow['id'];
                        $inputName = 'cf_' . $cfId;
                        $fileConfig = $cfRow['file_config'];
                        $maxSizeKb = (int) (($fileConfig['max_size_mb'] ?? 2) * 1024);
                        $mimeTypes = $this->extensionsToMimeTypes($fileConfig['allowed_types'] ?? []);
                        $label = 'Custom Field ' . $cfId;
                        $hasExisting = !empty($existingDocValues[$cfId]);

                        if ($hasExisting) {
                            $rules = "permit_empty|max_size[{$inputName},{$maxSizeKb}]";
                            $errors = [
                                'max_size' => labels('custom_field_file_size_exceeds_limit', "File size should not exceed " . ($fileConfig['max_size_mb'] ?? 2) . "MB"),
                            ];
                        } else {
                            $rules = "uploaded[{$inputName}]|max_size[{$inputName},{$maxSizeKb}]";
                            $errors = [
                                'uploaded' => labels('please_upload_a_valid_custom_field_document', "Please upload a valid document"),
                                'max_size' => labels('custom_field_file_size_exceeds_limit', "File size should not exceed " . ($fileConfig['max_size_mb'] ?? 2) . "MB"),
                            ];
                        }
                        if (!empty($mimeTypes)) {
                            $rules .= "|mime_in[{$inputName}," . implode(',', $mimeTypes) . "]";
                            $errors['mime_in'] = labels('custom_field_must_be_a_valid_file', "File must be a valid file type");
                        }
                        $validationRules[$inputName] = [
                            'rules' => $rules,
                            'errors' => $errors,
                        ];
                    }

                    $this->validation->setRules($validationRules);
                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors = $this->validation->getErrors();
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        $latitude = number_format($this->request->getPost('latitude'), 6, '.', '');
                        $longitude = number_format($this->request->getPost('longitude'), 6, '.', '');

                        // Validate coordinates
                        $this->validateCoordinates(
                            $latitude,
                            $longitude
                        );

                        // Fetch current user record to enforce loginType-based
                        // restrictions on which identity fields are allowed to change.
                        // We always trust the database as the source of truth here.
                        $data = fetch_details('users', ['id' => $this->userId])[0];
                        $IdProofs = fetch_details(
                            'partner_details',
                            ['partner_id' => $this->userId],
                            ['other_images', 'banner', 'company_name', 'slug']
                        )[0];
                        $old_image = $data['image'];
                        $old_banner = $IdProofs['banner'];

                        // Dynamically fetch old values for all visible file-type custom fields.
                        $oldDocValues = !empty($fileFieldIds) ? $this->getPartnerCustomFieldValuesById($this->userId, $fileFieldIds) : [];
                        $old_other_images = fetch_details('partner_details', ['partner_id' => $this->userId], ['other_images']);
                        $disk = fetch_current_file_manager();

                        $paths = [
                            'image' => [
                                'file' => $this->request->getFile('image'),
                                'path' => 'public/uploads/profile/',
                                'error' => labels(FAILED_TO_CREATE_PROFILE_FOLDERS, "Failed to create profile folders"),
                                'folder' => 'profile',
                                'old_file' => $old_image,
                                'disk' => $disk,
                            ],
                            'banner' => [
                                'file' => $this->request->getFile('banner'),
                                'path' => 'public/uploads/banner/',
                                'error' => labels(FAILED_TO_CREATE_BANNER_FOLDERS, "Failed to create banner folders"),
                                'folder' => 'banner',
                                'old_file' => $old_banner,
                                'disk' => $disk,
                            ],
                        ];

                        // Dynamically add upload paths for all visible file-type custom fields.
                        foreach ($visibleFileCustomFields as $cfRow) {
                            $cfId = (int) $cfRow['id'];
                            $inputName = 'cf_' . $cfId;
                            $paths[$inputName] = [
                                'file' => $this->request->getFile($inputName),
                                'path' => 'public/uploads/custom_fields/',
                                'error' => labels('failed_to_create_custom_fields_folders', "Failed to create custom fields folders"),
                                'folder' => 'custom_fields',
                                'old_file' => $oldDocValues[$cfId] ?? '',
                                'disk' => $disk,
                            ];
                        }

                        // Process single file uploads
                        $uploadedFiles = [];
                        foreach ($paths as $key => $config) {
                            if (!empty($_FILES[$key]) && isset($_FILES[$key])) {
                                $file = $config['file'];

                                if ($file && $file->isValid()) {
                                    if (!empty($config['old_file'])) {
                                        delete_file_based_on_server($config['folder'], $config['old_file'], $config['disk']);
                                    }
                                    $result = upload_file($config['file'], $config['path'], $config['error'], $config['folder']);
                                    if ($result['error'] == false) {
                                        $uploadedFiles[$key] = [
                                            'url' => $result['file_name'],
                                            'disk' => $result['disk']
                                        ];
                                    } else {
                                        return ErrorResponse(labels($result['message'], $result['message']), true, [], [], 200, csrf_token(), csrf_hash());
                                    }
                                } else {
                                    $uploadedFiles[$key] = [
                                        'url' => $config['old_file'],
                                        'disk' => $config['disk']
                                    ];
                                }
                            } else {
                                $uploadedFiles[$key] = [
                                    'url' => $config['old_file'],
                                    'disk' => $config['disk']
                                ];
                            }
                        }

                        $multipleFiles = $this->request->getFiles();
                        $uploadedOtherImages = [];
                        $old_other_images_array = json_decode($IdProofs['other_images'], true);
                        $other_images_disk = $disk;

                        // Process existing images - handle removals
                        $existingOtherImages = [];
                        $removeOtherImages = $this->request->getPost('remove_other_images');

                        if ($this->request->getPost('existing_other_images')) {
                            $existingImagesArr = $this->request->getPost('existing_other_images');

                            foreach ($existingImagesArr as $index => $img) {
                                // Check if this image is marked for removal
                                if (isset($removeOtherImages[$index]) && $removeOtherImages[$index] == '1') {
                                    // Delete image
                                    // Remove base URL if it exists in the image path
                                    $cleanImg = str_replace(base_url(), '', $img);
                                    delete_file_based_on_server('partner', $cleanImg, $other_images_disk);
                                } else {
                                    // Keep image - remove base URL if present
                                    $cleanImg = str_replace(base_url(), '', $img);
                                    $existingOtherImages[] = $cleanImg;
                                }
                            }
                        }

                        // Handle new uploads
                        if (isset($multipleFiles['other_service_image_selector_edit'])) {
                            foreach ($multipleFiles['other_service_image_selector_edit'] as $file) {
                                if ($file->isValid()) {
                                    $result = upload_file($file, 'public/uploads/partner/', labels(FAILED_TO_UPLOAD_OTHER_IMAGES, "Failed to upload other images"), 'partner');
                                    if ($result['error'] == false) {
                                        $uploadedOtherImages[] = $result['disk'] === "local_server"
                                            ? 'public/uploads/partner/' . $result['file_name']
                                            : $result['file_name'];
                                    } else {
                                        return ErrorResponse(labels($result['message'], $result['message']), true, [], [], 200, csrf_token(), csrf_hash());
                                    }
                                }
                            }
                        }

                        // Combine existing and new images
                        $finalOtherImages = array_merge($existingOtherImages, $uploadedOtherImages);
                        $other_images = !empty($finalOtherImages) ? json_encode($finalOtherImages) : '[]';

                        $banner = $uploadedFiles['banner']['url'] ?? 'public/uploads/banner/' . $this->request->getFile('banner_image')->getName();

                        $bannerUrl = $uploadedFiles['banner']['url'] ?? '';
                        if ($bannerUrl !== null && $bannerUrl !== '') {
                            if (isset($uploadedFiles['banner']['disk']) && $uploadedFiles['banner']['disk'] == 'local_server') {
                                $uploadedFiles['banner']['url'] = preg_replace('#(public/uploads/banner/)+#', '', $bannerUrl);
                                $banner = 'public/uploads/banner/' . $uploadedFiles['banner']['url'];
                            } else if (isset($uploadedFiles['banner']['disk']) && $uploadedFiles['banner']['disk'] == 'aws_s3') {
                                $banner = $bannerUrl;
                            } else {
                                $banner = 'public/uploads/banner/' . $bannerUrl;
                                $uploadedFiles['banner']['url'] = preg_replace('#(public/uploads/banner/)+#', '', $bannerUrl);
                                $banner = 'public/uploads/banner/' . $uploadedFiles['banner']['url'];
                            }
                        } else {
                            $banner = $old_banner ?? $banner;
                        }
                        // Dynamically resolve uploaded file paths for all visible file-type custom fields.
                        $uploadedFileCustomFieldValues = [];
                        foreach ($visibleFileCustomFields as $cfRow) {
                            $cfId = (int) $cfRow['id'];
                            $inputName = 'cf_' . $cfId;
                            if (!isset($uploadedFiles[$inputName])) {
                                continue;
                            }
                            $oldVal = $oldDocValues[$cfId] ?? '';
                            $url = $uploadedFiles[$inputName]['url'] ?? $oldVal;
                            $fileDisk = $uploadedFiles[$inputName]['disk'] ?? '';

                            if ($fileDisk === 'local_server') {
                                if ($url !== null && $url !== '') {
                                    $filename = basename($url);
                                    $url = 'public/uploads/custom_fields/' . $filename;
                                } else {
                                    $url = '';
                                }
                            }
                            $uploadedFileCustomFieldValues[$cfId] = $url;
                        }

                        // Update partner details
                        $partnerIDS = [
                            'banner' => $banner,
                        ];

                        if ($partnerIDS) {
                            update_details(
                                $partnerIDS,
                                ['partner_id' => $this->userId],
                                'partner_details',
                                false
                            );
                        }

                        // Persist all visible document custom field values into `partner_custom_fields`.
                        $this->upsertPartnerCustomFieldsValues($this->userId, $uploadedFileCustomFieldValues);
                        $image = $uploadedFiles['image']['url'] ?? 'public/uploads/profile/' . $this->request->getFile('image')->getName();
                        $imageUrl = $uploadedFiles['image']['url'] ?? '';
                        if ($imageUrl !== null && $imageUrl !== '' && isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server') {
                            $uploadedFiles['image']['url'] = preg_replace('#^public/uploads/profile/#', '', $imageUrl);
                            $image = 'public/uploads/profile/' . $uploadedFiles['image']['url'];
                        }
                        // Get default language username for users table
                        $defaultLanguage = 'en'; // fallback
                        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
                        foreach ($languages as $language) {
                            if ($language['is_default'] == 1) {
                                $defaultLanguage = $language['code'];
                                break;
                            }
                        }
                        $defaultUsername = $this->request->getPost('username[' . $defaultLanguage . ']') ?? $this->request->getPost('username') ?? '';

                        // Prepare base user payload from the request.
                        // We will later lock down specific fields based on loginType
                        // so that a provider cannot change the credential that they
                        // use to authenticate (email vs phone/country_code).
                      	$gstin = trim((string) $this->request->getPost('gstin'));
                        $gstin = $gstin !== '' ? strtoupper($gstin) : null;
                      
                        $userData = [
                            'username' => $defaultUsername,
                            'email' => $this->request->getPost('email'),
                            'phone' => $this->request->getPost('phone'),
                            'country_code' => $this->request->getPost('country_code'),
                          	'gstin'        => $gstin,
                            'image' => $image,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'city' => $this->request->getPost('city'),
                        ];

                        // Enforce loginType-based immutability for identity fields.
                        // - If loginType is "phone": phone + country_code must remain the
                        //   same as originally stored; email can still be updated.
                        // - If loginType is "email": email must remain unchanged; phone
                        //   and country_code can be updated.
                        // This protects the primary login identifier from being altered
                        // while still allowing the provider to manage secondary contact
                        // information.
                        $currentLoginType = $data['loginType'] ?? null;
                        $currentEmail = $data['email'] ?? null;
                        $currentPhone = $data['phone'] ?? null;
                        $currentCountryCode = $data['country_code'] ?? null;

                        if ($currentLoginType === 'phone') {
                            // Lock phone and country_code to stored values.
                            $userData['phone'] = $currentPhone;
                            $userData['country_code'] = $currentCountryCode;
                        } elseif ($currentLoginType === 'email') {
                            // Lock email to stored value.
                            $userData['email'] = $currentEmail;
                        }

                        if ($userData) {
                            update_details($userData, ['id' => $this->userId], 'users');
                        }
                        // Get default language values for main table storage
                        $defaultCompanyName = $this->request->getPost('company_name[' . $defaultLanguage . ']') ?? $this->request->getPost('company_name') ?? '';
                        $defaultAbout = $this->request->getPost('about[' . $defaultLanguage . ']') ?? $this->request->getPost('about') ?? '';
                        $defaultLongDescription = $this->request->getPost('long_description[' . $defaultLanguage . ']') ?? $this->request->getPost('long_description') ?? '';

                        // Slug generation logic aligned with admin Partners::update_partner
                        $existingSlug = $IdProofs['slug'] ?? '';
                        $resolvedSlug = $this->slugService->resolve(
                            currentSlug: $existingSlug,
                            inputSlug: trim($this->request->getPost('provider_slug') ?? ''),
                            fallbackName: $defaultCompanyName,
                            table: 'partner_details',
                            excludeId: $this->userId
                        );

                        if ($this->slugService->isLegacySlug($existingSlug)) {
                            $resolvedSlug = $this->slugService->generate(
                                $defaultCompanyName,
                                $defaultCompanyName,
                                'partner_details',
                                $this->userId
                            );
                        }

                        $partner_details = [
                            'company_name' => $defaultCompanyName,
                            'type' => $this->request->getPost('type'),
                            'visiting_charges' => $this->request->getPost('visiting_charges'),
                            'about' => $defaultAbout,
                            'number_of_members' => $this->request->getPost('number_of_members'),
                            'max_serviceable_distance' => $this->request->getPost('max_serviceable_distance') !== '' ? $this->request->getPost('max_serviceable_distance') : null,
                            'long_description' => $defaultLongDescription,
                            'address' => $this->request->getPost('address'),
                            'at_store' => (isset($_POST['at_store'])) ? 1 : 0,
                            'at_doorstep' => (isset($_POST['at_doorstep'])) ? 1 : 0,
                            'chat' => (isset($_POST['chat'])) ? 1 : 0,
                            'pre_chat' => (isset($_POST['pre_chat'])) ? 1 : 0,
                            'other_images' => $other_images,
                            'slug' => $resolvedSlug,
                        ];
                        if ($partner_details) {
                            update_details($partner_details, ['partner_id' => $this->userId], 'partner_details', false);
                        }

                        // Keep the repeatable provider locations in sync with the legacy default fields.
                        $postedLocations = (array) $this->request->getPost('locations');
                        $defaultLocationIndex = 0;
                        $locations = [];

                        foreach ($postedLocations as $postedLocation) {
                            $address = trim((string) ($postedLocation['address'] ?? ''));
                            $latitudeValue = $postedLocation['latitude'] ?? '';
                            $longitudeValue = $postedLocation['longitude'] ?? '';
                            if ($address === '' || !is_numeric($latitudeValue) || !is_numeric($longitudeValue)) {
                                continue;
                            }

                            $latitudeValue = (float) $latitudeValue;
                            $longitudeValue = (float) $longitudeValue;
                            if ($latitudeValue < -90 || $latitudeValue > 90 || $longitudeValue < -180 || $longitudeValue > 180) {
                                continue;
                            }

                            $locations[] = [
                                'provider_id' => $this->userId,
                                'address' => $address,
                                'city' => trim((string) ($postedLocation['city'] ?? '')),
                                'latitude' => number_format($latitudeValue, 7, '.', ''),
                                'longitude' => number_format($longitudeValue, 7, '.', ''),
                                'is_default' => !empty($postedLocation['is_default']) ? 1 : 0,
                                'is_active' => !empty($postedLocation['is_active']) ? 1 : 0,
                            ];
                        }

                        if (empty($locations)) {
                            $locations[] = [
                                'provider_id' => $this->userId,
                                'address' => trim((string) $this->request->getPost('address')),
                                'city' => trim((string) $this->request->getPost('city')),
                                'latitude' => number_format((float) $latitude, 7, '.', ''),
                                'longitude' => number_format((float) $longitude, 7, '.', ''),
                                'is_default' => 1,
                                'is_active' => 1,
                            ];
                        }

                        foreach ($locations as $index => $location) {
                            if ($location['is_default'] === 1) {
                                $defaultLocationIndex = $index;
                                break;
                            }
                        }
                        $defaultLocationIndex = min($defaultLocationIndex, count($locations) - 1);
                        foreach ($locations as $index => &$location) {
                            $location['is_default'] = $index === $defaultLocationIndex ? 1 : 0;
                        }
                        unset($location);
                        $providerLocationsModel = new ProviderLocationsModel();
                        $providerLocationsModel->where('provider_id', $this->userId)->delete();
                        $providerLocationsModel->insertBatch($locations);
                        $selectedDefaultLocation = $locations[$defaultLocationIndex];
                        update_details([
                            'latitude' => $selectedDefaultLocation['latitude'],
                            'longitude' => $selectedDefaultLocation['longitude'],
                            'city' => $selectedDefaultLocation['city'],
                        ], ['id' => $this->userId], 'users');
                        update_details([
                            'address' => $selectedDefaultLocation['address'],
                        ], ['partner_id' => $this->userId], 'partner_details', false);

                        // Persist all visible non-file custom field values (bank_details + any text-type
                        // documents fields) into `partner_custom_fields` dynamically.
                        $textCustomFieldValues = $this->collectTextCustomFieldValuesFromPost();
                        $this->upsertPartnerCustomFieldsValues($this->userId, $textCustomFieldValues);

                        // Handle translations for partner details
                        $this->handlePartnerTranslations();

                        // Send FCM notification to admin users about provider updating their information
                        // The FCM template with key 'provider_update_information' is already configured
                        try {
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Starting FCM notification process for provider_id: ' . $this->userId);

                            // Get provider name with translation support
                            $providerName = get_translated_partner_field($this->userId, 'user_name');
                            if (empty($providerName)) {
                                $providerData = fetch_details('users', ['id' => $this->userId], ['username']);
                                $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                            }
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Provider name: ' . $providerName . ', Provider ID: ' . $this->userId);

                            // Prepare context data for the notification template
                            $context = [
                                'provider_name' => $providerName,
                                'provider_id' => $this->userId
                            ];
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Context prepared: ' . json_encode($context));

                            // Queue notification to admin users (group_id = 1) via FCM channel
                            // The service will check preferences and configurations to determine if FCM should be sent
                            queue_notification_service(
                                eventType: 'provider_update_information',
                                recipients: [],
                                context: $context,
                                options: [
                                    'user_groups' => [1], // Admin user group
                                    'channels' => ['fcm'] // FCM channel only
                                ]
                            );
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] FCM notification result: ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            log_message('error', '[PROVIDER_UPDATE_INFORMATION] FCM notification error trace: ' . $notificationError->getTraceAsString());
                        }

                        // Get partner details for event tracking
                        $partnerData = fetch_details('partner_details', ['partner_id' => $this->userId], ['company_name']);
                        $companyName = !empty($partnerData) ? $partnerData[0]['company_name'] ?? '' : '';

                        // Prepare event data
                        $eventData = [
                            'clarity_event' => 'profile_updated',
                            'provider_id' => $this->userId,
                            'company_name' => $companyName
                        ];

                        // When login type is email, the user is allowed to change phone/country_code.
                        // After such a change we log them out so they must sign in again with the
                        // updated account (session identity may no longer match).
                        $customData = [];
                        if ($currentLoginType === 'email') {
                            $newPhone = trim((string) ($userData['phone'] ?? ''));
                            $newCountryCode = trim((string) ($userData['country_code'] ?? ''));
                            if ($newCountryCode !== '' && $newCountryCode[0] !== '+') {
                                $newCountryCode = '+' . $newCountryCode;
                            }
                            $oldCountryCode = trim((string) ($currentCountryCode ?? ''));
                            if ($oldCountryCode !== '' && $oldCountryCode[0] !== '+') {
                                $oldCountryCode = '+' . $oldCountryCode;
                            }
                            $phoneChanged = $newPhone !== trim((string) ($currentPhone ?? ''));
                            $countryCodeChanged = $newCountryCode !== $oldCountryCode;
                            if ($phoneChanged || $countryCodeChanged) {
                                helper('session');
                                safe_destroy_session();
                                $customData['require_relogin'] = true;
                                $customData['redirect_url'] = base_url('partner/login');
                            }
                        }

                        return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Profile updated successfully!"), false, $eventData, $customData, 200, csrf_token(), csrf_hash());
                    }
                } catch (\Throwable $th) {
                    log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - update_profile()');
                    return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - update_profile()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update()
    {
        try {
            $national_id = $this->request->getFile('national_id');
            $address_id = $this->request->getFile('address_id');
            $passport = $this->request->getFile('passport');
            if ($this->isLoggedIn) {
                // Collect values for columns dropped from partner_details → save to partner_custom_fields
                $customFieldsToSave = [];

                if ($this->request->getFile('national_id') && !empty($this->request->getFile('national_id'))) {
                    $file = $this->request->getFile('national_id');
                    if (!$file->isValid()) {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $type = $file->getMimeType();
                    if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg') {
                        $path = FCPATH . 'public/backend/assets/kyc-details/';
                        if (!empty($check_image)) {
                            $image_name = $check_image[0]['image'];
                            unlink($path . '' . $image_name);
                        }
                        $newName = $file->getRandomName();
                        $file->move($path, $newName);
                        $customFieldsToSave['national_id'] = $newName;
                    } else {
                        return ErrorResponse(labels(INVALID_IMAGE_FILE, "Please attach a valid image file."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                if ($this->request->getFile('address_id') && !empty($this->request->getFile('address_id'))) {
                    $file = $this->request->getFile('address_id');
                    if (!$file->isValid()) {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $type = $file->getMimeType();
                    if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg') {
                        $path = FCPATH . 'public/backend/assets/kyc-details/';
                        if (!empty($check_image)) {
                            $image_name = $check_image[0]['image'];
                            unlink($path . '' . $image_name);
                        }
                        $newName = $file->getRandomName();
                        $file->move($path, $newName);
                        $customFieldsToSave['address_id'] = $newName;
                    } else {
                        return ErrorResponse(labels(INVALID_IMAGE_FILE, "Please attach a valid image file."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                if ($this->request->getFile('passport') && !empty($this->request->getFile('passport'))) {
                    $file = $this->request->getFile('passport');
                    if (!$file->isValid()) {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $type = $file->getMimeType();
                    if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg') {
                        $path = FCPATH . 'public/backend/assets/kyc-details/';
                        if (!empty($check_image)) {
                            $image_name = $check_image[0]['image'];
                            unlink($path . '' . $image_name);
                        }
                        $newName = $file->getRandomName();
                        $file->move($path, $newName);
                        $customFieldsToSave['passport'] = $newName;
                    } else {
                        return ErrorResponse(labels(INVALID_IMAGE_FILE, "Please attach a valid image file."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                if (isset($_POST['bank_name']) && !empty($_POST['bank_name'])) {
                    $customFieldsToSave['bank_name'] = $_POST['bank_name'];
                }
                if (isset($_POST['account_number']) && !empty($_POST['account_number'])) {
                    $customFieldsToSave['account_number'] = $_POST['account_number'];
                }
                if (isset($_POST['account_name']) && !empty($_POST['account_name'])) {
                    $customFieldsToSave['account_name'] = $_POST['account_name'];
                }
                if (isset($_POST['bank_code']) && !empty($_POST['bank_code'])) {
                    $customFieldsToSave['bank_code'] = $_POST['bank_code'];
                }
                if (isset($_POST['type']) && !empty($_POST['type'])) {
                    $data['type'] = $_POST['type'];
                }
                if (isset($_POST['visiting_charges']) && !empty($_POST['visiting_charges'])) {
                    $data['visiting_charges'] = $_POST['visiting_charges'];
                }
                $days = [
                    0 => 'monday',
                    1 => 'tuesday',
                    2 => 'wednsday',
                    3 => 'thursday',
                    4 => 'friday',
                    5 => 'staturday',
                    6 => 'sunday'
                ];
                for ($i = 0; $i < count($_POST['start_time']); $i++) {
                    $partner_timing = [];
                    $partner_timing['day'] = $days[$i];
                    if (isset($_POST['start_time'][$i])) {
                        $partner_timing['opening_time'] = $_POST['start_time'][$i];
                    }
                    if (isset($_POST['end_time'][$i])) {
                        $partner_timing['closing_time'] = $_POST['end_time'][$i];
                    }
                    $partner_timing['is_open'] = (isset($_POST[$days[$i]])) ? 1 : 0;
                    if (exists(['partner_id' => $this->userId, 'day' => $days[$i]], 'partner_timings')) {
                        update_details($partner_timing, ['partner_id' => $this->userId, 'day' => $days[$i]], 'partner_timings');
                    } else {
                        $partner_timing['partner_id'] = $this->userId;
                        insert_details($partner_timing, 'partner_timings');
                    }
                }
                if (exists(['partner_id' => $this->userId], 'partner_details')) {
                    update_details($data, ['partner_id' => $this->userId], 'partner_details');
                } else {
                    $data['partner_id'] = $this->userId;
                    insert_details($data, 'partner_details');
                }

                // Save dropped-column fields to partner_custom_fields
                if (!empty($customFieldsToSave)) {
                    $cfDb = \Config\Database::connect();
                    if ($cfDb->tableExists('custom_fields') && $cfDb->tableExists('partner_custom_fields')) {
                        $fieldRows = $cfDb->table('custom_fields')
                            ->select(['id', 'field_key'])
                            ->whereIn('field_key', array_keys($customFieldsToSave))
                            ->get()->getResultArray();
                        $keyToId = array_column($fieldRows, 'id', 'field_key');
                        $valueById = [];
                        foreach ($customFieldsToSave as $fieldKey => $value) {
                            if (isset($keyToId[$fieldKey])) {
                                $valueById[(int) $keyToId[$fieldKey]] = $value;
                            }
                        }
                        if (!empty($valueById)) {
                            (new \App\Services\Provider\ProviderCustomFieldsService())->upsert((int) $this->userId, $valueById);
                        }
                    }
                }

                // Prepare base user payload from the legacy update flow.
                // We will enforce the same loginType-based immutability rules here
                // so that the primary login credential cannot be changed through
                // any profile update endpoint.
                $userRow = fetch_details('users', ['id' => $this->userId], ['loginType', 'email', 'phone', 'country_code']);
                $currentLoginType = $userRow[0]['loginType'] ?? null;
                $currentEmail = $userRow[0]['email'] ?? null;
                $currentPhone = $userRow[0]['phone'] ?? null;
                $currentCountryCode = $userRow[0]['country_code'] ?? null;

                $data = [
                    'username' => $_POST['username'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone'],
                ];

                // Apply loginType rules:
                // - phone login: keep phone (and implicitly country_code) fixed.
                // - email login: keep email fixed.
                if ($currentLoginType === 'phone') {
                    $data['phone'] = $currentPhone;
                    $data['country_code'] = $currentCountryCode;
                } elseif ($currentLoginType === 'email') {
                    $data['email'] = $currentEmail;
                }

                if ($this->request->getPost('profile')) {
                    $img = $this->request->getPost('profile');
                    $f = finfo_open();
                    $mime_type = finfo_buffer($f, $img, FILEINFO_MIME_TYPE);
                    if ($mime_type != 'text/plain') {
                        $response['error'] = true;
                        return $this->response->setJSON([
                            'csrfName' => csrf_token(),
                            'csrfHash' => csrf_hash(),
                            'error' => true,
                            'message' => labels(INVALID_IMAGE_FILE, "Please Insert valid image"),
                            "data" => []
                        ]);
                    }
                    $data_photo = $img;
                    $img_dir = './public/uploads/profiles/';
                    list($type, $data_photo) = explode(';', $data_photo);
                    list(, $data_photo) = explode(',', $data_photo);
                    $data_photo = base64_decode($data_photo);
                    $filename = microtime(true) . '.jpg';
                    if (!is_dir($img_dir)) {
                        mkdir($img_dir, 0777, true);
                    }
                    if (file_put_contents($img_dir . $filename, $data_photo)) {
                        $profile = $filename;
                        $data['image'] = $filename;
                        $old_image = fetch_details('users', ['id' => $this->userId], ['image']);
                        if ($old_image[0]['image'] != "") {
                            if (is_readable("public/uploads/profiles/" . $old_image[0]['image']) && unlink("public/uploads/profiles/" . $old_image[0]['image'])) {
                            }
                        }
                    } else {
                        $data['image'] = $this->request->getPost('old_profile');
                        $profile = $this->request->getPost('old_profile');
                    }
                }
                $status = update_details(
                    $data,
                    ['id' => $this->userId],
                    'users'
                );
                if ($status) {
                    if (isset($_POST['old']) && isset($_POST['new']) && ($_POST['new'] != "") && ($_POST['old'] != "")) {
                        $identity = session()->get('identity');
                        $change = $this->ionAuth->changePassword($identity, $this->request->getPost('old'), $this->request->getPost('new'), $this->userId);
                        if ($change) {
                            // Load session helper and destroy session files
                            helper('session');
                            safe_destroy_session();
                            return successResponse(labels(USER_UPDATED_SUCCESSFULLY, "User updated successfully"), false, $_POST, [], 200, csrf_token(), csrf_hash());
                        } else {
                            return ErrorResponse(labels(OLD_PASSWORD_DID_NOT_MATCH, "Old password did not matched."), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                    return successResponse(labels(USER_UPDATED_SUCCESSFULLY, "User updated successfully"), false, $_POST, [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function remove_other_images()
    {
        try {
            if (!$this->isLoggedIn) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->userId;
            $image_url = $this->request->getPost('image_url');

            if (empty($id) || empty($image_url)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Remove base URL if it exists in the image URL
            $clean_image_url = str_replace(base_url(), '', $image_url);

            $partner_details = fetch_details('partner_details', ['partner_id' => $id], 'other_images');
            if (empty($partner_details)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $other_images = json_decode($partner_details[0]['other_images'], true);

            // Check if image exists in the array (try both with and without the base URL)
            $key = array_search($clean_image_url, $other_images);
            if ($key === false) {
                $key = array_search($image_url, $other_images);
            }

            if ($key !== false) {
                // Remove the image from storage
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('partner', $other_images[$key], $disk);

                // Remove from array and update database
                unset($other_images[$key]);
                $other_images = array_values($other_images); // Re-index array

                $data = ['other_images' => json_encode($other_images)];
                update_details($data, ['partner_id' => $id], 'partner_details');

                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - remove_other_images()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }


    /**
     * Handle partner translations from form data
     * 
     * @return void
     */
    private function handlePartnerTranslations()
    {
        try {
            // Get languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            if (empty($languages)) {
                return;
            }

            // Get default language
            $defaultLanguage = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            if (empty($defaultLanguage)) {
                return;
            }

            // Process translations for each language (including default language)
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Get translated data from POST
                $username = $this->request->getPost('username[' . $languageCode . ']') ?? '';
                $companyName = $this->request->getPost('company_name[' . $languageCode . ']') ?? '';
                $about = $this->request->getPost('about[' . $languageCode . ']') ?? '';
                $longDescription = $this->request->getPost('long_description[' . $languageCode . ']') ?? '';

                // Only save if there's actual translated content
                if (!empty($username) || !empty($companyName) || !empty($about) || !empty($longDescription)) {
                    $translatedData = [
                        'username' => $username,
                        'company_name' => $companyName,
                        'about' => $about,
                        'long_description' => $longDescription
                    ];

                    // Save or update translation (including default language)
                    $this->partnerService->saveTranslations($this->userId, $languageCode, $translatedData);
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error handling partner translations: ' . $e->getMessage());
        }
    }



    /**
     * Validate coordinates
     * @param string $latitude
     * @param string $longitude
     * @return void
     */
    private function validateCoordinates(string $latitude, string $longitude): void
    {
        // Match register method: latitude -90 to 90, longitude -180 to 180, max 7 decimal places
        if (!preg_match('/^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$/', $latitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LATITUDE, "Please enter valid latitude"));
        }
        if (!preg_match('/^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$/', $longitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LONGITUDE, "Please enter a valid Longitude"));
        }
    }


    /**
     * Build the documents/bank_details custom-field definitions and their
     * per-language labels (same logic as the admin Partners controller).
     * Cannot be shared since the two controllers have different base classes.
     *
     * @param  array $languages  Rows from the `languages` table.
     * @return array{documents: array, bank_details: array, labels_by_language: array}
     */
    private function loadCustomFieldDefinitions(array $languages): array
    {
        $db = \Config\Database::connect();

        $documentsCustomFields = [];
        $bankDetailsCustomFields = [];
        $customFieldLabelsByLanguage = [];

        $languageCodes = [];
        $defaultLanguageCode = '';
        foreach ($languages as $langRow) {
            $code = (string) ($langRow['code'] ?? '');
            if ($code !== '') {
                $languageCodes[] = $code;
            }
            if (!empty($langRow['is_default']) && $code !== '') {
                $defaultLanguageCode = $code;
            }
        }
        if ($defaultLanguageCode === '') {
            $defaultLanguageCode = (string) get_default_language();
        }
        $languageCodes = array_values(array_unique($languageCodes));

        if ($db->tableExists('custom_fields')) {
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

            $customFieldRows = $db->table('custom_fields')
                ->select(['id', 'field_label', 'field_type', 'field_group', 'file_config', 'required', 'visible', 'sort_order'])
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $customFieldIds = array_values(array_unique(array_map(
                static fn($r) => (int) ($r['id'] ?? 0),
                array_filter($customFieldRows, static fn($r) => (int) ($r['id'] ?? 0) > 0)
            )));

            $translationsByFieldId = [];
            if (!empty($customFieldIds) && $db->tableExists('translated_custom_fields') && !empty($languageCodes)) {
                $translationRows = $db->table('translated_custom_fields tcf')
                    ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                    ->join('languages l', 'l.id = tcf.language_id')
                    ->whereIn('tcf.custom_field_id', $customFieldIds)
                    ->whereIn('l.code', $languageCodes)
                    ->get()
                    ->getResultArray();

                foreach ($translationRows as $tr) {
                    $fieldId = (int) ($tr['custom_field_id'] ?? 0);
                    $langCode = (string) ($tr['language_code'] ?? '');
                    if ($fieldId <= 0 || $langCode === '') {
                        continue;
                    }
                    $translationsByFieldId[$fieldId][$langCode] = (string) ($tr['field_label'] ?? '');
                }
            }

            foreach ($customFieldRows as $field) {
                $fieldId = (int) ($field['id'] ?? 0);
                $fieldLabelBase = (string) ($field['field_label'] ?? '');
                $fieldType = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                $fieldGroup = strtolower(trim((string) ($field['field_group'] ?? '')));

                if (!in_array($fieldGroup, ['documents', 'bank_details'], true)) {
                    continue;
                }

                $required = $toBool($field['required'] ?? 0);
                $visible = $toBool($field['visible'] ?? 0);
                $sortOrder = (int) ($field['sort_order'] ?? 0);

                if (!$visible) {
                    continue;
                }
                if ($fieldId <= 0) {
                    continue;
                }

                $customFieldLabelsByLanguage[$fieldId] = [];
                foreach ($languageCodes as $langCode) {
                    $label = $translationsByFieldId[$fieldId][$langCode]
                        ?? ($defaultLanguageCode !== '' ? ($translationsByFieldId[$fieldId][$defaultLanguageCode] ?? null) : null)
                        ?? $fieldLabelBase;
                    $customFieldLabelsByLanguage[$fieldId][$langCode] = $label;
                }

                $fileConfigRaw = (string) ($field['file_config'] ?? '');
                $fileConfig = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];

                $entry = [
                    'id' => $fieldId,
                    'field_label' => $fieldLabelBase,
                    'field_type' => $fieldType,
                    'field_group' => $fieldGroup,
                    'file_config' => $fileConfig,
                    'required' => $required ? 1 : 0,
                    'sort_order' => $sortOrder,
                ];

                if ($fieldGroup === 'documents') {
                    $documentsCustomFields[] = $entry;
                } else {
                    $bankDetailsCustomFields[] = $entry;
                }
            }

            usort($documentsCustomFields, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
            usort($bankDetailsCustomFields, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        }

        return [
            'documents' => $documentsCustomFields,
            'bank_details' => $bankDetailsCustomFields,
            'labels_by_language' => $customFieldLabelsByLanguage,
        ];
    }

    private function getPartnerCustomFieldValuesById(int $partnerId, array $fieldIds): array
    {
        if ($partnerId <= 0 || empty($fieldIds)) {
            return [];
        }

        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), fn($id) => $id > 0));
        if (empty($fieldIds)) {
            return [];
        }

        $db = \Config\Database::connect();

        $valueRows = $db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', (int) $partnerId)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($valueRows as $row) {
            $out[(int) $row['custom_field_id']] = $row['value'] ?? null;
        }
        return $out;
    }

    private function upsertPartnerCustomFieldsValues(int $partnerId, array $valuesById): void
    {
        if ($partnerId <= 0 || empty($valuesById)) {
            return;
        }

        $db = \Config\Database::connect();

        $valuesSql = [];
        $deletionIds = [];

        foreach ($valuesById as $customFieldId => $rawValue) {
            $customFieldId = (int) $customFieldId;
            if ($customFieldId <= 0) {
                continue;
            }

            if (is_array($rawValue)) {
                $rawValue = json_encode($rawValue, JSON_UNESCAPED_SLASHES);
            }

            if ($rawValue === '' || $rawValue === [] || $rawValue === null) {
                $deletionIds[] = $customFieldId;
                continue;
            }

            $valuesSql[] = '(' . (int) $partnerId . ',' . (int) $customFieldId . ',' . $db->escape((string) $rawValue) . ')';
        }

        if (!empty($deletionIds)) {
            $db->table('partner_custom_fields')
                ->where('partner_id', (int) $partnerId)
                ->whereIn('custom_field_id', array_values(array_unique($deletionIds)))
                ->delete();
        }

        if (empty($valuesSql)) {
            return;
        }

        $sql = 'INSERT INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
            . implode(',', $valuesSql)
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';

        $db->query($sql);
    }

    /**
     * Fetch all visible file-type custom fields (documents + bank_details groups).
     */
    private function getVisibleFileCustomFields(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $rows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group', 'required', 'file_config'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->where('field_type', 'file')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $fileConfigRaw = (string) ($row['file_config'] ?? '');
            $row['file_config'] = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];
            $row['required'] = (int) ($row['required'] ?? 0) === 1;
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Convert file extensions (e.g. ['.jpg', '.pdf']) to MIME types for CI4 validation.
     */
    private function extensionsToMimeTypes(array $extensions): array
    {
        $map = [
            '.jpg' => 'image/jpeg',
            '.jpeg' => 'image/jpeg',
            '.png' => 'image/png',
            '.gif' => 'image/gif',
            '.webp' => 'image/webp',
            '.bmp' => 'image/bmp',
            '.tif' => 'image/tiff',
            '.tiff' => 'image/tiff',
            '.svg' => 'image/svg+xml',
            '.pdf' => 'application/pdf',
        ];

        $mimes = [];
        foreach ($extensions as $ext) {
            $ext = strtolower(trim((string) $ext));
            if (isset($map[$ext])) {
                $mimes[] = $map[$ext];
            }
        }
        return array_values(array_unique($mimes));
    }

    /**
     * Collect all visible non-file custom field values from POST data.
     * Used for bank_details and any text/number/textarea/date fields.
     */
    private function collectTextCustomFieldValuesFromPost(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $customFieldRows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->where('field_type !=', 'file')
            ->get()
            ->getResultArray();

        $valuesById = [];
        foreach ($customFieldRows as $fieldRow) {
            $cfId = (int) ($fieldRow['id'] ?? 0);
            if ($cfId <= 0) {
                continue;
            }
            $inputName = 'cf_' . $cfId;
            $valuesById[$cfId] = $this->request->getPost($inputName) ?? '';
        }

        return $valuesById;
    }
}
