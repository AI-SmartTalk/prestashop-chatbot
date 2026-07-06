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
 * Builds the chatbot embed settings by merging API config, local overrides,
 * and auto-login logic. Used by both displayConfigurationPage() and renderChatbot().
 */
class ChatbotSettingsBuilder
{
    /** @var string[] Settings that must not be overridden by embed config */
    private const PROTECTED_SETTINGS = [
        'chatModelId', 'apiUrl', 'wsUrl', 'cdnUrl', 'source', 'userToken', 'lang', 'cart',
    ];

    /**
     * Build the complete chatbot settings array.
     *
     * @param string     $chatModelId Chat model ID
     * @param string     $lang        Language ISO code
     * @param string     $apiUrl      Frontend API URL
     * @param string     $cdnUrl      CDN URL
     * @param string     $wsUrl       WebSocket URL
     * @param array|null $embedConfig Embed config fetched from API
     * @return array The complete chatbot settings
     */
    public static function build(
        string $chatModelId,
        string $lang,
        string $apiUrl,
        string $cdnUrl,
        string $wsUrl,
        ?array $embedConfig
    ): array {
        // Base settings
        $settings = [
            'chatModelId' => $chatModelId,
            'lang' => $lang,
            'apiUrl' => rtrim($apiUrl, '/') . '/api',
            'wsUrl' => $wsUrl,
            'cdnUrl' => $cdnUrl,
            'source' => 'PRESTASHOP',
            // Server-rendered cart capability: drives the "Add to cart" button on
            // product cards. The flag only decides whether the widget SHOWS it; the
            // cart controller re-checks the logged-in customer authoritatively.
            'cart' => self::buildCartSettings(),
        ];

        // Resolve auto-login and inject user token
        $psAutoLogin = \Configuration::get('AI_SMART_TALK_ENABLE_AUTO_LOGIN') ?: '';
        $autoLoginEnabled = self::resolveAutoLogin($psAutoLogin, $embedConfig);

        if ($autoLoginEnabled) {
            $userToken = OAuthTokenHandler::getOrRefreshUserToken();
            if ($userToken) {
                $settings['userToken'] = $userToken;
            }
        }

        // Merge embed config from API (as base defaults)
        $settings = self::mergeEmbedConfig($settings, $embedConfig);

        // Apply PrestaShop customization overrides (priority over API defaults)
        $settings = self::applyCustomizationOverrides($settings);

        // Keep the active language consistent with the offered set.
        $settings = self::reconcileLangWithAllowed($settings);

        return $settings;
    }

    /**
     * Build the cart capability block exposed to the widget.
     *
     * `isCustomerLoggedIn` is server-rendered (authoritative at render time) so the
     * widget knows whether to show the "Add to cart" button. `url` is the same-origin
     * front-controller the parent loader will POST to (the storefront cookie travels
     * with it). `token` is a per-customer CSRF guard verified by the controller.
     *
     * @return array
     */
    public static function buildCartSettings(): array
    {
        $context = \Context::getContext();
        $loggedIn = isset($context->customer) && (int) $context->customer->id > 0 && $context->customer->isLogged();

        $url = '';
        $checkoutUrl = '';
        try {
            $url = $context->link->getModuleLink('aismarttalk', 'cart', [], true);
            $checkoutUrl = $context->link->getPageLink('order', true);
        } catch (\Throwable $e) {
            $url = '';
        }

        return [
            'enabled' => true,
            'isCustomerLoggedIn' => $loggedIn,
            'currency' => isset($context->currency) ? $context->currency->iso_code : '',
            // Single canonical endpoint (action=add|get|update|remove) + the native
            // checkout page. The widget never sees these; only the same-origin loader does.
            'url' => $url,
            'checkoutUrl' => $checkoutUrl,
            // How the loader refreshes the native cart block after a mutation
            // (canonical contract): PrestaShop fires prestashop.emit('updateCart').
            'nativeRefresh' => ['type' => 'prestashop', 'event' => 'updateCart'],
            'token' => $loggedIn ? self::cartTokenForCustomer((int) $context->customer->id) : null,
        ];
    }

    /**
     * Per-customer CSRF token for the cart controller. Derived from the customer id
     * via the shop cookie key, so it cannot be forged by a third-party page and is
     * stable for the customer's session.
     *
     * @param int $idCustomer
     * @return string
     */
    public static function cartTokenForCustomer(int $idCustomer): string
    {
        // NB: Tools::encrypt() was removed in PrestaShop 9 — calling it fatals the
        // whole footer render (and the widget silently vanishes). hash_hmac with the
        // shop cookie key is the version-safe equivalent (same secret, works on 1.7/8/9).
        return hash_hmac('sha256', 'aismarttalk_cart_' . $idCustomer, _COOKIE_KEY_);
    }

