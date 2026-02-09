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
 * ObjectModel for AI SmartTalk product synchronization tracking.
 * Replaces the previous approach of adding columns to ps_product table.
 */
class AiSmartTalkProductSync extends \ObjectModel
{
    /** @var int Product ID */
    public $id_product;

    /** @var int Shop ID */
    public $id_shop;

    /** @var bool Whether the product has been synchronized */
    public $synced = false;

    /** @var string|null Last synchronization timestamp */
    public $last_sync;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'aismarttalk_product_sync',
        'primary' => 'id_aismarttalk_product_sync',
        'fields' => [
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'synced' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'last_sync' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Get sync record for a product in a specific shop
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return AiSmartTalkProductSync|null
     */
    public static function getByProductId(int $idProduct, ?int $idShop = null): ?self
    {
        if ($idShop === null) {
            $idShop = (int) \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('id_aismarttalk_product_sync');
        $sql->from('aismarttalk_product_sync');
        $sql->where('id_product = ' . (int) $idProduct);
        $sql->where('id_shop = ' . (int) $idShop);

        $id = \Db::getInstance()->getValue($sql);

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * Get or create sync record for a product
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return AiSmartTalkProductSync
     */
    public static function getOrCreate(int $idProduct, ?int $idShop = null): self
    {
        $record = self::getByProductId($idProduct, $idShop);

        if ($record === null) {
            $record = new self();
            $record->id_product = $idProduct;
            $record->id_shop = $idShop ?? (int) \Context::getContext()->shop->id;
            $record->synced = false;
            $record->last_sync = null;
        }

        return $record;
    }

    /**
     * Check if a product is synchronized
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return bool
     */
    public static function isSynced(int $idProduct, ?int $idShop = null): bool
    {
        $record = self::getByProductId($idProduct, $idShop);
        return $record !== null && (bool) $record->synced;
    }

    /**
     * Get last sync time for a product
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return \DateTime|null
     */
    public static function getLastSyncTime(int $idProduct, ?int $idShop = null): ?\DateTime
    {
        $record = self::getByProductId($idProduct, $idShop);

        if ($record === null || empty($record->last_sync)) {
            return null;
        }

        return new \DateTime($record->last_sync);
    }

    /**
     * Mark a product as synchronized
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return bool
     */
    public static function markAsSynced(int $idProduct, ?int $idShop = null): bool
    {
        $record = self::getOrCreate($idProduct, $idShop);
        $record->synced = true;
        $record->last_sync = date('Y-m-d H:i:s');

        return $record->save();
    }

    /**
     * Mark a product as not synchronized
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return bool
     */
    public static function markAsNotSynced(int $idProduct, ?int $idShop = null): bool
    {
        $record = self::getByProductId($idProduct, $idShop);

        if ($record === null) {
            return true;
        }

        $record->synced = false;
        return $record->save();
    }

    /**
     * Mark multiple products as synchronized
     *
     * @param array $productIds
     * @param int|null $idShop
     * @return bool
     */
    public static function markProductsAsSynced(array $productIds, ?int $idShop = null): bool
    {
        if (empty($productIds)) {
            return true;
        }

        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;
        $now = date('Y-m-d H:i:s');

        foreach ($productIds as $idProduct) {
            $record = self::getOrCreate((int) $idProduct, $idShop);
            $record->synced = true;
            $record->last_sync = $now;

            if (!$record->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark multiple products as not synchronized
     *
     * @param array $productIds
     * @param int|null $idShop
     * @return bool
     */
    public static function markProductsAsNotSynced(array $productIds, ?int $idShop = null): bool
    {
        if (empty($productIds)) {
            return true;
        }

        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;
        $ids = implode(',', array_map('intval', $productIds));

        $sql = 'UPDATE `' . _DB_PREFIX_ . 'aismarttalk_product_sync`
                SET `synced` = 0
                WHERE `id_product` IN (' . $ids . ')
                AND `id_shop` = ' . (int) $idShop;

        return \Db::getInstance()->execute($sql);
    }

    /**
     * Update last sync time for a product
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return bool
     */
    public static function updateLastSyncTime(int $idProduct, ?int $idShop = null): bool
    {
        $record = self::getOrCreate($idProduct, $idShop);
        $record->last_sync = date('Y-m-d H:i:s');

        return $record->save();
    }

    /**
     * Check if enough time has passed since last sync (debounce)
     *
     * @param int $idProduct
     * @param int $seconds Minimum seconds between syncs
     * @param int|null $idShop
     * @return bool True if sync is allowed
     */
    public static function canSync(int $idProduct, int $seconds = 3, ?int $idShop = null): bool
    {
        $lastSync = self::getLastSyncTime($idProduct, $idShop);

        if ($lastSync === null) {
            return true;
        }

        $threshold = new \DateTime();
        $threshold->modify("-{$seconds} seconds");

        return $threshold > $lastSync;
    }

    /**
     * Get product IDs that are not synchronized
     *
     * @param int|null $idShop
     * @return array
     */
    public static function getUnsyncedProductIds(?int $idShop = null): array
    {
        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('p.id_product');
        $sql->from('product', 'p');
        $sql->leftJoin('aismarttalk_product_sync', 'aps', 'p.id_product = aps.id_product AND aps.id_shop = ' . (int) $idShop);
        $sql->where('aps.synced IS NULL OR aps.synced = 0');

        $results = \Db::getInstance()->executeS($sql);

        return array_column($results, 'id_product');
    }

    /**
     * Get product IDs that are synchronized
     *
     * @param int|null $idShop
     * @return array
     */
    public static function getSyncedProductIds(?int $idShop = null): array
    {
        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('id_product');
        $sql->from('aismarttalk_product_sync');
        $sql->where('synced = 1');
        $sql->where('id_shop = ' . (int) $idShop);

        $results = \Db::getInstance()->executeS($sql);

        return array_column($results, 'id_product');
    }

    /**
     * Delete sync record for a product
     *
     * @param int $idProduct
     * @param int|null $idShop
     * @return bool
     */
    public static function deleteByProductId(int $idProduct, ?int $idShop = null): bool
    {
        $record = self::getByProductId($idProduct, $idShop);

        if ($record === null) {
            return true;
        }

        return $record->delete();
    }

    /**
     * Delete all sync records (used during uninstall)
     *
     * @return bool
     */
    public static function deleteAll(): bool
    {
        return \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'aismarttalk_product_sync`');
    }

    /**
     * Create the database table
     *
     * @return bool
     */
    public static function createTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'aismarttalk_product_sync` (
            `id_aismarttalk_product_sync` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_product` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
            `synced` TINYINT(1) NOT NULL DEFAULT 0,
            `last_sync` DATETIME NULL,
            PRIMARY KEY (`id_aismarttalk_product_sync`),
            UNIQUE KEY `product_shop` (`id_product`, `id_shop`),
            INDEX `idx_synced` (`synced`),
            INDEX `idx_last_sync` (`last_sync`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return \Db::getInstance()->execute($sql);
    }

    /**
     * Drop the database table
     *
     * @return bool
     */
    public static function dropTable(): bool
    {
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'aismarttalk_product_sync`');
    }

    /**
     * Migrate data from legacy ps_product columns to new table
     *
     * @return bool
     */
    public static function migrateFromLegacyColumns(): bool
    {
        $db = \Db::getInstance();

        // Check if legacy columns exist
        $tableName = _DB_PREFIX_ . 'product';
        $hasSynchColumn = !empty($db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_synch'"));
        $hasLastSourceColumn = !empty($db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_last_source'"));

        if (!$hasSynchColumn && !$hasLastSourceColumn) {
            return true;
        }

        // Get current shop ID
        $idShop = (int) \Context::getContext()->shop->id;

        // Migrate data from legacy columns
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'aismarttalk_product_sync`
                (`id_product`, `id_shop`, `synced`, `last_sync`)
                SELECT
                    p.id_product,
                    ' . $idShop . ',
                    ' . ($hasSynchColumn ? 'COALESCE(p.aismarttalk_synch, 0)' : '0') . ',
                    ' . ($hasLastSourceColumn ? 'p.aismarttalk_last_source' : 'NULL') . '
                FROM `' . _DB_PREFIX_ . 'product` p
                WHERE ' . ($hasSynchColumn ? 'p.aismarttalk_synch = 1' : '1=1') . '
                ON DUPLICATE KEY UPDATE
                    `synced` = VALUES(`synced`),
                    `last_sync` = VALUES(`last_sync`)';

        return $db->execute($sql);
    }

    /**
     * Remove legacy columns from ps_product table
     *
     * @return bool
     */
    public static function removeLegacyColumns(): bool
    {
        $db = \Db::getInstance();
        $tableName = _DB_PREFIX_ . 'product';

        // Check and remove aismarttalk_synch column
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_synch'");
        if (!empty($result)) {
            $db->execute("ALTER TABLE `$tableName` DROP COLUMN `aismarttalk_synch`");
        }

        // Check and remove aismarttalk_last_source column
        $result = $db->executeS("SHOW COLUMNS FROM `$tableName` LIKE 'aismarttalk_last_source'");
        if (!empty($result)) {
            $db->execute("ALTER TABLE `$tableName` DROP COLUMN `aismarttalk_last_source`");
        }

        return true;
    }
}
