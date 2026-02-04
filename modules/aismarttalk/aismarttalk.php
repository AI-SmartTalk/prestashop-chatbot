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
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;

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
        $this->version = '3.1.0';
        $this->author = 'AI SmartTalk';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '9.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('AI SmartTalk', [], 'Modules.Aismarttalk.Admin');
        $this->description = $this->trans('https://aismarttalk.tech/', [], 'Modules.Aismarttalk.Admin');

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
            'actionProductUpdate',
            'actionProductCreate',
            'actionProductDelete',
            'actionUpdateQuantity',
            'actionAuthentication',
            'actionCustomerLogout',
            'actionCustomerAccountAdd',
            'actionCustomerAccountUpdate',
            'actionCustomerDelete',
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

    public function uninstall()
    {
        // Disconnect OAuth before uninstalling
        OAuthHandler::disconnect();

        // Drop the product sync table
        AiSmartTalkProductSync::dropTable();

        return parent::uninstall()
            && $this->unregisterHook('displayFooter')
            && $this->unregisterHook('displayBeforeBodyClosingTag')
            && $this->unregisterHook('actionProductUpdate')
            && $this->unregisterHook('actionProductCreate')
            && $this->unregisterHook('actionProductDelete')
            && $this->unregisterHook('actionUpdateQuantity')
            && $this->unregisterHook('actionAuthentication')
            && $this->unregisterHook('actionCustomerLogout')
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
            && Configuration::deleteByName('AI_SMART_TALK_ENABLE_VOICE_MODE');
    }

    public function hookActionAuthentication($params)
    {
        $customer = $params['customer'];
        OAuthTokenHandler::setOAuthTokenCookie($customer);
    }

    public function hookActionCustomerLogout($params)
    {
        OAuthTokenHandler::unsetOAuthTokenCookie();
    }

    public function getContent()
    {
        $output = '';

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
            Configuration::updateValue('AI_SMART_TALK_PRIMARY_COLOR', $primaryColor);
            Configuration::updateValue('AI_SMART_TALK_SECONDARY_COLOR', $secondaryColor);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_ATTACHMENT', $enableAttachment);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_FEEDBACK', $enableFeedback);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_VOICE_INPUT', $enableVoiceInput);
            Configuration::updateValue('AI_SMART_TALK_ENABLE_VOICE_MODE', $enableVoiceMode);

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

        // Get or refresh employee token for auto-login in back-office
        $employeeToken = OAuthTokenHandler::getOrRefreshEmployeeToken();
        if ($employeeToken) {
            $chatbotSettings['userToken'] = $employeeToken;
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
            'primaryColor' => Configuration::get('AI_SMART_TALK_PRIMARY_COLOR') ?: '',
            'secondaryColor' => Configuration::get('AI_SMART_TALK_SECONDARY_COLOR') ?: '',
            'enableAttachment' => Configuration::get('AI_SMART_TALK_ENABLE_ATTACHMENT') ?: '',
            'enableFeedback' => Configuration::get('AI_SMART_TALK_ENABLE_FEEDBACK') ?: '',
            'enableVoiceInput' => Configuration::get('AI_SMART_TALK_ENABLE_VOICE_INPUT') ?: '',
            'enableVoiceMode' => Configuration::get('AI_SMART_TALK_ENABLE_VOICE_MODE') ?: '',

            // Cache and override status
            'hasLocalOverrides' => $hasLocalOverrides,
            'cacheMetadata' => $cacheMetadata,
            'embedConfig' => $embedConfig,

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
        $position = Configuration::get('AI_SMART_TALK_IFRAME_POSITION');
        if ($position === 'footer') {
            return $this->renderChatbot();
        }

        return '';
    }

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        $position = Configuration::get('AI_SMART_TALK_IFRAME_POSITION');
        if ($position === 'before_footer') {
            return $this->renderChatbot();
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

        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $embedConfigUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
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

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'];
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File too large. Maximum size: 5MB'];
        }

        $avatarApiUrl = rtrim($apiUrl, '/') . '/api/v1/chatModel/' . urlencode($chatModelId) . '/avatar';

        // Prepare multipart form data
        $cfile = new \CURLFile($file['tmp_name'], $file['type'], $file['name']);

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
        $primaryColor = Configuration::get('AI_SMART_TALK_PRIMARY_COLOR');
        $secondaryColor = Configuration::get('AI_SMART_TALK_SECONDARY_COLOR');
        $enableAttachment = Configuration::get('AI_SMART_TALK_ENABLE_ATTACHMENT');
        $enableFeedback = Configuration::get('AI_SMART_TALK_ENABLE_FEEDBACK');
        $enableVoiceInput = Configuration::get('AI_SMART_TALK_ENABLE_VOICE_INPUT');
        $enableVoiceMode = Configuration::get('AI_SMART_TALK_ENABLE_VOICE_MODE');

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

        return $chatbotSettings;
    }

    public function hookActionProductUpdate($params)
    {
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
    }

    public function hookActionProductCreate($params)
    {
        // Check if product sync is enabled
        if (!(bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        $idProduct = $params['id_product'];
        $api = new SynchProductsToAiSmartTalk($this->context);
        $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
    }

    public function hookActionProductDelete($params)
    {
        // Check if product sync is enabled
        if (!(bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        $idProduct = $params['id_product'];
        $api = new CleanProductDocuments();
        $api(['productIds' => [(string) $idProduct]]);
    }

    public function hookActionUpdateQuantity($params)
    {
        // Check if product sync is enabled
        if (!(bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        if (!isset($params['id_product']) || !isset($params['quantity'])) {
            return;
        }

        $idProduct = (int) $params['id_product'];
        $newQuantity = $params['quantity'];

        // Récupérer la quantité actuelle (avant mise à jour)
        $currentQuantity = (int) StockAvailable::getQuantityAvailableByProduct($idProduct);

        // Si le produit passe à 0 stock (rupture), le supprimer d'AI SmartTalk
        if ($newQuantity === 0) {
            $api = new CleanProductDocuments();
            $api(['productIds' => [(string) $idProduct]]);
            // Mark as not synced since it's removed
            AiSmartTalkProductSync::markAsNotSynced($idProduct);
        } elseif ($currentQuantity == 0 && $newQuantity > 0) {
            // Si le produit passe de 0 à >0 (réapprovisionnement), le synchroniser
            $api = new SynchProductsToAiSmartTalk($this->context);
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
            // Update last sync time in dedicated table
            AiSmartTalkProductSync::updateLastSyncTime($idProduct);
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
        if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
            return;
        }

        $customer = $params['newCustomer'];
        $sync = new CustomerSync($this->context);
        $sync->exportCustomerBatch([$customer]);
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
            return;
        }

        $customer = $params['customer'];
        $sync = new CustomerSync($this->context);
        $sync->exportCustomerBatch([$customer]);
    }

    public function hookActionCustomerDelete($params)
    {
        // Implementation for customer deletion sync
        // This would call a different API endpoint to remove the customer from AI SmartTalk
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
