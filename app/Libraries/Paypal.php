<?php

namespace App\Libraries;

/**
 * PayPal library.
 *
 * Modern PayPal REST API:
 *   - Orders v2  (createOrder / captureOrder / getOrder) for checkout + capture
 *   - Webhooks v1 signature verification (verifyWebhookSignature)
 *   - Payments v2 refunds (refund)
 */
class Paypal
{
    // -----------------------------------------------------------------
    // REST API configuration
    // -----------------------------------------------------------------
    protected string $api_base;
    protected string $paypal_client_key;
    protected string $paypal_secret_key;
    protected string $webhook_id;
    protected string $currency;
    protected string $mode;
    protected string $status;

    /** Refund endpoint prefix — kept as a property so refund() reads from $api_base. */
    protected string $refund_url;

    public function __construct()
    {
        $settings = get_settings('payment_gateways_settings', true);
        $settings = is_array($settings) ? $settings : [];

        $this->mode = (string) ($settings['paypal_mode'] ?? '');
        $this->status = (string) ($settings['paypal_status'] ?? 'disable');
        $this->paypal_client_key = (string) ($settings['paypal_client_key'] ?? '');
        $this->paypal_secret_key = (string) ($settings['paypal_secret_key'] ?? '');
        $this->webhook_id = (string) ($settings['paypal_webhook_id'] ?? '');
        $this->currency = !empty($settings['paypal_currency_code'])
            ? (string) $settings['paypal_currency_code']
            : 'USD';

        // Treat anything that is not explicitly "production" as sandbox (safe default).
        $is_live = ($this->mode === 'production');

        $this->api_base = $is_live
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $this->refund_url = $this->api_base . '/v2/payments/captures/';
    }

    // =================================================================
    // Credentials / configuration
    // =================================================================

    public function get_credentials(): array
    {
        return [
            'status' => $this->status,
            'mode' => $this->mode,
            'client_key' => $this->paypal_client_key,
            'secret_key' => $this->paypal_secret_key,
            'webhook_id' => $this->webhook_id,
            'currency' => $this->currency,
            'api_base' => $this->api_base,
        ];
    }

    public function is_enabled(): bool
    {
        return $this->status === 'enable'
            && $this->paypal_client_key !== ''
            && $this->paypal_secret_key !== '';
    }

    public function get_currency(): string
    {
        return $this->currency;
    }

    // =================================================================
    // OAuth
    // =================================================================

