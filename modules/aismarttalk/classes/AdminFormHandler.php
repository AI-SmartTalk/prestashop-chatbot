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
 * Handles admin form submissions for the AI SmartTalk module.
 * Extracts form handling logic from AiSmartTalk::getContent() for better testability.
 */
class AdminFormHandler
{
    /** @var \AiSmartTalk The module instance */
    private $module;

    /** @var \Context PrestaShop context */
    private $context;

    public function __construct(\AiSmartTalk $module, \Context $context)
    {
        $this->module = $module;
        $this->context = $context;
    }

    /**
     * Translate a string using the module's public l() method.
     * Compatible with PrestaShop 1.7, 8, and 9.
     *
     * @param string $key     The translation key
     * @param array  $params  Translation parameters (e.g. ['%count%' => 5])
     * @param string $domain  Unused, kept for call-site compatibility
     * @return string
     */
    private function trans(string $key, array $params = [], string $domain = ''): string
    {
        $translated = $this->module->l($key);

        // Apply parameter replacements
        foreach ($params as $placeholder => $value) {
            $translated = str_replace($placeholder, (string) $value, $translated);
        }

        return $translated;
    }

    /**
     * Process all form submissions and return HTML messages.
     *
     * @return string HTML output (confirmations, errors, warnings)
     */
    public function processAll(): string
    {
        $output = '';

        $output .= $this->processOAuthMessages();
        $output .= $this->processActions();
        $output .= $this->processFormSubmissions();

        return $output;
    }

    /**
     * Process OAuth success/error messages from callback.
     *
     * @return string
     */
    private function processOAuthMessages(): string
    {
        $output = '';

        $oauthSuccess = \Configuration::get('AI_SMART_TALK_OAUTH_SUCCESS');
        $oauthError = \Configuration::get('AI_SMART_TALK_OAUTH_ERROR');

        if (!empty($oauthSuccess)) {
            $output .= $this->module->displayConfirmation($oauthSuccess);
            \Configuration::deleteByName('AI_SMART_TALK_OAUTH_SUCCESS');
        }

        if (!empty($oauthError)) {
            $output .= $this->module->displayError($oauthError);
            \Configuration::deleteByName('AI_SMART_TALK_OAUTH_ERROR');
        }

        return $output;
    }

    /**
     * Process URL-based actions (non-form submissions).
     *
     * @return string
     */
    private function processActions(): string
    {
        $output = '';

        // Display flash messages from previous action redirect
        $output .= $this->displayFlashMessages();

        if (\Tools::getValue('resetConfiguration') === $this->module->name) {
            $this->resetConfiguration();
        }

        if (\Tools::getValue('disconnectOAuth')) {
            OAuthHandler::disconnect();
            $this->flashAndRedirect($this->module->displayConfirmation(
                $this->trans('Successfully disconnected from AI SmartTalk.', [], 'Modules.Aismarttalk.Admin')
            ));
        }

        if (\Tools::getValue('connectOAuth')) {
            $this->handleOAuthConnect();
            // This method calls exit after redirect
        }

        if (\Tools::getValue('forceSync')) {
            $force = \Tools::getValue('forceSync') === 'true';
            $this->flashAndRedirect($this->handleProductSync($force));
        }

        if (\Tools::getValue('syncCustomers')) {
            $this->flashAndRedirect($this->handleCustomerSync());
        }

        if (\Tools::getValue('clean')) {
            (new CleanProductDocuments())();
            $this->flashAndRedirect($this->module->displayConfirmation(
                $this->trans('Deleted and inactive products have been cleaned.', [], 'Modules.Aismarttalk.Admin')
            ));
        }

        if (\Tools::getValue('refreshEmbedConfig')) {
            $this->flashAndRedirect($this->handleRefreshEmbedConfig());
        }

        if (\Tools::getValue('resetWhiteLabel')) {
            \Configuration::updateValue('AI_SMART_TALK_URL', \AiSmartTalk::DEFAULT_API_URL);
            \Configuration::updateValue('AI_SMART_TALK_FRONT_URL', \AiSmartTalk::DEFAULT_API_URL);
            \Configuration::updateValue('AI_SMART_TALK_CDN', \AiSmartTalk::DEFAULT_CDN_URL);
            \Configuration::updateValue('AI_SMART_TALK_WS', \AiSmartTalk::DEFAULT_WS_URL);
            OAuthHandler::registerRedirectUri($this->context);
            $this->flashAndRedirect($this->module->displayConfirmation(
                $this->trans('URLs reset to default values.', [], 'Modules.Aismarttalk.Admin')
            ));
        }

        if (\Tools::getValue('resetLocalCustomizations')) {
            $this->clearLocalCustomizations();
            AiSmartTalkCache::delete('embed_config');
            $this->flashAndRedirect($this->module->displayConfirmation(
                $this->trans('Local customizations cleared. Using AI SmartTalk defaults.', [], 'Modules.Aismarttalk.Admin')
            ));
        }

        return $output;
    }

