<?php
/**
 * Tests for configuration scoping across the entire module.
 *
 * Ensures that ALL module config keys are read/written at the correct scope:
 * - Global: everything except AI_SMART_TALK_ENABLED
 * - Per-shop: AI_SMART_TALK_ENABLED (chatbot display)
 *
 * These tests verify the contract between getContent()'s context forcing
 * and the hooks' explicit MultistoreHelper::getConfig() calls.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\MultistoreHelper;

class ConfigScopingTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
    }

    // =========================================================================
    // Global config keys — must always read global regardless of shop context
    // =========================================================================

    /**
     * @dataProvider globalConfigKeysProvider
     */
    public function testGlobalConfigKeyReadsGlobalInMultistore(string $key, string $value): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        // Set global value
        \Configuration::$globalStore[$key] = $value;
        // Set conflicting per-shop value
        \Configuration::$shopStore[2] = [$key => 'WRONG_VALUE'];

        // MultistoreHelper::getConfig must return the global value
        $this->assertEquals($value, MultistoreHelper::getConfig($key));
    }

    /**
     * @dataProvider globalConfigKeysProvider
     */
    public function testGlobalConfigKeyWritesGlobalInMultistore(string $key, string $value): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        MultistoreHelper::updateConfig($key, $value);

        // Must be in global store
        $this->assertEquals($value, \Configuration::$globalStore[$key]);
        // Must NOT be in per-shop store
        $this->assertArrayNotHasKey($key, \Configuration::$shopStore[2] ?? []);
    }

    public function globalConfigKeysProvider(): array
    {
        return [
            'Product sync toggle' => ['AI_SMART_TALK_PRODUCT_SYNC', '1'],
            'Customer sync toggle' => ['AI_SMART_TALK_CUSTOMER_SYNC', '1'],
            'Customer consent filter' => ['AI_SMART_TALK_CUSTOMER_SYNC_CONSENT', 'newsletter'],
            'Sync filter mode' => ['AI_SMART_TALK_SYNC_FILTER_MODE', 'exclude'],
            'Sync categories' => ['AI_SMART_TALK_SYNC_CATEGORIES', '[3,5]'],
            'Webhooks triggers' => ['AI_SMART_TALK_WEBHOOKS_TRIGGERS', '["trigger1"]'],
            'API URL' => ['AI_SMART_TALK_URL', 'https://api.example.com'],
            'CDN URL' => ['AI_SMART_TALK_CDN', 'https://cdn.example.com'],
            'WS URL' => ['AI_SMART_TALK_WS', 'wss://ws.example.com'],
            'Iframe position' => ['AI_SMART_TALK_IFRAME_POSITION', 'footer'],
            'Error message' => ['AI_SMART_TALK_ERROR', 'sync failed'],
        ];
    }

    // =========================================================================
    // Per-shop config — AI_SMART_TALK_ENABLED (chatbot display)
    // =========================================================================

    public function testChatbotEnabledIsPerShop(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
        ]);

        // Enable for shop 1 only
        MultistoreHelper::saveShopsChatbotStatus([1]);

        // Shop 1: enabled
        $this->assertTrue(
            (bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 1)
        );
        // Shop 2: disabled
        $this->assertFalse(
            (bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 2)
        );
    }

    // =========================================================================
    // Mono-shop — everything works without multistore
    // =========================================================================

    public function testMonoShopGetConfigWorksNormally(): void
    {
        \Shop::setFeatureActive(false);
        \Configuration::$globalStore['AI_SMART_TALK_PRODUCT_SYNC'] = '1';

        $this->assertEquals('1', MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC'));
    }

    public function testMonoShopUpdateConfigWorksNormally(): void
    {
        \Shop::setFeatureActive(false);

        MultistoreHelper::updateConfig('AI_SMART_TALK_PRODUCT_SYNC', '1');

        // In mono-shop, value is readable via standard Configuration::get()
        $this->assertEquals('1', \Configuration::get('AI_SMART_TALK_PRODUCT_SYNC'));
    }

    // =========================================================================
    // Context switch in getContent() — simulation
    // =========================================================================

    public function testContextSwitchToAllMakesConfigGlobal(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        // Simulate what getContent() does
        \Shop::setContext(\Shop::CONTEXT_ALL);

        // Now bare Configuration::updateValue should write globally
        \Configuration::updateValue('AI_SMART_TALK_PRODUCT_SYNC', '1');

        $this->assertEquals('1', \Configuration::$globalStore['AI_SMART_TALK_PRODUCT_SYNC']);

        // Restore context
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);
    }

    public function testContextSwitchPreservesPerShopChatbot(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
        ]);

        // Save per-shop chatbot status (uses explicit id_shop, not context)
        MultistoreHelper::saveShopsChatbotStatus([1]);

        // Switch context to ALL (like getContent does)
        \Shop::setContext(\Shop::CONTEXT_ALL);

        // Per-shop values should still be readable with explicit id_shop
        $this->assertTrue(
            (bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 1)
        );
        $this->assertFalse(
            (bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 2)
        );
    }
}