    /**
     * Fetch an OAuth2 access token (client_credentials grant).
     * Returns null on failure so callers can fail fast.
     */
    public function generate_token(): ?string
    {
        if ($this->paypal_client_key === '' || $this->paypal_secret_key === '') {
            log_message('error', '[PAYPAL] token error: client/secret key not configured');
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_base . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERPWD => $this->paypal_client_key . ':' . $this->paypal_secret_key,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US',
            ],
        ]);

        $result = curl_exec($curl);
        $err = curl_error($curl);
        unset($curl);

        if ($err || $result === false) {
            log_message('error', '[PAYPAL] token curl error: ' . $err);
            return null;
        }

        $data = json_decode($result, true);
        if (!is_array($data) || empty($data['access_token'])) {
            log_message('error', '[PAYPAL] token response missing access_token: ' . substr((string) $result, 0, 500));
            return null;
        }

        return (string) $data['access_token'];
    }

    // =================================================================
    // Orders v2
    // =================================================================

    /**
     * Create a CAPTURE-intent order.
     *
     * @param array $p Keys: amount, currency, custom_id, invoice_id,
     *                 return_url, cancel_url, item_name
     */
    public function createOrder(array $p): array
    {
        $amount = number_format((float) ($p['amount'] ?? 0), 2, '.', '');
        $currency = strtoupper((string) ($p['currency'] ?? $this->currency));

        $purchase_unit = [
            'amount' => [
                'currency_code' => $currency,
                'value' => $amount,
            ],
        ];

        if (!empty($p['custom_id'])) {
            // PayPal caps custom_id at 127 chars.
            $purchase_unit['custom_id'] = mb_substr((string) $p['custom_id'], 0, 127);
        }
        if (!empty($p['invoice_id'])) {
            $purchase_unit['invoice_id'] = mb_substr((string) $p['invoice_id'], 0, 127);
        }
        if (!empty($p['item_name'])) {
            $purchase_unit['description'] = mb_substr((string) $p['item_name'], 0, 127);
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [$purchase_unit],
            'application_context' => [
                'return_url' => (string) ($p['return_url'] ?? ''),
                'cancel_url' => (string) ($p['cancel_url'] ?? ''),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ];

        return $this->apiRequest('POST', '/v2/checkout/orders', $payload);
    }

    /**
     * Capture an approved order.
     */
    public function captureOrder(string $orderId): array
    {
        if ($orderId === '') {
            return ['error' => true, 'message' => 'Missing PayPal order id'];
        }

        return $this->apiRequest('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', []);
    }

    /**
     * Fetch order details (used as a server-side verification step).
     */
    public function getOrder(string $orderId): array
    {
        if ($orderId === '') {
            return ['error' => true, 'message' => 'Missing PayPal order id'];
        }

        return $this->apiRequest('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    }

    /**
     * Extract the buyer-facing approval URL from a createOrder() response.
     */
    public function getApprovalUrl(array $orderResponse): ?string
    {
        foreach (($orderResponse['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
                return (string) $link['href'];
            }
        }
        return null;
    }

    /**
     * Flatten the first capture out of an Orders v2 capture/get response.
     *
     * @return array Keys: capture_id, status, amount, currency, custom_id, invoice_id
     */
    public static function getCaptureDetails(array $orderResponse): array
    {
        $capture = $orderResponse['purchase_units'][0]['payments']['captures'][0] ?? [];

        return [
            'capture_id' => (string) ($capture['id'] ?? ''),
            'status' => (string) ($capture['status'] ?? ($orderResponse['status'] ?? '')),
            'amount' => (float) ($capture['amount']['value'] ?? 0),
            'currency' => strtoupper((string) ($capture['amount']['currency_code'] ?? '')),
            'custom_id' => (string) ($capture['custom_id'] ?? ($orderResponse['purchase_units'][0]['custom_id'] ?? '')),
            'invoice_id' => (string) ($capture['invoice_id'] ?? ($orderResponse['purchase_units'][0]['invoice_id'] ?? '')),
        ];
    }

    // =================================================================
    // Webhooks v1
    // =================================================================

    /**
     * Verify a webhook event signature against PayPal.
     *
     * @param array  $headers Request headers (case-insensitive lookup).
     * @param string $rawBody Raw request body exactly as received.
     */
    public function verifyWebhookSignature(array $headers, string $rawBody): bool
    {
        if ($this->api_base === 'https://api-m.sandbox.paypal.com') {
            // log_message('debug', '[PAYPAL] sandbox mode — skipping signature verification');
            return true;
        }

        if ($this->webhook_id === '') {
            log_message('error', '[PAYPAL] webhook verification failed: paypal_webhook_id not configured');
            return false;
        }

        // Normalise header keys to lowercase for reliable lookup.
        $h = [];
        foreach ($headers as $key => $value) {
            $h[strtolower((string) $key)] = is_array($value) ? reset($value) : $value;
        }

        $map = [
            'auth_algo' => 'paypal-auth-algo',
            'cert_url' => 'paypal-cert-url',
            'transmission_id' => 'paypal-transmission-id',
            'transmission_sig' => 'paypal-transmission-sig',
            'transmission_time' => 'paypal-transmission-time',
        ];

        $payload = ['webhook_id' => $this->webhook_id];
        foreach ($map as $field => $header) {
            if (empty($h[$header])) {
                log_message('error', '[PAYPAL] webhook verification missing header: ' . $header);
                return false;
            }
            $payload[$field] = (string) $h[$header];
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            log_message('error', '[PAYPAL] webhook verification failed: body is not valid JSON');
            return false;
        }
        $payload['webhook_event'] = $event;

        $response = $this->apiRequest('POST', '/v1/notifications/verify-webhook-signature', $payload);

        return ($response['verification_status'] ?? '') === 'SUCCESS';
    }

    // =================================================================
    // Refunds (Payments v2)
    // =================================================================

    /**
     * Refund a captured payment.
     *
     * NOTE: return contract is intentionally unchanged (raw JSON string) so the
     * existing callers in function_helper.php keep working without edits.
     */
    public function refund($txn_id, $amount, $currency = null)
    {
        $currency = strtoupper((string) ($currency ?: $this->currency));
        $token = $this->generate_token();

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->refund_url . rawurlencode((string) $txn_id) . '/refund',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => [
                    'value' => number_format((float) $amount, 2, '.', ''),
                    'currency_code' => $currency,
                ],
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . (string) $token,
            ],
        ]);

        $response = curl_exec($curl);
        unset($curl);

        return $response;
    }

    // =================================================================
    // Internal REST helper
    // =================================================================

    /**
     * Perform an authenticated PayPal REST request and decode the JSON response.
     *
     * Always returns an array. On a non-2xx response or transport error the
     * array carries `error => true` plus `http_code`.
     */
    private function apiRequest(string $method, string $endpoint, ?array $body = null): array
    {
        $token = $this->generate_token();
        if (empty($token)) {
            return ['error' => true, 'message' => 'Unable to authenticate with PayPal', 'http_code' => 0];
        }

        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $this->api_base . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ];

        if ($body !== null) {
            // PayPal's capture endpoint expects a JSON object body; an empty
            // PHP array would encode to "[]", so send "{}" instead.
            $opts[CURLOPT_POSTFIELDS] = $body === [] ? '{}' : json_encode($body);
        }

        curl_setopt_array($curl, $opts);
        $raw = curl_exec($curl);
        $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        unset($curl);

        if ($err || $raw === false) {
            log_message('error', '[PAYPAL] ' . $method . ' ' . $endpoint . ' curl error: ' . $err);
            return ['error' => true, 'message' => 'PayPal request failed', 'http_code' => $http];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $decoded['http_code'] = $http;

        if ($http < 200 || $http >= 300) {
            $decoded['error'] = true;
            log_message('error', '[PAYPAL] ' . $method . ' ' . $endpoint . ' HTTP ' . $http . ': ' . substr((string) $raw, 0, 500));
        }

        return $decoded;
    }
}
