-- =============================================================================
-- Test data: single shop (the 95% use case)
--
-- Setup:
--   Shop 1: "Ma Boutique" — root category 2
--   Language 1: French
--
-- Products:
--   P1:  Active, in stock, Clothes              → sync
--   P2:  Active, in stock, Accessories           → sync
--   P3:  Active, OUT OF STOCK                    → no sync
--   P4:  INACTIVE, in stock                      → no sync
--   P5:  Active, in stock, Clothes + Accessories → sync (multi-category)
--   P6:  Active, in stock, Art > Peinture        → sync (subcategory)
--   P7:  Active, in stock, NO IMAGE              → sync (image is optional)
--   P8:  Active, in stock, with active promo     → sync (with specific price)
--   P9:  Active, in stock, with expired promo    → sync (promo ignored)
--   P10: Active, in stock, two active promos     → sync (picks one)
--   P11: Active, parent stock = 0 but combination has stock → sync
--        (declination-only product: the Libyan client pattern)
--
-- Categories (nested set):
--   1: Root          nleft=1  nright=14
--   2: Home          nleft=2  nright=13
--   3: Clothes       nleft=3  nright=4
--   4: Accessories   nleft=5  nright=6
--   5: Art           nleft=7  nright=12
--   7:   Peinture    nleft=8  nright=9   (child of Art)
--   8:   Sculpture   nleft=10 nright=11  (child of Art)
-- =============================================================================

-- Shop
INSERT INTO ps_shop_group (id_shop_group, name, active, share_stock) VALUES
(1, 'Default', 1, 0);

INSERT INTO ps_shop (id_shop, id_shop_group, name, active, id_category) VALUES
(1, 1, 'Ma Boutique', 1, 2);

-- Categories with proper nested set
INSERT INTO ps_category (id_category, id_parent, nleft, nright, level_depth, active, position) VALUES
(1, 0,  1, 14, 0, 1, 0),
(2, 1,  2, 13, 1, 1, 0),
(3, 2,  3,  4, 2, 1, 0),
(4, 2,  5,  6, 2, 1, 1),
(5, 2,  7, 12, 2, 1, 2),
(7, 5,  8,  9, 3, 1, 0),
(8, 5, 10, 11, 3, 1, 1);

INSERT INTO ps_category_shop (id_category, id_shop) VALUES
(2, 1), (3, 1), (4, 1), (5, 1), (7, 1), (8, 1);

INSERT INTO ps_category_lang (id_category, id_shop, id_lang, name, link_rewrite) VALUES
(2, 1, 1, 'Accueil', 'accueil'),
(3, 1, 1, 'Vêtements', 'vetements'),
(4, 1, 1, 'Accessoires', 'accessoires'),
(5, 1, 1, 'Art', 'art'),
(7, 1, 1, 'Peinture', 'peinture'),
(8, 1, 1, 'Sculpture', 'sculpture');

-- Products
INSERT INTO ps_product (id_product, id_category_default, reference, price, active) VALUES
(1,  3, 'TSHIRT-01',  29.990000, 1),
(2,  4, 'WATCH-01',   99.990000, 1),
(3,  3, 'PANTS-OOS',  49.990000, 1),
(4,  3, 'JACKET-OFF', 79.990000, 0),
(5,  3, 'SCARF-MULTI',19.990000, 1),
(6,  7, 'PAINT-01',   149.990000, 1),
(7,  4, 'RING-NOIMG', 39.990000, 1),
(8,  3, 'SHIRT-PROMO',59.990000, 1),
(9,  4, 'BAG-EXPIRED',89.990000, 1),
(10, 3, 'HAT-2PROMO', 34.990000, 1),
(11, 3, 'JEAN-COMBO', 49.990000, 1);

INSERT INTO ps_product_shop (id_product, id_shop, active, price) VALUES
(1,  1, 1, 29.990000),
(2,  1, 1, 99.990000),
(3,  1, 1, 49.990000),
(4,  1, 0, 79.990000),
(5,  1, 1, 19.990000),
(6,  1, 1, 149.990000),
(7,  1, 1, 39.990000),
(8,  1, 1, 59.990000),
(9,  1, 1, 89.990000),
(10, 1, 1, 34.990000),
(11, 1, 1, 49.990000);

