import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig } from './helpers';

/**
 * E2E tests for Appearance tab customization.
 *
 * Tests that appearance settings saved in admin are reflected
 * in the front-office chatbot embed (chatbotSettings JSON).
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin';

/** Navigate to the Appearance tab. */
async function goToAppearanceTab(page: import('@playwright/test').Page) {
  await goToModuleConfig(page, adminPath);
  await page.locator('.ast-tab', { hasText: /Appearance/i }).click();
  await expect(page.locator('#panel-appearance')).toBeVisible();
}

/** Save the appearance form. */
async function saveAppearance(page: import('@playwright/test').Page) {
  const saveBtn = page.locator('button[name="submitChatbotCustomization"]');
  await saveBtn.scrollIntoViewIfNeeded();
  await saveBtn.click();
  await page.waitForLoadState('networkidle');
}

/** Get chatbotSettings from the front-office. */
async function getChatbotSettings(page: import('@playwright/test').Page): Promise<any> {
  await page.goto('/');
  await page.waitForLoadState('domcontentloaded');
  return page.evaluate(() => (window as any).chatbotSettings);
}

test.describe('Appearance', () => {

  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page, adminPath);
  });

  // ─── Display position ─────────────────────────────────────────────

  test('change iframe position to before_footer', async ({ page }) => {
    await goToAppearanceTab(page);

    await page.selectOption('select[name="AI_SMART_TALK_IFRAME_POSITION"]', 'before_footer');
    await saveAppearance(page);

    // Verify the setting persists on reload
    await goToAppearanceTab(page);
    const value = await page.locator('select[name="AI_SMART_TALK_IFRAME_POSITION"]').inputValue();
    expect(value).toBe('before_footer');
  });

  test('change iframe position back to footer', async ({ page }) => {
    await goToAppearanceTab(page);

    await page.selectOption('select[name="AI_SMART_TALK_IFRAME_POSITION"]', 'footer');
    await saveAppearance(page);

    await goToAppearanceTab(page);
    const value = await page.locator('select[name="AI_SMART_TALK_IFRAME_POSITION"]').inputValue();
    expect(value).toBe('footer');
  });

  // ─── Chat size ────────────────────────────────────────────────────

  test('set chat size → reflected in front-office settings', async ({ page }) => {
    await goToAppearanceTab(page);

    await page.selectOption('select[name="AI_SMART_TALK_CHAT_SIZE"]', 'large');
    await saveAppearance(page);

    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    expect(settings.chatSize).toBe('large');
  });

  // ─── Button position ──────────────────────────────────────────────

  test('set button position → reflected in front-office settings', async ({ page }) => {
    await goToAppearanceTab(page);

    await page.selectOption('select[name="AI_SMART_TALK_BUTTON_POSITION"]', 'bottom-left');
    await saveAppearance(page);

    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    expect(settings.position).toBe('bottom-left');
  });

  // ─── Color mode ───────────────────────────────────────────────────

  test('set color mode → reflected in front-office settings', async ({ page }) => {
    await goToAppearanceTab(page);

    await page.selectOption('select[name="AI_SMART_TALK_COLOR_MODE"]', 'dark');
    await saveAppearance(page);

    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    expect(settings.initialColorMode).toBe('dark');
  });

  // ─── Button text ──────────────────────────────────────────────────

  test('set custom button text → reflected in front-office settings', async ({ page }) => {
    await goToAppearanceTab(page);

    const input = page.locator('input[name="AI_SMART_TALK_BUTTON_TEXT"]');
    await input.scrollIntoViewIfNeeded();
    await input.fill('Need help?');
    await saveAppearance(page);

    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    expect(settings.buttonText).toBe('Need help?');
  });

  // ─── Primary color ────────────────────────────────────────────────

  test('set primary color → reflected in front-office theme', async ({ page }) => {
    await goToAppearanceTab(page);

    const input = page.locator('#input_primary');
    await input.scrollIntoViewIfNeeded();
    await input.fill('#ff5500');
    await saveAppearance(page);

    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    expect(settings.theme?.colors?.brand?.['500']).toBe('#ff5500');
  });

  // ─── Border radius ────────────────────────────────────────────────

  test('set border radius → reflected in front-office settings', async ({ page }) => {
    await goToAppearanceTab(page);

    await page.selectOption('select[name="AI_SMART_TALK_BORDER_RADIUS"]', 'square');
    await saveAppearance(page);

    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    expect(settings.borderRadius).toBe('square');
  });

  // ─── Feature toggles ─────────────────────────────────────────────

  test('disable attachment feature → reflected in front-office', async ({ page }) => {
    await goToAppearanceTab(page);

    await page.selectOption('select[name="AI_SMART_TALK_ENABLE_ATTACHMENT"]', 'off');
    await saveAppearance(page);

    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    expect(settings.enableAttachment).toBe(false);
  });

  // ─── Reset to defaults ────────────────────────────────────────────

  test('reset to defaults → customizations cleared', async ({ page }) => {
    await goToAppearanceTab(page);

    // Set everything back to empty/default
    await page.selectOption('select[name="AI_SMART_TALK_CHAT_SIZE"]', '');
    await page.selectOption('select[name="AI_SMART_TALK_BUTTON_POSITION"]', '');
    await page.selectOption('select[name="AI_SMART_TALK_COLOR_MODE"]', '');
    await page.selectOption('select[name="AI_SMART_TALK_BORDER_RADIUS"]', '');
    await page.selectOption('select[name="AI_SMART_TALK_ENABLE_ATTACHMENT"]', '');

    const buttonText = page.locator('input[name="AI_SMART_TALK_BUTTON_TEXT"]');
    await buttonText.scrollIntoViewIfNeeded();
    await buttonText.fill('');

    const primaryColor = page.locator('#input_primary');
    await primaryColor.scrollIntoViewIfNeeded();
    await primaryColor.fill('');

    await saveAppearance(page);

    // When reset, local overrides are removed — the API embed config defaults may still provide values.
    // Verify the previously set local override values are no longer present.
    const settings = await getChatbotSettings(page);
    expect(settings).toBeDefined();
    // chatSize/position/colorMode may still have API defaults (e.g. "medium"),
    // but our explicit overrides ("large", "bottom-left", "dark") should be gone.
    expect(settings.chatSize).not.toBe('large');
    expect(settings.position).not.toBe('bottom-left');
    expect(settings.initialColorMode).not.toBe('dark');
    expect(settings.borderRadius).not.toBe('square');
    expect(settings.buttonText).not.toBe('Need help?');
  });
});
