<?php
/**
 * Tests for ApiClient — centralized HTTP client.
 *
 * Covers: constructor, fromConfig(), hasCredentials(), getters, URL building.
 * Note: actual HTTP calls (get/post/upload) require a real server and are integration tests.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\ApiClient;

class ApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Context::reset();
    }

    // =========================================================================
    // Constructor and getters
    // =========================================================================

    public function testConstructorSetsProperties(): void
    {
        $client = new ApiClient('https://api.test.com/', 'token123', 'model-1', 'site-abc');

        $this->assertEquals('https://api.test.com', $client->getBaseUrl()); // trailing slash removed
        $this->assertEquals('token123', $client->getAccessToken());
        $this->assertEquals('model-1', $client->getChatModelId());
        $this->assertEquals('site-abc', $client->getSiteIdentifier());
    }

    public function testConstructorTrimsTrailingSlash(): void
    {
        $client = new ApiClient('https://api.test.com///');
        $this->assertEquals('https://api.test.com', $client->getBaseUrl());
    }

    public function testConstructorWithMinimalArgs(): void
    {
        $client = new ApiClient('https://api.test.com');
        $this->assertNull($client->getAccessToken());
        $this->assertNull($client->getChatModelId());
        $this->assertNull($client->getSiteIdentifier());
    }

    // =========================================================================
    // hasCredentials
    // =========================================================================

    public function testHasCredentialsWithAll(): void
    {
        $client = new ApiClient('https://api.test.com', 'token', 'model');
        $this->assertTrue($client->hasCredentials());
    }

    public function testHasCredentialsFalseWithoutToken(): void
    {
        $client = new ApiClient('https://api.test.com', null, 'model');
        $this->assertFalse($client->hasCredentials());
    }

    public function testHasCredentialsFalseWithoutModel(): void
    {
        $client = new ApiClient('https://api.test.com', 'token', null);
        $this->assertFalse($client->hasCredentials());
    }

    public function testHasCredentialsFalseWithEmptyUrl(): void
    {
        $client = new ApiClient('', 'token', 'model');
        $this->assertFalse($client->hasCredentials());
    }

    public function testHasCredentialsFalseWithEmptyToken(): void
    {
        $client = new ApiClient('https://api.test.com', '', 'model');
        $this->assertFalse($client->hasCredentials());
    }

    // =========================================================================
    // fromConfig — factory method
    // =========================================================================

    public function testFromConfigCreatesClient(): void
    {
        // Set up OAuth-like config
        \Configuration::$globalStore['AI_SMART_TALK_URL'] = 'https://api.aismarttalk.tech';
        \Configuration::$globalStore['AI_SMART_TALK_ACCESS_TOKEN'] = 'oauth-token-123';
        \Configuration::$globalStore['AI_SMART_TALK_CHAT_MODEL_ID'] = 'cm-456';

        // fromConfig uses OAuthHandler which reads from Configuration
        // Since we can't fully mock OAuthHandler, we just verify the factory doesn't crash
        $client = ApiClient::fromConfig();

        $this->assertInstanceOf(ApiClient::class, $client);
    }
}
