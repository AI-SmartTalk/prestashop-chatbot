<?php
/**
 * Tests for OAuthHandler — OAuth PKCE flow utilities.
 *
 * Covers: URL building, PKCE generation, state management, credential checks.
 * Note: actual HTTP token exchange requires a server (integration test).
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\OAuthHandler;

class OAuthHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Context::reset();
    }

    // =========================================================================
    // getFrontendApiUrl / getBackendApiUrl
    // =========================================================================

    public function testGetFrontendApiUrlFromConfig(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_FRONT_URL'] = 'https://front.aismarttalk.tech';
        $this->assertEquals('https://front.aismarttalk.tech', OAuthHandler::getFrontendApiUrl());
    }

    public function testGetFrontendApiUrlFallsBackToMainUrl(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_URL'] = 'https://api.aismarttalk.tech';
        $this->assertEquals('https://api.aismarttalk.tech', OAuthHandler::getFrontendApiUrl());
    }

    public function testGetFrontendApiUrlDefaultsToAiSmartTalk(): void
    {
        $this->assertEquals('https://aismarttalk.tech', OAuthHandler::getFrontendApiUrl());
    }

    public function testGetFrontendApiUrlStripsTrailingSlash(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_FRONT_URL'] = 'https://front.test.com/';
        $this->assertEquals('https://front.test.com', OAuthHandler::getFrontendApiUrl());
    }

    public function testGetBackendApiUrlFromConfig(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_URL'] = 'https://api.custom.com';
        $this->assertEquals('https://api.custom.com', OAuthHandler::getBackendApiUrl());
    }

    public function testGetBackendApiUrlDefault(): void
    {
        $this->assertEquals('https://aismarttalk.tech', OAuthHandler::getBackendApiUrl());
    }

    // =========================================================================
    // PKCE — code verifier and challenge
    // =========================================================================

    public function testGenerateCodeVerifierLength(): void
    {
        $verifier = OAuthHandler::generateCodeVerifier();
        $this->assertEquals(64, strlen($verifier)); // 32 bytes = 64 hex chars
    }

    public function testGenerateCodeVerifierIsHex(): void
    {
        $verifier = OAuthHandler::generateCodeVerifier();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $verifier);
    }

    public function testGenerateCodeVerifierIsRandom(): void
    {
        $v1 = OAuthHandler::generateCodeVerifier();
        $v2 = OAuthHandler::generateCodeVerifier();
        $this->assertNotEquals($v1, $v2);
    }

    public function testGenerateCodeChallengeFormat(): void
    {
        $challenge = OAuthHandler::generateCodeChallenge('test_verifier');

        // Should be base64url encoded (no + / =)
        $this->assertStringNotContainsString('+', $challenge);
        $this->assertStringNotContainsString('/', $challenge);
        $this->assertStringNotContainsString('=', $challenge);
        $this->assertNotEmpty($challenge);
    }

    public function testGenerateCodeChallengeIsDeterministic(): void
    {
        $c1 = OAuthHandler::generateCodeChallenge('same_verifier');
        $c2 = OAuthHandler::generateCodeChallenge('same_verifier');
        $this->assertEquals($c1, $c2);
    }

    public function testDifferentVerifiersProduceDifferentChallenges(): void
    {
        $c1 = OAuthHandler::generateCodeChallenge('verifier_a');
        $c2 = OAuthHandler::generateCodeChallenge('verifier_b');
        $this->assertNotEquals($c1, $c2);
    }

    // =========================================================================
    // State token
    // =========================================================================

    public function testGenerateStateLength(): void
    {
        $state = OAuthHandler::generateState();
        $this->assertEquals(32, strlen($state)); // 16 bytes = 32 hex chars
    }

    public function testGenerateStateIsRandom(): void
    {
        $s1 = OAuthHandler::generateState();
        $s2 = OAuthHandler::generateState();
        $this->assertNotEquals($s1, $s2);
    }

    // =========================================================================
    // State storage
    // =========================================================================

    public function testStoreAndRetrieveOAuthState(): void
    {
        OAuthHandler::storeOAuthState('state123', 'verifier456', 'https://return.url');

        $stored = OAuthHandler::getStoredOAuthState();

        $this->assertNotNull($stored);
        $this->assertEquals('state123', $stored['state']);
        $this->assertEquals('verifier456', $stored['code_verifier']);
        $this->assertEquals('https://return.url', $stored['return_url']);
    }

    public function testGetStoredOAuthStateReturnsNullWhenEmpty(): void
    {
        $this->assertNull(OAuthHandler::getStoredOAuthState());
    }

    public function testClearOAuthState(): void
    {
        OAuthHandler::storeOAuthState('state', 'verifier');
        OAuthHandler::clearOAuthState();

        $this->assertNull(OAuthHandler::getStoredOAuthState());
    }

    // =========================================================================
    // isConnected / getAccessToken / getChatModelId
    // =========================================================================

    public function testIsConnectedTrue(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_OAUTH_CONNECTED'] = '1';
        \Configuration::$globalStore['AI_SMART_TALK_ACCESS_TOKEN'] = 'token';
        \Configuration::$globalStore['AI_SMART_TALK_CHAT_MODEL_ID'] = 'model';

        $this->assertTrue(OAuthHandler::isConnected());
    }

    public function testIsConnectedFalseWhenNotSet(): void
    {
        $this->assertFalse(OAuthHandler::isConnected());
    }

    public function testGetAccessToken(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ACCESS_TOKEN'] = 'my-token';
        $this->assertEquals('my-token', OAuthHandler::getAccessToken());
    }

    public function testGetAccessTokenNull(): void
    {
        $this->assertNull(OAuthHandler::getAccessToken());
    }

    public function testGetChatModelId(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_CHAT_MODEL_ID'] = 'cm-123';
        $this->assertEquals('cm-123', OAuthHandler::getChatModelId());
    }

    // =========================================================================
    // disconnect
    // =========================================================================

    public function testDisconnectClearsCredentials(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_OAUTH_CONNECTED'] = '1';
        \Configuration::$globalStore['AI_SMART_TALK_ACCESS_TOKEN'] = 'token';
        \Configuration::$globalStore['AI_SMART_TALK_CHAT_MODEL_ID'] = 'model';

        OAuthHandler::disconnect();

        $this->assertFalse(\Configuration::get('AI_SMART_TALK_OAUTH_CONNECTED'));
        $this->assertFalse(\Configuration::get('AI_SMART_TALK_ACCESS_TOKEN'));
        $this->assertFalse(\Configuration::get('AI_SMART_TALK_CHAT_MODEL_ID'));
    }
}
