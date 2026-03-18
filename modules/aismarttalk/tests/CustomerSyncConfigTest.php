<?php
/**
 * Tests for CustomerSync consent filter config scoping.
 *
 * Ensures the consent filter is read from global scope in multistore.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\CustomerSync;

class CustomerSyncConfigTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Shop::reset();
        \Context::reset();
    }

    public function testConsentFilterReadsGlobal(): void
    {
        \Shop::setFeatureActive(true);
        \Shop::setContext(\Shop::CONTEXT_SHOP, 2);

        \Configuration::$globalStore['AI_SMART_TALK_CUSTOMER_SYNC_CONSENT'] = 'newsletter';
        \Configuration::$shopStore[2] = ['AI_SMART_TALK_CUSTOMER_SYNC_CONSENT' => 'all'];

        $customer = new \Customer(1);
        $customer->newsletter = false;
        $customer->optin = false;

        // With 'newsletter' filter, a non-subscriber should NOT match
        $this->assertFalse(CustomerSync::customerMatchesConsentFilter($customer));
    }

    public function testConsentFilterDefaultsToAll(): void
    {
        $customer = new \Customer(1);
        $customer->newsletter = false;
        $customer->optin = false;

        // No filter set = 'all' → any active customer matches
        $this->assertTrue(CustomerSync::customerMatchesConsentFilter($customer));
    }

    public function testInactiveCustomerNeverMatches(): void
    {
        $customer = new \Customer(1);
        $customer->active = false;
        $customer->newsletter = true;

        $this->assertFalse(CustomerSync::customerMatchesConsentFilter($customer));
    }

    /**
     * @dataProvider consentFilterProvider
     */
    public function testConsentFilterModes(string $filter, bool $newsletter, bool $optin, bool $expected): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_CUSTOMER_SYNC_CONSENT'] = $filter;

        $customer = new \Customer(1);
        $customer->active = true;
        $customer->newsletter = $newsletter;
        $customer->optin = $optin;

        $this->assertEquals($expected, CustomerSync::customerMatchesConsentFilter($customer));
    }

    public function consentFilterProvider(): array
    {
        return [
            // filter, newsletter, optin, expected
            'all: any customer' => ['all', false, false, true],
            'newsletter: subscriber' => ['newsletter', true, false, true],
            'newsletter: non-subscriber' => ['newsletter', false, false, false],
            'optin: opted-in' => ['optin', false, true, true],
            'optin: not opted-in' => ['optin', false, false, false],
            'or: newsletter only' => ['newsletter_or_optin', true, false, true],
            'or: optin only' => ['newsletter_or_optin', false, true, true],
            'or: neither' => ['newsletter_or_optin', false, false, false],
            'and: both' => ['newsletter_and_optin', true, true, true],
            'and: newsletter only' => ['newsletter_and_optin', true, false, false],
            'and: optin only' => ['newsletter_and_optin', false, true, false],
        ];
    }
}
