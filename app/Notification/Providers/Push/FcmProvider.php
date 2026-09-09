<?php

namespace App\Notification\Providers\Push;

use App\Notification\Contracts\PushProviderInterface;
use App\Services\NotificationTemplateService;
use Google\Client;

/**
 * Firebase Cloud Messaging (FCM) HTTP v1 provider.
 *
 * Sends push notifications via the FCM v1 REST API using HTTP/2 multiplexing
 * (`curl_multi` + CURLPIPE_MULTIPLEX) so hundreds of tokens are delivered in
 * parallel over a single TCP connection — roughly 22× faster than sequential
 * HTTP/1.1 calls the old implementation used.
 *
 * This replaces the per-token loop that was inside NotificationService::sendFcm().
 *
 * Usage (standalone — normally called via PushProviderFactory):
 *   $provider = new FcmProvider([
 *       'project_id'        => 'my-firebase-project',
 *       'service_file_path' => '/abs/path/to/service-account.json',
 *   ]);
 *   $result = $provider->send($tokens, 'Hello', 'World', [
 *       'type' => 'booking_confirmed',
 *       'data' => ['booking_id' => '123'],
 *       'device_info' => ['tokenA' => 'android', 'tokenB' => 'ios'],
 *   ]);
 */
class FcmProvider implements PushProviderInterface
{
    // ──────────────────────────────────────────────
    //  Constants
    // ──────────────────────────────────────────────

    /** Max tokens per curl_multi batch. Keeps memory usage reasonable. */
    private const DEFAULT_BATCH_SIZE = 500;

    /** Per-request timeout (seconds) for each FCM HTTP/2 handle. */
    private const FCM_REQUEST_TIMEOUT = 10;

    /** TCP connect timeout (seconds) for each FCM HTTP/2 handle. */
    private const FCM_CONNECT_TIMEOUT = 5;

    /** FCM v1 send endpoint template. %s = project ID. */
    private const FCM_URL_TEMPLATE = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    /** OAuth2 scope required for FCM v1. */
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    // ──────────────────────────────────────────────
    //  Configuration (injected at construction)
    // ──────────────────────────────────────────────

    /** Firebase project ID (e.g. "my-app-12345"). */
    private ?string $projectId;

    /** Absolute path to the Firebase service-account JSON file. */
    private ?string $serviceFilePath;

    /** Template service for title/body resolution per language. */
    private NotificationTemplateService $templateService;

    /** Cached OAuth2 Bearer token. Valid for ~1 hour. */
    private ?string $accessToken = null;

    /**
     * Create a new FcmProvider.
     *
     * @param array $config Required keys: project_id, service_file_path. Optional: template_service (NotificationTemplateService).
     */
    public function __construct(array $config)
    {
        $this->projectId = $config['project_id'] ?? null;
        $this->serviceFilePath = $config['service_file_path'] ?? null;
        $this->templateService = $config['template_service'] ?? new NotificationTemplateService();
    }

    // ================================================================
    //  PushProviderInterface — public API
    // ================================================================

