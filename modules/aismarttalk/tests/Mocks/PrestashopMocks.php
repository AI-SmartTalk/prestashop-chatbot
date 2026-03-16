<?php
/**
 * Mock implementations of PrestaShop core classes for unit testing.
 *
 * These mocks provide just enough behavior to test AI SmartTalk module logic
 * without requiring a full PrestaShop installation or database.
 *
 * Usage: Each test can configure mock behavior via static properties.
 * Example: MockConfiguration::$store['KEY'] = 'value';
 */

// =========================================================================
// Configuration mock
// =========================================================================

class Configuration
{
    /** @var array Simulated global config store (id_shop=0) */
    public static $globalStore = [];

    /** @var array Simulated per-shop config store: [id_shop => [key => value]] */
    public static $shopStore = [];

    /**
     * Reset all stored values (call in setUp)
     */
    public static function reset(): void
    {
        self::$globalStore = [];
        self::$shopStore = [];
    }

    /**
     * Mock Configuration::get()
     * When $idShop is 0 or null: reads from globalStore
     * When $idShop > 0: reads from shopStore, falls back to globalStore
     */
    public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null)
    {
        // Explicit global read
        if ($idShop === 0 || ($idShopGroup === 0 && $idShop === 0)) {
            return self::$globalStore[$key] ?? false;
        }

        // Per-shop read with fallback
        if ($idShop !== null && $idShop > 0) {
            if (isset(self::$shopStore[$idShop][$key])) {
                return self::$shopStore[$idShop][$key];
            }
            return self::$globalStore[$key] ?? false;
        }

        // Context-based read (simulate current shop)
        $contextShopId = Shop::getContextShopID();
        if ($contextShopId > 0 && Shop::getContext() === Shop::CONTEXT_SHOP) {
            if (isset(self::$shopStore[$contextShopId][$key])) {
                return self::$shopStore[$contextShopId][$key];
            }
        }

        return self::$globalStore[$key] ?? false;
    }

    /**
     * Mock Configuration::getGlobalValue()
     */
    public static function getGlobalValue($key)
    {
        return self::$globalStore[$key] ?? false;
    }

    /**
     * Mock Configuration::updateValue()
     * In CONTEXT_ALL: writes to global
     * In CONTEXT_SHOP: writes to current shop
     * With explicit $idShop: writes to that shop
     */
    public static function updateValue($key, $value, $html = false, $idShopGroup = null, $idShop = null)
    {
        // Explicit global write
        if (($idShopGroup === 0 && $idShop === 0) || Shop::getContext() === Shop::CONTEXT_ALL) {
            self::$globalStore[$key] = $value;
            return true;
        }

        // Explicit per-shop write
        if ($idShop !== null && $idShop > 0) {
            if (!isset(self::$shopStore[$idShop])) {
                self::$shopStore[$idShop] = [];
            }
            self::$shopStore[$idShop][$key] = $value;
            return true;
        }

        // Context-based write
        $contextShopId = Shop::getContextShopID();
        if ($contextShopId > 0 && Shop::getContext() === Shop::CONTEXT_SHOP) {
            if (!isset(self::$shopStore[$contextShopId])) {
                self::$shopStore[$contextShopId] = [];
            }
            self::$shopStore[$contextShopId][$key] = $value;
            return true;
        }

        // Fallback: global
        self::$globalStore[$key] = $value;
        return true;
    }

    /**
     * Mock Configuration::deleteByName()
     */
    public static function deleteByName($key)
    {
        unset(self::$globalStore[$key]);
        foreach (self::$shopStore as $shopId => &$store) {
            unset($store[$key]);
        }
        return true;
    }
}

// =========================================================================
// Shop mock
// =========================================================================

class Shop
{
    const CONTEXT_ALL = 1;
    const CONTEXT_GROUP = 2;
    const CONTEXT_SHOP = 4;

    /** @var int Current context */
    private static $context = self::CONTEXT_SHOP;

    /** @var int|null Current context shop ID */
    private static $contextShopId = 1;

    /** @var bool Multistore feature active */
    private static $featureActive = false;

    /** @var array List of shops: [id_shop => ['id_shop' => X, 'name' => Y]] */
    private static $shops = [
        1 => ['id_shop' => 1, 'name' => 'Default Shop', 'id_category' => 2],
    ];

    /** @var array Shop group map: [id_shop => id_shop_group] */
    private static $shopGroups = [1 => 1];

    /** @var int Root category ID (set per-instance) */
    public $id_category = 2;

    public function __construct($id = null)
    {
        if ($id !== null && isset(self::$shops[$id])) {
            $this->id_category = self::$shops[$id]['id_category'] ?? 2;
        }
    }

    public static function reset(): void
    {
        self::$context = self::CONTEXT_SHOP;
        self::$contextShopId = 1;
        self::$featureActive = false;
        self::$shops = [
            1 => ['id_shop' => 1, 'name' => 'Default Shop', 'id_category' => 2],
        ];
        self::$shopGroups = [1 => 1];
    }

    public static function isFeatureActive(): bool
    {
        return self::$featureActive;
    }

    public static function setFeatureActive(bool $active): void
    {
        self::$featureActive = $active;
    }

    public static function getContext(): int
    {
        return self::$context;
    }

