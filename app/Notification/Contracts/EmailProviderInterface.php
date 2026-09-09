<?php

namespace App\Notification\Contracts;

/**
 * Contract for email notification providers.
 *
 * Each implementation handles a specific email transport (SMTP via CodeIgniter,
 * SendGrid, Mailgun, etc.). The provider is responsible ONLY for:
 *   - Sending a single email with the given to, subject, body, and options
 *   - Applying provider-specific config (from address, auth, etc.)
 *
 * Recipient resolution, template resolution, and preference checks remain
 * the responsibility of the caller (e.g. NotificationService).
 *
 * To add a new email provider:
 *   1. Create a class in Providers/Email/ implementing this interface.
 *   2. Add a case in EmailProviderFactory::make() and a private builder method.
 *   3. No changes to existing providers or to NotificationService.
 */
interface EmailProviderInterface
{
    /**
     * Send one email.
     *
     * @param  string $to      Recipient email address.
     * @param  string $subject Email subject.
     * @param  string $body    Email body (HTML). Caller should normalize line breaks etc.
     * @param  array  $options Optional keys:
     *   - 'bcc'        (array) BCC addresses.
     *   - 'cc'         (array) CC addresses.
     *   - 'attachments' (array) List of ['path' => string, 'cid' => string] for inline images.
     *   - 'from'       (string) Override from address (if not set in provider config).
     *   - 'from_name'  (string) Override from name (if not set in provider config).
     * @return array Standardised result:
     *   ['success' => bool, 'message' => string, 'error' => string|null]
     */
    public function send(string $to, string $subject, string $body, array $options = []): array;

    /**
     * Return a human-readable provider name (e.g. "smtp", "sendgrid").
     *
     * Used for logging and result tagging.
     *
     * @return string
     */
    public function getName(): string;
}