    /**
     * {@inheritDoc}
     * Full FCM flow: resolve tokens, templates, enrich, send, cleanup, store in DB.
     */
    public function sendNotification(string $eventType, array $recipients, array $context, array $options = []): array
    {
        // $this->writeLog('info', '[FCM_PROVIDER] ===== SEND METHOD CALLED =====');
        // $this->writeLog('info', '[FCM_PROVIDER] Event type: ' . $eventType);
        // $this->writeLog('info', '[FCM_PROVIDER] Recipients: ' . json_encode($recipients));
        // $this->writeLog('info', '[FCM_PROVIDER] Context: ' . json_encode($context));
        // $this->writeLog('info', '[FCM_PROVIDER] Options: ' . json_encode($options));
        $userIdsForStorage = [];
        if (!empty($options['user_ids']) && is_array($options['user_ids'])) {
            $userIdsForStorage = array_values(array_unique(array_filter(array_map('intval', $options['user_ids']))));
        } elseif (!empty($recipients['user_id'])) {
            $userIdsForStorage = [(int) $recipients['user_id']];
        }

        $userIds = $options['user_ids'] ?? [];
        $platforms = $options['platforms'] ?? null;
        $userGroups = $options['user_groups'] ?? null;
        $sendToAll = $options['send_to_all'] ?? false;

        $fcmTokensByLanguage = $recipients['fcm_tokens_by_language'] ?? [];
        if (empty($fcmTokensByLanguage) && !empty($recipients['fcm_tokens'])) {
            $fcmTokensByLanguage = $this->groupTokensByLanguage($recipients['fcm_tokens']);
        }
        if (empty($fcmTokensByLanguage) && isset($recipients['user_id'])) {
            $fcmTokensByLanguage = $this->getUserFcmTokensGroupedByLanguage($recipients['user_id'], $platforms);
        }
        if (empty($fcmTokensByLanguage)) {
            $prepared = $this->prepareRecipients($userIds, $platforms, $userGroups, $sendToAll, $recipients);
            $fcmTokensByLanguage = $prepared['fcm_tokens_by_language'] ?? [];
        }

        // When running in demo mode we may receive an explicit per-language
        // FCM token limit from upstream (e.g. chat APIs). This lets us keep
        // demo pushes responsive even if a user has hundreds of stored tokens.
        // We keep only the last N tokens in each language bucket.
        $demoTokenLimit = isset($options['fcm_demo_token_limit']) ? (int) $options['fcm_demo_token_limit'] : 0;
        if ($demoTokenLimit > 0) {
            foreach ($fcmTokensByLanguage as $langCode => $tokens) {
                if (is_array($tokens) && count($tokens) > $demoTokenLimit) {
                    $fcmTokensByLanguage[$langCode] = array_slice($tokens, -$demoTokenLimit);
                }
            }
        }

        if (empty($fcmTokensByLanguage)) {
            if ($eventType !== 'new_message' && $eventType !== 'admin_custom_notification' && $eventType !== 'maintenance_mode') {
                $this->storeNotificationsInDatabase([], $eventType, $context, ['user_ids' => $userIdsForStorage] + $options);
            }
            $this->writeLog('info', '[FCM_NOTIFICATION] No FCM tokens found for recipient. Event: ' . $eventType);
            return ['error' => true, 'message' => 'No FCM tokens found for recipient.'];
        }

        $defaultLanguage = get_default_language();
        $resultsByLanguage = [];
        $allResults = [];
        $totalSuccess = 0;
        $totalFailure = 0;
        $totalInvalidTokens = 0;
        $allInvalidTokens = [];

        try {
            foreach ($fcmTokensByLanguage as $languageCode => $tokens) {
                if (empty($tokens)) {
                    continue;
                }
                $templateLanguage = $languageCode ?: $defaultLanguage;

                if ($eventType === 'admin_custom_notification') {
                    $title = $options['title'] ?? $context['title'] ?? 'Notification';
                    $message = $options['message'] ?? $context['message'] ?? '';
                } elseif ($eventType === 'new_message') {
                    // Chat notifications should use direct context payload and not FCM templates.
                    $title = $options['title'] ?? $context['title'] ?? ($context['sender_name'] ?? 'New Message');
                    $message = $options['message'] ?? $context['message'] ?? ($context['message_content'] ?? '');
                } else {
                    $template = $this->templateService->getFcmTemplate($eventType, $templateLanguage);
                    if (!$template && $templateLanguage !== $defaultLanguage) {
                        $template = $this->templateService->getFcmTemplate($eventType, $defaultLanguage);
                        $templateLanguage = $defaultLanguage;
                    }
                    if ($template) {
                        $variables = $this->templateService->extractVariablesFromContext($eventType, $context);
                        $title = $this->templateService->replaceVariables($template['title'], $variables);
                        $bodyVars = $variables;
                        if ($eventType !== 'new_message') {
                            unset($bodyVars['sender_name']);
                        }
                        $message = $this->templateService->replaceVariables($template['body'], $bodyVars);
                    } else {
                        $title = $options['title'] ?? $context['title'] ?? 'Notification';
                        $message = $options['message'] ?? $context['message'] ?? '';
                        if (!empty($context['sender_name'])) {
                            $escaped = preg_quote($context['sender_name'], '/');
                            $message = preg_replace('/\b' . $escaped . '\b\s*[:,\-]?\s*/i', '', $message);
                            $message = preg_replace('/\s+/', ' ', trim($message));
                        }
                    }
                }

                $type = $options['type'] ?? $eventType;
                $customData = $options['data'] ?? [];
                if ($eventType === 'new_message') {
                    // Keep all incoming context fields in push data for chat consumers.
                    $customData = array_merge($context, $customData);
                }
                $customData = $this->enrichNotificationData($customData, $context);
                if (empty($customData['click_action'])) {
                    $customData['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
                }
                if ($eventType === 'new_message') {
                    // Hard guarantee: chat context keys must exist in FCM data payload.
                    if (!array_key_exists('chat_message', $customData)) {
                        $customData['chat_message'] = $context['chat_message'] ?? '';
                    }
                    if (!array_key_exists('chat_user', $customData)) {
                        $customData['chat_user'] = $context['chat_user'] ?? '';
                    }
                }

                $deviceInfoList = $this->getDeviceInfo($tokens);
                $deviceInfoMap = [];
                foreach ($deviceInfoList as $d) {
                    $deviceInfoMap[$d['fcm_token']] = $d['platform_type'];
                }

                // $this->writeLog('info', '[FCM_PROVIDER] Sending notification: ' . $eventType);
                // $this->writeLog('info', '[FCM_PROVIDER] Title: ' . $title);
                // $this->writeLog('info', '[FCM_PROVIDER] Message: ' . $message);
                // if ($eventType === 'new_message') {
                //     $this->writeLog('info', '[FCM_PROVIDER] Chat keys present - chat_message: ' . (array_key_exists('chat_message', $customData) ? 'yes' : 'no') . ', chat_user: ' . (array_key_exists('chat_user', $customData) ? 'yes' : 'no'));
                // }
                // $this->writeLog('info', '[FCM_PROVIDER] Custom Data: ' . json_encode($customData));

                $result = $this->send($tokens, $title, $message, [
                    'type' => $type,
                    'data' => $customData,
                    'device_info' => $deviceInfoMap,
                ]);

                if (!empty($result['invalid_tokens'])) {
                    $allInvalidTokens = array_merge($allInvalidTokens, $result['invalid_tokens']);
                }
                $resultsByLanguage[$templateLanguage] = $result;
                $allResults[] = $result;
                $totalSuccess += $result['success_count'] ?? 0;
                $totalFailure += $result['failure_count'] ?? 0;
                $totalInvalidTokens += $result['invalid_tokens_count'] ?? 0;
            }

            if (!empty($allInvalidTokens)) {
                $this->cleanupInvalidTokens(array_unique($allInvalidTokens));
            }

            if ($eventType !== 'new_message' && $eventType !== 'admin_custom_notification' && $eventType !== 'maintenance_mode') {
                $this->storeNotificationsInDatabase([], $eventType, $context, ['user_ids' => $userIdsForStorage] + $options);
            }

            // Log full details for debugging; response stays simple for the API.
            $fullDetails = [
                'event_type' => $eventType,
                'success_count' => $totalSuccess,
                'failure_count' => $totalFailure,
                'invalid_tokens_count' => $totalInvalidTokens,
                'total_languages' => count($resultsByLanguage),
                'results_by_language' => $resultsByLanguage,
            ];
            $this->writeLog('info', '[FCM_NOTIFICATION] Result: ' . json_encode($fullDetails));

            // Simple contextual response: only error and message.
            $error = $totalFailure > 0;
            $message = $error
                ? 'Not sent; check logs for more details.'
                : 'Sent successfully.';

            return [
                'error' => $error,
                'message' => $message,
            ];
        } catch (\Throwable $th) {
            $this->writeLog('error', '[FCM_NOTIFICATION] Exception: ' . $th->getMessage());
            return [
                'error' => true,
                'message' => 'Not sent; check logs for more details.',
            ];
        }
    }

    /**
     * {@inheritDoc}
     *
     * Sends notifications using HTTP/2 multiplexed curl_multi.
     * Tokens are split into batches of `batch_size` (default 500) and each
     * batch is sent in parallel. Results are aggregated across all batches.
     */
    public function send(array $tokens, string $title, string $message, array $options = []): array
    {
        // ── Guard: need project ID ──
        if (empty($this->projectId)) {
            return $this->errorResult('FCM project ID is not configured.');
        }

        // ── Guard: need service file ──
        if (empty($this->serviceFilePath) || !file_exists($this->serviceFilePath)) {
            return $this->errorResult('FCM service-account file is missing or not configured.');
        }

        // ── Guard: need at least one token ──
        if (empty($tokens)) {
            return $this->errorResult('No tokens provided.');
        }

        // ── Authenticate (cached within request) ──
        $authResult = $this->authenticate();
        if ($authResult['error'] ?? false) {
            return $this->errorResult($authResult['message'] ?? 'FCM authentication failed.');
        }

        // ── Unpack options ──
        $type = $options['type'] ?? 'default';
        $customData = $options['data'] ?? [];
        $deviceInfo = $options['device_info'] ?? [];
        $batchSize = (int) ($options['batch_size'] ?? self::DEFAULT_BATCH_SIZE);

        // FCM data payload must be all-string values.
        // Merge title, body, and type into the data payload so Android (data-only) messages
        // can be displayed by the client app.
        $dataPayload = $this->stringifyPayload(array_merge($customData, [
            'title' => $title,
            'body' => $message,
            'type' => $type,
        ]));

        // ── Build per-token message payloads ──
        $url = $this->getApiUrl();
        $messagesList = [];

        foreach ($tokens as $token) {
            $messagesList[] = $this->buildMessagePayload(
                $token,
                $title,
                $message,
                $dataPayload,
                $this->resolvePlatform($token, $deviceInfo)
            );
        }

        // $this->writeLog('info', '[FCM_PROVIDER] Messages List: ' . json_encode($messagesList));

        // ── Send in batches via curl_multi (HTTP/2 multiplexed) ──
        $batches = array_chunk($messagesList, max(1, $batchSize));
        $allResponses = [];

        foreach ($batches as $batch) {
            $batchResults = $this->sendBatch($batch, $url, $this->accessToken);
            $allResponses = array_merge($allResponses, $batchResults);
        }

        // ── Aggregate results ──
        return $this->aggregateResults($tokens, $allResponses);
    }

    /**
     * {@inheritDoc}
     *
     * Deletes rows from `users_fcm_ids` where the token matches.
     */
    public function cleanupInvalidTokens(array $tokens): array
    {
        if (empty($tokens)) {
            return [
                'success' => true,
                'cleaned_count' => 0,
                'message' => 'No invalid tokens to clean up.',
            ];
        }

        try {
            $db = \Config\Database::connect();
            $builder = $db->table('users_fcm_ids');

            // Soft-delete: set status = 0 to match original NotificationService behavior (audit trail preserved).
            $builder->whereIn('fcm_id', $tokens)
                ->update(['status' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
            $affected = $db->affectedRows();

            return [
                'success' => true,
                'cleaned_count' => $affected,
                'message' => "Cleaned up {$affected} invalid FCM tokens",
                'tokens_processed' => count($tokens),
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'cleaned_count' => 0,
                'message' => 'Token cleanup failed: ' . $th->getMessage(),
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'fcm';
    }

    // ================================================================
    //  Core sending — HTTP/2 multiplexed curl_multi
    // ================================================================

    /**
     * Send a batch of FCM messages in parallel using curl_multi.
     *
     * Uses HTTP/2 multiplexing (CURLPIPE_MULTIPLEX) so all requests share a
     * single TCP connection. This is Google's recommended replacement for the
     * deprecated batch endpoint.
     *
     * @param  array  $messages     Array of FCM message payload arrays.
     * @param  string $url          FCM v1 API URL.
     * @param  string $accessToken  OAuth2 Bearer token.
     * @return array  Per-message results: [['response' => array|null, 'http_code' => int, 'error' => string], ...]
     */
    private function sendBatch(array $messages, string $url, string $accessToken): array
    {
        $multiHandle = curl_multi_init();

        // Enable HTTP/2 multiplexing — all handles share one connection.
        curl_multi_setopt($multiHandle, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];

        // ── Create a cURL handle per message ──
        $curlHandles = [];
        foreach ($messages as $index => $messagePayload) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => json_encode($messagePayload),

                // Force HTTP/2 — required for multiplexing.
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,

                // TLS settings
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,

                // Timeouts — prevent stalled connections from blocking the batch.
                CURLOPT_CONNECTTIMEOUT => self::FCM_CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::FCM_REQUEST_TIMEOUT,
            ]);

            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$index] = $ch;
        }

        // ── Execute all handles in parallel ──
        do {
            $status = curl_multi_exec($multiHandle, $active);

            // Wait for activity on any handle (avoids busy-wait CPU spin).
            if ($active) {
                curl_multi_select($multiHandle);
            }
        } while ($active && $status === CURLM_OK);

        // ── Collect per-handle results ──
        $results = [];
        foreach ($curlHandles as $index => $ch) {
            $body = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);

            $results[$index] = [
                'response' => $body ? json_decode($body, true) : null,
                'http_code' => $httpCode,
                'error' => $curlErr,
            ];

            curl_multi_remove_handle($multiHandle, $ch);
            unset($ch);
        }

        curl_multi_close($multiHandle);

        return $results;
    }

