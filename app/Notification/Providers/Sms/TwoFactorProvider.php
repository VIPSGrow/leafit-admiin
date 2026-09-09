<?php

namespace App\Notification\Providers\Sms;

use App\Notification\Contracts\SmsProviderInterface;

/**
 * 2Factor.in Transactional SMS (TSMS) provider.
 *
 * Sends SMS via 2Factor.in ADDON_SERVICES/SEND/TSMS API. Uses raw message body
 * (no template ID required). Config is passed from SmsProviderFactory
 * (from sms_gateway_setting.2factor in the database).
 *
 * Config keys (in constructor):
 *   - api_key   (string) 2Factor API key (used in URL path)
 *   - sender_id (string) Sender ID shown to recipient (From)
 *
 * Same endpoint supports single and bulk: To accepts one number or comma-separated.
 * We send one recipient per call to match SmsProviderInterface and personalized content.
 *
 * @see https://2factor.in/API/DOCS/ADDON_SRV_SEND_TSMS.html
 */
class TwoFactorProvider implements SmsProviderInterface
{
    private string $apiKey;
    private string $senderId;

    /** 2Factor TSMS POST endpoint. API key is injected into path. */
    private const TSMS_PATH = '/API/V1/%s/ADDON_SERVICES/SEND/TSMS';

    /**
     * Create a new TwoFactorProvider.
     *
     * @param array $config Required keys: api_key, sender_id (under 2factor in settings).
     */
    public function __construct(array $config)
    {
        $this->apiKey   = (string) ($config['api_key'] ?? '');
        $this->senderId = (string) ($config['sender_id'] ?? '');
    }

    /**
     * {@inheritDoc}
     * Sends one SMS via 2Factor TSMS API. Uses raw message body; $options is ignored.
     */
    public function send(string $phone, string $message, array $options = []): array
    {
        if (empty($this->apiKey) || empty($this->senderId)) {
            return [
                'success' => false,
                'message' => '2Factor configuration is incomplete (API key or Sender ID missing)',
                'data'    => null,
            ];
        }

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === '') {
            return [
                'success' => false,
                'message' => 'Invalid or empty phone number',
                'data'    => null,
            ];
        }

        $body = [
            'From' => $this->senderId,
            'To'   => $normalizedPhone,
            'Msg'  => $message,
        ];

        $url = 'https://2factor.in' . sprintf(self::TSMS_PATH, $this->apiKey);
        $result = $this->httpPost($url, $body);

        $responseBody = $result['body'] ?? null;
        $httpCode     = $result['http_code'] ?? 0;
        $curlError    = $result['error'] ?? '';

        if ($curlError !== '') {
            return [
                'success' => false,
                'message' => '2Factor request failed: ' . $curlError,
                'data'    => $result,
            ];
        }

        // 2Factor success: typically 200 with Status "Success" in response
        $success = $httpCode === 200;
        $errorMessage = 'Failed to send SMS';
        if (! $success && is_array($responseBody)) {
            $errorMessage = $responseBody['Details'] ?? $responseBody['Status'] ?? $errorMessage;
        }

        return [
            'success' => $success,
            'message' => $success ? 'SMS sent successfully' : $errorMessage,
            'data'    => $result,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return '2factor';
    }

    /**
     * Normalize phone to digits only (Indian numbers; strip + and spaces).
     *
     * @param string $phone Raw phone input
     * @return string Digits only (e.g. 919XXXXXXXXX), or empty if none
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return $digits === '' ? '' : $digits;
    }

    /**
     * Perform HTTP POST request with JSON body.
     *
     * @param string $url  Full URL
     * @param array  $body Request body (will be JSON-encoded)
     * @return array ['body' => array|null, 'http_code' => int, 'error' => string]
     */
    private function httpPost(string $url, array $body): array
    {
        $ch       = curl_init();
        $jsonBody = json_encode($body);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        unset($ch);

        return [
            'body'      => is_string($responseBody) ? json_decode($responseBody, true) : null,
            'http_code' => $httpCode,
            'error'     => $error ?: '',
        ];
    }
}