    public static function setContext(int $context, $shopId = null): void
    {
        self::$context = $context;
        if ($shopId !== null) {
            self::$contextShopId = (int) $shopId;
        }
    }

    public static function getContextShopID(): int
    {
        return self::$contextShopId ?? 1;
    }

    /**
     * @param bool $active Only active shops
     * @return array
     */
    public static function getShops($active = true): array
    {
        return self::$shops;
    }

    public static function setShops(array $shops): void
    {
        self::$shops = $shops;
    }

    public static function getGroupFromShop($idShop): int
    {
        return self::$shopGroups[$idShop] ?? 1;
    }

    public static function setShopGroups(array $groups): void
    {
        self::$shopGroups = $groups;
    }
}

// =========================================================================
// Context mock
// =========================================================================

class Context
{
    /** @var self */
    private static $instance;

    /** @var object */
    public $shop;

    /** @var object */
    public $language;

    public function __construct()
    {
        $this->shop = new \stdClass();
        $this->shop->id = 1;
        $this->shop->name = 'Default Shop';

        $this->language = new \stdClass();
        $this->language->id = 1;
        $this->language->iso_code = 'en';
    }

    public static function getContext(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}

// =========================================================================
// Db mock
// =========================================================================

class Db
{
    /** @var array Queue of results for executeS() */
    public static $executeSResults = [];

    /** @var array Queue of results for getValue() */
    public static $getValueResults = [];

    /** @var array Log of executed SQL queries */
    public static $executedQueries = [];

    private static $instance;

    public static function reset(): void
    {
        self::$executeSResults = [];
        self::$getValueResults = [];
        self::$executedQueries = [];
        self::$instance = null;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function executeS($sql)
    {
        self::$executedQueries[] = $sql;
        return array_shift(self::$executeSResults) ?: [];
    }

    public function getValue($sql)
    {
        self::$executedQueries[] = $sql;
        return array_shift(self::$getValueResults);
    }

    public function execute($sql)
    {
        self::$executedQueries[] = $sql;
        return true;
    }
}

// =========================================================================
// DbQuery mock (minimal)
// =========================================================================

class DbQuery
{
    private $parts = [];

    public function select($sql) { $this->parts['select'] = $sql; return $this; }
    public function from($table, $alias = null) { $this->parts['from'] = $table; return $this; }
    public function where($sql) { $this->parts['where'][] = $sql; return $this; }
    public function leftJoin($table, $alias, $on) { return $this; }

    public function __toString()
    {
        return 'SELECT ' . ($this->parts['select'] ?? '*') . ' FROM ' . _DB_PREFIX_ . ($this->parts['from'] ?? '');
    }
}

// =========================================================================
// Other PrestaShop stubs
// =========================================================================

class ObjectModel
{
    const TYPE_INT = 1;
    const TYPE_BOOL = 2;
    const TYPE_STRING = 3;
    const TYPE_DATE = 5;

    public $id;

    public function save() { return true; }
    public function delete() { return true; }
}

class Module
{
    public $name;
    public $version;
    public $bootstrap;

    public function l($string) { return $string; }
}

class Tools
{
    private static $values = [];

    public static function reset(): void { self::$values = []; }
    public static function setValue(string $key, $value): void { self::$values[$key] = $value; }

    public static function getValue($key, $default = false)
    {
        return self::$values[$key] ?? $default;
    }

    public static function isSubmit($key): bool
    {
        return isset(self::$values[$key]);
    }

    public static function redirect($url) {}
    public static function getAdminTokenLite($controller) { return 'test_token'; }
}

class Validate
{
    public static function isLoadedObject($object): bool { return true; }
}

class Product
{
    public $id;
    public $active = true;
    public $link_rewrite = ['en' => 'test-product'];

    public function __construct($id = null, $full = false, $idLang = null, $idShop = null)
    {
        $this->id = $id;
    }

    public function getPrice() { return 29.99; }
}

class StockAvailable
{
    public static function getQuantityAvailableByProduct($idProduct, $idAttr = 0)
    {
        return 10;
    }
}

class Currency
{
    public $sign = '€';
    public function __construct($id = null) {}
}

class Customer
{
    public $id;
    public $email = 'test@test.com';
    public $active = true;
    public $newsletter = true;
    public $optin = false;
    public $firstname = 'Test';
    public $lastname = 'User';

    public function __construct($id = null) { $this->id = $id; }
    public static function getCustomers($active = true) { return []; }
    public function getAddresses($idLang = null) { return []; }
}

class PrestaShopLogger
{
    public static $logs = [];

    public static function addLog($message, $severity = 1, $errorCode = null, $objectType = null, $objectId = null, $allowDuplicate = false)
    {
        self::$logs[] = ['message' => $message, 'severity' => $severity];
    }

    public static function reset(): void { self::$logs = []; }
}

class AdminController
{
    public static $currentIndex = '/admin/index.php';
}

class HelperForm
{
    public $module;
    public $name_controller;
    public $token;
    public $currentIndex;
    public $title;
    public $submit_action;
    public $fields_value = [];

    public function generateForm($fields_form) { return ''; }
}

// Utility functions
if (!function_exists('pSQL')) {
    function pSQL($string) { return addslashes($string); }
}
