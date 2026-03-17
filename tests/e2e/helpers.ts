import { Page, expect } from '@playwright/test';

const ADMIN_EMAIL = process.env.ADMIN_EMAIL || 'demo@prestashop.com';
const ADMIN_PASS = process.env.ADMIN_PASS || "Ds1J1umhy'l3~F%!b){1";

/**
 * Discover the admin folder name (it's randomized on install).
 */
export async function getAdminPath(page: Page): Promise<string> {
  // Try common patterns
  const candidates = ['admin', 'admin-dev', 'administration'];

  for (const candidate of candidates) {
    const response = await page.goto(`/${candidate}/`, { waitUntil: 'domcontentloaded' });
    if (response && response.status() < 400) {
      return candidate;
    }
  }

  // PS randomizes the admin folder; try to find it from the homepage redirect
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  // If we can't find it, fallback
  throw new Error('Could not find admin path. Set PS_ADMIN_PATH env var.');
}

/**
 * Log into the PrestaShop back office.
 */
export async function loginToAdmin(page: Page, adminPath: string): Promise<void> {
  await page.goto(`/${adminPath}/`);

  // Wait for login form
  const emailInput = page.locator('input[name="email"], #email');
  if (await emailInput.isVisible({ timeout: 5000 }).catch(() => false)) {
    await emailInput.fill(ADMIN_EMAIL);
    await page.locator('input[name="passwd"], #passwd').fill(ADMIN_PASS);
    await page.locator('#submit_login').click();
    await page.waitForLoadState('networkidle');
  }
  // Else already logged in
}

/**
 * Navigate to the AI SmartTalk module configuration page.
 * Handles both PS 1.7 (legacy admin with token) and PS 9 (Symfony routing).
 */
export async function goToModuleConfig(page: Page, adminPath: string): Promise<void> {
  // PS 1.7 may redirect with a token param, causing "navigation interrupted".
  // Use waitUntil: 'load' and catch redirect interruptions.
  try {
    await page.goto(`/${adminPath}/index.php?controller=AdminModules&configure=aismarttalk`, {
      waitUntil: 'load',
    });
  } catch (e: any) {
    // PS 1.7 auto-redirects to add ?token=xxx — wait for the final page
    if (e.message?.includes('interrupted by another navigation')) {
      await page.waitForLoadState('load');
    } else {
      throw e;
    }
  }

  // Handle possible login redirect
  if (page.url().includes('controller=AdminLogin') || page.url().includes('/login')) {
    await loginToAdmin(page, adminPath);
    try {
      await page.goto(`/${adminPath}/index.php?controller=AdminModules&configure=aismarttalk`, {
        waitUntil: 'load',
      });
    } catch (e: any) {
      if (e.message?.includes('interrupted by another navigation')) {
        await page.waitForLoadState('load');
      } else {
        throw e;
      }
    }
  }

  // PS 1.7: "CLÉ DE SÉCURITÉ INVALIDE" — bypass the security token warning
  const bypassBtn = page.getByText('Je comprends les risques', { exact: false });
  if (await bypassBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await bypassBtn.click();
    await page.waitForLoadState('networkidle');
  }
}
