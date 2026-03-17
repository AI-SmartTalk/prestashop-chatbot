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
 */
export async function goToModuleConfig(page: Page, adminPath: string): Promise<void> {
  await page.goto(`/${adminPath}/index.php?controller=AdminModules&configure=aismarttalk`, {
    waitUntil: 'domcontentloaded',
  });

  // Handle possible token redirect
  if (page.url().includes('controller=AdminLogin')) {
    await loginToAdmin(page, adminPath);
    await page.goto(`/${adminPath}/index.php?controller=AdminModules&configure=aismarttalk`, {
      waitUntil: 'domcontentloaded',
    });
  }
}
