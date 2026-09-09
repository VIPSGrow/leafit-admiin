<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Razorpay;

/**
 * Razorpay webhook controller. Idempotent against duplicate webhooks and the
 * customer/provider `add_transaction` APIs that insert a pending row before payment.
 * Validates webhook amount + currency against the order (or pending transaction).
 */
class RazorpayWebhook extends BaseController
{
    private Razorpay $razorpay;

    /** @var string Default language for notifications (no request context in webhooks) */
    protected $defaultLanguage;

    /** @var array General settings */
    protected $settings;

    /** Tolerance used when comparing money amounts (handles float rounding) */
    private const AMOUNT_TOLERANCE = 0.01;

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->razorpay = new Razorpay();
        $this->settings = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
        $this->defaultLanguage = get_default_language();
    }

    public function index()
    {
        $raw_body = file_get_contents('php://input');
        $request  = json_decode($raw_body, true);
        $event    = $request['event'] ?? 'unknown';

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            log_message('warning', '[RAZORPAY WEBHOOK] Rejected: method not allowed');
            return $this->response->setStatusCode(405)->setJSON(['error' => true, 'message' => 'Method not allowed']);
        }

        if (empty($request) || !is_array($request)) {
            log_message('error', '[RAZORPAY WEBHOOK] Rejected: invalid or empty JSON body');
            return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
        }

        log_message('info', '[RAZORPAY WEBHOOK] event=' . $event);

        // Signature verification intentionally disabled in this controller.

        $ctx = $this->parsePayloadContext($request);

        // ---- payment.authorized: capture immediately ----
        if ($event === 'payment.authorized') {
            log_message('info', '[RAZORPAY WEBHOOK] [payment.authorized] capturing txn=' . $ctx['txn_id']);
            $this->razorpay->capture_payment($ctx['amount'] * 100, $ctx['txn_id'], $ctx['currency'] ?: 'INR');
            return $this->okJson('Captured');
        }

        // ---- payment.captured: authoritative success event ----
        if ($event === 'payment.captured') {
            return $this->handlePaymentSuccess($event, $request, $ctx);
        }

        // ---- order.paid: duplicate of payment.captured for order-based payments.
        // Ack-only so we never run the success handler twice (avoids duplicate notifications
        // and double order updates). payment.captured is the source of truth.
        if ($event === 'order.paid') {
            log_message('info', '[RAZORPAY WEBHOOK] [order.paid] ack-only (handled by payment.captured). order=' . ($ctx['order_id'] ?? 0));
            return $this->okJson('order.paid ack-only');
        }

        // ---- payment.failed ----
        if ($event === 'payment.failed') {
            return $this->handlePaymentFailed($request, $ctx);
        }

        // ---- refund.processed ----
        if ($event === 'refund.processed') {
            $this->handleRefundProcessed($ctx);
            return $this->okJson('Processed');
        }

        // ---- refund.failed ----
        if ($event === 'refund.failed') {
            $refund_id      = $request['payload']['refund']['entity']['id']          ?? 'unknown';
            $failure_reason = $request['payload']['refund']['entity']['description'] ?? 'no reason provided';
            log_message('error', '[RAZORPAY WEBHOOK] [refund.failed] order=' . $ctx['order_id'] . ' refund=' . $refund_id . ' reason=' . $failure_reason);
            return $this->response->setStatusCode(200)->setBody('');
        }

        // Unhandled events: ack with 200 to stop retries.
        return $this->response->setStatusCode(200)->setBody('');
    }

    // =========================================================
    // Payload parsing
    // =========================================================

    /**
     * Pull everything the handlers need from the payload + DB lookups, ONCE.
     *
     * Returned keys: txn_id, amount, currency, transaction (row or []),
     * subscription_transaction_id (int|null), additional_charge_id (string|''),
     * order_id, user_id, partner_id, order_data (row or []).
     */
    private function parsePayloadContext(array $request): array
    {
        $payment = $request['payload']['payment']['entity'] ?? [];
        $txn_id  = (string) ($payment['id'] ?? '');
        $amount  = $txn_id !== '' ? ((float) ($payment['amount'] ?? 0)) / 100 : 0.0;
        $currency = (string) ($payment['currency'] ?? '');

        $payment_notes = $payment['notes'] ?? [];
        $order_notes   = $request['payload']['order']['entity']['notes'] ?? [];
        // Merge: payment notes win, but fall back to order notes (Razorpay does not always
        // mirror order-level notes onto the payment entity, e.g. older API behaviour).
        $notes = array_merge(is_array($order_notes) ? $order_notes : [], is_array($payment_notes) ? $payment_notes : []);

        $subscription_transaction_id = !empty($notes['transaction_id']) ? (int) $notes['transaction_id'] : null;
        $additional_charge_id        = (string) ($notes['additional_charges_transaction_id'] ?? '');

        // Razorpay order id (rzp_order_*) — stored on the pending transactions row as `reference`
        // when the client called razorpay_create_order. Primary lookup key for the row.
        $rzp_order_id = (string) ($payment['order_id'] ?? $request['payload']['order']['entity']['id'] ?? '');

        // Look up existing transaction. Prefer reference (rzp order id) as it's set at intent time;
        // fall back to txn_id (rzp payment id) for retries after the first webhook ran.
        $transaction = [];
        if ($rzp_order_id !== '') {
            $transaction = fetch_details('transactions', ['type' => 'razorpay', 'reference' => $rzp_order_id]);
        }
        if (empty($transaction) && $txn_id !== '') {
            $transaction = fetch_details('transactions', ['txn_id' => $txn_id]);
        }

        // Resolve order_id / user_id / partner_id.
        $order_id   = 0;
        $user_id    = 0;
        $partner_id = 0;
        $order_data = [];

        if (!empty($transaction) && !empty($transaction[0]['order_id'])) {
            $order_id   = (int) $transaction[0]['order_id'];
            $user_id    = (int) $transaction[0]['user_id'];
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (!empty($order_data)) {
                $user_id    = (int) $order_data[0]['user_id'];
                $partner_id = (int) $order_data[0]['partner_id'];
            }
        } elseif ($subscription_transaction_id === null) {
            $order_id = (int) ($request['payload']['order']['entity']['notes']['order_id']
                ?? $notes['order_id']
                ?? 0);
            if ($order_id > 0) {
                $order_data = fetch_details('orders', ['id' => $order_id]);
                if (!empty($order_data)) {
                    $user_id    = (int) $order_data[0]['user_id'];
                    $partner_id = (int) $order_data[0]['partner_id'];
                }
            }
        }

        // Derive additional_charge_id from the matched pending row when notes did not carry it
        // (older clients, additional charges flow stamped `reference` via razorpay_create_order).
        if ($additional_charge_id === '' && !empty($transaction) && ($transaction[0]['message'] ?? '') === 'txn_additional_charges') {
            $additional_charge_id = (string) $transaction[0]['id'];
        }

        return [
            'txn_id'                      => $txn_id,
            'amount'                      => $amount,
            'currency'                    => $currency,
            'rzp_order_id'                => $rzp_order_id,
            'transaction'                 => $transaction,
            'subscription_transaction_id' => $subscription_transaction_id,
            'additional_charge_id'        => $additional_charge_id,
            'order_id'                    => $order_id,
            'user_id'                     => $user_id,
            'partner_id'                  => $partner_id,
            'order_data'                  => $order_data,
        ];
    }

    // =========================================================
    // payment.captured / order.paid
    // =========================================================

    private function handlePaymentSuccess(string $event, array $request, array $ctx)
    {
        // Subscription path
        if ($ctx['subscription_transaction_id'] !== null) {
            $this->handleSubscriptionPayment($ctx['subscription_transaction_id'], $ctx['txn_id'], $ctx['amount'], $ctx['currency']);
            return $this->okJson('Processed subscription');
        }

        if (!empty($ctx['transaction']) && in_array($ctx['transaction'][0]['status'] ?? '', ['success', 'failed'], true)) {
            log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] txn=' . $ctx['transaction'][0]['id'] . ' already ' . $ctx['transaction'][0]['status'] . '. Skipping.');
            return $this->okJson('Already processed');
        }

        // Additional charge path
        if ($ctx['additional_charge_id'] !== '') {
            $this->handleAdditionalChargeSuccess($ctx);
            return $this->okJson('Processed additional charge');
        }

        // Regular order path: pull updated context (for order.paid, receipt may carry a different order_id)
        if ($event === 'order.paid') {
            $receipt_order_id = (int) ($request['payload']['order']['entity']['receipt'] ?? $ctx['order_id']);
            if ($receipt_order_id > 0 && $receipt_order_id !== $ctx['order_id']) {
                log_message('info', '[RAZORPAY WEBHOOK] [order.paid] receipt order=' . $receipt_order_id . ' overrides parsed order=' . $ctx['order_id']);
                $ctx['order_id']   = $receipt_order_id;
                $ctx['order_data'] = fetch_details('orders', ['id' => $receipt_order_id]);
                if (!empty($ctx['order_data'])) {
                    $ctx['user_id']    = (int) $ctx['order_data'][0]['user_id'];
                    $ctx['partner_id'] = (int) $ctx['order_data'][0]['partner_id'];
                }
            }
        }

        if (empty($ctx['order_id'])) {
            log_message('error', '[RAZORPAY WEBHOOK] [' . $event . '] order_id missing. txn=' . $ctx['txn_id']);
            return $this->okJson('No order id');
        }

        if (empty($ctx['order_data'])) {
            log_message('warning', '[RAZORPAY WEBHOOK] [' . $event . '] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return $this->okJson('Order not found');
        }

        // Resolve expected amount fields. Razorpay is charged orders.final_total for regular
        // bookings (subtotal + tax/fees); orders.total is the pre-fee subtotal and must NOT
        // be used for validation. Fall back to total when final_total is empty (legacy rows).
        $order_final_total      = (float) ($ctx['order_data'][0]['final_total'] ?? 0);
        $order_total            = (float) ($ctx['order_data'][0]['total'] ?? 0);
        $order_additional_total = (float) ($ctx['order_data'][0]['total_additional_charge'] ?? 0);

        $amount_matches_final            = $order_final_total > 0 && abs($ctx['amount'] - $order_final_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_total            = $order_total > 0 && abs($ctx['amount'] - $order_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_additional_total = $order_additional_total > 0 && abs($ctx['amount'] - $order_additional_total) <= self::AMOUNT_TOLERANCE;

        // Fallback: when notes lacked additional_charges_transaction_id and the matched pending
        // row carries the additional-charge marker, route to additional-charge handler.
        if (!$amount_matches_final && !$amount_matches_total && $amount_matches_additional_total) {
            $pending_additional = $this->findPendingAdditionalChargeForOrder($ctx['order_id']);
            if (!empty($pending_additional)) {
                $ctx['additional_charge_id'] = (string) $pending_additional['id'];
                log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] inferred additional charge txn=' . $ctx['additional_charge_id'] . ' for order=' . $ctx['order_id']);
                $this->handleAdditionalChargeSuccess($ctx);
                return $this->okJson('Processed additional charge');
            }
        }

        // Amount / currency validation against orders.final_total (charged amount) with
        // a fallback to orders.total for legacy rows where final_total is not populated.
        $expected_amount   = $order_final_total > 0 ? $order_final_total : $order_total;
        $expected_currency = (string) ($ctx['order_data'][0]['currency_code'] ?? '');
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'order ' . $ctx['order_id'])) {
            log_message('error', '[RAZORPAY WEBHOOK] [' . $event . '] order=' . $ctx['order_id']
                . ' totals: final_total=' . $order_final_total
                . ' total=' . $order_total
                . ' total_additional_charge=' . $order_additional_total);
            return $this->okJson('Amount or currency mismatch');
        }

        // Update-only path: pending row was inserted by razorpay_create_order with reference set.
        // If the row is missing, the client never recorded payment intent — log and ack.
        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingRazorpayTransactionForOrder($ctx['order_id']);

        if (empty($existing)) {
            log_message('error', '[RAZORPAY WEBHOOK] [' . $event . '] no pending razorpay row for order=' . $ctx['order_id']
                . ' rzp_order=' . ($ctx['rzp_order_id'] ?? '') . ' txn=' . $ctx['txn_id']
                . '. Intent never recorded — skipping insert. Reconcile manually.');
            return $this->okJson('No pending intent row');
        }

        if ($existing['status'] === 'success') {
            log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] txn=' . $existing['id'] . ' already success. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_paid = ((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 1;
        $stored_currency    = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            ['status' => 'success', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'amount' => $ctx['amount']],
            ['id' => $existing['id']],
            'transactions'
        );
        log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] txn=' . $existing['id'] . ' marked success. order=' . $ctx['order_id']);

        if (!$order_already_paid) {
            update_details(['payment_status' => 1], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'booked');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[RAZORPAY WEBHOOK] [' . $event . '] DB transaction failed. order=' . $ctx['order_id']);
            return $this->okJson('DB error logged');
        }

        // Notifications only on the transition.
        if (!$order_already_paid) {
            delete_details(['user_id' => $ctx['user_id']], 'cart');
            $this->send_booking_notifications($ctx['order_id'], $ctx['user_id'], $ctx['partner_id'], $ctx['order_data']);
        } else {
            log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] order=' . $ctx['order_id'] . ' already paid. Skipped notifications.');
        }

        return $this->okJson('Processed');
    }

    private function handleAdditionalChargeSuccess(array $ctx): void
    {
        $charge_id = $ctx['additional_charge_id'];

        if (empty($ctx['order_data'])) {
            log_message('warning', '[RAZORPAY WEBHOOK] [additional_charge] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return;
        }

        $existing = fetch_details('transactions', ['id' => $charge_id]);
        if (empty($existing)) {
            log_message('warning', '[RAZORPAY WEBHOOK] [additional_charge] txn=' . $charge_id . ' not found. Skipping.');
            return;
        }
        if ($existing[0]['status'] === 'success') {
            log_message('info', '[RAZORPAY WEBHOOK] [additional_charge] txn=' . $charge_id . ' already success. Skipping.');
            return;
        }

        // Validate against the pending row's amount — that's what the client intended and what
        // razorpay_create_order charged. orders.total_additional_charge may be cumulative/stale.
        $expected_amount   = (float) ($existing[0]['amount'] ?? 0);
        $expected_currency = (string) ($existing[0]['currency_code'] ?? '');
        if ($expected_currency === '') {
            $expected_currency = (string) ($ctx['order_data'][0]['currency_code'] ?? '');
        }
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'additional charge txn ' . $charge_id . ' for order ' . $ctx['order_id'])) {
            return;
        }

        $already_paid_flag = ((string) ($ctx['order_data'][0]['payment_status_of_additional_charge'] ?? '')) === '1';
        log_message('info', '[RAZORPAY WEBHOOK] [additional_charge] order=' . $ctx['order_id']
            . ' payment_status_of_additional_charge before update=' . ($ctx['order_data'][0]['payment_status_of_additional_charge'] ?? 'NULL')
            . ' already_paid_flag=' . ($already_paid_flag ? 'true' : 'false'));

        $stored_currency = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        // Each update_details call manages its own DB transaction; avoid an outer wrapper
        // since nested transStart/transComplete can roll the chain back if any inner unit
        // reports a non-success status.
        $txn_update = update_details(
            ['status' => 'success', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'amount' => $ctx['amount']],
            ['id' => $charge_id],
            'transactions'
        );
        log_message('info', '[RAZORPAY WEBHOOK] [additional_charge] transactions update result=' . ($txn_update ? 'true' : 'false') . ' for txn=' . $charge_id);

        if (!$already_paid_flag) {
            $order_update = update_details(['payment_status_of_additional_charge' => '1'], ['id' => $ctx['order_id']], 'orders');
            log_message('info', '[RAZORPAY WEBHOOK] [additional_charge] orders update result=' . ($order_update ? 'true' : 'false') . ' for order=' . $ctx['order_id']);
        }

        if (!$already_paid_flag) {
            $this->send_online_payment_status_notification(
                $ctx['order_id'],
                $ctx['user_id'],
                'success',
                [
                    'amount'         => $ctx['amount'],
                    'currency'       => $stored_currency,
                    'transaction_id' => $ctx['txn_id'],
                    'payment_method' => 'Razorpay',
                    'paid_at'        => date('d-m-Y H:i:s'),
                ],
                $ctx['order_data']
            );
        }
    }

    // =========================================================
    // payment.failed
    // =========================================================

    private function handlePaymentFailed(array $request, array $ctx)
    {
        $failure_reason = $request['payload']['payment']['entity']['error_description'] ?? 'Payment could not be processed.';
        log_message('warning', '[RAZORPAY WEBHOOK] [payment.failed] order=' . $ctx['order_id'] . ' txn=' . $ctx['txn_id'] . ' reason=' . $failure_reason);

        // Additional charge failed
        if ($ctx['additional_charge_id'] !== '') {
            $charge_id = $ctx['additional_charge_id'];
            $existing  = fetch_details('transactions', ['id' => $charge_id]);
            if (!empty($existing) && $existing[0]['status'] === 'failed') {
                log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] additional_charge txn=' . $charge_id . ' already failed. Skipping.');
                return $this->okJson('Already processed');
            }

            $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
            $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

            $db = \Config\Database::connect();
            $db->transStart();
            update_details(
                ['status' => 'failed', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency],
                ['id' => $charge_id],
                'transactions'
            );
            update_details(['payment_status_of_additional_charge' => '2'], ['id' => $ctx['order_id']], 'orders');
            $db->transComplete();

            if (!empty($ctx['order_data'])) {
                $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'failed', [
                    'amount'         => $ctx['amount'],
                    'currency'       => $stored_currency,
                    'transaction_id' => $ctx['txn_id'],
                    'payment_method' => 'Razorpay',
                    'failure_reason' => $failure_reason,
                ], $ctx['order_data']);
            }
            return $this->okJson('Processed');
        }

        if (empty($ctx['order_id'])) {
            log_message('warning', '[RAZORPAY WEBHOOK] [payment.failed] order_id missing. txn=' . $ctx['txn_id']);
            return $this->okJson('No order id');
        }

        // Idempotency: existing failed transaction OR cancelled order → skip.
        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingRazorpayTransactionForOrder($ctx['order_id']);

        if (!empty($existing) && $existing['status'] === 'failed') {
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] txn=' . $existing['id'] . ' already failed. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_cancelled = !empty($ctx['order_data'])
            && (((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 2
                || ($ctx['order_data'][0]['status'] ?? '') === 'cancelled');

        $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        if (empty($existing)) {
            log_message('error', '[RAZORPAY WEBHOOK] [payment.failed] no pending razorpay row for order=' . $ctx['order_id']
                . ' rzp_order=' . ($ctx['rzp_order_id'] ?? '') . ' txn=' . $ctx['txn_id']
                . '. Intent never recorded — skipping insert.');
            return $this->okJson('No pending intent row');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        if (!$order_already_cancelled) {
            update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'cancelled');
        }

        update_details(
            ['status' => 'failed', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency],
            ['id' => $existing['id']],
            'transactions'
        );

        $db->transComplete();

        if (!$order_already_cancelled) {
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'failed', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['txn_id'],
                'payment_method' => 'Razorpay',
                'failure_reason' => $failure_reason,
            ], $ctx['order_data']);
        }

        return $this->okJson('Processed');
    }

    // =========================================================
    // Subscription
    // =========================================================

    private function handleSubscriptionPayment(int $transaction_id, string $txn_id, float $amount, string $currency): void
    {
        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[RAZORPAY WEBHOOK] [subscription] txn=' . $transaction_id . ' not found. Abort.');
            return;
        }

        if ($transaction_details[0]['status'] === 'success') {
            log_message('info', '[RAZORPAY WEBHOOK] [subscription] txn=' . $transaction_id . ' already success. Skipping.');
            return;
        }

        $subscription_id = (int) $transaction_details[0]['subscription_id'];
        $partner_id      = (int) $transaction_details[0]['user_id'];

        // Amount validation against the stored (pending) transaction row.
        $expected_amount   = (float) ($transaction_details[0]['amount'] ?? 0);
        $stored_currency   = (string) ($transaction_details[0]['currency_code'] ?? '');
        if (!$this->validatePayment($amount, $currency, $expected_amount, $stored_currency, 'subscription transaction ' . $transaction_id)) {
            return;
        }

        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($details_for_subscription)) {
            log_message('error', '[RAZORPAY WEBHOOK] [subscription] subscription=' . $subscription_id . ' not found. Abort.');
            return;
        }

        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $details_for_subscription[0]['duration'] ?? 0;
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
        if ($subscriptionDuration === 'unlimited') {
            $subscriptionDuration = 0;
        }

        $store_currency_value = $this->resolveStoredCurrency($stored_currency, $currency);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            ['status' => 'success', 'txn_id' => $txn_id, 'currency_code' => $store_currency_value],
            ['id' => $transaction_id],
            'transactions'
        );

        $update_result = update_details(
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

        send_subscription_payment_status_notification($transaction_id, 'success');

        if (!$update_result) {
            log_message('warning', '[RAZORPAY WEBHOOK] [subscription] partner_subscriptions update affected 0 rows. partner=' . $partner_id . ' subscription=' . $subscription_id . ' txn=' . $transaction_id);
            return;
        }

        $provider_name = get_translated_partner_field($partner_id, 'company_name');
        if (empty($provider_name)) {
            $partner_data  = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
            $provider_name = !empty($partner_data) ? $partner_data[0]['company_name'] : 'Provider';
        }

        $subscription_name = $details_for_subscription[0]['name'] ?? 'Subscription';
        $admin_currency    = get_settings('general_settings', true)['currency'] ?? 'USD';

        queue_notification_service(
            eventType: 'subscription_purchased',
            recipients: [],
            context: [
                'provider_id'       => $partner_id,
                'provider_name'     => $provider_name,
                'subscription_id'   => $subscription_id,
                'subscription_name' => $subscription_name,
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
    }

    // =========================================================
    // refund.processed
    // =========================================================

    private function handleRefundProcessed(array $ctx): void
    {
        $txn_id     = $ctx['txn_id'];
        $amount     = $ctx['amount'];
        $currency   = $ctx['currency'];
        $order_id   = $ctx['order_id'];
        $user_id    = $ctx['user_id'];
        $partner_id = $ctx['partner_id'];

        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'razorpay',
            'status'           => 'success',
            'txn_id'           => $txn_id,
        ]);

        if (empty($success_transaction)) {
            log_message('warning', '[RAZORPAY WEBHOOK] [refund.processed] no matching success txn for gateway_id=' . $txn_id . '. Skipping.');
            return;
        }

        $already_exist_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'razorpay',
            'message'          => 'txn_refund_processed',
            'txn_id'           => $txn_id,
        ]);

        if (!empty($already_exist_refund)) {
            if ($already_exist_refund[0]['status'] === 'processed') {
                log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] refund txn=' . $already_exist_refund[0]['id'] . ' already processed. Skipping.');
                return;
            }
            update_details(['status' => 'processed'], ['id' => $already_exist_refund[0]['id']], 'transactions');
            return;
        }

        // Use stored currency from the original successful transaction when available.
        $stored_currency = (string) ($success_transaction[0]['currency_code'] ?? '');
        if ($stored_currency === '') {
            $order_data      = $order_id > 0 ? fetch_details('orders', ['id' => $order_id]) : [];
            $base_currency   = !empty($order_data) ? (string) $order_data[0]['currency_code'] : '';
            $stored_currency = $this->resolveStoredCurrency($base_currency, $currency);
        }

        $db = \Config\Database::connect();
        $db->transStart();
        add_transaction([
            'transaction_type' => 'refund',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'razorpay',
            'txn_id'           => $txn_id,
            'amount'           => $amount,
            'status'           => 'processed',
            'currency_code'    => $stored_currency,
            'message'          => 'txn_refund_processed',
        ]);
        update_custom_job_status($order_id, 'refunded');
        $db->transComplete();
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Fallback lookup: latest razorpay transaction row for this order.
     * Used when webhook txn_id is not yet linked (because the API path inserted a pending row first).
     */
    /**
     * Look up the most recent pending additional-charge transaction row for an order.
     * Used when the webhook payload does not carry `additional_charges_transaction_id`
     * in notes but the captured amount matches `orders.total_additional_charge`.
     */
    private function findPendingAdditionalChargeForOrder(int $order_id): array
    {
        if ($order_id <= 0) {
            return [];
        }
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'transaction_type' => 'transaction',
                'status'           => 'pending',
                'message'          => 'txn_additional_charges',
            ],
            [],
            1,
            0,
            'id',
            'DESC'
        );
        if (!empty($rows)) {
            return $rows[0];
        }
        // Fallback: any pending razorpay transaction for the order when message column differs.
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'transaction_type' => 'transaction',
                'type'             => 'razorpay',
                'status'           => 'pending',
            ],
            [],
            1,
            0,
            'id',
            'DESC'
        );
        return !empty($rows) ? $rows[0] : [];
    }

    private function findExistingRazorpayTransactionForOrder(int $order_id): array
    {
        if ($order_id <= 0) {
            return [];
        }
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'type'             => 'razorpay',
                'transaction_type' => 'transaction',
            ],
            [],
            1,
            0,
            'id',
            'DESC'
        );
        return !empty($rows) ? $rows[0] : [];
    }

    /**
     * Compare webhook amount + currency against expected values.
     * Returns true if OK (also true when expected is missing — nothing to compare against).
     * Logs and returns false on mismatch.
     */
    private function validatePayment(float $actual_amount, string $actual_currency, float $expected_amount, string $expected_currency, string $label): bool
    {
        if ($expected_amount > 0 && abs($actual_amount - $expected_amount) > self::AMOUNT_TOLERANCE) {
            log_message('error', '[RAZORPAY WEBHOOK] amount mismatch on ' . $label . ': received=' . $actual_amount . ' expected=' . $expected_amount . '. Aborting.');
            return false;
        }
        if ($expected_currency !== '' && $actual_currency !== '' && strcasecmp($actual_currency, $expected_currency) !== 0) {
            log_message('error', '[RAZORPAY WEBHOOK] currency mismatch on ' . $label . ': received=' . $actual_currency . ' expected=' . $expected_currency . '. Aborting.');
            return false;
        }
        return true;
    }

    private function okJson(string $message)
    {
        return $this->response->setJSON(['error' => false, 'message' => $message]);
    }

    /**
     * Pick the currency code to persist on the transaction row.
     * Prefers the order's base currency when set; else normalises the gateway value to uppercase ISO.
     */
    private function resolveStoredCurrency(string $base, string $gateway): string
    {
        if ($base !== '') {
            return strtoupper($base);
        }
        return $gateway !== '' ? strtoupper($gateway) : '';
    }

    /**
     * Send booking notifications to provider + customer.
     */
    private function send_booking_notifications(int $order_id, int $user_id, int $partner_id, array $order_data = []): void
    {
        try {
            if (empty($order_data)) {
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[RAZORPAY WEBHOOK] [notifications] order=' . $order_id . ' not found.');
                return;
            }
            $final_total = $order_data[0]['total'] ?? 0;
            $language    = $this->defaultLanguage;

            $notificationContext = [
                'provider_id' => $partner_id,
                'user_id'     => $user_id,
                'booking_id'  => $order_id,
                'amount'      => $final_total,
            ];

            queue_notification_service(
                eventType: 'new_booking_received_for_provider',
                recipients: ['user_id' => $partner_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $language,
                    'platforms' => ['android', 'ios', 'web', 'provider_panel'],
                ]
            );

            queue_notification_service(
                eventType: 'new_booking_confirmation_to_customer',
                recipients: ['user_id' => $user_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $language,
                    'platforms' => ['android', 'ios', 'web'],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[RAZORPAY WEBHOOK] [notifications] order=' . $order_id . ' exception: ' . $e->getMessage());
        }
    }

    /**
     * Send online payment status notification (success/failed/pending) to customer.
     */
    private function send_online_payment_status_notification(int $order_id, int $user_id, string $status, array $payment_data = [], array $order_data = []): void
    {
        try {
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses, true)) {
                log_message('error', '[RAZORPAY WEBHOOK] [payment-notification] invalid status=' . $status);
                return;
            }
            $event_type_map = [
                'success' => 'online_payment_success',
                'failed'  => 'online_payment_failed',
                'pending' => 'online_payment_pending',
            ];
            $event_type = $event_type_map[$status];

            if (empty($order_data)) {
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[RAZORPAY WEBHOOK] [payment-notification] order=' . $order_id . ' not found.');
                return;
            }

            $customer_details = fetch_details('users', ['id' => $user_id], ['username', 'email']);
            $customer_name    = !empty($customer_details) ? $customer_details[0]['username'] : 'Customer';
            $customer_email   = !empty($customer_details) ? $customer_details[0]['email']    : '';

            $notificationContext = [
                'booking_id'     => (string) $order_id,
                'order_id'       => (string) $order_id,
                'amount'         => number_format($payment_data['amount'] ?? $order_data[0]['total'] ?? 0, 2),
                'currency'       => $payment_data['currency'] ?? $order_data[0]['currency_code'] ?? 'INR',
                'transaction_id' => (string) ($payment_data['transaction_id'] ?? $payment_data['txn_id'] ?? ''),
                'customer_id'    => (string) $user_id,
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'payment_method' => $payment_data['payment_method'] ?? 'Razorpay',
            ];
            if ($status === 'success') {
                $notificationContext['paid_at'] = $payment_data['paid_at'] ?? date('d-m-Y H:i:s');
            } elseif ($status === 'failed') {
                $notificationContext['failure_reason'] = $payment_data['failure_reason'] ?? 'Payment could not be processed.';
            }

            queue_notification_service(
                eventType: $event_type,
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
                        'transaction_id' => (string) ($payment_data['transaction_id'] ?? $payment_data['txn_id'] ?? ''),
                        'click_action'   => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to'    => 'booking_details_screen',
                    ],
                ]
            );

            notify_handymen_payment_status_changed($order_id, $event_type, $notificationContext, $this->defaultLanguage);
        } catch (\Throwable $e) {
            log_message('error', '[RAZORPAY WEBHOOK] [payment-notification] order=' . $order_id . ' status=' . $status . ' exception: ' . $e->getMessage());
        }
    }
}
