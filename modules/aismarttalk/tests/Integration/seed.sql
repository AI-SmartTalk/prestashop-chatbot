-- =============================================================================
-- Test data: realistic multistore scenario
--
-- Setup:
--   Shop 1 (default): "Boutique FR"  — root category 2
--   Shop 2:           "Boutique EN"  — root category 2
--   Language 1: French
--
-- Products:
--   P1: Active in both shops, in stock in both         → SHOULD sync
--   P2: Active in shop 1 only, in stock                → SHOULD sync
--   P3: Active in shop 2 only, in stock                → SHOULD sync
--   P4: Active in both, out of stock in both           → SHOULD NOT sync
--   P5: Active in shop 1, out of stock in shop 1       → SHOULD NOT sync
--       Active in shop 2, in stock in shop 2           → SHOULD sync (via shop 2)
--   P6: Inactive in both shops                         → SHOULD NOT sync
--   P7: Active in shop 1, in stock, but in excluded    → SHOULD NOT sync (if cat 5 excluded)
--       category 5
--   P8: Active in both, in stock, in category 3+4      → SHOULD sync
--   P9: Active in shop 1, parent stock = 0, but one combination has stock → SHOULD sync
--       (Libyan client pattern: ALL products are sold via declinations)
--
-- Categories:
--   1: Root (PS global, hidden)
--   2: Home (root for both shops)
--   3: Clothes     — both shops  — P1, P8
--   4: Accessories — both shops  — P2, P3, P5, P8
--   5: Art         — shop 1 only — P7
--   6: Electronics — shop 2 only — P3
-- =============================================================================

-- Shop groups
INSERT INTO ps_shop_group (id_shop_group, name, active, share_stock) VALUES
(1, 'Default', 1, 0);

-- Shops
INSERT INTO ps_shop (id_shop, id_shop_group, name, active, id_category) VALUES
(1, 1, 'Boutique FR', 1, 2),
(2, 1, 'Boutique EN', 1, 2);

-- Categories
INSERT INTO ps_category (id_category, id_parent, nleft, nright, level_depth, active, position) VALUES
(1, 0,  1, 14, 0, 1, 0),  -- Root
(2, 1,  2, 13, 1, 1, 0),  -- Home
(3, 2,  3,  4, 2, 1, 0),  -- Clothes
(4, 2,  5,  6, 2, 1, 1),  -- Accessories
(5, 2,  7,  8, 2, 1, 2),  -- Art
(6, 2,  9, 10, 2, 1, 3);  -- Electronics

INSERT INTO ps_category_shop (id_category, id_shop) VALUES
(2, 1), (2, 2),  -- Home: both
(3, 1), (3, 2),  -- Clothes: both
(4, 1), (4, 2),  -- Accessories: both
(5, 1),          -- Art: shop 1 only
(6, 2);          -- Electronics: shop 2 only

INSERT INTO ps_category_lang (id_category, id_shop, id_lang, name, link_rewrite) VALUES
(2, 1, 1, 'Accueil', 'accueil'),
(2, 2, 1, 'Accueil', 'accueil'),
(3, 1, 1, 'Vêtements', 'vetements'),
(3, 2, 1, 'Vêtements', 'vetements'),
(4, 1, 1, 'Accessoires', 'accessoires'),
(4, 2, 1, 'Accessoires', 'accessoires'),
(5, 1, 1, 'Art', 'art'),
(6, 2, 1, 'Electronique', 'electronique');

-- Products
INSERT INTO ps_product (id_product, id_category_default, reference, price, active) VALUES
(1, 3, 'P1-SHARED', 29.99, 1),
(2, 4, 'P2-SHOP1',  19.99, 1),
(3, 4, 'P3-SHOP2',  39.99, 1),
(4, 3, 'P4-NOSTOCK', 9.99, 1),
(5, 4, 'P5-MIXED',  49.99, 1),
(6, 3, 'P6-INACTIVE',14.99, 0),
(7, 5, 'P7-ART',    24.99, 1),
(8, 3, 'P8-MULTI',  34.99, 1),
(9, 3, 'P9-COMBO-ONLY', 19.99, 1);

