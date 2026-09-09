<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Flutterwave;

/**
 * Flutterwave webhook controller. Idempotent against duplicate webhooks and the
 * customer/provider `add_transaction` + `flutterwave_webview` flows that insert a
 * pending row before payment. Validates webhook amount + currency against the order
 * (or pending transaction). Signature verification stays on (required for Flutterwave).
 */
class FlutterwaveWebhook extends BaseController
{
    /** @var string Default language for notifications (no request context in webhooks) */
    protected $defaultLanguage;

    /** @var array General settings */
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

    public function index()
    {
        $request_body = file_get_contents('php://input');

        // TEMP: capture raw inbound webhook to design event handling.
        // Logs raw body + headers + query/POST so we can inspect actual Flutterwave payload shape.
        $headers_dump = [];
        foreach ($this->request->headers() as $name => $header) {
            $headers_dump[$name] = $header->getValue();
        }
      
        $event = json_decode($request_body, false);

        if (empty($event) || !is_object($event)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] Rejected: empty or invalid JSON payload');
            return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
        }

        // ---- Signature verification ----
        $flutterwave       = new Flutterwave();
        $credentials       = $flutterwave->get_credentials();
        $local_secret_hash = $credentials['secret_hash'] ?? '';
        $signature         = $_SERVER['FLUTTERWAVE_SECRET_KEY'] ?? '';