    // ================================================================
    //  Payload building
    // ================================================================

    /**
     * Build the FCM v1 message payload for a single token.
     *
     * Android gets a data-only message (no `notification` block) so the client
     * app controls display. iOS/Web get both `notification` and `data` blocks,
     * plus APNS high-priority headers for immediate delivery.
     *
     * @param  string $token       Device FCM token.
     * @param  string $title       Notification title.
     * @param  string $message     Notification body.
     * @param  array  $dataPayload Stringified custom data (including title/body/type).
     * @param  string $platform    Platform identifier: 'android', 'ios', 'web', etc.
     * @return array  The payload array ready for json_encode().
     */
    private function buildMessagePayload(
        string $token,
        string $title,
        string $message,
        array $dataPayload,
        string $platform
    ): array {
        $payload = [
            'message' => [
                'token' => $token,
                'data' => $dataPayload,

                // APNS config — included for all platforms (harmless on non-Apple devices).
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10', // High priority for immediate delivery
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $message,
                            ],
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        // Add `notification` block for non-Android platforms.
        // Android relies on data-only messages so the Flutter/native app
        // handles display and can tap into custom data fields.
        if ($platform !== 'android') {
            $payload['message']['notification'] = [
                'title' => $title,
                'body' => $message,
            ];
        }

        return $payload;
    }

    /**
     * Resolve the platform for a given token from device_info map.
     *
     * Falls back to 'android' (data-only — safest default that works
     * everywhere since the client app handles display).
     *
     * @param  string $token      FCM token.
     * @param  array  $deviceInfo Map of token => platform string.
     * @return string Normalised platform: 'android', 'ios', 'web', etc.
     */
    private function resolvePlatform(string $token, array $deviceInfo): string
    {
        $platform = $deviceInfo[$token] ?? 'android';
        return strtolower($platform);
    }

    // ================================================================
    //  Authentication (OAuth2 via Google Client)
    // ================================================================

    /**
     * Authenticate with FCM using the service-account JSON file.
     *
     * The access token is cached on this instance so repeated calls within the
     * same PHP request reuse it (it's valid for ~1 hour).
     *
     * @return array ['error' => bool, 'message' => string, 'data' => string|null]
     */
    private function authenticate(): array
    {
        // Return cached token if already authenticated in this request.
        if ($this->accessToken !== null) {
            return ['error' => false, 'data' => $this->accessToken];
        }

        try {
            $client = new Client();
            $client->setAuthConfig($this->serviceFilePath);
            $client->setScopes([self::FCM_SCOPE]);

            $tokenData = $client->fetchAccessTokenWithAssertion();

            if (empty($tokenData['access_token'])) {
                return [
                    'error' => true,
                    'message' => 'FCM OAuth2 token response did not contain an access_token.',
                ];
            }

            // Cache for the lifetime of this PHP request.
            $this->accessToken = $tokenData['access_token'];

            return ['error' => false, 'data' => $this->accessToken];
        } catch (\Throwable $th) {
            return [
                'error' => true,
                'message' => 'Failed to get FCM access token: ' . $th->getMessage(),
            ];
        }
    }

    // ================================================================
    //  Result aggregation
    // ================================================================

    /**
     * Aggregate per-token responses into the standardised result array.
     *
     * Identifies invalid/expired tokens by checking known FCM error codes
     * so the caller (or auto-cleanup) can remove them from persistent storage.
     *
     * @param  array $tokens    Original token list (same order as responses).
     * @param  array $responses Per-token results from sendBatch().
     * @return array Standardised result matching PushProviderInterface::send().
     */
    private function aggregateResults(array $tokens, array $responses): array
    {
        $successCount = 0;
        $failureCount = 0;
        $invalidTokens = [];
        $rawData = [];

        foreach ($responses as $index => $result) {
            $response = $result['response'] ?? null;
            $httpCode = $result['http_code'] ?? 0;

            // cURL-level failure (no response at all — stalled/unreachable token).
            // Treat as invalid so the token is removed and never retried.
            if ($response === null || !empty($result['error'])) {
                $failureCount++;
                $rawData[] = $result;
                if (isset($tokens[$index])) {
                    $invalidTokens[] = $tokens[$index];
                }
                continue;
            }

            // FCM error in response body
            if (isset($response['error'])) {
                $errorCode = (string) ($response['error']['code'] ?? '');
                $errorMessage = (string) ($response['error']['message'] ?? '');

                // Check if the token is invalid/expired so we can clean it up.
                if ($this->isInvalidTokenError($errorCode, $errorMessage)) {
                    $invalidTokens[] = $tokens[$index] ?? null;
                }

                $failureCount++;
            } else {
                // Success — FCM accepted the message.
                $successCount++;
            }

            $rawData[] = $result;
        }

        // Remove nulls from invalid tokens (safety net for index mismatch).
        $invalidTokens = array_values(array_filter($invalidTokens));

        return [
            'success' => $failureCount === 0,
            'message' => "FCM: {$successCount} sent, {$failureCount} failed.",
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'invalid_tokens' => $invalidTokens,
            'invalid_tokens_count' => count($invalidTokens),
            'data' => $rawData,
        ];
    }

    // ================================================================
    //  Error detection helpers
    // ================================================================

    /**
     * Check if an FCM error indicates the token is invalid or expired.
     *
     * These tokens should be removed from the database to avoid sending
     * to them again in future requests.
     *
     * @param  string $errorCode    FCM error code (e.g. "NOT_FOUND", "UNREGISTERED").
     * @param  string $errorMessage FCM error message string.
     * @return bool   True if the token should be considered dead.
     */
    private function isInvalidTokenError(string $errorCode, string $errorMessage): bool
    {
        // Known FCM v1 error codes for dead tokens.
        $deadCodes = [
            'NOT_FOUND',
            'INVALID_ARGUMENT',
            'UNREGISTERED',
            'UNREGISTERED_TOKEN',
            404,
        ];

        if (in_array($errorCode, $deadCodes, false)) {
            return true;
        }

        // Fallback: pattern-match the error message.
        $patterns = [
            'registration-token-not-registered',
            'invalid-registration-token',
            'registration-token-not-valid',
            'requested entity was not found',
        ];

        $lower = strtolower($errorMessage);
        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    // ================================================================
    //  Utility helpers
    // ================================================================

    /**
     * Build the FCM v1 REST URL for this project.
     *
     * @return string Full URL.
     */
    private function getApiUrl(): string
    {
        return sprintf(self::FCM_URL_TEMPLATE, $this->projectId);
    }

    /**
     * Convert every value in the payload to a string.
     *
     * FCM `data` payloads require all values to be strings. Nested arrays are
     * JSON-encoded so the client can decode them back.
     *
     * @param  array $data Key-value data array.
     * @return array Same keys, all values cast to strings.
     */
    private function stringifyPayload(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // JSON-encode arrays so client can decode them on the other end.
                $result[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_null($value)) {
                $result[$key] = '';
            } else {
                $result[$key] = (string) $value;
            }
        }
        return $result;
    }

    /**
     * Shortcut to build a standardised error result.
     *
     * @param  string $message Human-readable error description.
     * @return array  Result array with success=false.
     */
    private function errorResult(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'success_count' => 0,
            'failure_count' => 0,
            'invalid_tokens' => [],
            'invalid_tokens_count' => 0,
            'data' => [],
        ];
    }

