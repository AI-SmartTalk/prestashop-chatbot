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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\AiSmartTalk\AdminFormHandler;
use PrestaShop\AiSmartTalk\AiSmartTalkCache;
use PrestaShop\AiSmartTalk\AiSmartTalkCustomerSync;
use PrestaShop\AiSmartTalk\AiSmartTalkProductSync;
use PrestaShop\AiSmartTalk\ApiClient;
use PrestaShop\AiSmartTalk\ChatbotSettingsBuilder;
use PrestaShop\AiSmartTalk\CleanProductDocuments;
use PrestaShop\AiSmartTalk\CustomerSync;
use PrestaShop\AiSmartTalk\OAuthHandler;
use PrestaShop\AiSmartTalk\OAuthTokenHandler;
use PrestaShop\AiSmartTalk\SyncFilterHelper;
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;
use PrestaShop\AiSmartTalk\WebhookHandler;

class AiSmartTalk extends Module
{
    /**
     * Default URLs for AI SmartTalk services.
     * Used as fallbacks when Configuration values are not set.
     */
    const DEFAULT_API_URL = 'https://aismarttalk.tech';
    const DEFAULT_CDN_URL = 'https://cdn.aismarttalk.tech';
    const DEFAULT_WS_URL = 'https://ws.223.io.aismarttalk.tech';