        if (empty($signature) || !hash_equals((string) $local_secret_hash, (string) $signature)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] Invalid signature. Rejected.');
            return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid signature']);
        }

        $event_type    = (string) ($event->event ?? 'unknown');
        $charge_status = (string) ($event->data->status ?? '');

        log_message('info', '[FLUTTERWAVE WEBHOOK] event=' . $event_type . ' status=' . $charge_status);

        // ---- Verify with Flutterwave API (server-side amount/currency we can trust) ----
        $verified = null;
        if (!empty($event->data->id)) {
            $verify_raw = $flutterwave->verify_transaction($event->data->id);
            $verified   = json_decode($verify_raw);
            if (!empty($verified) && ($verified->status ?? '') !== 'success') {
                log_message('warning', '[FLUTTERWAVE WEBHOOK] verify_transaction returned non-success: ' . ($verified->message ?? ''));
            }
        }

        $ctx = $this->parsePayloadContext($event, $verified);

        // ---- Refund ----
        if ($event_type === 'refund.completed' || $event_type === 'charge.refunded') {
            return $this->handleRefundProcessed($ctx);
        }

        if ($event_type !== 'charge.completed') {
            // Unhandled event — 200 so Flutterwave stops retrying.
            return $this->response->setStatusCode(200)->setBody('');
        }

        if ($charge_status === 'successful') {
            return $this->handleChargeSucceeded($ctx);
        }
        if ($charge_status === 'pending') {
            return $this->handleChargePending($ctx);
        }
        return $this->handleChargeFailed($ctx);
    }

    // =========================================================
    // Payload parsing
    // =========================================================

    private function parsePayloadContext(object $event, ?object $verified): array
    {
        // Prefer verified response (server-fetched, can't be spoofed in payload).
        $data = $verified->data ?? $event->data ?? null;

        $txn_id   = (string) ($data->id ?? '');
        $tx_ref   = (string) ($data->tx_ref ?? '');
        $amount   = (float) ($data->amount ?? 0);
        $currency = (string) ($data->currency ?? '');

        // Flutterwave webhook places metadata at root as `meta_data` (snake_case),
        // while the verify_transaction API response nests it under `data.meta`/`data.metadata`.
        // Check all three locations.
        $meta = $event->meta_data ?? $data->meta ?? $data->metadata ?? null;
        if (is_array($meta)) {
            $meta = (object) $meta;
        }

        $subscription_transaction_id = (int) ($meta->transaction_id ?? 0);
        $additional_charge_id        = (int) ($meta->additional_charges_transaction_id ?? 0);
        $metadata_order_id           = (int) ($meta->order_id ?? 0);

        // Look up existing transaction. Prefer reference (tx_ref stamped at intent creation)
        // since txn_id is only set after the webhook lands. Fall back to txn_id.
        $transaction = [];
        if ($tx_ref !== '') {
            $transaction = fetch_details('transactions', ['type' => 'flutterwave', 'reference' => $tx_ref]);
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

        // Infer additional_charge_id from matched pending row when meta did not carry it.
        if ($additional_charge_id === 0 && !empty($transaction) && ($transaction[0]['message'] ?? '') === 'txn_additional_charges') {
            $additional_charge_id = (int) $transaction[0]['id'];
        }

        $failure_reason = (string) ($data->processor_response ?? $data->narration ?? 'Payment could not be processed.');

        return [
            'txn_id'                      => $txn_id,
            'tx_ref'                      => $tx_ref,
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
    // charge.completed (successful)
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
            log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] txn=' . $ctx['transaction'][0]['id'] . ' already ' . $ctx['transaction'][0]['status'] . '. Skipping.');
            return $this->okJson('Already processed');
        }

        // Additional charge path (explicit metadata)
        if ($ctx['additional_charge_id'] > 0) {
            $this->handleAdditionalChargeSuccess($ctx);
            return $this->okJson('Additional charge processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] order_id missing. tx_ref=' . $ctx['tx_ref']);
            return $this->okJson('No order id');
        }

        if (empty($ctx['order_data'])) {
            log_message('warning', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] order=' . $ctx['order_id'] . ' not found. Skipping.');
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
                log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] inferred additional charge txn=' . $ctx['additional_charge_id'] . ' for order=' . $ctx['order_id']);
                $this->handleAdditionalChargeSuccess($ctx);
                return $this->okJson('Additional charge processed');
            }
        }

        $expected_amount   = $order_final_total > 0 ? $order_final_total : $order_total;
        $expected_currency = (string) ($ctx['order_data'][0]['currency_code'] ?? '');
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'order ' . $ctx['order_id'])) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] order=' . $ctx['order_id']
                . ' totals: final_total=' . $order_final_total
                . ' total=' . $order_total
                . ' total_additional_charge=' . $order_additional_total);
            return $this->okJson('Amount or currency mismatch');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingFlutterwaveTransactionForOrder($ctx['order_id']);

        if (empty($existing)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] no pending flutterwave row for order=' . $ctx['order_id']
                . ' tx_ref=' . $ctx['tx_ref'] . ' txn=' . $ctx['txn_id']
                . '. Intent never recorded — skipping insert. Reconcile manually.');
            return $this->okJson('No pending intent row');
        }

        if ($existing['status'] === 'success') {
            log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] txn=' . $existing['id'] . ' already success. Skipping.');
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
            log_message('error', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] DB transaction failed. order=' . $ctx['order_id']);
            return $this->okJson('DB error logged');
        }

        if (!$order_already_paid) {
            delete_details(['user_id' => $ctx['user_id']], 'cart');
            $this->send_booking_notifications($ctx['order_id'], $ctx['user_id'], $ctx['partner_id'], $ctx['order_data']);
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'success', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['txn_id'],
                'payment_method' => 'Flutterwave',
                'paid_at'        => date('d-m-Y H:i:s'),
            ], $ctx['order_data']);
        } else {
            log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.succeeded] order=' . $ctx['order_id'] . ' already paid. Skipped notifications.');
        }

        return $this->okJson('Processed');
    }

    private function handleAdditionalChargeSuccess(array $ctx): void
    {
        $charge_id = $ctx['additional_charge_id'];

        if (empty($ctx['order_data'])) {
            log_message('warning', '[FLUTTERWAVE WEBHOOK] [additional_charge] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return;
        }

        $existing = fetch_details('transactions', ['id' => $charge_id]);
        if (empty($existing)) {
            log_message('warning', '[FLUTTERWAVE WEBHOOK] [additional_charge] txn=' . $charge_id . ' not found. Skipping.');
            return;
        }
        if ($existing[0]['status'] === 'success') {
            log_message('info', '[FLUTTERWAVE WEBHOOK] [additional_charge] txn=' . $charge_id . ' already success. Skipping.');
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
            ['status' => 'success', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'amount' => $ctx['amount']],
            ['id' => $charge_id],
            'transactions'
        );

        if (!$already_paid_flag) {
            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $ctx['order_id']], 'orders');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [additional_charge] DB transaction failed. order=' . $ctx['order_id']);
            return;
        }

        if (!$already_paid_flag) {
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'success', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['txn_id'],
                'payment_method' => 'Flutterwave',
                'paid_at'        => date('d-m-Y H:i:s'),
            ], $ctx['order_data']);
        }
    }

    // =========================================================
    // charge.completed (failed)
    // =========================================================

    private function handleChargeFailed(array $ctx)
    {
        log_message('warning', '[FLUTTERWAVE WEBHOOK] [charge.failed] order=' . $ctx['order_id'] . ' tx_ref=' . $ctx['tx_ref'] . ' reason=' . $ctx['failure_reason']);

        // Subscription failure: mark pending sub row failed.
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
                log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.failed] additional_charge txn=' . $charge_id . ' already failed. Skipping.');
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
                    'payment_method' => 'Flutterwave',
                    'failure_reason' => $ctx['failure_reason'],
                ], $ctx['order_data']);
            }
            return $this->okJson('Processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('warning', '[FLUTTERWAVE WEBHOOK] [charge.failed] order_id missing. tx_ref=' . $ctx['tx_ref']);
            return $this->okJson('No order id');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingFlutterwaveTransactionForOrder($ctx['order_id']);

        if (!empty($existing) && $existing['status'] === 'failed') {
            log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.failed] txn=' . $existing['id'] . ' already failed. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_cancelled = !empty($ctx['order_data'])
            && (((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 2
                || ($ctx['order_data'][0]['status'] ?? '') === 'cancelled');

        $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        if (empty($existing)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [charge.failed] no pending flutterwave row for order=' . $ctx['order_id']
                . ' tx_ref=' . $ctx['tx_ref'] . ' txn=' . $ctx['txn_id']
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
                'payment_method' => 'Flutterwave',
                'failure_reason' => $ctx['failure_reason'],
            ], $ctx['order_data']);
        }

        return $this->okJson('Processed');
    }

    // =========================================================
    // charge.completed (pending — e.g. 3DS in progress)
    // =========================================================

    private function handleChargePending(array $ctx)
    {
        if ($ctx['additional_charge_id'] > 0) {
            $charge_id = $ctx['additional_charge_id'];
            $existing  = fetch_details('transactions', ['id' => $charge_id]);
            if (!empty($existing) && $existing[0]['status'] === 'pending') {
                log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.pending] additional_charge txn=' . $charge_id . ' already pending. Skipping.');
                return $this->okJson('Already processed');
            }

            $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
            $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

            update_details(
                ['status' => 'pending', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency],
                ['id' => $charge_id],
                'transactions'
            );

            if (!empty($ctx['order_data'])) {
                $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'pending', [
                    'amount'         => $ctx['amount'],
                    'currency'       => $stored_currency,
                    'transaction_id' => $ctx['txn_id'],
                    'payment_method' => 'Flutterwave',
                ], $ctx['order_data']);
            }
            return $this->okJson('Processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('warning', '[FLUTTERWAVE WEBHOOK] [charge.pending] order_id missing. tx_ref=' . $ctx['tx_ref']);
            return $this->okJson('No order id');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingFlutterwaveTransactionForOrder($ctx['order_id']);

        if (empty($existing)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [charge.pending] no pending flutterwave row for order=' . $ctx['order_id']
                . ' tx_ref=' . $ctx['tx_ref'] . '. Intent never recorded — skipping insert.');
            return $this->okJson('No pending intent row');
        }

        if ($existing['status'] === 'pending') {
            log_message('info', '[FLUTTERWAVE WEBHOOK] [charge.pending] txn=' . $existing['id'] . ' already pending. Skipping.');
            return $this->okJson('Already processed');
        }

        $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        update_details(
            ['status' => 'pending', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency],
            ['id' => $existing['id']],
            'transactions'
        );

        $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'pending', [
            'amount'         => $ctx['amount'],
            'currency'       => $stored_currency,
            'transaction_id' => $ctx['txn_id'],
            'payment_method' => 'Flutterwave',
        ], $ctx['order_data']);

        return $this->okJson('Processed');
    }

    // =========================================================
    // refund.completed / charge.refunded
    // =========================================================

    private function handleRefundProcessed(array $ctx)
    {
        $txn_id = $ctx['txn_id'];

        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'flutterwave',
            'status'           => 'success',
            'txn_id'           => $txn_id,
        ]);

        if (empty($success_transaction)) {
            log_message('warning', '[FLUTTERWAVE WEBHOOK] [refund] no matching success txn for gateway_id=' . $txn_id . '. Skipping.');
            return $this->response->setStatusCode(200)->setBody('');
        }

        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'flutterwave',
            'message'          => 'txn_refund_processed',
            'txn_id'           => $txn_id,
        ]);

        if (!empty($existing_refund)) {
            if ($existing_refund[0]['status'] === 'processed') {
                log_message('info', '[FLUTTERWAVE WEBHOOK] [refund] refund txn=' . $existing_refund[0]['id'] . ' already processed. Skipping.');
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
            'type'             => 'flutterwave',
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
        $tx_ref         = $ctx['tx_ref'];

        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] txn=' . $transaction_id . ' not found. Abort.');
            return;
        }

        if ($transaction_details[0]['status'] === 'success') {
            log_message('info', '[FLUTTERWAVE WEBHOOK] [subscription] txn=' . $transaction_id . ' already success. Skipping.');
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
            log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] subscription=' . $subscription_id . ' not found. Abort.');
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
            ['status' => 'success', 'txn_id' => $txn_id, 'currency_code' => $store_currency_value, 'reference' => $tx_ref],
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
            log_message('warning', '[FLUTTERWAVE WEBHOOK] [subscription] partner_subscriptions update affected 0 rows. partner=' . $partner_id . ' subscription=' . $subscription_id . ' txn=' . $transaction_id);
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
            log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] notification exception: ' . $e->getMessage());
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
                'type'             => 'flutterwave',
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

    private function findExistingFlutterwaveTransactionForOrder(int $order_id): array
    {
        if ($order_id <= 0) {
            return [];
        }
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'type'             => 'flutterwave',
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
            log_message('error', '[FLUTTERWAVE WEBHOOK] amount mismatch on ' . $label . ': received=' . $actual_amount . ' expected=' . $expected_amount . '. Aborting.');
            return false;
        }
        if ($expected_currency !== '' && $actual_currency !== '' && strcasecmp($actual_currency, $expected_currency) !== 0) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] currency mismatch on ' . $label . ': received=' . $actual_currency . ' expected=' . $expected_currency . '. Aborting.');
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
                log_message('error', '[FLUTTERWAVE WEBHOOK] [notifications] order=' . $order_id . ' not found.');
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
            log_message('error', '[FLUTTERWAVE WEBHOOK] [notifications] order=' . $order_id . ' exception: ' . $e->getMessage());
        }
    }

    private function send_online_payment_status_notification(int $order_id, int $user_id, string $status, array $payment_data = [], array $order_data = []): void
    {
        try {
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses, true)) {
                log_message('error', '[FLUTTERWAVE WEBHOOK] [payment-notification] invalid status=' . $status);
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
                log_message('error', '[FLUTTERWAVE WEBHOOK] [payment-notification] order=' . $order_id . ' not found.');
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
                'payment_method' => $payment_data['payment_method'] ?? 'Flutterwave',
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
            log_message('error', '[FLUTTERWAVE WEBHOOK] [payment-notification] order=' . $order_id . ' status=' . $status . ' exception: ' . $e->getMessage());
        }
    }
}
