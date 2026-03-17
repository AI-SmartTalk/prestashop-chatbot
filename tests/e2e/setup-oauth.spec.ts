import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig } from './helpers';

/**
 * OAuth setup: connects the module to AI SmartTalk if not already connected.
 *
 * Runs before all other tests (via "setup" project dependency in playwright config).
 * Goes through the full OAuth flow: PS admin → Connect → AI SmartTalk signin → select agent → Allow.
 *
 * To reset and re-run: make e2e-reset && make e2e-setup
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin';
const AIST_EMAIL = process.env.AIST_EMAIL || 'contact+test@aismarttalk.tech';
const AIST_PASS = process.env.AIST_PASS || 'MotDePasse420#';
const AIST_AGENT_NAME = process.env.AIST_AGENT_NAME || 'Prestashop test';

test('connect module via OAuth', async ({ page }) => {
  test.setTimeout(120_000);

  // 1. Log into PrestaShop admin
  await loginToAdmin(page, adminPath);

  // 2. Go to module config page
  await goToModuleConfig(page, adminPath);

  // 3. Already connected? Skip.
  const connectedBadge = page.locator('.ast-status-badge.connected');
  if (await connectedBadge.isVisible({ timeout: 5000 }).catch(() => false)) {
    console.log('Module already connected — skipping OAuth setup');
    return;
  }

  // 4. Click "Connect"
  const connectBtn = page.locator('a.ast-header-btn.primary, a.ast-btn-primary').first();
  await expect(connectBtn).toBeVisible({ timeout: 10_000 });
  await connectBtn.click();

  // 5. AI SmartTalk OAuth page
  await page.waitForURL('**/oauth/authorize**', { timeout: 30_000 });
  await page.waitForLoadState('networkidle');

  // 6. Sign in if needed
  const signInText = page.getByText('Sign in to continue');
  const isUnauthenticated = await signInText.isVisible({ timeout: 20_000 }).catch(() => false);

  if (isUnauthenticated) {
    // Click "All sign-in options" → custom signin page
    await page.getByText('sign-in options', { exact: false }).click();
    await page.waitForURL('**/auth/signin**', { timeout: 15_000 });

    // Step 1: Email
    const emailInput = page.locator('#email');
    await expect(emailInput).toBeVisible({ timeout: 10_000 });
    await emailInput.fill(AIST_EMAIL);
    await page.locator('form button[type="submit"]').first().click();

    // Step 2: Password
    const passwordInput = page.locator('#password');
    await expect(passwordInput).toBeVisible({ timeout: 15_000 });
    await passwordInput.fill(AIST_PASS);
    await page.locator('form button[type="submit"]').first().click();

    // Wait for redirect back to OAuth page (authenticated)
    await page.waitForTimeout(3000);

    // Handle ToS page if needed
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

  // 7. Select agent
  const agentCard = page.getByText(AIST_AGENT_NAME, { exact: true }).first();
  await expect(agentCard).toBeVisible({ timeout: 20_000 });
  await agentCard.click();

  // 8. Click "Allow"
  const allowBtn = page.getByRole('button', { name: /Allow/i });
  await expect(allowBtn).toBeEnabled({ timeout: 5_000 });
  await allowBtn.click();

  // 9. Redirect back to PrestaShop
  await page.waitForURL(`**/${adminPath}/**`, { timeout: 30_000 });

  // 10. Verify connected
  await expect(page.locator('.ast-status-badge.connected')).toBeVisible({ timeout: 15_000 });
  console.log('OAuth connection successful!');
});
