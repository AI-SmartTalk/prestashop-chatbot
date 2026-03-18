import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig } from './helpers';

/**
 * E2E tests for the Sync tab (product & customer sync).
 *
 * Tests toggle activation, manual sync trigger, and settings persistence.
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin';

/** Navigate to the Sync tab. */
async function goToSyncTab(page: import('@playwright/test').Page) {
  await goToModuleConfig(page, adminPath);
  await page.locator('.ast-tab', { hasText: /Sync/i }).click();
  await expect(page.locator('#panel-sync')).toBeVisible();
}

test.describe('Product & Customer Sync', () => {

  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page, adminPath);
  });

  // ─── Product sync toggle ──────────────────────────────────────────

  test('enable product sync toggle', async ({ page }) => {
    await goToSyncTab(page);

    const checkbox = page.locator('input[name="AI_SMART_TALK_PRODUCT_SYNC"]');
    const isChecked = await checkbox.isChecked();

    if (!isChecked) {
      // Toggle auto-submits the form on change
      await checkbox.evaluate((el: HTMLInputElement) => {
        el.checked = true;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      // The onchange triggers form.submit(), wait for page reload
      await page.waitForLoadState('networkidle');
    }

    // Verify it persists
    await goToSyncTab(page);
    await expect(page.locator('input[name="AI_SMART_TALK_PRODUCT_SYNC"]')).toBeChecked();
  });

  test('enable customer sync toggle', async ({ page }) => {
    await goToSyncTab(page);

    const checkbox = page.locator('input[name="AI_SMART_TALK_CUSTOMER_SYNC"]');
    const isChecked = await checkbox.isChecked();

    if (!isChecked) {
      await checkbox.evaluate((el: HTMLInputElement) => {
        el.checked = true;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      await page.waitForLoadState('networkidle');
    }

    await goToSyncTab(page);
    await expect(page.locator('input[name="AI_SMART_TALK_CUSTOMER_SYNC"]')).toBeChecked();
  });

  // ─── Sync settings ────────────────────────────────────────────────

  test('sync settings form is visible when sync is enabled', async ({ page }) => {
    await goToSyncTab(page);

    // Settings section should appear when product or customer sync is on
    const settingsForm = page.locator('#sync-settings-form, button[name="submitSyncSettings"]');
    await expect(settingsForm.first()).toBeAttached();
  });

  test('save sync filter settings', async ({ page }) => {
    await goToSyncTab(page);

    // Change customer consent filter
    const consentSelect = page.locator('select[name="AI_SMART_TALK_CUSTOMER_SYNC_CONSENT"]');
    if (await consentSelect.count() > 0) {
      await consentSelect.selectOption('newsletter');

      const saveBtn = page.locator('button[name="submitSyncSettings"]');
      await saveBtn.scrollIntoViewIfNeeded();
      await saveBtn.click();
      await page.waitForLoadState('networkidle');

      // Verify persistence
      await goToSyncTab(page);
      const value = await page.locator('select[name="AI_SMART_TALK_CUSTOMER_SYNC_CONSENT"]').inputValue();
      expect(value).toBe('newsletter');
    }
  });

  // ─── Manual product sync ──────────────────────────────────────────

  test('trigger product sync does not crash', async ({ page }) => {
    await goToSyncTab(page);

    // Click "Sync All Products" link
    const syncBtn = page.locator('a.ast-action-product, a[href*="forceSync"]').first();

    if (await syncBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      // Handle the confirm dialog (native JS confirm or custom modal)
      page.on('dialog', dialog => dialog.accept());

      await syncBtn.click();
      await page.waitForLoadState('networkidle', { timeout: 60_000 });

      // After sync, page should reload with a flash message (success, warning, or error)
      // We just verify no PHP crash
      const body = await page.textContent('body');
      expect(body).not.toContain('Fatal error');
      expect(body).not.toContain('Parse error');
    }
  });

  test('trigger customer sync does not crash', async ({ page }) => {
    await goToSyncTab(page);

    const syncBtn = page.locator('a.ast-action-customer, a[href*="syncCustomers"]').first();

    if (await syncBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      page.on('dialog', dialog => dialog.accept());

      await syncBtn.click();
      await page.waitForLoadState('networkidle', { timeout: 60_000 });

      const body = await page.textContent('body');
      expect(body).not.toContain('Fatal error');
      expect(body).not.toContain('Parse error');
    }
  });

  // ─── Disable toggles ─────────────────────────────────────────────

  test('disable product sync toggle', async ({ page }) => {
    await goToSyncTab(page);

    const checkbox = page.locator('input[name="AI_SMART_TALK_PRODUCT_SYNC"]');

    if (await checkbox.isChecked()) {
      await checkbox.evaluate((el: HTMLInputElement) => {
        el.checked = false;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      await page.waitForLoadState('networkidle');
    }

    await goToSyncTab(page);
    await expect(page.locator('input[name="AI_SMART_TALK_PRODUCT_SYNC"]')).not.toBeChecked();
  });

  test('re-enable product sync for other tests', async ({ page }) => {
    await goToSyncTab(page);

    const checkbox = page.locator('input[name="AI_SMART_TALK_PRODUCT_SYNC"]');

    if (!(await checkbox.isChecked())) {
      await checkbox.evaluate((el: HTMLInputElement) => {
        el.checked = true;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      await page.waitForLoadState('networkidle');
    }

    await goToSyncTab(page);
    await expect(page.locator('input[name="AI_SMART_TALK_PRODUCT_SYNC"]')).toBeChecked();
  });
});
