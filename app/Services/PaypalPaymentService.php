<?php

namespace App\Services;

/**
 * PaypalPaymentService — single source of truth for finalising PayPal
 * Orders v2 payments.
 *
 * Why this exists: the same "a PayPal payment succeeded / failed / was
 * refunded" logic is needed in three places — the customer return handler
 * (OrdersApiController::paypal_return), the provider return handler
 * (SubscriptionApiController::paypal_return) and the async webhook
 * (Webhooks\PaypalWebhook). Centralising it here keeps those write paths
 * identical and idempotent.
 *
 * Every method is keyed off an existing pending `transactions` row, which is
 * created up-front when the order is created. The row is self-describing:
 *   - subscription_id > 0            → provider subscription purchase
 *   - message = 'txn_additional_charges' → order additional-charge payment
 *   - otherwise                      → regular customer order payment
 *
 * All writes are idempotent: re-running a success on an already-success row
 * is a no-op, so a return-handler capture and a webhook for the same payment
 * never double-process.
 */
class PaypalPaymentService
{
    /** Float tolerance for money comparisons. */
    private const AMOUNT_TOLERANCE = 0.01;

    /** @var string Default language for notifications. */
    private string $defaultLanguage;

    public function __construct()
    {
        helper('function');
        $this->defaultLanguage = get_default_language();
    }

    // =================================================================
    // CHECKOUT CREATION
    // =================================================================

    /**
     * Create a PayPal Orders v2 checkout and return the buyer-facing approval URL.
     *
     * This is the single entry point every caller uses to start a PayPal
     * payment — customer order, customer additional charge, provider
     * subscription and the partner panel. It creates the PayPal order
     * server-side and stamps its id onto a pending `transactions` row, so no
     * public "webview" endpoint is needed: the caller hands the returned
     * `approval_url` straight to the client.
     *
     * @param array $p Keys:
     *   - transaction_id (int)    existing pending row to use; OR
     *   - txn_data       (array)  row passed to add_transaction() when no id
     *   - amount         (float)  payable amount (server-trusted; falls back to row amount)
     *   - currency       (string) optional; falls back to row / gateway currency
     *   - custom_id      (string) pipe-delimited payload echoed back by PayPal
     *   - item_name      (string)
     *   - invoice_prefix (string) e.g. 'order' | 'addl' | 'subs'
     *   - return_url, cancel_url (string)
     *
     * @return array ['error'=>bool, 'message'=>string, 'approval_url'=>string,
     *                'paypal_order_id'=>string, 'transaction_id'=>int]
     */
    public function createCheckout(array $p): array
    {
        $fail = static function (string $message): array {
            return [
                'error'           => true,
                'message'         => $message,
                'approval_url'    => '',
                'paypal_order_id' => '',
                'transaction_id'  => 0,
            ];
        };

        $paypal = new \App\Libraries\Paypal();
        if (!$paypal->is_enabled()) {
            return $fail('PayPal is not configured');
        }

        // Resolve the pending transactions row — use an existing one or create it.
        $transaction_id = (int) ($p['transaction_id'] ?? 0);
        if ($transaction_id <= 0) {
            if (empty($p['txn_data']) || !is_array($p['txn_data'])) {
                return $fail('Missing transaction context');
            }
            $transaction_id = (int) add_transaction($p['txn_data']);
        }
        if ($transaction_id <= 0) {
            return $fail('Unable to create transaction');
        }

        $row = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($row)) {
            return $fail('Transaction not found');
        }
        $row = $row[0];

        $amount = (float) ($p['amount'] ?? $row['amount'] ?? 0);
        if ($amount <= 0) {
            return $fail('Invalid amount');
        }

        $currency = (string) ($p['currency'] ?? '');
        if ($currency === '') {
            $currency = (string) ($row['currency_code'] ?? '');
        }
        if ($currency === '') {
            $currency = $paypal->get_currency();
        }

        $invoice_prefix = (string) ($p['invoice_prefix'] ?? 'txn');

