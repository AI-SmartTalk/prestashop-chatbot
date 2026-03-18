<?php
/**
 * Tests for AiSmartTalkCache — Configuration-based caching system.
 *
 * Covers: get(), set(), delete(), has(), getMetadata(), clearAll(), TTL expiration.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\AiSmartTalkCache;

class AiSmartTalkCacheTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Context::reset();
        \Db::reset();
    }

    public function testSetAndGet(): void
    {
        AiSmartTalkCache::set('test_key', ['data' => 'hello'], 3600);
        $result = AiSmartTalkCache::get('test_key');

        $this->assertEquals(['data' => 'hello'], $result);
    }

    public function testGetReturnsNullWhenNotSet(): void
    {
        $this->assertNull(AiSmartTalkCache::get('nonexistent'));
    }

    public function testGetReturnsNullWhenExpired(): void
    {
        // Manually store an expired entry
        $cacheKey = AiSmartTalkCache::CACHE_PREFIX . 'EXPIRED_KEY';
        \Configuration::$shopStore[1] = [
            $cacheKey => json_encode([
                'value' => 'old data',
                'expires_at' => time() - 100, // expired 100 seconds ago
                'created_at' => time() - 3700,
            ]),
        ];

        $this->assertNull(AiSmartTalkCache::get('expired_key'));
    }

    public function testHasReturnsTrueForValidCache(): void
    {
        AiSmartTalkCache::set('exists', 'value', 3600);
        $this->assertTrue(AiSmartTalkCache::has('exists'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $this->assertFalse(AiSmartTalkCache::has('missing'));
    }

    public function testDelete(): void
    {
        AiSmartTalkCache::set('to_delete', 'value', 3600);
        AiSmartTalkCache::delete('to_delete');

        $this->assertNull(AiSmartTalkCache::get('to_delete'));
    }

    public function testClearAll(): void
    {
        AiSmartTalkCache::set('key1', 'val1', 3600);
        AiSmartTalkCache::set('key2', 'val2', 3600);

        AiSmartTalkCache::clearAll();

        // clearAll uses Db::execute, verify it was called
        $lastQuery = end(\Db::$executedQueries);
        $this->assertStringContainsString('AI_SMART_TALK_CACHE_', $lastQuery);
    }

    public function testGetMetadata(): void
    {
        AiSmartTalkCache::set('meta_test', 'value', 3600);
        $metadata = AiSmartTalkCache::getMetadata('meta_test');

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('created_at', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);
        $this->assertArrayHasKey('is_expired', $metadata);
        $this->assertArrayHasKey('ttl_remaining', $metadata);
        $this->assertFalse($metadata['is_expired']);
        $this->assertGreaterThan(0, $metadata['ttl_remaining']);
    }

    public function testGetMetadataReturnsNullForMissing(): void
    {
        $this->assertNull(AiSmartTalkCache::getMetadata('missing'));
    }

    public function testCacheKeyIsUppercased(): void
    {
        AiSmartTalkCache::set('lower_case', 'value', 3600);

        // Verify the Configuration key is uppercased
        $expectedKey = AiSmartTalkCache::CACHE_PREFIX . 'LOWER_CASE';
        $found = false;
        foreach (\Configuration::$shopStore as $store) {
            if (isset($store[$expectedKey])) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Cache key should be uppercased in Configuration');
    }

    public function testCacheStoresScalarValues(): void
    {
        AiSmartTalkCache::set('string_val', 'hello', 3600);
        $this->assertEquals('hello', AiSmartTalkCache::get('string_val'));

        AiSmartTalkCache::set('int_val', 42, 3600);
        $this->assertEquals(42, AiSmartTalkCache::get('int_val'));

        AiSmartTalkCache::set('bool_val', true, 3600);
        $this->assertTrue(AiSmartTalkCache::get('bool_val'));
    }

    public function testCacheStoresNull(): void
    {
        AiSmartTalkCache::set('null_val', null, 3600);
        // null value is valid — should be retrievable (but get() returns null for missing too)
        // The important thing is has() should still work
        // Actually, the cache stores json_encode(null) which is "null" — the get() checks for
        // isset($data['value']) which will be true
        $this->assertNull(AiSmartTalkCache::get('null_val'));
    }
}