    /**
     * Process all form submissions.
     *
     * @return string
     */
    private function processFormSubmissions(): string
    {
        $output = '';

        if (\Tools::isSubmit('submitToggleChatbot')) {
            $output .= $this->handleToggleChatbot();
        }

        if (\Tools::isSubmit('submitWhiteLabel')) {
            $output .= $this->handleWhiteLabel();
        }

        if (\Tools::isSubmit('submitIframePosition')) {
            $output .= $this->handleIframePosition();
        }

        if (\Tools::isSubmit('submitProductSync')) {
            $output .= $this->handleProductSyncToggle();
        }

        if (\Tools::isSubmit('submitCustomerSync')) {
            $output .= $this->handleCustomerSyncToggle();
        }

        if (\Tools::isSubmit('submitChatbotSettings')) {
            $output .= $this->handleChatbotSettings();
        }

        if (\Tools::isSubmit('submitChatbotCustomization')) {
            $output .= $this->handleChatbotCustomization();
        }

        if (\Tools::isSubmit('submitSyncSettings')) {
            $output .= $this->handleSyncSettings();
        }

        if (\Tools::isSubmit('submitWebhooksSettings')) {
            $output .= $this->handleWebhooksSettings();
        }

        $output .= $this->handleLegacyForm();

        return $output;
    }

    // =========================================================================
    // Form Handlers
    // =========================================================================

