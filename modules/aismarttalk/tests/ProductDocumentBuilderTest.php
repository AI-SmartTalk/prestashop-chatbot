<?php
/**
 * Tests for ProductDocumentBuilder — legacy snake_case array → canonical
 * `ProductDocument` envelope expected by `/api/v1/integrations/prestashop/sync`.
 *
 * Each test below mirrors a server-side assertion in the AI SmartTalk repo
 * (`PrestashopDocumentAdapter.test.ts`). When the two ever drift it is a bug:
 * the canonical contract must accept what we send, byte for byte.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\ProductDocumentBuilder;

class ProductDocumentBuilderTest extends TestCase
{
    // =========================================================================
    // Happy path
    // =========================================================================

    public function testBuildsCanonicalShapeFromTheLegacyArray(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 1234,
            'title' => 'Red T-Shirt',
            'description' => '<p>A great shirt.</p>',
            'description_short' => 'Soft & comfy',
            'reference' => 'TSH-RED',
            'price' => '19.90',
            'currency' => 'EUR',
            'url' => 'https://shop.example.com/p/tsh-red',
            'image_url' => 'https://shop.example.com/img/tsh-red.jpg',
            'quantity' => 12,
            'variants' => [],
        ]);

        self::assertSame('product', $doc['type']);
        self::assertSame('1234', $doc['externalId']);
        self::assertSame('Red T-Shirt', $doc['title']);
        self::assertSame('TSH-RED', $doc['sku']);  // sku falls back to reference
        self::assertSame('TSH-RED', $doc['reference']);
        self::assertSame('19.90', $doc['price']);
        self::assertSame('EUR', $doc['currency']);
        self::assertSame(12, $doc['quantity']);
        self::assertSame('in_stock', $doc['availability']);
        self::assertSame('https://shop.example.com/p/tsh-red', $doc['url']);
        self::assertSame('https://shop.example.com/img/tsh-red.jpg', $doc['image']);
        self::assertSame('Soft & comfy', $doc['descriptionShort']);
        self::assertStringContainsString('great shirt', $doc['description']);
    }

    public function testFallsBackThroughIdentifierAliases(): void
    {
        $cases = [
            [['id_product' => 7, 'title' => 'x'], '7'],
            [['product_id' => '8', 'title' => 'x'], '8'],
            [['reference' => 'SKU-9', 'title' => 'x'], 'SKU-9'],
        ];
        foreach ($cases as [$input, $expected]) {
            self::assertSame(
                $expected,
                ProductDocumentBuilder::build($input)['externalId']
            );
        }
    }

    public function testTitleFallsBackToExternalIdWhenMissing(): void
    {
        $doc = ProductDocumentBuilder::build(['id' => 11]);
        self::assertSame('11', $doc['title']);
    }

    // =========================================================================
    // Currency / stock / quantity normalisation
    // =========================================================================

    public function testCurrencyIsUppercasedAndValidated(): void
    {
        self::assertSame('EUR', ProductDocumentBuilder::build([
            'id' => 1, 'title' => 'x', 'currency' => ' eur ',
        ])['currency']);

        self::assertArrayNotHasKey('currency', ProductDocumentBuilder::build([
            'id' => 1, 'title' => 'x', 'currency' => 'Euro',
        ]));
    }

    /**
     * @dataProvider stockProvider
     * @param mixed $input
     */
    public function testStockSignalNormalisation($input, string $expected): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 1,
            'title' => 'x',
            'availability' => $input,
        ]);
        self::assertSame($expected, $doc['availability']);
    }

    /**
     * @return array<int,array{0:mixed,1:string}>
     */
    public function stockProvider(): array
    {
        return [
            [true, 'in_stock'],
            [false, 'out_of_stock'],
            [3, 'in_stock'],
            [0, 'out_of_stock'],
            ['preorder', 'preorder'],
            ['weird', 'unknown'],
        ];
    }

    public function testAvailabilityInferredFromVariantsWhenNotProvided(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 9,
            'title' => 'Sneakers',
            'variants' => [
                ['id_product_attribute' => 1, 'in_stock' => false, 'attributes' => []],
                ['id_product_attribute' => 2, 'in_stock' => true, 'attributes' => []],
            ],
        ]);
        self::assertSame('in_stock', $doc['availability']);
    }

    // =========================================================================
    // Variants
    // =========================================================================

    public function testMapsCombinationsAsCanonicalVariants(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 99,
            'title' => 'Sneakers',
            'price' => '59.00',
            'currency' => 'EUR',
            'variants' => [
                [
                    'id_product_attribute' => 1,
                    'reference' => 'SNK-R-40',
                    'price' => '59.00',
                    'in_stock' => true,
                    'quantity' => 5,
                    'attributes' => [
                        ['group' => 'Color', 'value' => 'Red'],
                        ['group' => 'Size', 'value' => '40'],
                    ],
                ],
                [
                    'id_product_attribute' => 2,
                    'reference' => 'SNK-B-42',
                    'price' => '65',
                    'in_stock' => false,
                    'quantity' => 0,
                    'attributes' => [
                        ['group' => 'Color', 'value' => 'Blue'],
                        ['group' => 'Size', 'value' => '42'],
                    ],
                ],
            ],
        ]);

        self::assertCount(2, $doc['variants']);
        self::assertSame('99:1', $doc['variants'][0]['externalId']);
        self::assertSame('SNK-R-40', $doc['variants'][0]['sku']);
        self::assertSame('59.00', $doc['variants'][0]['price']);
        self::assertSame('in_stock', $doc['variants'][0]['stockStatus']);
        self::assertSame(
            ['Color' => 'Red', 'Size' => '40'],
            $doc['variants'][0]['attributes']
        );

        self::assertSame('99:2', $doc['variants'][1]['externalId']);
        self::assertSame('out_of_stock', $doc['variants'][1]['stockStatus']);
    }

    public function testKeepsPreComposedVariantExternalId(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 5,
            'title' => 'Mug',
            'variants' => [
                ['externalId' => '5:42', 'attributes' => ['Color' => 'White']],
            ],
        ]);
        self::assertSame('5:42', $doc['variants'][0]['externalId']);
    }

    // =========================================================================
    // Promotions (DEV-842 carry-over)
    // =========================================================================

    public function testMapsLegacySnakeCasePromoBlock(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 7,
            'title' => 'Discounted shirt',
            'price' => '12.00',
            'currency' => 'EUR',
            'original_price' => '20.00',
            'discount_percent' => 40,
            'discount_amount' => '8.00',
            'discount_type' => 'percentage',
            'price_from' => '2026-05-01 00:00:00',
            'price_to' => '2026-06-01 23:59:59',
        ]);

        self::assertSame('20.00', $doc['originalPrice']);
        self::assertSame(40.0, $doc['discountPercent']);
        self::assertSame('8.00', $doc['discountAmount']);
        self::assertSame('percentage', $doc['discountType']);
        self::assertSame('2026-05-01T00:00:00Z', $doc['discountStartsAt']);
        self::assertSame('2026-06-01T23:59:59Z', $doc['discountEndsAt']);
    }

    public function testTreatsZeroDateSentinelAsNull(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 7,
            'title' => 'x',
            'price_from' => '0000-00-00 00:00:00',
            'price_to' => '0000-00-00 00:00:00',
        ]);
        // Null promo fields are omitted from the envelope, not sent as `null`,
        // to keep the payload tight and let the server's Zod defaults apply.
        self::assertArrayNotHasKey('discountStartsAt', $doc);
        self::assertArrayNotHasKey('discountEndsAt', $doc);
    }

    public function testClampsDiscountPercent(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 1,
            'title' => 'x',
            'discount_percent' => '150',
        ]);
        self::assertSame(100.0, $doc['discountPercent']);
    }

    public function testPropagatesPromoFieldsOntoVariants(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 9,
            'title' => 'Sneakers',
            'variants' => [
                [
                    'id_product_attribute' => 1,
                    'price' => '59.00',
                    'original_price' => '80.00',
                    'discount_percent' => 26,
                    'discount_type' => 'percentage',
                    'attributes' => [['group' => 'Color', 'value' => 'Red']],
                    'in_stock' => true,
                ],
            ],
        ]);
        self::assertSame('80.00', $doc['variants'][0]['originalPrice']);
        self::assertSame(26.0, $doc['variants'][0]['discountPercent']);
    }

    // =========================================================================
    // Envelope builder
    // =========================================================================

    public function testBuildSyncEnvelopeShapesTheBatch(): void
    {
        $envelope = ProductDocumentBuilder::buildSyncEnvelope(
            [['type' => 'product', 'externalId' => '1', 'title' => 'A']],
            'shop-eu'
        );
        self::assertSame('PRESTASHOP', $envelope['source']);
        self::assertSame('1', $envelope['payloadVersion']);
        self::assertSame('shop-eu', $envelope['siteIdentifier']);
        self::assertCount(1, $envelope['documents']);
    }

    public function testBuildSyncEnvelopeOmitsEmptySiteIdentifier(): void
    {
        $envelope = ProductDocumentBuilder::buildSyncEnvelope(
            [['type' => 'product', 'externalId' => '1', 'title' => 'A']],
            null
        );
        self::assertArrayNotHasKey('siteIdentifier', $envelope);
    }

    // =========================================================================
    // Failure mode
    // =========================================================================

    public function testThrowsOnMissingExternalId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProductDocumentBuilder::build(['title' => 'Orphan']);
    }
}
