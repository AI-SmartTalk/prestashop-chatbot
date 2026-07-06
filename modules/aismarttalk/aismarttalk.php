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
use PrestaShop\AiSmartTalk\WidgetLocales;
use PrestaShop\AiSmartTalk\CleanProductDocuments;
use PrestaShop\AiSmartTalk\CustomerSync;
use PrestaShop\AiSmartTalk\MultistoreHelper;
use PrestaShop\AiSmartTalk\OAuthHandler;
use PrestaShop\AiSmartTalk\OAuthTokenHandler;
use PrestaShop\AiSmartTalk\SyncFilterHelper;
use PrestaShop\AiSmartTalk\SynchCategoriesToAiSmartTalk;
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
        $this->version = '3.12.0';
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
            // Live stock sync OFF by default: only push every quantity change when a
            // merchant opts in (otherwise we keep the lighter zero-crossing behaviour
            // and save requests for shops that don't need exact live stock).
            'AI_SMART_TALK_LIVE_STOCK_SYNC' => false,
            'AI_SMART_TALK_CUSTOMER_SYNC' => false,
            'AI_SMART_TALK_CUSTOMER_SYNC_CONSENT' => 'all',
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

        // Ensure module is visible to all customer groups in front-office
        $this->ensureModuleGroupAccess();

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
            // Combination (declination) lifecycle — keep the synced parent payload in sync
            // when only a variant is changed (new size, price impact tweak, ...).
            'actionProductAttributeCreate',
            'actionProductAttributeUpdate',
            'actionProductAttributeDelete',
            // Category lifecycle — keep the backend category tree (used to attach
            // products + drive hierarchy) in sync when the merchant edits it.
            'actionCategoryAdd',
            'actionCategoryUpdate',
            'actionCategoryDelete',
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

        // Optional / best-effort hooks — registration failure must NEVER abort the
        // install. The generic ObjectModel combination hooks are how PrestaShop
        // 8/9's new product page surfaces variant changes (the legacy
        // actionProductAttribute* hooks above cover 1.6/1.7). On any version where
        // one of these can't be registered we simply skip it — incremental sync
        // degrades gracefully (Force Sync remains the snapshot reconciliation).
        $optionalHooks = [
            'actionObjectCombinationAddAfter',
            'actionObjectCombinationUpdateAfter',
            'actionObjectCombinationDeleteAfter',
            // Stock — the robust, version-agnostic signal. PrestaShop fires
            // actionObject<Class>Add/UpdateAfter via the legacy Hook::exec on EVERY
            // StockAvailable ObjectModel write (PS 1.6 → 9), from EVERY path: product
            // page, the dedicated stock page, orders, webservice, CSV import. The
            // dedicated actionUpdateQuantity hook is NOT emitted consistently by the
            // PS 8/9 admin stock screens, so these are what make live stock reliable.
            'actionObjectStockAvailableAddAfter',
            'actionObjectStockAvailableUpdateAfter',
        ];
        foreach ($optionalHooks as $hook) {
            try {
                if (!$this->isRegisteredInHook($hook)) {
                    $this->registerHook($hook);
                }
            } catch (\Throwable $e) {
                // ignore — optional coverage only
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

    /**
     * Ensure the module is accessible to all customer groups in the front-office.
     * Without ps_module_group entries, PrestaShop's hook system silently skips the module.
     */
    public function ensureModuleGroupAccess(): void
    {
        $shops = \Shop::getShops(true, null, true);
        $groups = \Group::getGroups(\Context::getContext()->language->id);

        foreach ($shops as $shopId) {
            foreach ($groups as $group) {
                \Db::getInstance()->insert('module_group', [
                    'id_module' => (int) $this->id,
                    'id_shop' => (int) $shopId,
                    'id_group' => (int) $group['id_group'],
                ], false, true, \Db::ON_DUPLICATE_KEY);
            }
        }
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
            'actionProductAttributeCreate',
            'actionProductAttributeUpdate',
            'actionProductAttributeDelete',
            'actionObjectCombinationAddAfter',
            'actionObjectCombinationUpdateAfter',
            'actionObjectCombinationDeleteAfter',
            'actionCategoryAdd',
            'actionCategoryUpdate',
            'actionCategoryDelete',
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
            && Configuration::deleteByName('AI_SMART_TALK_LIVE_STOCK_SYNC')
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
            && Configuration::deleteByName('AI_SMART_TALK_REQUIRE_AUTHENTICATION')
            && Configuration::deleteByName('AI_SMART_TALK_BORDER_RADIUS')
            && Configuration::deleteByName('AI_SMART_TALK_BUTTON_BORDER_RADIUS')
            && Configuration::deleteByName('AI_SMART_TALK_ALLOWED_LANGUAGES')
            // Customer sync
            && Configuration::deleteByName('AI_SMART_TALK_CUSTOMER_SYNC')
            && Configuration::deleteByName('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT')
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
        // Force global configuration scope in multistore mode.
        // All AI SmartTalk settings are shared across all shops — the user should see
        // and edit the same configuration regardless of which shop is selected in the BO.
        $savedShopContext = null;
        $savedShopContextId = null;
        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_ALL) {
            $savedShopContext = Shop::getContext();
            $savedShopContextId = Shop::getContextShopID();
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        // Ensure all hooks are registered (for existing installations that may miss new hooks)
        $this->ensureHooksRegistered();

        // Ensure module is visible to all customer groups in front-office
        $this->ensureModuleGroupAccess();

        // Ensure sync tracking tables exist (for existing installations)
        AiSmartTalkProductSync::createTable();
        AiSmartTalkCustomerSync::createTable();

        // Ensure default URLs are always available
        $this->ensureDefaultUrls();

        // Asynchronous, browser-driven "Sync All Products": short-circuit here and
        // answer JSON instead of rendering the whole BO page. The admin token has
        // already been validated by AdminModulesController before getContent() runs.
        // A dedicated param (not "ajax"/"action") is used on purpose so the native
        // AdminController ajax router never intercepts the request before getContent().
        $astSyncStep = Tools::getValue('astSync');
        if ($astSyncStep === 'init' || $astSyncStep === 'batch') {
            $ajaxHandler = new AdminFormHandler($this, $this->context);
            $ajaxHandler->handleProductSyncAjax($astSyncStep);
            // handleProductSyncAjax() always exits.
        }

        $astCustomerStep = Tools::getValue('astCustomerSync');
        if ($astCustomerStep === 'init' || $astCustomerStep === 'batch') {
            $ajaxHandler = new AdminFormHandler($this, $this->context);
            $ajaxHandler->handleCustomerSyncAjax($astCustomerStep);
            // handleCustomerSyncAjax() always exits.
        }

        // Process all form submissions and actions via AdminFormHandler
        $handler = new AdminFormHandler($this, $this->context);
        $output = $handler->processAll();
        // Display the unified configuration interface
        $output .= $this->displayConfigurationPage();

        // Restore shop context
        if ($savedShopContext !== null) {
            Shop::setContext($savedShopContext, $savedShopContextId);
        }

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

        // Fetch chat model info (always fresh in admin — cheap API call, avoids stale avatar)
        $chatModelInfo = $this->fetchChatModelInfo(true);

        // Get cache metadata for display
        $cacheMetadata = AiSmartTalkCache::getMetadata('embed_config');
        $hasLocalOverrides = $this->hasLocalCustomizations();

        $syncFilterConfig = SyncFilterHelper::getFilterConfig();
        $syncFilterCategoryMode = empty($syncFilterConfig['categories']) ? 'all' : $syncFilterConfig['mode'];

        // Multistore context
        $isMultistoreActive = MultistoreHelper::isMultistoreActive();

        // Platform-provided GDPR defaults (nested) used to render the GDPR switches.
        $gdprPlatform = (is_array($embedConfig) && isset($embedConfig['gdprConsent']) && is_array($embedConfig['gdprConsent']))
            ? $embedConfig['gdprConsent']
            : null;

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

            // Multistore
            'isMultistoreActive' => $isMultistoreActive,
            'multistoreShopsChatbot' => $isMultistoreActive ? MultistoreHelper::getShopsChatbotStatus() : [],

            // Chatbot settings
            'chatbotEnabled' => (bool) Configuration::get('AI_SMART_TALK_ENABLED'),
            'iframePosition' => Configuration::get('AI_SMART_TALK_IFRAME_POSITION') ?: 'footer',

            // Sync settings
            'productSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC'),
            'liveStockSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_LIVE_STOCK_SYNC'),
            'hasExistingProductSync' => !empty(AiSmartTalkProductSync::getSyncedProductIds((int) $this->context->shop->id)),
            'customerSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC'),
            'hasExistingCustomerSync' => !empty(AiSmartTalkCustomerSync::getSyncedCustomerIds()),
            'customerSyncConsent' => Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT') ?: 'all',

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
            'defaultApiUrl' => self::DEFAULT_API_URL,
            'defaultCdnUrl' => self::DEFAULT_CDN_URL,
            'defaultWsUrl' => self::DEFAULT_WS_URL,
            'urlsAreDefault' => (
                (Configuration::get('AI_SMART_TALK_URL') ?: self::DEFAULT_API_URL) === self::DEFAULT_API_URL
                && $cdnUrl === self::DEFAULT_CDN_URL
                && $wsUrl === self::DEFAULT_WS_URL
            ),

            // Chatbot customization settings
            'buttonText' => Configuration::get('AI_SMART_TALK_BUTTON_TEXT') ?: '',
            'buttonType' => Configuration::get('AI_SMART_TALK_BUTTON_TYPE') ?: '',
            'chatModelAvatarUrl' => $chatModelInfo['avatarUrl'] ?: '',
            'chatModelName' => $chatModelInfo['name']
                ?: (is_array($embedConfig) && !empty($embedConfig['name']) ? $embedConfig['name'] : ''),
            'buttonPosition' => Configuration::get('AI_SMART_TALK_BUTTON_POSITION') ?: '',
            'chatSize' => Configuration::get('AI_SMART_TALK_CHAT_SIZE') ?: '',
            'colorMode' => Configuration::get('AI_SMART_TALK_COLOR_MODE') ?: '',
            'borderRadius' => Configuration::get('AI_SMART_TALK_BORDER_RADIUS') ?: '',
            'buttonBorderRadius' => Configuration::get('AI_SMART_TALK_BUTTON_BORDER_RADIUS') ?: '',
            'primaryColor' => Configuration::get('AI_SMART_TALK_PRIMARY_COLOR') ?: '',
            'secondaryColor' => Configuration::get('AI_SMART_TALK_SECONDARY_COLOR') ?: '',
            // Feature switches render a concrete on/off state: the merchant's saved
            // choice if any, otherwise the current platform value from the embed
            // config (never an opaque "default"). Auto-login defaults to on.
            'enableAttachment' => $this->featureSwitchState('AI_SMART_TALK_ENABLE_ATTACHMENT', $embedConfig, 'enableAttachment', false),
            'enableFeedback' => $this->featureSwitchState('AI_SMART_TALK_ENABLE_FEEDBACK', $embedConfig, 'enableFeedback', false),
            'enableVoiceInput' => $this->featureSwitchState('AI_SMART_TALK_ENABLE_VOICE_INPUT', $embedConfig, 'enableVoiceInput', false),
            'enableVoiceMode' => $this->featureSwitchState('AI_SMART_TALK_ENABLE_VOICE_MODE', $embedConfig, 'enableVoiceMode', false),
            'enableAutoLogin' => $this->featureSwitchState('AI_SMART_TALK_ENABLE_AUTO_LOGIN', $embedConfig, 'enableAutoLogin', true),
            'requireLogin' => $this->featureSwitchState('AI_SMART_TALK_REQUIRE_AUTHENTICATION', $embedConfig, 'requireAuthentication', false),

            // Widget languages — restrict the language switcher (empty = all)
            'availableLanguages' => WidgetLocales::all(),
            'allowedLanguagesSelected' => $this->getAllowedLanguagesSelected(),
            'allowedLanguagesMap' => array_fill_keys($this->getAllowedLanguagesSelected(), true),

            // GDPR settings
            // GDPR on/off and Consent Wall are switches too — show the concrete
            // state (saved choice, else the platform gdprConsent value: enabled
            // defaults on, consent wall defaults off).
            'gdprEnabled' => $this->featureSwitchState('AI_SMART_TALK_GDPR_ENABLED', $gdprPlatform, 'enabled', true),
            'gdprPrivacyUrl' => Configuration::get('AI_SMART_TALK_GDPR_PRIVACY_URL') ?: '',
            'consentWallEnabled' => $this->featureSwitchState('AI_SMART_TALK_CONSENT_WALL_ENABLED', $gdprPlatform, 'consentWallEnabled', false),
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
     * Fetch the chat model info (avatar + name) from the API with caching
     *
     * @param bool $forceRefresh Force refresh from API
     * @return array{avatarUrl: string|null, name: string|null}
     */
    private function fetchChatModelInfo(bool $forceRefresh = false): array
    {
        $default = ['avatarUrl' => null, 'name' => null];

        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('chat_model_info');
            if (is_array($cached)) {
                return $cached;
            }
        }

        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return $default;
        }

        $response = $client->get('/api/v1/chatModel/' . urlencode($client->getChatModelId()) . '/avatar');

        if (!$response->isSuccess()) {
            return $default;
        }

        $info = [
            'avatarUrl' => $response->get('data.avatarUrl') ?: null,
            'name' => $response->get('data.chatModelName') ?: null,
        ];

        AiSmartTalkCache::set('chat_model_info', $info, 3600);

        return $info;
    }

    /**
     * Fetch the chat model avatar from the API with caching (convenience wrapper)
     *
     * @param bool $forceRefresh Force refresh from API
     * @return string|null The avatar URL or null if not available
     */
    private function fetchChatModelAvatar(bool $forceRefresh = false)
    {
        return $this->fetchChatModelInfo($forceRefresh)['avatarUrl'];
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
        // Chatbot display is per-shop: read from current shop context, not global
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
            'moduleVersion' => $this->version,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        try {
            if (!(bool) MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            $idProduct = (int) $params['id_product'];

            // Decide keep vs purge according to the current sync mode
            // (active+stock by default, active-only when "include out-of-stock" is on).
            if (!SyncFilterHelper::shouldProductBeKept($idProduct)) {
                if (AiSmartTalkProductSync::isSynced($idProduct)) {
                    $api = new CleanProductDocuments();
                    $api(['productIds' => [(string) $idProduct]]);
                    AiSmartTalkProductSync::markAsNotSynced($idProduct);
                }
                return;
            }

            // Debounce check (3 seconds)
            if (!AiSmartTalkProductSync::canSync($idProduct, 3)) {
                return;
            }

            $api = new SynchProductsToAiSmartTalk($this->context);
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);

            AiSmartTalkProductSync::updateLastSyncTime($idProduct);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function hookActionProductCreate($params)
    {
        try {
            // Check if product sync is enabled
            if (!(bool) MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC')) {
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
            if (!(bool) MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            $idProduct = (int) $params['id_product'];

            // Only clean from AI SmartTalk if it was actually synced
            if (!AiSmartTalkProductSync::isSynced($idProduct)) {
                return;
            }

            // Remove from knowledge base.
            // Note: by the time this hook fires, product_shop rows may already be deleted,
            // so we can't reliably check if the product is still active in other shops.
            // It's safer to always clean — the next full sync will re-add it if still active elsewhere.
            $api = new CleanProductDocuments();
            $api(['productIds' => [(string) $idProduct]]);
            AiSmartTalkProductSync::markAsNotSynced($idProduct);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductDelete error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: actionProductAttributeCreate — a new combination was added to a product.
     * We re-sync the parent so its `variants[]` payload includes the new declination.
     */
    public function hookActionProductAttributeCreate($params)
    {
        try {
            $this->handleCombinationChange($params);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductAttributeCreate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: actionProductAttributeUpdate — an existing combination was edited
     * (price impact, reference, attributes). Re-sync the parent to keep variant data fresh.
     */
    public function hookActionProductAttributeUpdate($params)
    {
        try {
            $this->handleCombinationChange($params);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductAttributeUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: actionProductAttributeDelete — a combination was removed.
     * The parent must be re-synced so the deleted variant is dropped from `variants[]`.
     */
    public function hookActionProductAttributeDelete($params)
    {
        try {
            $this->handleCombinationChange($params);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionProductAttributeDelete error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    /**
     * Hook: actionObjectCombinationAddAfter — generic ObjectModel combination create.
     */
    public function hookActionObjectCombinationAddAfter($params)
    {
        $this->handleCombinationChange($params);
    }

    /**
     * Hook: actionObjectCombinationUpdateAfter — generic ObjectModel combination edit.
     */
    public function hookActionObjectCombinationUpdateAfter($params)
    {
        $this->handleCombinationChange($params);
    }

    /**
     * Hook: actionObjectCombinationDeleteAfter — generic ObjectModel combination delete.
     * Fires on PrestaShop 8/9's new product page where the legacy
     * actionProductAttributeDelete hook may not.
     */
    public function hookActionObjectCombinationDeleteAfter($params)
    {
        $this->handleCombinationChange($params);
    }

    /**
     * Resolve the parent product of a changed combination and trigger a parent re-sync.
     *
     * Hook params may carry: `id_product` directly, an `object` (Combination
     * ObjectModel, for the actionObjectCombination*After hooks), or only
     * `id_product_attribute`. On delete the attribute row may already be gone,
     * so we try the object/params first and fall back to a DB lookup.
     */
    protected function handleCombinationChange(array $params): void
    {
        if (!(bool) MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        $idProduct = isset($params['id_product']) ? (int) $params['id_product'] : 0;

        // Generic ObjectModel hooks pass the Combination instance in `object`.
        if ($idProduct <= 0 && isset($params['object']) && is_object($params['object'])) {
            $combination = $params['object'];
            if (!empty($combination->id_product)) {
                $idProduct = (int) $combination->id_product;
            } elseif (!empty($combination->id)) {
                $idProduct = (int) \Db::getInstance()->getValue(
                    'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_attribute
                     WHERE id_product_attribute = ' . (int) $combination->id
                );
            }
        }

        if ($idProduct <= 0 && !empty($params['id_product_attribute'])) {
            $idProductAttribute = (int) $params['id_product_attribute'];
            $idProduct = (int) \Db::getInstance()->getValue(
                'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_attribute
                 WHERE id_product_attribute = ' . $idProductAttribute
            );
        }

        if ($idProduct <= 0) {
            return;
        }

        // If the parent must no longer be kept (rule depends on the "include out-of-stock"
        // toggle — see SyncFilterHelper::shouldProductBeKept), purge it from the knowledge
        // base instead of re-syncing empty data.
        if (!SyncFilterHelper::shouldProductBeKept($idProduct)) {
            if (AiSmartTalkProductSync::isSynced($idProduct)) {
                $api = new CleanProductDocuments();
                $api(['productIds' => [(string) $idProduct]]);
                AiSmartTalkProductSync::markAsNotSynced($idProduct);
            }
            return;
        }

        // Debounce: combination edits often fire in bursts (BO save edits multiple rows).
        if (!AiSmartTalkProductSync::canSync($idProduct, 3)) {
            return;
        }

        $api = new SynchProductsToAiSmartTalk($this->context);
        $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);

        AiSmartTalkProductSync::updateLastSyncTime($idProduct);
    }

    /**
     * Hook: actionCategoryAdd — a category was created.
     */
    public function hookActionCategoryAdd($params)
    {
        $this->resyncCategoryTree();
    }

    /**
     * Hook: actionCategoryUpdate — a category was renamed / moved in the tree.
     */
    public function hookActionCategoryUpdate($params)
    {
        $this->resyncCategoryTree();
    }

    /**
     * Hook: actionCategoryDelete — a category was removed.
     */
    public function hookActionCategoryDelete($params)
    {
        $this->resyncCategoryTree();
    }

    /**
     * Resync the whole category tree to the backend (idempotent upsert). The tree
     * is small enough to push wholesale on any change; a missing/renamed/moved
     * category is reflected immediately so products stay attached to real ids.
     * Gated behind the product-sync toggle and fully non-fatal.
     */
    protected function resyncCategoryTree(): void
    {
        try {
            if (!(bool) MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            $sync = new SynchCategoriesToAiSmartTalk($this->context);
            $sync();
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk resyncCategoryTree error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
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
     * Robust, version-agnostic stock signal. PrestaShop fires
     * actionObject<Class>Add/UpdateAfter via the legacy Hook::exec on EVERY
     * StockAvailable ObjectModel write — product page, stock page, orders,
     * webservice, CSV import — across PS 1.6 → 9. Unlike actionUpdateQuantity
     * (not emitted by PS 8/9 admin stock screens), this fires reliably, so it is
     * what drives live stock. We normalize the StockAvailable object to the same
     * shape handleQuantityUpdate expects and reuse its debounced logic.
     */
    public function hookActionObjectStockAvailableUpdateAfter($params)
    {
        $this->handleStockAvailableObjectHook($params);
    }

    public function hookActionObjectStockAvailableAddAfter($params)
    {
        $this->handleStockAvailableObjectHook($params);
    }

    private function handleStockAvailableObjectHook($params): void
    {
        try {
            $stock = isset($params['object']) ? $params['object'] : null;
            if (!is_object($stock) || empty($stock->id_product)) {
                return;
            }
            $this->handleQuantityUpdate([
                'id_product' => (int) $stock->id_product,
                'id_product_attribute' => isset($stock->id_product_attribute) ? (int) $stock->id_product_attribute : 0,
                'quantity' => isset($stock->quantity) ? (int) $stock->quantity : null,
            ]);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionObjectStockAvailableUpdateAfter error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
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

                // Product sync: purge only if the current sync mode no longer wants this
                // product (active-only check when out-of-stock are kept, active+stock otherwise).
                if ((bool) MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC')
                    && AiSmartTalkProductSync::isSynced($idProduct)
                    && !SyncFilterHelper::shouldProductBeKept($idProduct)
                ) {
                    $api = new CleanProductDocuments();
                    $api(['productIds' => [(string) $idProduct]]);
                    AiSmartTalkProductSync::markAsNotSynced($idProduct);
                }
            }
        } else {
            // Product is now in stock
            if ($wasOutOfStock) {
                // Clear the out of stock flag
                Configuration::deleteByName($cacheKey);
            }

            if (!(bool) MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            // Not synced yet (new product or restock from zero) → sync if it passes
            // the filters. This is the baseline behaviour, independent of live stock.
            if (!AiSmartTalkProductSync::isSynced($idProduct)) {
                if (SyncFilterHelper::shouldProductBeSynced($idProduct, MultistoreHelper::getDefaultShopId())) {
                    $api = new SynchProductsToAiSmartTalk($this->context);
                    $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
                    AiSmartTalkProductSync::updateLastSyncTime($idProduct);
                }
                return;
            }

            // Already synced + LIVE STOCK opt-in → push this quantity change so the
            // assistant reflects the exact remaining units. Debounced (canSync) to
            // coalesce bursts; on the backend this resolves to a metadata-only update
            // (no re-vectorization). When the toggle is OFF (default) we do nothing
            // here for an in-stock change, saving requests for shops that don't need
            // live quantity (availability still updated on the zero-crossing above).
            if ((bool) MultistoreHelper::getConfig('AI_SMART_TALK_LIVE_STOCK_SYNC')
                && AiSmartTalkProductSync::canSync($idProduct)
                && SyncFilterHelper::shouldProductBeKept($idProduct)
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
            if (MultistoreHelper::getConfig('AI_SMART_TALK_CUSTOMER_SYNC')
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
            if (!MultistoreHelper::getConfig('AI_SMART_TALK_CUSTOMER_SYNC')) {
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
            if (!MultistoreHelper::getConfig('AI_SMART_TALK_CUSTOMER_SYNC')) {
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
            if (!MultistoreHelper::getConfig('AI_SMART_TALK_CUSTOMER_SYNC')) {
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
    /**
     * Decode the merchant's saved language restriction for the back-office picker.
     *
     * @return array<int, string> Valid locale codes (empty = all languages offered)
     */
    private function getAllowedLanguagesSelected(): array
    {
        $raw = Configuration::get('AI_SMART_TALK_ALLOWED_LANGUAGES');
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? WidgetLocales::sanitize($decoded) : [];
    }

    private function getCleanFormAction(): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        $actionParams = ['forceSync', 'syncCustomers', 'clean', 'refreshEmbedConfig', 'resetLocalCustomizations', 'resetWhiteLabel', 'disconnectOAuth', 'connectOAuth', 'resetConfiguration'];
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

    /**
     * Effective on/off state to render for a binary feature switch: the merchant's
     * explicit plugin choice if any, otherwise the current platform value from the
     * embed config, otherwise the given fallback. Keeps the switch showing a real
     * yes/no state instead of an opaque "default".
     *
     * @param string     $configKey   PrestaShop configuration key
     * @param array|null $embedConfig  Embed config fetched from the API
     * @param string     $embedKey     Corresponding key in the embed config
     * @param bool       $fallback     Value when neither a choice nor a platform value exists
     * @return bool
     */
    private function featureSwitchState($configKey, $embedConfig, $embedKey, $fallback)
    {
        $explicit = ChatbotSettingsBuilder::explicitBinary($configKey);
        if ($explicit !== null) {
            return $explicit;
        }
        if (is_array($embedConfig) && array_key_exists($embedKey, $embedConfig)) {
            return (bool) $embedConfig[$embedKey];
        }

        return $fallback;
    }

    private function hasLocalCustomizations(): bool
    {
        $settingsToCheck = [
            'AI_SMART_TALK_BUTTON_TEXT',
            'AI_SMART_TALK_BUTTON_TYPE',
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
            'AI_SMART_TALK_ALLOWED_LANGUAGES',
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
