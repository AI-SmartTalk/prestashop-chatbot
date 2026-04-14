import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig } from './helpers';

/**
 * E2E tests for the AI SmartTalk admin module page.
 *
 * Validates that the module configuration page renders correctly,
 * tabs work, forms submit, and no PHP errors are displayed.
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin-qa';

test.describe('Module Admin Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page, adminPath);
  });

  // ─── Page rendering ─────────────────────────────────────────────────

  test('config page loads without PHP errors', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // No PHP fatal/warning/notice in the page
    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('Warning:');
    expect(bodyText).not.toContain('Parse error');
    expect(bodyText).not.toContain('Undefined');
  });

  test('config page contains the app container', async ({ page }) => {
    await goToModuleConfig(page, adminPath);
    await expect(page.locator('.ast-app')).toBeVisible();
  });

  test('all tabs are present', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    await expect(page.locator('#panel-chatbot')).toBeAttached();
    await expect(page.locator('#panel-appearance')).toBeAttached();
    await expect(page.locator('#panel-sync')).toBeAttached();
    await expect(page.locator('#panel-webhooks')).toBeAttached();
  });

  // ─── Tab navigation ─────────────────────────────────────────────────

  test('clicking Sync tab shows sync panel', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // Click on the Sync tab
    await page.locator('.ast-tab', { hasText: /Sync/i }).click();
    await expect(page.locator('#panel-sync')).toBeVisible();
  });

  test('clicking Appearance tab shows appearance panel', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    await page.locator('.ast-tab', { hasText: /Appearance/i }).click();
    await expect(page.locator('#panel-appearance')).toBeVisible();
  });

  // ─── Chatbot tab ────────────────────────────────────────────────────

  test('chatbot toggle is present', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // Either the simple toggle (mono-shop) or the shop checkboxes (multistore)
    const toggle = page.locator('input[name="AI_SMART_TALK_ENABLED"], input[name="chatbot_shops[]"]');
    await expect(toggle.first()).toBeAttached();
  });

  test('save button exists on chatbot tab', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    const saveBtn = page.locator('#panel-chatbot button[name="submitToggleChatbot"]');
    await expect(saveBtn).toBeAttached();
  });

  // ─── Sync tab ───────────────────────────────────────────────────────

  test('product sync toggle is present', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    await page.locator('.ast-tab', { hasText: /Sync/i }).click();
    await expect(page.locator('input[name="AI_SMART_TALK_PRODUCT_SYNC"]')).toBeAttached();
  });

  test('customer sync toggle is present', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    await page.locator('.ast-tab', { hasText: /Sync/i }).click();
    await expect(page.locator('input[name="AI_SMART_TALK_CUSTOMER_SYNC"]')).toBeAttached();
  });

  // ─── No native multistore panel ─────────────────────────────────────

  test('PrestaShop native multistore panel is hidden', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // The JS should hide the panel containing activateModule
    const nativePanel = page.locator('input[name="activateModule"]');
    if (await nativePanel.count() > 0) {
      // If it exists, it should be hidden (or its parent panel)
      const parentPanel = nativePanel.locator('xpath=ancestor::div[contains(@class,"panel")]');
      if (await parentPanel.count() > 0) {
        await expect(parentPanel.first()).toBeHidden();
      }
    }
    // If it doesn't exist at all, that's also fine (mono-shop)
  });

  // ─── Appearance tab ─────────────────────────────────────────────────

  test('display position select exists in appearance tab', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    await page.locator('.ast-tab', { hasText: /Appearance/i }).click();
    await expect(page.locator('select[name="AI_SMART_TALK_IFRAME_POSITION"]')).toBeVisible();
  });
});
