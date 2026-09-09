<?php

namespace App\Libraries;

/**
 * Stripe (Gateway Library)
 *
 * We already ship the official Stripe PHP SDK (`stripe/stripe-php`) in `vendor/`.
 * This library wraps the SDK so the rest of the application can interact with Stripe
 * through a centralized, stable interface (SOLID: single responsibility, consistent rules).
 *
 * IMPORTANT:
 * - `create_payment_intent()` expects `amount` in Stripe minor units (cents/paise),
 *   unless the currency is zero-decimal. Use `StripeMoney::toMinorUnits()` in callers.
 * - Webhook signature verification is delegated to the Stripe SDK (`\Stripe\Webhook`).
 */

/* 
    Strip Payments Library v1.0 for codeigniter 
    by Jaydeep Goswami
*/

/* 
    1. get_credentials()
    2. create_customer($customer_data)
    3. construct_event($request_body, $sigHeader, $secret,$tolerance = DEFAULT_TOLERANCE)
    4. create_payment_intent($c_data)
    5. curl($url, $method = 'GET', $data = [])
*/

const DEFAULT_TOLERANCE = 300;
class Stripe
{
    private $secret_key = "";
    private $publishable_key = "";
    private $webhook_secret_key = "";
    private $currency_code = "";
    /**
     * Stripe mode from payment gateway settings.
     * Allowed values in UI: "test" | "live"
     *
     * NOTE:
     * This project stores only ONE `stripe_secret_key`. So if you switch mode,
     * you must manually update that key in settings as well.
     */
    private $mode = "test";

    /**
     * When Stripe is misconfigured (missing key / mode-key mismatch),
     * we keep the reason here so API callers get a clear message.
     */
    private $config_error_message = "";
    /**
     * Stripe SDK client (official).
     *
     * @var \Stripe\StripeClient|null
     */
    private $client = null;

    function __construct()
    {
        helper('url');
        helper('form');
        helper('function');

        $settings = get_settings('payment_gateways_settings', true);


        $this->secret_key = isset($settings['stripe_secret_key']) ? $settings['stripe_secret_key'] : "";
        $this->publishable_key = isset($settings['stripe_publishable_key']) ? $settings['stripe_publishable_key'] : "";
        $this->webhook_secret_key = isset($settings['stripe_webhook_secret_key']) ? $settings['stripe_webhook_secret_key'] : "";
        $this->mode = strtolower($settings['stripe_mode'] ?? 'test');
        // Currency is stored in payment gateway settings (admin panel).
        // Use a safe default to avoid notices if settings are incomplete.
        $this->currency_code = strtolower($settings['stripe_currency'] ?? 'usd');

        // Ensure Stripe SDK is available (some entrypoints may not load Composer autoload).
        $this->bootstrapStripeSdk();

        // Initialize Stripe SDK client.
        // If the secret key is missing, SDK calls will fail; we handle that at call time.
        $this->config_error_message = $this->validateConfiguration();
        if (empty($this->config_error_message) && !empty($this->secret_key) && class_exists('\Stripe\StripeClient')) {
            $this->client = new \Stripe\StripeClient($this->secret_key);
        }
    }

