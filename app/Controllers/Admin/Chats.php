<?php

namespace App\Controllers\Admin;

use App\Models\ChatModel;
use App\Models\Enquiries_model;
use App\Services\NotificationService;

class Chats extends Admin
{
    protected $db;
    protected ChatModel $chat;
    protected Enquiries_model $enquiry;

    protected $validation;
    protected $superadmin;
    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
        $this->chat = new ChatModel();
        $this->enquiry = new Enquiries_model();
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
        helper('api');
    }

    /**
     * Flatten PHP's $_FILES['attachment'] into a single normalized list,
     * regardless of whether one or multiple file inputs (image/file) posted.
     *
     * @param array $files Raw $_FILES['attachment']
     * @return array ['name'=>[], 'type'=>[], 'tmp_name'=>[], 'error'=>[], 'size'=>[]]
     */
    protected function normalizeChatAttachmentFilesArray(array $files): array
    {
        $normalized = [
            'name' => [],
            'type' => [],
            'tmp_name' => [],
            'error' => [],
            'size' => [],
        ];

        // If structure is missing, return empty.
        if (!isset($files['tmp_name'], $files['name'], $files['type'], $files['error'], $files['size'])) {
            return $normalized;
        }

        $pushOne = function ($name, $type, $tmp, $error, $size) use (&$normalized) {
            // Skip "no file" entries or empty names.
            if ((int) $error === UPLOAD_ERR_NO_FILE || empty($name)) {
                return;
            }
            $normalized['name'][] = $name;
            $normalized['type'][] = $type;
            $normalized['tmp_name'][] = $tmp;
            $normalized['error'][] = $error;
            $normalized['size'][] = $size;
        };

        // Common case: one dimension.
        if (!is_array($files['tmp_name'])) {
            $pushOne($files['name'], $files['type'], $files['tmp_name'], $files['error'], $files['size']);
            return $normalized;
        }

        // If 2D (multiple inputs each multiple), flatten.
        foreach ((array) $files['tmp_name'] as $i => $tmpNameEntry) {
            $nameEntry = $files['name'][$i] ?? null;
            $typeEntry = $files['type'][$i] ?? null;
            $errorEntry = $files['error'][$i] ?? null;
            $sizeEntry = $files['size'][$i] ?? null;

            if (is_array($tmpNameEntry)) {
                foreach ($tmpNameEntry as $j => $tmp) {
                    $pushOne(
                        $nameEntry[$j] ?? '',
                        $typeEntry[$j] ?? '',
                        $tmp,
                        $errorEntry[$j] ?? UPLOAD_ERR_NO_FILE,
                        $sizeEntry[$j] ?? 0
                    );
                }
            } else {
                $pushOne(
                    $nameEntry ?? '',
                    $typeEntry ?? '',
                    $tmpNameEntry,
                    $errorEntry ?? UPLOAD_ERR_NO_FILE,
                    $sizeEntry ?? 0
                );
            }
        }

        return $normalized;
    }

    /**
     * Server-side enforcement of chat attachment rules.
     *
     * Why:
     * - UI hides image/file pickers based on Admin chat settings, but the
     *   backend must enforce too. Prevents disallowed attachment types from
     *   being uploaded via a crafted request.
     *
     * @param array $normalizedFiles From normalizeChatAttachmentFilesArray()
     * @return array ['ok' => bool, 'message' => string]
     */
    protected function validateChatAttachments(array $normalizedFiles): array
    {
        $settings = get_settings('general_settings', true);

        $enableChatImageUpload = !empty($settings['enable_chat_image_upload']) ? (int) $settings['enable_chat_image_upload'] : 0;
        $enableChatFileUpload = !empty($settings['enable_chat_file_upload']) ? (int) $settings['enable_chat_file_upload'] : 0;

        $maxFiles = (int) ($settings['maxFilesOrImagesInOneMessage'] ?? 10);
        $maxBytes = (int) ($settings['maxFileSizeInBytesCanBeSent'] ?? 20000000);

        $count = isset($normalizedFiles['name']) && is_array($normalizedFiles['name']) ? count($normalizedFiles['name']) : 0;

        // Nothing uploaded => valid.
        if ($count === 0) {
            return ['ok' => true, 'message' => ''];
        }

        // If both are disabled, no attachments are allowed.
        if ($enableChatImageUpload !== 1 && $enableChatFileUpload !== 1) {
            return ['ok' => false, 'message' => labels('attachments_are_disabled', 'Attachments are disabled')];
        }

        if ($count > $maxFiles) {
            return [
                'ok' => false,
                'message' => labels('note_max_file_or_image_allowed_in_one_message', 'Note: Maximum File or image allowed in one message') . ' ' . $maxFiles
            ];
        }

        for ($i = 0; $i < $count; $i++) {
            $error = (int) ($normalizedFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            $size = (int) ($normalizedFiles['size'][$i] ?? 0);
            $tmp = $normalizedFiles['tmp_name'][$i] ?? '';
            $clientMime = $normalizedFiles['type'][$i] ?? '';

            if ($error !== UPLOAD_ERR_OK) {
                return ['ok' => false, 'message' => labels('something_went_wrong', 'Something went wrong')];
            }

            if ($size > $maxBytes) {
                return ['ok' => false, 'message' => labels('file_size_exceeds_the_maximum_limit_of', 'File size exceeds the maximum limit of')];
            }

            // Detect "is image" based on real mime where possible.
            $realMime = (!empty($tmp) && is_file($tmp)) ? @mime_content_type($tmp) : '';
            $mime = !empty($realMime) ? $realMime : $clientMime;
            $isImage = (is_string($mime) && strpos($mime, 'image/') === 0);

            // Enforce admin toggles.
            if ($isImage && $enableChatImageUpload !== 1) {
                return ['ok' => false, 'message' => labels('enable_chat_image_upload', 'Enable Chat Image Upload') . ' ' . labels('disable', 'Disable')];
            }
            if (!$isImage && $enableChatFileUpload !== 1) {
                return ['ok' => false, 'message' => labels('enable_chat_file_upload', 'Enable Chat File Upload') . ' ' . labels('disable', 'Disable')];
            }

            // Extension validation.
            $fileName = $normalizedFiles['name'][$i] ?? '';
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($isImage) {
                $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
                if (!in_array($extension, $allowedImageExtensions)) {
                    return ['ok' => false, 'message' => labels('only_image_files_are_allowed', 'Only image files are allowed') . " ($extension)"];
                }
            } else {
                $allowedFileExtensions = [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                    'odt', 'ods', 'odp', 'rtf', 'txt',
                    'zip', 'rar', '7z', 'tar', 'gz',
                    'csv', 'json', 'xml',
                    'mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm', 'm4v', '3gp',
                ];
                if (!in_array($extension, $allowedFileExtensions)) {
                    return ['ok' => false, 'message' => labels('this_file_type_is_not_allowed', 'This file type is not allowed') . " ($extension)"];
                }
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    public function index()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('chat', 'Chat') . ' | ' . labels('admin_panel', 'Admin Panel'), 'chat');
                $fileService = service('fileService');
                $db = \Config\Database::connect();
                $builder = $db->table('users u');
                $builder->select("u.*,u.image as profile_image, MAX(c.created_at) AS last_chat_date,
                        (SELECT COUNT(*) FROM chats uc WHERE uc.sender_id = u.id AND uc.sender_type = 2 AND uc.receiver_type = 0 AND uc.booking_id IS NULL AND uc.is_read = 0) AS unread_count")
                    ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 2)
                     OR (c.sender_id = u.id AND c.receiver_type = 0)
                         OR (c.receiver_id = u.id AND c.receiver_type = 0)
                     OR (c.receiver_id = u.id AND c.receiver_type = 2) ")
                    ->where('c.booking_id', NULL)
                    ->where('c.receiver_type', 0)->orwhere('c.receiver_type', 2)
                    ->groupBy('u.id')
                    ->orderBy('unread_count', 'DESC')
                    ->orderBy('last_chat_date', 'DESC');
                $customers_with_chats = $builder->get()->getResultArray();
                foreach ($customers_with_chats as $key => $row) {
                    $customers_with_chats[$key]['profile_image'] = $fileService->url($row['profile_image'] ?? '', 'profile');
                }



                foreach ($customers_with_chats as $key => $customer) {
                    $check_user = fetch_details('users_groups', ['user_id' => $customer['id'], 'group_id' => 2]);

                    if (empty($check_user)) {
                        unset($customers_with_chats[$key]);
                    }
                }
                $this->data['customers'] = $customers_with_chats;



                $builder = $db->table('users u');
                $builder->select("u.*,pd.company_name as username, u.image as profile_image, MAX(c.created_at) AS last_chat_date,
                        (SELECT COUNT(*) FROM chats uc WHERE uc.sender_id = u.id AND uc.sender_type = 1 AND uc.receiver_type = 0 AND uc.is_read = 0) AS unread_count")
                    ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 1 AND c.receiver_type = 0) OR (c.receiver_id = u.id AND c.receiver_type = 1 AND c.sender_type = 0)")
                    ->join('partner_details pd', 'pd.partner_id = u.id')
                    ->groupBy(['u.id', 'pd.company_name'])
                    ->orderBy('unread_count', 'DESC')
                    ->orderBy('last_chat_date', 'DESC');
                $provider_with_chats = $builder->get()->getResultArray();
                foreach ($provider_with_chats as $key => $row) {
                    $provider_with_chats[$key]['profile_image'] = $fileService->url($row['profile_image'] ?? '', 'profile');
                }
                $this->data['providers'] = $provider_with_chats;
                $this->data['current_user_id'] = $this->userId;
                $chat_settings = get_settings('general_settings', true);
                $this->data['maxFilesOrImagesInOneMessage'] = $chat_settings['maxFilesOrImagesInOneMessage'] ?? 10;
                $this->data['maxFileSizeInBytesCanBeSent'] = $chat_settings['maxFileSizeInBytesCanBeSent'] ?? 20000000;
                $this->data['maxCharactersInATextMessage'] = $chat_settings['maxCharactersInATextMessage'] ?? 500;
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' -->  app/Controllers/admin/Chats.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function store_chat()
    {
        try {
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            $sender_id = $this->userId;
            $receiver_id = isset($_POST['receiver_id']) ? $_POST['receiver_id'] : '';
            $user_type_for_send_message = $_POST['user_type_for_send_message'];
            $attachment_image = null;
            $is_file = false;
            if (!empty($_FILES['attachment']['name'])) {
                $normalized = $this->normalizeChatAttachmentFilesArray($_FILES['attachment']);
                $validation = $this->validateChatAttachments($normalized);
                if (!($validation['ok'] ?? false)) {
                    return ErrorResponse($validation['message'] ?? labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                if (!empty($normalized['name'])) {
                    $attachment_image = $_FILES['attachment'];
                    $is_file = true;
                }
            }
            if ($message === '' && !$is_file) {
                return ErrorResponse(labels('message_is_required', 'Message is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            if ($user_type_for_send_message == "provider") {
                $data['receiver_type'] = 1;
                $receiver_type = 1;

                $enquiry = fetch_details('enquiries', ['customer_id' => null, 'userType' => 1, 'booking_id' => NULL, 'provider_id' => $receiver_id]);
                if (empty($enquiry[0])) {
                    $user = fetch_details('users', ['id' => $sender_id], ['username'])[0];
                    $data['title'] =  $user['username'] . '_query';
                    $data['status'] =  1;
                    $data['userType'] =  1;
                    $data['customer_id'] = null;
                    $data['provider_id'] = $receiver_id;
                    $data['date'] =  now();
                    $store = insert_details($data, 'enquiries');
                    $e_id = $store['id'];
                } else {
                    $e_id = $enquiry[0]['id'];
                }
            } elseif ($user_type_for_send_message == "customer") {

                $data['receiver_type'] = 2;
                $receiver_type = 2;
                $enquiry = fetch_details('enquiries', ['customer_id' => $receiver_id, 'userType' => 2, 'booking_id' => NULL, 'provider_id' => NULL]);
                if (empty($enquiry[0])) {
                    $customer = fetch_details('users', ['id' => $receiver_id], ['username'])[0];
                    $data['title'] =  $customer['username'] . '_query';
                    $data['status'] =  1;
                    $data['userType'] =  2;
                    $data['customer_id'] = $receiver_id;
                    $data['provider_id'] = NULL;
                    $data['date'] =  now();
                    $store = insert_details($data, 'enquiries');
                    $e_id = $store['id'];
                } else {
                    $e_id = $enquiry[0]['id'];
                }
            }
            $data = insert_chat_message_for_chat($sender_id, $receiver_id, $message, $e_id, 0, $receiver_type, date('Y-m-d H:i:s'), $is_file, $attachment_image);

            $last_date = getLastMessageDateFromChat($e_id);
            if ($data) {
                if (!empty($data)) {
                    // Get booking_id from the data if available
                    $booking_id = isset($data['booking_id']) ? $data['booking_id'] : null;

                    // Determine provider_id based on receiver type
                    // If receiver is provider (type 1), then receiver_id is the provider_id
                    // If sender is provider (type 1), then sender_id is the provider_id
                    $provider_id_for_details = null;
                    if ($receiver_type == 1) {
                        $provider_id_for_details = $receiver_id;
                    } elseif (isset($data['sender_type']) && $data['sender_type'] == 1) {
                        $provider_id_for_details = $sender_id;
                    }

                    if ($user_type_for_send_message == "provider") {
                        $new_data = getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $data['id'], $last_date, 'admin');

                        // Enrich chat response with booking/provider info so apps can render context instantly
                        $chatExtras = build_chat_message_details(
                            $provider_id_for_details ? (int) $provider_id_for_details : null,
                            $booking_id ? (int) $booking_id : null,
                            $receiver_type !== null ? (int) $receiver_type : null,
                            (int) $sender_id
                        );
                        $new_data = array_merge($new_data ?? [], $chatExtras);
                        $platforms = ['android', 'ios', 'provider_panel'];
                    }
                    else if ($user_type_for_send_message == "customer") {
                        $new_data = getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $data['id'], $last_date, 'admin');

                        // Enrich chat response with booking/provider info so apps can render context instantly
                        $chatExtras = build_chat_message_details(
                            $provider_id_for_details ? (int)$provider_id_for_details : null,
                            $booking_id ? (int)$booking_id : null,
                            $receiver_type !== null ? (int)$receiver_type : null,
                            (int)$sender_id
                        );
                        $new_data = array_merge($new_data ?? [], $chatExtras);
                        $platforms = ['android', 'ios', 'web'];
                    }

                    // Send FCM notification using NotificationService (replaces legacy notification functions)
                    try {
                        // Foreground FCM handlers on provider/customer panels route messages
                        // by `viewer_type`. Without it, the panel receives the FCM but the
                        // onMessage handler ignores it and the chat UI never updates.
                        // Provider's admin-support handler matches `viewer_type == "admin"`.
                        $payloadForPush = $new_data ?? [];
                        $payloadForPush['viewer_type'] = 'admin';
                        $payloadForPush['last_message_date'] = $last_date;

                        $singleChatMessagePayload = json_encode($payloadForPush, JSON_UNESCAPED_SLASHES);
                        if ($singleChatMessagePayload === false) {
                            $singleChatMessagePayload = '{}';
                        }

                        $notificationContext = [
                            'chat_message' => $singleChatMessagePayload,
                            'chat_user' => null,
                        ];

                        if ($booking_id) {
                            $notificationContext['booking_id'] = (string)$booking_id;
                        }

                        $notificationService = new NotificationService();
                        $notificationService->send(
                            'new_message',
                        ['user_id' => (int)$receiver_id],
                            $notificationContext,
                        [
                            'channels' => ['fcm'],
                            'platforms' => $platforms,
                            'data' => $notificationContext,
                        ]
                        );
                    }
                    catch (\Throwable $notificationError) {
                        log_message('error', '[admin/Chats::store_chat] FCM notification error: ' . $notificationError->getMessage());
                    }
                    return successResponse(labels('chat_sent_successfully', "Chat sent successfully"), false, $data, [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse("Chat not found after saving", true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse("Please try again....", true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Chats.php - store_chat()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function getAllMessage()
    {
        try {
            $user_type = $_POST['user_type'];

            if ($user_type == 'provider') {
                $enquiry = fetch_details('enquiries', ['customer_id' => null, 'userType' => 1, 'booking_id' => NULL, 'provider_id' => $_POST['receiver_id']]);
                if (empty($enquiry[0])) {
                    $provider = fetch_details('users', ['id' => $_POST['receiver_id']], ['username'])[0];
                    $data['title'] =  $provider['username'] . '_query';
                    $data['status'] =  1;
                    $data['userType'] =  1;
                    $data['customer_id'] = NULL;
                    $data['provider_id'] = $_POST['receiver_id'];
                    $data['date'] =  now();
                    $store = insert_details($data, 'enquiries');
                    $e_id = $store['id'];
                } else {
                    $e_id = $enquiry[0]['id'];
                }
            } else if ($user_type == "customer") {
                $enquiry = fetch_details('enquiries', ['customer_id' =>  $_POST['receiver_id'], 'userType' => 2, 'booking_id' => NULL, 'provider_id' => NULL]);
                if (empty($enquiry[0])) {

                    $provider = fetch_details('users', ['id' => $_POST['receiver_id']], ['username'])[0];
                    $data['title'] =  $provider['username'] . '_query';
                    $data['status'] =  1;
                    $data['userType'] =  2;
                    $data['customer_id'] =  $_POST['receiver_id'];
                    $data['provider_id'] = NULL;
                    $data['date'] =  now();
                    $store = insert_details($data, 'enquiries');
                    $e_id = $store['id'];
                } else {
                    $e_id = $enquiry[0]['id'];
                }
            } else {
                $e_id = 0;
            }
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $receiver_id = $_POST['receiver_id'];
            $data = $this->chat->chat_list($limit, $offset, $sort, $order, $e_id, ['e_id' => $e_id], [], $search, false, $receiver_id, $user_type);
            return $data;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Chats.php - getAllMessage()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function mark_chat_as_read()
    {
        try {
            if (!($this->isLoggedIn && $this->userIsAdmin)) {
                return ErrorResponse(labels('unauthorized', 'Unauthorized'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $sender_id = $this->request->getPost('sender_id');
            $user_type = $this->request->getPost('user_type');
            if (empty($sender_id)) {
                return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $sender_type = ($user_type === 'provider') ? 1 : 2;
            $db = \Config\Database::connect();
            $db->table('chats')
                ->where('sender_id', $sender_id)
                ->where('sender_type', $sender_type)
                ->where('receiver_type', 0)
                ->where('is_read', 0)
                ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
            $affected = $db->affectedRows();
            return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), false, ['marked' => (int)$affected, 'sender_id' => (int)$sender_id], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Chats.php - mark_chat_as_read()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    /**
     * Returns the count of distinct users (customers + providers) with unread
     * messages to the admin. Drives the live sidebar Chat badge so it never
     * over-counts when one user sends multiple messages in a row.
     */
    public function unread_users_count()
    {
        try {
            if (!($this->isLoggedIn && $this->userIsAdmin)) {
                return ErrorResponse(labels('unauthorized', 'Unauthorized'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            // Filters mirror chat page render: sender must still exist, be in
            // the matching group (customer=2, provider=3), and customers must
            // have a usable phone (chat page skips phoneless rows). Without
            // these, deleted users / phoneless customers inflate the badge.
            $db = \Config\Database::connect();
            $unreadCustomers = $db->table('chats c')
                ->select('c.sender_id')
                ->join('users u', 'u.id = c.sender_id')
                ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 2')
                ->where('c.sender_type', 2)
                ->where('c.receiver_type', 0)
                ->where('c.booking_id', null)
                ->where('c.is_read', 0)
                ->where("TRIM(COALESCE(u.phone, '')) NOT IN ('', 'null', 'undefined')", null, false)
                ->groupBy('c.sender_id')
                ->get()->getResultArray();
            $unreadProviders = $db->table('chats c')
                ->select('c.sender_id')
                ->join('users u', 'u.id = c.sender_id')
                ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 3')
                ->where('c.sender_type', 1)
                ->where('c.receiver_type', 0)
                ->where('c.is_read', 0)
                ->groupBy('c.sender_id')
                ->get()->getResultArray();
            $count = count($unreadCustomers) + count($unreadProviders);
            return successResponse(
                labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                false,
                ['count' => (int) $count],
                [],
                200,
                csrf_token(),
                csrf_hash()
            );
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Chats.php - unread_users_count()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function get_customers()
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select("u.*,u.image as profile_image,ug.group_id,
                    (SELECT COUNT(*) FROM chats uc WHERE uc.sender_id = u.id AND uc.sender_type = 2 AND uc.receiver_type = 0 AND uc.booking_id IS NULL AND uc.is_read = 0) AS unread_count,
                    (SELECT MAX(lc.created_at) FROM chats lc WHERE (lc.sender_id = u.id OR lc.receiver_id = u.id) AND lc.booking_id IS NULL AND (lc.receiver_type = 0 OR lc.receiver_type = 2)) AS last_chat_date")
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 2);
            $search = $this->request->getPost('search');
            $users = [];
            if ($search != "") {
                $builder->groupStart()
                    ->like('u.id', $search)
                    ->orLike('u.username', $search)
                    ->orLike('u.email', $search)
                    ->orLike('u.phone', $search)
                    ->groupEnd();
                $users = $builder->get()->getResultArray();
            } else {
                $db = \Config\Database::connect();
                $builder = $db->table('chats c');
                $builder->distinct()->select('c.e_id');
                $customer_chat = $builder->get()->getResultArray();
                $e_ids = [];
                $users = [];
                foreach ($customer_chat as $row) {
                    $e_ids[] = $row['e_id'];
                }
                if (!empty($e_ids)) {
                    $customer_ids = fetch_chat_ids('enquiries', 'customer', [], ['customer_id'], '', 0, 'id', 'ASC', ['id' => $e_ids],);
                    $db = \Config\Database::connect();
                    $builder = $db->table('users u');
                    $builder->select("u.*,u.image as profile_image, ug.group_id,
                            (SELECT COUNT(*) FROM chats uc WHERE uc.sender_id = u.id AND uc.sender_type = 2 AND uc.receiver_type = 0 AND uc.booking_id IS NULL AND uc.is_read = 0) AS unread_count,
                            (SELECT MAX(lc.created_at) FROM chats lc WHERE (lc.sender_id = u.id OR lc.receiver_id = u.id) AND lc.booking_id IS NULL AND (lc.receiver_type = 0 OR lc.receiver_type = 2)) AS last_chat_date")
                        ->join('users_groups ug', 'ug.user_id = u.id')
                        ->where('ug.group_id', 2)
                        ->whereIn('u.id', $customer_ids)
                        ->orderBy('unread_count', 'DESC')
                        ->orderBy('last_chat_date', 'DESC');
                    $users = $builder->get()->getResultArray();
                }
            }
            $fileService = service('fileService');
            foreach ($users as $key => $row) {
                $users[$key]['profile_image'] = $fileService->url($row['profile_image'] ?? '', 'profile');
            }
            return json_encode($users);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Chats.php - get_customers()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function get_providers()
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select("u.id,ug.group_id,pd.company_name as username,pd.id as partner_id ,u.image as profile_image,u.phone,u.country_code,
                    (SELECT COUNT(*) FROM chats uc WHERE uc.sender_id = u.id AND uc.sender_type = 1 AND uc.receiver_type = 0 AND uc.is_read = 0) AS unread_count,
                    (SELECT MAX(lc.created_at) FROM chats lc WHERE (lc.sender_id = u.id AND lc.sender_type = 1 AND lc.receiver_type = 0) OR (lc.receiver_id = u.id AND lc.receiver_type = 1 AND lc.sender_type = 0)) AS last_chat_date")
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->where('ug.group_id', 3);
            $search = $this->request->getPost('search');
            $users = [];
            if ($search != "") {
                // Escape search term for use in LIKE queries
                $escapedSearch = $db->escapeLikeString($search);

                // Build translation search condition to search across ALL languages
                // This allows users to search for provider names in any language translation
                $translationSearchCondition = "EXISTS (
                    SELECT 1 FROM translated_partner_details tpd_search 
                    WHERE tpd_search.partner_id = pd.partner_id 
                    AND (
                        tpd_search.company_name LIKE '%{$escapedSearch}%' 
                        OR tpd_search.username LIKE '%{$escapedSearch}%'
                    )
                )";

                $builder->groupStart()
                    ->like('u.id', $search)
                    ->orLike('u.username', $search)
                    ->orLike('pd.company_name', $search)
                    ->orLike('u.email', $search)
                    ->orLike('u.phone', $search)
                    // Add condition to search in all language translations
                    ->orWhere($translationSearchCondition, null, false)
                    ->groupEnd();
                $users = $builder->get()->getResultArray();
                $fileService = service('fileService');
                foreach ($users as $key => $row) {
                    $users[$key]['profile_image'] = $fileService->url($row['profile_image'] ?? '', 'profile');
                }
            } else {
                $db = \Config\Database::connect();
                $builder = $db->table('chats c');
                $builder->distinct()->select('c.e_id');
                $provider_chat = $builder->where("(c.sender_id = u.id AND c.sender_type = 1) OR (c.receiver_id = u.id AND c.receiver_type = 1)")
                    ->join('users u', 'u.id = c.sender_id OR u.id = c.receiver_id')->get()->getResultArray();
                $e_ids = [];
                $users = [];
                foreach ($provider_chat as $row) {
                    $e_ids[] = $row['e_id'];
                }
                if (!empty($e_ids)) {
                    $provider_ids = fetch_chat_ids('enquiries', 'provider', [], ['provider_id'], '', 0, 'id', 'ASC', ['id' => $e_ids]);
                    $db = \Config\Database::connect();
                    $builder = $db->table('users u');
                    $builder->select("u.id,ug.group_id,pd.company_name as username,pd.id as partner_id ,u.image as profile_image ,u.phone,u.country_code,
                            (SELECT COUNT(*) FROM chats uc WHERE uc.sender_id = u.id AND uc.sender_type = 1 AND uc.receiver_type = 0 AND uc.is_read = 0) AS unread_count,
                            (SELECT MAX(lc.created_at) FROM chats lc WHERE (lc.sender_id = u.id AND lc.sender_type = 1 AND lc.receiver_type = 0) OR (lc.receiver_id = u.id AND lc.receiver_type = 1 AND lc.sender_type = 0)) AS last_chat_date")
                        ->join('users_groups ug', 'ug.user_id = u.id')
                        ->join('partner_details pd', 'pd.partner_id = u.id')
                        ->where('ug.group_id', 3)
                        ->whereIn('u.id', $provider_ids)
                        ->orderBy('unread_count', 'DESC')
                        ->orderBy('last_chat_date', 'DESC');
                    $users = $builder->get()->getResultArray();
                }
            }
            foreach ($users as $key => $row) {
                $users[$key]['profile_image'] = $fileService->url($row['profile_image'] ?? '', 'profile');
            }
            return json_encode($users);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Chats.php - get_providers()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
