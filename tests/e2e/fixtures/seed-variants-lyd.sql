-- E2E fixture: Libyan dinar (LYD, 3 decimals) currency + a test product
-- whose only stock comes from its combinations (declinations).
--
-- This reproduces the Libyan client setup: all products are sold via variants,
-- and the shop currency uses 3-decimal precision (.000 must survive sync).
--
-- Idempotent: re-running the fixture cleans up previous test data first.
-- Pairs with cleanup-variants-lyd.sql to fully restore state.

-- PrestaShop uses '0000-00-00' for "no date" but MySQL 5.7+ defaults to NO_ZERO_DATE.
-- Match the strict-mode-off setting PrestaShop itself uses at runtime.
SET sql_mode = '';

-- ─────────────────────────────────────────────────────────────────
-- 1. Add LYD currency with 3-decimal precision (id 99 reserved for tests)
-- ─────────────────────────────────────────────────────────────────
-- The currency schema diverges sharply across versions: 1.7.5 keeps display
-- (sign/format/decimals) on ps_currency and has NO ps_currency_lang; 1.7.6+/8/9
-- move display to ps_currency_lang and rename columns (precision, numeric_iso_code,
-- unofficial, modified). Clone an existing currency row so the column set matches
-- THIS version, then force only what the test relies on: iso_code + 3-decimal
-- precision (so LYD's .000 survives the sync).
DELETE FROM ps_currency WHERE id_currency = 99;
DROP TEMPORARY TABLE IF EXISTS tmp_ast_cur;
CREATE TEMPORARY TABLE tmp_ast_cur SELECT * FROM ps_currency WHERE id_currency = 1;
UPDATE tmp_ast_cur SET
   id_currency = 99, name = 'Libyan Dinar', iso_code = 'LYD',
   conversion_rate = 1.000000, deleted = 0, active = 1;
INSERT INTO ps_currency SELECT * FROM tmp_ast_cur;
DROP TEMPORARY TABLE tmp_ast_cur;

-- 3-decimal precision lives in `precision` (1.7.6+/8/9) or `decimals` (some 1.7.x).
-- On 1.7.0–1.7.5 ps_currency has NEITHER: precision is derived from CLDR via the
-- iso_code ('LYD' → 3 decimals), so there is nothing to set on the row.
SET @has_precision := (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'ps_currency' AND column_name = 'precision');
SET @has_decimals := (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'ps_currency' AND column_name = 'decimals');
SET @sql := CASE
   WHEN @has_precision > 0 THEN 'UPDATE ps_currency SET `precision`=3 WHERE id_currency=99'
   WHEN @has_decimals > 0 THEN 'UPDATE ps_currency SET decimals=3 WHERE id_currency=99'
   ELSE 'DO 0'
END;
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ps_currency_lang only exists on 1.7.6+. Insert the LYD .000 pattern when present.
SET @has_cur_lang := (SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema = DATABASE() AND table_name = 'ps_currency_lang');
SET @sql := IF(@has_cur_lang > 0,
   "INSERT INTO ps_currency_lang (id_currency, id_lang, name, symbol, pattern) SELECT 99, id_lang, 'Libyan Dinar', 'LYD', '#,##0.000 ¤' FROM ps_lang ON DUPLICATE KEY UPDATE name='Libyan Dinar', symbol='LYD', pattern='#,##0.000 ¤'",
   'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT IGNORE INTO ps_currency_shop (id_currency, id_shop, conversion_rate)
SELECT 99, id_shop, 1.000000 FROM ps_shop WHERE active=1;

-- ─────────────────────────────────────────────────────────────────
-- 2. Switch default currency to LYD (saving previous via marker config)
-- ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO ps_configuration (name, value, date_add, date_upd)
SELECT 'AST_TEST_ORIGINAL_CURRENCY', value, NOW(), NOW()
FROM ps_configuration WHERE name='PS_CURRENCY_DEFAULT';

UPDATE ps_configuration SET value='99', date_upd=NOW() WHERE name='PS_CURRENCY_DEFAULT';

-- ─────────────────────────────────────────────────────────────────
-- 3. Clean any previous test data — PS9 has no FK cascades so we must
--    delete dependents explicitly. Without this, re-running the seed
--    duplicate-key-fails on ps_product_lang / ps_product_shop / etc.
-- ─────────────────────────────────────────────────────────────────
SET @stale_pid := (SELECT id_product FROM ps_product WHERE reference='AST-VARIANT-LYD-TEST' ORDER BY id_product LIMIT 1);

DELETE FROM ps_stock_available WHERE id_product = @stale_pid;
DELETE FROM ps_product_attribute_shop WHERE id_product = @stale_pid;
DELETE FROM ps_product_attribute WHERE id_product = @stale_pid;
DELETE FROM ps_category_product WHERE id_product = @stale_pid;
DELETE FROM ps_product_lang WHERE id_product = @stale_pid;
DELETE FROM ps_product_shop WHERE id_product = @stale_pid;
DELETE FROM ps_product WHERE id_product = @stale_pid;
DELETE FROM ps_product_attribute WHERE reference IN ('AST-VAR-RED-M', 'AST-VAR-BLUE-L');

-- Purge ALL orphan product-related rows. PrestaShop never leaves orphans during
-- normal operation, but prior failed seed attempts can — and they cause
-- "Duplicate entry 'NN-1' for key 'PRIMARY'" when auto_increment reuses the id.
DELETE pl FROM ps_product_lang pl LEFT JOIN ps_product p ON pl.id_product=p.id_product WHERE p.id_product IS NULL;
DELETE ps FROM ps_product_shop ps LEFT JOIN ps_product p ON ps.id_product=p.id_product WHERE p.id_product IS NULL;
DELETE cp FROM ps_category_product cp LEFT JOIN ps_product p ON cp.id_product=p.id_product WHERE p.id_product IS NULL;
DELETE sa FROM ps_stock_available sa LEFT JOIN ps_product p ON sa.id_product=p.id_product WHERE sa.id_product > 0 AND p.id_product IS NULL;
DELETE pa FROM ps_product_attribute pa LEFT JOIN ps_product p ON pa.id_product=p.id_product WHERE p.id_product IS NULL;
DELETE pas FROM ps_product_attribute_shop pas LEFT JOIN ps_product_attribute pa ON pas.id_product_attribute=pa.id_product_attribute WHERE pa.id_product_attribute IS NULL;

-- ps_product — clone the demo product #1 through a temp table so the column set
-- ADAPTS to whatever schema THIS PrestaShop version has. Enumerating columns
-- (the old approach) hard-codes the PS9 schema and breaks on 1.7.x, which lacks
-- unit_price / product_type / low_stock_* etc. `SELECT *` copies exactly the
-- columns that exist. id_product = 0 makes auto_increment assign a fresh id
-- (sql_mode='' above keeps 0 → auto-increment instead of a literal 0 insert).
DROP TEMPORARY TABLE IF EXISTS tmp_ast_product;
CREATE TEMPORARY TABLE tmp_ast_product SELECT * FROM ps_product WHERE id_product = 1;
UPDATE tmp_ast_product SET
   id_product = 0,
   reference = 'AST-VARIANT-LYD-TEST',
   price = 50.000000,
   active = 1,
   date_add = NOW(), date_upd = NOW();
INSERT INTO ps_product SELECT * FROM tmp_ast_product;
SET @ast_pid := LAST_INSERT_ID();
DROP TEMPORARY TABLE tmp_ast_product;

-- ps_product_shop — same schema-agnostic clone, re-pointed at the new product.
DROP TEMPORARY TABLE IF EXISTS tmp_ast_pshop;
CREATE TEMPORARY TABLE tmp_ast_pshop SELECT * FROM ps_product_shop WHERE id_product = 1;
UPDATE tmp_ast_pshop SET
   id_product = @ast_pid,
   price = 50.000000,
   active = 1,
   date_add = NOW(), date_upd = NOW();
INSERT INTO ps_product_shop SELECT * FROM tmp_ast_pshop;
DROP TEMPORARY TABLE tmp_ast_pshop;

-- ps_product_lang — clone (covers meta_keywords on 1.7, absent on 9) then
-- overwrite the human-facing fields for every (shop, lang) row.
DROP TEMPORARY TABLE IF EXISTS tmp_ast_plang;
CREATE TEMPORARY TABLE tmp_ast_plang SELECT * FROM ps_product_lang WHERE id_product = 1;
UPDATE tmp_ast_plang SET
   id_product = @ast_pid,
   name = 'AST Variant LYD Test',
   link_rewrite = 'ast-variant-lyd-test',
   description = 'Produit AST test avec déclinaisons — utilisé pour vérifier la sync LYD.',
   description_short = 'AST test variantes LYD',
   meta_title = 'AST Variant LYD Test',
   meta_description = 'AST test';
INSERT INTO ps_product_lang SELECT * FROM tmp_ast_plang;
DROP TEMPORARY TABLE tmp_ast_plang;

-- Bind to root category (id=2)
INSERT INTO ps_category_product (id_category, id_product, position) VALUES (2, @ast_pid, 0);

-- Parent stock = 0 (the whole point: stock only comes from combinations)
INSERT INTO ps_stock_available
  (id_product, id_product_attribute, id_shop, id_shop_group, quantity, physical_quantity,
   reserved_quantity, depends_on_stock, out_of_stock, location)
VALUES
  (@ast_pid, 0, 1, 0, 0, 0, 0, 0, 2, '');

-- ─────────────────────────────────────────────────────────────────
-- 4. Two combinations, both in stock. PS9 ps_product_attribute schema:
--    no `quantity` column; price is the IMPACT, not the final price.
-- ─────────────────────────────────────────────────────────────────
-- Universal column set only (id_product → available_date): present since 1.6,
-- so the same INSERT runs on 1.7.5 / 1.7.8 / 8 / 9. The columns the old fixture
-- listed — isbn, mpn, low_stock_threshold, low_stock_alert — don't exist before
-- 1.7.6/1.7.7 and have safe defaults on newer versions, so dropping them is
-- version-portable.
INSERT INTO ps_product_attribute
  (id_product, reference, supplier_reference, ean13, upc, wholesale_price,
   price, ecotax, weight, unit_price_impact, default_on, minimal_quantity, available_date)
VALUES
  (@ast_pid, 'AST-VAR-RED-M', '', '', '', 0, 0, 0, 0, 0, 1, 1, '0000-00-00');
SET @ast_combo1 := LAST_INSERT_ID();

INSERT INTO ps_product_attribute_shop
  (id_product, id_product_attribute, id_shop, wholesale_price, price, ecotax, weight,
   unit_price_impact, default_on, minimal_quantity, available_date)
SELECT @ast_pid, @ast_combo1, id_shop, 0, 0, 0, 0, 0, 1, 1, '0000-00-00'
FROM ps_shop WHERE active=1;

INSERT INTO ps_stock_available
  (id_product, id_product_attribute, id_shop, id_shop_group, quantity, physical_quantity,
   reserved_quantity, depends_on_stock, out_of_stock, location)
VALUES
  (@ast_pid, @ast_combo1, 1, 0, 10, 10, 0, 0, 2, '');

INSERT INTO ps_product_attribute
  (id_product, reference, supplier_reference, ean13, upc, wholesale_price,
   price, ecotax, weight, unit_price_impact, default_on, minimal_quantity, available_date)
VALUES
  (@ast_pid, 'AST-VAR-BLUE-L', '', '', '', 0, 5.000000, 0, 0, 0, 0, 1, '0000-00-00');
SET @ast_combo2 := LAST_INSERT_ID();

INSERT INTO ps_product_attribute_shop
  (id_product, id_product_attribute, id_shop, wholesale_price, price, ecotax, weight,
   unit_price_impact, default_on, minimal_quantity, available_date)
SELECT @ast_pid, @ast_combo2, id_shop, 0, 5.000000, 0, 0, 1, 0, 1, '0000-00-00'
FROM ps_shop WHERE active=1;

INSERT INTO ps_stock_available
  (id_product, id_product_attribute, id_shop, id_shop_group, quantity, physical_quantity,
   reserved_quantity, depends_on_stock, out_of_stock, location)
VALUES
  (@ast_pid, @ast_combo2, 1, 0, 5, 5, 0, 0, 2, '');

-- ─────────────────────────────────────────────────────────────────
-- 5. Active -20% promo on the test product (catalog specific_price).
--    Exercises the PriceCalculator path that captures specificPriceOutput
--    and surfaces original_price / discount_percent / discount_type.
--    Idempotent: cleared by reference at the start of section 3.
-- ─────────────────────────────────────────────────────────────────
DELETE FROM ps_specific_price WHERE id_product = @ast_pid;

INSERT INTO ps_specific_price
  (id_specific_price_rule, id_cart, id_product, id_shop, id_shop_group, id_currency, id_country,
   id_group, id_customer, id_product_attribute, price, from_quantity, reduction, reduction_tax,
   reduction_type, `from`, `to`)
VALUES
  (0, 0, @ast_pid, 0, 0, 0, 0,
   0, 0, 0, -1.000000, 1, 0.200000, 1,
   'percentage', '2026-01-01 00:00:00', '2027-01-01 00:00:00');

-- ─────────────────────────────────────────────────────────────────
-- 6. Hand back the inserted IDs for tests that need them
-- ─────────────────────────────────────────────────────────────────
SELECT @ast_pid AS test_product_id, @ast_combo1 AS test_combo_red_id, @ast_combo2 AS test_combo_blue_id;
