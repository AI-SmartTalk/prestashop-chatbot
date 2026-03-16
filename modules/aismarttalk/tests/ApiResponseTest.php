<?php
/**
 * Tests for ApiResponse — HTTP response value object.
 *
 * Covers: constructor, isSuccess(), get() with dot notation.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\ApiResponse;

class ApiResponseTest extends TestCase
{
    public function testSuccessful200Response(): void
    {
        $response = new ApiResponse(200, '{"status":"ok"}');
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->httpCode);
        $this->assertNull($response->error);
        $this->assertEquals(['status' => 'ok'], $response->data);
    }

    public function testSuccessful201Response(): void
    {
        $response = new ApiResponse(201, '{"id":1}');
        $this->assertTrue($response->isSuccess());
    }

    public function test299IsStillSuccess(): void
    {
        $response = new ApiResponse(299, '{}');
        $this->assertTrue($response->isSuccess());
    }

    public function test300IsNotSuccess(): void
    {
        $response = new ApiResponse(300, '{}');
        $this->assertFalse($response->isSuccess());
    }

    public function test404IsNotSuccess(): void
    {
        $response = new ApiResponse(404, '{"error":"not found"}');
        $this->assertFalse($response->isSuccess());
    }

    public function test500IsNotSuccess(): void
    {
        $response = new ApiResponse(500, '{"error":"internal"}');
        $this->assertFalse($response->isSuccess());
    }

    public function testTransportErrorMakesItFail(): void
    {
        $response = new ApiResponse(200, '{"status":"ok"}', 'Connection timeout');
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('Connection timeout', $response->error);
    }

    public function testZeroHttpCodeWithError(): void
    {
        $response = new ApiResponse(0, null, 'cURL failed');
        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->data);
    }

    public function testNullBody(): void
    {
        $response = new ApiResponse(200, null);
        $this->assertNull($response->data);
        $this->assertNull($response->body);
    }

    public function testEmptyBody(): void
    {
        $response = new ApiResponse(200, '');
        $this->assertNull($response->data);
    }

    public function testInvalidJsonBody(): void
    {
        $response = new ApiResponse(200, 'not json');
        $this->assertNull($response->data);
        $this->assertEquals('not json', $response->body);
    }

    // =========================================================================
    // get() with dot notation
    // =========================================================================

    public function testGetReturnsFullDataWhenNoKey(): void
    {
        $response = new ApiResponse(200, '{"a":1,"b":2}');
        $this->assertEquals(['a' => 1, 'b' => 2], $response->get());
    }

    public function testGetReturnsDefaultWhenNoData(): void
    {
        $response = new ApiResponse(200, null);
        $this->assertEquals('default', $response->get(null, 'default'));
    }

    public function testGetTopLevelKey(): void
    {
        $response = new ApiResponse(200, '{"status":"ok","count":5}');
        $this->assertEquals('ok', $response->get('status'));
        $this->assertEquals(5, $response->get('count'));
    }

    public function testGetNestedKey(): void
    {
        $response = new ApiResponse(200, '{"data":{"user":{"name":"John"}}}');
        $this->assertEquals('John', $response->get('data.user.name'));
    }

    public function testGetMissingKeyReturnsDefault(): void
    {
        $response = new ApiResponse(200, '{"a":1}');
        $this->assertNull($response->get('missing'));
        $this->assertEquals('fallback', $response->get('missing', 'fallback'));
    }

    public function testGetMissingNestedKeyReturnsDefault(): void
    {
        $response = new ApiResponse(200, '{"a":{"b":1}}');
        $this->assertNull($response->get('a.c'));
        $this->assertNull($response->get('x.y.z'));
    }

    public function testGetReturnsNullValueNotDefault(): void
    {
        $response = new ApiResponse(200, '{"key":null}');
        $this->assertNull($response->get('key', 'default'));
    }

    public function testGetReturnsFalseValueNotDefault(): void
    {
        $response = new ApiResponse(200, '{"key":false}');
        $this->assertFalse($response->get('key', 'default'));
    }

    public function testGetReturnsZeroValueNotDefault(): void
    {
        $response = new ApiResponse(200, '{"key":0}');
        $this->assertEquals(0, $response->get('key', 'default'));
    }

    public function testGetReturnsArray(): void
    {
        $response = new ApiResponse(200, '{"data":{"items":[1,2,3]}}');
        $this->assertEquals([1, 2, 3], $response->get('data.items'));
    }
}
