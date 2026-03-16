<?php
/**
 * Tests for PayloadEncryptor — AES-256-GCM encryption for API payloads.
 *
 * Covers: encrypt(), key derivation, envelope format, edge cases.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\PayloadEncryptor;

class PayloadEncryptorTest extends TestCase
{
    protected function setUp(): void
    {
        \PrestaShopLogger::reset();
    }

    public function testEncryptReturnsValidEnvelope(): void
    {
        $result = PayloadEncryptor::encrypt(['key' => 'value'], 'test-token', 'model-123');

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['v']);
        $this->assertArrayHasKey('iv', $result);
        $this->assertArrayHasKey('tag', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testEncryptedDataIsBase64(): void
    {
        $result = PayloadEncryptor::encrypt(['test' => true], 'token', 'model');

        // All values should be valid base64
        $this->assertNotFalse(base64_decode($result['iv'], true));
        $this->assertNotFalse(base64_decode($result['tag'], true));
        $this->assertNotFalse(base64_decode($result['data'], true));
    }

    public function testIvIs12Bytes(): void
    {
        $result = PayloadEncryptor::encrypt(['test' => true], 'token', 'model');
        $iv = base64_decode($result['iv']);
        $this->assertEquals(12, strlen($iv));
    }

    public function testTagIs16Bytes(): void
    {
        $result = PayloadEncryptor::encrypt(['test' => true], 'token', 'model');
        $tag = base64_decode($result['tag']);
        $this->assertEquals(16, strlen($tag));
    }

    public function testEncryptReturnsNullWithEmptyToken(): void
    {
        $this->assertNull(PayloadEncryptor::encrypt(['data' => 1], '', 'model'));
    }

    public function testEncryptReturnsNullWithEmptyChatModelId(): void
    {
        $this->assertNull(PayloadEncryptor::encrypt(['data' => 1], 'token', ''));
    }

    public function testEncryptReturnsNullWithBothEmpty(): void
    {
        $this->assertNull(PayloadEncryptor::encrypt(['data' => 1], '', ''));
    }

    public function testDifferentInputsProduceDifferentCiphertext(): void
    {
        $result1 = PayloadEncryptor::encrypt(['a' => 1], 'token', 'model');
        $result2 = PayloadEncryptor::encrypt(['b' => 2], 'token', 'model');

        $this->assertNotEquals($result1['data'], $result2['data']);
    }

    public function testSameInputProducesDifferentIvAndCiphertext(): void
    {
        $payload = ['test' => 'determinism'];
        $result1 = PayloadEncryptor::encrypt($payload, 'token', 'model');
        $result2 = PayloadEncryptor::encrypt($payload, 'token', 'model');

        // IV should be different (random)
        $this->assertNotEquals($result1['iv'], $result2['iv']);
        // Ciphertext should be different (different IV)
        $this->assertNotEquals($result1['data'], $result2['data']);
    }

    public function testEncryptsComplexPayload(): void
    {
        $payload = [
            'customers' => [
                ['email' => 'a@test.com', 'name' => 'Alice'],
                ['email' => 'b@test.com', 'name' => 'Bob'],
            ],
            'nested' => ['deep' => ['value' => true]],
        ];

        $result = PayloadEncryptor::encrypt($payload, 'token123', 'model456');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['data']);
    }

    public function testEncryptsEmptyArray(): void
    {
        $result = PayloadEncryptor::encrypt([], 'token', 'model');
        $this->assertIsArray($result);
    }
}
