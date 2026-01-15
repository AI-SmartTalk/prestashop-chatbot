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
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2024 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles OAuth 2.0 with PKCE for AI SmartTalk integration
 */
class OAuthHandler
{
    private const CLIENT_ID = 'prestashop';
    private const OAUTH_SCOPES = 'embed chat sync:products sync:categories';
    
    /**
     * Get the callback URL for OAuth
     * 
     * @return string
     */
    public static function getCallbackUrl(): string
    {
        $shopUrl = \Tools::getShopDomainSsl(true) . __PS_BASE_URI__;
        return rtrim($shopUrl, '/') . 'modules/aismarttalk/oauth-callback.php';
    }
    
    /**
     * Get the frontend-facing API URL (for browser redirects)
     * 
     * @return string
     */
    public static function getFrontendApiUrl(): string
    {
        $frontUrl = \Configuration::get('AI_SMART_TALK_FRONT_URL');
        if (empty($frontUrl)) {
            $frontUrl = \Configuration::get('AI_SMART_TALK_URL');
        }
        if (empty($frontUrl)) {
            $frontUrl = 'https://aismarttalk.tech';
        }
        return rtrim($frontUrl, '/');
    }
    
    /**
     * Get the backend API URL (for server-to-server calls)
     * 
     * @return string
     */
    public static function getBackendApiUrl(): string
    {
        $url = \Configuration::get('AI_SMART_TALK_URL');
        if (empty($url)) {
            $url = 'https://aismarttalk.tech';
        }
        return rtrim($url, '/');
    }
    
    /**
     * Generate a PKCE code verifier (random string, 43-128 chars)
     * 
     * @return string
     */
    public static function generateCodeVerifier(): string
    {
        return bin2hex(random_bytes(32)); // 64 chars
    }
    
