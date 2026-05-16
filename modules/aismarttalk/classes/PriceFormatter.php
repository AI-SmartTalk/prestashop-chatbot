<?php
/**
 * Copyright (c) 2026 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2026 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Formats prices according to currency precision (e.g. 3 decimals for LYD, 2 for EUR, 0 for JPY).
 *
 * Prices are always emitted as STRINGS, never as PHP floats. This is critical:
 * `json_encode(12.000)` produces `12`, which silently loses the trailing zeros required
 * by currencies like the Libyan dinar (LYD) where the milli-unit is the unit of trade.
 */
class PriceFormatter
{
    /** Conservative bounds: ISO 4217 currencies use 0-4 decimals; clamp wider for safety. */
    const MIN_DECIMALS = 0;
    const MAX_DECIMALS = 6;

    /** Default precision when nothing else is available. */
    const DEFAULT_DECIMALS = 2;

    /**
     * Resolve the decimal precision of a PrestaShop \Currency object.
     *
     * PrestaShop 1.7.7+ exposes `precision` on \Currency. Older versions don't,
     * so we fall back to the conventional 2-decimal default.
     *
     * @param object|null $currency A \Currency instance (or null when no context).
     * @return int
     */
    public static function decimalsFromCurrency($currency): int
    {
        if ($currency === null) {
            return self::DEFAULT_DECIMALS;
        }

        // isset() returns false for unset properties (PS < 1.7.7), so the fallback kicks in.
        if (isset($currency->precision) && is_numeric($currency->precision)) {
            return self::clampDecimals((int) $currency->precision);
        }

        return self::DEFAULT_DECIMALS;
    }

    /**
     * Format a price as a string with the requested decimal precision.
     * No thousands separator, dot as decimal separator (machine-readable).
     *
     * Accepts int, float, numeric string, or null/false (returns "0.00" formatted).
     *
     * @param mixed $price
     * @param int   $decimals
     * @return string
     */
    public static function format($price, int $decimals): string
    {
        $decimals = self::clampDecimals($decimals);

        if ($price === null || $price === false || $price === '') {
            $price = 0.0;
        }

        if (!is_numeric($price)) {
            $price = 0.0;
        }

        return number_format((float) $price, $decimals, '.', '');
    }

    /**
     * Clamp a decimal count to a safe range.
     *
     * @param int $decimals
     * @return int
     */
    public static function clampDecimals(int $decimals): int
    {
        if ($decimals < self::MIN_DECIMALS) {
            return self::MIN_DECIMALS;
        }
        if ($decimals > self::MAX_DECIMALS) {
            return self::MAX_DECIMALS;
        }

        return $decimals;
    }
}