    private function handleToggleChatbot(): string
    {
        $chatbotEnabled = (bool) \Tools::getValue('AI_SMART_TALK_ENABLED');
        \Configuration::updateValue('AI_SMART_TALK_ENABLED', $chatbotEnabled);

        return $this->module->displayConfirmation(
            $this->trans('Settings updated.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleChatbotSettings(): string
    {
        $chatbotEnabled = (bool) \Tools::getValue('AI_SMART_TALK_ENABLED');
        $position = \Tools::getValue('AI_SMART_TALK_IFRAME_POSITION');

        if (!in_array($position, ['footer', 'before_footer'])) {
            $position = 'footer';
        }

        \Configuration::updateValue('AI_SMART_TALK_ENABLED', $chatbotEnabled);
        \Configuration::updateValue('AI_SMART_TALK_IFRAME_POSITION', $position);

        return $this->module->displayConfirmation(
            $this->trans('Chatbot settings saved.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleChatbotCustomization(): string
    {
        $output = '';

        // Button settings
        $buttonText = \Tools::getValue('AI_SMART_TALK_BUTTON_TEXT', '');
        $buttonType = \Tools::getValue('AI_SMART_TALK_BUTTON_TYPE', '');
        $buttonPosition = \Tools::getValue('AI_SMART_TALK_BUTTON_POSITION', '');

        // Validate button type
        $validButtonTypes = ['', 'default', 'icon', 'avatar', 'minimal'];
        if (!in_array($buttonType, $validButtonTypes)) {
            $buttonType = '';
        }

        // Handle avatar file upload (uploaded to platform API, not stored locally)
        if (isset($_FILES['AI_SMART_TALK_AVATAR_FILE']) && $_FILES['AI_SMART_TALK_AVATAR_FILE']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->uploadChatModelAvatarFile($_FILES['AI_SMART_TALK_AVATAR_FILE']);
            if ($uploadResult['success']) {
                // Clear any stale local avatar URL — the platform is the source of truth
                \Configuration::updateValue('AI_SMART_TALK_AVATAR_URL', '');
                AiSmartTalkCache::delete('embed_config');
                AiSmartTalkCache::delete('chat_model_info');
                $output .= $this->module->displayConfirmation(
                    $this->trans('Avatar uploaded successfully.', [], 'Modules.Aismarttalk.Admin')
                );
            } else {
                $output .= $this->module->displayError(
                    $this->trans('Avatar upload failed: ', [], 'Modules.Aismarttalk.Admin') . $uploadResult['message']
                );
            }
        }

        // Validate button position
        $validPositions = ['', 'bottom-right', 'bottom-left'];
        if (!in_array($buttonPosition, $validPositions)) {
            $buttonPosition = '';
        }

        // Layout settings
        $chatSize = \Tools::getValue('AI_SMART_TALK_CHAT_SIZE', '');
        $validSizes = ['', 'small', 'medium', 'large', 'xlarge', 'full'];
        if (!in_array($chatSize, $validSizes)) {
            $chatSize = '';
        }

        // Color settings
        $colorMode = \Tools::getValue('AI_SMART_TALK_COLOR_MODE', '');
        $validColorModes = ['', 'light', 'dark', 'auto'];
        if (!in_array($colorMode, $validColorModes)) {
            $colorMode = '';
        }

        $borderRadius = \Tools::getValue('AI_SMART_TALK_BORDER_RADIUS', '');
        $validBorderRadius = ['', 'rounded', 'slightly-rounded', 'square'];
        if (!in_array($borderRadius, $validBorderRadius)) {
            $borderRadius = '';
        }

        $buttonBorderRadius = \Tools::getValue('AI_SMART_TALK_BUTTON_BORDER_RADIUS', '');
        if (!in_array($buttonBorderRadius, $validBorderRadius)) {
            $buttonBorderRadius = '';
        }

        $primaryColor = \Tools::getValue('AI_SMART_TALK_PRIMARY_COLOR', '');
        $secondaryColor = \Tools::getValue('AI_SMART_TALK_SECONDARY_COLOR', '');

        // Validate hex colors
        if (!empty($primaryColor) && !preg_match('/^#[a-fA-F0-9]{6}$/', $primaryColor)) {
            $primaryColor = '';
        }
        if (!empty($secondaryColor) && !preg_match('/^#[a-fA-F0-9]{6}$/', $secondaryColor)) {
            $secondaryColor = '';
        }

        // Feature toggles
        $validToggleValues = ['', 'on', 'off'];
        $toggleFields = [
            'AI_SMART_TALK_ENABLE_ATTACHMENT',
            'AI_SMART_TALK_ENABLE_FEEDBACK',
            'AI_SMART_TALK_ENABLE_VOICE_INPUT',
            'AI_SMART_TALK_ENABLE_VOICE_MODE',
            'AI_SMART_TALK_ENABLE_AUTO_LOGIN',
        ];

        $toggleValues = [];
        foreach ($toggleFields as $field) {
            $value = \Tools::getValue($field, '');
            if (!in_array($value, $validToggleValues)) {
                $value = '';
            }
            $toggleValues[$field] = $value;
        }

        // Save all customization settings
        // Note: AI_SMART_TALK_AVATAR_URL is not saved here — avatar is managed on the platform
        \Configuration::updateValue('AI_SMART_TALK_BUTTON_TEXT', pSQL($buttonText));
        \Configuration::updateValue('AI_SMART_TALK_BUTTON_TYPE', $buttonType);
        \Configuration::updateValue('AI_SMART_TALK_BUTTON_POSITION', $buttonPosition);
        \Configuration::updateValue('AI_SMART_TALK_CHAT_SIZE', $chatSize);
        \Configuration::updateValue('AI_SMART_TALK_COLOR_MODE', $colorMode);
        \Configuration::updateValue('AI_SMART_TALK_BORDER_RADIUS', $borderRadius);
        \Configuration::updateValue('AI_SMART_TALK_BUTTON_BORDER_RADIUS', $buttonBorderRadius);
        \Configuration::updateValue('AI_SMART_TALK_PRIMARY_COLOR', $primaryColor);
        \Configuration::updateValue('AI_SMART_TALK_SECONDARY_COLOR', $secondaryColor);

        foreach ($toggleValues as $field => $value) {
            \Configuration::updateValue($field, $value);
        }

        // GDPR settings
        $gdprEnabled = \Tools::getValue('AI_SMART_TALK_GDPR_ENABLED', '');
        $gdprPrivacyUrl = \Tools::getValue('AI_SMART_TALK_GDPR_PRIVACY_URL', '');

        if (!in_array($gdprEnabled, $validToggleValues)) {
            $gdprEnabled = '';
        }

        if (!empty($gdprPrivacyUrl) && !filter_var($gdprPrivacyUrl, FILTER_VALIDATE_URL)) {
            $gdprPrivacyUrl = '';
            $output .= $this->module->displayWarning(
                $this->trans('Invalid privacy policy URL - must be a valid URL starting with http:// or https://', [], 'Modules.Aismarttalk.Admin')
            );
        }

        \Configuration::updateValue('AI_SMART_TALK_GDPR_ENABLED', $gdprEnabled);
        \Configuration::updateValue('AI_SMART_TALK_GDPR_PRIVACY_URL', pSQL($gdprPrivacyUrl));

        // GDPR Consent Wall settings
        $consentWallEnabled = \Tools::getValue('AI_SMART_TALK_CONSENT_WALL_ENABLED', '');
        if (!in_array($consentWallEnabled, $validToggleValues)) {
            $consentWallEnabled = '';
        }
        \Configuration::updateValue('AI_SMART_TALK_CONSENT_WALL_ENABLED', $consentWallEnabled);

        $consentWallMessage = \Tools::getValue('AI_SMART_TALK_CONSENT_WALL_MESSAGE', '');
        \Configuration::updateValue('AI_SMART_TALK_CONSENT_WALL_MESSAGE', pSQL($consentWallMessage));

        $output .= $this->module->displayConfirmation(
            $this->trans('Chatbot customization saved.', [], 'Modules.Aismarttalk.Admin')
        );

        return $output;
    }

    private function handleSyncSettings(): string
    {
        $consentFilter = \Tools::getValue('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT', 'all');
        $validConsentValues = ['all', 'newsletter', 'optin', 'newsletter_or_optin', 'newsletter_and_optin'];
        if (!in_array($consentFilter, $validConsentValues)) {
            $consentFilter = 'all';
        }
        \Configuration::updateValue('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT', $consentFilter);

        // Handle sync filters in the same form submission
        $categoryMode = \Tools::getValue('sync_filter_category_mode', '');
        if ($categoryMode !== '') {
            $filterConfig = [
                'mode' => ($categoryMode === 'exclude') ? SyncFilterHelper::MODE_EXCLUDE : SyncFilterHelper::MODE_INCLUDE,
                'categories' => ($categoryMode === 'all') ? [] : \Tools::getValue('sync_filter_categories', []),
                'include_subcategories' => false,
            ];

            if (is_string($filterConfig['categories'])) {
                $decoded = json_decode($filterConfig['categories'], true);
                $filterConfig['categories'] = is_array($decoded) ? $decoded : [];
            }

            SyncFilterHelper::saveFilterConfig($filterConfig);
        }

        return $this->module->displayConfirmation(
            $this->trans('Settings saved.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleWebhooksSettings(): string
    {
        $enabledTriggers = \Tools::getValue('webhooks_triggers', []);
        WebhookHandler::saveEnabledTriggers(is_array($enabledTriggers) ? $enabledTriggers : []);

        return $this->module->displayConfirmation(
            $this->trans('Webhooks settings saved.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleWhiteLabel(): string
    {
        $url = \Tools::getValue('AI_SMART_TALK_URL');
        $cdn = \Tools::getValue('AI_SMART_TALK_CDN');
        $ws = \Tools::getValue('AI_SMART_TALK_WS');

        $defaultUrl = \AiSmartTalk::DEFAULT_API_URL;
        $defaultCdn = \AiSmartTalk::DEFAULT_CDN_URL;
        $defaultWs = \AiSmartTalk::DEFAULT_WS_URL;

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = $defaultUrl;
        }
        if (empty($cdn) || !filter_var($cdn, FILTER_VALIDATE_URL)) {
            $cdn = $defaultCdn;
        }
        if (empty($ws)) {
            $ws = $defaultWs;
        }

        \Configuration::updateValue('AI_SMART_TALK_URL', $url);
        \Configuration::updateValue('AI_SMART_TALK_FRONT_URL', $url);
        \Configuration::updateValue('AI_SMART_TALK_CDN', $cdn);
        \Configuration::updateValue('AI_SMART_TALK_WS', $ws);

        // Re-register OAuth redirect URI with potentially new URLs
        OAuthHandler::registerRedirectUri($this->context);

        return $this->module->displayConfirmation(
            $this->trans('WhiteLabel settings updated.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleIframePosition(): string
    {
        $position = \Tools::getValue('AI_SMART_TALK_IFRAME_POSITION');
        if (!in_array($position, ['footer', 'before_footer'])) {
            $position = 'footer';
        }
        \Configuration::updateValue('AI_SMART_TALK_IFRAME_POSITION', $position);

        return $this->module->displayConfirmation(
            $this->trans('Iframe position updated.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleProductSyncToggle(): string
    {
        $productSyncEnabled = (bool) \Tools::getValue('AI_SMART_TALK_PRODUCT_SYNC');
        \Configuration::updateValue('AI_SMART_TALK_PRODUCT_SYNC', $productSyncEnabled);

        return $this->module->displayConfirmation(
            $productSyncEnabled
                ? $this->trans('Product sync enabled.', [], 'Modules.Aismarttalk.Admin')
                : $this->trans('Product sync disabled.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleCustomerSyncToggle(): string
    {
        $syncEnabled = (bool) \Tools::getValue('AI_SMART_TALK_CUSTOMER_SYNC');
        \Configuration::updateValue('AI_SMART_TALK_CUSTOMER_SYNC', $syncEnabled);

        return $this->module->displayConfirmation(
            $syncEnabled
                ? $this->trans('Customer sync enabled.', [], 'Modules.Aismarttalk.Admin')
                : $this->trans('Customer sync disabled.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    private function handleLegacyForm(): string
    {
        $output = '';

        if (\Tools::isSubmit('submit' . $this->module->name)) {
            \Configuration::updateValue('CHAT_MODEL_ID', \Tools::getValue('CHAT_MODEL_ID'));
            \Configuration::updateValue('CHAT_MODEL_TOKEN', \Tools::getValue('CHAT_MODEL_TOKEN'));

            $output .= $this->module->displayConfirmation(
                $this->trans('Settings updated. You can now enable product synchronization or use CSV import in AI SmartTalk.', [], 'Modules.Aismarttalk.Admin')
            );
        }

        return $output;
    }

    // =========================================================================
    // Action Handlers
    // =========================================================================

    private function handleOAuthConnect(): void
    {
        OAuthHandler::registerRedirectUri($this->context);

        $returnUrl = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->module->name,
        ]);

        $authUrl = OAuthHandler::buildAuthorizationUrl($this->context, $returnUrl);
        \Tools::redirect($authUrl);
        exit;
    }

    private function handleProductSync(bool $force): string
    {
        $output = '';
        $api = new SynchProductsToAiSmartTalk($this->context);
        $result = $api(['forceSync' => $force]);

        if ($result === false) {
            $output .= $this->module->displayError(
                $this->trans('An error occurred during synchronization with the API.', [], 'Modules.Aismarttalk.Admin')
            );
            $error = \Configuration::get('AI_SMART_TALK_ERROR');
            if ($error) {
                $output .= $this->module->displayError($error);
            }
        } elseif ($result === 0) {
            $output .= $this->module->displayWarning(
                $this->trans('No products found to synchronize. Check that your products are active, in stock, and match your sync filters.', [], 'Modules.Aismarttalk.Admin')
            );
        } else {
            if ($force) {
                $output .= $this->module->displayConfirmation(
                    $this->trans('%count% products have been synchronized with the API.', ['%count%' => (int) $result], 'Modules.Aismarttalk.Admin')
                );
            } else {
                $output .= $this->module->displayConfirmation(
                    $this->trans('%count% new products have been synchronized with the API.', ['%count%' => (int) $result], 'Modules.Aismarttalk.Admin')
                );
            }
        }

        return $output;
    }

    private function handleCustomerSync(): string
    {
        $output = '';
        AiSmartTalkCustomerSync::createTable();

        $sync = new CustomerSync($this->context);
        $result = $sync->syncAllCustomers();

        if ($result['success']) {
            $synced = isset($result['synced']) ? (int) $result['synced'] : 0;
            $removed = isset($result['removed']) ? (int) $result['removed'] : 0;
            $msg = sprintf($this->module->l('Customer sync completed: %d synced, %d removed.'), $synced, $removed);
            $output .= $this->module->displayConfirmation($msg);
        } else {
            $output .= $this->module->displayError($this->module->l('Customer sync failed. Please check the logs.'));
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $output .= $this->module->displayError($error);
                }
            }
        }

        return $output;
    }

    private function handleRefreshEmbedConfig(): string
    {
        AiSmartTalkCache::delete('embed_config');
        AiSmartTalkCache::delete('chat_model_info');

        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return $this->module->displayError(
                $this->trans('Failed to synchronize configuration from AI SmartTalk.', [], 'Modules.Aismarttalk.Admin')
            );
        }

        $chatModelId = $client->getChatModelId();
        $response = $client->get(
            '/api/public/chatModel/' . urlencode($chatModelId) . '/embed-config?integrationType=PRESTASHOP',
            3
        );

        if ($response->isSuccess() && $response->get('data')) {
            AiSmartTalkCache::set('embed_config', $response->get('data'), 3600);

            return $this->module->displayConfirmation(
                $this->trans('Configuration synchronized from AI SmartTalk.', [], 'Modules.Aismarttalk.Admin')
            );
        }

        return $this->module->displayError(
            $this->trans('Failed to synchronize configuration from AI SmartTalk.', [], 'Modules.Aismarttalk.Admin')
        );
    }

    // =========================================================================
    // Flash message helpers (PRG pattern)
    // =========================================================================

    /**
     * Store HTML message(s) in Configuration and redirect to clean URL.
     * Prevents action re-execution on browser refresh.
     *
     * @param string $html Rendered HTML from displayConfirmation/displayError/displayWarning
     */
    private function flashAndRedirect(string $html): void
    {
        \Configuration::updateValue('AI_SMART_TALK_FLASH_MSG', $html, true);

        $redirectUrl = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->module->name,
        ]);
        \Tools::redirect($redirectUrl);
        exit;
    }

    /**
     * Display and clear any stored flash messages.
     *
     * @return string HTML output
     */
    private function displayFlashMessages(): string
    {
        $html = \Configuration::get('AI_SMART_TALK_FLASH_MSG');
        if (!empty($html)) {
            \Configuration::deleteByName('AI_SMART_TALK_FLASH_MSG');

            return (string) $html;
        }

        return '';
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Upload an avatar file to the chat model via API.
     *
     * @param array $file The uploaded file from $_FILES
     * @return array Result with 'success' and 'avatarUrl' or 'message'
     */
    private function uploadChatModelAvatarFile(array $file): array
    {
        $client = ApiClient::fromConfig();
        if (!$client->hasCredentials()) {
            return ['success' => false, 'message' => 'Missing credentials'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
        }

        // Validate MIME type using actual file content
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

        $chatModelId = $client->getChatModelId();
        $cfile = new \CURLFile($file['tmp_name'], $mimeType, $file['name']);

        $response = $client->upload(
            '/api/v1/chatModel/' . urlencode($chatModelId) . '/avatar',
            $cfile,
            60
        );

        if ($response->isSuccess() && $response->get('success') && $response->get('data.avatarUrl')) {
            return [
                'success' => true,
                'avatarUrl' => $response->get('data.avatarUrl'),
            ];
        }

        // Build user-friendly error message
        $errorMessage = $response->error ?: ($response->get('message') ?? 'Unknown error');
        if ($response->httpCode === 413) {
            $errorMessage = 'File too large for server. Try a smaller image (max 5MB).';
        } elseif ($response->httpCode === 400) {
            $errorMessage = 'Invalid file format. Allowed: JPEG, PNG, GIF, WebP.';
        } elseif ($response->httpCode === 401 || $response->httpCode === 403) {
            $errorMessage = 'Authentication failed. Please reconnect your AI SmartTalk account.';
        } elseif ($response->httpCode === 500) {
            $errorMessage = 'Server error. Please try again later.';
        }

        \PrestaShopLogger::addLog(
            'AI SmartTalk: Failed to upload avatar. HTTP Code: ' . $response->httpCode . ' Error: ' . ($response->error ?? ''),
            3, null, 'AiSmartTalk', null, true
        );

        return ['success' => false, 'message' => $errorMessage];
    }

    private function resetConfiguration(): void
    {
        OAuthHandler::disconnect();

        \Configuration::deleteByName('CHAT_MODEL_ID');
        \Configuration::deleteByName('CHAT_MODEL_TOKEN');

        \Configuration::updateValue('AI_SMART_TALK_URL', \AiSmartTalk::DEFAULT_API_URL);
        \Configuration::updateValue('AI_SMART_TALK_FRONT_URL', \AiSmartTalk::DEFAULT_API_URL);
        \Configuration::updateValue('AI_SMART_TALK_CDN', \AiSmartTalk::DEFAULT_CDN_URL);
        \Configuration::updateValue('AI_SMART_TALK_WS', \AiSmartTalk::DEFAULT_WS_URL);

        AiSmartTalkCache::clearAll();
    }

    private function clearLocalCustomizations(): void
    {
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
            \Configuration::deleteByName($setting);
        }

        AiSmartTalkCache::clearAll();
    }
}