    // ─── High-level sendNotification helpers: token resolution, enrichment, storage ───

    /**
     * Prepare FCM recipients: resolve tokens grouped by language from user_ids, platforms, groups, or send_to_all.
     */
    private function prepareRecipients(array $userIds, ?array $platforms, ?array $userGroups, bool $sendToAll, array $recipients): array
    {
        $fcmTokensByLanguage = [];

        if (!empty($recipients['fcm_tokens'])) {
            $fcmTokensByLanguage = $this->groupTokensByLanguage($recipients['fcm_tokens']);
            $recipients['fcm_tokens_by_language'] = $fcmTokensByLanguage;
            return $recipients;
        }

        if (!empty($recipients['fcm_tokens_by_language'])) {
            return $recipients;
        }

        if (!empty($userIds)) {
            $fcmTokensByLanguage = $this->getFcmTokensByUserIdsGroupedByLanguage($userIds, $platforms);
        } elseif ($sendToAll) {
            if (!empty($platforms)) {
                $fcmTokensByLanguage = $this->getFcmTokensByPlatformsGroupedByLanguage($platforms, $userGroups);
            } elseif (!empty($userGroups)) {
                $fcmTokensByLanguage = $this->getFcmTokensByUserGroupsGroupedByLanguage($userGroups);
            } else {
                $fcmTokensByLanguage = $this->getFcmTokensByPlatformsGroupedByLanguage(['android', 'ios', 'admin_panel', 'provider_panel', 'web']);
            }
        } elseif (!empty($userGroups)) {
            $fcmTokensByLanguage = $this->getFcmTokensByUserGroupsGroupedByLanguage($userGroups, $platforms);
        } elseif (!empty($platforms)) {
            $fcmTokensByLanguage = $this->getFcmTokensByPlatformsGroupedByLanguage($platforms);
        }

        $recipients['fcm_tokens_by_language'] = $fcmTokensByLanguage;
        return $recipients;
    }

