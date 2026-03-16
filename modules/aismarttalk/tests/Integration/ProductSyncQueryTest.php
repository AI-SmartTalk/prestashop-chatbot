<?php
/**
 * Integration tests for product sync SQL queries against a real MySQL database.
 *
 * These tests verify the actual SQL queries that collect products to synchronize,
 * with realistic multistore data: shared products, shop-exclusive products,
 * out-of-stock products, inactive products, category filters.
 *
 * Test data (see seed.sql):
 *   P1: Shared (both shops), in stock         → sync
 *   P2: Shop 1 only, in stock                 → sync
 *   P3: Shop 2 only, in stock                 → sync
 *   P4: Shared, out of stock everywhere        → no sync
 *   P5: Shared, OOS shop 1 / in stock shop 2  → sync (via shop 2)
 *   P6: Shared, inactive                       → no sync
 *   P7: Shop 1 only, in stock, Art category    → sync (unless Art excluded)
 *   P8: Shared, in stock, multi-category       → sync
 *
 * Requires: docker compose -f docker-compose.test.yml up -d
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;
use PrestaShop\AiSmartTalk\SyncFilterHelper;
use PrestaShop\AiSmartTalk\MultistoreHelper;

class ProductSyncQueryTest extends TestCase
{
    /** @var \PDO */
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['test_pdo'];

        // Reset sync tracking and config for each test
        $this->pdo->exec('UPDATE ps_aismarttalk_product_sync SET synced = 0');
        $this->pdo->exec("DELETE FROM ps_configuration WHERE name LIKE 'AI_SMART_TALK_%'");
    }

    // =========================================================================
    // Helper: run getProductsToSynchronize via reflection
    // =========================================================================

    private function getProducts(bool $forceSync = true, array $productIds = []): array
    {
        $context = \Context::getContext();
        $syncher = new SynchProductsToAiSmartTalk($context);

        // Use reflection to call private method
        $ref = new \ReflectionClass($syncher);

        // Set properties
        $forceP = $ref->getProperty('forceSync');
        $forceP->setAccessible(true);
        $forceP->setValue($syncher, $forceSync);

        $idsP = $ref->getProperty('productIds');
        $idsP->setAccessible(true);
        $idsP->setValue($syncher, $productIds);

        $method = $ref->getMethod('getProductsToSynchronize');
        $method->setAccessible(true);

        return $method->invoke($syncher);
    }

    private function getProductIds(bool $forceSync = true, array $productIds = []): array
    {
        return array_map(function ($p) {
            return (int) $p['id_product'];
        }, $this->getProducts($forceSync, $productIds));
    }

    // =========================================================================
    // Core multistore sync tests
    // =========================================================================

    public function testForceSyncReturnsAllEligibleProducts(): void
    {
        $ids = $this->getProductIds(true);

        // P1 (shared, in stock), P2 (shop1), P3 (shop2), P5 (OOS shop1 but in stock shop2),
        // P7 (shop1 art), P8 (shared multi-cat) = 6 products
        $this->assertContains(1, $ids, 'P1 (shared, in stock) should be synced');
        $this->assertContains(2, $ids, 'P2 (shop 1 only, in stock) should be synced');
        $this->assertContains(3, $ids, 'P3 (shop 2 only, in stock) should be synced');
        $this->assertContains(5, $ids, 'P5 (in stock in shop 2) should be synced');
        $this->assertContains(7, $ids, 'P7 (art, shop 1) should be synced');
        $this->assertContains(8, $ids, 'P8 (shared, multi-cat) should be synced');

        $this->assertNotContains(4, $ids, 'P4 (out of stock everywhere) should NOT be synced');
        $this->assertNotContains(6, $ids, 'P6 (inactive) should NOT be synced');
    }

    public function testNoDuplicateProducts(): void
    {
        $ids = $this->getProductIds(true);

        // Each product should appear exactly once
        $this->assertEquals(count($ids), count(array_unique($ids)), 'Products should not be duplicated');
    }

    public function testProductDataComesFromDefaultShop(): void
    {
        $products = $this->getProducts(true);

        // Find P1 (shared) and verify its lang data comes from shop 1 (default)
        $p1 = null;
        foreach ($products as $p) {
            if ((int) $p['id_product'] === 1) {
                $p1 = $p;
                break;
            }
        }

        $this->assertNotNull($p1, 'P1 should be in results');
        $this->assertEquals('T-shirt partagé', $p1['name'], 'Product name should come from default shop lang');
    }

    public function testOutOfStockInOneShopButInStockInAnother(): void
    {
        $ids = $this->getProductIds(true);

        // P5 is OOS in shop 1 but in stock in shop 2 → should be synced
        $this->assertContains(5, $ids, 'P5 should sync because it is in stock in shop 2');
    }

    public function testShopExclusiveProductsIncluded(): void
    {
        $ids = $this->getProductIds(true);

        // P2 exists only in shop 1
        $this->assertContains(2, $ids, 'Shop 1 exclusive product should be included');
        // P3 exists only in shop 2
        $this->assertContains(3, $ids, 'Shop 2 exclusive product should be included');
    }

    // =========================================================================
    // Incremental sync (forceSync = false)
    // =========================================================================

    public function testIncrementalSyncSkipsAlreadySynced(): void
    {
        // Mark P1 as synced in default shop
        $this->pdo->exec("INSERT INTO ps_aismarttalk_product_sync (id_product, id_shop, synced) VALUES (1, 1, 1) ON DUPLICATE KEY UPDATE synced = 1");

        $ids = $this->getProductIds(false); // incremental

        // P1 already synced → should be skipped
        $this->assertNotContains(1, $ids, 'Already synced product should be skipped in incremental mode');
        // Other eligible products should still be included
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
    }

    public function testIncrementalSyncIncludesNewProducts(): void
    {
        // Nothing synced yet
        $ids = $this->getProductIds(false);

        // All eligible products should be returned
        $this->assertNotEmpty($ids);
        $this->assertContains(1, $ids);
    }

    // =========================================================================
    // Category filters
    // =========================================================================

    public function testIncludeCategoryFilter(): void
    {
        // Set filter: include only category 3 (Clothes)
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'include');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([3]));

        $ids = $this->getProductIds(true);

        // Only products in category 3: P1, P4 (OOS→excluded), P8
        $this->assertContains(1, $ids, 'P1 (Clothes) should be included');
        $this->assertContains(8, $ids, 'P8 (Clothes+Accessories) should be included');
        $this->assertNotContains(2, $ids, 'P2 (Accessories only) should be excluded');
        $this->assertNotContains(7, $ids, 'P7 (Art only) should be excluded');
    }

    public function testExcludeCategoryFilter(): void
    {
        // Set filter: exclude category 5 (Art)
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'exclude');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([5]));

        $ids = $this->getProductIds(true);

        // P7 is in Art (excluded) → should NOT be synced
        $this->assertNotContains(7, $ids, 'P7 (Art, excluded) should NOT be synced');
        // Others should still sync
        $this->assertContains(1, $ids, 'P1 should still sync');
        $this->assertContains(8, $ids, 'P8 should still sync');
    }

    public function testAllCategoriesNoFilter(): void
    {
        // No filter configured → all eligible products
        $ids = $this->getProductIds(true);

        $this->assertCount(6, $ids, 'All 6 eligible products should sync when no filter');
    }

    // =========================================================================
    // Specific product IDs
    // =========================================================================

    public function testSyncSpecificProducts(): void
    {
        $ids = $this->getProductIds(true, ['1', '3', '4']);

        // P1 and P3 eligible, P4 out of stock
        $this->assertContains(1, $ids);
        $this->assertContains(3, $ids);
        $this->assertNotContains(4, $ids, 'P4 out of stock even when explicitly requested');
    }

    public function testSyncSingleProduct(): void
    {
        $ids = $this->getProductIds(true, ['5']);

        // P5 is OOS in shop 1 but in stock in shop 2
        $this->assertContains(5, $ids, 'P5 should be syncable via shop 2 stock');
    }

    // =========================================================================
    // Product data integrity
    // =========================================================================

    public function testProductHasAllRequiredFields(): void
    {
        $products = $this->getProducts(true, ['1']);

        $this->assertNotEmpty($products);
        $p = $products[0];

        $requiredFields = ['id_product', 'name', 'description', 'description_short', 'reference', 'price', 'currency_code'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $p, "Product should have '$field' field");
        }

        $this->assertEquals('EUR', $p['currency_code']);
        $this->assertEquals('P1-SHARED', $p['reference']);
    }

    public function testProductNameIsNotNull(): void
    {
        $products = $this->getProducts(true);

        foreach ($products as $p) {
            $this->assertNotNull($p['name'], "Product {$p['id_product']} should have a non-null name");
            $this->assertNotEmpty($p['name'], "Product {$p['id_product']} should have a non-empty name");
        }
    }
}
