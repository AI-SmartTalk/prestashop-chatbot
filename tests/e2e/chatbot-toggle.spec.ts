import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig } from './helpers';

/**
 * E2E tests for chatbot enable/disable toggle.
 *
 * Tests the full user flow:
 *   1. Enable chatbot in admin → verify embed script appears on storefront
 *   2. Disable chatbot in admin → verify embed script disappears from storefront
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin-qa';

/** Enable or disable the chatbot via the admin toggle and save. */
async function setChatbotEnabled(page: import('@playwright/test').Page, enabled: boolean) {
  await goToModuleConfig(page, adminPath);

  const checkbox = page.locator('input[name="AI_SMART_TALK_ENABLED"]');
  const multistoreCheckbox = page.locator('input[name="chatbot_shops[]"]').first();

  if (await checkbox.count() > 0) {
    // Single-shop: checkbox hidden by CSS switch — set state via JS + dispatch change
    await checkbox.evaluate((el: HTMLInputElement, val: boolean) => {
      el.checked = val;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }, enabled);
  } else if (await multistoreCheckbox.count() > 0) {
    // Multistore: set all shop checkboxes
    const shopCheckboxes = page.locator('input[name="chatbot_shops[]"]');
    const count = await shopCheckboxes.count();
    for (let i = 0; i < count; i++) {
      await shopCheckboxes.nth(i).evaluate((el: HTMLInputElement, val: boolean) => {
        el.checked = val;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }, enabled);
    }
  }

  // Save — scroll into view first since it may be below the fold
  const saveBtn = page.locator('button[name="submitToggleChatbot"]');
  await saveBtn.scrollIntoViewIfNeeded();
  await saveBtn.click();
  await page.waitForLoadState('networkidle');
}

/** Check if the chatbot embed is present on the storefront. */
async function hasChatbotEmbed(page: import('@playwright/test').Page): Promise<boolean> {
  await page.goto('/');
  await page.waitForLoadState('domcontentloaded');
  const html = await page.content();
  return html.includes('window.chatbotSettings') && html.includes('universal-chatbot.js');
}

test.describe('Chatbot Toggle', () => {

  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page, adminPath);
  });

  test('enable chatbot → embed appears on storefront', async ({ page }) => {
    await setChatbotEnabled(page, true);

    const hasEmbed = await hasChatbotEmbed(page);
    expect(hasEmbed).toBe(true);
  });

  test('disable chatbot → embed disappears from storefront', async ({ page }) => {
    await setChatbotEnabled(page, false);

    const hasEmbed = await hasChatbotEmbed(page);
    expect(hasEmbed).toBe(false);
  });

  test('re-enable chatbot → embed reappears', async ({ page }) => {
    await setChatbotEnabled(page, true);

    const hasEmbed = await hasChatbotEmbed(page);
    expect(hasEmbed).toBe(true);
  });

  test('chatbot embed contains correct settings', async ({ page }) => {
    await setChatbotEnabled(page, true);

    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');

    const settings = await page.evaluate(() => {
      return (window as any).chatbotSettings;
    });

    expect(settings).toBeDefined();
    expect(settings.chatModelId).toBeTruthy();
    expect(settings.apiUrl).toBeTruthy();
    expect(settings.source).toBe('PRESTASHOP');
  });

  test('chatbot embed loads on product page too', async ({ page }) => {
    await setChatbotEnabled(page, true);

    await page.goto('/');
    const productLink = page.locator('a[href*="/product/"], a[href*="-detail.html"]').first();
    if (await productLink.isVisible({ timeout: 3000 }).catch(() => false)) {
      await productLink.click();
      await page.waitForLoadState('domcontentloaded');

      const html = await page.content();
      expect(html).toContain('window.chatbotSettings');
      expect(html).toContain('universal-chatbot.js');
    }
  });

  test('no JS console errors with chatbot enabled', async ({ page }) => {
    await setChatbotEnabled(page, true);

    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const moduleErrors = consoleErrors.filter(e =>
      e.includes('aismarttalk') || e.includes('smarttalk') || e.includes('chatbot')
    );

    expect(moduleErrors).toHaveLength(0);
  });
});
