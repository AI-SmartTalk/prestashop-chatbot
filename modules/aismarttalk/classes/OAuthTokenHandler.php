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

class OAuthTokenHandler
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
                    'AI SmartTalk: No userToken found in API response.',
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
     * Works for both front-office customers and back-office employees.
     *
     * @return string|null The user token or null if not available
     */
    public static function getOrRefreshUserToken()
    {
        // Check if cookie exists and is valid
        if (isset($_COOKIE['ai_smarttalk_oauth_token']) && !empty($_COOKIE['ai_smarttalk_oauth_token'])) {
            return $_COOKIE['ai_smarttalk_oauth_token'];
        }

        $context = \Context::getContext();

        // Check if customer is logged in (front-office)
        if ($context->customer && $context->customer->isLogged()) {
            $response = self::requestUserToken($context->customer);
        // Check if employee is logged in (back-office)
        } elseif ($context->employee && $context->employee->id) {
            $response = self::requestTokenForUser(
                $context->employee->email,
                'employee_' . (string) $context->employee->id,
                $context->employee->firstname . ' ' . $context->employee->lastname
            );
        } else {
            return null;
        }

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
     * Request token from AI SmartTalk API for any user type
     *
     * @param string $email User email
     * @param string $id User ID (can be prefixed for employees)
     * @param string $name User full name
     * @return string|false The API response or false on error
     */
    private static function requestTokenForUser($email, $id, $name)
    {
        $client = ApiClient::fromConfig();

        if (!$client->hasCredentials()) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: Missing API credentials for user token request.',
                3, null, 'OAuthTokenHandler', null, true
            );
            return false;
        }

        $response = $client->post('/api/v1/integrations/user-token', [
            'email' => $email,
            'id' => $id,
            'name' => $name,
        ], 3);

        if (!$response->isSuccess()) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: User token API request failed. HTTP Code: ' . $response->httpCode . ' Error: ' . ($response->error ?? ''),
                3, null, 'OAuthTokenHandler', null, true
            );
            return false;
        }

        return $response->body;
    }
}