    public function __construct()
    {
        $this->name = 'aismarttalk';
        $this->tab = 'front_office_features';
        $this->version = '3.4.0';
        $this->author = 'AI SmartTalk';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '9.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('AI SmartTalk', [], 'Modules.Aismarttalk.Admin');
        $this->description = $this->trans('Integrate AI SmartTalk chatbot to enhance customer support and engagement.', [], 'Modules.Aismarttalk.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Aismarttalk.Admin');

        // NOTE: No database operations in constructor for performance.
        // Default values are set during install() and validated in getContent().
        // OAuthHandler methods have built-in fallbacks to DEFAULT_API_URL.
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Initialize all default configuration values
        $defaults = [
            'AI_SMART_TALK_ENABLED' => false,
            'AI_SMART_TALK_URL' => self::DEFAULT_API_URL,
            'AI_SMART_TALK_FRONT_URL' => self::DEFAULT_API_URL,
            'AI_SMART_TALK_CDN' => self::DEFAULT_CDN_URL,
            'AI_SMART_TALK_WS' => self::DEFAULT_WS_URL,
            'AI_SMART_TALK_IFRAME_POSITION' => 'footer',
            'AI_SMART_TALK_PRODUCT_SYNC' => false,
            'AI_SMART_TALK_CUSTOMER_SYNC' => false,
            'AI_SMART_TALK_CUSTOMER_SYNC_CONSENT' => 'all',
            'AI_SMART_TALK_ENCRYPT_PAYLOADS' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        // Register OAuth redirect URI
        OAuthHandler::registerRedirectUri($this->context);

        // Create product sync table and migrate data from legacy columns if they exist
        if (!AiSmartTalkProductSync::createTable()) {
            return false;
        }
        AiSmartTalkProductSync::migrateFromLegacyColumns();
        AiSmartTalkProductSync::removeLegacyColumns();

        // Create customer sync tracking table
        if (!AiSmartTalkCustomerSync::createTable()) {
            return false;
        }

        $this->registerAiSmartTalkHooks();

        return true;
    }

    public function registerAiSmartTalkHooks()
    {
        $isPS8Plus = version_compare(_PS_VERSION_, '8.0.0', '>=');

        $hooks = [
            'displayFooter',
            'displayBeforeBodyClosingTag',
            'displayBackOfficeHeader',
            'actionProductUpdate',
            'actionProductCreate',
            'actionProductDelete',
            'actionUpdateQuantity',
            'actionAuthentication',
            'actionCustomerLogout',
            'actionCustomerAccountAdd',
            'actionCustomerAccountUpdate',
            'actionObjectCustomerUpdateAfter',
            'actionObjectCustomerDeleteAfter',
            // Webhook triggers (common)
            'actionPaymentConfirmation',
            'actionValidateOrder',
            'actionOrderReturn',
            'actionCartSave',
            'actionOrderSlipAdd',
            'actionProductAdd',
        ];

        if ($isPS8Plus) {
            // PS 8+ hooks
            $hooks[] = 'actionProductQuantityUpdate';
            $hooks[] = 'actionOrderStatusPostUpdate';
            $hooks[] = 'actionObjectProductCommentValidateAfter';
        } else {
            // PS 1.7 hooks
            $hooks[] = 'actionOrderStatusUpdate';
            $hooks[] = 'actionObjectProductCommentAddAfter';
        }

        foreach ($hooks as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                if (!$this->registerHook($hook)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Ensure all hooks are registered.
     * Called from getContent() to auto-register missing hooks for existing installations.
     */
    public function ensureHooksRegistered(): void
    {
        $this->registerAiSmartTalkHooks();
    }

    public function uninstall()
    {
        // Disconnect OAuth before uninstalling
        OAuthHandler::disconnect();

        // Drop sync tracking tables
        AiSmartTalkProductSync::dropTable();
        AiSmartTalkCustomerSync::dropTable();

        // Unregister all possible hooks (safe to call even if not registered)
        $allHooks = [
            'displayFooter',
            'displayBeforeBodyClosingTag',
            'displayBackOfficeHeader',
            'actionProductUpdate',
            'actionProductCreate',
            'actionProductDelete',
            'actionUpdateQuantity',
            'actionProductQuantityUpdate',
            'actionAuthentication',
            'actionCustomerLogout',
            'actionCustomerAccountAdd',
            'actionCustomerAccountUpdate',
            'actionObjectCustomerUpdateAfter',
            'actionObjectCustomerDeleteAfter',
            'actionOrderStatusPostUpdate',
            'actionOrderStatusUpdate',
            'actionPaymentConfirmation',
            'actionValidateOrder',
            'actionOrderReturn',
            'actionObjectProductCommentValidateAfter',
            'actionObjectProductCommentAddAfter',
            'actionCartSave',
            'actionOrderSlipAdd',
            'actionProductAdd',
        ];

        foreach ($allHooks as $hook) {
            $this->unregisterHook($hook);
        }

        return parent::uninstall()
            && Configuration::deleteByName('AI_SMART_TALK_WEBHOOKS_ENABLED')
            && Configuration::deleteByName('AI_SMART_TALK_WEBHOOKS_TRIGGERS')
            && Configuration::deleteByName('AI_SMART_TALK_ENABLED')
            && Configuration::deleteByName('AI_SMART_TALK_URL')
            && Configuration::deleteByName('AI_SMART_TALK_FRONT_URL')
            && Configuration::deleteByName('AI_SMART_TALK_CDN')
            && Configuration::deleteByName('AI_SMART_TALK_WS')
            && Configuration::deleteByName('AI_SMART_TALK_IFRAME_POSITION')
            && Configuration::deleteByName('AI_SMART_TALK_PRODUCT_SYNC')
            && Configuration::deleteByName('AI_SMART_TALK_ACCESS_TOKEN')
            && Configuration::deleteByName('AI_SMART_TALK_CHAT_MODEL_ID')
            && Configuration::deleteByName('AI_SMART_TALK_OAUTH_SCOPE')
            && Configuration::deleteByName('AI_SMART_TALK_OAUTH_CONNECTED')
            && Configuration::deleteByName('CHAT_MODEL_ID')
            && Configuration::deleteByName('CHAT_MODEL_TOKEN')
            // Chatbot customization settings
            && Configuration::deleteByName('AI_SMART_TALK_BUTTON_TEXT')
            && Configuration::deleteByName('AI_SMART_TALK_BUTTON_TYPE')
            && Configuration::deleteByName('AI_SMART_TALK_AVATAR_URL')
            && Configuration::deleteByName('AI_SMART_TALK_COLOR_MODE')
            && Configuration::deleteByName('AI_SMART_TALK_PRIMARY_COLOR')
            && Configuration::deleteByName('AI_SMART_TALK_SECONDARY_COLOR')
            && Configuration::deleteByName('AI_SMART_TALK_CHAT_SIZE')
            && Configuration::deleteByName('AI_SMART_TALK_BUTTON_POSITION')
            && Configuration::deleteByName('AI_SMART_TALK_ENABLE_ATTACHMENT')
            && Configuration::deleteByName('AI_SMART_TALK_ENABLE_FEEDBACK')
            && Configuration::deleteByName('AI_SMART_TALK_ENABLE_VOICE_INPUT')
            && Configuration::deleteByName('AI_SMART_TALK_ENABLE_VOICE_MODE')
            && Configuration::deleteByName('AI_SMART_TALK_BORDER_RADIUS')
            && Configuration::deleteByName('AI_SMART_TALK_BUTTON_BORDER_RADIUS')
            // Customer sync
            && Configuration::deleteByName('AI_SMART_TALK_CUSTOMER_SYNC')
            && Configuration::deleteByName('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT')
            && Configuration::deleteByName('AI_SMART_TALK_ENCRYPT_PAYLOADS')
            // GDPR settings
            && Configuration::deleteByName('AI_SMART_TALK_GDPR_ENABLED')
            && Configuration::deleteByName('AI_SMART_TALK_GDPR_PRIVACY_URL')
            && Configuration::deleteByName('AI_SMART_TALK_CONSENT_WALL_ENABLED')
            && Configuration::deleteByName('AI_SMART_TALK_CONSENT_WALL_MESSAGE')
            // Temporary/error config keys
            && Configuration::deleteByName('AI_SMART_TALK_ERROR')
            && Configuration::deleteByName('AI_SMART_TALK_OAUTH_ERROR')
            && Configuration::deleteByName('AI_SMART_TALK_OAUTH_SUCCESS')
            && Configuration::deleteByName('AI_SMART_TALK_OAUTH_PENDING')
            && Configuration::deleteByName('CLEAN_PRODUCT_DOCUMENTS_ERROR')
            // Sync filter settings
            && SyncFilterHelper::deleteFilterConfig();
    }

    public function hookActionAuthentication($params)
    {
        try {
            // Only set token cookie if auto-login is not explicitly disabled
            $psAutoLogin = Configuration::get('AI_SMART_TALK_ENABLE_AUTO_LOGIN') ?: '';
            if ($psAutoLogin === 'off') {
                return;
            }

            if (!isset($params['customer'])) {
                return;
            }
            $customer = $params['customer'];
            OAuthTokenHandler::setOAuthTokenCookie($customer);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionAuthentication error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Back-office header
     * Only generates employee token when the merchant is on the module config page.
     * No network call to AI SmartTalk on other back-office pages.
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        try {
            // Only generate BO token when on this module's configuration page
            $configure = Tools::getValue('configure');
            if ($configure === $this->name) {
                OAuthTokenHandler::getOrRefreshUserToken();
            }
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookDisplayBackOfficeHeader error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }

        return '';
    }

    public function hookActionCustomerLogout($params)
    {
        try {
            OAuthTokenHandler::unsetOAuthTokenCookie();
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionCustomerLogout error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function getContent()
    {
        // Ensure all hooks are registered (for existing installations that may miss new hooks)
        $this->ensureHooksRegistered();

        // Ensure customer sync tracking table exists (for existing installations)
        AiSmartTalkCustomerSync::createTable();

        // Ensure default URLs are always available
        $this->ensureDefaultUrls();

        // Process all form submissions and actions via AdminFormHandler
        $handler = new AdminFormHandler($this, $this->context);
        $output = $handler->processAll();
        // Display the unified configuration interface
        $output .= $this->displayConfigurationPage();

        return $output;
    }

    /**
     * Display the unified configuration page
     *
     * @return string
     */
    protected function displayConfigurationPage()
    {
        $isConnected = OAuthHandler::isConnected();
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $token = Tools::getAdminTokenLite('AdminModules');

        // Build URLs
        $frontendApiUrl = OAuthHandler::getFrontendApiUrl();
        $backofficeUrl = $frontendApiUrl . '/admin/chatModel/' . $chatModelId;
        $cdnUrl = Configuration::get('AI_SMART_TALK_CDN') ?: self::DEFAULT_CDN_URL;
        $wsUrl = Configuration::get('AI_SMART_TALK_WS') ?: self::DEFAULT_WS_URL;
        $lang = $this->context->language->iso_code;

        // Fetch embed config from API
        $embedConfig = $this->fetchEmbedConfig();

        // Build chatbot settings using shared builder (eliminates duplication with renderChatbot)
        $chatbotSettings = ChatbotSettingsBuilder::build(
            $chatModelId, $lang, $frontendApiUrl, $cdnUrl, $wsUrl, $embedConfig
        );

        // Extract avatar URLs for admin display
        $embedConfigAvatarUrl = ChatbotSettingsBuilder::getEmbedConfigAvatarUrl($embedConfig);
        $localAvatarUrl = Configuration::get('AI_SMART_TALK_AVATAR_URL') ?: '';
        $effectiveAvatarUrl = !empty($localAvatarUrl) ? $localAvatarUrl : $embedConfigAvatarUrl;

        // Get cache metadata for display
        $cacheMetadata = AiSmartTalkCache::getMetadata('embed_config');
        $hasLocalOverrides = $this->hasLocalCustomizations();

        $syncFilterConfig = SyncFilterHelper::getFilterConfig();
        $syncFilterCategoryMode = empty($syncFilterConfig['categories']) ? 'all' : $syncFilterConfig['mode'];

        $this->context->smarty->assign([
            'modulePath' => $this->_path,
            'moduleVersion' => $this->version,
            'isConnected' => $isConnected,
            'chatModelId' => $chatModelId,
            'accessToken' => OAuthHandler::getAccessToken() ?? '',
            'moduleLink' => $currentIndex . '&token=' . $token,
            'formAction' => $this->getCleanFormAction(),
            'backofficeUrl' => $backofficeUrl,
            'currentLang' => substr($this->context->language->iso_code, 0, 2),

            // Chatbot settings
            'chatbotEnabled' => (bool) Configuration::get('AI_SMART_TALK_ENABLED'),
            'iframePosition' => Configuration::get('AI_SMART_TALK_IFRAME_POSITION') ?: 'footer',

            // Sync settings
            'productSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC'),
            'customerSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC'),
            'customerSyncConsent' => Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT') ?: 'all',
            'encryptPayloads' => (bool) Configuration::get('AI_SMART_TALK_ENCRYPT_PAYLOADS'),

            // Sync filter settings
            'syncFilterConfig' => $syncFilterConfig,
            'syncFilterCategoryMode' => $syncFilterCategoryMode,
            'syncFilterCategoryTree' => SyncFilterHelper::flattenCategoryTree(
                SyncFilterHelper::getCategoryTree(
                    (int) $this->context->language->id,
                    (int) $this->context->shop->id
                )
            ),
            'syncFilterSummary' => SyncFilterHelper::getFilterSummary((int) $this->context->language->id),
            'syncFilterHasActiveFilters' => SyncFilterHelper::hasActiveFilters(),

            // Advanced/WhiteLabel settings
            'apiUrl' => Configuration::get('AI_SMART_TALK_URL') ?: self::DEFAULT_API_URL,
            'cdnUrl' => $cdnUrl,
            'wsUrl' => $wsUrl,

            // Chatbot customization settings
            'buttonText' => Configuration::get('AI_SMART_TALK_BUTTON_TEXT') ?: '',
            'buttonType' => Configuration::get('AI_SMART_TALK_BUTTON_TYPE') ?: '',
            'avatarUrl' => $localAvatarUrl,
            'embedConfigAvatarUrl' => $embedConfigAvatarUrl,
            'effectiveAvatarUrl' => $effectiveAvatarUrl,
            'chatModelAvatarUrl' => $this->fetchChatModelAvatar() ?: '',
            'buttonPosition' => Configuration::get('AI_SMART_TALK_BUTTON_POSITION') ?: '',
            'chatSize' => Configuration::get('AI_SMART_TALK_CHAT_SIZE') ?: '',
            'colorMode' => Configuration::get('AI_SMART_TALK_COLOR_MODE') ?: '',
            'borderRadius' => Configuration::get('AI_SMART_TALK_BORDER_RADIUS') ?: '',
            'buttonBorderRadius' => Configuration::get('AI_SMART_TALK_BUTTON_BORDER_RADIUS') ?: '',
            'primaryColor' => Configuration::get('AI_SMART_TALK_PRIMARY_COLOR') ?: '',
            'secondaryColor' => Configuration::get('AI_SMART_TALK_SECONDARY_COLOR') ?: '',
            'enableAttachment' => Configuration::get('AI_SMART_TALK_ENABLE_ATTACHMENT') ?: '',
            'enableFeedback' => Configuration::get('AI_SMART_TALK_ENABLE_FEEDBACK') ?: '',
            'enableVoiceInput' => Configuration::get('AI_SMART_TALK_ENABLE_VOICE_INPUT') ?: '',
            'enableVoiceMode' => Configuration::get('AI_SMART_TALK_ENABLE_VOICE_MODE') ?: '',
            'enableAutoLogin' => Configuration::get('AI_SMART_TALK_ENABLE_AUTO_LOGIN') ?: '',

            // GDPR settings
            'gdprEnabled' => Configuration::get('AI_SMART_TALK_GDPR_ENABLED') ?: '',
            'gdprPrivacyUrl' => Configuration::get('AI_SMART_TALK_GDPR_PRIVACY_URL') ?: '',
            'consentWallEnabled' => Configuration::get('AI_SMART_TALK_CONSENT_WALL_ENABLED') ?: '',
            'consentWallMessage' => Configuration::get('AI_SMART_TALK_CONSENT_WALL_MESSAGE') ?: '',

            // Cache and override status
            'hasLocalOverrides' => $hasLocalOverrides,
            'cacheMetadata' => $cacheMetadata,
            'embedConfig' => $embedConfig,

            // Plan usage and credits information
            'planUsage' => $this->fetchPlanUsage(),
            'pricingPlans' => $this->fetchPricingPlans(),

            // Webhooks/Triggers settings
            'webhooksEnabled' => WebhookHandler::isEnabled(),
            'webhooksEnabledTriggers' => WebhookHandler::getEnabledTriggers(),
            'webhooksAvailableTriggers' => WebhookHandler::getAvailableTriggers(),

            // Chatbot embed (base64 encoded for security)
            'chatbotSettingsEncoded' => base64_encode(json_encode($chatbotSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * @deprecated Use displayConfigurationPage instead
     */
    protected function displayChatbotToggleForm()
    {
        return ''; // Deprecated, handled by configure.tpl
    }

    /**
     * @deprecated Use displayConfigurationPage instead
     */
    public function displayOAuthForm()
    {
        return ''; // Deprecated, handled by configure.tpl
    }

    /**
     * Legacy form for backward compatibility (manual token entry)
     * Only shown if not connected via OAuth
     *
     * @return string
     */
    public function displayForm()
    {
        // If connected via OAuth, don't show the legacy form
        if (OAuthHandler::isConnected()) {
            return '';
        }

        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->trans('Manual Configuration (Legacy)', [], 'Modules.Aismarttalk.Admin'),
                'icon' => 'icon-cog',
            ],
            'description' => $this->trans('Use this only if you cannot use the OAuth connection above. We recommend using the "Connect with AI SmartTalk" button instead.', [], 'Modules.Aismarttalk.Admin'),
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Chat Model ID', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'CHAT_MODEL_ID',
                    'required' => true,
                    'desc' => $this->trans('ID of the chat model', [], 'Modules.Aismarttalk.Admin'),
                    'value' => Configuration::get('CHAT_MODEL_ID'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Chat Model Token', [], 'Modules.Aismarttalk.Admin'),
                    'name' => 'CHAT_MODEL_TOKEN',
                    'size' => 64,
                    'required' => true,
                    'desc' => $this->trans('Token of the chat model', [], 'Modules.Aismarttalk.Admin'),
                    'value' => Configuration::get('CHAT_MODEL_TOKEN'),
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Modules.Aismarttalk.Admin'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submit' . $this->name,
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit' . $this->name;
        $helper->fields_value['CHAT_MODEL_ID'] = Configuration::get('CHAT_MODEL_ID');
        $helper->fields_value['CHAT_MODEL_TOKEN'] = Configuration::get('CHAT_MODEL_TOKEN');

        return $helper->generateForm($fields_form);
    }

    /**
     * @deprecated Use displayConfigurationPage instead
     */
    public function displayWhiteLabelForm()
    {
        return ''; // Deprecated, handled by configure.tpl
    }

    /**
     * @deprecated Use displayConfigurationPage instead
     */
    public function displayIframePositionForm()
    {
        return ''; // Deprecated, handled by configure.tpl
    }

    /**
     * @deprecated Use displayConfigurationPage instead
     */
    public function displayProductSyncForm()
    {
        return ''; // Deprecated, handled by configure.tpl
    }

    public function hookDisplayFooter($params)
    {
        try {
            $position = Configuration::get('AI_SMART_TALK_IFRAME_POSITION');
            if ($position === 'footer') {
                return $this->renderChatbot();
            }
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookDisplayFooter error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }

        return '';
    }

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        try {
            $position = Configuration::get('AI_SMART_TALK_IFRAME_POSITION');
            if ($position === 'before_footer') {
                return $this->renderChatbot();
            }
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookDisplayBeforeBodyClosingTag error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }

        return '';
    }

    /**
     * Fetch embed configuration from the API with caching
     *
     * @param bool $forceRefresh Force refresh from API, bypassing cache
     * @return array|null The embed configuration or null if failed
     */
    private function fetchEmbedConfig(bool $forceRefresh = false)
    {
        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return null;
        }

        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('embed_config');
            if ($cached !== null) {
                return $cached;
            }
        }

        $response = $client->get(
            '/api/public/chatModel/' . urlencode($client->getChatModelId()) . '/embed-config?integrationType=PRESTASHOP',
            3
        );

        if (!$response->isSuccess()) {
            PrestaShopLogger::addLog(
                'AI SmartTalk: Failed to fetch embed config. HTTP Code: ' . $response->httpCode,
                3, null, 'AiSmartTalk', null, true
            );
            return AiSmartTalkCache::get('embed_config');
        }

        $data = $response->get('data');
        if ($data) {
            AiSmartTalkCache::set('embed_config', $data, 3600);
            return $data;
        }

        return null;
    }

    /**
     * Fetch the chat model avatar from the API with caching
     *
     * @param bool $forceRefresh Force refresh from API
     * @return string|null The avatar URL or null if not available
     */
    private function fetchChatModelAvatar(bool $forceRefresh = false)
    {
        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('chat_model_avatar');
            if ($cached !== null) {
                return $cached;
            }
        }

        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return null;
        }

        $response = $client->get('/api/v1/chatModel/' . urlencode($client->getChatModelId()) . '/avatar');

        $avatarUrl = $response->get('data.avatarUrl');
        if ($response->isSuccess() && $avatarUrl) {
            AiSmartTalkCache::set('chat_model_avatar', $avatarUrl, 3600);
            return $avatarUrl;
        }

        return null;
    }

    /**
     * Fetch plan usage and credits information from the API
     * Returns current usage, plan limits, and upgrade links
     *
     * @param bool $forceRefresh Force refresh from API (bypass cache)
     * @return array|null The plan usage data or null if failed
     */
    private function fetchPlanUsage(bool $forceRefresh = false): ?array
    {
        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return null;
        }

        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('plan_usage');
            if ($cached !== null) {
                return $cached;
            }
        }

        $response = $client->get('/api/v1/plan/usage');

        if (!$response->isSuccess()) {
            return AiSmartTalkCache::get('plan_usage');
        }

        $data = $response->get();
        if ($data && isset($data['plan'])) {
            AiSmartTalkCache::set('plan_usage', $data, 300);
            return $data;
        }

        return null;
    }

    /**
     * Fetch available pricing plans from the public API
     * No authentication required
     *
     * @param bool $forceRefresh Force refresh from API (bypass cache)
     * @return array The pricing plans or empty array if failed
     */
    private function fetchPricingPlans(bool $forceRefresh = false): array
    {
        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('pricing_plans');
            if ($cached !== null) {
                return $cached;
            }
        }

        $client = ApiClient::fromConfig();
        $response = $client->get('/api/stripe/pricing');

        if (!$response->isSuccess()) {
            return AiSmartTalkCache::get('pricing_plans') ?: [];
        }

        $plans = $response->get('data.plans');
        if ($plans) {
            AiSmartTalkCache::set('pricing_plans', $plans, 3600);
            return $plans;
        }

        return [];
    }

    /**
     * Fetch AI Skills (SmartFlows) from the API
     * Server-side loading to avoid CORS issues
     *
     * @return array The skills array or empty array if failed
     */
    private function fetchSkills(): array
    {
        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return [];
        }

        $chatModelId = $client->getChatModelId();
        $cached = AiSmartTalkCache::get('skills_' . $chatModelId);
        if ($cached !== null) {
            return $cached;
        }

        $lang = substr($this->context->language->iso_code, 0, 2);
        $response = $client->get('/api/v1/smartflow-templates/installed?lang=' . urlencode($lang));

        $installed = $response->get('installed');
        if ($response->isSuccess() && is_array($installed)) {
            AiSmartTalkCache::set('skills_' . $chatModelId, $installed, 300);
            return $installed;
        }

        return [];
    }

    /**
     * Fetch marketplace templates from the API
     * Server-side loading to avoid CORS issues
     *
     * @return array The templates array or empty array if failed
     */
    private function fetchMarketplaceTemplates(): array
    {
        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return [];
        }

        $cached = AiSmartTalkCache::get('marketplace_templates');
        if ($cached !== null) {
            return $cached;
        }

        $lang = substr($this->context->language->iso_code, 0, 2);
        $response = $client->get('/api/v1/smartflow-templates?platform=prestashop&limit=20&lang=' . urlencode($lang));

        $templates = $response->get('templates');
        if ($response->isSuccess() && is_array($templates)) {
            AiSmartTalkCache::set('marketplace_templates', $templates, 1800);
            return $templates;
        }

        return [];
    }

