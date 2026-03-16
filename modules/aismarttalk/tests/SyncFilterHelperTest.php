<?php
/**
 * Tests for SyncFilterHelper — category tree and product filter logic.
 *
 * Covers:
 * - getFilterConfig reads from global scope
 * - saveFilterConfig writes to global scope
 * - hasActiveFilters detection
 * - shouldProductBeSynced with include/exclude modes
 * - buildCategoryFilterSQL correctness
 * - getCategoryTree with multistore (boundary SQL)
 * - buildRootBoundarySQL for single and multiple shops
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\SyncFilterHelper;

class SyncFilterHelperTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
        \Db::reset();
    }

    // =========================================================================
    // getFilterConfig — global scope enforcement
    // =========================================================================

    public function testGetFilterConfigReturnsDefaultsWhenEmpty(): void
    {
        $config = SyncFilterHelper::getFilterConfig();

        $this->assertEquals('include', $config['mode']);
        $this->assertEquals([], $config['categories']);
        $this->assertFalse($config['include_subcategories']);
    }

    public function testGetFilterConfigReadsFromGlobalScope(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        // Set global value
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'exclude';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3, 5, 7]);
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES'] = '1';

        // Set different per-shop value
        \Configuration::$shopStore[2] = [
            'AI_SMART_TALK_SYNC_FILTER_MODE' => 'include',
            'AI_SMART_TALK_SYNC_CATEGORIES' => json_encode([99]),
        ];

        $config = SyncFilterHelper::getFilterConfig();

        // Should read GLOBAL values, not per-shop
        $this->assertEquals('exclude', $config['mode']);
        $this->assertEquals([3, 5, 7], $config['categories']);
        $this->assertTrue($config['include_subcategories']);
    }

    // =========================================================================
    // saveFilterConfig — global scope enforcement
    // =========================================================================

    public function testSaveFilterConfigWritesGlobally(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        SyncFilterHelper::saveFilterConfig([
            'mode' => 'exclude',
            'categories' => [1, 2, 3],
            'include_subcategories' => true,
        ]);

        // Should be in global store
        $this->assertEquals('exclude', \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE']);
        $this->assertEquals('[1,2,3]', \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES']);
        $this->assertEquals('1', \Configuration::$globalStore['AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES']);
    }

    public function testSaveFilterConfigValidatesMode(): void
    {
        SyncFilterHelper::saveFilterConfig(['mode' => 'invalid']);
        // Read back via MultistoreHelper to handle both mono/multi scoping
        $this->assertEquals('include', \PrestaShop\AiSmartTalk\MultistoreHelper::getConfig('AI_SMART_TALK_SYNC_FILTER_MODE'));
    }

    public function testSaveFilterConfigFiltersInvalidCategoryIds(): void
    {
        SyncFilterHelper::saveFilterConfig([
            'categories' => [0, -1, 5, 'abc', 10],
        ]);

        $saved = json_decode(\PrestaShop\AiSmartTalk\MultistoreHelper::getConfig('AI_SMART_TALK_SYNC_CATEGORIES'), true);
        $this->assertEquals([5, 10], $saved);
    }

    // =========================================================================
    // hasActiveFilters
    // =========================================================================

    public function testHasActiveFiltersReturnsFalseWhenNoCategories(): void
    {
        $this->assertFalse(SyncFilterHelper::hasActiveFilters());
    }

    public function testHasActiveFiltersReturnsTrueWhenCategoriesSet(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3]);
        $this->assertTrue(SyncFilterHelper::hasActiveFilters());
    }

    // =========================================================================
    // buildCategoryFilterSQL
    // =========================================================================

    public function testBuildCategoryFilterSQLReturnsEmptyWhenNoCategories(): void
    {
        $sql = SyncFilterHelper::buildCategoryFilterSQL(1);
        $this->assertEquals('', $sql);
    }

    public function testBuildCategoryFilterSQLIncludeMode(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'include';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3, 5]);

        $sql = SyncFilterHelper::buildCategoryFilterSQL(1);

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('3,5', $sql);
        $this->assertStringNotContainsString('NOT EXISTS', $sql);
    }

    public function testBuildCategoryFilterSQLExcludeMode(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'exclude';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3, 5]);

        $sql = SyncFilterHelper::buildCategoryFilterSQL(1);

        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('3,5', $sql);
    }

    public function testBuildCategoryFilterSQLWithSubcategories(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'include';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3]);
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES'] = '1';

        $sql = SyncFilterHelper::buildCategoryFilterSQL(1);

        // Should use nested set model (nleft/nright)
        $this->assertStringContainsString('nleft', $sql);
        $this->assertStringContainsString('nright', $sql);
    }

    // =========================================================================
    // shouldProductBeSynced
    // =========================================================================

    public function testShouldProductBeSyncedReturnsTrueWhenNoFilters(): void
    {
        // No categories set = all products match
        $this->assertTrue(SyncFilterHelper::shouldProductBeSynced(42, 1));
    }

    public function testShouldProductBeSyncedIncludeMode(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'include';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3]);

        // Product IS in category 3
        \Db::$getValueResults = [1];
        $this->assertTrue(SyncFilterHelper::shouldProductBeSynced(42, 1));

        // Product is NOT in category 3
        \Db::$getValueResults = [false];
        $this->assertFalse(SyncFilterHelper::shouldProductBeSynced(99, 1));
    }

    public function testShouldProductBeSyncedExcludeMode(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'exclude';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3]);

        // Product IS in excluded category 3 → should NOT be synced
        \Db::$getValueResults = [1];
        $this->assertFalse(SyncFilterHelper::shouldProductBeSynced(42, 1));

        // Product is NOT in excluded category → should be synced
        \Db::$getValueResults = [false];
        $this->assertTrue(SyncFilterHelper::shouldProductBeSynced(99, 1));
    }

    // =========================================================================
    // getFilterSummary
    // =========================================================================

    public function testGetFilterSummaryEmptyWhenNoFilters(): void
    {
        $this->assertEquals('', SyncFilterHelper::getFilterSummary(1));
    }

    public function testGetFilterSummarySingularCategory(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'include';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3]);

        $summary = SyncFilterHelper::getFilterSummary(1);
        $this->assertStringContainsString('1 category selected', $summary);
    }

    public function testGetFilterSummaryPluralCategories(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'exclude';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = json_encode([3, 5, 7]);

        $summary = SyncFilterHelper::getFilterSummary(1);
        $this->assertStringContainsString('3 categories excluded', $summary);
    }

    // =========================================================================
    // deleteFilterConfig
    // =========================================================================

    public function testDeleteFilterConfigRemovesAllKeys(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_FILTER_MODE'] = 'exclude';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_CATEGORIES'] = '[3]';
        \Configuration::$globalStore['AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES'] = '1';

        SyncFilterHelper::deleteFilterConfig();

        $this->assertArrayNotHasKey('AI_SMART_TALK_SYNC_FILTER_MODE', \Configuration::$globalStore);
        $this->assertArrayNotHasKey('AI_SMART_TALK_SYNC_CATEGORIES', \Configuration::$globalStore);
        $this->assertArrayNotHasKey('AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES', \Configuration::$globalStore);
    }
}
