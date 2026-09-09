<?php

namespace App\Libraries;

/**
 * StripeMoney
 *
 * Centralized helper for Stripe amount conversions.
 * Stripe expects amounts in the smallest currency unit (minor unit).
 * Example: USD 10.50 => 1050. But some currencies are "zero-decimal"
 * (no minor units), so they must NOT be multiplied by 100 (e.g. JPY 100 => 100).
 *
 * Keep all Stripe currency conversion rules here so controllers/webhooks
 * don't re-implement them in multiple places.
 */
class StripeMoney
{
    /**
     * Zero-decimal currencies as per Stripe documentation.
     * Note: Stripe expects amounts for these currencies in whole units already.
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public static function isZeroDecimalCurrency(string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true);
    }

    /**
     * Convert major units (e.g. 10.50 USD) into Stripe minor units (e.g. 1050).
     *
     * We intentionally round to the nearest integer to avoid float precision
     * issues when inputs come as strings like "10.1".
     */
    public static function toMinorUnits($amount, string $currency): int
    {
        $amountFloat = (float) $amount;

        if (self::isZeroDecimalCurrency($currency)) {
            return (int) round($amountFloat);
        }

        return (int) round($amountFloat * 100);
    }

    /**
     * Convert Stripe minor units (e.g. 1050) into major units (e.g. 10.50).
     */
    public static function fromMinorUnits($amount, string $currency): float
    {
        $amountFloat = (float) $amount;

        if (self::isZeroDecimalCurrency($currency)) {
            return $amountFloat;
        }

        return $amountFloat / 100;
    }
}

