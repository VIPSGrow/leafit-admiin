<?php

namespace App\Notification\Providers\Sms;

use App\Notification\Contracts\SmsProviderInterface;

/**
 * Fast2SMS DLT SMS provider.
 *
 * Sends SMS via Fast2SMS DLT API (GET bulkV2). Uses DLT-approved template/message ID
 * and pipe-separated variable values instead of sending raw message body. Config is
 * passed from SmsProviderFactory (from sms_gateway_setting.fast2sms in the database).
 *
 * Config keys (in constructor):
 *   - api_key    (string) Fast2SMS API key (authorization)
 *   - sender_id  (string) DLT-approved 3–6 letter Sender ID (e.g. FSTSMS)
 *
 * When sending, caller must pass options: template_id (DLT Message ID) and variables
 * (array of values in order matching the DLT template's {#var#} placeholders).
 *
 * @see https://docs.fast2sms.com/reference/new-endpoint DLT SMS GET API
 */
class Fast2SmsProvider implements SmsProviderInterface
{
    private string $apiKey;
    private string $senderId;

    /** DLT bulkV2 GET endpoint. */
    private const BULK_V2_URL = 'https://www.fast2sms.com/dev/bulkV2';

    /**
     * Create a new Fast2SmsProvider.
     *
     * @param array $config Required keys: api_key, sender_id (under fast2sms in settings).
     */
    public function __construct(array $config)
    {
        $this->apiKey   = (string) ($config['api_key'] ?? '');
        $this->senderId = (string) ($config['sender_id'] ?? '');
    }

    /**
     * {@inheritDoc}
     * Sends one SMS via Fast2SMS DLT GET API. Requires options['template_id'] and
     * options['variables'] (ordered array); builds variables_values as pipe-separated.
     */
    public function send(string $phone, string $message, array $options = []): array
    {
        if (empty($this->apiKey) || empty($this->senderId)) {
            return [
                'success' => false,
                'message' => 'Fast2SMS configuration is incomplete (API key or Sender ID missing)',
                'data'    => null,
            ];
        }

        $templateId = $options['template_id'] ?? null;
        $variables  = $options['variables'] ?? null;

        // DLT API requires template ID and variables; we do not send raw message body.
        if (empty($templateId) || ! is_array($variables)) {
            return [
                'success' => false,
                'message' => 'Fast2SMS DLT requires template_id and variables in options',
                'data'    => null,
            ];
        }

        // Build variables_values: pipe-separated, in the same order as DLT template placeholders.
        $variablesValues = implode('|', array_map(function ($v) {
            return (string) ($v !== null ? $v : '');
        }, $variables));

        $numbers = $this->normalizePhone($phone);
        if ($numbers === '') {
            return [
                'success' => false,
                'message' => 'Invalid or empty phone number',
                'data'    => null,
            ];
        }

        $query = [
            'authorization'    => $this->apiKey,
            'sender_id'       => $this->senderId,
            'message'         => $templateId,
            'variables_values' => $variablesValues,
            'route'           => 'dlt',
            'numbers'         => $numbers,
        ];

        $url = self::BULK_V2_URL . '?' . http_build_query($query);
        $result = $this->httpGet($url);

        $body     = $result['body'] ?? null;
        $httpCode = $result['http_code'] ?? 0;
        $success  = $httpCode === 200 && isset($body['return']) && $body['return'] === true;

        $errorMessage = 'Failed to send SMS';
        if (! $success && isset($body['message'])) {
            $errorMessage = is_array($body['message']) ? implode(', ', $body['message']) : (string) $body['message'];
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
        return 'fast2sms';
    }

    /**
     * Normalize phone to digits only for Fast2SMS (Indian numbers; strip + and spaces).
     *
     * @param string $phone Raw phone input
     * @return string Digits only, or empty if none
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return $digits === '' ? '' : $digits;
    }

    /**
     * Perform HTTP GET request (self-contained, no external service dependency).
     *
     * @param string $url Full URL including query string
     * @return array ['body' => array|null, 'http_code' => int, 'error' => string]
     */
    private function httpGet(string $url): array
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_HTTPGET        => true,
        ];
        curl_setopt_array($ch, $options);

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
