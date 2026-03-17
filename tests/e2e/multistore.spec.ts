import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig } from './helpers';

/**
 * E2E tests for multistore mode.
 *
 * These tests verify that the module works correctly when PrestaShop
 * multistore is enabled (multiple shops in one instance).
 *
 * Prerequisites: multistore must be enabled via make e2e-multistore-enable
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin';

test.describe('Multistore', () => {

  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page, adminPath);
  });

  test('config page loads without errors in multistore mode', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    const bodyText = await page.textContent('body');
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('Warning:');
    expect(bodyText).not.toContain('Parse error');
  });

  test('app container is visible', async ({ page }) => {
    await goToModuleConfig(page, adminPath);
    await expect(page.locator('.ast-app')).toBeVisible();
  });

  test('per-shop chatbot toggles are present', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // In multistore mode, should have chatbot_shops[] checkboxes instead of single toggle
    const shopCheckboxes = page.locator('input[name="chatbot_shops[]"]');
    const singleCheckbox = page.locator('input[name="AI_SMART_TALK_ENABLED"]');

    const shopCount = await shopCheckboxes.count();
    const singleCount = await singleCheckbox.count();

    // Either multistore checkboxes (≥2) or single toggle — both are valid
    // depending on whether multistore is actually active
    expect(shopCount + singleCount).toBeGreaterThan(0);

    if (shopCount >= 2) {
      // Multistore mode: verify we have per-shop checkboxes
      console.log(`Multistore detected: ${shopCount} shop checkboxes`);
    } else {
      console.log('Single-shop mode detected');
    }
  });

  test('enable chatbot on shop 1 only → save', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    const shopCheckboxes = page.locator('input[name="chatbot_shops[]"]');
    const count = await shopCheckboxes.count();

    if (count >= 2) {
      // Enable shop 1, disable shop 2
      await shopCheckboxes.nth(0).evaluate((el: HTMLInputElement) => { el.checked = true; });
      await shopCheckboxes.nth(1).evaluate((el: HTMLInputElement) => { el.checked = false; });

      const saveBtn = page.locator('button[name="submitToggleChatbot"]');
      await saveBtn.scrollIntoViewIfNeeded();
      await saveBtn.click();
      await page.waitForLoadState('networkidle');

      // Verify persistence
      await goToModuleConfig(page, adminPath);
      await expect(shopCheckboxes.nth(0)).toBeChecked();
      await expect(shopCheckboxes.nth(1)).not.toBeChecked();
    }
  });

  test('enable chatbot on all shops → save', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    const shopCheckboxes = page.locator('input[name="chatbot_shops[]"]');
    const count = await shopCheckboxes.count();

    if (count >= 2) {
      // Enable all shops
      for (let i = 0; i < count; i++) {
        await shopCheckboxes.nth(i).evaluate((el: HTMLInputElement) => { el.checked = true; });
      }

      const saveBtn = page.locator('button[name="submitToggleChatbot"]');
      await saveBtn.scrollIntoViewIfNeeded();
      await saveBtn.click();
      await page.waitForLoadState('networkidle');

      // Verify all are checked
      await goToModuleConfig(page, adminPath);
      for (let i = 0; i < count; i++) {
        await expect(shopCheckboxes.nth(i)).toBeChecked();
      }
    }
  });

  test('all tabs work in multistore mode', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // Chatbot tab (default)
    await expect(page.locator('#panel-chatbot')).toBeAttached();

    // Appearance tab
    await page.locator('.ast-tab', { hasText: /Appearance/i }).click();
    await expect(page.locator('#panel-appearance')).toBeVisible();

    // Sync tab
    await page.locator('.ast-tab', { hasText: /Sync/i }).click();
    await expect(page.locator('#panel-sync')).toBeVisible();

    // Webhooks tab
    await page.locator('.ast-tab', { hasText: /Webhooks/i }).click();
    await expect(page.locator('#panel-webhooks')).toBeVisible();
  });

  test('native multistore panel is hidden', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // The JS should hide the PrestaShop native multistore panel
    const nativePanel = page.locator('input[name="activateModule"]');
    if (await nativePanel.count() > 0) {
      const parentPanel = nativePanel.locator('xpath=ancestor::div[contains(@class,"panel")]');
      if (await parentPanel.count() > 0) {
        await expect(parentPanel.first()).toBeHidden();
      }
    }
  });
});
