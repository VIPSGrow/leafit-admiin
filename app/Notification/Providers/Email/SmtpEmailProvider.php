<?php

namespace App\Notification\Providers\Email;

use App\Notification\Contracts\EmailProviderInterface;

/**
 * SMTP email provider using CodeIgniter's Email library.
 *
 * Sends email via the configured SMTP transport (Config\Services::email()).
 * Supports inline image attachments with CID for HTML emails.
 *
 * Config (injected by EmailProviderFactory):
 *   - from_email  (string) From address (e.g. SMTP username).
 *   - from_name   (string) From display name (e.g. company title).
 *
 * To add another provider (e.g. SendGrid): create a new class implementing
 * EmailProviderInterface, add it in EmailProviderFactory::make(); this class
 * is not modified.
 */
class SmtpEmailProvider implements EmailProviderInterface
{
    /** From email address (set from config in constructor). */
    private string $fromEmail;

    /** From display name (set from config in constructor). */
    private string $fromName;

    /**
     * Create a new SmtpEmailProvider.
     *
     * @param array $config Required keys: from_email, from_name.
     */
    public function __construct(array $config)
    {
        $this->fromEmail = (string) ($config['from_email'] ?? '');
        $this->fromName  = (string) ($config['from_name'] ?? 'eDemand');
    }

    /**
     * {@inheritDoc}
     * Sends one email via CodeIgniter Email service with optional BCC, CC, and inline attachments.
     * Normalizes body (line breaks) and filters invalid attachments internally.
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        try {
            // Normalize email body (escaped newlines -> HTML <br>) before sending
            $body = $this->normalizeEmailContent(htmlspecialchars_decode($body));

            // Allow per-call override of from address/name
            $fromEmail = $options['from'] ?? $this->fromEmail;
            $fromName  = $options['from_name'] ?? $this->fromName;

            $emailService = \Config\Services::email();

            $emailService->setTo($to);
            $emailService->setFrom($fromEmail, $fromName);
            $emailService->setSubject($subject);
            $emailService->setMessage($body);
            $emailService->setMailType('html');

            $bcc = $options['bcc'] ?? [];
            $cc  = $options['cc'] ?? [];
            if (!empty($bcc)) {
                $emailService->setBCC($bcc);
            }
            if (!empty($cc)) {
                $emailService->setCC($cc);
            }

            // Inline image attachments: filter to valid files only, then attach
            $rawAttachments = $options['attachments'] ?? [];
            $attachments = $this->filterValidImageAttachments($rawAttachments);
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (empty($attachment['path']) || empty($attachment['cid'])) {
                        continue;
                    }
                    $filePath     = $attachment['path'];
                    $desiredCid   = $attachment['cid'];
                    if (!is_file($filePath) || !is_readable($filePath)) {
                        log_message('error', '[SmtpEmailProvider] Skip invalid attachment: ' . $filePath);
                        continue;
                    }

                    $attachResult = $emailService->attach($filePath, 'inline');
                    if ($attachResult === false) {
                        log_message('error', '[SmtpEmailProvider] Failed to attach: ' . $filePath);
                        continue;
                    }

                    // Set CID and multipart so template can use cid:company_logo etc.
                    $this->setAttachmentCid($emailService, $filePath, $desiredCid);
                }
            }

            $result = $emailService->send(false);

            if (!$result) {
                $error = $emailService->printDebugger(['headers']);
                log_message('error', '[SmtpEmailProvider] Send failed: ' . $error);
                return [
                    'success' => false,
                    'message' => 'Failed to send email',
                    'error'   => $error,
                ];
            }

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'error'   => null,
            ];
        } catch (\Throwable $th) {
            log_message('error', '[SmtpEmailProvider] Exception: ' . $th->getMessage());
            log_message('error', '[SmtpEmailProvider] Trace: ' . $th->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $th->getMessage(),
                'error'   => $th->getMessage(),
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'smtp';
    }

    /**
     * Normalize email content: fix escaped line break characters for HTML display.
     *
     * Converts various forms of escaped newlines (e.g. \\r\\n, \r\n, literal \r\n)
     * from database/templates into proper HTML <br> tags. Keeps email logic in the provider.
     *
     * @param string $content Raw email content
     * @return string Content with proper HTML line breaks
     */
    private function normalizeEmailContent(string $content): string
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/(?:\\\\)+r(?:\\\\)+n/i', '<br>', $content);
        $content = preg_replace('/\\\\r\\\\n|\\\\r|\\\\n/i', '<br>', $content);
        $content = preg_replace('/\\\\+[rn]/i', '<br>', $content);
        $content = str_replace(["\r\n", "\r", "\n"], '<br>', $content);
        $content = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br>', $content);
        $content = str_replace(['\\r\\n', '\\r', '\\n'], '<br>', $content);
        return $content;
    }

    /**
     * Keep only attachments that exist, are readable, and have size > 0.
     * Prevents empty or broken attachment parts in the sent email.
     *
     * @param array|null $imageAttachments Raw list or single assoc with 'path' and 'cid'
     * @return array List of valid attachments for inline use
     */
    private function filterValidImageAttachments(?array $imageAttachments): array
    {
        if (empty($imageAttachments)) {
            return [];
        }
        if (isset($imageAttachments['path'], $imageAttachments['cid'])) {
            $imageAttachments = [$imageAttachments];
        }
        $valid = [];
        foreach ($imageAttachments as $attachment) {
            if (!isset($attachment['path'], $attachment['cid']) || $attachment['path'] === '') {
                continue;
            }
            $path = $attachment['path'];
            if (!is_file($path) || !is_readable($path) || @filesize($path) === 0) {
                continue;
            }
            $valid[] = $attachment;
        }
        return $valid;
    }

    /**
     * Set Content-ID and multipart type on the last-added attachment for inline images.
     *
     * CodeIgniter does not expose CID directly; we use reflection to set cid and disposition
     * so that HTML can reference cid:company_logo (or other cid) correctly.
     *
     * @param \CodeIgniter\Email\Email $emailService CodeIgniter email instance
     * @param string                   $filePath     Path of the file we just attached
     * @param string                   $desiredCid   CID value (e.g. 'company_logo')
     */
    private function setAttachmentCid($emailService, string $filePath, string $desiredCid): void
    {
        try {
            $reflection = new \ReflectionClass($emailService);
            $attachmentsProperty = $reflection->getProperty('attachments');
            $attachmentsProperty->setAccessible(true);
            $attachments = $attachmentsProperty->getValue($emailService);

            for ($i = count($attachments) - 1; $i >= 0; $i--) {
                $att = $attachments[$i];
                $matches = false;
                if (isset($att['name'][0]) && $att['name'][0] === $filePath) {
                    $matches = true;
                } elseif (isset($att['name'][1]) && $att['name'][1] === $filePath) {
                    $matches = true;
                } elseif (isset($att['name'][0]) && realpath($att['name'][0]) === realpath($filePath)) {
                    $matches = true;
                }
                if ($matches) {
                    $attachments[$i]['cid']         = $desiredCid;
                    $attachments[$i]['multipart']  = 'related';
                    $attachments[$i]['disposition'] = 'inline';
                    $attachmentsProperty->setValue($emailService, $attachments);
                    return;
                }
            }

            // Fallback: set CID by filename
            $filename = basename($filePath);
            if (method_exists($emailService, 'setAttachmentCID')) {
                $emailService->setAttachmentCID($filename);
            }
        } catch (\ReflectionException $e) {
            $filename = basename($filePath);
            if (method_exists($emailService, 'setAttachmentCID')) {
                $emailService->setAttachmentCID($filename);
            }
        }
    }
}