    /**
     * Constant-time validation of a cart token against a customer id.
     *
     * @param string $token
     * @param int    $idCustomer
     * @return bool
     */
    public static function isCartTokenValid(string $token, int $idCustomer): bool
    {
        if ($token === '' || $idCustomer <= 0) {
            return false;
        }

        return hash_equals(self::cartTokenForCustomer($idCustomer), $token);
    }

    /**
     * Ensure the active language is one the widget actually offers.
     *
     * The base `lang` is the visitor's PrestaShop language, which may not be in
     * the merchant's allowed set. When that happens the widget would start in a
     * language its switcher can't even select — so fall back to the first
     * allowed language. No-op when no restriction is configured.
     *
     * @param array $settings Current chatbot settings
     * @return array Settings with a consistent `lang`
     */
    public static function reconcileLangWithAllowed(array $settings): array
    {
        $allowed = $settings['allowedLanguages'] ?? null;
        if (is_array($allowed) && count($allowed) > 0
            && (!isset($settings['lang']) || !in_array($settings['lang'], $allowed, true))) {
            $settings['lang'] = $allowed[0];
        }

        return $settings;
    }

    /**
     * Resolve whether auto-login should be enabled.
     * PrestaShop setting takes priority over API embed config.
     *
     * @param string     $psSetting   PrestaShop setting value ('on', 'off', or '')
     * @param array|null $embedConfig Embed config from API
     * @return bool
     */
    public static function resolveAutoLogin(string $psSetting, ?array $embedConfig): bool
    {
        if ($psSetting === 'on') {
            return true;
        }
        if ($psSetting === 'off') {
            return false;
        }

        // Fall back to API embed config (defaults to true if not set)
        return !is_array($embedConfig)
            || !isset($embedConfig['enableAutoLogin'])
            || $embedConfig['enableAutoLogin'] === true;
    }

