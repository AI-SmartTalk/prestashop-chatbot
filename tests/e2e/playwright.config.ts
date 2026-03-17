import { defineConfig } from '@playwright/test';

/**
 * E2E tests for AI SmartTalk PrestaShop module.
 *
 * Prerequisites:
 *   make up          → PS 9 on http://localhost
 *   make ps17        → PS 1.7 on http://localhost:8091
 *
 * Run:
 *   make e2e         → run all E2E tests on PS 9
 *   make e2e-ps17    → run all E2E tests on PS 1.7
 *   make e2e-setup   → run OAuth setup only
 *   make e2e-headed  → run with visible browser
 *   make e2e-ui      → run in Playwright UI mode
 */

const PS_URL = process.env.PS_URL || 'http://localhost';
const ADMIN_EMAIL = process.env.ADMIN_EMAIL || 'demo@prestashop.com';
const ADMIN_PASS = process.env.ADMIN_PASS || "Ds1J1umhy'l3~F%!b){1";

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.ts',
  timeout: 60_000,
  retries: 1,
  use: {
    baseURL: PS_URL,
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'setup',
      testMatch: 'setup-oauth.spec.ts',
      retries: 0,
    },
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
      testIgnore: 'setup-oauth.spec.ts',
      dependencies: ['setup'],
    },
  ],
});

export { PS_URL, ADMIN_EMAIL, ADMIN_PASS };
