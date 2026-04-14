import { Page, expect } from '@playwright/test';

const ADMIN_EMAIL = process.env.ADMIN_EMAIL || 'admin@test.local';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin123';
const ADMIN_PATH = process.env.PS_ADMIN_PATH || 'admin-qa';

/**
 * Get the admin path. Uses PS_ADMIN_PATH env var (default: admin-qa).
 */
export function getAdminPath(): string {
  return ADMIN_PATH;
}

/**
 * Log into the PrestaShop back office.
 */
export async function loginToAdmin(page: Page, adminPath?: string): Promise<void> {
  const admin = adminPath || ADMIN_PATH;
  await page.goto(`/${admin}/`, { waitUntil: 'domcontentloaded' });

  // PS 9 login form: #login_form with #email and #passwd
  const loginForm = page.locator('#login_form');
  if (await loginForm.isVisible({ timeout: 5000 }).catch(() => false)) {
    await loginForm.locator('#email').fill(ADMIN_EMAIL);
    await loginForm.locator('#passwd').fill(ADMIN_PASS);
    await loginForm.locator('#submit_login').click();
    await page.waitForLoadState('networkidle');
    return;
  }

  // PS 1.7 fallback: input[name="email"]
  const emailInput = page.locator('#login_form input[name="email"], form input[name="email"]');
  if (await emailInput.isVisible({ timeout: 3000 }).catch(() => false)) {
    await emailInput.fill(ADMIN_EMAIL);
    await page.locator('input[name="passwd"]').fill(ADMIN_PASS);
    await page.locator('#submit_login').click();
    await page.waitForLoadState('networkidle');
  }
  // Else already logged in
}

/**
 * Navigate to the AI SmartTalk module configuration page.
 */
export async function goToModuleConfig(page: Page, adminPath?: string): Promise<void> {
  const admin = adminPath || ADMIN_PATH;

  try {
    await page.goto(`/${admin}/index.php?controller=AdminModules&configure=aismarttalk`, {
      waitUntil: 'load',
    });
  } catch (e: any) {
    if (e.message?.includes('interrupted by another navigation')) {
      await page.waitForLoadState('load');
    } else {
      throw e;
    }
  }

  // Handle login redirect
  if (page.url().includes('controller=AdminLogin') || page.url().includes('/login')) {
    await loginToAdmin(page, admin);
    try {
      await page.goto(`/${admin}/index.php?controller=AdminModules&configure=aismarttalk`, {
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

  // PS 1.7: bypass security token warning
  const bypassBtn = page.getByText('Je comprends les risques', { exact: false });
  if (await bypassBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await bypassBtn.click();
    await page.waitForLoadState('networkidle');
  }
}
