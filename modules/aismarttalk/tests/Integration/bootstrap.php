<?php
/**
 * Integration test bootstrap.
 *
 * Connects to a real MySQL database (Docker), loads the schema,
 * and provides a thin PrestaShop compatibility layer so module classes
 * can execute their actual SQL queries against real data.
 */

// ──────────────────────────────────────────────
// 1. DB connection
// ──────────────────────────────────────────────
$dbHost = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('TEST_DB_PORT') ?: '3399';
$dbName = getenv('TEST_DB_NAME') ?: 'ps_test';
$dbUser = getenv('TEST_DB_USER') ?: 'test';
$dbPass = getenv('TEST_DB_PASS') ?: 'test';

$dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";

// Retry loop: DB container may still be starting
$maxRetries = 30;
$pdo = null;
for ($i = 0; $i < $maxRetries; $i++) {
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Match PrestaShop's MySQL config (no strict mode)
        $pdo->exec("SET sql_mode = ''");
        break;
    } catch (PDOException $e) {
        if ($i === $maxRetries - 1) {
            throw new RuntimeException(
                "Cannot connect to test DB at $dbHost:$dbPort after $maxRetries attempts.\n"
                . "Start it with: docker compose -f docker-compose.test.yml up -d\n"
                . "Error: " . $e->getMessage()
            );
        }
        sleep(1);
    }
}

// Store PDO globally for tests
$GLOBALS['test_pdo'] = $pdo;
$GLOBALS['test_schema_dir'] = __DIR__;

/**
 * Load a seed file into the test database (drops + recreates all tables).
 * Call from setUp() or setUpBeforeClass() to switch test data.
 *
 * @param string $seedFile Filename in tests/Integration/ (e.g. 'seed.sql', 'seed_single_shop.sql')
 */
function loadTestSeed(string $seedFile = 'seed.sql'): void
{
    $pdo = $GLOBALS['test_pdo'];
    $dir = $GLOBALS['test_schema_dir'];

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $pdo->exec(file_get_contents($dir . '/schema.sql'));
    $pdo->exec(file_get_contents($dir . '/' . $seedFile));

    // Reset Db singleton so it picks up fresh state
    Db::reset();
}

// Load default seed (multistore) for backward compatibility
loadTestSeed('seed.sql');

// ──────────────────────────────────────────────
// 3. PrestaShop compatibility layer (real DB)
// ──────────────────────────────────────────────
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}

/**
 * Real Db adapter backed by PDO.
 * Only implements the methods used by our module classes.
 *
 * Set TEST_SQL_LOG=1 to print every SQL query to stderr during tests.
 * Set TEST_SQL_LOG=2 to also print result counts and values.
 */
class Db
{
    private static $instance;
    private $pdo;

    /** @var int 0=off, 1=queries, 2=queries+results */
    private static $logLevel = 0;

    /** @var int Query counter */
    private static $queryCount = 0;

    public function __construct()
    {
        $this->pdo = $GLOBALS['test_pdo'];
        self::$logLevel = (int) (getenv('TEST_SQL_LOG') ?: 0);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
        self::$queryCount = 0;
    }

    public static function getQueryCount(): int
    {
        return self::$queryCount;
    }

    private function log(string $method, string $sql, $result = null): void
    {
        if (self::$logLevel < 1) {
            return;
        }

        self::$queryCount++;
        $num = self::$queryCount;
        $shortSql = trim(preg_replace('/\s+/', ' ', $sql));
        if (strlen($shortSql) > 200) {
            $shortSql = substr($shortSql, 0, 200) . '…';
        }

        fwrite(STDERR, "\n  \033[36m[$num] $method:\033[0m $shortSql\n");

        if (self::$logLevel >= 2 && $result !== null) {
            if (is_array($result)) {
                $count = count($result);
                fwrite(STDERR, "  \033[33m   → $count row(s)\033[0m");
                if ($count > 0 && $count <= 5) {
                    foreach ($result as $row) {
                        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (strlen($line) > 120) {
                            $line = substr($line, 0, 120) . '…';
                        }
                        fwrite(STDERR, "\n  \033[90m     $line\033[0m");
                    }
                } elseif ($count > 5) {
                    fwrite(STDERR, " (showing first 3)");
                    for ($i = 0; $i < 3; $i++) {
                        $line = json_encode($result[$i], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (strlen($line) > 120) {
                            $line = substr($line, 0, 120) . '…';
                        }
                        fwrite(STDERR, "\n  \033[90m     $line\033[0m");
                    }
                }
                fwrite(STDERR, "\n");
            } else {
                fwrite(STDERR, "  \033[33m   → " . var_export($result, true) . "\033[0m\n");
            }
        }
    }

    public function executeS($sql)
    {
        try {
            $stmt = $this->pdo->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->log('executeS', $sql, $result);
            return $result;
        } catch (PDOException $e) {
            throw new RuntimeException("SQL Error in executeS:\n$sql\n\n" . $e->getMessage());
        }
    }

    public function getValue($sql)
    {
        try {
            $stmt = $this->pdo->query($sql);
            $result = $stmt->fetchColumn();
            $val = $result !== false ? $result : false;
            $this->log('getValue', $sql, $val);
            return $val;
        } catch (PDOException $e) {
            throw new RuntimeException("SQL Error in getValue:\n$sql\n\n" . $e->getMessage());
        }
    }

    public function execute($sql)
    {
        try {
            $ok = $this->pdo->exec($sql) !== false;
            $this->log('execute', $sql);
            return $ok;
        } catch (PDOException $e) {
            throw new RuntimeException("SQL Error in execute:\n$sql\n\n" . $e->getMessage());
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}

/**
 * Configuration backed by real ps_configuration table.
 */
class Configuration
{
    public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null)
    {
        $pdo = $GLOBALS['test_pdo'];

        // Explicit global read
        if (($idShopGroup === 0 && $idShop === 0) || $idShop === null) {
            $stmt = $pdo->prepare(
                'SELECT value FROM ps_configuration WHERE name = ? AND id_shop IS NULL AND id_shop_group IS NULL LIMIT 1'
            );
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : false;
        }

        // Per-shop read
        if ($idShop > 0) {
            $stmt = $pdo->prepare(
                'SELECT value FROM ps_configuration WHERE name = ? AND id_shop = ? LIMIT 1'
            );
            $stmt->execute([$key, $idShop]);
            $result = $stmt->fetchColumn();
            if ($result !== false) {
                return $result;
            }
            // Fallback to global
            return self::get($key);
        }

        return false;
    }

    public static function updateValue($key, $value, $html = false, $idShopGroup = null, $idShop = null)
    {
        $pdo = $GLOBALS['test_pdo'];

        if ($idShopGroup === 0 && $idShop === 0) {
            // Global write
            $stmt = $pdo->prepare(
                'INSERT INTO ps_configuration (name, value, id_shop, id_shop_group)
                 VALUES (?, ?, NULL, NULL)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)'
            );
            return $stmt->execute([$key, $value]);
        }

        if ($idShop !== null && $idShop > 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO ps_configuration (name, value, id_shop, id_shop_group)
                 VALUES (?, ?, ?, NULL)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)'
            );
            return $stmt->execute([$key, $value, $idShop]);
        }