    /**
     * Generate a PKCE code challenge from the verifier using SHA256
     * 
     * @param string $codeVerifier
     * @return string
     */
    public static function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }
    
    /**
     * Generate a state token for CSRF protection
     * 
     * @return string
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Store OAuth state in session/cookie
     * 
     * @param string $state
     * @param string $codeVerifier
     */
    public static function storeOAuthState(string $state, string $codeVerifier): void
    {
        // Use PrestaShop cookie for session storage
        $cookie = \Context::getContext()->cookie;
        $cookie->oauth_state = $state;
        $cookie->oauth_code_verifier = $codeVerifier;
        $cookie->write();
    }
    
    /**
     * Get stored OAuth state
     * 
     * @return array|null
     */
    public static function getStoredOAuthState(): ?array
    {
        $cookie = \Context::getContext()->cookie;
        
        if (empty($cookie->oauth_state) || empty($cookie->oauth_code_verifier)) {
            return null;
        }
        
        return [
            'state' => $cookie->oauth_state,
            'code_verifier' => $cookie->oauth_code_verifier,
        ];
    }
    
    /**
     * Clear stored OAuth state
     */
    public static function clearOAuthState(): void
    {
        $cookie = \Context::getContext()->cookie;
        unset($cookie->oauth_state);
        unset($cookie->oauth_code_verifier);
        $cookie->write();
    }
    
    /**
     * Register the redirect URI with AI SmartTalk
     * This should be called during plugin installation or first setup
     * 
     * @return bool
     */
    public static function registerRedirectUri(): bool
    {
        $callbackUrl = self::getCallbackUrl();
        $apiUrl = self::getBackendApiUrl();
        $shopUrl = \Tools::getShopDomainSsl(true) . __PS_BASE_URI__;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/api/oauth/aist/clients/register',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'client_id' => self::CLIENT_ID,
                'redirect_uri' => $callbackUrl,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Origin: ' . rtrim($shopUrl, '/'),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: OAuth redirect URI registered successfully',
                1,
                null,
                'AiSmartTalk',
                null,
                true
            );
            return true;
        }
        
        \PrestaShopLogger::addLog(
            'AI SmartTalk: Failed to register OAuth redirect URI. HTTP Code: ' . $httpCode . ' Response: ' . $result,
            3,
            null,
            'AiSmartTalk',
            null,
            true
        );
        
        return false;
    }
    
    /**
     * Build the authorization URL for OAuth flow
     * 
     * @return string
     */
    public static function buildAuthorizationUrl(): string
    {
        // Generate PKCE values
        $codeVerifier = self::generateCodeVerifier();
        $codeChallenge = self::generateCodeChallenge($codeVerifier);
        $state = self::generateState();
        
        // Store for callback validation
        self::storeOAuthState($state, $codeVerifier);
        
        // Build authorization URL using frontend URL (for browser redirect)
        $frontendUrl = self::getFrontendApiUrl();
        
        $params = [
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::getCallbackUrl(),
            'response_type' => 'code',
            'scope' => self::OAUTH_SCOPES,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        
        return $frontendUrl . '/api/oauth/aist/authorize?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code The authorization code
     * @param string $codeVerifier The PKCE code verifier
     * @return array|null Token data or null on failure
     */
    public static function exchangeCodeForToken(string $code, string $codeVerifier): ?array
    {
        $apiUrl = self::getBackendApiUrl();
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/api/oauth/aist/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::getCallbackUrl(),
                'client_id' => self::CLIENT_ID,
                'code_verifier' => $codeVerifier,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($result)) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: Failed to exchange code for token. HTTP Code: ' . $httpCode . ' Response: ' . $result,
                3,
                null,
                'AiSmartTalk',
                null,
                true
            );
            return null;
        }
        
        $tokenData = json_decode($result, true);
        
        if (!isset($tokenData['access_token']) || !isset($tokenData['chat_model_id'])) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk: Token response missing required fields. Response: ' . $result,
                3,
                null,
                'AiSmartTalk',
                null,
                true
            );
            return null;
        }
        
        return $tokenData;
    }
    
    /**
     * Handle the OAuth callback
     * 
     * @param string $code The authorization code
     * @param string $state The state for CSRF validation
     * @return array Result with success status and message
     */
    public static function handleCallback(string $code, string $state): array
    {
        // Validate state
        $storedState = self::getStoredOAuthState();
        
        if ($storedState === null) {
            return [
                'success' => false,
                'message' => 'OAuth state not found. Please try connecting again.',
            ];
        }
        
        if ($storedState['state'] !== $state) {
            self::clearOAuthState();
            return [
                'success' => false,
                'message' => 'Invalid OAuth state (CSRF validation failed). Please try again.',
            ];
        }
        
        // Exchange code for token
        $tokenData = self::exchangeCodeForToken($code, $storedState['code_verifier']);
        
        // Clear OAuth state regardless of result
        self::clearOAuthState();
        
        if ($tokenData === null) {
            return [
                'success' => false,
                'message' => 'Failed to exchange authorization code for access token.',
            ];
        }
        
        // Store the credentials in PrestaShop configuration
        \Configuration::updateValue('AI_SMART_TALK_ACCESS_TOKEN', $tokenData['access_token']);
        \Configuration::updateValue('AI_SMART_TALK_CHAT_MODEL_ID', $tokenData['chat_model_id']);
        \Configuration::updateValue('AI_SMART_TALK_OAUTH_SCOPE', $tokenData['scope'] ?? self::OAUTH_SCOPES);
        \Configuration::updateValue('AI_SMART_TALK_OAUTH_CONNECTED', true);
        
        // For backward compatibility, also update the old config keys
        \Configuration::updateValue('CHAT_MODEL_ID', $tokenData['chat_model_id']);
        \Configuration::updateValue('CHAT_MODEL_TOKEN', $tokenData['access_token']);
        
        \PrestaShopLogger::addLog(
            'AI SmartTalk: OAuth connection successful. Chat Model ID: ' . $tokenData['chat_model_id'],
            1,
            null,
            'AiSmartTalk',
            null,
            true
        );
        
        return [
            'success' => true,
            'message' => 'Successfully connected to AI SmartTalk!',
            'chat_model_id' => $tokenData['chat_model_id'],
        ];
    }
    
    /**
     * Revoke the current OAuth token and disconnect
     * 
     * @return bool
     */
    public static function disconnect(): bool
    {
        $accessToken = \Configuration::get('AI_SMART_TALK_ACCESS_TOKEN');
        
        if (!empty($accessToken)) {
            // Revoke token on the server
            $apiUrl = self::getBackendApiUrl();
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl . '/api/oauth/aist/revoke',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'token' => $accessToken,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        }
        
        // Clear local credentials
        \Configuration::deleteByName('AI_SMART_TALK_ACCESS_TOKEN');
        \Configuration::deleteByName('AI_SMART_TALK_CHAT_MODEL_ID');
        \Configuration::deleteByName('AI_SMART_TALK_OAUTH_SCOPE');
        \Configuration::deleteByName('AI_SMART_TALK_OAUTH_CONNECTED');
        \Configuration::deleteByName('CHAT_MODEL_ID');
        \Configuration::deleteByName('CHAT_MODEL_TOKEN');
        
        \PrestaShopLogger::addLog(
            'AI SmartTalk: OAuth disconnected',
            1,
            null,
            'AiSmartTalk',
            null,
            true
        );
        
        return true;
    }
    
    /**
     * Check if the module is connected via OAuth
     * 
     * @return bool
     */
    public static function isConnected(): bool
    {
        return (bool) \Configuration::get('AI_SMART_TALK_OAUTH_CONNECTED')
            && !empty(\Configuration::get('AI_SMART_TALK_ACCESS_TOKEN'))
            && !empty(\Configuration::get('AI_SMART_TALK_CHAT_MODEL_ID'));
    }
    
    /**
     * Get the current access token
     * 
     * @return string|null
     */
    public static function getAccessToken(): ?string
    {
        $token = \Configuration::get('AI_SMART_TALK_ACCESS_TOKEN');
        return !empty($token) ? $token : null;
    }
    
    /**
     * Get the current chat model ID
     * 
     * @return string|null
     */
    public static function getChatModelId(): ?string
    {
        $id = \Configuration::get('AI_SMART_TALK_CHAT_MODEL_ID');
        return !empty($id) ? $id : null;
    }
    
    /**
     * Introspect the current token to verify it's still valid
     * 
     * @return array|null Token info or null if invalid
     */
    public static function introspectToken(): ?array
    {
        $accessToken = self::getAccessToken();
        
        if (empty($accessToken)) {
            return null;
        }
        
        $apiUrl = self::getBackendApiUrl();
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/api/oauth/aist/introspect',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'token' => $accessToken,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($result, true);
        
        if (!isset($data['active']) || !$data['active']) {
            return null;
        }
        
        return $data;
    }
}

