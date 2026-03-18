<?php
/**
 * Tests for MultistoreHelper — the central multistore utility class.
 *
 * Covers:
 * - Global config reads/writes (getConfig, updateConfig, deleteConfig)
 * - Shop enumeration (getAllShopIds, getAllShops)
 * - Per-shop chatbot status (getShopsChatbotStatus, saveShopsChatbotStatus)
 * - Product activity checks (isProductActiveInAnyShop, isProductActiveInShop)
 * - Mono-shop vs multistore behavior
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\MultistoreHelper;

class MultistoreHelperTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
        \Db::reset();
    }

    // =========================================================================
    // isMultistoreActive
    // =========================================================================

    public function testIsMultistoreActiveReturnsFalseByDefault(): void
    {
        $this->assertFalse(MultistoreHelper::isMultistoreActive());
    }

    public function testIsMultistoreActiveReturnsTrueWhenEnabled(): void
    {
        \Shop::setFeatureActive(true);
        $this->assertTrue(MultistoreHelper::isMultistoreActive());
    }

    // =========================================================================
    // getDefaultShopId
    // =========================================================================

    public function testGetDefaultShopIdReadsFromConfig(): void
    {
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 3;
        $this->assertEquals(3, MultistoreHelper::getDefaultShopId());
    }

    // =========================================================================
    // getAllShopIds
    // =========================================================================

    public function testGetAllShopIdsReturnsCurrentShopInMonoStore(): void
    {
        // Multistore OFF — should return context shop ID
        $ids = MultistoreHelper::getAllShopIds();
        $this->assertEquals([1], $ids);
    }

    public function testGetAllShopIdsReturnsAllShopsWithDefaultFirst(): void
    {
        \Shop::setFeatureActive(true);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 2;
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
            3 => ['id_shop' => 3, 'name' => 'Shop C'],
        ]);

        $ids = MultistoreHelper::getAllShopIds();

        // Default shop (2) should be first, then others sorted ascending
        $this->assertEquals([2, 1, 3], $ids);
    }

    public function testGetAllShopIdsHandlesSingleShopInMultistore(): void
    {
        \Shop::setFeatureActive(true);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 1;
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Only Shop'],
        ]);

        $ids = MultistoreHelper::getAllShopIds();
        $this->assertEquals([1], $ids);
    }

    // =========================================================================
    // getConfig / updateConfig / deleteConfig — global scope enforcement
    // =========================================================================

    public function testGetConfigReadsGlobalValueInMultistore(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        // Store value globally
        \Configuration::$globalStore['AI_SMART_TALK_PRODUCT_SYNC'] = '1';
        // Store different value per-shop
        \Configuration::$shopStore[2] = ['AI_SMART_TALK_PRODUCT_SYNC' => '0'];

        // getConfig should read GLOBAL, not per-shop
        $this->assertEquals('1', MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC'));
    }

    public function testGetConfigReadsNormallyInMonoStore(): void
    {
        \Shop::setFeatureActive(false);
        \Configuration::$globalStore['AI_SMART_TALK_PRODUCT_SYNC'] = '1';

        $this->assertEquals('1', MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC'));
    }

    public function testUpdateConfigWritesGloballyInMultistore(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        MultistoreHelper::updateConfig('AI_SMART_TALK_PRODUCT_SYNC', '1');

        // Should be in global store, not per-shop
        $this->assertEquals('1', \Configuration::$globalStore['AI_SMART_TALK_PRODUCT_SYNC']);
        $this->assertArrayNotHasKey('AI_SMART_TALK_PRODUCT_SYNC', \Configuration::$shopStore[2] ?? []);
    }

    public function testDeleteConfigRemovesFromAllScopes(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ERROR'] = 'some error';
        \Configuration::$shopStore[1] = ['AI_SMART_TALK_ERROR' => 'shop error'];

        MultistoreHelper::deleteConfig('AI_SMART_TALK_ERROR');

        $this->assertArrayNotHasKey('AI_SMART_TALK_ERROR', \Configuration::$globalStore);
        $this->assertArrayNotHasKey('AI_SMART_TALK_ERROR', \Configuration::$shopStore[1] ?? []);
    }

    // =========================================================================
    // getShopsChatbotStatus / saveShopsChatbotStatus — per-shop chatbot display
    // =========================================================================

    public function testGetShopsChatbotStatusReadsPerShop(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
        ]);

        // Shop A enabled, Shop B disabled
        \Configuration::$shopStore[1] = ['AI_SMART_TALK_ENABLED' => '1'];
        \Configuration::$shopStore[2] = ['AI_SMART_TALK_ENABLED' => '0'];

        $status = MultistoreHelper::getShopsChatbotStatus();

        $this->assertCount(2, $status);
        $this->assertTrue($status[0]['enabled']);
        $this->assertFalse($status[1]['enabled']);
    }

    public function testSaveShopsChatbotStatusWritesPerShop(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
            3 => ['id_shop' => 3, 'name' => 'Shop C'],
        ]);

        // Enable chatbot only on shops 1 and 3
        MultistoreHelper::saveShopsChatbotStatus([1, 3]);

        $this->assertTrue((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 1));
        $this->assertFalse((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 2));
        $this->assertTrue((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 3));
    }

    public function testSaveShopsChatbotStatusWithEmptyArrayDisablesAll(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
        ]);

        // First enable all
        MultistoreHelper::saveShopsChatbotStatus([1, 2]);
        // Then disable all
        MultistoreHelper::saveShopsChatbotStatus([]);

        $this->assertFalse((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 1));
        $this->assertFalse((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 2));
    }

    // =========================================================================
    // isProductActiveInShop
    // =========================================================================

    public function testIsProductActiveInShopReturnsTrueWhenActiveAndInStock(): void
    {
        \Shop::setShopGroups([1 => 1]);
        \Db::$getValueResults = [1]; // Query returns 1 = found

        $this->assertTrue(MultistoreHelper::isProductActiveInShop(42, 1));
    }

    public function testIsProductActiveInShopReturnsFalseWhenNotFound(): void
    {
        \Shop::setShopGroups([1 => 1]);
        \Db::$getValueResults = [false]; // Query returns false = not found

        $this->assertFalse(MultistoreHelper::isProductActiveInShop(42, 1));
    }

    // =========================================================================
    // isProductActiveInAnyShop
    // =========================================================================

    public function testIsProductActiveInAnyShopChecksAllShops(): void
    {
        \Shop::setFeatureActive(true);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 1;
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
        ]);
        \Shop::setShopGroups([1 => 1, 2 => 1]);

        // Product not active in shop 1, active in shop 2
        \Db::$getValueResults = [false, 1];

        $this->assertTrue(MultistoreHelper::isProductActiveInAnyShop(42));
    }

    public function testIsProductActiveInAnyShopReturnsFalseWhenInactiveEverywhere(): void
    {
        \Shop::setFeatureActive(true);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 1;
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
        ]);
        \Shop::setShopGroups([1 => 1, 2 => 1]);

        \Db::$getValueResults = [false, false];

        $this->assertFalse(MultistoreHelper::isProductActiveInAnyShop(42));
    }
}
