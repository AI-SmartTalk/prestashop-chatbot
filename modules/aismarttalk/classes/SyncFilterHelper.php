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
    const CONFIG_PRODUCT_TYPES = 'AI_SMART_TALK_SYNC_PRODUCT_TYPES';

    /** Filter modes */
    const MODE_INCLUDE = 'include';
    const MODE_EXCLUDE = 'exclude';

    /** Product types */
    const TYPE_STANDARD = 'standard';
    const TYPE_VIRTUAL = 'virtual';
    const TYPE_PACK = 'pack';

    /**
     * Get the category tree with product counts
     *
     * @param int $langId Language ID
     * @param int $shopId Shop ID
     * @return array Hierarchical array of categories
     */
    public static function getCategoryTree(int $langId, int $shopId): array
    {
        $sql = 'SELECT c.id_category, c.id_parent, c.nleft, c.nright, c.level_depth, cl.name,
                       COUNT(DISTINCT cp.id_product) as product_count
                FROM ' . _DB_PREFIX_ . 'category c
                INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl ON c.id_category = cl.id_category AND cl.id_lang = ' . (int) $langId . '
                INNER JOIN ' . _DB_PREFIX_ . 'category_shop cs ON c.id_category = cs.id_category AND cs.id_shop = ' . (int) $shopId . '
                LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp ON c.id_category = cp.id_category
                LEFT JOIN ' . _DB_PREFIX_ . 'product_shop ps ON cp.id_product = ps.id_product AND ps.id_shop = ' . (int) $shopId . ' AND ps.active = 1
                WHERE c.active = 1
                GROUP BY c.id_category
                ORDER BY c.level_depth ASC, c.position ASC';

        $categories = \Db::getInstance()->executeS($sql);

        if (!$categories) {
            return [];
        }

        return self::buildCategoryHierarchy($categories);
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
     * Get product counts by type (standard, virtual, pack)
     *
     * @param int $shopId Shop ID
     * @return array Counts keyed by type
     */
    public static function getProductTypeCounts(int $shopId): array
    {
        $counts = [
            self::TYPE_STANDARD => 0,
            self::TYPE_VIRTUAL => 0,
            self::TYPE_PACK => 0,
        ];

        // Standard products (not virtual, not pack)
        $sql = 'SELECT COUNT(p.id_product) as count
                FROM ' . _DB_PREFIX_ . 'product p
                INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = ' . (int) $shopId . '
                LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON p.id_product = sa.id_product
                    AND sa.id_product_attribute = 0
                    AND sa.id_shop = ' . (int) $shopId . '
                WHERE ps.active = 1 AND p.is_virtual = 0 AND p.cache_is_pack = 0
                    AND COALESCE(sa.quantity, 0) > 0';
        $result = \Db::getInstance()->getRow($sql);
        $counts[self::TYPE_STANDARD] = (int) ($result['count'] ?? 0);

        // Virtual products
        $sql = 'SELECT COUNT(p.id_product) as count
                FROM ' . _DB_PREFIX_ . 'product p
                INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = ' . (int) $shopId . '
                LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON p.id_product = sa.id_product
                    AND sa.id_product_attribute = 0
                    AND sa.id_shop = ' . (int) $shopId . '
                WHERE ps.active = 1 AND p.is_virtual = 1
                    AND COALESCE(sa.quantity, 0) > 0';
        $result = \Db::getInstance()->getRow($sql);
        $counts[self::TYPE_VIRTUAL] = (int) ($result['count'] ?? 0);

        // Pack products
        $sql = 'SELECT COUNT(p.id_product) as count
                FROM ' . _DB_PREFIX_ . 'product p
                INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = ' . (int) $shopId . '
                LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON p.id_product = sa.id_product
                    AND sa.id_product_attribute = 0
                    AND sa.id_shop = ' . (int) $shopId . '
                WHERE ps.active = 1 AND p.cache_is_pack = 1
                    AND COALESCE(sa.quantity, 0) > 0';
        $result = \Db::getInstance()->getRow($sql);
        $counts[self::TYPE_PACK] = (int) ($result['count'] ?? 0);

        return $counts;
    }

    /**
     * Get the current filter configuration
     *
     * @return array Filter configuration
     */
    public static function getFilterConfig(): array
    {
        $config = [
            'mode' => \Configuration::get(self::CONFIG_FILTER_MODE) ?: self::MODE_INCLUDE,
            'categories' => [],
            'include_subcategories' => (bool) \Configuration::get(self::CONFIG_INCLUDE_SUBCATEGORIES),
            'product_types' => [self::TYPE_STANDARD, self::TYPE_VIRTUAL, self::TYPE_PACK],
        ];

        // Decode categories JSON
        $categoriesJson = \Configuration::get(self::CONFIG_CATEGORIES);
        if (!empty($categoriesJson)) {
            $decoded = json_decode($categoriesJson, true);
            if (is_array($decoded)) {
                $config['categories'] = array_map('intval', $decoded);
            }
        }

        // Decode product types JSON
        $typesJson = \Configuration::get(self::CONFIG_PRODUCT_TYPES);
        if (!empty($typesJson)) {
            $decoded = json_decode($typesJson, true);
            if (is_array($decoded)) {
                $validTypes = [self::TYPE_STANDARD, self::TYPE_VIRTUAL, self::TYPE_PACK];
                $config['product_types'] = array_intersect($decoded, $validTypes);
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
        $success = $success && \Configuration::updateValue(self::CONFIG_FILTER_MODE, $mode);

        // Validate and save categories
        $categories = [];
        if (isset($config['categories']) && is_array($config['categories'])) {
            $categories = array_map('intval', array_filter($config['categories'], function ($id) {
                return (int) $id > 0;
            }));
        }
        $success = $success && \Configuration::updateValue(
            self::CONFIG_CATEGORIES,
            json_encode(array_values($categories))
        );

        // Save include subcategories flag
        $includeSubcategories = !empty($config['include_subcategories']);
        $success = $success && \Configuration::updateValue(
            self::CONFIG_INCLUDE_SUBCATEGORIES,
            $includeSubcategories ? '1' : '0'
        );

        // Validate and save product types
        $validTypes = [self::TYPE_STANDARD, self::TYPE_VIRTUAL, self::TYPE_PACK];
        $productTypes = isset($config['product_types']) && is_array($config['product_types'])
            ? array_intersect($config['product_types'], $validTypes)
            : $validTypes;
        $success = $success && \Configuration::updateValue(
            self::CONFIG_PRODUCT_TYPES,
            json_encode(array_values($productTypes))
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

        // Check if not all product types are selected
        $allTypes = [self::TYPE_STANDARD, self::TYPE_VIRTUAL, self::TYPE_PACK];
        if (count($config['product_types']) < count($allTypes)) {
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
     * Build SQL WHERE clause for product type filtering
     *
     * @return string SQL WHERE clause (empty if no filter)
     */
    public static function buildProductTypeFilterSQL(): string
    {
        $config = self::getFilterConfig();
        $types = $config['product_types'];

        // All types selected = no filter needed
        $allTypes = [self::TYPE_STANDARD, self::TYPE_VIRTUAL, self::TYPE_PACK];
        if (count(array_intersect($types, $allTypes)) === count($allTypes)) {
            return '';
        }

        // No types selected = return nothing
        if (empty($types)) {
            return ' AND 1 = 0';
        }

        $conditions = [];

        if (in_array(self::TYPE_STANDARD, $types)) {
            $conditions[] = '(p.is_virtual = 0 AND p.cache_is_pack = 0)';
        }

        if (in_array(self::TYPE_VIRTUAL, $types)) {
            $conditions[] = '(p.is_virtual = 1)';
        }

        if (in_array(self::TYPE_PACK, $types)) {
            $conditions[] = '(p.cache_is_pack = 1)';
        }

        if (empty($conditions)) {
            return ' AND 1 = 0';
        }

        return ' AND (' . implode(' OR ', $conditions) . ')';
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

        // Product types summary
        $allTypes = [self::TYPE_STANDARD, self::TYPE_VIRTUAL, self::TYPE_PACK];
        $selectedTypes = $config['product_types'];
        if (count($selectedTypes) < count($allTypes)) {
            $parts[] = count($selectedTypes) . ' ' . (count($selectedTypes) === 1 ? 'type' : 'types');
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
        $success = $success && \Configuration::deleteByName(self::CONFIG_PRODUCT_TYPES);
        return $success;
    }
}
