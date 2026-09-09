<?php
defined('BASEPATH') or exit('No direct script access allowed');
/* 
| ------------------------------------------------------------------- 
|  Stripe API Configuration 
| ------------------------------------------------------------------- 
| 
| You will get the API keys from Developers panel of the Stripe account 
| Login to Stripe account (https://dashboard.stripe.com/) 
| and navigate to the Developers >> API keys page 
|
|  stripe_api_key            string   Your Stripe API Secret key.
|  stripe_publishable_key    string   Your Stripe API Publishable key.
|  stripe_currency           string   Currency code.
|
| Keys are stored in the `settings` table under `payment_gateways_settings`
| to avoid hard-coding secrets. We keep environment fallbacks so deployments
| without DB access can still run safely in emergencies.
*/

// Safely pull Stripe credentials from the settings table with fallbacks.
$stripe_settings = [];

// Load helper only if it is not already loaded to prevent redeclare errors.
if (!function_exists('get_settings')) {
    helper('function');
}

// Wrap DB access so config loading never breaks the app if settings are absent.
try {
    if (function_exists('get_settings')) {
        $stripe_settings = get_settings('payment_gateways_settings', true);
        // Ensure we always get an array even if the DB row is missing.
        if (!is_array($stripe_settings)) {
            $stripe_settings = [];
        }
    }
} catch (\Throwable $th) {
    // Swallow exceptions during config bootstrap; fall back to env/defaults.
    $stripe_settings = [];
}

$config['stripe_api_key']         = $stripe_settings['stripe_secret_key'] ?? env('STRIPE_SECRET_KEY') ?? '';
$config['stripe_publishable_key'] = $stripe_settings['stripe_publishable_key'] ?? env('STRIPE_PUBLISHABLE_KEY') ?? '';
$config['stripe_currency']        = strtolower($stripe_settings['stripe_currency'] ?? env('STRIPE_CURRENCY') ?? 'usd');
