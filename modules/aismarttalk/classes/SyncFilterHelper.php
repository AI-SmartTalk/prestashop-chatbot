<?php
/**
 * Copyright (c) 2026 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2026 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Helper class for product sync filtering by categories and product types
 */
class SyncFilterHelper
{
    /** Configuration keys */
    const CONFIG_FILTER_MODE = 'AI_SMART_TALK_SYNC_FILTER_MODE';
    const CONFIG_CATEGORIES = 'AI_SMART_TALK_SYNC_CATEGORIES';
    const CONFIG_INCLUDE_SUBCATEGORIES = 'AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES';
    /** Filter modes */
    const MODE_INCLUDE = 'include';
    const MODE_EXCLUDE = 'exclude';

    /**
     * Get the category tree with product counts.
     * In multistore mode, shows categories from ALL shops with product counts across all shops.
     *
     * @param int $langId Language ID
     * @param int $shopId Shop ID (used as fallback for lang joins and root category)
     * @return array Hierarchical array of categories
     */
    public static function getCategoryTree(int $langId, int $shopId): array
    {
        $allShopIds = MultistoreHelper::getAllShopIds();
        $shopIdList = implode(',', array_map('intval', $allShopIds));

        // Get root categories from all shops to determine the full tree boundary
        $rootBoundary = self::buildRootBoundarySQL($allShopIds);

        // Use categories associated with ANY shop, count active products across ALL shops
        // Subquery for name with fallback (strict GROUP BY compatible)
        $nameSubquery = '(SELECT cl_sub.name FROM ' . _DB_PREFIX_ . 'category_lang cl_sub
                          WHERE cl_sub.id_category = c.id_category AND cl_sub.id_lang = ' . (int) $langId . '
                          ORDER BY FIELD(cl_sub.id_shop, ' . (int) $shopId . ') DESC, cl_sub.id_shop ASC
                          LIMIT 1)';

