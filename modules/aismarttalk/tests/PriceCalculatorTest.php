<?php
/**
 * Unit tests for PriceCalculator — the discount-aware price resolver.
 *
 * The tests drive Product::getPriceStatic via a callable mock to simulate the
 * shop's internal pricing engine. We assert on the returned PriceInfo, not
 * on the exact arguments forwarded to PrestaShop — the contract of this
 * helper is the OUTPUT (PriceInfo), not the call shape.
 */

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\PriceCalculator;
use PrestaShop\AiSmartTalk\PriceInfo;

class PriceCalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        \Product::resetPriceStaticMock();
    }

    /**
     * Configure Product::getPriceStatic to return $final when reductions are
     * applied and $original otherwise. Optionally populate the OUT
     * $specific_price_output with reduction_type metadata.
     */
    private function mockPrices(float $final, float $original, ?string $reductionType = null): void
    {
        \Product::$priceStaticMock = function ($idProduct, $idPa, $usereduc, &$spOut) use ($final, $original, $reductionType) {
            if ($reductionType !== null && $usereduc) {
                $spOut = ['reduction_type' => $reductionType, 'reduction' => 0.2];
            }
            return $usereduc ? $final : $original;
        };
    }

    // ─────────────────────────────────────────────────────────
    // No discount
    // ─────────────────────────────────────────────────────────

    public function testNoDiscountWhenFinalEqualsOriginal(): void
    {
        $this->mockPrices(29.99, 29.99);

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertInstanceOf(PriceInfo::class, $info);
        $this->assertFalse($info->hasDiscount);
        $this->assertSame(29.99, $info->finalPrice);
        $this->assertSame(29.99, $info->originalPrice);
        $this->assertSame(0.0, $info->discountAmount);
        $this->assertSame(0, $info->discountPercent);
        $this->assertSame('none', $info->discountType);
    }

    public function testNoDiscountWhenDifferenceIsBelowEpsilon(): void
    {
        // Sub-millicent difference must be treated as no discount — accounts
        // for floating-point rounding between the two getPriceStatic calls.
        $this->mockPrices(29.99, 29.99005);

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertFalse($info->hasDiscount);
        $this->assertSame('none', $info->discountType);
    }

    // ─────────────────────────────────────────────────────────
    // Percentage discount
    // ─────────────────────────────────────────────────────────

    public function testPercentageDiscount(): void
    {
        // 20% off : 100 → 80
        $this->mockPrices(80.00, 100.00, 'percentage');

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertTrue($info->hasDiscount);
        $this->assertSame(80.00, $info->finalPrice);
        $this->assertSame(100.00, $info->originalPrice);
        $this->assertSame(20.00, $info->discountAmount);
        $this->assertSame(20, $info->discountPercent);
        $this->assertSame('percentage', $info->discountType);
    }

    public function testPercentageDiscountRoundsHalfAwayFromZero(): void
    {
        // 12.5% off → display "-13%" (closer to user's expectation than "-12%")
        $this->mockPrices(87.50, 100.00, 'percentage');

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertSame(13, $info->discountPercent);
    }

    // ─────────────────────────────────────────────────────────
    // Amount discount
    // ─────────────────────────────────────────────────────────

    public function testAmountDiscount(): void
    {
        // -5€ flat : 35 → 30
        $this->mockPrices(30.00, 35.00, 'amount');

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertTrue($info->hasDiscount);
        $this->assertSame(5.00, $info->discountAmount);
        $this->assertSame(14, $info->discountPercent); // round(100 * 5/35)
        $this->assertSame('amount', $info->discountType);
    }

    // ─────────────────────────────────────────────────────────
    // Computed (group/catalog reduction, no single specific_price)
    // ─────────────────────────────────────────────────────────

    public function testComputedDiscountWhenSpecificPriceOutputEmpty(): void
    {
        // Group reduction applied but no specific_price drove it → 'computed'
        $this->mockPrices(90.00, 100.00, null);

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertTrue($info->hasDiscount);
        $this->assertSame(10, $info->discountPercent);
        $this->assertSame('computed', $info->discountType);
    }

    public function testComputedDiscountWhenSpecificPriceTypeIsUnknown(): void
    {
        // Defensive: an unknown reduction_type should not leak into the payload.
        \Product::$priceStaticMock = function ($idProduct, $idPa, $usereduc, &$spOut) {
            if ($usereduc) {
                $spOut = ['reduction_type' => 'something_weird'];
            }
            return $usereduc ? 80.00 : 100.00;
        };

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertSame('computed', $info->discountType);
    }

    // ─────────────────────────────────────────────────────────
    // Edge cases
    // ─────────────────────────────────────────────────────────

    public function testFreeProductFromHundredPercentDiscount(): void
    {
        $this->mockPrices(0.00, 50.00, 'percentage');

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertTrue($info->hasDiscount);
        $this->assertSame(100, $info->discountPercent);
        $this->assertSame(50.00, $info->discountAmount);
    }

    public function testZeroOriginalPriceDoesNotDivideByZero(): void
    {
        // Pathological data (price 0 in catalog) — no discount can be expressed.
        $this->mockPrices(0.00, 0.00, null);

        $info = PriceCalculator::calculate(1, 0, 2);

        $this->assertFalse($info->hasDiscount);
        $this->assertSame(0, $info->discountPercent);
    }

    public function testCombinationCalcUsesIdProductAttribute(): void
    {
        // Variants flow through the same logic — confirm idPa is forwarded.
        $forwardedIdPa = null;
        \Product::$priceStaticMock = function ($idProduct, $idPa, $usereduc, &$spOut) use (&$forwardedIdPa) {
            $forwardedIdPa = $idPa;
            return $usereduc ? 12.000 : 15.000;
        };

        PriceCalculator::calculate(7, 42, 3);

        $this->assertSame(42, $forwardedIdPa);
    }

    public function testLydPrecisionRespected(): void
    {
        // 3-decimal currency — the helper must not silently round trip through 2 decimals.
        $this->mockPrices(11.250, 12.500, 'percentage');

        $info = PriceCalculator::calculate(1, 0, 3);

        $this->assertSame(11.250, $info->finalPrice);
        $this->assertSame(12.500, $info->originalPrice);
        $this->assertSame(1.250, $info->discountAmount);
        $this->assertSame(10, $info->discountPercent);
    }
}
