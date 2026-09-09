<?php

namespace App\Notification\Contracts;

/**
 * Contract for SMS notification providers.
 *
 * Each implementation handles a specific SMS gateway (Twilio, Vonage, etc.).
 * The provider is responsible ONLY for sending a single SMS to one phone number
 * with the given message. Recipient resolution, template resolution, and
 * preference checks remain the responsibility of the caller (e.g. NotificationService).
 *
 * To add a new SMS provider (e.g. Vonage, Fast2SMS):
 *   1. Create a class in Providers/Sms/ implementing this interface.
 *   2. Add a case in SmsProviderFactory::make() and a private builder method.
 *   3. No changes to existing providers or to NotificationService.
 */
interface SmsProviderInterface
{
    /**
     * Send one SMS to the given phone number.
     *
     * @param  string $phone   Recipient phone number (E.164 or provider-accepted format).
     * @param  string $message SMS body. Caller should clean HTML / normalize content.
     *                         For template-based gateways (e.g. Fast2SMS DLT), may be empty
     *                         when using options.
     * @param  array  $options Optional. For gateways that use template/message ID instead of
     *                         body: 'template_id' (string) and 'variables' (array of values
     *                         in the order expected by the gateway). If present, provider
     *                         may ignore $message and use template ID + variables.
     * @return array Standardised result:
     *   ['success' => bool, 'message' => string, 'data' => array|null]
     *   'data' is optional (e.g. raw API response for logging).
     */
    public function send(string $phone, string $message, array $options = []): array;

    /**
     * Return a human-readable provider name (e.g. "twilio", "vonage").
     *
     * Used for logging and result tagging.
     *
     * @return string
     */
    public function getName(): string;
}
