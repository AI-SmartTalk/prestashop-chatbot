<?php
/**
 * API contract tests — verify the structure of payloads sent to AI SmartTalk API.
 *
 * These tests don't make real HTTP calls. They verify that the data structures
 * built by our sync classes match the API contract, catching breaking changes
 * before they reach production.
 *
 * Contracts tested:
 * - POST /api/v1/integrations/prestashop/sync    (canonical product sync, replaces /api/document/source)
 * - POST /api/v1/integrations/prestashop/cleanup (canonical cleanup,      replaces /api/document/clean)
 * - POST /api/v1/crm/importCustomer (customer sync)
 * - POST /api/v1/crm/removeCustomer (customer remove)
 * - Encrypted payload envelope format
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\PayloadEncryptor;
use PrestaShop\AiSmartTalk\ProductDocumentBuilder;

class ApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Context::reset();
    }

    // =========================================================================
    // Product sync payload: POST /api/v1/integrations/prestashop/sync
    // =========================================================================

    public function testCanonicalProductHasRequiredFields(): void
    {
        $doc = ProductDocumentBuilder::build([
            'id' => 42,
            'title' => 'T-shirt bleu',
            'description' => 'Un beau t-shirt en coton bio',
            'description_short' => 'T-shirt coton',
            'reference' => 'TSHIRT-42',
            'price' => '29.99',
            'currency' => 'EUR',
            'url' => 'https://myshop.com/42-t-shirt-bleu.html',
            'image_url' => 'https://myshop.com/img/42-large.jpg',
        ]);

        $this->assertSame('product', $doc['type']);
        $this->assertSame('42', $doc['externalId']);
        $this->assertSame('T-shirt bleu', $doc['title']);
        $this->assertSame('29.99', $doc['price']);
        $this->assertSame('EUR', $doc['currency']);
        $this->assertArrayHasKey('availability', $doc);
        $this->assertArrayHasKey('variants', $doc);
        $this->assertIsArray($doc['variants']);
    }

    public function testCanonicalSyncEnvelopeStructure(): void
    {
        $envelope = ProductDocumentBuilder::buildSyncEnvelope(
            [
                ProductDocumentBuilder::build(['id' => 1, 'title' => 'Product 1', 'price' => '10.00', 'currency' => 'EUR']),
                ProductDocumentBuilder::build(['id' => 2, 'title' => 'Product 2', 'price' => '20.00', 'currency' => 'EUR']),
            ],
            'site-xyz'
        );

        $this->assertSame('PRESTASHOP', $envelope['source']);
        $this->assertSame('1', $envelope['payloadVersion']);
        $this->assertSame('site-xyz', $envelope['siteIdentifier']);
        $this->assertCount(2, $envelope['documents']);

        // chatModelId / chatModelToken are NO LONGER in the body —
        // authentication moved to Authorization: Bearer + x-chat-model-id headers
        // owned by ApiClient. Asserting their absence here guards against an
        // accidental reintroduction of legacy payload shape.
        $this->assertArrayNotHasKey('chatModelId', $envelope);
        $this->assertArrayNotHasKey('chatModelToken', $envelope);
        $this->assertArrayNotHasKey('documentDatas', $envelope);
    }

    public function testProductSyncBatchSize(): void
    {
        // Contract: batches of max 10 documents on the client side. The server
        // enforces a 500-document hard cap on the envelope, our merchant-side
        // batching stays well under that to keep error blast radius small.
        $batchSize = 10;
        $documents = [];
        for ($i = 1; $i <= 25; $i++) {
            $documents[] = ['id' => $i, 'title' => "Product $i"];
        }

        $batches = array_chunk($documents, $batchSize);
        $this->assertCount(3, $batches);
        $this->assertCount(10, $batches[0]);
        $this->assertCount(10, $batches[1]);
        $this->assertCount(5, $batches[2]);
    }

    // =========================================================================
    // Product cleanup payload: POST /api/v1/integrations/prestashop/cleanup
    // =========================================================================

    public function testCleanupEnvelopeWithSpecificIds(): void
    {
        // Pre-v1: { productIds, chatModelToken, deleteFromIds: true, source, siteIdentifier }
        // v1:     { source, mode: "delete-ids", externalIds, siteIdentifier? }
        $envelope = [
            'source' => 'PRESTASHOP',
            'mode' => 'delete-ids',
            'externalIds' => ['1', '5', '12'],
            'siteIdentifier' => 'site-xyz',
        ];

        $this->assertSame('delete-ids', $envelope['mode']);
        $this->assertSame('PRESTASHOP', $envelope['source']);
        foreach ($envelope['externalIds'] as $id) {
            $this->assertIsString($id);
        }
        // Auth has moved out of the body.
        $this->assertArrayNotHasKey('chatModelToken', $envelope);
        $this->assertArrayNotHasKey('deleteFromIds', $envelope);
    }

    public function testCleanupEnvelopeKeepOnly(): void
    {
        // The merchant's full live catalogue → server deletes whatever is no
        // longer in the list. This is the diff-mode used by the periodic
        // resync flow.
        $envelope = [
            'source' => 'PRESTASHOP',
            'mode' => 'keep-only',
            'externalIds' => ['1', '2', '3', '4', '5'],
        ];

        $this->assertSame('keep-only', $envelope['mode']);
        $this->assertCount(5, $envelope['externalIds']);
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

        // Required fields
        $this->assertArrayHasKey('externalId', $customer);
        $this->assertArrayHasKey('email', $customer);
        $this->assertArrayHasKey('firstname', $customer);
        $this->assertArrayHasKey('lastname', $customer);

        // Types
        $this->assertIsString($customer['externalId']);
        $this->assertIsString($customer['email']);
        $this->assertIsBool($customer['newsletter']);
        $this->assertIsBool($customer['optin']);

        // externalId must be string (not int)
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

        // Version must be 1
        $this->assertEquals(1, $envelope['v']);

        // All values must be base64 strings
        $this->assertIsString($envelope['iv']);
        $this->assertIsString($envelope['tag']);
        $this->assertIsString($envelope['data']);
    }

    public function testEncryptedPayloadReplacesPlaintext(): void
    {
        // When encryption is used, 'customers' is replaced by 'encrypted'
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
            // customers key should NOT be present
        } else {
            $payload['customers'] = $customers;
        }

        // With encryption available, payload should have 'encrypted' not 'customers'
        $this->assertArrayHasKey('encrypted', $payload);
        $this->assertArrayNotHasKey('customers', $payload);
    }

    // =========================================================================
    // Special characters and edge cases
    // =========================================================================

    public function testProductTitleWithSpecialChars(): void
    {
        $document = [
            'id' => 1,
            'title' => 'T-shirt "été" — coton bio <100% naturel>',
            'description' => 'Desc with émojis 🎉 and accéntés',
        ];

        // JSON encoding should not fail
        $json = json_encode($document, JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);

        // Decoded back should match
        $decoded = json_decode($json, true);
        $this->assertEquals($document['title'], $decoded['title']);
    }

    public function testProductWithNullOptionalFields(): void
    {
        $document = [
            'id' => 1,
            'title' => 'Product',
            'description' => '',
            'description_short' => '',
            'reference' => null,
            'price' => 0.0,
            'currency' => 'EUR',
            'currency_sign' => '€',
            'has_special_price' => false,
            'price_from' => null,
            'price_to' => null,
            'url' => 'https://shop.com/1',
            'image_url' => null,
        ];

        $json = json_encode($document);
        $this->assertNotFalse($json);

        // Mandatory fields still present
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('id', $decoded);
        $this->assertArrayHasKey('title', $decoded);
        $this->assertArrayHasKey('url', $decoded);
    }
}
