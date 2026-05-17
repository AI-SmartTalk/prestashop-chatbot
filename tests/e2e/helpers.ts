import { Page, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const ADMIN_EMAIL = process.env.ADMIN_EMAIL || 'admin@test.local';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin123';
const ADMIN_PATH = process.env.PS_ADMIN_PATH || 'admin-qa';

/**
 * Database connection helper for `docker exec mysql` access from Playwright tests.
 * Mirrors the targets used in the Makefile so behavior stays consistent.
 */
const DB_CONTAINER = process.env.PS_DB_CONTAINER || 'prestashop_db';
const DB_NAME = process.env.PS_DB_NAME || 'prestashop';
const DB_USER = process.env.PS_DB_USER || 'prestashop';
const DB_PASS = process.env.PS_DB_PASS || 'prestashop';

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

/**
 * Run a SQL statement against the PrestaShop DB via `docker exec`.
 * Returns stdout (tab-separated rows, first line = column names).
 *
 * Use the tabular output for simple lookups; use `runSqlRows` for parsed access.
 * The container name and credentials default to the PS 9 dev stack (see PS_DB_*).
 */
export function runSqlOnPs(sql: string): string {
  const args = [
    'exec', '-i', DB_CONTAINER,
    'mysql', '-u', DB_USER, `-p${DB_PASS}`, '-N', '--batch', DB_NAME,
    '-e', sql,
  ];
  return execFileSync('docker', args, { encoding: 'utf-8' }).toString();
}

/**
 * Run a SELECT and return parsed rows. Each row is an object keyed by column name.
 * The query must include explicit column aliases (no SELECT *) — column names
 * are resolved from the SQL using the alias-before-FROM convention.
 */
export function runSqlRows(sql: string): Record<string, string>[] {
  const args = [
    'exec', '-i', DB_CONTAINER,
    'mysql', '-u', DB_USER, `-p${DB_PASS}`, '--batch', DB_NAME,
    '-e', sql,
  ];
  const stdout = execFileSync('docker', args, { encoding: 'utf-8' }).toString();
  const lines = stdout.split('\n').filter(Boolean);
  if (lines.length === 0) return [];

  const [header, ...rows] = lines;
  const cols = header.split('\t');
  return rows.map(line => {
    const vals = line.split('\t');
    const obj: Record<string, string> = {};
    cols.forEach((c, i) => { obj[c] = vals[i] ?? ''; });
    return obj;
  });
}

/**
 * Load a .sql fixture from tests/e2e/fixtures/ into the PS DB.
 * Path is resolved relative to the fixtures directory.
 */
export function loadSqlFixture(fixtureName: string): void {
  const path = resolve(__dirname, 'fixtures', fixtureName);
  const sql = readFileSync(path, 'utf-8');
  const args = [
    'exec', '-i', DB_CONTAINER,
    'mysql', '-u', DB_USER, `-p${DB_PASS}`, DB_NAME,
  ];
  execFileSync('docker', args, { input: sql, encoding: 'utf-8' });
}
