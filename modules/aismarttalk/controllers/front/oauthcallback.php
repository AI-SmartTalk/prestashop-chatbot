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
 * OAuth Callback Front Controller
 * This controller handles the redirect from AI SmartTalk after OAuth authorization
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2026 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'aismarttalk/vendor/autoload.php';

use PrestaShop\AiSmartTalk\OAuthHandler;

class AismarttalkOauthcallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;
    public $guestAllowed = true;

    public function initContent()
    {
        parent::initContent();
        
        // Get the authorization code and state from the callback
        $code = Tools::getValue('code');
        $state = Tools::getValue('state');
        $error = Tools::getValue('error');
        $errorDescription = Tools::getValue('error_description');

        // Default fallback URL (will be overridden by stored return_url)
        $redirectUrl = $this->context->link->getPageLink('index');

        // Handle OAuth errors from the provider
        if (!empty($error)) {
            $message = !empty($errorDescription) ? $errorDescription : 'Authorization was denied or failed.';
            PrestaShopLogger::addLog(
                'AI SmartTalk OAuth error: ' . $error . ' - ' . $message,
                3,
                null,
                'AiSmartTalk',
                null,
                true
            );
            
            // Try to get return URL from stored state
            $storedState = OAuthHandler::getStoredOAuthState();
            if ($storedState && !empty($storedState['return_url'])) {
                $redirectUrl = $storedState['return_url'];
            }
            OAuthHandler::clearOAuthState();
            
            // Store error message in configuration to display in admin
            Configuration::updateValue('AI_SMART_TALK_OAUTH_ERROR', $message);
            
            Tools::redirect($redirectUrl);
            exit;
        }

        // Validate required parameters
        if (empty($code) || empty($state)) {
            // Try to get return URL from stored state
            $storedState = OAuthHandler::getStoredOAuthState();
            if ($storedState && !empty($storedState['return_url'])) {
                $redirectUrl = $storedState['return_url'];
            }
            OAuthHandler::clearOAuthState();
            
            Configuration::updateValue('AI_SMART_TALK_OAUTH_ERROR', 'Missing authorization code or state parameter.');
            Tools::redirect($redirectUrl);
            exit;
        }

        // Handle the OAuth callback
        $result = OAuthHandler::handleCallback($code, $state);

        // Use the return URL from the result
        if (!empty($result['return_url'])) {
            $redirectUrl = $result['return_url'];
        }

        if ($result['success']) {
            // Clear any previous errors
            Configuration::deleteByName('AI_SMART_TALK_OAUTH_ERROR');
            Configuration::updateValue('AI_SMART_TALK_OAUTH_SUCCESS', $result['message']);
        } else {
            Configuration::updateValue('AI_SMART_TALK_OAUTH_ERROR', $result['message']);
        }

        // Redirect back to the stored admin URL
        Tools::redirect($redirectUrl);
    }
}

