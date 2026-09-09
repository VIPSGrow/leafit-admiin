<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Xendit;

/**
 * Xendit webhook controller. Idempotent against duplicate webhooks and the
 * customer/provider `xendit_transaction_webview` flows that insert a pending row
 * before payment. Validates webhook amount + currency against the order
 * (or pending transaction). Signature verification stays on.
 */
class XenditWebhook extends BaseController
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
        try {
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                log_message('warning', '[XENDIT WEBHOOK] Rejected: method not allowed');
                return $this->response->setStatusCode(405)->setJSON(['error' => true, 'message' => 'Method not allowed']);
            }

            $raw_body = file_get_contents('php://input');
            $event    = json_decode($raw_body, true);

            if (empty($event) || !is_array($event)) {
                log_message('error', '[XENDIT WEBHOOK] Rejected: invalid or empty JSON payload');
                return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
            }

            $signature = (string) ($_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '');
            $xendit    = new Xendit();
            if (!$xendit->verify_webhook($signature)) {
                log_message('error', '[XENDIT WEBHOOK] Invalid signature. Rejected.');
                // 400 instead of 401 — Xendit retry-storms on non-2xx; 400 is "do not retry".
                return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid signature']);
            }

            $event_type = (string) ($event['event'] ?? '');
            $status     = strtoupper((string) ($event['status'] ?? ''));
            log_message('info', '[XENDIT WEBHOOK] event=' . ($event_type ?: $status));

            // Refund events
            if ($event_type === 'refund.succeeded' || $event_type === 'ewallet.refund' || $event_type === 'refund.completed' || $status === 'REFUNDED' || $status === 'REFUND_SUCCEEDED') {
                $format = $event_type === 'refund.succeeded' ? 'new_format'
                    : ($event_type === 'ewallet.refund' ? 'ewallet_format' : 'legacy_format');
                return $this->handleRefundProcessed($event, $format);
            }
            if ($event_type === 'refund.failed' || $status === 'REFUND_FAILED') {
                $format = $event_type === 'refund.failed' ? 'new_format' : 'legacy_format';
                return $this->handleRefundFailed($event, $format);
            }

            $ctx = $this->parsePayloadContext($event);

            if ($event_type === 'invoice.paid' || $status === 'PAID') {
                return $this->handleInvoicePaid($ctx);
            }
            if ($event_type === 'invoice.expired' || $status === 'EXPIRED') {
                return $this->handleInvoiceExpired($ctx);
            }
            if ($event_type === 'invoice.payment_failed' || $status === 'FAILED') {
                return $this->handleInvoiceFailed($ctx);
            }

            log_message('info', '[XENDIT WEBHOOK] Unhandled event/status. Acking.');
            return $this->response->setStatusCode(200)->setBody('');
        } catch (\Throwable $th) {
            log_message('error', '[XENDIT WEBHOOK] Exception: ' . $th->getMessage() . ' in ' . $th->getFile() . ':' . $th->getLine());
            return $this->response->setStatusCode(500)->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }

    // =========================================================
    // Payload parsing
    // =========================================================

    private function parsePayloadContext(array $event): array
    {
        $external_id    = (string) ($event['external_id'] ?? '');
        $invoice_id     = (string) ($event['id'] ?? '');
        $payment_id     = (string) ($event['payment_id'] ?? '');
        $amount         = (float) ($event['paid_amount'] ?? $event['amount'] ?? 0);
        $currency       = strtoupper((string) ($event['currency'] ?? ''));
        $payment_method = (string) ($event['payment_method'] ?? '');
        $failure_reason = (string) ($event['failure_reason'] ?? 'Payment could not be processed.');
        $metadata       = $event['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        $parts            = $external_id !== '' ? explode('_', $external_id) : [];
        $prefix           = $parts[0] ?? '';
        $is_subscription  = $prefix === 'subscription';
        $is_additional    = $prefix === 'additionalCharges';
        $is_order         = $prefix === 'order';

        $subscription_id              = 0;
        $subscription_partner_id      = 0;
        $additional_charge_id         = 0;
        $order_id                     = 0;

        if ($is_subscription) {
            $subscription_id         = (int) ($parts[1] ?? 0);
            $subscription_partner_id = (int) ($parts[2] ?? 0);
        } elseif ($is_additional) {
            $additional_charge_id = (int) ($parts[1] ?? 0);
        } elseif ($is_order) {
            $order_id = (int) ($parts[1] ?? 0);
        }

        // Look up existing transaction by reference (external_id). Fall back to
        // legacy txn_id=external_id rows from before the refactor.
        $transaction = [];
        if ($external_id !== '') {
            $transaction = fetch_details('transactions', ['type' => 'xendit', 'reference' => $external_id]);
            if (empty($transaction)) {
                $transaction = fetch_details('transactions', ['type' => 'xendit', 'txn_id' => $external_id]);
            }
        }

        // Subscription transaction id: prefer metadata, then matched transaction row.
        $subscription_transaction_id = (int) ($metadata['transaction_id'] ?? 0);
        if ($subscription_transaction_id === 0 && $is_subscription && !empty($transaction) && !empty($transaction[0]['id'])) {
            $subscription_transaction_id = (int) $transaction[0]['id'];
        }

        // Resolve order_id from matched transaction row when prefix didn't carry it
        // (e.g. additional charges → pull order_id from the txn).
        if ($order_id === 0 && !empty($transaction) && !empty($transaction[0]['order_id'])) {
            $order_id = (int) $transaction[0]['order_id'];
        }

        $order_data = [];
        $user_id    = 0;
        $partner_id = 0;
        if ($order_id > 0) {
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (!empty($order_data)) {
                $user_id    = (int) $order_data[0]['user_id'];
                $partner_id = (int) $order_data[0]['partner_id'];
            }
        }

        return [
            'external_id'                 => $external_id,
            'invoice_id'                  => $invoice_id,
            'payment_id'                  => $payment_id,
            'amount'                      => $amount,
            'currency'                    => $currency,
            'payment_method'              => $payment_method,
            'failure_reason'              => $failure_reason,
            'metadata'                    => $metadata,
            'transaction'                 => $transaction,
            'is_subscription'             => $is_subscription,
            'is_additional'               => $is_additional,
            'is_order'                    => $is_order,
            'subscription_id'             => $subscription_id,
            'subscription_partner_id'     => $subscription_partner_id,
            'subscription_transaction_id' => $subscription_transaction_id,
            'additional_charge_id'        => $additional_charge_id,
            'order_id'                    => $order_id,
            'user_id'                     => $user_id,
            'partner_id'                  => $partner_id,
            'order_data'                  => $order_data,
            'paid_at'                     => (string) ($event['paid_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    // =========================================================
    // invoice.paid
    // =========================================================

    private function handleInvoicePaid(array $ctx)
    {
        if ($ctx['external_id'] === '') {
            log_message('error', '[XENDIT WEBHOOK] [paid] external_id missing.');
            return $this->okJson('No external_id');
        }

        if ($ctx['is_subscription']) {
            $this->handleSubscriptionPaid($ctx);
            return $this->okJson('Subscription processed');
        }

        if ($ctx['is_additional']) {
            $this->handleAdditionalChargeSuccess($ctx);
            return $this->okJson('Additional charge processed');
        }

        // Regular order path
        if ($ctx['order_id'] === 0) {
            log_message('error', '[XENDIT WEBHOOK] [paid] order_id missing. external_id=' . $ctx['external_id']);
            return $this->okJson('No order id');
        }

        if (empty($ctx['order_data'])) {
            log_message('warning', '[XENDIT WEBHOOK] [paid] order=' . $ctx['order_id'] . ' not found. Skipping.');
            return $this->okJson('Order not found');
        }

        // Idempotency on already-finalised pending row.
        if (!empty($ctx['transaction']) && in_array($ctx['transaction'][0]['status'] ?? '', ['success', 'failed'], true)) {
            log_message('info', '[XENDIT WEBHOOK] [paid] txn=' . $ctx['transaction'][0]['id'] . ' already ' . $ctx['transaction'][0]['status'] . '. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_final_total      = (float) ($ctx['order_data'][0]['final_total'] ?? 0);
        $order_total            = (float) ($ctx['order_data'][0]['total'] ?? 0);
        $order_additional_total = (float) ($ctx['order_data'][0]['total_additional_charge'] ?? 0);

        $amount_matches_final            = $order_final_total > 0 && abs($ctx['amount'] - $order_final_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_total            = $order_total > 0 && abs($ctx['amount'] - $order_total) <= self::AMOUNT_TOLERANCE;
        $amount_matches_additional_total = $order_additional_total > 0 && abs($ctx['amount'] - $order_additional_total) <= self::AMOUNT_TOLERANCE;

        // Fallback: external_id lacked additionalCharges_ prefix but amount matches the order's
        // additional-charge total — route to additional-charge handler.
        if (!$amount_matches_final && !$amount_matches_total && $amount_matches_additional_total) {
            $pending_additional = $this->findPendingAdditionalChargeForOrder($ctx['order_id']);
            if (!empty($pending_additional)) {
                $ctx['additional_charge_id'] = (int) $pending_additional['id'];
                log_message('info', '[XENDIT WEBHOOK] [paid] inferred additional charge txn=' . $ctx['additional_charge_id'] . ' for order=' . $ctx['order_id']);
                $this->handleAdditionalChargeSuccess($ctx);
                return $this->okJson('Additional charge processed');
            }
        }

        $expected_amount   = $order_final_total > 0 ? $order_final_total : $order_total;
        $expected_currency = (string) ($ctx['order_data'][0]['currency_code'] ?? '');
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'order ' . $ctx['order_id'])) {
            log_message('error', '[XENDIT WEBHOOK] [paid] order=' . $ctx['order_id']
                . ' totals: final_total=' . $order_final_total
                . ' total=' . $order_total
                . ' total_additional_charge=' . $order_additional_total);
            return $this->okJson('Amount or currency mismatch');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingXenditTransactionForOrder($ctx['order_id']);

        if (empty($existing)) {
            log_message('error', '[XENDIT WEBHOOK] [paid] no pending xendit row for order=' . $ctx['order_id']
                . ' external_id=' . $ctx['external_id']
                . '. Intent never recorded — skipping insert. Reconcile manually.');
            return $this->okJson('No pending intent row');
        }

        if ($existing['status'] === 'success') {
            log_message('info', '[XENDIT WEBHOOK] [paid] txn=' . $existing['id'] . ' already success. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_paid = ((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 1;
        $stored_currency    = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            [
                'status'        => 'success',
                'txn_id'        => $ctx['payment_id'] ?: $ctx['external_id'],
                'amount'        => $ctx['amount'],
                'currency_code' => $stored_currency,
                'reference'     => $ctx['external_id'],
                'message'       => 'txn_order_placed',
            ],
            ['id' => $existing['id']],
            'transactions'
        );

        if (!$order_already_paid) {
            update_details(['payment_status' => 1], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'booked');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', '[XENDIT WEBHOOK] [paid] DB transaction failed. order=' . $ctx['order_id']);
            return $this->okJson('DB error logged');
        }

        if (!$order_already_paid) {
            delete_details(['user_id' => $ctx['user_id']], 'cart');
            $this->send_booking_notifications($ctx['order_id'], $ctx['user_id'], $ctx['partner_id'], $ctx['order_data']);
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'success', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['payment_id'] ?: $ctx['external_id'],
                'payment_method' => $ctx['payment_method'] ?: 'Xendit',
                'paid_at'        => $ctx['paid_at'] ?: date('d-m-Y H:i:s'),
            ], $ctx['order_data']);
        } else {
            log_message('info', '[XENDIT WEBHOOK] [paid] order=' . $ctx['order_id'] . ' already paid. Skipped notifications.');
        }

        return $this->okJson('Processed');
    }

    private function handleAdditionalChargeSuccess(array $ctx): void
    {
        $charge_id = $ctx['additional_charge_id'];
        if ($charge_id <= 0 && !empty($ctx['transaction'])) {
            $charge_id = (int) $ctx['transaction'][0]['id'];
        }
        if ($charge_id <= 0) {
            log_message('warning', '[XENDIT WEBHOOK] [additional_charge] charge_id missing. external_id=' . $ctx['external_id']);
            return;
        }

        $existing = fetch_details('transactions', ['id' => $charge_id]);
        if (empty($existing)) {
            log_message('warning', '[XENDIT WEBHOOK] [additional_charge] txn=' . $charge_id . ' not found.');
            return;
        }
        if ($existing[0]['status'] === 'success') {
            log_message('info', '[XENDIT WEBHOOK] [additional_charge] txn=' . $charge_id . ' already success. Skipping.');
            return;
        }

        // Ensure we have order context (additionalCharges_ prefix carries txn id, not order id).
        $order_id   = (int) ($existing[0]['order_id'] ?? 0);
        $order_data = $order_id > 0 ? fetch_details('orders', ['id' => $order_id]) : [];
        if (empty($order_data)) {
            log_message('warning', '[XENDIT WEBHOOK] [additional_charge] order=' . $order_id . ' not found. Skipping.');
            return;
        }
        $user_id = (int) $order_data[0]['user_id'];

        // Validate against pending row's amount (source of truth).
        $expected_amount   = (float) ($existing[0]['amount'] ?? 0);
        $expected_currency = (string) ($existing[0]['currency_code'] ?? '');
        if ($expected_currency === '') {
            $expected_currency = (string) ($order_data[0]['currency_code'] ?? '');
        }
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $expected_currency, 'additional charge txn ' . $charge_id . ' for order ' . $order_id)) {
            return;
        }

        $already_paid_flag = ((string) ($order_data[0]['payment_status_of_additional_charge'] ?? '')) === '1';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        update_details(
            [
                'status'        => 'success',
                'txn_id'        => $ctx['payment_id'] ?: $ctx['external_id'],
                'amount'        => $ctx['amount'],
                'currency_code' => $stored_currency,
                'reference'     => $ctx['external_id'],
            ],
            ['id' => $charge_id],
            'transactions'
        );

        if (!$already_paid_flag) {
            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');

            $this->send_online_payment_status_notification(
                $order_id,
                $user_id,
                'success',
                [
                    'amount'         => $ctx['amount'],
                    'currency'       => $stored_currency,
                    'transaction_id' => $ctx['payment_id'] ?: $ctx['external_id'],
                    'payment_method' => $ctx['payment_method'] ?: 'Xendit',
                    'paid_at'        => $ctx['paid_at'] ?: date('d-m-Y H:i:s'),
                ],
                $order_data
            );
        }
    }

    // =========================================================
    // invoice.payment_failed
    // =========================================================

    private function handleInvoiceFailed(array $ctx)
    {
        log_message('warning', '[XENDIT WEBHOOK] [failed] external_id=' . $ctx['external_id'] . ' reason=' . $ctx['failure_reason']);

        if ($ctx['is_subscription']) {
            $this->markSubscriptionFailed($ctx);
            return $this->okJson('Subscription failed');
        }

        if ($ctx['is_additional']) {
            $this->markAdditionalChargeFailed($ctx);
            return $this->okJson('Additional charge failed');
        }

        if ($ctx['order_id'] === 0) {
            log_message('warning', '[XENDIT WEBHOOK] [failed] order_id missing. external_id=' . $ctx['external_id']);
            return $this->okJson('No order id');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingXenditTransactionForOrder($ctx['order_id']);

        if (!empty($existing) && $existing['status'] === 'failed') {
            log_message('info', '[XENDIT WEBHOOK] [failed] txn=' . $existing['id'] . ' already failed. Skipping.');
            return $this->okJson('Already processed');
        }

        $order_already_paid      = !empty($ctx['order_data']) && ((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 1;
        $order_already_cancelled = !empty($ctx['order_data'])
            && (((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 2
                || ($ctx['order_data'][0]['status'] ?? '') === 'cancelled');

        $expected_currency = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        if (empty($existing)) {
            log_message('error', '[XENDIT WEBHOOK] [failed] no pending xendit row for order=' . $ctx['order_id']
                . ' external_id=' . $ctx['external_id'] . '. Intent never recorded — skipping insert.');
            return $this->okJson('No pending intent row');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        if (!$order_already_paid && !$order_already_cancelled) {
            update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'cancelled');
        }

        update_details(
            [
                'status'        => 'failed',
                'txn_id'        => $ctx['payment_id'] ?: $ctx['external_id'],
                'amount'        => $ctx['amount'],
                'currency_code' => $stored_currency,
                'reference'     => $ctx['external_id'],
            ],
            ['id' => $existing['id']],
            'transactions'
        );

        $db->transComplete();

        if (!$order_already_paid && !$order_already_cancelled) {
            $this->send_online_payment_status_notification($ctx['order_id'], $ctx['user_id'], 'failed', [
                'amount'         => $ctx['amount'],
                'currency'       => $stored_currency,
                'transaction_id' => $ctx['payment_id'] ?: $ctx['external_id'],
                'payment_method' => $ctx['payment_method'] ?: 'Xendit',
                'failure_reason' => $ctx['failure_reason'],
            ], $ctx['order_data']);
        }

        return $this->okJson('Processed');
    }

    // =========================================================
    // invoice.expired
    // =========================================================

    private function handleInvoiceExpired(array $ctx)
    {
        log_message('warning', '[XENDIT WEBHOOK] [expired] external_id=' . $ctx['external_id']);

        if ($ctx['is_subscription']) {
            // Mark pending subscription row failed; expire active partner_subscriptions if any.
            $sub_id = $ctx['subscription_id'];
            $partner_id = $ctx['subscription_partner_id'];
            if ($sub_id > 0 && $partner_id > 0) {
                update_details(['status' => 'expired'], [
                    'subscription_id' => $sub_id,
                    'partner_id'      => $partner_id,
                    'status'          => 'active',
                ], 'partner_subscriptions');
            }
            if (!empty($ctx['transaction']) && !in_array($ctx['transaction'][0]['status'] ?? '', ['success', 'failed'], true)) {
                update_details(
                    ['status' => 'failed', 'message' => 'txn_payment_expired', 'reference' => $ctx['external_id']],
                    ['id' => $ctx['transaction'][0]['id']],
                    'transactions'
                );
                send_subscription_payment_status_notification((int) $ctx['transaction'][0]['id'], 'failed', 'Invoice expired');
            }
            $this->sendSubscriptionExpiredNotification($sub_id, $partner_id);
            return $this->okJson('Subscription expired');
        }

        if ($ctx['is_additional']) {
            $this->markAdditionalChargeFailed($ctx, 'txn_payment_expired');
            return $this->okJson('Additional charge expired');
        }

        if ($ctx['order_id'] === 0) {
            log_message('warning', '[XENDIT WEBHOOK] [expired] order_id missing. external_id=' . $ctx['external_id']);
            return $this->okJson('No order id');
        }

        $existing = !empty($ctx['transaction'])
            ? $ctx['transaction'][0]
            : $this->findExistingXenditTransactionForOrder($ctx['order_id']);

        if (!empty($existing) && in_array($existing['status'] ?? '', ['success', 'failed'], true)) {
            log_message('info', '[XENDIT WEBHOOK] [expired] txn=' . $existing['id'] . ' already ' . $existing['status'] . '. Skipping.');
            return $this->okJson('Already processed');
        }

        if (empty($existing)) {
            log_message('error', '[XENDIT WEBHOOK] [expired] no pending xendit row for order=' . $ctx['order_id']
                . ' external_id=' . $ctx['external_id'] . '. Skipping insert.');
            return $this->okJson('No pending intent row');
        }

        $order_already_paid = !empty($ctx['order_data']) && ((int) ($ctx['order_data'][0]['payment_status'] ?? 0)) === 1;
        $expected_currency  = !empty($ctx['order_data']) ? (string) $ctx['order_data'][0]['currency_code'] : '';
        $stored_currency    = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        if (!$order_already_paid) {
            update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $ctx['order_id']], 'orders');
            update_custom_job_status($ctx['order_id'], 'cancelled');
        }

        update_details(
            [
                'status'        => 'failed',
                'currency_code' => $stored_currency,
                'reference'     => $ctx['external_id'],
                'message'       => 'txn_payment_expired',
            ],
            ['id' => $existing['id']],
            'transactions'
        );

        $db->transComplete();

        return $this->okJson('Processed');
    }

    private function markAdditionalChargeFailed(array $ctx, string $message = ''): void
    {
        $charge_id = $ctx['additional_charge_id'];
        if ($charge_id <= 0 && !empty($ctx['transaction'])) {
            $charge_id = (int) $ctx['transaction'][0]['id'];
        }
        if ($charge_id <= 0) {
            log_message('warning', '[XENDIT WEBHOOK] [additional_charge_failed] charge_id missing. external_id=' . $ctx['external_id']);
            return;
        }

        $existing = fetch_details('transactions', ['id' => $charge_id]);
        if (empty($existing)) {
            log_message('warning', '[XENDIT WEBHOOK] [additional_charge_failed] txn=' . $charge_id . ' not found.');
            return;
        }
        if (in_array($existing[0]['status'] ?? '', ['success', 'failed'], true) && $existing[0]['status'] === 'failed' && $message === '') {
            log_message('info', '[XENDIT WEBHOOK] [additional_charge_failed] txn=' . $charge_id . ' already failed. Skipping.');
            return;
        }

        $order_id   = (int) ($existing[0]['order_id'] ?? 0);
        $order_data = $order_id > 0 ? fetch_details('orders', ['id' => $order_id]) : [];
        $expected_currency = !empty($order_data) ? (string) $order_data[0]['currency_code'] : '';
        $stored_currency   = $this->resolveStoredCurrency($expected_currency, $ctx['currency']);

        $update = [
            'status'        => 'failed',
            'currency_code' => $stored_currency,
            'reference'     => $ctx['external_id'],
        ];
        if ($message !== '') {
            $update['message'] = $message;
        }
        if (!empty($ctx['payment_id'])) {
            $update['txn_id'] = $ctx['payment_id'];
        }

        update_details($update, ['id' => $charge_id], 'transactions');
        if ($order_id > 0) {
            update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id], 'orders');
        }

        if (!empty($order_data)) {
            $this->send_online_payment_status_notification(
                $order_id,
                (int) $order_data[0]['user_id'],
                'failed',
                [
                    'amount'         => $ctx['amount'],
                    'currency'       => $stored_currency,
                    'transaction_id' => $ctx['payment_id'] ?: $ctx['external_id'],
                    'payment_method' => $ctx['payment_method'] ?: 'Xendit',
                    'failure_reason' => $ctx['failure_reason'],
                ],
                $order_data
            );
        }
    }

    // =========================================================
    // Subscription
    // =========================================================

    private function handleSubscriptionPaid(array $ctx): void
    {
        $transaction_id = $ctx['subscription_transaction_id'];
        if ($transaction_id <= 0) {
            log_message('error', '[XENDIT WEBHOOK] [subscription] transaction_id missing. external_id=' . $ctx['external_id']);
            return;
        }

        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[XENDIT WEBHOOK] [subscription] txn=' . $transaction_id . ' not found.');
            return;
        }
        if ($transaction_details[0]['status'] === 'success') {
            log_message('info', '[XENDIT WEBHOOK] [subscription] txn=' . $transaction_id . ' already success. Skipping.');
            return;
        }

        $subscription_id = (int) ($transaction_details[0]['subscription_id'] ?? $ctx['subscription_id']);
        $partner_id      = (int) ($transaction_details[0]['user_id'] ?? $ctx['subscription_partner_id']);

        $expected_amount = (float) ($transaction_details[0]['amount'] ?? 0);
        $stored_currency = (string) ($transaction_details[0]['currency_code'] ?? '');
        if (!$this->validatePayment($ctx['amount'], $ctx['currency'], $expected_amount, $stored_currency, 'subscription txn ' . $transaction_id)) {
            return;
        }

        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($details_for_subscription)) {
            log_message('error', '[XENDIT WEBHOOK] [subscription] subscription=' . $subscription_id . ' not found.');
            return;
        }

        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $details_for_subscription[0]['duration'] ?? 0;
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
        if ($subscriptionDuration === 'unlimited') {
            $subscriptionDuration = 0;
        }

        $store_currency_value = $this->resolveStoredCurrency($stored_currency, $ctx['currency']);

        $db = \Config\Database::connect();
        $db->transStart();

        update_details(
            [
                'status'        => 'success',
                'txn_id'        => $ctx['payment_id'] ?: $ctx['external_id'],
                'amount'        => $ctx['amount'],
                'currency_code' => $store_currency_value,
                'reference'     => $ctx['external_id'],
            ],
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
            log_message('warning', '[XENDIT WEBHOOK] [subscription] partner_subscriptions update affected 0 rows. partner=' . $partner_id . ' subscription=' . $subscription_id . ' txn=' . $transaction_id);
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
            log_message('error', '[XENDIT WEBHOOK] [subscription] notification exception: ' . $e->getMessage());
        }
    }

    private function markSubscriptionFailed(array $ctx): void
    {
        $transaction_id = $ctx['subscription_transaction_id'];
        if ($transaction_id <= 0 && !empty($ctx['transaction'])) {
            $transaction_id = (int) $ctx['transaction'][0]['id'];
        }
        if ($transaction_id <= 0) {
            log_message('warning', '[XENDIT WEBHOOK] [subscription_failed] transaction_id missing. external_id=' . $ctx['external_id']);
            return;
        }

        $row = fetch_details('transactions', ['id' => $transaction_id]);
        if (!empty($row) && in_array($row[0]['status'] ?? '', ['success', 'failed'], true)) {
            log_message('info', '[XENDIT WEBHOOK] [subscription_failed] txn=' . $transaction_id . ' already ' . $row[0]['status'] . '. Skipping.');
            return;
        }

        update_details(
            [
                'status'        => 'failed',
                'txn_id'        => $ctx['payment_id'] ?: $ctx['external_id'],
                'amount'        => $ctx['amount'],
                'currency_code' => $this->resolveStoredCurrency((string) ($row[0]['currency_code'] ?? ''), $ctx['currency']),
                'reference'     => $ctx['external_id'],
            ],
            ['id' => $transaction_id],
            'transactions'
        );

        if ($ctx['subscription_id'] > 0 && $ctx['subscription_partner_id'] > 0) {
            update_details(['status' => 'failed'], [
                'subscription_id' => $ctx['subscription_id'],
                'partner_id'      => $ctx['subscription_partner_id'],
                'status'          => 'pending',
            ], 'partner_subscriptions');
        }

        send_subscription_payment_status_notification($transaction_id, 'failed', $ctx['failure_reason']);
    }

    private function sendSubscriptionExpiredNotification(int $subscription_id, int $partner_id): void
    {
        if ($subscription_id <= 0 || $partner_id <= 0) {
            return;
        }
        try {
            $subscription_details = fetch_details('partner_subscriptions', [
                'subscription_id' => $subscription_id,
                'partner_id'      => $partner_id,
            ]);
            if (empty($subscription_details)) {
                return;
            }
            $sub = $subscription_details[0];
            $provider_details = fetch_details('users', ['id' => $partner_id], ['username']);
            $provider_name    = !empty($provider_details) ? $provider_details[0]['username'] : 'Provider';

            $subscription_name = '';
            $sub_info = fetch_details('subscriptions', ['id' => $subscription_id], ['name']);
            if (!empty($sub_info)) {
                $subscription_name = (string) $sub_info[0]['name'];
            }

            $context = [
                'subscription_id'   => (string) $subscription_id,
                'subscription_name' => $subscription_name,
                'provider_id'       => $partner_id,
                'provider_name'     => $provider_name,
                'expiry_date'       => !empty($sub['expiry_date']) ? date('d-m-Y', strtotime($sub['expiry_date'])) : date('d-m-Y'),
                'purchase_date'     => !empty($sub['purchase_date']) ? date('d-m-Y', strtotime($sub['purchase_date'])) : '',
                'duration'          => $sub['duration'] ?? '',
            ];

            queue_notification_service(
                eventType: 'subscription_expired',
                recipients: ['user_id' => $partner_id],
                context: $context,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $this->defaultLanguage,
                    'platforms' => ['android', 'ios', 'provider_panel'],
                    'type'      => 'subscription',
                    'data'      => [
                        'subscription_id' => (string) $subscription_id,
                        'provider_id'     => (string) $partner_id,
                        'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[XENDIT WEBHOOK] [subscription_expired_notification] ' . $e->getMessage());
        }
    }

    // =========================================================
    // Refund
    // =========================================================

    private function handleRefundProcessed(array $event, string $format)
    {
        log_message('info', '[XENDIT WEBHOOK] [refund.processed] format=' . $format);

        $info = $this->extractRefundFields($event, $format);

        $success_transaction = $this->findOriginalTransactionForRefund($info['external_id'], $info['payment_id']);
        if (empty($success_transaction)) {
            log_message('warning', '[XENDIT WEBHOOK] [refund.processed] no matching success txn. external_id=' . $info['external_id'] . ' payment_id=' . $info['payment_id']);
            return $this->response->setStatusCode(200)->setBody('');
        }

        $original   = $success_transaction[0];
        $order_id   = (int) ($original['order_id'] ?? 0);
        $user_id    = (int) ($original['user_id'] ?? 0);
        $partner_id = (int) ($original['partner_id'] ?? 0);

        // Idempotency keyed by reference=external_id on the refund row.
        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'xendit',
            'message'          => 'txn_refund_processed',
            'reference'        => $info['external_id'],
        ]);

        if (!empty($existing_refund)) {
            if (($existing_refund[0]['status'] ?? '') === 'succeeded') {
                log_message('info', '[XENDIT WEBHOOK] [refund.processed] refund txn=' . $existing_refund[0]['id'] . ' already succeeded. Skipping.');
                return $this->response->setStatusCode(200)->setBody('');
            }
            update_details(
                ['status' => 'succeeded', 'amount' => $info['amount'], 'currency_code' => strtoupper($info['currency'])],
                ['id' => $existing_refund[0]['id']],
                'transactions'
            );
            return $this->response->setStatusCode(200)->setBody('');
        }

        $stored_currency = (string) ($original['currency_code'] ?? '');
        if ($stored_currency === '') {
            $stored_currency = strtoupper($info['currency']);
        }

        $db = \Config\Database::connect();
        $db->transStart();
        add_transaction([
            'transaction_type' => 'refund',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'xendit',
            'txn_id'           => $info['payment_id'] ?: $original['txn_id'],
            'amount'           => $info['amount'],
            'status'           => 'succeeded',
            'currency_code'    => $stored_currency,
            'message'          => 'txn_refund_processed',
            'reference'        => $info['external_id'],
        ]);
        if ($order_id > 0) {
            update_custom_job_status($order_id, 'refunded');
        }
        $db->transComplete();

        $this->sendRefundNotifications($order_id, $user_id, $info, $stored_currency);

        return $this->response->setStatusCode(200)->setBody('');
    }

    private function handleRefundFailed(array $event, string $format)
    {
        log_message('warning', '[XENDIT WEBHOOK] [refund.failed] format=' . $format);

        $info = $this->extractRefundFields($event, $format);

        $success_transaction = $this->findOriginalTransactionForRefund($info['external_id'], $info['payment_id']);
        if (empty($success_transaction)) {
            log_message('warning', '[XENDIT WEBHOOK] [refund.failed] no matching success txn. external_id=' . $info['external_id'] . ' payment_id=' . $info['payment_id']);
            return $this->response->setStatusCode(200)->setBody('');
        }

        $original   = $success_transaction[0];
        $order_id   = (int) ($original['order_id'] ?? 0);
        $user_id    = (int) ($original['user_id'] ?? 0);
        $partner_id = (int) ($original['partner_id'] ?? 0);

        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'xendit',
            'message'          => 'txn_refund_processed',
            'reference'        => $info['external_id'],
        ]);

        $stored_currency = (string) ($original['currency_code'] ?? '');
        if ($stored_currency === '') {
            $stored_currency = strtoupper($info['currency']);
        }

        if (!empty($existing_refund)) {
            if (($existing_refund[0]['status'] ?? '') === 'failed') {
                log_message('info', '[XENDIT WEBHOOK] [refund.failed] refund txn=' . $existing_refund[0]['id'] . ' already failed. Skipping.');
                return $this->response->setStatusCode(200)->setBody('');
            }
            update_details(
                ['status' => 'failed', 'amount' => $info['amount'], 'currency_code' => $stored_currency],
                ['id' => $existing_refund[0]['id']],
                'transactions'
            );
            return $this->response->setStatusCode(200)->setBody('');
        }

        add_transaction([
            'transaction_type' => 'refund',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'xendit',
            'txn_id'           => $info['payment_id'] ?: $original['txn_id'],
            'amount'           => $info['amount'],
            'status'           => 'failed',
            'currency_code'    => $stored_currency,
            'message'          => 'txn_refund_processed',
            'reference'        => $info['external_id'],
        ]);

        return $this->response->setStatusCode(200)->setBody('');
    }

    private function extractRefundFields(array $event, string $format): array
    {
        $payment_id_field    = ($format === 'ewallet_format') ? 'charge_id' : 'payment_id';
        $amount_field        = ($format === 'ewallet_format') ? 'refund_amount' : 'amount';

        if ($format === 'new_format' || $format === 'ewallet_format') {
            $data = $event['data'] ?? [];
        } else {
            $data = $event;
        }

        return [
            'refund_id'   => (string) ($data['id'] ?? $event['id'] ?? ''),
            'external_id' => (string) ($data['reference_id'] ?? $data['invoice_id'] ?? $event['external_id'] ?? $event['invoice_id'] ?? ''),
            'payment_id'  => (string) ($data[$payment_id_field] ?? $event[$payment_id_field] ?? ''),
            'amount'      => (float) ($data[$amount_field] ?? $event[$amount_field] ?? 0),
            'currency'    => (string) ($data['currency'] ?? $event['currency'] ?? 'IDR'),
            'reason'      => (string) ($data['reason'] ?? $event['reason'] ?? $event['failure_reason'] ?? ''),
        ];
    }

    private function findOriginalTransactionForRefund(string $external_id, string $payment_id): array
    {
        $row = [];
        if ($external_id !== '') {
            $row = fetch_details('transactions', [
                'transaction_type' => 'transaction',
                'type'             => 'xendit',
                'status'           => 'success',
                'reference'        => $external_id,
            ]);
        }
        if (empty($row) && $payment_id !== '') {
            $row = fetch_details('transactions', [
                'transaction_type' => 'transaction',
                'type'             => 'xendit',
                'status'           => 'success',
                'txn_id'           => $payment_id,
            ]);
        }
        if (empty($row) && $external_id !== '') {
            // Legacy: txn_id stored external_id before refactor.
            $row = fetch_details('transactions', [
                'transaction_type' => 'transaction',
                'type'             => 'xendit',
                'status'           => 'success',
                'txn_id'           => $external_id,
            ]);
        }
        return $row;
    }

    private function sendRefundNotifications(int $order_id, int $user_id, array $info, string $stored_currency): void
    {
        try {
            $user_details   = fetch_details('users', ['id' => $user_id], ['username', 'email']);
            $customer_name  = !empty($user_details) ? $user_details[0]['username'] : 'Customer';
            $customer_email = !empty($user_details) ? $user_details[0]['email']    : '';

            $context = [
                'order_id'       => $order_id,
                'booking_id'     => $order_id,
                'amount'         => number_format($info['amount'], 2),
                'currency'       => $stored_currency,
                'refund_id'      => $info['refund_id'],
                'transaction_id' => $info['payment_id'] ?: $info['refund_id'],
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'customer_id'    => $user_id,
                'processed_date' => date('d-m-Y H:i:s'),
            ];

            queue_notification_service(
                eventType: 'payment_refund_executed',
                recipients: [],
                context: $context,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'user_ids'  => [$user_id],
                    'language'  => $this->defaultLanguage,
                    'platforms' => ['android', 'ios', 'web'],
                    'type'      => 'refund',
                    'data'      => [
                        'order_id'     => (string) $order_id,
                        'booking_id'   => (string) $order_id,
                        'refund_id'    => (string) $info['refund_id'],
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to'  => 'booking_details_screen',
                    ],
                ]
            );

            queue_notification_service(
                eventType: 'payment_refund_successful',
                recipients: [],
                context: $context,
                options: [
                    'channels'    => ['fcm', 'email', 'sms'],
                    'language'    => $this->defaultLanguage,
                    'user_groups' => [1],
                    'platforms'   => ['admin_panel'],
                    'type'        => 'refund',
                    'data'        => [
                        'order_id'     => (string) $order_id,
                        'refund_id'    => (string) $info['refund_id'],
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[XENDIT WEBHOOK] [refund_notifications] ' . $e->getMessage());
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
                'type'             => 'xendit',
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

    private function findExistingXenditTransactionForOrder(int $order_id): array
    {
        if ($order_id <= 0) {
            return [];
        }
        $rows = fetch_details(
            'transactions',
            [
                'order_id'         => $order_id,
                'type'             => 'xendit',
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
            log_message('error', '[XENDIT WEBHOOK] amount mismatch on ' . $label . ': received=' . $actual_amount . ' expected=' . $expected_amount . '. Aborting.');
            return false;
        }
        if ($expected_currency !== '' && $actual_currency !== '' && strcasecmp($actual_currency, $expected_currency) !== 0) {
            log_message('error', '[XENDIT WEBHOOK] currency mismatch on ' . $label . ': received=' . $actual_currency . ' expected=' . $expected_currency . '. Aborting.');
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
                log_message('error', '[XENDIT WEBHOOK] [notifications] order=' . $order_id . ' not found.');
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
            log_message('error', '[XENDIT WEBHOOK] [notifications] order=' . $order_id . ' exception: ' . $e->getMessage());
        }
    }

    private function send_online_payment_status_notification(int $order_id, int $user_id, string $status, array $payment_data = [], array $order_data = []): void
    {
        try {
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses, true)) {
                log_message('error', '[XENDIT WEBHOOK] [payment-notification] invalid status=' . $status);
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
                log_message('error', '[XENDIT WEBHOOK] [payment-notification] order=' . $order_id . ' not found.');
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
                'payment_method' => $payment_data['payment_method'] ?? 'Xendit',
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
            log_message('error', '[XENDIT WEBHOOK] [payment-notification] order=' . $order_id . ' status=' . $status . ' exception: ' . $e->getMessage());
        }
    }
}