        // Subquery for product count across all shops
        $productCountSubquery = '(SELECT COUNT(DISTINCT cp_sub.id_product)
                                  FROM ' . _DB_PREFIX_ . 'category_product cp_sub
                                  INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps_sub
                                      ON cp_sub.id_product = ps_sub.id_product
                                      AND ps_sub.id_shop IN (' . $shopIdList . ') AND ps_sub.active = 1
                                  WHERE cp_sub.id_category = c.id_category)';

        $sql = 'SELECT c.id_category, c.id_parent, c.nleft, c.nright, c.level_depth,
                       ' . $nameSubquery . ' as name,
                       ' . $productCountSubquery . ' as product_count
                FROM ' . _DB_PREFIX_ . 'category c
                WHERE c.active = 1
                    AND c.id_category != 1
                    AND EXISTS (SELECT 1 FROM ' . _DB_PREFIX_ . 'category_shop cs
                                WHERE cs.id_category = c.id_category AND cs.id_shop IN (' . $shopIdList . '))
                    AND ' . $nameSubquery . ' IS NOT NULL
                    ' . $rootBoundary . '
                ORDER BY c.level_depth ASC, c.position ASC';

        $categories = \Db::getInstance()->executeS($sql);

        if (!$categories) {
            return [];
        }

        return self::buildCategoryHierarchy($categories);
    }

    /**
     * Build SQL boundary condition to include categories from all shops' root categories.
     *
     * @param array $shopIds
     * @return string SQL WHERE fragment
     */
    private static function buildRootBoundarySQL(array $shopIds): string
    {
        if (count($shopIds) === 1) {
            $shop = new \Shop($shopIds[0]);
            $rootCategoryId = (int) $shop->id_category;
            return 'AND c.nleft >= (SELECT nleft FROM ' . _DB_PREFIX_ . 'category WHERE id_category = ' . $rootCategoryId . ')
                    AND c.nright <= (SELECT nright FROM ' . _DB_PREFIX_ . 'category WHERE id_category = ' . $rootCategoryId . ')';
        }

        // Multiple shops: include categories under ANY shop's root category
        $conditions = [];
        foreach ($shopIds as $id) {
            $shop = new \Shop($id);
            $rootId = (int) $shop->id_category;
            $conditions[] = '(c.nleft >= (SELECT nleft FROM ' . _DB_PREFIX_ . 'category WHERE id_category = ' . $rootId . ')
                              AND c.nright <= (SELECT nright FROM ' . _DB_PREFIX_ . 'category WHERE id_category = ' . $rootId . '))';
        }

        return 'AND (' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Build hierarchical structure from flat category list
     *
     * @param array $categories Flat list of categories
     * @return array Hierarchical tree
     */
    private static function buildCategoryHierarchy(array $categories): array
    {
        $tree = [];
        $lookup = [];

        // First pass: create lookup table
        foreach ($categories as $category) {
            $lookup[$category['id_category']] = array_merge($category, ['children' => []]);
        }

        // Second pass: build hierarchy
        foreach ($lookup as $id => &$category) {
            $parentId = $category['id_parent'];
            if (isset($lookup[$parentId])) {
                $lookup[$parentId]['children'][] = &$category;
            } else {
                $tree[] = &$category;
            }
        }

        return $tree;
    }

    /**
     * Flatten category tree for template rendering
     *
     * @param array $tree Category tree
     * @param int $depth Current depth
     * @param int|null $parentId Parent category ID
     * @return array Flat list with depth information
     */
    public static function flattenCategoryTree(array $tree, int $depth = 0, ?int $parentId = null): array
    {
        $flat = [];
        foreach ($tree as $category) {
            $children = $category['children'] ?? [];
            $childIds = array_map(function ($c) {
                return (int) $c['id_category'];
            }, $children);
            unset($category['children']);
            $category['depth'] = $depth;
            $category['has_children'] = !empty($children);
            $category['child_ids'] = $childIds;
            $category['parent_id'] = $parentId;
            $flat[] = $category;
            if (!empty($children)) {
                $flat = array_merge($flat, self::flattenCategoryTree($children, $depth + 1, (int) $category['id_category']));
            }
        }
        return $flat;
    }

    /**
     * Get all descendant category IDs for a given category
     *
     * @param int $categoryId Category ID
     * @return array List of descendant IDs (including the category itself)
     */
    public static function getAllDescendantIds(int $categoryId): array
    {
        $sql = 'SELECT c2.id_category
                FROM ' . _DB_PREFIX_ . 'category c1
                INNER JOIN ' . _DB_PREFIX_ . 'category c2 ON c2.nleft >= c1.nleft AND c2.nright <= c1.nright
                WHERE c1.id_category = ' . (int) $categoryId;

        $result = \Db::getInstance()->executeS($sql);
        if (!$result) {
            return [$categoryId];
        }

        return array_map(function ($row) {
            return (int) $row['id_category'];
        }, $result);
    }

    /**
     * Build the SQL JOIN clause for stock_available that handles both
     * shop-level stock (id_shop = X) and shop-group-level stock (id_shop = 0).
     *
     * PrestaShop 1.7 with multistore/shared stock stores quantities with id_shop = 0
     * and id_shop_group = X. PrestaShop 8+ always uses id_shop = X.
     * This helper ensures compatibility with both configurations.
     *
     * @param int $shopId Shop ID
     * @param string $productAlias Alias of the product table (default: 'p')
     * @param string $stockAlias Alias for the stock_available table (default: 'sa')
     * @return string SQL LEFT JOIN clause
     */
    public static function buildStockAvailableJoin(int $shopId, string $productAlias = 'p', string $stockAlias = 'sa'): string
    {
        $shopGroupId = (int) \Shop::getGroupFromShop((int) $shopId);

        return 'LEFT JOIN ' . _DB_PREFIX_ . 'stock_available ' . $stockAlias
            . ' ON ' . $productAlias . '.id_product = ' . $stockAlias . '.id_product'
            . ' AND ' . $stockAlias . '.id_product_attribute = 0'
            . ' AND ('
            .     $stockAlias . '.id_shop = ' . (int) $shopId
            .     ' OR (' . $stockAlias . '.id_shop = 0 AND ' . $stockAlias . '.id_shop_group = ' . $shopGroupId . ')'
            . ')';
    }

    /**
     * Get the current filter configuration
     *
     * @return array Filter configuration
     */
    public static function getFilterConfig(): array
    {
        $config = [
            'mode' => MultistoreHelper::getConfig(self::CONFIG_FILTER_MODE) ?: self::MODE_INCLUDE,
            'categories' => [],
            'include_subcategories' => (bool) MultistoreHelper::getConfig(self::CONFIG_INCLUDE_SUBCATEGORIES),
        ];

        // Decode categories JSON
        $categoriesJson = MultistoreHelper::getConfig(self::CONFIG_CATEGORIES);
        if (!empty($categoriesJson)) {
            $decoded = json_decode($categoriesJson, true);
            if (is_array($decoded)) {
                $config['categories'] = array_map('intval', $decoded);
            }
        }

        return $config;
    }

    /**
     * Save filter configuration
     *
     * @param array $config Filter configuration
     * @return bool Success
     */
    public static function saveFilterConfig(array $config): bool
    {
        $success = true;

        // Validate and save mode
        $mode = isset($config['mode']) && $config['mode'] === self::MODE_EXCLUDE
            ? self::MODE_EXCLUDE
            : self::MODE_INCLUDE;
        $success = $success && MultistoreHelper::updateConfig(self::CONFIG_FILTER_MODE, $mode);

        // Validate and save categories
        $categories = [];
        if (isset($config['categories']) && is_array($config['categories'])) {
            $categories = array_map('intval', array_filter($config['categories'], function ($id) {
                return (int) $id > 0;
            }));
        }
        $success = $success && MultistoreHelper::updateConfig(
            self::CONFIG_CATEGORIES,
            json_encode(array_values($categories))
        );

        // Save include subcategories flag
        $includeSubcategories = !empty($config['include_subcategories']);
        $success = $success && MultistoreHelper::updateConfig(
            self::CONFIG_INCLUDE_SUBCATEGORIES,
            $includeSubcategories ? '1' : '0'
        );

        return $success;
    }

    /**
     * Check if any filters are active (categories selected or product types limited)
     *
     * @return bool True if filters are active
     */
    public static function hasActiveFilters(): bool
    {
        $config = self::getFilterConfig();

        // Check if categories are selected
        if (!empty($config['categories'])) {
            return true;
        }

        return false;
    }

    /**
     * Build SQL WHERE clause for category filtering
     *
     * @param int $shopId Shop ID
     * @return string SQL WHERE clause (empty if no filter)
     */
    public static function buildCategoryFilterSQL(int $shopId): string
    {
        $config = self::getFilterConfig();

        // No categories selected = no filter
        if (empty($config['categories'])) {
            return '';
        }

        $categoryIds = array_map('intval', $config['categories']);
        $categoryList = implode(',', $categoryIds);
        $isExclude = $config['mode'] === self::MODE_EXCLUDE;

        if ($config['include_subcategories']) {
            // Include/exclude with subcategories using nested set model
            $subquery = 'SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp_filter
                         INNER JOIN ' . _DB_PREFIX_ . 'category c_filter ON cp_filter.id_category = c_filter.id_category
                         WHERE cp_filter.id_product = p.id_product
                         AND EXISTS (
                             SELECT 1 FROM ' . _DB_PREFIX_ . 'category c_parent
                             WHERE c_parent.id_category IN (' . $categoryList . ')
                             AND c_filter.nleft >= c_parent.nleft
                             AND c_filter.nright <= c_parent.nright
                         )';

            if ($isExclude) {
                return ' AND NOT EXISTS (' . $subquery . ')';
            } else {
                return ' AND EXISTS (' . $subquery . ')';
            }
        } else {
            // Direct category match only (no subcategories)
            $subquery = 'SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp_filter
                         WHERE cp_filter.id_product = p.id_product
                         AND cp_filter.id_category IN (' . $categoryList . ')';

            if ($isExclude) {
                return ' AND NOT EXISTS (' . $subquery . ')';
            } else {
                return ' AND EXISTS (' . $subquery . ')';
            }
        }
    }

    /**
     * Get a summary of active filters for display
     *
     * @param int $langId Language ID
     * @return string Human-readable summary
     */
    public static function getFilterSummary(int $langId): string
    {
        $config = self::getFilterConfig();
        $parts = [];

        // Categories summary
        if (!empty($config['categories'])) {
            $count = count($config['categories']);
            $mode = $config['mode'] === self::MODE_EXCLUDE ? 'excluded' : 'selected';
            $parts[] = $count . ' ' . ($count === 1 ? 'category' : 'categories') . ' ' . $mode;
        }

        if (empty($parts)) {
            return '';
        }

        return implode(', ', $parts);
    }

    /**
     * Delete all filter configuration values
     *
     * @return bool Success
     */
    public static function deleteFilterConfig(): bool
    {
        $success = true;
        $success = $success && \Configuration::deleteByName(self::CONFIG_FILTER_MODE);
        $success = $success && \Configuration::deleteByName(self::CONFIG_CATEGORIES);
        $success = $success && \Configuration::deleteByName(self::CONFIG_INCLUDE_SUBCATEGORIES);
        return $success;
    }

    /**
     * Check if a specific product should be synced based on current filters
     *
     * @param int $productId Product ID
     * @param int $shopId Shop ID
     * @return bool True if product matches filters and should be synced
     */
    public static function shouldProductBeSynced(int $productId, int $shopId): bool
    {
        $config = self::getFilterConfig();

        // Check category filter
        if (!self::productMatchesCategoryFilter($productId, $shopId, $config)) {
            return false;
        }

        return true;
    }

    /**
     * Check if product matches the configured category filter
     *
     * @param int $productId Product ID
     * @param int $shopId Shop ID
     * @param array $config Filter configuration
     * @return bool True if product matches category filter
     */
    private static function productMatchesCategoryFilter(int $productId, int $shopId, array $config): bool
    {
        // No categories selected = no filter (all products match)
        if (empty($config['categories'])) {
            return true;
        }

        $categoryIds = array_map('intval', $config['categories']);
        $categoryList = implode(',', $categoryIds);
        $isExclude = $config['mode'] === self::MODE_EXCLUDE;

        if ($config['include_subcategories']) {
            // Check with subcategories using nested set model
            $sql = 'SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp
                    INNER JOIN ' . _DB_PREFIX_ . 'category c ON cp.id_category = c.id_category
                    WHERE cp.id_product = ' . (int) $productId . '
                    AND EXISTS (
                        SELECT 1 FROM ' . _DB_PREFIX_ . 'category c_parent
                        WHERE c_parent.id_category IN (' . $categoryList . ')
                        AND c.nleft >= c_parent.nleft
                        AND c.nright <= c_parent.nright
                    )
                    LIMIT 1';
        } else {
            // Direct category match only
            $sql = 'SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp
                    WHERE cp.id_product = ' . (int) $productId . '
                    AND cp.id_category IN (' . $categoryList . ')
                    LIMIT 1';
        }

        $isInCategories = (bool) \Db::getInstance()->getValue($sql);

        // In exclude mode: product should NOT be in the selected categories
        // In include mode: product should BE in the selected categories
        if ($isExclude) {
            return !$isInCategories;
        } else {
            return $isInCategories;
        }
    }
}