    private function getUserFcmTokensGroupedByLanguage(int $userId, ?array $platforms = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('users_fcm_ids');
        $builder->select(['fcm_id', 'language_code'])->where('user_id', $userId)->where('status', 1);
        if (!empty($platforms)) {
            $builder->whereIn('platform', $platforms);
        }
        $tokens = $builder->get()->getResultArray();

        $tokensByLanguage = [];
        foreach ($tokens as $token) {
            if (!empty($token['fcm_id'])) {
                $langCode = !empty($token['language_code']) ? $token['language_code'] : '';
                if (!isset($tokensByLanguage[$langCode])) {
                    $tokensByLanguage[$langCode] = [];
                }
                $tokensByLanguage[$langCode][] = $token['fcm_id'];
            }
        }

        if (empty($tokensByLanguage) && empty($platforms)) {
            $user = $this->fetchDetails('users', ['id' => $userId], ['fcm_id', 'language_code']);
            if (!empty($user) && !empty($user[0]['fcm_id'])) {
                $userLangCode = !empty($user[0]['language_code']) ? $user[0]['language_code'] : '';
                $tokensByLanguage[$userLangCode] = [$user[0]['fcm_id']];
            }
        }

        return $tokensByLanguage;
    }

    private function getFcmTokensByUserIdsGroupedByLanguage(array $userIds, ?array $platforms = null): array
    {
        if (empty($userIds)) {
            return [];
        }
        $db = \Config\Database::connect();
        $builder = $db->table('users_fcm_ids');
        $builder->select(['fcm_id', 'language_code'])->whereIn('user_id', $userIds)->where('status', 1);
        if (!empty($platforms)) {
            $builder->whereIn('platform', $platforms);
        }
        $tokens = $builder->get()->getResultArray();

        $tokensByLanguage = [];
        foreach ($tokens as $token) {
            if (!empty($token['fcm_id'])) {
                $langCode = !empty($token['language_code']) ? $token['language_code'] : '';
                if (!isset($tokensByLanguage[$langCode])) {
                    $tokensByLanguage[$langCode] = [];
                }
                $tokensByLanguage[$langCode][] = $token['fcm_id'];
            }
        }
        return $tokensByLanguage;
    }

