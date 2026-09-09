<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Seo_model;
use App\Models\TranslatedPartnerDetails_model;
use App\Services\utility\SlugService;
use Exception;

class AuthApiController extends BaseController
{
    protected $excluded_routes =
        [
            "partner/api/v1",
            "partner/api/v1/index",
            "api/v1/register_provider",
            "api/v1/verify_provider",
            "api/v1/verify_provider_otp",
            "api/v1/resend_provider_otp",
            "partner/api/v1/register",
            "partner/api/v1/verify_user",
            "partner/api/v1/resend_otp",
            "partner/api/v1/verify_otp",
            "partner/api/v1/forgot-password",
            "partner/api/v1/change-password",
            "partner/api/v1/login",
        ];
    protected $validationListTemplate = 'list';
    private $user_details = [];
    private $user_data = ['id', 'username', 'phone', 'email', 'fcm_id', 'web_fcm_id', 'image', 'loginType'];
    protected $configIonAuth, $data, $validation;
    protected $seoModel;
    protected $translationModel;
    protected $serviceTranslationModel;
    protected $slugService;

    function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
        }
        $this->configIonAuth = config('IonAuth');
        helper('session');
        session()->remove('identity');
        $this->seoModel = new Seo_model();
        $this->translationModel = new TranslatedPartnerDetails_model();
        $this->serviceTranslationModel = new \App\Models\TranslatedServiceDetails_model();
        $this->slugService = new SlugService();
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('api-doc.txt')));
    }

    public function register()
    {
        try {
            $request = \Config\Services::request();
            $validation = \Config\Services::validation();
            $request = \Config\Services::request();
            $config = new \Config\IonAuth();
            $db = \Config\Database::connect();

            // Fetch user record by mobile or email based on login_type
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', "3")
                ->where('u.phone', $request->getPost('mobile'))
                ->where('u.email', $request->getPost('email'));
            $user_record = $builder->get()->getResultArray();

            // For new registration $user_record is empty; use login_type from request.
            $loginType = (!empty($user_record) && isset($user_record[0]['loginType']))
                ? $user_record[0]['loginType']
                : $request->getPost('login_type');


            $imagesToDelete = [];
            if ($request->getPost('images_to_delete')) {
                $imagesToDeleteJson = $request->getPost('images_to_delete');
                if (is_string($imagesToDeleteJson)) {
                    $imagesToDelete = json_decode($imagesToDeleteJson, true);
                } elseif (is_array($imagesToDeleteJson)) {
                    $imagesToDelete = $imagesToDeleteJson;
                }
            }

            // Get current other_images from database before deletion
            $currentOtherImages = [];
            if (!empty($user_record)) {
                $currentPartnerData = fetch_details('partner_details', ['partner_id' => $user_record[0]['id']], ['other_images']);
                if (!empty($currentPartnerData[0]['other_images'])) {
                    $currentOtherImages = json_decode($currentPartnerData[0]['other_images'], true) ?? [];
                }
            }

            // Delete specified images before processing uploads
            if (!empty($imagesToDelete)) {
                $deletionResults = $this->processImageDeletion($imagesToDelete);

                // Log deletion results (optional)
                foreach ($deletionResults as $result) {
                    if ($result['result']['error']) {
                        log_the_responce($this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . " Issue => " . $result['result']['message'], date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - register()');
                    }
                }

                // Remove deleted images from database array
                foreach ($imagesToDelete as $imageToDelete) {
                    $parsedInfo = $this->parseImageUrl($imageToDelete);
                    if ($parsedInfo['filename']) {
                        $currentOtherImages = array_filter($currentOtherImages, function ($img) use ($parsedInfo) {
                            return !str_contains($img, $parsedInfo['filename']);
                        });
                    }
                }
                // Re-index array to avoid gaps
                $currentOtherImages = array_values($currentOtherImages);
            }

            $isTokenBasedUpdate = !empty($this->user_details['id']);

            // Resolve user id: from existing record or from token when updating as logged-in partner.
            $resolved_user_id = (!empty($user_record) && isset($user_record[0]['id']))
                ? $user_record[0]['id']
                : ($isTokenBasedUpdate ? (int) $this->user_details['id'] : null);

            //update
            $isExistingUser = exists(['phone' => $request->getPost('mobile'), 'email' => $request->getPost('email'), 'loginType' => $loginType], 'users') && !empty($user_record);
            if (($isExistingUser || $isTokenBasedUpdate) && $resolved_user_id) {
                $userdata = fetch_details('users', ['id' => $resolved_user_id], ['id', 'username', 'email', 'balance', 'active', 'first_name', 'last_name', 'company', 'phone', 'country_code', 'fcm_id', 'image', 'city_id', 'city', 'latitude', 'longitude', 'loginType'])[0];
                $group_id = [
                    'group_id' => 3
                ];
                $user_id = $resolved_user_id;


                // loginType itself is never editable by provider
                if ($userdata['loginType'] === 'email') {
                    // Email-based login: Lock email, allow phone/country_code updates
                    $request->setGlobal('post', array_merge($request->getPost(), ['email' => $userdata['email']]));
                } elseif ($userdata['loginType'] === 'phone') {
                    // Phone-based login: Lock phone and country_code, allow email updates
                    $request->setGlobal('post', array_merge($request->getPost(), ['mobile' => $userdata['phone'], 'country_code' => $userdata['country_code']]));
                }

                $partnerData = fetch_details('partner_details', ['partner_id' => $user_id]);

                $partnerData = !empty($partnerData) ? $partnerData[0] : [];

                // Get default language for extracting default values from translated_fields
                $defaultLanguage = get_default_language();

                // Get translated fields from POST request
                $translatedFields = $this->request->getPost('translated_fields');

                // If translated fields are provided as JSON string, decode it
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }

                // Extract default language values from translated_fields for main table storage
                // This ensures the main partner_details table has the default language values
                $defaultCompanyName = '';
                $defaultAbout = '';
                $defaultLongDescription = '';

                if (!empty($translatedFields) && is_array($translatedFields)) {
                    // Get company_name for default language
                    if (isset($translatedFields['company_name'][$defaultLanguage]) && !empty($translatedFields['company_name'][$defaultLanguage])) {
                        $defaultCompanyName = trim($translatedFields['company_name'][$defaultLanguage]);
                    } elseif (isset($translatedFields['company_name']) && is_array($translatedFields['company_name'])) {
                        // Fallback to first available language if default not found
                        $defaultCompanyName = !empty($translatedFields['company_name']) ? trim(reset($translatedFields['company_name'])) : '';
                    }

                    // Get about_provider for default language (maps to 'about' in database)
                    if (isset($translatedFields['about_provider'][$defaultLanguage]) && !empty($translatedFields['about_provider'][$defaultLanguage])) {
                        $defaultAbout = trim($translatedFields['about_provider'][$defaultLanguage]);
                    } elseif (isset($translatedFields['about_provider']) && is_array($translatedFields['about_provider'])) {
                        // Fallback to first available language if default not found
                        $defaultAbout = !empty($translatedFields['about_provider']) ? trim(reset($translatedFields['about_provider'])) : '';
                    }

                    // Get long_description for default language
                    if (isset($translatedFields['long_description'][$defaultLanguage]) && !empty($translatedFields['long_description'][$defaultLanguage])) {
                        $defaultLongDescription = trim($translatedFields['long_description'][$defaultLanguage]);
                    } elseif (isset($translatedFields['long_description']) && is_array($translatedFields['long_description'])) {
                        // Fallback to first available language if default not found
                        $defaultLongDescription = !empty($translatedFields['long_description']) ? trim(reset($translatedFields['long_description'])) : '';
                    }
                }

                // Fallback to direct POST values if translated_fields not provided
                // This maintains backward compatibility
                if (empty($defaultCompanyName)) {
                    $defaultCompanyName = $request->getPost('company_name') ? trim($request->getPost('company_name')) : '';
                }
                if (empty($defaultAbout)) {
                    $defaultAbout = $request->getPost('about') ? trim($request->getPost('about')) : '';
                    // Also check for about_provider as alternative field name
                    if (empty($defaultAbout)) {
                        $defaultAbout = $request->getPost('about_provider') ? trim($request->getPost('about_provider')) : '';
                    }
                }
                if (empty($defaultLongDescription)) {
                    $defaultLongDescription = $request->getPost('long_description') ? trim($request->getPost('long_description')) : '';
                }

                $fields = [
                    'type',
                    'visiting_charges',
                    'advance_booking_days',
                    'number_of_members',
                    'address',
                    'post_booking_chat' => 'chat',
                    'pre_booking_chat' => 'pre_chat',
                    'at_store',
                    'at_doorstep'
                ];

                foreach ($fields as $requestKey => $partnerKey) {
                    if (is_int($requestKey))
                        $requestKey = $partnerKey; // When the key is an integer, use it as a string

                    $partner[$partnerKey] = !empty($request->getPost($requestKey))
                        ? $request->getPost($requestKey)
                        : (in_array($partnerKey, ['chat', 'pre_chat', 'at_store', 'at_doorstep']) ? 0 : null);
                }

                // Set translatable fields with default language values for main table
                // These values are stored in partner_details table as fallback
                if (!empty($defaultCompanyName)) {
                    $partner['company_name'] = $defaultCompanyName;
                }
                if (!empty($defaultAbout)) {
                    $partner['about'] = $defaultAbout;
                }
                if (!empty($defaultLongDescription)) {
                    $partner['long_description'] = $defaultLongDescription;
                }

                $fileService = service('fileService');
                $IdProofs = fetch_details('partner_details', ['partner_id' => $user_id], ['banner', 'other_images']);
                $IdProofs = !empty($IdProofs) ? $IdProofs[0] : [];

                // Parse custom_fields array from the request.
                // Expected format: custom_fields = [{"custom_field_id": 1, "value": "..."}, ...]
                // Accepts both JSON string and form-encoded array.
                // For file-type fields, the file is sent inline as custom_fields[N][value]=@file.
                $customFieldValues = [];
                $customFieldIndexMap = []; // Maps custom_field_id => array index (for file access)
                $rawCustomFields = $request->getPost('custom_fields');
                if ($rawCustomFields !== null) {
                    if (is_string($rawCustomFields)) {
                        $rawCustomFields = json_decode($rawCustomFields, true);
                    }
                    if (is_array($rawCustomFields)) {
                        $validationError = $this->validateProviderCustomFields($rawCustomFields, (int) $user_id);
                        if ($validationError !== null) {
                            return $this->response->setJSON([
                                'error' => true,
                                'message' => $validationError,
                            ]);
                        }
                        foreach ($rawCustomFields as $index => $entry) {
                            $cfId = (int) $entry['custom_field_id'];
                            $value = array_key_exists('value', $entry) ? $entry['value'] : null;
                            $customFieldValues[$cfId] = $value;
                            $customFieldIndexMap[$cfId] = $index;
                        }
                    }
                }

                $old_image = '';
                $old_banner = '';
                $old_other_images = '';
                if (!empty($IdProofs)) {
                    $old_image = $userdata['image'];
                    $old_banner = $IdProofs['banner'];
                    $old_other_images = $IdProofs['other_images'];
                }
                $paths = [
                    'image' => ['file' => $this->request->getFile('image'), 'folder' => 'profile', 'old_file' => $old_image],
                    'banner_image' => ['file' => $this->request->getFile('banner_image'), 'folder' => 'banner', 'old_file' => $old_banner],
                ];
                // Process single file uploads
                $uploadedFiles = [];

                foreach ($paths as $key => $config) {
                    $file = $config['file'];

                    if (!empty($_FILES[$key]) && $file && $file->isValid()) {
                        $result = $fileService->replace(
                            !empty($config['old_file']) ? $config['old_file'] : null,
                            $file,
                            $config['folder']
                        );
                        if ($result['error']) {
                            return ErrorResponse($result['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $uploadedFiles[$key] = $result['path'];
                    } else {
                        $uploadedFiles[$key] = $config['old_file'];
                    }
                }


                $multipleFiles = $this->request->getFiles();
                $uploadedOtherImages = [];

                // Start with current images after deletions
                $finalOtherImages = $currentOtherImages;

                if (isset($multipleFiles['other_images'])) {
                    foreach ($multipleFiles['other_images'] as $file) {
                        if ($file->isValid()) {
                            $result = $fileService->upload($file, 'partner');
                            if (!$result['error']) {
                                // Add new image to final images array
                                $finalOtherImages[] = $result['path'];
                            } else {
                                return ErrorResponse($result['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        }
                    }
                }

                // Set final other_images (existing after deletions + new uploads)
                $other_images = json_encode($finalOtherImages);
                $banner = $uploadedFiles['banner_image'] ?? "";

                $partner['other_images'] = $other_images;
                $partner['banner'] = $banner;
                if (!empty($request->getPost('city'))) {
                    $userdata['city'] = $request->getPost('city');
                }
                if (!empty($request->getPost('latitude'))) {
                    // Updated validation to match app-side validation patterns
                    // Latitude: -90 to 90 degrees, max 7 decimal places
                    if (!preg_match('/^-?(90(\.0{1,7})?|[0-8]?\d(\.\d{1,7})?)$/', $this->request->getPost('latitude'))) {
                        $response['error'] = true;
                        $response['message'] = labels(PLEASE_ENTER_VALID_LATITUDE, "Please enter valid latitude");
                        return $this->response->setJSON($response);
                    }
                    $userdata['latitude'] = $request->getPost('latitude');
                }
                if (!empty($request->getPost('longitude'))) {
                    // Updated validation to match app-side validation patterns
                    // Longitude: -180 to 180 degrees, max 7 decimal places
                    if (!preg_match('/^-?(180(\.0{1,7})?|1[0-7]\d(\.\d{1,7})?|[0-9]?\d(\.\d{1,7})?)$/', $this->request->getPost('longitude'))) {
                        $response['error'] = true;
                        $response['message'] = labels(PLEASE_ENTER_VALID_LONGITUDE, "Please enter valid Longitude");
                        return $this->response->setJSON($response);
                    }
                    $userdata['longitude'] = $request->getPost('longitude');
                }

                $maxServiceableDistance = $request->getPost('max_serviceable_distance');
                if ($maxServiceableDistance !== null && $maxServiceableDistance !== '') {
                    if (!is_numeric($maxServiceableDistance) || (float) $maxServiceableDistance < 0) {
                        $response['error'] = true;
                        $response['message'] = labels('max_serviceable_distance_must_be_a_number', 'Max serviceable distance must be a number');
                        return $this->response->setJSON($response);
                    }
                    $partner['max_serviceable_distance'] = $maxServiceableDistance;
                } else {
                    $partner['max_serviceable_distance'] = null;
                }

                // Handle multi-language username
                $this->processUsernameField($userdata);

                // CRITICAL: Enforce loginType-based field restrictions
                // loginType determines which authentication method was used during registration
                // These fields must remain locked to prevent security issues and data inconsistencies
                $currentLoginType = $userdata['loginType'];

                if ($currentLoginType === 'email') {
                    // Email-based login: email is LOCKED (used for authentication)
                    // Keep the existing email from database, ignore any updates
                    // Phone and country_code CAN be updated as they're not used for auth
                    if (!empty($request->getPost('mobile'))) {
                        $userdata['phone'] = $request->getPost('mobile');
                    }
                    if (!empty($request->getPost('country_code'))) {
                        $countryCode = $request->getPost('country_code');
                        // Normalize country code (ensure it has + prefix)
                        if (strpos($countryCode, '+') !== 0) {
                            $countryCode = '+' . $countryCode;
                        }
                        $userdata['country_code'] = $countryCode;
                    }
                    // Email stays as is from $userdata (locked)
                } elseif ($currentLoginType === 'phone') {
                    // Phone-based login: phone and country_code are LOCKED (used for authentication)
                    // Keep existing phone and country_code from database, ignore any updates
                    // Email CAN be updated as it's not used for auth
                    if (!empty($request->getPost('email'))) {
                        $userdata['email'] = $request->getPost('email');
                    }
                    // Phone and country_code stay as is from $userdata (locked)
                }
                // loginType itself is NEVER editable by providers - security measure
                // It's set during registration and cannot be changed

                $image = $uploadedFiles['image'] ?? '';
                $userdata['image'] = $image ?? $userdata['image'];

                $update_user = update_details($userdata, ['id' => $user_id], "users", false);
                $update_partner = update_details($partner, ['partner_id' => $user_id], 'partner_details', false);
                $partner_id = $user_id;

                // Upsert custom field values (text + file uploads) into partner_custom_fields
                $this->upsertProviderCustomFields((int) $user_id, $customFieldValues, $customFieldIndexMap);

                $this->saveProviderSeoSettings($partner_id);

                // Process partner translations if provided
                $this->processPartnerTranslations($partner_id);

                if ($update_user && $update_partner) {
                    $getData = fetch_partner_formatted_data($user_id);
                    $response = [
                        'error' => false,
                        'message' => labels(USER_UPDATED_SUCCESSFULLY, 'User Updated successfully'),
                        'data' => $getData,
                    ];
                    // Get company name from translations or use default
                    $companyName = $request->getPost('company_name') ?: 'Partner';


                    // Send FCM notification to admin users about provider updating their information
                    // The FCM template with key 'provider_update_information' is already configured
                    try {
                        // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Starting FCM notification process for provider_id: ' . $partner_id);

                        // Get provider name with translation support
                        $providerName = get_translated_partner_field($partner_id, 'user_name');
                        if (empty($providerName)) {
                            $providerData = fetch_details('users', ['id' => $partner_id], ['username']);
                            $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                        }
                        // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Provider name: ' . $providerName . ', Provider ID: ' . $partner_id);

                        // Prepare context data for the notification template
                        $context = [
                            'provider_name' => $providerName,
                            'provider_id' => $partner_id,
                            'company_name' => $companyName
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
                                'channels' => ['fcm'] // FCM channel only - email and SMS are handled above
                            ]
                        );
                        // log_message('info', '[PROVIDER_UPDATE_INFORMATION] FCM notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // log_message('error', '[PROVIDER_UPDATE_INFORMATION] FCM notification error: ' . $notificationError->getMessage());
                        log_message('error', '[PROVIDER_UPDATE_INFORMATION] FCM notification error trace: ' . $notificationError->getTraceAsString());
                    }

                    return $this->response->setJSON($response);
                } else {
                    $response = [
                        'error' => false,
                        'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                    ];
                }
                return $this->response->setJSON($response);
            }
            //new provider
            else {
                $validation->setRules(
                    [
                        'company_name' => 'required',
                        'country_code' => 'required',
                        'username' => 'required',
                        'email' => 'required|valid_email|',
                        'mobile' => 'required|numeric|',
                        'password' => 'required|matches[password_confirm]',
                        'password_confirm' => 'required',
                        'login_type' => 'required',
                    ],
                );
                if (!$validation->withRequest($this->request)->run()) {
                    $errors = $validation->getErrors();
                    $response = [
                        'error' => true,
                        'message' => reset($errors),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }

                // Password strength: must match frontend indicator (number, uppercase, special, min length)
                $strengthErrors = validate_password_strength($this->request->getPost('password'));
                if (!empty($strengthErrors)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => reset($strengthErrors),
                        'data' => []
                    ]);
                }

                $mobile = $request->getPost('mobile');
                // loginType must reflect how the user was verified: email OTP => email, phone OTP => phone.
                $loginType = $request->getPost('login_type');
                $country = $request->getPost('country_code');

                // Normalize country code (+)
                if (strpos($country, '+') !== 0) {
                    $country = '+' . $country;
                }

                $db = \Config\Database::connect();

                // Login-type-aware uniqueness check:
                // - email login: email must be unique among partners
                // - phone login: country_code + phone must be unique among partners
                $builder = $db->table('users u');
                $builder->select('u.id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', 3);

                if ($loginType === 'email') {
                    $builder->where('u.email', $request->getPost('email'))
                        ->where('u.loginType', 'email');
                } else {
                    $builder->where('u.phone', $mobile)
                        ->where('u.country_code', $country)
                        ->where('u.loginType', 'phone');
                }

                $existingUser = $builder->get()->getResultArray();

                if (!empty($existingUser)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels('user_already_exists', 'User already exists'),
                    ]);
                }

                // Manually create user instead of IonAuth->register() to avoid
                // IonAuth's blanket identity uniqueness check which doesn't consider loginType
                $hashedPassword = password_hash($request->getPost('password'), PASSWORD_BCRYPT, ['cost' => 10]);
                if ($hashedPassword === false) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels('error_occured', 'Error occurred'),
                    ]);
                }

                $userData = [
                    'phone' => $mobile,
                    'username' => $request->getPost('username') ?? 'user_' . time(),
                    'password' => $hashedPassword,
                    'email' => $request->getPost('email'),
                    'ip_address' => $request->getIPAddress(),
                    'created_on' => time(),
                    'active' => 1,
                    'latitude' => '',
                    'longitude' => '',
                    'city' => '',
                    'image' => '',
                    'country_code' => $country,
                    'loginType' => $loginType,
                ];

                $db->table('users')->insert($userData);
                $user_id = $db->insertID();

                if ($user_id) {
                    $db->table('users_groups')->insert([
                        'user_id' => $user_id,
                        'group_id' => 3,
                    ]);
                    $data = array();
                    $identity = ($request->getPost('login_type') === 'phone') ? $request->getPost('mobile') : $request->getPost('email');
                    // Pass country_code for phone so same number in different countries gets correct user
                    $token = generate_tokens(identity: $identity, user_group: 3, loginType: $request->getPost('login_type'), country_code: $country);


                    if ($request->getPost('fcm_id')) {
                        store_users_fcm_id($user_id, $request->getPost('fcm_id'), $request->getPost('platform'));
                    }
                    $token_data['user_id'] = $user_id;
                    $token_data['token'] = $token;
                    if (isset($token_data) && !empty($token_data)) {
                        insert_details($token_data, 'users_tokens');
                    }
                    // update_details(['api_key' => $token], ['username' => $defaultUsername], "users");
                    $data = fetch_details('users', ['id' => $user_id], $this->user_data)[0];
                    $data['login_type'] = $data['loginType'];
                    unset($data['loginType']);
                    $data = remove_null_values($data);
                    $partner_id = $data['id'];
                    $banner = $uploadedFiles['banner_image'] ?? '';
                    // Generate unique slug based on company name using the same function as in Partners.php
                    $slug = $this->slugService->generate(
                        raw: $request->getPost('company_name'),
                        fallback: $request->getPost('company_name'),
                        table: 'partner_details'
                    );
                    $partner = [
                        'company_name' => $request->getPost('company_name'),
                        'about' => ($request->getPost('about')) ? $request->getPost('about') : "",
                        'long_description' => ($request->getPost('long_description')) ? $request->getPost('long_description') : "",
                        'partner_id' => $partner_id,
                        'address' => ($request->getPost('address')) ? $request->getPost('address') : "",
                        'advance_booking_days' => ($request->getPost('advance_booking_days')) ? $request->getPost('advance_booking_days') : "",
                        'type' => ($request->getPost('type') && !empty($request->getPost('type'))) ? $request->getPost('type') : "",
                        'number_of_members' => ($request->getPost('number_of_members')) ? $request->getPost('number_of_members') : "",
                        'visiting_charges' => ($request->getPost('visiting_charges')) ? $request->getPost('visiting_charges') : "",
                        'ratings' => 0,
                        'number_of_ratings' => 0,
                        'is_approved' => ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0)) ? 1 : 0,
                        'slug' => $slug,
                        'banner' => isset($banner) ? $banner : "",
                        'other_images' => isset($other_images) ? $other_images : "",
                        'chat' => (isset($_POST['post_booking_chat'])) ? $_POST['post_booking_chat'] : "0",
                        'pre_chat' => (isset($_POST['pre_booking_chat'])) ? $_POST['pre_booking_chat'] : "0",
                        'at_store' => (isset($_POST['at_store'])) ? $_POST['at_store'] : "0",
                        'at_doorstep' => (isset($_POST['at_doorstep'])) ? $_POST['at_doorstep'] : "0",
                    ];

                    insert_details($partner, 'partner_details');

                    // Upsert custom field values (documents + bank details) into partner_custom_fields.
                    // Registration accepts the same custom_fields array format as the update endpoint.
                    $rawCustomFieldsReg = $request->getPost('custom_fields');
                    $regCustomFieldValues = [];
                    $regCustomFieldIndexMap = [];
                    if ($rawCustomFieldsReg !== null) {
                        if (is_string($rawCustomFieldsReg)) {
                            $rawCustomFieldsReg = json_decode($rawCustomFieldsReg, true);
                        }
                        if (is_array($rawCustomFieldsReg)) {
                            foreach ($rawCustomFieldsReg as $index => $entry) {
                                $cfId = (int) ($entry['custom_field_id'] ?? 0);
                                if ($cfId > 0) {
                                    $regCustomFieldValues[$cfId] = array_key_exists('value', $entry) ? $entry['value'] : null;
                                    $regCustomFieldIndexMap[$cfId] = $index;
                                }
                            }
                        }
                    }
                    if (!empty($regCustomFieldValues)) {
                        $this->upsertProviderCustomFields((int) $partner_id, $regCustomFieldValues, $regCustomFieldIndexMap);
                    }

                    // New provider registration: seed default schedule into provider_shifts.
                    // Working hours edited later via partner panel / dedicated endpoint.
                    $this->persistProviderShifts((int) $data['id'], null);

                    $this->saveProviderSeoSettings($partner_id);

                    // Process partner translations if provided
                    $this->processPartnerTranslations($partner_id);

                    $getData = fetch_partner_formatted_data($data['id']);
                    $response = [
                        'error' => false,
                        'token' => $token,
                        'message' => labels(USER_REGISTERED_SUCCESSFULLY, 'User Registered successfully'),
                        'data' => $getData,
                    ];
                    // Get company name from translations or use default
                    $companyName = $request->getPost('company_name') ?: 'Partner';


                    // Send FCM notification to admin users about new provider registration
                    // The FCM template with key 'new_provider_registerd' is already configured
                    try {
                        // log_message('info', '[NEW_PROVIDER_REGISTERED] Starting FCM notification process for provider_id: ' . $partner_id);

                        // Get provider name with translation support
                        $providerName = get_translated_partner_field($partner_id, 'user_name');
                        if (empty($providerName)) {
                            $providerData = fetch_details('users', ['id' => $partner_id], ['username']);
                            $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                        }
                        // log_message('info', '[NEW_PROVIDER_REGISTERED] Provider name: ' . $providerName . ', Provider ID: ' . $partner_id);

                        // Prepare context data for the notification template
                        $context = [
                            'provider_name' => $providerName,
                            'provider_id' => $partner_id,
                            'company_name' => $companyName
                        ];
                        // log_message('info', '[NEW_PROVIDER_REGISTERED] Context prepared: ' . json_encode($context));

                        // Queue notification to admin users (group_id = 1) via FCM channel
                        // The service will check preferences and configurations to determine if FCM should be sent
                        queue_notification_service(
                            eventType: 'new_provider_registerd',
                            recipients: [],
                            context: $context,
                            options: [
                                'user_groups' => [1], // Admin user group
                                'channels' => ['fcm', 'email', 'sms'] // FCM channel only - email and SMS are handled above
                            ]
                        );
                        // log_message('info', '[NEW_PROVIDER_REGISTERED] FCM notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        log_message('error', '[NEW_PROVIDER_REGISTERED] FCM notification error trace: ' . $notificationError->getTraceAsString());
                    }

                    return $this->response->setJSON($response);
                } else {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                    ]);
                }
            }
        } catch (\Exception $th) {
            // throw $th;
            log_the_responce(" Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_orders()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function verify_user()
    {
        // 101:- Mobile number already registered and Active
        // 102:- Mobile number is not registered
        // 103:- Mobile number is Deactive (edited) 
        // 104:- Email already registered and Active
        // 105:- Email is not registered
        // 106:- Email is De-active
        // login_type is required so we only conflict when same identity + same loginType (allows phone-login provider when email is taken by email-login provider).
        try {
            $req = service('request');
            $db = \Config\Database::connect();

            $email = trim($req->getPost('email') ?? '');
            $mobile = trim($req->getPost('mobile') ?? '');
            $uid = trim($req->getPost('uid') ?? '');
            $country = trim($req->getPost('country_code') ?? '');
            $loginType = strtolower(trim($req->getPost('login_type') ?? ''));

            /* ==========================
                HANDYMAN AUTO-DETECT (mirrors login() routing)
                If a non-soft-deleted group_id=4 user exists for this phone+country_code,
                run handyman verification instead of partner flow.
            =========================== */
            if ($mobile !== '' && !in_array($loginType, ['phone', 'email'], true)) {
                // No login_type supplied → handyman app flow.
                // Return conclusively (101/102/103) — never fall through to partner check
                // which would error on missing login_type.
                $handymanBuilder = $db->table('users u')
                    ->select('u.id, u.active')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', 4)
                    ->where('u.phone', $mobile)
                    ->where('u.deleted_at IS NULL');

                if ($country !== '') {
                    $handymanBuilder->where('u.country_code', $country);
                }

                $handymanRow = $handymanBuilder->get()->getRowArray();

                $found = !empty($handymanRow) && isset($handymanRow['id']);
                $code = $found ? ($handymanRow['active'] ? "101" : "103") : "102";

                $settings = get_settings('general_settings', true);
                return $this->response->setJSON([
                    'error' => $found,
                    'message_code' => $code,
                    'authentication_mode' => $settings['authentication_mode'],
                ]);
            }

            // Require login_type so existence check is scoped to that method (email OTP vs phone OTP).
            if (!in_array($loginType, ['phone', 'email'], true)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('login_type_required', 'login_type is required (phone or email)')
                ]);
            }

            // must supply either one
            if (!$email && !$mobile) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ENTER_MOBILE_OR_EMAIL, 'Enter Mobile or Email')
                ]);
            }

            // base query – JUST users + group
            $builder = $db->table('users u')
                ->select('u.id,u.active,u.phone,u.email,u.country_code')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('u.loginType', $loginType);

            /* ==========================
                EMAIL CHECK
            =========================== */
            if ($email) {
                $user = $builder
                    ->where('u.email', $email)
                    ->get()
                    ->getRowArray();

                $code = "105"; // default not found

                if ($user) {
                    $code = $user['active'] ? "104" : "106";
                }

                $settings = get_settings('general_settings', true);
                return $this->response->setJSON([
                    'error' => false,
                    'message_code' => $code,
                    'authentication_mode' => $settings['authentication_mode']
                ]);
            }

            /* ==========================
                MOBILE CHECK
            =========================== */
            if (!$mobile && !$uid) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ENTER_MOBILE_OR_UID, 'Enter Mobile or uid')
                ]);
            }

            $user = $builder
                ->where('u.phone', $mobile)
                ->where('u.country_code', $country)
                ->get()
                ->getRowArray();

            // default not found
            $code = "102";
            $error = false;

            if ($user) {
                $code = $user['active'] ? "101" : "103";
                $error = true; // matching your original "exists = error=true"
            }

            $settings = get_settings('general_settings', true);
            return $this->response->setJSON([
                'error' => $error,
                'message_code' => $code,
                'authentication_mode' => $settings['authentication_mode']
            ]);
        } catch (\Throwable $e) {
            // throw $e;
            log_message('error', 'verify_user fail: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')
            ]);
        }
    }

    public function resend_otp()
    {

        $validation = \Config\Services::validation();
        $request = \Config\Services::request();
        $mobile = trim($request->getPost('mobile') ?? '');
        $email = trim($request->getPost('email') ?? '');
        $country_code = trim($request->getPost('country_code') ?? '');

        $rules = [];
        $messages = [];

        if (!empty($mobile) && !empty($email)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ENTER_MOBILE_OR_EMAIL, 'Enter either Mobile or Email, not both'),
                'data' => []
            ]);
        }

        if (empty($mobile) && empty($email)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ENTER_MOBILE_OR_EMAIL, 'Enter Mobile or Email'),
                'data' => []
            ]);
        }

        if (!empty($email)) {
            $validation->setRules([
                'email' => 'required|valid_email'
            ], [
                'email' => [
                    'required' => labels('email_address_required', 'Email address is required.'),
                    'valid_email' => labels('valid_email_address', 'Please enter a valid email address.')
                ]
            ]);

            if (!$validation->withRequest($request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => reset($errors),
                    'data' => []
                ]);
            }
        }

        if (!empty($mobile)) {
            $validation->setRules([
                'mobile' => 'required'
            ], [
                'mobile' => [
                    'required' => labels('mobile_number_required', 'Mobile number is required.')
                ]
            ]);

            if (!$validation->withRequest($request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => reset($errors),
                    'data' => []
                ]);
            }
        }


        if (!empty($mobile)) {

            // Case 1: App sends full number (no separate country code)
            if (empty($country_code)) {

                // Starts with +
                if (strpos($mobile, '+') === 0) {
                    if (preg_match('/^\+(\d{1,4})(\d{6,15})$/', $mobile, $matches)) {
                        $country_code = '+' . $matches[1];
                        $mobile = $matches[2];
                    }
                }
                // No + but combined
                else {
                    if (preg_match('/^(\d{1,4})(\d{6,15})$/', $mobile, $matches)) {
                        $country_code = '+' . $matches[1];
                        $mobile = $matches[2];
                    }
                }
            }

            // Case 2: Web sends separate country code
            else {
                // Ensure + exists
                if (strpos($country_code, '+') !== 0) {
                    $country_code = '+' . $country_code;
                }
            }

            // Final safety check
            if (empty($country_code) || empty($mobile)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('invalid_mobile_format', 'Invalid mobile number format.'),
                    'data' => []
                ]);
            }
        }

        // Generate OTP
        $otp = random_int(100000, 999999);

        // Handle email OTP sending
        if (!empty($email)) {

            $send_otp_response = set_user_otp_email($email, $otp);

            return $this->response->setJSON([
                'error' => $send_otp_response['error'],
                'message' => $send_otp_response['error']
                    ? $send_otp_response['message']
                    : labels(OTP_SEND_SUCCESSFULLY, "OTP sent successfully"),
                'data' => []
            ]);
        }


        // Handle mobile/SMS OTP sending (existing logic)
        if (!empty($mobile)) {

            $authentication_mode = get_settings('general_settings', true);

            if ($authentication_mode['authentication_mode'] === "sms_gateway") {

                $send_otp_response = set_user_otp($otp, $mobile, $country_code);

                return $this->response->setJSON([
                    'error' => $send_otp_response['error'],
                    'message' => $send_otp_response['error']
                        ? $send_otp_response['message']
                        : labels(OTP_SEND_SUCCESSFULLY, "OTP sent successfully"),
                    'data' => []
                ]);
            }

            return $this->response->setJSON([
                'error' => true,
                'message' => labels('sms_auth_disabled', 'SMS authentication is disabled.'),
                'data' => []
            ]);
        }

        // If neither mobile nor email is provided (shouldn't reach here due to validation)
        $response = [
            'error' => true,
            'message' => labels(ENTER_MOBILE_OR_EMAIL, 'Enter Mobile or Email'),
            'data' => [],
        ];
        return $this->response->setJSON($response);
    }

    public function verify_otp()
    {
        $validation = service('validation');

        $email = $this->request->getPost('email');
        $mobile = $this->request->getPost('mobile');

        $rules = [
            'otp' => 'required'
        ];

        if (!empty($email)) {
            $rules['email'] = 'required|valid_email';
        } elseif (!empty($mobile)) {
            $rules['mobile'] = 'required';
        } else {
            return $this->response->setJSON([
                'error' => true,
                'message' => 'Either email or mobile is required.',
            ]);
        }

        $validation->setRules($rules);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'error' => true,
                'message' => $validation->getErrors(),
                'data' => [],
            ]);
        }

        $otp = $this->request->getPost('otp');
        $email = $this->request->getPost('email');

        /**
         * -------------------------------------
         * Decide channel & identity dynamically
         * -------------------------------------
         */
        if (!empty($email)) {
            // Email OTP
            $identity = $email;
            $channel = 'email';
        } else {
            // SMS OTP
            $mobile = trim($this->request->getPost('mobile') ?? '');
            $country_code = trim($this->request->getPost('country_code') ?? '');
            // Ensure country code starts with +
            if (!empty($country_code)) {
                if (strpos($country_code, '+') !== 0) {
                    $country_code = '+' . ltrim($country_code, '+');
                }
            }

            $identity = $country_code . $mobile;
            $channel = 'sms';
        }

        /**
         * -------------------------------------
         * Fetch OTP
         * -------------------------------------
         */
        $otpData = fetch_details('otps', [
            'identity' => $identity,
            'channel' => $channel,
            'otp' => $otp,
        ]);

        if (empty($otpData)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(OTP_NOT_VERIFIED, 'OTP not verified'),
            ]);
        }

        /**
         * -------------------------------------
         * Expiry check
         * -------------------------------------
         */
        $timeExpire = checkOTPExpiration($otpData[0]['created_at']);

        if ($timeExpire['error'] == 1) {
            return $this->response->setJSON([
                'error' => true,
                'message' => $timeExpire['message'],
            ]);
        }

        /**
         * -------------------------------------
         * Success
         * -------------------------------------
         */
        return $this->response->setJSON([
            'error' => false,
            'message' => labels(OTP_VERIFIED, 'OTP verified'),
        ]);
    }

    public function forgot_password()
    {
        try {

            $validation = service('validation');

            $loginType = trim($this->request->getPost('login_type') ?? 'phone');

            $rules = [
                'login_type' => 'required|in_list[phone,email]',
                'new_password' => 'required',
            ];

            // add phone fields if phone login
            if ($loginType === 'phone') {
                $rules['mobile_number'] = 'required';
                $rules['country_code'] = 'required';
            }

            // add email field if email login
            if ($loginType === 'email') {
                $rules['email'] = 'required|valid_email';
            }

            $validation = service('validation');
            $validation->setRules($rules);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => []
                ]);
            }

            $mobile = trim($this->request->getPost('mobile_number') ?? '');
            $email = trim($this->request->getPost('email') ?? '');
            $code = trim($this->request->getPost('country_code') ?? '');
            $password = trim($this->request->getPost('new_password') ?? '');

            if (!$mobile && !$email) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ENTER_MOBILE_OR_EMAIL, 'Enter Mobile or Email'),
                    'data' => []
                ]);
            }

            $db = \Config\Database::connect();
            $builder = $db->table('users u')
                ->select('u.*, ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('u.loginType', $loginType);

            // Figure out lookup
            if ($email) {
                $builder->where('u.email', $email);
            } else {
                $builder->where('u.phone', $mobile)
                    ->where('u.country_code', $code);
            }

            $user = $builder->get()->getRowArray();

            if (empty($user)) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(USER_DOES_NOT_EXIST, "User does not exist"),
                    "data" => $_POST,
                ]);
            }

            // Demo mode restriction – block only the specific demo partner
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $this->isDemoRestrictedPartner($user)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.')
                ]);
            }

            // Check if account is active
            if (isset($user['active']) && (int) $user['active'] === 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('account_is_inactive', 'Account is inactive')
                ]);
            }

            // Enforce minimum password length
            if (strlen($password) < 6) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('password_min_length', 'Password must be at least 6 characters')
                ]);
            }

            // Hash the new password manually (bcrypt, cost 10 – same as IonAuth default)
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

            if ($hashedPassword === false) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('password_hash_failed', 'Password hashing failed. Please try again.')
                ]);
            }

            // Update password in a transaction
            $db->transStart();

            $db->table('users')->where('id', $user['id'])->update([
                'password' => $hashedPassword,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('password_reset_failed', 'Password reset failed. Please try again.'),
                ]);
            }

            // Invalidate existing tokens so the user must re-login with the new password
            delete_details(['user_id' => $user['id']], 'users_tokens');

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(FORGOT_PASSWORD_SUCCESSFULLY, "Password reset successfully"),
                'data' => []
            ]);
        } catch (\Throwable $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - forgot_password()');
            return $this->response->setJSON($response);
        }
    }

    public function change_password()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'old' => 'required',
                    'new' => 'required',
                ],
                [
                    'old' => [
                        'required' => labels('old_password_required', 'Old password is required')
                    ],
                    'new' => [
                        'required' => labels('new_password_required', 'New password is required')
                    ]
                ]
            );

            // Set custom field labels for better error messages
            $validation->setRule('old', 'old', 'required');
            $validation->setRule('new', 'new', 'required');
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $user_id = $this->user_details['id'];
            $user_data = fetch_details('users', ['id' => $user_id]);
            $userRow = $user_data[0] ?? null;

            // In demo mode, block only the specific demo partner: phone 9876543210, +91, group 3, login type phone
            if (!empty($userRow) && defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $this->isDemoRestrictedPartner($userRow)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.')
                ]);
            }

            $identity = $user_data[0]['phone'];
            $change = $this->ionAuth->changePassword($identity, $this->request->getPost('old'), $this->request->getPost('new'), $user_id);
            if ($change) {
                $this->ionAuth->logout();
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(PASSWORD_CHANGED_SUCCESSFULLY, "Password changed successfully"),
                    "data" => $_POST,
                ]);
            } else {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(OLD_PASSWORD_DID_NOT_MATCHED, "Old password did not matched."),
                    "data" => $_POST,
                ]);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - change_password()');
            return $this->response->setJSON($response);
        }
    }

    /**
     * Returns true only if the user is the specific demo partner that must be blocked in demo mode:
     * phone 9876543210, country_code +91, group_id 3 (from users_groups), loginType phone.
     * Used by change_password to throw demo error only for this partner.
     *
     * @param array $userRow User row with at least id, phone, country_code, loginType
     * @return bool
     */
    private function isDemoRestrictedPartner(array $userRow): bool
    {
        $phone = $userRow['phone'] ?? '';
        $countryCode = $userRow['country_code'] ?? '';
        $loginType = $userRow['loginType'] ?? '';

        if ($phone !== '1234567890' || $countryCode !== '+91' || $loginType !== 'phone') {
            return false;
        }

        $userId = $userRow['id'] ?? null;
        if (empty($userId)) {
            return false;
        }

        $userGroups = fetch_details('users_groups', ['user_id' => $userId]);
        foreach ($userGroups as $ug) {
            if (isset($ug['group_id']) && (int) $ug['group_id'] === 3) {
                return true;
            }
        }

        return false;
    }

    public function login()
    {
        try {
            $validation = \Config\Services::validation();

            // -----------------------------------------------------------------
            // INPUT NORMALIZATION (mirrors Auth::login partner logic)
            // -----------------------------------------------------------------
            $loginTypeRaw = $this->request->getPost('login_type'); // expected: phone / email
            $identityPhone = trim((string) $this->request->getPost('mobile'));
            $identityEmail = trim((string) $this->request->getPost('email'));
            $identityCountryCode = trim((string) $this->request->getPost('country_code'));
            $userType = strtolower(trim($this->request->getPost('user_type') ?? 'provider'));

            // -----------------------------------------------------------------
            // USER TYPE ROUTING — handyman detected by group_id=4 lookup,
            // not by a user_type param. If a handyman row exists for the
            // submitted phone+country_code, route to loginHandyman().
            // -----------------------------------------------------------------
            if ($identityPhone !== '' && $userType === 'handyman') {
                $handymanExists = \Config\Database::connect()
                    ->table('users u')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', 4)
                    ->where('u.phone', $identityPhone)
                    ->where('u.country_code', $identityCountryCode)
                    ->where('u.deleted_at IS NULL')
                    ->countAllResults() > 0;

                if ($handymanExists) {
                    return $this->loginHandyman();
                }
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('user_not_found', 'User not found'),
                ]);
            }

            // Always work with lowercased + trimmed login type
            $loginType = strtolower(trim((string) $loginTypeRaw));

            // If login_type is missing/invalid, auto-detect from identity
            // This is the same behavior used in web Auth::login for partners.
            if (!in_array($loginType, ['phone', 'email'], true)) {
                $probeIdentity = $identityEmail !== '' ? $identityEmail : $identityPhone;
                $loginType = filter_var($probeIdentity, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            }

            // -----------------------------------------------------------------
            // VALIDATION RULES (provider only, group_id = 3)
            // -----------------------------------------------------------------
            switch ($loginType) {
                case 'phone':
                    $identityField = 'mobile';
                    $dbIdentityField = 'phone';
                    $identityRules = 'required|numeric';
                    $identityErrors = [
                        'required' => labels(PHONE_NUMBER_IS_REQUIRED, 'Phone number is required'),
                        'numeric' => labels(PHONE_NUMBER_MUST_BE_A_NUMBER, 'Phone must be numbers'),
                    ];
                    break;

                case 'email':
                    $identityField = 'email';
                    $dbIdentityField = 'email';
                    $identityRules = 'required|valid_email';
                    $identityErrors = [
                        'required' => labels(EMAIL_IS_REQUIRED, 'Email is required'),
                        'valid_email' => labels(ENTER_VALID_EMAIL, 'Email format is invalid'),
                    ];
                    break;

                default:
                    // Just in case someone posts login_type="pigeon"
                    $identityField = 'mobile';
                    $dbIdentityField = 'phone';
                    $identityRules = 'required';
                    $identityErrors = [
                        'required' => labels(PHONE_NUMBER_IS_REQUIRED, 'Phone number is required'),
                    ];
                    break;
            }

            $rules = [
                $identityField => [
                    'rules' => $identityRules,
                    'errors' => $identityErrors,
                ],
                'password' => [
                    'rules' => 'required',
                    'errors' => [
                        'required' => labels(PASSWORD_IS_REQUIRED, 'Password is required'),
                    ],
                ],
            ];


            // Run validation on identity + password
            if (!$validation->setRules($rules)->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $firstError = reset($errors);
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $firstError,
                    'data' => []
                ]);
            }

            // Canonical identity string (used for throttling + DB lookup)
            $identityInput = $loginType === 'phone'
                ? $identityPhone
                : $identityEmail;

            $password = (string) $this->request->getPost('password');

            // -----------------------------------------------------------------
            // THROTTLING (mirrors Auth::login)
            // -----------------------------------------------------------------
            // We protect provider login by combining identity + IP.
            $throttleKey = $identityInput . '|' . $this->request->getIPAddress();

            if ($this->ionAuth->isMaxLoginAttemptsExceeded($throttleKey)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => 'Too many login attempts. Try again later.',
                    'data' => [],
                ]);
            }

            // -----------------------------------------------------------------
            // USER RESOLUTION (provider group only)
            // -----------------------------------------------------------------
            $db = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*, ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)           // 3 = provider/partner group
                ->where('u.loginType', $loginType); // enforce provider login type

            // For email login we match on email; for phone we match on phone
            if ($loginType === 'email') {
                $builder->where('u.email', $identityInput);
            } else {
                $builder->where('u.phone', $identityInput);

                // If caller sends country_code, also scope by it (same as Auth::login)
                $countryCodeFilter = trim((string) $this->request->getPost('country_code'));
                if ($countryCodeFilter !== '') {
                    $builder->where('u.country_code', $countryCodeFilter);
                }
            }

            $user = $builder->get()->getRowArray();

            // -----------------------------------------------------------------
            // USER NOT FOUND
            // -----------------------------------------------------------------
            if (!$user) {
                // Count this as a failed attempt for throttling
                $this->ionAuth->increaseLoginAttempts($throttleKey);

                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(
                        USER_DOES_NOT_EXISTS,
                        'User does not exists !'
                    )
                ]);
            }

            // -----------------------------------------------------------------
            // ACTIVE STATUS CHECK (same as web login)
            // -----------------------------------------------------------------
            if (isset($user['active']) && (int) $user['active'] === 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => 'Account is inactive',
                    'data' => [],
                ]);
            }

            /** ------------------ Country Code Validation ------------------ **/
            $countryCode = $this->request->getPost('country_code') ?? $user['country_code'];
            if (
                !empty($user['country_code']) &&
                $user['country_code'] !== $countryCode &&
                $loginType === 'phone'
            ) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(USER_DOES_NOT_EXISTS, 'User does not exists !')
                ]);
            }

            // -----------------------------------------------------------------
            // PASSWORD VERIFY (mirrors Auth::login partner flow)
            // -----------------------------------------------------------------
            $ionModel = model(\IonAuth\Models\IonAuthModel::class);

            if (!$ionModel->verifyPassword($password, $user['password'], $identityInput)) {
                // Wrong password: increase attempts and return error
                $this->ionAuth->increaseLoginAttempts($throttleKey);

                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(
                        INCORRECT_LOGIN_CREDENTIALS_PLEASE_CHECK_AND_TRY_AGAIN,
                        'Incorrect login credentials. Please check and try again.'
                    )
                ]);
            }

            // -----------------------------------------------------------------
            // SUCCESS: clear attempts + set session & last login
            // -----------------------------------------------------------------
            $this->ionAuth->clearLoginAttempts($throttleKey);

            // IonAuth expects an object here; convert provider row to object.
            $userObject = (object) $user;
            $this->ionAuth->setSession($userObject);
            $this->ionAuth->updateLastLogin($userObject->id);

            // NOTE: Remember-me is not used for API login, so we skip it here.
            // Language and other per-user preferences will be picked up from session
            // by the rest of the system in the same way as web Auth::login.

            // Use phone as IonAuth identity, same as web controller.
            $ionIdentity = $user['phone'];

            /** ------------------ Update Missing User Details ------------------ **/
            // Update country_code first time
            if (empty($user['country_code']) && $countryCode) {
                update_details(['country_code' => $countryCode], ['phone' => $ionIdentity, 'loginType' => $loginType], 'users');
            }

            // FCM
            if ($this->request->getPost('fcm_id')) {
                $language_code = $this->request->getPost('language_code');
                store_users_fcm_id($user['id'], $this->request->getPost('fcm_id'), $this->request->getPost('platform'), null, $language_code);
            }

            // Platform
            if ($this->request->getPost('platform')) {
                update_details(['platform' => $this->request->getPost('platform')], ['phone' => $ionIdentity, 'loginType' => $loginType], 'users');
            }

            /** ------------------ Token + Response Data ------------------ **/
            // Pass country_code for phone so same number in different countries gets correct user
            // Use the same identity string we used for provider lookup / throttling.
            $token = generate_tokens(identity: $identityInput, user_group: 3, loginType: $loginType, country_code: $user['country_code'] ?? null);
            insert_details(['user_id' => $user['id'], 'token' => $token], 'users_tokens');

            $formatted = fetch_partner_formatted_data($user['id']);
            $formatted['user_type'] = 'provider';

            // -----------------------------------------------------------------
            //  Unread admin <-> provider chat count for LOGIN response
            // -----------------------------------------------------------------
            // We mirror the customer-side logic used in UserAuthApiController:
            // - Count only chats where current user (provider) is the receiver
            // - receiver_type = 1 (provider)
            // - sender_type   = 0 (admin)
            // - is_read       = 0 (unread messages only)
            //
            // This helps mobile apps show a quick badge for unread admin chats
            // immediately after login without making an extra API call.
            $unreadAdminChatsCount = $db->table('chats')
                ->where('receiver_id', (int) $user['id'])
                ->where('receiver_type', 1)
                ->where('sender_type', 0)
                ->where('is_read', 0)
                ->countAllResults();

            // Attach to the formatted payload in a simple, explicit key.
            // Use a flat key on root so both apps and web can read it easily.
            $formatted['unread_chats_count'] = (int) $unreadAdminChatsCount;

            return $this->response->setJSON([
                'error' => false,
                'token' => $token,
                'message' => labels(USER_LOGGED_SUCCESSFULLY, 'User Logged successfully'),
                'data' => $formatted,
            ]);
        } catch (\Exception $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params :: ' . json_encode($_POST) . ' Err => ' . $th,
                date("Y-m-d H:i:s") . '--> partner/api/V1.php - login()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    private function loginHandyman(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $validation = \Config\Services::validation();

            // -----------------------------------------------------------------
            // VALIDATION — handyman login is phone-only (mirrors Auth::login handyman branch)
            // -----------------------------------------------------------------
            $validation->setRules([
                'mobile' => [
                    'rules' => 'required|numeric',
                    'errors' => [
                        'required' => labels(PHONE_NUMBER_IS_REQUIRED, 'Phone number is required'),
                        'numeric' => labels(PHONE_NUMBER_MUST_BE_A_NUMBER, 'Phone must be numbers'),
                    ],
                ],
                'password' => [
                    'rules' => 'required',
                    'errors' => [
                        'required' => labels(PASSWORD_IS_REQUIRED, 'Password is required'),
                    ],
                ],
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => reset($errors),
                ]);
            }

            $mobile = trim((string) $this->request->getPost('mobile'));
            $countryCode = trim((string) $this->request->getPost('country_code'));
            $password = (string) $this->request->getPost('password');

            // -----------------------------------------------------------------
            // THROTTLING
            // -----------------------------------------------------------------
            $throttleKey = $mobile . '|' . $this->request->getIPAddress();

            if ($this->ionAuth->isMaxLoginAttemptsExceeded($throttleKey)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => 'Too many login attempts. Try again later.',
                ]);
            }

            // -----------------------------------------------------------------
            // USER RESOLUTION — group_id = 4 (handyman), phone lookup
            // -----------------------------------------------------------------
            $db = \Config\Database::connect();
            $builder = $db->table('users u')
                ->select('u.*, ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 4)
                ->where('u.phone', $mobile)
                ->where('u.deleted_at IS NULL');

            if ($countryCode !== '') {
                $builder->where('u.country_code', $countryCode);
            }

            $user = $builder->get()->getRowArray();

            if (!$user) {
                $this->ionAuth->increaseLoginAttempts($throttleKey);
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(USER_DOES_NOT_EXISTS, 'User does not exists !'),
                ]);
            }

            if (isset($user['active']) && (int) $user['active'] === 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => 'Account is inactive',
                ]);
            }

            // -----------------------------------------------------------------
            // PASSWORD VERIFY
            // -----------------------------------------------------------------
            $ionModel = model(\IonAuth\Models\IonAuthModel::class);

            if (!$ionModel->verifyPassword($password, $user['password'], $mobile)) {
                $this->ionAuth->increaseLoginAttempts($throttleKey);
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(
                        INCORRECT_LOGIN_CREDENTIALS_PLEASE_CHECK_AND_TRY_AGAIN,
                        'Incorrect login credentials. Please check and try again.'
                    ),
                ]);
            }

            // -----------------------------------------------------------------
            // SUCCESS
            // -----------------------------------------------------------------
            $this->ionAuth->clearLoginAttempts($throttleKey);
            $this->ionAuth->setSession((object) $user);
            $this->ionAuth->updateLastLogin($user['id']);

            // FCM
            if ($this->request->getPost('fcm_id')) {
                $languageCode = $this->request->getPost('language_code');
                store_users_fcm_id($user['id'], $this->request->getPost('fcm_id'), $this->request->getPost('platform'), null, $languageCode);
            }

            // generate_tokens() queries by loginType which handyman rows don't have set.
            // Build the JWT directly (same payload shape as generate_tokens).
            $jwt = new \App\Libraries\JWT();
            $token = $jwt->encode([
                'iat' => time(),
                'iss' => 'edemand',
                'exp' => time() + (60 * 60 * 24 * 365),
                'sub' => 'edemand_authentication',
                'user_id' => $user['id'],
            ], API_SECRET);
            $db->table('users_tokens')->insert(['user_id' => (int) $user['id'], 'token' => $token]);

            // Handyman profile data
            $handymanDetails = $db->table('handyman_details hd')
                ->select('hd.partner_id, hd.total_reviews, hd.average_rating, hd.is_available, hd.address')
                ->where('hd.handyman_id', $user['id'])
                ->where('hd.deleted_at IS NULL')
                ->get()
                ->getRowArray();

            $fileService = service('fileService');

            $data = [
                'id' => (int) $user['id'],
                'username' => $user['username'] ?? '',
                'email' => $user['email'] ?? '',
                'phone' => $user['phone'] ?? '',
                'country_code' => $user['country_code'] ?? '',
                'image' => $fileService->url($user['image'] ?? '', 'profile', ''),
                'fcm_id' => $user['fcm_id'] ?? '',
                'partner_id' => isset($handymanDetails['partner_id']) ? (int) $handymanDetails['partner_id'] : null,
                'average_rating' => $handymanDetails['average_rating'] ?? 0,
                'total_ratings' => isset($handymanDetails['total_reviews']) ? (int) $handymanDetails['total_reviews'] : 0,
                // 'is_available' => isset($handymanDetails['is_available']) ? (int) $handymanDetails['is_available'] : 0,
                'address' => $handymanDetails['address'] ?? '',
                'user_type' => 'handyman'
            ];

            return $this->response->setJSON([
                'error' => false,
                'token' => $token,
                'message' => labels(USER_LOGGED_SUCCESSFULLY, 'User Logged successfully'),
                'data' => $data,
            ]);
        } catch (Exception $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params :: ' . json_encode($_POST) . ' Err => ' . $th,
                date('Y-m-d H:i:s') . '--> partner/api/V1.php - loginHandyman()'
            );
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function update_fcm()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'platform' => 'required'
                ],
                []
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $fcm_id = $this->request->getPost('fcm_id');
            $platform = $this->request->getPost('platform');
            $result = store_users_fcm_id($this->user_details['id'], $fcm_id, $platform);
            if ($result) {
                // if (update_details(['fcm_id' => $fcm_id, 'platform' => $platform], ['id' => $this->user_details['id']], 'users')) {
                return response_helper(labels(FCM_ID_UPDATED_SUCCESSFULLY, 'fcm id updated succesfully'), false, ['fcm_id' => $fcm_id]);
            } else {
                return response_helper();
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - update_fcm()');
            return $this->response->setJSON($response);
        }
    }

    public function delete_provider_account()
    {
        try {
            $user_id = $this->user_details['id'];
            if (!exists(['id' => $user_id], 'users')) {
                return response_helper(labels(USER_DOES_NOT_EXIST_PLEASE_ENTER_VALID_USER_ID, 'user does not exist please enter valid user ID!'), true);
            }
            $user_data = fetch_details('users_groups', ['user_id' => $user_id]);
            $fileService = service('fileService');
            if (!empty($user_data) && isset($user_data[0]['group_id']) && !empty($user_data[0]['group_id']) && $user_data[0]['group_id'] == 3) {
                $partner_data = fetch_details('partner_details', ['partner_id' => $user_id]);
                $db = \Config\Database::connect();
                if (!empty($user_data[0]['image'])) {
                    $fileService->delete('profile', $user_data[0]['image']);
                }
                if (!empty($partner_data[0]['banner'])) {
                    $fileService->delete('banner', $partner_data[0]['banner']);
                }

                // Clean up SEO settings and related images before deleting provider.
                $this->seoModel->cleanupSeoData($user_id, 'providers');

                // Document KYC files are stored in partner_custom_fields (file-type, documents group).
                // Delete files from storage first, then remove field rows.
                if ($db->tableExists('custom_fields') && $db->tableExists('partner_custom_fields')) {
                    $documentFieldRows = $db->table('custom_fields')
                        ->select('id')
                        ->where('field_group', 'documents')
                        ->where('field_type', 'file')
                        ->get()
                        ->getResultArray();

                    $documentFieldIds = array_map('intval', array_column($documentFieldRows, 'id'));

                    if (!empty($documentFieldIds)) {
                        $partnerCustomFieldRows = $db->table('partner_custom_fields')
                            ->select(['custom_field_id', 'value'])
                            ->where('partner_id', $user_id)
                            ->whereIn('custom_field_id', $documentFieldIds)
                            ->get()
                            ->getResultArray();

                        foreach ($partnerCustomFieldRows as $customFieldRow) {
                            $fileValue = (string) ($customFieldRow['value'] ?? '');
                            if ($fileValue !== '') {
                                $fileService->delete('custom_fields', $fileValue);
                            }
                        }

                        $db->table('partner_custom_fields')
                            ->where('partner_id', $user_id)
                            ->whereIn('custom_field_id', $documentFieldIds)
                            ->delete();
                    }
                }

                // Legacy fallback (pre-custom-fields data model)
                if (!empty($partner_data[0]['address_id'])) {
                    $fileService->delete('address_id', $partner_data[0]['address_id']);
                }
                if (!empty($partner_data[0]['passport'])) {
                    $fileService->delete('passport', $partner_data[0]['passport']);
                }
                if (!empty($partner_data[0]['national_id'])) {
                    $fileService->delete('national_id', $partner_data[0]['national_id']);
                }
                if (delete_details(['id' => $user_id], 'users') && delete_details(['user_id' => $user_id], 'users_groups')) {
                    delete_details(['user_id' => $user_id], 'users_tokens');
                    delete_details(['partner_id' => $user_id], 'promo_codes');
                    $slider_data = fetch_details('sliders', ['type' => 'services'], 'type_id');
                    foreach ($slider_data as $row) {
                        $data = fetch_details('services', ['id' => $row['type_id']], 'user_id');
                        if ($data[0]['user_id'] == $user_id) {
                            delete_details(['type_id' => $row['type_id']], 'sliders');
                        }
                    }
                    return response_helper(labels(USER_ACCOUNT_DELETED_SUCCESSFULLY, 'User account deleted successfully'), false);
                } else {
                    return response_helper(labels(USER_ACCOUNT_DOES_NOT_DELETE, 'User account does not delete'), true);
                }
            } else {
                return response_helper(labels(THIS_USERS_ACCOUNT_CAN_T_DELETE, "This user's account can't delete "), true);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - delete_provider_account()');
            return $this->response->setJSON($response);
        }
    }

    public function get_user_info()
    {
        try {
            $db = \Config\Database::connect();
            $userId = $this->user_details['id'];

            // ------------------------------------
            // 1. Base user + group check + job count
            // ------------------------------------
            $builder = $db->table('users u');
            $builder->select(
                'u.*, ug.group_id, (
                SELECT COUNT(DISTINCT cj.id)
                FROM custom_job_requests cj
                JOIN custom_job_provider cjp ON cjp.custom_job_request_id = cj.id
                WHERE cj.status = "pending"
                AND cjp.partner_id = u.id
                AND NOT EXISTS (
                    SELECT 1 FROM partner_bids pb
                    WHERE pb.custom_job_request_id = cj.id
                    AND pb.partner_id = u.id
                )
            ) AS total_job_request'
            )
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('u.id', $userId);

            $userRow = $builder->get()->getRowArray();

            if (empty($userRow)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(USER_DOES_NOT_EXISTS, 'User does not exists !'),
                ]);
            }

            // ------------------------------------
            // 2. Fetch minimal user info
            // ------------------------------------
            $data = fetch_details(
                'users',
                ['id' => $userRow['id']],
                ['id', 'username', 'country_code', 'phone', 'email', 'fcm_id', 'image', 'api_key']
            )[0];

            $getData = fetch_partner_formatted_data($data['id']);

            // ------------------------------------
            // 2.b Unread admin <-> provider chat count for PROFILE response
            // ------------------------------------
            // Same idea as customer get_user_info:
            // - Current user (provider) is the receiver
            // - receiver_type = 1 (provider)
            // - sender_type   = 0 (admin)
            // - is_read       = 0 (unread only)
            //
            // We attach this to the formatted provider info structure so
            // dashboards and apps can show a global unread-admin-messages badge.
            $unreadAdminChatsCount = $db->table('chats')
                ->where('receiver_id', (int) $data['id'])
                ->where('receiver_type', 1)
                ->where('sender_type', 0)
                ->where('is_read', 0)
                ->countAllResults();

            // Store at root level of provider information for easy access.
            $getData['unread_chats_count'] = (int) $unreadAdminChatsCount;

            // ------------------------------------
            // 3. Partner preferences
            // ------------------------------------
            $partnerPrefRaw = fetch_details(
                'partner_details',
                ['partner_id' => $userId],
                ['custom_job_categories', 'is_accepting_custom_jobs']
            );

            $partnerCategories = (!empty($partnerPrefRaw) && !empty($partnerPrefRaw[0]['custom_job_categories']))
                ? json_decode($partnerPrefRaw[0]['custom_job_categories'], true)
                : [];

            // ------------------------------------
            // 4. Grab all pending matching jobs
            //    (partner not yet bid)
            // ------------------------------------
            $builder = $db->table('custom_job_requests cj')
                ->select('cj.*, u.username, u.image, c.id AS category_id, c.name AS category_name, c.image AS category_image')
                ->join('users u', 'u.id=cj.user_id')
                ->join('categories c', 'c.id=cj.category_id')
                ->where('cj.status', 'pending')
                ->where("(SELECT COUNT(1)
                        FROM partner_bids pb
                        WHERE pb.custom_job_request_id=cj.id
                        AND pb.partner_id={$userId}) = 0");

            if (!empty($partnerCategories)) {
                $builder->whereIn('cj.category_id', $partnerCategories);
            }

            $jobs = $builder->orderBy('cj.id', 'DESC')->get()->getResultArray();

            // ------------------------------------
            // 5. Prefetch all accepted provider requests
            //    Single query, not one per loop
            // ------------------------------------
            $providerRows = fetch_details(
                'custom_job_provider',
                ['partner_id' => $userId],
                ['custom_job_request_id']
            );

            $providerRequestIds = array_column($providerRows, 'custom_job_request_id');

            // ------------------------------------
            // 6. Filter in-memory, no DB calls
            // ------------------------------------
            $filteredJobs = array_filter(
                $jobs,
                fn($j) =>
                in_array($j['id'], $providerRequestIds)
            );

            // ------------------------------------
            // 7. Image formatting
            // ------------------------------------
            $fileService = service('fileService');
            foreach ($filteredJobs as &$job) {
                $job['image'] = $fileService->url($job['image'] ?? '', 'profiles', 'public/uploads/profiles/default.png');
            }

            // add job count
            $getData['provder_information']['total_job_request'] = count($filteredJobs);

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                'data' => $getData
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params :: ' . json_encode($_POST) .
                " Error => " . $th,
                date("Y-m-d H:i:s") . " --> get_user_info()"
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function get_statistics()
    {
        try {
            $db = \Config\Database::connect();
            $last_monthly_sales = (isset($_POST['last_monthly_sales']) && !empty(trim($_POST['last_monthly_sales']))) ? $this->request->getPost("last_monthly_sales") : 6;
            $partner_id = $this->user_details['id'];
            $categories = $db->table('categories c')->select('c.name as name,count(s.id) as total_services')
                ->where(['s.user_id' => $partner_id])
                ->join('services s', 's.category_id=c.id', 'left')
                ->groupBy('s.category_id')
                ->get()->getResultArray();
            if (!empty($categories)) {
                if ($categories[0]['name'] == '' && $categories[0]['total_services'] == 0) {
                    $this->data['caregories'] = [];
                } else {
                    $this->data['caregories'] = $categories;
                }
            } else {
                $categories = [];
            }
            $monthly_sales = $db->table('orders')
                ->select('MONTHNAME(date_of_service) as month, SUM(final_total) as total_amount')
                ->where('date_of_service BETWEEN CURDATE() - INTERVAL ' . $last_monthly_sales . ' MONTH AND CURDATE()')
                ->where(['partner_id' => $partner_id, 'date_of_service < ' => date("Y-m-d H:i:s"), "status" => "completed"])
                ->groupBy("MONTH(date_of_service)")
                ->get()->getResultArray();
            $month_wise_sales['monthly_sales'] = $monthly_sales;
            $this->data['monthly_earnings'] = $month_wise_sales;
            $total_orders = $db->table('orders o')->select('count(o.id) as `total`')->join('order_services os', 'os.order_id=o.id')
                ->join('users u', 'u.id=o.user_id')
                ->join('users up', 'up.id=o.partner_id')
                ->join('partner_details pd', 'o.partner_id = pd.partner_id')->where(['o.partner_id' => $partner_id])->get()->getResultArray()[0]['total'];
            $total_services = $db->table('services s')->select('count(s.id) as `total`')->where(['user_id' => $partner_id])->get()->getResultArray()[0]['total'];
            $amount = fetch_details('orders', ['partner_id' => $partner_id, 'is_commission_settled' => '0'], ['sum(final_total) as total']);
            $db = \config\Database::connect();
            $builder = $db
                ->table('orders')
                ->select('sum(final_total) as total')
                ->select('SUM(final_total) AS total_sale,DATE_FORMAT(created_at,"%b") AS month_name')
                ->where('partner_id', $partner_id)
                ->where('status', 'completed');
            $data = $builder->groupBy('created_at')->get()->getResultArray();
            $tempRow = array();
            $row1 = array();
            foreach ($data as $key => $row) {
                $tempRow = $row['total'];
                $row1[] = $tempRow;
            }
            $total_balance = unsettled_commision($partner_id);
            $total_ratings = $db->table('partner_details p')->select('count(p.ratings) as `total`')->where(['id' => $partner_id])->get()->getResultArray()[0]['total'];
            $number_or_ratings = $db->table('partner_details p')->select('count(p.number_of_ratings) as `total`')->where(['id' => $partner_id])->get()->getResultArray()[0]['total'];
            $income = $db->table('orders o')->select('count(o.id) as `total`')->where(['partner_id' => $partner_id])->where("created_at >= DATE(now()) - INTERVAL 7 DAY")->get()->getResultArray()[0]['total'];
            $total_cancel = $db->table('orders o')->select('count(o.id) as `total`')->where(['partner_id' => $partner_id])->where(["status" => "cancelled"])->get()->getResultArray()[0]['total'];
            $symbol = get_currency();
            $this->data['total_services'] = ($total_services != 0) ? $total_services : "0";
            $this->data['total_orders'] = ($total_orders != 0) ? $total_orders : "0";
            $this->data['total_cancelled_orders'] = ($total_cancel != 0) ? $total_cancel : "0";
            $this->data['total_balance'] = ($total_balance != 0) ? strval($total_balance) : "0";
            $this->data['total_ratings'] = ($total_ratings != 0) ? $total_ratings : "0";
            $this->data['number_of_ratings'] = ($number_or_ratings != 0) ? $number_or_ratings : "0";
            $this->data['currency'] = $symbol;
            $this->data['income'] = ($income != 0) ? $income : "0";
            $db = \Config\Database::connect();
            // Fixed: Changed $this->userId to $partner_id to prevent "Cannot access offset of type string on string" error
            $custom_job_categories = fetch_details('partner_details', ['partner_id' => $partner_id], ['custom_job_categories', 'is_accepting_custom_jobs']);
            $partner_categoried_preference = !empty($custom_job_categories) &&
                isset($custom_job_categories[0]['custom_job_categories']) &&
                !empty($custom_job_categories[0]['custom_job_categories']) ?
                json_decode($custom_job_categories[0]['custom_job_categories']) : [];
            $builder = $db->table('custom_job_requests cj')
                ->select('cj.*, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                ->join('users u', 'u.id = cj.user_id')
                ->join('categories c', 'c.id = cj.category_id')
                ->where('cj.status', 'pending')
                ->where("(SELECT COUNT(1) FROM partner_bids pb WHERE pb.custom_job_request_id = cj.id AND pb.partner_id = $partner_id) = 0");
            if (!empty($partner_categoried_preference)) {
                $builder->whereIn('cj.category_id', $partner_categoried_preference);
            }
            $builder->orderBy('cj.id', 'DESC');
            $custom_job_requests = $builder->get()->getResultArray();
            $filteredJobs = [];
            foreach ($custom_job_requests as $row) {
                $did_partner_bid = fetch_details('partner_bids', [
                    'custom_job_request_id' => $row['id'],
                    'partner_id' => $partner_id,
                ]);
                if (empty($did_partner_bid)) {
                    $check = fetch_details('custom_job_provider', [
                        'partner_id' => $partner_id,
                        'custom_job_request_id' => $row['id'],
                    ]);
                    if (!empty($check)) {
                        $filteredJobs[] = $row;
                    }
                }
            }
            if (!empty($filteredJobs)) {
                $fileService = service('fileService');
                foreach ($filteredJobs as &$job) {
                    $job['image'] = $fileService->url($job['image'] ?? '', 'profiles', 'public/uploads/profiles/default.png');
                }
            }
            $this->data['total_open_jobs'] = count($filteredJobs);
            $filteredJobs = array_slice($filteredJobs, 0, 2);

            $this->data['open_jobs'] = $filteredJobs;
            if (!empty($this->data)) {
                $response = [
                    'error' => false,
                    'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'data fetched successfully.'),
                    'data' => $this->data
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_statistics()');
            return $this->response->setJSON($response);
        }
    }

    public function logout()
    {
        try {

            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'fcm_id' => 'permit_empty'
                ],
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
            if ($this->request->getPost('fcm_id') != "" || !empty($this->request->getPost('fcm_id'))) {
                $fcm_id = $this->request->getPost('fcm_id');
                $user_fcm_ids = delete_details(['fcm_id' => $fcm_id], 'users_fcm_ids');
            }

            // ============================================================
            // Invalidate bearer token used for this request (if present).
            // ------------------------------------------------------------
            // 1. Read the Authorization header.
            // 2. Extract the Bearer token from it.
            // 3. Delete that token record from users_tokens table.
            // This ensures that the same token cannot be reused after logout.
            // ============================================================
            $authHeader = $this->request->getHeaderLine('Authorization');
            if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                $currentToken = $matches[1];
                if (!empty($currentToken)) {
                    delete_details(['token' => $currentToken], 'users_tokens');
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(LOGOUT_SUCCESSFULLY, 'Logout successfully'),

            ]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($this->request->header('Authorization') . ' Params: ' . json_encode($_POST) . " Issue: " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - logout()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }


    // Private Helper Methods
    private function processImageDeletion($imageUrls)
    {
        $deletionResults = [];

        if (empty($imageUrls) || !is_array($imageUrls)) {
            return $deletionResults;
        }

        $fileService = service('fileService');

        foreach ($imageUrls as $imageUrl) {
            if (empty($imageUrl)) {
                continue;
            }

            // Extract folder and filename from URL
            $parsedInfo = $this->parseImageUrl($imageUrl);

            if ($parsedInfo['folder'] && $parsedInfo['filename']) {
                $deleted = $fileService->delete($parsedInfo['folder'], $parsedInfo['filename']);

                $deletionResults[] = [
                    'url' => $imageUrl,
                    'folder' => $parsedInfo['folder'],
                    'filename' => $parsedInfo['filename'],
                    'result' => ['error' => !$deleted, 'message' => $deleted ? '' : 'File deletion failed'],
                ];
            }
        }

        return $deletionResults;
    }

    private function parseImageUrl($imageUrl)
    {
        $folder = '';
        $filename = '';

        // ONLY allow deletion of "other images" (partner folder)
        // This ensures the images_to_delete parameter only works for other_images
        if (strpos($imageUrl, 'public/uploads/partner/') !== false) {
            $folder = 'partner';
            $filename = basename($imageUrl);
        } else {
            // Try to extract from URL pattern for partner folder only
            $urlParts = parse_url($imageUrl);
            if (isset($urlParts['path'])) {
                $pathParts = explode('/', trim($urlParts['path'], '/'));
                $filename = end($pathParts);

                // Only allow partner folder
                if (in_array('partner', $pathParts)) {
                    $folder = 'partner';
                }
            }
        }

        return [
            'folder' => $folder,
            'filename' => $filename
        ];
    }

    /**
     * Process username field for multi-language support
     * 
     * @param array &$user User data array (passed by reference)
     * @return void
     */
    private function processUsernameField(array &$user): void
    {
        try {
            // Get default language username
            $defaultUsername = $this->getDefaultLanguageUsername();

            // Set the default language username in the main user table
            if (!empty($defaultUsername)) {
                $user['username'] = $defaultUsername;
            }
        } catch (\Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Username processing failed: ' . $e->getMessage());

            // Fallback to direct username if available
            if (!empty($this->request->getPost('username'))) {
                $user['username'] = $this->request->getPost('username');
            }
        }
    }

    private function saveProviderSeoSettings(int $partnerId): void
    {
        try {
            // Get and decode translated fields from POST request
            $translatedFields = $this->request->getPost('translated_fields');
            $translatedFields = $this->decodeTranslatedFields($translatedFields);

            // Determine default language (usually 'en' or first available language)
            $defaultLanguage = get_default_language(); // You can make this configurable from settings

            // Extract default language SEO data from translated_fields
            $defaultSeoData = [];
            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Check if default language data exists in translated_fields
                if (isset($translatedFields['seo_title'][$defaultLanguage])) {
                    $defaultSeoData['title'] = trim((string) $translatedFields['seo_title'][$defaultLanguage]);
                }
                if (isset($translatedFields['seo_description'][$defaultLanguage])) {
                    $defaultSeoData['description'] = trim((string) $translatedFields['seo_description'][$defaultLanguage]);
                }
                if (isset($translatedFields['seo_keywords'][$defaultLanguage])) {
                    $defaultSeoData['keywords'] = $this->parseKeywords($translatedFields['seo_keywords'][$defaultLanguage]);
                }
                if (isset($translatedFields['seo_schema_markup'][$defaultLanguage])) {
                    $defaultSeoData['schema_markup'] = trim((string) $translatedFields['seo_schema_markup'][$defaultLanguage]);
                }
            }

            // Fallback to direct POST parameters if no translated_fields or default language not found
            if (empty($defaultSeoData)) {
                $defaultSeoData = [
                    'title' => trim((string) $this->request->getPost('seo_title')),
                    'description' => trim((string) $this->request->getPost('seo_description')),
                    'keywords' => $this->parseKeywords($this->request->getPost('seo_keywords')),
                    'schema_markup' => trim((string) $this->request->getPost('seo_schema_markup')),
                ];
            }

            // Add partner_id to SEO data
            $defaultSeoData['partner_id'] = $partnerId;

            // Check if any SEO field is filled (excluding partner_id)
            $hasSeoData = array_filter($defaultSeoData, fn($v) => !empty($v) && $v !== $partnerId);

            // Check if all SEO fields are intentionally cleared
            $allFieldsCleared = empty($defaultSeoData['title']) &&
                empty($defaultSeoData['description']) &&
                empty($defaultSeoData['keywords']) &&
                empty($defaultSeoData['schema_markup']);

            // Handle SEO image upload
            $seoImage = $this->request->getFile('seo_og_image');
            $hasImage = $seoImage && $seoImage->isValid();

            // Use Seo_model for provider context
            $this->seoModel->setTableContext('providers');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

            $newSeoData = $defaultSeoData;
            if ($hasImage) {
                try {
                    $uploadResult = service('fileService')->upload($seoImage, 'provider_seo_settings');
                    if ($uploadResult['error']) {
                        throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed: ' . $uploadResult['message']));
                    }
                    $newSeoData['image'] = $uploadResult['path'];
                } catch (\Throwable $t) {
                    throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed') . ': ' . $t->getMessage());
                }
            } else {
                $newSeoData['image'] = $existingSettings['image'] ?? '';
            }

            // If no existing settings, create new if data or image exists
            if (!$existingSettings) {
                if ($hasSeoData || $hasImage) {
                    $result = $this->seoModel->createSeoSettings($newSeoData);
                    if (!empty($result['error'])) {
                        $errors = $result['validation_errors'] ?? [];
                        throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                    }
                }

                // Process SEO translations after creating base SEO settings
                // Include ALL languages (including default) in translations table
                $this->processPartnerSeoTranslations($partnerId, $translatedFields);
                return;
            }

            // If existing settings exist and all fields are cleared (and no new image), delete the record
            if ($existingSettings && $allFieldsCleared && !$hasImage) {
                $result = $this->seoModel->delete($existingSettings['id']);
                if ($result) {
                    // Clean up old image if it exists
                    if (!empty($existingSettings['image'])) {
                        service('fileService')->delete('provider_seo_settings', $existingSettings['image']);
                    }
                }
                // Also clean up SEO translations when deleting base SEO settings
                $this->cleanupPartnerSeoTranslations($partnerId);
                return;
            }

            // Compare existing and new settings
            $settingsChanged = false;
            foreach ($newSeoData as $key => $value) {
                $existingValue = $existingSettings[$key] ?? '';
                $newValue = $value ?? '';
                if ($existingValue !== $newValue) {
                    $settingsChanged = true;
                    break;
                }
            }

            // Also check if a new image was uploaded (this forces an update)
            if (!$settingsChanged && $hasImage) {
                $settingsChanged = true;
            }

            if (!$settingsChanged) {
                // Even if base SEO settings haven't changed, process translations
                $this->processPartnerSeoTranslations($partnerId, $translatedFields);
                return;
            }

            // Update existing settings with new data
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
            if (!empty($result['error'])) {
                $errors = $result['validation_errors'] ?? [];
                throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
            }

            // Process SEO translations after updating base SEO settings
            $this->processPartnerSeoTranslations($partnerId, $translatedFields);
        } catch (\Exception $e) {
            // Log the error with full details but don't fail the entire registration
            log_message('error', 'SEO settings save failed during registration for partner ' . $partnerId . ': ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            // Don't re-throw to allow registration to continue, but log the error
        }
    }

    /**
     * Process partner translations if provided in the request
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function processPartnerTranslations(int $partnerId): void
    {
        try {
            // Get translated fields from POST request
            // First, check if data is under 'translated_fields' key
            $translatedFields = $this->request->getPost('translated_fields');

            // If translated fields are provided as JSON string, decode it
            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);
            }

            // If no translated_fields found, check for data directly in POST
            // This handles cases where data is sent as: company_name[en], about_provider[en], etc.
            if (empty($translatedFields) || !is_array($translatedFields)) {
                $translatedFields = $this->extractTranslatedFieldsFromPost();
            }

            // Process translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Validate the translated fields structure
                $validationResult = $this->validateTranslatedFields($translatedFields);

                if (!$validationResult['valid']) {
                    // Log validation errors but don't fail the registration
                    log_message('error', 'Translation validation failed: ' . json_encode($validationResult['errors']));
                    return;
                }

                // Process and store the translations
                $translationResult = $this->processTranslations($partnerId, $translatedFields);

                // Check if translation processing was successful
                if (!$translationResult['success']) {
                    // Log the errors but don't fail the entire registration
                    log_message('error', 'Translation processing failed: ' . json_encode($translationResult['errors']));
                }

                // Log successful translations for debugging
                // if (!empty($translationResult['processed_languages'])) {
                //     log_message('info', 'Successfully processed translations for partner ' . $partnerId . ': ' . json_encode($translationResult['processed_languages']));
                // }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the registration
            log_message('error', 'Exception in processPartnerTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Decode translated fields from various input formats
     * 
     * Handles JSON strings, arrays, and null values with multiple fallback strategies
     * for JSON decoding to handle escaping issues.
     * 
     * @param mixed $translatedFields Input data (string, array, or null)
     * @return array|null Decoded array or null if decoding fails
     */
    private function decodeTranslatedFields($translatedFields): ?array
    {
        // Already an array - return as-is
        if (is_array($translatedFields)) {
            return $translatedFields;
        }

        // Null or empty - return null
        if ($translatedFields === null || $translatedFields === '') {
            return null;
        }

        // Not a string - unexpected type
        if (!is_string($translatedFields)) {
            log_message('warning', 'decodeTranslatedFields: Unexpected type: ' . gettype($translatedFields));
            return null;
        }

        // Try multiple JSON decode strategies
        $strategies = [
            // Strategy 1: Direct decode
            function ($str) {
                return json_decode($str, true);
            },

            // Strategy 2: Strip slashes (handles magic quotes or double escaping)
            function ($str) {
                return json_decode(stripslashes($str), true);
            },

            // Strategy 3: Remove outer quotes
            function ($str) {
                return json_decode(trim($str, '"\''), true);
            },

            // Strategy 4: HTML entity decode
            function ($str) {
                return json_decode(html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            },

            // Strategy 5: Double strip slashes (for triple escaping)
            function ($str) {
                return json_decode(stripslashes(stripslashes($str)), true);
            }
        ];

        // Try each strategy until one succeeds
        foreach ($strategies as $strategy) {
            $decoded = $strategy($translatedFields);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // All strategies failed
        log_message('error', 'decodeTranslatedFields: JSON decode failed - ' . json_last_error_msg());
        log_message('error', 'decodeTranslatedFields: First 1000 chars: ' . substr($translatedFields, 0, 1000));
        return null;
    }

    /**
     * Get default language username from multi-language data
     * 
     * @return string Default language username
     */
    private function getDefaultLanguageUsername(): string
    {
        try {
            $translatedFields = $this->request->getPost('translated_fields');

            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);
            }

            if (
                is_array($translatedFields) &&
                isset($translatedFields['username']) &&
                is_array($translatedFields['username'])
            ) {
                $defaultLanguage = get_default_language();

                // 1️⃣ Default language first
                if (!empty($translatedFields['username'][$defaultLanguage])) {
                    return trim($translatedFields['username'][$defaultLanguage]);
                }

                // 2️⃣ Any non-empty translation
                foreach ($translatedFields['username'] as $username) {
                    if (!empty($username)) {
                        return trim($username);
                    }
                }
            }

            // 3️⃣ HARD fallback — Ion Auth safety net
            return $this->request->getPost('mobile')
                ?: $this->request->getPost('email')
                ?: 'user_' . time();
        } catch (\Throwable $e) {
            log_message('error', '[USERNAME_RESOLVE_FAILED] ' . $e->getMessage());

            return $this->request->getPost('mobile')
                ?: $this->request->getPost('email')
                ?: 'user_' . time();
        }
    }

    /**
     * Extract translated fields from POST data when sent directly (not under translated_fields key)
     * Handles data structure like: {"company_name":{"en":"value"}, "about_provider":{"en":"value"}}
     * 
     * @return array Extracted translated fields in the expected format
     */
    private function extractTranslatedFieldsFromPost(): array
    {
        $translatedFields = [];

        // Define the translatable fields
        $translatableFields = ['username', 'company_name', 'about_provider', 'long_description'];

        // Get all POST data
        $postData = $this->request->getPost();

        // Process each translatable field
        foreach ($translatableFields as $fieldName) {
            $fieldValue = $postData[$fieldName] ?? null;

            // If field value is an array (like {"en":"value"}), use it directly
            if (is_array($fieldValue)) {
                $translatedFields[$fieldName] = $fieldValue;
            }
            // If field value is a JSON string, decode it
            elseif (is_string($fieldValue)) {
                $decoded = json_decode($fieldValue, true);
                if (is_array($decoded)) {
                    $translatedFields[$fieldName] = $decoded;
                }
            }
        }

        return $translatedFields;
    }

    /**
     * Validate translated fields structure
     * 
     * @param array $translatedFields The translated fields data
     * @return array Validation result with success status and errors
     */
    private function validateTranslatedFields(array $translatedFields): array
    {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Check if translated fields is an array
        if (!is_array($translatedFields)) {
            $result['valid'] = false;
            $result['errors'][] = 'Translated fields must be an array';
            return $result;
        }

        // Define allowed fields
        $allowedFields = ['username', 'company_name', 'about_provider', 'long_description'];

        // Check each field
        foreach ($translatedFields as $fieldName => $languageData) {
            // Skip fields that are not in the allowed list (like SEO fields)
            // These fields are handled separately, so we just ignore them here
            if (!in_array($fieldName, $allowedFields)) {
                continue;
            }

            // Check if language data is an array
            if (!is_array($languageData)) {
                $result['errors'][] = "Language data for field '{$fieldName}' must be an array";
                continue;
            }

            // Check each language
            foreach ($languageData as $languageCode => $translatedText) {
                // Validate language code
                if (empty($languageCode) || !is_string($languageCode) || strlen($languageCode) > 10) {
                    $result['errors'][] = "Invalid language code '{$languageCode}' for field '{$fieldName}'";
                }

                // Validate translated text
                if (!is_string($translatedText)) {
                    $result['errors'][] = "Translation text for field '{$fieldName}' in language '{$languageCode}' must be a string";
                }
            }
        }

        // If there are any errors, mark as invalid
        if (!empty($result['errors'])) {
            $result['valid'] = false;
        }

        return $result;
    }

    /**
     * Process and store translated fields for a partner
     * 
     * @param int $partnerId The partner ID
     * @param array $translatedFields The translated fields data from POST request
     * @return array Result with success status and any errors
     */
    private function processTranslations(int $partnerId, array $translatedFields): array
    {
        // Initialize result array
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        // Validate partner ID
        if (empty($partnerId)) {
            $result['success'] = false;
            $result['errors'][] = labels('partner_id_is_required', 'Partner ID is required');
            return $result;
        }

        // Validate translated fields structure
        if (empty($translatedFields) || !is_array($translatedFields)) {
            $result['success'] = false;
            $result['errors'][] = labels('translated_fields_data_is_required_and_must_be_an_array', 'Translated fields data is required and must be an array');
            return $result;
        }

        // Define the fields that can be translated
        $translatableFields = [
            'username',
            'company_name',
            'about_provider', // Maps to 'about' in the database
            'long_description'
        ];

        // Process each translatable field
        foreach ($translatableFields as $fieldName) {
            // Skip if this field doesn't exist in the translated fields
            if (!isset($translatedFields[$fieldName]) || !is_array($translatedFields[$fieldName])) {
                continue;
            }

            // Process each language for this field
            foreach ($translatedFields[$fieldName] as $languageCode => $translatedText) {
                // Validate language code
                if (empty($languageCode) || !is_string($languageCode)) {
                    $result['errors'][] = labels('invalid_language_code_for_field', 'Invalid language code for field') . " '{$fieldName}'";
                    continue;
                }

                // Validate translated text
                if (!is_string($translatedText)) {
                    $result['errors'][] = labels('invalid_translation_text_for_field', 'Invalid translation text for field') . " '{$fieldName}'" . labels('in_language', 'in language') . " '{$languageCode}'";
                    continue;
                }

                // Prepare the data for storage
                $translationData = [];

                // Map field names to database column names
                switch ($fieldName) {
                    case 'username':
                        $translationData['username'] = $translatedText;
                        break;
                    case 'company_name':
                        $translationData['company_name'] = $translatedText;
                        break;
                    case 'about_provider':
                        $translationData['about'] = $translatedText;
                        break;
                    case 'long_description':
                        $translationData['long_description'] = $translatedText;
                        break;
                    default:
                        $result['errors'][] = labels('unknown_field_for_translation', 'Unknown field') . " '{$fieldName}'" . labels('for_translation', 'for translation');
                        continue 2; // Skip to next field
                }

                // Try to save the translation
                try {
                    $saveResult = $this->translationModel->saveTranslatedDetails(
                        $partnerId,
                        $languageCode,
                        $translationData
                    );

                    if ($saveResult) {
                        // Track successfully processed translations
                        $result['processed_languages'][] = [
                            'field' => $fieldName,
                            'language' => $languageCode,
                            'status' => 'saved'
                        ];
                    } else {
                        $result['errors'][] = labels('failed_to_save_translation', 'Failed to save translation') . " '{$fieldName}'" . labels('in_language', 'in language') . " '{$languageCode}'";
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = labels('something_went_wrong_while_saving_translation', 'Something went wrong while saving translation') . " '{$fieldName}'" . labels('in_language', 'in language') . " '{$languageCode}': " . $e->getMessage();
                }
            }
        }

        // If there are any errors, mark as not fully successful
        if (!empty($result['errors'])) {
            $result['success'] = false;
        }

        return $result;
    }

    private function parseKeywords($input): string
    {
        // If input is empty, return empty string
        if (empty($input)) {
            return '';
        }

        // If input is a string, it might be JSON or comma-separated
        if (is_string($input)) {
            // Check if it's a JSON string
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Treat as comma-separated string
            return trim($input);
        }

        // If input is an array
        if (is_array($input)) {
            // Handle case where array contains a single JSON string (e.g., ['[{value: "tag1"}, {value: "tag2"}]'])
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
            $tags = array_map(function ($item) {
                return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
            }, $input);
            return implode(',', $tags);
        }

        // Fallback: return empty string for unexpected input
        return '';
    }


    /**
     * Process partner SEO translations from translated_fields data
     * 
     * Extracts SEO fields from translated_fields and stores them
     * in the translated_partner_seo_settings table.
     * 
     * @param int $partnerId Partner ID
     * @param array|null $translatedFields Translated fields data from POST
     * @return void
     */
    private function processPartnerSeoTranslations(int $partnerId, ?array $translatedFields = null): void
    {
        try {
            // Get translated fields from POST if not provided
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');
            }

            // Decode translated fields (handles JSON strings, arrays, etc.)
            $translatedFields = $this->decodeTranslatedFields($translatedFields);

            // Early return if no valid data
            if ($translatedFields === null || empty($translatedFields)) {
                return;
            }

            // Early return if no SEO fields present
            if (!$this->hasSeoFields($translatedFields)) {
                return;
            }

            // Load the partner SEO translation model
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

            // Restructure data for the model (convert field[lang] to lang[field] format)
            $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

            // Early return if restructured data is empty
            if (empty($restructuredData)) {
                return;
            }

            // Process and store the SEO translations
            $seoTranslationResult = $seoTranslationModel->processSeoTranslations($partnerId, $restructuredData);

            // Check if processing was successful
            if (!$seoTranslationResult['success']) {
                $errorMsg = 'SEO Translation processing failed for partner ' . $partnerId . ': ' . json_encode($seoTranslationResult['errors']);
                log_message('error', $errorMsg);
                throw new \Exception($errorMsg);
            }

            // Log success if languages were processed
            // if (!empty($seoTranslationResult['processed_languages'])) {
            //     log_message('info', 'Successfully processed partner SEO translations for partner ' . $partnerId . ' in languages: ' . json_encode($seoTranslationResult['processed_languages']));
            // }
        } catch (\Exception $e) {
            // Log error with full details
            log_message('error', 'Exception in processPartnerSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            // Re-throw for critical errors so caller can handle it
            throw new \Exception('Exception in processPartnerSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Clean up partner SEO translations when base SEO settings are deleted
     * 
     * Removes all translations from translated_partner_seo_settings
     * for the given partner ID.
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function cleanupPartnerSeoTranslations(int $partnerId): void
    {
        try {
            // Load the partner SEO translation model
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

            // Delete all SEO translations for this partner
            $seoTranslationModel->deletePartnerSeoTranslations($partnerId);

            log_message('info', 'Cleaned up SEO translations for partner ' . $partnerId);
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            log_message('error', 'Exception in cleanupPartnerSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Check if translated fields contain SEO fields
     * 
     * @param array $translatedFields Decoded translated fields
     * @return bool True if SEO fields exist and have data
     */
    private function hasSeoFields(array $translatedFields): bool
    {
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        foreach ($seoFields as $field) {
            if (
                isset($translatedFields[$field])
                && is_array($translatedFields[$field])
                && !empty($translatedFields[$field])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Restructure translated fields for SEO model
     * Convert from field[lang] format to lang[field] format
     * 
     * Input format:  field[lang] - e.g., seo_title['en'], seo_title['ar']
     * Output format: lang[field] - e.g., en[seo_title], ar[seo_title]
     * 
     * @param array $translatedFields Translated fields in field[lang] format
     * @return array Restructured data in lang[field] format
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];

        // SEO fields we want to process
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Get all available languages from the translated fields
        $languages = [];
        foreach ($seoFields as $field) {
            if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                $fieldLanguages = array_keys($translatedFields[$field]);
                $languages = array_merge($languages, $fieldLanguages);
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                if (isset($translatedFields[$field][$languageCode]) && !empty($translatedFields[$field][$languageCode])) {
                    $value = $translatedFields[$field][$languageCode];

                    // Special handling for keywords - parse them using parseKeywords function
                    if ($field === 'seo_keywords') {
                        $parsedKeywords = $this->parseKeywords($value);
                        $restructured[$languageCode][$field] = $parsedKeywords;
                    } else {
                        $restructured[$languageCode][$field] = $value;
                    }
                }
            }

            // Only keep languages that have at least one SEO field
            if (empty($restructured[$languageCode])) {
                unset($restructured[$languageCode]);
            }
        }

        return $restructured;
    }

    /**
     * Fetch all visible file-type custom fields (documents + bank_details groups).
     */
    /**
     * Validate the custom_fields array from the provider register/update request.
     *
     * Checks that:
     * - Each entry has a valid custom_field_id belonging to documents or bank_details groups
     * - All fields marked required=1 are present with a non-empty value (or file upload for file-type)
     * - No duplicate custom_field_id entries
     *
     * @param  array    $customFields  Raw array of ['custom_field_id' => int, 'value' => string]
     * @param  int|null $partnerId     When provided (update flow), required fields that already
     *                                 have a saved value in partner_custom_fields are not enforced.
     * @return string|null             Error message on failure, null on success
     */
    private function validateProviderCustomFields(array $customFields, ?int $partnerId = null): ?string
    {
        if (empty($customFields)) {
            return null;
        }

        $cfModel = new \App\Models\CustomFieldModel();
        if (!$cfModel->customFieldsTableExists()) {
            return null;
        }

        $db = \Config\Database::connect();

        $allDefs = $db->table('custom_fields')
            ->select(['id', 'field_type', 'required'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->get()
            ->getResultArray();

        $validIds = [];
        $requiredIds = [];
        foreach ($allDefs as $def) {
            $id = (int) $def['id'];
            $validIds[$id] = $def;
            if ((int) $def['required'] === 1) {
                $requiredIds[$id] = $def['field_type'];
            }
        }

        // For updates, load existing custom field values so we can skip required
        // checks for fields that already have a saved value in the database.
        $existingValues = [];
        if ($partnerId !== null && !empty($requiredIds) && $cfModel->partnerCustomFieldsTableExists()) {
            $rows = $db->table('partner_custom_fields')
                ->select(['custom_field_id', 'value'])
                ->where('partner_id', $partnerId)
                ->whereIn('custom_field_id', array_keys($requiredIds))
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                if ($row['value'] !== null && $row['value'] !== '') {
                    $existingValues[(int) $row['custom_field_id']] = true;
                }
            }
        }

        $seenIds = [];
        $providedIds = [];
        foreach ($customFields as $index => $entry) {
            if (!isset($entry['custom_field_id'])) {
                return labels('custom_field_id_required', 'Each custom field entry must include custom_field_id');
            }
            $cfId = (int) $entry['custom_field_id'];

            if (!isset($validIds[$cfId])) {
                return labels('invalid_custom_field_id', 'Invalid custom_field_id') . ': ' . $cfId;
            }

            if (isset($seenIds[$cfId])) {
                return labels('duplicate_custom_field_id', 'Duplicate custom_field_id') . ': ' . $cfId;
            }
            $seenIds[$cfId] = true;

            $def = $validIds[$cfId];
            $value = array_key_exists('value', $entry) ? $entry['value'] : null;

            // For file-type fields, check if a file upload exists in the nested array
            // Files are sent as custom_fields[N][value]=@file via multipart form
            if (strtolower($def['field_type']) === 'file') {
                $file = $this->request->getFile('custom_fields.' . $index . '.value');
                if (($file && $file->isValid()) || ($value !== null && $value !== '')) {
                    $providedIds[$cfId] = true;
                }
            } elseif ($value !== null && $value !== '') {
                $providedIds[$cfId] = true;
            }
        }

        // Check required fields have non-empty values.
        // Skip if the partner already has a saved value for this field (update flow).
        foreach ($requiredIds as $reqId => $fieldKey) {
            if (empty($providedIds[$reqId]) && empty($existingValues[$reqId])) {
                return labels('custom_field_required', 'Required custom field is missing or empty') . ': ' . $fieldKey;
            }
        }

        return null;
    }

    /**
     * Upsert custom field values into partner_custom_fields.
     *
     * Handles both text-type fields (value from JSON array) and file-type fields
     * (file uploaded inline as custom_fields[N][value]=@file via multipart form).
     *
     * @param int   $partnerId
     * @param array<int, string|null> $customFieldValues  Keyed by custom_field_id
     * @param array<int, int>         $indexMap           Maps custom_field_id => array index (for file access)
     */
    private function upsertProviderCustomFields(int $partnerId, array $customFieldValues, array $indexMap = []): void
    {
        if (empty($customFieldValues)) {
            return;
        }

        $cfModel = new \App\Models\CustomFieldModel();
        if (!$cfModel->customFieldsTableExists() || !$cfModel->partnerCustomFieldsTableExists()) {
            return;
        }

        $db = \Config\Database::connect();
        $cfIds = array_keys($customFieldValues);

        // Load field definitions for submitted IDs (including file_config for validation)
        $fieldDefs = $db->table('custom_fields')
            ->select(['id', 'field_type', 'file_config'])
            ->whereIn('id', $cfIds)
            ->get()
            ->getResultArray();

        $fieldDefsById = [];
        foreach ($fieldDefs as $def) {
            // Parse file_config JSON into array for easier access
            $raw = (string) ($def['file_config'] ?? '');
            $def['file_config_parsed'] = ($raw !== '') ? (json_decode($raw, true) ?? []) : [];
            $fieldDefsById[(int) $def['id']] = $def;
        }

        // Load existing values for file-type fields (to delete old files on replacement)
        $fileFieldIds = array_keys(array_filter($fieldDefsById, fn($d) => strtolower($d['field_type']) === 'file'));
        $oldFileValues = [];
        if (!empty($fileFieldIds)) {
            $rows = $db->table('partner_custom_fields')
                ->select(['custom_field_id', 'value'])
                ->where('partner_id', $partnerId)
                ->whereIn('custom_field_id', $fileFieldIds)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $oldFileValues[(int) $row['custom_field_id']] = $row['value'];
            }
        }

        $fileService = service('fileService');
        $now = date('Y-m-d H:i:s');

        foreach ($customFieldValues as $cfId => $value) {
            $def = $fieldDefsById[$cfId] ?? null;
            if ($def === null) {
                continue;
            }

            // For non-file fields, skip if value is null (field not sent by client)
            if ($value === null && strtolower($def['field_type']) !== 'file') {
                continue;
            }

            $finalValue = $value;

            // Handle file-type fields
            // Files are sent inline as custom_fields[N][value]=@file via multipart form
            if (strtolower($def['field_type']) === 'file') {
                $arrayIndex = $indexMap[$cfId] ?? null;
                $file = ($arrayIndex !== null)
                    ? $this->request->getFile('custom_fields.' . $arrayIndex . '.value')
                    : null;

                if ($file && $file->isValid()) {
                    // Validate file extension against allowed_types from file_config
                    $fileConfig = $def['file_config_parsed'] ?? [];
                    $allowedTypes = $fileConfig['allowed_types'] ?? [];
                    if (!empty($allowedTypes)) {
                        $ext = '.' . strtolower($file->getExtension());
                        if (!in_array($ext, $allowedTypes, true)) {
                            continue; // Skip — file type not permitted for this field
                        }
                    }

                    // Validate file size against max_size_mb from file_config
                    $maxSizeMb = (int) ($fileConfig['max_size_mb'] ?? 0);
                    if ($maxSizeMb > 0 && $file->getSize() > ($maxSizeMb * 1024 * 1024)) {
                        continue; // Skip — file exceeds max size
                    }

                    // Upload new file and remove the previous one on success
                    $oldFile = $oldFileValues[$cfId] ?? '';
                    $result = $fileService->replace(!empty($oldFile) ? $oldFile : null, $file, 'custom_fields');
                    if ($result['error']) {
                        continue; // Skip this field on upload error
                    }

                    $finalValue = $result['path'];
                } else {
                    // No new file uploaded — keep existing value, skip upsert
                    continue;
                }
            }

            $db->query(
                "INSERT INTO partner_custom_fields (partner_id, custom_field_id, value, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)",
                [(int) $partnerId, (int) $cfId, (string) $finalValue, $now, $now]
            );
        }
    }

    /**
     * Persist a partner's weekly working schedule into `provider_shifts`,
     * supporting multiple shifts per day. Existing rows for the partner are
     * deleted and rewritten so the call is idempotent.
     *
     * Per-day entry accepted in two forms:
     *   - Multi-shift: ['day' => 'monday', 'isOpen' => 1, 'shifts' => [
     *         ['start_time' => '09:00:00', 'end_time' => '13:00:00'],
     *         ['start_time' => '17:00:00', 'end_time' => '21:00:00'],
     *     ]]
     *   - Legacy single-shift: ['day' => 'monday', 'isOpen' => 1,
     *         'start_time' => '09:00:00', 'end_time' => '18:00:00']
     *
     * When $daysPayload is null/empty, a default open schedule
     * (09:00-18:00, isOpen=1) is written for all 7 days.
     */
    private function persistProviderShifts(int $partnerId, ?array $daysPayload): void
    {
        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $db = \Config\Database::connect();

        if (empty($daysPayload)) {
            $daysPayload = array_map(fn($d) => [
                'day' => $d,
                'isOpen' => 1,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ], $allowedDays);
        }

        $batch = [];
        foreach ($daysPayload as $row) {
            $day = strtolower((string) ($row['day'] ?? ''));
            if (!in_array($day, $allowedDays, true)) {
                continue;
            }
            $isOpen = !empty($row['isOpen']) ? 1 : 0;

            $shifts = [];
            if (!empty($row['shifts']) && is_array($row['shifts'])) {
                foreach ($row['shifts'] as $s) {
                    $start = trim((string) ($s['start_time'] ?? ''));
                    $end = trim((string) ($s['end_time'] ?? ''));
                    if ($start === '' || $end === '') {
                        continue;
                    }
                    $shifts[] = ['start' => $start, 'end' => $end];
                }
            } else {
                $start = trim((string) ($row['start_time'] ?? ''));
                $end = trim((string) ($row['end_time'] ?? ''));
                if ($start !== '' && $end !== '') {
                    $shifts[] = ['start' => $start, 'end' => $end];
                }
            }

            if (empty($shifts)) {
                $shifts[] = ['start' => '09:00:00', 'end' => '18:00:00'];
            }

            $shiftNumber = 0;
            foreach ($shifts as $s) {
                $shiftNumber++;
                $batch[] = [
                    'partner_id' => $partnerId,
                    'day' => $day,
                    'shift_number' => $shiftNumber,
                    'opening_time' => $s['start'],
                    'closing_time' => $s['end'],
                    'is_open' => $isOpen,
                ];
            }
        }

        $db->transException(true)->transStart();
        $db->table('provider_shifts')->delete(['partner_id' => $partnerId]);
        if (!empty($batch)) {
            $db->table('provider_shifts')->insertBatch($batch);
        }
        $db->transComplete();
    }
}