    /**
     * Render the chatbot using the universal embed script
     *
     * @return string The HTML/JS code to embed the chatbot
     */
    private function renderChatbot()
    {
        if (!Configuration::get('AI_SMART_TALK_ENABLED')) {
            return '';
        }

        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        if (empty($chatModelId)) {
            return '';
        }

        $apiUrl = OAuthHandler::getFrontendApiUrl();
        $cdnUrl = Configuration::get('AI_SMART_TALK_CDN') ?: self::DEFAULT_CDN_URL;
        $wsUrl = Configuration::get('AI_SMART_TALK_WS') ?: self::DEFAULT_WS_URL;
        $lang = $this->context->language->iso_code;

        // Build chatbot settings using shared builder (same logic as displayConfigurationPage)
        $embedConfig = $this->fetchEmbedConfig();
        $chatbotSettings = ChatbotSettingsBuilder::build(
            $chatModelId, $lang, $apiUrl, $cdnUrl, $wsUrl, $embedConfig
        );

        $this->context->smarty->assign([
            'chatbotSettingsEncoded' => base64_encode(json_encode($chatbotSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'cdnUrl' => $cdnUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        try {
            // Check if product sync is enabled
            if (!(bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            $idProduct = (int) $params['id_product'];
            $product = new Product($idProduct);
            $currentQuantity = (int) StockAvailable::getQuantityAvailableByProduct($idProduct);
            $shopId = (int) $this->context->shop->id;

            // If product is inactive or out of stock, clean it from AI SmartTalk
            if (!$product->active || $currentQuantity <= 0) {
                if (AiSmartTalkProductSync::isSynced($idProduct)) {
                    $api = new CleanProductDocuments();
                    $api(['productIds' => [(string) $idProduct]]);
                    AiSmartTalkProductSync::markAsNotSynced($idProduct);
                }
                return;
            }

            // Use dedicated sync table for debounce check (3 seconds)
            if (!AiSmartTalkProductSync::canSync($idProduct, 3)) {
                return;
            }

            $api = new SynchProductsToAiSmartTalk($this->context);
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);

            // Update last sync time in dedicated table
            AiSmartTalkProductSync::updateLastSyncTime($idProduct);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function hookActionProductCreate($params)
    {
        try {
            // Check if product sync is enabled
            if (!(bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            $idProduct = $params['id_product'];
            $api = new SynchProductsToAiSmartTalk($this->context);
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductCreate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function hookActionProductDelete($params)
    {
        try {
            // Check if product sync is enabled
            if (!(bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            $idProduct = (int) $params['id_product'];
            $shopId = (int) $this->context->shop->id;

            // Only delete from AI SmartTalk if product matched sync filters
            // (otherwise it was never synced in the first place)
            if (!SyncFilterHelper::shouldProductBeSynced($idProduct, $shopId)) {
                return;
            }

            $api = new CleanProductDocuments();
            $api(['productIds' => [(string) $idProduct]]);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductDelete error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: actionProductQuantityUpdate (PrestaShop 9 alternative)
     * This hook is called when product quantity is updated
     *
     * @param array $params Hook parameters
     */
    public function hookActionProductQuantityUpdate($params)
    {
        try {
            $this->handleQuantityUpdate($params);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductQuantityUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function hookActionUpdateQuantity($params)
    {
        try {
            $this->handleQuantityUpdate($params);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionUpdateQuantity error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Shared handler for stock quantity updates
     * Used by both actionUpdateQuantity and actionProductQuantityUpdate hooks
     *
     * @param array $params Hook parameters
     */
    protected function handleQuantityUpdate(array $params): void
    {
        if (!isset($params['id_product'])) {
            return;
        }

        $idProduct = (int) $params['id_product'];
        $idProductAttribute = isset($params['id_product_attribute']) ? (int) $params['id_product_attribute'] : 0;

        // Get the NEW quantity - could be in 'quantity' or 'new_quantity' depending on hook
        $newQuantity = null;
        if (isset($params['quantity'])) {
            $newQuantity = (int) $params['quantity'];
        } elseif (isset($params['new_quantity'])) {
            $newQuantity = (int) $params['new_quantity'];
        } else {
            $newQuantity = (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute);
        }

        // Use a cache key to track previous stock levels across requests
        $cacheKey = 'ast_stock_' . $idProduct . '_' . $idProductAttribute;
        $wasOutOfStock = Configuration::get($cacheKey) === 'out_of_stock';

        // Product is now out of stock
        if ($newQuantity <= 0) {
            // Only trigger webhook if it wasn't already out of stock
            if (!$wasOutOfStock) {
                Configuration::updateValue($cacheKey, 'out_of_stock');

                $this->triggerOutOfStockWebhook($idProduct, $idProductAttribute, 1);

                // Product sync: remove from AI SmartTalk if it matched filters
                if ((bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
                    $shopId = (int) $this->context->shop->id;
                    // Only delete if product matched sync filters (was actually synced)
                    if (SyncFilterHelper::shouldProductBeSynced($idProduct, $shopId)) {
                        $api = new CleanProductDocuments();
                        $api(['productIds' => [(string) $idProduct]]);
                        AiSmartTalkProductSync::markAsNotSynced($idProduct);
                    }
                }
            }
        } else {
            // Product is now in stock
            if ($wasOutOfStock) {
                // Clear the out of stock flag
                Configuration::deleteByName($cacheKey);
            }

            // Sync product if it hasn't been synced yet (new product or restock)
            if ((bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')
                && !AiSmartTalkProductSync::isSynced($idProduct)
            ) {
                $api = new SynchProductsToAiSmartTalk($this->context);
                $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
                AiSmartTalkProductSync::updateLastSyncTime($idProduct);
            }
        }
    }

    /**
     * Ensure default URLs are set in Configuration.
     * Only called from getContent() (admin page), never from frontend.
     */
    private function ensureDefaultUrls(): void
    {
        $currentUrl = Configuration::get('AI_SMART_TALK_URL');
        $currentFrontUrl = Configuration::get('AI_SMART_TALK_FRONT_URL');
        $currentCdn = Configuration::get('AI_SMART_TALK_CDN');
        $currentWs = Configuration::get('AI_SMART_TALK_WS');

        if (empty($currentUrl) || !filter_var($currentUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_URL', self::DEFAULT_API_URL);
        }
        if (empty($currentFrontUrl) || !filter_var($currentFrontUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_FRONT_URL', self::DEFAULT_API_URL);
        }
        if (empty($currentCdn) || !filter_var($currentCdn, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_CDN', self::DEFAULT_CDN_URL);
        }
        if (empty($currentWs) || !filter_var($currentWs, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_WS', self::DEFAULT_WS_URL);
        }
    }

    /**
     * @deprecated Use displayConfigurationPage instead
     */
    public function displayBackOfficeIframe()
    {
        return ''; // Deprecated, handled by configure.tpl
    }

    public function hookActionCustomerAccountAdd($params)
    {
        try {
            if (!isset($params['newCustomer'])) {
                return;
            }
            $customer = $params['newCustomer'];

            // Customer sync (with consent filter + tracking)
            if (\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')
                && CustomerSync::customerMatchesConsentFilter($customer)
            ) {
                $sync = new CustomerSync($this->context);
                $sync->syncCustomer($customer);
            }

            // Webhook: customer registered
            if (WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_CUSTOMER_REGISTERED)) {
                $webhookHandler = new WebhookHandler($this->context);
                $webhookHandler->triggerCustomerRegistered($customer);
            }
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionCustomerAccountAdd error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        try {
            if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
                return;
            }

            if (!isset($params['customer'])) {
                return;
            }
            $customer = $params['customer'];

            $sync = new CustomerSync($this->context);
            $sync->syncOrRemove($customer);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionCustomerAccountUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Customer object updated (fires for both FO and BO changes).
     * Catches consent field changes made by admin in back office.
     */
    public function hookActionObjectCustomerUpdateAfter($params)
    {
        try {
            if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
                return;
            }

            if (!isset($params['object']) || !($params['object'] instanceof \Customer)) {
                return;
            }

            $customer = $params['object'];

            $sync = new CustomerSync($this->context);
            $sync->syncOrRemove($customer);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionObjectCustomerUpdateAfter error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        try {
            if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
                return;
            }

            if (!isset($params['object']) || !($params['object'] instanceof \Customer)) {
                return;
            }

            $customer = $params['object'];
            if (empty($customer->email)) {
                return;
            }

            $sync = new CustomerSync($this->context);
            $sync->removeCustomer($customer->email);
            AiSmartTalkCustomerSync::deleteByCustomerId((int) $customer->id);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionObjectCustomerDeleteAfter error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    // =========================================================================
    // WEBHOOK TRIGGERS FOR SMARTFLOW
    // =========================================================================

    /**
     * Hook: Order status changed
     * Triggers when an order status is updated
     *
     * @param array $params Hook parameters
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_ORDER_STATUS_CHANGED)) {
                return;
            }

            $newOrderState = $params['newOrderStatus'] ?? null;
            $oldOrderState = $params['oldOrderStatus'] ?? null;
            $orderId = $params['id_order'] ?? null;

            if (!$newOrderState || !$orderId) {
                return;
            }

            $order = new \Order((int) $orderId);
            if (!\Validate::isLoadedObject($order)) {
                return;
            }

            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerOrderStatusChanged($order, $newOrderState, $oldOrderState);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionOrderStatusPostUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Order status changed (PS 1.7)
     * PS 1.7 equivalent of actionOrderStatusPostUpdate
     *
     * @param array $params Hook parameters: newOrderStatus (OrderState), id_order
     */
    public function hookActionOrderStatusUpdate($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_ORDER_STATUS_CHANGED)) {
                return;
            }

            $newOrderState = $params['newOrderStatus'] ?? null;
            $orderId = $params['id_order'] ?? null;

            if (!$newOrderState || !$orderId) {
                return;
            }

            $order = new \Order((int) $orderId);
            if (!\Validate::isLoadedObject($order)) {
                return;
            }

            // PS 1.7 does not provide oldOrderStatus, read it from current state
            $oldOrderState = null;
            if ($order->current_state) {
                $oldOrderState = new \OrderState((int) $order->current_state);
                if (!\Validate::isLoadedObject($oldOrderState)) {
                    $oldOrderState = null;
                }
            }

            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerOrderStatusChanged($order, $newOrderState, $oldOrderState);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionOrderStatusUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Payment confirmation
     * Triggers when a payment is confirmed
     *
     * @param array $params Hook parameters
     */
    public function hookActionPaymentConfirmation($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_PAYMENT_RECEIVED)) {
                return;
            }

            $orderId = $params['id_order'] ?? null;
            if (!$orderId) {
                return;
            }

            $order = new \Order((int) $orderId);
            if (!\Validate::isLoadedObject($order)) {
                return;
            }

            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerPaymentReceived($order, $order->payment);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionPaymentConfirmation error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Order validated
     * Params: cart, order, customer, currency, orderStatus
     *
     * @param array $params Hook parameters
     */
    public function hookActionValidateOrder($params)
    {
        try {
            $order = $params['order'] ?? null;
            $customer = $params['customer'] ?? null;
            $orderStatus = $params['orderStatus'] ?? null;

            if (!$order || !\Validate::isLoadedObject($order)) {
                return;
            }

            $webhookHandler = new WebhookHandler($this->context);

            // Webhook: new order
            if (WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_NEW_ORDER)) {
                if (!$customer) {
                    $customer = new \Customer((int) $order->id_customer);
                }
                $webhookHandler->triggerNewOrder($order, $customer, $orderStatus);
            }

            // Webhook: payment received (only if order is already paid)
            if (WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_PAYMENT_RECEIVED)) {
                $state = $orderStatus ?? new \OrderState((int) $order->current_state);
                if ($state->paid) {
                    $webhookHandler->triggerPaymentReceived($order, $order->payment);
                }
            }
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionValidateOrder error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Order return requested
     * Triggers when a customer requests a return
     *
     * @param array $params Hook parameters
     */
    public function hookActionOrderReturn($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_RETURN_REQUESTED)) {
                return;
            }

            $orderReturn = $params['orderReturn'] ?? null;
            if (!$orderReturn || !\Validate::isLoadedObject($orderReturn)) {
                return;
            }

            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerReturnRequested($orderReturn);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionOrderReturn error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Product comment/review validated
     * Triggers when a review is validated by the productcomments module.
     * Uses actionObjectProductCommentValidateAfter (Doctrine-compatible hook
     * explicitly dispatched by ProductComment.php).
     *
     * @see https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/actionobjectproductcommentvalidateafter/
     * @param array $params ['object' => ProductComment]
     */
    public function hookActionObjectProductCommentValidateAfter($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_REVIEW_POSTED)) {
                return;
            }

            $productComment = $params['object'] ?? null;
            if (!$productComment) {
                return;
            }

            $commentData = [
                'id_product' => $productComment->id_product ?? 0,
                'id_customer' => $productComment->id_customer ?? 0,
                'customer_name' => $productComment->customer_name ?? '',
                'grade' => $productComment->grade ?? 0,
                'title' => $productComment->title ?? '',
                'content' => $productComment->content ?? '',
                'date_add' => $productComment->date_add ?? date('Y-m-d H:i:s'),
                'validate' => $productComment->validate ?? false,
            ];

            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerReviewPosted($commentData);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionObjectProductCommentValidateAfter error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: Product comment added (PS 1.7)
     * PS 1.7 equivalent of actionObjectProductCommentValidateAfter
     *
     * @param array $params ['object' => ProductComment]
     */
    public function hookActionObjectProductCommentAddAfter($params)
    {
        $this->hookActionObjectProductCommentValidateAfter($params);
    }

    /**
     * Hook: actionProductAdd
     * Triggers when a new product is added to the catalog.
     * Params: id_product_old, id_product, product
     *
     * @param array $params Hook parameters
     */
    public function hookActionProductAdd($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_PRODUCT_CREATED)) {
                return;
            }

            $productId = (int) ($params['id_product'] ?? 0);
            if (!$productId) {
                return;
            }

            $product = $params['product'] ?? null;
            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerProductCreated($productId, $product);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductAdd error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: actionCartSave
     * Triggers when a cart is created or modified.
     * Params: cart
     *
     * @param array $params Hook parameters
     */
    public function hookActionCartSave($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_CART_UPDATED)) {
                return;
            }

            $cart = $params['cart'] ?? null;
            if (!$cart || !\Validate::isLoadedObject($cart)) {
                return;
            }

            // Only trigger for carts with products and a customer
            if (!$cart->id_customer || count($cart->getProducts()) === 0) {
                return;
            }

            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerCartUpdated($cart);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionCartSave error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: actionOrderSlipAdd
     * Triggers when a credit slip (refund) is created.
     * Params: order, productList, qtyList
     *
     * @param array $params Hook parameters
     */
    public function hookActionOrderSlipAdd($params)
    {
        try {
            if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_REFUND_CREATED)) {
                return;
            }

            $order = $params['order'] ?? null;
            if (!$order || !\Validate::isLoadedObject($order)) {
                return;
            }

            $productList = $params['productList'] ?? [];
            $qtyList = $params['qtyList'] ?? [];

            $webhookHandler = new WebhookHandler($this->context);
            $webhookHandler->triggerRefundCreated($order, $productList, $qtyList);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionOrderSlipAdd error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Trigger product out of stock webhook
     */
    private function triggerOutOfStockWebhook(int $productId, int $combinationId = 0, int $previousQuantity = 0): void
    {
        if (!WebhookHandler::isTriggerEnabled(WebhookHandler::TRIGGER_PRODUCT_OUT_OF_STOCK)) {
            return;
        }

        $webhookHandler = new WebhookHandler($this->context);
        $webhookHandler->triggerProductOutOfStock($productId, $combinationId, $previousQuantity);
    }

    /**
     * Check if user has any local customization overrides configured.
     * Used to determine if we should show "using API defaults" or "using local overrides".
     *
     * @return bool True if any local override is set
     */
    /**
     * Build a clean form action URL without one-shot action parameters.
     * Prevents URL parameter pollution where action params (forceSync, syncCustomers, clean)
     * persist in form actions and re-trigger on every subsequent form submission.
     */
    private function getCleanFormAction(): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        $actionParams = ['forceSync', 'syncCustomers', 'clean', 'refreshEmbedConfig', 'resetLocalCustomizations', 'disconnectOAuth', 'connectOAuth', 'resetConfiguration'];
        $parsed = parse_url($uri);
        if (!isset($parsed['query'])) {
            return $uri;
        }
        parse_str($parsed['query'], $params);
        foreach ($actionParams as $param) {
            unset($params[$param]);
        }
        $cleanQuery = http_build_query($params);
        $base = $parsed['path'] ?? '';

        return $base . ($cleanQuery ? '?' . $cleanQuery : '');
    }

    private function hasLocalCustomizations(): bool
    {
        $settingsToCheck = [
            'AI_SMART_TALK_BUTTON_TEXT',
            'AI_SMART_TALK_BUTTON_TYPE',
            'AI_SMART_TALK_AVATAR_URL',
            'AI_SMART_TALK_BUTTON_POSITION',
            'AI_SMART_TALK_CHAT_SIZE',
            'AI_SMART_TALK_COLOR_MODE',
            'AI_SMART_TALK_BORDER_RADIUS',
            'AI_SMART_TALK_BUTTON_BORDER_RADIUS',
            'AI_SMART_TALK_PRIMARY_COLOR',
            'AI_SMART_TALK_SECONDARY_COLOR',
            'AI_SMART_TALK_ENABLE_ATTACHMENT',
            'AI_SMART_TALK_ENABLE_FEEDBACK',
            'AI_SMART_TALK_ENABLE_VOICE_INPUT',
            'AI_SMART_TALK_ENABLE_VOICE_MODE',
        ];

        foreach ($settingsToCheck as $setting) {
            $value = Configuration::get($setting);
            if (!empty($value)) {
                return true;
            }
        }

        return false;
    }
}
