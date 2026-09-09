<?php

namespace App\Notification\Factories;

use App\Models\Settings;
use App\Notification\Contracts\SmsProviderInterface;
use App\Notification\Providers\Sms\Fast2SmsProvider;
use App\Notification\Providers\Sms\Msg91Provider;
use App\Notification\Providers\Sms\TwilioSmsProvider;
use App\Notification\Providers\Sms\TwoFactorProvider;
use App\Notification\Providers\Sms\CombirdsSmsProvider;

/**
 * Factory for creating SMS notification providers.
 *
 * Reads SMS gateway configuration from the database (sms_gateway_setting) and returns
 * a fully configured SmsProviderInterface implementation based on the active gateway.
 *
 * Active gateway rules:
 * - Exactly one gateway should be \"active\" at a time.
 * - We first look for a provider whose status flag is 1:
 *     - current schema: twilio.twilio_status, fast2sms.fast2sms_status, msg91.msg91_status, 2factor.2factor_status, etc.
 *     - future schema:  twilio.status, fast2sms.status, msg91.status, 2factor.status (generic \"status\" key).
 * - If multiple are marked active, we prefer current_sms_gateway (if set).
 * - If none are active, we fall back to current_sms_gateway or a sensible default.
 *
 * Currently supports:
 *   - 'twilio' (default) → TwilioSmsProvider
 *   - 'fast2sms' → Fast2SmsProvider (DLT GET API)
 *   - 'msg91' → Msg91Provider (DLT GET API)
 *   - '2factor' → TwoFactorProvider (raw message, no template ID)
 *
 * To add a new SMS provider (e.g. 2Factor):
 *   1. Create a class in Providers/Sms/ implementing SmsProviderInterface.
 *   2. Add a case in make() and a private builder method (e.g. makeTwoFactor()).
 *   3. No changes to existing providers or to NotificationService.
 */
class SmsProviderFactory
{
    /**
     * Build and return the configured SMS provider.
     *
     * Reads sms_gateway_setting from the database and selects the provider using
     * the active-gateway rules (status flags, then current_sms_gateway). Passes
     * the full gateway config so each builder can take the slice it needs.
     *
     * @param  string|null $provider Optional override. If null, read from settings.
     * @return SmsProviderInterface
     *
     * @throws \RuntimeException When the requested provider is unknown.
     */
    public function make(?string $provider = null): SmsProviderInterface
    {
        $gatewaySettings = $this->getGatewaySettings();
      	log_message('info', '[SMS Provider] gateway setting - '.json_encode($gatewaySettings));
        // If caller forces a provider, always respect that.
        $resolvedGateway = $provider ?? $this->detectActiveGateway($gatewaySettings);
      	log_message('info', '[Resolve] resolve - '.$resolvedGateway);

        return match (strtolower((string) $resolvedGateway)) {
            'twilio'    => $this->makeTwilio($gatewaySettings),
            'fast2sms'  => $this->makeFast2Sms($gatewaySettings),
            'msg91'     => $this->makeMsg91($gatewaySettings),
            '2factor'   => $this->makeTwoFactor($gatewaySettings),
          	'combirds'	=> $this->makeCombirds($gatewaySettings),
            default     => throw new \RuntimeException("Unknown SMS gateway: {$resolvedGateway}"),
        };
    }

  
  	private function makeCombirds(array $gatewaySettings): CombirdsSmsProvider {
        $twofactor = $gatewaySettings['combirds'] ?? [];
        return new CombirdsSmsProvider([
            'api_key'   => $twofactor['combirds_api_key'] ?? '',
            'sender_id' => $twofactor['combirds_sender_id'] ?? '',
        ]);
    }
  
  
    // ──────────────────────────────────────────────
    //  Provider-specific builders
    // ──────────────────────────────────────────────

    /**
     * Create TwilioSmsProvider with config from sms_gateway_setting.twilio.
     *
     * @param array $gatewaySettings Full sms_gateway_setting from database
     * @return TwilioSmsProvider
     */
    private function makeTwilio(array $gatewaySettings): TwilioSmsProvider
    {
        $twilio = $gatewaySettings['twilio'] ?? [];
        return new TwilioSmsProvider([
            'account_sid' => $twilio['twilio_account_sid'] ?? '',
            'auth_token'  => $twilio['twilio_auth_token'] ?? '',
            'from'        => $twilio['twilio_from'] ?? '',
        ]);
    }

