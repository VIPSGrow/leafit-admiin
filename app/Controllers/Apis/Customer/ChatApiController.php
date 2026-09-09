<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Services\NotificationService;
use App\Libraries\JWT;

class ChatApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
        [
            "api/v1/index",
            "api/v1"
        ];
    protected \App\Models\ChatModel $chatModel;
    protected \App\Models\ChatDeletionModel $chatDeletionModel;

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $this->chatModel = new \App\Models\ChatModel();
        $this->chatDeletionModel = new \App\Models\ChatDeletionModel();
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

    public function send_chat_message()
    {
        try {
            // Grab request and file once
            $request = $this->request;
            // $attachment = $request->getFile('attachment');
            $message = $request->getPost('message') ?? "";
            $receiver_id = $request->getPost('receiver_id');
            $booking_id = $request->getPost('booking_id') ?? null;
            $sender_id = $this->user_details['id'];

            // Try to grab multiple files; fallback to single
            $attachments = $request->getFileMultiple('attachment');
            if (empty($attachments)) {
                $file = $request->getFile('attachment');
                $attachments = $file ? [$file] : [];
            }

            // Check if there's at least one valid file
            $hasAttachment = !empty($attachments) && $attachments[0]->isValid();

            // Only require message if no valid attachment
            if (!$hasAttachment) {
                $validation = \Config\Services::validation();
                $validation->setRules(['message' => 'required']);

                if (!$validation->withRequest($request)->run()) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => $validation->getErrors(),
                        'data' => [],
                    ]);
                }
            }

            // Handle receiver_id fallback (admin group?)
            if ($receiver_id === null) {
                $user_group = fetch_details('users_groups', ['group_id' => '1']);
                $receiver_id = end($user_group)['group_id'] ?? null;
            }

            // Determine receiver_type based on resolved receiver_id, don't trust client.
            // Must run after admin fallback above, else receiver_type stays null and DB insert fails
            // (receiver_type column NOT NULL).
            $receiver_type = $this->determineReceiverType($receiver_id) ?? 0;

            // Check if the sender is blocked by the receiver
            if ($receiver_id && is_user_blocked($sender_id, $receiver_id)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(USER_BLOCKED_MESSAGE, 'User blocked, cannot send messages'),
                    'data' => [],
                ]);
            }

            // Handle enquiry creation / fetch
            if ($booking_id) {
                $e_id = add_enquiry_for_chat("customer", $sender_id, true, $booking_id);
            } else {
                $criteria = [
                    'customer_id' => $sender_id,
                    'userType' => 2,
                    'booking_id' => null,
                ];

                if ($receiver_type == 1) {
                    $criteria['provider_id'] = $receiver_id;
                } else {
                    $criteria['provider_id'] = null;
                }

                $enquiry = fetch_details('enquiries', $criteria, ['id']);

                if (empty($enquiry[0])) {
                    $customer = fetch_details('users', ['id' => $sender_id], ['username'])[0] ?? [];
                    $data = [
                        'title' => ($customer['username'] ?? 'user') . '_query',
                        'status' => 1,
                        'userType' => 2,
                        'customer_id' => $sender_id,
                        'provider_id' => $criteria['provider_id'],
                        'date' => now(),
                    ];
                    $store = insert_details($data, 'enquiries');
                    $e_id = $store['id'];
                } else {
                    $e_id = $enquiry[0]['id'];
                }
            }

            // Last message timestamp
            $last_date = getLastMessageDateFromChat($e_id);

            // Attachment check
            $is_file = (!empty($attachments) && $attachments[0]->isValid());
            $attachment_data = $is_file ? $_FILES['attachment'] : null;

            // Insert message
            $data = insert_chat_message_for_chat(
                $sender_id,
                $receiver_id,
                $message,
                $e_id,
                2,
                $receiver_type,
                date('Y-m-d H:i:s'),
                $is_file,
                $attachment_data,
                $booking_id
            );

            // Build notification payload
            $notifType = $booking_id ? 'provider_booking' : ($receiver_type == 1 ? 'provider' : 'admin');
            $new_data = getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $data['id'], $last_date, $notifType);

            // Enrich chat response with booking/provider info so apps can render context instantly.
            $chatExtras = build_chat_message_details(
                $receiver_type == 1 ? (int) $receiver_id : null,
                $booking_id ? (int) $booking_id : null,
                $receiver_type !== null ? (int) $receiver_type : null,
                (int) $sender_id
            );
            $new_data = array_merge($new_data ?? [], $chatExtras);

            // Determine sender and receiver types for FCM notification template
            // receiver_type: 0 = admin, 1 = provider, 2 = customer
            // sender is always customer (from this API endpoint)
            // Extract sender name from sender_details (handle both array and object formats)
            $sender_name = 'Customer'; // Default fallback
            if (isset($new_data['sender_details'])) {
                if (is_array($new_data['sender_details']) && isset($new_data['sender_details'][0]) && is_array($new_data['sender_details'][0])) {
                    // Array format: [0 => ['username' => ...]]
                    $sender_name = $new_data['sender_details'][0]['username'] ?? 'Customer';
                } elseif (is_array($new_data['sender_details']) && isset($new_data['sender_details']['username'])) {
                    // Single array format: ['username' => ...]
                    $sender_name = $new_data['sender_details']['username'] ?? 'Customer';
                }
            }

            // Determine receiver type name
            $receiver_type_name = 'admin';
            if ($receiver_type == 1) {
                $receiver_type_name = 'provider';
            } elseif ($receiver_type == 2) {
                $receiver_type_name = 'customer';
            }

            // Get receiver name if available (handle both array and object formats)
            $receiver_name = '';
            if (isset($new_data['receiver_details']) && !empty($new_data['receiver_details'])) {
                if (is_array($new_data['receiver_details']) && isset($new_data['receiver_details'][0]) && is_array($new_data['receiver_details'][0])) {
                    // Array format: [0 => ['username' => ...]]
                    $receiver_name = $new_data['receiver_details'][0]['username'] ?? '';
                } elseif (is_array($new_data['receiver_details']) && isset($new_data['receiver_details']['username'])) {
                    // Single array format: ['username' => ...]
                    $receiver_name = $new_data['receiver_details']['username'] ?? '';
                }
            }

            // Build a single chat message payload (same structure as get_chat_history rows)
            // and serialize it so notification consumers can parse the full message context.
            $singleChatMessagePayload = json_encode($new_data ?? [], JSON_UNESCAPED_SLASHES);
            if ($singleChatMessagePayload === false) {
                $singleChatMessagePayload = '{}';
            }

            // Build a single customer payload with the same shape as get_chat_customers_list().
            $singleCustomerPayload = null;
            if ($this->user_details['id']) {
                $customer = fetch_details('users', ['id' => (int) $this->user_details['id']], ['id', 'username', 'image']);
                $bookingStatus = '';
                if (!empty($booking_id)) {
                    $orderRow = fetch_details('orders', ['id' => (int) $booking_id], ['status']);
                    $bookingStatus = $orderRow[0]['status'] ?? '';
                }

                $customerObject = [
                    'customer_id' => (string) $this->user_details['id'],
                    'customer_name' => $customer[0]['username'] ?? '',
                    'image' => $customer[0]['image'] ?? '',
                    'last_chat_date' => $data['created_at'] ?? $last_date,
                    'booking_id' => $booking_id ? (string) $booking_id : null,
                    'booking_status' => $bookingStatus,
                    'translated_booking_status' => !empty($bookingStatus) ? getTranslatedValue($bookingStatus, 'panel') : '',
                    'order_id' => '',
                    'order_status' => '',
                ];

                if (!empty($customerObject['image'])) {
                    $customerObject['image'] = service('fileService')->url($customerObject['image'], 'profile');
                }

                $singleCustomerPayload = json_encode($customerObject, JSON_UNESCAPED_SLASHES);
                if ($singleCustomerPayload === false) {
                    $singleCustomerPayload = null;
                }
            }

            // Send FCM notification using NotificationService
            // This works for all scenarios: customer to admin, customer to provider, etc.
            // We use only NotificationService for sending notifications (no old notification functions)
            try {
                // Detect demo mode using ALLOW_MODIFICATION flag.
                // In demo mode we still send FCM, but only to a small
                // subset of the most recent tokens to keep this API fast.
                $isDemoMode = (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION != 1);
                $language = get_current_language_from_request();

                // Prepare comprehensive context data for notification templates.
                // This includes all fields needed for auto-appending messages to the chat area.
                // NOTE:
                // - Keep keys simple and camelCase so that apps and panels can read them easily.
                // - Include all fields from $new_data and $data to ensure complete payload.
                $notificationContext = [
                    // Template variables for notification title/body (used by NotificationTemplateService)
                    'sender_name' => $sender_name,
                    'receiver_name' => $receiver_name,
                    'receiver_type' => $receiver_type_name,
                    'message_content' => $message,
                    'username' => $new_data['username'] ?? $new_data['sender_details']['username'] ?? '',

                    // Core chat message fields (required for auto-appending to chat)
                    'id' => (string) ($new_data['id'] ?? $data['id'] ?? ''),
                    'sender_id' => (string) $sender_id,
                    'receiver_id' => (string) $receiver_id,
                    'booking_id' => $booking_id ? (string) $booking_id : '',
                    'message' => $message,
                    'e_id' => (string) ($new_data['e_id'] ?? $e_id ?? ''),
                    'sender_type' => (string) ($new_data['sender_type'] ?? '2'),
                    'created_at' => (string) ($new_data['created_at'] ?? $data['created_at'] ?? date('Y-m-d H:i:s')),
                    'updated_at' => (string) ($new_data['updated_at'] ?? $data['updated_at'] ?? date('Y-m-d H:i:s')),
                    'last_message_date' => (string) $last_date,
                    'viewer_type' => (string) $notifType,

                    // File attachment fields
                    'file' => isset($new_data['file']) ? (is_array($new_data['file']) ? json_encode($new_data['file']) : $new_data['file']) : '',
                    'file_type' => (string) ($new_data['file_type'] ?? ($is_file ? (
                        is_array($_FILES['attachment']['name'] ?? null)
                        ? pathinfo($_FILES['attachment']['name'][0] ?? '', PATHINFO_EXTENSION)
                        : pathinfo($_FILES['attachment']['name'] ?? '', PATHINFO_EXTENSION)
                    ) : '')),

                    // User details fields

                    'image' => (string) ($new_data['sender_details']['image'] ?? ''),
                    'user_id' => (string) ($new_data['user_id'] ?? $sender_id),
                    'profile_image' => (string) ($new_data['sender_details']['image'] ?? ''),

                    // Sender and receiver details (JSON encoded for FCM payload)
                    'sender_details' => isset($new_data['sender_details']) ? json_encode($new_data['sender_details']) : json_encode([]),
                    'receiver_details' => isset($new_data['receiver_details']) ? json_encode($new_data['receiver_details']) : json_encode([]),
                    // Serialized rich context payloads for chat notification consumers.
                    'chat_message' => $singleChatMessagePayload,
                    'chat_user' => $singleCustomerPayload,

                    // Notification type
                    'type' => 'chat',
                ];



                // Add booking_id if present (legacy key used by some templates).
                if ($booking_id) {
                    $notificationContext['booking_id'] = (string) $booking_id;
                }

                // Attach enriched chat metadata so FCM payloads always contain:
                // bookingId, bookingStatus, companyName, translatedName,
                // receiverType, providerId, profile, senderId.
                // These values come from build_chat_message_details() which already
                // composes booking / provider metadata for chat responses.
                if (!empty($chatExtras) && is_array($chatExtras)) {
                    $notificationContext = array_merge($notificationContext, [
                        'bookingId' => $chatExtras['bookingId'] ?? null,
                        'bookingStatus' => $chatExtras['bookingStatus'] ?? null,
                        'companyName' => $chatExtras['companyName'] ?? null,
                        'translatedName' => $chatExtras['translatedName'] ?? null,
                        'receiverType' => $chatExtras['receiverType'] ?? null,
                        'providerId' => $chatExtras['providerId'] ?? null,
                        'profile' => $chatExtras['profile'] ?? null,
                        'senderId' => $chatExtras['senderId'] ?? null,
                        // Legacy fields for backward compatibility
                        'booking_status' => $chatExtras['bookingStatus'] ?? null,
                        'provider_id' => $chatExtras['providerId'] ?? null
                    ]);
                }

                // Determine platforms based on receiver type.
                //
                // IMPORTANT:
                // - Provider panel (web backend) saves its FCM token under platform: `provider_panel`
                //   (see: app/Controllers/partner/Partner.php::save_web_token()).
                // - If we do not include `provider_panel` here, providers logged into the panel
                //   will NEVER receive chat pushes, and `fcm.onMessage()` won't fire in the UI.
                //
                // receiver_type: 0 = admin, 1 = provider, 2 = customer
                $platforms = ['android', 'ios', 'web'];
                if ($receiver_type == 0) {
                    // Admin panel only
                    $platforms = ['admin_panel'];
                } elseif ($receiver_type == 1) {
                    // Provider: mobile apps + web provider panel
                    $platforms = ['android', 'ios', 'provider_panel'];
                }


                /**
                 * Send FCM notification using NotificationService (NO QUEUE).
                 *
                 * Why:
                 * - Chat notifications should feel real-time.
                 * - We want the same unified pipeline used elsewhere (templates, preferences, platforms).
                 * - This mirrors the direct send approach in: app/Controllers/partner/Chats.php
                 */
                // In demo mode, do not actually send FCM notifications.
                // This avoids slow responses caused by huge FCM token lists,
                // while still returning the chat payload so the UI works normally.
                if (!$isDemoMode) {
                    // Build base options for NotificationService.
                    $sendOptions = [
                        'channels' => ['fcm'], // FCM only for chat message alerts.
                        'language' => $language,
                        'platforms' => $platforms,
                        // Force raw data payload to include the full chat context.
                        'data' => $notificationContext,
                        // Keep priority high to match the previous queued behavior.
                        'priority' => 'high',
                    ];

                    // In demo mode, tell the FCM provider to limit the number
                    // of FCM tokens it uses per language to avoid very large
                    // sends causing slow responses. We keep only the latest 20.
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
                     * Notify every handyman assigned to this booking.
                     *
                     * Why:
                     * - The handyman panel booking chat thread is shared between the
                     *   provider and all assigned handymen (they all view/reply to the
                     *   same customer conversation, scoped by booking_id).
                     * - The send above only targets the provider account (receiver_id),
                     *   so without this, handymen never get pushed for new customer
                     *   messages and `fcm.onMessage()` never fires in their panel.
                     * - Each handyman gets their own notification (receiver_id swapped
                     *   to their own user id) so per-user FCM token lookups resolve
                     *   correctly and the panel's foreground handler can match against
                     *   its own session user id.
                     */
                    if ($booking_id) {
                        $assignedHandymanIds = (new \App\Models\BookingHandymenModel())->getAssignedHandymanIds((int) $booking_id);
                        foreach ($assignedHandymanIds as $handymanId) {
                            $handymanContext = array_merge($notificationContext, [
                                'receiver_id' => (string) $handymanId,
                                'viewer_type' => 'handyman_booking',
                            ]);
                            $handymanSendOptions = $sendOptions;
                            $handymanSendOptions['data'] = $handymanContext;
                            $handymanSendOptions['platforms'] = ['android', 'ios', 'handyman_panel'];

                            $notificationService->send(
                                'new_message',
                                ['user_id' => $handymanId],
                                $handymanContext,
                                $handymanSendOptions
                            );
                        }
                    }
                }
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the message sending
                log_message('error', '[NEW_MESSAGE] FCM notification error trace: ' . $notificationError->getTraceAsString());
            }

            return response_helper(labels(SENT_MESSAGE_SUCCESSFULLY, 'Sent message successfully'), false, $new_data ?? [], 200);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - send_chat_message()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
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
            $limit = $this->request->getPost('limit') ?? '5';
            $offset = $this->request->getPost('offset') ?? '0';
            $current_user_id = $this->user_details['id'];


            $user_report = fetch_details('user_reports', [
                'reporter_id' => $current_user_id,
                'reported_user_id' => $this->request->getPost('provider_id')
            ]);
            $is_block_by_user = !empty($user_report) ? 1 : 0;

            // Check if provider blocked user
            $provider_report = fetch_details('user_reports', [
                'reporter_id' => $this->request->getPost('provider_id'),
                'reported_user_id' => $current_user_id
            ]);
            $is_block_by_provider = !empty($provider_report) ? 1 : 0;


            // Set overall blocked status
            $is_blocked = $is_block_by_user == 1 ? 1 : 0;

            $db = \Config\Database::connect();
            if ($type == "0") {
                $e_id_data = fetch_details('enquiries', ['customer_id' => $current_user_id, 'userType' => 2, 'provider_id' => null, 'booking_id' => null]);
                if (!empty($e_id_data)) {
                    $e_id = $e_id_data[0]['id'];

                    // Mark unread admin <-> customer chats as read when user opens admin chat history.
                    // This mirrors the behaviour for type = 1 (provider chats) where we mark
                    // customer-received messages as read as soon as history is fetched.
                    $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null);

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

                    return response_helper(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), false, $chat_record, 200, ['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                } else {
                    return response_helper(labels(NO_DATA_FOUND, 'No data Found'), false, [], 200, ['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                }
            } else if ($type == "1") {
                $booking_id = $this->request->getPost('booking_id');
                if ($booking_id == null) {
                    $enquiry = fetch_details('enquiries', ['customer_id' => $current_user_id, 'userType' => 2, 'booking_id' => NULL, 'provider_id' => $this->request->getPost('provider_id')]);
                } else {
                    $enquiry = fetch_details('enquiries', ['customer_id' => $current_user_id, 'userType' => 2, 'booking_id' => $booking_id]);
                }
                if (!empty($enquiry)) {
                    if ($enquiry[0]['booking_id'] != null) {
                        $e_id = $enquiry[0]['id'];
                        $booking_id = $enquiry[0]['booking_id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, (int) $booking_id);
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
                        $handymanDetails = $this->buildHandymanDetailsForBooking((int) $booking_id);
                        foreach ($chat_record as $key => $row) {
                            $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'provider_booking', 'yes');
                            $chat_record[$key]['sender_details'] = $new_data['sender_details'];
                            $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
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
                        return response_helper(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), false, $chat_record, 200, $this->withChatExtras(['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider], $handymanDetails));
                    } else {
                        $e_id = $enquiry[0]['id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null);
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
                        foreach ($chat_record as $key => $row) {
                            $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'provider_booking', 'yes');
                            $chat_record[$key]['sender_details'] = $new_data['sender_details'];
                            $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
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
                        return response_helper(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), false, $chat_record, 200, ['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                    }
                } else {
                    return response_helper(labels(NO_DATA_FOUND, 'No data Found'), false, [], 200, ['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                }
            }
        } catch (\Throwable $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_chat_history()');
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

            if ($type == "0") {
                // Admin chat: mark messages as read where receiver is current user
                $e_id_data = fetch_details('enquiries', ['customer_id' => $current_user_id, 'userType' => 2, 'provider_id' => null, 'booking_id' => null]);
                if (!empty($e_id_data)) {
                    $e_id = $e_id_data[0]['id'];
                    $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null);
                }
            } else if ($type == "1") {
                $booking_id = $this->request->getPost('booking_id');
                if ($booking_id == null) {
                    $enquiry = fetch_details('enquiries', ['customer_id' => $current_user_id, 'userType' => 2, 'booking_id' => NULL, 'provider_id' => $this->request->getPost('provider_id')]);
                } else {
                    $enquiry = fetch_details('enquiries', ['customer_id' => $current_user_id, 'userType' => 2, 'booking_id' => $booking_id]);
                }
                if (!empty($enquiry)) {
                    $e_id = $enquiry[0]['id'];
                    $b_id = $enquiry[0]['booking_id'] ?? null;
                    $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, $b_id !== null ? (int) $b_id : null);
                }
            }

            return response_helper(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), false, [], 200);
        } catch (\Throwable $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - mark_message_as_read()');
            return $this->response->setJSON($response);
        }
    }

    public function get_chat_providers_list()
    {
        try {
            $currentUserId = (int) $this->user_details['id'];
            $limit = $this->request->getPost('limit') ?? 5;
            $offset = $this->request->getPost('offset') ?? 0;
            $filter_type = $this->request->getPost('filter_type') ?? null; // 'booking' or 'pre_booking'
            $order_status_filter = $this->request->getPost('order_status') ?? null; // New filter
            $db = \Config\Database::connect();
            $exclusionSql = $this->chatModel->getExclusionSql((int) $currentUserId, 'uc');
            // ------------------ FETCH BOOKING-RELATED CHATS ------------------
            $builder = $db->table('users u');
            $builder->select('u.id, MAX(c.created_at) AS last_chat_date, 
                             c.booking_id, o.status as order_status, 
                             pd.partner_id, pd.company_name as partner_name, ps.image,
                             (SELECT COUNT(*) FROM chats uc WHERE uc.booking_id = c.booking_id AND uc.receiver_id = ' . $currentUserId . ' AND uc.is_read = 0 AND ' . $exclusionSql . ') AS un_read_chats')
                ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 2) OR (c.receiver_id = u.id AND c.receiver_type = 2)")
                ->join('orders o', "o.id = c.booking_id")
                ->join('partner_details pd', "pd.partner_id = o.partner_id")
                ->join('users ps', "ps.id = pd.partner_id")
                ->where('o.user_id', $this->user_details['id']);
            $this->chatModel->excludeDeletedForUser($builder, (int) $currentUserId, 'c');
            $builder->groupBy('c.booking_id')
                ->orderBy('last_chat_date', 'DESC');
            $bookingChats = $builder->get()->getResultArray();

            // Add blocking info after fetching chats
            foreach ($bookingChats as &$chat) {
                $chat['translated_order_status'] = getTranslatedValue($chat['order_status'], 'panel');
                $user_report = fetch_details('user_reports', ['reporter_id' => $this->user_details['id'], 'reported_user_id' => $chat['partner_id']], ['id']);
                $provider_report = fetch_details('user_reports', ['reporter_id' => $chat['partner_id'], 'reported_user_id' => $this->user_details['id']], ['id']);
                $chat['is_blocked'] = (!empty($user_report) || !empty($provider_report)) ? 1 : 0;
                $chat['is_block_by_user'] = !empty($user_report) ? 1 : 0;
                $chat['is_block_by_provider'] = !empty($provider_report) ? 1 : 0;
            }
            unset($chat);

            // ------------------ FETCH PRE-BOOKING CHATS ------------------
            $subquery = $db->table('users u')
                ->select('u.id, MAX(c.created_at) AS last_chat_date, 
                         c.booking_id, pd.partner_id, pd.company_name as partner_name, ps.image,
                         (SELECT COUNT(*) 
                            FROM chats uc 
                            JOIN enquiries ue ON ue.id = uc.e_id 
                           WHERE ue.provider_id = e.provider_id 
                             AND ue.customer_id = ' . $currentUserId . ' 
                             AND uc.booking_id IS NULL 
                             AND uc.receiver_id = ' . $currentUserId . ' 
                             AND uc.is_read = 0 AND ' . $exclusionSql . ') AS un_read_chats')
                ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 2) OR (c.receiver_id = u.id AND c.receiver_type = 2)")
                ->join('enquiries e', "e.id = c.e_id")
                ->join('partner_details pd', "pd.partner_id = e.provider_id")
                ->join('users ps', "ps.id = pd.partner_id")
                ->where('e.customer_id', $this->user_details['id']);
            $this->chatModel->excludeDeletedForUser($subquery, (int) $currentUserId, 'c');
            $subquery->groupBy('e.provider_id')
                ->orderBy('last_chat_date', 'DESC');
            $preBookingChats = $subquery->get()->getResultArray();

            // Add blocking info after fetching pre-booking chats
            foreach ($preBookingChats as &$chat) {
                $user_report = fetch_details('user_reports', ['reporter_id' => $this->user_details['id'], 'reported_user_id' => $chat['partner_id']], ['id']);
                $provider_report = fetch_details('user_reports', ['reporter_id' => $chat['partner_id'], 'reported_user_id' => $this->user_details['id']], ['id']);
                $chat['is_blocked'] = (!empty($user_report) || !empty($provider_report)) ? 1 : 0;
                $chat['is_block_by_user'] = !empty($user_report) ? 1 : 0;
                $chat['is_block_by_provider'] = !empty($provider_report) ? 1 : 0;
                $chat['order_status'] = null;
            }
            unset($chat);

            // ------------------ MERGE ALL CHATS ------------------
            $mergedChats = array_merge($bookingChats, $preBookingChats);

            // ------------------ FORMAT IMAGE PATHS AND ADD TRANSLATIONS ------------------
            foreach ($mergedChats as &$chat) {
                // Add translation support for partner names
                if (!empty($chat['partner_id'])) {
                    $partnerData = [
                        'company_name' => $chat['partner_name'] ?? '',
                        'about' => '',
                        'long_description' => '',
                    ];
                    $translatedData = get_translated_partner_data_for_api($chat['partner_id'], $partnerData);
                    $chat['partner_name'] = $translatedData['company_name'];
                    $chat['translated_partner_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                }

                $chat['image'] = service('fileService')->url($chat['image'] ?? '', 'profile');
            }
            unset($chat);

            // ------------------ APPLY FILTERS ------------------
            if ($filter_type === 'booking') {
                $mergedChats = array_values(array_filter($mergedChats, function ($chat) {
                    return (!empty($chat['booking_id']) && $chat['booking_id'] !== null);
                }));
            } elseif ($filter_type === 'pre_booking') {
                $mergedChats = array_values(array_filter($mergedChats, function ($chat) {
                    return empty($chat['booking_id']);
                }));
            }

            if (!is_null($order_status_filter)) {
                $mergedChats = array_values(array_filter($mergedChats, function ($chat) use ($order_status_filter) {
                    return isset($chat['order_status']) && $chat['order_status'] == $order_status_filter;
                }));
            }

            // ------------------ SORT CHATS BY LAST CHAT DATE ------------------
            usort($mergedChats, function ($a, $b) {
                return strtotime($b['last_chat_date']) <=> strtotime($a['last_chat_date']);
            });

            // ------------------ PAGINATION & UNREAD COUNTS ------------------
            $totalRecords = count($mergedChats);

            // Total unread chats across all visible providers after filters.
            $totalUnreadChats = array_sum(array_map(static fn($chat) => (int) ($chat['un_read_chats'] ?? 0), $mergedChats));

            // Aggregated count of providers (users) who have at least one unread chat.
            $usersWithUnreadChats = count(array_filter(
                $mergedChats,
                static fn($chat) => (int) ($chat['un_read_chats'] ?? 0) > 0
            ));

            $mergedChats = array_slice($mergedChats, $offset, $limit);

            return response_helper(
                labels(CHAT_RETRIEVED_SUCCESSFULLY, 'Chats retrieved successfully'),
                false,
                $mergedChats,
                200,
                [
                    'total' => $totalRecords,
                    'un_read_chats' => $totalUnreadChats,
                    'total_unread_users' => $usersWithUnreadChats,
                ]
            );
        } catch (\Throwable $th) {
            // throw $th;
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')]);
        }
    }

    public function delete_chat_user()
    {
        try {
            $current_user_id = (int) $this->user_details['id'];
            $receiver_id = $this->request->getPost('partner_id');
            $booking_id = $this->request->getPost('booking_id');

            if (!empty($booking_id)) {
                $enquiry = fetch_details('enquiries', ['booking_id' => (int) $booking_id, 'customer_id' => $current_user_id]);
                if (empty($enquiry[0])) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                    ]);
                }
                $e_id = (int) $enquiry[0]['id'];
                $booking_id = (int) $booking_id;
            } else {
                $criteria = [
                    'customer_id' => $current_user_id,
                    'userType' => 2,
                    'booking_id' => null,
                ];
                if (!empty($receiver_id)) {
                    $criteria['provider_id'] = (int) $receiver_id;
                } else {
                    $criteria['provider_id'] = null;
                }
                $enquiry = fetch_details('enquiries', $criteria);
                if (empty($enquiry[0])) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                    ]);
                }
                $e_id = (int) $enquiry[0]['id'];
                $booking_id = null;
            }

            $this->chatDeletionModel->clearConversationForUser($current_user_id, $e_id, $booking_id);

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(CHAT_DELETED_SUCCESSFULLY, 'Chat Deleted Successfully'),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Customer/ChatApiController.php - delete_chat_user()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    //Private helper methods
    // Merges handyman context into customData, once per response (mirrors Apis\Provider\ChatApiController).
    private function withChatExtras(array $customData, ?array $handymanDetails = null): array
    {
        if ($handymanDetails !== null) {
            $customData['handyman_details'] = $handymanDetails;
        }
        return $customData;
    }

    // Current lead handyman for a booking, so customer app can render who they're chatting with.
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
    private function markChatsAsReadForReceiver($db, int $currentUserId, int $enquiryId, ?int $bookingId = null): void
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
    }

    private function determineReceiverType(?int $receiver_id): ?int
    {
        if ($receiver_id === null) {
            return null;
        }

        $groupRow = fetch_details('users_groups', ['user_id' => $receiver_id], ['group_id']);
        if (empty($groupRow[0])) {
            return null;
        }

        $groupId = (int) $groupRow[0]['group_id'];
        if ($groupId === 1) {
            return 0; // admin
        } elseif ($groupId === 3) {
            return 1; // provider
        }

        return null;
    }
}
