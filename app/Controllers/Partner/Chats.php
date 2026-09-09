<?php

namespace App\Controllers\Partner;

use App\Models\BookingHandymenModel;
use App\Models\ChatModel;
use App\Models\ChatQuestions_model;
use App\Models\Enquiries_model;
use App\Services\NotificationService;
use App\Services\utility\FileService;

class Chats extends Partner
{
    protected $validationListTemplate = 'list';
    protected ChatModel $chat;
    protected Enquiries_model $enquiry;
    protected FileService $fileService;
    protected ChatModel $chatModel;
    protected \App\Models\ChatDeletionModel $chatDeletionModel;

    /**
     * Normalize chat attachment uploads into the same "single multiple input" shape.
     *
     * Why:
     * - The Provider chat UI can now provide separate pickers for images/files.
     * - The frontend sends everything as `attachment[]`, but different clients
     *   (or a crafted request) can still produce nested/odd `$_FILES` structures.
     * - Our upload pipeline (`insert_chat_message_for_chat`) expects a flat structure:
     *   $file['tmp_name'][N], $file['name'][N], $file['type'][N], ...
     *
     * This method flattens any nested arrays and removes empty entries.
     *
     * @param array $files The raw $_FILES['attachment'] array.
     * @return array Normalized array with keys: name,type,tmp_name,error,size
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
     * Server-side validation for chat attachments.
     *
     * Why:
     * - UI hides image/file pickers based on Admin settings, but backend must enforce too.
     * - Prevents uploading disallowed attachment types via crafted requests.
     *
     * @param array $normalizedFiles Normalized array from normalizeChatAttachmentFilesArray()
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

            // Extension validation
            $fileName = $normalizedFiles['name'][$i] ?? '';
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($isImage) {
                $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
                if (!in_array($extension, $allowedImageExtensions)) {
                    return ['ok' => false, 'message' => labels('only_image_files_are_allowed', 'Only image files are allowed') . " ($extension)"];
                }
            } else {
                $allowedFileExtensions = [
                    'pdf',
                    'doc',
                    'docx',
                    'xls',
                    'xlsx',
                    'ppt',
                    'pptx',
                    'odt',
                    'ods',
                    'odp',
                    'rtf',
                    'txt',
                    'zip',
                    'rar',
                    '7z',
                    'tar',
                    'gz',
                    'csv',
                    'json',
                    'xml',
                    'mp4',
                    'mov',
                    'avi',
                    'mkv',
                    'wmv',
                    'flv',
                    'webm',
                    'm4v',
                    '3gp',
                ];
                if (!in_array($extension, $allowedFileExtensions)) {
                    return ['ok' => false, 'message' => labels('this_file_type_is_not_allowed', 'This file type is not allowed') . " ($extension)"];
                }
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    public function __construct()
    {
        parent::__construct();
        $this->chat = new ChatModel();
        $this->enquiry = new Enquiries_model();
        $this->fileService = service('fileService');
        $this->chatModel = new ChatModel();
        $this->chatDeletionModel = new \App\Models\ChatDeletionModel();
        helper('ResponceServices');
        helper('api');
    }
    public function admin_support_index()
    {
        try {
            if ($this->isLoggedIn) {
                if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                    return redirect('partner/profile');
                }
                setPageInfo($this->data, labels('admin_support', 'Admin Support') . ' | ' . labels('provider_panel', 'Provider Panel'), 'admin_chat');
                $this->data['current_user_id'] = $this->userId;
                $chat_settings = get_settings('general_settings', true);
                $this->data['maxFilesOrImagesInOneMessage'] = $chat_settings['maxFilesOrImagesInOneMessage'] ?? 10;
                $this->data['maxFileSizeInBytesCanBeSent'] = $chat_settings['maxFileSizeInBytesCanBeSent'] ?? 20000000;
                $this->data['maxCharactersInATextMessage'] = $chat_settings['maxCharactersInATextMessage'] ?? 500;
                $chatQuestionsModel = new ChatQuestions_model();
                $this->data['chat_questions'] = $chatQuestionsModel->getByType('provider_admin_support');
                return view('backend/partner/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - admin_support_index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function provider_chats_index($bookingId = null)
    {
        try {
            if ($this->isLoggedIn) {
                if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                    return redirect('partner/profile');
                }
                setPageInfo($this->data, labels('chat', 'Chat') . ' | ' . labels('provider_panel', 'Provider Panel'), 'provider_chats');
                $this->data['current_user_id'] = $this->userId;
                $db = \Config\Database::connect();
                $partnerIdInt = (int) $this->userId;
                $exclusionSql = $this->chatModel->getExclusionSql($partnerIdInt, 'c');
                $exclusionSqlUc = $this->chatModel->getExclusionSql($partnerIdInt, 'uc');

                $builder = $db->table('orders o');
                $builder->select("
                    u.id,
                    u.username,
                    u.image as profile_image,
                    o.id as order_id,
                    o.status as booking_status,
                    o.status as order_status,
                    (SELECT c.message FROM chats c WHERE c.booking_id = o.id AND {$exclusionSql} ORDER BY c.created_at DESC LIMIT 1) as last_message,
                    (SELECT c.created_at FROM chats c WHERE c.booking_id = o.id AND {$exclusionSql} ORDER BY c.created_at DESC LIMIT 1) as last_chat_date,
                    (SELECT COUNT(*) FROM chats uc WHERE uc.booking_id = o.id AND ((uc.sender_type = 2 AND uc.is_read = 0) OR (uc.sender_type = 3 AND uc.is_read_by_provider = 0)) AND {$exclusionSqlUc}) AS unread_count
                ", false)
                    ->join('users u', 'u.id = o.user_id', 'inner')
                    ->where('o.partner_id', $this->userId)
                    ->where('o.id IN (SELECT DISTINCT booking_id FROM chats WHERE booking_id IS NOT NULL)')
                    ->where(
                        "NOT EXISTS (
                            SELECT 1 FROM chat_deletions cd
                            WHERE cd.user_id = {$partnerIdInt}
                              AND cd.chat_id IS NULL
                              AND cd.booking_id = o.id
                              AND cd.created_at >= (SELECT MAX(cx.created_at) FROM chats cx WHERE cx.booking_id = o.id)
                        )",
                        null,
                        false
                    )
                    ->orderBy("o.created_at", "DESC");

                $customers_with_chats = $builder->get()->getResultArray();
                foreach ($customers_with_chats as $key => $row) {
                    $customers_with_chats[$key]['profile_image'] = $this->fileService->url($row['profile_image'] ?? '', 'profile');
                }

                $builder1 = $db->table('chats c');
                $builder1->select("
                    u.id,
                    u.username,
                    u.image as profile_image,
                    MAX(c.id) as order_id,
                    MAX(c.e_id) as en_id,
                    MAX(c.created_at) AS last_chat_date,
                    (SELECT COUNT(*) FROM chats uc WHERE uc.booking_id IS NULL AND uc.sender_id = u.id AND uc.sender_type = 2 AND uc.receiver_id = {$partnerIdInt} AND uc.receiver_type = 1 AND uc.is_read = 0 AND {$exclusionSqlUc}) AS unread_count
                ", false)
                    ->join('users u', "(u.id = c.sender_id OR u.id = c.receiver_id)", 'inner')
                    ->where('c.booking_id', NULL)
                    ->where('u.id !=', $this->userId)
                    ->groupStart()
                    ->where('c.sender_id', $this->userId)
                    ->orWhere('c.receiver_id', $this->userId)
                    ->groupEnd();
                $this->chatModel->excludeDeletedForUser($builder1, $partnerIdInt, 'c');
                $builder1->groupBy('u.id')
                    ->orderBy('last_chat_date', 'DESC');

                $customer_pre_booking_queries = $builder1->get()->getResultArray();
                foreach ($customer_pre_booking_queries as $key => $row) {
                    $customer_pre_booking_queries[$key]['profile_image'] = $this->fileService->url($row['profile_image'] ?? '', 'profile');
                    $customer_pre_booking_queries[$key]['order_status'] = "awaiting";
                    $customer_pre_booking_queries[$key]['order_id'] = "enquire_" . $row['en_id'] . '_' . $row['order_id'];
                }

                $merged_array = array_merge($customers_with_chats, $customer_pre_booking_queries);

                usort($merged_array, function ($a, $b) {
                    $unreadDiff = ((int) ($b['unread_count'] ?? 0)) <=> ((int) ($a['unread_count'] ?? 0));
                    if ($unreadDiff !== 0)
                        return $unreadDiff;
                    return ($b['last_chat_date'] ?? '0000-00-00') <=> ($a['last_chat_date'] ?? '0000-00-00');
                });

                $this->data['auto_open_booking_id'] = null;
                $this->data['auto_open_user_id'] = null;

                if (!empty($bookingId)) {
                    $order = fetch_details('orders', ['id' => $bookingId, 'partner_id' => $this->userId]);
                    if (!empty($order[0])) {
                        $customerId = $order[0]['user_id'];
                        $this->data['auto_open_booking_id'] = $bookingId;
                        $this->data['auto_open_user_id'] = $customerId;

                        // Check if this customer+booking already exists in the sidebar
                        $found = false;
                        foreach ($merged_array as $item) {
                            if ($item['id'] == $customerId && $item['order_id'] == $bookingId) {
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            $customer = fetch_details('users', ['id' => $customerId], ['id', 'username', 'image']);
                            if (!empty($customer[0])) {
                                $profileImage = $this->fileService->url($customer[0]['image'] ?? '', 'profile');

                                array_unshift($merged_array, [
                                    'id' => $customer[0]['id'],
                                    'username' => $customer[0]['username'],
                                    'profile_image' => $profileImage,
                                    'order_id' => $bookingId,
                                    'last_chat_date' => date('Y-m-d H:i:s'),
                                    'booking_status' => $order[0]['status'] ?? '',
                                    'order_status' => $order[0]['status'] ?? '',
                                ]);
                            }
                        }
                    }
                }

                $this->data['customers'] = $merged_array;

                $chat_settings = get_settings('general_settings', true);
                $this->data['maxFilesOrImagesInOneMessage'] = $chat_settings['maxFilesOrImagesInOneMessage'] ?? 10;
                $this->data['maxFileSizeInBytesCanBeSent'] = $chat_settings['maxFileSizeInBytesCanBeSent'] ?? 20000000;
                $this->data['maxCharactersInATextMessage'] = $chat_settings['maxCharactersInATextMessage'] ?? 500;
                $chatQuestionsModel = new ChatQuestions_model();
                $this->data['booking_chat_questions'] = $chatQuestionsModel->getByType('provider_post_booking');
                return view('backend/partner/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - provider_chats_index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function store_admin_chat()
    {
        // if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
        //     return ErrorResponse(DEMO_MODE_ERROR, true, [], [], 200, csrf_token(), csrf_hash());
        // }
        try {
            $message = $this->request->getPost('message');
            $user_group = fetch_details('users_groups', ['group_id' => '1']);
            $receiver_id = end($user_group)['group_id'];
            $sender_id = $this->userId;
            $enquiry = fetch_details('enquiries', ['customer_id' => null, 'userType' => 1, 'booking_id' => NULL, 'provider_id' => $sender_id]);
            if (empty($enquiry[0])) {
                $user = fetch_details('users', ['id' => $sender_id], ['username'])[0];
                $data['title'] = $user['username'] . '_query';
                $data['status'] = 1;
                $data['userType'] = 1;
                $data['customer_id'] = null;
                $data['provider_id'] = $sender_id;
                $data['date'] = now();
                $store = insert_details($data, 'enquiries');
                $e_id = $store['id'];
            } else {
                $e_id = $enquiry[0]['id'];
            }
            $last_date = getLastMessageDateFromChat($e_id);
            $attachment_image = null;
            $is_file = false;
            if (!empty($_FILES['attachment']['name'])) {
                // Normalize/validate attachments before saving (backend enforcement for Admin toggles).
                $normalized = $this->normalizeChatAttachmentFilesArray($_FILES['attachment']);
                $validation = $this->validateChatAttachments($normalized);
                if (!empty($validation) && ($validation['ok'] ?? false) !== true) {
                    return ErrorResponse($validation['message'] ?? labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                $attachment_image = $normalized;
                $is_file = !empty($normalized['name']);
            }

            // Block empty messages that also have no attachments (safety net).
            if (trim((string) $message) === '' && $is_file === false) {
                return ErrorResponse(labels('message_is_required', 'Message is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $data = insert_chat_message_for_chat($sender_id, $receiver_id, $message, $e_id, 1, 0, date('Y-m-d H:i:s'), $is_file, $attachment_image);
            $new_data = getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $data['id'], $last_date, 'admin');

            /**
             * Send FCM notification to the admin using NotificationService (NO QUEUE).
             *
             * Why:
             * - Keeps chat notifications consistent with app notification pipeline.
             * - Avoids legacy direct-FCM helper functions.
             * - Chat should feel real-time, so we do not queue it.
             */
            try {
                $language = get_current_language_from_request();

                // Build a single message payload with the same shape as get_chat_history() rows.
                // This mirrors partner/api/V1.php::send_chat_message().
                $singleMessageForPush = fetch_details('chats', ['id' => (int) ($data['id'] ?? 0)]);
                $singleMessageForPush = !empty($singleMessageForPush[0]) ? $singleMessageForPush[0] : ($data ?? []);
                $singleMessageForPush['sender_details'] = $new_data['sender_details'] ?? [];
                $singleMessageForPush['receiver_details'] = $new_data['receiver_details'] ?? [];
                // Top-level convenience keys (mirrors ChatApiController::send_chat_message()) —
                // handyman/customer panels read parsed.username / parsed.profile_image directly
                // off the chat_message payload; without these the raw `chats` row has neither,
                // since it isn't joined to `users`, leaving the sender avatar blank until refresh.
                $singleMessageForPush['username'] = $new_data['sender_details']['username'] ?? '';
                $singleMessageForPush['sender_name'] = $new_data['sender_details']['username'] ?? '';
                $singleMessageForPush['image'] = (string) ($new_data['sender_details']['image'] ?? '');
                $singleMessageForPush['profile_image'] = (string) ($new_data['sender_details']['image'] ?? '');
                // Admin FCM handler keys on this to route the push to the admin chat
                // page hook / sidebar unread badge. Without it the chat_message is
                // ignored on the admin side.
                $singleMessageForPush['viewer_type'] = 'admin';
                $singleMessageForPush['last_message_date'] = $last_date;

                // Resolve file URLs so admin panel can display attachments directly.
                $disk = fetch_current_file_manager();
                if (!empty($singleMessageForPush['file'])) {
                    $decoded_files = json_decode($singleMessageForPush['file'], true);
                    if (is_array($decoded_files)) {
                        $tempFiles = [];
                        foreach ($decoded_files as $f) {
                            if ($disk == 'local_server') {
                                $fileUrl = base_url($f['file']);
                            } elseif ($disk == 'aws_s3') {
                                $fileUrl = fetch_cloud_front_url('chat_attachment', $f['file']);
                            } else {
                                $fileUrl = base_url($f['file']);
                            }
                            $tempFiles[] = [
                                'file' => $fileUrl,
                                'file_type' => $f['file_type'] ?? '',
                                'file_name' => $f['file_name'] ?? '',
                                'file_size' => $f['file_size'] ?? '',
                            ];
                        }
                        $singleMessageForPush['file'] = $tempFiles;
                    } else {
                        $singleMessageForPush['file'] = [];
                    }
                } else {
                    $singleMessageForPush['file'] = [];
                }

                $singleChatMessagePayload = json_encode($singleMessageForPush, JSON_UNESCAPED_SLASHES);
                if ($singleChatMessagePayload === false) {
                    $singleChatMessagePayload = '{}';
                }

                // Lean notification context: chat_message + chat_user (null for provider→admin)
                $notificationContext = [
                    'chat_message' => $singleChatMessagePayload,
                    'chat_user' => null,
                ];

                // Send immediately (no queue) to admin panel platforms.
                $notificationService = new NotificationService();
                $notificationService->send(
                    'new_message',
                    ['user_id' => (int) $receiver_id],
                    $notificationContext,
                    [
                        'channels' => ['fcm'],
                        'language' => $language,
                        'platforms' => ['admin_panel'],
                        'priority' => 'high',
                        'data' => $notificationContext,
                    ]
                );
            } catch (\Throwable $notificationError) {
                // Never block chat message sending due to notification failures.
                log_message('error', '[NEW_MESSAGE] Direct FCM notification error trace (admin chat, partner panel): ' . $notificationError->getTraceAsString());
            }

