<?php

namespace App\Libraries;

class Cashfree
{
    /**
     * Cashfree international supported currencies and decimal support.
     * Reference: https://www.cashfree.com/docs/payments/international-payments/ipg/currencies-supported
     */
    private const   SUPPORTED_CURRENCIES = [
        'AFN' => ['name' => 'Afghan Afghani', 'supports_decimal' => true],
        'ALL' => ['name' => 'Albanian Lek', 'supports_decimal' => true],
        'DZD' => ['name' => 'Algerian Dinar', 'supports_decimal' => true],
        'AOA' => ['name' => 'Angolan Kwanza', 'supports_decimal' => true],
        'ARS' => ['name' => 'Argentine Peso', 'supports_decimal' => true],
        'AMD' => ['name' => 'Armenian Dram', 'supports_decimal' => true],
        'AWG' => ['name' => 'Aruban Florin', 'supports_decimal' => true],
        'AUD' => ['name' => 'Australian Dollar', 'supports_decimal' => true],
        'AZN' => ['name' => 'Azerbaijani Manat', 'supports_decimal' => true],
        'BSD' => ['name' => 'Bahamian Dollar', 'supports_decimal' => true],
        'BHD' => ['name' => 'Bahraini Dinar', 'supports_decimal' => false],
        'BDT' => ['name' => 'Bangladeshi Taka', 'supports_decimal' => true],
        'BBD' => ['name' => 'Barbadian Dollar', 'supports_decimal' => true],
        'BZD' => ['name' => 'Belize Dollar', 'supports_decimal' => true],
        'BMD' => ['name' => 'Bermudian Dollar', 'supports_decimal' => true],
        'BTN' => ['name' => 'Bhutan Ngultrum', 'supports_decimal' => true],
        'BOB' => ['name' => 'Bolivian Boliviano', 'supports_decimal' => true],
        'BAM' => ['name' => 'Bosnia Convertible Mark', 'supports_decimal' => true],
        'BWP' => ['name' => 'Botswana Pula', 'supports_decimal' => true],
        'BRL' => ['name' => 'Brazilian Real', 'supports_decimal' => true],
        'BND' => ['name' => 'Brunei Dollar', 'supports_decimal' => true],
        'BGN' => ['name' => 'Bulgarian Lev', 'supports_decimal' => true],
        'BIF' => ['name' => 'Burundian Franc', 'supports_decimal' => false],
        'KHR' => ['name' => 'Cambodian Riel', 'supports_decimal' => false],
        'CAD' => ['name' => 'Canadian Dollar', 'supports_decimal' => true],
        'CVE' => ['name' => 'Cape Verdean Escudo', 'supports_decimal' => false],
        'KYD' => ['name' => 'Cayman Islands Dollar', 'supports_decimal' => true],
        'XAF' => ['name' => 'Central African CFA Franc', 'supports_decimal' => false],
        'XPF' => ['name' => 'CFP Franc', 'supports_decimal' => false],
        'CLP' => ['name' => 'Chilean Peso', 'supports_decimal' => false],
        'COP' => ['name' => 'Colombian Peso', 'supports_decimal' => false],
        'KMF' => ['name' => 'Comorian Franc', 'supports_decimal' => false],
        'CDF' => ['name' => 'Congolese Franc', 'supports_decimal' => false],
        'CRC' => ['name' => 'Costa Rican Colon', 'supports_decimal' => false],
        'CZK' => ['name' => 'Czech Koruna', 'supports_decimal' => false],
        'DKK' => ['name' => 'Danish Krone', 'supports_decimal' => true],
        'DJF' => ['name' => 'Djibouti Franc', 'supports_decimal' => false],
        'DOP' => ['name' => 'Dominican Peso', 'supports_decimal' => false],
        'XCD' => ['name' => 'East Caribbean Dollar', 'supports_decimal' => false],
        'EGP' => ['name' => 'Egyptian Pound', 'supports_decimal' => true],
        'ERN' => ['name' => 'Eritrean Nakfa', 'supports_decimal' => true],
        'SZL' => ['name' => 'Eswatini Lilangeni', 'supports_decimal' => true],
        'ETB' => ['name' => 'Ethiopian Birr', 'supports_decimal' => true],
        'EUR' => ['name' => 'Euro', 'supports_decimal' => true],
        'FKP' => ['name' => 'Falkland Islands Pound', 'supports_decimal' => true],
        'FJD' => ['name' => 'Fiji Dollar', 'supports_decimal' => true],
        'GMD' => ['name' => 'Gambian Dalasi', 'supports_decimal' => true],
        'GEL' => ['name' => 'Georgian Lari', 'supports_decimal' => true],
        'GHS' => ['name' => 'Ghanaian Cedi', 'supports_decimal' => true],
        'GIP' => ['name' => 'Gibraltar Pound', 'supports_decimal' => true],
        'GTQ' => ['name' => 'Guatemalan Quetzal', 'supports_decimal' => true],
        'GNF' => ['name' => 'Guinean Franc', 'supports_decimal' => false],
        'GYD' => ['name' => 'Guyana Dollar', 'supports_decimal' => true],
        'HTG' => ['name' => 'Haitian Gourde', 'supports_decimal' => true],
        'HNL' => ['name' => 'Honduran Lempira', 'supports_decimal' => true],
        'HKD' => ['name' => 'Hong Kong Dollar', 'supports_decimal' => true],
        'HUF' => ['name' => 'Hungarian Forint', 'supports_decimal' => true],
        'ISK' => ['name' => 'Icelandic Krona', 'supports_decimal' => true],
        'INR' => ['name' => 'Indian Rupee', 'supports_decimal' => true],
        'IDR' => ['name' => 'Indonesian Rupiah', 'supports_decimal' => false],
        'IQD' => ['name' => 'Iraqi Dinar', 'supports_decimal' => false],
        'JMD' => ['name' => 'Jamaican Dollar', 'supports_decimal' => true],
        'JPY' => ['name' => 'Japanese Yen', 'supports_decimal' => false],
        'JOD' => ['name' => 'Jordanian Dinar', 'supports_decimal' => false],
        'KZT' => ['name' => 'Kazakhstani Tenge', 'supports_decimal' => true],
        'KES' => ['name' => 'Kenyan Shilling', 'supports_decimal' => true],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'supports_decimal' => false],
        'KGS' => ['name' => 'Kyrgyzstani Som', 'supports_decimal' => true],
        'LAK' => ['name' => 'Lao Kip', 'supports_decimal' => false],
        'LBP' => ['name' => 'Lebanese Pound', 'supports_decimal' => false],
        'LRD' => ['name' => 'Liberian Dollar', 'supports_decimal' => true],
        'LYD' => ['name' => 'Libyan Dinar', 'supports_decimal' => false],
        'MOP' => ['name' => 'Macanese Pataca', 'supports_decimal' => true],
        'MKD' => ['name' => 'Macedonian Denar', 'supports_decimal' => true],
        'MGA' => ['name' => 'Malagasy Ariary', 'supports_decimal' => true],
        'MWK' => ['name' => 'Malawian Kwacha', 'supports_decimal' => true],
        'MYR' => ['name' => 'Malaysian Ringgit', 'supports_decimal' => true],
        'MVR' => ['name' => 'Maldivian Rufiyaa', 'supports_decimal' => true],
        'MRU' => ['name' => 'Mauritanian Ouguiya', 'supports_decimal' => true],
        'MUR' => ['name' => 'Mauritian Rupee', 'supports_decimal' => true],
        'MXN' => ['name' => 'Mexican Peso', 'supports_decimal' => true],
        'MDL' => ['name' => 'Moldovan Leu', 'supports_decimal' => true],
        'MNT' => ['name' => 'Mongolian Tugrik', 'supports_decimal' => true],
        'MAD' => ['name' => 'Moroccan Dirham', 'supports_decimal' => true],
        'MZN' => ['name' => 'Mozambican Metical', 'supports_decimal' => true],
        'NAD' => ['name' => 'Namibian Dollar', 'supports_decimal' => true],
        'NPR' => ['name' => 'Nepalese Rupee', 'supports_decimal' => true],
        'ILS' => ['name' => 'New Israeli Shekel', 'supports_decimal' => true],
        'TWD' => ['name' => 'New Taiwan Dollar', 'supports_decimal' => true],
        'NZD' => ['name' => 'New Zealand Dollar', 'supports_decimal' => true],
        'NIO' => ['name' => 'Nicaraguan Cordoba', 'supports_decimal' => true],
        'NGN' => ['name' => 'Nigerian Naira', 'supports_decimal' => true],
        'NOK' => ['name' => 'Norwegian Krone', 'supports_decimal' => true],
        'PGK' => ['name' => 'Papua New Guinean Kina', 'supports_decimal' => true],
        'PYG' => ['name' => 'Paraguayan Guarani', 'supports_decimal' => true],
        'PEN' => ['name' => 'Peruvian Sol', 'supports_decimal' => true],
        'PHP' => ['name' => 'Philippine Peso', 'supports_decimal' => true],
        'PLN' => ['name' => 'Polish Zloty', 'supports_decimal' => true],
        'GBP' => ['name' => 'Pound Sterling', 'supports_decimal' => true],
        'QAR' => ['name' => 'Qatari Riyal', 'supports_decimal' => true],
        'CNY' => ['name' => 'Renminbi (Yuan)', 'supports_decimal' => true],
        'OMR' => ['name' => 'Omani Rial', 'supports_decimal' => false],
        'RON' => ['name' => 'Romanian Leu', 'supports_decimal' => true],
        'RUB' => ['name' => 'Russian Ruble', 'supports_decimal' => true],
        'RWF' => ['name' => 'Rwandan Franc', 'supports_decimal' => false],
        'SHP' => ['name' => 'Saint Helena Pound', 'supports_decimal' => true],
        'WST' => ['name' => 'Samoan Tala', 'supports_decimal' => true],
        'SAR' => ['name' => 'Saudi Riyal', 'supports_decimal' => true],
        'RSD' => ['name' => 'Serbian Dinar', 'supports_decimal' => true],
        'SCR' => ['name' => 'Seychellois Rupee', 'supports_decimal' => true],
        'SLL' => ['name' => 'Sierra Leonean Leone', 'supports_decimal' => true],
        'SGD' => ['name' => 'Singapore Dollar', 'supports_decimal' => true],
        'SBD' => ['name' => 'Solomon Islands Dollar', 'supports_decimal' => true],
        'SOS' => ['name' => 'Somali Shilling', 'supports_decimal' => false],
        'ZAR' => ['name' => 'South African Rand', 'supports_decimal' => true],
        'KRW' => ['name' => 'South Korean Won', 'supports_decimal' => true],
        'LKR' => ['name' => 'Sri Lankan Rupee', 'supports_decimal' => true],
        'SRD' => ['name' => 'Surinamese Dollar', 'supports_decimal' => true],
        'SEK' => ['name' => 'Swedish Krona', 'supports_decimal' => true],
        'CHF' => ['name' => 'Swiss Franc', 'supports_decimal' => true],
        'TJS' => ['name' => 'Tajikistani Somoni', 'supports_decimal' => true],
        'TZS' => ['name' => 'Tanzanian Shilling', 'supports_decimal' => false],
        'THB' => ['name' => 'Thai Baht', 'supports_decimal' => true],
        'TOP' => ['name' => 'Tongan Paanga', 'supports_decimal' => true],
        'TTD' => ['name' => 'Trinidad and Tobago Dollar', 'supports_decimal' => true],
        'TND' => ['name' => 'Tunisian Dinar', 'supports_decimal' => false],
        'TRY' => ['name' => 'Turkish Lira', 'supports_decimal' => true],
        'TMT' => ['name' => 'Turkmenistan Manat', 'supports_decimal' => true],
        'AED' => ['name' => 'UAE Dirham', 'supports_decimal' => true],
        'UGX' => ['name' => 'Ugandan Shilling', 'supports_decimal' => false],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'supports_decimal' => true],
        'UYU' => ['name' => 'Uruguayan Peso', 'supports_decimal' => true],
        'USD' => ['name' => 'US Dollar', 'supports_decimal' => true],
        'UZS' => ['name' => 'Uzbekistani Som', 'supports_decimal' => true],
        'VUV' => ['name' => 'Vanuatu Vatu', 'supports_decimal' => true],
        'VND' => ['name' => 'Vietnamese Dong', 'supports_decimal' => false],
        'XOF' => ['name' => 'West African CFA Franc', 'supports_decimal' => false],
        'YER' => ['name' => 'Yemeni Riyal', 'supports_decimal' => false],
        'ZMW' => ['name' => 'Zambian Kwacha', 'supports_decimal' => true]
    ];

    private string $app_id = '';
    private string $secret_key = '';
    private string $webhook_secret_key = '';
    private string $mode = 'test';
    private string $currency = 'INR';
    private string $website_url = '';
    private string $status = 'disable';

    public function __construct()
    {
        $settings = get_settings('payment_gateways_settings', true);

        $this->status = isset($settings['cashfree_status']) ? (string) $settings['cashfree_status'] : 'disable';
        $this->mode = isset($settings['cashfree_mode']) ? (string) $settings['cashfree_mode'] : 'test';
        $this->app_id = isset($settings['cashfree_app_id']) ? (string) $settings['cashfree_app_id'] : '';
        $this->secret_key = isset($settings['cashfree_secret_key']) ? (string) $settings['cashfree_secret_key'] : '';
        $this->webhook_secret_key = isset($settings['cashfree_webhook_secret_key']) ? (string) $settings['cashfree_webhook_secret_key'] : '';
        $this->currency = isset($settings['cashfree_currency']) ? (string) $settings['cashfree_currency'] : 'INR';
        $this->website_url = isset($settings['cashfree_website_url']) ? (string) $settings['cashfree_website_url'] : '';
    }

    public function get_credentials(): array
    {
        return [
            'status' => $this->status,
            'mode' => $this->mode,
            'app_id' => $this->app_id,
            'secret_key' => $this->secret_key,
            'webhook_secret_key' => $this->webhook_secret_key,
            'currency' => $this->currency,
            'website_url' => $this->website_url,
        ];
    }

    public function create_order(array $payload): array
    {
        try {
            $client = $this->configureSdk();
            if (!method_exists($client, 'PGCreateOrder')) {
                return ['error' => true, 'message' => 'Cashfree SDK is not available'];
            }

            $response = $client->PGCreateOrder($payload);
            return $this->normalizeSdkResponse($response);
        } catch (\Throwable $th) {
            return ['error' => true, 'message' => $th->getMessage()];
        }
    }

    public function fetch_order(string $orderId): array
    {
        try {
            $client = $this->configureSdk();
            if (!method_exists($client, 'PGFetchOrder')) {
                return ['error' => true, 'message' => 'Cashfree SDK is not available'];
            }

            $response = $client->PGFetchOrder($orderId);
            return $this->normalizeSdkResponse($response);
        } catch (\Throwable $th) {
            return ['error' => true, 'message' => $th->getMessage()];
        }
    }

    public function fetch_order_payments(string $orderId): array
    {
        try {
            $client = $this->configureSdk();
            if (!method_exists($client, 'PGOrderFetchPayments')) {
                return ['error' => true, 'message' => 'Cashfree SDK is not available'];
            }

            $response = $client->PGOrderFetchPayments($orderId);
            return $this->normalizeSdkResponse($response);
        } catch (\Throwable $th) {
            return ['error' => true, 'message' => $th->getMessage()];
        }
    }

    public function create_refund(string $orderId, float $amount, ?string $refundId = null, ?string $refundNote = null): array
    {
        try {
            $client = $this->configureSdk();
            if (!method_exists($client, 'PGOrderCreateRefund')) {
                return ['error' => true, 'message' => 'Cashfree SDK is not available'];
            }

            $payload = [
                'refund_id' => $refundId ?: ('refund_' . time()),
                'refund_amount' => (float) number_format($amount, 2, '.', ''),
            ];

            if (!empty($refundNote)) {
                $payload['refund_note'] = $refundNote;
            }

            $response = $client->PGOrderCreateRefund($orderId, $payload);
            return $this->normalizeSdkResponse($response);
        } catch (\Throwable $th) {
            return ['error' => true, 'message' => $th->getMessage()];
        }
    }

    public function verify_webhook_signature(string $rawBody, string $signature, string $timestamp): bool
    {
        if (empty($this->webhook_secret_key) || empty($rawBody) || empty($signature) || empty($timestamp)) {
            return false;
        }

        $signedPayload = $timestamp . $rawBody;
        $generatedSignature = base64_encode(hash_hmac('sha256', $signedPayload, $this->webhook_secret_key, true));

        return hash_equals($generatedSignature, $signature);
    }

    public static function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public static function isZeroDecimalCurrency(string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        if (!isset(self::SUPPORTED_CURRENCIES[$currency])) {
            return false;
        }

        return self::SUPPORTED_CURRENCIES[$currency]['supports_decimal'] === false;
    }

    public static function toMinorUnits($amount, string $currency): int
    {
        $amountFloat = (float) $amount;
        if (self::isZeroDecimalCurrency($currency)) {
            return (int) round($amountFloat);
        }

        return (int) round($amountFloat * 100);
    }

    public static function fromMinorUnits($amount, string $currency): float
    {
        $amountFloat = (float) $amount;
        if (self::isZeroDecimalCurrency($currency)) {
            return $amountFloat;
        }

        return $amountFloat / 100;
    }

    private function configureSdk()
    {
        if (!class_exists('\Cashfree\Cashfree')) {
            throw new \RuntimeException('Cashfree SDK is not installed');
        }

        $environment = $this->mode === 'live' ? 1 : 0;
        // Keep Cashfree SDK error analytics disabled to avoid vendor-side undefined variable warnings
        // being promoted to exceptions by framework error handlers.
        return new \Cashfree\Cashfree($environment, $this->app_id, $this->secret_key, '', '', '', false);
    }

    private function normalizeSdkResponse($response): array
    {
        if (is_array($response) && isset($response[0])) {
            $payload = json_decode(json_encode($response[0]), true);
            $payload = is_array($payload) ? $payload : [];
            if (isset($response[1])) {
                $payload['http_code'] = (int) $response[1];
            }
            return $payload;
        }

        $payload = json_decode(json_encode($response), true);
        return is_array($payload) ? $payload : [];
    }
}
