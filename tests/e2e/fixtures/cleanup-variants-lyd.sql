-- Restore state after variants-sync E2E spec.
-- Safe to run even if seed wasn't applied (uses idempotent deletes).
SET sql_mode = '';

-- Restore PS_CURRENCY_DEFAULT from the marker config set by the seed
UPDATE ps_configuration pc
INNER JOIN ps_configuration mark ON mark.name='AST_TEST_ORIGINAL_CURRENCY'
SET pc.value = mark.value, pc.date_upd = NOW()
WHERE pc.name='PS_CURRENCY_DEFAULT';

DELETE FROM ps_configuration WHERE name='AST_TEST_ORIGINAL_CURRENCY';

-- Drop the test currency (ID 99 reserved for tests)
DELETE FROM ps_currency_shop WHERE id_currency=99;
DELETE FROM ps_currency_lang WHERE id_currency=99;
DELETE FROM ps_currency WHERE id_currency=99;

-- Drop the test product and ALL dependent rows. PS9 doesn't FK-cascade,
-- so leftover ps_product_lang / ps_product_shop / etc. rows would later
-- break re-seeding with duplicate primary keys.
SET @stale_pid := (SELECT id_product FROM ps_product WHERE reference='AST-VARIANT-LYD-TEST' ORDER BY id_product LIMIT 1);

DELETE FROM ps_stock_available WHERE id_product = @stale_pid;
DELETE FROM ps_product_attribute_shop WHERE id_product = @stale_pid;
DELETE FROM ps_product_attribute WHERE id_product = @stale_pid;
DELETE FROM ps_category_product WHERE id_product = @stale_pid;
DELETE FROM ps_product_lang WHERE id_product = @stale_pid;
DELETE FROM ps_product_shop WHERE id_product = @stale_pid;
DELETE FROM ps_product WHERE id_product = @stale_pid;
DELETE FROM ps_product_attribute WHERE reference IN ('AST-VAR-RED-M', 'AST-VAR-BLUE-L');

-- Drop the active promo on the test product (FK-less in PS, so we wipe by id).
DELETE FROM ps_specific_price WHERE id_product = @stale_pid;

-- Drop the sync tracking row for the test product, if any
DELETE aps FROM ps_aismarttalk_product_sync aps
LEFT JOIN ps_product p ON aps.id_product = p.id_product
WHERE p.id_product IS NULL;
