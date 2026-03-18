<?php
/**
 * Integration tests for category tree queries against a real MySQL database.
 *
 * Verifies that getCategoryTree() correctly returns categories from ALL shops
 * with accurate product counts, even when categories are shop-exclusive.
 *
 * Requires: docker compose -f docker-compose.test.yml up -d
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\SyncFilterHelper;

class CategoryTreeQueryTest extends TestCase
{
    /** @var \PDO */
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['test_pdo'];
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function getCategoryNames(array $tree): array
    {
        $flat = SyncFilterHelper::flattenCategoryTree($tree);
        $names = [];
        foreach ($flat as $cat) {
            $names[(int) $cat['id_category']] = $cat['name'];
        }
        return $names;
    }

    private function getCategoryCounts(array $tree): array
    {
        $flat = SyncFilterHelper::flattenCategoryTree($tree);
        $counts = [];
        foreach ($flat as $cat) {
            $counts[(int) $cat['id_category']] = (int) $cat['product_count'];
        }
        return $counts;
    }

    // =========================================================================
    // Multi-shop category tree
    // =========================================================================

    public function testCategoryTreeIncludesAllShopsCategories(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $names = $this->getCategoryNames($tree);

        // Categories from both shops should appear
        $this->assertContains('Vêtements', $names, 'Shared category should appear');
        $this->assertContains('Accessoires', $names, 'Shared category should appear');
        $this->assertContains('Art', $names, 'Shop 1 exclusive category should appear');
        $this->assertContains('Electronique', $names, 'Shop 2 exclusive category should appear');
    }

    public function testCategoryTreeIncludesHomeCategory(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $names = $this->getCategoryNames($tree);

        $this->assertContains('Accueil', $names);
    }

    public function testCategoryTreeExcludesRootCategory(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        $ids = array_map(fn($c) => (int) $c['id_category'], $flat);
        $this->assertNotContains(1, $ids, 'Root category (id=1) should be excluded');
    }

    public function testCategoryTreeProductCountsAcrossShops(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $counts = $this->getCategoryCounts($tree);

        // Category 3 (Clothes): P1 (both shops), P4 (both but inactive in shop? no, active but OOS),
        // P6 (inactive), P8 (both shops) → active products: P1, P4, P8 = but P4 is active, P6 is inactive
        // product_shop.active counts: P1(shop1+2), P4(shop1+2), P8(shop1+2) = 3 distinct active products
        // P6 is inactive (ps_product_shop.active=0)
        $this->assertGreaterThan(0, $counts[3] ?? 0, 'Clothes should have products');

        // Category 5 (Art, shop 1 only): P7
        $this->assertEquals(1, $counts[5] ?? 0, 'Art should have 1 product (P7)');

        // Category 6 (Electronics, shop 2 only): P3
        $this->assertEquals(1, $counts[6] ?? 0, 'Electronics should have 1 product (P3)');
    }

    public function testCategoryTreeNoDuplicateCategories(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        $ids = array_map(fn($c) => (int) $c['id_category'], $flat);
        $this->assertEquals(count($ids), count(array_unique($ids)), 'No duplicate categories');
    }

    // =========================================================================
    // Hierarchy structure
    // =========================================================================

    public function testCategoryTreeHierarchy(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        // Find Clothes (id=3)
        $clothes = null;
        foreach ($flat as $cat) {
            if ((int) $cat['id_category'] === 3) {
                $clothes = $cat;
                break;
            }
        }

        $this->assertNotNull($clothes);
        $this->assertEquals(1, $clothes['depth'], 'Clothes should be at depth 1 (child of Home)');
    }

    public function testFlattenPreservesOrder(): void
    {
        $tree = SyncFilterHelper::getCategoryTree(1, 1);
        $flat = SyncFilterHelper::flattenCategoryTree($tree);

        // First should be Home (depth 0), then children (depth 1)
        $this->assertEquals(0, $flat[0]['depth'] ?? -1, 'First category should be depth 0');

        // Verify depth never jumps more than 1
        for ($i = 1; $i < count($flat); $i++) {
            $this->assertLessThanOrEqual(
                $flat[$i - 1]['depth'] + 1,
                $flat[$i]['depth'],
                'Depth should not jump by more than 1 between siblings'
            );
        }
    }
}
