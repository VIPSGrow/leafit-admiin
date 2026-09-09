<?php

namespace App\Notification\Providers\Sms;

use App\Notification\Contracts\SmsProviderInterface;

/**
 * Twilio SMS provider.
 *
 * Sends SMS via Twilio REST API. Config is passed from SmsProviderFactory
 * (from sms_gateway_setting.twilio in the database).
 *
 * Config keys (in constructor):
 *   - account_sid  (string) Twilio account SID
 *   - auth_token   (string) Twilio auth token
 *   - from         (string) Twilio "From" phone number or Messaging Service SID
 *
 * To add another SMS provider: create a new class implementing SmsProviderInterface,
 * add it in SmsProviderFactory::make(); this class is not modified.
 */
class TwilioSmsProvider implements SmsProviderInterface
{
    private string $accountSid;
    private string $authToken;
    private string $from;

    /**
     * Create a new TwilioSmsProvider.
     *
     * @param array $config Required keys: account_sid, auth_token, from (under twilio in settings).
     */
    public function __construct(array $config)
    {
        $this->accountSid = (string) ($config['account_sid'] ?? '');
        $this->authToken  = (string) ($config['auth_token'] ?? '');
        $this->from       = (string) ($config['from'] ?? '');
    }

    /**
     * {@inheritDoc}
     * Sends one SMS via Twilio API (POST to Messages.json).
     * Twilio sends the message body directly; $options is ignored.
     */
    public function send(string $phone, string $message, array $options = []): array
    {
        if (empty($this->accountSid) || empty($this->authToken) || empty($this->from)) {
            return [
                'success' => false,
                'message' => 'Twilio configuration is incomplete',
                'data'    => null,
            ];
        }

        $body = [
            'To'   => $phone,
            'From' => $this->from,
            'Body' => $message,
        ];

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        $headers = [
            "Authorization: Basic " . base64_encode("{$this->accountSid}:{$this->authToken}"),
            "Content-Type: application/x-www-form-urlencoded",
        ];

        $result = $this->httpPost($url, http_build_query($body), $headers);
        $httpCode = $result['http_code'] ?? 0;
        $success = $httpCode === 201 || $httpCode === 200;

        return [
            'success' => $success,
            'message' => $success ? 'SMS sent successfully' : 'Failed to send SMS',
            'data'    => $result,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'twilio';
    }

    /**
     * Perform HTTP POST request (self-contained so provider does not depend on NotificationService).
     *
     * @param string $url     Full URL
     * @param string $body    Request body (e.g. application/x-www-form-urlencoded)
     * @param array  $headers List of "Header: value" strings
     * @return array ['body' => array|null, 'http_code' => int, 'error' => string]
     */
    private function httpPost(string $url, string $body, array $headers): array
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
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