    private function getFcmTokensByPlatformsGroupedByLanguage(array $platforms, ?array $userGroups = null): array
    {
        if (empty($platforms)) {
            return [];
        }
        $db = \Config\Database::connect();
        $builder = $db->table('users_fcm_ids uf');
        $builder->select(['uf.fcm_id', 'uf.language_code'])->whereIn('uf.platform', $platforms)->where('uf.status', 1);
        if (!empty($userGroups)) {
            $builder->join('users_groups ug', 'ug.user_id = uf.user_id')->whereIn('ug.group_id', $userGroups);
        }
        $tokens = $builder->get()->getResultArray();

        $tokensByLanguage = [];
        foreach ($tokens as $token) {
            if (!empty($token['fcm_id'])) {
                $langCode = !empty($token['language_code']) ? $token['language_code'] : '';
                if (!isset($tokensByLanguage[$langCode])) {
                    $tokensByLanguage[$langCode] = [];
                }
                $tokensByLanguage[$langCode][] = $token['fcm_id'];
            }
        }
        return $tokensByLanguage;
    }

    private function getFcmTokensByUserGroupsGroupedByLanguage(array $userGroups, ?array $platforms = null): array
    {
        if (empty($userGroups)) {
            return [];
        }
        $db = \Config\Database::connect();
        $builder = $db->table('users_fcm_ids uf');
        $builder->join('users_groups ug', 'ug.user_id = uf.user_id')
            ->select(['uf.fcm_id', 'uf.language_code'])
            ->whereIn('ug.group_id', $userGroups)
            ->where('uf.status', 1);
        if (!empty($platforms)) {
            $builder->whereIn('uf.platform', $platforms);
        }
        $tokens = $builder->get()->getResultArray();

        $tokensByLanguage = [];
        foreach ($tokens as $token) {
            if (!empty($token['fcm_id'])) {
                $langCode = !empty($token['language_code']) ? $token['language_code'] : '';
                if (!isset($tokensByLanguage[$langCode])) {
                    $tokensByLanguage[$langCode] = [];
                }
                $tokensByLanguage[$langCode][] = $token['fcm_id'];
            }
        }
        return $tokensByLanguage;
    }

    private function groupTokensByLanguage(array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }
        $db = \Config\Database::connect();
        $data = $db->table('users_fcm_ids')->select(['fcm_id', 'language_code'])
            ->whereIn('fcm_id', $tokens)->where('status', 1)->get()->getResultArray();

        $tokenLanguageMap = [];
        foreach ($data as $row) {
            if (!empty($row['fcm_id'])) {
                $tokenLanguageMap[$row['fcm_id']] = !empty($row['language_code']) ? $row['language_code'] : '';
            }
        }