            // Add event tracking data for chat message sent
            $eventData = [
                'clarity_event' => 'chat_message_sent',
                'message_id' => $data['id'],
                'receiver_id' => $receiver_id,
                'booking_id' => '',
                'message_type' => $is_file ? 'file' : 'text'
            ];

            return response_helper(labels(SENT_MESSAGE_SUCCESSFULLY, 'Sent message successfully '), false, $data, 200, ['custom_data' => $new_data, 'clarity_event_data' => $eventData]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - store_admin_chat()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function store_booking_chat()
    {
        try {

            $message = $this->request->getPost('message');
            $receiver_id = $this->request->getPost('receiver_id');
            $booking_id = $this->request->getPost('order_id');
            if (strpos($booking_id, "enquire") !== false) {
                ;
                $enquiry_id = (explode('_', $booking_id));
                $e_id = $enquiry_id[1];
                $booking_id = null;
            } else {
                $e_id = add_enquiry_for_chat("customer", $_POST['receiver_id'], true, $_POST['order_id']);
            }
            // Record time taken for enquiry / booking resolution work.
            // $bmAfterEnquiry = microtime(true);

            $sender_id = $this->userId;
            $last_date = getLastMessageDateFromChat($e_id);
            $attachment_image = null;
            $is_file = false;
            if (!empty($_FILES['attachment']['name'])) {
                // Normalize/validate attachments before saving (backend enforcement for Admin toggles).
                $normalized = $this->normalizeChatAttachmentFilesArray($_FILES['attachment']);
                $validation = $this->validateChatAttachments($normalized);
                if (!empty($validation) && ($validation['ok'] ?? false) !== true) {
                    return ErrorResponse($validation['message'] ?? labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                $attachment_image = $normalized;
                $is_file = !empty($normalized['name']);
            }
            // Record time taken for attachment normalization + validation.
            // $bmAfterAttachments = microtime(true);

            // Block empty messages that also have no attachments (safety net).
            if (trim((string) $message) === '' && $is_file === false) {
                return ErrorResponse(labels('message_is_required', 'Message is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }


            $data = insert_chat_message_for_chat($sender_id, $receiver_id, $message, $e_id, 1, 2, date('Y-m-d H:i:s'), $is_file, $attachment_image, $booking_id);
            $new_data = getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $data['id'], $last_date, 'admin');

            // Record time taken for chat insert + notification data preparation.
            // $bmAfterInsert = microtime(true);

            // Enrich chat response with booking/provider info so apps can render context instantly
            $chatExtras = build_chat_message_details(
                (int) $sender_id, // provider_id (sender is provider)
                $booking_id ? (int) $booking_id : null,
                2, // receiver_type (customer)
                (int) $sender_id
            );
            $new_data = array_merge($new_data ?? [], $chatExtras);

            $booking_status = fetch_details('orders', ['id' => $new_data['booking_id']], ['status']);
            $new_data['booking_status'] = isset($booking_status[0]) ? $booking_status[0]['status'] : "";
            $new_data['provider_id'] = $sender_id;
            $new_data['type'] = "chat";

            /**
             * Send FCM notification to the customer using NotificationService (NO QUEUE).
             *
             * Why:
             * - We want one unified notification pipeline (templates, language, settings checks).
             * - We intentionally do NOT use queue_notification_service() here, because chat UX
             *   should be real-time for both provider panel and customer apps.
             * - This mirrors the context-building approach used in:
             *   app/Controllers/partner/api/V1.php::send_chat_message()
             */
            try {
                $language = get_current_language_from_request();

                // Build a single message payload with the same shape as get_chat_history() rows.
                // This mirrors partner/api/V1.php::send_chat_message().
                $singleMessageForPush = fetch_details('chats', ['id' => (int) ($data['id'] ?? 0)]);
                $singleMessageForPush = !empty($singleMessageForPush[0]) ? $singleMessageForPush[0] : ($data ?? []);
                $singleMessageForPush['sender_details'] = $new_data['sender_details'] ?? [];
                $singleMessageForPush['receiver_details'] = $new_data['receiver_details'] ?? [];
                // Top-level convenience keys (mirrors ChatApiController::send_chat_message()) —
                // handyman/customer panels read parsed.username / parsed.profile_image directly
                // off the chat_message payload; without these the raw `chats` row has neither,
                // since it isn't joined to `users`, leaving the sender avatar blank until refresh.
                $singleMessageForPush['username'] = $new_data['sender_details']['username'] ?? '';
                $singleMessageForPush['sender_name'] = $new_data['sender_details']['username'] ?? '';
                $singleMessageForPush['image'] = (string) ($new_data['sender_details']['image'] ?? '');
                $singleMessageForPush['profile_image'] = (string) ($new_data['sender_details']['image'] ?? '');
                // Admin FCM handler keys on this to route the push to the admin chat
                // page hook / sidebar unread badge. Without it the chat_message is
                // ignored on the admin side.
                $singleMessageForPush['viewer_type'] = 'admin';
                $singleMessageForPush['last_message_date'] = $last_date;

                // Resolve file URLs so client apps can display attachments directly.
                $disk = fetch_current_file_manager();
                if (!empty($singleMessageForPush['file'])) {
                    $decoded_files = json_decode($singleMessageForPush['file'], true);
                    if (is_array($decoded_files)) {
                        $tempFiles = [];
                        foreach ($decoded_files as $f) {
                            if ($disk == 'local_server') {
                                $fileUrl = base_url($f['file']);
                            } elseif ($disk == 'aws_s3') {
                                $fileUrl = fetch_cloud_front_url('chat_attachment', $f['file']);
                            } else {
                                $fileUrl = base_url($f['file']);
                            }
                            $tempFiles[] = [
                                'file' => $fileUrl,
                                'file_type' => $f['file_type'] ?? '',
                                'file_name' => $f['file_name'] ?? '',
                                'file_size' => $f['file_size'] ?? '',
                            ];
                        }
                        $singleMessageForPush['file'] = $tempFiles;
                    } else {
                        $singleMessageForPush['file'] = [];
                    }
                } else {
                    $singleMessageForPush['file'] = [];
                }

                $singleChatMessagePayload = json_encode($singleMessageForPush, JSON_UNESCAPED_SLASHES);
                if ($singleChatMessagePayload === false) {
                    $singleChatMessagePayload = '{}';
                }

                // Build chat_user payload for provider (same shape as ChatApiController::send_chat_message()).
                $singleProviderPayload = null;
                if (!empty($this->userId)) {
                    $providerId = (int) $this->userId;
                    $providerUser = fetch_details('users', ['id' => $providerId], ['id', 'image']);
                    $providerDetails = fetch_details('partner_details', ['partner_id' => $providerId], ['partner_id', 'company_name']);

                    $providerObject = [
                        'id' => $providerId,
                        'last_chat_date' => (string) ($new_data['created_at'] ?? $data['created_at'] ?? $last_date),
                        'partner_id' => $providerId,
                        'partner_name' => $providerDetails[0]['company_name'] ?? '',
                        'translated_partner_name' => $providerDetails[0]['company_name'] ?? '',
                        'image' => '',
                        'booking_id' => $booking_id ? (string) $booking_id : null,
                        'order_status' => null,
                        'translated_order_status' => null,
                        'un_read_chats' => 0,
                        'is_blocked' => 0,
                        'is_block_by_user' => 0,
                        'is_block_by_provider' => 0,
                    ];

                    if (!empty($providerObject['partner_id']) && !empty($providerObject['partner_name'])) {
                        $translatedData = get_translated_partner_field((int) $providerObject['partner_id'], 'company_name', $providerObject['partner_name']);
                        $providerObject['partner_name'] = $translatedData ?? $providerObject['partner_name'];
                        $providerObject['translated_partner_name'] = $translatedData ?? $providerObject['partner_name'];
                    }

                    if ($booking_id) {
                        $orderRow = fetch_details('orders', ['id' => (int) $booking_id], ['status']);
                        $providerObject['order_status'] = $orderRow[0]['status'] ?? null;
                        if (!empty($providerObject['order_status'])) {
                            $providerObject['translated_order_status'] = getTranslatedValue($providerObject['order_status'], 'panel');
                        }
                    }

                    $userReport = fetch_details('user_reports', [
                        'reporter_id' => (int) $sender_id,
                        'reported_user_id' => (int) $receiver_id
                    ], ['id']);
                    $providerReport = fetch_details('user_reports', [
                        'reporter_id' => (int) $receiver_id,
                        'reported_user_id' => (int) $sender_id
                    ], ['id']);
                    $providerObject['is_block_by_user'] = !empty($userReport) ? 1 : 0;
                    $providerObject['is_block_by_provider'] = !empty($providerReport) ? 1 : 0;
                    $providerObject['is_blocked'] = (!empty($userReport) || !empty($providerReport)) ? 1 : 0;

                    $providerObject['image'] = service('fileService')->url($providerUser[0]['image'] ?? '', 'profile');

                    $singleProviderPayload = json_encode($providerObject, JSON_UNESCAPED_SLASHES);
                    if ($singleProviderPayload === false) {
                        $singleProviderPayload = null;
                    }
                }

                // Lean notification context: chat_message + chat_user (provider payload for provider→customer).
                $notificationContext = [
                    'chat_message' => $singleChatMessagePayload,
                    'chat_user' => $singleProviderPayload,
                ];

                if (!empty($booking_id)) {
                    $notificationContext['booking_id'] = (string) $booking_id;
                }

                // Send immediately (no queue) to customer platforms.
                $notificationService = new NotificationService();
                $notificationService->send(
                    'new_message',
                    ['user_id' => (int) $receiver_id],
                    $notificationContext,
                    [
                        'channels' => ['fcm'],
                        'language' => $language,
                        'platforms' => ['android', 'ios', 'web'],
                        'data' => $notificationContext,
                        // Important for performance: limit how many FCM tokens are used
                        // per language for this user when sending chat pushes.
                        // This keeps chat sends fast even if the user has many old tokens
                        // stored (multiple devices / tabs / sessions).
                        // FcmProvider will keep only the latest N tokens per language.
                        'fcm_demo_token_limit' => 20,
                    ]
                );
                // Record time taken for the NotificationService->send() call.
                // $bmAfterNotification = microtime(true);

                /**
                 * Also notify every handyman assigned to this booking.
                 *
                 * Why:
                 * - The booking chat thread is shared between the provider and all
                 *   assigned handymen (they all view/reply to the same customer
                 *   conversation, scoped by booking_id).
                 * - The send above only targets the customer (receiver_id), so
                 *   without this, handymen never get pushed for a provider-authored
                 *   message and their panel's foreground handler never fires.
                 * - Mirrors Handyman\Chat::notifyChatRecipients() on the panel side.
                 */
                if (!empty($booking_id)) {
                    $assignedHandymanIds = (new \App\Models\BookingHandymenModel())->getAssignedHandymanIds((int) $booking_id);
                    foreach ($assignedHandymanIds as $handymanId) {
                        $handymanContext = array_merge($notificationContext, [
                            'sender_id' => (string) $sender_id,
                            'booking_id' => (string) $booking_id,
                            'message' => $message,
                            'type' => 'chat',
                            'receiver_id' => (string) $handymanId,
                            'viewer_type' => 'handyman_booking',
                        ]);

                        $notificationService->send(
                            'new_message',
                            ['user_id' => $handymanId],
                            $handymanContext,
                            [
                                'channels' => ['fcm'],
                                'language' => $language,
                                'platforms' => ['android', 'ios', 'handyman_panel'],
                                'data' => $handymanContext,
                                'fcm_demo_token_limit' => 20,
                            ]
                        );
                    }
                }
            } catch (\Throwable $notificationError) {
                // Never block chat message sending due to notification failures.
                log_message('error', '[NEW_MESSAGE] Direct FCM notification error trace (partner panel): ' . $notificationError->getTraceAsString());
            }

            // Check if this is the first message in this chat (chat started)
            $existingMessages = fetch_details(table: 'chats', where: [
                'e_id' => $e_id,
                'booking_id' => $booking_id
            ], limit: 1, sort: 'id', order: 'ASC');
            $isFirstMessage = !empty($existingMessages) && count($existingMessages) == 1;

            // Add event tracking data
            $eventData = [
                'clarity_event' => 'chat_message_sent',
                'message_id' => $data['id'],
                'receiver_id' => $receiver_id,
                'booking_id' => $booking_id ?? '',
                'message_type' => $is_file ? 'file' : 'text'
            ];

            // If this is the first message, also track chat_started
            if ($isFirstMessage) {
                $eventData['chat_started'] = true;
            }

            return response_helper(labels(SENT_MESSAGE_SUCCESSFULLY, 'Sent message successfully '), false, $data, 200, ['custom_data' => $new_data, 'clarity_event_data' => $eventData]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - store_booking_chat()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function getAllMessage()
    {

        // if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
        //     return ErrorResponse(DEMO_MODE_ERROR, true, [], [], 200, csrf_token(), csrf_hash());
        // }
        try {
            if ($this->isLoggedIn) {
                if (isset($_POST['order_id'])) {
                    $is_already_exist_query = fetch_details('enquiries', ['customer_id' => $_POST['receiver_id'], 'userType' => '2', 'booking_id' => $_POST['order_id']]);
                } else {
                    $is_already_exist_query = fetch_details('enquiries', ['provider_id' => $this->userId, 'userType' => '1']);
                }

                $e_id = !empty($is_already_exist_query) ? $is_already_exist_query[0]['id'] : 0;
                $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
                $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
                $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
                $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
                $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
                $receiver_id = $_POST['receiver_id'];
                $where['booking_id'] = $_POST['order_id'] ?? null;
                $data = $this->chat->chat_list($limit, $offset, $sort, $order, $e_id, ['e_id' => $e_id], $where, $search, false, $receiver_id, 'customer', (int) $this->userId);
                return $data;
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - getAllMessage()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function provider_booking_chat_list()
    {
        try {
            if ($this->isLoggedIn) {
                if (strpos($_POST['order_id'], "enquire") !== false) {
                    ;
                    $enquiry_id = (explode('_', $_POST['order_id']));
                    $e_id = $enquiry_id[1];
                } else {
                    $e_id = add_enquiry_for_chat("customer", $_POST['receiver_id'], true, $_POST['order_id']);
                }
                $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
                $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
                $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
                $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
                $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
                $receiver_id = $_POST['receiver_id'];
                $sender_id = $this->userId;
                $where['booking_id'] = $_POST['order_id'] ?? null;
                $data = $this->chat->chat_list($limit, $offset, $sort, $order, $e_id, ['e_id' => $e_id], $where, $search, false, $receiver_id, 'customer', (int) $this->userId);
                return $data;
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - provider_booking_chat_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function check_booking_status()
    {
        try {
            $order_id = $this->request->getPost('order_id');
            if (strpos($order_id, "enquire") !== false) {
                $order_status = "awaiting";
            } else {
                $order_status = fetch_details('orders', ['id' => $order_id], ['status']);
                if (!empty($order_status)) {
                    $order_status = $order_status[0]['status'];
                } else {
                    $order_status = "completed";
                }
            }
            return $this->response->setJSON(['status' => $order_status]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - check_booking_status()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function get_lead_handyman()
    {
        try {
            if (!$this->isLoggedIn) {
                return JsonError(labels(ERROR_OCCURED, 'An error occurred'));
            }

            $orderId = $this->request->getPost('order_id');
            if (empty($orderId) || strpos($orderId, "enquire") !== false || !is_numeric($orderId)) {
                return JsonSuccess(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), null);
            }

            $leadHandyman = model(BookingHandymenModel::class)->getLeadHandyman((int) $orderId);
            return JsonSuccess(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), $leadHandyman);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Partner/Chats.php - get_lead_handyman()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function get_customer()
    {
        try {
            if ($this->isLoggedIn) {
                $db = \Config\Database::connect();
                $searchKeyword = $this->request->getPost('search');
                $partnerIdInt = (int) $this->userId;
                $builder = $db->table('users u');

                $builder->select("u.id, u.username, u.image as profile_image, o.id as order_id, o.status as order_status,u.phone,u.country_code,
                        (SELECT COUNT(*) FROM chats uc WHERE uc.booking_id = o.id AND ((uc.sender_type = 2 AND uc.is_read = 0) OR (uc.sender_type = 3 AND uc.is_read_by_provider = 0))) AS unread_count")
                    ->join('orders o', "o.user_id = u.id")
                    ->where('o.partner_id', $this->userId)
                    ->where('o.id IN (SELECT DISTINCT booking_id FROM chats WHERE booking_id IS NOT NULL)')
                    ->groupBy('o.id')
                    ->orderBy('o.created_at', 'DESC');
                if (!empty($searchKeyword)) {
                    $builder->like('u.username', $searchKeyword);
                }
                $customers_with_chats = $builder->get()->getResultArray();
                foreach ($customers_with_chats as $key => $row) {
                    $customers_with_chats[$key]['profile_image'] = $this->fileService->url($row['profile_image'] ?? '', 'profile');
                }
                $builder1 = $db->table('users u');
                $builder1->select("u.id,u.username,c.id as order_id,c.e_id as en_id,u.image as profile_image,u.phone,u.country_code,
                        (SELECT COUNT(*) FROM chats uc WHERE uc.sender_id = u.id AND uc.sender_type = 2 AND uc.receiver_id = {$partnerIdInt} AND uc.receiver_type = 1 AND uc.booking_id IS NULL AND uc.is_read = 0) AS unread_count")
                    ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 2) OR (c.receiver_id = u.id AND c.receiver_type = 2)")
                    ->where('c.booking_id', NULL)
                    ->groupStart()
                    ->where('c.receiver_type', '1')
                    ->orWhere('c.sender_type', '1')
                    ->groupEnd()
                    ->groupStart()
                    ->where('c.sender_id', $this->userId)
                    ->orWhere('c.receiver_id', $this->userId)
                    ->groupEnd()
                    ->groupBy('u.id')
                    ->orderBy('id', 'DESC');
                if (!empty($searchKeyword)) {
                    $builder1->like('u.username', $searchKeyword);
                }
                $customer_pre_booking_queries = $builder1->get()->getResultArray();
                foreach ($customer_pre_booking_queries as $key => $row) {
                    $customer_pre_booking_queries[$key]['profile_image'] = $this->fileService->url($row['profile_image'] ?? '', 'profile');
                    $customer_pre_booking_queries[$key]['order_status'] = "awaiting";
                    $customer_pre_booking_queries[$key]['order_id'] = "enquire_" . $row['en_id'] . '_' . $row['order_id'];
                }
                $merged_array = array_merge($customers_with_chats, $customer_pre_booking_queries);
                return json_encode($merged_array);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - get_customer()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function mark_chat_as_read()
    {
        try {
            if (!$this->isLoggedIn) {
                return ErrorResponse(labels('unauthorized', 'Unauthorized'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $partnerId = (int) $this->userId;
            $userType = (string) $this->request->getPost('user_type'); // 'customer' | 'admin'
            $senderId = $this->request->getPost('sender_id');
            $orderId = $this->request->getPost('order_id'); // booking id, "enquire_*", or empty for admin

            $db = \Config\Database::connect();
            $builder = $db->table('chats')
                ->where('receiver_id', $partnerId)
                ->where('receiver_type', 1)
                ->where('is_read', 0);

            if ($userType === 'admin') {
                $builder->where('sender_type', 0)->where('booking_id', null);
            } elseif ($userType === 'customer') {
                if (empty($senderId)) {
                    return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $builder->where('sender_id', (int) $senderId)->where('sender_type', 2);
                if (!empty($orderId) && is_string($orderId) && strpos($orderId, 'enquire_') === 0) {
                    $builder->where('booking_id', null);
                } elseif (!empty($orderId) && is_numeric($orderId)) {
                    $builder->where('booking_id', (int) $orderId);
                } else {
                    $builder->where('booking_id', null);
                }
            } else {
                return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $builder->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
            $affected = $db->affectedRows();

            // Booking chats are shared between the provider and their assigned lead
            // handyman (both can chat with the customer). The is_read update above
            // only covers customer→partner rows (sender_type=2); handyman→customer
            // rows (sender_type=3) have the customer as their native receiver, so the
            // provider's own read state for those lives in is_read_by_provider instead.
            if ($userType === 'customer' && !empty($orderId) && is_numeric($orderId)) {
                $ownsBooking = $db->table('orders')
                    ->where('id', (int) $orderId)
                    ->where('partner_id', $partnerId)
                    ->countAllResults() > 0;

                if ($ownsBooking) {
                    $db->table('chats')
                        ->where('booking_id', (int) $orderId)
                        ->where('sender_type', 3)
                        ->where('is_read_by_provider', 0)
                        ->update(['is_read_by_provider' => 1]);
                }
            }

            return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), false, ['marked' => (int) $affected], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - mark_chat_as_read()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Sidebar unread badge counts for the partner panel.
     *
     * Returns the number of distinct senders / conversations with unread messages
     * for the current partner, mirroring the SQL used to render the sidebar
     * badges on initial page load (see `Views/backend/partner/top_and_sidebar.php`).
     *
     * Response shape:
     *   data: { admin_support: int, chat: int }
     */
    public function sidebar_unread_users_count()
    {
        try {
            if (!$this->isLoggedIn) {
                return ErrorResponse(labels('unauthorized', 'Unauthorized'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $partnerId = (int) $this->userId;
            $db = \Config\Database::connect();

            // Admin Support badge: total unread MESSAGE count (not distinct senders).
            $adminSupportUnreadCount = $db->table('chats')
                ->where('sender_type', 0)
                ->where('receiver_id', $partnerId)
                ->where('receiver_type', 1)
                ->where('is_read', 0)
                ->countAllResults();

            // Chat badge: distinct USER count with unread activity on bookings owned by
            // this partner. Customer-authored messages use the native `is_read` flag
            // (receiver_type=1 already targets the provider); handyman-authored ones
            // use is_read_by_provider (the provider isn't the native receiver on those
            // rows — the booking's chat thread is shared between provider and their
            // assigned lead handyman, both able to chat with the same customer).
            // Inner-join `users` so orders whose customer account no longer exists
            // (deleted/seed accounts) don't inflate the badge — the chat list
            // applies the same filter, so those rows are never shown there either.
            $bookingUserRows = $db->table('orders o')
                ->select('o.user_id as sender_id')
                ->join('users u', 'u.id = o.user_id', 'inner')
                ->where('o.partner_id', $partnerId)
                ->where(
                    "EXISTS (SELECT 1 FROM chats c WHERE c.booking_id = o.id AND ((c.sender_type = 2 AND c.is_read = 0) OR (c.sender_type = 3 AND c.is_read_by_provider = 0)))",
                    null,
                    false
                )
                ->groupBy('o.user_id')
                ->get()->getResultArray();

            $preBookingUserRows = $db->table('chats c')
                ->select('c.sender_id')
                ->join('users u', 'u.id = c.sender_id')
                ->where('c.sender_type', 2)
                ->where('c.receiver_id', $partnerId)
                ->where('c.receiver_type', 1)
                ->where('c.is_read', 0)
                ->where('c.booking_id IS NULL', null, false)
                ->groupBy('c.sender_id')
                ->get()->getResultArray();

            // Union distinct sender_ids so a user appearing in both buckets
            // (pre-booking query + active booking) is counted once.
            $distinctUserIds = [];
            foreach ($bookingUserRows as $r) {
                $distinctUserIds[(int) $r['sender_id']] = true;
            }
            foreach ($preBookingUserRows as $r) {
                $distinctUserIds[(int) $r['sender_id']] = true;
            }

            return successResponse(
                labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                false,
                [
                    'admin_support' => $adminSupportUnreadCount,
                    'chat' => count($distinctUserIds),
                ],
                [],
                200,
                csrf_token(),
                csrf_hash()
            );
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - sidebar_unread_users_count()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function unblock_user()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(PLEASE_LOGIN_FIRST, 'Please login first'),
                    'data' => []
                ]);
            }

            $validation = \Config\Services::validation();
            $validation->setRules([
                'user_id' => 'required|numeric',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => []
                ]);
            }

            $partner_id = $this->userId;
            $user_id = $this->request->getPost('user_id');

            // Update chat block status
            $db = \Config\Database::connect();
            $builder = $db->table('chats');

            $db->table('user_reports')->where([
                'reporter_id' => $partner_id,
                'reported_user_id' => $user_id
            ])->delete();

            // Add event tracking data
            $eventData = [
                'clarity_event' => 'user_unblocked',
                'user_id' => $user_id
            ];

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(USER_UNBLOCKED_SUCCESSFULLY, 'User Unblocked Successfully'),
                'data' => $eventData
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Withdrawal_requests.php - unblockUser()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function check_block_status()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(PLEASE_LOGIN_FIRST, 'Please login first'),
                    'data' => []
                ]);
            }

            $validation = \Config\Services::validation();
            $validation->setRules([
                'user_id' => 'required|numeric'
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => []
                ]);
            }

            $partner_id = $this->userId;
            $user_id = $this->request->getPost('user_id');

            // Check block status in user_reports table
            $db = \Config\Database::connect();
            $builder = $db->table('user_reports');
            $blocked_in_reports = $builder->where([
                'reporter_id' => $partner_id,
                'reported_user_id' => $user_id,
            ])->countAllResults();

            $is_blocked = isset($blocked_in_reports) && $blocked_in_reports > 0 ? 1 : 0;
            return $this->response->setJSON([
                'error' => false,
                'message' => labels(BLOCK_STATUS_RETRIEVED_SUCCESSFULLY, 'Block status retrieved successfully'),
                'data' => $is_blocked,
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Chats.php - checkBlockStatus()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
        }
    }

    public function delete_chat()
    {
        try {
            if (!$this->isLoggedIn) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(PLEASE_LOGIN_FIRST, 'Please login first'),
                    'data' => []
                ]);
            }

            $validation = \Config\Services::validation();
            $validation->setRules([
                'sender_id' => 'required|numeric',
                'receiver_id' => 'required|numeric',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => []
                ]);
            }

            $current_user_id = (int) $this->userId;
            $order_id = $this->request->getPost('order_id');

            if (empty($order_id)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ERROR_OCCURED, 'An error occurred'),
                    'data' => []
                ]);
            }

            if (strpos($order_id, 'enquire_') === 0) {
                $parts = explode('_', $order_id);
                $e_id = isset($parts[1]) ? (int) $parts[1] : null;
                if (empty($e_id)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                        'data' => []
                    ]);
                }
                $this->chatDeletionModel->clearConversationForUser($current_user_id, $e_id, null);
            } else {
                $enquiry = fetch_details('enquiries', ['booking_id' => (int) $order_id]);
                if (empty($enquiry[0])) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                        'data' => []
                    ]);
                }
                $this->chatDeletionModel->clearConversationForUser($current_user_id, (int) $enquiry[0]['id'], (int) $order_id);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(CHAT_DELETED_SUCCESSFULLY, 'Chat deleted successfully'),
                'data' => []
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Partner/Chats.php - delete_chat()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
        }
    }
}