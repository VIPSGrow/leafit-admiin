<?php

namespace App\Controllers\Admin;

use App\Models\Notification_model;
use App\Services\NotificationTemplateService;
use CodeIgniter\I18n\Time;


class Notification extends Admin
{
    public $validation, $notification, $db;
    protected $superadmin;

    public function __construct()
    {
        parent::__construct();
        helper(['form', 'url']);
        $this->notification = new Notification_model();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('send_notification', 'Send Notification') . ' | ' . labels('admin_panel', 'Admin Panel'), 'notification');
        $this->data['categories_name'] = get_categories_with_translated_names();

        // Fetch users for personal dropdown, excluding admins (user_group 1) and deactivated users
        // Use subquery to exclude users who have group_id = 1 in users_groups table
        // Also exclude deactivated users (active != 1)
        $db = \Config\Database::connect();
        $subquery = $db->table('users_groups')
            ->select('user_id')
            ->where('group_id', 1);
        $builder = $db->table('users');

        $builder->select("
            id,
            CASE 
                WHEN (username IS NULL OR username = '')
                THEN CONCAT(COALESCE(country_code, ''), ' ', COALESCE(phone, ''))
                ELSE CONCAT(username, ' (', COALESCE(country_code, ''), ' ', COALESCE(phone, ''), ')')
            END AS username
        ")
            ->whereNotIn('id', $subquery)
            ->where('active', 1)
            ->groupStart()
            ->where('(username IS NOT NULL AND username != "")')
            ->orWhere('(phone IS NOT NULL AND phone != "")')
            ->orWhere('(country_code IS NOT NULL AND country_code != "")')
            ->groupEnd()
            ->orderBy('username', 'ASC');

        $this->data['users'] = $builder->get()->getResultArray();

        // Get current + default language
        $currentLanguage = get_current_language();
        $defaultLanguage = get_default_language();

        $db = \Config\Database::connect();
        // Fetch partners with translated company_name
        $partners = $db->table('users u')
            ->select('
            u.id,
            u.username,
            pd.partner_id,
            pd.company_name,
            pd.number_of_members,
            pd.at_store,
            pd.at_doorstep,
            pd.need_approval_for_the_service,
            tpd_current.company_name as current_translated_name,
            tpd_default.company_name as default_translated_name
        ')
            ->join('partner_details pd', 'pd.partner_id = u.id')
            ->join(
                'translated_partner_details tpd_current',
                'tpd_current.partner_id = pd.partner_id 
         AND tpd_current.language_code = "' . $currentLanguage . '"',
                'left'
            )
            ->join(
                'translated_partner_details tpd_default',
                'tpd_default.partner_id = pd.partner_id 
         AND tpd_default.language_code = "' . $defaultLanguage . '"',
                'left'
            )
            ->where('pd.is_approved', '1')
            ->get()
            ->getResultArray();

        // Replace with translated names where available
        foreach ($partners as &$partner) {
            if (!empty($partner['current_translated_name'])) {
                $partner['display_company_name'] = $partner['current_translated_name'];
            } elseif (!empty($partner['default_translated_name'])) {
                $partner['display_company_name'] = $partner['default_translated_name'];
            } else {
                $partner['display_company_name'] = $partner['company_name'];
            }
        }
        $this->data['partners'] = $partners;
        $this->data['notification'] = fetch_details('notifications');
        // fetch languages
        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
        $this->data['languages'] = $languages;
        return view('backend/admin/template', $this->data);
    }


    public function add_notification()
    {

        // print_r($_POST);
        // die;
        try {
            // Authentication check
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            // Get request data
            $type = $this->request->getPost('type');
            $user_type = $this->request->getPost('user_type');
            $title = $this->request->getPost('title');
            $message = $this->request->getPost('message');
            $image_data = $this->request->getFile('image');

            /**
             * IMPORTANT RULE (UI + API safety):
             * When "Send To" is Provider, "Notification Type" must NOT be "provider".
             *
             * Why:
             * - "Send To = provider" already targets provider users (audience).
             * - "Notification Type = provider" is used as a click-target type (provider-details).
             * - Allowing both creates ambiguous behavior and incorrect payload assumptions.
             *
             * Even though the UI hides the option, we must enforce it here too
             * to prevent request tampering.
             */
            if ($user_type === 'provider' && $type === 'provider') {
                return ErrorResponse('For provider audience, notification type cannot be provider. Please use General, Category, or URL.', true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Validation rules setup
            $common_rules = [
                'title' => [
                    "rules" => 'required|trim',
                    "errors" => ["required" => labels('please_enter_title_for_notification', "Please enter title for notification")]
                ],
                'message' => [
                    "rules" => 'required',
                    "errors" => ["required" => labels('please_enter_message_for_notification', "Please enter message for notification")]
                ]
            ];

            // Specific rules based on user type and notification type
            $specific_rules = [];
            // Validate user_ids when user_type is "specific_user"
            if ($user_type == "specific_user") {
                $specific_rules['user_ids'] = [
                    "rules" => 'required',
                    "errors" => ["required" => labels('please_select_at_least_one_user', "Please select at least one user")]
                ];
            }
            // Validate URL when notification type is "url"
            if ($type == "url") {
                $specific_rules['url'] = [
                    "rules" => 'required|valid_url',
                    "errors" => [
                        "required" => labels('please_enter_url_for_notification', 'Please enter URL for notification'),
                        "valid_url" => labels('please_enter_valid_url', 'Please enter a valid URL')
                    ]
                ];
                $specific_rules['type'] = [
                    "rules" => 'required',
                    "errors" => ["required" => labels('please_select_type_of_notification', "Please select type of notification")]
                ];
            } else {
                $specific_rules['type'] = [
                    "rules" => 'required',
                    "errors" => ["required" => labels('please_select_type_of_notification', "Please select type of notification")]
                ];
            }

            // Merge rules and validate
            $this->validation->setRules(array_merge($common_rules, $specific_rules));
            if (!$this->validation->withRequest($this->request)->run()) {
                return ErrorResponse($this->validation->getErrors(), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Image handling
            // Check if image checkbox is checked before processing image
            // This prevents previously uploaded images from being included when checkbox is unchecked
            $image_checkbox_checked = $this->request->getPost('image_checkbox') == 'on' || $this->request->getPost('image_checkbox') == '1';

            $t = time();
            $image = '';
            $ext = '';
            $image_name = '';

            // Only process image if checkbox is checked
            if ($image_checkbox_checked && $image_data && $image_data->isValid()) {
                $image = ($image_data->getName() != "") ? $image_data : '';
                $ext = ($image != "") ? $image->getExtension() : '';
                $image_name = ($image != "") ? $t . '.' . $ext : '';
            }

            // Prepare notification data
            $data = $this->prepareNotificationData($user_type, $type, $title, $message, $image_name);

            // Upload and compress image if exists and checkbox is checked
            if ($image_checkbox_checked && $ext != '') {
                $path = "public/uploads/notification/";
                $tempPath = $image->getTempName();
                $full_path = $path . $image_name;
                compressImage($tempPath, $full_path, 70);
            }

            $paths = [
                'image' => [
                    'file' => $this->request->getFile('image'),
                    'path' => 'public/uploads/notification',
                    'error' => labels('failed_to_create_notification_folders', 'Failed to create notification folders'),
                    'folder' => 'notification'
                ],
            ];
            $uploadedFiles = [];
            $registrationIDs = [];

            // Only process image upload if checkbox is checked
            if ($image_checkbox_checked) {
                foreach ($paths as $key => $upload) {
                    if ($upload['file'] && $upload['file']->isValid()) {
                        $result = upload_file($upload['file'], $upload['path'], $upload['error'], $upload['folder']);
                        $image_disk = $result['disk'];
                        if ($result['error'] == false) {
                            $uploadedFiles[$key] = [
                                'url' => 'public/uploads/notification/' . $result['file_name'],
                                'disk' => $result['disk']
                            ];
                            $data['image'] = $result['file_name'];
                        } else {
                            return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                }
            } else {
                // Ensure image is empty when checkbox is unchecked
                $data['image'] = '';
            }


            // Save notification to database
            if (!$this->notification->save($data)) {
                return ErrorResponse(labels('failed_to_save_notification', "Failed to save notification."), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Create FCM message data for custom notification
            // Note: NotificationService will handle fetching recipients based on the options we provide
            $fcmMsg = $this->createFcmMessage($type, $title, $message, $data, $ext, $image_name);
            $path = "public/uploads/notification/";

            // Prepare notification data for NotificationService
            // Extract user IDs from registration IDs if available
            $userIds = $this->extractUserIdsFromRecipients($user_type, $type);

            // Ensure user IDs are integers and log for debugging
            if (!empty($userIds)) {
                $userIds = array_map('intval', $userIds);
                $userIds = array_filter($userIds, function ($id) {
                    return $id > 0;
                });
                $userIds = array_values($userIds); // Re-index array
            } else {
                log_message('info', '[ADMIN_NOTIFICATION] No user IDs extracted for user_type: ' . $user_type);
            }

            // Prepare custom data for FCM notification
            // This data will be included in the FCM payload
            $customData = [];
            if (isset($fcmMsg['type_id'])) {
                $customData['type_id'] = $fcmMsg['type_id'];
            }
            if (isset($fcmMsg['category_id'])) {
                $customData['category_id'] = $fcmMsg['category_id'];
                $customData['parent_id'] = $fcmMsg['parent_id'] ?? '';
                $customData['category_name'] = $fcmMsg['category_name'] ?? '';
            }
            if (isset($fcmMsg['provider_id'])) {
                $customData['provider_id'] = $fcmMsg['provider_id'];
                $customData['provider_name'] = $fcmMsg['provider_name'] ?? '';
            }
            if (isset($fcmMsg['url'])) {
                $customData['url'] = $fcmMsg['url'];
            }

            // Add provider slug and default provider name (for provider or category types)
            if (isset($fcmMsg['provider_slug'])) {
                $customData['provider_slug'] = $fcmMsg['provider_slug'];
                // Add web_click_type for provider-details when provider_slug is present
                $customData['web_click_type'] = 'provider-details';
            }
            if (isset($fcmMsg['provider_name_default'])) {
                $customData['provider_name_default'] = $fcmMsg['provider_name_default'];
            }

            // Add category slug (for provider or category types)
            if (isset($fcmMsg['category_slug'])) {
                $customData['category_slug'] = $fcmMsg['category_slug'];
                // Add web_click_type for category when category_slug is present
                $customData['web_click_type'] = 'category';
            }

            // Add parent category slugs (comma-separated list, if category is a subcategory)
            if (isset($fcmMsg['parent_category_slugs'])) {
                $customData['parent_category_slugs'] = $fcmMsg['parent_category_slugs'];
            }
            // Handle image URL - construct full URL if image exists
            // Use image name from saved data, or from fcmMsg if available
            $imageName = $data['image'] ?? '';
            // If image name is in fcmMsg, extract just the filename (it might have full URL)
            if (empty($imageName) && isset($fcmMsg['image']) && !empty($fcmMsg['image'])) {
                // Extract filename from URL if it's a full URL
                $imageName = basename($fcmMsg['image']);
            }

            // Construct full image URL if image exists
            if (!empty($imageName)) {
                $disk = fetch_current_file_manager();
                if ($disk == "local_server") {
                    $customData['image'] = base_url($path) . '/' . $imageName;
                } else if ($disk == "aws_s3") {
                    $customData['image'] = fetch_cloud_front_url('notification', $imageName);
                } else {
                    $customData['image'] = base_url($path) . '/' . $imageName;
                }
            }
            $customData['click_action'] = $fcmMsg['click_action'] ?? 'FLUTTER_NOTIFICATION_CLICK';

            // Prepare options for NotificationService
            // For specific users, only send to their active platforms (don't send to all platforms)
            // This prevents sending duplicate notifications to the same user on multiple devices
            $platforms = ['android', 'ios', 'web']; // Include web platform for web notifications

            // Include provider_panel so providers receive the notification in their panel as well as apps
            if ($user_type === 'provider') {
                $platforms[] = 'provider_panel';
            } elseif (!empty($userIds)) {
                // Specific user(s): include provider_panel if any selected user is a provider
                $providerUserIds = $this->db->table('users_groups')
                    ->select('user_id')
                    ->whereIn('user_id', $userIds)
                    ->where('group_id', 3)
                    ->get()
                    ->getResultArray();
                if (!empty($providerUserIds)) {
                    $platforms[] = 'provider_panel';
                }
            }

            $options = [
                'channels' => ['fcm'], // Only send FCM notifications for admin custom notifications
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $customData,
                'platforms' => $platforms, // Include web platform for web notifications
            ];

            // Add user IDs to options if we have them
            if (!empty($userIds)) {
                $options['user_ids'] = $userIds;
            } else {
                // If no specific user IDs, determine how to send
                if ($user_type === 'all_users') {
                    // Send to all users
                    $options['send_to_all'] = true;
                } elseif ($user_type === 'customer') {
                    // Send to all customers (group_id = 2)
                    $options['user_groups'] = [2];
                } elseif ($user_type === 'provider') {
                    // Send to all providers (group_id = 3)
                    $options['user_groups'] = [3];
                }
            }

            // Queue notification using NotificationService
            // Use a generic event type for admin custom notifications
            $eventType = 'admin_custom_notification';
            $context = [
                'notification_type' => $type,
                'admin_notification' => true,
            ];

            // Queue the notification
            queue_notification_service($eventType, [], $context, $options, 'high');

            // Return success response
            return successResponse(labels('send_notification_successfully', "Send notification successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - add_notification()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Prepare notification data based on user type and notification type
     */
    private function prepareNotificationData($user_type, $type, $title, $message, $image_name)
    {
        $data = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'image' => $image_name,
            'notification_type' => $type,
            /**
             * IMPORTANT:
             * We tag admin-panel notifications explicitly.
             * This allows the app APIs to apply admin-specific formatting safely
             * (e.g., building full image URLs only for admin notifications).
             */
            'event_type' => 'sent_by_admin',
        ];

        // Set user_id and target based on user type
        switch ($user_type) {
            case 'all_users':
                $data['user_id'] = ['-'];
                $data['target'] = 'all_users';
                break;
            case 'specific_user':
                $data['user_id'] = json_encode($_POST['user_ids']);
                $data['target'] = 'specific_user';
                break;
            case 'provider':
                $data['target'] = 'provider';
                break;
            case 'customer':
                $data['user_id'] = ['-'];
                $data['target'] = 'customer';
                break;
            default:
            // Default case
        }

        // Set type_id based on notification type
        switch ($type) {
            case 'general':
                $data['type_id'] = '-';
                break;
            case 'provider':
                $data['type_id'] = $_POST['partner_id'];
                // $data['user_id'] = json_encode($_POST['partner_id']);
                break;
            case 'category':
                $data['type_id'] = $_POST['category_id'];
                break;
            case 'url':
                $data['type_id'] = '-';
                $data['url'] = $_POST['url'];

                break;
        }

        return $data;
    }

    /**
     * Get notification recipients based on user type
     */
    private function getNotificationRecipients($user_type, $type)
    {
        $registrationIDs = [];
        $web_registrationIDs = [];

        switch ($user_type) {
            case 'all_users':
                // Get all users with FCM ID

                $users_fcm = fetch_details('users_fcm_ids', ['status' => '1'], ['fcm_id', 'platform'], '', '', 'id', 'DESC', 'platform', ['android', 'ios']);
                // $where = "fcm_id IS NOT NULL AND fcm_id != '' AND platform IS NOT NULL AND platform!=''";
                // $users_fcm = $this->db->table('users')->select('fcm_id,platform')->where($where)->get()->getResultArray();

                foreach ($users_fcm as $ids) {
                    if ($ids['fcm_id'] != "") {
                        $registrationIDs[] = [
                            'fcm_id' => $ids['fcm_id'],
                            'platform' => $ids['platform']
                        ];
                    }
                }
                // Get web FCM IDs
                // $web_where = "web_fcm_id IS NOT NULL AND web_fcm_id != ''";
                // $web_fcm_id = $this->db->table('users')->select('web_fcm_id')->where($web_where)->get()->getResultArray();
                $web_fcm_id = fetch_details('users_fcm_ids', ['status' => '1'], ['fcm_id'], '', '', 'id', 'DESC', 'platform', ['web']);


                foreach ($web_fcm_id as $ids) {
                    if ($ids['fcm_id'] != "") {
                        $web_registrationIDs[] = ['web_fcm_id' => $ids['fcm_id']];
                    }
                }

                break;

            case 'specific_user':
                $to_send_id = $_POST['user_ids'];

                // Get mobile FCM IDs
                // $users_fcm = $this->db->table('users')
                //     ->select('fcm_id,platform')
                //     ->whereIn('id', $to_send_id)
                //     ->get()
                //     ->getResultArray();

                $users_fcm = $this->db->table('users_fcm_ids')
                    ->select('fcm_id,platform')
                    ->where('status', '1')
                    ->whereIn('user_id', $to_send_id)
                    ->whereIn('platform', ['android', 'ios'])
                    ->get()
                    ->getResultArray();


                if (!empty($users_fcm)) {
                    foreach ($users_fcm as $ids) {
                        if ($ids['fcm_id'] != "") {
                            $registrationIDs[] = [
                                'fcm_id' => $ids['fcm_id'],
                                'platform' => $ids['platform']
                            ];
                        }
                    }
                }

                // Get web FCM IDs
                // $web_fcm_id = $this->db->table('users')
                //     ->select('web_fcm_id')
                //     ->where("web_fcm_id IS NOT NULL AND web_fcm_id != ''")
                //     ->whereIn('id', $to_send_id)
                //     ->get()
                //     ->getResultArray();

                $web_fcm_id = $this->db->table('users_fcm_ids')
                    ->select('fcm_id')
                    ->where('status', '1')
                    ->whereIn('user_id', $to_send_id)
                    ->whereIn('platform', ['web'])
                    ->get()
                    ->getResultArray();


                if (!empty($web_fcm_id)) {
                    foreach ($web_fcm_id as $ids) {
                        if ($ids['fcm_id'] != "") {
                            $web_registrationIDs[] = ['web_fcm_id' => $ids['fcm_id']];
                        }
                    }
                }

                break;

            case 'provider':
                if (isset($_POST['partner_id'])) {
                    $partner = fetch_details('partner_details', ['partner_id' => $_POST['partner_id'][0]]);
                } else {
                    $partner = fetch_details('partner_details');
                }
                $to_send_id = array_column($partner, 'partner_id');

                if (empty($to_send_id)) {
                    return ['registrationIDs' => [], 'web_registrationIDs' => []];
                }

                // $users_fcm = $this->db->table('users')
                //     ->select('fcm_id,platform')
                //     ->whereIn('id', $to_send_id)
                //     ->get()
                //     ->getResultArray();


                $users_fcm = $this->db->table('users_fcm_ids')
                    ->select('fcm_id,platform')
                    ->where('status', '1')
                    ->whereIn('user_id', $to_send_id)
                    ->whereIn('platform', ['android', 'ios'])
                    ->get()
                    ->getResultArray();



                foreach ($users_fcm as $ids) {
                    if ($ids['fcm_id'] != "") {
                        $registrationIDs[] = [
                            'fcm_id' => $ids['fcm_id'],
                            'platform' => $ids['platform']
                        ];
                    }
                }
                break;

            case 'customer':
                // Get customer IDs
                $user_record = $this->db->table('users u')
                    ->select('u.*,ug.group_id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', "2")
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->getResultArray();

                $to_send_id = array_column($user_record, 'id');

                // Get mobile FCM IDs
                // $users_fcm = $this->db->table('users')
                //     ->select('fcm_id,platform')
                //     ->whereIn('id', $to_send_id)
                //     ->get()
                //     ->getResultArray();

                $users_fcm = $this->db->table('users_fcm_ids')
                    ->select('fcm_id,platform')
                    ->whereIn('user_id', $to_send_id)
                    ->whereIn('platform', ['android', 'ios'])
                    ->where('status', '1')
                    ->get()
                    ->getResultArray();

                foreach ($users_fcm as $ids) {
                    if ($ids['fcm_id'] != "") {
                        $registrationIDs[] = [
                            'fcm_id' => $ids['fcm_id'],
                            'platform' => $ids['platform']
                        ];
                    }
                }

                // Get web FCM IDs
                // $web_fcm_id = $this->db->table('users')
                //     ->select('web_fcm_id')
                //     ->where("web_fcm_id IS NOT NULL AND web_fcm_id != ''")
                //     ->whereIn('id', $to_send_id)
                //     ->get()
                //     ->getResultArray();

                $web_fcm_id = $this->db->table('users_fcm_ids')
                    ->select('fcm_id')
                    ->whereIn('user_id', $to_send_id)
                    ->whereIn('platform', ['web'])
                    ->where('status', '1')
                    ->get()
                    ->getResultArray();

                foreach ($web_fcm_id as $ids) {
                    if ($ids['fcm_id'] != "") {
                        $web_registrationIDs[] = ['web_fcm_id' => $ids['fcm_id']];
                    }
                }
                break;
        }

        return [
            'registrationIDs' => $registrationIDs,
            'web_registrationIDs' => $web_registrationIDs
        ];
    }

    /**
     * Extract user IDs from recipients based on user type and notification type
     * 
     * This method extracts user IDs that can be passed to NotificationService
     * to identify which users should receive the notification.
     * 
     * @param string $user_type The type of users to notify (all_users, specific_user, provider, customer)
     * @param string $type The notification type (general, provider, category, url)
     * @return array Array of user IDs
     */
    private function extractUserIdsFromRecipients($user_type, $type)
    {
        $userIds = [];

        switch ($user_type) {
            case 'specific_user':
                // Get user IDs from POST data
                if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
                    $userIds = $_POST['user_ids'];
                    // Ensure all values are converted to integers and filter out empty values
                    $userIds = array_map('intval', $userIds);
                    $userIds = array_filter($userIds, function ($id) {
                        return $id > 0;
                    });
                    $userIds = array_values($userIds); // Re-index array
                    log_message('info', '[ADMIN_NOTIFICATION] Extracted specific user IDs from POST: ' . json_encode($userIds));
                } else {
                    log_message('warning', '[ADMIN_NOTIFICATION] user_ids not found in POST or not an array. POST data: ' . json_encode($_POST));
                }
                break;

            case 'provider':
                // Get partner IDs
                if (isset($_POST['partner_id'])) {
                    if (is_array($_POST['partner_id'])) {
                        $userIds = $_POST['partner_id'];
                    } else {
                        $userIds = [$_POST['partner_id']];
                    }
                } else {
                    // If no specific partner, get all approved partners
                    $partners = fetch_details('partner_details', ['is_approved' => '1'], ['partner_id']);
                    $userIds = array_column($partners, 'partner_id');
                }
                break;

            case 'customer':
                // Get all customer user IDs (group_id = 2)
                $user_record = $this->db->table('users u')
                    ->select('u.id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', "2")
                    ->get()
                    ->getResultArray();
                $userIds = array_column($user_record, 'id');
                break;

            case 'all_users':
                // For all users, we'll use send_to_all option instead
                // Return empty array to trigger send_to_all
                $userIds = [];
                break;

            default:
                $userIds = [];
                break;
        }

        return $userIds;
    }

    /**
     * Get all parent category slugs recursively
     * 
     * Traverses up the category hierarchy to collect all parent category slugs.
     * Returns slugs in order from top-most parent to immediate parent (e.g., ['home-services', 'carpet-cleaning']).
     * 
     * @param int $parentId Starting parent category ID
     * @return array Array of parent category slugs
     */
    private function getAllParentCategorySlugs(int $parentId): array
    {
        $parentSlugs = [];
        $currentParentId = $parentId;

        // Traverse up the category hierarchy until we reach root (parent_id is null/0)
        while (!empty($currentParentId) && $currentParentId != '0') {
            $parentCategory = $this->db->table('categories')
                ->select('slug, parent_id')
                ->where('id', $currentParentId)
                ->get()
                ->getResultArray();

            if (!empty($parentCategory) && !empty($parentCategory[0]['slug'])) {
                // Add slug to array (immediate parent first, will reverse later)
                $parentSlugs[] = $parentCategory[0]['slug'];
                // Move to next parent
                $currentParentId = $parentCategory[0]['parent_id'] ?? null;
            } else {
                // No more parents found, break the loop
                break;
            }
        }

        // Reverse array so top-most parent is first, then immediate parent
        // Use array_values to ensure proper indexing for JSON encoding as array (not object)
        // Example: ['home-services', 'carpet-cleaning'] for a subsubcategory
        return array_values(array_reverse($parentSlugs));
    }

    /**
     * Create FCM message based on notification type
     * 
     * For provider or category types, includes:
     * - Provider slug and default provider name (if provider is available)
     * - Category slug (if category is available)
     * - Parent category slugs (array ordered from top-most parent to immediate parent, if category is a subcategory)
     */
    private function createFcmMessage($type, $title, $message, $data, $ext, $image_name)
    {
        $path = "public/uploads/notification/";
        $fcmMsg = [
            'title' => $title,
            'body' => $message,
            'type' => $type,
            'type_id' => $data['type_id'],
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ];

        // Add image to FCM message if exists
        if ($ext != '') {
            $fcmMsg['image'] = base_url($path) . '/' . $image_name;
        }

        // Add type-specific data
        switch ($type) {
            case 'provider':
                // Get provider data including slug
                $provider_data = $this->db->table('partner_details')
                    ->select('partner_id, company_name, slug')
                    ->where('partner_id', $_POST['partner_id'])
                    ->get()
                    ->getResultArray();

                if (!empty($provider_data)) {
                    $fcmMsg['provider_id'] = $provider_data[0]['partner_id'];
                    $fcmMsg['provider_name'] = $provider_data[0]['company_name'];
                    $fcmMsg['provider_slug'] = $provider_data[0]['slug'] ?? '';

                    // Get default provider name from translated_partner_details
                    $defaultLanguage = get_default_language();
                    $translatedProvider = $this->db->table('translated_partner_details')
                        ->select('company_name')
                        ->where('partner_id', $provider_data[0]['partner_id'])
                        ->where('language_code', $defaultLanguage)
                        ->get()
                        ->getResultArray();

                    // Use translated name if available, otherwise use company_name
                    if (!empty($translatedProvider) && !empty($translatedProvider[0]['company_name'])) {
                        $fcmMsg['provider_name_default'] = $translatedProvider[0]['company_name'];
                    } else {
                        $fcmMsg['provider_name_default'] = $provider_data[0]['company_name'];
                    }
                }
                break;

            case 'category':
                // Get category data including slug and parent_id
                $category_data = $this->db->table('categories')
                    ->select('id, name, parent_id, slug')
                    ->where('id', $_POST['category_id'])
                    ->get()
                    ->getResultArray();

                if (!empty($category_data)) {
                    $fcmMsg['category_id'] = $data['type_id'];
                    $fcmMsg['parent_id'] = $category_data[0]['parent_id'];
                    $fcmMsg['category_name'] = $category_data[0]['name'];
                    $fcmMsg['category_slug'] = $category_data[0]['slug'] ?? '';

                    // If category is a subcategory (parent_id is not null/0), get all parent category slugs recursively
                    if (!empty($category_data[0]['parent_id']) && $category_data[0]['parent_id'] != '0') {
                        $parentSlugs = $this->getAllParentCategorySlugs($category_data[0]['parent_id']);
                        if (!empty($parentSlugs)) {
                            // Store as array ordered from top-most parent to immediate parent
                            // Example: ['home-services', 'carpet-cleaning'] for a subsubcategory
                            $fcmMsg['parent_category_slugs'] = $parentSlugs;
                        }
                    }
                }
                break;

            case 'url':
                $fcmMsg['url'] = $_POST['url'];
                break;
        }

        // For provider or category types, ensure we have both provider and category slugs if available
        // This handles cases where provider notifications might have category context or vice versa
        if ($type === 'provider' || $type === 'category') {
            // If provider type and category_id is available in POST, get category slug
            if ($type === 'provider' && isset($_POST['category_id']) && !empty($_POST['category_id'])) {
                $category_data = $this->db->table('categories')
                    ->select('id, slug, parent_id')
                    ->where('id', $_POST['category_id'])
                    ->get()
                    ->getResultArray();

                if (!empty($category_data)) {
                    $fcmMsg['category_slug'] = $category_data[0]['slug'] ?? '';

                    // If category is a subcategory, get all parent category slugs recursively
                    if (!empty($category_data[0]['parent_id']) && $category_data[0]['parent_id'] != '0') {
                        $parentSlugs = $this->getAllParentCategorySlugs($category_data[0]['parent_id']);
                        if (!empty($parentSlugs)) {
                            // Store as array ordered from top-most parent to immediate parent
                            // Example: ['home-services', 'carpet-cleaning'] for a subsubcategory
                            $fcmMsg['parent_category_slugs'] = $parentSlugs;
                        }
                    }
                }
            }

            // If category type and partner_id is available in POST, get provider slug and default name
            if ($type === 'category' && isset($_POST['partner_id']) && !empty($_POST['partner_id'])) {
                $provider_data = $this->db->table('partner_details')
                    ->select('partner_id, company_name, slug')
                    ->where('partner_id', $_POST['partner_id'])
                    ->get()
                    ->getResultArray();

                if (!empty($provider_data)) {
                    $fcmMsg['provider_id'] = $provider_data[0]['partner_id'];
                    $fcmMsg['provider_slug'] = $provider_data[0]['slug'] ?? '';

                    // Get default provider name from translated_partner_details
                    $defaultLanguage = get_default_language();
                    $translatedProvider = $this->db->table('translated_partner_details')
                        ->select('company_name')
                        ->where('partner_id', $provider_data[0]['partner_id'])
                        ->where('language_code', $defaultLanguage)
                        ->get()
                        ->getResultArray();

                    // Use translated name if available, otherwise use company_name
                    if (!empty($translatedProvider) && !empty($translatedProvider[0]['company_name'])) {
                        $fcmMsg['provider_name_default'] = $translatedProvider[0]['company_name'];
                    } else {
                        $fcmMsg['provider_name_default'] = $provider_data[0]['company_name'];
                    }
                }
            }
        }

        return $fcmMsg;
    }

    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Filter notifications to only show those sent by admin
            // This ensures the admin panel only displays notifications with event_type = 'sent_by_admin'
            $where = ['event_type' => 'sent_by_admin'];

            $data = $this->notification->list(false, $search, $limit, $offset, $sort, $order, $where);
            return $data;
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Admin notifications "inbox" (web panel).
     *
     * Why:
     * - Providers already have a bell dropdown + full notifications page.
     * - Admin users also need the same UX to view system-generated notifications (e.g. chat/order events)
     *   directly inside the admin panel.
     *
     * Important:
     * - We reuse `Notification_model` audience methods with `audienceTarget = all_users`.
     *   This keeps the "visibility rules" centralized in the model:
     *   - system_generated for the logged-in admin user_id
     *   - broadcasts to all_users
     *   - specific_user JSON contains admin id
     *
     * This intentionally does NOT show provider-only or customer-only broadcasts.
     */
    public function notifications()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        setPageInfo(
            $this->data,
            labels('notifications', 'Notifications') . ' | ' . labels('admin_panel', 'Admin Panel'),
            'notifications'
        );

        return view('backend/admin/template', $this->data);
    }

    /**
     * Latest 5 notifications for the admin navbar bell dropdown.
     *
     * Response shape mirrors partner dropdown response so the JS stays simple.
     */
    public function recent()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'data' => [],
                ]);
            }

            /**
             * IMPORTANT (Admin panel correctness):
             * We must fetch ONLY rows that are stored for this admin user in `notifications.user_id`.
             *
             * Why:
             * - Relying on `target = all_users` will show "broadcast" rows even if nothing was
             *   stored for this specific admin. This is exactly the bug being reported.
             *
             * Also:
             * - For admin-sent notifications (`event_type = sent_by_admin`) user_id can be a JSON
             *   array of multiple recipients, so the model handles JSON_CONTAINS safely.
             */
            $rows = $this->notification->fetchForUserDropdownLite($this->userId, 5, 0);
            $unreadCount = $this->notification->countUnreadForUser($this->userId);

            $templateService = new NotificationTemplateService();
            $out = [];
            foreach ($rows as $n) {
                if (($n['event_type'] ?? '') === 'system_generated') {
                    $n = $this->renderSystemGenerated($n, $this->userId, $templateService);
                }

                $ts = $n['date_sent'] ?? $n['created_at'] ?? null;
                $out[] = [
                    'id' => $n['id'] ?? null,
                    'title' => $n['title'] ?? '',
                    'message' => $n['message'] ?? '',
                    'url' => $n['url'] ?? '',
                    // Needed for redirect rules in `notification_redirects.js`
                    // (admin-sent notifications use event_type + notification_type).
                    // For system_generated, redirect uses template key from event_key.
                    'event_type' => $n['event_type'] ?? '',
                    'event_key' => $n['type'] ?? '',
                    'notification_type' => $n['notification_type'] ?? '',
                    'is_readed' => $n['is_readed'] ?? 0,
                    /**
                     * Redirect context (admin panel)
                     * -----------------------------
                     * Security:
                     * - Never expose the full raw `context_data` blob.
                     * - Send only the minimal identifiers needed for redirect decisions.
                     */
                    'context_data' => $this->extractRedirectContext($n['context_data'] ?? null),
                    'duration' => $this->duration($ts),
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('notifications_fetched_successfully', 'Notifications fetched successfully'),
                'data' => $out,
                'unread_count' => (int) $unreadCount,
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - recent()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Mark all admin-visible notifications as read.
     *
     * Used by the bell dropdown "Mark all as read" action.
     */
    public function mark_all_read()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            // Strict: mark read only for notifications that belong to this admin user_id column.
            $ok = $this->notification->markAllAsReadForUser($this->userId);
            $unreadCount = $this->notification->countUnreadForUser($this->userId);

            return $this->response->setJSON([
                'error' => $ok ? false : true,
                'message' => $ok
                    ? labels('notifications_marked_as_read', 'Notifications marked as read')
                    : labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'unread_count' => (int) $unreadCount,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - mark_all_read()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Mark a single admin-visible notification as read (web panel).
     *
     * Used by:
     * - Admin navbar bell dropdown item click (before redirect).
     *
     * Security:
     * - Only allow marking notifications that are visible to this admin user
     *   via the `notifications.user_id` column (scalar or JSON array).
     */
    public function mark_read()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $id = (int) ($this->request->getPost('notification_id') ?? 0);
            if ($id <= 0) {
                return $this->response->setStatusCode(400)->setJSON([
                    'error' => true,
                    'message' => labels('invalid_request', 'Invalid request'),
                    'unread_count' => (int) $this->notification->countUnreadForUser($this->userId),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            // Safety: only allow marking notifications that belong to this admin user.
            if (!$this->notification->isVisibleToUser($this->userId, $id)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => true,
                    'message' => labels('not_found', 'Not found'),
                    'unread_count' => (int) $this->notification->countUnreadForUser($this->userId),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $ok = $this->notification->markAsReadById($id);
            $unreadCount = $this->notification->countUnreadForUser($this->userId);

            return $this->response->setJSON([
                'error' => $ok ? false : true,
                'message' => $ok
                    ? labels('notifications_marked_as_read', 'Notifications marked as read')
                    : labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'unread_count' => (int) $unreadCount,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - mark_read()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Validate that a notification redirect target still exists before redirecting.
     *
     * Used by notification_redirects.js so we don't redirect to order/provider pages
     * when the related user has been deleted from the users table.
     *
     * Expects POST: target_type = 'order' | 'provider', target_id = <id>
     * Returns: { exists: true|false, ... }
     */
    public function validate_redirect_target()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'exists' => false,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            helper('function');

            $targetType = strtolower(trim((string) ($this->request->getPost('target_type') ?? $this->request->getGet('target_type') ?? '')));
            $targetId = (int) ($this->request->getPost('target_id') ?? $this->request->getGet('target_id') ?? 0);

            if ($targetId <= 0 || !in_array($targetType, ['order', 'provider'], true)) {
                return $this->response->setJSON([
                    'error' => true,
                    'exists' => false,
                    'message' => labels('invalid_request', 'Invalid request'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $exists = false;

            if ($targetType === 'provider') {
                // Provider ID is a user id; check that user still exists.
                $exists = exists(['id' => $targetId], 'users') && exists(['partner_id' => $targetId], 'partner_details');
            } elseif ($targetType === 'order') {
                // Order must exist and both customer and provider (users) must still exist.
                $order = fetch_details('orders', ['id' => $targetId]);
                if (!empty($order) && !empty($order[0])) {
                    $userId = (int) ($order[0]['user_id'] ?? 0);
                    $partnerId = (int) ($order[0]['partner_id'] ?? 0);
                    $exists = ($userId > 0 && $partnerId > 0
                        && exists(['id' => $userId], 'users')
                        && exists(['id' => $partnerId], 'users'));
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'exists' => $exists,
                'message' => $exists ? labels('ok', 'OK') : labels('user_no_longer_exists', 'User no longer exists'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - validate_redirect_target()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'exists' => false,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Ultra-lean endpoint for the admin notifications bootstrap-table.
     *
     * Mirrors partner table endpoint but uses audienceTarget = all_users (admin panel).
     */
    public function table()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setStatusCode(401)->setJSON([
                    'total' => 0,
                    'rows' => [],
                ]);
            }

            $limit = max((int) ($this->request->getGet('limit') ?? 10), 1);
            $offset = max((int) ($this->request->getGet('offset') ?? 0), 0);

            $search = (string) ($this->request->getGet('search') ?? $this->request->getGet('searchText') ?? '');
            $search = trim($search);

            $templateEventKeys = [];
            if ($search !== '') {
                $templateEventKeys = $this->getTemplateEventKeysForSearch($search);
            }

            if ($search !== '') {
                $total = $this->notification->countForUserTableSearch($this->userId, $search, $templateEventKeys);
                $rows = $this->notification->fetchForUserTableLiteSearch($this->userId, $limit, $offset, $search, $templateEventKeys);
            } else {
                $total = $this->notification->countForUser($this->userId);
                $rows = $this->notification->fetchForUserTableLite($this->userId, $limit, $offset);
            }

            $templateService = new NotificationTemplateService();
            $out = [];
            foreach ($rows as $n) {
                if (($n['event_type'] ?? '') === 'system_generated') {
                    $n = $this->renderSystemGenerated($n, $this->userId, $templateService);
                }

                $ts = $n['date_sent'] ?? $n['created_at'] ?? null;
                $out[] = [
                    'id' => $n['id'] ?? null,
                    'title' => $n['title'] ?? '',
                    'message' => $n['message'] ?? '',
                    'time_ago' => $this->duration($ts),
                    'event_type' => $n['event_type'] ?? '',
                    'event_key' => $n['type'] ?? '',
                    'notification_type' => $n['notification_type'] ?? '',
                    'url' => $n['url'] ?? '',
                    'context_data' => $this->extractRedirectContext($n['context_data'] ?? null),
                ];
            }

            return $this->response->setJSON([
                'total' => (int) $total,
                'rows' => $out,
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - table()');
            return $this->response->setStatusCode(500)->setJSON([
                'total' => 0,
                'rows' => [],
            ]);
        }
    }

    /**
     * Search helper for template-driven notifications.
     *
     * We search template title/body (base + translated) and return matching event_key list.
     * This allows the notifications query to match system_generated notifications by `type`.
     *
     * NOTE:
     * Templates are small compared to notifications, so doing this extra query is cheap.
     */
    private function getTemplateEventKeysForSearch(string $search): array
    {
        $q = trim($search);
        if ($q === '') {
            return [];
        }

        helper('function');
        $currentLang = function_exists('get_current_language') ? get_current_language() : 'en';
        $defaultLang = function_exists('get_default_language') ? get_default_language() : 'en';

        $db = \Config\Database::connect();
        $builder = $db->table('notification_templates nt');

        // Left join translations for current/default language.
        // We put the language restriction in the join condition to keep it a true LEFT JOIN.
        $join = 't.template_id = nt.id AND t.language_code IN (' . $db->escape($currentLang) . ', ' . $db->escape($defaultLang) . ')';
        $builder->join('translated_notification_templates t', $join, 'left', false);

        $builder->select('DISTINCT nt.event_key', false);
        $builder->groupStart()
            ->like('nt.title', $q)
            ->orLike('nt.body', $q)
            ->orLike('t.title', $q)
            ->orLike('t.body', $q)
            ->groupEnd();

        // Safety: templates are small; still cap to keep things predictable.
        $rows = $builder->limit(200)->get()->getResultArray();

        $keys = [];
        foreach ($rows as $r) {
            if (!empty($r['event_key'])) {
                $keys[] = $r['event_key'];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Render system-generated notification title/message using templates.
     *
     * This mirrors logic already implemented in:
     * - app/Controllers/api/NotificationApiController.php
     * - app/Controllers/partner/Notifications.php
     */
    private function renderSystemGenerated(array $n, $userId, NotificationTemplateService $templateService): array
    {
        $eventKey = $n['type'] ?? '';
        $context = json_decode($n['context_data'] ?? '{}', true) ?: [];
        $context['user_id'] = $context['user_id'] ?? $userId;

        if (!$eventKey) {
            return $n;
        }

        $tpl = $templateService->getFcmTemplate($eventKey);
        if (!$tpl) {
            $n['event_key'] = $eventKey;
            return $n;
        }

        $vars = $templateService->extractVariablesFromContext($eventKey, $context);

        $n['title'] = $templateService->replaceVariables($tpl['title'] ?? '', $vars);
        $n['message'] = $templateService->replaceVariables($tpl['body'] ?? '', $vars);
        $n['event_key'] = $eventKey;

        return $n;
    }

    /**
     * Human time diff (matches Flutter app logic).
     *
     * DB returns all timestamps normalized to UTC via CONVERT_TZ.
     * No explicit timezone conversion needed—use app timezone (which is UTC).
     */
    private function duration(?string $date): string
    {
        if (!$date)
            return '';

        try {
            $utcDate = new Time($date);
            $now = Time::now();

            $diff = $now->getTimestamp() - $utcDate->getTimestamp();
            $isPast = $diff >= 0;
            $elapsed = abs($diff);

            $seconds = $elapsed;
            $minutes = (int) floor($elapsed / 60);
            $hours = (int) floor($elapsed / 3600);
            $days = (int) floor($elapsed / 86400);

            if ($seconds < 45) {
                $msg = labels('less_than_a_minute', 'less than a minute');
            } elseif ($seconds < 90) {
                $msg = labels('about_a_minute', 'about a minute');
            } elseif ($minutes < 45) {
                $msg = $this->formatPlural($minutes, labels('minute', 'minute'));
            } elseif ($minutes < 90) {
                $msg = labels('about_an_hour', 'about an hour');
            } elseif ($hours < 24) {
                $msg = $this->formatPlural($hours, labels('hour', 'hour'));
            } elseif ($hours < 48) {
                $msg = labels('a_day', 'a day');
            } elseif ($days < 7) {
                $msg = $this->formatPlural($days, labels('day', 'day'));
            } elseif ($days < 14) {
                $msg = labels('about_a_week', 'about a week');
            } elseif ($days < 30) {
                $weeks = (int) floor($days / 7);
                $msg = $this->formatPlural($weeks, labels('week', 'week'));
            } elseif ($days < 60) {
                $msg = labels('about_a_month', 'about a month');
            } elseif ($days < 365) {
                $months = (int) floor($days / 30);
                $msg = $this->formatPlural($months, labels('month', 'month'));
            } elseif ($days < 730) {
                $msg = labels('about_a_year', 'about a year');
            } else {
                $years = (int) floor($days / 365);
                $msg = $this->formatPlural($years, labels('year', 'year'));
            }

            return $isPast ? $msg . ' ' . labels('ago', 'ago') : labels('in', 'in') . ' ' . $msg;
        } catch (\Exception $e) {
            return '';
        }
    }

    private function formatPlural(int $value, string $unit): string
    {
        return $value === 1 ? "1 $unit" : "$value {$unit}s";
    }

    /**
     * Extract only the redirect-relevant keys from `notifications.context_data`.
     *
     * IMPORTANT:
     * - Keep this small and explicit.
     * - Do NOT leak additional context into the browser unless needed.
     */
    private function extractRedirectContext(?string $contextDataJson): array
    {
        $ctx = json_decode($contextDataJson ?? '', true);
        if (!is_array($ctx)) {
            $ctx = [];
        }

        // Keep keys aligned with `public/backend/assets/js/notification_redirects.js`
        $allowedKeys = ['order_id', 'booking_id', 'subscription_id', 'provider_id', 'service_id', 'category_id'];
        $out = [];

        foreach ($allowedKeys as $k) {
            if (array_key_exists($k, $ctx) && $ctx[$k] !== null && $ctx[$k] !== '') {
                $out[$k] = $ctx[$k];
            }
        }

        return $out;
    }

    public function delete_notification()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }

            // Permission guard: wrap with helper so every controller can use the same pattern.
            $permissionCheck = enforce_permission('delete', 'send_notification', $this->userId);
            if ($permissionCheck !== true) {
                return $permissionCheck;
            }

            $id = $this->request->getPost('user_id');
            $icons = fetch_details('notifications', ['id' => $id]);
            $image = ($icons[0] != '') ? $icons[0]['image'] : '';
            $db = \Config\Database::connect();
            $builder = $db->table('notifications');
            if ($builder->delete(['id' => $id])) {
                $path = ($image != "") ? "public/uploads/notification/" . $image : '';
                if ($image != "") {
                    unlink($path);
                }
                return successResponse("Notification deleted successfully", false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occured"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Notification.php - delete_notification()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Queue Progress Tray — returns active and recently completed notification batches.
     */
    public function queue_status()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => true,
                'message' => 'Unauthorized',
            ]);
        }

        try {
            $db = \Config\Database::connect();

            // Auto-complete rows where all jobs finished but the job itself
            // failed to update the status (e.g. column-missing errors before migration).
            $db->query(
                "UPDATE `notification_dispatches`
                 SET `status` = 'completed', `completed_at` = UTC_TIMESTAMP()
                 WHERE `status` IN ('queued', 'processing')
                   AND `total_jobs` > 0
                   AND `completed_jobs` >= `total_jobs`"
            );

            // Also expire batches stuck in processing with no job activity for
            // more than 15 minutes — guards against orphaned rows. Back-date
            // completed_at so they fall outside the 2-minute newly_completed
            // window and don't fire spurious toasts.
            $db->query(
                "UPDATE `notification_dispatches`
                 SET `status` = 'completed', `completed_at` = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 MINUTE)
                 WHERE `status` IN ('queued', 'processing')
                   AND `created_at` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)"
            );

            $rows = $db->query(
                "SELECT * FROM `notification_dispatches`
                 WHERE `status` IN ('queued', 'processing')
                    OR (`status` = 'completed' AND `completed_at` > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 SECOND))
                 ORDER BY `created_at` DESC
                 LIMIT 20"
            )->getResultArray();

            $batches = [];
            $newlyCompleted = [];

            foreach ($rows as $row) {
                // Cast integer fields for clean JSON output.
                $row['total_recipients'] = (int) $row['total_recipients'];
                $row['total_jobs'] = (int) $row['total_jobs'];
                $row['completed_jobs'] = (int) $row['completed_jobs'];
                $row['fcm_sent'] = (int) $row['fcm_sent'];
                $row['fcm_failed'] = (int) $row['fcm_failed'];
                $row['email_sent'] = (int) $row['email_sent'];
                $row['email_failed'] = (int) $row['email_failed'];
                $row['sms_sent'] = (int) $row['sms_sent'];
                $row['sms_failed'] = (int) $row['sms_failed'];

                if ($row['status'] === 'completed') {
                    $newlyCompleted[] = $row;
                } else {
                    $batches[] = $row;
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'batches' => $batches,
                'newly_completed' => $newlyCompleted,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_message('error', '[QUEUE_STATUS] ' . $th->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => 'Failed to fetch queue status',
            ]);
        }
    }
}