    /**
     * Create Fast2SmsProvider with config from sms_gateway_setting.fast2sms.
     *
     * @param array $gatewaySettings Full sms_gateway_setting from database
     * @return Fast2SmsProvider
     */
    private function makeFast2Sms(array $gatewaySettings): Fast2SmsProvider
    {
        $fast2sms = $gatewaySettings['fast2sms'] ?? [];
        return new Fast2SmsProvider([
            'api_key'   => $fast2sms['fast2sms_api_key'] ?? '',
            'sender_id' => $fast2sms['fast2sms_sender_id'] ?? '',
        ]);
    }

    /**
     * Create Msg91Provider with config from sms_gateway_setting.msg91.
     *
     * @param array $gatewaySettings Full sms_gateway_setting from database
     * @return Msg91Provider
     */
    private function makeMsg91(array $gatewaySettings): Msg91Provider
    {
        $msg91 = $gatewaySettings['msg91'] ?? [];
        return new Msg91Provider([
            'authkey' => $msg91['msg91_authkey'] ?? '',
        ]);
    }

    /**
     * Create TwoFactorProvider with config from sms_gateway_setting.2factor.
     *
     * @param array $gatewaySettings Full sms_gateway_setting from database
     * @return TwoFactorProvider
     */
    private function makeTwoFactor(array $gatewaySettings): TwoFactorProvider
    {
        $twofactor = $gatewaySettings['2factor'] ?? [];
        return new TwoFactorProvider([
            'api_key'   => $twofactor['2factor_api_key'] ?? '',
            'sender_id' => $twofactor['2factor_sender_id'] ?? '',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Read SMS gateway config from the settings table.
     *
     * @return array Decoded sms_gateway_setting value, or empty array
     */
    private function getGatewaySettings(): array
    {
        $model  = new Settings();
        $record = $model->where('variable', 'sms_gateway_setting')->first();

        if (empty($record['value'])) {
            return [];
        }

        return json_decode($record['value'], true) ?? [];
    }

    /**
     * Detect which gateway should be used as the \"active\" one.
     *
     * Priority:
     *  1. If exactly one provider has status = 1, use that.
     *     - Supports both current keys (twilio_status / fast2sms_status / msg91_status / 2factor_status) and
     *       future generic \"status\" key to be forward compatible.
     *  2. If multiple are active, prefer current_sms_gateway if it is in that set.
     *  3. If none are active, fall back to current_sms_gateway.
     *  4. If still unknown, default to 'twilio'.
     *
     * This keeps NotificationService independent from how many gateways exist
     * and how their internal status flags are named.
     *
     * @param  array $gatewaySettings Full sms_gateway_setting
     * @return string Resolved gateway identifier (e.g. 'twilio', 'fast2sms', 'msg91', '2factor')
     */
    private function detectActiveGateway(array $gatewaySettings): string
    {
        // Known gateway keys – extend this list when adding new providers.
        $knownGateways = ['twilio', 'fast2sms', 'msg91', '2factor'];

        $activeGateways = [];

        foreach ($knownGateways as $key) {
            if (!isset($gatewaySettings[$key]) || !is_array($gatewaySettings[$key])) {
                continue;
            }

            $config = $gatewaySettings[$key];

            // Support both current names (*_status) and future generic 'status'.
            $rawStatus = $config['status']
                ?? $config["{$key}_status"]
                ?? null;

            // Consider '1', 1, true as active.
            if ($rawStatus === '1' || $rawStatus === 1 || $rawStatus === true) {
                $activeGateways[] = $key;
            }
        }

        // If exactly one gateway is active via status flags, use it.
        if (count($activeGateways) === 1) {
            return $activeGateways[0];
        }

        // If multiple are active, prefer current_sms_gateway if it is among them.
        $current = isset($gatewaySettings['current_sms_gateway'])
            ? strtolower((string) $gatewaySettings['current_sms_gateway'])
            : null;

        if (!empty($activeGateways) && $current && in_array($current, $activeGateways, true)) {
            return $current;
        }

        // If no (or multiple) active flags, fall back to current_sms_gateway if set.
        if ($current) {
            return $current;
        }

        // Final fallback: default to Twilio so existing behaviour is stable.
        return 'twilio';
    }
}
