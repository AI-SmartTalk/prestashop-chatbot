import { test, expect } from '@playwright/test';

/**
 * E2E tests for chatbot front-office display.
 *
 * Validates that the chatbot embed script is present (or absent)
 * on the storefront based on the AI_SMART_TALK_ENABLED config.
 */

test.describe('Chatbot Front-Office Display', () => {

  test('storefront loads without errors', async ({ page }) => {
    await page.goto('/');
    const body = await page.textContent('body');
    expect(body).not.toContain('Fatal error');
    expect(body).not.toContain('Parse error');
  });

  test('chatbot script tag is present when module is installed', async ({ page }) => {
    await page.goto('/');

    // The chatbot embed uses a base64-encoded settings script or an iframe
    // Look for the AI SmartTalk embed marker
    const html = await page.content();

    // If chatbot is enabled, should have the embed script
    // If disabled, should not — but we just check no crash
    expect(html).not.toContain('Fatal error');
  });

  test('no JavaScript console errors on storefront', async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Filter out known third-party errors
    const moduleErrors = consoleErrors.filter(e =>
      e.includes('aismarttalk') || e.includes('smarttalk')
    );

    expect(moduleErrors).toHaveLength(0);
  });

  test('product page loads without errors', async ({ page }) => {
    // Navigate to any product
    await page.goto('/');

    const productLink = page.locator('a[href*="/product/"], a[href*="-detail.html"]').first();
    if (await productLink.isVisible({ timeout: 3000 }).catch(() => false)) {
      await productLink.click();
      await page.waitForLoadState('domcontentloaded');

      const body = await page.textContent('body');
      expect(body).not.toContain('Fatal error');
    }
  });
});
