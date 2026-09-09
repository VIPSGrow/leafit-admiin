<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Paystack;

/**
 * Paystack webhook controller. Idempotent against duplicate webhooks and the
 * customer/provider `add_transaction` + `paystack_transaction_webview` flows that
 * stamp the Paystack `reference` on a pending transactions row before payment.
 *
 * No signature verification — Paystack secret is reused as the API secret and the
 * project does not provision a separate webhook secret. Trust anchor instead is the
 * server-side `verify_transation()` re-fetch which Paystack signs on our behalf.
 *
 * Mirrors the shape of FlutterwaveWebhook / StripeWebhook / RazorpayWebhook:
 *   index() parses + dispatches → private handlers do the work.
 */
class PaystackWebhook extends BaseController
{
    /** @var string Default language for notifications (no request context in webhooks) */
    protected $defaultLanguage;

    /** @var array General settings (timezone, currency, etc.) */
    protected $settings;

    /** Float tolerance for money comparisons */
    private const AMOUNT_TOLERANCE = 0.01;

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->settings        = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
        $this->defaultLanguage = get_default_language();
    }

    // =========================================================
    // Main entry point
    // =========================================================

    public function index()
    {
        $raw_body = file_get_contents('php://input');
        $event    = json_decode($raw_body, true);

        log_message('info', '[PAYSTACK WEBHOOK] ===== Incoming request =====');

        if (empty($event) || !is_array($event)) {
            log_message('error', '[PAYSTACK WEBHOOK] Rejected: empty or invalid JSON payload');
            return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
        }

        $event_type = (string) ($event['event'] ?? 'unknown');
        log_message('info', '[PAYSTACK WEBHOOK] event=' . $event_type);

        // Server-side verify is our trust anchor (no signature verification).
        $paystack = new Paystack();
        $verified = null;
        $reference = (string) ($event['data']['reference'] ?? '');
        if ($reference !== '') {
            $verify_raw = $paystack->verify_transation($reference);
            $verified   = json_decode($verify_raw, true);
            if (!empty($verified) && (($verified['status'] ?? false) !== true || ($verified['data']['status'] ?? '') !== 'success')) {
                log_message('warning', '[PAYSTACK WEBHOOK] verify_transation non-success for ref=' . $reference . ': ' . ($verified['message'] ?? ''));
            }
        }

        $ctx = $this->parsePayloadContext($event, $verified);

        switch ($event_type) {
            case 'charge.success':
                return $this->handleChargeSucceeded($ctx);

            case 'charge.dispute.create':
                return $this->handleChargeDisputeCreate($ctx);

            case 'refund.processed':
                return $this->handleRefundProcessed($ctx);

            default:
                // Treat any other event as failure when context is identifiable.
                return $this->handleChargeFailed($ctx, $event_type);
        }
    }

    // =========================================================
    // Payload parsing
    // =========================================================

    private function parsePayloadContext(array $event, ?array $verified): array
    {
        // Prefer verified response (server-fetched, can't be spoofed in payload).
        $data = $verified['data'] ?? $event['data'] ?? [];

        $txn_id    = (string) ($data['id']        ?? '');
        $reference = (string) ($data['reference'] ?? '');
        // Paystack delivers amount in minor units (kobo). Convert to major units.
        $amount_minor = (float) ($data['amount']   ?? 0);
        $amount       = $amount_minor > 0 ? round($amount_minor / 100, 2) : 0.0;
        $currency     = (string) ($data['currency'] ?? '');

        $meta = $data['metadata'] ?? [];
        if (is_string($meta)) {
            // Paystack sometimes serialises metadata as a JSON string in events.
            $decoded = json_decode($meta, true);
            $meta    = is_array($decoded) ? $decoded : [];
        }

        $subscription_transaction_id = (int) ($meta['transaction_id'] ?? 0);
        $additional_charge_id        = (int) ($meta['additional_charges_transaction_id'] ?? 0);
        $metadata_order_id           = (int) ($meta['order_id'] ?? 0);

        // Locate the pending transaction. Prefer reference (stamped at intent creation),
        // fall back to txn_id (only set after webhook lands).
        $transaction = [];
        if ($reference !== '') {
            $transaction = fetch_details('transactions', ['type' => 'paystack', 'reference' => $reference]);
        }
        if (empty($transaction) && $txn_id !== '') {
            $transaction = fetch_details('transactions', ['txn_id' => $txn_id]);
        }

        $order_id   = 0;
        $user_id    = 0;
        $partner_id = 0;
        $order_data = [];

        if (!empty($transaction) && !empty($transaction[0]['order_id'])) {
            $order_id = (int) $transaction[0]['order_id'];
        } elseif ($subscription_transaction_id === 0) {
            $order_id = $metadata_order_id;
        }

        if ($order_id > 0) {
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (!empty($order_data)) {
                $user_id    = (int) $order_data[0]['user_id'];
                $partner_id = (int) $order_data[0]['partner_id'];
            }
        }

        // Infer additional_charge_id from matched pending row when metadata did not carry it.
        if ($additional_charge_id === 0 && !empty($transaction) && ($transaction[0]['message'] ?? '') === 'txn_additional_charges') {
            $additional_charge_id = (int) $transaction[0]['id'];
        }

        $failure_reason = (string) ($data['gateway_response'] ?? $data['message'] ?? 'Payment could not be processed.');

        return [
            'txn_id'                      => $txn_id,
            'reference'                   => $reference,
            'amount'                      => $amount,
            'currency'                    => $currency,
            'failure_reason'              => $failure_reason,
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
    // charge.success
    // =========================================================

    private function handleChargeSucceeded(array $ctx)
    {
        // Subscription path
        if ($ctx['subscription_transaction_id'] > 0) {
            $this->handleSubscriptionPayment($ctx);
            return $this->okJson('Subscription processed');
        }

        // Idempotency on already-finalised pending row.
        if (!empty($ctx['transaction']) && in_array($ctx['transaction'][0]['status'] ?? '', ['success', 'failed'], true)) {
            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] txn=' . $ctx['transaction'][0]['id'] . ' already ' . $ctx['transaction'][0]['status'] . '. Skipping.');
            return $this->okJson('Already processed');
        }

        // Additional charge path (explicit metadata)
        if ($ctx['additional_charge_id'] > 0) {
            $this->handleAdditionalChargeSuccess($ctx);
            return $this->okJson('Additional charge processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('error', '[PAYSTACK WEBHOOK] [charge.success] order_id missing. ref=' . $ctx['reference']);
            return $this->okJson('No order id');
        }

        if (empty($ctx['order_data'])) {
            log_message('warning', '[PAYSTACK WEBHOOK] [charge.success] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return $this->okJson('Order not found');
        }

        $order_final_total      = (float) ($ctx['order_data'][0]['final_total'] ?? 0);
        $order_total            = (float) ($ctx['order_data'][0]['total'] ?? 0);
        $order_additional_total = (float) ($ctx['order_data'][0]['total_additional_charge'] ?? 0);

        $amount_matches_final            = $order_final_total > 0 && abs($ctx['amount'] - $order_final_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_total            = $order_total > 0 && abs($ctx['amount'] - $order_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_additional_total = $order_additional_total > 0 && abs($ctx['amount'] - $order_additional_total) <= self::AMOUNT_TOLERANCE;

        if (!$amount_matches_final && !$amount_matches_total && $amount_matches_additional_total) {
            $pending_additional = $this->findPendingAdditionalChargeForOrder($ctx['order_id']);
            if (!empty($pending_additional)) {
                $ctx['additional_charge_id'] = (int) $pending_additional['id'];
                log_message('info', '[PAYSTACK WEBHOOK] [charge.success] inferred additional charge txn=' . $ctx['additional_charge_id'] . ' for order=' . $ctx['order_id']);
                $this->handleAdditionalChargeSuccess($ctx);
                return $this->okJson('Additional charge processed');
            }
        }

        $expected_amount   = $order_final_total > 0 ? $order_final_total : $order_total;
        $expected_currency = (string) ($ctx['order_data'][0]['currency_code'] ?? '');
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'order ' . $ctx['order_id'])) {
            log_message('error', '[PAYSTACK WEBHOOK] [charge.success] order=' . $ctx['order_id']
                . ' totals: final_total=' . $order_final_total
                . ' total=' . $order_total
                . ' total_additional_charge=' . $order_additional_total);
            return $this->okJson('Amount or currency mismatch');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingPaystackTransactionForOrder($ctx['order_id']);

        if (empty($existing)) {
            log_message('error', '[PAYSTACK WEBHOOK] [charge.success] no pending paystack row for order=' . $ctx['order_id']
                . ' ref=' . $ctx['reference'] . ' txn=' . $ctx['txn_id']
                . '. Intent never recorded — skipping insert. Reconcile manually.');
            return $this->okJson('No pending intent row');
        }

        if ($existing['status'] === 'success') {
            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] txn=' . $existing['id'] . ' already success. Skipping.');
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

        if (!$order_already_paid) {
            update_details(['payment_status' => 1], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'booked');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[PAYSTACK WEBHOOK] [charge.success] DB transaction failed. order=' . $ctx['order_id']);
            return $this->okJson('DB error logged');
        }

        if (!$order_already_paid) {
            delete_details(['user_id' => $ctx['user_id']], 'cart');
            $this->send_booking_notifications($ctx['order_id'], $ctx['user_id'], $ctx['partner_id'], $ctx['order_data']);
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'success', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['txn_id'],
                'payment_method' => 'Paystack',
                'paid_at'        => date('d-m-Y H:i:s'),
            ], $ctx['order_data']);
        } else {
            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] order=' . $ctx['order_id'] . ' already paid. Skipped notifications.');
        }

        return $this->okJson('Processed');
    }

    private function handleAdditionalChargeSuccess(array $ctx): void
    {
        $charge_id = $ctx['additional_charge_id'];

        if (empty($ctx['order_data'])) {
            log_message('warning', '[PAYSTACK WEBHOOK] [additional_charge] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return;
        }

        $existing = fetch_details('transactions', ['id' => $charge_id]);
        if (empty($existing)) {
            log_message('warning', '[PAYSTACK WEBHOOK] [additional_charge] txn=' . $charge_id . ' not found. Skipping.');
            return;
        }
        if ($existing[0]['status'] === 'success') {
            log_message('info', '[PAYSTACK WEBHOOK] [additional_charge] txn=' . $charge_id . ' already success. Skipping.');
            return;
        }

        $expected_amount   = (float) ($existing[0]['amount'] ?? 0);
        $expected_currency = (string) ($existing[0]['currency_code'] ?? '');
        if ($expected_currency === '') {
            $expected_currency = (string) ($ctx['order_data'][0]['currency_code'] ?? '');
        }
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'additional charge txn ' . $charge_id . ' for order ' . $ctx['order_id'])) {
            return;
        }

        $already_paid_flag = ((string) ($ctx['order_data'][0]['payment_status_of_additional_charge'] ?? '')) === '1';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            [
                'status'        => 'success',
                'txn_id'        => $ctx['txn_id'],
                'reference'     => $ctx['reference'] ?: ($existing[0]['reference'] ?? null),
                'currency_code' => $stored_currency,
                'amount'        => $ctx['amount'],
            ],
            ['id' => $charge_id],
            'transactions'
        );

        if (!$already_paid_flag) {
            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $ctx['order_id']], 'orders');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[PAYSTACK WEBHOOK] [additional_charge] DB transaction failed. order=' . $ctx['order_id']);
            return;
        }

        if (!$already_paid_flag) {
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'success', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['txn_id'],
                'payment_method' => 'Paystack',
                'paid_at'        => date('d-m-Y H:i:s'),
            ], $ctx['order_data']);
        }
    }

    // =========================================================
    // Failed events (any non-success charge event)
    // =========================================================

    private function handleChargeFailed(array $ctx, string $event_type)
    {
        log_message('warning', '[PAYSTACK WEBHOOK] [' . $event_type . '] order=' . $ctx['order_id'] . ' ref=' . $ctx['reference'] . ' reason=' . $ctx['failure_reason']);

        // Subscription failure
        if ($ctx['subscription_transaction_id'] > 0) {
            $sub_id  = $ctx['subscription_transaction_id'];
            $sub_row = fetch_details('transactions', ['id' => $sub_id]);
            if (!empty($sub_row) && in_array($sub_row[0]['status'] ?? '', ['success', 'failed'], true)) {
                return $this->okJson('Already processed');
            }
            update_details(['status' => 'failed', 'txn_id' => $ctx['txn_id']], ['id' => $sub_id], 'transactions');
            send_subscription_payment_status_notification($sub_id, 'failed');
            return $this->okJson('Subscription failed');
        }

        if ($ctx['additional_charge_id'] > 0) {
            $charge_id = $ctx['additional_charge_id'];
            $existing  = fetch_details('transactions', ['id' => $charge_id]);
            if (!empty($existing) && $existing[0]['status'] === 'failed') {
                log_message('info', '[PAYSTACK WEBHOOK] [' . $event_type . '] additional_charge txn=' . $charge_id . ' already failed. Skipping.');
                return $this->okJson('Already processed');
            }

            $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
            $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

            $db = \Config\Database::connect();
            $db->transStart();
            update_details(
                ['status' => 'failed', 'txn_id' => $ctx['txn_id'], 'reference' => $ctx['reference'] ?: ($existing[0]['reference'] ?? null), 'currency_code' => $stored_currency],
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
                    'payment_method' => 'Paystack',
                    'failure_reason' => $ctx['failure_reason'],
                ], $ctx['order_data']);
            }
            return $this->okJson('Processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('warning', '[PAYSTACK WEBHOOK] [' . $event_type . '] order_id missing. ref=' . $ctx['reference']);
            return $this->okJson('No order id');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingPaystackTransactionForOrder($ctx['order_id']);

        if (!empty($existing) && $existing['status'] === 'failed') {
            log_message('info', '[PAYSTACK WEBHOOK] [' . $event_type . '] txn=' . $existing['id'] . ' already failed. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_cancelled = !empty($ctx['order_data'])
            && (((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 2
                || ($ctx['order_data'][0]['status'] ?? '') === 'cancelled');

        $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        if (empty($existing)) {
            log_message('error', '[PAYSTACK WEBHOOK] [' . $event_type . '] no pending paystack row for order=' . $ctx['order_id']
                . ' ref=' . $ctx['reference'] . ' txn=' . $ctx['txn_id']
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
                'payment_method' => 'Paystack',
                'failure_reason' => $ctx['failure_reason'],
            ], $ctx['order_data']);
        }

        return $this->okJson('Processed');
    }

    // =========================================================
    // charge.dispute.create
    // =========================================================

    private function handleChargeDisputeCreate(array $ctx)
    {
        log_message('info', '[PAYSTACK WEBHOOK] [charge.dispute.create] order_id=' . $ctx['order_id']);

        if (!empty($ctx['order_id']) && !empty($ctx['order_data'])) {
            $active_status = $ctx['order_data'][0]['active_status'] ?? '';

            if (in_array($active_status, ['received', 'processed'], true)) {
                update_details(['status' => 'awaiting'], ['id' => $ctx['order_id']], 'orders');
                update_custom_job_status($ctx['order_id'], 'pending');
                log_message('info', '[PAYSTACK WEBHOOK] [charge.dispute.create] order=' . $ctx['order_id'] . ' set to awaiting (was: ' . $active_status . ').');
            }

            if (!empty($ctx['transaction'])) {
                $transaction_id = (int) $ctx['transaction'][0]['id'];
                update_details(['status' => 'pending'], ['id' => $transaction_id], 'transactions');
                log_message('info', '[PAYSTACK WEBHOOK] [charge.dispute.create] txn=' . $transaction_id . ' set to pending.');
            }
        }

        return $this->okJson('Processed');
    }

    // =========================================================
    // refund.processed
    // =========================================================

    private function handleRefundProcessed(array $ctx)
    {
        $txn_id = $ctx['txn_id'];

        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'paystack',
            'status'           => 'success',
            'txn_id'           => $txn_id,
        ]);

        if (empty($success_transaction)) {
            log_message('warning', '[PAYSTACK WEBHOOK] [refund] no matching success txn for gateway_id=' . $txn_id . '. Skipping.');
            return $this->response->setStatusCode(200)->setBody('');
        }

        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'paystack',
            'message'          => 'txn_refund_processed',
            'txn_id'           => $txn_id,
        ]);

        if (!empty($existing_refund)) {
            if ($existing_refund[0]['status'] === 'processed') {
                log_message('info', '[PAYSTACK WEBHOOK] [refund] refund txn=' . $existing_refund[0]['id'] . ' already processed. Skipping.');
                return $this->response->setStatusCode(200)->setBody('');
            }
            update_details(['status' => 'processed'], ['id' => $existing_refund[0]['id']], 'transactions');
            return $this->response->setStatusCode(200)->setBody('');
        }

        $stored_currency = (string) ($success_transaction[0]['currency_code'] ?? '');
        if ($stored_currency === '') {
            $stored_currency = $this->resolveStoredCurrency(
                !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '',
                $ctx['currency']
            );
        }

        $db = \Config\Database::connect();
        $db->transStart();
        add_transaction([
            'transaction_type' => 'refund',
            'user_id'          => $ctx['user_id'],
            'partner_id'       => $ctx['partner_id'],
            'order_id'         => $ctx['order_id'],
            'type'             => 'paystack',
            'txn_id'           => $txn_id,
            'amount'           => $ctx['amount'],
            'status'           => 'processed',
            'currency_code'    => $stored_currency,
            'message'          => 'txn_refund_processed',
        ]);
        update_custom_job_status($ctx['order_id'], 'refunded');
        $db->transComplete();

        return $this->response->setStatusCode(200)->setBody('');
    }

    // =========================================================
    // Subscription
    // =========================================================

    private function handleSubscriptionPayment(array $ctx): void
    {
        $transaction_id = $ctx['subscription_transaction_id'];
        $txn_id         = $ctx['txn_id'];
        $amount         = $ctx['amount'];
        $currency       = $ctx['currency'];
        $reference      = $ctx['reference'];

        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[PAYSTACK WEBHOOK] [subscription] txn=' . $transaction_id . ' not found. Abort.');
            return;
        }

        if ($transaction_details[0]['status'] === 'success') {
            log_message('info', '[PAYSTACK WEBHOOK] [subscription] txn=' . $transaction_id . ' already success. Skipping.');
            return;
        }

        $subscription_id = (int) $transaction_details[0]['subscription_id'];
        $partner_id      = (int) $transaction_details[0]['user_id'];

        $expected_amount = (float) ($transaction_details[0]['amount'] ?? 0);
        $stored_currency = (string) ($transaction_details[0]['currency_code'] ?? '');
        if (!$this->validatePayment($amount, $currency, $expected_amount, $stored_currency, 'subscription txn ' . $transaction_id)) {
            return;
        }

        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($details_for_subscription)) {
            log_message('error', '[PAYSTACK WEBHOOK] [subscription] subscription=' . $subscription_id . ' not found. Abort.');
            return;
        }

        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $details_for_subscription[0]['duration'];
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
        if ($subscriptionDuration === 'unlimited') {
            $subscriptionDuration = 0;
        }

        $store_currency_value = $this->resolveStoredCurrency($stored_currency, $currency);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            ['status' => 'success', 'txn_id' => $txn_id, 'currency_code' => $store_currency_value, 'reference' => $reference],
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
            log_message('warning', '[PAYSTACK WEBHOOK] [subscription] partner_subscriptions update affected 0 rows. partner=' . $partner_id . ' subscription=' . $subscription_id . ' txn=' . $transaction_id);
            return;
        }

        try {
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
        } catch (\Throwable $e) {
            log_message('error', '[PAYSTACK WEBHOOK] [subscription] notification exception: ' . $e->getMessage());
        }
    }

    // =========================================================
    // Helpers
    // =========================================================

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
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'transaction_type' => 'transaction',
                'type'             => 'paystack',
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

    private function findExistingPaystackTransactionForOrder(int $order_id): array
    {
        if ($order_id <= 0) {
            return [];
        }
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'type'             => 'paystack',
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

    private function resolveStoredCurrency(string $base, string $gateway): string
    {
        if ($base !== '') {
            return strtoupper($base);
        }
        return $gateway !== '' ? strtoupper($gateway) : '';
    }

    private function validatePayment(float $actual_amount, string $actual_currency, float $expected_amount, string $expected_currency, string $label): bool
    {
        if ($expected_amount > 0 && abs($actual_amount - $expected_amount) > self::AMOUNT_TOLERANCE) {
            log_message('error', '[PAYSTACK WEBHOOK] amount mismatch on ' . $label . ': received=' . $actual_amount . ' expected=' . $expected_amount . '. Aborting.');
            return false;
        }
        if ($expected_currency !== '' && $actual_currency !== '' && strcasecmp($actual_currency, $expected_currency) !== 0) {
            log_message('error', '[PAYSTACK WEBHOOK] currency mismatch on ' . $label . ': received=' . $actual_currency . ' expected=' . $expected_currency . '. Aborting.');
            return false;
        }
        return true;
    }

    private function okJson(string $message)
    {
        return $this->response->setJSON(['error' => false, 'message' => $message]);
    }

    // =========================================================
    // Notifications
    // =========================================================

    private function send_booking_notifications(int $order_id, int $user_id, int $partner_id, array $order_data = []): void
    {
        try {
            if (empty($order_data)) {
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[PAYSTACK WEBHOOK] [notifications] order=' . $order_id . ' not found.');
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
            log_message('error', '[PAYSTACK WEBHOOK] [notifications] order=' . $order_id . ' exception: ' . $e->getMessage());
        }
    }

    private function send_online_payment_status_notification(int $order_id, int $user_id, string $status, array $payment_data = [], array $order_data = []): void
    {
        try {
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses, true)) {
                log_message('error', '[PAYSTACK WEBHOOK] [payment-notification] invalid status=' . $status);
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
                log_message('error', '[PAYSTACK WEBHOOK] [payment-notification] order=' . $order_id . ' not found.');
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
                'payment_method' => $payment_data['payment_method'] ?? 'Paystack',
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
                        'transaction_id' => (string) ($payment_data['transaction_id'] ?? ''),
                        'click_action'   => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to'    => 'booking_details_screen',
                    ],
                ]
            );

            notify_handymen_payment_status_changed($order_id, $event_type, $notificationContext, $this->defaultLanguage);
        } catch (\Throwable $e) {
            log_message('error', '[PAYSTACK WEBHOOK] [payment-notification] order=' . $order_id . ' status=' . $status . ' exception: ' . $e->getMessage());
        }
    }
}