INSERT INTO ps_product_shop (id_product, id_shop, active, price) VALUES
(1, 1, 1, 29.99), (1, 2, 1, 29.99),  -- P1: both active
(2, 1, 1, 19.99),                      -- P2: shop 1 only
(3, 2, 1, 39.99),                      -- P3: shop 2 only
(4, 1, 1,  9.99), (4, 2, 1,  9.99),  -- P4: both active (but no stock)
(5, 1, 1, 49.99), (5, 2, 1, 49.99),  -- P5: both active
(6, 1, 0, 14.99), (6, 2, 0, 14.99),  -- P6: both inactive
(7, 1, 1, 24.99),                      -- P7: shop 1 only (art category)
(8, 1, 1, 34.99), (8, 2, 1, 34.99),  -- P8: both active
(9, 1, 1, 19.99);                      -- P9: shop 1, parent OOS but combo in stock

INSERT INTO ps_product_lang (id_product, id_shop, id_lang, name, description, description_short, link_rewrite) VALUES
(1, 1, 1, 'T-shirt partagé', 'Description P1', 'Short P1', 't-shirt-partage'),
(2, 1, 1, 'Montre boutique FR', 'Description P2', 'Short P2', 'montre-boutique-fr'),
(3, 1, 1, 'Casque boutique EN', 'Description P3', 'Short P3', 'casque-boutique-en'),
(4, 1, 1, 'Produit sans stock', 'Description P4', 'Short P4', 'produit-sans-stock'),
(5, 1, 1, 'Bracelet mixte', 'Description P5', 'Short P5', 'bracelet-mixte'),
(6, 1, 1, 'Produit inactif', 'Description P6', 'Short P6', 'produit-inactif'),
(7, 1, 1, 'Tableau art', 'Description P7', 'Short P7', 'tableau-art'),
(8, 1, 1, 'Produit multi-cat', 'Description P8', 'Short P8', 'produit-multi-cat'),
(9, 1, 1, 'T-shirt déclinaisons', 'Description P9', 'Short P9', 't-shirt-declinaisons');

-- Category-Product mapping
INSERT INTO ps_category_product (id_category, id_product, position) VALUES
(3, 1, 0),  -- P1 in Clothes
(4, 2, 0),  -- P2 in Accessories
(4, 3, 0),  -- P3 in Accessories
(6, 3, 1),  -- P3 also in Electronics
(3, 4, 1),  -- P4 in Clothes
(4, 5, 1),  -- P5 in Accessories
(3, 6, 2),  -- P6 in Clothes
(5, 7, 0),  -- P7 in Art
(3, 8, 3),  -- P8 in Clothes
(4, 8, 2),  -- P8 also in Accessories
(3, 9, 4);  -- P9 in Clothes (combo-only stock)

-- Stock: per-shop (PS 8+ style, id_shop = shopId)
INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity) VALUES
(1, 0, 1, 0, 50),   -- P1: in stock shop 1
(1, 0, 2, 0, 30),   -- P1: in stock shop 2
(2, 0, 1, 0, 10),   -- P2: in stock shop 1
(3, 0, 2, 0, 20),   -- P3: in stock shop 2
(4, 0, 1, 0, 0),    -- P4: OUT OF STOCK shop 1
(4, 0, 2, 0, 0),    -- P4: OUT OF STOCK shop 2
(5, 0, 1, 0, 0),    -- P5: OUT OF STOCK shop 1
(5, 0, 2, 0, 15),   -- P5: IN STOCK shop 2
(6, 0, 1, 0, 100),  -- P6: in stock but inactive
(6, 0, 2, 0, 100),  -- P6: in stock but inactive
(7, 0, 1, 0, 5),    -- P7: in stock shop 1
(8, 0, 1, 0, 25),   -- P8: in stock shop 1
(8, 0, 2, 0, 10),   -- P8: in stock shop 2
(9, 0,   1, 0, 0),  -- P9: PARENT out of stock
(9, 101, 1, 0, 5);  -- P9: combination 101 IN STOCK → product is eligible via combo

-- Currency
INSERT INTO ps_currency (id_currency, iso_code, sign, active) VALUES
(1, 'EUR', '€', 1);

-- Sync tracking: P1 already synced in shop 1
INSERT INTO ps_aismarttalk_product_sync (id_product, id_shop, synced, last_sync) VALUES
(1, 1, 1, '2026-03-15 10:00:00');

-- Configuration defaults
INSERT INTO ps_configuration (id_shop_group, id_shop, name, value) VALUES
(NULL, NULL, 'PS_LANG_DEFAULT', '1'),
(NULL, NULL, 'PS_CURRENCY_DEFAULT', '1'),
(NULL, NULL, 'PS_SHOP_DEFAULT', '1');
