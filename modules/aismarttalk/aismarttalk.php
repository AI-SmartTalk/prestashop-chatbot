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

use PrestaShop\AiSmartTalk\CleanProductDocuments;
use PrestaShop\AiSmartTalk\OAuthHandler;
use PrestaShop\AiSmartTalk\OAuthTokenHandler;
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;
use PrestaShop\AiSmartTalk\CustomerSync;

class AiSmartTalk extends Module
{
    public function __construct()
    {
        $this->name = 'aismarttalk';
        $this->tab = 'front_office_features';
        $this->version = '3.0.0';
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

        // Initialize URL configurations with defaults if not already set or empty
        $defaultUrl = 'https://aismarttalk.tech';
        $defaultFrontUrl = 'https://aismarttalk.tech';
        $defaultCdn = 'https://cdn.aismarttalk.tech';
        $defaultWs = 'https://ws.223.io.aismarttalk.tech';
        
        $currentUrl = Configuration::get('AI_SMART_TALK_URL');
        $currentFrontUrl = Configuration::get('AI_SMART_TALK_FRONT_URL');
        $currentCdn = Configuration::get('AI_SMART_TALK_CDN');
        $currentWs = Configuration::get('AI_SMART_TALK_WS');
        
        if (empty($currentUrl) || !filter_var($currentUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_URL', $defaultUrl);
        }
        if (empty($currentFrontUrl) || !filter_var($currentFrontUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_FRONT_URL', $defaultFrontUrl);
        }
        if (empty($currentCdn) || !filter_var($currentCdn, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_CDN', $defaultCdn);
        }
        if (empty($currentWs)) {
            Configuration::updateValue('AI_SMART_TALK_WS', $defaultWs);
        }

        // Set default iframe position if not set
        if (empty(Configuration::get('AI_SMART_TALK_IFRAME_POSITION'))) {
            Configuration::updateValue('AI_SMART_TALK_IFRAME_POSITION', 'footer');
        }

        // Set default product sync if not set (disabled by default)
        $productSyncConfig = Configuration::get('AI_SMART_TALK_PRODUCT_SYNC');
        if ($productSyncConfig === null || $productSyncConfig === '') {
            Configuration::updateValue('AI_SMART_TALK_PRODUCT_SYNC', false);
        }

        $this->addSynchField();
        $this->registerAiSmartTalkHooks();
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!Configuration::updateValue('AI_SMART_TALK_ENABLED', false)) {
            return false;
        }

        // Set default URL configurations
        if (!Configuration::updateValue('AI_SMART_TALK_URL', 'https://aismarttalk.tech')) {
            return false;
        }

        // Set default frontend URL (for browser redirects, may differ from backend URL in Docker)
        if (!Configuration::updateValue('AI_SMART_TALK_FRONT_URL', 'https://aismarttalk.tech')) {
            return false;
        }

        if (!Configuration::updateValue('AI_SMART_TALK_CDN', 'https://cdn.aismarttalk.tech')) {
            return false;
        }

        // Set default WebSocket URL
        if (!Configuration::updateValue('AI_SMART_TALK_WS', 'https://ws.223.io.aismarttalk.tech')) {
            return false;
        }

        // Set default iframe position
        if (!Configuration::updateValue('AI_SMART_TALK_IFRAME_POSITION', 'footer')) {
            return false;
        }

        // Set default product sync to disabled (users can choose CSV import instead)
        if (!Configuration::updateValue('AI_SMART_TALK_PRODUCT_SYNC', false)) {
            return false;
        }

        // Register OAuth redirect URI
        OAuthHandler::registerRedirectUri();

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
        
        return parent::uninstall()
            && $this->unregisterHook('displayFooter')
            && $this->unregisterHook('displayBeforeBodyClosingTag')
            && $this->unregisterHook('actionProductUpdate')
            && $this->unregisterHook('actionProductCreate')
            && $this->unregisterHook('actionProductDelete')
            && $this->unregisterHook('actionUpdateQuantity')
            && $this->unregisterHook('actionAuthentication')
            && $this->unregisterHook('actionCustomerLogout')
            && $this->removeSynchField()
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
            && Configuration::deleteByName('CHAT_MODEL_TOKEN');
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

    private function addSynchField()
    {
        $db = Db::getInstance();
        $tableName = _DB_PREFIX_ . 'product';

        // Check if aismarttalk_synch column exists before adding
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_synch'");
        if (empty($result)) {
            $db->execute("ALTER TABLE `$tableName` ADD COLUMN `aismarttalk_synch` TINYINT(1) NOT NULL DEFAULT 0");
        }

        // Check if aismarttalk_last_source column exists before adding
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_last_source'");
        if (empty($result)) {
            $db->execute("ALTER TABLE `$tableName` ADD COLUMN `aismarttalk_last_source` DATETIME NULL");
        }

        return true;
    }

    private function removeSynchField()
    {
        $db = Db::getInstance();
        $tableName = _DB_PREFIX_ . 'product';

        // Check if aismarttalk_synch column exists before dropping
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_synch'");
        if (!empty($result)) {
            $db->execute("ALTER TABLE `$tableName` DROP COLUMN `aismarttalk_synch`");
        }

        // Check if aismarttalk_last_source column exists before dropping
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_last_source'");
        if (!empty($result)) {
            $db->execute("ALTER TABLE `$tableName` DROP COLUMN `aismarttalk_last_source`");
        }

        return true;
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
            OAuthHandler::registerRedirectUri();
            
            // Build return URL (current admin page) to redirect back after OAuth
            $returnUrl = $this->context->link->getAdminLink('AdminModules', true, [], [
                'configure' => $this->name,
            ]);
            
            // Build and redirect to authorization URL
            $authUrl = OAuthHandler::buildAuthorizationUrl($returnUrl);
            Tools::redirect($authUrl);
            exit;
        }

        if (Tools::getValue('forceSync')) {
            $force = Tools::getValue('forceSync') === 'true';
            $output .= $this->sync($force, $output);
        }

        if (Tools::getValue('exportCustomers')) {
            $sync = new CustomerSync();
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
            $defaultUrl = 'https://aismarttalk.tech';
            $defaultCdn = 'https://cdn.aismarttalk.tech';
            $defaultWs = 'https://ws.223.io.aismarttalk.tech';
            
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
            OAuthHandler::registerRedirectUri();
            
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
        $cdnUrl = Configuration::get('AI_SMART_TALK_CDN') ?: 'https://cdn.aismarttalk.tech';
        $wsUrl = Configuration::get('AI_SMART_TALK_WS') ?: 'https://ws.223.io.aismarttalk.tech';
        $lang = $this->context->language->iso_code;
        
        $chatbotSettings = [
            'chatModelId' => $chatModelId,
            'lang' => $lang,
            'apiUrl' => rtrim($apiUrl, '/') . '/api',
            'wsUrl' => $wsUrl,
            'cdnUrl' => $cdnUrl,
            'source' => 'PRESTASHOP',
        ];
        
        // Fetch and merge embed config
        $embedConfig = $this->fetchEmbedConfig();
        if ($embedConfig && is_array($embedConfig)) {
            $protectedSettings = ['chatModelId', 'apiUrl', 'wsUrl', 'cdnUrl', 'source', 'userToken', 'lang'];
            foreach ($embedConfig as $key => $value) {
                if (!in_array($key, $protectedSettings)) {
                    $chatbotSettings[$key] = $value;
                }
            }
        }
        
        $this->context->smarty->assign([
            'isConnected' => $isConnected,
            'chatModelId' => $chatModelId,
            'moduleLink' => $currentIndex . '&token=' . $token,
            'formAction' => $_SERVER['REQUEST_URI'],
            'backofficeUrl' => $backofficeUrl,
            
            // Chatbot settings
            'chatbotEnabled' => (bool) Configuration::get('AI_SMART_TALK_ENABLED'),
            'iframePosition' => Configuration::get('AI_SMART_TALK_IFRAME_POSITION') ?: 'footer',
            
            // Sync settings
            'productSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_PRODUCT_SYNC'),
            'customerSyncEnabled' => (bool) Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC'),
            
            // Advanced/WhiteLabel settings
            'apiUrl' => Configuration::get('AI_SMART_TALK_URL') ?: 'https://aismarttalk.tech',
            'cdnUrl' => $cdnUrl,
            'wsUrl' => $wsUrl,
            
            // Chatbot embed
            'chatbotSettings' => json_encode($chatbotSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
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
     * Fetch embed configuration from the API
     * 
     * @return array|null The embed configuration or null if failed
     */
    private function fetchEmbedConfig()
    {
        // Use OAuthHandler for credentials, with fallback to legacy config
        $chatModelId = OAuthHandler::getChatModelId() ?? Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? Configuration::get('CHAT_MODEL_TOKEN');
        $apiUrl = OAuthHandler::getBackendApiUrl();
        
        if (empty($chatModelId) || empty($chatModelToken) || empty($apiUrl)) {
            return null;
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
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['data'])) {
            return $data['data'];
        }
        
        return null;
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
        $apiUrl = OAuthHandler::getFrontendApiUrl();
        $cdnUrl = Configuration::get('AI_SMART_TALK_CDN');
        $wsUrl = Configuration::get('AI_SMART_TALK_WS');
        
        if (empty($cdnUrl) || !filter_var($cdnUrl, FILTER_VALIDATE_URL)) {
            $cdnUrl = 'https://cdn.aismarttalk.tech';
            Configuration::updateValue('AI_SMART_TALK_CDN', $cdnUrl);
        }
        if (empty($wsUrl) || !filter_var($wsUrl, FILTER_VALIDATE_URL)) {
            $wsUrl = 'https://ws.223.io.aismarttalk.tech';
            Configuration::updateValue('AI_SMART_TALK_WS', $wsUrl);
        }
        
        $lang = $this->context->language->iso_code;
        $userToken = isset($_COOKIE['ai_smarttalk_oauth_token']) ? $_COOKIE['ai_smarttalk_oauth_token'] : null;
        
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
        
        // Merge with API embed config if available
        // Automatically merge all settings from API, except critical ones that must not be overridden
        if ($embedConfig && is_array($embedConfig)) {
            $protectedSettings = ['chatModelId', 'apiUrl', 'wsUrl', 'cdnUrl', 'source', 'userToken', 'lang'];
            
            foreach ($embedConfig as $key => $value) {
                // Only merge settings that are not protected
                if (!in_array($key, $protectedSettings)) {
                    $chatbotSettings[$key] = $value;
                }
            }
        }
        
        // Assign variables to Smarty
        $this->context->smarty->assign([
            'chatbotSettings' => json_encode($chatbotSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'cdnUrl' => $cdnUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
    }

    public function hookActionProductUpdate($params)
    {   
        // Check if product sync is enabled
        if (!(bool)Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        $idProduct = $params['id_product'];
        $currentQuantity = (int) StockAvailable::getQuantityAvailableByProduct($idProduct);
    
        if ($currentQuantity == 0) {
            return;
        }

        $lastTimeWeSynch = Db::getInstance()->getValue('SELECT aismarttalk_last_source FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . (int) $params['id_product']);

        $date = new DateTime();
        $date->modify('-3 seconds')->format('Y-m-d H:i:s');
        $lastTimeWeSynch = (new DateTime($lastTimeWeSynch));

        if (empty($lastTimeWeSynch) || ($date > $lastTimeWeSynch)) {
            $idProduct = $params['id_product'];
            $api = new SynchProductsToAiSmartTalk();
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
            $now = new DateTime();
            Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'product SET aismarttalk_last_source = "' . $now->format('Y-m-d H:i:s') . '" WHERE id_product = ' . (int) $params['id_product']);
        }
    }

    public function hookActionProductCreate($params)
    {
        // Check if product sync is enabled
        if (!(bool)Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        $idProduct = $params['id_product'];
        $api = new SynchProductsToAiSmartTalk();
        $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
    }

    public function hookActionProductDelete($params)
    {
        // Check if product sync is enabled
        if (!(bool)Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        $idProduct = $params['id_product'];
        $api = new CleanProductDocuments();
        $api(['productIds' => [(string) $idProduct]]);
    }

    public function hookActionUpdateQuantity($params)
    {
        // Check if product sync is enabled
        if (!(bool)Configuration::get('AI_SMART_TALK_PRODUCT_SYNC')) {
            return;
        }

        if (!isset($params['id_product']) || !isset($params['quantity'])) {
            return;
        }

        $idProduct = $params['id_product'];
        $newQuantity = $params['quantity'];
        
        // Récupérer la quantité actuelle (avant mise à jour)
        $currentQuantity = (int) StockAvailable::getQuantityAvailableByProduct($idProduct);

        // Si le produit passe à 0 stock (rupture), le supprimer d'AI SmartTalk
        if ($newQuantity === 0) {
            $api = new CleanProductDocuments();
            $api(['productIds' => [(string) $idProduct]]);
        }
        // Si le produit passe de 0 à >0 (réapprovisionnement), le synchroniser
        elseif ($currentQuantity == 0 && $newQuantity > 0) {
            $api = new SynchProductsToAiSmartTalk();
            $api(['productIds' => [(string) $idProduct], 'forceSync' => true]);
            $now = new DateTime();
            Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'product SET aismarttalk_last_source = "' . $now->format('Y-m-d H:i:s') . '" WHERE id_product = ' . (int) $idProduct
            );
        }
    }

    private function getApiHost()
    {
        $url = Configuration::get('AI_SMART_TALK_URL');
        
        // Fallback to default if URL is empty or invalid
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = 'https://aismarttalk.tech';
            Configuration::updateValue('AI_SMART_TALK_URL', $url);
        }

        if (strpos($url, 'http://ai-toolkit-node:3000') !== false) {
            $url = str_replace('http://ai-toolkit-node:3000', 'http://localhost:3000', $url);
        }
        return $url;
    }

    private function ensureDefaultUrls()
    {
        $defaultUrl = 'https://aismarttalk.tech';
        $defaultFrontUrl = 'https://aismarttalk.tech';
        $defaultCdn = 'https://cdn.aismarttalk.tech';
        $defaultWs = 'https://ws.223.io.aismarttalk.tech';
        
        $currentUrl = Configuration::get('AI_SMART_TALK_URL');
        $currentFrontUrl = Configuration::get('AI_SMART_TALK_FRONT_URL');
        $currentCdn = Configuration::get('AI_SMART_TALK_CDN');
        $currentWs = Configuration::get('AI_SMART_TALK_WS');
        
        if (empty($currentUrl) || !filter_var($currentUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_URL', $defaultUrl);
        }
        if (empty($currentFrontUrl) || !filter_var($currentFrontUrl, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_FRONT_URL', $defaultFrontUrl);
        }
        if (empty($currentCdn) || !filter_var($currentCdn, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_CDN', $defaultCdn);
        }
        if (empty($currentWs) || !filter_var($currentWs, FILTER_VALIDATE_URL)) {
            Configuration::updateValue('AI_SMART_TALK_WS', $defaultWs);
        }
    }

    /**
     * @deprecated Use displayConfigurationPage instead
     */
    public function displayBackOfficeIframe()
    {
        return ''; // Deprecated, handled by configure.tpl
    }


    private function isConfigured()
    {
        // Check OAuth connection first, then fall back to legacy config
        return OAuthHandler::isConnected() 
            || (!empty(Configuration::get('CHAT_MODEL_ID'))
                && !empty(Configuration::get('CHAT_MODEL_TOKEN')));
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

    private function displayButtons()
    {
        return '';
    }

    private function resetConfiguration()
    {
        // Disconnect OAuth first
        OAuthHandler::disconnect();
        
        Configuration::deleteByName('CHAT_MODEL_ID');
        Configuration::deleteByName('CHAT_MODEL_TOKEN');
        Configuration::deleteByName('AI_SMART_TALK_URL');
        Configuration::deleteByName('AI_SMART_TALK_FRONT_URL');
        Configuration::deleteByName('AI_SMART_TALK_CDN');
        Configuration::deleteByName('AI_SMART_TALK_WS');

        // Reset to default values
        Configuration::updateValue('AI_SMART_TALK_URL', 'https://aismarttalk.tech');
        Configuration::updateValue('AI_SMART_TALK_FRONT_URL', 'https://aismarttalk.tech');
        Configuration::updateValue('AI_SMART_TALK_CDN', 'https://cdn.aismarttalk.tech');
        Configuration::updateValue('AI_SMART_TALK_WS', 'https://ws.223.io.aismarttalk.tech');

        return true;
    }

    private function sync(bool $force = false, $output = '')
    {
        $api = new SynchProductsToAiSmartTalk();
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
        $sync = new CustomerSync();
        $sync->exportCustomerBatch([$customer]);
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        if (!\Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC')) {
            return;
        }
        
        $customer = $params['customer'];
        $sync = new CustomerSync();
        $sync->exportCustomerBatch([$customer]);
    }

    public function hookActionCustomerDelete($params)
    {
        // Implementation for customer deletion sync
        // This would call a different API endpoint to remove the customer from AI SmartTalk
    }
}