    /**
     * Bootstrap Stripe SDK if Composer autoload wasn't loaded for this request.
     * This makes the library reliable in CLI/odd controller entrypoints.
     */
    private function bootstrapStripeSdk(): void
    {
        if (class_exists('\Stripe\StripeClient')) {
            return;
        }

        $root = defined('ROOTPATH') ? rtrim(ROOTPATH, DIRECTORY_SEPARATOR) : rtrim(dirname(__DIR__, 3), DIRECTORY_SEPARATOR);
        $autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    /**
     * Validate Stripe configuration for current mode.
     *
     * We want to fail fast when the admin switches Stripe mode but forgets to
     * change the stored secret key.
     */
    private function validateConfiguration(): string
    {
        if (!class_exists('\Stripe\StripeClient')) {
            return 'Stripe SDK is not available (vendor/autoload.php not loaded).';
        }

        if (empty($this->secret_key)) {
            return 'Stripe is not configured (missing secret key).';
        }

        // Guardrail: ensure key prefix matches selected mode.
        // Stripe keys are typically:
        // - sk_test_... or rk_test_... for test
        // - sk_live_... or rk_live_... for live
        $key = trim($this->secret_key);
        $looksTest = (strpos($key, 'sk_test_') === 0) || (strpos($key, 'rk_test_') === 0);
        $looksLive = (strpos($key, 'sk_live_') === 0) || (strpos($key, 'rk_live_') === 0);

        if ($this->mode === 'test' && $looksLive) {
            return 'Stripe mode is set to TEST but the stored secret key looks like a LIVE key. Please update `stripe_secret_key` to a `sk_test_...` (or `rk_test_...`) key.';
        }

        if ($this->mode === 'live' && $looksTest) {
            return 'Stripe mode is set to LIVE but the stored secret key looks like a TEST key. Please update `stripe_secret_key` to a `sk_live_...` (or `rk_live_...`) key.';
        }

        // Unknown key format: do not block, but allow Stripe SDK to validate.
        return '';
    }

    /**
     * Ensure Stripe client exists before any SDK call.
     *
     * @return bool true when ready, false when missing config.
     */
    private function ensureClient(): bool
    {
        if (!empty($this->config_error_message)) {
            return false;
        }
        if ($this->client instanceof \Stripe\StripeClient) {
            return true;
        }
        return false;
    }

    public function get_credentials()
    {
        $data['secret_key'] = $this->secret_key;
        $data['publishable_key'] = $this->publishable_key;
        $data['webhook_key'] = $this->webhook_secret_key;
        $data['currency'] = $this->currency_code;
        $data['mode'] = $this->mode;
        return $data;
    }
    // Set your secret key. Remember to switch to your live secret key in production.
    // See your keys here: https://dashboard.stripe.com/apikeys


    public function create_customer($customer_data)
    {
        if (!$this->ensureClient()) {
            return [
                'error' => [
                    'message' => $this->config_error_message ?: 'Stripe is not configured.',
                ]
            ];
        }

        try {
            $payload = [
                'name' => $customer_data['name'] ?? '',
                'address' => [
                    'line1' => $customer_data['line1'] ?? '',
                    'postal_code' => $customer_data['postal_code'] ?? '',
                    'city' => $customer_data['city'] ?? '',
                ]
            ];

            $customer = $this->client->customers->create($payload);
            return json_decode(json_encode($customer), true);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        }
    }
    
    public function construct_event($request_body, $sigHeader, $secret, $tolerance = DEFAULT_TOLERANCE)
    {
        // Use Stripe SDK to verify signature. This is the most correct method and
        // supports multiple signatures, timestamp tolerance, etc.
        $this->bootstrapStripeSdk();

        try {
            if (empty($sigHeader) || empty($secret)) {
                return [
                    'error' => true,
                    'message' => 'Missing Stripe signature header or webhook secret.',
                ];
            }

            // We don't need the Event object here, but we keep verification strict.
            \Stripe\Webhook::constructEvent($request_body, $sigHeader, $secret, (int) $tolerance);
            return "Matched";
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        } catch (\UnexpectedValueException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function create_payment_intent($c_data)
    {
        if (!$this->ensureClient()) {
            return [
                'error' => [
                    'message' => $this->config_error_message ?: 'Stripe is not configured.',
                ]
            ];
        }

        try {
            // Always force currency from settings for consistency.
            $c_data['currency'] = $this->currency_code;

            // Stripe expects integer amount in minor units.
            if (isset($c_data['amount'])) {
                $c_data['amount'] = (int) $c_data['amount'];
            }

            // If caller doesn't define payment method types, enable automatic payment methods.
            // This avoids "payment_method_types is required" errors across clients.
            if (!isset($c_data['payment_method_types']) && !isset($c_data['automatic_payment_methods'])) {
                $c_data['automatic_payment_methods'] = ['enabled' => true];
            }

            $intent = $this->client->paymentIntents->create($c_data);
            return json_decode(json_encode($intent), true);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        }
    }

    public function refund($payment_intent, $amount)
    {
        if (!$this->ensureClient()) {
            return [
                'error' => [
                    'message' => $this->config_error_message ?: 'Stripe is not configured.',
                ]
            ];
        }

        try {
            // Stripe refund amount must be in minor units (and currency dependent).
            // Keep the conversion centralized and consistent.
            $amountMinor = StripeMoney::toMinorUnits($amount, $this->currency_code);

            $refund = $this->client->refunds->create([
                'payment_intent' => $payment_intent,
                'amount' => (int) $amountMinor,
            ]);

            return json_decode(json_encode($refund), true);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        }
    }

    /**
     * Retrieve a Stripe PaymentIntent by ID using the official SDK.
     *
     * This is the SDK-based alternative to:
     * POST https://api.stripe.com/v1/payment_intents/{payment_intent_id}
     *
     * @param string $payment_intent_id Example: "pi_3SrdIoI4kfuyFuCo0sx13VXg"
     * @return array Stripe PaymentIntent payload (decoded) or ['error' => ['message' => ...]]
     */
    public function get_payment_intent(string $payment_intent_id): array
    {
        if (!$this->ensureClient()) {
            return [
                'error' => [
                    'message' => $this->config_error_message ?: 'Stripe is not configured.',
                ]
            ];
        }

        $payment_intent_id = trim($payment_intent_id);
        if ($payment_intent_id === '') {
            return [
                'error' => [
                    'message' => 'payment_intent_id is required.',
                ]
            ];
        }

        try {
            $intent = $this->client->paymentIntents->retrieve($payment_intent_id, []);
            return json_decode(json_encode($intent), true);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                ]
            ];
        }
    }
}
