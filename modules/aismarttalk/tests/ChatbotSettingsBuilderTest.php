<?php
/**
 * Tests for ChatbotSettingsBuilder — chatbot embed settings construction.
 *
 * Covers: build(), resolveAutoLogin(), mergeEmbedConfig(), getEmbedConfigAvatarUrl(),
 *         applyCustomizationOverrides(), GDPR overrides, color themes.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\AiSmartTalk\ChatbotSettingsBuilder;

class ChatbotSettingsBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
        \Context::reset();
    }

    // =========================================================================
    // resolveAutoLogin
    // =========================================================================

    public function testAutoLoginExplicitlyOn(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_AUTO_LOGIN'] = '1';
        $this->assertTrue(ChatbotSettingsBuilder::resolveAutoLogin(null));
    }

    public function testAutoLoginExplicitlyOff(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_AUTO_LOGIN'] = '0';
        $this->assertFalse(ChatbotSettingsBuilder::resolveAutoLogin(null));
    }

    public function testAutoLoginDefaultsToTrueWhenNoEmbedConfig(): void
    {
        $this->assertTrue(ChatbotSettingsBuilder::resolveAutoLogin(null));
    }

    public function testAutoLoginFallsBackToEmbedConfigTrue(): void
    {
        $this->assertTrue(ChatbotSettingsBuilder::resolveAutoLogin(['enableAutoLogin' => true]));
    }

    public function testAutoLoginFallsBackToEmbedConfigFalse(): void
    {
        $this->assertFalse(ChatbotSettingsBuilder::resolveAutoLogin(['enableAutoLogin' => false]));
    }

    public function testAutoLoginDefaultsTrueWhenEmbedConfigMissesKey(): void
    {
        $this->assertTrue(ChatbotSettingsBuilder::resolveAutoLogin(['otherKey' => 'value']));
    }

    public function testAutoLoginPluginChoiceOverridesEmbedConfig(): void
    {
        // Plugin says off, embed says true → plugin wins
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_AUTO_LOGIN'] = '0';
        $this->assertFalse(ChatbotSettingsBuilder::resolveAutoLogin(['enableAutoLogin' => true]));
        // Plugin says on, embed says false → plugin wins
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_AUTO_LOGIN'] = '1';
        $this->assertTrue(ChatbotSettingsBuilder::resolveAutoLogin(['enableAutoLogin' => false]));
    }

    public function testAutoLoginLegacyOnOffStillHonored(): void
    {
        // A shop upgrading from the tri-state era keeps 'on'/'off' semantics.
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_AUTO_LOGIN'] = 'off';
        $this->assertFalse(ChatbotSettingsBuilder::resolveAutoLogin(['enableAutoLogin' => true]));
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_AUTO_LOGIN'] = 'on';
        $this->assertTrue(ChatbotSettingsBuilder::resolveAutoLogin(['enableAutoLogin' => false]));
    }

    // =========================================================================
    // mergeEmbedConfig
    // =========================================================================

    public function testMergeEmbedConfigAddsNewKeys(): void
    {
        $settings = ['chatModelId' => 'abc'];
        $embedConfig = ['buttonText' => 'Chat!', 'chatSize' => 'large'];

        $result = ChatbotSettingsBuilder::mergeEmbedConfig($settings, $embedConfig);

        $this->assertEquals('Chat!', $result['buttonText']);
        $this->assertEquals('large', $result['chatSize']);
    }

    public function testMergeEmbedConfigDoesNotOverrideProtectedKeys(): void
    {
        $settings = ['chatModelId' => 'original', 'apiUrl' => 'https://my.api', 'lang' => 'fr'];
        $embedConfig = ['chatModelId' => 'hacked', 'apiUrl' => 'https://evil.com', 'lang' => 'en', 'buttonText' => 'OK'];

        $result = ChatbotSettingsBuilder::mergeEmbedConfig($settings, $embedConfig);

        // Protected keys should NOT be overridden
        $this->assertEquals('original', $result['chatModelId']);
        $this->assertEquals('https://my.api', $result['apiUrl']);
        $this->assertEquals('fr', $result['lang']);
        // Non-protected keys should be merged
        $this->assertEquals('OK', $result['buttonText']);
    }

    public function testMergeEmbedConfigWithNull(): void
    {
        $settings = ['key' => 'value'];
        $this->assertEquals($settings, ChatbotSettingsBuilder::mergeEmbedConfig($settings, null));
    }

    public function testMergeEmbedConfigWithEmptyArray(): void
    {
        $settings = ['key' => 'value'];
        $this->assertEquals($settings, ChatbotSettingsBuilder::mergeEmbedConfig($settings, []));
    }

    // =========================================================================
    // getEmbedConfigAvatarUrl
    // =========================================================================

    public function testGetAvatarUrlFromEmbedConfig(): void
    {
        $this->assertEquals(
            'https://cdn.example.com/avatar.png',
            ChatbotSettingsBuilder::getEmbedConfigAvatarUrl(['avatarUrl' => 'https://cdn.example.com/avatar.png'])
        );
    }

    public function testGetAvatarUrlReturnsEmptyWhenMissing(): void
    {
        $this->assertEquals('', ChatbotSettingsBuilder::getEmbedConfigAvatarUrl(null));
        $this->assertEquals('', ChatbotSettingsBuilder::getEmbedConfigAvatarUrl([]));
        $this->assertEquals('', ChatbotSettingsBuilder::getEmbedConfigAvatarUrl(['other' => 'val']));
    }

    public function testGetAvatarUrlReturnsEmptyWhenEmptyString(): void
    {
        $this->assertEquals('', ChatbotSettingsBuilder::getEmbedConfigAvatarUrl(['avatarUrl' => '']));
    }

    // =========================================================================
    // applyCustomizationOverrides
    // =========================================================================

    public function testTextOverridesAppliedWhenSet(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_BUTTON_TEXT'] = 'Help me!';
        \Configuration::$globalStore['AI_SMART_TALK_CHAT_SIZE'] = 'large';

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);

        $this->assertEquals('Help me!', $result['buttonText']);
        $this->assertEquals('large', $result['chatSize']);
    }

    public function testTextOverridesNotAppliedWhenEmpty(): void
    {
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides(['buttonText' => 'original']);
        $this->assertEquals('original', $result['buttonText']);
    }

    public function testToggleOverrideOn(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_FEEDBACK'] = 'on';
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertTrue($result['enableFeedback']);
    }

    public function testToggleOverrideOff(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_VOICE_INPUT'] = 'off';
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertFalse($result['enableVoiceInput']);
    }

    public function testToggleOverrideIgnoredWhenEmpty(): void
    {
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides(['enableFeedback' => true]);
        $this->assertTrue($result['enableFeedback']);
    }

    public function testToggleBinaryStoredOn(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_ATTACHMENT'] = '1';
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides(['enableAttachment' => false]);
        $this->assertTrue($result['enableAttachment']);
    }

    public function testToggleBinaryStoredOff(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_ATTACHMENT'] = '0';
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides(['enableAttachment' => true]);
        $this->assertFalse($result['enableAttachment']);
    }

    public function testToggleLegacyEmptyStringInheritsPlatform(): void
    {
        // Legacy tri-state "Default" ('') → no explicit choice → inherit platform.
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_FEEDBACK'] = '';
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides(['enableFeedback' => true]);
        $this->assertTrue($result['enableFeedback']);
    }

    public function testExplicitBinaryParsing(): void
    {
        $this->assertNull(ChatbotSettingsBuilder::explicitBinary('AI_SMART_TALK_ENABLE_VOICE_MODE'));
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_VOICE_MODE'] = '';
        $this->assertNull(ChatbotSettingsBuilder::explicitBinary('AI_SMART_TALK_ENABLE_VOICE_MODE'));
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_VOICE_MODE'] = '1';
        $this->assertTrue(ChatbotSettingsBuilder::explicitBinary('AI_SMART_TALK_ENABLE_VOICE_MODE'));
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_VOICE_MODE'] = '0';
        $this->assertFalse(ChatbotSettingsBuilder::explicitBinary('AI_SMART_TALK_ENABLE_VOICE_MODE'));
        \Configuration::$globalStore['AI_SMART_TALK_ENABLE_VOICE_MODE'] = 'off';
        $this->assertFalse(ChatbotSettingsBuilder::explicitBinary('AI_SMART_TALK_ENABLE_VOICE_MODE'));
    }

    public function testRequireLoginExplicitOnForcesLogin(): void
    {
        // Stored '1' → plugin authoritative: forces login even if the platform said false.
        \Configuration::$globalStore['AI_SMART_TALK_REQUIRE_AUTHENTICATION'] = '1';
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides(['requireAuthentication' => false]);
        $this->assertTrue($result['requireAuthentication']);
    }

    public function testRequireLoginExplicitOffDisablesLogin(): void
    {
        // Stored '0' → plugin authoritative: disables login even if the platform said true.
        \Configuration::$globalStore['AI_SMART_TALK_REQUIRE_AUTHENTICATION'] = '0';
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides(['requireAuthentication' => true]);
        $this->assertFalse($result['requireAuthentication']);
    }

    public function testRequireLoginInheritsPlatformWhenNeverSet(): void
    {
        // No stored key → keep whatever the platform embed config set (never silently dropped)...
        $inherited = ChatbotSettingsBuilder::applyCustomizationOverrides(['requireAuthentication' => true]);
        $this->assertTrue($inherited['requireAuthentication']);
        // ...and never inject a default when the platform did not provide one.
        $absent = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertArrayNotHasKey('requireAuthentication', $absent);
    }

    public function testColorThemeOverride(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_PRIMARY_COLOR'] = '#667eea';
        \Configuration::$globalStore['AI_SMART_TALK_SECONDARY_COLOR'] = '#764ba2';

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);

        $this->assertEquals('#667eea', $result['theme']['colors']['brand']['500']);
        $this->assertEquals('#764ba2', $result['theme']['colors']['brand']['200']);
    }

    public function testColorThemeMergesWithExisting(): void
    {
        $existing = ['theme' => ['colors' => ['brand' => ['100' => '#fff']]]];
        \Configuration::$globalStore['AI_SMART_TALK_PRIMARY_COLOR'] = '#000';

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides($existing);

        // Existing key preserved
        $this->assertEquals('#fff', $result['theme']['colors']['brand']['100']);
        // New key added
        $this->assertEquals('#000', $result['theme']['colors']['brand']['500']);
    }

    public function testNoColorThemeWhenNothingSet(): void
    {
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertArrayNotHasKey('theme', $result);
    }

    // =========================================================================
    // GDPR overrides
    // =========================================================================

    public function testGdprEnabledOverride(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_GDPR_ENABLED'] = 'on';
        \Configuration::$globalStore['AI_SMART_TALK_URL'] = 'https://api.test.com';

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);

        $this->assertTrue($result['gdprConsent']['enabled']);
        $this->assertStringContainsString('privacy-policy', $result['gdprConsent']['privacyPolicyUrl']);
    }

    public function testGdprDisabledOverride(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_GDPR_ENABLED'] = 'off';

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertFalse($result['gdprConsent']['enabled']);
    }

    public function testGdprCustomPrivacyUrl(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_GDPR_ENABLED'] = 'on';
        \Configuration::$globalStore['AI_SMART_TALK_GDPR_PRIVACY_URL'] = 'https://mysite.com/privacy';

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertEquals('https://mysite.com/privacy', $result['gdprConsent']['privacyPolicyUrl']);
    }

    public function testConsentWallEnabled(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_CONSENT_WALL_ENABLED'] = 'on';
        \Configuration::$globalStore['AI_SMART_TALK_CONSENT_WALL_MESSAGE'] = 'Accept cookies?';

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);

        $this->assertTrue($result['gdprConsent']['consentWallEnabled']);
        $this->assertEquals('Accept cookies?', $result['gdprConsent']['consentWallMessage']);
    }

    public function testNoGdprOverrideWhenNothingSet(): void
    {
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertArrayNotHasKey('gdprConsent', $result);
    }

    // =========================================================================
    // build() — full integration
    // =========================================================================

    public function testBuildReturnsBaseSettings(): void
    {
        $result = ChatbotSettingsBuilder::build('model-1', 'fr', 'https://api.test', 'https://cdn.test', 'wss://ws.test', null);

        $this->assertEquals('model-1', $result['chatModelId']);
        $this->assertEquals('fr', $result['lang']);
        $this->assertEquals('https://api.test/api', $result['apiUrl']);
        $this->assertEquals('wss://ws.test', $result['wsUrl']);
        $this->assertEquals('https://cdn.test', $result['cdnUrl']);
        $this->assertEquals('PRESTASHOP', $result['source']);
    }

    public function testBuildMergesEmbedConfig(): void
    {
        $embedConfig = ['buttonText' => 'Chat now', 'chatSize' => 'medium'];

        $result = ChatbotSettingsBuilder::build('m', 'en', 'https://api', 'https://cdn', 'wss://ws', $embedConfig);

        $this->assertEquals('Chat now', $result['buttonText']);
        $this->assertEquals('medium', $result['chatSize']);
    }

    // =========================================================================
    // allowedLanguages overrides
    // =========================================================================

    public function testAllowedLanguagesOverrideAppliedWhenSet(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ALLOWED_LANGUAGES'] = json_encode(['fr', 'en']);

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);

        $this->assertEquals(['fr', 'en'], $result['allowedLanguages']);
    }

    public function testAllowedLanguagesDropsInvalidCodes(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ALLOWED_LANGUAGES'] = json_encode(['fr', 'klingon', 'xx']);

        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);

        $this->assertEquals(['fr'], $result['allowedLanguages']);
    }

    public function testAllowedLanguagesNotAppliedWhenUnset(): void
    {
        $result = ChatbotSettingsBuilder::applyCustomizationOverrides([]);
        $this->assertArrayNotHasKey('allowedLanguages', $result);
    }

    public function testAllowedLanguagesNotAppliedWhenEmptyOrAllInvalid(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ALLOWED_LANGUAGES'] = '';
        $this->assertArrayNotHasKey('allowedLanguages', ChatbotSettingsBuilder::applyCustomizationOverrides([]));

        \Configuration::$globalStore['AI_SMART_TALK_ALLOWED_LANGUAGES'] = json_encode([]);
        $this->assertArrayNotHasKey('allowedLanguages', ChatbotSettingsBuilder::applyCustomizationOverrides([]));

        \Configuration::$globalStore['AI_SMART_TALK_ALLOWED_LANGUAGES'] = json_encode(['klingon']);
        $this->assertArrayNotHasKey('allowedLanguages', ChatbotSettingsBuilder::applyCustomizationOverrides([]));
    }

    public function testAllowedLanguagesInheritedFromEmbedConfigWhenNoLocalOverride(): void
    {
        // Platform restricts languages; no PS-local override → widget inherits them.
        $embedConfig = ['allowedLanguages' => ['de', 'fr']];

        $result = ChatbotSettingsBuilder::build('m', 'en', 'https://api', 'https://cdn', 'wss://ws', $embedConfig);

        $this->assertEquals(['de', 'fr'], $result['allowedLanguages']);
    }

    public function testLocalAllowedLanguagesOverrideEmbedConfig(): void
    {
        \Configuration::$globalStore['AI_SMART_TALK_ALLOWED_LANGUAGES'] = json_encode(['fr']);
        $embedConfig = ['allowedLanguages' => ['de', 'en']];

        $result = ChatbotSettingsBuilder::build('m', 'en', 'https://api', 'https://cdn', 'wss://ws', $embedConfig);

        $this->assertEquals(['fr'], $result['allowedLanguages']);
    }

    // =========================================================================
    // reconcileLangWithAllowed
    // =========================================================================

    public function testReconcileLangFallsBackWhenVisitorLangNotOffered(): void
    {
        $result = ChatbotSettingsBuilder::reconcileLangWithAllowed(['lang' => 'fr', 'allowedLanguages' => ['lu', 'th', 'ge']]);
        $this->assertEquals('lu', $result['lang']);
    }

    public function testReconcileLangKeptWhenOffered(): void
    {
        $result = ChatbotSettingsBuilder::reconcileLangWithAllowed(['lang' => 'th', 'allowedLanguages' => ['lu', 'th', 'ge']]);
        $this->assertEquals('th', $result['lang']);
    }

    public function testReconcileLangNoopWithoutRestriction(): void
    {
        $result = ChatbotSettingsBuilder::reconcileLangWithAllowed(['lang' => 'fr']);
        $this->assertEquals('fr', $result['lang']);
    }

    public function testBuildReconcilesLangWithLocalAllowed(): void
    {
        // Visitor browses in French, but the merchant only offers lu/th/ge →
        // the widget must start in an offered language, not 'fr'.
        \Configuration::$globalStore['AI_SMART_TALK_ALLOWED_LANGUAGES'] = json_encode(['lu', 'th', 'ge']);
        $result = ChatbotSettingsBuilder::build('m', 'fr', 'https://api', 'https://cdn', 'wss://ws', null);
        $this->assertEquals('lu', $result['lang']);
    }
}
