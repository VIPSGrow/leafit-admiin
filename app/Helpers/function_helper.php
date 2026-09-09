<?php

use App\Libraries\Flutterwave;
use App\Libraries\Cashfree;
use App\Libraries\Paypal;
use App\Libraries\Paystack;
use App\Libraries\Paytm;
use App\Libraries\Razorpay;
use App\Libraries\Stripe;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\ApiResponseAndNotificationStrings;
use Google\Client;
use Razorpay\Api\Api;

function update_balance($amount, $partner_id, $action)
{
    $db = \Config\Database::connect();
    $builder = $db->table('users');
    if ($action == "add") {
        $builder->set('balance', 'balance+' . $amount, false);
    } elseif ($action == "deduct") {
        $builder->set('balance', 'balance-' . $amount, false);
    }
    return $builder->where('id', $partner_id)->update();
}

/**
 * Normalize folder names to match the format used in sample_panel.json
 * Converts folder names to lowercase and replaces spaces with underscores
 * 
 * @param string $folderName The original folder name
 * @return string The normalized folder name
 */
function normalize_folder_name($folderName)
{
    // Convert to lowercase and replace spaces with underscores
    $normalized = strtolower(str_replace(' ', '_', $folderName));

    // Handle special cases for common folder names
    $specialCases = [
        'chat_attachement' => 'chat_attachment',
        'provider_bulk_upload' => 'provider_bulk_upload',
        'featured_section' => 'featured_section',
        'seo_settings' => 'seo_settings',
        'web_settings' => 'web_settings',
        'address_id' => 'address_id',
        'country_flags' => 'country_flags',
        'national_id' => 'national_id',
        'provider_work_evidence' => 'provider_work_evidence'
    ];

    // Return special case if exists, otherwise return normalized name
    return $specialCases[$normalized] ?? $normalized;
}

/**
 * Returns password requirement rules from login_settings (Authentication Settings).
 * Used by views to show conditional rules and by validate_password_strength for server-side validation.
 *
 * @return array min_length (int), require_uppercase, require_lowercase, require_number, require_special (each 0|1)
 */
function get_password_rules()
{
    $loginSettings = get_settings('login_settings', true);
    if (!is_array($loginSettings)) {
        $loginSettings = [];
    }
    $minLength = isset($loginSettings['minimum_password_length']) ? (int) $loginSettings['minimum_password_length'] : 8;
    return [
        'min_length' => $minLength,
        'require_uppercase' => !empty($loginSettings['require_at_least_one_uppercase']) ? 1 : 0,
        'require_lowercase' => !empty($loginSettings['require_at_least_one_lowercase']) ? 1 : 0,
        'require_number' => !empty($loginSettings['require_at_least_one_number']) ? 1 : 0,
        'require_special' => !empty($loginSettings['require_at_least_one_special_character']) ? 1 : 0,
    ];
}

/**
 * Validates password strength using Authentication Settings (login_settings).
 * When minimum length is 0, no character rules apply. Otherwise only enabled rules are checked.
 *
 * @param string $password The password to validate
 * @param array|null $rules Optional rules from get_password_rules(); if null, loaded from settings
 * @return array List of error messages (empty if valid)
 */
function validate_password_strength($password, $rules = null)
{
    $errors = [];
    $password = (string) $password;

    if ($rules === null) {
        $rules = get_password_rules();
    }

    $minLength = (int) ($rules['min_length'] ?? 8);
    // When min length is 0, no length or character restrictions apply
    if ($minLength === 0) {
        return $errors;
    }

    if (strlen($password) < $minLength) {
        $msg = labels('password_min_length_n', 'Password must be at least %s characters');
        $errors[] = (strpos($msg, '%s') !== false) ? sprintf($msg, $minLength) : $msg;
    }
    if (!empty($rules['require_number']) && !preg_match('/\d/', $password)) {
        $errors[] = labels('password_strength_contains_number', 'Contains a number');
    }
    if (!empty($rules['require_uppercase']) && !preg_match('/[A-Z]/', $password)) {
        $errors[] = labels('password_strength_contains_uppercase', 'Contains an uppercase letter');
    }
    if (!empty($rules['require_lowercase']) && !preg_match('/[a-z]/', $password)) {
        $errors[] = labels('password_strength_contains_lowercase', 'Contains a lowercase letter');
    }
    if (!empty($rules['require_special']) && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $password)) {
        $errors[] = labels('password_strength_contains_special', 'Contains a special character');
    }

    return $errors;
}

function check_cancelable($date_of_service, $starting_time, $cancellable_befor_min)
{
    $today = strtotime(date('y-m-d H:i'));
    $format_date = date('y-m-d H:i', strtotime("$date_of_service $starting_time"));
    $service_date = strtotime($format_date);
    if ($service_date >= $today) {
        $i = ($service_date - $today) / 60;
        if (intval($cancellable_befor_min) > $i) {
            return false;
        } else {
            return true;
        }
    }
}

/**
 * Get booking status event type for notifications
 * 
 * Maps booking status to status-specific notification event type.
 * Returns null for "awaiting" status (default initial status, no notification needed)
 * and for invalid/unsupported statuses.
 * 
 * @param string $status Booking status (confirmed, rescheduled, cancelled, completed, started, booking_ended)
 * @return string|null Event type for notification (e.g., 'booking_confirmed') or null if no notification needed
 */
function get_booking_status_event_type($status)
{
    // Map booking status to status-specific event type
    // Status changes never go backwards, so "awaiting" will never be passed as a status change
    $statusMap = [
        'confirmed' => 'booking_confirmed',
        'rescheduled' => 'booking_rescheduled',
        'cancelled' => 'booking_cancelled',
        'completed' => 'booking_completed',
        'started' => 'booking_started',
        'booking_ended' => 'booking_ended',
        'on_the_way' => 'booking_on_the_way',
        'arrived' => 'booking_arrived',
        'awaiting' => null // Safety check - status never changes to awaiting
    ];

    return $statusMap[$status] ?? null;
}

/**
 * Send booking status change notifications (recipient depends on actor)
 * 
 * This function sends notifications when booking status changes.
 * Notifications are sent via NotificationService for all channels (FCM, Email, SMS).
 * Recipient rules:
 * - Provider updated => notify customer + admin
 * - Customer updated => notify provider + admin
 * - Admin/system updated => notify customer + provider + admin
 * 
 * @param int $order_id Order/booking ID
 * @param string $status New booking status
 * @param string $translated_status Translated status text
 * @param string $previous_status Previous booking status
 * @param string $languageCode Language code for notifications
 * @param int|null $user_id Actor user ID who updated the status (recommended)
 * @param array|null $additional_charges Additional charges data (optional, for booking_ended status)
 * @return void
 */
function send_booking_status_notifications($order_id, $status, $translated_status, $previous_status, $languageCode, $user_id = null, $additional_charges = null)
{
    // Get status-specific event type for notifications
    // Only send notifications if status is not "awaiting" (helper returns null for awaiting)
    $eventType = get_booking_status_event_type($status);

    // Only send notifications if we have a valid event type (not awaiting or invalid status)
    if (empty($eventType)) {
        log_message('info', '[BOOKING_STATUS] No notification sent for status: ' . $status . ' (awaiting or invalid status)');
        return;
    }

    try {
        // Get order details for notification context
        $db = \Config\Database::connect();
        $order_details = fetch_details('orders', ['id' => $order_id]);

        if (empty($order_details) || empty($order_details[0])) {
            log_message('error', '[' . strtoupper($eventType) . '] Order not found: ' . $order_id);
            return;
        }

        $order = $order_details[0];
        $customer_id = $order['user_id'];
        $provider_id = $order['partner_id'] ?? null;

        // Get customer details
        $usersEmail = fetch_details('users', ['id' => $customer_id], ['email', 'username']);
        $customer_name = !empty($usersEmail) && !empty($usersEmail[0]['username']) ? $usersEmail[0]['username'] : 'Customer';

        // Get provider details
        $providerName = 'Provider';
        if (!empty($provider_id)) {
            $providerName = get_translated_partner_field($provider_id, 'company_name');
            if (empty($providerName)) {
                $partner_data = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
                $providerName = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
            }
        }

        // Determine who updated the status
        $updated_by = $user_id ?? $provider_id ?? $customer_id;
        $updated_by_name = '';
        $updated_by_type = 'system';

        // Check if updated by admin
        if (!empty($user_id)) {
            $admin_check = fetch_details('users_groups', ['user_id' => $user_id, 'group_id' => 1]);
            if (!empty($admin_check)) {
                $admin_details = fetch_details('users', ['id' => $user_id], ['username']);
                $updated_by_name = !empty($admin_details) && !empty($admin_details[0]['username']) ? $admin_details[0]['username'] : 'Admin';
                $updated_by_type = 'admin';
            } else {
                // Check if updated by provider
                $provider_check = fetch_details('partner_details', ['partner_id' => $user_id]);
                if (!empty($provider_check)) {
                    $updated_by_name = $providerName;
                    $updated_by_type = 'provider';
                } else {
                    // Check if updated by handyman
                    $handyman_check = fetch_details('handyman_details', ['handyman_id' => $user_id]);
                    if (!empty($handyman_check)) {
                        $handyman_details = fetch_details('users', ['id' => $user_id], ['username']);
                        $updated_by_name = !empty($handyman_details) && !empty($handyman_details[0]['username']) ? $handyman_details[0]['username'] : 'Handyman';
                        $updated_by_type = 'handyman';
                    } else {
                        // Updated by customer
                        $updated_by_name = $customer_name;
                        $updated_by_type = 'customer';
                    }
                }
            }
        } else {
            // Default to provider if user_id not provided (legacy behavior)
            $updated_by_name = $providerName;
            $updated_by_type = 'provider';
        }

        // Get currency from settings
        $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

        // Format date of service
        $date_of_service = !empty($order['date_of_service']) ? date('d-m-Y', strtotime($order['date_of_service'])) : '';
        $service_time = '';
        if (!empty($order['starting_time']) && !empty($order['ending_time'])) {
            $service_time = date('h:i A', strtotime($order['starting_time'])) . ' - ' . date('h:i A', strtotime($order['ending_time']));
        } elseif (!empty($order['starting_time'])) {
            $service_time = date('h:i A', strtotime($order['starting_time']));
        }

        // Build status message
        $status_message = 'The booking status has been updated.';
        if ($status == "booking_ended" && !empty($additional_charges) && ($additional_charges[0]['name'] != "" && $additional_charges[0]['charge'] != "")) {
            $status_message = 'The booking has ended and additional charges have been added.';
        }

        // Prepare base context data for notification templates
        $baseContext = [
            'booking_id' => (string) $order_id,
            'order_id' => (string) $order_id,
            'booking_status' => $translated_status,
            'status_message' => $status_message,
            'previous_status' => $previous_status,
            'updated_by' => (string) $updated_by,
            'updated_by_name' => $updated_by_name,
            'updated_by_type' => $updated_by_type,
            'customer_id' => (string) $customer_id,
            'customer_name' => $customer_name,
            'provider_id' => !empty($provider_id) ? (string) $provider_id : '',
            'provider_name' => $providerName,
            'date_of_service' => $date_of_service,
            'service_time' => $service_time,
            'final_total' => number_format($order['final_total'] ?? 0, 2),
            'currency' => $currency
        ];

        // Decide who should receive the status update notifications.
        //
        // Business rule (requested):
        // - If provider updates status => notify ONLY customer + admin.
        // - If customer updates status => notify ONLY provider + admin.
        // - If admin/system updates status => notify customer + provider + admin (legacy behavior).
        //
        // NOTE:
        // `updated_by_type` is derived from `$user_id` (the actor) when controllers pass it.
        // If controllers do not pass `$user_id`, this function defaults to "provider" (legacy).
        $notifyCustomer = true;
        $notifyProvider = !empty($provider_id);
        if ($updated_by_type === 'provider') {
            $notifyProvider = false;
        } elseif ($updated_by_type === 'customer') {
            $notifyCustomer = false;
        }

        // Send notification to customer (if allowed)
        if ($notifyCustomer) {
            $customerContext = array_merge($baseContext, [
                'recipient_name' => $customer_name,
                'recipient_type' => 'customer'
            ]);
            queue_notification_service(
                eventType: $eventType,
                recipients: ['user_id' => $customer_id],
                context: $customerContext,
                options: [
                    'channels' => ['fcm', 'email', 'sms'],
                    'language' => $languageCode,
                    'platforms' => ['android', 'ios', 'web'],
                    'type' => 'booking_status',
                    'data' => [
                        'order_id' => (string) $order_id,
                        'booking_id' => (string) $order_id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to' => 'booking_details_screen'
                    ]
                ]
            );
            // log_message('info', '[' . strtoupper($eventType) . '] Customer notification queued: ' . $customer_id . ', Result: ' . json_encode($customerResult));
        }

        // Send notification to provider (if allowed and provider exists)
        if ($notifyProvider && !empty($provider_id)) {
            $providerContext = array_merge($baseContext, [
                'recipient_name' => $providerName,
                'recipient_type' => 'provider'
            ]);
            queue_notification_service(
                eventType: $eventType,
                recipients: ['user_id' => $provider_id],
                context: $providerContext,
                options: [
                    'channels' => ['fcm', 'email', 'sms'],
                    'language' => $languageCode,
                    'platforms' => ['android', 'ios', 'web', 'provider_panel'],
                    'type' => 'booking_status',
                    'data' => [
                        'order_id' => (string) $order_id,
                        'booking_id' => (string) $order_id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to' => 'booking_details_screen'
                    ]
                ]
            );
            // log_message('info', '[' . strtoupper($eventType) . '] Provider notification queued: ' . $provider_id . ', Result: ' . json_encode($providerResult));
        }

        // Send push-only notification to every handyman assigned to this booking,
        // unless the handyman themselves is the actor (avoid self-notifying).
        if ($updated_by_type !== 'handyman') {
            $bookingHandymenModel = model(\App\Models\BookingHandymenModel::class);
            $handymanIds = $bookingHandymenModel->getAssignedHandymanIds((int) $order_id);
            if (!empty($handymanIds)) {
                $handymanContext = array_merge($baseContext, [
                    'recipient_name' => 'Handyman',
                    'recipient_type' => 'handyman'
                ]);
                queue_notification_service(
                    eventType: $eventType,
                    recipients: [],
                    context: $handymanContext,
                    options: [
                        'channels' => ['fcm'],
                        'language' => $languageCode,
                        'user_ids' => $handymanIds,
                        'type' => 'booking_status',
                        'data' => [
                            'order_id' => (string) $order_id,
                            'booking_id' => (string) $order_id,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'redirect_to' => 'booking_details_screen'
                        ]
                    ]
                );
            }
        }

        // Send notification to admin users (group_id = 1)
        // $adminContext = array_merge($baseContext, [
        //     'recipient_name' => 'Admin',
        //     'recipient_type' => 'admin'
        // ]);
        // queue_notification_service(
        //     eventType: $eventType,
        //     recipients: [],
        //     context: $adminContext,
        //     options: [
        //         'channels' => ['fcm', 'email', 'sms'],
        //         'language' => $languageCode,
        //         'user_groups' => [1], // Admin user group
        //         'platforms' => ['admin_panel'],
        //         'type' => 'booking_status',
        //         'data' => [
        //             'order_id' => (string)$order_id,
        //             'booking_id' => (string)$order_id,
        //             'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        //             'redirect_to' => 'booking_details_screen'
        //         ]
        //     ]
        // );
        // log_message('info', '[' . strtoupper($eventType) . '] Admin notification queued, Result: ' . json_encode($adminResult));
    } catch (\Throwable $notificationError) {
        // Log error but don't fail the booking status update
        log_message('error', '[' . strtoupper($eventType) . '] Notification error trace: ' . $notificationError->getTraceAsString());
    }
}

/**
 * Send a push-only 'assigned'/'unassigned' notification to one or more handymen
 * for a booking. Used when a provider or admin assigns/unassigns a handyman.
 *
 * @param int $order_id Order/booking ID
 * @param array $handyman_ids Handyman user IDs to notify
 * @param string $event_key 'booking_assigned' or 'booking_unassigned'
 * @param string|null $languageCode Language code for notifications
 * @return void
 */
function notify_handyman_assignment_change(int $order_id, array $handyman_ids, string $event_key, ?string $languageCode = null): void
{
    if (empty($handyman_ids)) {
        return;
    }

    try {
        $order_details = fetch_details('orders', ['id' => $order_id]);
        if (empty($order_details) || empty($order_details[0])) {
            log_message('error', '[' . strtoupper($event_key) . '] Order not found: ' . $order_id);
            return;
        }

        $order = $order_details[0];
        $provider_id = $order['partner_id'] ?? null;

        $providerName = 'Provider';
        if (!empty($provider_id)) {
            $providerName = get_translated_partner_field($provider_id, 'company_name');
            if (empty($providerName)) {
                $partner_data = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
                $providerName = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
            }
        }

        $date_of_service = !empty($order['date_of_service']) ? date('d-m-Y', strtotime($order['date_of_service'])) : '';
        $service_time = '';
        if (!empty($order['starting_time']) && !empty($order['ending_time'])) {
            $service_time = date('h:i A', strtotime($order['starting_time'])) . ' - ' . date('h:i A', strtotime($order['ending_time']));
        } elseif (!empty($order['starting_time'])) {
            $service_time = date('h:i A', strtotime($order['starting_time']));
        }

        queue_notification_service(
            eventType: $event_key,
            recipients: [],
            context: [
                'booking_id' => (string) $order_id,
                'order_id' => (string) $order_id,
                'provider_id' => !empty($provider_id) ? (string) $provider_id : '',
                'provider_name' => $providerName,
                'date_of_service' => $date_of_service,
                'service_time' => $service_time,
                'recipient_type' => 'handyman'
            ],
            options: [
                'channels' => ['fcm'],
                'language' => $languageCode ?? get_default_language(),
                'user_ids' => $handyman_ids,
                'type' => 'booking_assignment',
                'data' => [
                    'order_id' => (string) $order_id,
                    'booking_id' => (string) $order_id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'redirect_to' => 'booking_details_screen'
                ]
            ]
        );
    } catch (\Throwable $notificationError) {
        log_message('error', '[' . strtoupper($event_key) . '] Notification error trace: ' . $notificationError->getTraceAsString());
    }
}

/**
 * Send a push-only payment-status notification to every handyman assigned to a
 * booking, reusing the existing customer-facing payment templates
 * ('online_payment_success' | 'online_payment_failed' | 'online_payment_pending').
 *
 * @param int $order_id Order/booking ID
 * @param string $eventType One of the existing online_payment_* event keys
 * @param array $context Same context payload used for the customer notification
 * @param string|null $languageCode Language code for notifications
 * @return void
 */
function notify_handymen_payment_status_changed(int $order_id, string $eventType, array $context = [], ?string $languageCode = null): void
{
    try {
        $bookingHandymenModel = model(\App\Models\BookingHandymenModel::class);
        $handymanIds = $bookingHandymenModel->getAssignedHandymanIds($order_id);
        if (empty($handymanIds)) {
            return;
        }

        $context['booking_id'] = $context['booking_id'] ?? (string) $order_id;
        $context['order_id'] = $context['order_id'] ?? (string) $order_id;
        $context['recipient_type'] = 'handyman';

        queue_notification_service(
            eventType: $eventType,
            recipients: [],
            context: $context,
            options: [
                'channels' => ['fcm'],
                'language' => $languageCode ?? get_default_language(),
                'user_ids' => $handymanIds,
                'type' => 'payment_status',
                'data' => [
                    'order_id' => (string) $order_id,
                    'booking_id' => (string) $order_id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'redirect_to' => 'booking_details_screen'
                ]
            ]
        );
    } catch (\Throwable $notificationError) {
        log_message('error', '[' . strtoupper($eventType) . '] Handyman payment notification error trace: ' . $notificationError->getTraceAsString());
    }
}

function validate_status($order_id, $status, $date = '', $selected_time = "", $otp = null, $work_proof = null, $additional_charges = null, $user_id = null, $language = null)
{
    $languageCode = $language ?? get_default_language();

    $trans = new ApiResponseAndNotificationStrings();
    if ($status == "awaiting") {
        $translated_status = $trans->awaiting;
    } else if ($status == "confirmed") {
        $translated_status = $trans->confirmed;
    } else if ($status == "rescheduled") {
        $translated_status = $trans->rescheduled;
    } else if ($status == "cancelled") {
        $translated_status = $trans->cancelled;
    } else if ($status == "cancelled") {
        $translated_status = $trans->cancelled;
    } else if ($status == "completed") {
        $translated_status = $trans->completed;
    } else if ($status == "started") {
        $translated_status = $trans->started;
    } else if ($status == "booking_ended") {
        $translated_status = $trans->bookingEnded;
    } else if ($status == "booking_ended") {
        $translated_status = $trans->bookingEnded;
    } else if ($status == "on_the_way") {
        $translated_status = labels('on_the_way', 'On The Way');
    } else if ($status == "reached_destination") {
        $translated_status = labels('reached_destination', 'Reached Destination');
    }
    $check_status = ['awaiting', 'confirmed', 'rescheduled', 'cancelled', 'completed', 'started', 'booking_ended', 'on_the_way', 'reached_destination'];
    if (in_array(($status), $check_status)) {
        $db = \Config\Database::connect();
        $builder = $db->table('orders');
        $builder->select('status,payment_method,user_id,otp,final_total,total_additional_charge,payment_status_of_additional_charge,payment_method_of_additional_charge')->where('id', $order_id);
        $active_status1 = $builder->get()->getResultArray();

        $active_status = (isset($active_status1[0]['status'])) ? $active_status1[0]['status'] : "";
        if ($active_status == $status) {
            $response['error'] = true;
            $response['message'] = labels(YOU_CANT_UPDATE_THE_SAME_STATUS_AGAIN, "You can't update the same status again");
            $response['data'] = array();
            return $response;
        }
        if ($active_status == 'cancelled' || $active_status == 'completed') {
            $response['error'] = true;
            $response['message'] = labels(YOU_CANT_UPDATE_STATUS_ONCE_ITEM_CANCELLED_OR_COMPLETED, "You can't update status once item cancelled OR completed");
            $response['data'] = array();
            return $response;
        }
        if (in_array($active_status, ["booking_ended"]) && (($status == "rescheduled") || ($status == "confirmed") || ($status == "awaiting") || ($status == "pending") || ($status == "on_the_way") || ($status == "reached_destination"))) {
            $response['error'] = true;

            $response['message'] = labels(YOU_CANT_ALTER_THE_STATUS_THAT_HAS_ALREADY_BEEN_MARKED_AS, "You cannot alter the status that has already been marked as") . " " . labels(strtolower($translated_status));
            $response['data'] = array();
            return $response;
        }
        if (in_array($active_status, ["started"]) && (($status == "rescheduled") || ($status == "confirmed") || ($status == "on_the_way") || ($status == "reached_destination"))) {
            $response['error'] = true;
            $response['message'] = labels(ONCE_YOU_BEGIN_THE_BOOKING_PROCESS_YOU_CANNOT_CHANGE_THE_BOOKING_TIME, "Once you begin the booking process, you cannot change the booking time.");
            $response['data'] = array();
            return $response;
        }
        if (in_array($active_status, ["started"]) && (($status == "rescheduled") || ($status == "confirmed") || ($status == "awaiting") || ($status == "pending") || ($status == "on_the_way") || ($status == "reached_destination"))) {
            $response['error'] = true;
            $response['message'] = labels(YOU_CANT_ALTER_THE_STATUS_THAT_HAS_ALREADY_BEEN_MARKED_AS, "You cannot alter the status that has already been marked as") . " " . labels(strtolower($translated_status));
            $response['data'] = array();
            return $response;
        }
        if (in_array($active_status, ["reached_destination"]) && (($status == "rescheduled") || ($status == "confirmed") || ($status == "awaiting") || ($status == "pending") || ($status == "on_the_way"))) {
            $response['error'] = true;
            $response['message'] = labels(YOU_CANT_ALTER_THE_STATUS_THAT_HAS_ALREADY_BEEN_MARKED_AS, "You cannot alter the status that has already been marked as") . " " . labels(strtolower($translated_status));
            $response['data'] = array();
            return $response;
        }
        if (in_array($active_status, ["on_the_way"]) && (($status == "rescheduled") || ($status == "confirmed") || ($status == "awaiting") || ($status == "pending"))) {
            $response['error'] = true;
            $response['message'] = labels(YOU_CANT_ALTER_THE_STATUS_THAT_HAS_ALREADY_BEEN_MARKED_AS, "You cannot alter the status that has already been marked as") . " " . labels(strtolower($translated_status));
            $response['data'] = array();
            return $response;
        }
        // Prevent changing status to 'started' after booking has ended
        if (in_array($active_status, ["booking_ended"]) && $status == "started") {
            $response['error'] = true;
            $response['message'] = labels(YOU_CANT_ALTER_THE_STATUS_THAT_HAS_ALREADY_BEEN_MARKED_AS, "You cannot alter the status that has already been marked as") . " " . labels(strtolower($translated_status));
            $response['data'] = array();
            return $response;
        }
        if ($active_status == '') {
            $response['error'] = true;
            $response['message'] = labels(INVALID_BOOKING_OR_STATUS_DATA, "Invalid booking or status data");
            $response['data'] = array();
            return $response;
        }
        if (in_array($active_status, ["confirmed", "rescheduled"]) && $status == "awaiting") {
            $response['error'] = true;
            $response['message'] = labels(YOU_CANT_ALTER_THE_STATUS_THAT_HAS_ALREADY_BEEN_MARKED_AS, "You cannot alter the status that has already been marked as") . " " . labels(strtolower($translated_status));
            $response['data'] = array();
            return $response;
        }
        if (in_array($status, ["awaiting", "confirmed"])) {
            update_details(['status' => $status], ['id' => $order_id], 'orders');
            update_details(["status" => $status], ["order_id" => $order_id, "status!=" => "cancelled"], "order_services");

            // Send notifications for confirmed status (awaiting doesn't send notifications)
            if ($status == "confirmed") {
                send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
            }
        }
        //if order status is completed
        if ($status == 'completed') {
            if (empty($active_status1[0]['payment_method_of_additional_charge']) || $active_status1[0]['payment_method_of_additional_charge'] != "cod") {
                if (($active_status1[0]['total_additional_charge'] != 0 || $active_status1[0]['total_additional_charge'] != "") && ($active_status1[0]['payment_status_of_additional_charge'] == '' || $active_status1[0]['payment_status_of_additional_charge'] == '0')) {
                    $response['error'] = true;
                    $response['message'] = labels(BOOKING_CANNOT_BE_COMPLETED_WITH_A_PENDING_PAYMENT_OF_ADDITIONAL_CHARGES, "Booking cannot be completed because payment of additional charges is pending by customer.");
                    $response['data'] = array();
                    return $response;
                }
            }

            $settings = get_settings('general_settings', true);
            if (isset($settings['otp_system']) && $settings['otp_system'] == 1) {
                $settings['otp_system'] = 1;
            } else {
                $settings['otp_system'] = 0;
            }
            //if otp system is enabled
            if ($settings['otp_system'] == "1") {
                if (empty($otp)) {
                    $response['error'] = true;
                    $response['message'] = labels(OTP_IS_REQUIRED, "OTP is required");
                    $response['data'] = [];
                    return $response;
                }
                //if otp is mathed then update status otherwise not
                if ($active_status1[0]['otp'] == $otp) {
                    // $data = get_service_details($order_id);
                    $order_details = fetch_details('orders', ['id' => $order_id]);
                    update_details(['status' => $status], ['id' => $order_id], 'orders');
                    // Update handyman status to match order status
                    $bookingHandymenModel = model('App\Models\BookingHandymenModel');
                    $bookingHandymenModel->where('order_id', $order_id)->where('status!=', 'cancelled')->set(['status' => 'completed'])->update();

                    // Send notifications for completed status
                    send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
                    if ($order_details[0]['payment_method'] != "cod") {
                        $user_details = fetch_details('users', ['id' => $order_details[0]['partner_id']]);
                        $admin_commission_percentage = get_admin_commision($order_details[0]['partner_id']);
                        $admin_commission_amount = intval($admin_commission_percentage) / 100;
                        $total = $order_details[0]['final_total'];
                        $commision = intval($total) * $admin_commission_amount;
                        $unsettled_amount = $total - $commision;
                        $current_admin_commission = $user_details[0]['admin_commission'];

                        update_details(["balance" => ($user_details[0]['balance'] + $unsettled_amount), 'admin_commission' => ($current_admin_commission + $commision)], ["id" => $order_details[0]['partner_id']], "users");
                        add_settlement_cashcollection_history('Received by admin', 'received_by_admin', date('Y-m-d'), date('h:i:s'), $unsettled_amount, $order_details[0]['partner_id'], $order_id, '', $admin_commission_percentage, $total, $commision);
                        $customer_details = fetch_details('users', ['id' => $order_details[0]['user_id']]);

                        // Send FCM notification for rating request to customer
                        // NotificationService handles FCM notifications using templates
                        if (check_notification_setting('rating_request_to_customer', 'notification')) {
                            try {
                                // Prepare context data for notification templates
                                // This context will be used to populate template variables like [[booking_id]], [[provider_name]], etc.
                                $notificationContext = [
                                    'booking_id' => $order_id,
                                    'provider_id' => $order_details[0]['partner_id'],
                                    'user_id' => $customer_details[0]['id']
                                ];

                                // Queue FCM notification using NotificationService
                                // NotificationService automatically handles:
                                // - Translation of templates based on user language
                                // - Variable replacement in templates
                                // - Notification settings checking
                                // - Fetching user FCM tokens
                                queue_notification_service(
                                    eventType: 'rating_request_to_customer',
                                    recipients: ['user_id' => $customer_details[0]['id']],
                                    context: $notificationContext,
                                    options: [
                                        'channels' => ['fcm'], // Only FCM channel (email and SMS already handled above)
                                        'language' => get_default_language(),
                                        'platforms' => ['android', 'ios', 'web'], // Customer platforms for FCM
                                        'type' => 'rating_request', // Notification type for app routing
                                        'data' => [
                                            'booking_id' => (string) $order_id,
                                            'provider_id' => (string) $order_details[0]['partner_id'],
                                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                        ]
                                    ]
                                );

                                // log_message('info', '[RATING_REQUEST_TO_CUSTOMER_FCM] Notification queued for user: ' . $customer_details[0]['id'] . ', Result: ' . json_encode($result));
                            } catch (\Throwable $notificationError) {
                                // Log error but don't fail the order completion
                                log_message('error', '[RATING_REQUEST_TO_CUSTOMER_FCM] Notification error trace: ' . $notificationError->getTraceAsString());
                            }
                        }
                    }
                    if (($order_details[0]['payment_method']) == "cod") {
                        $admin_commission_percentage = get_admin_commision($order_details[0]['partner_id']);
                        $admin_commission_amount = intval($admin_commission_percentage) / 100;
                        $total = $order_details[0]['final_total'];
                        $commision = intval($total) * $admin_commission_amount;
                        $isHandymanCollection = _is_handyman_completion($order_id, $user_id);

                        update_details(['payment_status' => '1'], ['id' => $order_id], 'orders');
                        notify_handymen_payment_status_changed($order_id, 'online_payment_success', [
                            'booking_id' => (string) $order_id,
                            'order_id' => (string) $order_id,
                            'amount' => number_format($order_details[0]['final_total'] ?? 0, 2),
                            'payment_method' => 'cod',
                            'transaction_id' => (string) $order_id,
                        ], $languageCode);
                        if (($active_status1[0]['total_additional_charge'] != 0 || $active_status1[0]['total_additional_charge'] != "")) {
                            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');
                        }

                        if ($isHandymanCollection) {
                            // Handyman physically collected the cash — provider-side settlement
                            // is deferred until the partner marks "cash received from handyman".
                            _record_handyman_cash_collection($order_id, $order_details[0], (int) $user_id);
                        } else {
                            $current_commision = fetch_details('users', ['id' => $order_details[0]['partner_id']], ['payable_commision', 'email', 'admin_commission'])[0];
                            $current_commision['payable_commision'] = ($current_commision['payable_commision'] == "") ? 0 : $current_commision['payable_commision'];
                            $current_admin_commission = $current_commision['admin_commission'];
                            update_details(['payable_commision' => ($current_commision['payable_commision'] + $commision), 'admin_commission' => ($current_admin_commission + $commision)], ['id' => $order_details[0]['partner_id']], 'users');
                            $cash_collecetion_data = [
                                'user_id' => $order_details[0]['user_id'],
                                'order_id' => $order_id,
                                'message' => "provider received cash",
                                'status' => 'provider_cash_recevied',
                                'commison' => intval($commision),
                                'partner_id' => $order_details[0]['partner_id'],
                                'date' => date("Y-m-d"),
                            ];
                            insert_details($cash_collecetion_data, 'cash_collection');
                            add_settlement_cashcollection_history('Cash collected by provider', 'cash_collection_by_provider', date('Y-m-d'), date('h:i:s'), $commision, $order_details[0]['partner_id'], $order_id, '', $commision, $order_details[0]['final_total'], $admin_commission_amount);

                            // Send notification to admin users about cash collection by provider (only if commission > 0)
                            // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Checking commission: ' . $commision . ' for order_id: ' . $order_id);
                            if ($commision > 0) {
                                try {
                                    // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Starting notification process for order_id: ' . $order_id);

                                    // Get provider name with translation support
                                    $providerName = get_translated_partner_field($order_details[0]['partner_id'], 'user_name');
                                    if (empty($providerName)) {
                                        $providerData = fetch_details('users', ['id' => $order_details[0]['partner_id']], ['username']);
                                        $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                                    }
                                    // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Provider name: ' . $providerName . ', Provider ID: ' . $order_details[0]['partner_id']);

                                    // Get currency from settings
                                    $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

                                    // Prepare context data for the notification template
                                    $context = [
                                        'provider_name' => $providerName,
                                        'provider_id' => $order_details[0]['partner_id'],
                                        'amount' => number_format($commision, 2),
                                        'currency' => $currency,
                                        'booking_id' => $order_id
                                    ];
                                    // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Context prepared: ' . json_encode($context));

                                    // Get all admin user IDs (group_id = 1) and add provider ID
                                    $db = \Config\Database::connect();
                                    $adminUsers = $db->table('users_groups')
                                        ->select('user_id')
                                        ->where('group_id', 1)
                                        ->get()
                                        ->getResultArray();

                                    $recipientUserIds = array_column($adminUsers, 'user_id');
                                    // Add provider ID if not already in the list
                                    if (!in_array($order_details[0]['partner_id'], $recipientUserIds)) {
                                        $recipientUserIds[] = $order_details[0]['partner_id'];
                                    }

                                    // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Queueing notification to admin users and provider. Total recipients: ' . count($recipientUserIds));

                                    // Queue notification to both admin users and provider in a single call
                                    queue_notification_service(
                                        eventType: 'cash_collection_by_provider',
                                        recipients: [],
                                        context: $context,
                                        options: [
                                            'user_ids' => $recipientUserIds, // Admin users + provider
                                            'channels' => ['fcm', 'email', 'sms'] // All channels - service will check preferences
                                        ]
                                    );
                                    // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Notification result: ' . json_encode($result));
                                } catch (\Throwable $notificationError) {
                                    log_message('error', '[CASH_COLLECTION_BY_PROVIDER] Notification error trace: ' . $notificationError->getTraceAsString());
                                }
                            } else {
                                log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Commission is 0 or less, skipping notification for order_id: ' . $order_id);
                            }
                        }

                        $customer_details = fetch_details('users', ['id' => $order_details[0]['user_id']]);
                        $transaction_details = fetch_details('transactions', ['order_id' => $order_id, 'message' => 'txn_additional_charges']);
                        if (!empty($transaction_details)) {
                            update_details(['status' => 'success'], ['id' => $transaction_details[0]['id']], 'transactions');
                        }

                        // Send FCM notification for rating request to customer
                        // NotificationService handles FCM notifications using templates
                        if (check_notification_setting('rating_request_to_customer', 'notification')) {
                            try {
                                // Prepare context data for notification templates
                                // This context will be used to populate template variables like [[booking_id]], [[provider_name]], etc.
                                $notificationContext = [
                                    'booking_id' => $order_id,
                                    'provider_id' => $order_details[0]['partner_id'],
                                    'user_id' => $customer_details[0]['id']
                                ];

                                // Queue FCM notification using NotificationService
                                // NotificationService automatically handles:
                                // - Translation of templates based on user language
                                // - Variable replacement in templates
                                // - Notification settings checking
                                // - Fetching user FCM tokens
                                queue_notification_service(
                                    eventType: 'rating_request_to_customer',
                                    recipients: ['user_id' => $customer_details[0]['id']],
                                    context: $notificationContext,
                                    options: [
                                        'channels' => ['fcm'], // Only FCM channel (email and SMS already handled above)
                                        'language' => $languageCode,
                                        'platforms' => ['android', 'ios', 'web'], // Customer platforms for FCM
                                        'type' => 'rating_request', // Notification type for app routing
                                        'data' => [
                                            'booking_id' => (string) $order_id,
                                            'provider_id' => (string) $order_details[0]['partner_id'],
                                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                        ]
                                    ]
                                );

                                // log_message('info', '[RATING_REQUEST_TO_CUSTOMER_FCM] Notification queued for user: ' . $customer_details[0]['id'] . ', Result: ' . json_encode($result));
                            } catch (\Throwable $notificationError) {
                                // Log error but don't fail the order completion
                                log_message('error', '[RATING_REQUEST_TO_CUSTOMER_FCM] Notification error trace: ' . $notificationError->getTraceAsString());
                            }
                        }
                    }
                    ;
                    update_details(["status" => $status], ["order_id" => $order_id], "order_services");
                } else {
                    $response['error'] = true;
                    $response['message'] = labels(OTP_DOES_NOT_MATCH, "OTP does not match!");
                    $response['data'] = [];
                    return $response;
                }
            }
            //if otp system is disabled
            else {
                // $data = get_service_details($order_id);
                $order_details = fetch_details('orders', ['id' => $order_id]);
                update_details(['status' => $status], ['id' => $order_id], 'orders');
                // Update handyman status to match order status
                $bookingHandymenModel = model('App\Models\BookingHandymenModel');
                $bookingHandymenModel->where('order_id', $order_id)->where('status!=', 'cancelled')->set(['status' => 'completed'])->update();

                // Send notifications for completed status (when OTP is disabled)
                send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
                if ($order_details[0]['payment_method'] != "cod") {
                    $user_details = fetch_details('users', ['id' => $order_details[0]['partner_id']]);
                    $admin_commission_percentage = get_admin_commision($order_details[0]['partner_id']);
                    $admin_commission_amount = intval($admin_commission_percentage) / 100;
                    $total = $order_details[0]['final_total'];
                    $commision = intval($total) * $admin_commission_amount;
                    $unsettled_amount = $total - $commision;
                    update_details(["status" => $status], ["order_id" => $order_id], "order_services");
                    update_details(["balance" => $user_details[0]['balance'] + $unsettled_amount], ["id" => $order_details[0]['partner_id']], "users");
                    add_settlement_cashcollection_history('Received by admin', 'received_by_admin', date('Y-m-d'), date('h:i:s'), $unsettled_amount, $order_details[0]['partner_id'], $order_id, '', $admin_commission_percentage, $total, $commision);
                }
                if (($order_details[0]['payment_method']) == "cod") {
                    $admin_commission_percentage = get_admin_commision($order_details[0]['partner_id']);
                    $admin_commission_amount = intval($admin_commission_percentage) / 100;
                    $total = $order_details[0]['final_total'];
                    $commision = intval($total) * $admin_commission_amount;
                    $isHandymanCollection = _is_handyman_completion($order_id, $user_id);

                    update_details(['payment_status' => '1'], ['id' => $order_id], 'orders');
                    notify_handymen_payment_status_changed($order_id, 'online_payment_success', [
                        'booking_id' => (string) $order_id,
                        'order_id' => (string) $order_id,
                        'amount' => number_format($order_details[0]['final_total'] ?? 0, 2),
                        'payment_method' => 'cod',
                        'transaction_id' => (string) $order_id,
                    ], $languageCode);
                    if (($active_status1[0]['total_additional_charge'] != 0 || $active_status1[0]['total_additional_charge'] != "")) {
                        update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');
                    }

                    if ($isHandymanCollection) {
                        // Handyman physically collected the cash — provider-side settlement
                        // is deferred until the partner marks "cash received from handyman".
                        _record_handyman_cash_collection($order_id, $order_details[0], (int) $user_id);
                    } else {
                        $current_commision = fetch_details('users', ['id' => $order_details[0]['partner_id']], ['payable_commision', 'email'])[0];
                        $current_commision['payable_commision'] = ($current_commision['payable_commision'] == "") ? 0 : $current_commision['payable_commision'];
                        // update_details(['payable_commision' => $current_commision['payable_commision'] + $commision], ['id' => $order_details[0]['partner_id']], 'users');
                        $sum = $current_commision['payable_commision'] + $commision;
                        update_details(['payable_commision' => $sum == 0 ? "0" : $sum], ['id' => $order_details[0]['partner_id']], 'users');
                        $cash_collecetion_data = [
                            'user_id' => $order_details[0]['user_id'],
                            'order_id' => $order_id,
                            'message' => "provider received cash",
                            'status' => 'provider_cash_recevied',
                            'commison' => intval($commision),
                            'partner_id' => $order_details[0]['partner_id'],
                            'date' => date("Y-m-d"),
                        ];
                        insert_details($cash_collecetion_data, 'cash_collection');
                        $actual_amount_of_provider = $order_details[0]['final_total'] - $commision;
                        add_settlement_cashcollection_history(
                            'Cash collected by provider',
                            'cash_collection_by_provider',
                            date('Y-m-d'),
                            date('h:i:s'),
                            $actual_amount_of_provider,
                            $order_details[0]['partner_id'],
                            $order_id,
                            '',
                            $admin_commission_percentage,
                            $order_details[0]['final_total'],
                            $commision
                        );

                        // Send notification to admin users about cash collection by provider (only if commission > 0)
                        // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Checking commission: ' . $commision . ' for order_id: ' . $order_id);
                        if ($commision > 0) {
                            try {
                                // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Starting notification process for order_id: ' . $order_id);

                                // Get provider name with translation support
                                $providerName = get_translated_partner_field($order_details[0]['partner_id'], 'user_name');
                                if (empty($providerName)) {
                                    $providerData = fetch_details('users', ['id' => $order_details[0]['partner_id']], ['username']);
                                    $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                                }
                                // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Provider name: ' . $providerName . ', Provider ID: ' . $order_details[0]['partner_id']);

                                // Get currency from settings
                                $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

                                // Prepare context data for the notification template
                                $context = [
                                    'provider_name' => $providerName,
                                    'provider_id' => $order_details[0]['partner_id'],
                                    'amount' => number_format($commision, 2),
                                    'currency' => $currency,
                                    'booking_id' => $order_id
                                ];
                                // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Context prepared: ' . json_encode($context));

                                // Get all admin user IDs (group_id = 1) and add provider ID
                                $db = \Config\Database::connect();
                                $adminUsers = $db->table('users_groups')
                                    ->select('user_id')
                                    ->where('group_id', 1)
                                    ->get()
                                    ->getResultArray();

                                $recipientUserIds = array_column($adminUsers, 'user_id');
                                // Add provider ID if not already in the list
                                if (!in_array($order_details[0]['partner_id'], $recipientUserIds)) {
                                    $recipientUserIds[] = $order_details[0]['partner_id'];
                                }

                                // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Queueing notification to admin users and provider. Total recipients: ' . count($recipientUserIds));

                                // Queue notification to both admin users and provider in a single call
                                queue_notification_service(
                                    eventType: 'cash_collection_by_provider',
                                    recipients: [],
                                    context: $context,
                                    options: [
                                        'user_ids' => $recipientUserIds, // Admin users + provider
                                        'channels' => ['fcm', 'email', 'sms'] // All channels - service will check preferences
                                    ]
                                );
                                // log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Notification result: ' . json_encode($result));
                            } catch (\Throwable $notificationError) {
                                log_message('error', '[CASH_COLLECTION_BY_PROVIDER] Notification error trace: ' . $notificationError->getTraceAsString());
                            }
                        } else {
                            log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Commission is 0 or less, skipping notification for order_id: ' . $order_id);
                        }
                    }
                }
                ;
                $customer_details = fetch_details('users', ['id' => $order_details[0]['user_id']]);
                if (check_notification_setting('rating_request_to_customer', 'notification')) {
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[booking_id]], [[provider_name]], etc.
                        $notificationContext = [
                            'booking_id' => $order_id,
                            'provider_id' => $order_details[0]['partner_id'],
                            'user_id' => $customer_details[0]['id']
                        ];

                        // Queue FCM notification using NotificationService
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking
                        // - Fetching user FCM tokens
                        queue_notification_service(
                            eventType: 'rating_request_to_customer',
                            recipients: ['user_id' => $customer_details[0]['id']],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm'], // Only FCM channel (email and SMS already handled above)
                                'language' => $languageCode,
                                'platforms' => ['android', 'ios', 'web'], // Customer platforms for FCM
                                'type' => 'rating_request', // Notification type for app routing
                                'data' => [
                                    'booking_id' => (string) $order_id,
                                    'provider_id' => (string) $order_details[0]['partner_id'],
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[RATING_REQUEST_TO_CUSTOMER_FCM] Notification queued for user: ' . $customer_details[0]['id'] . ', Result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the order completion
                        log_message('error', '[RATING_REQUEST_TO_CUSTOMER_FCM] Notification error trace: ' . $notificationError->getTraceAsString());
                    }
                }
            }
        }
        if ($status == 'started') {
            if (!empty($work_proof)) {
                $imagefile = $work_proof['work_started_files'];
                $work_started_images = [];
                foreach ($imagefile as $key => $img) {
                    if ($img->isValid() && !$img->hasMoved()) {
                        $result = upload_file($img, 'public/uploads/provider_work_evidence', labels('error_creating_provider_work_evidence_folder'), 'provider_work_evidence');
                        if ($result['disk'] == "local_server") {
                            $work_started_images[$key] = "/public/uploads/provider_work_evidence/" . $result['file_name'];
                        } else if ($result['disk'] == "aws_s3") {
                            $work_started_images[$key] = $result['file_name'];
                        }
                    }
                }
            }
            $dataToUpdate = [
                'status' => 'started',
                'work_started_proof' => !empty($work_started_images) ? json_encode($work_started_images) : "",
            ];
            update_details($dataToUpdate, ['id' => $order_id], 'orders', false);
            update_details(["status" => $status], ["order_id" => $order_id], "order_services");
            // Update handyman status to match order status
            $bookingHandymenModel = model('App\Models\BookingHandymenModel');
            $bookingHandymenModel->where('order_id', $order_id)->where('status!=', 'cancelled')->set(['status' => 'started'])->update();

            // Send notifications for started status
            send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
        }
        if ($status == 'rescheduled') {
            if (empty($date) || empty($selected_time)) {
                $response['error'] = true;
                $response['message'] = labels(PLEASE_SELECT_UPCOMING_DATE, "Please select upcoming date");
                $response['data'] = array();
                return $response;
            }
            $orders = fetch_details('orders', ['id' => $order_id]);
            if (empty($orders)) {
                $response['error'] = true;
                $response['message'] = labels(INVALID_BOOKING_OR_STATUS_DATA, "Invalid booking or status data");
                $response['data'] = array();
                return $response;
            }
            $partner_id = (int) $orders[0]['partner_id'];
            $sub_orders = fetch_details('orders', ['parent_id' => $order_id]);
            $service_total_duration = (int) $orders[0]['duration'];
            foreach (($sub_orders ?: []) as $so) {
                $service_total_duration += (int) ($so['duration'] ?? 0);
            }

            // Lock-first: reserve the new slot atomically. If it can't be locked,
            // the existing booking stays untouched and we return the engine's reason.
            $slotSvc = service('slot');
            $lockResult = $slotSvc->validateAndLockSlot(
                $partner_id,
                $date,
                (string) $selected_time,
                $service_total_duration,
                (int) ($user_id ?: $orders[0]['user_id'])
            );
            if ($lockResult['error']) {
                $response['error'] = true;
                $response['message'] = $lockResult['message'];
                $response['data'] = array();
                return $response;
            }

            $confirm = $slotSvc->confirmBooking(
                (int) $lockResult['data']['lock_id'],
                $partner_id,
                $date,
                (string) $selected_time,
                $service_total_duration,
                (int) ($user_id ?: $orders[0]['user_id'])
            );
            if ($confirm['error']) {
                $response['error'] = true;
                $response['message'] = $confirm['message'];
                $response['data'] = array();
                return $response;
            }

            $new_starting = (string) $confirm['data']['starting_time'];
            $new_ending = (string) $confirm['data']['ending_time'];
            $new_shift_id = $confirm['data']['shift_id'] ?? null;
            $continuation = $confirm['data']['continuation'] ?? null;
            $primary_duration = max(1, (int) ((strtotime($new_ending) - strtotime($new_starting)) / 60));
            if (empty($continuation)) {
                $primary_duration = $service_total_duration;
            }

            update_details(
                [
                    'status' => 'rescheduled',
                    'date_of_service' => $date,
                    'starting_time' => $new_starting,
                    'ending_time' => $new_ending,
                    'duration' => $primary_duration,
                    'shift_id' => $new_shift_id,
                ],
                ['id' => $order_id],
                'orders'
            );

            // Replace any existing children: cascade-cancel old, then re-insert from
            // the new continuation. Children carry the booking through cross-shift or
            // multi-day boundaries; the engine's continuation is authoritative.
            if (!empty($sub_orders)) {
                update_details(['status' => 'cancelled'], ['parent_id' => $order_id], 'orders');
            }
            if (!empty($continuation)) {
                $cont_start = (string) $continuation['starting_time'];
                $cont_end = (string) $continuation['ending_time'];
                $cont_duration = max(1, (int) ((strtotime($cont_end) - strtotime($cont_start)) / 60));
                $sub_order = [
                    'partner_id' => $partner_id,
                    'user_id' => $orders[0]['user_id'],
                    'city' => $orders[0]['city'] ?? ($orders[0]['city_id'] ?? ''),
                    'total' => $orders[0]['total'],
                    'payment_method' => $orders[0]['payment_method'],
                    'address_id' => $orders[0]['address_id'],
                    'visiting_charges' => $orders[0]['visiting_charges'],
                    'address' => $orders[0]['address'],
                    'date_of_service' => (string) $continuation['date'],
                    'starting_time' => $cont_start,
                    'ending_time' => $cont_end,
                    'duration' => $cont_duration,
                    'shift_id' => $continuation['shift_id'] ?? null,
                    'status' => 'rescheduled',
                    'remarks' => 'sub_order',
                    'otp' => random_int(100000, 999999),
                    'parent_id' => $orders[0]['id'],
                    'order_latitude' => $orders[0]['order_latitude'],
                    'order_longitude' => $orders[0]['order_longitude'],
                    'final_total' => $orders[0]['final_total'],
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                insert_details($sub_order, 'orders');
            }

            send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);

            $response['error'] = false;
            $response['message'] = labels(THE_BOOKING_HAS_BEEN_SUCCESSFULLY_RESCHEDULED, "The booking has been successfully rescheduled.");
            $response['data'] = array();
            return $response;
        }
        if ($status == 'cancelled') {
            $provider_details = fetch_details('partner_details', ['partner_id' => $user_id], ['partner_id']);
            if (!empty($provider_details) && $provider_details[0]['partner_id'] == $user_id) {
                // Get current order status before updating (needed for notifications)
                $order_data = fetch_details('orders', ['id' => $order_id], ['user_id', 'status']);
                $active_status = !empty($order_data) && !empty($order_data[0]['status']) ? $order_data[0]['status'] : '';
                $customer_id = !empty($order_data) && !empty($order_data[0]['user_id']) ? $order_data[0]['user_id'] : null;

                // Update order status to cancelled
                $order_details = fetch_details('order_services', ['order_id' => $order_id]);
                update_details(['status' => $status], ['id' => $order_id], 'orders');

                // Process refund if payment was made
                if (!empty($customer_id)) {
                    $refund = process_refund($order_id, $status, $customer_id);
                }

                // Send email notifications to customer when provider cancels booking
                // This ensures customers are notified via email, SMS, and FCM when their booking is cancelled by provider
                send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);

                $response['error'] = false;
                $response['message'] = labels(BOOKING_IS_CANCELLED, "Booking is cancelled.");
                $response['data'] = [];
                return $response;
            } else {
                $order_details = fetch_details('orders', ['id' => $order_id]);
                $order_details = json_encode($order_details);
                $details = json_decode($order_details);
                $data['order'] = isset($details[0]) ? $details[0] : '';
                if ($details[0]->custom_job_request_id != "" || $details[0]->custom_job_request_id != NULL) {
                    $order_services = fetch_details('partner_bids', ['custom_job_request_id' => $details[0]->custom_job_request_id]);
                    $custom_job_request = get_settings('general_settings', true);
                    $data['cancellable'] = [];
                    $cancellable[0] = [
                        'id' => $order_services[0]['custom_job_request_id'],
                        'duration' => $order_services[0]['duration'],
                        'is_cancelable' => 1,
                        'cancelable_till' => $custom_job_request['booking_auto_cancle_duration']
                    ];
                    // $dat?a['cancellable'][] = $cancellable;
                } else {
                    $order_services = fetch_details('order_services', ['order_id' => $order_id]);
                    foreach ($order_services as $row) {
                        $services[] = $row['service_id'];
                    }
                    $data['cancellable'] = [];
                    foreach ($services as $row) {
                        $data_of_service = fetch_details('services', ['id' => $row], ['id', 'duration', 'is_cancelable', 'cancelable_till'], null, '0', '', '');
                        foreach ($data_of_service as $data1) {
                            $cancellable[] = $data1;
                        }
                    }
                }
                if (!empty($order_details)) {
                    $order = $data['order'];
                    $customer_id = $order->user_id;
                    $date_of_service = $order->date_of_service;
                    $starting_time = $order->starting_time;
                    $cancellable = ($cancellable);
                    $response = [];
                    $response['status'] = $status;
                    $can_cancle = false;

                    foreach ($cancellable as $key) {
                        $can_cancle = ($key['is_cancelable'] == 1) ? true : false;
                        if ($key['is_cancelable'] == "1" && $key['cancelable_till']) {
                            $is_cancelable = check_cancelable(date('y-m-d', strtotime($date_of_service)), $starting_time, $key['cancelable_till']);
                            if ($is_cancelable == true) {
                                if ($can_cancle == false) {
                                    $response['error'] = true;
                                    $response['message'] = labels(BOOKING_IS_NOT_CANCELABLE, "Booking is not cancelable!");
                                    $response['data'] = [];
                                    return $response;
                                } else {
                                    update_details(['status' => $status], ['id' => $order_id], 'orders');
                                    $refund = process_refund($order_id, $status, $customer_id);

                                    // Send notifications for cancelled status
                                    send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);

                                    $response['is_cancelable'] = true;
                                    $response['error'] = false;
                                    $response['message'] = labels(BOOKING_UPDATED_SUCCESSFULLY, "Booking updated successfully");
                                    $response['data'] = $refund;

                                    return $response;
                                }
                            } else {
                                $response['error'] = true;
                                $response['message'] = labels(BOOKING_IS_NOT_CANCELABLE, "Booking is not cancelable !");
                                $response['data'] = [];
                                return $response;
                            }
                        } else {

                            $response['error'] = true;
                            $response['message'] = labels(BOOKING_IS_NOT_CANCELABLE, "Booking is not cancelable!");
                            $response['data'] = [];
                            return $response;
                        }
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = labels(BOOKING_DATA_NOT_FOUND, "Booking data not found!");
                    $response['data'] = [];
                    return $response;
                }
            }
        }
        if ($status == "booking_ended") {
            $dataToUpdate = [
                'status' => 'booking_ended',
            ];
            if ($additional_charges != "" && ($additional_charges[0]['name'] != "" && $additional_charges[0]['charge'] != "")) {
                $additional_total_charge = 0;
                $tax_row = \Config\Database::connect()->table('order_services')
                    ->select('tax_percentage')
                    ->where('order_id', $order_id)
                    ->where('status !=', 'cancelled')
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getRowArray();
                $tax_percentage = (float) ($tax_row['tax_percentage'] ?? 0);
                if (isset($additional_charges)) {
                    foreach ($additional_charges as $key => $charge) {
                        if (empty($charge['name']) || empty($charge['charge'])) {
                            $response['error'] = true;
                            $response['message'] = labels(ALL_ADDITIONAL_CHARGE_FIELDS_ARE_REQUIRED, "All additional charge fields are required");
                            $response['data'] = [];
                            return $response;
                        }
                        if ((float) $charge['charge'] < 1) {
                            $response['error'] = true;
                            $response['message'] = labels(CHARGE_AMOUNT_MUST_BE_GREATER_THAN_0, "Charge amount must be greater than 0");
                            $response['data'] = [];
                            return $response;
                        }
                        $charge_amount = round((float) $charge['charge'], 2);
                        $charge_tax = calculate_tax_amount($charge_amount, $tax_percentage, 'excluded');
                        $additional_charges[$key]['charge'] = number_format($charge_amount, 2, '.', '');
                        $additional_charges[$key]['tax_percentage'] = number_format($tax_percentage, 2, '.', '');
                        $additional_charges[$key]['tax_type'] = 'excluded';
                        $additional_charges[$key]['tax_amount'] = number_format($charge_tax, 2, '.', '');
                        $additional_charges[$key]['total'] = number_format($charge_amount + $charge_tax, 2, '.', '');
                    }
                    foreach ($additional_charges as $key => $charge) {
                        $additional_total_charge += (float) $charge['charge'];
                    }
                }
                $additional_tax_amount = round(array_sum(array_column($additional_charges, 'tax_amount')), 2);
                $dataToUpdate['additional_charges'] = json_encode($additional_charges);
                $dataToUpdate['total_additional_charge'] = $additional_total_charge + $additional_tax_amount;
                // $dataToUpdate['payment_status_of_additional_charge'] = '0';
                $dataToUpdate['final_total'] = $active_status1[0]['final_total'] + $additional_total_charge + $additional_tax_amount;
            }
            update_details($dataToUpdate, ['id' => $order_id], 'orders', false);
            // Update handyman status to match order status
            $bookingHandymenModel = model('App\Models\BookingHandymenModel');
            $bookingHandymenModel->where('order_id', $order_id)->where('status!=', 'cancelled')->set(['status' => 'booking_ended'])->update();

            // Send notification to customer when additional charges are added
            // This notification is specifically for additional charges and redirects to booking details screen
            if (!empty($additional_charges) && ($additional_charges[0]['name'] != "" && $additional_charges[0]['charge'] != "")) {
                try {
                    // Get order details to get customer and provider information
                    $order_details = fetch_details('orders', ['id' => $order_id]);
                    if (empty($order_details)) {
                        log_message('error', '[ADDED_ADDITIONAL_CHARGES] Order not found: ' . $order_id);
                    } else {
                        $order = $order_details[0];
                        $customer_id = $order['user_id'];
                        $provider_id = $order['partner_id'];

                        // Get provider name with translation support
                        $providerName = get_translated_partner_field($provider_id, 'company_name');
                        if (empty($providerName)) {
                            $partner_data = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
                            $providerName = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
                        }

                        // Get customer details
                        $customer_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);
                        $customer_name = !empty($customer_details) && !empty($customer_details[0]['username']) ? $customer_details[0]['username'] : 'Customer';

                        // Get currency from settings
                        $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

                        // Format additional charges list for email template
                        $additional_charges_list = '';
                        if (!empty($additional_charges)) {
                            $charges_items = [];
                            foreach ($additional_charges as $charge) {
                                if (!empty($charge['name']) && !empty($charge['charge'])) {
                                    $charges_items[] = $charge['name'] . ': ' . number_format($charge['charge'], 2) . ' ' . $currency;
                                }
                            }
                            $additional_charges_list = implode('<br>', $charges_items);
                        }

                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[provider_name]], [[total_additional_charge]], [[amount]], etc.
                        $notificationContext = [
                            'booking_id' => (string) $order_id,
                            'order_id' => (string) $order_id,
                            'total_additional_charge' => number_format($additional_total_charge + $additional_tax_amount, 2),
                            'currency' => $currency,
                            'provider_id' => (string) $provider_id,
                            'provider_name' => $providerName,
                            'customer_id' => (string) $customer_id,
                            'customer_name' => $customer_name,
                            'additional_charges_list' => $additional_charges_list,
                            'final_total' => number_format($order['final_total'], 2)
                        ];

                        // Queue all notifications (FCM, Email, SMS) to customer using NotificationService
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'added_additional_charges',
                            recipients: ['user_id' => $customer_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels
                                'language' => $languageCode ?? get_default_language(),
                                'platforms' => ['android', 'ios', 'web'], // Customer platforms
                                'type' => 'additional_charges', // Notification type for app routing
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'booking_id' => (string) $order_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'redirect_to' => 'booking_details_screen' // Redirect to booking details screen
                                ]
                            ]
                        );

                        // log_message('info', '[ADDED_ADDITIONAL_CHARGES] Notification queued for customer: ' . $customer_id . ', Order: ' . $order_id . ', Total Additional Charge: ' . $additional_total_charge . ', Result: ' . json_encode($result));
                    }
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the booking status update
                    log_message('error', '[ADDED_ADDITIONAL_CHARGES] Notification error trace: ' . $notificationError->getTraceAsString());
                }
            }

            if (!empty($work_proof)) {
                $imagefile = $work_proof['work_complete_files'];
                $work_completed_images = [];
                foreach ($imagefile as $key => $img) {
                    if ($img->isValid() && !$img->hasMoved()) {
                        $result = upload_file($img, 'public/uploads/provider_work_evidence', 'error creating provider work evidence folder', 'provider_work_evidence');
                        if ($result['disk'] == "local_server") {
                            $work_completed_images[$key] = "/public/uploads/provider_work_evidence/" . $result['file_name'];
                        } else if ($result['disk'] == "aws_s3") {
                            $work_completed_images[$key] = $result['file_name'];
                        }
                    }
                }
                $dataToUpdate = [
                    'work_completed_proof' => !empty($work_completed_images) ? json_encode($work_completed_images) : "",
                ];
                update_details($dataToUpdate, ['id' => $order_id], 'orders', false);
            }
        }
        $response['error'] = false;
        $response['message'] = labels(BOOKING_UPDATED_SUCCESSFULLY, "Booking updated successfully ");
        $response['data'] = [];

        return $response;
    } else {
        $response['error'] = true;
        $response['message'] = labels(INVALID_STATUS_PASSED, "Invalid Status Passed");
        $response['data'] = array();
        return $response;
    }
}

function unsettled_commision($partner_id = '')
{
    $amount = fetch_details('orders', ['partner_id' => $partner_id, 'is_commission_settled' => '0', 'status' => 'completed'], ['sum(final_total) as total']);
    if (isset($amount) && !empty($amount)) {
        $admin_commission_percentage = get_admin_commision($partner_id);
        $admin_commission_amount = intval($admin_commission_percentage) / 100;
        $total = $amount[0]['total'];
        $commision = intval($total) * $admin_commission_amount;
        $unsettled_amount = $total - $commision;
    } else {
        $unsettled_amount = 0;
    }
    return $unsettled_amount;
}

function get_admin_commision($partner_id = '')
{
    $commision = fetch_details('partner_details', ['partner_id' => $partner_id], ['admin_commission']) ?? [];
    if (!empty($commision)) {
        $commision = $commision[0]['admin_commission'];
    }
    return $commision ?? 0;
}

function process_refund($order_id, $status, $customer_id)
{
    $possible_status = array("cancelled");
    if (!in_array($status, $possible_status)) {
        $response['error'] = true;
        $response['message'] = 'Refund cannot be processed. Invalid status';
        $response['data'] = array();
        return $response;
    }
    /* if complete order is getting cancelled */
    $transaction = fetch_details('transactions', ['order_id' => $order_id, 'transaction_type' => 'transaction'], ['amount', 'txn_id', 'type', 'currency_code', 'status', 'partner_id', 'reference']);
    if (isset($transaction) && !empty($transaction)) {
        $type = $transaction[0]['type'];
        $currency = $transaction[0]['currency_code'];
        $txn_id = $transaction[0]['txn_id'];
        $reference = $transaction[0]['reference'] ?? '';
        $amount = $transaction[0]['amount'];
        $partner_id = $transaction[0]['partner_id'];
        if ($type == 'flutterwave' && $transaction[0]['status'] == "successfull") {
            $flutterwave = new Flutterwave();
            $payment = $flutterwave->refund_payment($txn_id, $amount);
            if (isset($payment->status) && $payment->status == 'success') {
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'flutterwave',
                    'txn_id' => $txn_id,
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $payment->status,
                    'message' => "flutterwave_refund",
                    'partner_id' => $partner_id,
                ];
                $success = insert_details($data, 'transactions');
                $response['error'] = false;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['message'] = "Payment Refund Successfully";
                if ($success) {
                    update_details(['status' => $status, 'isRefunded' => '1'], ['id' => $order_id], 'orders');

                    // Send notifications to user and admin when refund is successfully processed
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // Single generalized template works for both user and admin
                    try {
                        // Get user and order details for notification context
                        $user_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);
                        $order_details = fetch_details('orders', ['id' => $order_id], ['total']);

                        $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                        $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';

                        // Get refund transaction ID
                        $refund_transaction_id = $txn_id;
                        $refund_id = $data['txn_id'] ?? $txn_id;

                        // Prepare context data for notification templates (generalized for both user and admin)
                        $notificationContext = [
                            'order_id' => $order_id,
                            'booking_id' => $order_id, // Add booking_id for template variables
                            'amount' => number_format($amount, 2),
                            'currency' => $currency,
                            'refund_id' => (string) $refund_id,
                            'transaction_id' => $refund_transaction_id,
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'customer_id' => $customer_id,
                            'processed_date' => date('d-m-Y H:i:s')
                        ];

                        // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                        // Send payment_refund_executed notification to customer (redirects to booking details)
                        queue_notification_service(
                            eventType: 'payment_refund_executed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_ids' => [$customer_id], // Send only to this specific customer
                                'platforms' => ['android', 'ios', 'web'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'booking_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',

                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_FLUTTERWAVE_USER_NOTIFICATION] Notification queued for user: ' . $customer_id . ', Result: ' . json_encode($userResult));

                        // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                        queue_notification_service(
                            eventType: 'payment_refund_successful',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_groups' => [1], // Admin user group
                                'platforms' => ['admin_panel'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_FLUTTERWAVE_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the refund processing
                        log_message('error', '[PROCESS_REFUND_FLUTTERWAVE_NOTIFICATION] Notification error: ' . $notificationError->getMessage());
                    }

                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                }
            } else {
                $message = json_decode($payment, true);
                $response['error'] = true;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['message'] = $message['message'];
            }
        }
        if ($type == "stripe" && $transaction[0]['status'] == 'success') {
            $amount = $transaction[0]['amount'] / 100;
            $stripe = new Stripe();
            $payment = $stripe->refund($txn_id, $amount);
            if (isset($payment['status']) && $payment['status'] == "succeeded") {
                $amount = intval($payment['amount']);
                $data = [
                    'transaction_type' => $payment['object'],
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'stripe',
                    'txn_id' => $payment['payment_intent'],
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $payment['status'],
                    'message' => "stripe_refund",
                    'partner_id' => $partner_id,
                ];
                $success = insert_details($data, 'transactions');
                $response = [
                    'error' => false,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => "Payment Refund Successfully",
                ];
                if ($success) {
                    update_details(['status' => $status, 'isRefunded' => '1'], ['id' => $order_id], 'orders');

                    // Send notifications to user and admin when refund is successfully processed
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // Single generalized template works for both user and admin
                    try {
                        // Get user and order details for notification context
                        $user_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);

                        $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                        $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';

                        // Get refund transaction ID
                        $refund_transaction_id = $payment['payment_intent'] ?? $txn_id;
                        $refund_id = $data['txn_id'] ?? $refund_transaction_id;

                        // Prepare context data for notification templates (generalized for both user and admin)
                        $notificationContext = [
                            'order_id' => $order_id,
                            'booking_id' => $order_id, // Add booking_id for template variables
                            'amount' => number_format($amount, 2),
                            'currency' => $currency,
                            'refund_id' => (string) $refund_id,
                            'transaction_id' => $refund_transaction_id,
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'customer_id' => $customer_id,
                            'processed_date' => date('d-m-Y H:i:s')
                        ];

                        // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                        // Send payment_refund_executed notification to customer (redirects to booking details)
                        queue_notification_service(
                            eventType: 'payment_refund_executed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_ids' => [$customer_id], // Send only to this specific customer
                                'platforms' => ['android', 'ios', 'web'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'booking_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'redirect_to' => 'booking_details_screen'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_STRIPE_USER_NOTIFICATION] Notification queued for user: ' . $customer_id . ', Result: ' . json_encode($userResult));

                        // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                        queue_notification_service(
                            eventType: 'payment_refund_successful',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_groups' => [1], // Admin user group
                                'platforms' => ['admin_panel'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_STRIPE_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the refund processing
                        log_message('error', '[PROCESS_REFUND_STRIPE_NOTIFICATION] Notification error: ' . $notificationError->getMessage());
                    }

                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                }
                return $response;
            } else {
                $res = json_decode($payment['body']);
                $msg = $res->error->message;
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $msg,
                ];
                return $response;
            }
        }
        if ($type == "razorpay" && $transaction[0]['status'] == "success") {
            $razorpay = new Razorpay();
            $payment = $razorpay->refund_payment($txn_id, $amount);
            if (isset($payment['status']) && $payment['status'] == "processed") {
                $amount = intval($payment['amount']) / 100;
                $data = [
                    'transaction_type' => $payment['entity'],
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'razorpay',
                    'txn_id' => $payment['payment_id'],
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $payment['status'],
                    'message' => 'txn_refund_processed',
                    'partner_id' => $partner_id,
                ];
                $success = insert_details($data, 'transactions');
                if ($success) {
                    update_details(['status' => $status, 'isRefunded' => '1'], ['id' => $order_id], 'orders');

                    // Send notifications to user and admin when refund is successfully processed
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // Single generalized template works for both user and admin
                    try {
                        // Get user and order details for notification context
                        $user_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);

                        $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                        $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';

                        // Get refund transaction ID
                        $refund_transaction_id = $payment['payment_id'] ?? $txn_id;
                        $refund_id = $data['txn_id'] ?? $refund_transaction_id;

                        // Prepare context data for notification templates (generalized for both user and admin)
                        $notificationContext = [
                            'order_id' => $order_id,
                            'booking_id' => $order_id, // Add booking_id for template variables
                            'amount' => number_format($amount, 2),
                            'currency' => $currency,
                            'refund_id' => (string) $refund_id,
                            'transaction_id' => $refund_transaction_id,
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'customer_id' => $customer_id,
                            'processed_date' => date('d-m-Y H:i:s')
                        ];

                        // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                        // Send payment_refund_executed notification to customer (redirects to booking details)
                        queue_notification_service(
                            eventType: 'payment_refund_executed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_ids' => [$customer_id], // Send only to this specific customer
                                'platforms' => ['android', 'ios', 'web'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'booking_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'redirect_to' => 'booking_details_screen'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_RAZORPAY_USER_NOTIFICATION] Notification queued for user: ' . $customer_id . ', Result: ' . json_encode($userResult));

                        // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                        queue_notification_service(
                            eventType: 'payment_refund_successful',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_groups' => [1], // Admin user group
                                'platforms' => ['admin_panel'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_RAZORPAY_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the refund processing
                        log_message('error', '[PROCESS_REFUND_RAZORPAY_NOTIFICATION] Notification error: ' . $notificationError->getMessage());
                    }

                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                } else {
                    $response = [
                        'error' => false,
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'message' => "Booking can not be cancelled",
                    ];
                    return $response;
                }
            } else {
                $res = json_decode($payment['body'], true);
                $msg = $res['error']['description'];
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $msg,
                ];
                return $response;
            }
        }
        if ($type == "cashfree" && $transaction[0]['status'] == "success" && !empty($reference)) {
            $cashfree = new Cashfree();
            $refund = $cashfree->create_refund($reference, (float) $amount, 'refund_' . $order_id . '_' . time(), 'Auto refund on booking cancellation');
            $refund_status = strtoupper((string) ($refund['refund_status'] ?? ''));

            if (in_array($refund_status, ['SUCCESS', 'PENDING', 'PROCESSING', 'PROCESSED'])) {
                $refund_txn_id = (string) ($refund['cf_payment_id'] ?? ($refund['refund_id'] ?? $txn_id));
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'cashfree',
                    'txn_id' => $refund_txn_id,
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => strtolower($refund_status),
                    'message' => 'txn_refund_processed',
                    'partner_id' => $partner_id,
                    'reference' => (string) ($refund['refund_id'] ?? ''),
                ];
                $success = insert_details($data, 'transactions');
                if ($success) {
                    update_details(['status' => $status, 'isRefunded' => '1'], ['id' => $order_id], 'orders');
                    return [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                }
            }

            return [
                'error' => true,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'message' => $refund['message'] ?? 'Cashfree refund could not be processed',
            ];
        }
        if ($type == "paystack" && $transaction[0]['status'] == "success") {
            $paystack = new Paystack();
            $amount = $transaction[0]['amount'] / 100;
            $payment = $paystack->refund($txn_id, $amount);
            $message = json_decode($payment, true);
            if (isset($message['status']) && $message['status'] == 1) {
                $amount = intval($message['data']['amount']);
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'paystack',
                    'txn_id' => $message['data']['transaction']['id'],
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $message['data']['status'],
                    'message' => 'txn_refund_processed',
                    'partner_id' => $partner_id
                ];
                $success = insert_details($data, 'transactions');
                update_details(['status' => $status], ['id' => $order_id, 'isRefunded' => '1'], 'orders');
                if ($success) {
                    // Send notifications to user and admin when refund is successfully processed
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // Single generalized template works for both user and admin
                    try {
                        // Get user and order details for notification context
                        $user_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);

                        $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                        $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';

                        // Get refund transaction ID
                        $refund_transaction_id = $message['data']['transaction']['id'] ?? $txn_id;
                        $refund_id = $data['txn_id'] ?? $refund_transaction_id;

                        // Prepare context data for notification templates (generalized for both user and admin)
                        $notificationContext = [
                            'order_id' => $order_id,
                            'booking_id' => $order_id, // Add booking_id for template variables
                            'amount' => number_format($amount, 2),
                            'currency' => $currency,
                            'refund_id' => (string) $refund_id,
                            'transaction_id' => $refund_transaction_id,
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'customer_id' => $customer_id,
                            'processed_date' => date('d-m-Y H:i:s')
                        ];

                        // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                        // Send payment_refund_executed notification to customer (redirects to booking details)
                        queue_notification_service(
                            eventType: 'payment_refund_executed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_ids' => [$customer_id], // Send only to this specific customer
                                'platforms' => ['android', 'ios', 'web'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'booking_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'redirect_to' => 'booking_details_screen'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_PAYSTACK_USER_NOTIFICATION] Notification queued for user: ' . $customer_id . ', Result: ' . json_encode($userResult));

                        // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                        queue_notification_service(
                            eventType: 'payment_refund_successful',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_groups' => [1], // Admin user group
                                'platforms' => ['admin_panel'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_PAYSTACK_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the refund processing
                        log_message('error', '[PROCESS_REFUND_PAYSTACK_NOTIFICATION] Notification error: ' . $notificationError->getMessage());
                    }

                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                } else {
                    $response = [
                        'error' => false,
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'message' => "Booking can not be cancelled",
                    ];
                    return $response;
                }
            } else {
                $res = json_decode($payment, true);
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $res['message'],
                ];
                return $response;
            }
        }
        if ($type == "paypal" && $transaction[0]['status'] == 'success') {
            $paypal = new Paypal();
            $payment = $paypal->refund($txn_id, $amount, $transaction[0]['currency_code']);
            $message = json_decode($payment, true);
            if (isset($message['status']) && $message['status'] == "COMPLETED") {
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'paypal',
                    'txn_id' => $txn_id,
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $message['status'],
                    'message' => 'txn_refund_processed',
                    'partner_id' => $partner_id
                ];
                $success = insert_details($data, 'transactions');
                $response = [
                    'error' => false,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => "Payment Refund Successfully",
                ];
                if ($success) {
                    update_details(['status' => $status], ['id' => $order_id], 'orders');

                    // Send notifications to user and admin when refund is successfully processed
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // Single generalized template works for both user and admin
                    try {
                        // Get user and order details for notification context
                        $user_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);

                        $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                        $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';

                        // Get refund transaction ID
                        $refund_transaction_id = $txn_id;
                        $refund_id = $data['txn_id'] ?? $refund_transaction_id;

                        // Prepare context data for notification templates (generalized for both user and admin)
                        $notificationContext = [
                            'order_id' => $order_id,
                            'booking_id' => $order_id, // Add booking_id for template variables
                            'amount' => number_format($amount, 2),
                            'currency' => $currency,
                            'refund_id' => (string) $refund_id,
                            'transaction_id' => $refund_transaction_id,
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'customer_id' => $customer_id,
                            'processed_date' => date('d-m-Y H:i:s')
                        ];

                        // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                        // Send payment_refund_executed notification to customer (redirects to booking details)
                        queue_notification_service(
                            eventType: 'payment_refund_executed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_ids' => [$customer_id], // Send only to this specific customer
                                'platforms' => ['android', 'ios', 'web'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'booking_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'redirect_to' => 'booking_details_screen'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_PAYPAL_USER_NOTIFICATION] Notification queued for user: ' . $customer_id . ', Result: ' . json_encode($userResult));

                        // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                        queue_notification_service(
                            eventType: 'payment_refund_successful',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'user_groups' => [1], // Admin user group
                                'platforms' => ['admin_panel'],
                                'type' => 'refund',
                                'data' => [
                                    'order_id' => (string) $order_id,
                                    'refund_id' => (string) $refund_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[PROCESS_REFUND_PAYPAL_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the refund processing
                        log_message('error', '[PROCESS_REFUND_PAYPAL_NOTIFICATION] Notification error: ' . $notificationError->getMessage());
                    }

                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                }
                return $response;
            } else {
                $res = json_decode($payment, true);
                $msg = $res['message'];
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $msg,
                ];
                return $response;
            }
        }
        if ($type == "xendit" && $transaction[0]['status'] == 'success') {
            // Initialize Xendit library
            $xendit = new \App\Libraries\Xendit();

            // Create refund through Xendit API
            $payment = $xendit->refund_payment($txn_id, $amount, 'REQUESTED_BY_CUSTOMER');

            log_message('error', 'Xendit Refund Response: ' . json_encode($payment));

            if ($payment && isset($payment['status'])) {
                // Store additional refund data in reference column as JSON
                $refund_reference_data = [
                    'refund_id' => $payment['id'] ?? '',
                    'external_id' => $payment['external_id'] ?? '',
                    'payment_id' => $payment['payment_id'] ?? $txn_id,
                    'refund_reason' => $payment['reason'] ?? 'Customer requested refund',
                    'refund_fee' => $payment['fee'] ?? 0,
                    'refund_type' => 'manual_refund',
                    'processed_at' => date('Y-m-d H:i:s'),
                    'raw_response' => $payment
                ];

                // Check if refund was successful
                $refund_status = strtolower($payment['status']);

                // Xendit refund statuses: PENDING, SUCCEEDED, FAILED
                if (in_array($refund_status, ['pending', 'succeeded'])) {
                    $data = [
                        'transaction_type' => 'refund',
                        'order_id' => $order_id,
                        'user_id' => $customer_id,
                        'type' => 'xendit',
                        'txn_id' => $payment['id'] ?? $txn_id, // Use refund ID as transaction ID
                        'amount' => isset($payment['amount']) ? ($payment['amount'] / 100) : $amount, // Convert from cents
                        'currency_code' => $currency,
                        'status' => $refund_status,
                        'message' => 'txn_refund_processed',
                        'partner_id' => $partner_id,
                        'reference' => json_encode($refund_reference_data)
                    ];

                    $success = insert_details($data, 'transactions');

                    if ($success) {
                        // Update order status
                        update_details(['status' => $status, 'isRefunded' => '1'], ['id' => $order_id], 'orders');

                        // Send notifications to user and admin when refund is successfully processed
                        // Only send notifications if refund status is 'succeeded' (not 'pending')
                        // NotificationService handles FCM, Email, and SMS notifications using templates
                        // Single generalized template works for both user and admin
                        if ($refund_status === 'succeeded') {
                            try {
                                // Get user and order details for notification context
                                $user_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);

                                $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                                $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';

                                // Get refund transaction ID
                                $refund_transaction_id = $payment['id'] ?? $txn_id;
                                $refund_id = $data['txn_id'] ?? $refund_transaction_id;

                                // Prepare context data for notification templates (generalized for both user and admin)
                                $notificationContext = [
                                    'order_id' => $order_id,
                                    'booking_id' => $order_id, // Add booking_id for template variables
                                    'amount' => number_format(isset($payment['amount']) ? ($payment['amount'] / 100) : $amount, 2),
                                    'currency' => $currency,
                                    'refund_id' => (string) $refund_id,
                                    'transaction_id' => $refund_transaction_id,
                                    'customer_name' => $customer_name,
                                    'customer_email' => $customer_email,
                                    'customer_id' => $customer_id,
                                    'processed_date' => date('d-m-Y H:i:s')
                                ];

                                // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                                // Send payment_refund_executed notification to customer (redirects to booking details)
                                queue_notification_service(
                                    eventType: 'payment_refund_executed',
                                    recipients: [],
                                    context: $notificationContext,
                                    options: [
                                        'channels' => ['fcm', 'email', 'sms'],
                                        'user_ids' => [$customer_id], // Send only to this specific customer
                                        'platforms' => ['android', 'ios', 'web'],
                                        'type' => 'refund',
                                        'data' => [
                                            'order_id' => (string) $order_id,
                                            'booking_id' => (string) $order_id,
                                            'refund_id' => (string) $refund_id,
                                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                            'redirect_to' => 'booking_details_screen'
                                        ]
                                    ]
                                );

                                // log_message('info', '[PROCESS_REFUND_XENDIT_USER_NOTIFICATION] Notification queued for user: ' . $customer_id . ', Result: ' . json_encode($userResult));

                                // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                                queue_notification_service(
                                    eventType: 'payment_refund_successful',
                                    recipients: [],
                                    context: $notificationContext,
                                    options: [
                                        'channels' => ['fcm', 'email', 'sms'],
                                        'user_groups' => [1], // Admin user group
                                        'platforms' => ['admin_panel'],
                                        'type' => 'refund',
                                        'data' => [
                                            'order_id' => (string) $order_id,
                                            'refund_id' => (string) $refund_id,
                                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                        ]
                                    ]
                                );

                                // log_message('info', '[PROCESS_REFUND_XENDIT_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                            } catch (\Throwable $notificationError) {
                                // Log error but don't fail the refund processing
                                log_message('error', '[PROCESS_REFUND_XENDIT_NOTIFICATION] Notification error: ' . $notificationError->getMessage());
                            }
                        }

                        $response = [
                            'error' => false,
                            'csrfName' => csrf_token(),
                            'csrfHash' => csrf_hash(),
                            'message' => "Refund initiated successfully. Status: " . ucfirst($refund_status),
                        ];

                        // If refund is successful immediately
                        if ($refund_status === 'succeeded') {
                            $response['message'] = "Booking cancelled and refund processed successfully!";
                        } else {
                            $response['message'] = "Booking cancelled. Refund is being processed and will be completed shortly.";
                        }

                        return $response;
                    } else {
                        $response = [
                            'error' => true,
                            'csrfName' => csrf_token(),
                            'csrfHash' => csrf_hash(),
                            'message' => "Refund initiated but failed to update database",
                        ];
                        return $response;
                    }
                } else {
                    // Refund failed
                    log_message('error', 'Xendit Refund Failed: ' . json_encode($payment));

                    $response = [
                        'error' => true,
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'message' => "Refund failed: " . ($payment['failure_reason'] ?? 'Unknown error'),
                    ];
                    return $response;
                }
            } else {
                // API call failed
                log_message('error', 'Xendit Refund API call failed for TXN ID: ' . $txn_id);

                // Create pending transaction entry for failed refund
                $pending_refund_data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'xendit',
                    'txn_id' => $payment['id'] ?? $txn_id,
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => 'pending',
                    'message' => 'txn_manual_refund',
                    'partner_id' => $partner_id,
                    'reference' => json_encode([
                        'refund_id' => $payment['id'] ?? '',
                        'external_id' => $payment['external_id'] ?? '',
                        'payment_id' => $payment['payment_id'] ?? $txn_id,
                        'refund_reason' => 'Customer requested refund - Initial attempt failed',
                        'refund_type' => 'manual_refund_retry',
                        'processed_at' => date('Y-m-d H:i:s'),
                        'failure_reason' => $payment['failure_reason'] ?? 'Unknown error',
                        'raw_response' => $payment
                    ])
                ];

                $pending_success = insert_details($pending_refund_data, 'transactions');

                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => "Refund failed: " . ($payment['failure_reason'] ?? 'Unknown error') . ". A pending refund request has been created.",
                ];
                return $response;
            }
        }
    } else {
        $response = [
            'error' => true,
            'csrfName' => csrf_token(),
            'csrfHash' => csrf_hash(),
            'message' => 'No transactio found of this order!',
        ];
        return $response;
    }
}

function process_service_refund($order_id, $ordered_service_id, $status, $customer_id, $amount)
{
    $transaction = fetch_details('transactions', ['order_id' => $order_id, 'transaction_type' => 'transaction'], ['amount', 'txn_id', 'type', 'currency_code', 'status', 'reference']);
    if (isset($transaction) && !empty($transaction)) {
        $service_id = $ordered_service_id;
        $type = $transaction[0]['type'];
        $currency = $transaction[0]['currency_code'];
        $txn_id = $transaction[0]['txn_id'];
        $reference = $transaction[0]['reference'] ?? '';
        if ($type == "stripe" && $transaction[0]['status'] == 'succeeded') {
            $stripe = new Stripe();
            $payment = $stripe->refund($txn_id, $amount);
            if (isset($payment['status']) && $payment['status'] == "succeeded") {
                $amount = intval($payment['amount']) / 100;
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'stripe',
                    'txn_id' => $payment['payment_intent'],
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $payment['status'],
                ];
                $success = insert_details($data, 'transactions');
                $response = [
                    'error' => false,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => "Payment Refund Successfully",
                ];
                if ($success) {
                    update_details(['status' => $status], ['id' => $order_id], 'orders');
                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                }
                return $response;
            } else {
                $res = json_decode($payment['body']);
                $msg = $res->error->message;
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $msg,
                ];
                return $response;
            }
        }
        if ($type == "razorpay" && $transaction[0]['status'] == "captured") {
            $razorpay = new Razorpay();
            $payment = $razorpay->refund_payment($txn_id, $amount);
            if (isset($payment['status']) && $payment['status'] == "processed") {
                $amount = intval($payment['amount']) / 100;
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'razorpay',
                    'txn_id' => $payment['payment_id'],
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $payment['status'],
                ];
                $success = insert_details($data, 'transactions');
                if ($success) {
                    update_details(['status' => $status], ['id' => $order_id], 'orders');
                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                } else {
                    $response = [
                        'error' => false,
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'message' => "order can not be cancelled",
                    ];
                    return $response;
                }
            } else {
                $res = json_decode($payment['body'], true);
                $msg = $res['error']['description'];
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $msg,
                ];
                return $response;
            }
        }
        if ($type == "cashfree" && $transaction[0]['status'] == "success" && !empty($reference)) {
            $cashfree = new Cashfree();
            $refund = $cashfree->create_refund($reference, (float) $amount, 'refund_service_' . $order_id . '_' . time(), 'Service refund on booking cancellation');
            $refund_status = strtoupper((string) ($refund['refund_status'] ?? ''));
            if (in_array($refund_status, ['SUCCESS', 'PENDING', 'PROCESSING', 'PROCESSED'])) {
                $refund_txn_id = (string) ($refund['cf_payment_id'] ?? ($refund['refund_id'] ?? $txn_id));
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'cashfree',
                    'txn_id' => $refund_txn_id,
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => strtolower($refund_status),
                    'reference' => (string) ($refund['refund_id'] ?? ''),
                ];
                $success = insert_details($data, 'transactions');
                if ($success) {
                    update_details(['status' => $status], ['id' => $order_id], 'orders');
                    return [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                }
            }
            return [
                'error' => true,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'message' => $refund['message'] ?? 'Cashfree refund could not be processed',
            ];
        }
        if ($type == "paystack" && $transaction[0]['status'] == "success") {
            $paystack = new Paystack();
            $payment = $paystack->refund($txn_id, $amount);
            $message = json_decode($payment, true);
            if (isset($payment['status']) && $payment['status'] == "true") {
                update_details(['status' => $status], ['id' => $order_id], 'orders');
                $amount = intval($payment['amount']) / 100;
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'paystack',
                    'txn_id' => $payment['payment_id'],
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $payment['status'],
                ];
                $success = insert_details($data, 'transactions');
                if ($success) {
                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                } else {
                    $response = [
                        'error' => false,
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'message' => "Booking can not be cancelled",
                    ];
                    return $response;
                }
            } else {
                $res = json_decode($payment, true);
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $res['message'],
                ];
                return $response;
            }
        }
        if ($type == 'flutterwave' && $transaction[0]['status'] == "successfull") {
            $flutterwave = new Flutterwave();
            $payment = $flutterwave->refund_payment($txn_id, $amount);
            $payment = json_decode($payment);
            if (isset($payment->status) && $payment->status == 'success') {
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'paystack',
                    'txn_id' => $payment['payment_id'],
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => $payment['status'],
                ];
                $success = insert_details($data, 'transactions');
                $response['error'] = false;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['message'] = "Payment Refund Successfully";
                if ($success) {
                    update_details(['status' => $status], ['id' => $order_id], 'orders');
                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                }
            } else {
                $message = json_decode($payment, true);
                $response['error'] = true;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['message'] = $message['message'];
            }
        }
        if ($type == "paypal" && $transaction[0]['status'] == 'success') {
            $paypal = new Paypal();
            $payment = $paypal->refund($txn_id, $amount, $transaction[0]['currency_code']);
            $message = json_decode($payment, true);
            if (isset($message['status']) && $message['status'] == "COMPLETED") {
                $data = [
                    'transaction_type' => 'refund',
                    'order_id' => $order_id,
                    'user_id' => $customer_id,
                    'type' => 'paypal',
                    'txn_id' => $txn_id,
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'status' => 'success',
                ];
                $success = insert_details($data, 'transactions');
                $response = [
                    'error' => false,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => "Payment Refund Successfully",
                ];
                if ($success) {
                    update_details(['status' => $status], ['id' => $order_id], 'orders');
                    $response = [
                        'error' => false,
                        'message' => "Booking cancelled Successfully!",
                    ];
                    return $response;
                }
                return $response;
            } else {
                $res = json_decode($payment['body']);
                $msg = $res->error->message;
                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => $msg,
                ];
                return $response;
            }
        }
        if ($type == "xendit" && $transaction[0]['status'] == 'success') {
            // Initialize Xendit library
            $xendit = new \App\Libraries\Xendit();

            // Create partial refund through Xendit API
            $payment = $xendit->refund_payment($txn_id, $amount, 'REQUESTED_BY_CUSTOMER');

            log_message('error', 'Xendit Service Refund Response: ' . json_encode($payment));

            if ($payment && isset($payment['status'])) {
                // Store additional refund data in reference column as JSON
                $refund_reference_data = [
                    'refund_id' => $payment['id'] ?? '',
                    'external_id' => $payment['external_id'] ?? '',
                    'payment_id' => $payment['payment_id'] ?? $txn_id,
                    'refund_reason' => $payment['reason'] ?? 'Partial service refund requested',
                    'refund_fee' => $payment['fee'] ?? 0,
                    'refund_type' => 'service_refund',
                    'service_id' => $service_id,
                    'processed_at' => date('Y-m-d H:i:s'),
                    'raw_response' => $payment
                ];

                // Check if refund was successful
                $refund_status = strtolower($payment['status']);

                // Xendit refund statuses: PENDING, SUCCEEDED, FAILED
                if (in_array($refund_status, ['pending', 'succeeded'])) {
                    $data = [
                        'transaction_type' => 'refund',
                        'order_id' => $order_id,
                        'user_id' => $customer_id,
                        'type' => 'xendit',
                        'txn_id' => $payment['id'] ?? $txn_id, // Use refund ID as transaction ID
                        'amount' => isset($payment['amount']) ? ($payment['amount'] / 100) : $amount, // Convert from cents
                        'currency_code' => $currency,
                        'status' => $refund_status,
                        'message' => 'txn_refund_processed',
                        'reference' => json_encode($refund_reference_data)
                    ];

                    $success = insert_details($data, 'transactions');

                    if ($success) {
                        // Update order status
                        update_details(['status' => $status], ['id' => $order_id], 'orders');

                        $response = [
                            'error' => false,
                            'csrfName' => csrf_token(),
                            'csrfHash' => csrf_hash(),
                            'message' => "Service refund initiated successfully. Status: " . ucfirst($refund_status),
                        ];

                        // If refund is successful immediately
                        if ($refund_status === 'succeeded') {
                            $response['message'] = "Service refund processed successfully!";
                        } else {
                            $response['message'] = "Service refund is being processed and will be completed shortly.";
                        }

                        return $response;
                    } else {
                        $response = [
                            'error' => true,
                            'csrfName' => csrf_token(),
                            'csrfHash' => csrf_hash(),
                            'message' => "Service refund initiated but failed to update database",
                        ];
                        return $response;
                    }
                } else {
                    // Refund failed
                    log_message('error', 'Xendit Service Refund Failed: ' . json_encode($payment));

                    $response = [
                        'error' => true,
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'message' => "Service refund failed: " . ($payment['failure_reason'] ?? 'Unknown error'),
                    ];
                    return $response;
                }
            } else {
                // API call failed
                log_message('error', 'Xendit Service Refund API call failed for TXN ID: ' . $txn_id);

                $response = [
                    'error' => true,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'message' => "Failed to process service refund. Please try again or contact support.",
                ];
                return $response;
            }
        }
    } else {
        $response = [
            'error' => true,
            'csrfName' => csrf_token(),
            'csrfHash' => csrf_hash(),
            'message' => 'No transaction found of this order!',
        ];
        return $response;
    }
}

function get_settings($type = 'system_settings', $is_json = false, $bool = false)
{
    $db = \Config\Database::connect();
    $builder = $db->table('settings');
    if ($type == 'all') {
        $res = $builder->select(' * ')->get()->getResultArray();
    } else {
        $res = $builder->select(' * ')->where('variable', $type)->get()->getResultArray();
    }
    if (!empty($res)) {
        if ($is_json) {
            return json_decode($res[0]['value'], true);
        } else {
            return $res[0]['value'];
        }
    } else {
        if ($bool) {
            return false;
        } else {
            return [];
        }
    }
}

/**
 * Get configured map provider ('google' or 'openstreetmap') from api_key_settings.
 * Defaults to 'google' when unset/missing, so existing installs behave unchanged.
 *
 * @return string
 */
function get_map_provider(): string
{
    $settings = get_settings('api_key_settings', true);
    if (!empty($settings) && !empty($settings['map_provider']) && $settings['map_provider'] === 'openstreetmap') {
        return 'openstreetmap';
    }
    return 'google';
}

/**
 * Get current system version for panel asset cache busting (`?v=<version>`).
 * Bumped in .env (APP_VERSION) by Admin/Updater::upload_update_file() on each
 * successful update, so bundled assets bust cache only on real deploys instead
 * of on every request (as `?v=<?= time() ?>` did).
 *
 * Falls back to the `updates` table's latest version, then '1.0', when
 * APP_VERSION is unset (fresh install before .env has been written).
 *
 * @return string
 */
function get_system_version(): string
{
    $version = trim((string) env('APP_VERSION', ''));
    if ($version !== '') {
        return $version;
    }

    try {
        $db = \Config\Database::connect();
        $row = $db->table('updates')->select('version')->orderBy('id', 'DESC')->get(1)->getRowArray();
        if (!empty($row['version'])) {
            return trim((string) $row['version']);
        }
    } catch (\Throwable $th) {
        log_message('error', date('Y-m-d H:i:s') . ' --> app/Helpers/function_helper.php - get_system_version()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
    }

    return '1.0';
}

/**
 * Convert a maintenance schedule range from local timezone to UTC for storage.
 * Admin submits "start to end" in system/local time (e.g. IST); we store only UTC in the DB.
 *
 * @param string $rangeString Range in format "Y-m-d H:i to Y-m-d H:i" (local time).
 * @param string $systemTimezone Timezone for the incoming times (e.g. "Asia/Kolkata" or from settings).
 * @return string Range in format "Y-m-d H:i to Y-m-d H:i" in UTC, or original string on parse error.
 */
function maintenance_schedule_local_to_utc(string $rangeString, string $systemTimezone = 'Asia/Kolkata'): string
{
    $rangeString = trim($rangeString);
    if ($rangeString === '') {
        return '';
    }
    // Daterangepicker sends "start to end" (separator " to " with spaces); support both " to " and "to".
    $parts = preg_split('/\s+to\s+/i', $rangeString, 2);
    if (count($parts) !== 2) {
        $parts = explode('to', $rangeString);
    }
    if (count($parts) !== 2) {
        return $rangeString;
    }
    $startStr = trim($parts[0]);
    $endStr = trim($parts[1]);
    if ($startStr === '' || $endStr === '') {
        return $rangeString;
    }
    try {
        $tzLocal = new \DateTimeZone($systemTimezone);
        $tzUtc = new \DateTimeZone('UTC');
        // Parse incoming datetimes as local (system) time. Try explicit format first (Y-m-d H:i), then fallback.
        $startLocal = \DateTime::createFromFormat('Y-m-d H:i', $startStr, $tzLocal);
        if ($startLocal === false) {
            $startLocal = new \DateTime($startStr, $tzLocal);
        }
        $endLocal = \DateTime::createFromFormat('Y-m-d H:i', $endStr, $tzLocal);
        if ($endLocal === false) {
            $endLocal = new \DateTime($endStr, $tzLocal);
        }
        // Convert to UTC and format as Y-m-d H:i for storage.
        $startUtc = clone $startLocal;
        $startUtc->setTimezone($tzUtc);
        $endUtc = clone $endLocal;
        $endUtc->setTimezone($tzUtc);
        return $startUtc->format('Y-m-d H:i') . ' to ' . $endUtc->format('Y-m-d H:i');
    } catch (\Exception $e) {
        return $rangeString;
    }
}

/**
 * Convert a maintenance schedule range from UTC (stored) to local timezone for display in admin UI.
 * Keeps the same "start to end" format so the UI does not change.
 *
 * @param string $rangeString Range in format "Y-m-d H:i to Y-m-d H:i" (UTC in DB).
 * @param string $systemTimezone Timezone for display (e.g. "Asia/Kolkata" or from settings).
 * @return string Range in local time for the form, or original string on parse error.
 */
function maintenance_schedule_utc_to_local(string $rangeString, string $systemTimezone = 'Asia/Kolkata'): string
{
    $rangeString = trim($rangeString);
    if ($rangeString === '') {
        return '';
    }
    $parts = explode('to', $rangeString);
    if (count($parts) !== 2) {
        return $rangeString;
    }
    $startStr = trim($parts[0]);
    $endStr = trim($parts[1]);
    if ($startStr === '' || $endStr === '') {
        return $rangeString;
    }
    try {
        $tzUtc = new \DateTimeZone('UTC');
        $tzLocal = new \DateTimeZone($systemTimezone);
        // Parse stored datetimes as UTC.
        $startUtc = new \DateTime($startStr, $tzUtc);
        $endUtc = new \DateTime($endStr, $tzUtc);
        // Convert to local for display.
        $startLocal = clone $startUtc;
        $startLocal->setTimezone($tzLocal);
        $endLocal = clone $endUtc;
        $endLocal->setTimezone($tzLocal);
        return $startLocal->format('Y-m-d H:i') . ' to ' . $endLocal->format('Y-m-d H:i');
    } catch (\Exception $e) {
        return $rangeString;
    }
}

/**
 * Check if provider app (and partner panel) is currently in maintenance mode.
 * Schedule is stored in UTC; all comparisons are done in UTC.
 *
 * @return array{active: bool, message: string, end_at: int} active = true when current time is within
 *         the configured maintenance schedule; message = same as provider app (message_for_provider_application);
 *         end_at = Unix timestamp (seconds) when maintenance ends, for JS countdown; 0 when not active.
 */
function is_provider_app_maintenance_mode(): array
{
    $general = get_settings('general_settings', true);
    if (empty($general)) {
        return ['active' => false, 'message' => '', 'end_at' => 0];
    }

    // Maintenance must be enabled in settings.
    if (!isset($general['provider_app_maintenance_mode']) || $general['provider_app_maintenance_mode'] != 1) {
        return ['active' => false, 'message' => '', 'end_at' => 0];
    }

    // Parse schedule date range (stored as "start to end" in UTC).
    $scheduleDate = isset($general['provider_app_maintenance_schedule_date'])
        ? explode('to', $general['provider_app_maintenance_schedule_date'])
        : null;
    if (empty($scheduleDate)) {
        return ['active' => false, 'message' => '', 'end_at' => 0];
    }

    $startDate = isset($scheduleDate[0]) ? trim($scheduleDate[0]) : '';
    $endDate = isset($scheduleDate[1]) ? trim($scheduleDate[1]) : '';
    if (empty($startDate) || empty($endDate)) {
        return ['active' => false, 'message' => '', 'end_at' => 0];
    }

    // All comparisons in UTC: stored range is UTC, so compare with current time in UTC.
    try {
        $tzUtc = new \DateTimeZone('UTC');
        $nowUtc = new \DateTime('now', $tzUtc);
        $startUtc = new \DateTime($startDate, $tzUtc);
        $endUtc = new \DateTime($endDate, $tzUtc);
        if (!($nowUtc >= $startUtc && $nowUtc <= $endUtc)) {
            return ['active' => false, 'message' => '', 'end_at' => 0];
        }
        $expiryTime = $endUtc->getTimestamp();
    } catch (\Exception $e) {
        return ['active' => false, 'message' => '', 'end_at' => 0];
    }

    // Resolve message: same as provider app (message_for_provider_application), with language fallback.
    $messageField = $general['message_for_provider_application'] ?? '';
    if (is_array($messageField)) {
        $message = resolve_translation_fallback($messageField, [get_current_language(), get_default_language()]);
    } else {
        $message = is_string($messageField) ? $messageField : '';
    }

    // end_at: Unix timestamp when maintenance ends (for JS countdown).
    return ['active' => true, 'message' => $message, 'end_at' => (int) $expiryTime];
}

/**
 * Pick the first non-empty translation using the provided priority order.
 * Falls back to the first available non-empty translation if none of the
 * preferred languages contain data. This keeps default language fields
 * populated even when their translation is missing.
 *
 * @param array $translations Language keyed translation array.
 * @param array $priorityLanguages Ordered list of language codes to prefer.
 *
 * @return string The best available translation or empty string.
 */
function resolve_translation_fallback(array $translations, array $priorityLanguages = []): string
{
    if (empty($translations)) {
        return '';
    }

    // Always prioritize explicit language choices first
    foreach ($priorityLanguages as $langCode) {
        if (empty($langCode)) {
            continue;
        }

        if (!empty($translations[$langCode]) && is_string($translations[$langCode])) {
            return $translations[$langCode];
        }
    }

    // Nothing matched the priority list, so return the first non-empty entry
    foreach ($translations as $content) {
        if (!empty($content) && is_string($content)) {
            return $content;
        }
    }

    return '';
}

function escape_array($array)
{
    $db = \Config\Database::connect();
    $posts = [];
    if (!empty($array)) {
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                // Only escape strings, leave other types as is
                $posts[$key] = is_string($value) ? $db->escapeString($value) : $value;
            }
        } else {
            // Escape only if it's a string
            return is_string($array) ? $db->escapeString($array) : $array;
        }
    }
    return $posts;
}

function update_details($set, $where, $table, $escape = true)
{
    $db = \Config\Database::connect();
    $db->transStart();
    if ($escape) {
        $set = escape_array($set);
    }
    $db->table($table)->update($set, $where);
    $db->transComplete();
    $response = false;
    if ($db->transStatus() === true) {
        $response = true;
    }
    // print_r($db->getLastQuery());
    // die;
    return $response;
}

function fetch_details($table, $where = [], $fields = [], $limit = "", $offset = '0', $sort = 'id', $order = 'DESC', $where_in_key = '', $where_in_value = [], $or_like = [])
{
    $db = \Config\Database::connect();
    // // Guard: skip if table missing (e.g. pending migration). Cache per-request.
    // static $table_exists_cache = [];
    // if (!array_key_exists($table, $table_exists_cache)) {
    //     try {
    //         $table_exists_cache[$table] = $db->tableExists($table);
    //     } catch (\Throwable $e) {
    //         $table_exists_cache[$table] = false;
    //     }
    // }
    // if (!$table_exists_cache[$table]) {
    //     log_message('error', 'fetch_details: table missing - ' . $table);
    //     return [];
    // }
    $builder = $db->table($table);
    if (!empty($fields)) {
        $builder = $builder->select($fields);
    }
    if (!empty($where)) {
        $builder = $builder->where($where);
    }
    if (!empty($where_in_key) && !empty($where_in_value)) {
        $builder = $builder->whereIn($where_in_key, $where_in_value);
    }
    if (isset($or_like) && !empty($or_like)) {
        $builder->groupStart();
        $builder->orLike($or_like);
        $builder->groupEnd();
    }
    if ($limit != null && $limit != "") {
        $builder = $builder->limit($limit, $offset);
    }
    $builder = $builder->orderBy($sort, $order);
    $res = $builder->get()->getResultArray();

    return $res;
}

/**
 * Get category IDs including all descendants at every level (n-levels deep).
 * Recursively fetches all child categories until no more children exist.
 * Use this when you need providers from a category and all nested subcategories.
 *
 * @param array $category_ids Array of category IDs to start from (e.g. [5] or [5, 10])
 * @return array Flat array of all category IDs (original + all descendants at any depth)
 */
function getAllDescendantCategoryIds(array $category_ids): array
{
    if (empty($category_ids)) {
        return [];
    }
    // Ensure we have a list of IDs to search from (copy so we can extend it)
    $all_ids = array_values(array_unique(array_map('intval', $category_ids)));
    $to_check = $all_ids;
    // Keep fetching children of the current set until no new children are found
    do {
        $children = fetch_details(
            table: 'categories',
            where: [],
            fields: ['id'],
            where_in_key: 'parent_id',
            where_in_value: $to_check
        );
        $new_ids = [];
        foreach ($children as $row) {
            $id = (int) $row['id'];
            if (!in_array($id, $all_ids, true)) {
                $new_ids[] = $id;
                $all_ids[] = $id;
            }
        }
        $to_check = $new_ids;
    } while (!empty($to_check));
    return $all_ids;
}

/**
 * Return the booking statuses that should consume subscription limits.
 * Keeping it centralized makes it easy to tweak behaviour later.
 *
 * @return array
 */
function get_subscription_limit_countable_statuses(): array
{
    return ['started', 'completed'];
}

/**
 * Count only the bookings that progressed far enough to consume subscription limits.
 * We pass an optional DB connection so long-running loops can reuse the same handle.
 *
 * @param int|string $partner_id
 * @param string $subscription_purchase_date
 * @param array $statuses
 * @param \CodeIgniter\Database\BaseConnection|null $dbConnection
 * @return int
 */
function count_orders_towards_subscription_limit(
    int $partner_id,
    string $subscription_purchase_date,
    array $statuses = [],
    $dbConnection = null
): int {
    if (!$partner_id || !$subscription_purchase_date) {
        return 0;
    }

    $db = $dbConnection ?? \Config\Database::connect();
    $shouldClose = $dbConnection === null;

    $statuses = $statuses ?: get_subscription_limit_countable_statuses();

    $builder = $db->table('orders')
        ->select('COUNT(orders.id) AS total')
        ->where('orders.partner_id', $partner_id)
        ->where('orders.created_at >', $subscription_purchase_date)
        ->where('orders.parent_id IS NULL', null, false)
        ->groupStart()
        ->where('orders.payment_status !=', 2)
        ->orWhere('orders.payment_status IS NULL', null, false)
        ->groupEnd();

    if (!empty($statuses)) {
        $builder->whereIn('orders.status', $statuses);
    }

    $row = $builder->get()->getRow();

    if ($shouldClose) {
    }

    return (int) ($row->total ?? 0);
}

function exists($where, $table)
{
    $db = \Config\Database::connect();
    $builder = $db->table($table);
    $builder = $builder->where($where);
    $res = count($builder->get()->getResultArray());
    if ($res > 0) {
        return true;
    } else {
        return false;
    }
}

function verify_payment_transaction($txn_id, $payment_method, $additional_data = [])
{
    $db = \Config\Database::connect();
    if (empty(trim($txn_id))) {
        $response['error'] = true;
        $response['message'] = "Transaction ID is required";
        return $response;
    }
    $razorpay = new Razorpay;
    switch ($payment_method) {
        case 'razorpay':
            $payment = $razorpay->fetch_payments($txn_id);
            if (!empty($payment) && isset($payment['status'])) {
                if ($payment['status'] == 'authorized') {
                    $capture_response = $razorpay->capture_payment($payment['amount'], $txn_id, $payment['currency']);
                    if ($capture_response['status'] == 'captured') {
                        $response['error'] = false;
                        $response['message'] = "Payment captured successfully";
                        $response['amount'] = $capture_response['amount'] / 100;
                        $response['data'] = $capture_response;
                        $response['status'] = $payment['status'];
                        return $response;
                    } else if ($capture_response['status'] == 'refunded') {
                        $response['error'] = true;
                        $response['message'] = "Payment is refunded.";
                        $response['amount'] = $capture_response['amount'] / 100;
                        $response['data'] = $capture_response;
                        $response['status'] = $payment['status'];
                        return $response;
                    } else {
                        $response['error'] = true;
                        $response['message'] = "Payment could not be captured.";
                        $response['amount'] = (isset($capture_response['amount'])) ? $capture_response['amount'] / 100 : 0;
                        $response['data'] = $capture_response;
                        $response['status'] = $payment['status'];
                        return $response;
                    }
                } else if ($payment['status'] == 'captured') {
                    $status = 'captured';
                    $response['error'] = false;
                    $response['message'] = "Payment captured successfully";
                    $response['amount'] = $payment['amount'] / 100;
                    $response['status'] = $payment['status'];
                    $response['data'] = $payment;
                    return $response;
                } else if ($payment['status'] == 'created') {
                    $status = 'created';
                    $response['error'] = true;
                    $response['message'] = "Payment is just created and yet not authorized / captured!";
                    $response['amount'] = $payment['amount'] / 100;
                    $response['data'] = $payment;
                    $response['status'] = $payment['status'];
                    return $response;
                } else {
                    $status = 'failed';
                    $response['error'] = true;
                    $response['message'] = "Payment is " . ucwords($payment['status']) . "! ";
                    $response['amount'] = (isset($payment['amount'])) ? $payment['amount'] / 100 : 0;
                    $response['status'] = $payment['status'];
                    $response['data'] = $payment;
                    return $response;
                }
            } else {
                $response['error'] = true;
                $response['message'] = "Payment not found by the transaction ID!";
                $response['amount'] = 0;
                $response['data'] = [];
                $response['status'] = 'failed';
                return $response;
            }
            break;
        case "paystack":
            $paystack = new Paystack;
            $payment = $paystack->verify_transation($txn_id);
            if (!empty($payment)) {
                $payment = json_decode($payment, true);
                if (isset($payment['data']['status']) && $payment['data']['status'] == 'success') {
                    $response['error'] = false;
                    $response['message'] = "Payment is successful";
                    $response['amount'] = (isset($payment['data']['amount'])) ? $payment['data']['amount'] / 100 : 0;
                    $response['data'] = $payment;
                    $response['status'] = $payment['data']['status'];
                    return $response;
                } elseif (isset($payment['data']['status']) && $payment['data']['status'] != 'success') {
                    $response['error'] = true;
                    $response['message'] = "Payment is " . ucwords($payment['data']['status']) . "! ";
                    $response['amount'] = (isset($payment['data']['amount'])) ? $payment['data']['amount'] / 100 : 0;
                    $response['data'] = $payment;
                    $response['status'] = $payment['data']['status'];
                    return $response;
                } else {
                    $response['error'] = true;
                    $response['message'] = "Payment is unsuccessful! ";
                    $response['amount'] = (isset($payment['data']['amount'])) ? $payment['data']['amount'] / 100 : 0;
                    $response['data'] = $payment;
                    return $response;
                }
            } else {
                $response['error'] = true;
                $response['message'] = "Payment not found by the transaction ID!";
                $response['amount'] = 0;
                $response['data'] = [];
                $response['status'] = 'failed';
                return $response;
            }
            break;
        case 'paytm':
            $paytm = new Paytm;
            $payment = $paytm->transaction_status($txn_id);
            if (!empty($payment)) {
                $payment = json_decode($payment, true);
                if (
                    isset($payment['body']['resultInfo']['resultCode'])
                    && ($payment['body']['resultInfo']['resultCode'] == '01' && $payment['body']['resultInfo']['resultStatus'] == 'TXN_SUCCESS')
                ) {
                    $response['error'] = false;
                    $response['message'] = "Payment is successful";
                    $response['amount'] = (isset($payment['body']['txnAmount'])) ? $payment['body']['txnAmount'] : 0;
                    $response['data'] = $payment;
                    return $response;
                } elseif (
                    isset($payment['body']['resultInfo']['resultCode'])
                    && ($payment['body']['resultInfo']['resultStatus'] == 'TXN_FAILURE')
                ) {
                    $response['error'] = true;
                    $response['message'] = $payment['body']['resultInfo']['resultMsg'];
                    $response['amount'] = (isset($payment['body']['txnAmount'])) ? $payment['body']['txnAmount'] : 0;
                    $response['data'] = $payment;
                    return $response;
                } else if (
                    isset($payment['body']['resultInfo']['resultCode'])
                    && ($payment['body']['resultInfo']['resultStatus'] == 'PENDING')
                ) {
                    $response['error'] = true;
                    $response['message'] = $payment['body']['resultInfo']['resultMsg'];
                    $response['amount'] = (isset($payment['body']['txnAmount'])) ? $payment['body']['txnAmount'] : 0;
                    $response['data'] = $payment;
                    return $response;
                } else {
                    $response['error'] = true;
                    $response['message'] = "Payment is unsuccessful!";
                    $response['amount'] = (isset($payment['body']['txnAmount'])) ? $payment['body']['txnAmount'] : 0;
                    $response['data'] = $payment;
                    return $response;
                }
            } else {
                $response['error'] = true;
                $response['message'] = "Payment not found by the Order ID!";
                $response['amount'] = 0;
                $response['data'] = [];
                return $response;
            }
            break;
    }
}

function add_transaction($transaction_details)
{
    $db = \Config\Database::connect();
    $insert = $db->table('transactions')->insert($transaction_details);
    if ($insert) {
        return $db->insertID();
    } else {
        return false;
    }
}

function valid_image($image)
{
    helper(['form', 'url']);
    $request = \Config\Services::request();
    if ($request->getFile($image)) {
        $file = $request->getFile($image);
        if (!$file->isValid()) {
            return false;
        }
        $type = $file->getMimeType();
        if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg' || $type == 'image/svg+xml' || $type = 'image/gif') {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function formatOffset($offset)
{
    if ($offset === 0) {
        return '+00:00';
    }

    $sign = $offset >= 0 ? '+' : '-';
    $offset = abs($offset);
    $hours = floor($offset / 3600);
    $minutes = floor(($offset % 3600) / 60);

    return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
}

function get_timezone_array()
{
    $zones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    $options = [];

    foreach ($zones as $tz) {
        $zone = new DateTimeZone($tz);

        // Use a fixed reference time to avoid DST madness
        $ref = new DateTime('now', new DateTimeZone('UTC'));
        $offset = $zone->getOffset($ref);

        $options[] = [
            'time' => (new DateTime('now', $zone))->format('h:i A'),
            'offset' => $offset,
            'offset_text' => formatOffset($offset),
            'timezone_id' => $tz,
        ];
    }

    // Sort by offset
    usort($options, fn($a, $b) => $a['offset'] <=> $b['offset']);

    return $options;
}

function check_exists($file)
{
    $target_path = FCPATH . $file;
    if (!file_exists($target_path)) {
        return true;
    } else {
        return false;
    }
}

function get_system_update_info()
{
    $check_query = false;
    $query_path = "";
    $data['previous_error'] = false;
    $sub_directory = (file_exists(UPDATE_PATH . "update/updater.json")) ? "update/" : "";
    if (file_exists(UPDATE_PATH . "updater.json") || file_exists(UPDATE_PATH . "update/updater.json")) {
        $lines_array = file_get_contents(UPDATE_PATH . $sub_directory . "updater.json");
        $lines_array = json_decode($lines_array, true);
        $file_version = $lines_array['version'];
        $file_previous = $lines_array['previous'];
        $check_query = $lines_array['manual_queries'];
        $query_path = $lines_array['query_path'];
    } else {
        print_r("no json exists");
        die();
    }
    $db_version_data = fetch_details("updates");
    if (!empty($db_version_data) && isset($db_version_data[0]['version'])) {
        $db_current_version = $db_version_data[0]['version'];
    }
    if (!empty($db_current_version)) {
        $data['db_current_version'] = $db_current_version;
    } else {
        $data['db_current_version'] = $db_current_version = 1.0;
    }
    if ($db_current_version == $file_previous) {
        $data['file_current_version'] = $file_current_version = $file_version;
    } else {
        $data['previous_error'] = true;
        $data['file_current_version'] = $file_current_version = false;
    }
    if ($file_current_version != false && $file_current_version > $db_current_version) {
        $data['is_updatable'] = true;
    } else {
        $data['is_updatable'] = false;
    }
    $data['query'] = $check_query;
    $data['query_path'] = $query_path;
    return $data;
}

function labels(string $label, string $alt = '', array $params = [])
{
    //If label is array
    if (is_array($label)) {
        $translated = [];
        foreach ($label as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if (lang('Text.' . $value, $params) != 'Text.' . $value) {
                    $translated[$key] = (lang('Text.' . $value, $params) == '') ? trim($alt) : trim(lang('Text.' . $value, $params));
                } else {
                    //as alt shall be an array as well
                    if (is_array($alt)) {
                        foreach ($alt as $a) {
                            $translated[$key] = trim($a);
                        }
                    }
                }
            }
        }
        return $translated;
    }
    // If label is string
    $label = trim($label);
    if (lang('Text.' . $label, $params) != 'Text.' . $label) {
        if (lang('Text.' . $label, $params) == '') {
            return ($alt !== '') ? $alt : $label;
        }
        return trim(lang('Text.' . $label, $params));
    } else {
        return ($alt !== '') ? trim($alt) : $label;
    }
}

/**
 * Translated "Cannot update booking — payment <status>" message for the payment gate
 * shared by handyman/partner booking status updates (panel controllers, views, and API).
 */
function payment_block_message(?string $paymentStatus): string
{
    $status = $paymentStatus ?: 'pending';
    $statusLabel = strtolower(labels($status, ucfirst($status)));
    return sprintf(labels('cannot_update_booking_payment', 'Cannot update booking — payment %s'), $statusLabel);
}

function get_currency()
{
    try {
        $currency = get_settings('general_settings', true)['currency'];
        if ($currency == '') {
            $currency = '₹';
        }
    } catch (Exception $e) {
        $currency = '₹';
    }
    return $currency;
}

function delete_directory($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . "/" . $object) == "dir") {
                    $dir_sec = $dir . "/" . $object;
                    if (is_dir($dir_sec)) {
                        $objects_sec = scandir($dir_sec);
                        foreach ($objects_sec as $object_sec) {
                            if ($object_sec != "." && $object_sec != "..") {
                                if (filetype($dir_sec . "/" . $object_sec) == "dir") {
                                    rmdir($dir_sec . "/" . $object_sec);
                                } else {
                                    unlink($dir_sec . "/" . $object_sec);
                                }
                            }
                        }
                        rmdir($dir_sec);
                    }
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        return rmdir($dir);
    }
}

function insert_details(array $data, string $table): array
{
    $db = \Config\Database::connect();
    $status = $db->table($table)->insert($data);
    $id = $db->insertID();
    if (!$status) {
        return [
            "error" => true,
            "message" => UNKNOWN_ERROR_MESSAGE,
            "data" => [],
        ];
    }
    return [
        "error" => false,
        "message" => "Data inserted",
        "id" => $id,
        "data" => [],
    ];
}

function remove_null_values($data)
{
    $integer = [
        'alternate_mobile' => 0,
        'range_wise_charges' => 0,
        'per_km_charge' => 0,
        'max_deliverable_distance' => 0,
        'fixed_charge' => 0,
        'discount' => 0,
    ];
    $array = [];
    foreach ($data as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $data[$key] = remove_null_values($value);
        } else {
            if (is_null($value)) {
                if (isset($integer[$key])) {
                    $data[$key] = 0;
                } else if (isset($array[$key])) {
                    $data[$key] = [];
                } else {
                    $data[$key] = '';
                }
            }
        }
    }
    return $data;
}

if (!function_exists('response_helper')) {
    function response_helper(string $message = UNKNOWN_ERROR_MESSAGE, bool $error = true, $data = [], int $status_code = 200, $additional_data = [])
    {
        $response = \Config\Services::response();
        $send = [
            "error" => $error,
            "message" => $message,
            "data" => $data,
        ];
        $send = array_merge($send, $additional_data);
        return $response->setJSON($send)->setStatusCode($status_code);
    }
}

function delete_details(array $data, string $table)
{
    $db = \Config\Database::connect();
    $builder = $db->table($table);
    if ($builder->delete($data)) {
        return true;
    }
    return false;
}

function validate_promo_code($user_id, $promo_code, $final_total)
{
    $db = \Config\Database::connect();
    $builder = $db->table('promo_codes pc');
    // Count distinct users so repeat usages by the same customer do not exhaust the global user cap.
    $promo_code = $builder->select('pc.*,COUNT(DISTINCT o.user_id) as promo_used_counter ,( SELECT count(user_id) from orders where user_id =' . $user_id . ' and promocode_id ="' . $promo_code . '") as user_promo_usage_counter ')
        ->join('orders o', 'o.promocode_id=pc.id', 'left')
        ->where(['pc.id' => $promo_code, 'pc.status' => '1', ' start_date <= ' => date('Y-m-d'), '  end_date >= ' => date('Y-m-d')])
        ->get()->getResultArray();
    // Add translated message to promo code data if promo code exists
    if (!empty($promo_code[0]['id'])) {
        // Get language from header using existing helper function
        $requestedLanguage = get_current_language_from_request();
        $defaultLanguage = get_default_language();

        // Initialize translation model
        $translationModel = new \App\Models\TranslatedPromocodeModel();

        // Get default language translation from translations table
        $defaultTranslation = $translationModel->getTranslation($promo_code[0]['id'], $defaultLanguage);

        // Get requested language translation
        $requestedTranslation = $translationModel->getTranslation($promo_code[0]['id'], $requestedLanguage);

        // Set translated message based on fallback logic:
        // 1. If requested language translation exists → use it
        // 2. If requested language translation doesn't exist → use empty string
        if ($requestedTranslation) {
            $promo_code[0]['translated_message'] = $requestedTranslation;
        } else {
            $promo_code[0]['translated_message'] = '';
        }

        // Update main message field with default language value from translations table
        // If no translation exists, keep the original message from main table
        if ($defaultTranslation) {
            $promo_code[0]['message'] = $defaultTranslation ?? $promo_code[0]['message'];
        }
        // If no default translation exists, message field keeps its original value from main table
        // Check if promo code usage limit is not exceeded
        // Use <= instead of < to allow usage when counter equals limit (for repeat usage scenarios)
        // When repeat_usage is enabled, same user can use the code multiple times even if promo_used_counter equals no_of_users
        $promo_usage_allowed = false;
        if (intval($promo_code[0]['promo_used_counter']) < intval($promo_code[0]['no_of_users'])) {
            // Counter is below limit, usage is allowed
            $promo_usage_allowed = true;
        } else if (intval($promo_code[0]['promo_used_counter']) == intval($promo_code[0]['no_of_users'])) {
            // Counter equals limit - allow only if repeat usage is enabled and current user has already used it
            // This handles the case: no_of_users=1, no_of_repeat_usage=2, same user using it twice
            if ($promo_code[0]['repeat_usage'] == 1 && intval($promo_code[0]['user_promo_usage_counter']) > 0) {
                $promo_usage_allowed = true;
            }
        }

        if ($promo_usage_allowed) {

            if ($final_total >= intval($promo_code[0]['minimum_order_amount'])) {
                // Check if user hasn't exceeded their repeat usage limit
                // Use < instead of <= because validation happens before order creation
                // Example: no_of_repeat_usage=2 means user can use it 2 times
                // - First use: counter=0, check 0 < 2 is TRUE, order created, counter becomes 1
                // - Second use: counter=1, check 1 < 2 is TRUE, order created, counter becomes 2
                // - Third use attempt: counter=2, check 2 < 2 is FALSE, correctly blocked
                if ($promo_code[0]['repeat_usage'] == 1 && (intval($promo_code[0]['user_promo_usage_counter']) < intval($promo_code[0]['no_of_repeat_usage']))) {
                    if (intval($promo_code[0]['user_promo_usage_counter']) < intval($promo_code[0]['no_of_repeat_usage'])) {
                        $response['error'] = false;
                        $response['message'] = labels(THE_PROMO_CODE_IS_VALID, 'The promo code is valid');
                        if ($promo_code[0]['discount_type'] == 'percentage') {
                            $promo_code_discount = floatval($final_total * $promo_code[0]['discount'] / 100);
                        } else {
                            $promo_code_discount = floatval($final_total - $promo_code[0]['discount']);
                        }

                        if ($promo_code[0]['discount_type'] == 'amount') {
                            if ($promo_code[0]['discount'] > $final_total) {
                                $promo_code_discount = $final_total;
                                $total = floatval($final_total);
                            }
                        }
                        if ($promo_code_discount > $final_total) {
                            if ($promo_code[0]['discount_type'] == 'amount') {
                                $promo_code_discount = $final_total;
                                $total = floatval($final_total);
                            }
                        } else {
                            // For amount type promo codes, max_discount_amount is not set, so skip the check
                            // For percentage type promo codes, check max_discount_amount if it exists
                            $max_discount = !empty($promo_code[0]['max_discount_amount']) ? floatval($promo_code[0]['max_discount_amount']) : null;

                            // If max_discount_amount is not set (for amount type) or discount is within limit, apply discount normally
                            if ($max_discount === null || $promo_code_discount <= $max_discount) {
                                $total = $promo_code[0]['discount_type'] == 'amount' ? ($total = floatval($final_total) - $promo_code_discount) : $promo_code_discount;
                            } else {
                                // Discount exceeds max_discount_amount, apply max discount limit
                                $total = floatval($final_total) - $max_discount;
                                $promo_code_discount = $max_discount;
                            }
                        }
                        $promo_code[0]['final_total'] = strval(floatval($total));
                        $promo_code[0]['final_discount'] = strval(floatval($promo_code_discount));
                        $response['data'] = $promo_code;
                        return $response;
                    } else {
                        $response['error'] = true;
                        $response['message'] = labels(THIS_PROMO_CODE_CANNOT_BE_REDEEMED_AS_IT_EXCEEDS_THE_USAGE_LIMIT, 'This promo code cannot be redeemed as it exceeds the usage limit');
                        $response['data']['final_total'] = strval(floatval($final_total));
                        return $response;
                    }
                } else if ($promo_code[0]['repeat_usage'] == 0 && ($promo_code[0]['user_promo_usage_counter'] <= 0)) {
                    if (intval($promo_code[0]['user_promo_usage_counter']) <= intval($promo_code[0]['no_of_repeat_usage'])) {
                        $response['error'] = false;
                        $response['message'] = labels(THE_PROMO_CODE_IS_VALID, 'The promo code is valid');
                        // if ($promo_code[0]['discount_type'] == 'percentage') {
                        //     $promo_code_discount = floatval($final_total * $promo_code[0]['discount'] / 100);
                        // } else {
                        //     $promo_code_discount = floatval($final_total - $promo_code[0]['discount']);
                        // }
                        // if ($promo_code_discount > $final_total) {
                        //     $promo_code_discount = $final_total;
                        //     $total = floatval($final_total);
                        // } else {
                        //     if ($promo_code_discount <= $promo_code[0]['max_discount_amount']) {
                        //         $total = floatval($final_total) - $promo_code_discount;
                        //     } else {
                        //         $total = floatval($final_total) - $promo_code[0]['max_discount_amount'];
                        //         $promo_code_discount = $promo_code[0]['max_discount_amount'];
                        //     }
                        // }
                        if ($promo_code[0]['discount_type'] == 'percentage') {
                            $promo_code_discount = floatval($final_total * ($promo_code[0]['discount'] / 100));
                        } else {
                            $promo_code_discount = $promo_code[0]['discount'];
                        }

                        if ($promo_code[0]['discount'] > $final_total) {
                            $promo_code_discount = $final_total;
                            $total = floatval($final_total);
                        }
                        if ($promo_code_discount > $final_total) {
                            $promo_code_discount = $final_total;
                            $total = floatval($final_total);
                        } else {
                            // For amount type promo codes, max_discount_amount is not set, so skip the check
                            // For percentage type promo codes, check max_discount_amount if it exists
                            $max_discount = !empty($promo_code[0]['max_discount_amount']) ? floatval($promo_code[0]['max_discount_amount']) : null;

                            // If max_discount_amount is not set (for amount type) or discount is within limit, apply discount normally
                            if ($max_discount === null || $promo_code_discount <= $max_discount) {
                                $total = $final_total - $promo_code_discount;
                            } else {
                                // Discount exceeds max_discount_amount, apply max discount limit
                                $total = floatval($final_total) - $max_discount;
                                $promo_code_discount = $max_discount;
                            }
                        }

                        $promo_code[0]['final_total'] = strval(floatval($total));
                        $promo_code[0]['final_discount'] = strval(floatval($promo_code_discount));
                        $response['data'] = $promo_code;
                        return $response;
                    } else {
                        $response['error'] = true;
                        $response['message'] = labels(THIS_PROMO_CODE_CANNOT_BE_REDEEMED_AS_IT_EXCEEDS_THE_USAGE_LIMIT, 'This promo code cannot be redeemed as it exceeds the usage limit');
                        $response['data']['final_total'] = strval(floatval($final_total));
                        return $response;
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = labels(THE_PROMO_CODE_HAS_ALREADY_BEEN_REDEEMED_CANNOT_BE_REUSED, 'The promo has already been redeemed. cannot be reused');
                    $response['data']['final_total'] = strval(floatval($final_total));
                    return $response;
                }
            } else {
                $response['error'] = true;
                $response['message'] = labels(THIS_PROMO_CODE_IS_APPLICABLE_ONLY_FOR_AMOUNT_GREATER_THAN_OR_EQUAL_TO, 'This promo code is applicable only for amount greater than or equal to') . " " . $promo_code[0]['minimum_order_amount'];
                $response['data']['final_total'] = strval(floatval($final_total));
                return $response;
            }
        } else {
            $response['error'] = true;
            $response['message'] = labels(PROMOCODE_USAGE_EXCEEDED, "promocode usage exceeded");
            $response['data']['final_total'] = strval(floatval($final_total));
            return $response;
        }
    } else {
        $response['error'] = true;
        $response['message'] = labels(THE_PROMO_CODE_IS_NOT_AVAILABLE_OR_EXPIRED, 'The promo code is not available or expired');
        $response['data']['final_total'] = strval(floatval($final_total));
        return $response;
    }
}

/**
 * Build the SQL fragment to compare a computed `distance` column against the
 * configured max serviceable distance. When general_settings.max_serviceable_distance_type
 * is 'global', the single admin-configured value is used for every partner. When it is
 * 'provider_wise', each partner's own partner_details.max_serviceable_distance is used
 * instead (falling back to the global value if the partner hasn't set one).
 */
function get_max_serviceable_distance_condition($settings, $partnerColumn = 'pd.max_serviceable_distance', $fallbackDistance = null)
{
    $globalDistance = (float) ($fallbackDistance ?? ($settings['max_serviceable_distance'] ?? 0));
    $type = $settings['max_serviceable_distance_type'] ?? 'global';
    if ($type === 'provider_wise') {
        return 'COALESCE(' . $partnerColumn . ', ' . $globalDistance . ')';
    }
    return (string) $globalDistance;
}

/**
 * PHP-side counterpart of get_max_serviceable_distance_condition() for code paths that
 * already fetched the partner's own max_serviceable_distance value in an array/row.
 */
function resolve_max_serviceable_distance($settings, $partnerDistance = null)
{
    $globalDistance = (float) ($settings['max_serviceable_distance'] ?? 0);
    $type = $settings['max_serviceable_distance_type'] ?? 'global';
    if ($type === 'provider_wise' && !empty($partnerDistance)) {
        return (float) $partnerDistance;
    }
    return $globalDistance;
}

function get_near_partners($latitude, $longitude, $distance, $is_array = false)
{
    $settings = get_settings('general_settings', true);
    $db = \Config\Database::connect();
    $builder = $db->table('users u');
    $distanceSql = get_provider_distance_sql($latitude, $longitude, 'u.id', 'u.latitude', 'u.longitude');
    $partners = $builder->Select("u.latitude,u.longitude,u.id,pd.max_serviceable_distance,{$distanceSql} as distance")
        ->join('users_groups ug', 'ug.user_id=u.id')
        ->join('partner_details pd', 'pd.partner_id = u.id', 'left')
        ->where('ug.group_id', '3')
        // ->where('ABS((u.latitude)) > 180  or  ABS((u.longitude)) > 90')
        ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $distance))
        ->orderBy('distance')
        ->get()->getResultArray();
    $ids = [];
    foreach ($partners as $key => $parnter) {
        $ids[] = $parnter['id'];
    }
    if ($is_array == false) {
        $ids = implode(',', $ids);
    }
    return $ids;
}

function get_provider_distance_sql($latitude, $longitude, string $providerColumn = 'u.id', string $fallbackLatitudeColumn = 'u.latitude', string $fallbackLongitudeColumn = 'u.longitude'): string
{
    $latitude = (float) $latitude;
    $longitude = (float) $longitude;

    return "CASE WHEN EXISTS (SELECT 1 FROM provider_locations pl_exists WHERE pl_exists.provider_id = {$providerColumn}) THEN (SELECT MIN(ST_DISTANCE_SPHERE(POINT({$longitude}, {$latitude}), POINT(pl.longitude, pl.latitude))/1000) FROM provider_locations pl WHERE pl.provider_id = {$providerColumn} AND pl.is_active = 1) ELSE ST_DISTANCE_SPHERE(POINT({$longitude}, {$latitude}), POINT({$fallbackLongitudeColumn}, {$fallbackLatitudeColumn}))/1000 END";
}

function fetch_cart($from_app = false, int $user_id = 0, string $search = '', $limit = 0, int $offset = 0, string $sort = 'c.id', string $order = 'Desc', $where = [], $additional_data = [], $reorder = null, $order_id = null, $selected_at_store = null, bool $include_visiting_tax = false)
{
    $fileService = service('fileService');
    $db = \Config\Database::connect();
    $builder = $db->table('cart c');
    $sortable_fields = [
        'c.id' => 'c.id',
    ];
    if ($search and $search != '') {
        $multipleWhere = [
            '`s.id`' => $search,
            '`s.title`' => $search,
            '`s.description`' => $search,
            '`s.status`' => $search,
            '`s.tags`' => $search,
            '`s.price`' => $search,
            '`s.discounted_price`' => $search,
            '`s.rating`' => $search,
            '`s.number_of_ratings`' => $search,
            '`s.max_quantity_allowed`' => $search,
        ];
    }
    $total = $builder->select(' COUNT(c.id) as `total` ')->where('c.user_id', $user_id);
    if (isset($multipleWhere) && !empty($multipleWhere)) {
        $builder->orWhere($multipleWhere);
    }
    if (isset($where) && !empty($where)) {
        $builder->where($where);
    }
    $service_count = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
    $total = $service_count[0]['total'];
    if (isset($multipleWhere) && !empty($multipleWhere)) {
        $builder->orLike($multipleWhere);
    }
    if (isset($where) && !empty($where)) {
        $builder->where($where);
    }
    if ($reorder == 'yes' && !empty($order_id)) {
        $builder = $db->table('order_services os');
        $service_record = $builder
            ->select('os.id as cart_id,os.service_id,os.quantity as qty,s.image as service_image,s.*,s.title as service_name,p.username as partner_name,pd.visiting_charges as visiting_charges,cat.name as category_name')
            ->join('services s', 'os.service_id=s.id', 'left')
            ->join('orders o', 'o.id=os.order_id', 'left')
            ->join('users p', 'p.id=s.user_id', 'left')
            ->join('categories cat', 'cat.id=s.category_id', 'left')
            ->join('partner_details pd', 'pd.partner_id=s.user_id', 'left')
            ->where('os.order_id', $order_id)
            ->where('o.user_id', $user_id)->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
    } else {
        $service_record = $builder
            ->select('c.id as cart_id,c.service_id,c.qty,c.is_saved_for_later,s.image as service_image,s.*,s.title as service_name,p.username as partner_name,pd.visiting_charges as visiting_charges,cat.name as category_name')
            ->join('services s', 'c.service_id=s.id', 'left')
            ->join('users p', 'p.id=s.user_id', 'left')
            ->join('categories cat', 'cat.id=s.category_id', 'left')
            ->join('partner_details pd', 'pd.partner_id=s.user_id', 'left')
            ->where('c.user_id', $user_id)->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
    }
    $bulkData = $rows = $tempRow = array();
    $bulkData['total'] = $total;
    // $tax = get_settings('system_tax_settings', true)['tax'];
    foreach ($service_record as $row) {
        $image_url = !empty($row['service_image']) && $fileService->exists('services', $row['service_image'])
            ? $fileService->url($row['service_image'], 'services')
            : 'nothing found';

        if ($from_app) {
            $images = $image_url;
        } else {
            $images = '<a  href="' . $image_url . '" data-lightbox="image-1"><img height="80px" class="rounded-circle" src="' . $image_url . '" alt="image of the services multiple will be here"></a>';
        }
        $status = ($row['status'] == 1) ? 'Enable' : 'Disable';
        $site_allowed = ($row['on_site_allowed'] == 1) ? 'Allowed' : 'Not Allowed';
        $pay_later = ($row['is_pay_later_allowed'] == 1) ? 'Allowed' : 'Not Allowed';
        $rating = $row['rating'] . "/5";
        $tempRow['id'] = $row['cart_id'];
        $tempRow['order_id'] = $order_id ?? "";
        $tempRow['service_id'] = $row['service_id'];
        $tempRow['is_saved_for_later'] = isset($row['is_saved_for_later']) ? $row['is_saved_for_later'] : "";
        $tempRow['qty'] = isset($row['qty']) ? $row['qty'] : 0;
        $tempRow['visiting_charges'] = $row['visiting_charges'];
        $tempRow['price'] = $row['price'];
        $tempRow['discounted_price'] = $row['discounted_price'];
        $taxPercentageData = fetch_details('taxes', ['id' => $row['tax_id']], ['percentage']);
        $taxPercentage = !empty($taxPercentageData) ? $taxPercentageData[0]['percentage'] : 0;
        $tempRow['servic_details']['id'] = $row['id'];
        $tempRow['servic_details']['partner_id'] = $row['user_id'];
        $tempRow['servic_details']['category_id'] = $row['category_id'];
        $tempRow['servic_details']['category_name'] = $row['category_name'];

        // Get translated category data
        $categoryFallbackData = ['name' => $row['category_name'] ?? ''];
        $translatedCategoryData = get_translated_category_data_for_api($row['category_id'], $categoryFallbackData);

        $tempRow['servic_details']['partner_name'] = $row['partner_name'];
        $tempRow['servic_details']['translated_partner_name'] = get_translated_partner_field($row['user_id'], 'username', $row['partner_name']);
        $tempRow['servic_details']['tax_type'] = $row['tax_type'];
        $tempRow['servic_details']['tax_id'] = $row['tax_id'];
        $tempRow['servic_details']['current_tax_percentage'] = $taxPercentage;
        $tempRow['servic_details']['tax'] = $row['tax'];
        // Get service details for translation fallback
        $serviceFallbackData = [
            'title' => $row['title'] ?? '',
            'description' => $row['description'] ?? '',
            'long_description' => $row['long_description'] ?? '',
            'tags' => $row['tags'] ?? '',
            'faqs' => $row['faqs'] ?? ''
        ];

        // Get translated data for this service
        $translatedServiceData = get_translated_service_data_for_api($row['id'], $serviceFallbackData);

        $tempRow['servic_details']['title'] = $row['title'];
        $tempRow['servic_details']['slug'] = $row['slug'];
        $tempRow['servic_details']['description'] = $row['description'];
        $tempRow['servic_details']['tags'] = $row['tags'];
        $tempRow['servic_details']['image_of_the_service'] = $images;

        // Add translated fields
        $tempRow['servic_details']['translated_title'] = $translatedServiceData['translated_title'] ?? $row['title'];
        $tempRow['servic_details']['translated_description'] = $translatedServiceData['translated_description'] ?? $row['description'];
        $tempRow['servic_details']['translated_long_description'] = $translatedServiceData['translated_long_description'] ?? ($row['long_description'] ?? '');
        $tempRow['servic_details']['translated_tags'] = $translatedServiceData['translated_tags'] ?? $row['tags'];
        $tempRow['servic_details']['translated_faqs'] = $translatedServiceData['translated_faqs'] ?? ($row['faqs'] ?? '');

        // print_r($translatedServiceData['translated_faqs']);
        // die;

        if (isset($translatedServiceData['translated_faqs']) && is_array($translatedServiceData['translated_faqs'])) {
            $normalizedFaqs = [];

            foreach ($translatedServiceData['translated_faqs'] as $faq) {
                // Skip any invalid junk
                if (!is_array($faq) || empty($faq)) {
                    continue;
                }

                // Case 1: New format (associative with 'question' and 'answer' keys)
                if (isset($faq['question']) && isset($faq['answer'])) {
                    $question = trim($faq['question']);
                    $answer = trim($faq['answer']);
                }

                // Case 2: Old format (numeric array, usually [0] => question, [1] => answer)
                elseif (isset($faq[0]) && isset($faq[1])) {
                    $question = trim($faq[0]);
                    $answer = trim($faq[1]);
                }

                // Case 3: Mismatched or malformed data — skip it
                else {
                    continue;
                }

                // Double-check that both have content
                if ($question !== '' && $answer !== '') {
                    $normalizedFaqs[] = [
                        'question' => $question,
                        'answer' => $answer,
                    ];
                }
            }

            $tempRow['servic_details']['translated_faqs'] = $normalizedFaqs;
        }

        // Add translated category fields
        $tempRow['servic_details']['translated_category_name'] = $translatedCategoryData['translated_name'] ?? $row['category_name'];
        $tempRow['servic_details']['price'] = $row['price'];
        $tempRow['servic_details']['discounted_price'] = $row['discounted_price'];
        $tempRow['servic_details']['number_of_members_required'] = $row['number_of_members_required'];
        $tempRow['servic_details']['duration'] = $row['duration'];
        $tempRow['servic_details']['tags'] = json_decode((string) $row['tags'], true);
        $tempRow['servic_details']['rating'] = $rating;
        $tempRow['servic_details']['number_of_ratings'] = $row['number_of_ratings'];
        $tempRow['servic_details']['on_site_allowed'] = $site_allowed;
        $tempRow['servic_details']['max_quantity_allowed'] = $row['max_quantity_allowed'];
        $tempRow['servic_details']['is_pay_later_allowed'] = $pay_later;
        $tempRow['servic_details']['status'] = $status;
        $tempRow['servic_details']['created_at'] = $row['created_at'];
        $basePrice = ($row['discounted_price'] > 0)
            ? $row['discounted_price']
            : $row['price'];

        $taxType = $row['tax_type']; // included | excluded

        // ---- DISCOUNTED PRICE ---- //
        $taxValue = calculate_tax_amount($basePrice, $taxPercentage, $taxType);

        if ($taxType === 'included') {
            // price already includes tax
            $priceWithTax = $basePrice;
        } else {
            // tax excluded
            $priceWithTax = $basePrice + $taxValue;
        }

        // ---- ORIGINAL PRICE (strike-through) ---- //
        $originalTax = calculate_tax_amount($row['price'], $taxPercentage, $taxType);

        if ($taxType === 'included') {
            $originalPriceWithTax = $row['price'];
        } else {
            $originalPriceWithTax = $row['price'] + $originalTax;
        }

        // ---- Assign back to SAME KEYS ---- //
        $tempRow['tax_value'] = number_format($taxValue, 2);

        $tempRow['servic_details']['price_with_tax'] = strval(number_format($priceWithTax, 2, '.', ''));

        $tempRow['servic_details']['original_price_with_tax'] = strval(number_format($originalPriceWithTax, 2, '.', ''));
        $rows[] = $tempRow;
    }
    if ($from_app) {
        $db = \Config\Database::connect();
        $cart_builder = $db->table('cart');
        foreach ($service_record as $key => $s) {
            $detail = fetch_details('services', ['id' => $s['service_id']], ['id', 'user_id', 'approved_by_admin', 'at_store', 'at_doorstep'])[0];
            $p_detail = fetch_details('partner_details', ['partner_id' => $s['user_id']], ['id', 'at_store', 'at_doorstep', 'need_approval_for_the_service'])[0];
            if (($detail['at_store'] != $p_detail['at_store']) && ($detail['at_doorstep'] || $detail['at_doorstep'])) {
                unset($service_record[$key]);
                $cart_builder->delete(['service_id' => $detail['id']]);
            }
            $is_already_subscribe = fetch_details('partner_subscriptions', ['partner_id' => $detail['user_id'], 'status' => 'active']);
            if ($p_detail['need_approval_for_the_service'] == 1) {
                if ($detail['approved_by_admin'] != 1 || empty($is_already_subscribe)) {
                    unset($service_record[$key]);
                    $cart_builder->delete(['service_id' => $detail['id']]);
                }
            }
        }
        if (!empty($service_record)) {
            if (($reorder) == 'yes' && !empty($order_id)) {
                $builder = $db->table('order_services os');
                $order_record = $builder
                    ->select('os.id, os.service_id, os.quantity as qty')
                    ->join('orders o', 'o.id=os.order_id', 'left')
                    ->where('o.user_id', $user_id)
                    ->where('os.order_id', $order_id)
                    ->orderBy($sort, $order)
                    ->limit($limit, $offset)
                    ->get()
                    ->getResultArray();
                foreach ($order_record as $row) {
                    $array_ids[] = [
                        'service_id' => $row['service_id'],
                        'qty' => $row['qty'],
                    ];
                }
            } else {
                $array_ids = fetch_details('cart c', ['user_id' => $user_id], 'service_id,qty');
            }
            $s = [];
            $q = [];
            foreach ($array_ids as $ids) {
                array_push($s, $ids['service_id']);
                array_push($q, $ids['qty']);
            }
            $id = implode(',', $s);
            $qty = implode(',', $q);
            $extra_data = [];
            if (!empty($s)) {
                $builder = $db->table('services s');
                if (($reorder) == 'yes' && !empty($order_id)) {
                    $builder = $db->table('order_services os');
                    $extra_data = $builder
                        ->select('SUM(IF(s.discounted_price  > 0 , (s.discounted_price * os.quantity) , (s.price * os.quantity))) as subtotal,
                    SUM(os.quantity) as total_quantity,pd.visiting_charges as visiting_charges,SUM(s.duration * os.quantity) as total_duration,pd.at_store,pd.at_doorstep,pd.advance_booking_days as advance_booking_days,pd.company_name as company_name')
                        ->join('services s', 'os.service_id=s.id', 'left')
                        ->join('partner_details pd', 'pd.partner_id=s.user_id')
                        ->where('os.order_id', $order_id)
                        ->whereIn('s.id', $s)->get()->getResultArray();
                } else {
                    $builder = $db->table('services s');
                    $extra_data = $builder
                        ->select('SUM(IF(s.discounted_price  > 0 , (s.discounted_price * c.qty) , (s.price * c.qty))) as subtotal,
                   SUM(c.qty) as total_quantity,pd.visiting_charges as visiting_charges,SUM(s.duration * c.qty) as total_duration,pd.at_store,pd.at_doorstep,pd.advance_booking_days as advance_booking_days,pd.company_name as company_name')
                        ->join('cart c', 'c.service_id = s.id')
                        ->join('partner_details pd', 'pd.partner_id=s.user_id')
                        ->where('c.user_id', $user_id)
                        ->whereIn('s.id', $s)->get()->getResultArray();
                }
            }

            $sub_total = 0;   // always BASE price total (without tax)
            $tax_total = 0;   // total tax amount

            foreach ($service_record as $s1) {

                // 1. Unit price (as stored)
                $unitPrice = ($s1['discounted_price'] > 0)
                    ? $s1['discounted_price']
                    : $s1['price'];

                $qty = (int) $s1['qty'];

                // 2. Tax percentage (from taxes table)
                $taxPercentageData = fetch_details('taxes', ['id' => $s1['tax_id']], ['percentage']);
                $taxPercentage = !empty($taxPercentageData)
                    ? (float) $taxPercentageData[0]['percentage']
                    : 0;

                // 3. Tax type comes from SERVICE
                $taxType = $s1['tax_type'] ?? 'excluded';
                // expected: 'included' or 'excluded'

                // 4. Calculate unit tax correctly
                $unitTax = calculate_tax_amount($unitPrice, $taxPercentage, $taxType);

                if ($taxType === 'included') {
                    // price already contains tax → extract base
                    $unitBasePrice = $unitPrice - $unitTax;
                } else {
                    // price does NOT contain tax
                    $unitBasePrice = $unitPrice;
                }

                // 5. Accumulate totals
                $sub_total += ($unitBasePrice * $qty); // BASE only
                $tax_total += ($unitTax * $qty);       // TAX only
            }

            $data['total'] = (empty($total)) ? (string) count($rows) : $total;
            $data['advance_booking_days'] = isset($extra_data[0]['advance_booking_days']) ? $extra_data[0]['advance_booking_days'] : "";
            $visiting_charges = isset($extra_data[0]['visiting_charges'])
                ? (float) $extra_data[0]['visiting_charges']
                : 0;
            $service_at_store = $selected_at_store !== null
                ? $selected_at_store
                : '0';
            if ((string) $service_at_store === '1') {
                $visiting_charges = 0;
            }
            $data['visiting_charges'] = $visiting_charges;
            $data['company_name'] = isset($extra_data[0]['company_name']) ? $extra_data[0]['company_name'] : "";
            $data['at_store'] = isset($extra_data[0]['at_store']) ? $extra_data[0]['at_store'] : "0";
            $data['at_doorstep'] = isset($extra_data[0]['at_doorstep']) ? $extra_data[0]['at_doorstep'] : "0";
            $data['service_ids'] = $id;
            $data['qtys'] = isset($qty) ? $qty : 0;
            $data['total_quantity'] = isset($extra_data[0]['total_quantity']) ? $extra_data[0]['total_quantity'] : 0;
            $data['total_duration'] = isset($extra_data[0]['total_duration']) ? $extra_data[0]['total_duration'] : 0;
            // Format prices without comma separators but keep decimal point
            // Using empty string for thousands separator to remove commas
            // subtotal = price * quantity (total of items before tax)
            $data['sub_total'] = number_format($sub_total, 2, '.', '');
            if ($include_visiting_tax && $visiting_charges > 0 && !empty($service_record)) {
                $visitingTaxPercentageData = fetch_details('taxes', ['id' => $service_record[0]['tax_id']], ['percentage']);
                $visitingTaxPercentage = !empty($visitingTaxPercentageData)
                    ? (float) $visitingTaxPercentageData[0]['percentage']
                    : 0;
                $tax_total += calculate_tax_amount($visiting_charges, $visitingTaxPercentage, 'excluded');
            }
            $data['tax_value'] = number_format($tax_total, 2, '.', '');
            // sub_total_without_tax = subtotal - tax amount
            $data['sub_total_without_tax'] = number_format(($sub_total), 2, '.', '');

            $data['overall_amount'] = number_format(
                $include_visiting_tax
                    ? $sub_total + $data['visiting_charges'] + $tax_total
                    : $sub_total + $tax_total,
                2,
                '.',
                ''
            );

            $data['data'] = $rows;
            $providers = [];
            if (!empty($s)) {
                $provider_data = $db->table('services s');
                $providers = $provider_data
                    ->select('u.username as provider_names, u.id as provider_id')
                    ->join('users u', 'u.id = s.user_id')
                    ->whereIn('s.id', $s)->get()->getResultArray();
            }
            $pds = [];
            $pid = [];
            foreach ($providers as $provider) {
                array_push($pds, $provider['provider_names']);
                array_push($pid, $provider['provider_id']);
            }
            $unique_name = array_unique($pds);
            $unique_id = array_unique($pid);
            $names = implode(',', $unique_name);
            $ids = implode(',', $unique_id);
            $data['provider_names'] = $names;
            $data['provider_id'] = $ids;
            $pay_later_array = [];
            foreach ($service_record as $service_row) {
                array_push($pay_later_array, $service_row['is_pay_later_allowed']);
            }
            $first_provider_id = isset($providers[0]['provider_id']) ? $providers[0]['provider_id'] : 0;
            $active_partner_subscription = !empty($first_provider_id) ? fetch_details('partner_subscriptions', ['partner_id' => $first_provider_id, 'status' => 'active']) : [];
            $provider_details = !empty($first_provider_id) ? fetch_details('users', ['id' => $first_provider_id]) : [];
            if (!empty($active_partner_subscription)) {
                if ($active_partner_subscription[0]['is_commision'] == "yes") {
                    $commission_threshold = $active_partner_subscription[0]['commission_threshold'];
                } else {
                    $commission_threshold = 0;
                }
            } else {
                $commission_threshold = 0;
            }
            $check_payment_gateway = get_settings('payment_gateways_settings', true);
            $data['is_online_payment_allowed'] = $check_payment_gateway['payment_gateway_setting'];
            if ($check_payment_gateway['cod_setting'] == 1 && $check_payment_gateway['payment_gateway_setting'] == 0) {
                $data['is_pay_later_allowed'] = 1;
            } else if ($check_payment_gateway['cod_setting'] == 0) {
                $data['is_pay_later_allowed'] = 0;
            } else {
                $payable_commission_of_provider = isset($provider_details[0]['payable_commision']) ? $provider_details[0]['payable_commision'] : 0;
                if (($payable_commission_of_provider >= $commission_threshold) && $commission_threshold != 0) {
                    $data['is_pay_later_allowed'] = 0;
                } else {
                    if (in_array(0, $pay_later_array)) {
                        $data['is_pay_later_allowed'] = 0;
                    } else {
                        $data['is_pay_later_allowed'] = 1;
                    }
                }
            }
            return $data;
        } else {
            $data = [];
            return $data;
        }
    } else {
        $bulkData['rows'] = $rows;
        return json_encode($bulkData);
    }
}

function calculate_tax_amount(float $price, float $taxPercentage, string $taxType = 'excluded'): float
{
    if ($taxPercentage <= 0) {
        return 0;
    }

    if ($taxType === 'included') {
        // Extract tax from tax-inclusive price
        $basePrice = $price / (1 + ($taxPercentage / 100));
        return round($price - $basePrice, 2);
    }

    // Tax excluded → calculate tax on top
    return round(($price * $taxPercentage) / 100, 2);
}

/**
 * Format a monetary amount for DB storage using system decimal_point.
 * When decimal places is 0, uses floor() so e.g. 6.63 → 6 (not 7).
 * When 1 or 2, uses that many decimal places. Returns string without thousand separators.
 *
 * @param float|string $amount Amount to format
 * @param int|null $decimals Override; if null, uses general_settings decimal_point (0, 1, or 2)
 * @return string Formatted amount for storage
 */
function format_amount($amount, $decimals = null)
{
    $amount = (float) $amount;

    if ($decimals === null) {
        $settings = get_settings('general_settings', true);
        $decimals = isset($settings['decimal_point']) ? (int) $settings['decimal_point'] : 2;
    }

    $decimals = max(0, min(2, (int) $decimals));

    if ($decimals === 0) {
        return (string) round($amount);
    }

    return number_format(round($amount, $decimals), $decimals, '.', '');
}

function get_taxable_amount($service_id)
{
    $service_details = fetch_details('services', ['id' => $service_id])[0];
    if ($service_details['tax_id'] != 0) {
        $tax_details = fetch_details('taxes', ['id' => $service_details['tax_id']])[0];
        $tax_percentage = strval(str_replace(',', '', number_format(strval($tax_details['percentage']), 2)));
    } else {
        $tax_percentage = 0;
    }
    $tax_percentage_float = (float) $tax_percentage;
    $taxable_amount = 0;
    if ($service_details['tax_type'] == "excluded") {
        if ($service_details['discounted_price'] == 0) {
            $tax_amount = (!empty($tax_percentage)) ? ($service_details['price'] * $tax_percentage) / 100 : 0;
            $taxable_amount = strval(str_replace(',', '', number_format(strval($service_details['price'] + ($tax_amount)), 2)));
        } else {
            $tax_amount = (!empty($tax_percentage)) ? ($service_details['discounted_price'] * $tax_percentage) / 100 : 0;
            $taxable_amount = strval(str_replace(',', '', number_format(strval($service_details['discounted_price'] + ($tax_amount)), 2)));
        }
    } else {
        // Tax included: price already contains tax; extract tax using calculate_tax_amount
        if ($service_details['discounted_price'] == 0) {
            $tax_amount = (!empty($tax_percentage)) ? calculate_tax_amount((float) $service_details['price'], $tax_percentage_float, 'included') : 0;
            $taxable_amount = strval(str_replace(',', '', number_format(strval($service_details['price']), 2)));
        } else {
            $tax_amount = (!empty($tax_percentage)) ? calculate_tax_amount((float) $service_details['discounted_price'], $tax_percentage_float, 'included') : 0;
            $taxable_amount = strval(str_replace(',', '', number_format(strval($service_details['discounted_price']), 2)));
        }
    }
    // Include tax_type so callers (e.g. place_order) can store it in order_services.
    $result = [
        'title' => $service_details['title'],
        'tax_percentage' => $tax_percentage,
        'tax_amount' => $tax_amount,
        'price' => $service_details['price'],
        'discounted_price' => $service_details['discounted_price'],
        'taxable_amount' => $taxable_amount ?? 0,
        'tax_type' => $service_details['tax_type'] ?? null,
    ];
    return $result;
}

function get_partner_ids(string $type = '', string $column_name = 'id', array $ids = [], $is_array = false, array $fields_name = ['*'])
{
    $db = \Config\Database::connect();
    if ($type == 'service') {
        $builder = $db->table('services s');
        $partners = $builder->select('s.user_id as id')
            ->whereIn('s.' . $column_name, $ids)
            ->get()->getResultArray();
    } else if ($type == 'category') {
        $builder = $db->table('services s');
        $partners = $builder->select('s.user_id as id')
            ->whereIN('s.' . $column_name, $ids)
            ->get()->getResultArray();
    } else {
        $builder = $db->table('users u');
        $partners = $builder->select($fields_name)
            ->join('users_groups ug', 'ug.user_id=u.id')
            ->where('ug.group_id', '3')
            ->whereIn($column_name, $ids)
            ->get()->getResultArray();
    }
    $ids = [];
    foreach ($partners as $key => $parnter) {
        $ids[] = $parnter['id'];
    }
    $ids = array_unique($ids);
    if ($is_array == false) {
        $ids = implode(',', $ids);
    }
    return $ids;
}

function check_partner_availibility(int $partner_id)
{
    $days = [
        'Mon' => 'monday',
        'Tue' => 'tuesday',
        'Wed' => 'wednsday',
        'Thu' => 'thursday',
        'Fri' => 'friday',
        'Sat' => 'staturday',
        'Sun' => 'sunday',
    ];
    // Provider's first shift on today's weekday (provider_shifts replaces partner_timings).
    $partner_timing = fetch_details('provider_shifts', ['partner_id' => $partner_id, 'day' => $days[date('D')], 'shift_number' => 1]);
    if (empty($partner_timing)) {
        return false;
    }
    $partner_timing = $partner_timing[0];
    $time = new DateTime($partner_timing['opening_time']);
    $opening_time = $time->format('H:i');
    $time = new DateTime($partner_timing['closing_time']);
    $closing_time = $time->format('H:i');
    $current_time = date('H:i');
    if (($opening_time <= $current_time) or ($current_time >= $closing_time)) {
        return $partner_timing;
    } else {
        return false;
    }
}

function is_bookmarked($user_id, $partner_id)
{
    $db = \Config\Database::connect();
    $builder = $db->table('bookmarks');
    $data = $builder
        ->select('COUNT(id) as total')
        ->where('user_id', $user_id)
        ->where('partner_id', $partner_id)->get()->getResultArray();
    return $data;
}

function delete_bookmark($user_id, $partner_id)
{
    $db = \Config\Database::connect();
    $builder = $db->table('bookmarks');
    $data = $builder->where(['user_id' => $user_id, 'partner_id' => $partner_id])
        ->delete();
    if ($data) {
        return true;
    } else {
        return false;
    }
}

/**
 * 
 * queue_notification_service()
 *   1. FCM with fewer than FCM_DIRECT_THRESHOLD recipients is sent directly
 *      (no queue round-trip) so push notifications feel instantaneous.
 *   2. FCM and Email/SMS are dispatched as separate jobs so a slow email send
 *      can never delay a push notification waiting in the queue.
 *   3. Smaller default chunk sizes (FCM=25, Email/SMS=20) create more parallel
 *      jobs so multiple workers process large blasts faster.
 *   4. The FCM job pre-resolves FCM tokens from the DB before calling
 *      NotificationService, so FcmProvider skips its own token DB query.
 */
function queue_notification_service(string $eventType, array $recipients = [], array $context = [], array $options = [], string $priority = 'default'): mixed
{
    // ------------------------------------------------------------------
    // Step 1: Resolve the final list of user IDs from all input sources.
    // ------------------------------------------------------------------

    $explicitUserIds = $options['user_ids'] ?? [];
    $userGroupFilters = $options['user_groups'] ?? [];
    $sendToAll = $options['send_to_all'] ?? false;
    $resolvedUserIds = [];

    // Fetch users when a group filter or send_to_all flag is provided.
    if (!empty($userGroupFilters) || $sendToAll) {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('users u');

            if (!empty($userGroupFilters)) {
                // Only pick users that belong to the requested groups.
                $builder->join('users_groups ug', 'ug.user_id = u.id')
                    ->whereIn('ug.group_id', $userGroupFilters)
                    ->groupBy('u.id');
            }

            $users = $builder->select('u.id')->get()->getResultArray();

            $resolvedUserIds = array_column($users, 'id');
            log_message('info', '[QNS_V2] Resolved ' . count($resolvedUserIds) . ' users via user_groups/send_to_all for event: ' . $eventType);
        } catch (\Throwable $dbError) {
            log_message('error', '[QNS_V2] Failed to resolve user IDs: ' . $dbError->getMessage());
            return false;
        }
    }

    // Merge any explicitly supplied user_ids.
    if (!empty($explicitUserIds)) {
        $resolvedUserIds = array_merge($resolvedUserIds, $explicitUserIds);
    }

    // De-duplicate and cast to int for clean downstream handling.
    if (!empty($resolvedUserIds)) {
        $resolvedUserIds = array_values(array_unique(array_map('intval', $resolvedUserIds)));
    } elseif (!empty($userGroupFilters) || $sendToAll) {
        // We expected users but found none — bail gracefully.
        log_message('warning', '[QNS_V2] No users resolved for event: ' . $eventType);
        return false;
    }

    // Bake the resolved IDs into options so downstream services skip re-fetching.
    if (!empty($resolvedUserIds)) {
        $options['user_ids'] = $resolvedUserIds;
        unset($options['user_groups'], $options['send_to_all']);
    }

    // ------------------------------------------------------------------
    // Queue Progress Tray: generate a batch ID for job tracking.
    // The actual tracking row is created AFTER dispatch (Step 5) only
    // when jobs are queued, so the tray never flashes for direct sends.
    // ------------------------------------------------------------------
    $batchId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
    $dispatchTitle = $context['title'] ?? $options['title'] ?? null;
    if (empty($dispatchTitle)) {
        try {
            $tplRow = \Config\Database::connect()
                ->table('notification_templates')
                ->select('title')
                ->where('event_key', $eventType)
                ->get()
                ->getRowArray();
            $dispatchTitle = $tplRow['title'] ?? $eventType;
        } catch (\Throwable $e) {
            $dispatchTitle = $eventType;
        }
    }
    $totalRecipients = !empty($resolvedUserIds) ? count($resolvedUserIds) : 1;
    $options['batch_id'] = $batchId;

    // ------------------------------------------------------------------
    // Step 2: Determine channels and per-channel configuration.
    // ------------------------------------------------------------------

    // Default to all three channels if the caller did not specify.
    $channels = $options['channels'] ?? ['fcm', 'email', 'sms'];

    // Fixed tuning values — not caller-configurable.
    // FCM_DIRECT_THRESHOLD: send FCM directly (no queue) when recipient count is below this.
    // FCM_CHUNK_SIZE:        users per FCM queue job. 500 users → 20 parallel jobs with 25/chunk.
    // EMAIL_SMS_CHUNK_SIZE:  users per Email/SMS queue job. Smaller because SMTP is slower.
    $fcmDirectThreshold = defined(ALLOW_MODIFICATION) && (ALLOW_MODIFICATION == 0) ? 0 : 100;
    $fcmChunkSize = 25;
    $emailSmsChunkSize = 20;

    // Validate the requested priority against the configured list.
    $allowedPriorities = \Config\Queue::$queuePriorities['notifications'] ?? ['default'];
    $priority = in_array($priority, $allowedPriorities, true) ? $priority : 'default';

    // ------------------------------------------------------------------
    // Compute the effective recipient count for the FCM direct/queue decision.
    //
    // There are three ways recipients can be specified:
    //
    // 1. $options['user_ids'] or $options['user_groups'] / send_to_all
    //    → already resolved into $resolvedUserIds above (Step 1).
    //    → $recipientCount = count($resolvedUserIds) is correct.
    //    → Works for ALL group sizes: 5 users from a group → count = 5 → direct FCM. ✓
    //
    // 2. $recipients['user_id'] (single-user pattern used in most event notifications,
    //    e.g. "booking placed", "booking status updated" in OrdersApiController).
    //    → These callers never set $options['user_ids'] or user_groups.
    //    → $resolvedUserIds stays empty → count = 0.
    //    → Without the fix below, 0 > 0 is false → FCM would always be queued for
    //       single-user notifications, defeating the whole optimisation.
    //
    // 3. Mixed: user_groups + recipients['user_id'] at the same time.
    //    → Group members are in $resolvedUserIds. The single recipients['user_id']
    //       is an additional user not in the list, so add 1 to the effective count.
    // ------------------------------------------------------------------

    $recipientCount = count($resolvedUserIds);

    if (!empty($recipients['user_id'])) {
        $singleId = (int) $recipients['user_id'];
        if ($recipientCount === 0) {
            // Pattern 2: only recipients['user_id'] was provided — treat as 1 recipient.
            // We do NOT add it to $resolvedUserIds; it flows through $recipients unchanged.
            $recipientCount = 1;
        } elseif (!in_array($singleId, $resolvedUserIds, true)) {
            // Pattern 3: user_groups was also provided and this user is not in the group.
            // Count them as an additional recipient for the threshold decision.
            $recipientCount++;
        }
        // If $singleId is already in $resolvedUserIds (pattern 1 with overlap), no change.
    }

    // ------------------------------------------------------------------
    // Step 3: Dispatch FCM channel.
    // ------------------------------------------------------------------

    $fcmJobIds = [];

    if (in_array('fcm', $channels, true)) {
        // Build FCM-only options — never let the FCM path accidentally trigger email/SMS.
        $fcmOptions = $options;
        $fcmOptions['channels'] = ['fcm'];

        if ($recipientCount > 0 && $recipientCount < $fcmDirectThreshold) {
            // --- DIRECT PATH: small list → send now, skip the queue entirely. ---
            // FcmProvider uses curl_multi + HTTP/2 so this is sub-second.
            log_message('info', '[QNS_V2] FCM direct send for ' . $recipientCount . ' recipients, event: ' . $eventType);
            try {
                $notificationService = new \App\Services\NotificationService();
                $notificationService->send($eventType, $recipients, $context, $fcmOptions);
            } catch (\Throwable $e) {
                log_message('error', '[QNS_V2] FCM direct send failed: ' . $e->getMessage());
                // Non-fatal — we continue so Email/SMS jobs are still queued.
            }
        } else {
            // --- QUEUED PATH: large list → chunk and push to queue. ---
            // The SendFcmNotificationJob will pre-resolve FCM tokens so FcmProvider
            // skips its own DB query (it short-circuits when fcm_tokens_by_language
            // is already set in recipients).
            try {
                $queue = service('queue');
                $targetUserIds = !empty($resolvedUserIds) ? $resolvedUserIds : [];

                if (!empty($targetUserIds)) {
                    $userChunks = array_chunk($targetUserIds, $fcmChunkSize);

                    foreach ($userChunks as $chunk) {
                        $chunkOptions = $fcmOptions;
                        $chunkOptions['user_ids'] = $chunk;

                        $jobData = [
                            'eventType' => $eventType,
                            'recipients' => $recipients,
                            'context' => $context,
                            'options' => $chunkOptions,
                        ];

                        $jobId = $queue->setPriority($priority)->push('notifications', 'sendFcmNotification', $jobData);
                        $fcmJobIds[] = $jobId;
                    }

                    log_message('info', '[QNS_V2] Queued ' . count($fcmJobIds) . ' FCM jobs (' . $fcmChunkSize . ' users/chunk) for event: ' . $eventType);
                } elseif ($recipientCount === 0) {
                    // No resolved IDs but FCM was requested — push a single job so
                    // the handler can still process single-user recipients[] payloads.
                    $jobData = [
                        'eventType' => $eventType,
                        'recipients' => $recipients,
                        'context' => $context,
                        'options' => $fcmOptions,
                    ];
                    $fcmJobIds[] = $queue->setPriority($priority)->push('notifications', 'sendFcmNotification', $jobData);
                }
            } catch (\Throwable $e) {
                log_message('error', '[QNS_V2] FCM queue push failed, falling back to direct send: ' . $e->getMessage());
                try {
                    $notificationService = new \App\Services\NotificationService();
                    $notificationService->send($eventType, $recipients, $context, $fcmOptions);
                } catch (\Throwable $fallback) {
                    log_message('error', '[QNS_V2] FCM fallback direct send also failed: ' . $fallback->getMessage());
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Step 4: Dispatch Email and SMS channels — always queued.
    // ------------------------------------------------------------------

    $emailSmsJobIds = [];
    $emailSmsChannels = array_values(array_intersect($channels, ['email', 'sms']));

    if (!empty($emailSmsChannels)) {
        // Build Email/SMS-only options so the job never accidentally triggers FCM.
        $emailSmsOptions = $options;
        $emailSmsOptions['channels'] = $emailSmsChannels;

        try {
            $queue = service('queue');
            $targetUserIds = !empty($resolvedUserIds) ? $resolvedUserIds : [];

            if (!empty($targetUserIds)) {
                $userChunks = array_chunk($targetUserIds, $emailSmsChunkSize);

                foreach ($userChunks as $chunk) {
                    $chunkOptions = $emailSmsOptions;
                    $chunkOptions['user_ids'] = $chunk;

                    $jobData = [
                        'eventType' => $eventType,
                        'recipients' => $recipients,
                        'context' => $context,
                        'options' => $chunkOptions,
                    ];

                    $jobId = $queue->setPriority($priority)->push('notifications', 'sendEmailSmsNotification', $jobData);
                    $emailSmsJobIds[] = $jobId;
                }

                log_message('info', '[QNS_V2] Queued ' . count($emailSmsJobIds) . ' Email/SMS jobs (' . $emailSmsChunkSize . ' users/chunk) for event: ' . $eventType);
            } else {
                // Single-recipient path — push one job without chunking.
                // Populate user_ids from recipients['user_id'] so the job has
                // something to iterate over (job loops over user_ids, not recipients).
                $singleUserOptions = $emailSmsOptions;
                if (!empty($recipients['user_id'])) {
                    $singleUserOptions['user_ids'] = [(int) $recipients['user_id']];
                }
                $jobData = [
                    'eventType' => $eventType,
                    'recipients' => $recipients,
                    'context' => $context,
                    'options' => $singleUserOptions,
                ];
                $emailSmsJobIds[] = $queue->setPriority($priority)->push('notifications', 'sendEmailSmsNotification', $jobData);
            }
        } catch (\Throwable $e) {
            log_message('error', '[QNS_V2] Email/SMS queue push failed, falling back to direct send: ' . $e->getMessage());
            try {
                $notificationService = new \App\Services\NotificationService();
                $notificationService->send($eventType, $recipients, $context, $emailSmsOptions);
            } catch (\Throwable $fallback) {
                log_message('error', '[QNS_V2] Email/SMS fallback direct send also failed: ' . $fallback->getMessage());
            }
        }
    }

    // ------------------------------------------------------------------
    // Step 5: Return job IDs so callers can track what was dispatched.
    // ------------------------------------------------------------------

    // Merge FCM and Email/SMS job IDs into a single return value.
    $allJobIds = array_merge($fcmJobIds, $emailSmsJobIds);

    // Track which channels were actually queued (not sent directly).
    $queuedChannels = [];
    if (!empty($fcmJobIds)) {
        $queuedChannels[] = 'fcm';
    }
    if (!empty($emailSmsJobIds)) {
        $queuedChannels = array_merge($queuedChannels, $emailSmsChannels);
    }

    // ------------------------------------------------------------------
    // Queue Progress Tray: create tracking row only when jobs are queued.
    // Direct sends skip this entirely so the tray never appears for them.
    // ------------------------------------------------------------------
    if (!empty($allJobIds) && !empty($options['batch_id'])) {
        // Dedicated, non-shared connection so a tracking failure cannot
        // poison the caller's transaction (CI4 marks transStatus=false on any
        // failed query against the shared connection, causing a silent
        // rollback of unrelated business writes).
        $trackingDb = \Config\Database::connect(null, false);
        $trackingDb->transException(false);

        try {
            $row = [
                'batch_id' => $options['batch_id'],
                'title' => mb_substr($dispatchTitle, 0, 255),
                'event_type' => $eventType,
                'total_recipients' => $totalRecipients,
                'total_jobs' => count($allJobIds),
                'status' => 'processing',
            ];
            $row['channels'] = implode(',', $queuedChannels);

            $trackingDb->table('notification_dispatches')->insert($row);
        } catch (\Throwable $e) {
            log_message('error', '[QNS_V2] Failed to create dispatch tracking row: ' . $e->getMessage());
        } finally {
            $trackingDb->close();
        }
    }

    if (empty($allJobIds)) {
        // Nothing was queued (e.g. direct FCM send only) — return true to signal success.
        return true;
    }

    // Return the single ID when only one job was pushed, array for multiple.
    return count($allJobIds) === 1 ? $allJobIds[0] : $allJobIds;
}

function get_permission($user_id)
{
    $db = \Config\Database::connect();
    $builder = $db->table('user_permissions');
    $builder->select('role,permissions');
    $builder->where('user_id', $user_id);
    $user_perms = $builder->get()->getRowArray();

    $edemand = new \Config\Edemand;
    $modules = $edemand->permissions;

    $permissions = [
        'create' => [],
        'read' => [],
        'update' => [],
        'delete' => []
    ];

    foreach ($modules as $module => $perms) {
        foreach (['create', 'read', 'update', 'delete'] as $type) {
            if (in_array($type, $perms)) {
                $permissions[$type][$module] = 0; // Default to 0
            }
        }
    }

    if (!empty($user_perms) && !empty($user_perms['permissions'])) {
        $stored_permissions = json_decode($user_perms['permissions'], true);
        if (is_array($stored_permissions)) {
            foreach ($stored_permissions as $type => $type_perms) {
                if (is_array($type_perms)) {
                    foreach ($type_perms as $module => $val) {
                        // Normalize 'yes'/'no' or boolean to 1/0
                        if ($val == "yes" || $val === "1" || $val === 1 || $val === true) {
                            $permissions[$type][$module] = 1;
                        } else {
                            $permissions[$type][$module] = 0;
                        }
                    }
                }
            }
        }
    } else {
        // If no permissions record, we might want to return some system defaults
        // For now, defaulting all to 0 is the safest approach for new system users.
        // However, if the user is a super admin (role 1), we return all 1s.
        if (isset($user_perms['role']) && $user_perms['role'] == "1") {
            foreach ($permissions as $type => &$type_perms) {
                foreach ($type_perms as $module => &$val) {
                    $val = 1;
                }
            }
        }
    }

    return $permissions;
}

function is_permitted($user_id, $type_of_permission, $permit)
{
    $db = \Config\Database::connect();
    $builder = $db->table('user_permissions');
    $builder->select('role,permissions');
    $builder->where('user_id', $user_id);
    $permissions = $builder->get()->getResultArray();

    if (empty($permissions)) {
        return false;
    }

    if ($permissions[0]['role'] == "1") {
        return true;
    } else {
        if (empty($permissions[0]['permissions'])) {
            return false;
        }
        $permissions = json_decode($permissions[0]['permissions'], true);
        if (isset($permissions[$type_of_permission][$permit])) {
            $val = $permissions[$type_of_permission][$permit];
            if ($val == "yes" || $val == "1" || $val == 1) {
                return true;
            }
        }
        return false;
    }
}

function get_service($service_id)
{
    if ($service_id != null) {
        return false;
    }
    $service = fetch_details('services', ['id' => $service_id]);
    if ($service != null && !empty($service)) {
        return response_helper('Found data', false, $service);
    } else {
        return response_helper('No Data Found', false, []);
    }
}

function has_ordered($user_id, $service_id, $custom_job_id = null)
{
    $db = \config\Database::connect();
    if ($custom_job_id) {
        // For custom jobs, validate that the custom job exists for the given ID.
        // If the custom job does not exist, we should not allow rating.
        // This keeps the behavior consistent with the normal service flow.
        $custom_job = fetch_details('custom_job_requests', ['id' => $custom_job_id]);
        if (empty($custom_job)) {
            $response['error'] = true;
            $response['message'] = "No Custom Service Found";
            return $response;
        }

        // NOTE:
        // Earlier this check was using only `os.custom_job_request_id` from `order_services`.
        // However, custom job requests are associated at the order level and may not always
        // have `os.custom_job_request_id` populated in `order_services` in every flow.
        //
        // This caused the system to think the customer had not ordered the custom job
        // (returning "Can not rate service without Placing orders"), so the rating
        // creation in `RatingsApiController::add_rating` never executed for custom jobs.
        //
        // To reliably detect a completed custom job order, we:
        // - Ensure the order belongs to the current user
        // - Ensure the order is completed
        // - Match by the custom_job_request_id stored on either the `orders` table
        //   or the `order_services` table (supporting both schemas).
        $builder = $db
            ->table('orders o')
            ->select('o.id,o.user_id,o.custom_job_request_id,os.service_id,os.custom_job_request_id')
            ->join('order_services os', 'os.order_id = o.id', 'left')
            ->where('o.user_id', $user_id)
            ->where('o.status', 'completed')
            ->groupStart()
            ->where('o.custom_job_request_id', $custom_job_id)
            ->orWhere('os.custom_job_request_id', $custom_job_id)
            ->groupEnd()
            ->get()
            ->getResultArray();
    } else {
        $services = fetch_details('services', ['id' => $service_id]);
        if (empty($services)) {
            $response['error'] = true;
            $response['message'] = "No Service Found";
            return $response;
        }
        $builder = $db
            ->table('orders o')
            ->select(' o.id,o.user_id,os.service_id')
            ->join('order_services os', 'os.order_id = o.id')
            ->where('user_id', $user_id)
            ->where('o.status', 'completed')
            ->where('os.service_id', $service_id)->get()->getResultArray();
    }
    if (!empty($builder)) {
        $response['error'] = false;
        $response['message'] = "Has ordered";
        return $response;
    } else {
        $response['error'] = true;
        $response['message'] = "Can not rate service  without Placing orders";
        return $response;
    }
}

function has_rated($user_id, $rate_id)
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('services_ratings sr')
        ->select('sr.*')
        ->where('sr.id', $rate_id)
        ->where('user_id', $user_id);
    $old_data = $builder->get()->getResultArray();
    if (!empty($old_data)) {
        $response['error'] = false;
        $response['message'] = "Found Rating";
        $response['data'] = $old_data;
        return $response;
    } else {
        $response['error'] = true;
        $response['message'] = "No Rating Found";
        return $response;
    }
}

function get_ratings($user_id)
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('services_ratings sr')
        ->select("
            COUNT(sr.rating) as total_ratings,
            SUM(CASE WHEN sr.rating = ceil(5) THEN 1 ELSE 0 END) as rating_5,
            SUM(CASE WHEN sr.rating = ceil(4) THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN sr.rating = ceil(3) THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN sr.rating = ceil(2) THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN sr.rating = ceil(1) THEN 1 ELSE 0 END) as rating_1
        ")
        ->join('services s', 'sr.service_id = s.id', 'left')
        ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
        ->join('partner_bids pb', 'pb.custom_job_request_id = cj.id', 'left')
        ->join('users u', 'u.id = sr.user_id')
        ->where("(s.user_id = {$user_id}) OR (pb.partner_id = {$user_id} AND sr.custom_job_request_id IS NOT NULL)")
        ->get()->getResultArray();
    return $builder;
}

function update_ratings($service_id, $rate)
{
    $db = \config\Database::connect();
    // Get service data
    $service_data = fetch_details('services', ['id' => $service_id]);
    if (empty($service_data)) {
        return ['error' => true];
    }
    $user_id = $service_data[0]['user_id'];
    // Get all ratings for this user's services and custom job requests in one query
    $ratings = $db->table('services_ratings sr')
        ->select('COUNT(sr.rating) as number_of_ratings, SUM(sr.rating) as total_rating')
        ->join('services s', 's.id = sr.service_id', 'left')
        ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
        ->join('partner_bids pb', 'pb.custom_job_request_id = cj.id', 'left')
        ->where("(s.user_id = {$user_id}) OR (pb.partner_id = {$user_id} AND sr.custom_job_request_id IS NOT NULL)")
        ->get()
        ->getRowArray();

    // Prepare update data
    if (!empty($ratings) && $ratings['number_of_ratings'] > 0) {
        $avg_rating = $ratings['total_rating'] / $ratings['number_of_ratings'];
        $num_ratings = $ratings['number_of_ratings'];
    } else {
        $avg_rating = $rate;
        $num_ratings = 1;
    }
    // Update partner details
    $updated = update_details(
        ['ratings' => $avg_rating, 'number_of_ratings' => $num_ratings],
        ['partner_id' => $user_id],
        'partner_details',
        false
    );
    // Update service
    $updated = update_details(
        ['rating' => $avg_rating, 'number_of_ratings' => $num_ratings],
        ['id' => $service_id],
        'services',
        false
    );
    return ['error' => ($updated === "") ? true : false];
}

function rating_images($rating_id, $from_app = false)
{
    $rating_data = fetch_details('services_ratings', ['id' => $rating_id]);
    $disk = fetch_current_file_manager();
    $d = ($from_app == false) ? 'for web' : 'for app';
    if (!empty($rating_data)) {
        $rating_images = json_decode($rating_data[0]['images'], true);
        $images_restored = [];
        foreach ($rating_images as $ri) {
            if ($from_app == false) {
                if ($disk == "local_server") {
                    $image_url = base_url($ri);
                } else if ($disk == "aws_s3") {
                    $image_url = fetch_cloud_front_url('ratings', $ri);
                }
                $image = '<a  href="' . base_url($ri) . '" data-lightbox="image-1"><img height="80px" class="rounded" src="' . $image_url . '" alt=""></a>';
                array_push($images_restored, $image);
            } else {
                if ($disk == "local_server") {
                    array_push($images_restored, base_url($ri));
                } else if ($disk == "aws_s3") {
                    array_push($images_restored, fetch_cloud_front_url('ratings', $ri));
                }
            }
        }
    }
    return $images_restored;
}

function is_favorite($user_id, $partner_id)
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('bookmarks b')
        ->select('b.*')
        ->where('b.user_id', $user_id)
        ->where('b.partner_id', $partner_id);
    $data = $builder->get()->getResultArray();
    if (!empty($data)) {
        return true;
    } else {
        return false;
    }
}

function favorite_list($user_id)
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('bookmarks b')
        ->select('b.partner_id')
        ->where('b.user_id', $user_id);
    $data = $builder->get()->getResultArray();
    $partner_ids = [];
    if (!empty($data)) {
        foreach ($data as $dt) {
            array_push($partner_ids, $dt['partner_id']);
        }
        return $partner_ids;
    } else {
        return false;
    }
}

function in_cart_qty($service_id, $user_id)
{
    $data = fetch_details('cart', ['user_id' => $user_id, 'service_id' => $service_id], ['qty']);
    $quantity = (!empty($data)) ? $data[0]['qty'] : '0';
    return $quantity;
}

function resize_image($image, $new_image, $thumbnail, $width = 300, $height = 300)
{
    if (file_exists(FCPATH . $image)) {
        if (!is_dir(base_url($thumbnail))) {
            mkdir(base_url($thumbnail), 0775, true);
        }
        \Config\Services::image('gd')
            ->withFile(FCPATH . $image)
            ->resize($width, $height, true, 'auto')
            ->save(FCPATH . $new_image);
        $response['error'] = false;
        $response['message'] = "File resizes successfully";
        return $response;
    } else {
        $response['error'] = true;
        $response['message'] = "File does not exist";
        return $response;
    }
}

function provider_total_earning_chart($partner_id = '')
{
    $amount = fetch_details('orders', ['partner_id' => $partner_id, 'is_commission_settled' => '0'], ['sum(final_total) as total']);
    $db = \config\Database::connect();
    // Fixed: Group by YEAR and MONTH instead of created_at to ensure correct monthly aggregation
    // Use date_of_service for earnings as it represents when the service was actually provided
    $builder = $db
        ->table('orders')
        ->select('SUM(final_total) AS total, DATE_FORMAT(date_of_service,"%b") AS month_name')
        ->where('partner_id', $partner_id)
        ->where('status', 'completed')
        ->groupBy('YEAR(date_of_service), MONTH(date_of_service)')
        ->orderBy('YEAR(date_of_service), MONTH(date_of_service)');
    $data = $builder->get()->getResultArray();
    $admin_commission_percentage = get_admin_commision($partner_id);
    $admin_commission_amount = intval($admin_commission_percentage) / 100;
    $month_wise_sales = ['total_sale' => [], 'month_name' => []];
    foreach ($data as $row) {
        $tempRow = $row['total'];
        $commission = intval($tempRow) * $admin_commission_amount;
        $total_after_commission = $tempRow - $commission;
        $month_wise_sales['total_sale'][] = $total_after_commission;
        $month_wise_sales['month_name'][] = $row['month_name'];
    }
    return $month_wise_sales;
}

function provider_already_withdraw_chart($partner_id = '')
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('payment_request')
        ->select('sum(amount) as total')
        ->select('SUM(amount) AS total_withdraw,DATE_FORMAT(created_at,"%b") AS month_name')
        ->where('status', '1')
        ->where('user_id', $partner_id);
    $data = $builder->groupBy('created_at')->get()->getResultArray();
    $tempRow = array();
    $row1 = array();
    foreach ($data as $key => $row) {
        $tempRow = $row['total'];
        $row1[] = $tempRow;
    }
    $month_wise_sales['total_withdraw'] = array_map('intval', array_column($data, 'total_withdraw'));
    $month_wise_sales['month_name'] = array_column($data, 'month_name');
    $total_withdraw = $month_wise_sales;
    return $total_withdraw;
}

function provider_pending_withdraw_chart($partner_id = '')
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('payment_request')
        ->select('sum(amount) as total')
        ->select('SUM(amount) AS pending_withdraw,DATE_FORMAT(created_at,"%b") AS month_name')
        ->where('status', '0')
        ->where('user_id', $partner_id);
    $data = $builder->groupBy('created_at')->get()->getResultArray();
    $month_wise_sales['pending_withdraw'] = array_map('floatval', array_column($data, 'pending_withdraw'));
    $month_wise_sales['month_name'] = array_column($data, 'month_name');
    $pending_withdraw = $month_wise_sales;
    return $pending_withdraw;
    // return $row1;
}

function provider_withdraw_chart($partner_id = '')
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('payment_request')
        ->select('sum(amount) as total')
        ->select('SUM(amount) AS withdraw_request,DATE_FORMAT(created_at,"%b") AS month_name')
        ->where('user_id', $partner_id);
    $data = $builder->groupBy('created_at')->get()->getResultArray();
    $month_wise_sales['withdraw_request'] = array_map('intval', array_column($data, 'withdraw_request'));
    $month_wise_sales['month_name'] = array_column($data, 'month_name');
    $withdraw_request = $month_wise_sales;
    return $withdraw_request;
}

function income_revenue($partner_id = '')
{
    $db = \config\Database::connect();
    $builder = $db
        ->table('payment_request')
        ->select('sum(amount) as total')
        ->select('SUM(amount) AS income_revenue,DATE_FORMAT(date_of_service,"%b") AS month_name')
        ->where('status', '0');
    $data = $builder->groupBy('MONTH(created_at), YEAR(created_at)')->get()->getResultArray();
    $month_wise_sales['income_revenue'] = array_map('intval', array_column($data, 'income_revenue'));
    $month_wise_sales['month_name'] = array_column($data, 'month_name');
    $income_revenue = $month_wise_sales;
    return $income_revenue;
}

function admin_income_revenue($partner_id = '')
{
    return _income_revenue_by_year('SUM(o.final_total * COALESCE(pd.admin_commission, 0) / 100)');
}

function provider_income_revenue($partner_id = '')
{
    return _income_revenue_by_year('SUM(o.final_total - (o.final_total * COALESCE(pd.admin_commission, 0) / 100))');
}

function total_income_revenue($partner_id = '')
{
    return _income_revenue_by_year('SUM(o.final_total)');
}

/**
 * Aggregate completed-order earnings by year+month and return per-year buckets
 * zero-filled across all 12 months.
 *
 * Return shape:
 * [
 *   'available_years' => [2024, 2025, ...],
 *   'years' => [
 *     2024 => ['income_revenue' => [int x 12], 'month_name' => [translated x 12]],
 *     ...
 *   ]
 * ]
 */
function _income_revenue_by_year($earningExpr)
{
    $db = \config\Database::connect();

    $builder = $db
        ->table('orders o')
        ->select('YEAR(o.date_of_service) AS yr, MONTH(o.date_of_service) AS mo, ' . $earningExpr . ' AS earning')
        ->where('o.status', 'completed')
        ->join('partner_details pd', 'pd.partner_id = o.partner_id', 'left')
        ->groupBy('YEAR(o.date_of_service), MONTH(o.date_of_service)')
        ->orderBy('YEAR(o.date_of_service), MONTH(o.date_of_service)');

    $queryResult = $builder->get();
    $rows = ($queryResult === false) ? [] : $queryResult->getResultArray();

    $monthKeys = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
    $translatedMonths = array_map(fn($k) => labels($k, ucfirst($k)), $monthKeys);

    $byYear = [];
    foreach ($rows as $r) {
        $year = (int) $r['yr'];
        $monthIdx = ((int) $r['mo']) - 1;
        if ($year <= 0 || $monthIdx < 0 || $monthIdx > 11) {
            continue;
        }
        if (!isset($byYear[$year])) {
            $byYear[$year] = [
                'income_revenue' => array_fill(0, 12, 0),
                'month_name' => $translatedMonths,
            ];
        }
        $byYear[$year]['income_revenue'][$monthIdx] = (int) $r['earning'];
    }

    ksort($byYear);
    return [
        'available_years' => array_keys($byYear),
        'years' => $byYear,
    ];
}

function fetch_top_trending_services($category_id = 'null')
{
    $db = \config\Database::connect();
    $builder = $db->table('order_services');
    $builder->select('service_id, COUNT(*) as count');
    $builder->where('status', 'completed');
    $builder->groupBy('service_id');
    $builder->orderBy('count', 'desc');
    $builder->limit(10);
    $trending_services = $builder->get()->getResultArray();
    $top_trending_services = array();
    $total_service_orders = array();

    // Get current language and default language for translations
    $currentLang = get_current_language();
    $defaultLang = get_default_language();

    // Initialize translation model for service translations
    $translatedServiceModel = new \App\Models\TranslatedServiceDetails_model();

    foreach ($trending_services as $key => $trending_service) {
        if ($category_id != "null") {
            $where = ['id' => $trending_service['service_id'], 'category_id' => $category_id];
        } else {
            $where = ['id' => $trending_service['service_id']];
        }
        $services = fetch_details("services", $where, ['id', 'title', 'image', 'price', 'discounted_price', 'category_id'], '10');
        foreach ($services as $key => $row) {
            // Get service title with language fallback: current language → default language → base table
            // Priority: current language translation → default language translation → base table title
            $serviceTitle = $row['title']; // Default fallback to base table title

            if (!empty($row['id'])) {
                // Get all translations for this service
                $allTranslations = $translatedServiceModel->getAllTranslationsForService($row['id']);

                if (!empty($allTranslations)) {
                    // Organize translations by language code
                    $translationsByLang = [];
                    foreach ($allTranslations as $translation) {
                        $translationsByLang[$translation['language_code']] = $translation;
                    }

                    // Try current language first
                    if (!empty($translationsByLang[$currentLang]['title'])) {
                        $serviceTitle = $translationsByLang[$currentLang]['title'];
                    } elseif (!empty($translationsByLang[$defaultLang]['title'])) {
                        // Fallback to default language
                        $serviceTitle = $translationsByLang[$defaultLang]['title'];
                    }
                    // If no translation found, keep base table title (already set above)
                }
                // If no translations exist, keep base table title (already set above)
            }

            // Update service title with translated version
            $services[$key]['title'] = $serviceTitle;

            $total_service_orders = $db->table('order_services o')->select('count(o.id) as `total`')->where('status', 'completed')->where('o.service_id', $row['id'])->get()->getResultArray();
            $services[$key]['order_data'] = $total_service_orders[0]['total'];
        }
        $top_trending_services[] = (!empty($services[0])) ? $services[0] : "";
    }
    return (array_filter($top_trending_services));
}

function order_encrypt($user_id, $amount, $order_id)
{
    $simple_string = $user_id . "-" . $amount . "-" . $order_id;
    // Store the cipher method
    $ciphering = "AES-128-CTR";
    // Use OpenSSl Encryption method
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    // Non-NULL Initialization Vector for encryption (load from environment to avoid hardcoding secrets)
    $encryption_iv = env('DECRYPTION_IV');
    // Store the encryption key (load from environment to avoid hardcoding secrets)
    $encryption_key = env('decryption_key');
    // Use openssl_encrypt() function to encrypt the data
    $encryption = openssl_encrypt(
        $simple_string,
        $ciphering,
        $encryption_key,
        $options,
        $encryption_iv
    );
    return $encryption;
}

function order_decrypt($order_id)
{
    $ciphering = "AES-128-CTR";
    $options = 0;
    // Use openssl_encrypt() function to encrypt the data
    $encryption = $order_id;
    // Non-NULL Initialization Vector for decryption
    $decryption_iv = env('DECRYPTION_IV');
    // Store the decryption key
    $decryption_key = env('decryption_key');
    // Use openssl_decrypt() function to decrypt the data
    $decryption = openssl_decrypt(
        $encryption,
        $ciphering,
        $decryption_key,
        $options,
        $decryption_iv
    );
    $order_id = (explode("-", $decryption));
    return $order_id;
}

function is_file_uploaded($result = null)
{
    if ($result == true) {
        return true;
    } else {
        return false;
    }
}

function get_service_ratings($service_id)
{
    $db = \config\Database::connect();

    // Get the partner_id (user_id) for this service first
    // Use query builder with parameter binding to prevent SQL injection
    $serviceData = $db->table('services')
        ->select('user_id')
        ->where('id', $service_id)
        ->get()
        ->getRowArray();
    $partnerId = $serviceData['user_id'] ?? null;

    if (!$partnerId) {
        // Return default values if service not found
        return [['total_ratings' => 0, 'total_rating' => null, 'average_rating' => null, 'rating_5' => 0, 'rating_4' => 0, 'rating_3' => 0, 'rating_2' => 0, 'rating_1' => 0]];
    }

    // Use the SAME logic as Service_ratings_model to ensure consistency
    // This query calculates rating statistics for ALL ratings of the partner who owns this service
    // Use parameter binding with ? placeholders to prevent SQL injection
    $query = "
        SELECT 
            COUNT(sr.rating) AS total_ratings,
            SUM(sr.rating) AS total_rating,
            (SUM(sr.rating) / COUNT(sr.rating)) AS average_rating,
            SUM(CASE WHEN sr.rating = 5 THEN 1 ELSE 0 END) AS rating_5,
            SUM(CASE WHEN sr.rating = 4 THEN 1 ELSE 0 END) AS rating_4,
            SUM(CASE WHEN sr.rating = 3 THEN 1 ELSE 0 END) AS rating_3,
            SUM(CASE WHEN sr.rating = 2 THEN 1 ELSE 0 END) AS rating_2,
            SUM(CASE WHEN sr.rating = 1 THEN 1 ELSE 0 END) AS rating_1
        FROM services_ratings sr
        LEFT JOIN services s ON sr.service_id = s.id
        WHERE (s.user_id = ? OR (sr.custom_job_request_id IS NOT NULL AND EXISTS (SELECT 1 FROM partner_bids pbid WHERE pbid.custom_job_request_id = sr.custom_job_request_id AND pbid.partner_id = ?)))
    ";

    // Execute query with parameter binding to prevent SQL injection
    // Both placeholders are bound to $partnerId for security
    $rating_data = $db->query($query, [$partnerId, $partnerId])->getResultArray();


    return $rating_data;
}

function calculate_subscription_price($subscription_id)
{
    $subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
    if (empty($subscription)) {
        return [];
    }

    $sub = $subscription[0];

    // Get tax percentage (default 0)
    $taxData = fetch_details('taxes', ['id' => $sub['tax_id']], ['percentage']);
    $taxPercentage = !empty($taxData) ? floatval($taxData[0]['percentage']) : 0.0;

    // Determine base price (discounted or regular)
    $basePrice = (isset($sub['discount_price']) && floatval($sub['discount_price']) > 0)
        ? floatval($sub['discount_price'])
        : floatval($sub['price']);

    // Determine if tax is excluded or included
    $taxExcluded = isset($sub['tax_type']) && $sub['tax_type'] === 'excluded';

    // Calculate tax value
    $taxValue = $taxExcluded ? ($basePrice * $taxPercentage / 100) : 0.0;

    // Calculate prices
    $priceWithTax = $taxExcluded ? ($basePrice + $taxValue) : $basePrice;
    $originalPriceWithTax = $taxExcluded
        ? (floatval($sub['price']) + (floatval($sub['price']) * $taxPercentage / 100))
        : floatval($sub['price']);

    // Round and format for safety
    $sub['tax_percentage'] = $taxPercentage;
    $sub['tax_value'] = number_format($taxValue, 2, '.', '');
    $sub['price_with_tax'] = number_format($priceWithTax, 2, '.', '');
    $sub['original_price_with_tax'] = number_format($originalPriceWithTax, 2, '.', '');

    return [$sub];
}

function calculate_partner_subscription_price($partner_id, $subscription_id, $id)
{
    $partner_subscriptions = fetch_details('partner_subscriptions', [
        'partner_id' => $partner_id,
        'subscription_id' => $subscription_id,
        'id' => $id
    ]);

    // log_message('debug', 'partner id is: ' . $partner_id);
    // log_message('debug', 'subscription id is: ' . $subscription_id);
    // log_message('debug', 'id is: ' . $id);

    // log_message('debug', 'partner_subscription is: ' . json_encode($partner_subscriptions));
    $sub = &$partner_subscriptions[0]; // keep [0] dependency

    // Get tax percentage from partner_subscriptions table first
    // If not available or 0, fetch from taxes table using tax_id
    // This ensures tax percentage is always available when tax is configured
    if (empty($sub['tax_percentage']) || floatval($sub['tax_percentage']) == 0) {
        // Fetch tax percentage from taxes table if not stored in partner_subscriptions
        if (!empty($sub['tax_id'])) {
            $taxData = fetch_details('taxes', ['id' => $sub['tax_id']], ['percentage']);
            $sub['tax_percentage'] = !empty($taxData) ? floatval($taxData[0]['percentage']) : 0.0;
        } else {
            $sub['tax_percentage'] = 0;
        }
    } else {
        $sub['tax_percentage'] = floatval($sub['tax_percentage']);
    }

    $price = (!empty($sub['discount_price']) && $sub['discount_price'] != "0") ? $sub['discount_price'] : $sub['price'];

    if (!empty($sub['tax_type']) && $sub['tax_type'] === "excluded") {
        $sub['tax_value'] = number_format(($price * $sub['tax_percentage'] / 100), 2, '.', '');
        $sub['price_with_tax'] = strval($price + ($price * $sub['tax_percentage'] / 100));
        $sub['original_price_with_tax'] = strval($sub['price'] + ($sub['price'] * $sub['tax_percentage'] / 100));
    } else {
        $sub['tax_value'] = 0;
        $sub['price_with_tax'] = strval($price);
        $sub['original_price_with_tax'] = strval($sub['price']);
    }

    return $partner_subscriptions;
}

function add_subscription($subscription_id, $partner_id, $insert_id = null)
{
    $settings = get_settings('general_settings', true);
    date_default_timezone_set($settings['system_timezone']); // Added user timezone
    $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);
    if ($subscription_details[0]['price'] == "0") {
        $price = calculate_subscription_price($subscription_details[0]['id']);
        ;
        $purchaseDate = date('Y-m-d');
        $subscriptionDuration = $subscription_details[0]['duration'];
        if ($subscriptionDuration == "unlimited") {
            $subscriptionDuration = 0;
        }
        $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
        $partner_subscriptions = [
            'partner_id' => $partner_id,
            'subscription_id' => $subscription_id,
            'is_payment' => "1",
            'status' => "active",
            'purchase_date' => date('Y-m-d'),
            'expiry_date' => $expiryDate,
            'name' => $subscription_details[0]['name'],
            'description' => $subscription_details[0]['description'],
            'duration' => $subscription_details[0]['duration'],
            'price' => $subscription_details[0]['price'],
            'discount_price' => $subscription_details[0]['discount_price'],
            'publish' => $subscription_details[0]['publish'],
            'order_type' => $subscription_details[0]['order_type'],
            'max_order_limit' => $subscription_details[0]['max_order_limit'],
            'service_type' => $subscription_details[0]['service_type'],
            'max_service_limit' => $subscription_details[0]['max_service_limit'],
            'tax_type' => $subscription_details[0]['tax_type'],
            'tax_id' => $subscription_details[0]['tax_id'],
            'is_commision' => $subscription_details[0]['is_commision'],
            'commission_threshold' => $subscription_details[0]['commission_threshold'],
            'commission_percentage' => $subscription_details[0]['commission_percentage'],
            'transaction_id' => '0',
            'tax_percentage' => $price[0]['tax_percentage'],
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
        ];
        $data = insert_details($partner_subscriptions, 'partner_subscriptions');
        $inserted_subscription = fetch_details('partner_subscriptions', ['id' => $data['id']]);
        if ($inserted_subscription[0]['is_commision'] == "yes") {
            $commission = $inserted_subscription[0]['commission_percentage'];
        } else {
            $commission = 0;
        }
        update_details(['admin_commission' => $commission], ['partner_id' => $partner_id], 'partner_details');

        // Send notification to admin when free subscription is activated
        try {
            // Get provider name with translation support
            $provider_name = get_translated_partner_field($partner_id, 'company_name');
            if (empty($provider_name)) {
                $partner_data = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                $provider_name = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
            }

            // Get subscription name
            $subscription_name = $subscription_details[0]['name'] ?? 'Subscription';

            // Get currency from settings
            $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

            // Format dates for display
            $purchase_date_formatted = date('d-m-Y', strtotime($purchaseDate));
            $expiry_date_formatted = date('d-m-Y', strtotime($expiryDate));

            // Prepare context data for notification templates
            $context = [
                'provider_id' => $partner_id,
                'provider_name' => $provider_name,
                'subscription_id' => $subscription_id,
                'subscription_name' => $subscription_name,
                'purchase_date' => $purchase_date_formatted,
                'expiry_date' => $expiry_date_formatted,
                'duration' => $subscriptionDuration,
                'amount' => '0.00', // Free subscription
                'currency' => $currency,
                'transaction_id' => '0' // No transaction for free subscription
            ];

            // Queue notification to admin users (group_id = 1)
            queue_notification_service(
                eventType: 'subscription_purchased',
                recipients: [],
                context: $context,
                options: [
                    'channels' => ['fcm', 'email', 'sms'],
                    'user_groups' => [1], // Admin user group
                    'platforms' => ['admin_panel']
                ]
            );
            log_message('info', '[SUBSCRIPTION_PURCHASED] Notification queued for admin - Provider: ' . $provider_name . ', Subscription: ' . $subscription_name . ' (Free)');
        } catch (\Throwable $notificationError) {
            // Log error but don't fail the subscription activation
            log_message('error', '[SUBSCRIPTION_PURCHASED] Notification error: ' . $notificationError->getMessage());
        }

        return true;
    } else {
        if ($subscription_details[0]['is_commision'] == "yes") {
            $commission = $subscription_details[0]['commission_percentage'];
        } else {
            $commission = 0;
        }
        update_details(['admin_commission' => $commission], ['partner_id' => $partner_id], 'partner_details');
        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        $subscriptionDuration = $details_for_subscription[0]['duration'];
        // Calculate the expiry date based on the current date and subscription duration
        $purchaseDate = date('Y-m-d'); // Get the current date
        if ($subscriptionDuration == "unlimited") {
            $subscriptionDuration = 0;
        }
        $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days')); // Add the duration to the purchase date
        $taxPercentageData = fetch_details('taxes', ['id' => $details_for_subscription[0]['tax_id']], ['percentage']);
        if (!empty($taxPercentageData)) {
            $taxPercentage = $taxPercentageData[0]['percentage'];
        } else {
            $taxPercentage = 0;
        }
        $partner_subscriptions = [
            'partner_id' => $partner_id,
            'subscription_id' => $subscription_id,
            'is_payment' => "0",
            'status' => "pending",
            'purchase_date' => $purchaseDate,
            'expiry_date' => $expiryDate,
            'name' => $details_for_subscription[0]['name'],
            'description' => $details_for_subscription[0]['description'],
            'duration' => $details_for_subscription[0]['duration'],
            'price' => $details_for_subscription[0]['price'],
            'discount_price' => $details_for_subscription[0]['discount_price'],
            'publish' => $details_for_subscription[0]['publish'],
            'order_type' => $details_for_subscription[0]['order_type'],
            'max_order_limit' => $details_for_subscription[0]['max_order_limit'],
            'service_type' => $details_for_subscription[0]['service_type'],
            'max_service_limit' => $details_for_subscription[0]['max_service_limit'],
            'tax_type' => $details_for_subscription[0]['tax_type'],
            'tax_id' => $details_for_subscription[0]['tax_id'],
            'is_commision' => $details_for_subscription[0]['is_commision'],
            'commission_threshold' => $details_for_subscription[0]['commission_threshold'],
            'commission_percentage' => $details_for_subscription[0]['commission_percentage'],
            'transaction_id' => $insert_id,
            'tax_percentage' => $taxPercentage,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
        ];
        insert_details($partner_subscriptions, 'partner_subscriptions');
        return true;
    }
}

if (!function_exists('format_date')) {
    function format_date($dateString, $format = 'Y-m-d H:i:s')
    {
        $date = date_create($dateString);
        return date_format($date, $format);
    }
}

function uploadFile($request, $fieldName, $uploadPath, &$updatedData, $data)
{
    $file = $request->getFile($fieldName);
    if ($file->isValid()) {
        $newName = $file->getRandomName();
        $file->move($uploadPath, $newName);
        $updatedData[$fieldName] = $newName;
    } else {
        $updatedData[$fieldName] = isset($data[$fieldName]) ? $data[$fieldName] : "";
    }
}

function verify_transaction($order_id)
{
    $transaction = fetch_details('transactions', ['order_id' => $order_id]);
    if (!empty($transaction)) {
        if ($transaction[0]['type'] == "razorpay") {
            $razorpay = new Razorpay;
            $credentials = $razorpay->get_credentials();
            $secret = $credentials['secret'];
            $api = new Api($credentials['key'], $secret);
            $payment = $api->payment->fetch($transaction[0]['txn_id']);
            $status = $payment->status;
            if ($status != "captured") {
                update_details(['payment_status' => '1'], ['id' => $order_id], 'orders');
                notify_handymen_payment_status_changed($order_id, 'online_payment_success', ['booking_id' => (string) $order_id, 'order_id' => (string) $order_id, 'transaction_id' => (string) $transaction[0]['txn_id']]);
                $response['error'] = false;
                $response['message'] = 'Verified Successfully';
            } else if ($status != "captured") {
                update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');
                $response['error'] = true;
                $response['message'] = 'Booking is cancelled due to pending payment .';
            }
        } elseif ($transaction[0]['type'] == "stripe") {
            $settings = get_settings('payment_gateways_settings', true);
            $secret_key = isset($settings['stripe_secret_key']) ? $settings['stripe_secret_key'] : "";
            $http = service('curlrequest');
            $http->setHeader('Authorization', 'Bearer ' . $secret_key);
            $http->setHeader('Content-Type', 'application/x-www-form-urlencoded');
            $response = $http->get("https://api.stripe.com/v1/payment_intents/{$transaction[0]['txn_id']}");
            $responseData = json_decode($response->getBody(), true);
            $statusOfTransaction = $responseData['status'];
            if ($statusOfTransaction == "succeeded") {
                update_details(['payment_status' => '1'], ['id' => $order_id], 'orders');
                notify_handymen_payment_status_changed($order_id, 'online_payment_success', ['booking_id' => (string) $order_id, 'order_id' => (string) $order_id, 'transaction_id' => (string) $transaction[0]['txn_id']]);
                $response['error'] = false;
                $response['message'] = 'Verified Successfully';
            } else if ($statusOfTransaction != "succeeded") {
                update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');
                $response['error'] = true;
                $response['message'] = 'Booking is cancelled due to pending payment .';
            }
        } else if ($transaction[0]['type'] = "paystack") {
            $paystack = new Paystack();
            $payment = $paystack->verify_transation($transaction[0]['reference']);
            $message = json_decode($payment, true);
            if ($message['status'] == "1" || $message['status'] == "success") {
                update_details(['payment_status' => '1'], ['id' => $order_id], 'orders');
                notify_handymen_payment_status_changed($order_id, 'online_payment_success', ['booking_id' => (string) $order_id, 'order_id' => (string) $order_id, 'transaction_id' => (string) $transaction[0]['reference']]);
                $response['error'] = false;
                $response['message'] = 'Verified Successfully';
            } else if ($message['status'] != "1" || $message['status'] != "success") {
                update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');
                $response['error'] = true;
                $response['message'] = 'Booking is cancelled due to pending payment .';
            }
        }
        return $response;
    }
}

function add_settlement_cashcollection_history($message, $type, $date, $time, $amount, $provider_id = null, $order_id = null, $payment_request_id = null, $commission_percentage = null, $total_amount = null, $commision_amount = null)
{
    $settlement_cashcollection_history = [
        'provider_id' => $provider_id,
        'order_id' => $order_id,
        'payment_request_id' => $payment_request_id,
        'commission_percentage' => $commission_percentage,
        'message' => $message,
        'type' => $type,
        'date' => $date,
        'time' => $time,
        'amount' => $amount,
        'total_amount' => $total_amount,
        'commission_amount' => $commision_amount,
    ];
    insert_details($settlement_cashcollection_history, 'settlement_cashcollection_history');
}

function partner_settlement_and_cash_collection_history_status($status, $panel_type)
{
    $value = '';
    if ($panel_type == "admin") {
        if ($status == "cash_collection_by_provider") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('debit', 'Debit') . "
            </div>";
        } else if ($status == "cash_collection_by_admin") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('credit', 'Credit') . "
            </div>";
        } else if ($status == "received_by_admin") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('credit', 'Credit') . "
            </div>";
        } else if ($status == "settled_by_settlement") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('debit', 'Debit') . "
        </div>";
        } else if ($status == "settled_by_payment_request") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('debit', 'Debit') . "
        </div>";
        }
    } else if ($panel_type == "provider") {
        if ($status == "cash_collection_by_provider") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('credit', 'Credit') . "
            </div>";
        } else if ($status == "cash_collection_by_admin") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('debit', 'Debit') . "
            </div>";
        } else if ($status == "received_by_admin") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('debit', 'Debit') . "
        </div>";
        } else if ($status == "settled_by_settlement") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('credit', 'Credit') . "
            </div>";
        } else if ($status == "settled_by_payment_request") {
            $value = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('credit', 'Credit') . "
            </div>";
        }
    }
    return $value;
}

function partner_settlement_and_cash_collection_history_type($type)
{
    if ($type == "cash_collection_by_provider") {
        $value = labels("cash_collection_by_provider", "Cash Collection By Provider");
    } else if ($type == "cash_collection_by_admin") {
        $value = labels("cash_collection_by_admin", "Cash Collection By Admin");
    } else if ($type == "received_by_admin") {
        $value = labels("received_by_admin", "Received By Admin");
    } else if ($type == "settled_by_settlement") {
        $value = labels("settled_by_settlement", "Settled By settlement");
    } else if ($type == "settled_by_payment_request") {
        $value = labels("settled_by_payment_request", "Settled By Payment Request");
    }
    return $value;
}

function fetch_chat_ids($table, $type, $where = [], $fields = [], $limit = "", $offset = '0', $sort = 'id', $order = 'DESC', $or_like = [], )
{
    $db = \Config\Database::connect();
    $builder = $db->table($table);
    if (!empty($fields)) {
        $builder->select($fields);
    }
    if (!empty($where)) {
        $builder->where($where);
    }
    if (!empty($or_like)) {
        $builder->groupStart();
        foreach ($or_like as $field => $values) {
            $builder->whereIn($field, $values);
        }
        $builder->groupEnd();
    }
    $builder->orderBy($sort, $order);
    if (!empty($limit)) {
        $builder->limit($limit, $offset);
    }
    $query = $builder->get();
    if ($type == "customer") {
        $ids = [];
        foreach ($query->getResultArray() as $row) {
            $ids[] = $row['customer_id'];
        }
    } else if ($type == "provider") {
        $ids = [];
        foreach ($query->getResultArray() as $row) {
            $ids[] = $row['provider_id'];
        }
    }
    return $ids;
}

function add_enquiry_for_chat($user_type, $enquiry_user_id, $for_booking = false, $booking_id = null)
{
    if ($user_type == "provider") {
        $enquiry_field = 'provider_id';
    } else if ($user_type == "customer") {
        $enquiry_field = 'customer_id';
    }
    if ($for_booking && $user_type == "customer") {
        $is_already_exist_query = fetch_details('enquiries', [$enquiry_field => $enquiry_user_id, 'booking_id' => $booking_id]);
    } else {
        $is_already_exist_query = fetch_details('enquiries', [$enquiry_field => $enquiry_user_id, 'booking_id' => $booking_id]);
    }
    if (empty($is_already_exist_query)) {
        $user = fetch_details('users', ['id' => $enquiry_user_id]);

        $user = !empty($user) ? $user[0] : [];
        $data['title'] = ($user['username'] ?? 'user_' . $enquiry_user_id) . '_query';
        $data['status'] = 1;
        if ($user_type == "provider") {
            $data['userType'] = 1;
            $data['provider_id'] = $enquiry_user_id;
        } else if ($user_type == "customer") {
            $data['userType'] = 2;
            $data['customer_id'] = $enquiry_user_id;
        }
        if ($for_booking && $user_type == "customer") {
            $data['booking_id'] = $booking_id;
        }
        $data['date'] = \CodeIgniter\I18n\Time::now()->toDateTimeString();
        $store = insert_details($data, 'enquiries');
        $e_id = $store['id'];
    } else {
        $e_id = $is_already_exist_query[0]['id'];
    }
    return $e_id;
}

function insert_chat_message_for_chat($sender_id, $receiver_id, $message, $e_id, $sender_type, $receiver_type, $created_at, $upload_attachment = false, ?array $file = null, ?int $booking_id = null)
{
    $data = [
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'message' => $message,
        'e_id' => $e_id,
        'sender_type' => $sender_type,
        'receiver_type' => $receiver_type,
        'created_at' => $created_at,
        'booking_id' => $booking_id,
        'is_read' => 0,
        'read_at' => null,
        /**
         * Booking chats are shared between the provider and their assigned lead
         * handyman (both can chat with the same customer). For customer-authored
         * messages, `is_read` above ALREADY means "has the provider read this"
         * (receiver_type=1 targets the provider natively) — no separate flag
         * needed there. The gap is handyman-authored messages (sender_type=3):
         * their native receiver is the customer, so the provider isn't tracked
         * anywhere unless we add this. Only ever 0 for handyman-authored booking
         * messages; everything else stays at the default "1" (not applicable).
         */
        'is_read_by_provider' => ((int) $sender_type === 3 && $booking_id !== null) ? 0 : 1,
    ];

    $path = './public/uploads/chat_attachment/';
    $uploaded_files = [];
    if ($upload_attachment && !empty($file)) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        foreach ($file['tmp_name'] as $key => $tmp_name) {
            $file_type = $file['type'][$key];
            $file_size = $file['size'][$key];
            $file_name = $file['name'][$key];
            $uploadedFile = new UploadedFile(
                $file['tmp_name'][$key],
                $file['name'][$key],
                $file['type'][$key],
                $file['size'][$key],
                $file['error'][$key]
            );
            $result = upload_file($uploadedFile, 'public/uploads/chat_attachment', "Error creating chat attachements", 'chat_attachment');
            if ($result['disk'] == "local_server") {
                $file_name = $path . $result['file_name'];
            } else if ($result['disk'] == "aws_s3") {
                $file_name = $result['file_name'];
            }
            $uploaded_files[] = ['file' => $file_name, 'file_type' => $file_type, 'file_size' => $file_size, 'file_name' => $result['file_name']];
        }
    }

    $disk = fetch_current_file_manager();
    $data['file'] = json_encode($uploaded_files);
    $chat_message = insert_details($data, 'chats');
    $db = \Config\Database::connect();
    $builder = $db->table('chats c');
    $builder->select('c.*,u.username,u.image,u.id as user_id')
        ->join('users u', 'u.id = c.sender_id')
        ->where(['c.id' => $chat_message['id']]);
    $chat = $builder->get()->getResultArray();
    if (!empty($chat)) {
        if (!empty($chat[0]['file'])) {
            $decodedFiles = json_decode($chat[0]['file'], true); // Decode the JSON string to an array
            $chat[0]['file'] = []; // Initialize the array to store the transformed data
            foreach ($decodedFiles as $data_file) {
                if ($disk == "local_server") {
                    $file = base_url($data_file['file']);
                } else if ($disk == "aws_s3") {
                    $file = fetch_cloud_front_url('chat_attachment', $data_file['file']);
                } else {
                    $file = base_url($data_file['file']);
                }
                $chat[0]['file'][] = [
                    'file' => $file,
                    'file_type' => $data_file['file_type'],
                    'file_size' => $data_file['file_size'],
                    'file_name' => $data_file['file_name']
                ];
            }
        } else {
            $chat[0]['file'] = is_array($chat[0]['file']) ? [] : "";
        }
        if (array_key_exists('image', $chat[0])) {
            $imagePath = $chat[0]['image'];
            if ($disk == "local_server") {
                $chat[0]['profile_image'] = fix_provider_path($imagePath);
            } else if ($disk == "aws_s3") {
                $chat[0]['profile_image'] = fetch_cloud_front_url('profile', $chat[0]['image']);
            } else {
                $chat[0]['profile_image'] = fix_provider_path($imagePath);
            }
        }
        $chat_last_message_date = fetch_details('chats', ['e_id' => $chat[0]['e_id']], ['id', 'created_at'], 1, 0, 'created_at', 'DESC');
        if (!empty($chat_last_message_date)) {
            $last_date = $chat_last_message_date[0]['created_at'];
        } else {
            $last_date = now();
        }
        $chat[0]['last_message_date'] = $last_date;
    }
    return $chat[0];
}

function fix_provider_path($imagePath)
{
    $image = "";
    if (empty($imagePath) || $imagePath == NULL) {
        return $image;
    }
    if (strpos($imagePath, '/public/uploads/profiles/') === 0) {
        $image = $imagePath;
    } elseif (file_exists(FCPATH . 'public/uploads/profiles/' . $imagePath)) {
        $image = base_url('public/uploads/profiles/' . $imagePath);
    } else {
        $image = base_url($imagePath);
    }
    if (empty($image)) {
        $image = base_url('public/uploads/profiles/default.png');
    }
    return $image;
}

function getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $chat_id, $last_chat_date, $view_user_type, $when_customer_is_receiver = null)
{
    $db = \Config\Database::connect();
    if ($view_user_type == "admin") {
        $receiver_details = $db->table('users u')->select('u.id,u.image,u.username')->where('u.id', $receiver_id)->get()->getResultArray();
        $receiver_details = !empty($receiver_details) ? $receiver_details[0] : [];
        $receiver_details['image'] = service('fileService')->url($receiver_details['image'] ?? '', 'profile');
    } else if ($view_user_type == "provider" || $view_user_type == "provider_booking") {
        if ($when_customer_is_receiver == "yes") {
            $receiver_details = $db->table('users u')->select('u.id,u.image,u.username')->where('u.id', $receiver_id)->get()->getResultArray();
            $receiver_details = !empty($receiver_details) ? $receiver_details[0] : [];
        } else {
            $receiver_details = $db->table('users u')->select('u.id,u.image,pd.company_name as username')->where('u.id', $receiver_id)->join('partner_details pd', 'pd.partner_id = u.id')->get()->getResultArray();
            $receiver_details = !empty($receiver_details) ? $receiver_details[0] : [];
        }
        $receiver_details['image'] = service('fileService')->url($receiver_details['image'] ?? '', 'profile');
    }
    $sender_details = fetch_details('users', ['id' => $sender_id], ['id', 'username', 'image']);
    $sender_details = !empty($sender_details) ? $sender_details[0] : [];
    $sender_details['image'] = service('fileService')->url($sender_details['image'] ?? '', 'profile');
    $builder = $db->table('chats c');
    $builder->select('c.*,u.username,u.image,u.id as user_id')
        ->join('users u', 'u.id = c.sender_id')
        ->where(['c.id' => $chat_id]);
    $chat = $builder->get()->getResultArray();

    if (!empty($chat)) {
        if (!empty($chat[0]['file'])) {
            $decodedFiles = json_decode($chat[0]['file'], true); // Decode the JSON string into an array
            $chat[0]['file'] = []; // Initialize the array to store the formatted data
            foreach ($decodedFiles as $data) {
                $chat[0]['file'][] = [
                    'file' => service('fileService')->url($data['file'] ?? '', 'chat_attachment'),
                    'file_type' => $data['file_type'],
                    'file_name' => $data['file_name'],
                    'file_size' => $data['file_size'],
                ];
            }
        } else {
            $chat[0]['file'] = is_array($chat[0]['file']) ? [] : "";
        }
        $chat[0]['profile_image'] = service('fileService')->url($chat[0]['image'] ?? '', 'profile');
        $chat[0]['last_message_date'] = $last_chat_date;
        $data = $chat[0];
    }
    $data['sender_details'] = $sender_details;
    $data['receiver_details'] = $receiver_details;
    $data['last_message_date'] = $last_chat_date;
    $data['viewer_type'] = $view_user_type;

    return $data;
}

/**
 * Build a reusable chat payload describing booking + provider context.
 * We add these keys so mobile apps can display richer message cards
 * without performing extra queries after every send action.
 *
 * @param int|null $providerId   Provider attached to the chat (if any)
 * @param int|null $bookingId    Booking reference for the chat (if any)
 * @param int|null $receiverType Receiver type from chats table (0/1/2)
 * @param int|null $senderId     Current sender id (helps consumers trace origin)
 *
 * @return array Simple array with camelCase keys expected by apps
 */
function build_chat_message_details(?int $providerId, ?int $bookingId, ?int $receiverType, ?int $senderId): array
{
    // Default skeleton keeps response stable even when data is missing.
    $details = [
        'bookingId' => $bookingId ? (int) $bookingId : null,
        'bookingStatus' => null,
        'companyName' => null,
        'translatedName' => null,
        'receiverType' => $receiverType !== null ? (int) $receiverType : null,
        'providerId' => $providerId ? (int) $providerId : null,
        'profile' => null,
        'senderId' => $senderId ? (int) $senderId : null,
    ];

    $orderPartnerId = null;

    // Booking status helps providers understand current job state instantly.
    if ($bookingId) {
        $orderRow = fetch_details('orders', ['id' => $bookingId], ['status', 'partner_id']);
        if (!empty($orderRow[0])) {
            $details['bookingStatus'] = $orderRow[0]['status'] ?? null;
            $orderPartnerId = $orderRow[0]['partner_id'] ?? null;
        }
    }

    if (!$providerId && $orderPartnerId) {
        $details['providerId'] = (int) $orderPartnerId;
        $providerId = (int) $orderPartnerId;
    }

    // Provider metadata (name, avatar, translations) is optional but useful.
    if ($providerId) {
        $providerRow = fetch_details('users', ['id' => $providerId], ['id', 'username', 'image']);
        $details['profile'] = service('fileService')->url($providerRow[0]['image'] ?? '', 'profile');

        $partnerRow = fetch_details('partner_details', ['partner_id' => $providerId], ['company_name']);
        $details['companyName'] = $partnerRow[0]['company_name'] ?? ($providerRow[0]['username'] ?? null);

        // Translated name honours the language header while keeping fallbacks.
        $translatedName = get_translated_partner_field($providerId, 'company_name', $details['companyName']);
        if (empty($translatedName)) {
            $translatedName = get_translated_partner_field($providerId, 'username', $details['companyName']);
        }
        $details['translatedName'] = $translatedName;
    }

    return $details;
}

/**
 * Build a JSON-encoded chat_user payload for notification data.
 * The structure matches the provider object used in new_message notifications,
 * so that apps can parse it consistently across event types.
 *
 * @param int         $partnerId              Provider / partner ID
 * @param string|null $orderId                Booking / order ID (if any)
 * @param string      $partnerName            Raw company name
 * @param string      $translatedPartnerName  Translated company name
 * @param bool        $isBlockByUser          Whether the customer blocked the provider
 * @param bool        $isBlockByProvider      Whether the provider blocked the customer
 *
 * @return string|null JSON string or null on encoding failure
 */
function build_chat_user_payload(
    int $partnerId,
    ?string $orderId,
    string $partnerName,
    string $translatedPartnerName,
    bool $isBlockByUser = false,
    bool $isBlockByProvider = false
): ?string {
    // Fetch provider image from users table.
    $providerUser = fetch_details('users', ['id' => $partnerId], ['id', 'image']);
    $rawImage = $providerUser[0]['image'] ?? '';

    // Resolve via FileService so both local and S3 disks are handled consistently.
    $imageUrl = service('fileService')->url($rawImage, 'profile');

    // Build provider object matching the chat_user structure from new_message.
    $chatUser = [
        'id' => $partnerId,
        'partner_id' => $partnerId,
        'partner_name' => $partnerName,
        'translated_partner_name' => $translatedPartnerName,
        'image' => $imageUrl,
        'booking_id' => $orderId ? (string) $orderId : null,
        'order_status' => null,
        'translated_order_status' => null,
        'un_read_chats' => 0,
        'is_blocked' => ($isBlockByUser || $isBlockByProvider) ? 1 : 0,
        'is_block_by_user' => $isBlockByUser ? 1 : 0,
        'is_block_by_provider' => $isBlockByProvider ? 1 : 0,
    ];

    // Fetch order status if booking exists.
    if ($orderId) {
        $orderRow = fetch_details('orders', ['id' => (int) $orderId], ['status']);
        if (!empty($orderRow[0]['status'])) {
            $chatUser['order_status'] = $orderRow[0]['status'];
            $chatUser['translated_order_status'] = getTranslatedValue($orderRow[0]['status'], 'panel');
        }
    }

    $payload = json_encode($chatUser, JSON_UNESCAPED_SLASHES);
    return ($payload !== false) ? $payload : null;
}

function getLastMessageDateFromChat($e_id)
{
    $chat_last_message_date = fetch_details('chats', ['e_id' => $e_id], ['id', 'created_at'], 1, 0, 'created_at', 'DESC');
    if (!empty($chat_last_message_date)) {
        $last_date = $chat_last_message_date[0]['created_at'];
    } else {
        $last_date1 = new DateTime();
        $last_date = $last_date1->format('Y-m-d H:i:s');
    }
    return $last_date;
}
function checkModificationInDemoMode($superadminEmail)
{
    if ($superadminEmail == "superadmin@gmail.com") {
        return true;
    } else {
        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1) {
            return true;
        } else {
            $response['error'] = true;
            $response['message'] = labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.');
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            return $response;
        }
    }
}
function setPageInfo(&$data, $title, $mainPage)
{
    $data['title'] = $title;
    $data['main_page'] = $mainPage;
}
function getAccessToken()
{
    try {
        // Get service file name from settings using Settings model (same logic as NotificationService.php)
        $settingsModel = new \App\Models\Settings();
        $fileRecord = $settingsModel->where('variable', 'firebase_settings')->first();

        if (!$fileRecord) {
            log_message('error', 'FCM configuration not found in settings');
            return false;
        }

        $firebaseSettings = json_decode($fileRecord['value'], true);
        $fileName = $firebaseSettings['json_file'] ?? null;

        // Alternative: Try to get from service_file setting directly
        if (!$fileName) {
            $fileRecord = $settingsModel->where('variable', 'json_file')->first();
            $fileName = $fileRecord['value'] ?? null;
        }

        if (empty($fileName)) {
            log_message('error', 'FCM service file not configured in settings');
            return false;
        }

        // Use same path construction as NotificationService.php
        // APPPATH . '../public/' resolves to the public directory
        $filePath = realpath(APPPATH . '../public/' . $fileName);

        if (!$filePath || !file_exists($filePath)) {
            log_message('error', 'Firebase service account file not found at: ' . ($filePath ?: APPPATH . '../public/' . $fileName));
            return false;
        }

        $client = new Client();
        $client->setAuthConfig($filePath);
        $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);
        $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];
        return $accessToken;
    } catch (\Throwable $th) {
        log_message('error', 'FCM access token error in getAccessToken: ' . $th->getMessage());
        return false;
    }
}

function sendNotificationToFCM($url, $access_token, $Data)
{
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $Data);
    $result = curl_exec($ch);

    // log_message('debug', 'The notification send response is ' . json_encode($result));
    if (!empty($result['error']['code']) && $result['error']['code'] == "404") {
        return false;
        // die('Curl failed: ' . curl_error($ch));
    }
    unset($ch);
    return true;
}

function extractVariables($content)
{
    preg_match_all('/\[\[(.*?)\]\]/', $content, $matches);
    return array_map('trim', $matches[1]);
}

function unsubscribe_link_user_encrypt($user_id, $email)
{
    $simple_string = $user_id . "-" . $email;

    $ciphering = "AES-128-CTR";
    $options = 0;

    $encryption_iv = env('DECRYPTION_IV');
    $encryption_key = env('decryption_key');

    $encrypted = openssl_encrypt(
        $simple_string,
        $ciphering,
        $encryption_key,
        $options,
        $encryption_iv
    );

    // Make it URL-safe (base64url)
    return rtrim(strtr($encrypted, '+/', '-_'), '=');
}

function unsubscribe_link_user_decrypt($token)
{
    $ciphering = "AES-128-CTR";
    $options = 0;

    $decryption_iv = env('DECRYPTION_IV');
    $decryption_key = env('decryption_key');

    // Convert URL-safe base64 back to normal base64
    $token = strtr($token, '-_', '+/');

    // Restore padding
    $token .= str_repeat('=', 3 - (3 + strlen($token)) % 4);

    $decryption = openssl_decrypt(
        $token,
        $ciphering,
        $decryption_key,
        $options,
        $decryption_iv
    );

    if (!$decryption) {
        return false; // invalid or tampered token
    }

    return explode("-", $decryption);
}

function is_unsubscribe_enabled($user_id)
{
    $user = fetch_details('users', ['id' => $user_id], ['id', 'unsubscribe_email'])[0];
    return $user['unsubscribe_email'];
}

function compressImage(string $source, string $destination, int $quality = 75): bool
{
    // 1. Source must exist
    if (!is_file($source)) {
        log_message('error', 'compressImage: source file missing -> ' . $source);
        return false;
    }

    // 2. Ensure destination directory exists
    $destinationDir = dirname($destination);
    if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
        log_message('error', 'compressImage: failed to create directory -> ' . $destinationDir);
        return false;
    }

    $settings = get_settings('general_settings', true);

    // 3. Compression disabled → just copy
    if (($settings['image_compression_preference'] ?? 1) == 0) {
        if (!copy($source, $destination)) {
            log_message('error', 'compressImage: copy failed -> ' . $source . ' → ' . $destination);
            return false;
        }
        return true;
    }

    // 4. Detect MIME safely
    $mime = mime_content_type($source);

    // 5. SVG: copy as-is (no GD, no compression)
    if ($mime === 'image/svg+xml') {
        if (!copy($source, $destination)) {
            log_message('error', 'compressImage: SVG copy failed -> ' . $source);
            return false;
        }
        return true;
    }

    // 6. Load image resource
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;

        case 'image/png':
            $image = imagecreatefrompng($source);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;

        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;

        default:
            // Unsupported image → copy without touching
            if (!copy($source, $destination)) {
                log_message('error', 'compressImage: unsupported mime copy failed -> ' . $mime);
                return false;
            }
            return true;
    }

    if (!$image) {
        log_message('error', 'compressImage: failed to create image resource -> ' . $source);
        return false;
    }

    // 7. Override quality from settings if present
    $quality = (int) ($settings['image_compression_quality'] ?? $quality);

    // 8. Save according to format (NO forced JPEGs)
    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($image, $destination, max(0, min(100, $quality))),
        'image/png' => imagepng($image, $destination, 9),
        'image/gif' => imagegif($image, $destination),
        default => false,
    };

    // Free memory - imagedestroy() is deprecated in PHP 8.0+
    // GD image objects are now auto-freed when they go out of scope
    unset($image);

    // 9. Fallback if compression failed
    if (!$saved) {
        if (!copy($source, $destination)) {
            log_message('error', 'compressImage: fallback copy failed -> ' . $source);
            return false;
        }
    }

    return true;
}

function copy_image($number, $og_path)
{
    $sourceFilePath = FCPATH . $number;
    if (file_exists($sourceFilePath)) {
        $destinationDirectory = FCPATH . $og_path;
        $og_path = rtrim($og_path, '/');
        $fileName = basename($sourceFilePath);
        $destinationFilePath = $destinationDirectory . '/' . $fileName;
        if (copy($sourceFilePath, $destinationFilePath)) {
            $image = $og_path . '/' . $fileName;
        } else {
            $image = $og_path . '/' . $fileName;
        }
    } else {
        $image = "";
    }
    return $image;
}

/**
 * Send OTP via SMS using NotificationService with template support
 * 
 * This function sends an OTP code via SMS using NotificationService.
 * It uses customizable SMS templates based on user's language preference.
 * 
 * @param int $otp The OTP code to send
 * @param string|null $mobile_number Mobile number without country code
 * @param string|null $country_code Country code for the mobile number
 * @return array Response array with error status and message
 */
function set_user_otp($otp, $mobile_number = null, $country_code = null)
{
    $dateString = date('Y-m-d H:i:s');

    // Build the full mobile number with country code
    if (!empty($country_code)) {
        $mobile_for_sms = $country_code . $mobile_number;
    } else {
        $mobile_for_sms = $mobile_number;
    }

    // Try to get user_id from phone number for better language preference handling
    $userData = null;
    if (!empty($country_code)) {
        $userData = fetch_details('users', ['phone' => $mobile_number, 'country_code' => $country_code], ['id']);
    } else {
        $userData = fetch_details('users', ['phone' => $mobile_number], ['id']);
    }
    $user_id = !empty($userData) ? $userData[0]['id'] : null;

    // Prepare data for OTP record using new schema (identity, channel, user_id)
    $data = [
        'otp' => $otp,
        'identity' => $mobile_for_sms,
        'channel' => 'sms',
        'created_at' => $dateString
    ];
    if ($user_id) {
        $data['user_id'] = $user_id;
    }

    // Check if OTP record exists for this identity and channel
    $otps = fetch_details('otps', ['user_id' => $user_id, 'identity' => $mobile_for_sms, 'channel' => 'sms']);

    // Use NotificationService to send OTP SMS
    try {
        $notificationService = new \App\Services\NotificationService();

        // Prepare recipients
        $recipients = ['phone' => $mobile_for_sms];
        if ($user_id) {
            $recipients['user_id'] = $user_id;
        }

        // Prepare context with OTP
        $context = ['otp' => $otp];

        // Send notification using NotificationService
        $result = $notificationService->send(
            eventType: 'send_otp',
            recipients: $recipients,
            context: $context,
            options: ['channels' => ['sms']]
        );

        // Check if SMS was sent successfully
        $smsResult = $result['results']['sms'] ?? null;
        if ($smsResult && $smsResult['success'] === true) {
            // Update existing OTP record or insert new one
            if (!empty($otps)) {
                // Update existing record
                update_details($data, ['user_id' => $user_id, 'identity' => $mobile_for_sms, 'channel' => 'sms'], 'otps');
            } else {
                // Insert new record
                insert_details($data, 'otps');
            }
            return [
                "error" => false,
                "message" => "OTP send successfully.",
                "data" => $data
            ];
        } else {
            $errorMessage = $smsResult['message'] ?? "OTP Can not send.";
            return [
                "error" => true,
                "message" => $errorMessage,
                "data" => $data
            ];
        }
    } catch (\Exception $e) {
        log_message('error', 'Error sending OTP SMS via NotificationService: ' . $e->getMessage());
        return [
            "error" => true,
            "message" => "OTP Can not send.",
            "data" => $data
        ];
    }
}

/**
 * Send OTP via email using NotificationService with template support
 * 
 * This function sends an OTP code to the provided email address using NotificationService.
 * It uses customizable email templates based on user's language preference.
 * It stores the OTP in the otps table for verification purposes.
 * 
 * @param string $email Email address to send OTP to
 * @param int $otp The OTP code to send
 * @return array Response array with error status and message
 */
function set_user_otp_email($email, $otp)
{
    $dateString = date('Y-m-d H:i:s');

    // Try to get user_id from email for better language preference handling
    $user = fetch_details('users', ['email' => $email], ['id']);
    $user_id = !empty($user) ? $user[0]['id'] : null;

    // Prepare data for OTP record using new schema (identity, channel, user_id)
    $data = [
        'otp' => $otp,
        'identity' => $email,
        'channel' => 'email',
        'created_at' => $dateString
    ];
    if ($user_id) {
        $data['user_id'] = $user_id;
    }

    // Check if OTP record exists for this email and channel
    $otps = fetch_details('otps', ['identity' => $email, 'channel' => 'email', 'user_id' => $user_id]);

    // Use NotificationService to send OTP email
    try {
        $notificationService = new \App\Services\NotificationService();

        // Prepare recipients
        $recipients = ['email' => $email];
        if ($user_id) {
            $recipients['user_id'] = $user_id;
        }

        // Prepare context with OTP
        $context = ['otp' => $otp];

        // Send notification using NotificationService
        $result = $notificationService->send(
            eventType: 'send_otp',
            recipients: $recipients,
            context: $context,
            options: ['channels' => ['email']]
        );

        // Check if email was sent successfully
        $emailResult = $result['results']['email'] ?? null;
        if ($emailResult && $emailResult['success'] === true) {
            // Update existing OTP record or insert new one
            if (!empty($otps)) {
                // Update existing record
                update_details($data, ['user_id' => $user_id, 'identity' => $email, 'channel' => 'email'], 'otps');
            } else {
                // Insert new record
                insert_details($data, 'otps');
            }
            return [
                "error" => false,
                "message" => "OTP send successfully.",
                "data" => $data
            ];
        } else {
            $errorMessage = $emailResult['message'] ?? "OTP Can not send.";
            return [
                "error" => true,
                "message" => $errorMessage,
                "data" => $data
            ];
        }
    } catch (\Exception $e) {
        log_message('error', 'Error sending OTP email via NotificationService: ' . $e->getMessage());
        return [
            "error" => true,
            "message" => "OTP Can not send.",
            "data" => $data
        ];
    }
}

function send_sms($phone, $msg, $country_code = "+91")
{
    $data = get_settings('sms_gateway_setting', true);
    $current_sms_gateway = $data['current_sms_gateway'] ?? "twilio";
    if ($current_sms_gateway == "vonage") {
    } else if ($current_sms_gateway == "twilio") {
        $account_sid = $data['twilio']['twilio_account_sid'] ?? '';
        $auth_token = $data['twilio']['twilio_auth_token'] ?? "";
        $from = $data['twilio']['twilio_from'] ?? "";

        $body = [
            'To' => $phone,
            'From' => $from,
            'Body' => $msg
        ];
        // return curl_sms(
        //     "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json",
        //     'POST',
        //     $body,
        //     [
        //         "Authorization: Basic " . base64_encode("{$account_sid}:{$auth_token}")
        //     ]
        // );
        return curl_sms(
            "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json",
            'POST',
            http_build_query($body), // <-- Add this
            [
                "Authorization: Basic " . base64_encode("{$account_sid}:{$auth_token}"),
                "Content-Type: application/x-www-form-urlencoded"
            ]
        );
    }
}

function curl_sms($url, $method = 'GET', $data = [], $headers = [])
{
    $ch = curl_init();
    $curl_options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
        )
    );
    if (count($headers) != 0) {
        $curl_options[CURLOPT_HTTPHEADER] = $headers;
    }
    if (strtolower($method) == 'post') {
        $curl_options[CURLOPT_POST] = 1;
        $curl_options[CURLOPT_POSTFIELDS] = $data;
    } else {
        $curl_options[CURLOPT_CUSTOMREQUEST] = 'GET';
    }
    curl_setopt_array($ch, $curl_options);
    $result = array(
        'body' => json_decode(curl_exec($ch), true),
        'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    );
    return $result;
}

function check_notification_setting($setting, $type)
{
    $data = get_settings('notification_settings', true);
    if (isset($data[$setting . '_' . $type])) {
        return filter_var($data[$setting . '_' . $type], FILTER_VALIDATE_BOOLEAN);
    }
    return true; // Default to enabled for new settings
}

function encrypt_data($key, $text)
{
    $iv = openssl_random_pseudo_bytes(16);
    $key .= "0000";
    $encrypted_data = openssl_encrypt($text, 'aes-256-cbc', $key, 0, $iv);
    $data = array("ciphertext" => $encrypted_data, "iv" => bin2hex($iv));
    return $data;
}

function checkOTPExpiration(string $otpTime, int $ttlSeconds = 600): array
{
    $otpTimestamp = strtotime($otpTime);

    if ($otpTimestamp === false) {
        return [
            'error' => true,
            'message' => labels('invalid_otp_time_format', 'Invalid OTP time format'),
        ];
    }

    if ((time() - $otpTimestamp) > $ttlSeconds) {
        return [
            'error' => true,
            'message' => labels('otp_has_expired', 'OTP has expired'),
        ];
    }

    return [
        'error' => false,
        'message' => labels('otp_is_valid', 'OTP is valid'),
    ];
}

function feature_section_type($type)
{
    $value = "";
    if ($type == "categories") {
        $value = labels("categories", "Categories");
    } else if ($type == "partners") {
        $value = labels("partners", "Partners");
    } else if ($type == "top_rated_partner") {
        $value = labels("top_rated_provider", "Top Rated Provider");
    } else if ($type == "previous_order") {
        $value = labels("previous_order", "Previos Order");
    } else if ($type == "ongoing_order") {
        $value = labels("ongoing_order", "Ongoing Order");
    } else if ($type == "near_by_provider") {
        $value = labels("near_by_providers", "Near By Providers");
    } else if ($type == "banner") {
        $value = labels("banner", "Banner");
    } else {
        $value = labels("no_section_type_found", "No Section Type Found");
    }
    return $value;
}

/**
 * Maps display names (labels) back to their database keys for section types
 * This function is used for search functionality to allow users to search by display names
 * 
 * @param string $displayName The display name or search term to map
 * @return array Array of possible database keys that match the display name
 */
function get_section_type_keys_from_display_name($displayName)
{
    // Convert search term to lowercase for case-insensitive matching
    $searchTerm = strtolower(trim($displayName));
    $matchingKeys = [];

    // Map all possible display names to their database keys
    // Check each section type's display name against the search term
    $sectionTypeMappings = [
        'categories' => [strtolower(labels("categories", "Categories")), 'categories'],
        'partners' => [strtolower(labels("partners", "Partners")), 'partners'],
        'top_rated_partner' => [strtolower(labels("top_rated_provider", "Top Rated Provider")), 'top_rated_partner', 'top rated provider', 'top rated'],
        'previous_order' => [strtolower(labels("previous_order", "Previos Order")), 'previous_order', 'previous order', 'previous booking'],
        'ongoing_order' => [strtolower(labels("ongoing_order", "Ongoing Order")), 'ongoing_order', 'ongoing order', 'ongoing booking'],
        'near_by_provider' => [strtolower(labels("near_by_providers", "Near By Providers")), 'near_by_provider', 'near by provider', 'near by providers'],
        'banner' => [strtolower(labels("banner", "Banner")), 'banner']
    ];

    // Check if search term matches any display name or partial match
    foreach ($sectionTypeMappings as $key => $displayNames) {
        foreach ($displayNames as $display) {
            // Check for exact match or if search term is contained in display name
            if ($searchTerm === $display || strpos($display, $searchTerm) !== false || strpos($searchTerm, $display) !== false) {
                $matchingKeys[] = $key;
                break; // Found a match for this key, move to next
            }
        }
    }

    return $matchingKeys;
}

function banner_type($type)
{
    $value = "";
    if ($type == "banner_default") {
        $value = labels("default", "Default");
    } else if ($type == "banner_category") {
        $value = labels("category", "Category");
    } else if ($type == "banner_provider") {
        $value = labels("provider", "Provider");
    } else if ($type == "banner_url") {
        $value = labels("url", "URL");
    } else {
        $value = "-";
    }
    return $value;
}

function create_folder($path)
{
    $fullPath = FCPATH . $path;
    if (is_dir($fullPath)) {
        return true;
    }
    if (mkdir($fullPath, 0775, true)) {
        return true;
    } else {
        return false;
    }
}

function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function updateEnv($key, $value)
{
    $envPath = ROOTPATH . '.env';

    if (!file_exists($envPath)) {
        touch($envPath);
    }

    $fp = fopen($envPath, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        return false;
    }

    $envContent = stream_get_contents($fp);
    $pattern = "/^" . preg_quote($key, '/') . "=.*/m";
    $line = "{$key}={$value}";

    if (preg_match($pattern, $envContent)) {
        $envContent = preg_replace_callback($pattern, fn() => $line, $envContent);
    } else {
        $envContent = rtrim($envContent, "\n") . "\n{$line}\n";
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $envContent);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;

    return true;
}

function diffForHumans($datetime)
{
    // We convert both timestamps to the configured system timezone so the UI and modal stay in sync.
    $settings = get_settings('general_settings', true);
    $timezoneName = $settings['system_timezone'] ?? date_default_timezone_get();
    $timezone = new \DateTimeZone($timezoneName);

    try {
        $createdAt = new \DateTime($datetime, $timezone);
    } catch (\Exception $e) {
        // Fall back to PHP parsing if the incoming value is malformed and normalize it afterwards.
        $createdAt = new \DateTime($datetime);
        $createdAt->setTimezone($timezone);
    }

    $now = new \DateTime('now', $timezone);
    $diff = $now->getTimestamp() - $createdAt->getTimestamp();

    // Define time intervals
    $intervals = [
        'year' => 31536000,  // 365 days * 24 hours * 60 minutes * 60 seconds
        'month' => 2592000,   // 30 days * 24 hours * 60 minutes * 60 seconds
        'week' => 604800,    // 7 days * 24 hours * 60 minutes * 60 seconds
        'day' => 86400,     // 24 hours * 60 minutes * 60 seconds
        'hour' => 3600,      // 60 minutes * 60 seconds
        'minute' => 60,        // 60 seconds
        'second' => 1
    ];

    foreach ($intervals as $key => $value) {
        if ($diff >= $value) {
            $time_diff = floor($diff / $value);
            return $time_diff == 1
                ? "1" . labels($key, $key) . ' ' . labels('ago', 'ago')
                : "$time_diff " . labels($key, $key) . ' ' . labels('ago', 'ago');
        }
    }

    return labels('just_now', 'Just now');
}

function send_notification_to_related_providers($category_id, $custom_job_request_id, $latitude, $longitude)
{
    $partners = fetch_details('partner_details', ['is_accepting_custom_jobs' => 1], ['partner_id', 'custom_job_categories']);
    $category_name = fetch_details('categories', ['id' => $category_id], ['name']);
    // Prepare partner IDs for the specific category
    $partners_ids = [];
    foreach ($partners as $partner) {
        // Ensure custom_job_categories is a valid JSON string
        $category_ids = !empty($partner['custom_job_categories'])
            ? json_decode($partner['custom_job_categories'], true)
            : [];
        if (is_array($category_ids) && in_array($category_id, $category_ids)) {
            $partners_ids[] = $partner['partner_id'];
        }
    }

    // Proceed only if there are matching partners
    if (!empty($partners_ids)) {
        $settings = get_settings('general_settings', true);
        $db = \Config\Database::connect();
        $builder = $db->table('partner_details pd');
        $builder->select("
        pd.*,
        u.username as partner_name, u.balance, u.longitude, u.latitude, 
        u.payable_commision,
        ps.id as partner_subscription_id, ps.status, ps.max_order_limit,
        st_distance_sphere(POINT('$longitude','$latitude'), POINT(u.longitude, u.latitude))/1000 as distance
    ")
            ->join('users u', 'pd.partner_id = u.id')
            ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
            ->whereIn('pd.partner_id', $partners_ids) // Filter by partner IDs matching the category
            ->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance')) // Radius check
            ->groupBy(['pd.partner_id', 'pd.id']);
        $partners_for_notifiy = $builder->get()->getResultArray();

        // Insert each eligible partner into custom_job_provider (so they see the job in their list).
        foreach ($partners_for_notifiy as $partner_row) {
            insert_details(
                [
                    'custom_job_request_id' => $custom_job_request_id['id'],
                    'partner_id' => $partner_row['partner_id']
                ],
                'custom_job_provider'
            );
        }

        // Send notifications via NotificationService and queue (eventType: new_custom_job_request).
        $provider_ids = array_column($partners_for_notifiy, 'partner_id');
        if (!empty($provider_ids)) {
            $context = [
                'custom_job_request_id' => $custom_job_request_id['id'],
                'category_id' => $category_id,
                'category_name' => isset($category_name[0]['name']) ? $category_name[0]['name'] : '',
            ];

            // In demo mode, limit FCM tokens (per language) to the last 20,
            // while still keeping full user_ids for DB/email/SMS.
            $isDemoMode = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;
            $fcmDemoTokenLimit = $isDemoMode ? 20 : 0;

            queue_notification_service(
                eventType: 'new_custom_job_request',
                recipients: [],
                context: $context,
                options: [
                    'user_ids' => $provider_ids,
                    'channels' => ['fcm', 'email', 'sms'],
                    'platforms' => ['android', 'ios', 'provider_panel'],
                    // This is consumed by FcmProvider::sendNotification() to trim tokens only.
                    'fcm_demo_token_limit' => $fcmDemoTokenLimit,
                ],
                priority: 'default'
            );
        }
    }
}

// Functions for handling transactions
function handleAdditionalCharge($status, $transaction, $order, $order_id, $user_id)
{
    $data1['status'] = $status == "success" ? 'success' : 'failed';
    if (!empty($transaction)) {
        update_details($data1, [
            'order_id' => $order_id,
            'id' => $transaction['id'],
            'user_id' => $user_id
        ], 'transactions');
    } else {
        createTransaction($order, $order_id, 'failed', 'txn_payment_cancelled_by_customer', $user_id);
    }
}

function handleSuccessfulTransaction($transaction, $order, $order_id, $user_id, $is_redorder = false)
{
    if (!empty($transaction)) {
        $data1['status'] = 'success';
        update_details($data1, [
            'order_id' => $order_id,
            'user_id' => $user_id
        ], 'transactions');
    }
    $cart_data = fetch_cart(true, $user_id);
    if ($is_redorder == false && !empty($cart_data)) {
        foreach ($cart_data['data'] as $row) {
            delete_details(['id' => $row['id']], 'cart');
        }
    }
}

function handleFailedTransaction($transaction, $order, $order_id, $user_id)
{
    // Finalise the up-front pending row (e.g. the razorpay row stamped with
    // reference=rzp_order_id by razorpay_create_order) so a manual checkout-close
    // marks that SAME transaction failed instead of inserting a duplicate
    // "cancelled by customer" entry. Mapped by (order_id, user_id) pending row.
    $pending = !empty($transaction) ? $transaction : fetch_details(
        'transactions',
        [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'transaction_type' => 'transaction',
            'status' => 'pending',
        ],
        [],
        1,
        0,
        'id',
        'DESC'
    );

    if (!empty($pending)) {
        update_details(
            ['status' => 'failed', 'message' => 'txn_payment_cancelled_by_customer'],
            ['id' => $pending[0]['id']],
            'transactions'
        );
    } else {
        // No pending row to finalise. Only create a fresh record when the gateway
        // webhook has not already written a terminal row for this order — otherwise
        // we would duplicate the failure entry.
        $existing = fetch_details(
            'transactions',
            [
                'order_id' => $order_id,
                'user_id' => $user_id,
                'transaction_type' => 'transaction',
            ],
            ['id'],
            1,
            0,
            'id',
            'DESC'
        );
        if (empty($existing)) {
            createTransaction($order, $order_id, 'failed', 'txn_payment_cancelled_by_customer', $user_id);
        }
    }

    update_details(['status' => "cancelled"], [
        'id' => $order_id,
        'status' => 'awaiting',
        'user_id' => $user_id
    ], 'orders');
}

function createTransaction($order, $order_id, $status, $message, $user_id)
{
    $data = [
        'transaction_type' => 'transaction',
        'user_id' => $user_id,
        'partner_id' => "",
        'order_id' => $order_id,
        'type' => $order[0]['payment_method'],
        'txn_id' => "",
        'amount' => $order[0]['final_total'],
        'status' => $status,
        'currency_code' => "",
        'message' => $message,
    ];
    add_transaction($data);
}

function priceFormat($currencyCode, $price, $decimalDigits)
{
    $r = number_to_currency($price, $currencyCode, locale_get_default(), $decimalDigits);
    // Check if price is empty or "null" (as string)
    if (empty($price) || $price === "null") {
        return $price;
    }
    // Convert price string to a float after removing commas
    $newPrice = (float) str_replace(",", "", $price);
    // Define the locale
    $locale = locale_get_default(); // or specify a default locale, e.g., "en_US"
    // Initialize formatter
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currencyCode);
    $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimalDigits);
    // Format and return the price
    return $formatter->formatCurrency($newPrice, $currencyCode);
}

function update_custom_job_status($order_id, $status)
{
    $get_custom_job_data = fetch_details('orders', ['id' => $order_id]);
    if (!empty($get_custom_job_data)) {
        if ($get_custom_job_data[0]['custom_job_request_id'] != "" || $get_custom_job_data[0]['custom_job_request_id'] != NULL) {
            $update = update_details(['status' => $status], ['id' => $get_custom_job_data[0]['custom_job_request_id']], 'custom_job_requests');
        }
    }
}

function truncateWords($str, $limit = 20)
{
    $str = strip_tags($str); // Remove HTML tags
    if (mb_strlen($str) <= $limit) {
        return $str;
    }
    return mb_substr($str, 0, $limit) . '...';
}

if (!function_exists('image_url')) {
    function image_url($image_path)
    {
        $settings = get_settings('general_settings', true);
        $file_manager = $settings['file_manager'] ?? "local_server";
        if ($file_manager == "aws_s3") {
            return $image_path;
        } else if ($file_manager == "local_server") {
            return $image_path;
            // Trim the image path to remove any whitespace
            $image_path = trim($image_path);
            // Get settings for default logo
            $settings = get_settings('general_settings', true);
            $default_logo = base_url("public/uploads/site/" . $settings['logo']);
            // If empty path, return default logo
            if (empty($image_path)) {
                return $default_logo;
            }
            // Handle URLs
            if (filter_var($image_path, FILTER_VALIDATE_URL)) {
                // Parse the URL to get the path
                $parsed_url = parse_url($image_path);
                $url_path = isset($parsed_url['path']) ? urldecode($parsed_url['path']) : ''; // Check if 'path' exists
                // Extract the path after 'public/'
                if (strpos($url_path, '/public/') !== false) {
                    $relative_path = substr($url_path, strpos($url_path, '/public/') + 8);
                } else {
                    $relative_path = ltrim($url_path, '/');
                }
                // Define possible paths to check
                $possible_paths = [
                    FCPATH . $relative_path,
                    FCPATH . 'public/' . $relative_path
                ];
                foreach ($possible_paths as $path) {
                    if (is_file($path)) { // Use is_file instead of file_exists
                        return $image_path;
                    }
                }
                // Additional check for direct path
                $direct_path = FCPATH . str_replace('/public/', '', $url_path);
                if (is_file($direct_path)) {
                    return $image_path;
                }
                // If file not found, return default logo
                return $default_logo;
            }
            // Handle local paths
            // Remove 'public/' prefix if exists
            $clean_path = str_replace('public/', '', $image_path);
            $clean_path = urldecode($clean_path); // Decode URL-encoded characters
            // Define possible local paths to check
            $possible_paths = [
                FCPATH . $clean_path,
                FCPATH . 'public/' . $clean_path,
                FCPATH . 'backend/' . $clean_path
            ];
            foreach ($possible_paths as $path) {
                if (is_file($path)) {
                    $final_url = base_url(str_replace(FCPATH, '', $path));
                    return $final_url;
                }
            }
            // If no file found, return default logo
            return $default_logo;
        } else {
            return $image_path;
        }
    }
}

function upload_to_aws_s3($file_name, $file_tmp_name, $folder_name)
{
    try {
        // Load CI4's Security helper for filename sanitization
        // This provides sanitize_filename() function to prevent malicious filenames
        helper('security');

        // Security: Remove null bytes from inputs (prevents null byte injection attacks)
        $file_name = str_replace("\0", '', $file_name ?? '');
        $folder_name = str_replace("\0", '', $folder_name ?? '');

        // Security: Sanitize folder_name to prevent path traversal in S3 keys
        // Only allow alphanumeric characters, underscores, forward slashes, and hyphens
        $directory = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $folder_name);

        // Security: Use basename() to extract only the filename part (prevents directory traversal in filename)
        // This ensures that even if $file_name contains "../", it will be stripped
        $file_name = basename($file_name);

        // Security: Sanitize the filename using CI4's sanitize_filename() helper
        // This removes dangerous characters and prevents malicious filenames
        $sanitized_filename = sanitize_filename($file_name);

        // If sanitization changed the filename, it contained dangerous characters - reject it
        if ($file_name !== $sanitized_filename) {
            return [
                "error" => true,
                "message" => "Invalid filename detected. File name contains dangerous characters."
            ];
        }

        // Use the sanitized filename
        $file_name = $sanitized_filename;

        $S3_settings = get_settings('general_settings', true);
        $aws_key = $S3_settings['aws_access_key_id'] ?? '';
        $aws_secret = $S3_settings['aws_secret_access_key'] ?? '';
        $bucket = $S3_settings['aws_bucket'] ?? '';
        $region = $S3_settings['aws_default_region'] ?? 'us-east-1';
        if (!$aws_key || !$aws_secret || !$bucket || !$region) {
            return [
                "error" => true,
                "message" => "AWS configuration missing. Please check configuration variables."
            ];
        }
        $config = [
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret,
            ],
        ];
        $s3 = new S3Client($config);
        $file_open = fopen($file_tmp_name, 'r');
        // Security: Construct the full file path with the directory using sanitized components
        $key = $directory ? rtrim($directory, '/') . '/' . $file_name : $file_name;
        $result = $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => $file_open,
            'ContentType' => mime_content_type($file_tmp_name) // Ensure the file has the correct MIME type
            // 'ACL'    => 'public-read'
        ]);
        fclose($file_open);
        if ($result && isset($result['ObjectURL'])) {
            $file_url = $result['ObjectURL'];
            return [
                "error" => false,
                "data" => $file_url,
                "message" => "File uploaded successfully"
            ];
        } else {
            return [
                "error" => true,
                "data" => "",
                "message" => "File upload failed"
            ];
        }
    } catch (Exception $e) {
        return [
            "error" => true,
            "message" => "An error occurred: " . $e->getMessage()
        ];
    }
}

/**
 * @param array<string,true>|null $allowed_basenames When provided, the file
 *        count is restricted to objects whose basename exists in this set.
 *        Used by the provider gallery so counts reflect only that provider's
 *        files instead of every file in a shared folder. Admin passes null
 *        and keeps the full folder-wide count.
 */
function get_aws_s3_folder_info($folder_name = null, ?array $allowed_basenames = null)
{
    try {
        $S3_settings = get_settings('general_settings', true);
        $aws_key = $S3_settings['aws_access_key_id'] ?? '';
        $aws_secret = $S3_settings['aws_secret_access_key'] ?? '';
        $bucket = $S3_settings['aws_bucket'] ?? '';
        $region = $S3_settings['aws_default_region'] ?? 'us-east-1';
        if (!$aws_key || !$aws_secret || !$bucket || !$region) {
            return [
                "error" => true,
                "message" => "AWS configuration missing. Please check configuration variables."
            ];
        }
        $config = [
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret,
            ],
        ];
        $s3 = new S3Client($config);
        $params = [
            'Bucket' => $bucket,
            'Delimiter' => '/',
        ];
        if ($folder_name) {
            // If a specific folder name is provided, set it as the Prefix
            $params['Prefix'] = rtrim($folder_name, '/') . '/';
        }
        $result = $s3->listObjectsV2($params);
        $folderInfo = [];
        if (isset($result['CommonPrefixes']) || $folder_name) {
            $folders = $folder_name ? [['Prefix' => $params['Prefix']]] : $result['CommonPrefixes'];
            foreach ($folders as $prefix) {
                $folderPath = $prefix['Prefix'];
                $folderName = rtrim(basename($folderPath), '/');
                // Count files in folder
                $fileParams = [
                    'Bucket' => $bucket,
                    'Prefix' => $folderPath,
                ];
                $fileResult = $s3->listObjectsV2($fileParams);
                if (isset($fileResult['Contents'])) {
                    if ($allowed_basenames !== null) {
                        $fileCount = 0;
                        foreach ($fileResult['Contents'] as $object) {
                            // Skip the folder placeholder key itself.
                            if ($object['Key'] === $folderPath) {
                                continue;
                            }
                            if (isset($allowed_basenames[basename($object['Key'])])) {
                                $fileCount++;
                            }
                        }
                    } else {
                        $fileCount = count($fileResult['Contents']);
                    }
                } else {
                    $fileCount = 0;
                }
                $folderInfo[] = [
                    'name' => $folderName,
                    'path' => $folderPath,
                    'fileCount' => $fileCount
                ];
            }
        }
        return [
            "error" => false,
            "data" => $folderInfo,
            "message" => "Folder information retrieved successfully"
        ];
    } catch (AwsException $e) {
        return [
            "error" => true,
            "message" => "AWS Error: " . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            "error" => true,
            "message" => "An error occurred: " . $e->getMessage()
        ];
    }
}

function get_provider_files_from_aws_s3_folder($segments)
{
    $S3_settings = get_settings('general_settings', true);
    $aws_key = $S3_settings['aws_access_key_id'] ?? '';
    $aws_secret = $S3_settings['aws_secret_access_key'] ?? '';
    $region = $S3_settings['aws_region'] ?? 'us-east-1';
    $aws_url = $S3_settings['aws_url'] ?? '';
    $bucket_name = $S3_settings['aws_bucket'] ?? '';
    // Validate AWS configuration
    if (!$aws_key || !$aws_secret || !$bucket_name || !$region) {
        return [
            "error" => true,
            "message" => "AWS configuration missing. Please check configuration variables."
        ];
    }
    $config = [
        'region' => $region,
        'version' => 'latest',
        'credentials' => [
            'key' => $aws_key,
            'secret' => $aws_secret,
        ],
    ];
    $s3 = new S3Client($config);
    $new_path = implode('/', array_slice($segments, array_search('get-gallery-files', $segments) + 1));
    $result = $s3->listObjects([
        'Bucket' => $bucket_name,
        'Prefix' => $new_path,
    ]);
    $files = array_map(function ($object) use ($bucket_name, $s3, $new_path, $aws_url) {
        $fileName = basename($object['Key']);
        return [
            'name' => $fileName,
            'type' => $s3->headObject([
                'Bucket' => $bucket_name,
                'Key' => $object['Key'],
            ])['ContentType'],
            'size' => $object['Size'],
            'full_path' => $aws_url . '/' . $object['Key'],
            // 'full_path' => $s3->getObjectUrl($bucket_name, $object['Key']),
            'path' => $object['Key'],
            'disk' => 'aws_s3'
        ];
    }, $result['Contents']);
    return $files;
}

function get_aws_s3_file($file_path)
{
    try {
        $S3_settings = get_settings('general_settings', true);
        $aws_key = $S3_settings['aws_access_key_id'] ?? '';
        $aws_secret = $S3_settings['aws_secret_access_key'] ?? '';
        $aws_url = $S3_settings['aws_url'] ?? '';
        $region = $S3_settings['aws_region'] ?? 'us-east-1';
        $bucket_name = $S3_settings['aws_bucket'] ?? '';
        // Validate AWS configuration
        if (!$aws_key || !$aws_secret || !$bucket_name || !$region) {
            return [
                "error" => true,
                "message" => "AWS configuration missing. Please check configuration variables."
            ];
        }
        $config = [
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret,
            ],
        ];
        $s3 = new S3Client($config);
        // Check if file exists
        if (!$s3->doesObjectExist($bucket_name, $file_path)) {
            return [
                "error" => true,
                "message" => "File does not exist in S3"
            ];
        }
        // Get file metadata
        $result = $s3->headObject([
            'Bucket' => $bucket_name,
            'Key' => $file_path
        ]);
        $fileName = basename($file_path);
        $fileData = [
            'name' => $fileName,
            'type' => getFileType($fileName),
            'size' => formatFileSize($result['ContentLength']),
            'full_path' => $aws_url . '/' . $file_path,
            'path' => $file_path,
            'lastModified' => $result['LastModified']->format('Y-m-d H:i:s'),
            'contentType' => $result['ContentType']
        ];
        return [
            "error" => false,
            "data" => $fileData,
            "message" => "File retrieved successfully"
        ];
    } catch (AwsException $e) {
        return [
            "error" => true,
            "message" => "AWS Error: " . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            "error" => true,
            "message" => "An error occurred: " . $e->getMessage()
        ];
    }
}

function get_aws_s3_folder_files($folder_path)
{
    try {
        $S3_settings = get_settings('general_settings', true);
        $aws_key = $S3_settings['aws_access_key_id'] ?? '';
        $aws_secret = $S3_settings['aws_secret_access_key'] ?? '';
        $aws_url = $S3_settings['aws_url'] ?? '';
        $region = $S3_settings['aws_region'] ?? 'us-east-1';
        $bucket_name = $S3_settings['aws_bucket'] ?? '';
        if (!$aws_key || !$aws_secret || !$bucket_name || !$region) {
            return [
                "error" => true,
                "message" => "AWS configuration missing. Please check configuration variables."
            ];
        }
        $config = [
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret,
            ],
        ];
        $s3 = new S3Client($config);
        // Ensure folder path ends with '/'
        $folder_path = rtrim($folder_path, '/') . '/';
        $params = [
            'Bucket' => $bucket_name,
            'Prefix' => $folder_path,
        ];
        $result = $s3->listObjectsV2($params);
        $files = [];
        if (isset($result['Contents'])) {
            foreach ($result['Contents'] as $file) {
                // Skip the folder itself
                if ($file['Key'] !== $folder_path) {
                    $fileName = basename($file['Key']);
                    $files[] = [
                        'name' => $fileName,
                        'type' => getFileType($fileName),
                        'size' => formatFileSize($file['Size']),
                        'full_path' => $aws_url . '/' . $file['Key'],
                        'path' => $file['Key'],
                        'lastModified' => $file['LastModified']->format('Y-m-d H:i:s')
                    ];
                }
            }
        }
        return [
            "error" => false,
            "data" => $files,
            "message" => "Files retrieved successfully"
        ];
    } catch (AwsException $e) {
        return [
            "error" => true,
            "message" => "AWS Error: " . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            "error" => true,
            "message" => "An error occurred: " . $e->getMessage()
        ];
    }
}

function download_aws_s3_file($file_path, $download_name = null)
{
    try {
        $S3_settings = get_settings('general_settings', true);
        $aws_key = $S3_settings['aws_access_key_id'] ?? '';
        $aws_secret = $S3_settings['aws_secret_access_key'] ?? '';
        $region = $S3_settings['aws_region'] ?? 'us-east-1';
        $bucket_name = $S3_settings['aws_bucket'] ?? '';
        if (!$aws_key || !$aws_secret || !$bucket_name || !$region) {
            return [
                "error" => true,
                "message" => "AWS configuration missing. Please check configuration variables."
            ];
        }
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret,
            ]
        ]);
        // Get file metadata
        $result = $s3->headObject([
            'Bucket' => $bucket_name,
            'Key' => $file_path
        ]);
        // Set download name if not provided
        if (!$download_name) {
            $download_name = basename($file_path);
        }
        // Get the file content type
        $contentType = $result['ContentType'];
        // Get the file
        $file = $s3->getObject([
            'Bucket' => $bucket_name,
            'Key' => $file_path
        ]);
        // Set headers for download
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . $file['ContentLength']);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        // Output the file content
        echo $file['Body'];
        exit;
    } catch (S3Exception $e) {
        return [
            "error" => true,
            "message" => "S3 Error: " . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            "error" => true,
            "message" => "An error occurred: " . $e->getMessage()
        ];
    }
}

function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getFileType($filename)
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $fileTypes = [
        // Images
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'svg' => 'image',
        'webp' => 'image',
        // Documents
        'pdf' => 'document',
        'doc' => 'document',
        'docx' => 'document',
        'xls' => 'document',
        'xlsx' => 'document',
        'ppt' => 'document',
        'pptx' => 'document',
        'txt' => 'document',
        // Audio
        'mp3' => 'audio',
        'wav' => 'audio',
        'ogg' => 'audio',
        // Video
        'mp4' => 'video',
        'avi' => 'video',
        'mov' => 'video',
        'wmv' => 'video',
        // Archives
        'zip' => 'archive',
        'rar' => 'archive',
        '7z' => 'archive',
        'tar' => 'archive',
        'gz' => 'archive'
    ];
    return $fileTypes[$extension] ?? 'other';
}

function upload_file($file, $upload_path, $error_message, $folder_name, $is_login_image = null)
{
    // Load CI4's Security helper for filename sanitization
    // This provides sanitize_filename() function to prevent malicious filenames
    helper('security');

    // Security: Remove null bytes from inputs (prevents null byte injection attacks)
    $upload_path = str_replace("\0", '', $upload_path ?? '');
    $folder_name = str_replace("\0", '', $folder_name ?? '');

    // Validate inputs are not empty
    if (empty(trim($upload_path))) {
        return ['error' => true, 'message' => "Upload path is required."];
    }

    // Security: Sanitize folder_name to prevent path traversal in S3 keys
    // Only allow alphanumeric characters, underscores, forward slashes, and hyphens
    $folder_name = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $folder_name);

    $settings = get_settings('general_settings', true);
    $file_manager = $settings['file_manager'];
    if ($file_manager == "aws_s3") {
        // Security: Validate file object
        if (!$file || !$file->isValid()) {
            return ['error' => true, 'message' => "Invalid file provided."];
        }

        // Get random filename from CI4's UploadedFile object (already sanitized)
        $file_name = $file->getRandomName();
        $file_tmp_name = $file->getTempName();

        // Security: Additional sanitization of filename for S3
        // Use basename() to ensure no directory components
        $file_name = basename($file_name);
        $sanitized_filename = sanitize_filename($file_name);

        // If sanitization changed the filename, reject it
        if ($file_name !== $sanitized_filename) {
            return ['error' => true, 'message' => "Invalid filename detected. File name contains dangerous characters."];
        }

        $file_name = $sanitized_filename;

        $result = upload_to_aws_s3($file_name, $file_tmp_name, $folder_name);
        if ($result['error']) {
            return ['error' => true, 'message' => "file not uploded"];
        } else {
            return ['error' => false, 'file_path' => $result, 'disk' => 'aws_s3', 'file_name' => $file_name];
        }
    } else if ($file_manager == "local_server") {
        // Security: Validate file object
        if (!$file || !$file->isValid()) {
            return ['error' => true, 'message' => "Invalid file provided."];
        }

        // Store original upload_path for special case comparison (before sanitization)
        $original_upload_path = $upload_path;

        // Security: Normalize upload_path - remove leading/trailing slashes and resolve relative to FCPATH
        $upload_path = trim($upload_path, '/');

        // Security: Remove any path traversal sequences from upload_path
        // Replace any '../' or './' sequences
        $upload_path = str_replace(['../', './'], '', $upload_path);

        // Security: Construct full path relative to FCPATH
        $full_upload_path = FCPATH . $upload_path;

        // Security: Use realpath() to resolve the path and detect path traversal attempts
        // First, ensure the directory exists or can be created
        if (!is_dir($full_upload_path)) {
            // Attempt to create directory
            if (!mkdir($full_upload_path, 0755, true)) {
                return ['error' => true, 'message' => $error_message];
            }
        }

        // Security: Resolve the path to detect any remaining path traversal
        $resolved_upload_path = realpath($full_upload_path);
        if ($resolved_upload_path === false) {
            return ['error' => true, 'message' => "Invalid upload path or path traversal attempt detected."];
        }

        // Security: Ensure the resolved path is within FCPATH
        // This is the critical check that prevents path traversal attacks
        $normalized_fcpath = rtrim(realpath(FCPATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($resolved_upload_path, $normalized_fcpath) !== 0) {
            return ['error' => true, 'message' => "Path traversal attempt detected. Upload path is outside allowed directory."];
        }

        if ($file->isValid() && !$file->hasMoved()) {
            // Determine filename based on special case or generate random name
            // Compare against normalized original path (handle both with and without trailing slash)
            $normalized_original = trim($original_upload_path, '/');
            if ($is_login_image == 'yes' && $normalized_original == "public/frontend/retro") {
                $file_name = "Login_BG.jpg";
            } else {
                // Get random filename from CI4's UploadedFile object (already sanitized)
                $file_name = $file->getRandomName();
            }

            // Security: Additional sanitization of filename
            // Use basename() to ensure no directory components
            $file_name = basename($file_name);
            $sanitized_filename = sanitize_filename($file_name);

            // If sanitization changed the filename, reject it
            if ($file_name !== $sanitized_filename) {
                return ['error' => true, 'message' => "Invalid filename detected. File name contains dangerous characters."];
            }

            $file_name = $sanitized_filename;

            if ($file->isValid() && !$file->hasMoved()) {
                $tempPath = $file->getTempName();

                // Security: Construct full path using resolved upload path
                // This ensures we're using the validated, resolved path
                $full_path = $resolved_upload_path . DIRECTORY_SEPARATOR . $file_name;

                // Security: Final validation - ensure the final path is still within FCPATH
                $final_resolved_path = realpath(dirname($full_path));
                if ($final_resolved_path === false || strpos($final_resolved_path, $normalized_fcpath) !== 0) {
                    return ['error' => true, 'message' => "Path traversal attempt detected in final file path."];
                }

                // All security checks passed - safe to process the file
                compressImage($tempPath, $full_path, 70);
                return ['error' => false, 'file_path' => $upload_path . '/' . $file_name, 'disk' => 'local_server', 'file_name' => $file_name];
            }
        }
    }
    return ['error' => true, 'message' => 'Failed to upload the file.'];
}

function get_top_rated_providers($latitude = null, $longitude = null)
{
    $db = \Config\Database::connect();
    $disk = fetch_current_file_manager();
    $rating_data = $db->table('partner_details pd')
        ->select('p.id, p.username, p.company, p.image, pd.banner, pd.company_name, pd.at_store, pd.at_doorstep,
                  COUNT(sr.rating) as number_of_rating, 
                  COALESCE(SUM(sr.rating), 0) as total_rating,
                  CASE 
                      WHEN COUNT(sr.rating) > 0 THEN SUM(sr.rating) / COUNT(sr.rating)
                      ELSE 0 
                  END as average_rating,
                  ps.status as subscription_status')
        ->join('users p', 'p.id = pd.partner_id')
        ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id')
        ->join('services s', 's.user_id = pd.partner_id', 'left')
        ->join('services_ratings sr', 'sr.service_id = s.id', 'left')
        ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
        ->join('partner_bids pb', 'pb.custom_job_request_id = cj.id', 'left')
        ->where('ps.status', 'active')
        ->where("(s.user_id = pd.partner_id) OR (pb.partner_id = pd.partner_id AND sr.custom_job_request_id IS NOT NULL)")
        ->groupBy('p.id')
        ->orderBy('average_rating', 'desc')
        ->get()
        ->getResultArray();
    // Filter out providers with exceeded order limits or inactive subscriptions
    foreach ($rating_data as $key => $row) {
        $partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $row['id'], 'status' => 'active']);
        if ($partner_subscription) {
            $subscription_purchase_date = $partner_subscription[0]['updated_at'];
            // Only progressed bookings consume the limit.
            $consumedOrders = count_orders_towards_subscription_limit($row['id'], $subscription_purchase_date, [], $db);
            $subscription_data = fetch_details('partner_subscriptions', ['partner_id' => $row['id'], 'status' => 'active']);
            $subscription_order_limit = $subscription_data[0]['max_order_limit'];
            if ($subscription_data[0]['order_type'] == 'limited' && $consumedOrders >= $subscription_order_limit) {
                unset($rating_data[$key]);
            }
        } else {
            unset($rating_data[$key]);
        }
    }
    $rating_data = array_values($rating_data);
    if (!empty($rating_data)) {
        foreach ($rating_data as &$provider) {
            if ($provider['image'] != null || $provider['banner'] != null) {
                if ($disk == 'local_server') {
                    $provider['image'] = get_image_url($provider['image']);
                } else if ($disk) {
                    $provider['image'] = fetch_cloud_front_url('profile', $provider['image']);
                } else {
                    $provider['image'] = get_image_url($provider['banner']);
                }
                if ($disk == 'local_server') {
                    $provider['banner_image'] = base_url($provider['banner']) ?? '';
                } else if ($disk) {
                    $provider['banner_image'] = fetch_cloud_front_url('banner', $provider['banner']);
                } else {
                    $provider['banner_image'] = base_url($provider['banner']) ?? '';
                }
            } else {
                $provider['image'] = '';
                $provider['banner_image'] = '';
            }
            unset($provider['minimum_order_amount'], $provider['banner']);
            $total_services_of_providers = fetch_details(
                'services',
                [
                    'user_id' => $provider['id'],
                    'at_store' => $provider['at_store'],
                    'at_doorstep' => $provider['at_doorstep']
                ],
                ['id']
            );
            $provider['total_services'] = count($total_services_of_providers);
            // Safely calculate average rating
            $provider['average_rating'] = $provider['number_of_rating'] > 0
                ? ($provider['total_rating'] / $provider['number_of_rating'])
                : 0;
            $provider_services = fetch_details(
                'services',
                ['user_id' => $provider['id']],
                ['title']
            );
            $provider['services'] = $provider_services;
        }
    }
    return $rating_data;
}

function get_image_url($image_path)
{
    if (file_exists(FCPATH . 'public/uploads/profiles/' . $image_path)) {
        return base_url('public/uploads/profiles/' . $image_path);
    }
    if (file_exists(FCPATH . $image_path)) {
        return base_url($image_path);
    }
    if (file_exists(FCPATH . "public/uploads/users/partners/" . $image_path)) {
        return base_url("public/uploads/users/partners/" . $image_path);
    }
    return base_url("public/uploads/profiles/default.png");
}

if (!function_exists('extract_language_specific_post_value')) {
    /**
     * Safely extract translated form data regardless of format (new array or legacy field[code]).
     */
    function extract_language_specific_post_value(array $postData, string $field, string $languageCode): string
    {
        if (isset($postData[$field]) && is_array($postData[$field]) && isset($postData[$field][$languageCode])) {
            return trim((string) $postData[$field][$languageCode]);
        }

        $legacyKey = $field . '[' . $languageCode . ']';
        if (isset($postData[$legacyKey])) {
            return trim((string) $postData[$legacyKey]);
        }

        return '';
    }
}

function delete_file_based_on_server($folder_name, $file_name, $disk)
{
    // Load CI4's Security helper for filename sanitization
    // This provides sanitize_filename() function to prevent malicious filenames
    helper('security');

    // Security: Remove null bytes from inputs (prevents null byte injection attacks)
    $folder_name = str_replace("\0", '', $folder_name ?? '');
    $file_name = str_replace("\0", '', $file_name ?? '');

    // Validate inputs are not empty
    if (empty(trim($folder_name)) || empty(trim($file_name))) {
        return ['error' => true, 'message' => "Folder name and file name are required."];
    }

    // Security: Sanitize folder name to prevent path traversal
    // Only allow alphanumeric characters, underscores, and forward slashes for folder names
    $folder_name = preg_replace('/[^a-zA-Z0-9_\/]/', '', $folder_name);

    // Security: Use basename() to extract only the filename part (prevents directory traversal in filename)
    // This ensures that even if $file_name contains "../", it will be stripped
    $file_name = basename($file_name);

    // Security: Sanitize the filename using CI4's sanitize_filename() helper
    // This removes dangerous characters and prevents malicious filenames
    $sanitized_filename = sanitize_filename($file_name);

    // If sanitization changed the filename, it contained dangerous characters - reject it
    if ($file_name !== $sanitized_filename) {
        return ['error' => true, 'message' => "Invalid filename detected. File name contains dangerous characters."];
    }

    // Use the sanitized filename
    $file_name = $sanitized_filename;

    $settings = get_settings('general_settings', true);
    if ($disk == "aws_s3") {
        $aws_key = $settings['aws_access_key_id'] ?? '';
        $aws_secret = $settings['aws_secret_access_key'] ?? '';
        $bucket = $settings['aws_bucket'] ?? '';
        $region = $settings['aws_region'] ?? 'us-east-1';
        if (!$aws_key || !$aws_secret || !$bucket || !$region) {
            return ['error' => true, 'message' => "AWS configuration missing. Please check configuration variables."];
        }
        $config = [
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret,
            ],
        ];
        $s3 = new S3Client($config);
        // Security: Sanitize folder name for S3 key (remove any remaining dangerous characters)
        $sanitized_folder = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $folder_name);
        // Construct the file key using sanitized components
        $file_key = $sanitized_folder . '/' . $file_name;
        try {
            $s3->deleteObject(['Bucket' => $bucket, 'Key' => $file_key]);
            return ['error' => false, 'message' => "File deleted successfully from S3."];
        } catch (Aws\S3\Exception\S3Exception $e) {
            return ['error' => false, 'message' => "Failed to delete file from S3: " . $e->getMessage()];
        }
    } else {
        // Security: Whitelist approach - only allow specific folder names
        // This prevents path traversal by restricting folder_name to known safe values
        $allowed_folders = [
            "categories" => "public/uploads/categories/",
            "site" => "public/uploads/site/",
            "profile" => "public/uploads/profile/",
            "profiles" => "public/uploads/profiles/",
            "banner" => "public/uploads/banner/",
            "custom_fields" => "public/uploads/custom_fields/",
            "partner" => "public/uploads/partner/",
            "sliders" => "public/uploads/sliders/",
            "services" => "public/uploads/services/",
            "feature_section" => "public/uploads/feature_section/",
            "ratings" => "public/uploads/ratings/",
            "promocodes" => "public/uploads/promocodes/",
            "become_provider" => "public/uploads/become_provider/",
            "provider_work_evidence" => "public/uploads/provider_work_evidence/",
            "seo_settings" => "public/uploads/seo_settings/general_seo_settings/",
            "service_seo_settings" => "public/uploads/seo_settings/service_seo_settings/",
            "category_seo_settings" => "public/uploads/seo_settings/category_seo_settings/",
            "provider_seo_settings" => "public/uploads/seo_settings/provider_seo_settings/",
            "blog_seo_settings" => "public/uploads/seo_settings/blog_seo_settings/",
            "blogs" => "public/uploads/blogs/",
            "blogs/images" => "public/uploads/blogs/images/",
        ];

        // Security: Validate folder_name against whitelist
        if (!isset($allowed_folders[$folder_name])) {
            return ['error' => true, 'message' => "Invalid folder name specified."];
        }

        // Get the allowed path from whitelist
        $path = $allowed_folders[$folder_name];

        if (!empty($file_name)) {
            // Construct the full file path
            $file_path = FCPATH . $path . basename($file_name);

            // Security: Use realpath() to resolve the path and detect path traversal attempts
            // realpath() resolves symlinks, relative paths, and returns false if path traversal is detected
            $resolved_path = realpath($file_path);

            // If realpath() returns false, the path is invalid or contains path traversal
            if ($resolved_path === false) {
                return [
                    "error" => true,
                    "message" => "Invalid file path or path traversal attempt detected."
                ];
            }

            // Security: Ensure the resolved path is within the allowed base directory
            // Get the normalized allowed base directory
            $allowed_base_dir = realpath(FCPATH . $path);
            if ($allowed_base_dir === false) {
                return [
                    "error" => true,
                    "message" => "Allowed directory does not exist."
                ];
            }

            // Normalize the allowed directory path (ensure it ends with directory separator)
            $allowed_base_dir = rtrim($allowed_base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            // Security: Verify the resolved path is within the allowed directory
            // This is the critical check that prevents path traversal attacks
            if (strpos($resolved_path, $allowed_base_dir) !== 0) {
                // Path traversal attempt detected - the resolved path is outside the allowed directory
                return [
                    "error" => true,
                    "message" => "Path traversal attempt detected. File path is outside allowed directory."
                ];
            }

            // Security: Ensure it's a file, not a directory
            if (!is_file($resolved_path)) {
                return [
                    "error" => true,
                    "message" => "Path does not point to a valid file."
                ];
            }

            // All security checks passed - safe to delete the file
            if (unlink($resolved_path)) {
                return [
                    "error" => false,
                    "message" => "File deleted successfully from the local server"
                ];
            } else {
                return [
                    "error" => true,
                    "message" => "Failed to delete file from the local server"
                ];
            }
        } else {
            return [
                "error" => true,
                "message" => "File name is required."
            ];
        }
    }
}

function fetch_cloud_front_url($folder_name, $file_name)
{
    $settings = get_settings('general_settings', true);
    $aws_url = rtrim($settings['aws_url'] ?? '', '/');

    if (empty($aws_url) || empty($file_name)) {
        return $file_name ?? '';
    }

    $folder_name = trim($folder_name ?? '', '/');
    $file_name = ltrim($file_name ?? '', '/');

    $folder_config = get_s3_folder_config();

    foreach ($folder_config as $folder => $behavior) {
        if (str_starts_with($file_name, $folder . '/')) {
            if ($behavior === 'preserve') {
                $file_name = preg_replace('#^' . preg_quote($folder) . '/#', '', $file_name);
            } else {
                $file_name = basename($file_name); // Simple, fast cut
            }
            break;
        }
    }

    return $aws_url . '/' . $folder_name . '/' . $file_name;
}

function get_file_url($disk, $file_key, $default_path = 'public/backend/assets/default.png', $cloud_front_type = '')
{
    // If it's already a full URL, just return it (no further processing needed)
    if (is_full_url($file_key)) {
        return $file_key;
    }

    if (empty($file_key)) {
        return base_url($default_path);
    }

    switch ($disk) {
        case 'local_server':
            $local_path = FCPATH . $file_key;
            return is_file($local_path)
                ? base_url($file_key)
                : base_url($default_path);

        case 'aws_s3':
            $url = fetch_cloud_front_url($cloud_front_type, $file_key);
            return remote_file_exists($url)
                ? $url
                : base_url($default_path);

        default:
            $fallback_path = FCPATH . $file_key;
            return is_file($fallback_path)
                ? base_url($file_key)
                : base_url($default_path);
    }
}

function is_full_url($path)
{
    return is_string($path) && preg_match('#^https?://#i', $path);
}

function remote_file_exists($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    return $http_code === 200;
}

function get_s3_folder_config()
{
    return [
        'seo_settings' => 'preserve',
        'user_documents' => 'preserve',
        'category_images' => 'preserve',

        'public' => 'remove',
        'uploads' => 'remove',
        'backend' => 'remove',
        'assets' => 'remove',
        'services' => 'remove',
        'chat_attachment' => 'remove',
        'provider_work_evidence' => 'remove',
        'custom_fields' => 'remove',
        'banner' => 'remove',
        'promocodes' => 'remove',
        'ratings' => 'remove',
        'profile' => 'remove',
        'profiles' => 'remove'
    ];
}

function fetch_current_file_manager()
{
    $setting = get_settings('storage_disk');
    if (empty($setting) || !isset($setting)) {
        $setting = 'local_server';
    }
    return $setting;
}

function fetch_partner_formatted_data($user_id)
{
    $userdata = fetch_details('users', ['id' => $user_id], [
        'id',
        'username',
        'email',
        'balance',
        'active',
        'first_name',
        'last_name',
        'company',
        'phone',
        'country_code',
        'fcm_id',
        'image',
        'city_id',
        'city',
        'latitude',
        'longitude',
        'loginType as login_type'
    ])[0];
    $partnerData = fetch_details('partner_details', ['partner_id' => $user_id]);
    $partnerData = !empty($partnerData) ? $partnerData[0] : [];
    $subscription = fetch_details('partner_subscriptions', ['partner_id' => $user_id], [], 1, 0, 'id', 'DESC');
    $fileService = service('fileService');

    $partnerCustomFieldValues = [];
    try {
        $cfDb = \Config\Database::connect();
        if ($cfDb->tableExists('partner_custom_fields') && $cfDb->tableExists('custom_fields')) {
            $cfRows = $cfDb->table('partner_custom_fields')
                ->select('partner_custom_fields.custom_field_id, partner_custom_fields.value')
                ->where('partner_custom_fields.partner_id', $user_id)
                ->get()
                ->getResultArray();
            foreach ($cfRows as $row) {
                $partnerCustomFieldValues[(int) $row['custom_field_id']] = $row['value'];
            }
        }
    } catch (\Exception $e) {
        log_message('error', 'fetch_partner_formatted_data: failed to load partner_custom_fields for partner ' . $user_id . ': ' . $e->getMessage());
    }

    $imageFields = [
        'userdata' => [
            'image' => 'profile'
        ],
        'partnerData' => [
            'banner' => 'banner',
        ]
    ];

    $docFieldIds = [];
    if ($cfDb->tableExists('custom_fields')) {
        $docRows = $cfDb->table('custom_fields')
            ->select('id')
            ->where('field_group', 'documents')
            ->where('field_type', 'file')
            ->get()
            ->getResultArray();
        $docFieldIds = array_column($docRows, 'id');
    }

    foreach ($docFieldIds as $docId) {
        $docId = (int) $docId;
        $rawValue = $partnerCustomFieldValues[$docId] ?? '';
        $partnerCustomFieldValues[$docId] = (!empty($rawValue) && $fileService->exists('custom_fields', $rawValue))
            ? $fileService->url($rawValue, 'custom_fields')
            : '';
    }

    foreach ($imageFields as $varName => $fields) {
        foreach ($fields as $key => $type) {
            $value = ${$varName}[$key] ?? '';

            ${$varName}[$key] = (!empty($value) && $fileService->exists($type, $value))
                ? $fileService->url($value, $type)
                : ($type === 'address_id' ? null : '');
        }
    }

    $get_settings = get_settings('general_settings', true);
    $partnerData['pre_booking_chat'] = ($get_settings['allow_pre_booking_chat'] == 1) ? ($partnerData['pre_chat'] ?? "") : 0;
    $partnerData['post_booking_chat'] = ($get_settings['allow_post_booking_chat'] == 1) ? ($partnerData['chat'] ?? "") : 0;
    if (!empty($partnerData['other_images'])) {
        if (isset($partnerData['other_images'])) {
            $decodedImages = json_decode($partnerData['other_images'], true);
            $otherImages = [];
            if (is_array($decodedImages)) {
                foreach ($decodedImages as $image) {
                    $otherImages[] = $fileService->url($image, 'partner');
                }
            }
            $partnerData['other_images'] = $otherImages;
        }
    } else {
        $partnerData['other_images'] = [];
    }
    if (!empty($partnerData['custom_job_categories'])) {
        $partnerData['custom_job_categories'] =
            json_decode($partnerData['custom_job_categories'], true);
    } else {
        $partnerData['custom_job_categories'] = [];
    }


    $location_information = [
        'city' => $userdata['city'],
        'latitude' => $userdata['latitude'],
        'longitude' => $userdata['longitude'],
        'address' => $partnerData['address'] ?? ""
    ];
    $bank_information = [
        'tax_name' => $partnerCustomFieldValues['tax_name'] ?? '',
        'tax_number' => $partnerCustomFieldValues['tax_number'] ?? "",
        'account_number' => $partnerCustomFieldValues['account_number'] ?? "",
        'account_name' => $partnerCustomFieldValues['account_name'] ?? "",
        'bank_code' => $partnerCustomFieldValues['bank_code'] ?? "",
        'swift_code' => $partnerCustomFieldValues['swift_code'] ?? "",
        'bank_name' => $partnerCustomFieldValues['bank_name'] ?? "",
    ];
    // Get subscription translations if subscription exists
    $subscriptionTranslations = [];
    if (!empty($subscription[0]['subscription_id'])) {
        try {
            $subscriptionTranslationModel = new \App\Models\TranslatedSubscriptionModel();
            $currentLanguage = get_current_language_from_request();
            $defaultLanguage = get_default_language();

            // Get current language translation
            $currentTranslation = $subscriptionTranslationModel->getTranslation($subscription[0]['subscription_id'], $currentLanguage);

            // Get default language translation
            $defaultTranslation = $subscriptionTranslationModel->getTranslation($subscription[0]['subscription_id'], $defaultLanguage);

            // Set subscription translations
            if ($currentTranslation && $currentLanguage !== $defaultLanguage) {
                $subscriptionTranslations['translated_name'] = $currentTranslation['name'];
                $subscriptionTranslations['translated_description'] = $currentTranslation['description'];
            } else {
                // Fallback to default language or main table
                $subscriptionTranslations['translated_name'] = $defaultTranslation['name'] ?? $subscription[0]['name'] ?? '';
                $subscriptionTranslations['translated_description'] = $defaultTranslation['description'] ?? $subscription[0]['description'] ?? '';
            }
        } catch (\Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Error getting subscription translations: ' . $e->getMessage());

            // Fallback to main table data
            $subscriptionTranslations['translated_name'] = $subscription[0]['name'] ?? '';
            $subscriptionTranslations['translated_description'] = $subscription[0]['description'] ?? '';
        }
    } else {
        // No subscription, set empty translated fields
        $subscriptionTranslations['translated_name'] = '';
        $subscriptionTranslations['translated_description'] = '';
    }

    $subscription_information = [
        'subscription_id' => $subscription[0]['subscription_id'] ?? "",
        'isSubscriptionActive' => $subscription[0]['status'] ?? "deactive",
        'created_at' => $subscription[0]['created_at'] ?? "",
        'updated_at' => $subscription[0]['updated_at'] ?? "",
        'is_payment' => $subscription[0]['is_payment'] ?? "",
        'id' => $subscription[0]['id'] ?? "",
        'partner_id' => $subscription[0]['partner_id'] ?? "",
        'purchase_date' => $subscription[0]['purchase_date'] ?? "",
        'expiry_date' => $subscription[0]['expiry_date'] ?? "",
        'name' => $subscription[0]['name'] ?? "",
        'description' => $subscription[0]['description'] ?? "",
        'duration' => $subscription[0]['duration'] ?? "",
        'price' => $subscription[0]['price'] ?? "",
        'discount_price' => $subscription[0]['discount_price'] ?? "",
        'order_type' => $subscription[0]['order_type'] ?? "",
        'max_order_limit' => $subscription[0]['max_order_limit'] ?? "",
        'is_commision' => $subscription[0]['is_commision'] ?? "",
        'commission_threshold' => $subscription[0]['commission_threshold'] ?? "",
        'commission_percentage' => $subscription[0]['commission_percentage'] ?? "",
        'publish' => $subscription[0]['publish'] ?? "",
        'tax_id' => $subscription[0]['tax_id'] ?? "",
        'tax_type' => $subscription[0]['tax_type'] ?? "",
        // Add translated subscription fields
        'translated_name' => $subscriptionTranslations['translated_name'],
        'translated_description' => $subscriptionTranslations['translated_description']
    ];
    if (!empty($subscription[0])) {
        $price = calculate_partner_subscription_price($subscription[0]['partner_id'], $subscription[0]['subscription_id'], $subscription[0]['id']);
    }
    $subscription_information['tax_value'] = $price[0]['tax_value'] ?? "";
    $subscription_information['price_with_tax'] = $price[0]['price_with_tax'] ?? "";
    $subscription_information['original_price_with_tax'] = $price[0]['original_price_with_tax'] ?? "";
    $subscription_information['tax_percentage'] = $price[0]['tax_percentage'] ?? "";

    // Use the new SEO model for formatted data
    $seoModel = new \App\Models\Seo_model();
    $seoModel->setTableContext('providers');

    $seoData = $seoModel->getSeoSettingsByReferenceId($user_id, 'meta');

    $formatted_seo_settings = [];
    if ($seoData) {
        $formatted_seo_settings['seo_title'] = $seoData['title'];
        $formatted_seo_settings['seo_description'] = $seoData['description'];
        $formatted_seo_settings['seo_keywords'] = $seoData['keywords'];
        $formatted_seo_settings['seo_og_image'] = $seoData['image']; // Already formatted with proper URL
        $formatted_seo_settings['seo_schema_markup'] = $seoData['schema_markup'] ?? '';
    } else {
        $formatted_seo_settings['seo_title'] = "";
        $formatted_seo_settings['seo_description'] = "";
        $formatted_seo_settings['seo_keywords'] = "";
        $formatted_seo_settings['seo_og_image'] = "";
        $formatted_seo_settings['seo_schema_markup'] = "";
    }

    // Process translations for partner data
    $translatedData = [];
    try {
        // Initialize translation model
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();

        // Get all available translations for this partner
        $allTranslations = $translationModel->getAllTranslationsForPartner($user_id);

        // Get all available languages from the database dynamically
        // This ensures the structure supports all languages configured in the system
        $languageModel = new \App\Models\Language_model();
        $availableLanguages = $languageModel->select('code')->findAll();

        // Initialize translated_fields structure dynamically with all available languages
        // This replaces the hardcoded 'en' and 'hi' structure to support any number of languages
        $translatableFields = ['username', 'company_name', 'about', 'long_description'];
        $translatedData['translated_fields'] = [];

        foreach ($translatableFields as $field) {
            $translatedData['translated_fields'][$field] = [];
            foreach ($availableLanguages as $language) {
                $translatedData['translated_fields'][$field][$language['code']] = '';
            }
        }

        // Process each translation record
        foreach ($allTranslations as $translation) {
            $languageCode = $translation['language_code'];

            // Map the translatable fields
            if (isset($translation['username'])) {
                $translatedData['translated_fields']['username'][$languageCode] = $translation['username'];
            }
            if (isset($translation['company_name'])) {
                $translatedData['translated_fields']['company_name'][$languageCode] = $translation['company_name'];
            }
            if (isset($translation['about'])) {
                $translatedData['translated_fields']['about'][$languageCode] = $translation['about'];
            }
            if (isset($translation['long_description'])) {
                $translatedData['translated_fields']['long_description'][$languageCode] = $translation['long_description'];
            }
        }

        // Get default language for fallback mechanism
        $defaultLanguage = get_default_language();

        // Fill in fallback values from main table for default language if empty
        // This ensures default language always has a value from base table if translation is missing
        if (empty($translatedData['translated_fields']['username'][$defaultLanguage])) {
            $translatedData['translated_fields']['username'][$defaultLanguage] = $userdata['username'] ?? '';
        }
        if (empty($translatedData['translated_fields']['company_name'][$defaultLanguage])) {
            $translatedData['translated_fields']['company_name'][$defaultLanguage] = $partnerData['company_name'] ?? '';
        }
        if (empty($translatedData['translated_fields']['about'][$defaultLanguage])) {
            $translatedData['translated_fields']['about'][$defaultLanguage] = $partnerData['about'] ?? '';
        }
        if (empty($translatedData['translated_fields']['long_description'][$defaultLanguage])) {
            $translatedData['translated_fields']['long_description'][$defaultLanguage] = $partnerData['long_description'] ?? '';
        }

        // Apply fallback mechanism: if any language has empty value, use default language value
        // If default language is also empty, use base table data
        foreach ($translatableFields as $field) {
            // Get the default language value (which should now be populated from base table if needed)
            $defaultValue = $translatedData['translated_fields'][$field][$defaultLanguage] ?? '';

            // If default value is still empty, get from base table
            if (empty($defaultValue)) {
                if ($field === 'username') {
                    $defaultValue = $userdata['username'] ?? '';
                } else {
                    $defaultValue = $partnerData[$field] ?? '';
                }
                // Update default language with base table value
                $translatedData['translated_fields'][$field][$defaultLanguage] = $defaultValue;
            }

            // For each language, if value is empty, use default language value
            foreach ($availableLanguages as $language) {
                $langCode = $language['code'];
                if (empty($translatedData['translated_fields'][$field][$langCode])) {
                    $translatedData['translated_fields'][$field][$langCode] = $defaultValue;
                }
            }
        }

        // Fetch and add SEO translations to translated_fields (similar to services API)
        // This ensures SEO settings are returned in the same format as other multilanguage fields
        try {
            // Load SEO translations model for partners
            $seoTransModel = new \App\Models\TranslatedPartnerSeoSettings_model();
            $seoTranslations = $seoTransModel->getAllTranslationsForPartner($user_id);

            // Initialize SEO fields in translated_fields structure
            $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];
            foreach ($seoFields as $seoField) {
                if (!isset($translatedData['translated_fields'][$seoField])) {
                    $translatedData['translated_fields'][$seoField] = [];
                }
                // Initialize with all available languages
                foreach ($availableLanguages as $language) {
                    if (!isset($translatedData['translated_fields'][$seoField][$language['code']])) {
                        $translatedData['translated_fields'][$seoField][$language['code']] = '';
                    }
                }
            }

            // Build per-language maps from SEO translations
            $tfSeoTitle = [];
            $tfSeoDesc = [];
            $tfSeoKeywords = [];
            $tfSeoSchema = [];

            foreach ($seoTranslations as $trow) {
                $langCode = $trow['language_code'] ?? '';
                if ($langCode === '') {
                    continue;
                }

                // Map SEO translation fields to per-language arrays
                if (isset($trow['seo_title']) && $trow['seo_title'] !== '') {
                    $tfSeoTitle[$langCode] = $trow['seo_title'];
                }
                if (isset($trow['seo_description']) && $trow['seo_description'] !== '') {
                    $tfSeoDesc[$langCode] = $trow['seo_description'];
                }
                if (isset($trow['seo_keywords']) && $trow['seo_keywords'] !== '') {
                    $tfSeoKeywords[$langCode] = $trow['seo_keywords'];
                }
                if (isset($trow['seo_schema_markup']) && $trow['seo_schema_markup'] !== '') {
                    $tfSeoSchema[$langCode] = $trow['seo_schema_markup'];
                }
            }

            // Add fallback values from main SEO settings table for default language
            // This ensures that if translations don't exist, we fall back to main table values
            $defaultLanguage = get_default_language();
            if (!empty($formatted_seo_settings)) {
                // Fallback to main SEO settings for default language if translation doesn't exist
                if (!isset($tfSeoTitle[$defaultLanguage]) && !empty($formatted_seo_settings['seo_title'])) {
                    $tfSeoTitle[$defaultLanguage] = $formatted_seo_settings['seo_title'];
                }
                if (!isset($tfSeoDesc[$defaultLanguage]) && !empty($formatted_seo_settings['seo_description'])) {
                    $tfSeoDesc[$defaultLanguage] = $formatted_seo_settings['seo_description'];
                }
                if (!isset($tfSeoKeywords[$defaultLanguage]) && !empty($formatted_seo_settings['seo_keywords'])) {
                    $tfSeoKeywords[$defaultLanguage] = $formatted_seo_settings['seo_keywords'];
                }
                if (!isset($tfSeoSchema[$defaultLanguage]) && !empty($formatted_seo_settings['seo_schema_markup'])) {
                    $tfSeoSchema[$defaultLanguage] = $formatted_seo_settings['seo_schema_markup'];
                }
            }

            // Assign SEO translations into translated_fields (overwrite empty values with actual translations)
            // Use array_merge to ensure translations overwrite empty initialized values
            $translatedData['translated_fields']['seo_title'] = array_merge(
                $translatedData['translated_fields']['seo_title'] ?? [],
                $tfSeoTitle
            );
            $translatedData['translated_fields']['seo_description'] = array_merge(
                $translatedData['translated_fields']['seo_description'] ?? [],
                $tfSeoDesc
            );
            $translatedData['translated_fields']['seo_keywords'] = array_merge(
                $translatedData['translated_fields']['seo_keywords'] ?? [],
                $tfSeoKeywords
            );
            $translatedData['translated_fields']['seo_schema_markup'] = array_merge(
                $translatedData['translated_fields']['seo_schema_markup'] ?? [],
                $tfSeoSchema
            );

            // Apply fallback mechanism for SEO fields: if any language has empty value, use default language value
            // If default language is also empty, use base table data from formatted_seo_settings
            $seoFieldMap = [
                'seo_title' => 'seo_title',
                'seo_description' => 'seo_description',
                'seo_keywords' => 'seo_keywords',
                'seo_schema_markup' => 'seo_schema_markup'
            ];

            foreach ($seoFieldMap as $seoField => $seoSettingsKey) {
                // Get the default language value
                $defaultSeoValue = $translatedData['translated_fields'][$seoField][$defaultLanguage] ?? '';

                // If default value is still empty, get from base table (formatted_seo_settings)
                if (empty($defaultSeoValue) && !empty($formatted_seo_settings[$seoSettingsKey])) {
                    $defaultSeoValue = $formatted_seo_settings[$seoSettingsKey];
                    // Update default language with base table value
                    $translatedData['translated_fields'][$seoField][$defaultLanguage] = $defaultSeoValue;
                }

                // For each language, if value is empty, use default language value
                foreach ($availableLanguages as $language) {
                    $langCode = $language['code'];
                    if (empty($translatedData['translated_fields'][$seoField][$langCode])) {
                        $translatedData['translated_fields'][$seoField][$langCode] = $defaultSeoValue;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the function if SEO translation processing fails
            log_message('error', 'Failed to assemble multilingual SEO for partner ' . $user_id . ' in fetch_partner_formatted_data: ' . $e->getMessage());

            // Initialize SEO fields with fallback to base table data if translation processing fails
            $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];
            if (!isset($availableLanguages)) {
                $languageModel = new \App\Models\Language_model();
                $availableLanguages = $languageModel->select('code')->findAll();
            }
            $defaultLanguage = get_default_language();

            foreach ($seoFields as $seoField) {
                if (!isset($translatedData['translated_fields'][$seoField])) {
                    $translatedData['translated_fields'][$seoField] = [];
                }

                // Get base table value for this SEO field
                $baseSeoValue = '';
                if (!empty($formatted_seo_settings[$seoField])) {
                    $baseSeoValue = $formatted_seo_settings[$seoField];
                }

                // Set default language value from base table
                $translatedData['translated_fields'][$seoField][$defaultLanguage] = $baseSeoValue;

                // For all other languages, use default language value as fallback
                foreach ($availableLanguages as $language) {
                    $langCode = $language['code'];
                    if ($langCode !== $defaultLanguage) {
                        $translatedData['translated_fields'][$seoField][$langCode] = $baseSeoValue;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Translation processing failed in fetch_partner_formatted_data: ' . $e->getMessage());

        // Set default structure even if translation fails - use dynamic language structure
        $languageModel = new \App\Models\Language_model();
        $availableLanguages = $languageModel->select('code')->findAll();

        $translatableFields = ['username', 'company_name', 'about', 'long_description'];
        $translatedData['translated_fields'] = [];

        // Get default language for fallback mechanism
        $defaultLanguage = get_default_language();

        foreach ($translatableFields as $field) {
            $translatedData['translated_fields'][$field] = [];

            // Get base table value for this field
            $baseTableValue = '';
            if ($field === 'username') {
                $baseTableValue = $userdata['username'] ?? '';
            } else {
                $baseTableValue = $partnerData[$field] ?? '';
            }

            // Set default language value from base table
            $translatedData['translated_fields'][$field][$defaultLanguage] = $baseTableValue;

            // For all other languages, use default language value as fallback
            foreach ($availableLanguages as $language) {
                $langCode = $language['code'];
                if ($langCode !== $defaultLanguage) {
                    $translatedData['translated_fields'][$field][$langCode] = $baseTableValue;
                }
            }
        }

        // Also initialize SEO fields in translated_fields even if translation processing fails
        // This ensures the structure is consistent even when errors occur
        // Apply fallback mechanism: all languages use default language value, which comes from base table
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];
        foreach ($seoFields as $seoField) {
            $translatedData['translated_fields'][$seoField] = [];

            // Get base table value for this SEO field
            $baseSeoValue = '';
            if (!empty($formatted_seo_settings[$seoField])) {
                $baseSeoValue = $formatted_seo_settings[$seoField];
            }

            // Set default language value from base table
            $translatedData['translated_fields'][$seoField][$defaultLanguage] = $baseSeoValue;

            // For all other languages, use default language value as fallback
            foreach ($availableLanguages as $language) {
                $langCode = $language['code'];
                if ($langCode !== $defaultLanguage) {
                    $translatedData['translated_fields'][$seoField][$langCode] = $baseSeoValue;
                }
            }
        }
    }


    $data1 = [
        'subscription_information' => $subscription_information,
        'location_information' => $location_information,
        'user' => array_diff_key($userdata, array_flip(['city', 'latitude', 'longitude'])),
        'provder_information' => array_merge(
            array_diff_key($partnerData, array_flip([
                'tax_name',
                'tax_number',
                'account_number',
                'account_name',
                'bank_code',
                'swift_code',
                'bank_name',
                'address',
                'chat',
                'pre_chat',
                'passport',
                'national_id',
                'address_id',
            ])),
            [
                'passport' => $partnerCustomFieldValues['passport'] ?? '',
                'national_id' => $partnerCustomFieldValues['national_id'] ?? '',
                'address_id' => $partnerCustomFieldValues['address_id'] ?? null,
            ],
            $formatted_seo_settings,
            $translatedData // Add translated data to the response
        ),
        'bank_information' => $bank_information,
        'working_days' => array_map(function ($val) {
            return [
                'day' => $val['day'],
                'isOpen' => $val['is_open'],
                'start_time' => $val['opening_time'],
                'end_time' => $val['closing_time']
            ];
        }, fetch_details('provider_shifts', ['partner_id' => $userdata['id'], 'shift_number' => 1])),
    ];

    return $data1;
}

function get_cart_formatted_data($user_id, $search, $limit, $offset, $sort, $order, $where, $message, $error)
{
    $cart_details = fetch_cart(true, $user_id, $search, $limit, $offset, $sort, $order, $where);
    if (!empty($cart_details['data'])) {
        // Get company name with proper fallback logic
        // company_name should contain default language data
        // translated_company_name should contain requested language data (from header)
        $baseCompanyName = $cart_details['company_name'] ?? '';
        $providerId = $cart_details['provider_id'] ?? '';

        // Extract first provider ID if multiple (comma-separated)
        $firstProviderId = !empty($providerId) ? (int) explode(',', $providerId)[0] : 0;

        // Get company name with default language fallback
        $companyName = '';
        if (!empty($firstProviderId) && !empty($baseCompanyName)) {
            $companyName = get_company_name_with_default_language_fallback($firstProviderId, $baseCompanyName);
        } else {
            $companyName = $baseCompanyName;
        }

        // Get translated company name with requested language fallback
        $translatedCompanyName = '';
        if (!empty($firstProviderId) && !empty($baseCompanyName)) {
            $translatedCompanyName = get_translated_company_name_with_fallback($firstProviderId, $baseCompanyName);
        } else {
            $translatedCompanyName = $baseCompanyName;
        }

        return response_helper(
            $message,
            $error,
            remove_null_values($cart_details['data']),
            200,
            remove_null_values(
                [
                    'provider_id' => $cart_details['provider_id'],
                    'provider_names' => $cart_details['provider_names'],
                    'service_ids' => $cart_details['service_ids'],
                    'qtys' => $cart_details['qtys'],
                    'visiting_charges' => $cart_details['visiting_charges'],
                    'advance_booking_days' => $cart_details['advance_booking_days'],
                    'company_name' => $companyName,
                    'translated_company_name' => $translatedCompanyName,
                    'total_duration' => $cart_details['total_duration'],
                    'is_pay_later_allowed' => $cart_details['is_pay_later_allowed'],
                    'total_quantity' => $cart_details['total_quantity'],
                    'sub_total' => $cart_details['sub_total'],
                    'overall_amount' => $cart_details['overall_amount'],
                    'total' => $cart_details['total'],
                    "at_store" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['at_store'] : "0",
                    "at_doorstep" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['at_doorstep'] : "0",
                    "is_online_payment_allowed" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['is_online_payment_allowed'] : "0",
                    "tax_value" => (!empty($cart_details) && isset($cart_details)) ? $cart_details['tax_value'] : "",
                    "sub_total_without_tax" => (!empty($cart_details)) ? $cart_details['sub_total_without_tax'] : "",
                ]
            )
        );
    } else {
        return response_helper('service not found');
    }
}

function store_notifications($title, $message, $type, $user_id, $is_readed, $notification_type, $date_sent, $target, $image = null, $order_id = null, $type_id = null, $order_status = null, $custom_job_request_id = null, $bidder_id = null, $bid_status = null)
{
    $data['title'] = $title;
    $data['message'] = $message;
    $data['type'] = $type;
    $data['type_id'] = $type_id;
    $data['image'] = $image;
    $data['order_id'] = $order_id;
    $data['user_id'] = $user_id;
    $data['is_readed'] = $is_readed;
    $data['notification_type'] = $notification_type;
    $data['target'] = $target;
    $data['order_status'] = $order_status;
    $data['custom_job_request_id'] = $custom_job_request_id;
    $data['bidder_id'] = $bidder_id;
    $data['bid_status'] = $bid_status;
    insert_details($data, 'notifications');
}

function store_users_fcm_id($user_id, $fcm_id, $platform, $web_fcm_id = null, $language_code = null)
{
    if (!empty($fcm_id) || !empty($web_fcm_id)) {
        $fcmData['fcm_id'] = $fcm_id;
        if ($web_fcm_id != '') {
            $fcmData['fcm_id'] = $web_fcm_id;
        } else if ($fcm_id != '') {
            $fcmData['fcm_id'] = $fcm_id;
        }
        $data['user_id'] = $user_id;
        $data['fcm_id'] = $fcm_id ?? $web_fcm_id;
        $data['platform'] = $platform;
        $data['status'] = 1;

        // Add language_code if provided
        if (!empty($language_code)) {
            $data['language_code'] = $language_code;
        }

        // Check if entry exists with same user_id, fcm_id, and platform
        $checkDataExist = fetch_details('users_fcm_ids', ['user_id' => $user_id, 'fcm_id' => $fcm_id ?? $web_fcm_id, 'platform' => $platform]);
        if (!empty($checkDataExist)) {
            // Update the last entry (most recent) for this FCM token
            // Get the most recent entry by ordering by id desc
            $db = \Config\Database::connect();
            $builder = $db->table('users_fcm_ids');
            $lastEntry = $builder->where('user_id', $user_id)
                ->where('fcm_id', $fcm_id ?? $web_fcm_id)
                ->where('platform', $platform)
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getResultArray();

            if (!empty($lastEntry)) {
                // Update the last entry with language_code and status
                $updateData = [];
                if (!empty($language_code)) {
                    $updateData['language_code'] = $language_code;
                }
                // Also update other fields if they changed
                $updateData['status'] = 1;
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                update_details($updateData, ['id' => $lastEntry[0]['id']], 'users_fcm_ids');
            } else {
                // Fallback: update the first matching entry
                update_details($data, ['id' => $checkDataExist[0]['id']], 'users_fcm_ids');
            }
        } else {
            // New entry - insert with language_code if provided
            insert_details($data, 'users_fcm_ids');
        }
    }
    return true;
}

/**
 * Prepare country code data for forms
 * This function handles the logic for selecting the appropriate country code
 * 
 * @param string $user_country_code The user's existing country code from database
 * @return array Returns array with 'country_codes' and 'selected_country_code'
 */
function prepare_country_code_data($user_country_code = '')
{
    // Fetch all country codes
    $country_codes = fetch_details('country_codes', []);

    // Find default country code from the already-fetched country codes
    $default_country_code = '';
    foreach ($country_codes as $code) {
        if (!empty($code['is_default'])) {
            $default_country_code = $code['calling_code'];
            break;
        }
    }

    // Use user's country code if available, otherwise use default
    $selected_country_code = !empty($user_country_code) ? $user_country_code : $default_country_code;

    return [
        'country_codes' => $country_codes,
        'selected_country_code' => $selected_country_code
    ];
}

/**
 * Get default language code from database
 * 
 * @return string Default language code (defaults to 'en' if none set)
 */
function get_default_language(): string
{
    $defaultLanguage = fetch_details('languages', ['is_default' => '1']);
    if (!empty($defaultLanguage)) {
        return $defaultLanguage[0]['code'];
    }

    // Fallback to 'en' only if no default language is set in database
    return 'en';
}

/**
 * Get current language code from session
 * 
 * @return string Current language code (defaults to database default language)
 */
function get_current_language(): string
{
    $session = session();
    $currentLang = $session->get('lang');

    // Return current language or default to database default language
    if (!empty($currentLang)) {
        return $currentLang;
    }

    // Get default language from database
    return get_default_language();
}

/**
 * Get translated email template with fallback mechanism
 * 
 * Fetches email template with multi-language support:
 * 1. First tries to get translation for current language
 * 2. If not found, tries default language translation
 * 3. If no translations exist, uses original template from main table
 * 
 * @param string $type Email template type
 * @param string|null $languageCode Specific language code (optional, uses current language if not provided)
 * @return array|false Template data with translated content or false if template not found
 */
function get_translated_email_template(string $type, ?string $languageCode = null): array|false
{
    // Get language codes for fallback mechanism
    $currentLanguage = $languageCode ?? get_current_language();
    $defaultLanguage = get_default_language();

    // Fetch base email template
    $template_data = fetch_details('email_templates', ['type' => $type]);
    if (!$template_data) {
        return false;
    }

    // Initialize with original template data
    $result = $template_data[0];

    // Try to get translated template for requested language
    $translationModel = new \App\Models\Translated_email_template_model();
    $translatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $currentLanguage);

    // If translation exists for current language, use it
    if (!empty($translatedTemplate)) {
        $result['template'] = !empty($translatedTemplate['template']) ? $translatedTemplate['template'] : $result['template'];
        $result['subject'] = !empty($translatedTemplate['subject']) ? $translatedTemplate['subject'] : $result['subject'];
    }
    // If no translation for current language, try default language (if different from current)
    else if ($currentLanguage !== $defaultLanguage) {
        $defaultTranslatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $defaultLanguage);
        if (!empty($defaultTranslatedTemplate)) {
            $result['template'] = !empty($defaultTranslatedTemplate['template']) ? $defaultTranslatedTemplate['template'] : $result['template'];
            $result['subject'] = !empty($defaultTranslatedTemplate['subject']) ? $defaultTranslatedTemplate['subject'] : $result['subject'];
        }
    }
    // If no translations available, use original template from main table (already set in $result)

    return $result;
}

/**
 * Get translated SMS template with fallback mechanism
 * 
 * Fetches SMS template with multi-language support:
 * 1. First tries to get translation for current language
 * 2. If not found, tries default language translation
 * 3. If no translations exist, uses original template from main table
 * 
 * @param string $type SMS template type
 * @param string|null $languageCode Specific language code (optional, uses current language if not provided)
 * @return array|false Template data with translated content or false if template not found
 */
function get_translated_sms_template(string $type, ?string $languageCode = null): array|false
{
    // Get language codes for fallback mechanism
    $currentLanguage = $languageCode ?? get_current_language();
    $defaultLanguage = get_default_language();

    // Fetch base SMS template
    $template_data = fetch_details('sms_templates', ['type' => $type]);
    if (!$template_data) {
        return false;
    }

    // Initialize with original template data
    $result = $template_data[0];

    // Try to get translated template for requested language
    $translationModel = new \App\Models\Translated_sms_template_model();
    $translatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $currentLanguage);

    // If translation exists for current language, use it
    if (!empty($translatedTemplate)) {
        $result['template'] = !empty($translatedTemplate['template']) ? $translatedTemplate['template'] : $result['template'];
        $result['title'] = !empty($translatedTemplate['title']) ? $translatedTemplate['title'] : $result['title'];
    }
    // If no translation for current language, try default language (if different from current)
    else if ($currentLanguage !== $defaultLanguage) {
        $defaultTranslatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $defaultLanguage);
        if (!empty($defaultTranslatedTemplate)) {
            $result['template'] = !empty($defaultTranslatedTemplate['template']) ? $defaultTranslatedTemplate['template'] : $result['template'];
            $result['title'] = !empty($defaultTranslatedTemplate['title']) ? $defaultTranslatedTemplate['title'] : $result['title'];
            $result['gateway_template_ids'] = $defaultTranslatedTemplate['gateway_template_ids'] ?? $result['gateway_template_ids'] ?? null;
        }
    }
    // gateway_template_ids: from translation first, else base sms_templates (already in $result)

    return $result;
}

/**
 * Get user's language preference by email or phone
 * 
 * Fetches user's preferred_language from users table based on email or phone.
 * Returns default language if user not found or no preference set.
 * 
 * @param string|null $email User's email address
 * @param string|null $phone User's phone number (with or without country code)
 * @param string|null $country_code Country code for phone lookup
 * @return string Language code (e.g., 'en', 'ar', 'hi')
 */
function get_user_language_preference($email = null, $phone = null, $country_code = null)
{
    $user = null;

    // Try to find user by email first
    if (!empty($email)) {
        $user = fetch_details('users', ['email' => $email], ['preferred_language']);
    }

    // If not found by email, try by phone
    if (empty($user) && !empty($phone)) {
        if (!empty($country_code)) {
            $user = fetch_details('users', ['phone' => $phone, 'country_code' => $country_code], ['preferred_language']);
        } else {
            $user = fetch_details('users', ['phone' => $phone], ['preferred_language']);
        }
    }

    // If user found and has preferred language, return it
    if (!empty($user) && !empty($user[0]['preferred_language'])) {
        return $user[0]['preferred_language'];
    }

    // Fallback to default language
    return get_default_language();
}

/**
 * Get translated partner data based on current language
 * 
 * @param array $partnerData Original partner data
 * @param int $partnerId Partner ID
 * @return array Partner data with translated fields
 */
function get_translated_partner_data(array $partnerData, int $partnerId): array
{
    $currentLang = get_current_language();
    $defaultLangCode = get_default_language();

    // If current language is the default language, return original data
    if ($currentLang === $defaultLangCode) {
        return $partnerData;
    }

    try {
        // Get translated data for current language
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
        $translatedData = $translationModel->getTranslatedDetails($partnerId, $currentLang);

        // log_message('debug', 'translatedData: ' . json_encode($translatedData));
        if ($translatedData) {
            // Replace translatable fields with translated versions
            $partnerData['company_name'] = !empty($translatedData['company_name']) ? $translatedData['company_name'] : $partnerData['company_name'];
            $partnerData['about'] = !empty($translatedData['about']) ? $translatedData['about'] : $partnerData['about'];
            $partnerData['long_description'] = !empty($translatedData['long_description']) ? $translatedData['long_description'] : $partnerData['long_description'];
        }
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Translation processing failed in get_translated_partner_data: ' . $e->getMessage());
    }

    return $partnerData;
}

/**
 * Get translated category data based on current language
 * 
 * @param array $categoryData Original category data
 * @param int $categoryId Category ID
 * @return array Category data with translated fields
 */
function get_translated_category_data(array $categoryData, int $categoryId): array
{
    $currentLang = get_current_language();
    $defaultLangCode = get_default_language();

    try {
        // Get translated data for current language
        $translationModel = new \App\Models\TranslatedCategoryDetails_model();
        $translatedData = $translationModel->getTranslatedDetails($categoryId, $currentLang);

        if ($translatedData) {
            // Replace translatable fields with translated versions
            $categoryData['name'] = !empty($translatedData['name']) ? $translatedData['name'] : $categoryData['name'];
        }
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Translation processing failed in get_translated_category_data: ' . $e->getMessage());
    }

    return $categoryData;
}

/**
 * Get categories with translated names
 * 
 * This helper function fetches categories and applies translated names
 * based on the current language with fallback to main table
 * 
 * @param array $whereConditions Optional where conditions for filtering
 * @return array Array of categories with translated names
 */
function get_categories_with_translated_names(array $whereConditions = []): array
{
    try {
        $categoryModel = new \App\Models\Category_model();

        // Get categories from main table
        $query = $categoryModel->select('id, name, parent_id, image');

        if (!empty($whereConditions)) {
            $query = $query->where($whereConditions);
        }

        $categories = $query->findAll();

        if (empty($categories)) {
            return [];
        }

        // Get category IDs for batch translation lookup
        $categoryIds = array_column($categories, 'id');
        $translatedNames = $categoryModel->getTranslatedCategoryNames($categoryIds);

        // Update category names with translations
        // Always ensure each category has a name (use translation if available, otherwise keep base table name)
        foreach ($categories as &$category) {
            // If translation exists and is not empty, use it
            if (isset($translatedNames[$category['id']]) && !empty(trim($translatedNames[$category['id']]))) {
                $category['name'] = $translatedNames[$category['id']];
            }
            // If translation is empty or doesn't exist, keep the base table name (already in $category['name'])
            // This ensures categories always have a name displayed, even when translations are missing

            $category['image'] = !empty($category['image']) ? $category['image'] : '';
        }

        return $categories;
    } catch (\Exception $e) {
        log_message('error', 'Error fetching categories with translated names: ' . $e->getMessage());

        // Fallback to main table only
        $categoryModel = new \App\Models\Category_model();
        $query = $categoryModel->select('id, name, image');
        if (!empty($whereConditions)) {
            $query = $query->where($whereConditions);
        }
        return $query->findAll();
    }
}

/**
 * Sort languages array so that default language appears first
 * This function ensures better UI by always showing the default language tab first
 * 
 * @param array $languages Array of language data from database
 * @return array Sorted languages array with default language first
 */
function sort_languages_with_default_first(array $languages): array
{
    // Separate default and non-default languages
    $default_languages = [];
    $non_default_languages = [];

    foreach ($languages as $language) {
        if (isset($language['is_default']) && $language['is_default'] == 1) {
            $default_languages[] = $language;
        } else {
            $non_default_languages[] = $language;
        }
    }

    // Return default languages first, then non-default languages
    return array_merge($default_languages, $non_default_languages);
}

/**
 * Get translated service data based on current language
 * 
 * @param array $serviceData Original service data
 * @param int $serviceId Service ID
 * @return array Service data with translated fields
 */
function get_translated_service_data(array $serviceData, int $serviceId): array
{
    $currentLang = get_current_language();
    $defaultLangCode = get_default_language();

    // If current language is the default language, return original data
    if ($currentLang === $defaultLangCode) {
        return $serviceData;
    }

    try {
        // Get translated data for current language
        $translationModel = new \App\Models\TranslatedServiceDetails_model();
        $translatedData = $translationModel->getTranslatedDetails($serviceId, $currentLang);

        if ($translatedData) {
            // Replace translatable fields with translated versions
            $serviceData['title'] = !empty($translatedData['title']) ? $translatedData['title'] : $serviceData['title'];
            $serviceData['description'] = !empty($translatedData['description']) ? $translatedData['description'] : $serviceData['description'];
            $serviceData['long_description'] = !empty($translatedData['long_description']) ? $translatedData['long_description'] : $serviceData['long_description'];
            $serviceData['tags'] = !empty($translatedData['tags']) ? $translatedData['tags'] : $serviceData['tags'];
            $serviceData['faqs'] = !empty($translatedData['faqs']) ? $translatedData['faqs'] : $serviceData['faqs'];
        }
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Translation processing failed in get_translated_service_data: ' . $e->getMessage());
    }

    return $serviceData;
}

/**
 * Transform form data to translated_fields structure for services
 * 
 * @param array $postData POST data from form
 * @param string $defaultLanguage Default language code
 * @return array Translated fields structure
 */
function transform_service_form_data_to_translated_fields(array $postData, string $defaultLanguage): array
{
    // Get languages from database
    $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

    if (empty($languages)) {
        return [];
    }

    // Define translatable fields for services
    $translatableFields = [
        'title',
        'description',
        'long_description',
        'tags',
        'faqs'
    ];

    // Build the translated_fields structure
    $translatedFields = [];
    foreach ($translatableFields as $fieldName) {
        $translatedFields[$fieldName] = [];
    }

    // Process each language
    foreach ($languages as $language) {
        $languageCode = $language['code'];

        foreach ($translatableFields as $fieldName) {
            // Get the field value for this language from POST data
            // Form sends data as: title[en], description[en], etc.
            $fieldKey = $fieldName . '[' . $languageCode . ']';
            $fieldValue = $postData[$fieldKey] ?? null;

            // For default language, also check if there's a direct field value (fallback)
            if ($languageCode === $defaultLanguage && empty($fieldValue)) {
                $fieldValue = $postData[$fieldName] ?? null;
            }

            // Handle special cases for tags and faqs
            if ($fieldName === 'tags' && is_array($fieldValue)) {
                // Extract tag values from objects with 'value' property or use strings directly
                $tagValues = [];
                foreach ($fieldValue as $tag) {
                    if (is_string($tag)) {
                        $tagValues[] = trim($tag);
                    } elseif (is_array($tag) && isset($tag['value'])) {
                        $tagValues[] = trim($tag['value']);
                    }
                }
                $fieldValue = implode(', ', array_filter($tagValues));
            } elseif ($fieldName === 'faqs' && is_array($fieldValue)) {
                // Convert faqs array to JSON string
                $fieldValue = json_encode($fieldValue);
            }

            // Only add non-empty values
            if (!empty($fieldValue)) {
                $translatedFields[$fieldName][$languageCode] = trim($fieldValue);
            }
        }
    }

    return $translatedFields;
}

/**
 * Get partner translations for a specific language
 * 
 * @param int $partnerId The partner ID
 * @param string $languageCode Language code
 * @return array|null Translated details or null if not found
 */
function get_partner_translations(int $partnerId, string $languageCode): ?array
{
    try {
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
        return $translationModel->getTranslatedDetails($partnerId, $languageCode);
    } catch (\Exception $e) {
        log_message('error', 'Error getting partner translations: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all translations for a partner
 * 
 * @param int $partnerId The partner ID
 * @return array All translations for the partner
 */
function get_all_partner_translations(int $partnerId): array
{
    try {
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
        return $translationModel->getAllTranslationsForPartner($partnerId);
    } catch (\Exception $e) {
        log_message('error', 'Error getting all partner translations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get service translations for a specific language
 * 
 * @param int $serviceId Service ID
 * @param string $languageCode Language code
 * @return array|null Translated details or null if not found
 */
function get_service_translations(int $serviceId, string $languageCode): ?array
{
    try {
        $translationModel = new \App\Models\TranslatedServiceDetails_model();
        return $translationModel->getTranslatedDetails($serviceId, $languageCode);
    } catch (\Exception $e) {
        log_message('error', 'Error getting service translations: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all translations for a service
 * 
 * @param int $serviceId Service ID
 * @return array All translations for the service
 */
function get_all_service_translations(int $serviceId): array
{
    try {
        $translationModel = new \App\Models\TranslatedServiceDetails_model();
        return $translationModel->getAllTranslationsForService($serviceId);
    } catch (\Exception $e) {
        log_message('error', 'Error getting all service translations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all translations for multiple sections in one query
 * 
 * @param array $sectionIds Array of section IDs
 * @return array All translations organized by section_id and language_code
 */
function get_all_section_translations(array $sectionIds): array
{
    try {
        if (empty($sectionIds)) {
            return [];
        }

        $translationModel = new \App\Models\TranslatedFeaturedSections_model();
        $db = \Config\Database::connect();

        // Get all translations for the sections
        $builder = $db->table('translated_featured_sections');
        $translations = $builder->select('section_id, language_code, title, description')
            ->whereIn('section_id', $sectionIds)
            ->get()
            ->getResultArray();


        // Organize translations by section_id and language_code
        $organizedTranslations = [];
        foreach ($translations as $translation) {
            $sectionId = $translation['section_id'];
            $languageCode = $translation['language_code'];

            if (!isset($organizedTranslations[$sectionId])) {
                $organizedTranslations[$sectionId] = [];
            }

            $organizedTranslations[$sectionId][$languageCode] = [
                'title' => $translation['title'],
                'description' => $translation['description']
            ];
        }

        return $organizedTranslations;
    } catch (\Exception $e) {
        log_message('error', 'Error getting all section translations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Apply translation logic to section data
 * 
 * This function implements the translation logic for sections:
 * - Main fields (title, description): Use default language translation, fallback to main table
 * - Translated fields (translated_title, translated_description): Use requested language, fallback to default language, then first available, then main table
 * 
 * @param array $sectionData Original section data from main table
 * @param array $allTranslations All translations organized by section_id and language_code
 * @param int $sectionId Section ID
 * @param string $requestedLanguage Language from request header
 * @param string $defaultLanguage Default language from database
 * @return array Section data with main and translated fields
 */
function apply_section_translation_logic(array $sectionData, array $allTranslations, int $sectionId, string $requestedLanguage, string $defaultLanguage): array
{
    try {
        // Get translations for this specific section
        $sectionTranslations = $allTranslations[$sectionId] ?? [];

        // Initialize result with original data
        $result = $sectionData;

        // Get main fields (title, description) - use default language translation, fallback to main table
        $defaultTranslation = $sectionTranslations[$defaultLanguage] ?? null;
        $result['title'] = $defaultTranslation['title'] ?? $sectionData['title'] ?? '';
        $result['description'] = $defaultTranslation['description'] ?? $sectionData['description'] ?? '';

        // Get translated fields (translated_title, translated_description) - use requested language with fallbacks
        $translatedTitle = '';
        $translatedDescription = '';

        // Try requested language first
        if (isset($sectionTranslations[$requestedLanguage])) {
            $requestedTranslation = $sectionTranslations[$requestedLanguage];
            $translatedTitle = $requestedTranslation['title'] ?? '';
            $translatedDescription = $requestedTranslation['description'] ?? '';
        }

        // If requested language not available or empty, try default language
        if (empty($translatedTitle) && isset($sectionTranslations[$defaultLanguage])) {
            $defaultTranslation = $sectionTranslations[$defaultLanguage];
            $translatedTitle = $defaultTranslation['title'] ?? '';
            $translatedDescription = $defaultTranslation['description'] ?? '';
        }

        // If still empty, use first available translation
        if (empty($translatedTitle) && !empty($sectionTranslations)) {
            $firstTranslation = reset($sectionTranslations);
            $translatedTitle = $firstTranslation['title'] ?? '';
            $translatedDescription = $firstTranslation['description'] ?? '';
        }

        // Final fallback to main table data
        if (empty($translatedTitle)) {
            $translatedTitle = $sectionData['title'] ?? '';
            $translatedDescription = $sectionData['description'] ?? '';
        }

        // Add translated fields to result
        $result['translated_title'] = $translatedTitle;
        $result['translated_description'] = $translatedDescription;

        return $result;
    } catch (\Exception $e) {
        log_message('error', 'Error applying section translation logic: ' . $e->getMessage());

        // Return original data with empty translated fields as fallback
        $result = $sectionData;
        $result['translated_title'] = $sectionData['title'] ?? '';
        $result['translated_description'] = $sectionData['description'] ?? '';

        return $result;
    }
}

// ─── Internal helpers for validate_order_status ──────────────────────────────

if (!function_exists('_order_status_error')) {
    function _order_status_error(string $message): array
    {
        return ['error' => true, 'message' => $message, 'data' => []];
    }
}

if (!function_exists('_send_rating_request_notification')) {
    function _send_rating_request_notification(int $order_id, int $customer_id, int $partner_id, string $languageCode): void
    {
        if (!check_notification_setting('rating_request_to_customer', 'notification')) {
            return;
        }
        try {
            queue_notification_service(
                eventType: 'rating_request_to_customer',
                recipients: ['user_id' => $customer_id],
                context: [
                    'booking_id' => $order_id,
                    'provider_id' => $partner_id,
                    'user_id' => $customer_id,
                ],
                options: [
                    'channels' => ['fcm'],
                    'language' => $languageCode,
                    'platforms' => ['android', 'ios', 'web'],
                    'type' => 'rating_request',
                    'data' => [
                        'booking_id' => (string) $order_id,
                        'provider_id' => (string) $partner_id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[RATING_REQUEST_TO_CUSTOMER_FCM] Notification error trace: ' . $e->getTraceAsString());
        }
    }
}

if (!function_exists('_send_cash_collection_notification')) {
    function _send_cash_collection_notification(int $order_id, int $partner_id, float $commission): void
    {
        if ($commission <= 0) {
            log_message('info', '[CASH_COLLECTION_BY_PROVIDER] Commission is 0 or less, skipping notification for order_id: ' . $order_id);
            return;
        }
        try {
            $providerName = get_translated_partner_field($partner_id, 'user_name');
            if (empty($providerName)) {
                $providerData = fetch_details('users', ['id' => $partner_id], ['username']);
                $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
            }

            $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

            $db = \Config\Database::connect();
            $adminUsers = $db->table('users_groups')
                ->select('user_id')
                ->where('group_id', 1)
                ->get()
                ->getResultArray();

            $recipientUserIds = array_column($adminUsers, 'user_id');
            if (!in_array($partner_id, $recipientUserIds)) {
                $recipientUserIds[] = $partner_id;
            }

            queue_notification_service(
                eventType: 'cash_collection_by_provider',
                recipients: [],
                context: [
                    'provider_name' => $providerName,
                    'provider_id' => $partner_id,
                    'amount' => number_format($commission, 2),
                    'currency' => $currency,
                    'booking_id' => $order_id,
                ],
                options: [
                    'user_ids' => $recipientUserIds,
                    'channels' => ['fcm', 'email', 'sms'],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[CASH_COLLECTION_BY_PROVIDER] Notification error trace: ' . $e->getTraceAsString());
        }
    }
}

if (!function_exists('_upload_work_evidence_files')) {
    function _upload_work_evidence_files(array $files): array
    {
        $images = [];
        foreach ($files as $key => $img) {
            if (!$img->isValid() || $img->hasMoved()) {
                continue;
            }
            $result = upload_file($img, 'public/uploads/provider_work_evidence', labels('error_creating_provider_work_evidence_folder'), 'provider_work_evidence');
            $images[$key] = $result['disk'] === 'aws_s3'
                ? $result['file_name']
                : '/public/uploads/provider_work_evidence/' . $result['file_name'];
        }
        return $images;
    }
}

if (!function_exists('_settle_completed_non_cod')) {
    function _settle_completed_non_cod(int $order_id, array $order, float $commission, string $admin_commission_pct, bool $otpEnabled, string $languageCode): void
    {
        $partner_id = $order['partner_id'];
        $user_details = fetch_details('users', ['id' => $partner_id]);
        $unsettled_amount = $order['final_total'] - $commission;

        $updateData = ["balance" => ($user_details[0]['balance'] + $unsettled_amount)];
        if ($otpEnabled) {
            $updateData['admin_commission'] = ($user_details[0]['admin_commission'] + $commission);
        } else {
            // OTP path updates order_services after both payment blocks; non-OTP updates here
            update_details(["status" => 'completed'], ["order_id" => $order_id], "order_services");
        }
        update_details($updateData, ["id" => $partner_id], "users");

        add_settlement_cashcollection_history('Received by admin', 'received_by_admin', date('Y-m-d'), date('h:i:s'), $unsettled_amount, $partner_id, $order_id, '', $admin_commission_pct, $order['final_total'], $commission);

        $customer_details = fetch_details('users', ['id' => $order['user_id']]);
        _send_rating_request_notification($order_id, $customer_details[0]['id'], $partner_id, $languageCode);
    }
}

if (!function_exists('_is_handyman_completion')) {
    function _is_handyman_completion(int $order_id, ?int $user_id): bool
    {
        if (empty($user_id)) {
            return false;
        }

        return model(\App\Models\BookingHandymenModel::class)
            ->where('order_id', $order_id)
            ->where('handyman_id', $user_id)
            ->countAllResults() > 0;
    }
}

if (!function_exists('_record_handyman_cash_collection')) {
    function _record_handyman_cash_collection(int $order_id, array $order, int $user_id): void
    {
        try {
            model(\App\Models\HandymanCashCollectionModel::class)->recordCollection(
                $order_id,
                $user_id,
                (int) $order['partner_id'],
                (float) $order['final_total']
            );
        } catch (\Throwable $e) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Helpers/function_helper.php - _record_handyman_cash_collection() order_id=' . $order_id . ' user_id=' . $user_id . "\nMessage : " . $e->getMessage());
        }
    }
}

if (!function_exists('_settle_completed_cod')) {
    function _settle_completed_cod(int $order_id, array $order, array $order_row, float $commission, string $admin_commission_pct, bool $otpEnabled, string $languageCode, ?int $user_id = null): void
    {
        $partner_id = $order['partner_id'];
        $isHandymanCollection = _is_handyman_completion($order_id, $user_id);

        if ($isHandymanCollection) {
            // Handyman physically collected the cash, not the partner — the partner
            // hasn't received anything yet. Provider-side settlement (payable_commision,
            // cash_collection row, settlement_cashcollection_history) is deferred until
            // the partner marks "cash received from handyman" (see
            // HandymanCashCollectionService::collectFromHandyman()).
            _record_handyman_cash_collection($order_id, $order, (int) $user_id);
        } elseif ($otpEnabled) {
            $current = fetch_details('users', ['id' => $partner_id], ['payable_commision', 'email', 'admin_commission'])[0];
            $current['payable_commision'] = ($current['payable_commision'] === '') ? 0 : $current['payable_commision'];
            update_details([
                'payable_commision' => ($current['payable_commision'] + $commission),
                'admin_commission' => ($current['admin_commission'] + $commission),
            ], ['id' => $partner_id], 'users');
            $settlement_amount = $commission;
            $settlement_pct_arg = $commission;
        } else {
            $current = fetch_details('users', ['id' => $partner_id], ['payable_commision', 'email'])[0];
            $current['payable_commision'] = ($current['payable_commision'] === '') ? 0 : $current['payable_commision'];
            $sum = $current['payable_commision'] + $commission;
            update_details(['payable_commision' => $sum == 0 ? "0" : $sum], ['id' => $partner_id], 'users');
            $settlement_amount = $order['final_total'] - $commission;
            $settlement_pct_arg = $admin_commission_pct;
        }

        update_details(['payment_status' => '1'], ['id' => $order_id], 'orders');
        notify_handymen_payment_status_changed($order_id, 'online_payment_success', [
            'booking_id' => (string) $order_id,
            'order_id' => (string) $order_id,
            'amount' => number_format($order['final_total'] ?? 0, 2),
            'payment_method' => 'cod',
            'transaction_id' => (string) $order_id,
        ], $languageCode);
        if ($order_row['total_additional_charge'] != 0 || $order_row['total_additional_charge'] !== '') {
            update_details(['payment_status_of_additional_charge' => '1', 'payment_method_of_additional_charge' => 'cod'], ['id' => $order_id], 'orders');
        }

        if (!$isHandymanCollection) {
            insert_details([
                'user_id' => $order['user_id'],
                'order_id' => $order_id,
                'message' => 'provider received cash',
                'status' => 'provider_cash_recevied',
                'commison' => intval($commission),
                'partner_id' => $partner_id,
                'date' => date('Y-m-d'),
            ], 'cash_collection');

            add_settlement_cashcollection_history('Cash collected by provider', 'cash_collection_by_provider', date('Y-m-d'), date('h:i:s'), $settlement_amount, $partner_id, $order_id, '', $settlement_pct_arg, $order['final_total'], $commission);
        }

        if ($otpEnabled) {
            $txn = fetch_details('transactions', ['order_id' => $order_id, 'message' => 'txn_additional_charges']);
            if (!empty($txn)) {
                update_details(['status' => 'success'], ['id' => $txn[0]['id']], 'transactions');
            }
        }

        if (!$isHandymanCollection) {
            _send_cash_collection_notification($order_id, $partner_id, $commission);
        }

        $customer_details = fetch_details('users', ['id' => $order['user_id']]);
        _send_rating_request_notification($order_id, $customer_details[0]['id'], $partner_id, $languageCode);
    }
}

if (!function_exists('_send_additional_charges_notification')) {
    function _send_additional_charges_notification(int $order_id, array $additional_charges, float $additional_total_charge, string $languageCode): void
    {
        try {
            $order_details = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_details)) {
                log_message('error', '[ADDED_ADDITIONAL_CHARGES] Order not found: ' . $order_id);
                return;
            }

            $order = $order_details[0];
            $customer_id = $order['user_id'];
            $provider_id = $order['partner_id'];
            $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

            $providerName = get_translated_partner_field($provider_id, 'company_name');
            if (empty($providerName)) {
                $partner_data = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
                $providerName = !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
            }

            $customer_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);
            $customer_name = !empty($customer_details[0]['username']) ? $customer_details[0]['username'] : 'Customer';

            $charges_items = [];
            foreach ($additional_charges as $charge) {
                if (!empty($charge['name']) && !empty($charge['charge'])) {
                    $charges_items[] = $charge['name'] . ': ' . number_format($charge['charge'], 2) . ' ' . $currency;
                }
            }

            queue_notification_service(
                eventType: 'added_additional_charges',
                recipients: ['user_id' => $customer_id],
                context: [
                    'booking_id' => (string) $order_id,
                    'order_id' => (string) $order_id,
                    'total_additional_charge' => number_format($additional_total_charge, 2),
                    'currency' => $currency,
                    'provider_id' => (string) $provider_id,
                    'provider_name' => $providerName,
                    'customer_id' => (string) $customer_id,
                    'customer_name' => $customer_name,
                    'additional_charges_list' => implode('<br>', $charges_items),
                    'final_total' => number_format($order['final_total'], 2),
                ],
                options: [
                    'channels' => ['fcm', 'email', 'sms'],
                    'language' => $languageCode ?? get_default_language(),
                    'platforms' => ['android', 'ios', 'web'],
                    'type' => 'additional_charges',
                    'data' => [
                        'order_id' => (string) $order_id,
                        'booking_id' => (string) $order_id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to' => 'booking_details_screen',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[ADDED_ADDITIONAL_CHARGES] Notification error trace: ' . $e->getTraceAsString());
        }
    }
}

// ─── Refactored validate_status ───────────────────────────────────────────────

if (!function_exists('validate_order_status')) {
    function validate_order_status($order_id, $status, $date = '', $selected_time = "", $otp = null, $work_proof = null, $additional_charges = null, $user_id = null, $language = null, $actor_type = null)
    {
        $languageCode = $language ?? get_default_language();

        // Who triggered this status change (admin/customer/provider/handyman) — merged
        // into every 'orders' status write below. Write-only, no read path yet.
        $statusActorFields = (!empty($actor_type) && !empty($user_id))
            ? ['status_changed_by_type' => $actor_type, 'status_changed_by_id' => (int) $user_id]
            : [];

        $validStatuses = ['awaiting', 'confirmed', 'rescheduled', 'cancelled', 'completed', 'started', 'booking_ended', 'on_the_way', 'arrived'];
        if (!in_array($status, $validStatuses)) {
            return _order_status_error(labels(INVALID_STATUS_PASSED, "Invalid Status Passed"));
        }

        $statusLabels = [
            'awaiting' => labels('awaiting', 'Awaiting'),
            'confirmed' => labels('confirmed', 'Confirmed'),
            'rescheduled' => labels('rescheduled', 'Rescheduled'),
            'cancelled' => labels('cancelled', 'Cancelled'),
            'completed' => labels('completed', 'Completed'),
            'started' => labels('started', 'Started'),
            'booking_ended' => labels('booking_ended', 'Booking Ended'),
            'on_the_way' => labels('on_the_way', 'On The Way'),
            'arrived' => labels('arrived', 'Arrived'),
        ];
        $translated_status = $statusLabels[$status] ?? '';

        $db = \Config\Database::connect();
        $active_status1 = $db->table('orders')
            ->select('status,payment_method,user_id,otp,final_total,total_additional_charge,payment_status_of_additional_charge,payment_method_of_additional_charge')
            ->where('id', $order_id)
            ->get()
            ->getResultArray();

        $active_status = $active_status1[0]['status'] ?? '';

        if ($active_status === '') {
            return _order_status_error(labels(INVALID_BOOKING_OR_STATUS_DATA, "Invalid booking or status data"));
        }
        if ($active_status === $status) {
            return _order_status_error(labels(YOU_CANT_UPDATE_THE_SAME_STATUS_AGAIN, "You can't update the same status again"));
        }
        if (in_array($active_status, ['cancelled', 'completed'])) {
            return _order_status_error(labels(YOU_CANT_UPDATE_STATUS_ONCE_ITEM_CANCELLED_OR_COMPLETED, "You can't update status once item cancelled OR completed"));
        }

        // ── Forbidden-transition guards ───────────────────────────────────────
        // "Once you begin..." applies when started → scheduling statuses
        if ($active_status === 'started' && in_array($status, ['rescheduled', 'confirmed', 'on_the_way', 'arrived'])) {
            return _order_status_error(labels(ONCE_YOU_BEGIN_THE_BOOKING_PROCESS_YOU_CANNOT_CHANGE_THE_BOOKING_TIME, "Once you begin the booking process, you cannot change the booking time."));
        }

        // "Can't alter" matrix — all remaining forbidden transitions share one message
        $cantAlterMsg = labels(YOU_CANT_ALTER_THE_STATUS_THAT_HAS_ALREADY_BEEN_MARKED_AS, "You cannot alter the status that has already been marked as") . " " . labels(strtolower($translated_status));
        $cantAlterMatrix = [
            'booking_ended' => ['rescheduled', 'confirmed', 'awaiting', 'pending', 'on_the_way', 'arrived', 'started'],
            'started' => ['awaiting', 'pending'],
            'arrived' => ['rescheduled', 'confirmed', 'awaiting', 'pending', 'on_the_way'],
            'on_the_way' => ['rescheduled', 'confirmed', 'awaiting', 'pending'],
            'confirmed' => ['awaiting'],
            'rescheduled' => ['awaiting'],
        ];
        foreach ($cantAlterMatrix as $from => $blocked) {
            if ($active_status === $from && in_array($status, $blocked)) {
                return _order_status_error($cantAlterMsg);
            }
        }

        // ── awaiting / confirmed ──────────────────────────────────────────────
        if (in_array($status, ['awaiting', 'confirmed'])) {
            update_details(['status' => $status] + $statusActorFields, ['id' => $order_id], 'orders');
            update_details(['status' => $status], ['order_id' => $order_id, 'status!=' => 'cancelled'], 'order_services');
            if ($status === 'confirmed') {
                send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
            }
        }

        // ── completed ─────────────────────────────────────────────────────────
        if ($status === 'completed') {
            // payment_status_of_additional_charge is NULL until a payment attempt is made
            // (booking_ended only sets total_additional_charge/additional_charges) — treat
            // NULL the same as '' / '0' (unpaid), not just the two string states.
            $paymentStatusOfAdditionalCharge = $active_status1[0]['payment_status_of_additional_charge'] ?? '';
            if (
                (empty($active_status1[0]['payment_method_of_additional_charge']) || $active_status1[0]['payment_method_of_additional_charge'] !== 'cod') &&
                (!empty($active_status1[0]['total_additional_charge']) && $active_status1[0]['total_additional_charge'] != 0) &&
                ($paymentStatusOfAdditionalCharge === '' || $paymentStatusOfAdditionalCharge === '0')
            ) {
                return _order_status_error(labels(BOOKING_CANNOT_BE_COMPLETED_WITH_A_PENDING_PAYMENT_OF_ADDITIONAL_CHARGES, "Booking cannot be completed because payment of additional charges is pending by customer."));
            }

            $settings = get_settings('general_settings', true);
            $otpEnabled = isset($settings['otp_system']) && $settings['otp_system'] == 1;

            if ($otpEnabled) {
                if (empty($otp)) {
                    return _order_status_error(labels(OTP_IS_REQUIRED, "OTP is required"));
                }
                if ($active_status1[0]['otp'] != $otp) {
                    return _order_status_error(labels(OTP_DOES_NOT_MATCH, "OTP does not match!"));
                }
            }

            $order_details = fetch_details('orders', ['id' => $order_id]);
            $partner_id = $order_details[0]['partner_id'];
            $admin_commission_pct = get_admin_commision($partner_id);
            $commission = intval($order_details[0]['final_total']) * (intval($admin_commission_pct) / 100);

            update_details(['status' => $status] + $statusActorFields, ['id' => $order_id], 'orders');
            send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);

            if ($order_details[0]['payment_method'] !== 'cod') {
                _settle_completed_non_cod($order_id, $order_details[0], $commission, $admin_commission_pct, $otpEnabled, $languageCode);
            } else {
                _settle_completed_cod($order_id, $order_details[0], $active_status1[0], $commission, $admin_commission_pct, $otpEnabled, $languageCode, $user_id);
            }

            if ($otpEnabled) {
                update_details(["status" => $status], ["order_id" => $order_id], "order_services");
            }
        }

        // ── started ───────────────────────────────────────────────────────────
        if ($status === 'started') {
            $work_started_images = [];
            if (!empty($work_proof['work_started_files'])) {
                $work_started_images = _upload_work_evidence_files($work_proof['work_started_files']);
            }
            update_details([
                'status' => 'started',
                'work_started_proof' => !empty($work_started_images) ? json_encode($work_started_images) : '',
            ] + $statusActorFields, ['id' => $order_id], 'orders', false);
            update_details(['status' => $status], ['order_id' => $order_id], 'order_services');
            send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
        }

        // ── rescheduled ───────────────────────────────────────────────────────
        if ($status === 'rescheduled') {
            if (empty($date) || empty($selected_time)) {
                return _order_status_error(labels(PLEASE_SELECT_UPCOMING_DATE, "Please select upcoming date"));
            }
            $orders = fetch_details('orders', ['id' => $order_id]);
            if (empty($orders)) {
                return _order_status_error(labels(INVALID_BOOKING_OR_STATUS_DATA, "Invalid booking or status data"));
            }

            $partner_id = (int) $orders[0]['partner_id'];
            $sub_orders = fetch_details('orders', ['parent_id' => $order_id]);
            $service_total_duration = (int) $orders[0]['duration'];
            foreach (($sub_orders ?: []) as $so) {
                $service_total_duration += (int) ($so['duration'] ?? 0);
            }

            $slotSvc = service('slot');
            $lockResult = $slotSvc->validateAndLockSlot(
                $partner_id,
                $date,
                (string) $selected_time,
                $service_total_duration,
                (int) ($user_id ?: $orders[0]['user_id'])
            );
            if ($lockResult['error']) {
                return _order_status_error($lockResult['message']);
            }

            $confirm = $slotSvc->confirmBooking(
                (int) $lockResult['data']['lock_id'],
                $partner_id,
                $date,
                (string) $selected_time,
                $service_total_duration,
                (int) ($user_id ?: $orders[0]['user_id'])
            );
            if ($confirm['error']) {
                return _order_status_error($confirm['message']);
            }

            $new_starting = (string) $confirm['data']['starting_time'];
            $new_ending = (string) $confirm['data']['ending_time'];
            $new_shift_id = $confirm['data']['shift_id'] ?? null;
            $continuation = $confirm['data']['continuation'] ?? null;
            $primary_duration = empty($continuation)
                ? $service_total_duration
                : max(1, (int) ((strtotime($new_ending) - strtotime($new_starting)) / 60));

            update_details([
                'status' => 'rescheduled',
                'date_of_service' => $date,
                'starting_time' => $new_starting,
                'ending_time' => $new_ending,
                'duration' => $primary_duration,
                'shift_id' => $new_shift_id,
            ] + $statusActorFields, ['id' => $order_id], 'orders');

            if (!empty($sub_orders)) {
                update_details(['status' => 'cancelled'] + $statusActorFields, ['parent_id' => $order_id], 'orders');
            }
            if (!empty($continuation)) {
                $cont_start = (string) $continuation['starting_time'];
                $cont_end = (string) $continuation['ending_time'];
                $cont_duration = max(1, (int) ((strtotime($cont_end) - strtotime($cont_start)) / 60));
                insert_details([
                    'partner_id' => $partner_id,
                    'user_id' => $orders[0]['user_id'],
                    'city' => $orders[0]['city'] ?? ($orders[0]['city_id'] ?? ''),
                    'total' => $orders[0]['total'],
                    'payment_method' => $orders[0]['payment_method'],
                    'address_id' => $orders[0]['address_id'],
                    'visiting_charges' => $orders[0]['visiting_charges'],
                    'address' => $orders[0]['address'],
                    'date_of_service' => (string) $continuation['date'],
                    'starting_time' => $cont_start,
                    'ending_time' => $cont_end,
                    'duration' => $cont_duration,
                    'shift_id' => $continuation['shift_id'] ?? null,
                    'status' => 'rescheduled',
                    'remarks' => 'sub_order',
                    'otp' => random_int(100000, 999999),
                    'parent_id' => $orders[0]['id'],
                    'order_latitude' => $orders[0]['order_latitude'],
                    'order_longitude' => $orders[0]['order_longitude'],
                    'final_total' => $orders[0]['final_total'],
                    'created_at' => date('Y-m-d H:i:s'),
                ], 'orders');
            }

            send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
            return ['error' => false, 'message' => labels(THE_BOOKING_HAS_BEEN_SUCCESSFULLY_RESCHEDULED, "The booking has been successfully rescheduled."), 'data' => []];
        }

        // ── cancelled ─────────────────────────────────────────────────────────
        if ($status === 'cancelled') {
            $provider_details = fetch_details('partner_details', ['partner_id' => $user_id], ['partner_id']);

            if (!empty($provider_details) && $provider_details[0]['partner_id'] == $user_id) {
                // Provider cancelling
                $order_data = fetch_details('orders', ['id' => $order_id], ['user_id', 'status']);
                $active_status = !empty($order_data[0]['status']) ? $order_data[0]['status'] : '';
                $customer_id = !empty($order_data[0]['user_id']) ? $order_data[0]['user_id'] : null;

                update_details(['status' => $status] + $statusActorFields, ['id' => $order_id], 'orders');
                if (!empty($customer_id)) {
                    process_refund($order_id, $status, $customer_id);
                }
                send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
                return ['error' => false, 'message' => labels(BOOKING_IS_CANCELLED, "Booking is cancelled."), 'data' => []];
            }

            // Customer cancelling
            $order_details = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_details)) {
                return _order_status_error(labels(BOOKING_DATA_NOT_FOUND, "Booking data not found!"));
            }

            $order = json_decode(json_encode($order_details[0]));
            $customer_id = $order->user_id;
            $date_of_service = $order->date_of_service;
            $starting_time = $order->starting_time;

            if ($order->custom_job_request_id != "" || $order->custom_job_request_id != null) {
                $bids = fetch_details('partner_bids', ['custom_job_request_id' => $order->custom_job_request_id]);
                $custom_settings = get_settings('general_settings', true);
                $cancellable = [
                    [
                        'id' => $bids[0]['custom_job_request_id'],
                        'duration' => $bids[0]['duration'],
                        'is_cancelable' => 1,
                        'cancelable_till' => $custom_settings['booking_auto_cancle_duration'],
                    ]
                ];
            } else {
                $order_services = fetch_details('order_services', ['order_id' => $order_id]);
                $cancellable = [];
                foreach (array_column($order_services, 'service_id') as $sid) {
                    $svc_rows = fetch_details('services', ['id' => $sid], ['id', 'duration', 'is_cancelable', 'cancelable_till'], null, '0', '', '');
                    $cancellable = array_merge($cancellable, $svc_rows);
                }
            }

            foreach ($cancellable as $key) {
                if ($key['is_cancelable'] != 1 || empty($key['cancelable_till'])) {
                    return _order_status_error(labels(BOOKING_IS_NOT_CANCELABLE, "Booking is not cancelable!"));
                }
                $is_cancelable = check_cancelable(date('y-m-d', strtotime($date_of_service)), $starting_time, $key['cancelable_till']);
                if (!$is_cancelable) {
                    return _order_status_error(labels(BOOKING_IS_NOT_CANCELABLE, "Booking is not cancelable !"));
                }
                update_details(['status' => $status] + $statusActorFields, ['id' => $order_id], 'orders');
                $refund = process_refund($order_id, $status, $customer_id);
                send_booking_status_notifications($order_id, $status, $translated_status, $active_status, $languageCode, $user_id);
                return ['error' => false, 'is_cancelable' => true, 'message' => labels(BOOKING_UPDATED_SUCCESSFULLY, "Booking updated successfully"), 'data' => $refund];
            }

            return _order_status_error(labels(BOOKING_IS_NOT_CANCELABLE, "Booking is not cancelable!"));
        }

        // ── booking_ended ─────────────────────────────────────────────────────
        if ($status === 'booking_ended') {
            $additional_total_charge = 0;
            $additional_tax_amount = 0;
            $dataToUpdate = ['status' => 'booking_ended'];

            if (!empty($additional_charges) && ($additional_charges[0]['name'] !== '' && $additional_charges[0]['charge'] !== '')) {
                $tax_row = \Config\Database::connect()->table('order_services')
                    ->select('tax_percentage')
                    ->where('order_id', $order_id)
                    ->where('status !=', 'cancelled')
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getRowArray();
                $tax_percentage = (float) ($tax_row['tax_percentage'] ?? 0);

                foreach ($additional_charges as $key => $charge) {
                    if (empty($charge['name']) || empty($charge['charge'])) {
                        return _order_status_error(labels(ALL_ADDITIONAL_CHARGE_FIELDS_ARE_REQUIRED, "All additional charge fields are required"));
                    }
                    if ((float) $charge['charge'] < 1) {
                        return _order_status_error(labels(CHARGE_AMOUNT_MUST_BE_GREATER_THAN_0, "Charge amount must be greater than 0"));
                    }
                    $charge_amount = round((float) $charge['charge'], 2);
                    $charge_tax = calculate_tax_amount($charge_amount, $tax_percentage, 'excluded');
                    $additional_charges[$key]['charge'] = number_format($charge_amount, 2, '.', '');
                    $additional_charges[$key]['tax_percentage'] = number_format($tax_percentage, 2, '.', '');
                    $additional_charges[$key]['tax_type'] = 'excluded';
                    $additional_charges[$key]['tax_amount'] = number_format($charge_tax, 2, '.', '');
                    $additional_charges[$key]['total'] = number_format($charge_amount + $charge_tax, 2, '.', '');
                    $additional_total_charge += $charge_amount;
                    $additional_tax_amount += $charge_tax;
                }
                $dataToUpdate['additional_charges'] = json_encode($additional_charges);
                $additional_tax_amount = round($additional_tax_amount, 2);
                $dataToUpdate['total_additional_charge'] = $additional_total_charge + $additional_tax_amount;
                $dataToUpdate['final_total'] = $active_status1[0]['final_total'] + $additional_total_charge + $additional_tax_amount;
                // Mark unpaid at creation time — column defaults to NULL otherwise, which the
                // completed-guard's strict '' / '0' check doesn't catch.
                // payment_status_of_additional_charge: '0' = pending/unpaid, '1' = paid, '2' = failed.
                $dataToUpdate['payment_status_of_additional_charge'] = '0';
            }

            update_details($dataToUpdate + $statusActorFields, ['id' => $order_id], 'orders', false);

            if (!empty($additional_charges) && ($additional_charges[0]['name'] !== '' && $additional_charges[0]['charge'] !== '')) {
                _send_additional_charges_notification($order_id, $additional_charges, $additional_total_charge + $additional_tax_amount, $languageCode);
            }

            if (!empty($work_proof['work_complete_files'])) {
                $work_completed_images = _upload_work_evidence_files($work_proof['work_complete_files']);
                update_details([
                    'work_completed_proof' => !empty($work_completed_images) ? json_encode($work_completed_images) : '',
                ], ['id' => $order_id], 'orders', false);
            }
        }

        return ['error' => false, 'message' => labels(BOOKING_UPDATED_SUCCESSFULLY, "Booking updated successfully"), 'data' => []];
    }
}

/**
 * Apply translation logic to multiple sections at once
 * 
 * This is the main function to use for section translation in APIs.
 * It handles fetching all translations and applying the logic to multiple sections efficiently.
 * 
 * @param array $sections Array of section data from main table
 * @param string $requestedLanguage Language from request header (optional, will be auto-detected)
 * @param string $defaultLanguage Default language (optional, will be auto-detected)
 * @return array Array of sections with main and translated fields
 */
function apply_section_translations_to_multiple(array $sections, ?string $requestedLanguage = null, ?string $defaultLanguage = null): array
{
    try {
        // Auto-detect languages if not provided
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }
        if ($defaultLanguage === null) {
            $defaultLanguage = get_default_language();
        }

        // Extract section IDs
        $sectionIds = array_column($sections, 'id');
        if (empty($sectionIds)) {
            return $sections;
        }

        // Get all translations in one query
        $allTranslations = get_all_section_translations($sectionIds);

        // Apply translation logic to each section
        $translatedSections = [];
        foreach ($sections as $section) {
            $translatedSections[] = apply_section_translation_logic(
                $section,
                $allTranslations,
                $section['id'],
                $requestedLanguage,
                $defaultLanguage
            );
        }

        return $translatedSections;
    } catch (\Exception $e) {
        log_message('error', 'Error applying section translations to multiple sections: ' . $e->getMessage());

        // Return original sections with empty translated fields as fallback
        $fallbackSections = [];
        foreach ($sections as $section) {
            $fallbackSection = $section;
            $fallbackSection['translated_title'] = $section['title'] ?? '';
            $fallbackSection['translated_description'] = $section['description'] ?? '';
            $fallbackSections[] = $fallbackSection;
        }

        return $fallbackSections;
    }
}

/**
 * Apply translation logic to a single section
 * 
 * This is a convenience function for single section translation.
 * 
 * @param array $section Section data from main table
 * @param string $requestedLanguage Language from request header (optional, will be auto-detected)
 * @param string $defaultLanguage Default language (optional, will be auto-detected)
 * @return array Section with main and translated fields
 */
function apply_section_translations_to_single(array $section, ?string $requestedLanguage = null, ?string $defaultLanguage = null): array
{
    return apply_section_translations_to_multiple([$section], $requestedLanguage, $defaultLanguage)[0];
}

/**
 * Decode JSON fields to arrays if they are JSON strings
 * This helper function ensures that JSON fields like FAQs and tags are returned as proper arrays
 * 
 * @param mixed $value The value to decode
 * @param string $fieldName The name of the field being processed
 * @return mixed Decoded value (array if JSON string, original value otherwise)
 */
function decode_json_field($value, string $fieldName)
{
    // Only process JSON fields
    if (!in_array($fieldName, ['faqs', 'tags'])) {
        return $value;
    }

    // If value is empty or null, return empty array
    if (empty($value)) {
        return [];
    }

    // If value is already an array, return as-is
    if (is_array($value)) {
        return $value;
    }

    // If value is a string, try to decode JSON
    if (is_string($value)) {
        $decoded = json_decode($value, true);

        // If JSON decode was successful and returned an array, return it
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // If JSON decode failed, log the error and return empty array
        log_message('warning', "Failed to decode JSON for field '$fieldName': " . json_last_error_msg());
        return [];
    }

    // For any other type, return empty array
    return [];
}

/**
 * Get translated service data with fallback logic based on Content-Language header
 * This function provides both default language and translated values for API responses
 * 
 * @param int $serviceId Service ID
 * @param array $fallbackData Fallback data from main table
 * @param string|null $requestedLanguage Optional specific language code (if not provided, will use Content-Language header)
 * @return array Translated data with both default and translated values
 */
function get_translated_service_data_for_api(int $serviceId, array $fallbackData = [], ?string $requestedLanguage = null): array
{
    try {
        // 1️⃣ Get default language
        $defaultLanguage = get_default_language();

        // 2️⃣ Determine requested language
        $requestedLanguage = $requestedLanguage
            ?? get_current_language_from_request()
            ?? $defaultLanguage;

        // 3️⃣ Fetch all translations for the service
        $allTranslationsRaw = get_all_service_translations($serviceId);

        // Organize translations by language code
        $allTranslations = [];
        foreach ($allTranslationsRaw as $translation) {
            $langCode = $translation['language_code'];
            $allTranslations[$langCode] = [
                'title' => $translation['title'] ?? '',
                'description' => $translation['description'] ?? '',
                'long_description' => $translation['long_description'] ?? '',
                'tags' => $translation['tags'] ?? '',
                'faqs' => $translation['faqs'] ?? ''
            ];
        }


        // 4️⃣ Pick default and requested translations
        $defaultTranslations = $allTranslations[$defaultLanguage] ?? [];
        $requestedTranslations = $allTranslations[$requestedLanguage] ?? [];

        // 5️⃣ Define fields and fallback values
        $translatableFields = [
            'title' => $fallbackData['title'] ?? '',
            'description' => $fallbackData['description'] ?? '',
            'long_description' => $fallbackData['long_description'] ?? '',
            'tags' => $fallbackData['tags'] ?? '',
            'faqs' => $fallbackData['faqs'] ?? ''
        ];

        $translatedData = [];

        // 6️⃣ Process each field
        foreach ($translatableFields as $field => $fallbackValue) {

            // Helper function to get the first non-empty value from translations
            $getFirstAvailableTranslation = function ($field) use ($allTranslations, $fallbackValue) {
                foreach ($allTranslations as $langData) {
                    if (!empty($langData[$field])) {
                        return $langData[$field];
                    }
                }
                return $fallbackValue; // fallback to main record
            };

            // Default value: default language or fallback
            $defaultValue = $defaultTranslations[$field] ?? null;
            if ($defaultValue === null) {
                // Default language doesn't exist, try first available
                $defaultValue = $getFirstAvailableTranslation($field);
            } elseif (empty($defaultValue)) {
                // Default language exists but is empty, try first available
                $defaultValue = $getFirstAvailableTranslation($field);
            }

            // Requested value: Proper fallback chain for translated fields
            // 1. Try requested language translation (if non-empty)
            // 2. If requested language translation is missing or empty, try default language translation (if non-empty)
            // 3. If default language translation is also missing or empty, fallback to base table data
            $requestedValue = '';
            if (isset($requestedTranslations[$field]) && !empty($requestedTranslations[$field])) {
                // Requested language has this field and it's not empty, use it
                $requestedValue = $requestedTranslations[$field];
            } else {
                // Requested language doesn't have this field or it's empty, try default language
                if (isset($defaultTranslations[$field]) && !empty($defaultTranslations[$field])) {
                    // Default language translation exists and is not empty, use it
                    $requestedValue = $defaultTranslations[$field];
                } else {
                    // Default language translation is also missing or empty, fallback to base table data
                    $requestedValue = $fallbackValue;
                }
            }

            // Decode JSON fields if needed
            $defaultValue = decode_json_field($defaultValue, $field);
            $requestedValue = decode_json_field($requestedValue, $field);

            // Assign to API response
            $translatedData[$field] = $defaultValue;
            $translatedData["translated_$field"] = $requestedValue;
        }

        return $translatedData;
    } catch (\Exception $e) {
        log_message('error', 'Translation processing failed in get_translated_service_data_for_api: ' . $e->getMessage());
        return [];
    }
}


/**
 * Get translated partner data with proper fallback logic based on Content-Language header
 * 
 * Fallback logic:
 * 1. If data for the language passed in header exists → use that data as translated_fieldName
 * 2. If header language data not present → use default language data from translations table
 * 3. If neither header nor default language available → use main table data as fallback
 * 
 * @param int $partnerId Partner ID
 * @param array $fallbackData Fallback data from main table
 * @param string|null $requestedLanguage Optional specific language code (if not provided, will use Content-Language header)
 * @return array Partner data with translations following fallback logic
 */
function get_translated_partner_data_for_api(int $partnerId, array $fallbackData = [], ?string $requestedLanguage = null): array
{
    try {
        // Validate partner ID to prevent errors
        if (empty($partnerId) || $partnerId <= 0) {
            log_message('error', 'Invalid partner ID provided to get_translated_partner_data_for_api: ' . $partnerId);
            return $fallbackData; // Return fallback data if partner ID is invalid
        }

        // 1️⃣ Get default language
        $defaultLanguage = get_default_language();

        // 2️⃣ Determine requested language
        $requestedLanguage = $requestedLanguage
            ?? get_current_language_from_request()
            ?? $defaultLanguage;

        // 3️⃣ Fetch all translations for the partner
        $allTranslationsRaw = get_all_partner_translations($partnerId);

        // Organize translations by language code
        $allTranslations = [];
        foreach ($allTranslationsRaw as $translation) {
            $langCode = $translation['language_code'];
            $allTranslations[$langCode] = [
                'company_name' => $translation['company_name'] ?? '',
                'about' => $translation['about'] ?? '',
                'long_description' => $translation['long_description'] ?? '',
                'username' => $translation['username'] ?? ''
            ];
        }

        // log_message('debug', 'Partner allTranslations organized: ' . json_encode($allTranslations));

        // 4️⃣ Pick default and requested translations
        $defaultTranslations = $allTranslations[$defaultLanguage] ?? [];
        $requestedTranslations = $allTranslations[$requestedLanguage] ?? [];

        // 5️⃣ Define fields and fallback values
        $translatableFields = [
            'company_name' => $fallbackData['company_name'] ?? $fallbackData['company'] ?? '',
            'about' => $fallbackData['about'] ?? '',
            'long_description' => $fallbackData['long_description'] ?? $fallbackData['description'] ?? '',
            'username' => $fallbackData['username'] ?? $fallbackData['partner_name'] ?? $fallbackData['username'] ?? ''
        ];

        $translatedData = [];

        // 6️⃣ Process each field with same logic as service translations
        foreach ($translatableFields as $field => $fallbackValue) {

            // Helper function to get the first non-empty value from translations
            $getFirstAvailableTranslation = function ($field) use ($allTranslations, $fallbackValue) {
                foreach ($allTranslations as $langData) {
                    if (!empty($langData[$field])) {
                        return $langData[$field];
                    }
                }
                return $fallbackValue; // fallback to main record
            };

            // Default value: default language or fallback to main table
            // IMPORTANT: When default language translation doesn't exist or is empty,
            // we should use the main table data (fallbackValue) instead of falling back
            // to other language translations. This ensures that when default language
            // is English but English translation doesn't exist, we use the English data
            // from the main table, not German or other language data.
            $defaultValue = $defaultTranslations[$field] ?? null;
            if ($defaultValue === null) {
                // Default language doesn't exist, use main table data (fallbackValue)
                // Don't fall back to other languages - main table contains default language data
                $defaultValue = $fallbackValue;
            } elseif (empty($defaultValue)) {
                // Default language exists but is empty, use main table data (fallbackValue)
                // Don't fall back to other languages - main table contains default language data
                $defaultValue = $fallbackValue;
            }

            // Requested value: Use requested language if it exists (even if empty), otherwise fallback
            if (isset($requestedTranslations[$field])) {
                // Requested language has this field (even if empty), use it
                $requestedValue = $requestedTranslations[$field];
            } else {
                // Requested language doesn't have this field, use default or first available
                $requestedValue = $defaultValue;
                if (empty($requestedValue)) {
                    $requestedValue = $getFirstAvailableTranslation($field);
                }
            }

            // Decode JSON fields if needed
            $defaultValue = decode_json_field($defaultValue, $field);
            $requestedValue = decode_json_field($requestedValue, $field);

            // Assign to API response following the same pattern as services
            $translatedData[$field] = $defaultValue;
            $translatedData["translated_$field"] = $requestedValue;
        }

        return $translatedData;
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Translation processing failed in get_translated_partner_data_for_api: ' . $e->getMessage());
        return [];
    }
}

/**
 * Apply translations to multiple services for API responses
 * 
 * @param array $services Array of services with their IDs
 * @return array Services with applied translations
 */
function apply_translations_to_services_for_api(array $services): array
{
    try {
        foreach ($services as &$service) {
            if (isset($service['id'])) {
                $translatedData = get_translated_service_data_for_api($service['id'], $service);

                // Merge translated data with original service data
                $service = array_merge($service, $translatedData);
                $service['translated_status'] = getTranslatedValue($service['status'], 'panel');
            }
        }

        return $services;
    } catch (\Exception $e) {
        log_message('error', 'Error applying translations to services: ' . $e->getMessage());
        return $services; // Return original data if translation fails
    }
}

/**
 * Apply translations to multiple partners for API responses
 * 
 * @param array $partners Array of partners with their IDs
 * @return array Partners with applied translations
 */
function apply_translations_to_partners_for_api(array $partners): array
{
    try {
        foreach ($partners as &$partner) {
            if (isset($partner['id'])) {
                $translatedData = get_translated_partner_data_for_api($partner['id'], $partner);

                // Merge translated data with original partner data
                $partner = array_merge($partner, $translatedData);
            }
        }

        return $partners;
    } catch (\Exception $e) {
        log_message('error', 'Error applying translations to partners: ' . $e->getMessage());
        return $partners; // Return original data if translation fails
    }
}

/**
 * Get translated category data for API responses
 * 
 * This function follows the same pattern as partner and service translations:
 * - Main field contains default language value (from translations table or main table as fallback)
 * - Translated field contains requested language value (empty if no translation exists)
 * 
 * @param int $categoryId Category ID
 * @param array $categoryData Original category data
 * @return array Category data with translated fields
 */
function get_translated_category_data_for_api(int $categoryId, array $categoryData, array $fields = ['name']): array
{
    try {
        $defaultLanguage = get_default_language();
        $requestedLanguage = get_current_language_from_request();

        // Load translation model
        $translationModel = new \App\Models\TranslatedCategoryDetails_model();

        // Get all available translations for this category
        $allTranslations = $translationModel->getAllTranslationsForCategory($categoryId);

        $translatedData = [];

        foreach ($fields as $field) {
            // Get base table value for this field (final fallback)
            // Check for base_name if field is 'name', otherwise use the field directly
            $baseTableValue = '';
            if ($field === 'name' && isset($categoryData['base_name'])) {
                $baseTableValue = $categoryData['base_name'];
            } else {
                $baseTableValue = $categoryData[$field] ?? '';
            }

            // 1. NAME field: Always contains default language data
            // Fallback chain: Default language translation -> Base table data
            $defaultValue = '';
            if (!empty($allTranslations[$defaultLanguage][$field])) {
                // Use default language translation if available
                $defaultValue = $allTranslations[$defaultLanguage][$field];
            } else {
                // Fallback to base table data
                $defaultValue = $baseTableValue;
            }
            $translatedData[$field] = $defaultValue;

            // 2. TRANSLATED_NAME field: Contains requested language data with proper fallback
            // Fallback chain: Requested language -> Default language -> Base table data
            $translatedValue = '';

            if ($requestedLanguage === $defaultLanguage) {
                // If requested language is default, use the same value as name
                $translatedValue = $defaultValue;
            } else {
                // Check if requested language translation exists
                if (!empty($allTranslations[$requestedLanguage][$field])) {
                    // Use requested language translation
                    $translatedValue = $allTranslations[$requestedLanguage][$field];
                } else {
                    // Fallback to default language translation
                    if (!empty($allTranslations[$defaultLanguage][$field])) {
                        $translatedValue = $allTranslations[$defaultLanguage][$field];
                    } else {
                        // Final fallback to base table data
                        $translatedValue = $baseTableValue;
                    }
                }
            }

            $translatedData['translated_' . $field] = $translatedValue;
        }

        return $translatedData;
    } catch (\Exception $e) {
        log_message('error', 'Translation processing failed in get_translated_category_data_for_api: ' . $e->getMessage());

        // Fallback: return base table data only
        $fallbackData = [];
        foreach ($fields as $field) {
            // Get base table value for this field
            $fallbackValue = '';
            if ($field === 'name' && isset($categoryData['base_name'])) {
                $fallbackValue = $categoryData['base_name'];
            } else {
                $fallbackValue = $categoryData[$field] ?? '';
            }
            $fallbackData[$field] = $fallbackValue;
            $fallbackData['translated_' . $field] = $fallbackValue;
        }

        return $fallbackData;
    }
}


/**
 * Apply translations to multiple categories for API responses
 * 
 * @param array $categories Array of categories with their IDs
 * @return array Categories with applied translations
 */
function apply_translations_to_categories_for_api(array $categories, array $fields = ['name']): array
{
    try {
        // Get all category IDs for batch fetching base table names (optimization)
        $categoryIds = [];
        foreach ($categories as $category) {
            if (isset($category['id'])) {
                $categoryIds[] = $category['id'];
            }
        }

        // Fetch base table names for all categories in one query (efficient batch operation)
        $baseTableNames = [];
        if (!empty($categoryIds)) {
            try {
                $db = \Config\Database::connect();
                $baseCategories = $db->table('categories')
                    ->select('id, name')
                    ->whereIn('id', $categoryIds)
                    ->get()
                    ->getResultArray();

                // Create a map of category ID to base table name
                foreach ($baseCategories as $baseCategory) {
                    $baseTableNames[$baseCategory['id']] = $baseCategory['name'] ?? '';
                }
            } catch (\Exception $e) {
                log_message('error', 'Error fetching base table names for categories: ' . $e->getMessage());
            }
        }

        // Process each category and apply translations with proper fallback
        foreach ($categories as &$category) {
            if (isset($category['id'])) {
                // Get base table name for this category (final fallback)
                $baseTableName = $baseTableNames[$category['id']] ?? ($category['name'] ?? '');

                // Prepare category data for translation function
                // Ensure base table name is available for fallback in translation function
                $categoryDataForTranslation = $category;
                // Store base table name so translation function can use it as final fallback
                if (!empty($baseTableName)) {
                    $categoryDataForTranslation['base_name'] = $baseTableName;
                    // Also ensure the 'name' field has base table value if empty
                    if (empty($categoryDataForTranslation['name'])) {
                        $categoryDataForTranslation['name'] = $baseTableName;
                    }
                }

                // Get translated data with fallback chain:
                // 1. Requested language translation
                // 2. Default language translation  
                // 3. Base table name (final fallback)
                $translatedData = get_translated_category_data_for_api($category['id'], $categoryDataForTranslation, $fields);

                // Merge translated data with original category data
                $category = array_merge($category, $translatedData);

                // Ensure translated_name follows proper fallback chain:
                // 1. Requested language (already set by get_translated_category_data_for_api)
                // 2. Default language (use 'name' field which contains default language)
                // 3. Base table name (final fallback)
                if (!isset($category['translated_name']) || empty($category['translated_name'])) {
                    // Fallback to default language name
                    if (!empty($category['name'])) {
                        $category['translated_name'] = $category['name'];
                    } else {
                        // Final fallback to base table name
                        $category['translated_name'] = $baseTableName;
                    }
                }

                // Ensure name field (default language) follows proper fallback:
                // 1. Default language translation (from translatedData)
                // 2. Base table name (final fallback)
                if (!isset($category['name']) || empty($category['name'])) {
                    $category['name'] = $baseTableName;
                }
            }
        }

        return $categories;
    } catch (\Exception $e) {
        log_message('error', 'Error applying translations to categories: ' . $e->getMessage());
        return $categories; // Return original data if translation fails
    }
}

/**
 * Update category names in database query results with translations
 * 
 * This function is used for database queries that fetch category names directly
 * and need to be updated with translations based on Content-Language header
 * 
 * @param array $data Array of data containing category information
 * @return array Updated data with translated category names
 */
function update_category_names_in_query_results(array $data): array
{
    try {
        // Update category names in the data using the helper function
        foreach ($data as &$item) {
            if (isset($item['category_id']) && !empty($item['category_id'])) {
                $categoryData = ['name' => $item['category_name'] ?? ''];
                $translatedCategoryData = get_translated_category_data_for_api($item['category_id'], $categoryData);
                $item['category_name'] = $translatedCategoryData['name'];
                $item['translated_category_name'] = $translatedCategoryData['translated_name'];
            }
        }

        return $data;
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Error updating category names in query results: ' . $e->getMessage());
        return $data; // Return original data if translation fails
    }
}

/**
 * Get current language from request headers or default to 'en'
 * 
 * @return string Language code
 */
function get_current_language_from_request(): string
{
    try {
        $request = \Config\Services::request();
        $contentLanguage = $request->getHeaderLine('Content-Language');

        if (!empty($contentLanguage)) {
            // Extract language code (e.g., "en-US" -> "en")
            $languageCode = explode('-', $contentLanguage)[0];
            $result = strtolower($languageCode);

            return $result;
        }

        return 'en'; // Default fallback
    } catch (\Exception $e) {
        log_message('error', 'Error getting current language from request: ' . $e->getMessage());
        return 'en'; // Default fallback
    }
}

/**
 * Get translated featured section data based on current language
 * 
 * @param array $sectionData Original section data
 * @param int $sectionId Section ID
 * @return array Section data with translated fields
 */
function get_translated_featured_section_data(array $sectionData, int $sectionId): array
{
    $currentLang = get_current_language();
    $defaultLangCode = get_default_language();

    // If current language is the default language, return original data
    if ($currentLang === $defaultLangCode) {
        return $sectionData;
    }

    try {
        // Get translated data for current language
        $translationModel = new \App\Models\TranslatedFeaturedSections_model();
        $translatedData = $translationModel->getTranslatedDetails($sectionId, $currentLang);

        if ($translatedData) {
            // Replace translatable fields with translated versions
            $sectionData['title'] = !empty($translatedData['title']) ? $translatedData['title'] : $sectionData['title'];
            $sectionData['description'] = !empty($translatedData['description']) ? $translatedData['description'] : $sectionData['description'];
        }
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Translation processing failed in get_translated_featured_section_data: ' . $e->getMessage());
    }

    return $sectionData;
}

/**
 * Get featured sections with translated names
 * 
 * This helper function fetches featured sections and applies translated names
 * based on the current language with fallback to main table
 * 
 * @param array $whereConditions Optional where conditions for filtering
 * @return array Array of featured sections with translated names
 */
function get_featured_sections_with_translated_names(array $whereConditions = []): array
{
    try {
        $sectionModel = new \App\Models\Featured_sections_model();

        // Get sections from main table
        $query = $sectionModel->select('id, title, description');

        if (!empty($whereConditions)) {
            $query = $query->where($whereConditions);
        }

        $sections = $query->findAll();

        if (empty($sections)) {
            return [];
        }

        // Get section IDs for batch translation lookup
        $sectionIds = array_column($sections, 'id');
        $translatedTitles = $sectionModel->getTranslatedSectionTitles($sectionIds);
        $translatedDescriptions = $sectionModel->getTranslatedSectionDescriptions($sectionIds);

        // Update section titles and descriptions with translations
        foreach ($sections as &$section) {
            if (isset($translatedTitles[$section['id']])) {
                $section['title'] = $translatedTitles[$section['id']];
            }
            if (isset($translatedDescriptions[$section['id']])) {
                $section['description'] = $translatedDescriptions[$section['id']];
            }
        }

        return $sections;
    } catch (\Exception $e) {
        log_message('error', 'Error fetching featured sections with translated names: ' . $e->getMessage());

        // Fallback to main table only
        $sectionModel = new \App\Models\Featured_sections_model();
        $query = $sectionModel->select('id, title, description');
        if (!empty($whereConditions)) {
            $query = $query->where($whereConditions);
        }
        return $query->findAll();
    }
}

/**
 * Get translated featured section data for API responses
 * 
 * This function processes featured section data for API responses,
 * applying translations based on the Content-Language header
 * 
 * @param int $sectionId Section ID
 * @param array $sectionData Original section data
 * @return array Processed section data with translations
 */
function get_translated_featured_section_data_for_api(int $sectionId, array $sectionData): array
{
    try {
        $defaultLanguage = get_default_language();
        $requestedLanguage = get_current_language_from_request();

        // Get default language translation
        $translationModel = new \App\Models\TranslatedFeaturedSections_model();
        $defaultTranslation = $translationModel->getTranslatedDetails($sectionId, $defaultLanguage);

        // Get requested language translation
        $requestedTranslation = null;
        if ($requestedLanguage !== $defaultLanguage) {
            $translationModel = new \App\Models\TranslatedFeaturedSections_model();
            $requestedTranslation = $translationModel->getTranslatedDetails($sectionId, $requestedLanguage);
        }

        // Initialize translated data
        $translatedData = [];
        $defaultTitle = '';
        $defaultDescription = '';
        $requestedTitle = '';
        $requestedDescription = '';

        // Get default language title and description
        if ($defaultTranslation) {
            if (!empty($defaultTranslation['title'])) {
                $defaultTitle = $defaultTranslation['title'];
            }
            if (!empty($defaultTranslation['description'])) {
                $defaultDescription = $defaultTranslation['description'];
            }
        } else {
            // Fallback to main table title and description
            $defaultTitle = $sectionData['title'] ?? '';
            $defaultDescription = $sectionData['description'] ?? '';
        }

        // Get requested language title and description
        if ($requestedTranslation) {
            if (!empty($requestedTranslation['title'])) {
                $requestedTitle = $requestedTranslation['title'];
            }
            if (!empty($requestedTranslation['description'])) {
                $requestedDescription = $requestedTranslation['description'];
            }
        }

        // Set the fields based on your requirement:
        // Main fields should always contain default language values
        // Translated fields should contain the requested language values
        $translatedData['title'] = $defaultTitle; // Always default language
        $translatedData['description'] = $defaultDescription; // Always default language

        // Set translated fields based on requested language
        if ($requestedLanguage === $defaultLanguage) {
            // If requested language is the same as default language
            $translatedData['translated_title'] = $defaultTitle; // Same as default language
            $translatedData['translated_description'] = $defaultDescription; // Same as default language
        } else {
            // If requested language is different from default language
            $translatedData['translated_title'] = $requestedTitle; // Requested language value (empty if no translation)
            $translatedData['translated_description'] = $requestedDescription; // Requested language value (empty if no translation)
        }

        return $translatedData;
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Translation processing failed in get_translated_featured_section_data_for_api: ' . $e->getMessage());

        // Return fallback data
        $defaultLanguage = get_default_language();
        $requestedLanguage = get_current_language_from_request();
        $fallbackTitle = $sectionData['title'] ?? '';
        $fallbackDescription = $sectionData['description'] ?? '';

        return [
            'title' => $fallbackTitle, // Always default language (from main table)
            'description' => $fallbackDescription, // Always default language (from main table)
            'translated_title' => ($requestedLanguage === $defaultLanguage) ? $fallbackTitle : '', // Requested language or empty
            'translated_description' => ($requestedLanguage === $defaultLanguage) ? $fallbackDescription : '' // Requested language or empty
        ];
    }
}

/**
 * Apply translations to multiple featured sections for API responses
 * 
 * @param array $sections Array of sections with their IDs
 * @return array Sections with applied translations
 */
function apply_translations_to_featured_sections_for_api(array $sections): array
{
    try {
        foreach ($sections as &$section) {
            if (isset($section['id'])) {
                $translatedData = get_translated_featured_section_data_for_api($section['id'], $section);

                // Merge translated data with original section data
                $section = array_merge($section, $translatedData);
            }
        }

        return $sections;
    } catch (\Exception $e) {
        log_message('error', 'Error applying translations to featured sections: ' . $e->getMessage());
        return $sections; // Return original data if translation fails
    }
}
/**
 * Get translated value for any key based on current language
 * 
 * @param string $key The key to translate
 * @param string $category Language file category (default: 'customer_app')
 * @return string Translated value
 */
function getTranslatedValue($key, $category = 'customer_app')
{
    try {
        helper('language');
        // Get current language from request header
        $languageCode = get_current_language_from_request();

        // Load language file for the specified category
        $languageData = load_language_file($languageCode, $category);

        // Return translated value if found
        if ($languageData && isset($languageData[$key])) {
            return $languageData[$key];
        }

        // Fallback to default English translations if not found
        $defaultLanguageData = load_language_file('en', $category);
        if ($defaultLanguageData && isset($defaultLanguageData[$key])) {
            return $defaultLanguageData[$key];
        }

        // Final fallback to original key with proper capitalization
        return ucfirst($key);
    } catch (\Exception $e) {
        log_message('error', 'Failed to get translated value for key "' . $key . '": ' . $e->getMessage());
        return ucfirst($key);
    }
}

/**
 * Get taxes with translated names based on current language
 * Fetches taxes from main table and joins with translations table to get localized tax titles
 * 
 * @param array $whereConditions Optional where conditions to filter taxes (e.g., ['status' => 1])
 * @param array $fields Optional specific fields to select (defaults to id, title, percentage)
 * @return array Array of taxes with translated titles for current language
 */
if (!function_exists('get_taxes_with_translated_names')) {
    function get_taxes_with_translated_names(array $whereConditions = ['status' => 1], array $fields = ['id', 'title', 'percentage']): array
    {
        try {
            $db = \Config\Database::connect();
            $currentLanguage = get_current_language();
            $defaultLanguage = get_default_language();

            // Build select statement for requested fields
            $selectFields = [];
            foreach ($fields as $field) {
                $selectFields[] = 't.' . $field;
            }

            // Add translated title to select if title is requested
            if (in_array('title', $fields)) {
                // Build COALESCE statement based on whether we need default language join
                if ($currentLanguage !== $defaultLanguage) {
                    // Try current language first, then default language, then original title
                    $selectFields[] = 'COALESCE(ttd_current.title, ttd_default.title, t.title) as title';
                } else {
                    // Only current language (which is same as default), then original title
                    $selectFields[] = 'COALESCE(ttd_current.title, t.title) as title';
                }
                // Remove duplicate title from array
                $selectFields = array_filter($selectFields, function ($field) {
                    return $field !== 't.title';
                });
            }

            $builder = $db->table('taxes t');
            $builder->select(implode(', ', $selectFields));

            // Left join with translations table for current language
            $builder->join(
                'translated_tax_details ttd_current',
                "ttd_current.tax_id = t.id AND ttd_current.language_code = " . $db->escape($currentLanguage),
                'left'
            );

            // Left join with translations table for default language (only if different from current)
            if ($currentLanguage !== $defaultLanguage) {
                $builder->join(
                    'translated_tax_details ttd_default',
                    "ttd_default.tax_id = t.id AND ttd_default.language_code = " . $db->escape($defaultLanguage),
                    'left'
                );
            }

            // Apply where conditions if provided
            if (!empty($whereConditions)) {
                foreach ($whereConditions as $key => $value) {
                    $builder->where('t.' . $key, $value);
                }
            }

            $taxes = $builder->get()->getResultArray();

            return $taxes;
        } catch (\Exception $e) {
            log_message('error', 'Error in get_taxes_with_translated_names: ' . $e->getMessage());

            // Fallback to simple fetch_details without translations
            return fetch_details('taxes', $whereConditions, $fields);
        }
    }
}

/**
 * Check if a user is blocked by another user
 * 
 * @param int $sender_id The ID of the user trying to send a message
 * @param int $receiver_id The ID of the user receiving the message
 * @return bool True if the sender is blocked by the receiver, false otherwise
 */
function is_user_blocked($sender_id, $receiver_id)
{
    try {
        // Check if the receiver has blocked the sender
        $blocked_by_receiver = fetch_details('user_reports', [
            'reporter_id' => $receiver_id,
            'reported_user_id' => $sender_id
        ], ['id']);

        // If there's a record, the sender is blocked by the receiver
        return !empty($blocked_by_receiver);
    } catch (\Exception $e) {
        log_message('error', 'Error in is_user_blocked: ' . $e->getMessage());
        // Return false on error to allow message sending (fail-safe approach)
        return false;
    }
}

/**
 * Get company title with proper language fallback
 * 
 * This function handles company title retrieval with the following priority:
 * 1. Current language translation (if available and not empty)
 * 2. Default language translation (if available and not empty)
 * 3. First available translation (if any translations exist)
 * 4. Old single language format (if company_title is a string)
 * 5. Final fallback to "eDemand"
 * 
 * @param array $settings General settings array
 * @param string|null $currentLanguage Optional current language code (if not provided, will get from session)
 * @return string Company title with proper fallback
 */
function get_company_title_with_fallback($settings, $currentLanguage = null)
{
    try {
        // Get current language from session if not provided
        if ($currentLanguage === null) {
            $session = \Config\Services::session();
            $currentLanguage = $session->get('language_code');
        }

        // If no current language, get default language
        if (!$currentLanguage) {
            $default_lang = fetch_details('languages', ['is_default' => 1], ['code']);
            $currentLanguage = !empty($default_lang) ? $default_lang[0]['code'] : 'en';
        }

        // Check if company_title is multilingual (array format)
        if (isset($settings['company_title']) && is_array($settings['company_title'])) {
            // New multilingual format - try current language first
            if (isset($settings['company_title'][$currentLanguage]) && !empty($settings['company_title'][$currentLanguage])) {
                return $settings['company_title'][$currentLanguage];
            }

            // Try default language as fallback
            $default_lang = fetch_details('languages', ['is_default' => 1], ['code']);
            $defaultLanguageCode = !empty($default_lang) ? $default_lang[0]['code'] : 'en';

            if (isset($settings['company_title'][$defaultLanguageCode]) && !empty($settings['company_title'][$defaultLanguageCode])) {
                return $settings['company_title'][$defaultLanguageCode];
            }

            // Fallback to first available translation
            foreach ($settings['company_title'] as $lang => $title) {
                if (!empty($title)) {
                    return $title;
                }
            }
        }
        // Check if company_title is old single string format
        else if (isset($settings['company_title']) && is_string($settings['company_title']) && !empty($settings['company_title'])) {
            return $settings['company_title'];
        }

        // Final fallback
        return "eDemand";
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Error in get_company_title_with_fallback: ' . $e->getMessage());

        // Return fallback on error
        return "eDemand";
    }
}

/**
 * Get translated names for services and partners in batch - OPTIMIZED
 * 
 * This function efficiently fetches translated service titles and partner company names
 * for multiple records in just 2 database queries, preventing N+1 query problems.
 * 
 * Translation fallback logic:
 * 1. Try current language translation
 * 2. Fallback to default language translation  
 * 3. Fallback to original data from main table
 * 
 * @param array $serviceIds Array of service IDs to get translations for
 * @param array $partnerIds Array of partner IDs to get translations for
 * @param string $currentLang Current language code (e.g., 'en', 'ar', 'tr')
 * @param string $defaultLang Default language code from database settings
 * @return array Array with 'services' and 'partners' keys containing translated names
 *               Format: ['services' => [service_id => translated_title], 'partners' => [partner_id => translated_name]]
 */
function get_batch_translated_names(array $serviceIds, array $partnerIds, string $currentLang, string $defaultLang): array
{
    $result = [
        'services' => [],
        'partners' => []
    ];

    try {
        // Get service translations in batch - OPTIMIZED single query
        if (!empty($serviceIds)) {
            $serviceModel = new \App\Models\TranslatedServiceDetails_model();
            $serviceTranslations = $serviceModel->getAllTranslationsForMultipleServices($serviceIds);

            // Process each service ID to get translated title
            foreach ($serviceIds as $serviceId) {
                $translatedTitle = null;

                // Try current language first
                if (
                    isset($serviceTranslations[$serviceId][$currentLang]['title']) &&
                    !empty(trim($serviceTranslations[$serviceId][$currentLang]['title']))
                ) {
                    $translatedTitle = trim($serviceTranslations[$serviceId][$currentLang]['title']);
                }
                // Fallback to default language
                elseif (
                    isset($serviceTranslations[$serviceId][$defaultLang]['title']) &&
                    !empty(trim($serviceTranslations[$serviceId][$defaultLang]['title']))
                ) {
                    $translatedTitle = trim($serviceTranslations[$serviceId][$defaultLang]['title']);
                }

                // Only set translation if we found a valid non-empty value
                // If null, the calling code will use the original data as fallback
                $result['services'][$serviceId] = $translatedTitle;
            }
        }

        // Get partner translations in batch - OPTIMIZED single query
        if (!empty($partnerIds)) {
            $partnerModel = new \App\Models\TranslatedPartnerDetails_model();
            $partnerTranslations = $partnerModel->getAllTranslationsForPartners($partnerIds);

            // Process each partner ID to get translated company name
            foreach ($partnerIds as $partnerId) {
                $translatedName = null;

                // Try current language first - check both username and company_name fields
                if (
                    isset($partnerTranslations[$partnerId][$currentLang]['company_name']) &&
                    !empty(trim($partnerTranslations[$partnerId][$currentLang]['company_name']))
                ) {
                    $translatedName = trim($partnerTranslations[$partnerId][$currentLang]['company_name']);
                } elseif (
                    isset($partnerTranslations[$partnerId][$currentLang]['username']) &&
                    !empty(trim($partnerTranslations[$partnerId][$currentLang]['username']))
                ) {
                    $translatedName = trim($partnerTranslations[$partnerId][$currentLang]['username']);
                }
                // Fallback to default language - check both username and company_name fields
                elseif (
                    isset($partnerTranslations[$partnerId][$defaultLang]['company_name']) &&
                    !empty(trim($partnerTranslations[$partnerId][$defaultLang]['company_name']))
                ) {
                    $translatedName = trim($partnerTranslations[$partnerId][$defaultLang]['company_name']);
                } elseif (
                    isset($partnerTranslations[$partnerId][$defaultLang]['username']) &&
                    !empty(trim($partnerTranslations[$partnerId][$defaultLang]['username']))
                ) {
                    $translatedName = trim($partnerTranslations[$partnerId][$defaultLang]['username']);
                }

                // Only set translation if we found a valid non-empty value
                // If null, the calling code will use the original data as fallback
                $result['partners'][$partnerId] = $translatedName;
            }
        }
    } catch (\Exception $e) {
        // Log error but don't break the function - graceful degradation
        log_message('error', 'Error in get_batch_translated_names: ' . $e->getMessage());
    }

    return $result;
}

function get_translated_partner_field($partnerId, $fieldName, $defaultValue = null)
{
    $currentLang = get_current_language();
    $defaultLang = get_default_language();

    if (empty($partnerId) || empty($fieldName)) {
        return $defaultValue;
    }

    $translationModel = new \App\Models\TranslatedPartnerDetails_model();
    $translations = $translationModel->getAllTranslationsForPartner($partnerId);

    if (!empty($translations)) {
        // Try current language first (must be non-empty)
        foreach ($translations as $item) {
            if ($item['language_code'] === $currentLang && isset($item[$fieldName]) && !empty($item[$fieldName])) {
                return $item[$fieldName];
            }
        }

        // Fallback to default language (must be non-empty)
        foreach ($translations as $item) {
            if ($item['language_code'] === $defaultLang && isset($item[$fieldName]) && !empty($item[$fieldName])) {
                return $item[$fieldName];
            }
        }
    }

    // Final fallback to base table data
    return $defaultValue;
}

/**
 * Get company name with default language fallback
 * Priority: default language translation → base table data
 * 
 * This function is used to get the company_name field which should always contain
 * the default language data. If default language translation is missing, it falls
 * back to the base table data.
 * 
 * @param int $partnerId Partner ID
 * @param string $baseCompanyName Company name from base table (partner_details)
 * @return string Company name in default language
 */
function get_company_name_with_default_language_fallback(int $partnerId, string $baseCompanyName): string
{
    // Get default language code
    $defaultLang = get_default_language();

    // If no partner ID or base company name, return empty string
    if (empty($partnerId) || empty($baseCompanyName)) {
        return $baseCompanyName ?? '';
    }

    try {
        // Get all translations for this partner
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
        $translations = $translationModel->getAllTranslationsForPartner($partnerId);

        // If no translations available, return base table data
        if (empty($translations)) {
            return $baseCompanyName;
        }

        // Look for default language translation
        foreach ($translations as $translation) {
            if (
                $translation['language_code'] === $defaultLang &&
                isset($translation['company_name']) &&
                !empty($translation['company_name'])
            ) {
                return $translation['company_name'];
            }
        }

        // Default language translation not found, fallback to base table data
        return $baseCompanyName;
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Error in get_company_name_with_default_language_fallback: ' . $e->getMessage());
        return $baseCompanyName;
    }
}

/**
 * Get translated company name with requested language fallback
 * Priority: requested language (from header) → default language translation → base table data
 * 
 * This function is used to get the translated_company_name field which should contain
 * the data for the requested language. If translation is not available for the requested
 * language, it falls back to default language translation. If default language translation
 * is missing, it falls back to the base table data.
 * 
 * @param int $partnerId Partner ID
 * @param string $baseCompanyName Company name from base table (partner_details)
 * @param string|null $requestedLanguage Requested language code (from header). If null, uses get_current_language_from_request()
 * @return string Translated company name with fallback
 */
function get_translated_company_name_with_fallback(int $partnerId, string $baseCompanyName, ?string $requestedLanguage = null): string
{
    // Get language codes
    $requestedLang = $requestedLanguage ?? get_current_language_from_request();
    $defaultLang = get_default_language();

    // If no partner ID or base company name, return empty string
    if (empty($partnerId) || empty($baseCompanyName)) {
        return $baseCompanyName ?? '';
    }

    try {
        // Get all translations for this partner
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
        $translations = $translationModel->getAllTranslationsForPartner($partnerId);

        // If no translations available, return base table data
        if (empty($translations)) {
            return $baseCompanyName;
        }

        $requestedTranslation = null;
        $defaultTranslation = null;

        // Loop through translations to find requested and default language translations
        foreach ($translations as $translation) {
            // Check for requested language translation
            if (
                $translation['language_code'] === $requestedLang &&
                isset($translation['company_name']) &&
                !empty($translation['company_name'])
            ) {
                $requestedTranslation = $translation['company_name'];
            }

            // Check for default language translation
            if (
                $translation['language_code'] === $defaultLang &&
                isset($translation['company_name']) &&
                !empty($translation['company_name'])
            ) {
                $defaultTranslation = $translation['company_name'];
            }
        }

        // Apply fallback chain: requested language → default language → base table
        if (!empty($requestedTranslation)) {
            return $requestedTranslation;
        }

        if (!empty($defaultTranslation)) {
            return $defaultTranslation;
        }

        // Final fallback to base table data
        return $baseCompanyName;
    } catch (\Exception $e) {
        // Log error but don't break the function
        log_message('error', 'Error in get_translated_company_name_with_fallback: ' . $e->getMessage());
        return $baseCompanyName;
    }
}
function getTranslatedSetting(string $key, string $field = ""): string
{
    $session = session();
    $currentLang = $session->get('lang') ?? 'en';

    // Pull the setting (with optional nested field if needed)
    $settings = get_settings($key, true);
    $value = $field ? ($settings[$field] ?? '') : ($settings[$key] ?? '');

    // Case 1: Old clients → plain HTML (last fallback for old single language data)
    if (is_string($value)) {
        return $value;
    }

    // Case 2: New clients → translations array with 4-tier fallback logic
    if (is_array($value)) {
        $defaultLang = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';

        // Tier 1: Current language translation (if available)
        if (!empty($value[$currentLang])) {
            return $value[$currentLang];
        }

        // Tier 2: Default language translation (if current language fails)
        if (!empty($value[$defaultLang])) {
            return $value[$defaultLang];
        }

        // Tier 3: First available translation (if default language fails)
        if (!empty($value)) {
            return reset($value);
        }
    }

    // Tier 4: If all above fail, try to get old single language data as last fallback
    // This handles cases where the new structure exists but is empty
    $oldValue = get_settings($key, true);
    if (is_string($oldValue)) {
        return $oldValue;
    }

    return '';
}

/**
 * Send subscription payment status notification to provider
 * 
 * Sends notification to the specific provider (and only that provider) when their 
 * subscription payment status changes (successful, failed, or pending). 
 * Notifications redirect to subscription screen.
 * 
 * The provider is determined from the transaction's user_id, ensuring notifications
 * are sent only to the provider who owns the subscription transaction.
 * 
 * @param int $transaction_id Transaction ID
 * @param string $status Payment status ('success', 'failed', 'pending')
 * @param string|null $failure_reason Optional failure reason for failed payments
 * @return void
 */
function send_subscription_payment_status_notification(int $transaction_id, string $status, ?string $failure_reason = null): void
{
    try {
        // Get transaction details
        $transaction = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction) || empty($transaction[0]['subscription_id'])) {
            // Not a subscription transaction, skip
            return;
        }

        $transaction_data = $transaction[0];
        $provider_id = $transaction_data['user_id'];
        $subscription_id = $transaction_data['subscription_id'];
        $amount = $transaction_data['amount'] ?? '0.00';
        $currency = $transaction_data['currency_code'] ?? get_settings('general_settings', true)['currency'] ?? 'USD';

        // Get subscription details
        $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($subscription_details)) {
            log_message('error', '[SUBSCRIPTION_PAYMENT_STATUS] Subscription not found: ' . $subscription_id);
            return;
        }

        $subscription_name = $subscription_details[0]['name'] ?? 'Subscription';

        // Get provider name with translation support
        $provider_name = get_translated_partner_field($provider_id, 'company_name');
        if (empty($provider_name)) {
            $partner_data = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
            $provider_name = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
        }

        // Determine event type based on status
        $eventType = match ($status) {
            'success' => 'subscription_payment_successful',
            'failed' => 'subscription_payment_failed',
            'pending' => 'subscription_payment_pending',
            default => null
        };

        if (!$eventType) {
            log_message('warning', '[SUBSCRIPTION_PAYMENT_STATUS] Unknown status: ' . $status);
            return;
        }

        // Prepare context data
        $context = [
            'provider_id' => $provider_id,
            'provider_name' => $provider_name,
            'subscription_id' => $subscription_id,
            'subscription_name' => $subscription_name,
            'amount' => number_format($amount, 2),
            'currency' => $currency,
            'transaction_id' => (string) $transaction_id
        ];

        // Add status-specific data
        if ($status === 'success') {
            // Get partner subscription details for dates
            $partner_subscription = fetch_details(
                table: 'partner_subscriptions',
                where: ['subscription_id' => $subscription_id, 'partner_id' => $provider_id, 'status' => 'active'],
                limit: 1,
                sort: 'id',
                order: 'DESC',
            );

            if (!empty($partner_subscription)) {
                $purchase_date = $partner_subscription[0]['purchase_date'] ?? date('Y-m-d');
                $expiry_date = $partner_subscription[0]['expiry_date'] ?? '';
                $context['purchase_date'] = !empty($purchase_date) ? date('d-m-Y', strtotime($purchase_date)) : '';
                $context['expiry_date'] = !empty($expiry_date) ? date('d-m-Y', strtotime($expiry_date)) : '';
            }
        } elseif ($status === 'failed') {
            $context['failure_reason'] = $failure_reason ?? 'Payment could not be processed. Please try again.';
        }

        // Queue notification to provider only (specific user_id)
        // Using user_ids in options ensures notification is sent only to this specific provider
        queue_notification_service(
            eventType: $eventType,
            recipients: [],
            context: $context,
            options: [
                'channels' => ['fcm', 'email', 'sms'],
                'user_ids' => [$provider_id], // Send only to this specific provider
                'platforms' => ['provider_panel', 'android', 'ios', 'web'],
                'type' => 'subscription_payment',
                'data' => [
                    'subscription_id' => (string) $subscription_id,
                    'transaction_id' => (string) $transaction_id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'redirect_to' => 'subscription_screen'
                ]
            ]
        );

        // log_message('info', '[SUBSCRIPTION_PAYMENT_STATUS] Notification queued for provider: ' . $provider_id . ', Status: ' . $status . ', Event: ' . $eventType);
    } catch (\Throwable $e) {
        // Log error but don't fail the transaction update
        log_message('error', '[SUBSCRIPTION_PAYMENT_STATUS] Stack trace: ' . $e->getTraceAsString());
    }
}

/**
 * Check if HTML content is effectively empty
 * 
 * This function determines if an HTML string contains no meaningful human-visible text.
 * It handles cases where the content only contains:
 * - Non-breaking spaces (&nbsp;)
 * - Regular whitespace
 * - Line breaks (<br> tags)
 * - Empty block tags (e.g., <p>&nbsp;</p>, <div></div>, <h3><br></h3>)
 * - Deeply nested empty structures
 * 
 * The function preserves legitimate styled HTML content (e.g., <h3>About our company</h3>)
 * and only returns true when there is truly no visible text content.
 * 
 * @param string|null $html The HTML string to check
 * @return bool True if the HTML is effectively empty, false otherwise
 * 
 * Examples:
 * - html_is_effectively_empty('<h3>&nbsp;</h3>') returns true
 * - html_is_effectively_empty('<div><span>&nbsp;</span></div>') returns true
 * - html_is_effectively_empty('<h2 style="text-align:center"><br></h2>') returns true
 * - html_is_effectively_empty('<h3>About our company</h3>') returns false
 * - html_is_effectively_empty('   &nbsp;  ') returns true
 */
function html_is_effectively_empty($html)
{
    // Return true if input is null or empty string
    if ($html === null || $html === '') {
        return true;
    }

    // Work with a copy to avoid modifying the original
    $processedHtml = $html;

    // First, remove all <br> and <br/> tags (case-insensitive, with optional attributes)
    // This handles <br>, <br/>, <BR>, <br style="...">, etc.
    $processedHtml = preg_replace('/<br\s*\/?>/i', '', $processedHtml);

    // Remove all non-breaking spaces (both entity form and character form)
    // We need to check both before and after entity decoding
    $processedHtml = str_replace(['&nbsp;', '&amp;nbsp;', "\xC2\xA0", "\xA0"], '', $processedHtml);

    // Convert HTML entities to their actual characters for easier processing
    // This helps us detect any remaining entities
    $processedHtml = html_entity_decode($processedHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove non-breaking space characters again after decoding
    $processedHtml = str_replace(["\xC2\xA0", "\xA0"], '', $processedHtml);

    // Remove all whitespace characters (spaces, tabs, newlines, etc.)
    // This normalizes the content for easier checking
    $processedHtml = preg_replace('/\s+/', '', $processedHtml);

    // Now we need to check if there are any actual text nodes left
    // We'll strip HTML tags and see if anything meaningful remains

    // First, let's try to remove empty block-level tags recursively
    // Common block tags that might be empty
    $blockTags = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'blockquote', 'pre', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot', 'span', 'strong', 'em', 'b', 'i', 'u'];

    // Keep removing empty tags until no more can be removed
    $previousHtml = '';
    $iterations = 0;
    $maxIterations = 10; // Prevent infinite loops

    while ($processedHtml !== $previousHtml && $iterations < $maxIterations) {
        $previousHtml = $processedHtml;

        // Remove empty block tags (with optional attributes)
        // Pattern matches: <tag>...</tag> where content is empty
        foreach ($blockTags as $tag) {
            $pattern = '/<' . preg_quote($tag, '/') . '(\s+[^>]*)?>\s*<\/' . preg_quote($tag, '/') . '>/i';
            $processedHtml = preg_replace($pattern, '', $processedHtml);

            // Also handle self-closing tags
            $pattern = '/<' . preg_quote($tag, '/') . '(\s+[^>]*)?\s*\/>/i';
            $processedHtml = preg_replace($pattern, '', $processedHtml);
        }

        // Remove nested empty structures like <div><span></span></div>
        // This pattern matches any tag that contains only whitespace or other empty tags
        $processedHtml = preg_replace('/<(\w+)(\s+[^>]*)?>\s*<\/\1>/i', '', $processedHtml);

        $iterations++;
    }

    // Remove all remaining HTML tags to check for actual text content
    // This preserves the text content while removing all markup
    $textOnly = strip_tags($processedHtml);

    // Remove any remaining whitespace, entities, or special characters
    $textOnly = trim($textOnly);
    $textOnly = preg_replace('/\s+/', '', $textOnly);

    // Remove common HTML entities that might remain (double-check)
    $textOnly = html_entity_decode($textOnly, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $textOnly = str_replace(["\xC2\xA0", "\xA0"], '', $textOnly);

    // If there's no text left after all processing, the HTML is effectively empty
    return empty($textOnly);
}

if (!function_exists('render_categories_options')) {
    function render_categories_options($categories, $parent_id = 0, $depth = 0, $selected_id = null)
    {
        $html = '';

        foreach ($categories as $category) {
            if ($category['parent_id'] == $parent_id) {

                $is_selected = ($category['id'] == $selected_id) ? 'selected' : '';

                // Force indentation using non-breaking spaces
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
                $prefix = $depth > 0 ? '— ' : '';

                $display_name = $indent . $prefix . htmlspecialchars($category['name']);

                $html .= sprintf(
                    '<option value="%s" %s>%s</option>',
                    htmlspecialchars($category['id']),
                    $is_selected,
                    $display_name
                );

                $html .= render_categories_options($categories, $category['id'], $depth + 1, $selected_id);
            }
        }

        return $html;
    }
}
/**
 * Helper function to handle variety of file types for custom job requests.
 * Accepts multiple files and validates them against Request Quote settings.
 *
 * @param array $files Array of UploadedFile objects
 * @param array $settings Request Quote Settings
 * @return array Result containing error status and uploaded file paths
 */
function upload_custom_job_request_files(array $files, array $settings): array
{
    $max_files = (int) ($settings['max_files_allowed'] ?? 5);
    if (count($files) > $max_files) {
        return [
            'error' => true,
            'message' => labels('upto_n_number_of_files_allowed', "Upto $max_files number of files allowed", [$max_files])
        ];
    }

    $uploaded_paths = [];
    $upload_path = 'public/uploads/custom_job_requests';

    foreach ($files as $file) {
        if (!$file->isValid()) {
            continue;
        }

        $mime = $file->getMimeType();
        $size = $file->getSizeByUnit('mb');

        // Determine file type and validate size
        $max_size = 0;
        if (str_starts_with($mime, 'image/')) {
            $max_size = (float) ($settings['max_file_size_images'] ?? 5);
        } elseif (str_starts_with($mime, 'video/')) {
            $max_size = (float) ($settings['max_file_size_video'] ?? 100);
        } else {
            $max_size = (float) ($settings['max_file_size_other'] ?? 10);
        }

        if ($size > $max_size) {
            return [
                'error' => true,
                'message' => labels('file_size_too_large', "File size is too large. Maximum allowed size is $max_size MB.")
            ];
        }

        // Upload the file
        $result = upload_file($file, $upload_path, "Error uploading file", 'custom_job_requests');
        if ($result['error']) {
            return $result;
        }

        $uploaded_paths[] = $result['file_path'];
    }

    return [
        'error' => false,
        'file_paths' => $uploaded_paths
    ];
}

if (!function_exists('validate_cancel_reason')) {
    /**
     * Validate cancel_reason_id + additional_info pair against the reasons table.
     *
     * @return array{error:bool, message?:string, additional_info?:?string}
     */
    function validate_cancel_reason($cancel_reason_id, $additional_info)
    {
        $cancelReasonModel = model('CancelReasonModel');

        $countCancelReasons = $cancelReasonModel->where('type', 'cancel')->countAllResults();

        if ($countCancelReasons > 0 && (empty($cancel_reason_id) || !is_numeric($cancel_reason_id))) {
            return [
                'error' => true,
                'message' => labels('cancel_reason_id_is_required', 'Cancellation reason is required'),
            ];
        }

        $reason = fetch_details('reasons', ['id' => (int) $cancel_reason_id, 'type' => 'cancel'], 'id, needs_additional_info');
        if (empty($reason) && !empty($cancel_reason_id)) {
            return [
                'error' => true,
                'message' => labels('invalid_reason_selected', 'Invalid reason selected.'),
            ];
        }

        $additional_info = is_string($additional_info) ? trim($additional_info) : '';

        if (!empty($reason) && (int) $reason[0]['needs_additional_info'] === 1 && $additional_info === '') {
            return [
                'error' => true,
                'message' => labels('additional_information_is_required_for_this_reason', 'Additional information is required for this reason'),
            ];
        }

        // if (mb_strlen($additional_info) > 500) {
        //     $additional_info = mb_substr($additional_info, 0, 500);
        // }

        return [
            'error' => false,
            'additional_info' => $additional_info === '' ? null : $additional_info,
        ];
    }
}

if (!function_exists('get_cancel_reasons_for_panel')) {
    /**
     * Fetch active cancel reasons with translation fallback for backend panels.
     * Returns: [['id' => int, 'reason' => string, 'needs_additional_info' => 0|1]]
     */
    function get_cancel_reasons_for_panel(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('reasons')) {
            return [];
        }

        $rows = $db->table('reasons')
            ->select('id, reason, needs_additional_info')
            ->where('type', 'cancel')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return [];
        }

        $translationsByReasonId = [];
        if ($db->tableExists('translated_reasons')) {
            $reasonIds = array_column($rows, 'id');
            $translations = $db->table('translated_reasons tr')
                ->select('tr.reason_id, tr.reason as translated_reason, l.code as language_code')
                ->join('languages l', 'l.id = tr.language_id')
                ->whereIn('tr.reason_id', $reasonIds)
                ->get()
                ->getResultArray();
            foreach ($translations as $t) {
                $translationsByReasonId[$t['reason_id']][$t['language_code']] = $t['translated_reason'];
            }
        }

        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        $result = [];
        foreach ($rows as $row) {
            $rid = (int) $row['id'];
            $resolved = $translationsByReasonId[$rid][$currentLang]
                ?? $translationsByReasonId[$rid][$defaultLang]
                ?? $row['reason'];
            $result[] = [
                'id' => $rid,
                'reason' => (string) $resolved,
                'needs_additional_info' => (int) $row['needs_additional_info'],
            ];
        }
        return $result;
    }
}