<?php

namespace App\Notification\Providers\Sms;

use App\Notification\Contracts\SmsProviderInterface;

/**
 * CombirdsSmsProvider DLT SMS provider.
 *
 * Sends SMS via Combirds (Edumarc) JSON POST API v1/sendsms.
 * Endpoint: https://smsapi.edumarcsms.com/api/v1/sendsms
 *
 * Requirements:
 * - API Key passed via header: "apikey: YOUR_API_KEY"
 * - Headers: "Content-Type: application/json"
 * - JSON Payload: number (array), message, senderId, templateId
 */
class CombirdsSmsProvider implements SmsProviderInterface
{
    private string $apiKey;
    private string $senderId;

    /** Combirds / Edumarc POST endpoint */
    private const SEND_SMS_URL = 'https://smsapi.edumarcsms.com/api/v1/sendsms';

    /**
     * Create a new CombirdsSmsProvider instance.
     *
     * @param array $config Required keys: combides_api_key, combides_sender_id (or fallbacks)
     */
    public function __construct(array $config)
    {
        $this->apiKey   = (string) ($config['combides_api_key'] ?? $config['api_key'] ?? '');
        $this->senderId = (string) ($config['combides_sender_id'] ?? $config['sender_id'] ?? '');
    }

    /**
     * {@inheritDoc}
     * Sends SMS via Combirds/Edumarc JSON POST API.
     */
    public function send(string $phone, string $message, array $options = []): array
    {
      log_message('info', '[Combirds provider] option - '.json_encode($options).' - message: '.$message);
        if (empty($this->apiKey) || empty($this->senderId)) {
            return [
                'success' => false,
                'message' => 'Combirds configuration is incomplete (API key or Sender ID missing)',
                'data'    => null,
            ];
        }

        $templateId = $options['template_id'] ?? null;

        $formattedPhone = $this->normalizePhone($phone);
        if ($formattedPhone === '') {
            return [
                'success' => false,
                'message' => 'Invalid or empty phone number',
                'data'    => null,
            ];
        }

        // Build payload strictly adhering to Section 4.1 of the Combirds API Doc
        $payload = [
            'number'     => [$formattedPhone], // API expects array of mobile numbers
            'message'    => $message,          // Full replaced DLT-approved message text
            'senderId'   => $this->senderId,   // Sender ID (e.g. COMBRD)
            'templateId' => (string) ($templateId ?? '') // DLT template ID
        ];

        // Execute POST JSON Request
        $result = $this->httpPostJson(self::SEND_SMS_URL, $payload);

        $body     = $result['body'] ?? null;
        $httpCode = $result['http_code'] ?? 0;
        
      	log_message('info', '[SMS aPI col] option - '.json_encode($result));
        // Success condition check (HTTP 200 and status === 'success')
        $success = ($httpCode === 200 || $httpCode === 201) && isset($body['success']) && $body['success'] === true;

        $errorMessage = 'Failed to send SMS';
        if (!$success && isset($body['message'])) {
            $errorMessage = is_array($body['message']) ? implode(', ', $body['message']) : (string) $body['message'];
        } elseif (!$success && !empty($result['error'])) {
            $errorMessage = $result['error'];
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
        return 'combirds';
    }

    /**
     * Normalize phone to digits only (strips + and non-numeric chars).
     * Prepends 91 if a 10-digit Indian number is provided.
     *
     * @param string $phone Raw phone number
     * @return string Digits-only phone number
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        
        // Auto-prepend 91 for 10-digit Indian numbers if country code is omitted
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        return $digits;
    }

    /**
     * Perform HTTP POST request with JSON body and apikey header.
     *
     * @param string $url Target API endpoint URL
     * @param array $payload Payload data to JSON-encode
     * @return array ['body' => array|null, 'http_code' => int, 'error' => string]
     */
    private function httpPostJson(string $url, array $payload): array
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . trim($this->apiKey)
            ],
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