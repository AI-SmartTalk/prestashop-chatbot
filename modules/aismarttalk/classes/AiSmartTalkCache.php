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
 * Simple cache system using PrestaShop's Configuration table.
 * Stores JSON data with expiration timestamps.
 */
class AiSmartTalkCache
{
    /** @var int Default TTL in seconds (1 hour) */
    const DEFAULT_TTL = 3600;

    /** @var string Prefix for cache keys */
    const CACHE_PREFIX = 'AI_SMART_TALK_CACHE_';

    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param int|null $idShop Shop ID (null for current shop)
     * @return mixed|null Cached data or null if expired/not found
     */
    public static function get(string $key, ?int $idShop = null)
    {
        $cacheKey = self::CACHE_PREFIX . strtoupper($key);
        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;

        $cached = \Configuration::get($cacheKey, null, null, $idShop);

        if (empty($cached)) {
            return null;
        }

        $data = json_decode($cached, true);

        if (!$data || !isset($data['expires_at']) || !isset($data['value'])) {
            return null;
        }

        // Check if expired
        if (time() > $data['expires_at']) {
            self::delete($key, $idShop);
            return null;
        }

        return $data['value'];
    }

    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed $value Data to cache (will be JSON encoded)
     * @param int $ttl Time to live in seconds
     * @param int|null $idShop Shop ID (null for current shop)
     * @return bool Success
     */
    public static function set(string $key, $value, int $ttl = self::DEFAULT_TTL, ?int $idShop = null): bool
    {
        $cacheKey = self::CACHE_PREFIX . strtoupper($key);
        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;

        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
        ];

        return \Configuration::updateValue($cacheKey, json_encode($data), false, null, $idShop);
    }

    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @param int|null $idShop Shop ID (null for current shop)
     * @return bool Success
     */
    public static function delete(string $key, ?int $idShop = null): bool
    {
        $cacheKey = self::CACHE_PREFIX . strtoupper($key);
        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;

        // Delete for specific shop
        return \Configuration::deleteByName($cacheKey);
    }

    /**
     * Clear all AI SmartTalk cache entries
     *
     * @return bool Success
     */
    public static function clearAll(): bool
    {
        $db = \Db::getInstance();
        $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'configuration`
                WHERE `name` LIKE \'' . pSQL(self::CACHE_PREFIX) . '%\'';

        return $db->execute($sql);
    }

    /**
     * Check if cache entry exists and is valid
     *
     * @param string $key Cache key
     * @param int|null $idShop Shop ID
     * @return bool True if valid cache exists
     */
    public static function has(string $key, ?int $idShop = null): bool
    {
        return self::get($key, $idShop) !== null;
    }

    /**
     * Get cache metadata (created_at, expires_at) without returning value
     *
     * @param string $key Cache key
     * @param int|null $idShop Shop ID
     * @return array|null Metadata or null
     */
    public static function getMetadata(string $key, ?int $idShop = null): ?array
    {
        $cacheKey = self::CACHE_PREFIX . strtoupper($key);
        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;

        $cached = \Configuration::get($cacheKey, null, null, $idShop);

        if (empty($cached)) {
            return null;
        }

        $data = json_decode($cached, true);

        if (!$data || !isset($data['expires_at'])) {
            return null;
        }

        return [
            'created_at' => $data['created_at'] ?? null,
            'expires_at' => $data['expires_at'],
            'is_expired' => time() > $data['expires_at'],
            'ttl_remaining' => max(0, $data['expires_at'] - time()),
        ];
    }
}
