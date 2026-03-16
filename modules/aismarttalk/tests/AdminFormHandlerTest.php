<?php
/**
 * Tests for AdminFormHandler — admin form submission handling.
 *
 * Covers: handleToggleChatbot (mono/multi), handleSyncSettings, handleProductSyncToggle,
 *         handleCustomerSyncToggle, handleWhiteLabel, handleIframePosition,
 *         flash messages, config scoping.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\AdminFormHandler;

class AdminFormHandlerTest extends TestCase
{
    /** @var \AiSmartTalk */
    private $module;

    /** @var AdminFormHandler */
    private $handler;

    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
        \Tools::reset();

        $this->module = new \AiSmartTalk();
        $this->module->name = 'aismarttalk';

        $this->handler = new AdminFormHandler($this->module, \Context::getContext());
    }

    // =========================================================================
    // handleToggleChatbot — mono-shop
    // =========================================================================

    public function testToggleChatbotMonoShopEnable(): void
    {
        \Tools::setValue('submitToggleChatbot', '1');
        \Tools::setValue('AI_SMART_TALK_ENABLED', '1');

        $output = $this->handler->processAll();

        $this->assertStringContainsString('<ok>', $output);
        $this->assertEquals('1', \Configuration::get('AI_SMART_TALK_ENABLED'));
    }

    public function testToggleChatbotMonoShopDisable(): void
    {
        \Tools::setValue('submitToggleChatbot', '1');
        // Not setting AI_SMART_TALK_ENABLED means false

        $output = $this->handler->processAll();

        $this->assertStringContainsString('<ok>', $output);
    }

    // =========================================================================
    // handleToggleChatbot — multistore
    // =========================================================================

    public function testToggleChatbotMultistoreSavesPerShop(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setShops([
            1 => ['id_shop' => 1, 'name' => 'Shop A'],
            2 => ['id_shop' => 2, 'name' => 'Shop B'],
            3 => ['id_shop' => 3, 'name' => 'Shop C'],
        ]);

        \Tools::setValue('submitToggleChatbot', '1');
        \Tools::setValue('chatbot_shops', ['1', '3']); // Enable shops 1 and 3 only

        $this->handler->processAll();

        $this->assertTrue((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 1));
        $this->assertFalse((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 2));
        $this->assertTrue((bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, 3));
    }

    // =========================================================================
    // handleProductSyncToggle
    // =========================================================================

    public function testProductSyncToggleEnable(): void
    {
        \Tools::setValue('submitProductSync', '1');
        \Tools::setValue('AI_SMART_TALK_PRODUCT_SYNC', '1');

        $output = $this->handler->processAll();

        $this->assertStringContainsString('enabled', $output);
    }

    public function testProductSyncToggleDisable(): void
    {
        \Tools::setValue('submitProductSync', '1');
        \Tools::setValue('AI_SMART_TALK_PRODUCT_SYNC', '0');

        $output = $this->handler->processAll();

        $this->assertStringContainsString('disabled', $output);
    }

    // =========================================================================
    // handleCustomerSyncToggle
    // =========================================================================

    public function testCustomerSyncToggleEnable(): void
    {
        \Tools::setValue('submitCustomerSync', '1');
        \Tools::setValue('AI_SMART_TALK_CUSTOMER_SYNC', '1');

        $output = $this->handler->processAll();

        $this->assertStringContainsString('enabled', $output);
    }

    // =========================================================================
    // handleSyncSettings
    // =========================================================================

    public function testSyncSettingsSavesConsentFilter(): void
    {
        // Force CONTEXT_ALL like getContent() does
        \Shop::setContext(\Shop::CONTEXT_ALL);

        \Tools::setValue('submitSyncSettings', '1');
        \Tools::setValue('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT', 'newsletter');
        \Tools::setValue('sync_filter_category_mode', 'all');

        $this->handler->processAll();

        $this->assertEquals('newsletter', \Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT'));
    }

    public function testSyncSettingsSavesCategoryFilters(): void
    {
        \Shop::setContext(\Shop::CONTEXT_ALL);

        \Tools::setValue('submitSyncSettings', '1');
        \Tools::setValue('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT', 'all');
        \Tools::setValue('sync_filter_category_mode', 'include');
        \Tools::setValue('sync_filter_categories', json_encode([3, 5, 7]));

        $this->handler->processAll();

        $config = \PrestaShop\AiSmartTalk\SyncFilterHelper::getFilterConfig();
        $this->assertEquals('include', $config['mode']);
        $this->assertEquals([3, 5, 7], $config['categories']);
    }

    public function testSyncSettingsAllCategoriesClearsSelection(): void
    {
        \Shop::setContext(\Shop::CONTEXT_ALL);

        \Tools::setValue('submitSyncSettings', '1');
        \Tools::setValue('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT', 'all');
        \Tools::setValue('sync_filter_category_mode', 'all');

        $this->handler->processAll();

        $config = \PrestaShop\AiSmartTalk\SyncFilterHelper::getFilterConfig();
        $this->assertEquals([], $config['categories']);
    }

    public function testSyncSettingsRejectsInvalidConsent(): void
    {
        \Shop::setContext(\Shop::CONTEXT_ALL);

        \Tools::setValue('submitSyncSettings', '1');
        \Tools::setValue('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT', 'invalid_value');
        \Tools::setValue('sync_filter_category_mode', 'all');

        $this->handler->processAll();

        // Should default to 'all'
        $this->assertEquals('all', \Configuration::get('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT'));
    }

    // =========================================================================
    // handleWebhooksSettings
    // =========================================================================

    public function testWebhooksSettingsSaves(): void
    {
        \Shop::setContext(\Shop::CONTEXT_ALL);

        // Use real trigger names (saveEnabledTriggers filters invalid ones)
        \Tools::setValue('submitWebhooksSettings', '1');
        \Tools::setValue('webhooks_triggers', ['ps_on_new_order', 'ps_on_payment_received']);

        $this->handler->processAll();

        $triggers = \PrestaShop\AiSmartTalk\WebhookHandler::getEnabledTriggers();
        $this->assertContains('ps_on_new_order', $triggers);
        $this->assertContains('ps_on_payment_received', $triggers);
    }

    // =========================================================================
    // handleIframePosition
    // =========================================================================

    public function testIframePositionFooter(): void
    {
        \Tools::setValue('submitIframePosition', '1');
        \Tools::setValue('AI_SMART_TALK_IFRAME_POSITION', 'footer');

        $this->handler->processAll();

        $this->assertEquals('footer', \Configuration::get('AI_SMART_TALK_IFRAME_POSITION'));
    }

    public function testIframePositionBeforeFooter(): void
    {
        \Tools::setValue('submitIframePosition', '1');
        \Tools::setValue('AI_SMART_TALK_IFRAME_POSITION', 'before_footer');

        $this->handler->processAll();

        $this->assertEquals('before_footer', \Configuration::get('AI_SMART_TALK_IFRAME_POSITION'));
    }

    public function testIframePositionInvalidDefaultsToFooter(): void
    {
        \Tools::setValue('submitIframePosition', '1');
        \Tools::setValue('AI_SMART_TALK_IFRAME_POSITION', 'invalid');

        $this->handler->processAll();

        $this->assertEquals('footer', \Configuration::get('AI_SMART_TALK_IFRAME_POSITION'));
    }

    // =========================================================================
    // Flash messages
    // =========================================================================

    public function testFlashMessagesDisplayAndClear(): void
    {
        // Simulate a stored flash message
        \Configuration::$globalStore['AI_SMART_TALK_FLASH_MSG'] = '<ok>Done!</ok>';

        $output = $this->handler->processAll();

        $this->assertStringContainsString('Done!', $output);
        // Flash should be cleared after display
        $this->assertFalse(\Configuration::get('AI_SMART_TALK_FLASH_MSG'));
    }

    // =========================================================================
    // OAuth messages
    // =========================================================================

    public function testOAuthSuccessMessage(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_OAUTH_SUCCESS'] = 'Connected!';

        $output = $this->handler->processAll();

        $this->assertStringContainsString('Connected!', $output);
        // Should be cleared
        $this->assertFalse(\Configuration::get('AI_SMART_TALK_OAUTH_SUCCESS'));
    }

    public function testOAuthErrorMessage(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_OAUTH_ERROR'] = 'Auth failed';

        $output = $this->handler->processAll();

        $this->assertStringContainsString('Auth failed', $output);
        $this->assertFalse(\Configuration::get('AI_SMART_TALK_OAUTH_ERROR'));
    }
}
