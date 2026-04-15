import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig } from './helpers';

/**
 * E2E tests for OAuth disconnect and reconnect flow.
 *
 * IMPORTANT: This test must run LAST because disconnect temporarily breaks
 * the OAuth connection. It reconnects at the end to restore state.
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin-qa';
const AIST_EMAIL = process.env.AIST_EMAIL || 'contact+test@aismarttalk.tech';
const AIST_PASS = process.env.AIST_PASS || 'MotDePasse420#';
const AIST_AGENT_NAME = process.env.AIST_AGENT_NAME || 'Prestashop test';

/** Full OAuth login flow on AI SmartTalk (reused from setup-oauth). */
async function connectViaOAuth(page: import('@playwright/test').Page) {
  // Click "Connect"
  const connectBtn = page.locator('a.ast-header-btn.primary, a.ast-btn-primary').first();
  await expect(connectBtn).toBeVisible({ timeout: 10_000 });
  await connectBtn.click();

  // AI SmartTalk OAuth page
  await page.waitForURL('**/oauth/authorize**', { timeout: 30_000 });
  await page.waitForLoadState('networkidle');

  // Sign in if needed
  const signInText = page.getByText('Sign in to continue');
  const isUnauthenticated = await signInText.isVisible({ timeout: 20_000 }).catch(() => false);

  if (isUnauthenticated) {
    await page.getByText('sign-in options', { exact: false }).click();
    await page.waitForURL('**/auth/signin**', { timeout: 15_000 });

    // Email step
    const emailInput = page.locator('#email');
    await expect(emailInput).toBeVisible({ timeout: 10_000 });
    await emailInput.fill(AIST_EMAIL);
    await page.locator('form button[type="submit"]').first().click();

    // Password step
    const passwordInput = page.locator('#password');
    await expect(passwordInput).toBeVisible({ timeout: 15_000 });
    await passwordInput.fill(AIST_PASS);
    await page.locator('form button[type="submit"]').first().click();

    await page.waitForTimeout(3000);

    if (page.url().includes('/auth/new-user')) {
      const acceptBtn = page.getByRole('button', { name: /accept|continue|agree|start/i });
      if (await acceptBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await acceptBtn.click();
        await page.waitForTimeout(2000);
      }
    }

    if (!page.url().includes('/oauth/authorize')) {
      await page.waitForURL('**/oauth/authorize**', { timeout: 15_000 });
    }
  }

  // Select agent
  const agentCard = page.getByText(AIST_AGENT_NAME, { exact: true }).first();
  await expect(agentCard).toBeVisible({ timeout: 20_000 });
  await agentCard.click();

  // Allow
  const allowBtn = page.getByRole('button', { name: /Allow/i });
  await expect(allowBtn).toBeEnabled({ timeout: 5_000 });
  await allowBtn.click();

  // Back to PrestaShop
  await page.waitForURL(`**/${adminPath}/**`, { timeout: 30_000 });
}

test.describe('Disconnect & Reconnect', () => {
  test.setTimeout(120_000);

  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page, adminPath);
  });

  test('disconnect → shows not connected state', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // Ensure we're connected first
    const connected = page.locator('.ast-status-badge.connected');
    await expect(connected).toBeVisible({ timeout: 5_000 });

    // Click disconnect — handle native confirm dialog
    page.on('dialog', dialog => dialog.accept());
    await page.locator('a.ast-header-btn.danger').click();
    await page.waitForLoadState('networkidle');

    // Verify "Not connected" state
    await expect(page.locator('.ast-status-badge.disconnected')).toBeVisible({ timeout: 10_000 });
  });

  test('after disconnect → embed disappears from front-office', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');

    const html = await page.content();
    const hasEmbed = html.includes('window.chatbotSettings') && html.includes('universal-chatbot.js');
    expect(hasEmbed).toBe(false);
  });

  test('after disconnect → config page shows connect button', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // The empty state with "Connect Now" button should be visible
    const connectNow = page.locator('a.ast-btn-primary', { hasText: /Connect/i });
    await expect(connectNow).toBeVisible({ timeout: 10_000 });
  });

  test('reconnect via OAuth → restores connected state', async ({ page }) => {
    await goToModuleConfig(page, adminPath);

    // Should be disconnected at this point
    const disconnected = page.locator('.ast-status-badge.disconnected');
    await expect(disconnected).toBeVisible({ timeout: 5_000 });

    // Reconnect
    await connectViaOAuth(page);

    // Verify connected
    await expect(page.locator('.ast-status-badge.connected')).toBeVisible({ timeout: 15_000 });
  });

  test('after reconnect → embed reappears on front-office', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');

    const html = await page.content();
    const hasEmbed = html.includes('window.chatbotSettings') && html.includes('universal-chatbot.js');
    expect(hasEmbed).toBe(true);
  });
});