        // Default: global
        return self::updateValue($key, $value, $html, 0, 0);
    }

    public static function deleteByName($key)
    {
        $pdo = $GLOBALS['test_pdo'];
        $stmt = $pdo->prepare('DELETE FROM ps_configuration WHERE name = ?');
        return $stmt->execute([$key]);
    }
}

/**
 * Shop backed by real ps_shop table.
 */
class Shop
{
    const CONTEXT_ALL = 1;
    const CONTEXT_GROUP = 2;
    const CONTEXT_SHOP = 4;

    private static $context = self::CONTEXT_SHOP;
    public $id_category;

    public function __construct($id = null)
    {
        if ($id !== null) {
            $pdo = $GLOBALS['test_pdo'];
            $stmt = $pdo->prepare('SELECT id_category FROM ps_shop WHERE id_shop = ?');
            $stmt->execute([$id]);
            $this->id_category = (int) ($stmt->fetchColumn() ?: 2);
        }
    }

    public static function isFeatureActive(): bool
    {
        $pdo = $GLOBALS['test_pdo'];
        $count = (int) $pdo->query('SELECT COUNT(*) FROM ps_shop WHERE active = 1')->fetchColumn();
        return $count > 1;
    }

    public static function getShops($active = true): array
    {
        $pdo = $GLOBALS['test_pdo'];
        $sql = 'SELECT * FROM ps_shop' . ($active ? ' WHERE active = 1' : '');
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getGroupFromShop($idShop): int
    {
        $pdo = $GLOBALS['test_pdo'];
        $stmt = $pdo->prepare('SELECT id_shop_group FROM ps_shop WHERE id_shop = ?');
        $stmt->execute([$idShop]);
        return (int) ($stmt->fetchColumn() ?: 1);
    }

    public static function getContext(): int
    {
        return self::$context;
    }

    public static function setContext(int $context, $shopId = null): void
    {
        self::$context = $context;
    }

    public static function getContextShopID(): int
    {
        return 1;
    }
}

class Context
{
    private static $instance;
    public $shop;
    public $language;

    public function __construct()
    {
        $this->shop = new \stdClass();
        $this->shop->id = 1;
        $this->shop->name = 'Boutique FR';
        $this->language = new \stdClass();
        $this->language->id = 1;
        $this->language->iso_code = 'fr';
    }

    public static function getContext(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

class DbQuery
{
    private $parts = [];
    public function select($s) { $this->parts['select'] = $s; return $this; }
    public function from($t, $a = null) { $this->parts['from'] = $t; return $this; }
    public function where($s) { $this->parts['where'][] = $s; return $this; }
    public function leftJoin($t, $a, $o) { return $this; }
    public function __toString()
    {
        $sql = 'SELECT ' . ($this->parts['select'] ?? '*');
        $sql .= ' FROM ' . _DB_PREFIX_ . ($this->parts['from'] ?? '');
        if (!empty($this->parts['where'])) {
            $sql .= ' WHERE ' . implode(' AND ', $this->parts['where']);
        }
        return $sql;
    }
}

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

class PrestaShopLogger
{
    public static function addLog($msg, $sev = 1, $code = null, $obj = null, $id = null, $dup = false) {}
}

class Tools
{
    public static function getValue($key, $default = false) { return $default; }
    public static function isSubmit($key) { return false; }
    public static function usingSecureMode() { return true; }
    public static function getShopDomainSsl() { return 'localhost'; }
    public static function redirect($url) {}
    public static function getAdminTokenLite($c) { return 'test'; }
}

class Module
{
    public $name;
    public function l($s) { return $s; }
    public function displayConfirmation($m) { return $m; }
    public function displayError($m) { return $m; }
}

if (!function_exists('pSQL')) {
    function pSQL($string) { return addslashes($string); }
}

// ──────────────────────────────────────────────
// 4. Load module autoloader
// ──────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