    /**
     * Merge embed config from API into chatbot settings.
     * Protected settings are never overridden.
     *
     * @param array      $settings    Current settings
     * @param array|null $embedConfig Embed config from API
     * @return array Merged settings
     */
    public static function mergeEmbedConfig(array $settings, ?array $embedConfig): array
    {
        if (!$embedConfig || !is_array($embedConfig)) {
            return $settings;
        }

        foreach ($embedConfig as $key => $value) {
            if (!in_array($key, self::PROTECTED_SETTINGS, true)) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Extract the avatar URL from embed config for admin display.
     *
     * @param array|null $embedConfig Embed config from API
     * @return string Avatar URL or empty string
     */
    public static function getEmbedConfigAvatarUrl(?array $embedConfig): string
    {
        if (is_array($embedConfig) && isset($embedConfig['avatarUrl']) && !empty($embedConfig['avatarUrl'])) {
            return $embedConfig['avatarUrl'];
        }

        return '';
    }

    /**
     * Apply PrestaShop customization overrides to chatbot settings.
     * These settings take priority over API defaults when configured.
     *
     * @param array $settings The base chatbot settings
     * @return array The settings with overrides applied
     */
    public static function applyCustomizationOverrides(array $settings): array
    {
        // Text/select overrides (only if non-empty)
        // Note: avatarUrl is NOT overridden locally — the platform embed config is the source of truth
        $textOverrides = [
            'AI_SMART_TALK_BUTTON_TEXT' => 'buttonText',
            'AI_SMART_TALK_BUTTON_TYPE' => 'buttonType',
            'AI_SMART_TALK_BUTTON_POSITION' => 'position',
            'AI_SMART_TALK_CHAT_SIZE' => 'chatSize',
            'AI_SMART_TALK_COLOR_MODE' => 'initialColorMode',
            'AI_SMART_TALK_BORDER_RADIUS' => 'borderRadius',
            'AI_SMART_TALK_BUTTON_BORDER_RADIUS' => 'buttonBorderRadius',
        ];

        foreach ($textOverrides as $configKey => $settingKey) {
            $value = \Configuration::get($configKey);
            if (!empty($value)) {
                $settings[$settingKey] = $value;
            }
        }

        // Boolean toggle overrides (only if explicitly 'on' or 'off')
        $toggleOverrides = [
            'AI_SMART_TALK_ENABLE_ATTACHMENT' => 'enableAttachment',
            'AI_SMART_TALK_ENABLE_FEEDBACK' => 'enableFeedback',
            'AI_SMART_TALK_ENABLE_VOICE_INPUT' => 'enableVoiceInput',
            'AI_SMART_TALK_ENABLE_VOICE_MODE' => 'enableVoiceMode',
            'AI_SMART_TALK_ENABLE_AUTO_LOGIN' => 'enableAutoLogin',
            'AI_SMART_TALK_REQUIRE_AUTHENTICATION' => 'requireAuthentication',
        ];

        foreach ($toggleOverrides as $configKey => $settingKey) {
            $value = \Configuration::get($configKey);
            if ($value === 'on') {
                $settings[$settingKey] = true;
            } elseif ($value === 'off') {
                $settings[$settingKey] = false;
            }
        }

        // Color theme overrides (build nested theme structure)
        $primaryColor = \Configuration::get('AI_SMART_TALK_PRIMARY_COLOR');
        $secondaryColor = \Configuration::get('AI_SMART_TALK_SECONDARY_COLOR');

        if (!empty($primaryColor) || !empty($secondaryColor)) {
            if (!isset($settings['theme'])) {
                $settings['theme'] = [];
            }
            if (!isset($settings['theme']['colors'])) {
                $settings['theme']['colors'] = [];
            }
            if (!isset($settings['theme']['colors']['brand'])) {
                $settings['theme']['colors']['brand'] = [];
            }

            if (!empty($primaryColor)) {
                $settings['theme']['colors']['brand']['500'] = $primaryColor;
            }
            if (!empty($secondaryColor)) {
                $settings['theme']['colors']['brand']['200'] = $secondaryColor;
            }
        }

        // Allowed languages override (restrict the widget's language switcher).
        // Empty/unset locally → inherit the platform embed config (or all langs).
        $allowedLanguagesRaw = \Configuration::get('AI_SMART_TALK_ALLOWED_LANGUAGES');
        if (!empty($allowedLanguagesRaw)) {
            $decoded = json_decode($allowedLanguagesRaw, true);
            if (is_array($decoded)) {
                $sanitized = WidgetLocales::sanitize($decoded);
                if (count($sanitized) > 0) {
                    $settings['allowedLanguages'] = $sanitized;
                }
            }
        }

        // GDPR settings (override API defaults if configured locally)
        $settings = self::applyGdprOverrides($settings);

        return $settings;
    }

    /**
     * Apply GDPR consent overrides.
     *
     * @param array $settings Current chatbot settings
     * @return array Settings with GDPR overrides
     */
    private static function applyGdprOverrides(array $settings): array
    {
        $gdprEnabled = \Configuration::get('AI_SMART_TALK_GDPR_ENABLED');
        $gdprPrivacyUrl = \Configuration::get('AI_SMART_TALK_GDPR_PRIVACY_URL');
        $consentWallEnabled = \Configuration::get('AI_SMART_TALK_CONSENT_WALL_ENABLED');
        $consentWallMessage = \Configuration::get('AI_SMART_TALK_CONSENT_WALL_MESSAGE');

        $hasGdprOverrides = $gdprEnabled === 'on' || $gdprEnabled === 'off'
            || !empty($gdprPrivacyUrl)
            || $consentWallEnabled === 'on' || $consentWallEnabled === 'off';

        if (!$hasGdprOverrides) {
            return $settings;
        }

        // Build default privacy URL
        $apiUrl = \Configuration::get('AI_SMART_TALK_URL') ?: 'https://aismarttalk.tech';
        $context = \Context::getContext();
        $currentLang = isset($context->language) ? substr($context->language->iso_code, 0, 2) : 'en';
        $defaultPrivacyUrl = rtrim($apiUrl, '/') . '/' . $currentLang . '/privacy-policy';

        if (!isset($settings['gdprConsent'])) {
            $settings['gdprConsent'] = [];
        }

        if ($gdprEnabled === 'on') {
            $settings['gdprConsent']['enabled'] = true;
            $settings['gdprConsent']['privacyPolicyUrl'] = !empty($gdprPrivacyUrl) ? $gdprPrivacyUrl : $defaultPrivacyUrl;
        } elseif ($gdprEnabled === 'off') {
            $settings['gdprConsent']['enabled'] = false;
        }

        if ($consentWallEnabled === 'on') {
            $settings['gdprConsent']['consentWallEnabled'] = true;
            if (!empty($consentWallMessage)) {
                $settings['gdprConsent']['consentWallMessage'] = $consentWallMessage;
            }
        } elseif ($consentWallEnabled === 'off') {
            $settings['gdprConsent']['consentWallEnabled'] = false;
        }

        if (!empty($gdprPrivacyUrl)) {
            $settings['gdprConsent']['privacyPolicyUrl'] = $gdprPrivacyUrl;
        }

        return $settings;
    }
}
