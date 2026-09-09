<?php

namespace App\Notification\Providers\Sms;

use App\Notification\Contracts\SmsProviderInterface;

/**
 * MSG91 Flow SMS provider.
 *
 * Sends SMS via MSG91 Flow API (POST). Uses verified template ID and variable
 * placeholders (VAR1, VAR2, etc.) instead of raw message body. Config is passed
 * from SmsProviderFactory (from sms_gateway_setting.msg91 in the database).
 *
 * Config keys (in constructor):
 *   - authkey (string) MSG91 auth key (authorization header)
 *
 * When sending, caller must pass options: template_id and variables (ordered array).
 * Variables are mapped to VAR1, VAR2, VAR3... in the recipients payload.
 *
 * @see https://docs.msg91.com/p/flow-api MSG91 Flow API
 */
class Msg91Provider implements SmsProviderInterface
{
    private string $authKey;

    /** MSG91 Flow API POST endpoint. */
    private const FLOW_URL = 'https://control.msg91.com/api/v5/flow';

    /** Log prefix for easy filtering in log files. */
    private const LOG_PREFIX = '[MSG91]';

    /**
     * Create a new Msg91Provider.
     *
     * @param array $config Required key: authkey (under msg91 in settings).
     */
    public function __construct(array $config)
    {
        $this->authKey = (string) ($config['authkey'] ?? '');
    }

    /**
     * {@inheritDoc}
     * Sends one SMS via MSG91 Flow API. Requires options['template_id'] and
     * options['variables'] (ordered array); maps to VAR1, VAR2, VAR3... in recipients.
     */
    public function send(string $phone, string $message, array $options = []): array
    {
        // log_message('info', self::LOG_PREFIX . ' send() called. phone=' . $this->maskPhone($phone) . ', message_length=' . strlen($message) . ', options_keys=' . implode(',', array_keys($options)));

        // Step 1: Validate authkey
        if (empty($this->authKey)) {
            log_message('info', self::LOG_PREFIX . ' STEP 1 FAILED: authkey is empty or missing. Check sms_gateway_setting.msg91.msg91_authkey in database.');
            return [
                'success' => false,
                'message' => 'MSG91 configuration is incomplete (authkey missing)',
                'data'    => null,
            ];
        }
        // log_message('info', self::LOG_PREFIX . ' STEP 1 OK: authkey present (length=' . strlen($this->authKey) . ')');

        $templateId = $options['template_id'] ?? null;
        $variables  = $options['variables'] ?? null;

        // Step 2: Validate template_id and variables
        if (empty($templateId) || ! is_array($variables)) {
            log_message('info', self::LOG_PREFIX . ' STEP 2 FAILED: template_id or variables missing. template_id=' . var_export($templateId, true) . ', variables_is_array=' . (is_array($variables) ? 'yes' : 'no') . ', variables=' . json_encode($variables));
            return [
                'success' => false,
                'message' => 'MSG91 Flow requires template_id and variables in options',
                'data'    => null,
            ];
        }
        // log_message('info', self::LOG_PREFIX . ' STEP 2 OK: template_id=' . $templateId . ', variables_count=' . count($variables) . ', variables=' . json_encode($variables));

        // Step 3: Normalize phone number
        $mobiles = $this->normalizePhone($phone);
        if ($mobiles === '') {
            log_message('info', self::LOG_PREFIX . ' STEP 3 FAILED: phone number invalid or empty after normalization. raw_phone=' . $this->maskPhone($phone));
            return [
                'success' => false,
                'message' => 'Invalid or empty phone number',
                'data'    => null,
            ];
        }
        // log_message('info', self::LOG_PREFIX . ' STEP 3 OK: normalized phone=' . $this->maskPhone($mobiles) . ' (length=' . strlen($mobiles) . ')');

        // Step 4: Build request body
        $recipient = ['mobiles' => $mobiles];
        foreach ($variables as $i => $value) {
            $varKey = 'VAR' . ($i + 1);
            $recipient[$varKey] = (string) ($value !== null ? $value : '');
        }

        $body = [
            'template_id' => $templateId,
            'recipients'  => [$recipient],
        ];
        // Log body with masked mobile for privacy; full body is sent to API
        $bodyForLog = $body;
        $bodyForLog['recipients'][0]['mobiles'] = $this->maskPhone($mobiles);
        // log_message('info', self::LOG_PREFIX . ' STEP 4: request body=' . json_encode($bodyForLog));

        // Step 5: Call MSG91 API
        // log_message('info', self::LOG_PREFIX . ' STEP 5: calling API POST ' . self::FLOW_URL);
        $result = $this->httpPost(self::FLOW_URL, $body);

        $responseBody = $result['body'] ?? null;
        $httpCode     = $result['http_code'] ?? 0;
        $curlError    = $result['error'] ?? '';

        // Log full API response for debugging
        // log_message('info', self::LOG_PREFIX . ' STEP 5 RESPONSE: http_code=' . $httpCode . ', curl_error=' . ($curlError ?: '(none)') . ', response_body=' . json_encode($responseBody));

        if ($curlError !== '') {
            log_message('info', self::LOG_PREFIX . ' STEP 5 FAILED: cURL error. error=' . $curlError . ', full_result=' . json_encode($result));
            return [
                'success' => false,
                'message' => 'MSG91 request failed: ' . $curlError,
                'data'    => $result,
            ];
        }

        // Step 6: Interpret response
        $success = $httpCode === 200;
        $errorMessage = 'Failed to send SMS';
        if (! $success && is_array($responseBody)) {
            $errorMessage = $responseBody['message'] ?? $responseBody['msg'] ?? $errorMessage;
        }

        // if ($success) {
        //     log_message('info', self::LOG_PREFIX . ' STEP 6 SUCCESS: SMS sent. http_code=200');
        // } else {
        //     log_message('error', self::LOG_PREFIX . ' STEP 6 FAILED: http_code=' . $httpCode . ', error_message=' . $errorMessage . ', response_body=' . json_encode($responseBody));
        // }

        return [
            'success' => $success,
            'message' => $success ? 'SMS sent successfully' : $errorMessage,
            'data'    => $result,
        ];
    }