        $order = $paypal->createOrder([
            'amount'     => $amount,
            'currency'   => $currency,
            'custom_id'  => (string) ($p['custom_id'] ?? ''),
            'invoice_id' => $invoice_prefix . '_' . $transaction_id . '_' . time(),
            'item_name'  => (string) ($p['item_name'] ?? 'Payment'),
            'return_url' => (string) ($p['return_url'] ?? ''),
            'cancel_url' => (string) ($p['cancel_url'] ?? ''),
        ]);

        $approval_url = $paypal->getApprovalUrl($order);
        if (!empty($order['error']) || empty($order['id']) || empty($approval_url)) {
            log_message('error', '[PAYPAL] createCheckout createOrder failed for txn=' . $transaction_id . ' :: ' . json_encode($order));
            return $fail('Unable to create PayPal checkout');
        }

        // Stamp the PayPal order id on the pending row — the return handler and
        // the webhook both resolve the payment by this reference.
        update_details(
            [
                'reference'     => $order['id'],
                'type'          => 'paypal',
                'amount'        => $amount,
                'currency_code' => strtoupper($currency),
            ],
            ['id' => $transaction_id],
            'transactions'
        );

        return [
            'error'           => false,
            'message'         => 'PayPal checkout created',
            'approval_url'    => $approval_url,
            'paypal_order_id' => (string) $order['id'],
            'transaction_id'  => $transaction_id,
        ];
    }

    // =================================================================
    // SUCCESS
    // =================================================================

    /**
     * Finalise a successful capture against a pending transactions row.
     *
     * @param array $txn     The pending `transactions` row.
     * @param array $capture Flattened capture details (see Paypal::getCaptureDetails()).
     *
     * @return string One of: processed | already | not_found | amount_mismatch | error
     */
    public function finalizeSuccess(array $txn, array $capture): string
    {
        if (empty($txn['id'])) {
            return 'not_found';
        }

        if (($txn['status'] ?? '') === 'success') {
            log_message('info', '[PAYPAL] txn=' . $txn['id'] . ' already success. Skipping.');
            return 'already';
        }

        if ((int) ($txn['subscription_id'] ?? 0) > 0) {
            return $this->finalizeSubscription($txn, $capture);
        }
        if (($txn['message'] ?? '') === 'txn_additional_charges') {
            return $this->finalizeAdditionalCharge($txn, $capture);
        }
        return $this->finalizeOrder($txn, $capture);
    }

    /**
     * Regular customer order payment.
     */
    private function finalizeOrder(array $txn, array $capture): string
    {
        $order_id = (int) ($txn['order_id'] ?? 0);
        if ($order_id <= 0) {
            log_message('error', '[PAYPAL] [order] txn=' . $txn['id'] . ' has no order_id. Skipping.');
            return 'error';
        }

        $order_data = fetch_details('orders', ['id' => $order_id]);
        if (empty($order_data)) {
            log_message('error', '[PAYPAL] [order] order=' . $order_id . ' not found. Skipping.');
            return 'not_found';
        }

        $expected_amount   = (float) ($order_data[0]['final_total'] ?? $order_data[0]['total'] ?? $txn['amount'] ?? 0);
        $expected_currency = (string) ($order_data[0]['currency_code'] ?? $txn['currency_code'] ?? '');

        if (!$this->validatePayment($capture['amount'], $capture['currency'], $expected_amount, $expected_currency, 'order ' . $order_id)) {
            return 'amount_mismatch';
        }

        $user_id            = (int) ($order_data[0]['user_id'] ?? 0);
        $partner_id         = (int) ($order_data[0]['partner_id'] ?? 0);
        $order_already_paid = ((int) ($order_data[0]['payment_status'] ?? 0)) === 1;
        $stored_currency    = $this->resolveCurrency($expected_currency, $capture['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            [
                'status'        => 'success',
                'txn_id'        => $capture['capture_id'],
                'amount'        => $capture['amount'],
                'currency_code' => $stored_currency,
            ],
            ['id' => $txn['id']],
            'transactions'
        );

        if (!$order_already_paid) {
            update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
            update_custom_job_status($order_id, 'booked');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[PAYPAL] [order] DB transaction failed. order=' . $order_id);
            return 'error';
        }

        log_message('info', '[PAYPAL] [order] txn=' . $txn['id'] . ' marked success. order=' . $order_id);

        if (!$order_already_paid) {
            delete_details(['user_id' => $user_id], 'cart');
            $this->sendBookingNotifications($order_id, $user_id, $partner_id, $order_data);
            $this->sendOnlinePaymentStatusNotification($order_id, $user_id, 'success', [
                'amount'         => $capture['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $capture['capture_id'],
                'paid_at'        => date('d-m-Y H:i:s'),
            ], $order_data);
        }

        return 'processed';
    }

    /**
     * Order additional-charge payment.
     */
    private function finalizeAdditionalCharge(array $txn, array $capture): string
    {
        $order_id = (int) ($txn['order_id'] ?? 0);
        if ($order_id <= 0) {
            log_message('error', '[PAYPAL] [additional_charge] txn=' . $txn['id'] . ' has no order_id. Skipping.');
            return 'error';
        }

        $order_data = fetch_details('orders', ['id' => $order_id]);
        if (empty($order_data)) {
            log_message('error', '[PAYPAL] [additional_charge] order=' . $order_id . ' not found. Skipping.');
            return 'not_found';
        }

        // Validate against the pending row's amount — what the client intended
        // and what createOrder() charged. orders.total_additional_charge can be cumulative.
        $expected_amount   = (float) ($txn['amount'] ?? 0);
        $expected_currency = (string) ($txn['currency_code'] ?? $order_data[0]['currency_code'] ?? '');

        if (!$this->validatePayment($capture['amount'], $capture['currency'], $expected_amount, $expected_currency, 'additional charge txn ' . $txn['id'])) {
            return 'amount_mismatch';
        }

        $user_id           = (int) ($order_data[0]['user_id'] ?? 0);
        $already_paid_flag = ((string) ($order_data[0]['payment_status_of_additional_charge'] ?? '')) === '1';
        $stored_currency   = $this->resolveCurrency($expected_currency, $capture['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            [
                'status'        => 'success',
                'txn_id'        => $capture['capture_id'],
                'amount'        => $capture['amount'],
                'currency_code' => $stored_currency,
            ],
            ['id' => $txn['id']],
            'transactions'
        );

        if (!$already_paid_flag) {
            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[PAYPAL] [additional_charge] DB transaction failed. order=' . $order_id);
            return 'error';
        }

        if (!$already_paid_flag) {
            $this->sendOnlinePaymentStatusNotification($order_id, $user_id, 'success', [
                'amount'         => $capture['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $capture['capture_id'],
                'paid_at'        => date('d-m-Y H:i:s'),
            ], $order_data);
        }

        return 'processed';
    }

    /**
     * Provider subscription purchase.
     */
    private function finalizeSubscription(array $txn, array $capture): string
    {
        $transaction_id  = (int) $txn['id'];
        $subscription_id = (int) ($txn['subscription_id'] ?? 0);
        $partner_id      = (int) ($txn['user_id'] ?? 0);

        $expected_amount   = (float) ($txn['amount'] ?? 0);
        $expected_currency = (string) ($txn['currency_code'] ?? '');

        if (!$this->validatePayment($capture['amount'], $capture['currency'], $expected_amount, $expected_currency, 'subscription txn ' . $transaction_id)) {
            return 'amount_mismatch';
        }

        $subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($subscription)) {
            log_message('error', '[PAYPAL] [subscription] subscription=' . $subscription_id . ' not found. Abort.');
            return 'not_found';
        }

        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $subscription[0]['duration'] ?? 0;
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
        $stored_currency      = $this->resolveCurrency($expected_currency, $capture['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            [
                'status'        => 'success',
                'txn_id'        => $capture['capture_id'],
                'currency_code' => $stored_currency,
            ],
            ['id' => $transaction_id],
            'transactions'
        );

        update_details(
            [
                'status'        => 'active',
                'is_payment'    => '1',
                'purchase_date' => $purchaseDate,
                'expiry_date'   => $expiryDate,
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'subscription_id' => $subscription_id,
                'partner_id'      => $partner_id,
                'status !='       => 'active',
                'transaction_id'  => $transaction_id,
            ],
            'partner_subscriptions'
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[PAYPAL] [subscription] DB transaction failed. txn=' . $transaction_id);
            return 'error';
        }

        log_message('info', '[PAYPAL] [subscription] txn=' . $transaction_id . ' activated. partner=' . $partner_id);

        // Provider-facing payment notification.
        send_subscription_payment_status_notification($transaction_id, 'success');

        // Admin-facing subscription-purchased notification.
        try {
            $provider_name = get_translated_partner_field($partner_id, 'company_name');
            if (empty($provider_name)) {
                $partner_data  = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                $provider_name = !empty($partner_data) ? $partner_data[0]['company_name'] : 'Provider';
            }

            $admin_currency = get_settings('general_settings', true)['currency'] ?? 'USD';

            queue_notification_service(
                eventType: 'subscription_purchased',
                recipients: [],
                context: [
                    'provider_id'       => $partner_id,
                    'provider_name'     => $provider_name,
                    'subscription_id'   => $subscription_id,
                    'subscription_name' => $subscription[0]['name'] ?? 'Subscription',
                    'purchase_date'     => date('d-m-Y', strtotime($purchaseDate)),
                    'expiry_date'       => date('d-m-Y', strtotime($expiryDate)),
                    'duration'          => $subscriptionDuration,
                    'amount'            => number_format($expected_amount, 2),
                    'currency'          => $admin_currency,
                    'transaction_id'    => (string) $transaction_id,
                ],
                options: [
                    'channels'    => ['fcm', 'email', 'sms'],
                    'user_groups' => [1],
                    'platforms'   => ['admin_panel'],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[PAYPAL] [subscription] notification exception: ' . $e->getMessage());
        }

        return 'processed';
    }

    // =================================================================
    // FAILURE
    // =================================================================

    /**
     * Mark a pending transaction (and its order / subscription) as failed.
     */
    public function finalizeFailure(array $txn, string $captureId = ''): string
    {
        if (empty($txn['id'])) {
            return 'not_found';
        }
        if (in_array(($txn['status'] ?? ''), ['success', 'failed'], true)) {
            return 'already';
        }

        // Subscription failure.
        if ((int) ($txn['subscription_id'] ?? 0) > 0) {
            update_details(
                ['status' => 'failed', 'txn_id' => $captureId],
                ['id' => $txn['id']],
                'transactions'
            );
            update_details(
                ['status' => 'deactive', 'is_payment' => '2'],
                [
                    'subscription_id' => (int) $txn['subscription_id'],
                    'partner_id'      => (int) ($txn['user_id'] ?? 0),
                    'transaction_id'  => (int) $txn['id'],
                    'status !='       => 'active',
                ],
                'partner_subscriptions'
            );
            send_subscription_payment_status_notification((int) $txn['id'], 'failed');
            return 'processed';
        }

        $order_id = (int) ($txn['order_id'] ?? 0);

        // Additional-charge failure: do not cancel the underlying order.
        if (($txn['message'] ?? '') === 'txn_additional_charges') {
            update_details(
                ['status' => 'failed', 'txn_id' => $captureId],
                ['id' => $txn['id']],
                'transactions'
            );
            if ($order_id > 0) {
                update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id], 'orders');
            }
            return 'processed';
        }

        // Regular order failure.
        $order_data              = $order_id > 0 ? fetch_details('orders', ['id' => $order_id]) : [];
        $order_already_paid      = !empty($order_data) && ((int) ($order_data[0]['payment_status'] ?? 0)) === 1;
        $order_already_cancelled = !empty($order_data)
            && (((int) ($order_data[0]['payment_status'] ?? 0)) === 2 || ($order_data[0]['status'] ?? '') === 'cancelled');

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            ['status' => 'failed', 'txn_id' => $captureId],
            ['id' => $txn['id']],
            'transactions'
        );

        if ($order_id > 0 && !$order_already_paid && !$order_already_cancelled) {
            update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $order_id], 'orders');
            update_custom_job_status($order_id, 'cancelled');
        }

        $db->transComplete();

        if ($order_id > 0 && !$order_already_paid && !$order_already_cancelled && !empty($order_data)) {
            $this->sendOnlinePaymentStatusNotification($order_id, (int) ($order_data[0]['user_id'] ?? 0), 'failed', [
                'amount'         => (float) ($txn['amount'] ?? 0),
                'currency'       => (string) ($txn['currency_code'] ?? ''),
                'transaction_id' => $captureId,
                'failure_reason' => 'Payment could not be processed.',
            ], $order_data);
        }

        return 'processed';
    }

    // =================================================================
    // REFUND
    // =================================================================

    /**
     * Record a refund against a previously successful PayPal transaction.
     *
     * @param array $params Keys: order_id, user_id, partner_id, capture_id
     *                      (original capture id), refund_id, amount, currency.
     */
    public function processRefund(array $params): string
    {
        $capture_id = (string) ($params['capture_id'] ?? '');
        if ($capture_id === '') {
            log_message('warning', '[PAYPAL] [refund] missing capture id. Skipping.');
            return 'not_found';
        }

        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'paypal',
            'status'           => 'success',
            'txn_id'           => $capture_id,
        ]);

        if (empty($success_transaction)) {
            log_message('warning', '[PAYPAL] [refund] no matching success txn for capture=' . $capture_id . '. Skipping.');
            return 'not_found';
        }

        $original   = $success_transaction[0];
        $order_id   = (int) ($params['order_id'] ?? $original['order_id'] ?? 0);
        $user_id    = (int) ($params['user_id'] ?? $original['user_id'] ?? 0);
        $partner_id = (int) ($params['partner_id'] ?? $original['partner_id'] ?? 0);
        $amount     = (float) ($params['amount'] ?? $original['amount'] ?? 0);
        $currency   = (string) ($params['currency'] ?: $original['currency_code'] ?? '');

        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'paypal',
            'message'          => 'txn_refund_processed',
            'txn_id'           => $capture_id,
        ]);

        if (!empty($existing_refund)) {
            if (($existing_refund[0]['status'] ?? '') === 'success') {
                log_message('info', '[PAYPAL] [refund] refund txn=' . $existing_refund[0]['id'] . ' already processed. Skipping.');
                return 'already';
            }
            update_details(['status' => 'success'], ['id' => $existing_refund[0]['id']], 'transactions');
            return 'processed';
        }

        $db = \Config\Database::connect();
        $db->transStart();

        add_transaction([
            'transaction_type' => 'refund',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'paypal',
            'txn_id'           => $capture_id,
            'amount'           => $amount,
            'status'           => 'success',
            'currency_code'    => $currency,
            'message'          => 'txn_refund_processed',
        ]);

        if ($order_id > 0) {
            update_custom_job_status($order_id, 'refunded');
        }

        $db->transComplete();

        return 'processed';
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function validatePayment(float $actual_amount, string $actual_currency, float $expected_amount, string $expected_currency, string $label): bool
    {
        if ($expected_amount > 0 && abs($actual_amount - $expected_amount) > self::AMOUNT_TOLERANCE) {
            log_message('error', '[PAYPAL] amount mismatch on ' . $label . ': received=' . $actual_amount . ' expected=' . $expected_amount . '. Aborting.');
            return false;
        }
        if ($expected_currency !== '' && $actual_currency !== '' && strcasecmp($actual_currency, $expected_currency) !== 0) {
            log_message('error', '[PAYPAL] currency mismatch on ' . $label . ': received=' . $actual_currency . ' expected=' . $expected_currency . '. Aborting.');
            return false;
        }
        return true;
    }

    private function resolveCurrency(string $base, string $gateway): string
    {
        if ($base !== '') {
            return strtoupper($base);
        }
        return $gateway !== '' ? strtoupper($gateway) : '';
    }

    private function removeOrderedServicesFromCart(int $order_id, int $user_id): void
    {
        if ($order_id <= 0 || $user_id <= 0) {
            return;
        }
        $order_services = fetch_details('order_services', ['order_id' => $order_id], ['service_id']);
        if (empty($order_services)) {
            return;
        }
        $service_ids = [];
        foreach ($order_services as $service_row) {
            $service_id = (int) ($service_row['service_id'] ?? 0);
            if ($service_id > 0) {
                $service_ids[] = $service_id;
            }
        }
        foreach (array_unique($service_ids) as $service_id) {
            delete_details(['user_id' => $user_id, 'service_id' => $service_id], 'cart');
        }
    }

    private function sendBookingNotifications(int $order_id, int $user_id, int $partner_id, array $order_data = []): void
    {
        try {
            if (empty($order_data)) {
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[PAYPAL] [notifications] order=' . $order_id . ' not found.');
                return;
            }

            $notificationContext = [
                'provider_id' => $partner_id,
                'user_id'     => $user_id,
                'booking_id'  => $order_id,
                'amount'      => $order_data[0]['total'] ?? 0,
            ];

            queue_notification_service(
                eventType: 'new_booking_received_for_provider',
                recipients: ['user_id' => $partner_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $this->defaultLanguage,
                    'platforms' => ['android', 'ios', 'web', 'provider_panel'],
                ]
            );

            queue_notification_service(
                eventType: 'new_booking_confirmation_to_customer',
                recipients: ['user_id' => $user_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $this->defaultLanguage,
                    'platforms' => ['android', 'ios', 'web'],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[PAYPAL] [notifications] order=' . $order_id . ' exception: ' . $e->getMessage());
        }
    }

    private function sendOnlinePaymentStatusNotification(int $order_id, int $user_id, string $status, array $payment_data = [], array $order_data = []): void
    {
        try {
            $event_type_map = [
                'success' => 'online_payment_success',
                'failed'  => 'online_payment_failed',
                'pending' => 'online_payment_pending',
            ];
            if (!isset($event_type_map[$status])) {
                log_message('error', '[PAYPAL] [payment-notification] invalid status=' . $status);
                return;
            }

            if (empty($order_data)) {
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[PAYPAL] [payment-notification] order=' . $order_id . ' not found.');
                return;
            }

            $customer_details = fetch_details('users', ['id' => $user_id], ['username', 'email']);
            $customer_name    = !empty($customer_details) ? $customer_details[0]['username'] : 'Customer';
            $customer_email   = !empty($customer_details) ? $customer_details[0]['email']    : '';

            $notificationContext = [
                'booking_id'     => (string) $order_id,
                'order_id'       => (string) $order_id,
                'amount'         => number_format($payment_data['amount'] ?? $order_data[0]['total'] ?? 0, 2),
                'currency'       => $payment_data['currency'] ?? $order_data[0]['currency_code'] ?? '',
                'transaction_id' => (string) ($payment_data['transaction_id'] ?? ''),
                'customer_id'    => (string) $user_id,
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'payment_method' => 'PayPal',
            ];

            if ($status === 'success') {
                $notificationContext['paid_at'] = $payment_data['paid_at'] ?? date('d-m-Y H:i:s');
            } elseif ($status === 'failed') {
                $notificationContext['failure_reason'] = $payment_data['failure_reason'] ?? 'Payment could not be processed.';
            }

            queue_notification_service(
                eventType: $event_type_map[$status],
                recipients: ['user_id' => $user_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $this->defaultLanguage,
                    'platforms' => ['android', 'ios', 'web'],
                    'type'      => 'payment',
                    'data'      => [
                        'order_id'       => (string) $order_id,
                        'booking_id'     => (string) $order_id,
                        'transaction_id' => (string) ($payment_data['transaction_id'] ?? ''),
                        'click_action'   => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to'    => 'booking_details_screen',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[PAYPAL] [payment-notification] order=' . $order_id . ' status=' . $status . ' exception: ' . $e->getMessage());
        }
    }
}
