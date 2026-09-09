<?php

namespace App\Notification\Factories;

use App\Models\Settings;
use App\Notification\Contracts\EmailProviderInterface;
use App\Notification\Providers\Email\SmtpEmailProvider;

/**
 * Factory for creating email notification providers.
 *
 * Reads email configuration from the database (e.g. email_settings) and returns
 * a fully configured EmailProviderInterface implementation.
 *
 * Currently supports:
 *   - 'smtp' (default) → SmtpEmailProvider (CodeIgniter Email / SMTP)
 *
 * To add a new email provider (e.g. SendGrid, Mailgun):
 *   1. Create a class in Providers/Email/ implementing EmailProviderInterface.
 *   2. Add a case in make() and a private builder method below.
 *   3. No changes to existing providers or to NotificationService.
 */
class EmailProviderFactory
{
    /**
     * Build and return the configured email provider.
     *
     * @param  string $provider Provider identifier. Default: 'smtp'.
     * @return EmailProviderInterface
     *
     * @throws \RuntimeException When the requested provider is unknown.
     */
    public function make(string $provider = 'smtp'): EmailProviderInterface
    {
        return match ($provider) {
            'smtp'   => $this->makeSmtp(),
            default  => throw new \RuntimeException("Unknown email provider: {$provider}"),
        };
    }

    // ──────────────────────────────────────────────
    //  Provider-specific builders
    // ──────────────────────────────────────────────

    /**
     * Create SmtpEmailProvider with config from the database.
     *
     * Uses email_settings for from address; general_settings (company_title) for from name.
     * Same source as NotificationService previously used.
     *
     * @return SmtpEmailProvider
     */
    private function makeSmtp(): SmtpEmailProvider
    {
        $settingsModel = new Settings();
        $emailSettings = $this->getJsonSetting($settingsModel, 'email_settings');

        $fromEmail = (string) ($emailSettings['smtpUsername'] ?? '');

        // From name: company title from general_settings (helper may be available in app)
        $fromName = 'eDemand';
        if (function_exists('getTranslatedSetting')) {
            $companyTitle = getTranslatedSetting('general_settings', 'company_title');
            if (!empty($companyTitle)) {
                $fromName = $companyTitle;
            }
        }

        return new SmtpEmailProvider([
            'from_email' => $fromEmail,
            'from_name'  => $fromName,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Read a JSON-encoded setting from the `settings` table.
     *
     * @param  Settings $model    Settings model instance.
     * @param  string   $variable The `variable` column value to look up.
     * @return array  Decoded JSON, or empty array if not found / invalid.
     */
    private function getJsonSetting(Settings $model, string $variable): array
    {
        $record = $model->where('variable', $variable)->first();

        if (empty($record['value'])) {
            return [];
        }

        return json_decode($record['value'], true) ?? [];
    }
}
