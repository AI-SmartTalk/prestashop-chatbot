<?php
/**
 * Copyright (c) 2024 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * OAuth Callback Handler
 * This file handles the redirect from AI SmartTalk after OAuth authorization
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2024 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

// Load PrestaShop configuration
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/vendor/autoload.php';

use PrestaShop\AiSmartTalk\OAuthHandler;

// Get the authorization code and state from the callback
$code = Tools::getValue('code');
$state = Tools::getValue('state');
$error = Tools::getValue('error');
$errorDescription = Tools::getValue('error_description');

// Build the redirect URL to the module configuration page
$adminLink = Context::getContext()->link->getAdminLink('AdminModules', true, [], [
    'configure' => 'aismarttalk',
]);

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
    
    // Store error message in configuration to display in admin
    Configuration::updateValue('AI_SMART_TALK_OAUTH_ERROR', $message);
    
    Tools::redirect($adminLink);
    exit;
}

// Validate required parameters
if (empty($code) || empty($state)) {
    Configuration::updateValue('AI_SMART_TALK_OAUTH_ERROR', 'Missing authorization code or state parameter.');
    Tools::redirect($adminLink);
    exit;
}

// Handle the OAuth callback
$result = OAuthHandler::handleCallback($code, $state);

if ($result['success']) {
    // Clear any previous errors
    Configuration::deleteByName('AI_SMART_TALK_OAUTH_ERROR');
    Configuration::updateValue('AI_SMART_TALK_OAUTH_SUCCESS', $result['message']);
} else {
    Configuration::updateValue('AI_SMART_TALK_OAUTH_ERROR', $result['message']);
}

// Redirect back to the module configuration page
Tools::redirect($adminLink);

