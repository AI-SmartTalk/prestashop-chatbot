<?php
/**
 * Extended tests for SyncFilterHelper — methods not covered by SyncFilterHelperTest.
 *
 * Covers: flattenCategoryTree(), buildCategoryHierarchy(), buildStockAvailableJoin(),
 *         getAllDescendantIds(), getCategoryTree() SQL generation.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\SyncFilterHelper;

class SyncFilterHelperExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
        \Db::reset();
    }

    // =========================================================================
    // flattenCategoryTree
    // =========================================================================

    public function testFlattenEmptyTree(): void
    {
        $this->assertEquals([], SyncFilterHelper::flattenCategoryTree([]));
    }

    public function testFlattenSingleNode(): void
    {
        $tree = [
            ['id_category' => 2, 'name' => 'Root', 'children' => [], 'product_count' => 5],
        ];

        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        $this->assertCount(1, $flat);
        $this->assertEquals(2, $flat[0]['id_category']);
        $this->assertEquals(0, $flat[0]['depth']);
        $this->assertFalse($flat[0]['has_children']);
        $this->assertNull($flat[0]['parent_id']);
    }

    public function testFlattenWithChildren(): void
    {
        $tree = [
            [
                'id_category' => 2,
                'name' => 'Root',
                'product_count' => 10,
                'children' => [
                    [
                        'id_category' => 3,
                        'name' => 'Child A',
                        'product_count' => 3,
                        'children' => [
                            ['id_category' => 5, 'name' => 'Grandchild', 'product_count' => 1, 'children' => []],
                        ],
                    ],
                    ['id_category' => 4, 'name' => 'Child B', 'product_count' => 7, 'children' => []],
                ],
            ],
        ];

        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        $this->assertCount(4, $flat);

        // Root
        $this->assertEquals(2, $flat[0]['id_category']);
        $this->assertEquals(0, $flat[0]['depth']);
        $this->assertTrue($flat[0]['has_children']);
        $this->assertEquals([3, 4], $flat[0]['child_ids']);

        // Child A
        $this->assertEquals(3, $flat[1]['id_category']);
        $this->assertEquals(1, $flat[1]['depth']);
        $this->assertEquals(2, $flat[1]['parent_id']);
        $this->assertTrue($flat[1]['has_children']);

        // Grandchild
        $this->assertEquals(5, $flat[2]['id_category']);
        $this->assertEquals(2, $flat[2]['depth']);
        $this->assertEquals(3, $flat[2]['parent_id']);

        // Child B
        $this->assertEquals(4, $flat[3]['id_category']);
        $this->assertEquals(1, $flat[3]['depth']);
        $this->assertEquals(2, $flat[3]['parent_id']);
    }

    // =========================================================================
    // buildStockAvailableJoin
    // =========================================================================

    public function testBuildStockAvailableJoinDefault(): void
    {
        \Shop::setShopGroups([1 => 1]);

        $sql = SyncFilterHelper::buildStockAvailableJoin(1);

        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('stock_available', $sql);
        $this->assertStringContainsString('id_product_attribute = 0', $sql);
        // Should handle both shop-level and shop-group-level stock
        $this->assertStringContainsString('id_shop = 1', $sql);
        $this->assertStringContainsString('id_shop = 0', $sql);
        $this->assertStringContainsString('id_shop_group', $sql);
    }

    public function testBuildStockAvailableJoinCustomAliases(): void
    {
        \Shop::setShopGroups([1 => 1]);

        $sql = SyncFilterHelper::buildStockAvailableJoin(1, 'prod', 'stock');

        $this->assertStringContainsString('prod.id_product', $sql);
        $this->assertStringContainsString('stock.id_product', $sql);
    }

    public function testBuildStockAvailableJoinDifferentShop(): void
    {
        \Shop::setShopGroups([2 => 3]);

        $sql = SyncFilterHelper::buildStockAvailableJoin(2);

        $this->assertStringContainsString('id_shop = 2', $sql);
        $this->assertStringContainsString('id_shop_group = 3', $sql);
    }

    // =========================================================================
    // getAllDescendantIds
    // =========================================================================

    public function testGetAllDescendantIdsWithResults(): void
    {
        \Db::$executeSResults = [
            [['id_category' => '3'], ['id_category' => '5'], ['id_category' => '7']],
        ];

        $ids = SyncFilterHelper::getAllDescendantIds(3);

        $this->assertEquals([3, 5, 7], $ids);
    }

    public function testGetAllDescendantIdsWithNoResults(): void
    {
        \Db::$executeSResults = [[]];

        $ids = SyncFilterHelper::getAllDescendantIds(99);

        // Should return the category itself as fallback
        $this->assertEquals([99], $ids);
    }

    // =========================================================================
    // getCategoryTree — SQL generation
    // =========================================================================

    public function testGetCategoryTreeMonoShop(): void
    {
        \Shop::setFeatureActive(false);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 1;
        \Db::$executeSResults = [[]]; // Empty categories

        SyncFilterHelper::getCategoryTree(1, 1);

        // Verify SQL was generated
        $this->assertNotEmpty(\Db::$executedQueries);
        $sql = \Db::$executedQueries[0];

        // Should contain EXISTS for category_shop
        $this->assertStringContainsString('category_shop', $sql);
        $this->assertStringContainsString('category', $sql);
    }

    public function testGetCategoryTreeMultiShop(): void
    {
        \Shop::setFeatureActive(true);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 1;
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A', 'id_category' => 2],
            2 => ['id_shop' => 2, 'name' => 'Shop B', 'id_category' => 2],
        ]);

        \Db::$executeSResults = [[]];

        SyncFilterHelper::getCategoryTree(1, 1);

        $sql = \Db::$executedQueries[0];

        // Should query categories from ALL shops
        $this->assertStringContainsString('1,2', $sql);
    }

    public function testGetCategoryTreeReturnsEmptyArrayWhenNoResults(): void
    {
        \Shop::setFeatureActive(false);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 1;
        \Db::$executeSResults = [[]];

        $result = SyncFilterHelper::getCategoryTree(1, 1);
        $this->assertEquals([], $result);
    }

    public function testGetCategoryTreeBuildsBoundaryForMultipleRoots(): void
    {
        \Shop::setFeatureActive(true);
        \Configuration::$globalStore['PS_SHOP_DEFAULT'] = 1;
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A', 'id_category' => 2],
            2 => ['id_shop' => 2, 'name' => 'Shop B', 'id_category' => 10],
        ]);

        \Db::$executeSResults = [[]];

        SyncFilterHelper::getCategoryTree(1, 1);

        $sql = \Db::$executedQueries[0];

        // Should have OR conditions for different root categories
        $this->assertStringContainsString('id_category = 2', $sql);
        $this->assertStringContainsString('id_category = 10', $sql);
    }
}
