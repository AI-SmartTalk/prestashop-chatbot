<?php
/**
 * Integration tests for single-shop product sync — the 95% use case.
 *
 * Tests the full sync lifecycle on a realistic single-shop PrestaShop installation:
 * basic sync, incremental, category filters with subcategories, specific prices,
 * products without images, empty store, out-of-stock handling, cleanup.
 *
 * Requires: docker compose -f docker-compose.test.yml up -d
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk;
use PrestaShop\AiSmartTalk\SyncFilterHelper;
use PrestaShop\AiSmartTalk\MultistoreHelper;
use PrestaShop\AiSmartTalk\AiSmartTalkProductSync;

class SingleShopSyncTest extends TestCase
{
    /** @var \PDO */
    private $pdo;

    public static function setUpBeforeClass(): void
    {
        \loadTestSeed('seed_single_shop.sql');
    }

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['test_pdo'];

        // Clean state for each test
        $this->pdo->exec('TRUNCATE ps_aismarttalk_product_sync');
        $this->pdo->exec("DELETE FROM ps_configuration WHERE name LIKE 'AI_SMART_TALK_%'");
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getProducts(bool $forceSync = true, array $productIds = []): array
    {
        $context = \Context::getContext();
        $syncher = new SynchProductsToAiSmartTalk($context);
        $ref = new \ReflectionClass($syncher);

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
        return array_map(fn($p) => (int) $p['id_product'], $this->getProducts($forceSync, $productIds));
    }

    private function findProduct(array $products, int $id): ?array
    {
        foreach ($products as $p) {
            if ((int) $p['id_product'] === $id) {
                return $p;
            }
        }
        return null;
    }

    private function markSynced(array $productIds): void
    {
        foreach ($productIds as $id) {
            $this->pdo->exec(
                "INSERT INTO ps_aismarttalk_product_sync (id_product, id_shop, synced, last_sync)
                 VALUES ($id, 1, 1, NOW())
                 ON DUPLICATE KEY UPDATE synced = 1, last_sync = NOW()"
            );
        }
    }

    // =========================================================================
    // Basic sync — active, in stock, inactive, OOS
    // =========================================================================

    public function testSyncReturnsActiveInStockProducts(): void
    {
        $ids = $this->getProductIds();

        // P1 (active, stock), P2 (active, stock), P5 (multi-cat), P6 (subcategory),
        // P7 (no image), P8 (promo), P9 (expired promo), P10 (2 promos) = 8
        $this->assertContains(1, $ids, 'P1 active+stock should sync');
        $this->assertContains(2, $ids, 'P2 active+stock should sync');
        $this->assertContains(5, $ids, 'P5 multi-category should sync');
        $this->assertContains(6, $ids, 'P6 subcategory should sync');
        $this->assertContains(7, $ids, 'P7 no image should sync');
        $this->assertContains(8, $ids, 'P8 with promo should sync');
        $this->assertContains(9, $ids, 'P9 expired promo should sync');
        $this->assertContains(10, $ids, 'P10 double promo should sync');
    }

    public function testSyncExcludesOutOfStock(): void
    {
        $ids = $this->getProductIds();
        $this->assertNotContains(3, $ids, 'P3 out of stock should NOT sync');
    }

    public function testSyncExcludesInactive(): void
    {
        $ids = $this->getProductIds();
        $this->assertNotContains(4, $ids, 'P4 inactive should NOT sync');
    }

    public function testSyncReturnsCorrectCount(): void
    {
        $ids = $this->getProductIds();
        $this->assertCount(8, $ids, 'Should return exactly 8 eligible products');
    }

    public function testNoDuplicates(): void
    {
        $ids = $this->getProductIds();
        $this->assertEquals(count($ids), count(array_unique($ids)));
    }

    // =========================================================================
    // Product data integrity
    // =========================================================================

    public function testProductHasCompleteData(): void
    {
        $p = $this->findProduct($this->getProducts(true, ['1']), 1);

        $this->assertNotNull($p);
        $this->assertEquals('T-shirt basique', $p['name']);
        $this->assertEquals('TSHIRT-01', $p['reference']);
        $this->assertStringContainsString('coton bio', $p['description']);
        $this->assertEquals('EUR', $p['currency_code']);
        $this->assertNotNull($p['id_image'], 'P1 should have an image');
    }

    public function testProductWithoutImageHasNullImage(): void
    {
        $p = $this->findProduct($this->getProducts(true, ['7']), 7);

        $this->assertNotNull($p, 'P7 should be in results');
        $this->assertEmpty($p['id_image'], 'P7 should have no image');
        $this->assertEquals('Bague sans image', $p['name']);
    }

    public function testProductNameIsNeverNull(): void
    {
        foreach ($this->getProducts() as $p) {
            $this->assertNotEmpty($p['name'], "Product {$p['id_product']} name should not be empty");
        }
    }

    // =========================================================================
    // Specific prices
    // =========================================================================

    public function testProductWithActivePromo(): void
    {
        $p = $this->findProduct($this->getProducts(true, ['8']), 8);

        $this->assertNotNull($p);
        // Should have specific_price data
        $this->assertNotEmpty($p['price_reduction'], 'P8 should have a reduction');
        $this->assertEquals('percentage', $p['reduction_type']);
    }

    public function testProductWithExpiredPromo(): void
    {
        $p = $this->findProduct($this->getProducts(true, ['9']), 9);

        $this->assertNotNull($p);
        // Expired promo should NOT appear (the subquery filters by date)
        $this->assertEmpty($p['specific_price'] ?? null, 'P9 expired promo should not have specific_price');
    }

    public function testProductWithTwoPromosPicksOne(): void
    {
        $p = $this->findProduct($this->getProducts(true, ['10']), 10);

        $this->assertNotNull($p);
        // Should have exactly one specific price (the subquery uses LIMIT 1)
        // The shop-specific one (id_shop=1) should take priority over global (id_shop=0)
        $this->assertNotEmpty($p['price_reduction'], 'P10 should have a reduction');
    }

    // =========================================================================
    // Incremental sync lifecycle
    // =========================================================================

    public function testIncrementalSkipsSyncedProducts(): void
    {
        // Mark P1 and P2 as synced
        $this->markSynced([1, 2]);

        $ids = $this->getProductIds(false); // incremental

        $this->assertNotContains(1, $ids, 'P1 already synced → skip');
        $this->assertNotContains(2, $ids, 'P2 already synced → skip');
        $this->assertContains(5, $ids, 'P5 not synced → include');
        $this->assertContains(8, $ids, 'P8 not synced → include');
    }

    public function testIncrementalReturnsNothingWhenAllSynced(): void
    {
        // Mark all eligible products as synced
        $this->markSynced([1, 2, 5, 6, 7, 8, 9, 10]);

        $ids = $this->getProductIds(false);
        $this->assertEmpty($ids, 'All synced → nothing to do');
    }

    public function testForceSyncIgnoresSyncedFlag(): void
    {
        $this->markSynced([1, 2, 5, 6, 7, 8, 9, 10]);

        $ids = $this->getProductIds(true); // force
        $this->assertCount(8, $ids, 'Force sync should return all eligible regardless of sync flag');
    }

    public function testProductRestockedAfterSync(): void
    {
        // Sync everything
        $this->markSynced([1, 2, 5, 6, 7, 8, 9, 10]);

        // P3 was OOS, now gets restocked
        $this->pdo->exec('UPDATE ps_stock_available SET quantity = 5 WHERE id_product = 3');

        // Incremental should pick up P3 (it was never marked synced)
        $ids = $this->getProductIds(false);
        $this->assertContains(3, $ids, 'Restocked P3 should appear in incremental sync');

        // Restore
        $this->pdo->exec('UPDATE ps_stock_available SET quantity = 0 WHERE id_product = 3');
    }

    public function testProductGoesOOSThenComesBack(): void
    {
        // Sync P1
        $this->markSynced([1]);

        // P1 goes OOS
        $this->pdo->exec('UPDATE ps_stock_available SET quantity = 0 WHERE id_product = 1');

        // Force sync should NOT include P1 (OOS)
        $ids = $this->getProductIds(true);
        $this->assertNotContains(1, $ids, 'P1 OOS should not sync');

        // P1 comes back in stock
        $this->pdo->exec('UPDATE ps_stock_available SET quantity = 10 WHERE id_product = 1');

        // Mark P1 as NOT synced (hook would do this)
        $this->pdo->exec('UPDATE ps_aismarttalk_product_sync SET synced = 0 WHERE id_product = 1');

        // Incremental should pick it up again
        $ids = $this->getProductIds(false);
        $this->assertContains(1, $ids, 'P1 back in stock + unsynced → should appear in incremental');
    }

    // =========================================================================
    // Category filters — include / exclude
    // =========================================================================

    public function testIncludeOnlyClothesCategoryFilter(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'include');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([3]));

        $ids = $this->getProductIds();

        // Category 3 (Clothes): P1, P5, P8, P10
        $this->assertContains(1, $ids, 'P1 (Clothes) should sync');
        $this->assertContains(5, $ids, 'P5 (Clothes+Accessories) should sync');
        $this->assertContains(8, $ids, 'P8 (Clothes) should sync');
        $this->assertContains(10, $ids, 'P10 (Clothes) should sync');

        // NOT in Clothes
        $this->assertNotContains(2, $ids, 'P2 (Accessories only) should NOT sync');
        $this->assertNotContains(6, $ids, 'P6 (Peinture/Art) should NOT sync');
        $this->assertNotContains(7, $ids, 'P7 (Accessories only) should NOT sync');
        $this->assertNotContains(9, $ids, 'P9 (Accessories only) should NOT sync');
    }

    public function testExcludeAccessoriesCategoryFilter(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'exclude');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([4]));

        $ids = $this->getProductIds();

        // Excluded: products ONLY in Accessories (P2, P7, P9)
        // P5 is in Clothes AND Accessories → should be excluded (is in excluded cat)
        $this->assertNotContains(2, $ids, 'P2 (Accessories) excluded');
        $this->assertNotContains(7, $ids, 'P7 (Accessories) excluded');
        $this->assertNotContains(9, $ids, 'P9 (Accessories) excluded');
        $this->assertNotContains(5, $ids, 'P5 (in Accessories) excluded');

        // NOT excluded
        $this->assertContains(1, $ids, 'P1 (Clothes) not excluded');
        $this->assertContains(6, $ids, 'P6 (Art>Peinture) not excluded');
    }

    public function testIncludeMultipleCategoriesIsUnion(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'include');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([3, 4]));

        $ids = $this->getProductIds();

        // Clothes (P1,P5,P8,P10) + Accessories (P2,P5,P7,P9) = union
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertContains(5, $ids);
        $this->assertContains(7, $ids);

        // Art products excluded
        $this->assertNotContains(6, $ids, 'P6 (Art) not in selected categories');
    }

    // =========================================================================
    // Category filters — with subcategories (nested set)
    // =========================================================================

    public function testIncludeArtWithSubcategories(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'include');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([5])); // Art parent
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES', '1');

        $ids = $this->getProductIds();

        // Art (5) includes subcategory Peinture (7) and Sculpture (8)
        // P6 is in Peinture (child of Art) → should be included
        $this->assertContains(6, $ids, 'P6 (Peinture, child of Art) should sync with subcategories');

        // Not in Art tree
        $this->assertNotContains(1, $ids, 'P1 (Clothes) should NOT sync');
        $this->assertNotContains(2, $ids, 'P2 (Accessories) should NOT sync');
    }

    public function testIncludeArtWithoutSubcategoriesExcludesChildren(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'include');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([5])); // Art parent
        // include_subcategories = false (default)

        $ids = $this->getProductIds();

        // P6 is in Peinture (7), NOT directly in Art (5)
        // Without subcategories, only products directly in Art are included
        $this->assertNotContains(6, $ids, 'P6 (Peinture) should NOT sync without subcategories flag');
    }

    public function testExcludeArtWithSubcategories(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_FILTER_MODE', 'exclude');
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_CATEGORIES', json_encode([5]));
        MultistoreHelper::updateConfig('AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES', '1');

        $ids = $this->getProductIds();

        // P6 in Peinture (child of Art) → excluded
        $this->assertNotContains(6, $ids, 'P6 (Peinture, child of Art) excluded');

        // Others not affected
        $this->assertContains(1, $ids, 'P1 (Clothes) not excluded');
        $this->assertContains(2, $ids, 'P2 (Accessories) not excluded');
    }

    // =========================================================================
    // Cleanup: isProductActiveInAnyShop (the decision logic behind cleanup)
    // =========================================================================

    public function testOOSProductShouldBeCleaned(): void
    {
        // P3 is OOS → should be cleaned
        $this->assertFalse(
            MultistoreHelper::isProductActiveInAnyShop(3),
            'P3 (OOS) should be flagged for cleanup'
        );
    }

    public function testInactiveProductShouldBeCleaned(): void
    {
        // P4 is inactive → should be cleaned
        $this->assertFalse(
            MultistoreHelper::isProductActiveInAnyShop(4),
            'P4 (inactive) should be flagged for cleanup'
        );
    }

    public function testActiveInStockProductShouldNotBeCleaned(): void
    {
        $this->assertTrue(
            MultistoreHelper::isProductActiveInAnyShop(1),
            'P1 (active+stock) should NOT be cleaned'
        );
    }

    public function testNonExistentProductShouldBeCleaned(): void
    {
        $this->assertFalse(
            MultistoreHelper::isProductActiveInAnyShop(999),
            'Non-existent product should be flagged for cleanup'
        );
    }

    public function testProductGoesOOSThenCleanupDetectsIt(): void
    {
        // P1 is in stock
        $this->assertTrue(MultistoreHelper::isProductActiveInAnyShop(1));

        // Set P1 to OOS
        $this->pdo->exec('UPDATE ps_stock_available SET quantity = 0 WHERE id_product = 1');

        // Now cleanup should detect it
        $this->assertFalse(
            MultistoreHelper::isProductActiveInAnyShop(1),
            'P1 now OOS should be detected for cleanup'
        );

        // Restore
        $this->pdo->exec('UPDATE ps_stock_available SET quantity = 50 WHERE id_product = 1');
    }

    // =========================================================================
    // Empty store edge case
    // =========================================================================

    public function testEmptyStoreReturnsNothing(): void
    {
        // Deactivate all products
        $this->pdo->exec('UPDATE ps_product_shop SET active = 0');

        $ids = $this->getProductIds();
        $this->assertEmpty($ids, 'Empty/all-inactive store should return no products');

        // Restore
        $this->pdo->exec('UPDATE ps_product_shop SET active = 1 WHERE id_product NOT IN (4)');
    }

    public function testAllOutOfStockReturnsNothing(): void
    {
        $this->pdo->exec('UPDATE ps_stock_available SET quantity = 0');

        $ids = $this->getProductIds();
        $this->assertEmpty($ids, 'All OOS should return no products');

        // Restore
        \loadTestSeed('seed_single_shop.sql');
    }

    // =========================================================================
    // Category tree in single shop
    // =========================================================================

    public function testCategoryTreeSingleShop(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        $names = array_column($flat, 'name');

        $this->assertContains('Accueil', $names);
        $this->assertContains('Vêtements', $names);
        $this->assertContains('Accessoires', $names);
        $this->assertContains('Art', $names);
        $this->assertContains('Peinture', $names);
        $this->assertContains('Sculpture', $names);
    }

    public function testCategoryTreeProductCounts(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        $counts = [];
        foreach ($flat as $cat) {
            $counts[$cat['name']] = (int) $cat['product_count'];
        }

        // Clothes: P1, P3(OOS but active), P4(inactive→not counted), P5, P8, P10 = 5 active
        // Actually product_shop.active is checked in the query
        $this->assertGreaterThanOrEqual(4, $counts['Vêtements'], 'Clothes should have products');
        $this->assertGreaterThanOrEqual(1, $counts['Peinture'], 'Peinture should have P6');
        $this->assertEquals(0, $counts['Sculpture'], 'Sculpture has no products');
    }

    public function testCategoryTreeHierarchyDepth(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        // Find Peinture — should be depth 2 (Home > Art > Peinture)
        $peinture = null;
        foreach ($flat as $cat) {
            if ($cat['name'] === 'Peinture') {
                $peinture = $cat;
                break;
            }
        }

        $this->assertNotNull($peinture);
        $this->assertEquals(2, $peinture['depth'], 'Peinture should be at depth 2');
        $this->assertTrue($peinture['parent_id'] > 0, 'Peinture should have a parent');
    }

    // =========================================================================
    // Config persistence via real DB
    // =========================================================================

    public function testConfigWriteAndReadGlobal(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_PRODUCT_SYNC', '1');

        $value = MultistoreHelper::getConfig('AI_SMART_TALK_PRODUCT_SYNC');
        $this->assertEquals('1', $value);
    }

    public function testConfigDeleteRemovesValue(): void
    {
        MultistoreHelper::updateConfig('AI_SMART_TALK_TEST_KEY', 'hello');
        MultistoreHelper::deleteConfig('AI_SMART_TALK_TEST_KEY');

        $this->assertFalse(MultistoreHelper::getConfig('AI_SMART_TALK_TEST_KEY'));
    }

    public function testFilterConfigRoundTrip(): void
    {
        SyncFilterHelper::saveFilterConfig([
            'mode' => 'exclude',
            'categories' => [3, 5],
            'include_subcategories' => true,
        ]);

        $config = SyncFilterHelper::getFilterConfig();

        $this->assertEquals('exclude', $config['mode']);
        $this->assertEquals([3, 5], $config['categories']);
        $this->assertTrue($config['include_subcategories']);
    }
}
