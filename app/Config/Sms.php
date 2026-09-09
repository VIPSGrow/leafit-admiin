<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * SMS configuration.
 *
 * Defines gateways that use verified template IDs instead of raw message body.
 * When adding a new template-based gateway (e.g. MSG91), add the provider class,
 * add to SmsProviderFactory, then add the entry here.
 */
class Sms extends BaseConfig
{
    /**
     * Gateways that require sending template ID instead of full message.
     * Key = gateway identifier (e.g. 'fast2sms'), must match SmsProviderFactory.
     * Value = human-readable label for the UI dropdown.
     *
     * @var array<string, string>
     */
    public array $templateBasedGateways = [
        'fast2sms' => 'Fast2SMS',
        'msg91'    => 'MSG91',
      	'combirds' => 'Combirds',
    ];
}
