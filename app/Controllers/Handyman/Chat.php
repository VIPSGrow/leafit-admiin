<?php

namespace App\Controllers\Handyman;

use App\Models\BookingHandymenModel;
use App\Models\ChatModel;
use App\Models\ChatQuestions_model;
use App\Models\Orders_model;
use App\Models\TranslatedChatQuestions_model;
use App\Models\Users_model;
use App\Services\NotificationService;
use App\Services\utility\FileService;

class Chat extends Handyman
{
    protected ChatModel $chat;
    protected FileService $fileService;
    protected BookingHandymenModel $bookingHandymen;
    protected Orders_model $ordersModel;

    public function __construct()
    {
        parent::__construct();
        $this->chat = new ChatModel();
        $this->fileService = new FileService();
        $this->bookingHandymen = new BookingHandymenModel();
        $this->ordersModel = model(Orders_model::class);
        helper(['function', 'ResponceServices']);
    }

    /**
     * Normalize chat attachment uploads into a flat "single multiple input" shape.
     * The frontend sends everything as `attachment[]`, but different clients can
     * still produce nested/odd `$_FILES` structures. This flattens them and drops
     * empty entries. Mirrors Partner\Chats::normalizeChatAttachmentFilesArray().
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

        if (!isset($files['tmp_name'], $files['name'], $files['type'], $files['error'], $files['size'])) {
            return $normalized;
        }

        $pushOne = function ($name, $type, $tmp, $error, $size) use (&$normalized) {
            if ((int) $error === UPLOAD_ERR_NO_FILE || empty($name)) {
                return;
            }
            $normalized['name'][] = $name;
            $normalized['type'][] = $type;
            $normalized['tmp_name'][] = $tmp;
            $normalized['error'][] = $error;
            $normalized['size'][] = $size;
        };

        if (!is_array($files['tmp_name'])) {
            $pushOne($files['name'], $files['type'], $files['tmp_name'], $files['error'], $files['size']);
            return $normalized;
        }

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
     * Server-side validation for chat attachments — the UI hides pickers based on
     * Admin settings, but backend must enforce too (crafted requests). Mirrors
     * Partner\Chats::validateChatAttachments().
     */
    protected function validateChatAttachments(array $normalizedFiles): array
    {
        $settings = get_settings('general_settings', true);

        $enableChatImageUpload = !empty($settings['enable_chat_image_upload']) ? (int) $settings['enable_chat_image_upload'] : 0;
        $enableChatFileUpload = !empty($settings['enable_chat_file_upload']) ? (int) $settings['enable_chat_file_upload'] : 0;

        $maxFiles = (int) ($settings['maxFilesOrImagesInOneMessage'] ?? 10);
        $maxBytes = (int) ($settings['maxFileSizeInBytesCanBeSent'] ?? 20000000);

        $count = isset($normalizedFiles['name']) && is_array($normalizedFiles['name']) ? count($normalizedFiles['name']) : 0;

        if ($count === 0) {
            return ['ok' => true, 'message' => ''];
        }

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
                return ['ok' => false, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')];
            }

            if ($size > $maxBytes) {
                return ['ok' => false, 'message' => labels('file_size_exceeds_the_maximum_limit_of', 'File size exceeds the maximum limit of')];
            }

            $realMime = (!empty($tmp) && is_file($tmp)) ? @mime_content_type($tmp) : '';
            $mime = !empty($realMime) ? $realMime : $clientMime;
            $isImage = (is_string($mime) && strpos($mime, 'image/') === 0);

            if ($isImage && $enableChatImageUpload !== 1) {
                return ['ok' => false, 'message' => labels('enable_chat_image_upload', 'Enable Chat Image Upload') . ' ' . labels('disable', 'Disable')];
            }
            if (!$isImage && $enableChatFileUpload !== 1) {
                return ['ok' => false, 'message' => labels('enable_chat_file_upload', 'Enable Chat File Upload') . ' ' . labels('disable', 'Disable')];
            }

            $fileName = $normalizedFiles['name'][$i] ?? '';
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($isImage) {
                $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
                if (!in_array($extension, $allowedImageExtensions)) {
                    return ['ok' => false, 'message' => labels('only_image_files_are_allowed', 'Only image files are allowed') . " ($extension)"];
                }
            } else {
                $allowedFileExtensions = [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
                    'rtf', 'txt', 'zip', 'rar', '7z', 'tar', 'gz', 'csv', 'json', 'xml',
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
        $this->data['title'] = labels('chat', 'Chat');
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
            ['label' => labels('chat', 'Chat')]
        ];

        $this->data['company_title'] = getTranslatedSetting('general_settings', 'company_title');
        $this->data['current_user_id'] = (int) $this->userId;
        $this->data['firebase_setting'] = get_settings('firebase_settings', true);

        $currentUser = (new Users_model())->find($this->userId) ?? [];
        $this->data['current_user_name'] = $currentUser['username'] ?? '';
        $this->data['current_user_image'] = $this->fileService->url($currentUser['image'] ?? '', 'profile');

        $chatSettings = get_settings('general_settings', true);
        $this->data['enable_chat_image_upload'] = !empty($chatSettings['enable_chat_image_upload']) ? (int) $chatSettings['enable_chat_image_upload'] : 0;
        $this->data['enable_chat_file_upload'] = !empty($chatSettings['enable_chat_file_upload']) ? (int) $chatSettings['enable_chat_file_upload'] : 0;
        $this->data['maxFilesOrImagesInOneMessage'] = (int) ($chatSettings['maxFilesOrImagesInOneMessage'] ?? 10);
        $this->data['maxFileSizeInBytesCanBeSent'] = (int) ($chatSettings['maxFileSizeInBytesCanBeSent'] ?? 20000000);
        $this->data['server_tz_offset'] = !empty($chatSettings['system_timezone_gmt']) ? $chatSettings['system_timezone_gmt'] : '+00:00';

        $chatQuestionsModel = new ChatQuestions_model();
        $questions = $chatQuestionsModel->getByType('provider_post_booking');

        if (!empty($questions)) {
            $questionIds = array_column($questions, 'id');
            $translatedModel = new TranslatedChatQuestions_model();
            $currentLang = get_current_language();
            $defaultLang = get_default_language();

            $currentTranslations = $translatedModel->getForMultipleQuestions($questionIds, $currentLang);
            $defaultTranslations = ($currentLang !== $defaultLang)
                ? $translatedModel->getForMultipleQuestions($questionIds, $defaultLang)
                : [];

            foreach ($questions as &$q) {
                $q['question'] = $currentTranslations[$q['id']]
                    ?? $defaultTranslations[$q['id']]
                    ?? $q['question'];
            }
            unset($q);
        }

        $this->data['booking_chat_questions'] = $questions;

        return view('backend/handyman/pages/chat', $this->data);
    }

    /**
     * Returns customer list for this handyman.
     * Only post-booking chats (booking_id is not null).
     * Any booking where this handyman is assigned, regardless of status (excludes rejected).
     */
    public function get_customer_list()
    {
        try {
            $disk = fetch_current_file_manager();
            $db = \Config\Database::connect();
            $handymanId = (int) $this->userId;
            $search = $this->request->getPost('search') ?? '';

            $exclusionSql = $this->chat->getExclusionSql($handymanId, 'c');
            $exclusionSqlUc = $this->chat->getExclusionSql($handymanId, 'uc');
            $builder = $db->table('orders o')
                ->select("
                    u.id,
                    u.username,
                    u.image as profile_image,
                    o.id as order_id,
                    o.status as booking_status,
                    bh.is_lead,
                    (SELECT c.message FROM chats c WHERE c.booking_id = o.id AND {$exclusionSql} ORDER BY c.created_at DESC LIMIT 1) as last_message,
                    (SELECT c.created_at FROM chats c WHERE c.booking_id = o.id AND {$exclusionSql} ORDER BY c.created_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*) FROM chats uc WHERE uc.booking_id = o.id AND uc.sender_id != {$handymanId} AND (bh.chat_last_read_at IS NULL OR uc.created_at > bh.chat_last_read_at) AND {$exclusionSqlUc}) AS unread_count
                ", false)
                ->join('booking_handymen bh', "bh.order_id = o.id AND bh.handyman_id = {$handymanId} AND bh.status != 'rejected'", 'inner')
                ->join('users u', 'u.id = o.user_id', 'inner')
                ->where("(SELECT COUNT(*) FROM chats c WHERE c.booking_id = o.id AND {$exclusionSql}) > 0", null, false)
                ->orderBy("(SELECT MAX(c2.created_at) FROM chats c2 WHERE c2.booking_id = o.id AND " . $this->chat->getExclusionSql($handymanId, 'c2') . ")", "DESC")
                ->orderBy("o.created_at", "DESC");

            if (!empty($search)) {
                $builder->like('u.username', $search);
            }

            $customers = $builder->get()->getResultArray();

            foreach ($customers as $key => $row) {
                $url = $this->fileService->url($row['profile_image'], 'profile');
                $customers[$key]['profile_image'] = !empty($url) ? $url : null;
                $customers[$key]['is_lead'] = (int) $row['is_lead'];
            }

            return $this->response->setJSON($customers);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - get_customer_list()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    /**
     * Returns paginated message history for a specific booking conversation.
     */
    public function booking_chat_list()
    {
        try {
            $receiverId = (int) $this->request->getPost('receiver_id');
            $orderId = (int) $this->request->getPost('order_id');

            $eId = add_enquiry_for_chat('customer', $receiverId, true, $orderId);

            $limit = $this->request->getGet('limit') ?? 50;
            $offset = $this->request->getGet('offset') ?? 0;

            $data = $this->chat->chat_list(
                $limit,
                $offset,
                'id',
                'ASC',
                $eId,
                ['c.e_id' => $eId, 'c.booking_id' => $orderId],
                [],
                '',
                false,
                $receiverId,
                'customer',
                (int) $this->userId
            );

            $decoded = json_decode((string) $data, true);
            if (!is_array($decoded)) {
                log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - booking_chat_list()' . "\nMessage : chat_list() returned non-JSON-encodable data for order_id={$orderId}, receiver_id={$receiverId}");
                return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
            }

            return $this->response->setJSON($decoded);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - booking_chat_list()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    /**
     * Stores a new chat message from handyman to customer for a booking.
     * sender_type = 3 (handyman), receiver_type = 2 (customer).
     * Only the current lead handyman for the booking may send; others are view-only.
     */
    public function store_chat()
    {
        try {
            $message = $this->request->getPost('message') ?? '';
            $receiverId = (int) $this->request->getPost('receiver_id');
            $orderId = (int) $this->request->getPost('order_id');
            $senderId = (int) $this->userId;

            if (!$this->bookingHandymen->isLeadForOrder($orderId, $senderId)) {
                return JsonError(labels('only_lead_handyman_can_chat', 'Only the lead handyman can chat with the customer for this booking'));
            }

            $orderStatus = $this->ordersModel->select('status')->find($orderId)['status'] ?? null;
            if (in_array($orderStatus, ['completed', 'cancelled'], true)) {
                return JsonError(labels('cant_chat_booking_completed_or_cancelled', "Sorry, you can't send a message since this booking has been completed or cancelled."));
            }

            $eId = add_enquiry_for_chat('customer', $receiverId, true, $orderId);

            $isFile = false;
            $attachmentFiles = null;
            if (!empty($_FILES['attachment']['name'])) {
                $normalized = $this->normalizeChatAttachmentFilesArray($_FILES['attachment']);
                $validation = $this->validateChatAttachments($normalized);
                if (($validation['ok'] ?? false) !== true) {
                    return JsonError($validation['message'] ?? labels(ERROR_OCCURED, 'An error occurred'));
                }

                $attachmentFiles = $normalized;
                $isFile = !empty($normalized['name']);
            }

            if (trim($message) === '' && !$isFile) {
                return JsonError(labels('message_is_required', 'Message is required'));
            }

            $data = insert_chat_message_for_chat(
                $senderId,
                $receiverId,
                $message,
                $eId,
                3,
                2,
                date('Y-m-d H:i:s'),
                $isFile,
                $attachmentFiles,
                $orderId
            );

            $this->notifyChatRecipients($senderId, $receiverId, $orderId, $message, $data);

            return JsonSuccess(labels(SENT_MESSAGE_SUCCESSFULLY, 'Message sent successfully'), null, ['data' => $data]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - store_chat()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    /**
     * Sends FCM push notifications for a handyman-authored booking chat message.
     *
     * Notifies:
     * - The customer (mobile apps + web), so their app/panel updates live.
     * - Every OTHER handyman assigned to the booking (excluding the sender), since
     *   the booking chat thread is shared — all assigned handymen view the same
     *   customer conversation and their panel should auto-append too.
     *
     * Failures here are logged only; they must never block message sending.
     */
    private function notifyChatRecipients(int $senderId, int $receiverId, int $orderId, string $message, array $chatRow): void
    {
        try {
            $chatMessagePayload = json_encode($chatRow, JSON_UNESCAPED_SLASHES);
            if ($chatMessagePayload === false) {
                $chatMessagePayload = '{}';
            }

            $db = \Config\Database::connect();
            $orderRow = $db->table('orders')->select('partner_id')->where('id', $orderId)->get()->getRowArray();
            $providerId = !empty($orderRow['partner_id']) ? (int) $orderRow['partner_id'] : null;

            // Sender (handyman) display info for the `chat_user` field — required by
            // client apps/panels to render the notification's from-user name/avatar.
            // Without it they fall back to a generic "customerSupport" title.
            $langCode = get_current_language();
            $senderUser = $db->table('users')->select('id, username')->where('id', $senderId)->get()->getRowArray();
            $handymanName = $db->table('users u')
                ->select('COALESCE(thd.username, u.username) AS username')
                ->join('languages l', "l.code = '{$langCode}'", 'left')
                ->join('translated_handyman_details thd', 'thd.handyman_id = u.id AND thd.language_id = l.id AND thd.deleted_at IS NULL', 'left')
                ->where('u.id', $senderId)
                ->get()->getRowArray();
            $resolvedSenderName = $handymanName['username'] ?? ($senderUser['username'] ?? '');
            $chatUserPayload = build_chat_user_payload($senderId, (string) $orderId, $resolvedSenderName, $resolvedSenderName);

            $notificationContext = [
                'id' => (string) ($chatRow['id'] ?? ''),
                'sender_id' => (string) $senderId,
                'receiver_id' => (string) $receiverId,
                'booking_id' => (string) $orderId,
                'message' => $message,
                'sender_type' => '3',
                'created_at' => (string) ($chatRow['created_at'] ?? date('Y-m-d H:i:s')),
                'chat_message' => $chatMessagePayload,
                'chat_user' => $chatUserPayload,
                'type' => 'chat',
            ];

            $language = $langCode;
            $notificationService = new NotificationService();

            // Customer (mobile apps + web).
            $customerContext = array_merge($notificationContext, [
                'receiver_type' => '2',
                'viewer_type' => 'provider_booking',
            ]);
            $notificationService->send('new_message', ['user_id' => $receiverId], $customerContext, [
                'channels' => ['fcm'],
                'language' => $language,
                'platforms' => ['android', 'ios', 'web'],
                'data' => $customerContext,
                'priority' => 'high',
            ]);

            // Other assigned handymen (excluding the sender).
            $otherHandymanIds = array_filter(
                $this->bookingHandymen->getAssignedHandymanIds($orderId),
                fn ($handymanId) => $handymanId !== $senderId
            );

            foreach ($otherHandymanIds as $handymanId) {
                $handymanContext = array_merge($notificationContext, [
                    'receiver_id' => (string) $handymanId,
                    'receiver_type' => '3',
                    'viewer_type' => 'handyman_booking',
                ]);

                $notificationService->send('new_message', ['user_id' => $handymanId], $handymanContext, [
                    'channels' => ['fcm'],
                    'language' => $language,
                    'platforms' => ['android', 'ios', 'handyman_panel'],
                    'data' => $handymanContext,
                    'priority' => 'high',
                ]);
            }

            // Provider/company account — they own the booking and share this
            // chat thread too, so their panel/app should also get pushed.
            if ($providerId) {
                $providerContext = array_merge($notificationContext, [
                    'receiver_id' => (string) $providerId,
                    'receiver_type' => '1',
                    'viewer_type' => 'provider_booking',
                ]);

                $notificationService->send('new_message', ['user_id' => $providerId], $providerContext, [
                    'channels' => ['fcm'],
                    'language' => $language,
                    'platforms' => ['android', 'ios', 'web', 'provider_panel'],
                    'data' => $providerContext,
                    'priority' => 'high',
                ]);
            }
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - notifyChatRecipients()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
        }
    }

    /**
     * Returns the distinct customer count with unread messages for this handyman,
     * used to render the sidebar chat unread-count badge.
     *
     * Response shape: data: { chat: int }
     */
    public function sidebar_unread_users_count()
    {
        try {
            $handymanId = (int) $this->userId;
            $count = $this->bookingHandymen->unreadChatBookingCountForHandyman($handymanId);

            return JsonSuccess(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), ['chat' => $count]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - sidebar_unread_users_count()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    /**
     * Marks this handyman's chat thread for a booking as read as of now.
     * Independent of the customer/provider `chats.is_read` flag — see
     * BookingHandymenModel::markChatRead().
     */
    public function mark_as_read()
    {
        try {
            $handymanId = (int) $this->userId;
            $orderId = (int) $this->request->getPost('order_id');

            $this->bookingHandymen->markChatRead($orderId, $handymanId);

            return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, 'Marked as read'));
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - mark_as_read()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    /**
     * Fetches booking and customer details for a specific order_id.
     * Used when opening a conversation directly from the bookings page
     * (where the customer may not yet have messages in the sidebar).
     */
    public function get_booking_customer_details()
    {
        try {
            $orderId = (int) $this->request->getPost('order_id');
            $handymanId = (int) $this->userId;

            $db = \Config\Database::connect();
            $booking = $db->table('orders o')
                ->select('u.id, u.username, u.image as profile_image, o.status as booking_status, bh.is_lead')
                ->join('users u', 'u.id = o.user_id', 'inner')
                ->join('booking_handymen bh', "bh.order_id = o.id AND bh.handyman_id = {$handymanId} AND bh.status != 'rejected'", 'inner')
                ->where('o.id', $orderId)
                ->get()
                ->getRowArray();

            if (!$booking) {
                return JsonError(labels('booking_not_found', 'Booking not found'));
            }

            $booking['profile_image'] = $this->fileService->url($booking['profile_image'], 'profile');
            $booking['is_lead'] = (int) $booking['is_lead'];

            return JsonSuccess(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), $booking);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - get_booking_customer_details()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    /**
     * Stores the FCM web token for this handyman's panel session, so booking
     * chat pushes (see notifyChatRecipients()) can reach the open panel tab.
     * Mirrors Partner::save_web_token() — stored under platform 'handyman_panel'.
     */
    public function save_web_token()
    {
        try {
            $token = $this->request->getPost('token');
            $languageCode = get_current_language();

            store_users_fcm_id($this->userId, $token, 'handyman_panel', null, $languageCode);

            return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, 'Token saved'));
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Chat.php - save_web_token()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
}
