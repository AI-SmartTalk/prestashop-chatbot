import { test, expect } from '@playwright/test';
import { loginToAdmin, goToModuleConfig, loadSqlFixture, runSqlOnPs, runSqlRows } from './helpers';

/**
 * E2E coverage for the declination (variant) sync + LYD currency precision.
 *
 * What this guards against (the Libyan client regression):
 *   1. A product whose stock only comes from combinations must remain eligible for sync.
 *   2. The new combination hooks (actionProductAttribute*) must be registered.
 *   3. The default currency precision (LYD = 3 decimals) must be readable from the DB.
 *   4. A full sync of a combination-only product must not crash PHP-side.
 *
 * Setup uses a SQL fixture that adds a fresh LYD currency + a test product with
 * two combinations. Teardown restores everything. Requires `make up` (PS 9 stack).
 */

const adminPath = process.env.PS_ADMIN_PATH || 'admin-qa';
const TEST_PRODUCT_REF = 'AST-VARIANT-LYD-TEST';

async function goToSyncTab(page: import('@playwright/test').Page) {
  await goToModuleConfig(page, adminPath);
  await page.locator('.ast-tab', { hasText: /Sync/i }).click();
  await expect(page.locator('#panel-sync')).toBeVisible();
}

function getTestProductId(): number {
  const rows = runSqlRows(
    `SELECT id_product FROM ps_product WHERE reference='${TEST_PRODUCT_REF}' LIMIT 1`
  );
  if (rows.length === 0) throw new Error(`Test product ${TEST_PRODUCT_REF} not found — seed fixture not loaded?`);
  return Number(rows[0].id_product);
}

