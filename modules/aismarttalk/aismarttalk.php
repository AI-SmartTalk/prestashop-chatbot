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

use PrestaShop\AiSmartTalk\AiSmartTalkCache;
use PrestaShop\AiSmartTalk\AiSmartTalkProductSync;
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
        $this->version = '3.2.1';
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

        $this->registerAiSmartTalkHooks();

        return true;
    }

    public function registerAiSmartTalkHooks()
    {
        $hooks = [
            'displayFooter',
            'displayBeforeBodyClosingTag',
            'displayBackOfficeHeader',
            'actionProductUpdate',
            'actionProductCreate',
            'actionProductDelete',
            'actionUpdateQuantity',
            'actionProductQuantityUpdate', // Alternative stock hook for PS 9
            'actionAuthentication',
            'actionCustomerLogout',
            'actionCustomerAccountAdd',
            'actionCustomerAccountUpdate',
            'actionCustomerDelete',
            // Webhook triggers
            'actionOrderStatusPostUpdate',
            'actionPaymentConfirmation',
            'actionValidateOrder',
            'actionOrderReturn',
            'actionObjectProductCommentValidateAfter',
            'actionCartSave',
            'actionOrderSlipAdd',
            'actionProductAdd',
        ];

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

        // Drop the product sync table
        AiSmartTalkProductSync::dropTable();

        return parent::uninstall()
            && $this->unregisterHook('displayFooter')
            && $this->unregisterHook('displayBeforeBodyClosingTag')
            && $this->unregisterHook('displayBackOfficeHeader')
            && $this->unregisterHook('actionProductUpdate')
            && $this->unregisterHook('actionProductCreate')
            && $this->unregisterHook('actionProductDelete')
            && $this->unregisterHook('actionUpdateQuantity')
            && $this->unregisterHook('actionProductQuantityUpdate')
            && $this->unregisterHook('actionAuthentication')
            && $this->unregisterHook('actionCustomerLogout')
            && $this->unregisterHook('actionCustomerAccountAdd')
            && $this->unregisterHook('actionCustomerAccountUpdate')
            && $this->unregisterHook('actionCustomerDelete')
            // Webhook triggers
            && $this->unregisterHook('actionOrderStatusPostUpdate')
            && $this->unregisterHook('actionPaymentConfirmation')
            && $this->unregisterHook('actionValidateOrder')
            && $this->unregisterHook('actionOrderReturn')
            && $this->unregisterHook('actionObjectProductCommentValidateAfter')
            && $this->unregisterHook('actionCartSave')
            && $this->unregisterHook('actionOrderSlipAdd')
            && $this->unregisterHook('actionProductAdd')
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
            // GDPR settings
            && Configuration::deleteByName('AI_SMART_TALK_GDPR_ENABLED')
            && Configuration::deleteByName('AI_SMART_TALK_GDPR_PRIVACY_URL')
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
     * Sets user OAuth token cookie proactively for employee auto-login.
     * Cookie check ensures the API is only called once per session.
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        try {
            OAuthTokenHandler::getOrRefreshUserToken();
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
        $output = '';

        // Ensure all hooks are registered (for existing installations that may miss new hooks)
        $this->ensureHooksRegistered();

        // Ensure default URLs are always available
        $this->ensureDefaultUrls();

        // Check for OAuth success/error messages from callback
        $oauthSuccess = Configuration::get('AI_SMART_TALK_OAUTH_SUCCESS');
        $oauthError = Configuration::get('AI_SMART_TALK_OAUTH_ERROR');

        if (!empty($oauthSuccess)) {
            $output .= $this->displayConfirmation($oauthSuccess);
            Configuration::deleteByName('AI_SMART_TALK_OAUTH_SUCCESS');
        }

        if (!empty($oauthError)) {
            $output .= $this->displayError($oauthError);
            Configuration::deleteByName('AI_SMART_TALK_OAUTH_ERROR');
        }

        if (Tools::getValue('resetConfiguration') === $this->name) {
            $this->resetConfiguration();
        }

        // Handle OAuth disconnect
        if (Tools::getValue('disconnectOAuth')) {
            OAuthHandler::disconnect();
            $output .= $this->displayConfirmation($this->trans('Successfully disconnected from AI SmartTalk.', [], 'Modules.Aismarttalk.Admin'));
        }

        // Handle OAuth connect (redirect to authorization URL)
        if (Tools::getValue('connectOAuth')) {
            // First register the redirect URI (in case it wasn't registered during install)
            OAuthHandler::registerRedirectUri($this->context);

            // Build return URL (current admin page) to redirect back after OAuth
            $returnUrl = $this->context->link->getAdminLink('AdminModules', true, [], [
                'configure' => $this->name,
            ]);

            // Build and redirect to authorization URL
            $authUrl = OAuthHandler::buildAuthorizationUrl($this->context, $returnUrl);
            Tools::redirect($authUrl);
            exit;
        }

        if (Tools::getValue('forceSync')) {
            $force = Tools::getValue('forceSync') === 'true';
            $output .= $this->sync($force, $output);
        }

        if (Tools::getValue('exportCustomers')) {
            $sync = new CustomerSync($this->context);
            $result = $sync->exportAllCustomers();

            if ($result['success']) {
                $output .= $this->displayConfirmation($this->l('Customers exported successfully!'));
            } else {
                $output .= $this->displayError($this->l('Failed to export customers. Please check the logs.'));
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $output .= $this->displayError($error);
                    }
                }
            }
        }

        if (Tools::getValue('clean')) {
            (new CleanProductDocuments())();
            $output .= $this->displayConfirmation($this->trans('Deleted and inactive products have been cleaned.', [], 'Modules.Aismarttalk.Admin'));
        }

        // Handle refresh embed config from API (re-sync from AI SmartTalk backend)
        if (Tools::getValue('refreshEmbedConfig')) {
            AiSmartTalkCache::delete('embed_config');
            $embedConfig = $this->fetchEmbedConfig(true); // Force refresh
            if ($embedConfig) {
                $output .= $this->displayConfirmation($this->trans('Configuration synchronized from AI SmartTalk.', [], 'Modules.Aismarttalk.Admin'));
            } else {
                $output .= $this->displayError($this->trans('Failed to synchronize configuration from AI SmartTalk.', [], 'Modules.Aismarttalk.Admin'));
            }
        }

        // Handle reset local customizations (use API defaults only)
        if (Tools::getValue('resetLocalCustomizations')) {
            $this->clearLocalCustomizations();
            AiSmartTalkCache::delete('embed_config');
            $output .= $this->displayConfirmation($this->trans('Local customizations cleared. Using AI SmartTalk defaults.', [], 'Modules.Aismarttalk.Admin'));
        }

        if (Tools::isSubmit('submitToggleChatbot')) {
            $chatbotEnabled = (bool) Tools::getValue('AI_SMART_TALK_ENABLED');
            Configuration::updateValue('AI_SMART_TALK_ENABLED', $chatbotEnabled);
            $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Modules.Aismarttalk.Admin'));
        }

        if (Tools::isSubmit('submitCustomerSync')) {
            $syncEnabled = (bool) Tools::getValue('AI_SMART_TALK_CUSTOMER_SYNC');
            Configuration::updateValue('AI_SMART_TALK_CUSTOMER_SYNC', $syncEnabled);
            $output .= $this->displayConfirmation($this->l('Customer sync settings updated.'));
        }

        if (Tools::isSubmit('submitWhiteLabel')) {
            $url = Tools::getValue('AI_SMART_TALK_URL');
            $cdn = Tools::getValue('AI_SMART_TALK_CDN');
            $ws = Tools::getValue('AI_SMART_TALK_WS');

            // Validate URLs and use defaults if invalid
            $defaultUrl = self::DEFAULT_API_URL;
            $defaultCdn = self::DEFAULT_CDN_URL;
            $defaultWs = self::DEFAULT_WS_URL;

            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $url = $defaultUrl;
            }
            if (empty($cdn) || !filter_var($cdn, FILTER_VALIDATE_URL)) {
                $cdn = $defaultCdn;
            }
            // Validate WebSocket URL (wss:// or ws:// or https://)
            if (empty($ws)) {
                $ws = $defaultWs;
            }

            Configuration::updateValue('AI_SMART_TALK_URL', $url);
            // Keep frontend URL in sync with main URL
            Configuration::updateValue('AI_SMART_TALK_FRONT_URL', $url);
            Configuration::updateValue('AI_SMART_TALK_CDN', $cdn);
            Configuration::updateValue('AI_SMART_TALK_WS', $ws);

            // Re-register OAuth redirect URI with potentially new URLs
            OAuthHandler::registerRedirectUri($this->context);

            $output .= $this->displayConfirmation($this->trans('WhiteLabel settings updated.', [], 'Modules.Aismarttalk.Admin'));
        }

        if (Tools::isSubmit('submitIframePosition')) {
            $position = Tools::getValue('AI_SMART_TALK_IFRAME_POSITION');
            if (!in_array($position, ['footer', 'before_footer'])) {
                $position = 'footer';
            }
            Configuration::updateValue('AI_SMART_TALK_IFRAME_POSITION', $position);
            $output .= $this->displayConfirmation($this->trans('Iframe position updated.', [], 'Modules.Aismarttalk.Admin'));
        }

        if (Tools::isSubmit('submitProductSync')) {
            $productSyncEnabled = (bool) Tools::getValue('AI_SMART_TALK_PRODUCT_SYNC');
            Configuration::updateValue('AI_SMART_TALK_PRODUCT_SYNC', $productSyncEnabled);

            if ($productSyncEnabled) {
                $output .= $this->displayConfirmation($this->trans('Product synchronization enabled. Products will automatically sync with AI SmartTalk.', [], 'Modules.Aismarttalk.Admin'));
            } else {
                $output .= $this->displayConfirmation($this->trans('Product synchronization disabled. You can use CSV import in AI SmartTalk instead.', [], 'Modules.Aismarttalk.Admin'));
            }
        }

        // Handle combined chatbot settings form
        if (Tools::isSubmit('submitChatbotSettings')) {
            $chatbotEnabled = (bool) Tools::getValue('AI_SMART_TALK_ENABLED');
            $position = Tools::getValue('AI_SMART_TALK_IFRAME_POSITION');

            if (!in_array($position, ['footer', 'before_footer'])) {
                $position = 'footer';
            }

            Configuration::updateValue('AI_SMART_TALK_ENABLED', $chatbotEnabled);
            Configuration::updateValue('AI_SMART_TALK_IFRAME_POSITION', $position);
            $output .= $this->displayConfirmation($this->trans('Chatbot settings saved.', [], 'Modules.Aismarttalk.Admin'));
        }

        // Handle chatbot customization form
        if (Tools::isSubmit('submitChatbotCustomization')) {
            // Button settings
            $buttonText = Tools::getValue('AI_SMART_TALK_BUTTON_TEXT', '');
            $buttonType = Tools::getValue('AI_SMART_TALK_BUTTON_TYPE', '');
            $buttonPosition = Tools::getValue('AI_SMART_TALK_BUTTON_POSITION', '');

            // Validate button type
            $validButtonTypes = ['', 'default', 'icon', 'avatar', 'minimal'];
            if (!in_array($buttonType, $validButtonTypes)) {
                $buttonType = '';
            }

            // Handle avatar file upload
            $avatarUrl = Configuration::get('AI_SMART_TALK_AVATAR_URL') ?: '';
            if (isset($_FILES['AI_SMART_TALK_AVATAR_FILE']) && $_FILES['AI_SMART_TALK_AVATAR_FILE']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->uploadChatModelAvatarFile($_FILES['AI_SMART_TALK_AVATAR_FILE']);
                if ($uploadResult['success']) {
                    $avatarUrl = $uploadResult['avatarUrl'];
                    $output .= $this->displayConfirmation($this->trans('Avatar uploaded successfully.', [], 'Modules.Aismarttalk.Admin'));
                } else {
                    $output .= $this->displayError($this->trans('Avatar upload failed: ', [], 'Modules.Aismarttalk.Admin') . $uploadResult['message']);
                }
            }

            // Validate button position
            $validPositions = ['', 'bottom-right', 'bottom-left'];
            if (!in_array($buttonPosition, $validPositions)) {
                $buttonPosition = '';
            }

            // Layout settings
            $chatSize = Tools::getValue('AI_SMART_TALK_CHAT_SIZE', '');
            $validSizes = ['', 'small', 'medium', 'large'];
            if (!in_array($chatSize, $validSizes)) {
                $chatSize = '';
            }

            // Color settings
            $colorMode = Tools::getValue('AI_SMART_TALK_COLOR_MODE', '');
            $validColorModes = ['', 'light', 'dark', 'auto'];
            if (!in_array($colorMode, $validColorModes)) {
                $colorMode = '';
            }

            $borderRadius = Tools::getValue('AI_SMART_TALK_BORDER_RADIUS', '');
            $validBorderRadius = ['', 'rounded', 'slightly-rounded', 'square'];
            if (!in_array($borderRadius, $validBorderRadius)) {
                $borderRadius = '';
            }

            $buttonBorderRadius = Tools::getValue('AI_SMART_TALK_BUTTON_BORDER_RADIUS', '');
            if (!in_array($buttonBorderRadius, $validBorderRadius)) {
                $buttonBorderRadius = '';
            }

            $primaryColor = Tools::getValue('AI_SMART_TALK_PRIMARY_COLOR', '');
            $secondaryColor = Tools::getValue('AI_SMART_TALK_SECONDARY_COLOR', '');

            // Validate hex colors
            if (!empty($primaryColor) && !preg_match('/^#[a-fA-F0-9]{6}$/', $primaryColor)) {
                $primaryColor = '';
            }
            if (!empty($secondaryColor) && !preg_match('/^#[a-fA-F0-9]{6}$/', $secondaryColor)) {
                $secondaryColor = '';
            }

            // Feature toggles (empty = API default, 'on' = enabled, 'off' = disabled)
            $enableAttachment = Tools::getValue('AI_SMART_TALK_ENABLE_ATTACHMENT', '');
            $enableFeedback = Tools::getValue('AI_SMART_TALK_ENABLE_FEEDBACK', '');
            $enableVoiceInput = Tools::getValue('AI_SMART_TALK_ENABLE_VOICE_INPUT', '');
            $enableVoiceMode = Tools::getValue('AI_SMART_TALK_ENABLE_VOICE_MODE', '');

            // Validate feature toggles
            $validToggleValues = ['', 'on', 'off'];
            if (!in_array($enableAttachment, $validToggleValues)) {
                $enableAttachment = '';
            }
            if (!in_array($enableFeedback, $validToggleValues)) {
                $enableFeedback = '';
            }
            if (!in_array($enableVoiceInput, $validToggleValues)) {
                $enableVoiceInput = '';
            }
            if (!in_array($enableVoiceMode, $validToggleValues)) {
                $enableVoiceMode = '';
            }

            // Note: Avatar URL is already uploaded via uploadChatModelAvatarFile above
            // No need for a second upload - the $avatarUrl already contains the CDN URL

            // Save all customization settings
            Configuration::updateValue('AI_SMART_TALK_BUTTON_TEXT', pSQL($buttonText));
            Configuration::updateValue('AI_SMART_TALK_BUTTON_TYPE', $buttonType);
            Configuration::updateValue('AI_SMART_TALK_AVATAR_URL', pSQL($avatarUrl));
            Configuration::updateValue('AI_SMART_TALK_BUTTON_POSITION', $buttonPosition);
            Configuration::updateValue('AI_SMART_TALK_CHAT_SIZE', $chatSize);
            Configuration::updateValue('AI_SMART_TALK_COLOR_MODE', $colorMode);
            Configuration::updateValue('AI_SMART_TALK_BORDER_RADIUS', $borderRadius);
            Configuration::updateValue('AI_SMART_TALK_BUTTON_BORDER_RADIUS', $buttonBorderRadius);
            Configuration::updateValue('AI_SMART_TALK_PRIMARY_COLOR', $primaryColor);
            Configuration::updateValue('AI_SMART_TALK_SECONDARY_COLOR', $secondaryColor);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_ATTACHMENT', $enableAttachment);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_FEEDBACK', $enableFeedback);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_VOICE_INPUT', $enableVoiceInput);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_VOICE_MODE', $enableVoiceMode);

            // GDPR settings
            $gdprEnabled = Tools::getValue('AI_SMART_TALK_GDPR_ENABLED', '');
            $gdprPrivacyUrl = Tools::getValue('AI_SMART_TALK_GDPR_PRIVACY_URL', '');

            // Validate GDPR toggle
            if (!in_array($gdprEnabled, $validToggleValues)) {
                $gdprEnabled = '';
            }

            // Validate privacy URL (must be valid URL or empty)
            if (!empty($gdprPrivacyUrl) && !filter_var($gdprPrivacyUrl, FILTER_VALIDATE_URL)) {
                $gdprPrivacyUrl = '';
                $output .= $this->displayWarning($this->trans('Invalid privacy policy URL - must be a valid URL starting with http:// or https://', [], 'Modules.Aismarttalk.Admin'));
            }

            Configuration::updateValue('AI_SMART_TALK_GDPR_ENABLED', $gdprEnabled);
            Configuration::updateValue('AI_SMART_TALK_GDPR_PRIVACY_URL', pSQL($gdprPrivacyUrl));

            $output .= $this->displayConfirmation($this->trans('Chatbot customization saved.', [], 'Modules.Aismarttalk.Admin'));
        }

        // Handle combined sync settings form
        if (Tools::isSubmit('submitSyncSettings')) {
            $productSyncEnabled = (bool) Tools::getValue('AI_SMART_TALK_PRODUCT_SYNC');
            $customerSyncEnabled = (bool) Tools::getValue('AI_SMART_TALK_CUSTOMER_SYNC');

            Configuration::updateValue('AI_SMART_TALK_PRODUCT_SYNC', $productSyncEnabled);
            Configuration::updateValue('AI_SMART_TALK_CUSTOMER_SYNC', $customerSyncEnabled);
            $output .= $this->displayConfirmation($this->trans('Synchronization settings saved.', [], 'Modules.Aismarttalk.Admin'));
        }

        // Handle sync filters form
        if (Tools::isSubmit('submitSyncFilters')) {
            $categoryMode = Tools::getValue('sync_filter_category_mode', 'all');

            $filterConfig = [
                'mode' => ($categoryMode === 'exclude') ? SyncFilterHelper::MODE_EXCLUDE : SyncFilterHelper::MODE_INCLUDE,
                'categories' => ($categoryMode === 'all') ? [] : Tools::getValue('sync_filter_categories', []),
                'include_subcategories' => false,
                'product_types' => Tools::getValue('sync_filter_product_types', []),
            ];

            // Handle categories as JSON string or array
            if (is_string($filterConfig['categories'])) {
                $decoded = json_decode($filterConfig['categories'], true);
                $filterConfig['categories'] = is_array($decoded) ? $decoded : [];
            }

            if (SyncFilterHelper::saveFilterConfig($filterConfig)) {
                $output .= $this->displayConfirmation($this->trans('Sync filters saved successfully.', [], 'Modules.Aismarttalk.Admin'));
            } else {
                $output .= $this->displayError($this->trans('Failed to save sync filters.', [], 'Modules.Aismarttalk.Admin'));
            }
        }

        // Handle webhooks settings form
        if (Tools::isSubmit('submitWebhooksSettings')) {
            $enabledTriggers = Tools::getValue('webhooks_triggers', []);

            WebhookHandler::saveEnabledTriggers(is_array($enabledTriggers) ? $enabledTriggers : []);

            $output .= $this->displayConfirmation($this->trans('Webhooks settings saved.', [], 'Modules.Aismarttalk.Admin'));
        }

        // Handle legacy form submissions (for backward compatibility)
        $output .= $this->handleForm();

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

        // Get chatbot settings for embedding
        $apiUrl = $frontendApiUrl;
        $cdnUrl = Configuration::get('AI_SMART_TALK_CDN') ?: self::DEFAULT_CDN_URL;
        $wsUrl = Configuration::get('AI_SMART_TALK_WS') ?: self::DEFAULT_WS_URL;
        $lang = $this->context->language->iso_code;

        $chatbotSettings = [
            'chatModelId' => $chatModelId,
            'lang' => $lang,
            'apiUrl' => rtrim($apiUrl, '/') . '/api',
            'wsUrl' => $wsUrl,
            'cdnUrl' => $cdnUrl,
            'source' => 'PRESTASHOP',
        ];

        // Get or refresh user token for auto-login in back-office
        $userToken = OAuthTokenHandler::getOrRefreshUserToken();
        if ($userToken) {
            $chatbotSettings['userToken'] = $userToken;
        }

        // Fetch and merge embed config from API (as base defaults)
        $embedConfig = $this->fetchEmbedConfig();
        $embedConfigAvatarUrl = '';
        if ($embedConfig && is_array($embedConfig)) {
            $protectedSettings = ['chatModelId', 'apiUrl', 'wsUrl', 'cdnUrl', 'source', 'userToken', 'lang'];
            foreach ($embedConfig as $key => $value) {
                if (!in_array($key, $protectedSettings)) {
                    $chatbotSettings[$key] = $value;
                }
            }
            // Extract avatarUrl from embed config for display in admin
            if (isset($embedConfig['avatarUrl']) && !empty($embedConfig['avatarUrl'])) {
                $embedConfigAvatarUrl = $embedConfig['avatarUrl'];
            }
        }

        // Apply PrestaShop customization overrides (these take priority over API defaults)
        $chatbotSettings = $this->applyCustomizationOverrides($chatbotSettings);

        // Get local avatar URL (user uploaded)
        $localAvatarUrl = Configuration::get('AI_SMART_TALK_AVATAR_URL') ?: '';

        // Determine effective avatar URL: local override > embed config
        $effectiveAvatarUrl = !empty($localAvatarUrl) ? $localAvatarUrl : $embedConfigAvatarUrl;

        // Get cache metadata for display
        $cacheMetadata = AiSmartTalkCache::getMetadata('embed_config');
        $hasLocalOverrides = $this->hasLocalCustomizations();

        $syncFilterConfig = SyncFilterHelper::getFilterConfig();
        $syncFilterCategoryMode = empty($syncFilterConfig['categories']) ? 'all' : $syncFilterConfig['mode'];

        $this->context->smarty->assign([
            'isConnected' => $isConnected,
            'chatModelId' => $chatModelId,
            'accessToken' => OAuthHandler::getAccessToken() ?? '',
            'moduleLink' => $currentIndex . '&token=' . $token,
            'formAction' => htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'),
            'backofficeUrl' => $backofficeUrl,
            'currentLang' => substr($this->context->language->iso_code, 0, 2),

            // Chatbot settings
            'chatbotEnabled' => (bool) Configuration::get('AI_SMART_TALK_ENABLED'),
            'iframePosition' => Configuration::get('AI_SMART_TALK_IFRAME_POSITION') ?: 'footer',

            // Sync settings
            'productSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC'),
            'customerSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC'),

            // Sync filter settings
            'syncFilterConfig' => $syncFilterConfig,
            'syncFilterCategoryMode' => $syncFilterCategoryMode,
            'syncFilterCategoryTree' => SyncFilterHelper::flattenCategoryTree(
                SyncFilterHelper::getCategoryTree(
                    (int) $this->context->language->id,
                    (int) $this->context->shop->id
                )
            ),
            'syncFilterProductTypeCounts' => SyncFilterHelper::getProductTypeCounts((int) $this->context->shop->id),
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

            // GDPR settings
            'gdprEnabled' => Configuration::get('AI_SMART_TALK_GDPR_ENABLED') ?: '',
            'gdprPrivacyUrl' => Configuration::get('AI_SMART_TALK_GDPR_PRIVACY_URL') ?: '',

            // Cache and override status
            'hasLocalOverrides' => $hasLocalOverrides,
            'cacheMetadata' => $cacheMetadata,
            'embedConfig' => $embedConfig,

            // AI Skills (loaded client-side via JavaScript API calls)

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
        // Use OAuthHandler for credentials, with fallback to legacy config
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? Configuration::get('CHAT_MODEL_TOKEN');
        $apiUrl = OAuthHandler::getBackendApiUrl();

        if (empty($chatModelId) || empty($chatModelToken) || empty($apiUrl)) {
            return null;
        }

        // Check cache first (unless forcing refresh)
        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('embed_config');
            if ($cached !== null) {
                return $cached;
            }
        }

        // Build the API URL for embed config
        $embedConfigUrl = rtrim($apiUrl, '/') . '/api/public/chatModel/' . urlencode($chatModelId) . '/embed-config?integrationType=PRESTASHOP';

        // Initialize cURL (short timeout for front-end rendering)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $embedConfigUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $chatModelToken,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            // Log error for debugging
            PrestaShopLogger::addLog(
                'AI SmartTalk: Failed to fetch embed config. HTTP Code: ' . $httpCode,
                3,
                null,
                'AiSmartTalk',
                null,
                true
            );

            // On error, try to return stale cache if available
            $staleCache = AiSmartTalkCache::get('embed_config');
            return $staleCache;
        }

        $data = json_decode($response, true);

        if (isset($data['data'])) {
            // Cache for 1 hour (3600 seconds)
            AiSmartTalkCache::set('embed_config', $data['data'], 3600);
            return $data['data'];
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
        // Check cache first (unless forcing refresh)
        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('chat_model_avatar');
            if ($cached !== null) {
                return $cached;
            }
        }

        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? Configuration::get('CHAT_MODEL_TOKEN');
        $apiUrl = OAuthHandler::getBackendApiUrl();

        if (empty($chatModelId) || empty($chatModelToken) || empty($apiUrl)) {
            return null;
        }

        $avatarUrl = rtrim($apiUrl, '/') . '/api/v1/chatModel/' . urlencode($chatModelId) . '/avatar';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $avatarUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $chatModelToken,
                'Content-Type: application/json',
                'x-chat-model-id: ' . $chatModelId,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['data']['avatarUrl'])) {
            // Cache for 1 hour
            AiSmartTalkCache::set('chat_model_avatar', $data['data']['avatarUrl'], 3600);
            return $data['data']['avatarUrl'];
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
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? Configuration::get('CHAT_MODEL_TOKEN');
        $apiUrl = OAuthHandler::getBackendApiUrl();

        if (empty($chatModelId) || empty($chatModelToken) || empty($apiUrl)) {
            return null;
        }

        // Check cache first (unless forcing refresh) - cache for 5 minutes
        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('plan_usage');
            if ($cached !== null) {
                return $cached;
            }
        }

        // Build the API URL for plan usage
        $planUsageUrl = rtrim($apiUrl, '/') . '/api/v1/plan/usage';

        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $planUsageUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $chatModelToken,
                'x-chat-model-id: ' . $chatModelId,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            // On error, try to return stale cache if available
            $staleCache = AiSmartTalkCache::get('plan_usage');
            return $staleCache;
        }

        $data = json_decode($response, true);

        if ($data && isset($data['plan'])) {
            // Cache for 5 minutes (300 seconds) - shorter than embed config as usage changes
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
        $apiUrl = OAuthHandler::getBackendApiUrl();

        if (empty($apiUrl)) {
            return [];
        }

        // Check cache first (unless forcing refresh) - cache for 1 hour
        if (!$forceRefresh) {
            $cached = AiSmartTalkCache::get('pricing_plans');
            if ($cached !== null) {
                return $cached;
            }
        }

        // Build the API URL for pricing
        $pricingUrl = rtrim($apiUrl, '/') . '/api/stripe/pricing';

        // Initialize cURL (public endpoint, no auth needed)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $pricingUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            $staleCache = AiSmartTalkCache::get('pricing_plans');
            return $staleCache ?: [];
        }

        $data = json_decode($response, true);

        if ($data && isset($data['data']['plans'])) {
            // Cache for 1 hour (3600 seconds)
            AiSmartTalkCache::set('pricing_plans', $data['data']['plans'], 3600);
            return $data['data']['plans'];
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
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? Configuration::get('CHAT_MODEL_TOKEN');
        $apiUrl = OAuthHandler::getBackendApiUrl();

        if (empty($chatModelId) || empty($chatModelToken) || empty($apiUrl)) {
            return [];
        }

        // Check cache first
        $cached = AiSmartTalkCache::get('skills_' . $chatModelId);
        if ($cached !== null) {
            return $cached;
        }

        $lang = substr($this->context->language->iso_code, 0, 2);
        $skillsUrl = rtrim($apiUrl, '/') . '/api/v1/smartflow-templates/installed?lang=' . urlencode($lang);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $skillsUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $chatModelToken,
                'Content-Type: application/json',
                'x-chat-model-id: ' . $chatModelId,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return [];
        }

        $data = json_decode($response, true);

        if (isset($data['installed']) && is_array($data['installed'])) {
            // Cache for 5 minutes
            AiSmartTalkCache::set('skills_' . $chatModelId, $data['installed'], 300);
            return $data['installed'];
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
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? Configuration::get('CHAT_MODEL_TOKEN');
        $apiUrl = OAuthHandler::getBackendApiUrl();

        if (empty($chatModelId) || empty($chatModelToken) || empty($apiUrl)) {
            return [];
        }

        // Check cache first
        $cached = AiSmartTalkCache::get('marketplace_templates');
        if ($cached !== null) {
            return $cached;
        }

        $lang = substr($this->context->language->iso_code, 0, 2);
        $templatesUrl = rtrim($apiUrl, '/') . '/api/v1/smartflow-templates?platform=prestashop&limit=20&lang=' . urlencode($lang);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $templatesUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $chatModelToken,
                'Content-Type: application/json',
                'x-chat-model-id: ' . $chatModelId,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return [];
        }

        $data = json_decode($response, true);

        if (isset($data['templates']) && is_array($data['templates'])) {
            // Cache for 30 minutes
            AiSmartTalkCache::set('marketplace_templates', $data['templates'], 1800);
            return $data['templates'];
        }

        return [];
    }

    /**
     * Upload an avatar file to the chat model
     *
     * @param array $file The uploaded file from $_FILES
     * @return array Result with success status and avatar URL
     */
    private function uploadChatModelAvatarFile(array $file): array
    {
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? Configuration::get('CHAT_MODEL_TOKEN');
        $apiUrl = OAuthHandler::getBackendApiUrl();

        if (empty($chatModelId) || empty($chatModelToken) || empty($apiUrl)) {
            return ['success' => false, 'message' => 'Missing credentials'];
        }

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
        }

        // Validate MIME type using actual file content (not client-provided $_FILES['type'])
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'];
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File too large. Maximum size: 5MB'];
        }

        $avatarApiUrl = rtrim($apiUrl, '/') . '/api/v1/chatModel/' . urlencode($chatModelId) . '/avatar';

        // Prepare multipart form data using validated MIME type
        $cfile = new \CURLFile($file['tmp_name'], $mimeType, $file['name']);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $avatarApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $cfile],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $chatModelToken,
                'x-chat-model-id: ' . $chatModelId,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            $errorMessage = $curlError;
            if ($httpCode === 413) {
                $errorMessage = 'File too large for server. Try a smaller image (max 5MB).';
            } elseif ($httpCode === 400) {
                $errorMessage = 'Invalid file format. Allowed: JPEG, PNG, GIF, WebP.';
            } elseif ($httpCode === 401 || $httpCode === 403) {
                $errorMessage = 'Authentication failed. Please reconnect your AI SmartTalk account.';
            } elseif ($httpCode === 500) {
                $errorMessage = 'Server error. Please try again later.';
            }

            PrestaShopLogger::addLog(
                'AI SmartTalk: Failed to upload avatar. HTTP Code: ' . $httpCode . ' Error: ' . $curlError,
                3,
                null,
                'AiSmartTalk',
                null,
                true
            );
            return ['success' => false, 'message' => $errorMessage];
        }

        $data = json_decode($response, true);

        if (isset($data['success']) && $data['success'] && isset($data['data']['avatarUrl'])) {
            return [
                'success' => true,
                'avatarUrl' => $data['data']['avatarUrl'],
            ];
        }

        return ['success' => false, 'message' => $data['message'] ?? 'Unknown error'];
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

        // Use OAuthHandler for credentials, with fallback to legacy config
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');

        if (empty($chatModelId)) {
            return '';
        }

        // For chatbot (runs in browser), use frontend URL (accessible from browser)
        // NOTE: No database writes on frontend - use fallbacks only
        $apiUrl = OAuthHandler::getFrontendApiUrl();
        $cdnUrl = Configuration::get('AI_SMART_TALK_CDN') ?: self::DEFAULT_CDN_URL;
        $wsUrl = Configuration::get('AI_SMART_TALK_WS') ?: self::DEFAULT_WS_URL;

        $lang = $this->context->language->iso_code;

        // Get or refresh user token for auto-login (handles cookie check + API refresh)
        $userToken = OAuthTokenHandler::getOrRefreshUserToken();

        // Fetch embed config from API
        $embedConfig = $this->fetchEmbedConfig();

        // Build chatbot settings - merge API config with local settings
        $chatbotSettings = [
            'chatModelId' => $chatModelId,
            'lang' => $lang,
            'apiUrl' => rtrim($apiUrl, '/') . '/api',
            'wsUrl' => $wsUrl,
            'cdnUrl' => $cdnUrl,
            'source' => 'PRESTASHOP',
        ];

        // Add user token if available
        if ($userToken) {
            $chatbotSettings['userToken'] = $userToken;
        }

        // Merge with API embed config if available (as base defaults)
        if ($embedConfig && is_array($embedConfig)) {
            $protectedSettings = ['chatModelId', 'apiUrl', 'wsUrl', 'cdnUrl', 'source', 'userToken', 'lang'];

            foreach ($embedConfig as $key => $value) {
                // Only merge settings that are not protected
                if (!in_array($key, $protectedSettings)) {
                    $chatbotSettings[$key] = $value;
                }
            }
        }

        // Apply PrestaShop customization overrides (these take priority over API defaults)
        $chatbotSettings = $this->applyCustomizationOverrides($chatbotSettings);

        // Assign variables to Smarty (base64 encoded for security)
        $this->context->smarty->assign([
            'chatbotSettingsEncoded' => base64_encode(json_encode($chatbotSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'cdnUrl' => $cdnUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    /**
     * Apply PrestaShop customization overrides to chatbot settings
     * These settings take priority over API defaults when configured
     *
     * @param array $chatbotSettings The base chatbot settings
     * @return array The settings with PrestaShop overrides applied
     */
    private function applyCustomizationOverrides(array $chatbotSettings): array
    {
        // Get PrestaShop customization settings
        $buttonText = Configuration::get('AI_SMART_TALK_BUTTON_TEXT');
        $buttonType = Configuration::get('AI_SMART_TALK_BUTTON_TYPE');
        $avatarUrl = Configuration::get('AI_SMART_TALK_AVATAR_URL');
        $buttonPosition = Configuration::get('AI_SMART_TALK_BUTTON_POSITION');
        $chatSize = Configuration::get('AI_SMART_TALK_CHAT_SIZE');
        $colorMode = Configuration::get('AI_SMART_TALK_COLOR_MODE');
        $borderRadius = Configuration::get('AI_SMART_TALK_BORDER_RADIUS');
        $buttonBorderRadius = Configuration::get('AI_SMART_TALK_BUTTON_BORDER_RADIUS');
        $primaryColor = Configuration::get('AI_SMART_TALK_PRIMARY_COLOR');
        $secondaryColor = Configuration::get('AI_SMART_TALK_SECONDARY_COLOR');
        $enableAttachment = Configuration::get('AI_SMART_TALK_ENABLE_ATTACHMENT');
        $enableFeedback = Configuration::get('AI_SMART_TALK_ENABLE_FEEDBACK');
        $enableVoiceInput = Configuration::get('AI_SMART_TALK_ENABLE_VOICE_INPUT');
        $enableVoiceMode = Configuration::get('AI_SMART_TALK_ENABLE_VOICE_MODE');

        // Note: GDPR settings come from AI SmartTalk backend, not overridden locally

        // Apply text/select overrides (only if non-empty, meaning user has configured them)
        if (!empty($buttonText)) {
            $chatbotSettings['buttonText'] = $buttonText;
        }
        if (!empty($buttonType)) {
            $chatbotSettings['buttonType'] = $buttonType;
        }
        if (!empty($avatarUrl)) {
            $chatbotSettings['avatarUrl'] = $avatarUrl;
        }
        if (!empty($buttonPosition)) {
            $chatbotSettings['position'] = $buttonPosition;
        }
        if (!empty($chatSize)) {
            $chatbotSettings['chatSize'] = $chatSize;
        }
        if (!empty($colorMode)) {
            $chatbotSettings['initialColorMode'] = $colorMode;
        }
        if (!empty($borderRadius)) {
            $chatbotSettings['borderRadius'] = $borderRadius;
        }
        if (!empty($buttonBorderRadius)) {
            $chatbotSettings['buttonBorderRadius'] = $buttonBorderRadius;
        }

        // Apply boolean overrides (only if explicitly 'on' or 'off')
        if ($enableAttachment === 'on') {
            $chatbotSettings['enableAttachment'] = true;
        } elseif ($enableAttachment === 'off') {
            $chatbotSettings['enableAttachment'] = false;
        }

        if ($enableFeedback === 'on') {
            $chatbotSettings['enableFeedback'] = true;
        } elseif ($enableFeedback === 'off') {
            $chatbotSettings['enableFeedback'] = false;
        }

        if ($enableVoiceInput === 'on') {
            $chatbotSettings['enableVoiceInput'] = true;
        } elseif ($enableVoiceInput === 'off') {
            $chatbotSettings['enableVoiceInput'] = false;
        }

        if ($enableVoiceMode === 'on') {
            $chatbotSettings['enableVoiceMode'] = true;
        } elseif ($enableVoiceMode === 'off') {
            $chatbotSettings['enableVoiceMode'] = false;
        }

        // Apply color theme overrides (build nested theme structure)
        if (!empty($primaryColor) || !empty($secondaryColor)) {
            if (!isset($chatbotSettings['theme'])) {
                $chatbotSettings['theme'] = [];
            }
            if (!isset($chatbotSettings['theme']['colors'])) {
                $chatbotSettings['theme']['colors'] = [];
            }
            if (!isset($chatbotSettings['theme']['colors']['brand'])) {
                $chatbotSettings['theme']['colors']['brand'] = [];
            }

            if (!empty($primaryColor)) {
                $chatbotSettings['theme']['colors']['brand']['500'] = $primaryColor;
            }
            if (!empty($secondaryColor)) {
                $chatbotSettings['theme']['colors']['brand']['200'] = $secondaryColor;
            }
        }

        // GDPR settings (override API defaults if configured locally)
        $gdprEnabled = Configuration::get('AI_SMART_TALK_GDPR_ENABLED');
        $gdprPrivacyUrl = Configuration::get('AI_SMART_TALK_GDPR_PRIVACY_URL');

        // Build default privacy policy URL from AI SmartTalk
        $apiUrl = Configuration::get('AI_SMART_TALK_URL') ?: self::DEFAULT_API_URL;
        $currentLang = isset($this->context->language) ? substr($this->context->language->iso_code, 0, 2) : 'en';
        $defaultPrivacyUrl = rtrim($apiUrl, '/') . '/' . $currentLang . '/privacy-policy';

        if ($gdprEnabled === 'on' || $gdprEnabled === 'off' || !empty($gdprPrivacyUrl)) {
            if (!isset($chatbotSettings['gdprConsent'])) {
                $chatbotSettings['gdprConsent'] = [];
            }

            if ($gdprEnabled === 'on') {
                $chatbotSettings['gdprConsent']['enabled'] = true;
                // Use default AI SmartTalk privacy URL if none configured
                $chatbotSettings['gdprConsent']['privacyPolicyUrl'] = !empty($gdprPrivacyUrl) ? $gdprPrivacyUrl : $defaultPrivacyUrl;
            } elseif ($gdprEnabled === 'off') {
                $chatbotSettings['gdprConsent']['enabled'] = false;
            }

            if (!empty($gdprPrivacyUrl)) {
                $chatbotSettings['gdprConsent']['privacyPolicyUrl'] = $gdprPrivacyUrl;
            }
        }

        return $chatbotSettings;
    }

    public function hookActionProductUpdate($params)
    {
        try {
            // Check if product sync is enabled
            if (!(bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
                return;
            }

            $idProduct = (int) $params['id_product'];
            $currentQuantity = (int) StockAvailable::getQuantityAvailableByProduct($idProduct);

            if ($currentQuantity == 0) {
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

                // Product sync: re-sync when restocked
                if ((bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
                    $api = new SynchProductsToAiSmartTalk($this->context);
                    $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
                    AiSmartTalkProductSync::updateLastSyncTime($idProduct);
                }
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

    private function handleForm()
    {
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            // Ensure URLs are valid before processing
            $this->ensureDefaultUrls();

            // Only update Chat Model ID and Token from main form
            // URLs are handled by the WhiteLabel form now
            Configuration::updateValue('CHAT_MODEL_ID', Tools::getValue('CHAT_MODEL_ID'));
            Configuration::updateValue('CHAT_MODEL_TOKEN', Tools::getValue('CHAT_MODEL_TOKEN'));

            // Don't auto-sync anymore - let users choose
            $output .= $this->displayConfirmation($this->trans('Settings updated. You can now enable product synchronization or use CSV import in AI SmartTalk.', [], 'Modules.Aismarttalk.Admin'));
        }

        return $output;
    }

    private function resetConfiguration(): bool
    {
        // Disconnect OAuth first
        OAuthHandler::disconnect();

        // Clear legacy credentials
        Configuration::deleteByName('CHAT_MODEL_ID');
        Configuration::deleteByName('CHAT_MODEL_TOKEN');

        // Reset URLs to defaults
        Configuration::updateValue('AI_SMART_TALK_URL', self::DEFAULT_API_URL);
        Configuration::updateValue('AI_SMART_TALK_FRONT_URL', self::DEFAULT_API_URL);
        Configuration::updateValue('AI_SMART_TALK_CDN', self::DEFAULT_CDN_URL);
        Configuration::updateValue('AI_SMART_TALK_WS', self::DEFAULT_WS_URL);

        // Clear cache
        AiSmartTalkCache::clearAll();

        return true;
    }

    private function sync(bool $force = false, $output = '')
    {
        $api = new SynchProductsToAiSmartTalk($this->context);
        $isSynch = $api(['forceSync' => $force]);

        if (true === $isSynch) {
            if ($force) {
                $output .= $this->displayConfirmation($this->trans('All products have been synchronized with the API.', [], 'Modules.Aismarttalk.Admin'));
            } else {
                $output .= $this->displayConfirmation($this->trans('New products have been synchronized with the API.', [], 'Modules.Aismarttalk.Admin'));
            }
        } else {
            $output .= $this->displayError($this->trans('An error occurred during synchronization with the API.', [], 'Modules.Aismarttalk.Admin'));
            $output .= Configuration::get('AI_SMART_TALK_ERROR') ? $this->displayError(Configuration::get('AI_SMART_TALK_ERROR')) : '';
        }

        return $output;
    }

    public function hookActionCustomerAccountAdd($params)
    {
        try {
            if (!isset($params['newCustomer'])) {
                return;
            }
            $customer = $params['newCustomer'];

            // Customer sync
            if (\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
                $sync = new CustomerSync($this->context);
                $sync->exportCustomerBatch([$customer]);
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
            $sync->exportCustomerBatch([$customer]);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionCustomerAccountUpdate error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
        }
    }

    public function hookActionCustomerDelete($params)
    {
        try {
            // Implementation for customer deletion sync
            // This would call a different API endpoint to remove the customer from AI SmartTalk
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('AI SmartTalk hookActionCustomerDelete error: ' . $e->getMessage(), 3, null, 'AiSmartTalk', null, true);
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
     * Clear all local customization settings.
     * After clearing, the module will use API defaults only.
     *
     * @return void
     */
    private function clearLocalCustomizations(): void
    {
        // Clear all customization settings
        $settingsToDelete = [
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

        foreach ($settingsToDelete as $setting) {
            Configuration::deleteByName($setting);
        }

        // Clear cache to force re-fetch from API
        AiSmartTalkCache::clearAll();
    }

    /**
     * Check if user has any local customization overrides configured.
     * Used to determine if we should show "using API defaults" or "using local overrides".
     *
     * @return bool True if any local override is set
     */
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
