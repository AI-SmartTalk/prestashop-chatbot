<?php
/**
 * Unit tests for CanonicalProductMapper — the PrestaShop → canonical v1
 * document mapping. The contract is validated server-side by a strict Zod
 * schema, so these tests pin the shape that keeps the backend from rejecting:
 *   - prices are integer minor units + ISO currency + display string;
 *   - attributes are {name,value} (not the legacy {group,value});
 *   - promotion fields appear only when discounted;
 *   - combinations become canonical variants.
 */

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\CanonicalProductMapper;
use PrestaShop\AiSmartTalk\PriceInfo;

class CanonicalProductMapperTest extends TestCase
{
    public function testToMoneyConvertsToMinorUnitsWithDisplay(): void
    {
        $money = CanonicalProductMapper::toMoney(19.99, 2, 'eur', '€');

        $this->assertSame(1999, $money['amount']);
        $this->assertSame('EUR', $money['currency']);
        $this->assertSame('19.99 €', $money['display']);
    }

    public function testToMoneyHonoursThreeDecimalCurrencies(): void
    {
        $money = CanonicalProductMapper::toMoney(12.5, 3, 'LYD', null);

        $this->assertSame(12500, $money['amount']);
        $this->assertSame('LYD', $money['currency']);
        $this->assertSame('12.500', $money['display']);
    }

    public function testToMoneyReturnsNullForEmptyAmount(): void
    {
        $this->assertNull(CanonicalProductMapper::toMoney(null, 2, 'EUR'));
        $this->assertNull(CanonicalProductMapper::toMoney('', 2, 'EUR'));
    }

    public function testAvailabilityFromQuantity(): void
    {
        $this->assertSame('in_stock', CanonicalProductMapper::availability(5));
        $this->assertSame('out_of_stock', CanonicalProductMapper::availability(0));
    }

    public function testMapAttributesRenamesGroupToName(): void
    {
        $mapped = CanonicalProductMapper::mapAttributes([
            ['group' => 'Couleur', 'value' => 'Rouge'],
            ['group' => '', 'value' => 'ignored'],
        ]);

        $this->assertSame([['name' => 'Couleur', 'value' => 'Rouge']], $mapped);
    }

    public function testDiscountTypeOnlyKeepsContractValues(): void
    {
        $this->assertSame('percentage', CanonicalProductMapper::discountType('percentage'));
        $this->assertSame('amount', CanonicalProductMapper::discountType('amount'));
        $this->assertNull(CanonicalProductMapper::discountType('computed'));
        $this->assertNull(CanonicalProductMapper::discountType('none'));
    }

    public function testMapBuildsCanonicalProductWithoutPromotion(): void
    {
        $priceInfo = new PriceInfo(19.99, 19.99, 0.0, 0, false, 'none');

        $doc = CanonicalProductMapper::map([
            'idProduct' => 1234,
            'name' => 'T-shirt',
            'description' => 'desc',
            'descriptionShort' => 'short',
            'reference' => 'REF1',
            'brand' => 'Acme',
            'decimals' => 2,
            'currency' => 'EUR',
            'sign' => '€',
            'url' => 'https://shop/p/1234',
            'imageUrl' => 'https://shop/img/1234.jpg',
            'quantity' => 7,
            'availableDate' => null,
            'categories' => ['Vêtements', 'Homme'],
            'variants' => [],
            'priceInfo' => $priceInfo,
        ]);

        $this->assertSame('product', $doc['type']);
        $this->assertSame('1234', $doc['externalId']);
        $this->assertSame(1999, $doc['price']['amount']);
        $this->assertSame('in_stock', $doc['availability']);
        $this->assertSame(7, $doc['quantity']);
        $this->assertSame(['Vêtements', 'Homme'], $doc['categories']);
        $this->assertSame([], $doc['variants']);
        $this->assertArrayNotHasKey('originalPrice', $doc, 'No promo block when not discounted.');
    }

    public function testMapEmitsPromotionAndRestockDate(): void
    {
        $priceInfo = new PriceInfo(15.0, 20.0, 5.0, 25, true, 'percentage');

        $doc = CanonicalProductMapper::map([
            'idProduct' => 9,
            'name' => 'Promo item',
            'decimals' => 2,
            'currency' => 'EUR',
            'sign' => '€',
            'quantity' => 0,
            'availableDate' => '2026-07-01 00:00:00',
            'categories' => [],
            'variants' => [],
            'priceInfo' => $priceInfo,
        ]);

        $this->assertSame('out_of_stock', $doc['availability']);
        $this->assertSame('2026-07-01', $doc['restockDate']);
        $this->assertSame(2000, $doc['originalPrice']['amount']);
        $this->assertSame(25, $doc['discountPercent']);
        $this->assertSame('percentage', $doc['discountType']);
    }

    public function testMapVariantConvertsCombination(): void
    {
        $variant = CanonicalProductMapper::mapVariant([
            'id_product_attribute' => 42,
            'reference' => 'SKU-42',
            'ean13' => '1234567890123',
            'upc' => '',
            'price' => '17.99',
            'image_url' => 'https://shop/img/v42.jpg',
            'attributes' => [['group' => 'Taille', 'value' => 'M']],
            'quantity' => 3,
            'original_price' => null,
        ], 2, 'EUR', '€');

        $this->assertSame('42', $variant['externalId']);
        $this->assertSame('SKU-42', $variant['sku']);
        $this->assertSame('1234567890123', $variant['gtin']);
        $this->assertSame(1799, $variant['price']['amount']);
        $this->assertSame('in_stock', $variant['availability']);
        $this->assertSame(3, $variant['quantity']);
        $this->assertSame([['name' => 'Taille', 'value' => 'M']], $variant['attributes']);
        $this->assertArrayNotHasKey('originalPrice', $variant);
    }
}