test.describe('Sync — variants (declinations) + LYD currency', () => {
  test.beforeAll(() => {
    // Idempotent: the seed fixture wipes prior test data before inserting.
    loadSqlFixture('seed-variants-lyd.sql');
  });

  test.afterAll(() => {
    loadSqlFixture('cleanup-variants-lyd.sql');
  });

  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page, adminPath);
  });

  // ─── Fixture sanity ───────────────────────────────────────────────

  test('fixture inserted: test product exists with 2 combinations', () => {
    const pid = getTestProductId();
    expect(pid).toBeGreaterThan(0);

    const combos = runSqlRows(
      `SELECT id_product_attribute AS id, reference FROM ps_product_attribute WHERE id_product=${pid}`
    );
    expect(combos.length).toBe(2);
    const refs = combos.map(c => c.reference).sort();
    expect(refs).toEqual(['AST-VAR-BLUE-L', 'AST-VAR-RED-M']);
  });

  test('fixture inserted: LYD currency has precision = 3', () => {
    const rows = runSqlRows(
      `SELECT iso_code, \`precision\` AS prec FROM ps_currency WHERE id_currency=99`
    );
    expect(rows.length).toBe(1);
    expect(rows[0].iso_code).toBe('LYD');
    expect(rows[0].prec).toBe('3');
  });

  test('default currency is LYD after seed', () => {
    const rows = runSqlRows(
      `SELECT value FROM ps_configuration WHERE name='PS_CURRENCY_DEFAULT' LIMIT 1`
    );
    expect(rows[0].value).toBe('99');
  });

  // ─── Combination-only stock eligibility ───────────────────────────

  test('test product is eligible despite parent stock = 0 (combo-only)', () => {
    const pid = getTestProductId();

    // Verify the stock layout we expect: parent at 0, combinations > 0.
    const parentStock = runSqlRows(
      `SELECT quantity FROM ps_stock_available WHERE id_product=${pid} AND id_product_attribute=0`
    );
    expect(parentStock.length).toBe(1);
    expect(Number(parentStock[0].quantity)).toBe(0);

    const comboStock = runSqlRows(
      `SELECT SUM(quantity) AS total FROM ps_stock_available WHERE id_product=${pid} AND id_product_attribute>0`
    );
    expect(Number(comboStock[0].total)).toBeGreaterThan(0);

    // Eligibility check mirrors the SQL in SynchProductsToAiSmartTalk::getProductsToSynchronize:
    // "in stock if at least one stock_available row (parent OR any combination) has quantity > 0".
    // Without the fix, restricting to id_product_attribute = 0 would return 0.
    const eligible = runSqlRows(
      `SELECT EXISTS (
         SELECT 1 FROM ps_stock_available sa
         WHERE sa.id_product=${pid} AND sa.quantity > 0
       ) AS is_eligible`
    );
    expect(eligible[0].is_eligible).toBe('1');

    // And the previous (broken) check would have excluded it — assert that
    // explicitly so a future regression is caught at the SQL level.
    const oldCheck = runSqlRows(
      `SELECT EXISTS (
         SELECT 1 FROM ps_stock_available sa
         WHERE sa.id_product=${pid} AND sa.id_product_attribute=0 AND sa.quantity > 0
       ) AS old_eligible`
    );
    expect(oldCheck[0].old_eligible).toBe('0');
  });

  // ─── Hooks for combination lifecycle ──────────────────────────────

  test('actionProductAttribute hooks are registered for the module', () => {
    const rows = runSqlRows(
      `SELECT h.name AS hook_name
       FROM ps_hook h
       INNER JOIN ps_hook_module hm ON h.id_hook = hm.id_hook
       INNER JOIN ps_module m ON hm.id_module = m.id_module
       WHERE m.name='aismarttalk'
         AND h.name IN ('actionProductAttributeCreate','actionProductAttributeUpdate','actionProductAttributeDelete')`
    );

    // ps_hook_module has one row per (hook, shop), so the same hook name can
    // appear multiple times in multistore. Dedupe before comparing.
    const names = Array.from(new Set(rows.map(r => r.hook_name))).sort();
    expect(names).toEqual([
      'actionProductAttributeCreate',
      'actionProductAttributeDelete',
      'actionProductAttributeUpdate',
    ]);
  });

  // ─── End-to-end sync of the combo-only product ────────────────────

  test('SynchProductsToAiSmartTalk runs on combo-only LYD product without PHP error', () => {
    // Direct PHP-CLI invocation of the sync class against ONLY our test product.
    // This is the exact code path that crashed the client's staging
    // ("SQLSTATE 1064 ... near 'LIMIT 1'" caused by Db::getRow appending its own
    // LIMIT 1 on top of ours). Going through the UI sync button would sync
    // the entire demo catalogue (slow, flaky) and would only surface the error
    // as a 500 page — this gets the PHP exception in stderr directly.
    const pid = getTestProductId();

    // PHP `-r` mishandles backslashes in namespace separators ("unexpected token \").
    // Feed the script over stdin instead — backslashes pass through unchanged.
    const phpScript = `<?php
define("_PS_ADMIN_DIR_", "/var/www/html/admin-qa");
require_once "/var/www/html/config/config.inc.php";
// Force the module to load — config.inc.php doesn't pull its autoloader on its own.
require_once "/var/www/html/modules/aismarttalk/vendor/autoload.php";
require_once "/var/www/html/modules/aismarttalk/aismarttalk.php";
$ctx = Context::getContext();
PrestaShop\\AiSmartTalk\\AiSmartTalkProductSync::markAsNotSynced(${pid});
$sync = new PrestaShop\\AiSmartTalk\\SynchProductsToAiSmartTalk($ctx);
try {
  $result = $sync(["productIds" => ["${pid}"], "forceSync" => true]);
  echo "SYNC_RESULT=" . var_export($result, true) . "\\n";
} catch (\\Throwable $e) {
  echo "SYNC_EXCEPTION=" . get_class($e) . ": " . $e->getMessage() . "\\n";
  echo $e->getTraceAsString() . "\\n";
  exit(1);
}
`;

    const { execFileSync } = require('child_process');
    let output: string;
    try {
      output = execFileSync('docker', [
        'exec', '-i', 'prestashop', 'php', '-d', 'display_errors=1',
      ], { encoding: 'utf-8', input: phpScript, stdio: ['pipe', 'pipe', 'pipe'] });
    } catch (e: any) {
      // Surface the stderr/stdout from PHP so the failure is actionable.
      const stderr = e.stderr?.toString?.() ?? '';
      const stdout = e.stdout?.toString?.() ?? '';
      throw new Error(`SynchProductsToAiSmartTalk crashed:\nSTDOUT:\n${stdout}\nSTDERR:\n${stderr}`);
    }

    // Sync result is the number of synced products (int) on success, or false on
    // API error. We accept BOTH here — the goal is "no PHP exception". The API
    // failure is expected because there's no real AiSmartTalk backend behind
    // the dev container; the PHP path itself must complete cleanly.
    expect(output, 'sync output').toContain('SYNC_RESULT=');
    expect(output, 'sync raised an SQL error').not.toContain('SQLSTATE');
    expect(output, 'sync raised a PDO exception').not.toContain('PDOException');
    expect(output, 'sync threw an exception').not.toContain('SYNC_EXCEPTION=');
  });

  test('combination has its own price impact, distinct from parent', () => {
    const pid = getTestProductId();

    // The seed gives AST-VAR-BLUE-L a +5.000 price impact. The plugin's
    // CombinationHelper relies on Product::getPriceStatic to fold this in;
    // here we just verify the SQL-level impact column is present and correct
    // so a future seed/schema drift is caught.
    const rows = runSqlRows(
      `SELECT reference, price FROM ps_product_attribute WHERE id_product=${pid} ORDER BY reference`
    );
    expect(rows.length).toBe(2);

    const blue = rows.find(r => r.reference === 'AST-VAR-BLUE-L');
    const red = rows.find(r => r.reference === 'AST-VAR-RED-M');
    expect(red?.price).toBe('0.000000');
    expect(Number(blue?.price)).toBeCloseTo(5);
  });
});
