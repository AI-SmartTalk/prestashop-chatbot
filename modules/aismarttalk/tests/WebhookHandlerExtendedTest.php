<?php
/**
 * Extended tests for WebhookHandler — trigger management and config.
 *
 * Covers: isEnabled(), isTriggerEnabled(), getAvailableTriggers(),
 *         saveEnabledTriggers validation, customerPassesConsentFilter.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\WebhookHandler;

class WebhookHandlerExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
    }

    // =========================================================================
    // isEnabled
    // =========================================================================

    public function testIsEnabledReturnsTrueWhenTriggersExist(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_WEBHOOKS_TRIGGERS'] = json_encode(['trigger_a']);
        $this->assertTrue(WebhookHandler::isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse(WebhookHandler::isEnabled());
    }

    public function testIsEnabledReturnsFalseWithEmptyArray(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_WEBHOOKS_TRIGGERS'] = json_encode([]);
        $this->assertFalse(WebhookHandler::isEnabled());
    }

    // =========================================================================
    // isTriggerEnabled
    // =========================================================================

    public function testIsTriggerEnabledTrue(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_WEBHOOKS_TRIGGERS'] = json_encode([
            'ps_on_order_status_changed',
            'ps_on_payment_received',
        ]);

        $this->assertTrue(WebhookHandler::isTriggerEnabled('ps_on_order_status_changed'));
        $this->assertTrue(WebhookHandler::isTriggerEnabled('ps_on_payment_received'));
    }

    public function testIsTriggerEnabledFalse(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_WEBHOOKS_TRIGGERS'] = json_encode(['ps_on_order_status_changed']);

        $this->assertFalse(WebhookHandler::isTriggerEnabled('ps_on_payment_received'));
        $this->assertFalse(WebhookHandler::isTriggerEnabled('nonexistent'));
    }

    public function testIsTriggerEnabledFalseWhenNoTriggers(): void
    {
        $this->assertFalse(WebhookHandler::isTriggerEnabled('ps_on_order_status_changed'));
    }

    // =========================================================================
    // saveEnabledTriggers — validation
    // =========================================================================

    public function testSaveEnabledTriggersFiltersInvalid(): void
    {
        $validTriggers = array_keys(WebhookHandler::getAvailableTriggers());
        $input = array_merge($validTriggers, ['fake_trigger', 'another_fake']);

        WebhookHandler::saveEnabledTriggers($input);

        $saved = WebhookHandler::getEnabledTriggers();
        $this->assertNotContains('fake_trigger', $saved);
        $this->assertNotContains('another_fake', $saved);
        // Valid triggers should be saved
        foreach ($validTriggers as $t) {
            $this->assertContains($t, $saved);
        }
    }

    public function testSaveEmptyTriggersDisablesAll(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_WEBHOOKS_TRIGGERS'] = json_encode(['ps_on_order_status_changed']);

        WebhookHandler::saveEnabledTriggers([]);

        $this->assertEquals([], WebhookHandler::getEnabledTriggers());
    }

    // =========================================================================
    // getAvailableTriggers
    // =========================================================================

    public function testGetAvailableTriggersReturnsArray(): void
    {
        $triggers = WebhookHandler::getAvailableTriggers();

        $this->assertIsArray($triggers);
        $this->assertNotEmpty($triggers);

        // Each trigger should have name and description
        foreach ($triggers as $key => $trigger) {
            $this->assertStringStartsWith('ps_on_', $key);
            $this->assertArrayHasKey('name', $trigger);
            $this->assertArrayHasKey('description', $trigger);
        }
    }

    public function testGetAvailableTriggersContainsExpectedTriggers(): void
    {
        $triggers = WebhookHandler::getAvailableTriggers();

        $this->assertArrayHasKey('ps_on_order_status_changed', $triggers);
        $this->assertArrayHasKey('ps_on_payment_received', $triggers);
        $this->assertArrayHasKey('ps_on_product_out_of_stock', $triggers);
        $this->assertArrayHasKey('ps_on_new_order', $triggers);
        $this->assertArrayHasKey('ps_on_customer_registered', $triggers);
    }

    // =========================================================================
    // Global scoping — reads global even in shop context
    // =========================================================================

    public function testTriggersReadGlobalInMultistore(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        \Configuration::$globalStore['AI_SMART_TALK_WEBHOOKS_TRIGGERS'] = json_encode(['ps_on_new_order']);
        \Configuration::$shopStore[2] = ['AI_SMART_TALK_WEBHOOKS_TRIGGERS' => json_encode(['wrong'])];

        $triggers = WebhookHandler::getEnabledTriggers();
        $this->assertEquals(['ps_on_new_order'], $triggers);
    }
}
