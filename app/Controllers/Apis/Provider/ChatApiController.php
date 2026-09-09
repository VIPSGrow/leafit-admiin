<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Services\NotificationService;
class ChatApiController extends BaseController
{
    protected $request;
    protected \App\Models\ChatModel $chatModel;
    protected \App\Models\ChatDeletionModel $chatDeletionModel;
    protected $user_details = [];
    protected $excluded_routes =
        [
            "api/v1/index",
            "api/v1",
        ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->chatModel = new \App\Models\ChatModel();
        $this->chatDeletionModel = new \App\Models\ChatDeletionModel();
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
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function send_chat_message()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'receiver_type' => 'required'
                ]
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
            // Try to grab multiple files; fallback to single
            $attachments = $this->request->getFileMultiple('attachment');
            if (empty($attachments)) {
                $file = $this->request->getFile('attachment');
                $attachments = $file ? [$file] : [];
            }

            // Check if there's at least one valid file
            $hasAttachment = !empty($attachments) && $attachments[0]->isValid();

            // Only require message if no valid attachment
            if (!$hasAttachment) {
                $validation = \Config\Services::validation();
                $validation->setRules(['message' => 'required']);

                if (!$validation->withRequest($this->request)->run()) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => $validation->getErrors(),
                        'data' => [],
                    ]);
                }
            }
            $message = $this->request->getPost('message') ?? "";
            $receiver_id = $this->request->getPost('receiver_id');
            if ($receiver_id == null) {
                $user_group = fetch_details('users_groups', ['group_id' => '1']);
                $receiver_id = end($user_group)['group_id'];
            }
            $receiver_type = $this->request->getPost('receiver_type');
            $sender_id = $this->user_details['id'];
            // group_id is in users_groups, not users — fetch it explicitly
            $userGroup = fetch_details('users_groups', ['user_id' => $sender_id], ['group_id']);
            $userGroupId = !empty($userGroup) ? (int) $userGroup[0]['group_id'] : 0;
            // group_id 4 = handyman, 3 = provider
            $isHandyman = $userGroupId === 4;
            $sender_type = $isHandyman ? 3 : 1;
            $booking_id = $this->request->getPost('booking_id');
            if (isset($booking_id)) {
                $e_id_data = fetch_details('enquiries', ['customer_id' => $receiver_id, 'userType' => 2, 'booking_id' => $booking_id]);
                $e_id = empty($e_id_data) ? add_enquiry_for_chat("customer", $_POST['receiver_id'], true, $_POST['booking_id']) : $e_id_data[0]['id'];
            } else {
                if ($booking_id == null) {
                    if ($receiver_type == "0") {
                        $enquiry = fetch_details('enquiries', ['customer_id' => null, 'userType' => 1, 'booking_id' => NULL, 'provider_id' => $sender_id]);
                        if (empty($enquiry[0])) {
                            $provider = fetch_details('users', ['id' => $sender_id], ['username'])[0];
                            $data['title'] = $provider['username'] . '_query';
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
                    } else if ($receiver_type == "2") {
                        $enquiry = fetch_details('enquiries', ['customer_id' => $receiver_id, 'userType' => 2, 'booking_id' => NULL, 'provider_id' => $sender_id]);
                        if (empty($enquiry[0])) {
                            $customer = fetch_details('users', ['id' => $sender_id], ['username'])[0];
                            $data['title'] = $customer['username'] . '_query';
                            $data['status'] = 1;
                            $data['userType'] = 2;
                            $data['customer_id'] = $receiver_id;
                            $data['provider_id'] = $sender_id;
                            $data['date'] = now();
                            $store = insert_details($data, 'enquiries');
                            $e_id = $store['id'];
                        } else {
                            $e_id = $enquiry[0]['id'];
                        }
                    }
                }
            }
            $last_date = getLastMessageDateFromChat($e_id);
            // Attachment check
            $is_file = (!empty($attachments) && $attachments[0]->isValid());
            $attachment_image = $is_file ? $_FILES['attachment'] : null;

            $booking_id = $this->request->getPost('booking_id') ?? null;
            $data = insert_chat_message_for_chat($sender_id, $receiver_id, $message, $e_id, $sender_type, $receiver_type, date('Y-m-d H:i:s'), $is_file, $attachment_image, $booking_id);

            // Determine notification type and get data
            $notifType = isset($booking_id) ? 'provider_booking' : ($receiver_type == 2 ? 'provider' : 'admin');
            $when_customer_is_receiver = isset($booking_id) ? 'yes' : ($receiver_type == 2 ? 'yes' : null);
            $new_data = getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $data['id'], $last_date, $notifType, $when_customer_is_receiver);

            // Surface booking/provider metadata for client apps (mirrors customer chat response structure).
            $chatExtras = build_chat_message_details(
                (int) $sender_id,
                $booking_id ? (int) $booking_id : null,
                $receiver_type !== null ? (int) $receiver_type : null,
                (int) $sender_id
            );
            $data_with_extras = array_merge($data ?? [], $chatExtras);
            $new_data = array_merge($new_data ?? [], $chatExtras);

            // Build a single message payload with the same shape as get_chat_history() rows.
            $singleMessageForPush = fetch_details('chats', ['id' => (int) ($data['id'] ?? 0)]);
            $singleMessageForPush = !empty($singleMessageForPush[0]) ? $singleMessageForPush[0] : ($data ?? []);
            $singleMessageForPush['sender_details'] = $new_data['sender_details'] ?? [];
            $singleMessageForPush['receiver_details'] = $new_data['receiver_details'] ?? [];
            // Top-level convenience keys (mirrors ChatApiController::send_chat_message() for
            // customers) — handyman/panel FCM handlers read parsed.username / parsed.profile_image
            // directly off chat_message; the raw `chats` row has neither since it isn't joined
            // to `users`, leaving the sender avatar blank until refresh.
            $singleMessageForPush['username'] = $new_data['sender_details']['username'] ?? '';
            $singleMessageForPush['sender_name'] = $new_data['sender_details']['username'] ?? '';
            $singleMessageForPush['image'] = (string) ($new_data['sender_details']['image'] ?? '');
            $singleMessageForPush['profile_image'] = (string) ($new_data['sender_details']['image'] ?? '');

            $fileService = service('fileService');
            if (!empty($singleMessageForPush['file'])) {
                $decoded_files = json_decode($singleMessageForPush['file'], true);
                if (is_array($decoded_files)) {
                    $tempFiles = [];
                    foreach ($decoded_files as $f) {
                        $fileUrl = $fileService->url($f['file'], 'chat_attachment');
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


            // Build a single sender payload for receiver context.
            // Shape mirrors get_chat_providers_list; sender_type distinguishes handyman (3) from provider (1).
            $singleProviderPayload = null;
            if ($this->user_details['id']) {
                $db = \Config\Database::connect();
                $senderUser = $db->table('users')->select('id, username, image')->where('id', (int) $this->user_details['id'])->get()->getRowArray();

                $senderObject = [
                    'id' => (int) $this->user_details['id'],
                    'last_chat_date' => (string) ($new_data['created_at'] ?? $data['created_at'] ?? $last_date),
                    'partner_id' => (int) $this->user_details['id'],
                    'partner_name' => '',
                    'translated_partner_name' => '',
                    'image' => '',
                    'booking_id' => $booking_id ? (string) $booking_id : null,
                    'order_status' => null,
                    'translated_order_status' => null,
                    'un_read_chats' => 0,
                    'is_blocked' => 0,
                    'is_block_by_user' => 0,
                    'is_block_by_provider' => 0,
                    'sender_type' => $sender_type,
                ];

                if ($isHandyman) {
                    $langCode = get_current_language_from_request();
                    $handymanName = $db->table('users u')
                        ->select('COALESCE(thd.username, u.username) AS username')
                        ->join('languages l', "l.code = '{$langCode}'", 'left')
                        ->join('translated_handyman_details thd', 'thd.handyman_id = u.id AND thd.language_id = l.id AND thd.deleted_at IS NULL', 'left')
                        ->where('u.id', (int) $this->user_details['id'])
                        ->get()->getRowArray();
                    $resolvedName = $handymanName['username'] ?? ($senderUser['username'] ?? '');
                    $senderObject['partner_name'] = $resolvedName;
                    $senderObject['translated_partner_name'] = $resolvedName;
                } else {
                    $providerDetails = fetch_details('partner_details', ['partner_id' => (int) $this->user_details['id']], ['partner_id', 'company_name']);
                    $companyName = $providerDetails[0]['company_name'] ?? '';
                    $translatedName = (!empty($companyName))
                        ? (get_translated_partner_field((int) $this->user_details['id'], 'company_name', $companyName) ?? $companyName)
                        : '';
                    $senderObject['partner_name'] = $translatedName ?: $companyName;
                    $senderObject['translated_partner_name'] = $translatedName ?: $companyName;
                }

                if ($booking_id) {
                    $orderRow = fetch_details('orders', ['id' => (int) $booking_id], ['status']);
                    $senderObject['order_status'] = $orderRow[0]['status'] ?? null;
                    if (!empty($senderObject['order_status'])) {
                        $senderObject['translated_order_status'] = getTranslatedValue($senderObject['order_status'], 'panel');
                    }
                }

                $userReport = fetch_details('user_reports', ['reporter_id' => (int) $sender_id, 'reported_user_id' => (int) $receiver_id], ['id']);
                $providerReport = fetch_details('user_reports', ['reporter_id' => (int) $receiver_id, 'reported_user_id' => (int) $sender_id], ['id']);
                $senderObject['is_block_by_user'] = !empty($userReport) ? 1 : 0;
                $senderObject['is_block_by_provider'] = !empty($providerReport) ? 1 : 0;
                $senderObject['is_blocked'] = (!empty($userReport) || !empty($providerReport)) ? 1 : 0;

                $senderObject['image'] = service('fileService')->url($senderUser['image'] ?? '', 'profile');

                $singleProviderPayload = json_encode($senderObject, JSON_UNESCAPED_SLASHES) ?: null;
            }

            // Send FCM notification using NotificationService
            // This works for all scenarios: provider to admin, provider to customer, etc.
            try {
                // Detect demo mode using ALLOW_MODIFICATION flag.
                // In demo mode we still send FCM, but only to a small,
                // recent subset of tokens to avoid slow responses.
                $isDemoMode = (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION != 1);
                $language = get_current_language_from_request();

                // Prepare context data for notification templates.
                // NOTE:
                // - Keep keys simple and camelCase so that apps and panels can read them easily.
                // - Core identity fields (names / types) stay here.
                $notificationContext = [
                    // Serialized rich context payloads for chat notification consumers.
                    'chat_message' => $singleChatMessagePayload,
                    'chat_user' => $singleProviderPayload,
                ];

                // Add booking_id if present (legacy key used by some templates).
                if ($booking_id) {
                    $notificationContext['booking_id'] = $booking_id;
                }

                // Determine platforms based on receiver type
                $platforms = ['android', 'ios', 'web'];
                if ($receiver_type == 0) {
                    // Admin
                    $platforms = ['admin_panel'];
                } elseif ($receiver_type == 1) {
                    // Provider
                    $platforms = ['android', 'ios', 'web', 'provider_panel'];
                }

                // Build base options for NotificationService.
                $sendOptions = [
                    'channels' => ['fcm'],
                    'language' => $language,
                    'platforms' => $platforms,
                    'data' => $notificationContext,
                ];

                // In demo mode, instruct FCM provider to only use the last
                // N tokens per language (here 20) so pushes are still sent
                // but the request stays fast even with many stored tokens.
                if ($isDemoMode) {
                    $sendOptions['fcm_demo_token_limit'] = 20;
                }

                $notificationService = new NotificationService();
                $notificationService->send(
                    'new_message',
                    ['user_id' => (int) $receiver_id],
                    $notificationContext,
                    $sendOptions
                );

                /**
                 * This shared API is used by BOTH provider and handyman mobile apps
                 * (see $isHandyman above). Whoever sends, the booking chat thread is
                 * shared by the provider + every assigned handyman, so everyone else
                 * on the booking needs a push too — the send above only reaches
                 * $receiver_id (the customer). Mirrors the panel-side fan-out in
                 * Handyman\Chat::notifyChatRecipients() / Partner\Chats::store_booking_chat().
                 */
                if ($booking_id) {
                    // sender_id/message already present inside chat_message blob (notificationContext) — no need to duplicate flat.
                    $fanoutContext = array_merge($notificationContext, [
                        'sender_id' => (string) $sender_id,
                        'booking_id' => (string) $booking_id,
                        'type' => 'chat',
                    ]);

                    // Provider/company account — only needs telling when they are
                    // NOT the sender (i.e. a handyman sent this message).
                    if ($isHandyman) {
                        $orderRow = $db->table('orders')->select('partner_id')->where('id', (int) $booking_id)->get()->getRowArray();
                        $providerId = !empty($orderRow['partner_id']) ? (int) $orderRow['partner_id'] : null;

                        if ($providerId) {
                            $providerContext = array_merge($fanoutContext, [
                                'receiver_id' => (string) $providerId,
                                'viewer_type' => 'provider_booking',
                            ]);
                            $notificationService->send('new_message', ['user_id' => $providerId], $providerContext, [
                                'channels' => ['fcm'],
                                'language' => $language,
                                'platforms' => ['android', 'ios', 'web', 'provider_panel'],
                                'data' => $providerContext,
                            ]);
                        }
                    }

                    // Every OTHER assigned handyman (excluding the sender, if the
                    // sender itself is a handyman).
                    $otherHandymanIds = array_filter(
                        (new \App\Models\BookingHandymenModel())->getAssignedHandymanIds((int) $booking_id),
                        fn($handymanId) => $handymanId !== (int) $sender_id
                    );
                    foreach ($otherHandymanIds as $handymanId) {
                        $handymanContext = array_merge($fanoutContext, [
                            'receiver_id' => (string) $handymanId,
                            'viewer_type' => 'handyman_booking',
                        ]);
                        $notificationService->send('new_message', ['user_id' => $handymanId], $handymanContext, [
                            'channels' => ['fcm'],
                            'language' => $language,
                            'platforms' => ['android', 'ios', 'handyman_panel'],
                            'data' => $handymanContext,
                        ]);
                    }
                }

            } catch (\Throwable $notificationError) {
                // Log error but don't fail the message sending
                log_message('error', '[NEW_MESSAGE] FCM notification error trace (partner): ' . $notificationError->getTraceAsString());
            }

            return response_helper(labels(SENT_MESSAGE_SUCCESSFULLY, 'Sent message successfully '), false, $data_with_extras, 200);
        } catch (\Throwable $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - send_chat_message()');
            return $this->response->setJSON($response);
        }
    }

    public function get_chat_history()
    {
        try {
            $validation = service('validation');
            $validation->setRules([
                'type' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $type = $this->request->getPost('type');
            $e_id = $this->request->getPost('e_id');


            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $db = \Config\Database::connect();
            $current_user_id = $this->user_details['id'];

            // Handyman shares this API with provider. Only give handyman the
            // provider_details object (who they're chatting on behalf of).
            $userGroup = fetch_details('users_groups', ['user_id' => $current_user_id], ['group_id']);
            $isHandyman = !empty($userGroup) && (int) $userGroup[0]['group_id'] === 4;
            $provider_details = $isHandyman ? $this->buildProviderDetailsForHandyman((int) $current_user_id) : null;

            $provider_report = fetch_details('user_reports', [
                'reporter_id' => $current_user_id,
                'reported_user_id' => $this->request->getPost('customer_id')
            ]);


            $is_block_by_provider = !empty($provider_report) ? "1" : "0";

            // Check if provider blocked user
            $user_report = fetch_details('user_reports', [
                'reporter_id' => $this->request->getPost('customer_id'),
                'reported_user_id' => $current_user_id
            ]);
            $is_block_by_user = !empty($user_report) ? "1" : "0";

            // Set overall blocked status
            $is_blocked = $is_block_by_provider == "1" ? "1" : "0";


            if ($type == "0") {
                // Chat messages sent by provider to admin
                $e_id_data = fetch_details('enquiries', ['customer_id' => NULL, 'userType' => 1, 'provider_id' => $current_user_id, 'booking_id' => null]);
                if (!empty($e_id_data)) {
                    $e_id = $e_id_data[0]['id'];
                    $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null, $isHandyman);
                    $countBuilder = $db->table('chats c');
                    $countBuilder->select('COUNT(*) as total')
                        ->where('c.booking_id', null)
                        ->where('c.e_id', $e_id);
                    $this->chatModel->excludeDeletedForUser($countBuilder, (int) $current_user_id);
                    $totalRecords = $countBuilder->get()->getRow()->total;
                    $mainBuilder = $db->table('chats c');
                    $mainBuilder->select('c.*')
                        ->where('c.e_id', $e_id)
                        ->where('c.booking_id', null);
                    $this->chatModel->excludeDeletedForUser($mainBuilder, (int) $current_user_id);
                    $mainBuilder->limit($limit, $offset);
                    $chat_record = $mainBuilder->orderBy('c.created_at', 'DESC')->get()->getResultArray();
                    $fileService = service('fileService');
                    foreach ($chat_record as $key => $row) {
                        $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'admin');

                        $provider_report = fetch_details('user_reports', [
                            'reporter_id' => $row['sender_id'],
                            'reported_user_id' => $row['receiver_id']
                        ]);
                        $is_block_by_provider = !empty($provider_report) ? "1" : "0";

                        // Check if provider blocked user
                        $user_report = fetch_details('user_reports', [
                            'reporter_id' => $row['receiver_id'],
                            'reported_user_id' => $row['sender_id']
                        ]);
                        $is_block_by_user = !empty($user_report) ? "1" : "0";

                        // Set overall blocked status
                        $is_blocked = $is_block_by_provider == "1" ? "1" : "0";

                        $chat_record[$key]['sender_details'] = $new_data['sender_details'] ?? [];
                        // $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
                        if (!empty($chat_record[$key]['file'])) {
                            $decoded_files = json_decode($chat_record[$key]['file'], true);
                            if (is_array($decoded_files)) {
                                $tempFiles = [];
                                foreach ($decoded_files as $data) {
                                    $file = $fileService->url($data['file'], 'chat_attachment');
                                    $tempFiles[] = [
                                        'file' => $file,
                                        'file_type' => $data['file_type'],
                                        'file_name' => $data['file_name'],
                                        'file_size' => $data['file_size'],
                                    ];
                                }
                                $chat_record[$key]['file'] = $tempFiles;
                            } else {
                                $chat_record[$key]['file'] = [];
                            }
                        } else {
                            $chat_record[$key]['file'] = [];
                        }
                    }
                    return response_helper(labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '), false, $chat_record, 200, $this->withChatExtras(['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider], $provider_details));
                } else {
                    return response_helper(labels(NO_DATA_FOUND, 'No data Found '), false, [], 200, $this->withChatExtras(['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider], $provider_details));
                }
            } else if ($type = "2") {
                // Chat messages sent by provider to customer
                if ($this->request->getPost('booking_id') != null) {
                    $booking = fetch_details('orders', ['id' => $this->request->getPost('booking_id')], ['user_id']);
                }
                if (!empty($booking)) {
                    $e_id_data = fetch_details('enquiries', ['booking_id' => $this->request->getPost('booking_id'), 'customer_id' => $booking[0]['user_id']]);
                    if (!empty($e_id_data)) {
                        $e_id = $e_id_data[0]['id'];
                        $booking_id = $e_id_data[0]['booking_id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, (int) $booking_id, $isHandyman);
                        $countBuilder = $db->table('chats c');
                        $countBuilder->select('COUNT(*) as total')
                            ->where('c.e_id', $e_id)
                            ->where('c.booking_id', $booking_id);
                        $this->chatModel->excludeDeletedForUser($countBuilder, (int) $current_user_id);
                        $totalRecords = $countBuilder->get()->getRow()->total;
                        $mainBuilder = $db->table('chats c');
                        $mainBuilder->select('c.*')
                            ->where('c.e_id', $e_id)
                            ->where('c.booking_id', $booking_id);
                        $this->chatModel->excludeDeletedForUser($mainBuilder, (int) $current_user_id);
                        $mainBuilder->limit($limit, $offset);
                        $chat_record = $mainBuilder->orderBy('c.created_at', 'DESC')->get()->getResultArray();
                        $fileService = service('fileService');
                        // $customerDetails = $this->buildCustomerDetails((int) $booking[0]['user_id']);
                        $customerDetails = null;
                        $handymanDetails = $isHandyman ? null : $this->buildHandymanDetailsForBooking((int) $booking_id);
                        foreach ($chat_record as $key => $row) {
                            $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'admin');
                            $chat_record[$key]['sender_details'] = $new_data['sender_details'] ?? [];
                            // $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
                            if (!empty($chat_record[$key]['file'])) {
                                $decoded_files = json_decode($chat_record[$key]['file'], true);
                                if (is_array($decoded_files)) {
                                    $tempFiles = [];
                                    foreach ($decoded_files as $data) {
                                        $file = $fileService->url($data['file'], 'chat_attachment');
                                        $tempFiles[] = [
                                            'file' => $file,
                                            'file_type' => $data['file_type'],
                                            'file_name' => $data['file_name'],
                                            'file_size' => $data['file_size'],
                                        ];
                                    }
                                    $chat_record[$key]['file'] = $tempFiles;
                                } else {
                                    $chat_record[$key]['file'] = [];
                                }
                            } else {
                                $chat_record[$key]['file'] = [];
                            }
                        }
                        return response_helper(labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '), false, $chat_record, 200, $this->withChatExtras(['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider], $provider_details, $customerDetails, $handymanDetails));
                    } else {
                        return response_helper(labels(NO_DATA_FOUND, 'No data found '), false, [], 200, $this->withChatExtras(['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider], $provider_details));
                    }
                } else {
                    if ($this->request->getPost('booking_id') == null) {
                        $customer_id = $this->request->getPost('customer_id');
                        $e_id_data = fetch_details('enquiries', ['booking_id' => NULL, 'customer_id' => $customer_id, 'provider_id' => $current_user_id]);

                        $e_id = $e_id_data[0]['id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null, $isHandyman);
                        $countBuilder = $db->table('chats c');
                        $countBuilder->select('COUNT(*) as total')
                            ->where('c.e_id', $e_id);
                        $this->chatModel->excludeDeletedForUser($countBuilder, (int) $current_user_id);
                        $totalRecords = $countBuilder->get()->getRow()->total;
                        $mainBuilder = $db->table('chats c');
                        $mainBuilder->select('c.*')
                            ->where('c.e_id', $e_id);
                        $this->chatModel->excludeDeletedForUser($mainBuilder, (int) $current_user_id);
                        $mainBuilder->limit($limit, $offset);
                        $chat_record = $mainBuilder->orderBy('c.created_at', 'DESC')->get()->getResultArray();
                        $fileService = service('fileService');
                        // $customerDetails = $this->buildCustomerDetails((int) $customer_id);
                        $customerDetails = null;
                        $handymanDetails = $isHandyman ? null : [];
                        foreach ($chat_record as $key => $row) {
                            $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'provider_booking', 'yes');

                            // // Check if user blocked provider
                            // $user_report = fetch_details('user_reports', [
                            //     'reporter_id' => $row['sender_id'],
                            //     'reported_user_id' => $row['receiver_id']
                            // ]);
                            // $is_block_by_user = !empty($user_report) ? "1" : "0";

                            // // Check if provider blocked user
                            // $provider_report = fetch_details('user_reports', [
                            //     'reporter_id' => $row['receiver_id'],
                            //     'reported_user_id' => $row['sender_id']
                            // ]);
                            // $is_block_by_provider = !empty($provider_report) ? "1" : "0";

                            // // Set overall blocked status
                            // $is_blocked = $is_block_by_user == "1" ? "1" : "0";

                            $chat_record[$key]['sender_details'] = $new_data['sender_details'] ?? [];
                            // $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
                            if (!empty($chat_record[$key]['file'])) {
                                $decoded_files = json_decode($chat_record[$key]['file'], true);
                                if (is_array($decoded_files)) {
                                    $tempFiles = [];
                                    foreach ($decoded_files as $data) {
                                        $file = $fileService->url($data['file'], 'chat_attachment');
                                        $tempFiles[] = [
                                            'file' => $file,
                                            'file_type' => $data['file_type'],
                                            'file_name' => $data['file_name'],
                                            'file_size' => $data['file_size'],
                                        ];
                                    }
                                    $chat_record[$key]['file'] = $tempFiles;
                                } else {
                                    $chat_record[$key]['file'] = [];
                                }
                            } else {
                                $chat_record[$key]['file'] = [];
                            }
                        }
                        return response_helper(labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '), false, $chat_record, 200, $this->withChatExtras(['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider], $provider_details, $customerDetails, $handymanDetails));
                    }
                    return response_helper(labels(NO_BOOKING_FOUND, 'No Booking found'), false, [], 200, $this->withChatExtras(['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider], $provider_details));
                }
            }
        } catch (\Throwable $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_chat_history()');
            return $this->response->setJSON($response);
        }
    }

    public function mark_message_as_read()
    {
        try {
            $validation = service('validation');
            $validation->setRules([
                'type' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $type = $this->request->getPost('type');
            $current_user_id = $this->user_details['id'];
            $db = \Config\Database::connect();
            $userGroup = fetch_details('users_groups', ['user_id' => $current_user_id], ['group_id']);
            $isHandyman = !empty($userGroup) && (int) $userGroup[0]['group_id'] === 4;

            if ($type == "0") {
                // Admin chat: mark messages as read where receiver is current provider
                $e_id_data = fetch_details('enquiries', ['customer_id' => NULL, 'userType' => 1, 'provider_id' => $current_user_id, 'booking_id' => null]);
                if (!empty($e_id_data)) {
                    $e_id = $e_id_data[0]['id'];
                    $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null, $isHandyman);
                }
            } else if ($type == "2") {
                $booking_id = $this->request->getPost('booking_id');
                if ($booking_id != null) {
                    $booking = fetch_details('orders', ['id' => $booking_id], ['user_id']);
                    if (!empty($booking)) {
                        $e_id_data = fetch_details('enquiries', ['booking_id' => $booking_id, 'customer_id' => $booking[0]['user_id']]);
                        if (!empty($e_id_data)) {
                            $e_id = $e_id_data[0]['id'];
                            $b_id = $e_id_data[0]['booking_id'];
                            $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, (int) $b_id, $isHandyman);
                        }
                    }
                } else {
                    $customer_id = $this->request->getPost('customer_id');
                    $e_id_data = fetch_details('enquiries', ['booking_id' => NULL, 'customer_id' => $customer_id, 'provider_id' => $current_user_id]);
                    if (!empty($e_id_data)) {
                        $e_id = $e_id_data[0]['id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null, $isHandyman);
                    }
                }
            }

            return response_helper(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), false, [], 200);
        } catch (\Throwable $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - mark_message_as_read()');
            return $this->response->setJSON($response);
        }
    }

    public function get_chat_customers_list()
    {
        try {
            $currentUserId = (int) $this->user_details['id'];
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            // 'enquiry' = pre-booking chats, 'booking' = booking-related chats, null = both
            $type = $this->request->getPost('type') ?? null;
            $db = \Config\Database::connect();

            // Detect handyman (group_id 4 in users_groups)
            $userGroup = fetch_details('users_groups', ['user_id' => $currentUserId], ['group_id']);
            $isHandyman = !empty($userGroup) && (int) $userGroup[0]['group_id'] === 4;

            $fileService = service('fileService');
            $exclusionSql = $this->chatModel->getExclusionSql($currentUserId, 'uc');

            if ($isHandyman) {
                // Handyman booking chats: linked via booking_handymen, sender_type = 3
                $builder = $db->table('chats c');
                $builder->select("us.id as customer_id,
                                    us.username as customer_name,
                                    us.image as image,
                                    MAX(c.created_at) AS last_chat_date,
                                    c.booking_id,
                                    o.status as booking_status,
                                    (SELECT COUNT(*)
                                       FROM chats uc
                                       LEFT JOIN booking_handymen bh_read ON bh_read.order_id = uc.booking_id AND bh_read.handyman_id = {$currentUserId}
                                      WHERE uc.booking_id = c.booking_id
                                        AND uc.sender_id != {$currentUserId}
                                        AND uc.created_at > COALESCE(bh_read.chat_last_read_at, '0000-00-00 00:00:00')
                                        AND {$exclusionSql}
                                    ) AS un_read_chats")
                    ->join('orders o', 'o.id = c.booking_id')
                    ->join('booking_handymen bh', "bh.order_id = o.id AND bh.handyman_id = {$currentUserId}")
                    ->join('users us', 'us.id = o.user_id')
                    ->where('c.booking_id IS NOT NULL', null, false);
                $this->chatModel->excludeDeletedForUser($builder, $currentUserId, 'c');
                $builder->groupBy('c.booking_id')
                    ->orderBy('last_chat_date', 'DESC');
                $totalCustomersQuery1 = $builder->countAllResults(false);
                $customers_with_chats = $builder->get()->getResultArray();

                foreach ($customers_with_chats as $key => $row) {
                    $bookingStatus = $row['booking_status'] ?? '';
                    $customers_with_chats[$key]['translated_booking_status'] = getTranslatedValue($bookingStatus, 'panel');
                    $customers_with_chats[$key]['image'] = $fileService->url($row['image'] ?? '', 'profile');
                }

                // Handymen have no pre-booking enquiry chats
                $totalCustomersQuery2 = 0;
                $customer_pre_booking_queries = [];
            } else {
                $builder = $db->table('users u');
                $builder->select(' us.id as customer_id,
                                    us.username as customer_name,
                                    us.image as image,
                                    MAX(c.created_at) AS last_chat_date,
                                    c.booking_id,
                                    o.status as booking_status,
                                    (SELECT COUNT(*)
                                       FROM chats uc
                                      WHERE uc.booking_id = c.booking_id
                                        AND (
                                            (uc.receiver_id = ' . $currentUserId . ' AND uc.is_read = 0)
                                            OR (uc.sender_type = 3 AND uc.is_read_by_provider = 0)
                                        )
                                        AND ' . $exclusionSql . '
                                    ) AS un_read_chats')
                    ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 1) OR (c.receiver_id = u.id AND c.receiver_type = 1)")
                    ->join('orders o', "o.id = c.booking_id")
                    ->join('users us', "us.id = o.user_id")
                    ->where('o.partner_id', $this->user_details['id'])
                    ->groupStart()
                    ->where('c.sender_id', $currentUserId)
                    ->orWhere('c.receiver_id', $currentUserId)
                    ->groupEnd();
                $this->chatModel->excludeDeletedForUser($builder, $currentUserId, 'c');
                $builder->groupBy('c.booking_id')
                    ->orderBy('last_chat_date', 'DESC');
                $totalCustomersQuery1 = $builder->countAllResults(false);
                $customers_with_chats = $builder->get()->getResultArray();

                foreach ($customers_with_chats as $key => $row) {
                    $orderStatus = isset($row['order_status']) && !empty($row['order_status']) ? $row['order_status'] : '';
                    $bookingStatus = isset($row['booking_status']) && !empty($row['booking_status']) ? $row['booking_status'] : '';
                    if (!empty($orderStatus)) {
                        $customers_with_chats[$key]['translated_order_status'] = getTranslatedValue($orderStatus, 'panel');
                    }
                    $customers_with_chats[$key]['translated_booking_status'] = getTranslatedValue($bookingStatus, 'panel');
                    $customers_with_chats[$key]['image'] = $fileService->url($row['image'] ?? '', 'profile');
                }

                $builder1 = $db->table('users u');
                $builder1->select(' us.id as customer_id,
                                    us.username as customer_name,
                                    us.image as image,
                                    MAX(c.created_at) AS last_chat_date,
                                    c.booking_id,
                                    (SELECT COUNT(*)
                                       FROM chats uc
                                       JOIN enquiries ue ON ue.id = uc.e_id
                                      WHERE ue.provider_id = e.provider_id
                                        AND ue.customer_id = e.customer_id
                                        AND uc.booking_id IS NULL
                                        AND uc.receiver_id = ' . $currentUserId . '
                                        AND uc.is_read = 0
                                        AND ' . $exclusionSql . '
                                    ) AS un_read_chats')
                    ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 1) OR (c.receiver_id = u.id AND c.receiver_type = 1)")
                    ->join('enquiries e', "e.id = c.e_id")
                    ->join('users us', "us.id = e.customer_id")
                    ->where('e.provider_id', $this->user_details['id'])
                    ->groupStart()
                    ->where('c.sender_id', $currentUserId)
                    ->orWhere('c.receiver_id', $currentUserId)
                    ->groupEnd();
                $this->chatModel->excludeDeletedForUser($builder1, $currentUserId, 'c');
                $builder1->groupBy('e.customer_id')
                    ->orderBy('last_chat_date', 'DESC');
                $totalCustomersQuery2 = $builder1->countAllResults(false);
                $customer_pre_booking_queries = $builder1->get()->getResultArray();

                foreach ($customer_pre_booking_queries as $key => $row) {
                    $customer_pre_booking_queries[$key]['image'] = $fileService->url($row['image'] ?? '', 'profile');
                    $customer_pre_booking_queries[$key]['order_id'] = "";
                    $customer_pre_booking_queries[$key]['order_status'] = "";
                }
            }

            //note: If limit and offset are greater than total records, then array slice empty array is returned.
            $merged_array = array_merge($customers_with_chats, $customer_pre_booking_queries);
            $totalRecords = $totalCustomersQuery1 + $totalCustomersQuery2;

            // Filter by type if specified (similar to filter_type in get_chat_providers_list)
            if ($type === 'booking') {
                // Keep only chats that have a booking_id (booking-related chats)
                $merged_array = array_values(array_filter($merged_array, function ($chat) {
                    return (!empty($chat['booking_id']) && $chat['booking_id'] !== null);
                }));
                $totalRecords = count($merged_array);
            } elseif ($type === 'enquiry') {
                // Keep only chats without a booking_id (pre-booking / enquiry chats)
                $merged_array = array_values(array_filter($merged_array, function ($chat) {
                    return empty($chat['booking_id']);
                }));
                $totalRecords = count($merged_array);
            }

            // Aggregated count of customers (users) who have at least one unread chat
            // within the current filter (booking / enquiry / both).
            $usersWithUnreadChats = count(array_filter(
                $merged_array,
                static function ($chat) {
                    return (int) ($chat['un_read_chats'] ?? 0) > 0;
                }
            ));

            usort($merged_array, function ($a, $b) {
                return ($b['last_chat_date'] <=> $a['last_chat_date']);
            });

            $merged_array = array_slice($merged_array, $offset, $limit);

            return response_helper(
                labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '),
                false,
                $merged_array,
                200,
                [
                    'total' => $totalRecords,
                    'total_unread_users' => $usersWithUnreadChats,
                ]
            );
        } catch (\Throwable $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_chat_customers_list()');
            return $this->response->setJSON($response);
        }
    }

    public function delete_chat_user()
    {
        try {
            $current_user_id = (int) $this->user_details['id'];
            $receiver_id = $this->request->getPost('user_id');
            $booking_id = $this->request->getPost('booking_id');

            if (!empty($booking_id)) {
                $enquiry = fetch_details('enquiries', ['booking_id' => (int) $booking_id]);
                if (empty($enquiry[0])) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(CHAT_NOT_FOUND, 'Chat not found'),
                    ]);
                }
                $e_id = (int) $enquiry[0]['id'];
                $booking_id = (int) $booking_id;
            } else {
                $enquiry = fetch_details('enquiries', [
                    'provider_id' => $current_user_id,
                    'customer_id' => (int) $receiver_id,
                    'booking_id' => null,
                ]);
                if (empty($enquiry[0])) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(CHAT_NOT_FOUND, 'Chat not found'),
                    ]);
                }
                $e_id = (int) $enquiry[0]['id'];
                $booking_id = null;
            }

            $this->chatDeletionModel->clearConversationForUser($current_user_id, $e_id, $booking_id);

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(CHAT_DELETED_SUCCESSFULLY, 'Chat deleted successfully'),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Provider/ChatApiController.php - delete_chat_user()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    // Private Helper methods
    // Merges provider/customer/handyman context into customData (alongside total, is_blocked, etc.),
    // once per response, rather than per chat row. Keys omitted when their value is null.
    private function withChatExtras(array $customData, ?array $providerDetails, ?array $customerDetails = null, ?array $handymanDetails = null): array
    {
        if ($providerDetails !== null) {
            $customData['provider_details'] = $providerDetails;
        }
        if ($customerDetails !== null) {
            $customData['customer_details'] = $customerDetails;
        }
        if ($handymanDetails !== null) {
            $customData['handyman_details'] = $handymanDetails;
        }
        return $customData;
    }

    // Builds id/name/profile_image of the provider a handyman works under.
    private function buildProviderDetailsForHandyman(int $handymanId): ?array
    {
        $handymanDetails = fetch_details('handyman_details', ['handyman_id' => $handymanId], ['partner_id']);
        if (empty($handymanDetails)) {
            return null;
        }
        $providerId = (int) $handymanDetails[0]['partner_id'];

        $providerUser = fetch_details('users', ['id' => $providerId], ['id', 'username', 'image']);
        if (empty($providerUser)) {
            return null;
        }

        $providerDetailsRow = fetch_details('partner_details', ['partner_id' => $providerId], ['partner_id', 'company_name']);
        $companyName = $providerDetailsRow[0]['company_name'] ?? '';
        $name = (!empty($companyName))
            ? (get_translated_partner_field($providerId, 'company_name', $companyName) ?? $companyName)
            : $providerUser[0]['username'];

        $fileService = service('fileService');

        return [
            'id' => $providerId,
            'name' => $name,
            'profile_image' => $fileService->url($providerUser[0]['image'], 'profile'),
        ];
    }

    private function buildCustomerDetails(int $customerId): ?array
    {
        $customer = fetch_details('users', ['id' => $customerId], ['id', 'username', 'image']);
        if (empty($customer)) {
            return null;
        }
        $fileService = service('fileService');

        return [
            'id' => (int) $customer[0]['id'],
            'name' => $customer[0]['username'],
            'profile_image' => $fileService->url($customer[0]['image'], 'profile'),
        ];
    }

    // Current lead handyman for a booking, for provider-sent messages in a booking chat.
    // Only the lead is surfaced here even if the lead has changed since the chat started.
    private function buildHandymanDetailsForBooking(int $bookingId): ?array
    {
        $bookingHandymenModel = new \App\Models\BookingHandymenModel();
        $rows = $bookingHandymenModel->getAssignedHandymen($bookingId);
        $lead = null;
        foreach ($rows as $row) {
            if ((int) $row['is_lead'] === 1) {
                $lead = $row;
                break;
            }
        }

        if ($lead === null) {
            return null;
        }

        return [
            'id' => (int) $lead['id'],
            'name' => $lead['username'],
            'profile_image' => $lead['image'],
        ];
    }

    // Marks all unread chats for a given enquiry + (optional) booking
    // where the given user is the receiver.
    //
    // Booking chats are shared between the provider and their assigned handymen,
    // each side tracking its own read state separately from `receiver_id`/`is_read`:
    // - Provider reading a handyman-authored message (sender_type=3, receiver_id=customer)
    //   isn't caught by the receiver_id filter above, so it's synced via is_read_by_provider,
    //   mirroring Partner\Chats::mark_chat_as_read().
    // - Handyman unread state is tracked independently via booking_handymen.chat_last_read_at
    //   (BookingHandymenModel::markChatRead()), not chats.is_read at all.
    // Without this, reading a chat in the app leaves the panel showing it as unread.
    private function markChatsAsReadForReceiver($db, int $currentUserId, int $enquiryId, ?int $bookingId = null, bool $isHandyman = false): void
    {
        $builder = $db->table('chats');
        $builder->where('e_id', $enquiryId)
            ->where('receiver_id', $currentUserId)
            ->where('is_read', 0);

        if ($bookingId === null) {
            $builder->where('booking_id', null);
        } else {
            $builder->where('booking_id', $bookingId);
        }

        $builder->update([
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s'),
        ]);

        if ($bookingId === null) {
            return;
        }

        if ($isHandyman) {
            (new \App\Models\BookingHandymenModel())->markChatRead($bookingId, $currentUserId);
            return;
        }

        $ownsBooking = $db->table('orders')
            ->where('id', $bookingId)
            ->where('partner_id', $currentUserId)
            ->countAllResults() > 0;

        if ($ownsBooking) {
            $db->table('chats')
                ->where('booking_id', $bookingId)
                ->where('sender_type', 3)
                ->where('is_read_by_provider', 0)
                ->update(['is_read_by_provider' => 1]);
        }
    }
}
