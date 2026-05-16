<?php
/**
 * Unit tests for PriceFormatter — currency-aware price formatting.
 *
 * Core guarantee: prices are returned as STRINGS with the exact requested decimals.
 * This is what protects 3-decimal currencies like LYD (Libyan dinar) from json_encode()
 * silently dropping trailing zeros (12.000 → 12).
 */

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\PriceFormatter;

class PriceFormatterTest extends TestCase
{
    // =========================================================================
    // decimalsFromCurrency — resolves currency precision with safe fallback
    // =========================================================================

    public function testDecimalsFromCurrencyReturnsDefaultWhenNull(): void
    {
        $this->assertSame(2, PriceFormatter::decimalsFromCurrency(null));
    }

    public function testDecimalsFromCurrencyReadsPrecisionProperty(): void
    {
        $currency = new \stdClass();
        $currency->precision = 3;

        $this->assertSame(3, PriceFormatter::decimalsFromCurrency($currency));
    }

    public function testDecimalsFromCurrencyHandlesEur(): void
    {
        $currency = new \stdClass();
        $currency->precision = 2;

        $this->assertSame(2, PriceFormatter::decimalsFromCurrency($currency));
    }

    public function testDecimalsFromCurrencyHandlesJpyZeroDecimals(): void
    {
        $currency = new \stdClass();
        $currency->precision = 0;

        $this->assertSame(0, PriceFormatter::decimalsFromCurrency($currency));
    }

    public function testDecimalsFromCurrencyFallsBackWhenPropertyMissing(): void
    {
        // PS < 1.7.7 \Currency objects have no `precision` property.
        $currency = new \stdClass();

        $this->assertSame(2, PriceFormatter::decimalsFromCurrency($currency));
    }

    public function testDecimalsFromCurrencyClampsAboveMax(): void
    {
        $currency = new \stdClass();
        $currency->precision = 99;

        $this->assertSame(6, PriceFormatter::decimalsFromCurrency($currency));
    }

    public function testDecimalsFromCurrencyClampsBelowZero(): void
    {
        $currency = new \stdClass();
        $currency->precision = -1;

        $this->assertSame(0, PriceFormatter::decimalsFromCurrency($currency));
    }

    public function testDecimalsFromCurrencyHandlesStringPrecision(): void
    {
        // SQL drivers often return DECIMAL/TINYINT as string in PHP.
        $currency = new \stdClass();
        $currency->precision = '3';

        $this->assertSame(3, PriceFormatter::decimalsFromCurrency($currency));
    }

    // =========================================================================
    // format — the core LYD guarantee
    // =========================================================================

    public function testFormatPreservesTrailingZerosForLyd(): void
    {
        // The Libyan dinar regression: 12.000 must NOT become "12".
        $this->assertSame('12.000', PriceFormatter::format(12, 3));
        $this->assertSame('12.500', PriceFormatter::format(12.5, 3));
        $this->assertSame('0.001', PriceFormatter::format(0.001, 3));
    }

    public function testFormatHandlesEurStandardCase(): void
    {
        $this->assertSame('29.99', PriceFormatter::format(29.99, 2));
        $this->assertSame('30.00', PriceFormatter::format(30, 2));
    }

    public function testFormatHandlesJpyZeroDecimals(): void
    {
        // 100.5 rounds away from zero to 101 — standard half-up accounting behavior.
        $this->assertSame('101', PriceFormatter::format(100.5, 0));
        $this->assertSame('100', PriceFormatter::format(100, 0));
        $this->assertSame('100', PriceFormatter::format(100.4, 0));
    }

    public function testFormatAcceptsNumericString(): void
    {
        $this->assertSame('12.345', PriceFormatter::format('12.345', 3));
    }

    public function testFormatTreatsNullAsZero(): void
    {
        $this->assertSame('0.000', PriceFormatter::format(null, 3));
    }

    public function testFormatTreatsEmptyStringAsZero(): void
    {
        $this->assertSame('0.00', PriceFormatter::format('', 2));
    }

    public function testFormatUsesDotAsDecimalSeparator(): void
    {
        // The output is machine-readable (sent over JSON), never locale-formatted.
        $formatted = PriceFormatter::format(1234.56, 2);

        $this->assertStringNotContainsString(',', $formatted);
        $this->assertStringContainsString('.', $formatted);
        $this->assertSame('1234.56', $formatted);
    }

    public function testFormatHasNoThousandsSeparator(): void
    {
        $this->assertSame('1000000.00', PriceFormatter::format(1000000, 2));
    }

    public function testFormatSurvivesJsonEncodeRoundtrip(): void
    {
        // The whole reason PriceFormatter exists: json_encode(12.000) yields "12",
        // but json_encode("12.000") yields "\"12.000\"" — the trailing zeros survive.
        $formatted = PriceFormatter::format(12, 3);
        $json = json_encode(['price' => $formatted]);
        $decoded = json_decode($json, true);

        $this->assertSame('12.000', $decoded['price']);
    }

    // =========================================================================
    // clampDecimals
    // =========================================================================

    public function testClampDecimalsRespectsRange(): void
    {
        $this->assertSame(0, PriceFormatter::clampDecimals(0));
        $this->assertSame(2, PriceFormatter::clampDecimals(2));
        $this->assertSame(6, PriceFormatter::clampDecimals(6));
        $this->assertSame(0, PriceFormatter::clampDecimals(-5));
        $this->assertSame(6, PriceFormatter::clampDecimals(100));
    }
}
