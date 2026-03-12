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
 * ObjectModel for AI SmartTalk customer synchronization tracking.
 * Mirrors AiSmartTalkProductSync pattern for consistency.
 */
class AiSmartTalkCustomerSync extends \ObjectModel
{
    /** @var int Customer ID */
    public $id_customer;

    /** @var int Shop ID */
    public $id_shop;

    /** @var bool Whether the customer has been synchronized */
    public $synced = false;

    /** @var string|null Last synchronization timestamp */
    public $last_sync;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'aismarttalk_customer_sync',
        'primary' => 'id_aismarttalk_customer_sync',
        'fields' => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'synced' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'last_sync' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Get sync record for a customer in a specific shop
     *
     * @param int $idCustomer
     * @param int|null $idShop
     * @return self|null
     */
    public static function getByCustomerId(int $idCustomer, ?int $idShop = null): ?self
    {
        if ($idShop === null) {
            $idShop = (int) \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('id_aismarttalk_customer_sync');
        $sql->from('aismarttalk_customer_sync');
        $sql->where('id_customer = ' . (int) $idCustomer);
        $sql->where('id_shop = ' . (int) $idShop);

        $id = \Db::getInstance()->getValue($sql);

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * Get or create sync record for a customer
     *
     * @param int $idCustomer
     * @param int|null $idShop
     * @return self
     */
    public static function getOrCreate(int $idCustomer, ?int $idShop = null): self
    {
        $record = self::getByCustomerId($idCustomer, $idShop);

        if ($record === null) {
            $record = new self();
            $record->id_customer = $idCustomer;
            $record->id_shop = $idShop ?? (int) \Context::getContext()->shop->id;
            $record->synced = false;
            $record->last_sync = null;
        }

        return $record;
    }

    /**
     * Mark a customer as synchronized
     *
     * @param int $idCustomer
     * @param int|null $idShop
     * @return bool
     */
    public static function markAsSynced(int $idCustomer, ?int $idShop = null): bool
    {
        $record = self::getOrCreate($idCustomer, $idShop);
        $record->synced = true;
        $record->last_sync = date('Y-m-d H:i:s');

        return $record->save();
    }

    /**
     * Mark a customer as not synchronized
     *
     * @param int $idCustomer
     * @param int|null $idShop
     * @return bool
     */
    public static function markAsNotSynced(int $idCustomer, ?int $idShop = null): bool
    {
        $record = self::getByCustomerId($idCustomer, $idShop);

        if ($record === null) {
            return true;
        }

        $record->synced = false;
        return $record->save();
    }

    /**
     * Check if enough time has passed since last sync (debounce)
     *
     * @param int $idCustomer
     * @param int $seconds Minimum seconds between syncs
     * @param int|null $idShop
     * @return bool True if sync is allowed
     */
    public static function canSync(int $idCustomer, int $seconds = 3, ?int $idShop = null): bool
    {
        $record = self::getByCustomerId($idCustomer, $idShop);

        if ($record === null || empty($record->last_sync)) {
            return true;
        }

        $lastSync = new \DateTime($record->last_sync);
        $threshold = new \DateTime();
        $threshold->modify("-{$seconds} seconds");

        return $threshold > $lastSync;
    }

    /**
     * Get customer IDs that are currently marked as synced
     *
     * @param int|null $idShop
     * @return array
     */
    public static function getSyncedCustomerIds(?int $idShop = null): array
    {
        $idShop = $idShop ?? (int) \Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('id_customer');
        $sql->from('aismarttalk_customer_sync');
        $sql->where('synced = 1');
        $sql->where('id_shop = ' . (int) $idShop);

        $results = \Db::getInstance()->executeS($sql);

        return $results ? array_column($results, 'id_customer') : [];
    }

    /**
     * Delete sync record for a customer
     *
     * @param int $idCustomer
     * @param int|null $idShop
     * @return bool
     */
    public static function deleteByCustomerId(int $idCustomer, ?int $idShop = null): bool
    {
        $record = self::getByCustomerId($idCustomer, $idShop);

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
        return \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'aismarttalk_customer_sync`');
    }

    /**
     * Create the database table
     *
     * @return bool
     */
    public static function createTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'aismarttalk_customer_sync` (
            `id_aismarttalk_customer_sync` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_customer` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
            `synced` TINYINT(1) NOT NULL DEFAULT 0,
            `last_sync` DATETIME NULL,
            PRIMARY KEY (`id_aismarttalk_customer_sync`),
            UNIQUE KEY `customer_shop` (`id_customer`, `id_shop`),
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
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'aismarttalk_customer_sync`');
    }
}
