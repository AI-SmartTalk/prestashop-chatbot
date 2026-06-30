<?php
/**
 * Unit tests for StockStatusHelper — the reference implementation of the unified
 * "stock" block of the AI SmartTalk product-document standard.
 *
 * The three acceptance cases from the Dotit feedback (DEV-857) are the heart of
 * this suite:
 *   1. in stock                -> status in_stock,    restock_date null
 *   2. out of stock, no date   -> status out_of_stock, restock_date null
 *   3. out of stock, with date -> status out_of_stock, restock_date "YYYY-MM-DD"
 *
 * These guarantee the agent receives exactly what it needs to answer
 * "Indisponible actuellement, réappro prévu le {date}".
 */

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\StockStatusHelper;

class StockStatusHelperTest extends TestCase
{
    // =========================================================================
    // Acceptance case 1 — product in stock
    // =========================================================================

    public function testInStockReturnsInStockStatusAndNoRestockDate(): void
    {
        $stock = StockStatusHelper::normalize(12, null);

        $this->assertSame('in_stock', $stock['status']);
        $this->assertSame(12, $stock['quantity']);
        $this->assertNull($stock['restock_date'], 'An in-stock product has nothing to restock.');
    }

    public function testInStockIgnoresAnyAvailabilityDate(): void
    {
        // Even if a date is set on the product, an in-stock product never advertises a restock date.
        $stock = StockStatusHelper::normalize(3, '2026-07-01');

        $this->assertSame('in_stock', $stock['status']);
        $this->assertNull($stock['restock_date']);
    }

    // =========================================================================
    // Acceptance case 2 — out of stock, no restock date
    // =========================================================================

    public function testOutOfStockWithoutDate(): void
    {
        $stock = StockStatusHelper::normalize(0, null);

        $this->assertSame('out_of_stock', $stock['status']);
        $this->assertSame(0, $stock['quantity']);
        $this->assertNull($stock['restock_date']);
    }

    public function testOutOfStockWithZeroDateSentinel(): void
    {
        // PrestaShop stores "0000-00-00" when no availability date is set.
        $stock = StockStatusHelper::normalize(0, '0000-00-00');

        $this->assertSame('out_of_stock', $stock['status']);
        $this->assertNull($stock['restock_date']);
    }

    public function testOutOfStockWithZeroDateTimeSentinel(): void
    {
        $stock = StockStatusHelper::normalize(0, '0000-00-00 00:00:00');

        $this->assertSame('out_of_stock', $stock['status']);
        $this->assertNull($stock['restock_date']);
    }

    // =========================================================================
    // Acceptance case 3 — out of stock, with restock date
    // =========================================================================

    public function testOutOfStockWithDate(): void
    {
        $stock = StockStatusHelper::normalize(0, '2026-06-15');

        $this->assertSame('out_of_stock', $stock['status']);
        $this->assertSame(0, $stock['quantity']);
        $this->assertSame('2026-06-15', $stock['restock_date']);
    }

    public function testOutOfStockWithDatetimeKeepsDayOnly(): void
    {
        $stock = StockStatusHelper::normalize(0, '2026-06-15 09:30:00');

        $this->assertSame('out_of_stock', $stock['status']);
        $this->assertSame('2026-06-15', $stock['restock_date'], 'restock_date is a bare ISO day.');
    }

    // =========================================================================
    // Robustness — quantity coercion & malformed dates
    // =========================================================================

    public function testNegativeQuantityIsOutOfStock(): void
    {
        $stock = StockStatusHelper::normalize(-5, null);

        $this->assertSame('out_of_stock', $stock['status']);
        $this->assertSame(-5, $stock['quantity']);
    }

    public function testStringQuantityIsCoercedToInt(): void
    {
        $stock = StockStatusHelper::normalize('7', null);

        $this->assertSame('in_stock', $stock['status']);
        $this->assertSame(7, $stock['quantity']);
    }

    public function testMalformedDateNormalizesToNull(): void
    {
        $stock = StockStatusHelper::normalize(0, 'not-a-date');

        $this->assertNull($stock['restock_date']);
    }

    public function testImpossibleCalendarDateNormalizesToNull(): void
    {
        // 2026-02-30 does not exist.
        $stock = StockStatusHelper::normalize(0, '2026-02-30');

        $this->assertNull($stock['restock_date']);
    }

    public function testNormalizeDateTrimsWhitespace(): void
    {
        $this->assertSame('2026-06-15', StockStatusHelper::normalizeDate('  2026-06-15  '));
    }

    public function testNormalizeDateReturnsNullForNull(): void
    {
        $this->assertNull(StockStatusHelper::normalizeDate(null));
    }

    // =========================================================================
    // Contract shape — the block always carries the three standard keys
    // =========================================================================

    public function testBlockAlwaysHasTheThreeStandardKeys(): void
    {
        $stock = StockStatusHelper::normalize(0, null);

        $this->assertArrayHasKey('status', $stock);
        $this->assertArrayHasKey('quantity', $stock);
        $this->assertArrayHasKey('restock_date', $stock);
    }

    public function testStatusIsAlwaysOneOfTheTwoAllowedValues(): void
    {
        $allowed = [StockStatusHelper::STATUS_IN_STOCK, StockStatusHelper::STATUS_OUT_OF_STOCK];

        $this->assertContains(StockStatusHelper::normalize(1)['status'], $allowed);
        $this->assertContains(StockStatusHelper::normalize(0)['status'], $allowed);
    }
}
