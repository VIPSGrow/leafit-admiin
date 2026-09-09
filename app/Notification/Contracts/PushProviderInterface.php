<?php

namespace App\Notification\Contracts;

/**
 * Contract for push notification providers.
 *
 * Each implementation handles a specific push delivery service (FCM, APNs, etc.).
 * The provider is responsible ONLY for:
 *   - Authenticating with the push service
 *   - Building the correct payload per platform (Android, iOS, Web)
 *   - Sending notifications in bulk (campaigns) using efficient parallel I/O
 *   - Reporting per-token success/failure and identifying invalid tokens
 *
 * The provider does NOT resolve recipients, read templates, or access
 * domain-specific database tables. Those are the orchestrator's job.
 *
 * Optional high-level entry point: sendNotification() resolves tokens, templates,
 * enriches payload, sends, cleans invalid tokens, and stores to DB. FcmProvider
 * implements it; other providers may throw or return a "not supported" result.
 */
interface PushProviderInterface
{
    /**
     * High-level: send push notifications by event type (resolve tokens, templates, enrich, send, store in DB).
     *
     * Single entry point for the full FCM flow. Implementations (e.g. FcmProvider) handle token resolution,
     * template resolution per language, payload enrichment, low-level send(), invalid-token cleanup,
     * and persisting notifications to the database when appropriate.
     *
     * @param  string $eventType  Event type (e.g. booking_confirmed)
     * @param  array  $recipients Recipients (may contain fcm_tokens_by_language, fcm_tokens, user_id; options used to resolve if missing)
     * @param  array  $context    Context for templates and enrichment
     * @param  array  $options    Options (user_ids, platforms, user_groups, send_to_all, type, data, etc.)
     * @return array Simplified result for API: ['error' => bool, 'message' => string]. Detailed data is logged by the provider.
     */
    public function sendNotification(string $eventType, array $recipients, array $context, array $options = []): array;

    /**
     * Send a push notification to one or more device tokens.
     *
     * Implementations MUST handle bulk sending efficiently (e.g. HTTP/2
     * multiplexing, connection pooling, or native batch APIs).
     *
     * @param  array  $tokens   Flat list of device tokens to deliver to.
     * @param  string $title    Notification title (plain text).
     * @param  string $message  Notification body  (plain text).
     * @param  array  $options  Provider-specific options. Common keys:
     *   - 'type'         (string)  Notification category/type for client routing. Default: 'default'.
     *   - 'data'         (array)   Custom key-value data payload sent alongside the notification.
     *   - 'device_info'  (array)   Map of token → platform string ('android'|'ios'|'web'|'admin_panel'|'provider_panel').
     *                              When omitted, provider uses a safe default (data-only for all tokens).
     *   - 'batch_size'   (int)     Max tokens per parallel batch. Default is provider-defined.
     *
     * @return array Standardised result:
     *   [
     *     'success'              => bool,   // true when ALL tokens succeeded
     *     'message'              => string,
     *     'success_count'        => int,
     *     'failure_count'        => int,
     *     'invalid_tokens'       => array,  // tokens that are expired / unregistered
     *     'invalid_tokens_count' => int,
     *     'data'                 => array,  // raw per-token responses (provider-specific)
     *   ]
     */
    public function send(array $tokens, string $title, string $message, array $options = []): array;

    /**
     * Remove invalid / expired tokens from persistent storage.
     *
     * Called by the orchestrator after a send() reports invalid tokens.
     * The provider knows which DB table(s) hold the tokens.
     *
     * @param  array $tokens  Tokens to remove or deactivate.
     * @return array Result: ['success' => bool, 'cleaned_count' => int, 'message' => string]
     */
    public function cleanupInvalidTokens(array $tokens): array;

    /**
     * Return a human-readable provider name (e.g. "fcm", "apns").
     *
     * Used for logging and result tagging.
     *
     * @return string
     */
    public function getName(): string;
}
