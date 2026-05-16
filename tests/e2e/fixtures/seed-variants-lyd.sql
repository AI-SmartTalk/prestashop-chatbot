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
INSERT INTO ps_currency
  (id_currency, name, iso_code, numeric_iso_code, `precision`, conversion_rate, deleted, active, unofficial, modified)
VALUES
  (99, 'Libyan Dinar', 'LYD', '434', 3, 1.000000, 0, 1, 0, 0)
ON DUPLICATE KEY UPDATE
  iso_code='LYD', `precision`=3, active=1, deleted=0, conversion_rate=1.000000;

INSERT INTO ps_currency_lang (id_currency, id_lang, name, symbol, pattern)
SELECT 99, id_lang, 'Libyan Dinar', 'LYD', '#,##0.000 ¤' FROM ps_lang
ON DUPLICATE KEY UPDATE name='Libyan Dinar', symbol='LYD';

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

-- ps_product (PS9 column list: 55 columns, id_product omitted for auto_increment)
INSERT INTO ps_product
  (id_supplier, id_manufacturer, id_category_default, id_shop_default, id_tax_rules_group,
   on_sale, online_only, ean13, isbn, upc, mpn, ecotax, quantity, minimal_quantity,
   low_stock_threshold, low_stock_alert, price, wholesale_price, unity, unit_price, unit_price_ratio,
   additional_shipping_cost, reference, supplier_reference, location, width, height, depth,
   weight, out_of_stock, additional_delivery_times, quantity_discount, customizable,
   uploadable_files, text_fields, active, redirect_type, id_type_redirected, available_for_order,
   available_date, show_condition, `condition`, show_price, indexed, visibility, cache_is_pack,
   cache_has_attachments, is_virtual, cache_default_attribute, date_add, date_upd,
   advanced_stock_management, pack_stock_type, state, product_type)
SELECT
   id_supplier, id_manufacturer, id_category_default, id_shop_default, id_tax_rules_group,
   on_sale, online_only, ean13, isbn, upc, mpn, ecotax, quantity, minimal_quantity,
   low_stock_threshold, low_stock_alert, 50.000000, wholesale_price, unity, unit_price, unit_price_ratio,
   additional_shipping_cost, 'AST-VARIANT-LYD-TEST', supplier_reference, location, width, height, depth,
   weight, out_of_stock, additional_delivery_times, quantity_discount, customizable,
   uploadable_files, text_fields, 1, redirect_type, id_type_redirected, available_for_order,
   available_date, show_condition, `condition`, show_price, indexed, visibility, cache_is_pack,
   cache_has_attachments, is_virtual, cache_default_attribute, NOW(), NOW(),
   advanced_stock_management, pack_stock_type, state, 'combinations'
FROM ps_product WHERE id_product = 1;

SET @ast_pid := LAST_INSERT_ID();

-- ps_product_shop (PS9: 34 columns, no product_type)
INSERT INTO ps_product_shop
  (id_product, id_shop, id_category_default, id_tax_rules_group, on_sale, online_only,
   ecotax, minimal_quantity, low_stock_threshold, low_stock_alert, price, wholesale_price,
   unity, unit_price, unit_price_ratio, additional_shipping_cost, customizable, uploadable_files,
   text_fields, active, redirect_type, id_type_redirected, available_for_order, available_date,
   show_condition, `condition`, show_price, indexed, visibility, cache_default_attribute,
   advanced_stock_management, date_add, date_upd, pack_stock_type)
SELECT
   @ast_pid, id_shop, id_category_default, id_tax_rules_group, on_sale, online_only,
   ecotax, minimal_quantity, low_stock_threshold, low_stock_alert, 50.000000, wholesale_price,
   unity, unit_price, unit_price_ratio, additional_shipping_cost, customizable, uploadable_files,
   text_fields, 1, redirect_type, id_type_redirected, available_for_order, available_date,
   show_condition, `condition`, show_price, indexed, visibility, cache_default_attribute,
   advanced_stock_management, NOW(), NOW(), pack_stock_type
FROM ps_product_shop WHERE id_product = 1;

-- ps_product_lang (PS9: no meta_keywords)
INSERT INTO ps_product_lang
  (id_product, id_shop, id_lang, description, description_short, link_rewrite,
   meta_description, meta_title, name, available_now, available_later, delivery_in_stock,
   delivery_out_stock)
SELECT
   @ast_pid, id_shop, id_lang,
   'Produit AST test avec déclinaisons — utilisé pour vérifier la sync LYD.',
   'AST test variantes LYD',
   'ast-variant-lyd-test',
   'AST test', 'AST Variant LYD Test',
   'AST Variant LYD Test', available_now, available_later, delivery_in_stock, delivery_out_stock
FROM ps_product_lang WHERE id_product = 1;

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
INSERT INTO ps_product_attribute
  (id_product, reference, supplier_reference, ean13, isbn, upc, mpn, wholesale_price,
   price, ecotax, weight, unit_price_impact, default_on, minimal_quantity,
   low_stock_threshold, low_stock_alert, available_date)
VALUES
  (@ast_pid, 'AST-VAR-RED-M', '', '', '', '', '', 0, 0, 0, 0, 0, 1, 1, NULL, 0, '0000-00-00');
SET @ast_combo1 := LAST_INSERT_ID();

INSERT INTO ps_product_attribute_shop
  (id_product, id_product_attribute, id_shop, wholesale_price, price, ecotax, weight,
   unit_price_impact, default_on, minimal_quantity, low_stock_threshold, low_stock_alert, available_date)
SELECT @ast_pid, @ast_combo1, id_shop, 0, 0, 0, 0, 0, 1, 1, NULL, 0, '0000-00-00'
FROM ps_shop WHERE active=1;

INSERT INTO ps_stock_available
  (id_product, id_product_attribute, id_shop, id_shop_group, quantity, physical_quantity,
   reserved_quantity, depends_on_stock, out_of_stock, location)
VALUES
  (@ast_pid, @ast_combo1, 1, 0, 10, 10, 0, 0, 2, '');

INSERT INTO ps_product_attribute
  (id_product, reference, supplier_reference, ean13, isbn, upc, mpn, wholesale_price,
   price, ecotax, weight, unit_price_impact, default_on, minimal_quantity,
   low_stock_threshold, low_stock_alert, available_date)
VALUES
  (@ast_pid, 'AST-VAR-BLUE-L', '', '', '', '', '', 0, 5.000000, 0, 0, 0, 0, 1, NULL, 0, '0000-00-00');
SET @ast_combo2 := LAST_INSERT_ID();

INSERT INTO ps_product_attribute_shop
  (id_product, id_product_attribute, id_shop, wholesale_price, price, ecotax, weight,
   unit_price_impact, default_on, minimal_quantity, low_stock_threshold, low_stock_alert, available_date)
SELECT @ast_pid, @ast_combo2, id_shop, 0, 5.000000, 0, 0, 1, 0, 1, NULL, 0, '0000-00-00'
FROM ps_shop WHERE active=1;

INSERT INTO ps_stock_available
  (id_product, id_product_attribute, id_shop, id_shop_group, quantity, physical_quantity,
   reserved_quantity, depends_on_stock, out_of_stock, location)
VALUES
  (@ast_pid, @ast_combo2, 1, 0, 5, 5, 0, 0, 2, '');

-- ─────────────────────────────────────────────────────────────────
-- 5. Hand back the inserted IDs for tests that need them
-- ─────────────────────────────────────────────────────────────────
SELECT @ast_pid AS test_product_id, @ast_combo1 AS test_combo_red_id, @ast_combo2 AS test_combo_blue_id;