INSERT INTO ps_product_lang (id_product, id_shop, id_lang, name, description, description_short, link_rewrite) VALUES
(1,  1, 1, 'T-shirt basique',     'Un beau t-shirt en coton bio', 'T-shirt coton', 't-shirt-basique'),
(2,  1, 1, 'Montre classique',    'Montre analogique en acier',   'Montre acier',  'montre-classique'),
(3,  1, 1, 'Pantalon épuisé',     'Ce pantalon est en rupture',   'Pantalon',      'pantalon-epuise'),
(4,  1, 1, 'Veste désactivée',    'Veste hiver, retirée',         'Veste',         'veste-desactivee'),
(5,  1, 1, 'Écharpe multi-catégorie','Écharpe tendance',          'Écharpe',       'echarpe-multi'),
(6,  1, 1, 'Tableau peinture',    'Huile sur toile 80x60',        'Peinture',      'tableau-peinture'),
(7,  1, 1, 'Bague sans image',    'Bague argent, pas de photo',   'Bague',         'bague-sans-image'),
(8,  1, 1, 'Chemise en promo',    'Chemise avec réduction active','Chemise promo', 'chemise-promo'),
(9,  1, 1, 'Sac promo expirée',   'Sac dont la promo est finie', 'Sac',           'sac-promo-expiree'),
(10, 1, 1, 'Chapeau double promo','Chapeau avec 2 réductions',   'Chapeau',       'chapeau-double-promo'),
(11, 1, 1, 'Jean déclinaisons',   'Jean vendu uniquement en tailles', 'Jean',     'jean-declinaisons');

-- Category-Product mapping
INSERT INTO ps_category_product (id_category, id_product, position) VALUES
(3, 1,  0),
(4, 2,  0),
(3, 3,  1),
(3, 4,  2),
(3, 5,  3), (4, 5, 1),   -- P5 in Clothes AND Accessories
(7, 6,  0),               -- P6 in Peinture (subcategory of Art)
(4, 7,  2),
(3, 8,  4),
(4, 9,  3),
(3, 10, 5),
(3, 11, 6);             -- P11 in Clothes (combo-only stock)

-- Stock
INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity) VALUES
(1,  0, 1, 0, 50),
(2,  0, 1, 0, 12),
(3,  0, 1, 0, 0),    -- OUT OF STOCK
(4,  0, 1, 0, 30),   -- has stock but product is inactive
(5,  0, 1, 0, 8),
(6,  0, 1, 0, 3),
(7,  0, 1, 0, 20),
(8,  0, 1, 0, 15),
(9,  0, 1, 0, 7),
(10, 0, 1, 0, 10),
(11, 0,   1, 0, 0),     -- P11: PARENT out of stock
(11, 201, 1, 0, 6);     -- P11: combination 201 (e.g. size M) IN STOCK

-- Images (P7 has NO image)
INSERT INTO ps_image (id_image, id_product, cover) VALUES
(1, 1, 1), (2, 2, 1), (3, 3, 1), (4, 5, 1), (5, 6, 1), (6, 8, 1), (7, 9, 1), (8, 10, 1);

INSERT INTO ps_image_shop (id_image, id_product, id_shop, cover) VALUES
(1, 1, 1, 1), (2, 2, 1, 1), (3, 3, 1, 1), (4, 5, 1, 1), (5, 6, 1, 1), (6, 8, 1, 1), (7, 9, 1, 1), (8, 10, 1, 1);

-- Specific prices
INSERT INTO ps_specific_price (id_specific_price, id_product, id_shop, price, `from`, `to`, reduction, reduction_type) VALUES
-- P8: active promo (-20%)
(1, 8, 1, -1.000000, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 0.200000, 'percentage'),
-- P9: EXPIRED promo
(2, 9, 1, -1.000000, '2025-01-01 00:00:00', '2025-06-30 23:59:59', 0.150000, 'percentage'),
-- P10: TWO active promos (shop-specific + global)
(3, 10, 1, -1.000000, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 0.100000, 'percentage'),
(4, 10, 0, -1.000000, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 0.050000, 'amount');

-- Currency
INSERT INTO ps_currency (id_currency, iso_code, sign, active) VALUES
(1, 'EUR', '€', 1);

-- Configuration
INSERT INTO ps_configuration (id_shop_group, id_shop, name, value) VALUES
(NULL, NULL, 'PS_LANG_DEFAULT', '1'),
(NULL, NULL, 'PS_CURRENCY_DEFAULT', '1'),
(NULL, NULL, 'PS_SHOP_DEFAULT', '1');
