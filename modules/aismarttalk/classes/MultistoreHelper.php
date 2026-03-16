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
 * Helper class for multistore-aware operations.
 * Compatible with PrestaShop 1.7, 8, and 9.
 *
 * ALL module configuration is GLOBAL (same regardless of which shop is selected in BO).
 * The sync collects products from ALL shops (union, deduplicated).
 */
class MultistoreHelper
{
    /**
     * Check if multistore feature is active
     *
     * @return bool
     */
    public static function isMultistoreActive(): bool
    {
        return \Shop::isFeatureActive();
    }

    /**
     * Get the default shop ID
     *
     * @return int
     */
    public static function getDefaultShopId(): int
    {
        return (int) \Configuration::get('PS_SHOP_DEFAULT');
    }

    /**
     * Get all active shop IDs, default shop first.
     *
     * @return array Array of shop IDs
     */
    public static function getAllShopIds(): array
    {
        if (!self::isMultistoreActive()) {
            return [(int) \Context::getContext()->shop->id];
        }

        $shops = \Shop::getShops(true);
        $defaultShopId = self::getDefaultShopId();
        $ids = [];
        $hasDefault = false;

        foreach ($shops as $shop) {
            $id = (int) $shop['id_shop'];
            if ($id === $defaultShopId) {
                $hasDefault = true;
            } else {
                $ids[] = $id;
            }
        }

        sort($ids);
        if ($hasDefault) {
            array_unshift($ids, $defaultShopId);
        }

        return $ids;
    }

    /**
     * Check if a product is active and in stock in at least one shop.
     *
     * @param int $idProduct
     * @return bool
     */
    public static function isProductActiveInAnyShop(int $idProduct): bool
    {
        $shopIds = self::getAllShopIds();

        foreach ($shopIds as $shopId) {
            if (self::isProductActiveInShop($idProduct, $shopId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a product is active and in stock in a specific shop.
     *
     * @param int $idProduct
     * @param int $idShop
     * @return bool
     */
    public static function isProductActiveInShop(int $idProduct, int $idShop): bool
    {
        $shopGroupId = (int) \Shop::getGroupFromShop($idShop);

        $sql = 'SELECT 1
                FROM ' . _DB_PREFIX_ . 'product_shop ps
                LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa
                    ON ps.id_product = sa.id_product
                    AND sa.id_product_attribute = 0
                    AND (sa.id_shop = ' . (int) $idShop . '
                         OR (sa.id_shop = 0 AND sa.id_shop_group = ' . $shopGroupId . '))
                WHERE ps.id_product = ' . (int) $idProduct . '
                    AND ps.id_shop = ' . (int) $idShop . '
                    AND ps.active = 1
                    AND COALESCE(sa.quantity, 0) > 0
                LIMIT 1';

        return (bool) \Db::getInstance()->getValue($sql);
    }

    /**
     * Get all active shops with their names.
     *
     * @return array Array of ['id_shop' => int, 'name' => string]
     */
    public static function getAllShops(): array
    {
        $shops = \Shop::getShops(true);
        $result = [];
        foreach ($shops as $shop) {
            $result[] = [
                'id_shop' => (int) $shop['id_shop'],
                'name' => $shop['name'],
            ];
        }
        return $result;
    }

    /**
     * Get the chatbot enabled status for each shop.
     * This reads per-shop values (NOT global) since chatbot display IS per-shop.
     *
     * @return array Array of ['id_shop' => int, 'name' => string, 'enabled' => bool]
     */
    public static function getShopsChatbotStatus(): array
    {
        $shops = self::getAllShops();
        $result = [];

        foreach ($shops as $shop) {
            $result[] = [
                'id_shop' => $shop['id_shop'],
                'name' => $shop['name'],
                'enabled' => (bool) \Configuration::get('AI_SMART_TALK_ENABLED', null, null, $shop['id_shop']),
            ];
        }

        return $result;
    }

    /**
     * Save the chatbot enabled status for specific shops.
     * Writes per-shop values since chatbot display IS per-shop.
     *
     * @param array $enabledShopIds Array of shop IDs where chatbot should be enabled
     * @return bool
     */
    public static function saveShopsChatbotStatus(array $enabledShopIds): bool
    {
        $shops = self::getAllShops();
        $success = true;

        foreach ($shops as $shop) {
            $enabled = in_array($shop['id_shop'], $enabledShopIds);
            $success = $success && \Configuration::updateValue(
                'AI_SMART_TALK_ENABLED',
                $enabled,
                false,
                null,
                $shop['id_shop']
            );
        }

        return $success;
    }

    // =========================================================================
    // Global Configuration helpers
    // =========================================================================
    // In multistore, PrestaShop scopes Configuration::get/updateValue to the
    // current shop context. AI SmartTalk configuration must always be GLOBAL
    // (same regardless of which shop the admin has selected).
    // These wrappers ensure we always read/write at global scope.
    // =========================================================================

    /**
     * Read a module configuration value at global scope (ignoring shop context).
     *
     * @param string $key Configuration key
     * @return string|false
     */
    public static function getConfig(string $key)
    {
        if (!self::isMultistoreActive()) {
            return \Configuration::get($key);
        }

        return \Configuration::get($key, null, 0, 0);
    }

    /**
     * Write a module configuration value at global scope (ignoring shop context).
     *
     * @param string $key Configuration key
     * @param mixed $value Value
     * @param bool $html Is HTML content
     * @return bool
     */
    public static function updateConfig(string $key, $value, bool $html = false): bool
    {
        if (!self::isMultistoreActive()) {
            return \Configuration::updateValue($key, $value, $html);
        }

        return \Configuration::updateValue($key, $value, $html, 0, 0);
    }

    /**
     * Delete a module configuration value across all scopes.
     *
     * @param string $key Configuration key
     * @return bool
     */
    public static function deleteConfig(string $key): bool
    {
        return \Configuration::deleteByName($key);
    }
}
