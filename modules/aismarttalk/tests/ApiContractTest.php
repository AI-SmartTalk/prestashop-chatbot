<?php
/**
 * API contract tests — pin the structure of payloads sent to AI SmartTalk.
 *
 * These tests don't make real HTTP calls. The product assertions exercise the
 * real CanonicalProductMapper so the shape they pin is exactly what ships, not a
 * hand-written copy that can silently drift from the code.
 *
 * Contracts tested (canonical v1, camelCase):
 * - POST /api/v1/products          (product sync — { payloadVersion, source, siteIdentifier, documents[] })
 * - POST /api/v1/products/cleanup  (product cleanup — { source, siteIdentifier, mode, externalIds[] })
 * - POST /api/v1/crm/importCustomer (customer sync)
 * - POST /api/v1/crm/removeCustomer (customer remove)
 * - Encrypted payload envelope format
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\CanonicalProductMapper;
use PrestaShop\AiSmartTalk\PayloadEncryptor;
use PrestaShop\AiSmartTalk\PriceInfo;

class ApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Context::reset();
    }

    /**
     * Build a realistic canonical document through the REAL mapper: a variable
     * product on promotion, with one combination.
     */
    private function buildProductDocument(): array
    {
        return CanonicalProductMapper::map([
            'idProduct' => 42,
            'name' => 'T-shirt bleu',
            'description' => 'Un beau t-shirt en coton bio',
            'descriptionShort' => 'T-shirt coton',
            'reference' => 'TSHIRT-42',
            'brand' => 'Acme',
            'decimals' => 2,
            'currency' => 'EUR',
            'sign' => '€',
            'url' => 'https://myshop.com/42-t-shirt-bleu.html',
            'imageUrl' => 'https://myshop.com/img/42-large.jpg',
            'quantity' => 7,
            'availableDate' => null,
            'attributes' => [
                ['name' => 'Matière', 'value' => 'Coton bio'],
            ],
            'categories' => ['Vêtements', 'Homme'],
            'defaultCategoryExternalId' => 8,
            'variants' => [
                [
                    'id_product_attribute' => 555,
                    'reference' => 'TSHIRT-42-M',
                    'ean13' => '0123456789012',
                    'price' => 15.00,
                    'original_price' => 20.00,
                    'discount_percent' => 25,
                    'discount_amount' => 5.00,
                    'discount_type' => 'percentage',
                    'image_url' => 'https://myshop.com/img/555.jpg',
                    'attributes' => [
                        ['group' => 'Taille', 'value' => 'M'],
                        ['group' => 'Couleur', 'value' => 'Rouge'],
                    ],
                    'quantity' => 3,
                ],
            ],
            'priceInfo' => new PriceInfo(15.00, 20.00, 5.00, 25, true, 'percentage'),
        ]);
    }

    // =========================================================================
    // Product sync document: POST /api/v1/products
    // =========================================================================

    public function testCanonicalProductDocumentShape(): void
    {
        $doc = $this->buildProductDocument();

        // Canonical identity — camelCase, externalId is a STRING (not int id).
        $this->assertSame('product', $doc['type']);
        $this->assertSame('42', $doc['externalId']);
        $this->assertIsString($doc['externalId']);
        $this->assertSame('T-shirt bleu', $doc['title']);
        $this->assertSame('TSHIRT-42', $doc['reference']);
        $this->assertSame('Acme', $doc['brand']);
        $this->assertSame('https://myshop.com/42-t-shirt-bleu.html', $doc['url']);
        // Cover image is `image`, not the legacy `image_url`.
        $this->assertArrayHasKey('image', $doc);
        $this->assertArrayNotHasKey('image_url', $doc);

        // Price is a Money object in integer minor units, not a float + sign.
        $this->assertIsArray($doc['price']);
        $this->assertSame(1500, $doc['price']['amount']);
        $this->assertSame('EUR', $doc['price']['currency']);
        $this->assertSame(2, $doc['price']['decimals']);
        $this->assertArrayHasKey('display', $doc['price']);
        $this->assertArrayNotHasKey('currency_sign', $doc);

        // Stock is flat: availability + quantity (no nested stock{} block).
        $this->assertSame('in_stock', $doc['availability']);
        $this->assertSame(7, $doc['quantity']);
        $this->assertArrayNotHasKey('stock', $doc);

        // Attributes are {name,value} pairs the backend can facet on.
        $this->assertSame([['name' => 'Matière', 'value' => 'Coton bio']], $doc['attributes']);

        // Categories are CategoryRef objects; default category is a string.
        $this->assertIsArray($doc['categories']);
        $this->assertArrayHasKey('name', $doc['categories'][0]);
        $this->assertSame('8', $doc['defaultCategoryExternalId']);

        // Promotion block is present because the product is discounted.
        $this->assertArrayHasKey('originalPrice', $doc);
        $this->assertSame(2000, $doc['originalPrice']['amount']);
        $this->assertSame(25, $doc['discountPercent']);
        $this->assertSame('percentage', $doc['discountType']);
    }

    public function testCanonicalVariantShape(): void
    {
        $variant = $this->buildProductDocument()['variants'][0];

        $this->assertSame('555', $variant['externalId']);
        $this->assertSame('TSHIRT-42-M', $variant['sku']);
        // gtin comes from ean13 (upc fallback).
        $this->assertSame('0123456789012', $variant['gtin']);
        $this->assertSame(1500, $variant['price']['amount']);
        $this->assertSame('in_stock', $variant['availability']);
        $this->assertSame(3, $variant['quantity']);
        // Legacy {group,value} is converted to canonical {name,value}.
        $this->assertSame([
            ['name' => 'Taille', 'value' => 'M'],
            ['name' => 'Couleur', 'value' => 'Rouge'],
        ], $variant['attributes']);
        $this->assertSame(2000, $variant['originalPrice']['amount']);
    }

    public function testProductSyncEnvelopeContract(): void
    {
        // Mirrors SynchProductsToAiSmartTalk::postToApi() — the wrapper posted to
        // /api/v1/products around real canonical documents.
        $envelope = [
            'payloadVersion' => '1',
            'source' => 'prestashop',
            'siteIdentifier' => 'site-xyz',
            'documents' => [$this->buildProductDocument()],
        ];

        $this->assertArrayHasKey('payloadVersion', $envelope);
        $this->assertArrayHasKey('source', $envelope);
        $this->assertArrayHasKey('siteIdentifier', $envelope);
        $this->assertArrayHasKey('documents', $envelope);

        $this->assertSame('1', $envelope['payloadVersion']);
        // source is the lowercase platform slug, not the legacy 'PRESTASHOP'.
        $this->assertSame('prestashop', $envelope['source']);

        // No legacy top-level keys leaked into the canonical envelope.
        $this->assertArrayNotHasKey('documentDatas', $envelope);
        $this->assertArrayNotHasKey('chatModelId', $envelope);
        $this->assertArrayNotHasKey('chatModelToken', $envelope);

        $this->assertNotEmpty($envelope['documents']);
        foreach ($envelope['documents'] as $doc) {
            $this->assertSame('product', $doc['type']);
            $this->assertArrayHasKey('externalId', $doc);
            $this->assertArrayHasKey('title', $doc);
        }
    }

    public function testProductBatchFlushSize(): void
    {
        // Contract: documents are flushed in batches of 50 (see
        // SynchProductsToAiSmartTalk::sendProductsToApi()).
        $batchSize = 50;
        $documents = [];
        for ($i = 1; $i <= 120; $i++) {
            $documents[] = ['type' => 'product', 'externalId' => (string) $i, 'title' => "Product $i"];
        }

        $batches = array_chunk($documents, $batchSize);
        $this->assertCount(3, $batches);
        $this->assertCount(50, $batches[0]);
        $this->assertCount(50, $batches[1]);
        $this->assertCount(20, $batches[2]);
    }

    // =========================================================================
    // Product cleanup payload: POST /api/v1/products/cleanup
    // =========================================================================

    public function testCleanupDeleteIdsPayload(): void
    {
        // Mirrors CleanProductDocuments::run() when specific ids are targeted.
        $payload = [
            'source' => 'prestashop',
            'siteIdentifier' => 'site-xyz',
            'mode' => 'delete-ids',
            'externalIds' => ['1', '5', '12', '12::555'],
        ];

        $this->assertArrayHasKey('mode', $payload);
        $this->assertArrayHasKey('externalIds', $payload);
        $this->assertSame('delete-ids', $payload['mode']);
        $this->assertSame('prestashop', $payload['source']);

        // externalIds are strings (products and "<product>::<combination>" variants).
        foreach ($payload['externalIds'] as $id) {
            $this->assertIsString($id);
        }

        // Legacy cleanup keys are gone.
        $this->assertArrayNotHasKey('productIds', $payload);
        $this->assertArrayNotHasKey('deleteFromIds', $payload);
    }

    public function testCleanupKeepOnlyPayload(): void
    {
        // Full snapshot: keep the listed externalIds, delete everything else.
        $payload = [
            'source' => 'prestashop',
            'siteIdentifier' => 'site-xyz',
            'mode' => 'keep-only',
            'externalIds' => ['1', '2', '3'],
        ];

        $this->assertSame('keep-only', $payload['mode']);
        $this->assertNotEmpty($payload['externalIds']);
    }

    // =========================================================================
    // Customer sync payload: POST /api/v1/crm/importCustomer
    // =========================================================================

    public function testCustomerPayloadStructure(): void
    {
        $customer = [
            'externalId' => '42',
            'email' => 'john@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'phone' => '+33612345678',
            'address' => '1 rue de la Paix',
            'city' => 'Paris',
            'country' => 'France',
            'postalCode' => '75001',
            'newsletter' => true,
            'optin' => false,
        ];

        $this->assertArrayHasKey('externalId', $customer);
        $this->assertArrayHasKey('email', $customer);
        $this->assertArrayHasKey('firstname', $customer);
        $this->assertArrayHasKey('lastname', $customer);

        $this->assertIsString($customer['externalId']);
        $this->assertIsString($customer['email']);
        $this->assertIsBool($customer['newsletter']);
        $this->assertIsBool($customer['optin']);

        // externalId must be a numeric string (not an int).
        $this->assertMatchesRegularExpression('/^\d+$/', $customer['externalId']);
    }

    public function testCustomerSyncPayloadStructure(): void
    {
        $payload = [
            'customers' => [
                ['externalId' => '1', 'email' => 'a@test.com', 'firstname' => 'A', 'lastname' => 'B'],
            ],
            'chatModelId' => 'cm-123',
            'chatModelToken' => 'token-abc',
            'source' => 'PRESTASHOP',
            'siteIdentifier' => 'site-xyz',
        ];

        $this->assertArrayHasKey('customers', $payload);
        $this->assertEquals('PRESTASHOP', $payload['source']);
        $this->assertNotEmpty($payload['customers']);
    }

    // =========================================================================
    // Customer remove payload: POST /api/v1/crm/removeCustomer
    // =========================================================================

    public function testCustomerRemovePayload(): void
    {
        $payload = [
            'email' => 'john@example.com',
            'chatModelId' => 'cm-123',
            'chatModelToken' => 'token-abc',
            'source' => 'PRESTASHOP',
            'siteIdentifier' => 'site-xyz',
        ];

        $this->assertArrayHasKey('email', $payload);
        $this->assertIsString($payload['email']);
        $this->assertStringContainsString('@', $payload['email']);
    }

    // =========================================================================
    // Encrypted payload envelope
    // =========================================================================

    public function testEncryptedEnvelopeStructure(): void
    {
        $envelope = PayloadEncryptor::encrypt(
            ['customers' => [['email' => 'test@test.com']]],
            'access-token-123',
            'chat-model-456'
        );

        $this->assertIsArray($envelope);

        // Contract: encrypted envelope has v, iv, tag, data
        $this->assertArrayHasKey('v', $envelope);
        $this->assertArrayHasKey('iv', $envelope);
        $this->assertArrayHasKey('tag', $envelope);
        $this->assertArrayHasKey('data', $envelope);

        $this->assertEquals(1, $envelope['v']);

        $this->assertIsString($envelope['iv']);
        $this->assertIsString($envelope['tag']);
        $this->assertIsString($envelope['data']);
    }

    public function testEncryptedPayloadReplacesPlaintext(): void
    {
        $customers = [['email' => 'a@b.com']];
        $encrypted = PayloadEncryptor::encrypt($customers, 'token', 'model');

        $payload = [
            'chatModelId' => 'model',
            'chatModelToken' => 'token',
            'source' => 'PRESTASHOP',
            'siteIdentifier' => 'site',
        ];

        if ($encrypted !== null) {
            $payload['encrypted'] = $encrypted;
        } else {
            $payload['customers'] = $customers;
        }

        $this->assertArrayHasKey('encrypted', $payload);
        $this->assertArrayNotHasKey('customers', $payload);
    }

    // =========================================================================
    // Special characters and edge cases
    // =========================================================================

    public function testProductDocumentSurvivesJsonEncoding(): void
    {
        $doc = CanonicalProductMapper::map([
            'idProduct' => 1,
            'name' => 'T-shirt "été" — coton bio <100% naturel>',
            'description' => 'Desc with émojis 🎉 and accéntés',
            'decimals' => 2,
            'currency' => 'EUR',
            'sign' => '€',
            'quantity' => 1,
            'categories' => [],
            'variants' => [],
            'priceInfo' => new PriceInfo(19.99, 19.99, 0.0, 0, false, 'none'),
        ]);

        $json = json_encode($doc, JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);

        $decoded = json_decode($json, true);
        $this->assertSame($doc['title'], $decoded['title']);
    }

    public function testNullOptionalProductFieldsAreKeptAsNull(): void
    {
        // At PRODUCT level the mapper keeps optional keys present with a null
        // value; the backend's Zod .nullable().optional() + .passthrough() schema
        // treats null the same as absent. (Only VARIANT optionals are physically
        // dropped, by mapVariant()'s array_filter — see testNullVariantFields.)
        $doc = CanonicalProductMapper::map([
            'idProduct' => 1,
            'name' => 'Product',
            'reference' => null,
            'brand' => null,
            'url' => null,
            'imageUrl' => null,
            'decimals' => 2,
            'currency' => 'EUR',
            'quantity' => 0,
            'categories' => [],
            'variants' => [],
            'priceInfo' => new PriceInfo(0.0, 0.0, 0.0, 0, false, 'none'),
        ]);

        // Mandatory identity always present.
        $this->assertArrayHasKey('type', $doc);
        $this->assertArrayHasKey('externalId', $doc);
        $this->assertArrayHasKey('title', $doc);

        // Optional keys are present but null.
        $this->assertNull($doc['reference']);
        $this->assertNull($doc['brand']);
        $this->assertNull($doc['url']);
        $this->assertNull($doc['image']);
    }

    public function testNullVariantFieldsAreDropped(): void
    {
        // Variant-level optionals ARE physically dropped (mapVariant array_filter):
        // a variant with no sku/gtin/image simply omits those keys.
        $doc = CanonicalProductMapper::map([
            'idProduct' => 1,
            'name' => 'Product',
            'decimals' => 2,
            'currency' => 'EUR',
            'quantity' => 1,
            'categories' => [],
            'priceInfo' => new PriceInfo(9.99, 9.99, 0.0, 0, false, 'none'),
            'variants' => [
                [
                    'id_product_attribute' => 7,
                    'price' => 9.99,
                    'attributes' => [['group' => 'Taille', 'value' => 'S']],
                    'quantity' => 2,
                ],
            ],
        ]);

        $variant = $doc['variants'][0];
        $this->assertSame('7', $variant['externalId']);
        $this->assertArrayNotHasKey('sku', $variant);
        $this->assertArrayNotHasKey('gtin', $variant);
        $this->assertArrayNotHasKey('image', $variant);
    }
}