        $tokensByLanguage = [];
        foreach ($tokens as $token) {
            $langCode = $tokenLanguageMap[$token] ?? '';
            if (!isset($tokensByLanguage[$langCode])) {
                $tokensByLanguage[$langCode] = [];
            }
            $tokensByLanguage[$langCode][] = $token;
        }
        return $tokensByLanguage;
    }

    private function getDeviceInfo(array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }
        $db = \Config\Database::connect();
        $rows = $db->table('users_fcm_ids')->select(['fcm_id', 'platform'])
            ->whereIn('fcm_id', $tokens)->where('status', 1)->get()->getResultArray();

        $result = [];
        foreach ($rows as $device) {
            $result[] = [
                'fcm_token' => $device['fcm_id'],
                'platform_type' => strtolower($device['platform'] ?? 'android'),
            ];
        }
        return $result;
    }

    private function fetchDetails(string $table, array $where = [], array $select = ['*']): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table($table);
        if (!empty($select) && !in_array('*', $select)) {
            $builder->select(implode(', ', $select));
        }
        if (!empty($where)) {
            $builder->where($where);
        }
        $result = $builder->get()->getResultArray();
        return $result;
    }

    private function getAllParentCategorySlugs($db, int $parentId): array
    {
        $parentSlugs = [];
        $currentParentId = $parentId;
        while (!empty($currentParentId) && $currentParentId != '0') {
            $parentCategory = $db->table('categories')->select('slug, parent_id')->where('id', $currentParentId)->get()->getResultArray();
            if (!empty($parentCategory) && !empty($parentCategory[0]['slug'])) {
                $parentSlugs[] = $parentCategory[0]['slug'];
                $currentParentId = $parentCategory[0]['parent_id'] ?? null;
            } else {
                break;
            }
        }
        return array_values(array_reverse($parentSlugs));
    }

    private function enrichNotificationData(array $customData, array $context): array
    {
        $db = \Config\Database::connect();

        $looksLikeChatContext =
            array_key_exists('viewer_type', $context) ||
            array_key_exists('last_message_date', $context) ||
            array_key_exists('sender_details', $context) ||
            array_key_exists('receiver_details', $context) ||
            array_key_exists('sender_id', $context) ||
            array_key_exists('receiver_id', $context);

        if ($looksLikeChatContext) {
            $chatKeys = [
                'id',
                'sender_id',
                'receiver_id',
                'booking_id',
                'e_id',
                'viewer_type',
                'sender_type',
                'receiver_type',
                'message',
                'message_content',
                'file',
                'file_type',
                'created_at',
                'updated_at',
                'last_message_date',
                'sender_details',
                'receiver_details',
                'chat_message',
                'chat_user',
            ];
            foreach ($chatKeys as $key) {
                if (!array_key_exists($key, $customData) || $customData[$key] === '' || $customData[$key] === null) {
                    if (array_key_exists($key, $context) && $context[$key] !== null && $context[$key] !== '') {
                        $customData[$key] = $context[$key];
                    }
                }
            }
            if ((!isset($customData['senderId']) || $customData['senderId'] === '') && !empty($customData['sender_id'])) {
                $customData['senderId'] = (string) $customData['sender_id'];
            }
            if ((!isset($customData['receiverId']) || $customData['receiverId'] === '') && !empty($customData['receiver_id'])) {
                $customData['receiverId'] = (string) $customData['receiver_id'];
            }
            if ((!isset($customData['bookingId']) || $customData['bookingId'] === '') && isset($customData['booking_id'])) {
                $customData['bookingId'] = (string) $customData['booking_id'];
            }
        }


        $providerId = $context['provider_id'] ?? $customData['provider_id'] ?? null;
        $categoryId = $context['category_id'] ?? $customData['category_id'] ?? null;
        $serviceId = $context['service_id'] ?? $customData['service_id'] ?? null;
        $blogId = $context['blog_id'] ?? $customData['blog_id'] ?? null;

        if (!empty($providerId)) {
            $providerData = $db->table('partner_details')->select('partner_id, company_name, slug')->where('partner_id', $providerId)->get()->getResultArray();
            if (!empty($providerData)) {
                if (!isset($customData['provider_slug']) || $customData['provider_slug'] === '') {
                    $customData['provider_slug'] = $providerData[0]['slug'] ?? '';
                }
                if (!empty($customData['provider_slug'])) {
                    $customData['web_click_type'] = 'provider-details';
                }
                $defaultLang = get_default_language();
                $translated = $db->table('translated_partner_details')->select('company_name')->where('partner_id', $providerId)->where('language_code', $defaultLang)->get()->getResultArray();
                $customData['provider_name_default'] = (!empty($translated) && !empty($translated[0]['company_name'])) ? $translated[0]['company_name'] : ($providerData[0]['company_name'] ?? '');
                if (!isset($customData['provider_id'])) {
                    $customData['provider_id'] = $providerId;
                }
                if (!isset($customData['provider_name']) && !empty($providerData[0]['company_name'])) {
                    $customData['provider_name'] = $providerData[0]['company_name'];
                }
            }
        }

        if (!empty($categoryId)) {
            $categoryData = $db->table('categories')->select('id, name, parent_id, slug')->where('id', $categoryId)->get()->getResultArray();
            if (!empty($categoryData)) {
                if (!isset($customData['category_slug']) || $customData['category_slug'] === '') {
                    $customData['category_slug'] = $categoryData[0]['slug'] ?? '';
                }
                if (!empty($customData['category_slug'])) {
                    $customData['web_click_type'] = 'category';
                }
                $parentId = $categoryData[0]['parent_id'] ?? null;
                if (!empty($parentId) && $parentId != '0') {
                    $parentSlugs = $this->getAllParentCategorySlugs($db, (int) $parentId);
                    if (!empty($parentSlugs)) {
                        $customData['parent_category_slugs'] = json_encode(array_values($parentSlugs), JSON_UNESCAPED_SLASHES);
                    }
                }
                if (!isset($customData['category_id'])) {
                    $customData['category_id'] = $categoryId;
                }
                if (!isset($customData['parent_id']) && isset($categoryData[0]['parent_id'])) {
                    $customData['parent_id'] = $categoryData[0]['parent_id'];
                }
                if (!isset($customData['category_name']) && !empty($categoryData[0]['name'])) {
                    $customData['category_name'] = $categoryData[0]['name'];
                }
            }
        }

        if (!empty($serviceId)) {
            $serviceData = $db->table('services')->select('id, slug, title')->where('id', $serviceId)->get()->getResultArray();
            if (!empty($serviceData)) {
                if (!isset($customData['service_slug']) || $customData['service_slug'] === '') {
                    $customData['service_slug'] = $serviceData[0]['slug'] ?? '';
                }
                if (!empty($customData['service_slug'])) {
                    $customData['web_click_type'] = 'service-details';
                }
                if (!isset($customData['service_id'])) {
                    $customData['service_id'] = $serviceId;
                }
            }
        }

        if (!empty($serviceId) && (empty($providerId) || empty($categoryId))) {
            $serviceDetails = $db->table('services')->select('user_id, category_id')->where('id', $serviceId)->get()->getResultArray();
            if (!empty($serviceDetails)) {
                if (empty($providerId) && !empty($serviceDetails[0]['user_id'])) {
                    $spId = $serviceDetails[0]['user_id'];
                    $providerData = $db->table('partner_details')->select('partner_id, company_name, slug')->where('partner_id', $spId)->get()->getResultArray();
                    if (!empty($providerData)) {
                        if (!isset($customData['provider_slug']) || $customData['provider_slug'] === '') {
                            $customData['provider_slug'] = $providerData[0]['slug'] ?? '';
                        }
                        if (!empty($customData['provider_slug'])) {
                            $customData['web_click_type'] = 'provider-details';
                        }
                        $defaultLang = get_default_language();
                        $translated = $db->table('translated_partner_details')->select('company_name')->where('partner_id', $spId)->where('language_code', $defaultLang)->get()->getResultArray();
                        $customData['provider_name_default'] = (!empty($translated) && !empty($translated[0]['company_name'])) ? $translated[0]['company_name'] : ($providerData[0]['company_name'] ?? '');
                        if (!isset($customData['provider_id'])) {
                            $customData['provider_id'] = $spId;
                        }
                    }
                }
                if (empty($categoryId) && !empty($serviceDetails[0]['category_id'])) {
                    $scId = $serviceDetails[0]['category_id'];
                    $categoryData = $db->table('categories')->select('id, name, parent_id, slug')->where('id', $scId)->get()->getResultArray();
                    if (!empty($categoryData)) {
                        if (!isset($customData['category_slug']) || $customData['category_slug'] === '') {
                            $customData['category_slug'] = $categoryData[0]['slug'] ?? '';
                        }
                        if (!empty($customData['category_slug'])) {
                            $customData['web_click_type'] = 'category';
                        }
                        $parentId = $categoryData[0]['parent_id'] ?? null;
                        if (!empty($parentId) && $parentId != '0') {
                            $parentSlugs = $this->getAllParentCategorySlugs($db, (int) $parentId);
                            if (!empty($parentSlugs)) {
                                $customData['parent_category_slugs'] = json_encode(array_values($parentSlugs), JSON_UNESCAPED_SLASHES);
                            }
                        }
                        if (!isset($customData['category_id'])) {
                            $customData['category_id'] = $scId;
                        }
                    }
                }
            }
        }

        if (!empty($blogId)) {
            $blogData = $db->table('blogs')->select('id, slug, title')->where('id', $blogId)->get()->getResultArray();
            if (!empty($blogData)) {
                if (!isset($customData['blog_slug']) || $customData['blog_slug'] === '') {
                    $customData['blog_slug'] = $blogData[0]['slug'] ?? '';
                }
                if (!empty($customData['blog_slug'])) {
                    $customData['web_click_type'] = 'blog-details';
                }
                if (!isset($customData['blog_id'])) {
                    $customData['blog_id'] = $blogId;
                }
            }
        }

        if (isset($context['user_id']) && !isset($customData['user_id'])) {
            $customData['user_id'] = $context['user_id'];
        }
        if (isset($context['provider_id']) && !isset($customData['provider_id'])) {
            $customData['provider_id'] = $context['provider_id'];
        }
        if (isset($context['order_id']) && $context['order_id'] !== null && !isset($customData['order_id'])) {
            $customData['order_id'] = $context['order_id'];
        }

        $chatMetadataFields = ['bookingId', 'bookingStatus', 'companyName', 'translatedName', 'receiverType', 'providerId', 'profile', 'senderId', 'booking_id', 'chat_message', 'chat_user'];
        foreach ($chatMetadataFields as $field) {
            if (isset($context[$field]) && $context[$field] !== null && !isset($customData[$field])) {
                $customData[$field] = $context[$field];
            }
        }

        return $customData;
    }

    private function writeLog(string $level, string $message): void
    {
        try {
            $logger = service('logger');
            if ($logger !== null) {
                $logger->log($level, $message);
                return;
            }
        } catch (\Throwable $e) {
            // fallback
        }
        $logPath = defined('WRITEPATH') && WRITEPATH ? rtrim(WRITEPATH, '/') . '/logs/' : (defined('ROOTPATH') ? rtrim(ROOTPATH, '/') . '/writable/logs/' : getcwd() . '/writable/logs/');
        if (!is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
        }
        @file_put_contents($logPath . 'log-' . date('Y-m-d') . '.log', date('Y-m-d H:i:s') . ' --> ' . $level . ': ' . $message . "\n", FILE_APPEND);
    }

    /**
     * Store FCM notifications in the notifications table (one row per user).
     * Used by sendNotification when event is not new_message / admin_custom_notification / maintenance_mode.
     */
    private function storeNotificationsInDatabase(array $tokens, string $eventType, array $context, array $options): void
    {
        try {
            $db = \Config\Database::connect();

            $userIds = [];
            if (!empty($options['user_ids']) && is_array($options['user_ids'])) {
                $userIds = array_values(array_unique(array_filter(array_map('intval', $options['user_ids']))));
            }
            if (empty($userIds) && !empty($tokens)) {
                $users = $db->table('users_fcm_ids uf')
                    ->select('uf.user_id')
                    ->whereIn('uf.fcm_id', $tokens)
                    ->where('uf.status', 1)
                    ->get()
                    ->getResultArray();
                $userIds = array_values(array_unique(array_filter(array_column($users, 'user_id'))));
            }

            if (empty($userIds)) {
                $this->writeLog('warning', "[STORE_NOTIFICATIONS] No valid user_ids found for event: {$eventType}");
                return;
            }

            $orderId = $context['order_id'] ?? ($context['booking_id'] ?? null);
            $typeId = $context['type_id'] ?? null;
            $baseData = [
                'type' => $eventType,
                'event_type' => 'system_generated',
                'is_readed' => 0,
            ];
            if (!empty($orderId)) {
                $baseData['order_id'] = (string) $orderId;
            } elseif (!empty($typeId)) {
                $baseData['type_id'] = (string) $typeId;
            }
            if (!empty($context['custom_job_request_id'])) {
                $baseData['custom_job_request_id'] = (string) $context['custom_job_request_id'];
            }
            if (!empty($context['bidder_id'])) {
                $baseData['bidder_id'] = (string) $context['bidder_id'];
            } elseif (!empty($context['provider_id']) && !empty($context['custom_job_request_id'])) {
                $baseData['bidder_id'] = (string) $context['provider_id'];
            }
            if (isset($context['bid_status']) && $context['bid_status'] !== '') {
                $baseData['bid_status'] = (string) $context['bid_status'];
            }
            if (!empty($context)) {
                $baseData['context_data'] = json_encode($context);
            }

            foreach ($userIds as $uid) {
                $db->table('notifications')->insert(array_merge($baseData, ['user_id' => (string) $uid]));
            }

            $this->writeLog('info', "[STORE_NOTIFICATIONS] Stored " . count($userIds) . " notifications for {$eventType}");
        } catch (\Throwable $th) {
            $this->writeLog('error', "[STORE_NOTIFICATIONS] Exception: {$th->getMessage()} | Trace: {$th->getTraceAsString()}");
        }
    }
}
