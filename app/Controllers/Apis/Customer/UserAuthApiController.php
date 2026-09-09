<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;

class UserAuthApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $toDateTime;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/manage_user",
        "api/v1/verify_user",
        "api/v1/verify_otp",
        "api/v1/resend_otp",
        "api/v1/logout",
        "api/v1/change_password",
    ];
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        } else {
            $token = verify_app_request();
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function manage_user()
    {
        try {
            $validation = \Config\Services::validation();
            $request = \Config\Services::request();
            $db = \Config\Database::connect();
            $response = ['error' => true, 'message' => '', 'data' => []];

            // Normalize input
            $mobile = trim($request->getPost('mobile') ?? '');
            $email  = trim($request->getPost('email') ?? '');
            $uid    = trim($request->getPost('uid') ?? '');
            $loginType = trim($request->getPost('login_type'));

            // Convert '' to null
            $mobile = ($mobile !== '') ? $mobile : null;
            $email  = ($email !== '') ? $email  : null;
            $uid    = ($uid !== '') ? $uid     : null;

            // Count how many identity fields are present
            $provided = array_filter([$mobile, $email, $uid], fn($v) => !is_null($v));
            $identityCount = count($provided);

            $loginSettings = get_settings('login_settings', true);
            $postedPassword = trim($request->getPost('password') ?? '');


            // Require exactly ONE
            if ($identityCount === 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ENTER_MOBILE_OR_UID, 'Please provide one of: Mobile, Email or UID')
                ]);
            }

            // Dynamic validation rules
            $rules = [];

            if (!is_null($mobile)) {
                $rules['mobile'] = [
                    'rules' => 'required|numeric',
                    'errors' => [
                        'required' => labels(MOBILE_IS_REQUIRED, 'Mobile is required'),
                        'numeric' => labels(MOBILE_MUST_BE_NUMERIC, 'Mobile must be numeric'),
                    ]
                ];
                $rules['country_code'] = [
                    'rules' => 'required',
                    'errors' => [
                        'required' => labels('country_code_is_required', 'Country code is required'),
                    ]
                ];
            }

            if (!is_null($email)) {
                $rules['email'] = [
                    'rules' => 'required|valid_email',
                    'errors' => [
                        'required' => labels(EMAIL_IS_REQUIRED, 'Email is required'),
                        'valid_email' => labels('email_must_be_valid', 'Email must be valid'),
                    ]
                ];
            }

            if (!is_null($uid)) {
                $rules['uid'] = [
                    'rules' => 'required',
                    'errors' => [
                        'required' => labels(UID_IS_REQUIRED, 'UID is required'),
                    ]
                ];
            }

            // Optional extras
            if ($request->getPost('fcm_id')) {
                $rules['fcm_id'] = 'permit_empty';
            }

            if ($loginSettings['customer_password_login_enabled'] == 1 && $request->getPost('login_type') !== 'apple' && $request->getPost('login_type') !== 'google') {
                $rules['password'] = [
                    'rules' => 'required',
                    'errors' => [
                        'required' => labels(PASSWORD_IS_REQUIRED, 'Password is required'),
                    ]
                ];
            }

            $rules['login_type'] = [
                'rules' => 'required',
                'errors' => [
                    'required' => labels('login_type_is_required', 'Login type is required'),
                ]
            ];

            // Run validation
            if (!$validation->setRules($rules)->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => reset($errors)
                ]);
            }
            // Check if user exists - support mobile, email, or uid
            $userCheck = [];
            if ($mobile !== null) {
                $builder = $db->table('users u')
                    ->select('u.*,ug.group_id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where(['ug.group_id' => 2, 'phone' => $mobile, 'loginType' => $loginType]);

                $country_code = trim($request->getPost('country_code') ?? '');
                if ($country_code !== '') {
                    $builder->where(['country_code' => $country_code]);
                }

                $userCheck = $builder->get()->getResultArray();
            } elseif ($email !== null) {
                $builder = $db->table('users u')
                    ->select('u.*,ug.group_id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where(['ug.group_id' => 2, 'email' => $email, 'loginType' => $loginType]);
                $userCheck = $builder->get()->getResultArray();
            } elseif ($uid !== null) {
                $userCheck = fetch_details('users', ['uid' => $uid, 'loginType' => $loginType]);
            }

            $user_group = !empty($userCheck) ? fetch_details('users_groups', ['user_id' => $userCheck[0]['id'], 'group_id' => '2']) : [];

            // Collect common data fields
            $update_data = [];
            $fieldMap = [
                'latitude',
                'longitude',
                'country_code',
                'platform',
                'email',
                'preferred_language',
                'uid',
            ];

            if ($request->getPost('fcm_id') != '' || $request->getPost('web_fcm_id') != '') {

                if (!empty($userCheck)) {
                    $fcm_id = $request->getPost('fcm_id');
                    $web_fcm_id = $request->getPost('web_fcm_id');
                    $platform = $fcm_id != '' ? $request->getPost('platform') : 'web';
                    // Get language_code from request (optional)
                    $language_code = $request->getPost('language_code');
                    store_users_fcm_id($userCheck[0]['id'], $fcm_id, $platform, $web_fcm_id, $language_code);
                }
            }

            foreach ($fieldMap as $field) {
                if ($value = $request->getPost($field)) {
                    $update_data[$field] = $value;
                }
            }

            // Map login_type from POST to loginType in database
            if ($login_type = $request->getPost('login_type')) {
                $update_data['loginType'] = $login_type;
            }

            // LOGIN EXSITING USER
            if (!empty($userCheck) && !empty($user_group)) {
                // Login flow
                if (isset($_POST['mobile']) && $_POST['mobile'] != '') {
                    $field = 'phone';
                } elseif (isset($_POST['email']) && $_POST['email'] != '') {
                    $field = 'email';
                } elseif (isset($_POST['uid']) && $_POST['uid'] != '') {
                    $field = 'uid';
                } else {
                    $response['message'] = labels(ENTER_MOBILE_OR_UID, 'Enter Mobile, Email or UID');
                    return $this->response->setJSON($response);
                }

                $data = fetch_details('users', ['id' => $userCheck[0]['id'], 'loginType' => $loginType])[0];
                $fcm_data = fetch_details('users_fcm_ids', ['user_id' => $userCheck[0]['id']], 'fcm_id');
                if (!empty($fcm_data)) {
                    $data['web_fcm_id'] = $fcm_data[0]['fcm_id'];
                }
                if (empty($data)) {
                    $response['message'] = labels(USER_NOT_FOUND, 'User not found');
                    return $this->response->setJSON($response);
                }

                // Calculate unread chats count for admin <-> customer (current user) conversations during login.
                // This mirrors the logic used in get_user_info().
                // Conditions:
                // - Current user is the receiver (receiver_id = user id, receiver_type = 2)
                // - Sender is an admin (sender_type = 0)
                // - Message is not yet read (is_read = 0)
                $unreadAdminChatsCount = $db->table('chats')
                    ->where('receiver_id', (int) $data['id'])
                    ->where('receiver_type', 2)
                    ->where('sender_type', 0)
                    ->where('is_read', 0)
                    ->countAllResults();

                // Attach unread admin chats count so client apps get it directly from login response.
                $data['unread_chats_count'] = (int) $unreadAdminChatsCount;

                // Update user data
                foreach ($update_data as $key => $value) {
                    $data[$key] = $value;
                }

                update_details($update_data, ['id' => $data['id']], "users", false);



                // Generate token - use email or phone as identity
                $identity = ($loginType == 'phone')
                    ? $data['phone']
                    : $data['email'];

                if (!empty($data['password']) && $postedPassword !== '' && $loginSettings['customer_password_login_enabled'] == 1) {
                    if (!password_verify($postedPassword, $data['password'])) {
                        return $this->response->setJSON([
                            'error' => true,
                            'message' => labels('invalid_credentials', 'Invalid credentials')
                        ]);
                    }
                }

                // Pass country_code for phone login so same number in different countries gets correct user
                $uidForToken = ($this->request->getPost('uid') === $data['uid']) ? $this->request->getPost('uid') : '';
                $token = generate_tokens($identity, 2, $uidForToken, $data['loginType'], $data['country_code'] ?? null);
                insert_details(['user_id' => $data['id'], 'token' => $token], 'users_tokens');

                // Resolve profile image; empty stays empty
                $data['image'] = !empty($data['image']) ? service('fileService')->url($data['image'], 'profile') : "";

                $data['login_type'] = $data['loginType'];
                unset($data['loginType']);

                $data = remove_null_values($data);
                $response = [
                    'error' => false,
                    'token' => $token,
                    'message' => labels(USER_LOGGED_SUCCESSFULLY, 'User Logged successfully'),
                    'data' => $data,
                ];
            } else {
                // Registration flow
                $mobile = $request->getPost('mobile');
                $email = $request->getPost('email');
                $uid = $request->getPost('uid');

                if (empty($mobile) && empty($email) && empty($uid)) {
                    return response_helper(labels(MOBILE_OR_UID_IS_REQUIRED, 'Mobile number, Email or UID is required'));
                }

                $data = $update_data;
                if (!empty($mobile)) {
                    $data['phone'] = $mobile;
                }
                if (!empty($email)) {
                    $data['email'] = $email;
                }
                $data['active'] = 1;
                $data['username'] = $request->getPost('username');

                $data['loginType'] = $request->getPost('login_type');
                if(!empty($uid)) {
                    $data['uid'] = $uid;
                }

                // Handle image upload
                if (!empty($_FILES['image']) && isset($_FILES['image'])) {
                    $file = $request->getFile('image');
                    if ($file) {
                        $result = service('fileService')->upload($file, 'profile');
                        if ($result['error']) {
                            return ErrorResponse($result['message'] ?? labels(FAILED_TO_CREATE_PROFILES_FOLDERS, 'Failed to create profiles folders'), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $data['image'] = $result['path'];
                    }
                }

                if ($postedPassword !== '' && $loginSettings['customer_password_login_enabled'] == 1) {
                    $data['password'] = password_hash($postedPassword, PASSWORD_DEFAULT);
                }

                if ($insert_user = insert_details($data, 'users')) {
                    if (!exists(["user_id" => $insert_user['id'], "group_id" => 2], 'users_groups')) {
                        insert_details(['user_id' => $insert_user['id'], 'group_id' => 2], 'users_groups');
                    }

                    $data = fetch_details('users', ['id' => $insert_user['id'], 'loginType' => $loginType])[0];
                    // Store FCM IDs for new users
                    $fcm_id = $request->getPost('fcm_id');
                    $web_fcm_id = $request->getPost('web_fcm_id');
                    $platform = $fcm_id ? $request->getPost('platform') : 'web';
                    $language_code = $request->getPost('language_code');

                    if ($fcm_id || $web_fcm_id) {
                        store_users_fcm_id($insert_user['id'], $fcm_id, $platform, $web_fcm_id, $language_code);
                    }

                    // Generate token - pass country_code for phone so same number in different countries gets correct user
                    $identity = $data['loginType'] == 'phone' ? $data['phone'] : $data['email'];
                    $token = generate_tokens($identity, 2, isset($_POST['uid']) ? $_POST['uid'] : "", $data['loginType'], $data['country_code'] ?? null);
                    insert_details(['user_id' => $data['id'], 'token' => $token], 'users_tokens');

                    // Fetch user FCM IDs
                    $fcm_ids = fetch_details('users_fcm_ids', ['user_id' => $data['id']]);
                    $data['fcm_ids'] = $fcm_ids;

                    $data['login_type'] = $data['loginType'];
                    unset($data['loginType']);

                    $response = [
                        'error' => false,
                        "token" => $token,
                        'message' => labels(USER_REGISTERED_SUCCESSFULLY, 'User Registered successfully'),
                        'data' => remove_null_values($data),
                    ];

                    // Send notifications to admin users about new user registration
                    $user_id = $insert_user['id'];
                    // $db = \Config\Database::connect();
                    // $builder = $db->table('users u');
                    // $users = $builder->Select("u.id,u.fcm_id,u.username,u.email")
                    //     ->join('users_groups ug', 'ug.user_id=u.id')
                    //     ->where('ug.group_id', '1')
                    //     ->get()
                    //     ->getResultArray();

                    // Queue FCM notification to admin users about new user registration
                    try {
                        // log_message('info', '[NEW_USER_REGISTERED] Starting notification process for user_id: ' . $user_id);

                        // Get user information
                        $userName = $data['username'] ?? 'User';
                        $userEmail = $data['email'] ?? '';
                        $userPhone = $data['phone'] ?? '';
                        // log_message('info', '[NEW_USER_REGISTERED] User name: ' . $userName . ', User ID: ' . $user_id);

                        // Prepare context data for the notification template
                        $context = [
                            'user_name' => $userName,
                            'user_id' => $user_id,
                            'user_email' => $userEmail,
                            'user_phone' => $userPhone
                        ];
                        // log_message('info', '[NEW_USER_REGISTERED] Context prepared: ' . json_encode($context));

                        // Queue notification to admin users (group_id = 1) via FCM channel
                        // Email and SMS are handled above using the existing functions
                        queue_notification_service(
                            eventType: 'new_user_registered',
                            recipients: [],
                            context: $context,
                            options: [
                                'user_groups' => [1], // Admin user group
                                'channels' => ['fcm', 'email', 'sms'] // FCM channel only - email and SMS are handled above
                            ]
                        );
                        // log_message('info', '[NEW_USER_REGISTERED] Notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // throw $notificationError;
                        // log_message('error', '[NEW_USER_REGISTERED] Notification error: ' . $notificationError->getMessage());
                        log_message('error', '[NEW_USER_REGISTERED] Notification error trace: ' . $notificationError->getTraceAsString());
                    }
                } else {
                    $response['message'] = labels(REGISTRATION_FAILED, 'Registration failed');
                    return $this->response->setJSON($response);
                }
            }

            // log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Response => " . $response['token'], date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - manage_user()');
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            // throw $th;
            $response = [
                'error' => true,
                'message' => 'Something went wrong'
            ];
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - manage_user()');
            return $this->response->setJSON($response);
        }
    }

    public function update_user()
    {
        try {
            helper(['form', 'url']);
            $validation = \Config\Services::validation();
            $config = new \Config\IonAuth();
            $tables = $config->tables;
            $validation->setRules(
                [
                    'email' => 'permit_empty|valid_email',
                    'phone' => 'permit_empty|numeric|is_unique[' . $tables['users'] . '.phone]',
                    'username' => 'permit_empty',
                    'referral_code' => 'permit_empty',
                    'friends_code' => 'permit_empty',
                    'city_id' => 'permit_empty',
                    'latitude' => 'permit_empty',
                    'longitude' => 'permit_empty',
                ],
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $firstError = reset($errors);
                $response = [
                    'error' => true,
                    'message' => $firstError,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            //Data
            $arr = array_filter([
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'phone' => $this->request->getPost('mobile'),
                'country_code'  => $this->request->getPost('country_code'),
                'referral_code' => $this->request->getPost('referral_code'),
                'friends_code' => $this->request->getPost('friends_code'),
                'city' => $this->request->getPost('city_id'),
                'latitude' => $this->request->getPost('latitude'),
                'longitude' => $this->request->getPost('longitude')
            ], fn($value) => !empty($value));
            $user_id = $this->user_details['id'];
            
            // ============================================================
            // LOGIN TYPE PROTECTION: Do not allow updating identity fields
            // based on the user's login type. Similar to manage_user login flow.
            // ============================================================
            $currentUser = fetch_details('users', ['id' => $user_id]);
            if (!empty($currentUser)) {
                $userLoginType = $currentUser[0]['loginType'] ?? '';
                
                // Fields that should never be updated via update_user
                // (loginType should never be changeable)
                $protectedFields = ['loginType'];
                
                // Add login-type-specific protected fields
                // - Phone login: cannot change phone or country_code
                // - Email login: cannot change email
                // - Google/Apple login: cannot change email (tied to social account)
                if ($userLoginType === 'phone') {
                    $protectedFields = array_merge($protectedFields, ['phone', 'country_code']);
                } elseif ($userLoginType === 'email') {
                    $protectedFields = array_merge($protectedFields, ['email']);
                } elseif (in_array($userLoginType, ['google', 'apple'])) {
                    $protectedFields = array_merge($protectedFields, ['email']);
                }
                
                // Remove protected fields from update array
                foreach ($protectedFields as $field) {
                    unset($arr[$field]);
                }
            }
            if (!exists(['id' => $user_id], 'users')) {
                $response = [
                    'error' => true,
                    'message' => labels(INVALID_USER_ID, 'Invalid User Id'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            if ($this->request->getFile('image')) {
                $file = $this->request->getFile('image');
                if (!$file->isValid()) {
                    $response = [
                        'error' => true,
                        'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                        'data' => [],
                    ];
                    return $this->response->setJSON($response);
                }
                $type = $file->getMimeType();
                if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg') {
                    $check_image = fetch_details('users', ['id' => $this->user_details['id']], ['image']);
                    if ($file) {
                        $oldImage = !empty($check_image) ? ($check_image[0]['image'] ?? null) : null;
                        $result = service('fileService')->replace($oldImage, $file, 'profile');
                        if ($result['error']) {
                            return ErrorResponse($result['message'] ?? labels(FAILED_TO_CREATE_PROFILES_FOLDERS, 'Failed to create profiles folders'), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $arr['image'] = $result['path'];
                    }
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(PLEASE_ATTACH_A_VALID_IMAGE_FILE, 'Please attach a valid image file.'),
                        'data' => [],
                    ];
                    return $this->response->setJSON($response);
                }
            }
            if (!empty($arr)) {
                $status = update_details($arr, ['id' => $user_id], 'users');
                $web_fcm_id = fetch_details('users_fcm_ids', ['user_id' => $user_id, 'platform' => 'web'], 'fcm_id');
                $user_fcm_ids = fetch_details('users_fcm_ids', ['user_id' => $user_id, 'platform !=' => 'web'], 'fcm_id');
                $web_fcm_id = !empty($web_fcm_id) ? $web_fcm_id[0]['fcm_id'] : '';
                $user_fcm_ids = !empty($user_fcm_ids) ? $user_fcm_ids[0]['fcm_id'] : '';
                if ($status) {
                    $data = fetch_details('users', ['id' => $user_id])[0];
                    $data['fcm_id'] = $user_fcm_ids;
                    $data['web_fcm_id'] = $web_fcm_id;
                    $data['image'] = !empty($data['image']) ? service('fileService')->url($data['image'], 'profile') : "";
                    $response = [
                        'error' => false,
                        'message' => labels(USER_UPDATED_SUCCESSFULLY, 'User updated successfully.'),
                        'data' => remove_null_values($data),
                    ];
                    return $this->response->setJSON($response);
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(PLEASE_INSERT_ANY_ONE_FIELD_TO_UPDATE, 'Please insert any one field to update.'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - update_user()');
            return $this->response->setJSON($response);
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
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $firstError = reset($errors);
                $response = [
                    'error' => true,
                    'message' => $firstError,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $fcm_id = $this->request->getPost('fcm_id');
            $platform = $this->request->getPost('platform');

            $result = store_users_fcm_id($this->user_details['id'], $fcm_id, $platform, '');
            if ($result) {
                return response_helper(labels(FCM_ID_UPDATED_SUCCESSFULLY, 'fcm id updated succesfully'), true, ['fcm_id' => $fcm_id]);
            } else {
                return response_helper();
            }
            // if (update_details(['fcm_id' => $fcm_id, 'platform' => $platform], ['id' => $this->user_details['id']], 'users')) {
            //     return response_helper('fcm id updated succesfully', true, ['fcm_id' => $fcm_id]);
            // } else {
            //     return response_helper();
            // }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - update_fcm()');
            return $this->response->setJSON($response);
        }
    }

    public function verify_user()
    {
        try {
            $request = service('request');
            $db      = db_connect();

            $mobile       = trim($request->getPost('mobile') ?? '');
            $email        = trim($request->getPost('email') ?? '');
            $uid          = trim($request->getPost('uid') ?? '');
            $country_code = trim($request->getPost('country_code') ?? '');
            $updatePassword  = (string)$request->getPost('password_update');
            $loginType      = trim($request->getPost('login_type') ?? '');

            if ($mobile && $email && $loginType !== 'apple' && $loginType !== 'google') {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(ENTER_MOBILE_OR_EMAIL, 'Enter either Mobile or Email, not both'),
                ]);
            }

            if (!$mobile && !$uid && $loginType !== 'email') {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(ENTER_MOBILE_OR_UID, 'Enter Mobile, Email or UID'),
                ]);
            }

            $builder = $db->table('users u')
                ->select('u.id, u.active, u.phone, u.email, u.country_code, u.password')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 2)
                ->where('u.loginType', $loginType);

            $identityType = $email ? 'email' : ($mobile ? 'mobile' :'uid');

            $identityMap = [
                'email'  => 'u.email',
                'mobile' => 'u.phone',
                'uid'    => 'u.uid',
            ];

            $builder->where($identityMap[$identityType], $$identityType);

            if ($identityType === 'mobile' && $country_code) {
                $builder->where('u.country_code', $country_code);
            }

            $user = $builder->get()->getRowArray();

            /**
             * Message Codes
             * 101 → Exists & Active
             * 102 → Not Registered
             * 103 → Exists but Deactive
             */
            $hasUser = !empty($user);

            $message_code = !$hasUser
                ? '102'
                : ($user['active'] ? '101' : '103');

            // Decide if password exists (including user-not-found cases)
            $hasPassword = $hasUser && !empty($user['password']);
            
            $response = [
                'error'        => false,
                'message_code' => $message_code,
                'has_password' => $hasPassword,
            ];

            // 🔐 OTP handling (only for 101 & 102)
            $settings = get_settings('general_settings', true);
            $authMode = $settings['authentication_mode'];

            $loginSettings = get_settings('login_settings', true);
            $cple = (int)($loginSettings['customer_password_login_enabled'] ?? 0); // 1 or 0

            // 🧠 OTP decision logic
            $sendOtp = false;

            if ($updatePassword === '1') {
                $sendOtp = true;
            } else {
                if ($cple === 1) {
                    // password login enabled → send only if user has no password
                    $sendOtp = !$hasPassword;
                } else {
                    // password login disabled → OTP always
                    $sendOtp = true;
                }
            }

            if (in_array($message_code, ['101', '102']) && $sendOtp) {
                $otp = random_int(100000, 999999);

                if ($email) {
                    $otpResponse = set_user_otp_email($email, $otp);
                } elseif ($mobile && $authMode === 'sms_gateway') {
                    $otpResponse = set_user_otp(
                        $otp,
                        $mobile,
                        $country_code ?: ($user['country_code'] ?? '')
                    );
                }

                if (!empty($otpResponse) && $otpResponse['error']) {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => $otpResponse['message'],
                    ]);
                }

                $response['message'] = labels(
                    OTP_SEND_SUCCESSFULLY,
                    'OTP sent successfully'
                );
            }

            $response['authentication_mode'] = $authMode;
            return $this->response->setJSON($response);
        } catch (\Throwable $e) {
            // throw $e;
            log_the_responce(
                $this->request->header('Authorization') .
                ' Params :: ' . json_encode($_POST) .
                ' Error => ' . $e->getMessage(),
                date('Y-m-d H:i:s') . ' verify_user()'
            );

            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function verify_otp()
    {
        $validation = service('validation');

        $rules = [
            'otp' => 'required',
        ];

        // If they sent phone, demand phone
        if ($this->request->getPost('phone')) {
            $rules['phone'] = 'required';
        }

        // If they sent email, demand email
        if ($this->request->getPost('email')) {
            $rules['email'] = 'required|valid_email';
        }

        // If they sent neither (little goblin move), force at least one
        if (!$this->request->getPost('phone') && !$this->request->getPost('email')) {
            $rules['phone'] = 'required';
            $rules['email'] = 'required|valid_email';
        }

        $validation->setRules($rules);

        if (!$validation->withRequest($this->request)->run()) {
            $errors = $validation->getErrors();
            return $this->response->setJSON([
                'error'   => true,
                'message' => reset($errors),
                'data'    => [],
            ]);
        }

        $otp   = $this->request->getPost('otp');
        $email = $this->request->getPost('email');
        $updatePassword = $this->request->getPost('password_update');

        /**
         * -------------------------------------
         * Decide channel & identity dynamically
         * -------------------------------------
         */
        if (!empty($email)) {
            // Email OTP
            $identity = $email;
            $channel  = 'email';
        } else {
            // SMS OTP
            $mobile       = $this->request->getPost('phone');
            $country_code = $this->request->getPost('country_code') ?? '';
            $identity     = $country_code . $mobile;
            $channel      = 'sms';
        }

        /**
         * -------------------------------------
         * Fetch OTP
         * -------------------------------------
         */
        $otpData = fetch_details('otps', [
            'identity' => $identity,
            'channel'  => $channel,
            'otp'      => $otp,
        ]);

        if (empty($otpData)) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(OTP_NOT_VERIFIED, 'OTP not verified'),
            ]);
        }

        /**
         * -------------------------------------
         * Expiry check
         * -------------------------------------
         */
        $timeExpire = checkOTPExpiration($otpData[0]['created_at']);

        if ($timeExpire['error']) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => $timeExpire['message'],
            ]);
        }

        $resetToken = null;
        if (!empty($updatePassword) && $updatePassword == '1') {
            $resetToken = bin2hex(random_bytes(32));

            update_details(table: 'users', set: [
                'password_reset_token'   => $resetToken,
                'password_reset_expires' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
            ], where: ['id' => $otpData[0]['user_id']]);
        }

        /**
         * -------------------------------------
         * Success
         * -------------------------------------
         */
        return $this->response->setJSON([
            'error'   => false,
            'message' => labels(OTP_VERIFIED, 'OTP verified'),
            'reset_token' => $resetToken,
        ]);
    }

    public function resend_otp()
    {
        $request = $this->request;
        $validation = service('validation');

        $validation->setRules([
            'mobile' => 'permit_empty|required_without[email]',
            'email'  => 'permit_empty|valid_email|required_without[mobile]',
            'country_code' => 'required_with[mobile]',
        ]);

        if (!$validation->withRequest($request)->run()) {
            $errors = $validation->getErrors();
            return $this->response->setJSON([
                'error' => true,
                'message' => reset($errors),
                'data' => [],
            ]);
        }

        $mobile = $request->getPost('mobile');
        $email  = $request->getPost('email');
        $country_code = $request->getPost('country_code');
        $loginType = $request->getPost('login_type');
        // Hard stop if both exist
        if (!empty($mobile) && !empty($email)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ENTER_MOBILE_OR_EMAIL, 'Enter either Mobile or Email'),
                'data' => [],
            ]);
        }

        $otp = random_int(100000, 999999);

        // Email path
        if (!empty($email) && $loginType === 'email') {
            $result = set_user_otp_email($email, $otp);

            if ($result['error']) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $result['message'],
                    'data' => [],
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(OTP_SEND_SUCCESSFULLY, 'OTP send successfully'),
                'data' => [],
            ]);
        }

        // SMS path
        $result = set_user_otp($otp, $mobile, $country_code);

        if ($result['error']) {
            return $this->response->setJSON([
                'error' => true,
                'message' => $result['message'],
                'data' => [],
            ]);
        }

        return $this->response->setJSON([
            'error' => false,
            'message' => labels(OTP_SEND_SUCCESSFULLY, 'OTP send successfully'),
            'data' => [],
        ]);
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
                delete_details(['fcm_id' => $fcm_id], 'users_fcm_ids');
            }

            // ============================================================
            // Invalidate bearer token used for this request (if present).
            // ------------------------------------------------------------
            // We:
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

    public function change_password()
    {
        $db        = \Config\Database::connect();
        $request   = service('request');

        $newPassword     = trim($request->getPost('new_password') ?? '');
        $confirmPassword = trim($request->getPost('confirm_password') ?? '');
        $oldPassword     = trim($request->getPost('old_password') ?? '');
        $resetToken      = trim($request->getPost('reset_token') ?? '');

        // 1. Basic validation
        if ($newPassword === '' || $confirmPassword === '') {
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('new_password_and_confirm_password_are_required', 'New password and confirm password are required')
            ]);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('password_and_confirm_password_do_not_match', 'Password and confirm password do not match')
            ]);
        }

        // Pull bearer token
        $authHeader = $this->request->getHeaderLine('Authorization');
        $token = null;
        if ($authHeader && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }


        /*
        |--------------------------------------------------------------------------
        | 1: Normal logged-in user flow (Bearer token)
        |--------------------------------------------------------------------------
        */
        if (!empty($token) && empty($resetToken)) {
            $tokenResult = verify_app_request();

            if ($tokenResult['error']) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $tokenResult['message'] ?? 'Unauthorized'
                ])->setStatusCode($tokenResult['status'] ?? 401);
            }

            if (empty($tokenResult['data']['id'])) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('user_not_found', 'User not found')
                ]);
            }

            $userRow = $tokenResult['data'];
            $userId  = $userRow['id'];

            // In demo mode, block only the specific demo user: phone 9876543210, +91, group 2, login type phone
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $this->isDemoRestrictedUser($userRow)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.')
                ]);
            }

            // Require old password if user already has one
            if (empty($oldPassword)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('old_password_required', 'Old password required')
                ]);
            }

            if (!empty($userRow['password']) && !password_verify($oldPassword, $userRow['password'])) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('old_password_is_incorrect', 'Old password is incorrect')
                ]);
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);

            $db->transStart();

            $db->table('users')->where('id', $userId)->update([
                'password'               => $hash,
                'password_reset_token'   => null,
                'password_reset_expires' => null,
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('password_update_failed', 'Password update failed'),
                ]);
            }

            return $this->response->setJSON([
                'error'   => false,
                'message' => labels('password_updated_successfully', 'Password updated successfully'),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 2: Reset token flow (forgot password)
        |--------------------------------------------------------------------------
        */
        if (!empty($resetToken)) {
            $userRow = $db->table('users')
                ->where('password_reset_token', $resetToken)
                ->get()
                ->getRowArray();


            if (empty($userRow)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_reset_token', 'Invalid reset token')
                ]);
            }

            if (
                !empty($userRow['password_reset_expires']) &&
                strtotime($userRow['password_reset_expires']) < time()
            ) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('reset_token_expired', 'Reset token expired')
                ]);
            }

            // In demo mode, block only the specific demo user: phone 9876543210, +91, group 2, login type phone
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $this->isDemoRestrictedUser($userRow)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.')
                ]);
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);

            $db->transStart();

            $db->table('users')->where('id', $userRow['id'])->update([
                'password'               => $hash,
                'password_reset_token'   => null,
                'password_reset_expires' => null,
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();


            if ($db->transStatus() === false) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('password_update_failed', 'Password update failed'),
                ]);
            }
            return $this->response->setJSON([
                'error'   => false,
                'message' => labels('password_updated_successfully', 'Password updated successfully'),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 3: Firebase mode change (special case)
        |--------------------------------------------------------------------------
        */
        $settings = get_settings('general_settings', true);
        if (strtolower($settings['authentication_mode'] ?? '') === 'firebase') {
            $mobile = $this->request->getPost('mobile');
            $countryCode = $this->request->getPost('country_code');
            $loginType = $this->request->getPost('login_type');

            if (empty($mobile) || empty($countryCode)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('mobile_and_country_code_are_required', 'Mobile and country code are required'),
                ]);
            }

            // For demo check we need the user row; fetch by phone, country_code, loginType
            $userRowFirebase = $db->table('users')
                ->where('phone', $mobile)
                ->where('country_code', $countryCode)
                ->where('loginType', $loginType ?? '')
                ->get()
                ->getRowArray();

            if(empty($userRowFirebase)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('user_not_found', 'User not found')
                ]);
            }
            
            if(!empty($userRowFirebase) && ($userRowFirebase['loginType'] == 'google' || $userRowFirebase['loginType'] == 'apple')) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('changing_password_for_google_or_apple_login_is_not_allowed', 'Changing password for Google or Apple login is not allowed')
                ]);
            }
            if (!empty($userRowFirebase) && defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $this->isDemoRestrictedUser($userRowFirebase)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.')
                ]);
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);

            $db->transStart();

            $db->table('users')->where([
                'phone'        => $mobile,
                'country_code' => $countryCode,
                'loginType'    => $loginType,
            ])->update([
                'password'   => $hash,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('password_update_failed', 'Password update failed'),
                ]);
            }

            return $this->response->setJSON([
                'error'   => false,
                'message' => labels('password_updated_successfully', 'Password updated successfully'),
            ]);
        }
    }

    public function get_user_info()
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 2)
                ->where(['u.id' =>  $this->user_details['id']]);
            $data = $builder->get()->getResultArray()[0];

            // Calculate unread chats count for admin <-> customer (current user) conversations.
            // This counts all chat messages where:
            // - The current user is the receiver (customer side, receiver_type = 2)
            // - The sender is an admin (sender_type = 0)
            // - The message is not yet read (is_read = 0)
            $unreadAdminChatsCount = $db->table('chats')
                ->where('receiver_id', (int) $this->user_details['id'])
                ->where('receiver_type', 2)
                ->where('sender_type', 0)
                ->where('is_read', 0)
                ->countAllResults();

            // Attach unread admin chats count to user payload for easy access on client side.
            $data['unread_chats_count'] = (int) $unreadAdminChatsCount;

            $data['image'] = !empty($data['image']) ? service('fileService')->url($data['image'], 'profile') : "";
            $data['login_type'] = $data['loginType'];
            unset($data['loginType']);
            $data = remove_null_values($data);
            $response = [
                'error' => false,
                'message' => labels(USER_FETCHED_SUCCESSFULLY, 'User fetched successfully'),
                'data' => $data,
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_user_info()');
            return $this->response->setJSON($response);
        }
    }

    /**
     * Returns true only if the user is the specific demo account that must be blocked in demo mode:
     * phone 9876543210, country_code +91, group_id 2 (from users_groups), loginType phone.
     * Used by change_password to throw demo error only for this user.
     *
     * @param array $userRow User row with at least id, phone, country_code, loginType
     * @return bool
     */
    private function isDemoRestrictedUser(array $userRow): bool
    {
        $phone = $userRow['phone'] ?? '';
        $countryCode = $userRow['country_code'] ?? '';
        $loginType = $userRow['loginType'] ?? '';

        if ($phone !== '9876543210' || $countryCode !== '+91' || $loginType !== 'phone') {
            return false;
        }

        $userId = $userRow['id'] ?? null;
        if (empty($userId)) {
            return false;
        }

        $userGroups = fetch_details('users_groups', ['user_id' => $userId]);
        foreach ($userGroups as $ug) {
            if (isset($ug['group_id']) && (int) $ug['group_id'] === 2) {
                return true;
            }
        }

        return false;
    }

    public function delete_user_account()
    {
        try {
            $user_id = $this->user_details['id'];
            if (!exists(['id' => $user_id], 'users')) {
                return response_helper(labels(USER_DOES_NOT_EXIST_PLEASE_ENTER_VALID_USER_ID, 'user does not exist please enter valid user ID!'), true);
            }
            $user = fetch_details('users', ['id' => $user_id]);
            // In demo mode, block only the specific demo user: phone 9876543210, +91, group 2, login type phone
            if (!empty($user) && defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $this->isDemoRestrictedUser($user[0])) {
                return response_helper(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'), true);
            }
            $user_data = fetch_details('users_groups', ['user_id' => $user_id]);

            if (!empty($user_data) && isset($user_data[0]['group_id']) && !empty($user_data[0]['group_id']) && $user_data[0]['group_id'] == 2) {
                if (delete_details(['id' => $user_id], 'users') && delete_details(['user_id' => $user_id], 'users_groups')) {
                    delete_details(['user_id' => $user_id], 'users_tokens');
                    return response_helper(labels(USER_ACCOUNT_DELETED_SUCCESSFULLY, 'User account deleted successfully'), false);
                } else {
                    return response_helper(labels(USER_ACCOUNT_DOES_NOT_DELETE, 'User account does not delete'), true);
                }
            } else {
                return response_helper(labels(THIS_USER_S_ACCOUNT_CAN_T_DELETE, "This user's account can't delete "), true);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - delete_user_account()');
            return $this->response->setJSON($response);
        }
    }
}
