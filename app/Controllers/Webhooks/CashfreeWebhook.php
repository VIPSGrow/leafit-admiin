<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Cashfree;

/**
 * Cashfree webhook controller. Idempotent against duplicate webhooks and the
 * customer/provider `cashfree_create_order` APIs that insert a pending row before payment.
 * Validates webhook amount + currency against the order (or pending transaction).
 * Signature verification stays on (required for Cashfree).
 */
class CashfreeWebhook extends BaseController
{
    private Cashfree $cashfree;

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
        $this->cashfree        = new Cashfree();
        $this->settings        = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
        $this->defaultLanguage = get_default_language();
    }

    public function index()
    {
        try {
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                log_message('warning', '[CASHFREE WEBHOOK] Rejected: method not allowed');
                return $this->response->setStatusCode(405)->setJSON(['error' => true, 'message' => 'Method not allowed']);
            }

            $raw_body = file_get_contents('php://input');
            $event    = json_decode($raw_body, true);

            if (empty($event) || !is_array($event)) {
                log_message('error', '[CASHFREE WEBHOOK] Rejected: empty or invalid JSON payload');
                return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
            }

            $signature = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
            $timestamp = (string) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '');

            if (!$this->cashfree->verify_webhook_signature($raw_body, $signature, $timestamp)) {
                log_message('error', '[CASHFREE WEBHOOK] Invalid signature. Rejected.');
                // 400 instead of 401 — Cashfree retry-storms on non-2xx; 400 is "do not retry".
                return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid signature']);
            }

            $event_type = strtoupper((string) ($event['type'] ?? 'unknown'));
            log_message('info', '[CASHFREE WEBHOOK] event=' . $event_type);

            $ctx = $this->parsePayloadContext($event);

            // Refund events
            if (strpos($event_type, 'REFUND') !== false) {
                return $this->handleRefundProcessed($ctx);
            }

            $payment_status = strtoupper((string) ($event['data']['payment']['payment_status'] ?? ''));

            if ($payment_status === 'SUCCESS') {
                return $this->handlePaymentSuccess($ctx);
            }
            if ($payment_status === 'FAILED' || $payment_status === 'USER_DROPPED' || $payment_status === 'CANCELLED') {
                return $this->handlePaymentFailed($ctx);
            }
            if ($payment_status === 'PENDING' || $payment_status === 'NOT_ATTEMPTED' || $payment_status === 'FLAGGED') {
                return $this->handlePaymentPending($ctx);
            }

            // Unhandled event — 200 so Cashfree stops retrying.
            return $this->response->setStatusCode(200)->setBody('');
        } catch (\Throwable $th) {
            log_message('error', '[CASHFREE WEBHOOK] Exception: ' . $th->getMessage() . ' in ' . $th->getFile() . ':' . $th->getLine());
            return $this->response->setStatusCode(500)->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }

    // =========================================================
    // Payload parsing
    // =========================================================

    private function parsePayloadContext(array $event): array
    {
        $gateway_order_id = (string) ($event['data']['order']['order_id'] ?? '');
        $cf_payment_id    = (string) ($event['data']['payment']['cf_payment_id'] ?? '');
        $currency         = strtoupper((string) ($event['data']['order']['order_currency'] ?? ''));
        $amount           = (float) ($event['data']['payment']['payment_amount']
            ?? $event['data']['order']['order_amount']
            ?? 0);
        $failure_reason   = (string) ($event['data']['payment']['payment_message']
            ?? $event['data']['error_details']['error_description']
            ?? 'Payment could not be processed.');

        $order_tags = $event['data']['order']['order_tags'] ?? [];
        if (is_object($order_tags)) {
            $order_tags = json_decode(json_encode($order_tags), true);
        }
        if (!is_array($order_tags)) {
            $order_tags = [];
        }

        $subscription_transaction_id = (int) ($order_tags['transaction_id'] ?? 0);
        $additional_charge_id        = (int) ($order_tags['additional_charges_transaction_id'] ?? 0);
        $tags_order_id               = (int) ($order_tags['order_id'] ?? 0);

        // Look up existing transaction. Prefer reference (gateway_order_id stamped at intent creation)
        // since txn_id (cf_payment_id) is only known after the webhook lands. Fall back to txn_id.
        $transaction = [];
        if ($gateway_order_id !== '') {
            $transaction = fetch_details('transactions', ['type' => 'cashfree', 'reference' => $gateway_order_id]);
        }
        if (empty($transaction) && $cf_payment_id !== '') {
            $transaction = fetch_details('transactions', ['txn_id' => $cf_payment_id, 'type' => 'cashfree']);
        }

        // Resolve order_id / user_id / partner_id.
        $order_id   = 0;
        $user_id    = 0;
        $partner_id = 0;
        $order_data = [];

        if (!empty($transaction) && !empty($transaction[0]['order_id'])) {
            $order_id = (int) $transaction[0]['order_id'];
        } elseif ($subscription_transaction_id === 0) {
            // Fall back to order_tags then gateway_order_id prefix.
            if ($tags_order_id > 0) {
                $order_id = $tags_order_id;
            } else {
                $order_id = $this->parseOrderIdFromGatewayId($gateway_order_id);
            }
        }

        if ($order_id > 0) {
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (!empty($order_data)) {
                $user_id    = (int) $order_data[0]['user_id'];
                $partner_id = (int) $order_data[0]['partner_id'];
            }
        }

        // Derive subscription_transaction_id from prefix when tags missing.
        if ($subscription_transaction_id === 0 && preg_match('/^subs_(\d+)_\d+_\d+$/', $gateway_order_id, $m)) {
            $subscription_transaction_id = (int) $m[1];
        }

        // Derive additional_charge_id from matched pending row when tags did not carry it.
        if ($additional_charge_id === 0 && !empty($transaction) && ($transaction[0]['message'] ?? '') === 'txn_additional_charges') {
            $additional_charge_id = (int) $transaction[0]['id'];
        }

        return [
            'gateway_order_id'            => $gateway_order_id,
            'txn_id'                      => $cf_payment_id,
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

    private function parseOrderIdFromGatewayId(string $gateway_order_id): int
    {
        if (preg_match('/^additional_charges_(\d+)_\d+$/', $gateway_order_id, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^order_(\d+)_\d+$/', $gateway_order_id, $m)) {
            return (int) $m[1];
        }
        if (is_numeric($gateway_order_id)) {
            return (int) $gateway_order_id;
        }
        return 0;
    }

    // =========================================================
    // SUCCESS
    // =========================================================

    private function handlePaymentSuccess(array $ctx)
    {
        // Subscription path
        if ($ctx['subscription_transaction_id'] > 0) {
            $this->handleSubscriptionPayment($ctx);
            return $this->okJson('Subscription processed');
        }

        // Idempotency on already-finalised pending row.
        if (!empty($ctx['transaction']) && in_array($ctx['transaction'][0]['status'] ?? '', ['success', 'failed'], true)) {
            log_message('info', '[CASHFREE WEBHOOK] [success] txn=' . $ctx['transaction'][0]['id'] . ' already ' . $ctx['transaction'][0]['status'] . '. Skipping.');
            return $this->okJson('Already processed');
        }

        // Server-side verification: confirm with Cashfree API that order is PAID before any state change.
        if (!$this->verifyOrderPaidWithCashfree($ctx['gateway_order_id'])) {
            log_message('error', '[CASHFREE WEBHOOK] [success] API verification failed for gateway_order_id=' . $ctx['gateway_order_id']);
            return $this->okJson('API verification failed');
        }

        // Additional charge path (explicit tag)
        if ($ctx['additional_charge_id'] > 0) {
            $this->handleAdditionalChargeSuccess($ctx);
            return $this->okJson('Additional charge processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('error', '[CASHFREE WEBHOOK] [success] order_id missing. gateway_order_id=' . $ctx['gateway_order_id']);
            return $this->okJson('No order id');
        }

        if (empty($ctx['order_data'])) {
            log_message('warning', '[CASHFREE WEBHOOK] [success] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return $this->okJson('Order not found');
        }

        $order_final_total      = (float) ($ctx['order_data'][0]['final_total'] ?? 0);
        $order_total            = (float) ($ctx['order_data'][0]['total'] ?? 0);
        $order_additional_total = (float) ($ctx['order_data'][0]['total_additional_charge'] ?? 0);

        $amount_matches_final            = $order_final_total > 0 && abs($ctx['amount'] - $order_final_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_total            = $order_total > 0 && abs($ctx['amount'] - $order_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_additional_total = $order_additional_total > 0 && abs($ctx['amount'] - $order_additional_total) <= self::AMOUNT_TOLERANCE;

        // Fallback: tags lacked additional_charges_transaction_id but amount matches additional total.
        if (!$amount_matches_final && !$amount_matches_total && $amount_matches_additional_total) {
            $pending_additional = $this->findPendingAdditionalChargeForOrder($ctx['order_id']);
            if (!empty($pending_additional)) {
                $ctx['additional_charge_id'] = (int) $pending_additional['id'];
                log_message('info', '[CASHFREE WEBHOOK] [success] inferred additional charge txn=' . $ctx['additional_charge_id'] . ' for order=' . $ctx['order_id']);
                $this->handleAdditionalChargeSuccess($ctx);
                return $this->okJson('Additional charge processed');
            }
        }

        $expected_amount   = $order_final_total > 0 ? $order_final_total : $order_total;
        $expected_currency = (string) ($ctx['order_data'][0]['currency_code'] ?? '');
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'order ' . $ctx['order_id'])) {
            log_message('error', '[CASHFREE WEBHOOK] [success] order=' . $ctx['order_id']
                . ' totals: final_total=' . $order_final_total
                . ' total=' . $order_total
                . ' total_additional_charge=' . $order_additional_total);
            return $this->okJson('Amount or currency mismatch');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingCashfreeTransactionForOrder($ctx['order_id']);

        if (empty($existing)) {
            log_message('error', '[CASHFREE WEBHOOK] [success] no pending cashfree row for order=' . $ctx['order_id']
                . ' gateway_order_id=' . $ctx['gateway_order_id'] . ' cf_payment_id=' . $ctx['txn_id']
                . '. Intent never recorded — skipping insert. Reconcile manually.');
            return $this->okJson('No pending intent row');
        }

        if ($existing['status'] === 'success') {
            log_message('info', '[CASHFREE WEBHOOK] [success] txn=' . $existing['id'] . ' already success. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_paid = ((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 1;
        $stored_currency    = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            ['status' => 'success', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'amount' => $ctx['amount'], 'reference' => $ctx['gateway_order_id']],
            ['id' => $existing['id']],
            'transactions'
        );
        log_message('info', '[CASHFREE WEBHOOK] [success] txn=' . $existing['id'] . ' marked success. order=' . $ctx['order_id']);

        if (!$order_already_paid) {
            update_details(['payment_status' => 1], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'booked');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[CASHFREE WEBHOOK] [success] DB transaction failed. order=' . $ctx['order_id']);
            return $this->okJson('DB error logged');
        }

        if (!$order_already_paid) {
            delete_details(['user_id' => $ctx['user_id']], 'cart');
            $this->send_booking_notifications($ctx['order_id'], $ctx['user_id'], $ctx['partner_id'], $ctx['order_data']);
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'success', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['txn_id'],
                'payment_method' => 'Cashfree',
                'paid_at'        => date('d-m-Y H:i:s'),
            ], $ctx['order_data']);
        } else {
            log_message('info', '[CASHFREE WEBHOOK] [success] order=' . $ctx['order_id'] . ' already paid. Skipped notifications.');
        }

        return $this->okJson('Processed');
    }

    private function handleAdditionalChargeSuccess(array $ctx): void
    {
        $charge_id = $ctx['additional_charge_id'];

        if (empty($ctx['order_data'])) {
            log_message('warning', '[CASHFREE WEBHOOK] [additional_charge] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return;
        }

        $existing = fetch_details('transactions', ['id' => $charge_id]);
        if (empty($existing)) {
            log_message('warning', '[CASHFREE WEBHOOK] [additional_charge] txn=' . $charge_id . ' not found. Skipping.');
            return;
        }
        if ($existing[0]['status'] === 'success') {
            log_message('info', '[CASHFREE WEBHOOK] [additional_charge] txn=' . $charge_id . ' already success. Skipping.');
            return;
        }

        // Validate against the pending row's amount — what the client intended and what
        // cashfree_create_order charged. orders.total_additional_charge may be cumulative.
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

        update_details(
            ['status' => 'success', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'amount' => $ctx['amount'], 'reference' => $ctx['gateway_order_id']],
            ['id' => $charge_id],
            'transactions'
        );

        if (!$already_paid_flag) {
            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $ctx['order_id']], 'orders');
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
                    'payment_method' => 'Cashfree',
                    'paid_at'        => date('d-m-Y H:i:s'),
                ],
                $ctx['order_data']
            );
        }
    }

    // =========================================================
    // FAILED
    // =========================================================

    private function handlePaymentFailed(array $ctx)
    {
        log_message('warning', '[CASHFREE WEBHOOK] [failed] order=' . $ctx['order_id'] . ' gateway_order_id=' . $ctx['gateway_order_id'] . ' reason=' . $ctx['failure_reason']);

        // Subscription failure
        if ($ctx['subscription_transaction_id'] > 0) {
            $sub_id  = $ctx['subscription_transaction_id'];
            $sub_row = fetch_details('transactions', ['id' => $sub_id]);
            if (!empty($sub_row) && in_array($sub_row[0]['status'] ?? '', ['success', 'failed'], true)) {
                return $this->okJson('Already processed');
            }
            update_details(
                ['status' => 'failed', 'txn_id' => $ctx['txn_id'], 'reference' => $ctx['gateway_order_id']],
                ['id' => $sub_id],
                'transactions'
            );
            send_subscription_payment_status_notification($sub_id, 'failed');
            return $this->okJson('Subscription failed');
        }

        // Additional charge failed
        if ($ctx['additional_charge_id'] > 0) {
            $charge_id = $ctx['additional_charge_id'];
            $existing  = fetch_details('transactions', ['id' => $charge_id]);
            if (!empty($existing) && $existing[0]['status'] === 'failed') {
                log_message('info', '[CASHFREE WEBHOOK] [failed] additional_charge txn=' . $charge_id . ' already failed. Skipping.');
                return $this->okJson('Already processed');
            }

            $expected_currency = !empty($ctx['order_data']) ? (string) ($ctx['order_data'][0]['currency_code'] ?? '') : '';
            $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

            $db = \Config\Database::connect();
            $db->transStart();
            update_details(
                ['status' => 'failed', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'reference' => $ctx['gateway_order_id']],
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
                    'payment_method' => 'Cashfree',
                    'failure_reason' => $ctx['failure_reason'],
                ], $ctx['order_data']);
            }
            return $this->okJson('Processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('warning', '[CASHFREE WEBHOOK] [failed] order_id missing. gateway_order_id=' . $ctx['gateway_order_id']);
            return $this->okJson('No order id');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingCashfreeTransactionForOrder($ctx['order_id']);

        if (!empty($existing) && $existing['status'] === 'failed') {
            log_message('info', '[CASHFREE WEBHOOK] [failed] txn=' . $existing['id'] . ' already failed. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_paid      = !empty($ctx['order_data']) && ((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 1;
        $order_already_cancelled = !empty($ctx['order_data'])
            && (((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 2
                || ($ctx['order_data'][0]['status'] ?? '') === 'cancelled');

        $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        if (empty($existing)) {
            log_message('error', '[CASHFREE WEBHOOK] [failed] no pending cashfree row for order=' . $ctx['order_id']
                . ' gateway_order_id=' . $ctx['gateway_order_id'] . ' cf_payment_id=' . $ctx['txn_id']
                . '. Intent never recorded — skipping insert.');
            return $this->okJson('No pending intent row');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // Never overwrite a paid order with failed.
        if (!$order_already_paid && !$order_already_cancelled) {
            update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'cancelled');
        }

        update_details(
            ['status' => 'failed', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'reference' => $ctx['gateway_order_id']],
            ['id' => $existing['id']],
            'transactions'
        );

        $db->transComplete();

        if (!$order_already_paid && !$order_already_cancelled) {
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'failed', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['txn_id'],
                'payment_method' => 'Cashfree',
                'failure_reason' => $ctx['failure_reason'],
            ], $ctx['order_data']);
        }

        return $this->okJson('Processed');
    }

    // =========================================================
    // PENDING (e.g. payment in 3DS / awaiting bank)
    // =========================================================

    private function handlePaymentPending(array $ctx)
    {
        if ($ctx['additional_charge_id'] > 0) {
            $charge_id = $ctx['additional_charge_id'];
            $existing  = fetch_details('transactions', ['id' => $charge_id]);
            if (!empty($existing) && $existing[0]['status'] === 'pending') {
                log_message('info', '[CASHFREE WEBHOOK] [pending] additional_charge txn=' . $charge_id . ' already pending. Skipping.');
                return $this->okJson('Already processed');
            }

            $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
            $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

            update_details(
                ['status' => 'pending', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'reference' => $ctx['gateway_order_id']],
                ['id' => $charge_id],
                'transactions'
            );

            if (!empty($ctx['order_data'])) {
                $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'pending', [
                    'amount'         => $ctx['amount'],
                    'currency'       => $stored_currency,
                    'transaction_id' => $ctx['txn_id'],
                    'payment_method' => 'Cashfree',
                ], $ctx['order_data']);
            }
            return $this->okJson('Processed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('warning', '[CASHFREE WEBHOOK] [pending] order_id missing. gateway_order_id=' . $ctx['gateway_order_id']);
            return $this->okJson('No order id');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingCashfreeTransactionForOrder($ctx['order_id']);

        if (empty($existing)) {
            log_message('error', '[CASHFREE WEBHOOK] [pending] no pending cashfree row for order=' . $ctx['order_id']
                . ' gateway_order_id=' . $ctx['gateway_order_id'] . '. Intent never recorded — skipping insert.');
            return $this->okJson('No pending intent row');
        }

        if ($existing['status'] === 'pending') {
            log_message('info', '[CASHFREE WEBHOOK] [pending] txn=' . $existing['id'] . ' already pending. Skipping.');
            return $this->okJson('Already processed');
        }

        $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        update_details(
            ['status' => 'pending', 'txn_id' => $ctx['txn_id'], 'currency_code' => $stored_currency, 'reference' => $ctx['gateway_order_id']],
            ['id' => $existing['id']],
            'transactions'
        );

        $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'pending', [
            'amount'         => $ctx['amount'],
            'currency'       => $stored_currency,
            'transaction_id' => $ctx['txn_id'],
            'payment_method' => 'Cashfree',
        ], $ctx['order_data']);

        return $this->okJson('Processed');
    }

    // =========================================================
    // REFUND
    // =========================================================

    private function handleRefundProcessed(array $ctx)
    {
        $refund_data = $ctx['order_id'] > 0 ? null : null; // placeholder no-op
        $event_refund = $ctx; // for log clarity

        // Match the original successful txn by reference (gateway_order_id) since the
        // refund payload's `payment_id` may not equal the original cf_payment_id.
        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'cashfree',
            'status'           => 'success',
            'reference'        => $ctx['gateway_order_id'],
        ]);

        if (empty($success_transaction)) {
            log_message('warning', '[CASHFREE WEBHOOK] [refund] no matching success txn for gateway_order_id=' . $ctx['gateway_order_id'] . '. Skipping.');
            return $this->response->setStatusCode(200)->setBody('');
        }

        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'cashfree',
            'message'          => 'txn_refund_processed',
            'reference'        => $ctx['gateway_order_id'],
        ]);

        if (!empty($existing_refund)) {
            if (($existing_refund[0]['status'] ?? '') === 'processed') {
                log_message('info', '[CASHFREE WEBHOOK] [refund] refund txn=' . $existing_refund[0]['id'] . ' already processed. Skipping.');
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
            'type'             => 'cashfree',
            'txn_id'           => $ctx['txn_id'],
            'amount'           => $ctx['amount'],
            'status'           => 'processed',
            'currency_code'    => $stored_currency,
            'message'          => 'txn_refund_processed',
            'reference'        => $ctx['gateway_order_id'],
        ]);
        if ($ctx['order_id'] > 0) {
            update_custom_job_status($ctx['order_id'], 'refunded');
        }
        $db->transComplete();

        return $this->response->setStatusCode(200)->setBody('');
    }

    // =========================================================
    // SUBSCRIPTION
    // =========================================================

    private function handleSubscriptionPayment(array $ctx): void
    {
        $transaction_id = $ctx['subscription_transaction_id'];
        $txn_id         = $ctx['txn_id'];
        $amount         = $ctx['amount'];
        $currency       = $ctx['currency'];

        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[CASHFREE WEBHOOK] [subscription] txn=' . $transaction_id . ' not found. Abort.');
            return;
        }

        if ($transaction_details[0]['status'] === 'success') {
            log_message('info', '[CASHFREE WEBHOOK] [subscription] txn=' . $transaction_id . ' already success. Skipping.');
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
            log_message('error', '[CASHFREE WEBHOOK] [subscription] subscription=' . $subscription_id . ' not found. Abort.');
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
            ['status' => 'success', 'txn_id' => $txn_id, 'currency_code' => $store_currency_value, 'reference' => $ctx['gateway_order_id']],
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
            log_message('warning', '[CASHFREE WEBHOOK] [subscription] partner_subscriptions update affected 0 rows. partner=' . $partner_id . ' subscription=' . $subscription_id . ' txn=' . $transaction_id);
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
            log_message('error', '[CASHFREE WEBHOOK] [subscription] notification exception: ' . $e->getMessage());
        }
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Verify with Cashfree API that the order is actually PAID before we mark it paid.
     * Prevents "back to merchant" / test UPI / duplicate webhooks from marking unpaid orders as paid.
     */
    private function verifyOrderPaidWithCashfree(string $gateway_order_id): bool
    {
        if ($gateway_order_id === '') {
            return false;
        }
        $response = $this->cashfree->fetch_order($gateway_order_id);
        if (!empty($response['error'])) {
            log_message('info', '[CASHFREE WEBHOOK] API verification failed: fetch_order error. gateway_order_id=' . $gateway_order_id . ' message=' . ($response['message'] ?? ''));
            return false;
        }
        $order_status = '';
        if (isset($response['order_status'])) {
            $order_status = strtoupper((string) $response['order_status']);
        } elseif (isset($response['order']['order_status'])) {
            $order_status = strtoupper((string) $response['order']['order_status']);
        }
        if ($order_status !== 'PAID') {
            log_message('info', '[CASHFREE WEBHOOK] API verification: order not PAID. gateway_order_id=' . $gateway_order_id . ' order_status=' . ($order_status ?: 'empty'));
            return false;
        }
        return true;
    }

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
                'type'             => 'cashfree',
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

    private function findExistingCashfreeTransactionForOrder(int $order_id): array
    {
        if ($order_id <= 0) {
            return [];
        }
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'type'             => 'cashfree',
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
            log_message('error', '[CASHFREE WEBHOOK] amount mismatch on ' . $label . ': received=' . $actual_amount . ' expected=' . $expected_amount . '. Aborting.');
            return false;
        }
        if ($expected_currency !== '' && $actual_currency !== '' && strcasecmp($actual_currency, $expected_currency) !== 0) {
            log_message('error', '[CASHFREE WEBHOOK] currency mismatch on ' . $label . ': received=' . $actual_currency . ' expected=' . $expected_currency . '. Aborting.');
            return false;
        }
        return true;
    }

    private function okJson(string $message)
    {
        return $this->response->setJSON(['error' => false, 'message' => $message]);
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
                log_message('error', '[CASHFREE WEBHOOK] [notifications] order=' . $order_id . ' not found.');
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
            log_message('error', '[CASHFREE WEBHOOK] [notifications] order=' . $order_id . ' exception: ' . $e->getMessage());
        }
    }

    private function send_online_payment_status_notification(int $order_id, int $user_id, string $status, array $payment_data = [], array $order_data = []): void
    {
        try {
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses, true)) {
                log_message('error', '[CASHFREE WEBHOOK] [payment-notification] invalid status=' . $status);
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
                log_message('error', '[CASHFREE WEBHOOK] [payment-notification] order=' . $order_id . ' not found.');
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
                'payment_method' => $payment_data['payment_method'] ?? 'Cashfree',
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
            log_message('error', '[CASHFREE WEBHOOK] [payment-notification] order=' . $order_id . ' status=' . $status . ' exception: ' . $e->getMessage());
        }
    }
}
