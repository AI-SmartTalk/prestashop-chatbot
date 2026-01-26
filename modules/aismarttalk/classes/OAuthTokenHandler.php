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

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use PrestaShop\PrestaShop\Adapter\Module\Module;

class OAuthTokenHandler extends Module
{
    /**
     * Set OAuth token cookie on customer login
     *
     * @param object $customer The customer object
     */
    public static function setOAuthTokenCookie($customer)
    {
        $response = self::requestUserToken($customer);

        if ($response === false) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: Error retrieving user token on login.',
                3,
                null,
                'OAuthTokenHandler',
                null,
                true
            );
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['userToken'])) {
                $loginCookieLifetime = time() + (int) \Configuration::get('PS_COOKIE_LIFETIME_BO') * 3600;
                setcookie('ai_smarttalk_oauth_token', $responseData['userToken'], $loginCookieLifetime, '/', '', \Tools::usingSecureMode(), true);
                $_COOKIE['ai_smarttalk_oauth_token'] = $responseData['userToken'];
            } else {
                \PrestaShopLogger::addLog(
                    'AI SmartTalk: No userToken found in API response. Response: ' . substr($response, 0, 200),
                    3,
                    null,
                    'OAuthTokenHandler',
                    null,
                    true
                );
            }
        }
    }

    /**
     * Remove OAuth token cookie on customer logout
     */
    public static function unsetOAuthTokenCookie()
    {
        setcookie('ai_smarttalk_oauth_token', '', time() - 3600, '/', '', \Tools::usingSecureMode(), true);
        unset($_COOKIE['ai_smarttalk_oauth_token']);
    }

    /**
     * Get or refresh user token for chatbot auto-login
     * Call this on page load to ensure the token is valid
     *
     * @return string|null The user token or null if not available
     */
    public static function getOrRefreshUserToken()
    {
        // Check if cookie exists and is valid
        if (isset($_COOKIE['ai_smarttalk_oauth_token']) && !empty($_COOKIE['ai_smarttalk_oauth_token'])) {
            return $_COOKIE['ai_smarttalk_oauth_token'];
        }

        // Check if customer is logged in
        $context = \Context::getContext();
        if (!$context->customer || !$context->customer->isLogged()) {
            return null;
        }

        // Customer is logged in but no token - request one
        $response = self::requestUserToken($context->customer);

        if ($response === false) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: Error refreshing user token.',
                3,
                null,
                'OAuthTokenHandler',
                null,
                true
            );
            return null;
        }

        $responseData = json_decode($response, true);
        if (isset($responseData['userToken'])) {
            $loginCookieLifetime = time() + (int) \Configuration::get('PS_COOKIE_LIFETIME_BO') * 3600;
            setcookie('ai_smarttalk_oauth_token', $responseData['userToken'], $loginCookieLifetime, '/', '', \Tools::usingSecureMode(), true);
            $_COOKIE['ai_smarttalk_oauth_token'] = $responseData['userToken'];
            return $responseData['userToken'];
        }

        return null;
    }

    /**
     * Get or refresh employee token for chatbot auto-login in back-office
     *
     * @return string|null The user token or null if not available
     */
    public static function getOrRefreshEmployeeToken()
    {
        // Check if employee cookie exists and is valid
        if (isset($_COOKIE['ai_smarttalk_employee_token']) && !empty($_COOKIE['ai_smarttalk_employee_token'])) {
            return $_COOKIE['ai_smarttalk_employee_token'];
        }

        // Check if employee is logged in
        $context = \Context::getContext();
        if (!$context->employee || !$context->employee->id) {
            return null;
        }

        // Employee is logged in but no token - request one
        $response = self::requestEmployeeToken($context->employee);

        if ($response === false) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: Error refreshing employee token.',
                3,
                null,
                'OAuthTokenHandler',
                null,
                true
            );
            return null;
        }

        $responseData = json_decode($response, true);
        if (isset($responseData['userToken'])) {
            $loginCookieLifetime = time() + (int) \Configuration::get('PS_COOKIE_LIFETIME_BO') * 3600;
            setcookie('ai_smarttalk_employee_token', $responseData['userToken'], $loginCookieLifetime, '/', '', \Tools::usingSecureMode(), true);
            $_COOKIE['ai_smarttalk_employee_token'] = $responseData['userToken'];
            return $responseData['userToken'];
        }

        return null;
    }

    /**
     * Request user token from AI SmartTalk API
     * Uses the v1 API endpoint for auto-login
     *
     * @param object $customer The customer object
     * @return string|false The API response or false on error
     */
    private static function requestUserToken($customer)
    {
        return self::requestTokenForUser($customer->email, (string) $customer->id, $customer->firstname . ' ' . $customer->lastname);
    }

    /**
     * Request employee token from AI SmartTalk API
     * Uses the v1 API endpoint for auto-login
     *
     * @param object $employee The employee object
     * @return string|false The API response or false on error
     */
    private static function requestEmployeeToken($employee)
    {
        // Use 'employee_' prefix to distinguish from customers
        return self::requestTokenForUser($employee->email, 'employee_' . (string) $employee->id, $employee->firstname . ' ' . $employee->lastname);
    }

    /**
     * Request token from AI SmartTalk API for any user type
     *
     * @param string $email User email
     * @param string $id User ID (can be prefixed for employees)
     * @param string $name User full name
     * @return string|false The API response or false on error
     */
    private static function requestTokenForUser($email, $id, $name)
    {
        $apiUrl = OAuthHandler::getBackendApiUrl();
        $chatModelId = OAuthHandler::getChatModelId() ?? \Configuration::get('CHAT_MODEL_ID');
        $chatModelToken = OAuthHandler::getAccessToken() ?? \Configuration::get('CHAT_MODEL_TOKEN');

        if (empty($apiUrl) || empty($chatModelId) || empty($chatModelToken)) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: Missing API credentials for user token request.',
                3,
                null,
                'OAuthTokenHandler',
                null,
                true
            );
            return false;
        }

        $url = rtrim($apiUrl, '/') . '/api/v1/integrations/user-token';
        $data = [
            'email' => $email,
            'id' => $id,
            'name' => $name,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $chatModelToken,
                'x-chat-model-id: ' . $chatModelId,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: User token API request failed. HTTP Code: ' . $httpCode . ' Error: ' . $curlError,
                3,
                null,
                'OAuthTokenHandler',
                null,
                true
            );
            return false;
        }

        return $response;
    }
}
