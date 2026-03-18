<?php
/**
 * Tests for WebhookHandler config scoping.
 *
 * Ensures webhook triggers and consent filters are read/written globally.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\WebhookHandler;

class WebhookHandlerConfigTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
    }

    public function testGetEnabledTriggersReadsGlobal(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        \Configuration::$globalStore['AI_SMART_TALK_WEBHOOKS_TRIGGERS'] = json_encode(['trigger_a', 'trigger_b']);
        \Configuration::$shopStore[2] = ['AI_SMART_TALK_WEBHOOKS_TRIGGERS' => json_encode(['wrong'])];

        $triggers = WebhookHandler::getEnabledTriggers();

        $this->assertEquals(['trigger_a', 'trigger_b'], $triggers);
    }

    public function testGetEnabledTriggersReturnsEmptyArrayWhenNotSet(): void
    {
        $this->assertEquals([], WebhookHandler::getEnabledTriggers());
    }

    public function testSaveEnabledTriggersWritesGlobal(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        WebhookHandler::saveEnabledTriggers(['trigger_a']);

        $this->assertArrayHasKey('AI_SMART_TALK_WEBHOOKS_TRIGGERS', \Configuration::$globalStore);
        $this->assertArrayNotHasKey('AI_SMART_TALK_WEBHOOKS_TRIGGERS', \Configuration::$shopStore[2] ?? []);
    }
}