    /**
     * Mask phone number for safe logging (show first 3 and last 2 digits).
     */
    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 5) {
            return str_repeat('*', $len);
        }
        return substr($phone, 0, 3) . str_repeat('*', $len - 5) . substr($phone, -2);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'msg91';
    }

    /**
     * Normalize phone to digits only for MSG91 (Indian numbers; strip + and spaces).
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
        $ch = curl_init();
        $jsonBody = json_encode($body);

        // Log request details (authkey masked for security)
        // log_message('info', self::LOG_PREFIX . ' httpPost: url=' . $url . ', json_body_length=' . strlen($jsonBody) . ', authkey_set=' . (empty($this->authKey) ? 'no' : 'yes'));

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
                'authkey: ' . $this->authKey,
                'content-type: application/json',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        // $curlErrno    = curl_errno($ch);
        unset($ch);

        // Decode response for return; also log raw response for debugging
        $decodedBody = is_string($responseBody) ? json_decode($responseBody, true) : null;
        // $rawPreview  = is_string($responseBody) ? (strlen($responseBody) > 500 ? substr($responseBody, 0, 500) . '...[truncated]' : $responseBody) : '(not string)';
        // log_message('info', self::LOG_PREFIX . ' httpPost result: http_code=' . $httpCode . ', curl_errno=' . $curlErrno . ', curl_error=' . ($error ?: '(none)') . ', raw_response_preview=' . $rawPreview);
        // log_message('info', self::LOG_PREFIX . ' httpPost decoded_body=' . json_encode($decodedBody));

        return [
            'body'      => $decodedBody,
            'http_code' => $httpCode,
            'error'     => $error ?: '',
        ];
    }
}
