<?php
/**
 * Integration tests for product cleanup logic against a real MySQL database.
 *
 * Verifies that isProductActiveInAnyShop() and cleanOutOfStockProducts()
 * correctly handle multistore scenarios.
 *
 * Requires: docker compose -f docker-compose.test.yml up -d
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\MultistoreHelper;

class CleanupQueryTest extends TestCase
{
    /** @var \PDO */
    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['test_pdo'];
    }

    // =========================================================================
    // isProductActiveInAnyShop
    // =========================================================================

    public function testSharedProductActiveInBothShops(): void
    {
        // P1: active + in stock in both shops
        $this->assertTrue(MultistoreHelper::isProductActiveInAnyShop(1));
    }

    public function testShopExclusiveProductActive(): void
    {
        // P2: only in shop 1, active + in stock
        $this->assertTrue(MultistoreHelper::isProductActiveInAnyShop(2));
        // P3: only in shop 2
        $this->assertTrue(MultistoreHelper::isProductActiveInAnyShop(3));
    }

    public function testOutOfStockEverywhereNotActive(): void
    {
        // P4: active but out of stock in ALL shops
        $this->assertFalse(MultistoreHelper::isProductActiveInAnyShop(4));
    }

    public function testOutOfStockInOneShopButInStockInAnother(): void
    {
        // P5: OOS in shop 1, in stock in shop 2
        $this->assertTrue(MultistoreHelper::isProductActiveInAnyShop(5));
    }

    public function testInactiveProductNotActive(): void
    {
        // P6: inactive in both shops (even though stock > 0)
        $this->assertFalse(MultistoreHelper::isProductActiveInAnyShop(6));
    }

    public function testNonExistentProduct(): void
    {
        $this->assertFalse(MultistoreHelper::isProductActiveInAnyShop(999));
    }

    // =========================================================================
    // isProductActiveInShop — per-shop checks
    // =========================================================================

    public function testProductActiveInSpecificShop(): void
    {
        // P1: active in shop 1
        $this->assertTrue(MultistoreHelper::isProductActiveInShop(1, 1));
        // P1: active in shop 2
        $this->assertTrue(MultistoreHelper::isProductActiveInShop(1, 2));
    }

    public function testProductNotInShop(): void
    {
        // P2: only in shop 1, not in shop 2
        $this->assertFalse(MultistoreHelper::isProductActiveInShop(2, 2));
    }

    public function testProductOutOfStockInSpecificShop(): void
    {
        // P5: OOS in shop 1
        $this->assertFalse(MultistoreHelper::isProductActiveInShop(5, 1));
        // P5: in stock in shop 2
        $this->assertTrue(MultistoreHelper::isProductActiveInShop(5, 2));
    }

    public function testInactiveProductInSpecificShop(): void
    {
        // P6: inactive in shop 1
        $this->assertFalse(MultistoreHelper::isProductActiveInShop(6, 1));
    }

    // =========================================================================
    // Cleanup decision scenarios
    // =========================================================================

    /**
     * Scenario: product goes OOS in shop 1 but still in stock in shop 2.
     * Should NOT be cleaned from knowledge base.
     */
    public function testCleanupDecisionPartialOOS(): void
    {
        // P5 goes OOS in shop 1 → check if we should clean
        $shouldClean = !MultistoreHelper::isProductActiveInAnyShop(5);
        $this->assertFalse($shouldClean, 'Should NOT clean P5 — still in stock in shop 2');
    }

    /**
     * Scenario: product goes OOS in ALL shops.
     * SHOULD be cleaned from knowledge base.
     */
    public function testCleanupDecisionFullOOS(): void
    {
        // P4 is OOS in both shops
        $shouldClean = !MultistoreHelper::isProductActiveInAnyShop(4);
        $this->assertTrue($shouldClean, 'Should clean P4 — out of stock everywhere');
    }

    /**
     * Scenario: product becomes inactive.
     * SHOULD be cleaned from knowledge base.
     */
    public function testCleanupDecisionInactive(): void
    {
        $shouldClean = !MultistoreHelper::isProductActiveInAnyShop(6);
        $this->assertTrue($shouldClean, 'Should clean P6 — inactive everywhere');
    }

    /**
     * Scenario: product deleted from one shop but exists in another.
     * Simulated by checking P2 (shop 1 only) in shop 2.
     */
    public function testCleanupDecisionDeletedFromOneShop(): void
    {
        // P2 doesn't exist in shop 2, but exists in shop 1
        $stillActive = MultistoreHelper::isProductActiveInAnyShop(2);
        $this->assertTrue($stillActive, 'P2 should still be active (shop 1)');
    }
}
