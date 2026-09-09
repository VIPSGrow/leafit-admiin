<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Paypal;
use App\Services\PaypalPaymentService;

/**
 * PayPal webhook controller (Webhooks v1).
 *
 * Replaces the deprecated PayPal Standard IPN listener. Events are verified
 * against PayPal via the verify-webhook-signature API before any DB write.
 *
 * All state changes are delegated to PaypalPaymentService — the same service
 * used by the synchronous capture-on-return handlers — so a return-handler
 * capture and the webhook for the same payment can never double-process.
 *
 * Subscribed events:
 *   - PAYMENT.CAPTURE.COMPLETED → finalise the pending transaction
 *   - PAYMENT.CAPTURE.DENIED    → mark the pending transaction failed
 *   - PAYMENT.CAPTURE.REFUNDED  → record a refund transaction
 *   - PAYMENT.CAPTURE.REVERSED  → record a refund transaction
 */
class PaypalWebhook extends BaseController
{
    private Paypal $paypal_lib;

    /** @var array General settings (timezone, etc.) */
    protected $settings;

    public function __construct()
    {
        helper('api');
        helper('function');

        $this->paypal_lib = new Paypal();
        $this->settings   = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
    }

    public function index()
    {
        try {
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                log_message('warning', '[PAYPAL WEBHOOK] Rejected: method not allowed');
                return $this->response->setStatusCode(405)->setJSON(['error' => true, 'message' => 'Method not allowed']);
            }

            $raw_body = file_get_contents('php://input');
            $event    = json_decode($raw_body, true);

            if (empty($event) || !is_array($event)) {
                log_message('error', '[PAYPAL WEBHOOK] Rejected: empty or invalid JSON payload');
                return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
            }

            $headers = [
                'paypal-auth-algo'         => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
                'paypal-cert-url'          => $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '',
                'paypal-transmission-id'   => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
                'paypal-transmission-sig'  => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
                'paypal-transmission-time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
            ];

            if (!$this->paypal_lib->verifyWebhookSignature($headers, $raw_body)) {
                log_message('error', '[PAYPAL WEBHOOK] Invalid signature. Rejected.');
                // 400 ("do not retry") rather than 401 — a bad signature will never verify on retry.
                return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid signature']);
            }

            $event_type = strtoupper((string) ($event['event_type'] ?? 'UNKNOWN'));
            $resource   = is_array($event['resource'] ?? null) ? $event['resource'] : [];
            log_message('info', '[PAYPAL WEBHOOK] event=' . $event_type);

            $service = new PaypalPaymentService();

            switch ($event_type) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    return $this->handleCaptureCompleted($resource, $service);

                case 'PAYMENT.CAPTURE.DENIED':
                    return $this->handleCaptureDenied($resource, $service);

                case 'PAYMENT.CAPTURE.REFUNDED':
                case 'PAYMENT.CAPTURE.REVERSED':
                    return $this->handleRefund($resource, $service);

                default:
                    // Acknowledge unhandled events so PayPal stops retrying.
                    log_message('info', '[PAYPAL WEBHOOK] Unhandled event=' . $event_type);
                    return $this->ok('Unhandled event');
            }
        } catch (\Throwable $th) {
            log_message('error', '[PAYPAL WEBHOOK] Exception: ' . $th->getMessage() . ' in ' . $th->getFile() . ':' . $th->getLine());
            return $this->response->setStatusCode(500)->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }

    // =================================================================
    // Event handlers
    // =================================================================

    private function handleCaptureCompleted(array $resource, PaypalPaymentService $service)
    {
        $order_id = $this->relatedOrderId($resource);
        $txn      = $this->resolveTransaction($order_id);

        if (empty($txn)) {
            log_message('error', '[PAYPAL WEBHOOK] [completed] no pending paypal txn for order=' . $order_id . '. Skipping.');
            return $this->ok('No pending intent row');
        }

        $capture = [
            'capture_id' => (string) ($resource['id'] ?? ''),
            'status'     => 'COMPLETED',
            'amount'     => (float) ($resource['amount']['value'] ?? 0),
            'currency'   => strtoupper((string) ($resource['amount']['currency_code'] ?? '')),
            'custom_id'  => (string) ($resource['custom_id'] ?? ''),
            'invoice_id' => (string) ($resource['invoice_id'] ?? ''),
        ];

        $result = $service->finalizeSuccess($txn, $capture);
        log_message('info', '[PAYPAL WEBHOOK] [completed] txn=' . $txn['id'] . ' result=' . $result);

        return $this->ok('Processed: ' . $result);
    }

    private function handleCaptureDenied(array $resource, PaypalPaymentService $service)
    {
        $order_id = $this->relatedOrderId($resource);
        $txn      = $this->resolveTransaction($order_id);

        if (empty($txn)) {
            log_message('warning', '[PAYPAL WEBHOOK] [denied] no pending paypal txn for order=' . $order_id . '. Skipping.');
            return $this->ok('No pending intent row');
        }

        $result = $service->finalizeFailure($txn, (string) ($resource['id'] ?? ''));
        log_message('info', '[PAYPAL WEBHOOK] [denied] txn=' . $txn['id'] . ' result=' . $result);

        return $this->ok('Processed: ' . $result);
    }

    private function handleRefund(array $resource, PaypalPaymentService $service)
    {
        $related    = $resource['supplementary_data']['related_ids'] ?? [];
        $capture_id = (string) ($related['capture_id'] ?? '');
        $order_id   = (int) ($this->relatedOrderId($resource));

        $result = $service->processRefund([
            'capture_id' => $capture_id,
            'order_id'   => $order_id,
            'amount'     => (float) ($resource['amount']['value'] ?? 0),
            'currency'   => strtoupper((string) ($resource['amount']['currency_code'] ?? '')),
        ]);
        log_message('info', '[PAYPAL WEBHOOK] [refund] capture=' . $capture_id . ' result=' . $result);

        return $this->ok('Processed: ' . $result);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Extract the Orders v2 order id a capture/refund resource belongs to.
     */
    private function relatedOrderId(array $resource): string
    {
        return (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
    }

    /**
     * Resolve the local pending transaction row from the PayPal order id
     * stamped onto `transactions.reference` at checkout creation time.
     */
    private function resolveTransaction(string $paypal_order_id): array
    {
        if ($paypal_order_id === '') {
            return [];
        }
        $rows = fetch_details('transactions', ['type' => 'paypal', 'reference' => $paypal_order_id]);
        return !empty($rows) ? $rows[0] : [];
    }

    private function ok(string $message)
    {
        return $this->response->setStatusCode(200)->setJSON(['error' => false, 'message' => $message]);
    }
}
