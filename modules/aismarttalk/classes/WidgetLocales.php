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
 * Canonical list of locales the embedded chatbot widget can render.
 *
 * Single source of truth for the back-office language picker and for
 * validating the merchant's selection. Mirrors chatbot-front's `allowedLocales`
 * (src/shared/utils/languageDetection.ts) and the display metadata of the
 * platform's `languageConfig` (src/utils/languageConfig.ts). Keep the three in
 * sync when a new widget language ships.
 */
class WidgetLocales
{
    /**
     * code => endonym display name (flag is added separately for the picker).
     *
     * @var array<string, array{flag: string, name: string}>
     */
    private const LOCALES = [
        'fr' => ['flag' => '🇫🇷', 'name' => 'Français'],
        'en' => ['flag' => '🇬🇧', 'name' => 'English'],
        'de' => ['flag' => '🇩🇪', 'name' => 'Deutsch'],
        'lu' => ['flag' => '🇱🇺', 'name' => 'Lëtzebuergesch'],
        'it' => ['flag' => '🇮🇹', 'name' => 'Italiano'],
        'es' => ['flag' => '🇪🇸', 'name' => 'Español'],
        'tr' => ['flag' => '🇹🇷', 'name' => 'Türkçe'],
        'th' => ['flag' => '🇹🇭', 'name' => 'ไทย'],
        'ge' => ['flag' => '🇬🇪', 'name' => 'ქართული'],
        'da' => ['flag' => '🇩🇰', 'name' => 'Dansk'],
        'nl' => ['flag' => '🇳🇱', 'name' => 'Nederlands'],
        'no' => ['flag' => '🇳🇴', 'name' => 'Norsk'],
        'pl' => ['flag' => '🇵🇱', 'name' => 'Polski'],
        'pt' => ['flag' => '🇵🇹', 'name' => 'Português'],
        'ro' => ['flag' => '🇷🇴', 'name' => 'Română'],
        'sv' => ['flag' => '🇸🇪', 'name' => 'Svenska'],
        'sq' => ['flag' => '🇦🇱', 'name' => 'Shqip'],
        'bg' => ['flag' => '🇧🇬', 'name' => 'Български'],
        'hr' => ['flag' => '🇭🇷', 'name' => 'Hrvatski'],
        'cs' => ['flag' => '🇨🇿', 'name' => 'Čeština'],
        'sk' => ['flag' => '🇸🇰', 'name' => 'Slovenčina'],
        'sl' => ['flag' => '🇸🇮', 'name' => 'Slovenščina'],
        'et' => ['flag' => '🇪🇪', 'name' => 'Eesti'],
        'lt' => ['flag' => '🇱🇹', 'name' => 'Lietuvių'],
        'lv' => ['flag' => '🇱🇻', 'name' => 'Latviešu'],
        'fi' => ['flag' => '🇫🇮', 'name' => 'Suomi'],
        'sr' => ['flag' => '🇷🇸', 'name' => 'Српски'],
        'mk' => ['flag' => '🇲🇰', 'name' => 'Македонски'],
        'is' => ['flag' => '🇮🇸', 'name' => 'Íslenska'],
        'ga' => ['flag' => '🇮🇪', 'name' => 'Gaeilge'],
        'hu' => ['flag' => '🇭🇺', 'name' => 'Magyar'],
        'ary' => ['flag' => '🇲🇦', 'name' => 'العربية المغربية'],
        'ara' => ['flag' => '🇸🇦', 'name' => 'العربية'],
        'aeb' => ['flag' => '🇹🇳', 'name' => 'العربية التونسية'],
        'ayl' => ['flag' => '🇱🇾', 'name' => 'العربية الليبية'],
        'vi' => ['flag' => '🇻🇳', 'name' => 'Tiếng Việt'],
        'ms' => ['flag' => '🇲🇾', 'name' => 'Bahasa Melayu'],
    ];

    /**
     * Display metadata for the back-office picker.
     *
     * @return array<int, array{code: string, flag: string, name: string}>
     */
    public static function all(): array
    {
        $rows = [];
        foreach (self::LOCALES as $code => $meta) {
            $rows[] = ['code' => $code, 'flag' => $meta['flag'], 'name' => $meta['name']];
        }

        return $rows;
    }

    /**
     * Whether a locale code is renderable by the widget.
     */
    public static function isValid(string $code): bool
    {
        return isset(self::LOCALES[$code]);
    }

    /**
     * Keep only valid, unique locale codes, preserving the input order.
     *
     * @param array<int, mixed> $codes
     * @return array<int, string>
     */
    public static function sanitize(array $codes): array
    {
        $clean = [];
        foreach ($codes as $code) {
            $code = (string) $code;
            if (self::isValid($code) && !in_array($code, $clean, true)) {
                $clean[] = $code;
            }
        }

        return $clean;
    }
}
