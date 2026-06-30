<?php
/**
 * Smoke tests for AI SmartTalk module — run INSIDE a PrestaShop container.
 *
 * Validates that the module installs, renders, and uninstalls cleanly
 * on a real PrestaShop instance. Catches incompatibilities between
 * PS versions (1.7, 8, 9) that unit/integration tests cannot detect.
 *
 * Usage:
 *   docker exec <container> php modules/aismarttalk/tests/Smoke/run_smoke_tests.php
 *
 * Or via Makefile:
 *   make smoke-test
 *   make smoke-test-ps17
 */

// ─── Bootstrap PrestaShop ───────────────────────────────────────────────────
$psRoot = '/var/www/html';
if (!file_exists($psRoot . '/config/config.inc.php')) {
    fwrite(STDERR, "ERROR: PrestaShop not found at $psRoot\n");
    exit(1);
}

// Suppress display errors for clean output
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once $psRoot . '/config/config.inc.php';

// ─── Test framework ─────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;
$errors = [];

function smokeTest(string $name, callable $fn): void
{
    global $passed, $failed, $errors;
    try {
        $result = $fn();
        if ($result === false) {
            $failed++;
            $errors[] = $name . ': returned false';
            echo "  \033[31m✗\033[0m $name\n";
        } else {
            $passed++;
            echo "  \033[32m✓\033[0m $name\n";
        }
    } catch (\Throwable $e) {
        $failed++;
        $errors[] = $name . ': ' . $e->getMessage();
        echo "  \033[31m✗\033[0m $name — " . $e->getMessage() . "\n";
    }
}

// ─── Detect PS version ──────────────────────────────────────────────────────
$psVersion = _PS_VERSION_;
echo "\n\033[1m=== AI SmartTalk Smoke Tests ===\033[0m\n";
echo "PrestaShop version: $psVersion\n";
echo "PHP version: " . PHP_VERSION . "\n\n";

// ─── Module loading ─────────────────────────────────────────────────────────
echo "\033[1m[Module Loading]\033[0m\n";

smokeTest('Module file exists', function () {
    return file_exists(_PS_MODULE_DIR_ . 'aismarttalk/aismarttalk.php');
});

smokeTest('Module class loads without error', function () {
    require_once _PS_MODULE_DIR_ . 'aismarttalk/aismarttalk.php';
    return class_exists('AiSmartTalk');
});

smokeTest('Module can be instantiated', function () {
    $module = Module::getInstanceByName('aismarttalk');
    return $module !== false && $module instanceof Module;
});

// ─── Installation ───────────────────────────────────────────────────────────
echo "\n\033[1m[Installation]\033[0m\n";

$module = Module::getInstanceByName('aismarttalk');

// PS 1.7 CLI cannot call Module::install() (Symfony container unavailable).
// If module is already installed (via BO), skip install/uninstall tests.
$canInstallCLI = true;
if (!Module::isInstalled('aismarttalk')) {
    try {
        $canInstallCLI = $module->install();
    } catch (\Throwable $e) {
        $canInstallCLI = false;
        echo "  \033[33m⚠\033[0m Install via CLI not supported on this PS version (expected on 1.7)\n";
        echo "    → " . substr($e->getMessage(), 0, 80) . "\n";
        // Manually create tables so remaining tests can run
        \PrestaShop\AiSmartTalk\AiSmartTalkProductSync::createTable();
        \PrestaShop\AiSmartTalk\AiSmartTalkCustomerSync::createTable();
    }
}

if ($canInstallCLI) {
    smokeTest('Module is installed', function () {
        return Module::isInstalled('aismarttalk');
    });
}

// Ensure tables exist (create if missing — covers both fresh install and upgrade scenarios)
\PrestaShop\AiSmartTalk\AiSmartTalkProductSync::createTable();
\PrestaShop\AiSmartTalk\AiSmartTalkCustomerSync::createTable();

smokeTest('Product sync table exists', function () {
    $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_product_sync'");
    return !empty($result);
});

smokeTest('Customer sync table exists', function () {
    $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_customer_sync'");
    return !empty($result);
});

smokeTest('Product sync table exists', function () {
    $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_product_sync'");
    return !empty($result);
});

smokeTest('Customer sync table exists', function () {
    $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_customer_sync'");
    return !empty($result);
});

// ─── Hooks ──────────────────────────────────────────────────────────────────
echo "\n\033[1m[Hooks Registration]\033[0m\n";

// Ensure hooks are registered (mirrors what getContent() does on first admin visit)
if (Module::isInstalled('aismarttalk') && method_exists($module, 'registerAiSmartTalkHooks')) {
    try {
        $module->registerAiSmartTalkHooks();
    } catch (\Throwable $e) {
        // Ignore — may fail in CLI context on some PS versions
    }
}

$criticalHooks = [
    'displayFooter',
    'actionProductUpdate',
    'actionProductCreate',
    'actionProductDelete',
    'actionCustomerAccountAdd',
];

foreach ($criticalHooks as $hook) {
    smokeTest("Hook '$hook' registered", function () use ($module, $hook) {
        return $module->isRegisteredInHook($hook);
    });
}

// ─── Admin page rendering ───────────────────────────────────────────────────
echo "\n\033[1m[Admin Page Rendering]\033[0m\n";

smokeTest('getContent() does not crash', function () use ($module) {
    // Ensure tables exist for getContent
    \PrestaShop\AiSmartTalk\AiSmartTalkProductSync::createTable();
    \PrestaShop\AiSmartTalk\AiSmartTalkCustomerSync::createTable();

    ob_start();
    try {
        $output = $module->getContent();
        ob_end_clean();
        return is_string($output) && strlen($output) > 100;
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
});

smokeTest('getContent() contains expected HTML', function () use ($module) {
    ob_start();
    $output = $module->getContent();
    ob_end_clean();

    // The module always renders the configure.tpl with these elements
    // (even when not OAuth-connected, it shows the connect page within ast-app)
    $hasApp = strpos($output, 'ast-app') !== false || strpos($output, 'aismarttalk') !== false;
    $hasContent = strlen($output) > 200;

    return $hasApp && $hasContent;
});

// ─── Module classes ─────────────────────────────────────────────────────────
echo "\n\033[1m[Module Classes]\033[0m\n";

$requiredClasses = [
    'PrestaShop\AiSmartTalk\MultistoreHelper',
    'PrestaShop\AiSmartTalk\SyncFilterHelper',
    'PrestaShop\AiSmartTalk\SynchCategoriesToAiSmartTalk',
    'PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk',
    'PrestaShop\AiSmartTalk\CleanProductDocuments',
    'PrestaShop\AiSmartTalk\AdminFormHandler',
    'PrestaShop\AiSmartTalk\ApiClient',
    'PrestaShop\AiSmartTalk\CustomerSync',
    'PrestaShop\AiSmartTalk\WebhookHandler',
    'PrestaShop\AiSmartTalk\PayloadEncryptor',
    'PrestaShop\AiSmartTalk\ChatbotSettingsBuilder',
    'PrestaShop\AiSmartTalk\OAuthHandler',
    'PrestaShop\AiSmartTalk\PriceFormatter',
    'PrestaShop\AiSmartTalk\PriceCalculator',
    'PrestaShop\AiSmartTalk\PriceInfo',
    'PrestaShop\AiSmartTalk\CombinationHelper',
    // DEV-857 — canonical product document + out-of-stock sync
    'PrestaShop\AiSmartTalk\CanonicalProductMapper',
    'PrestaShop\AiSmartTalk\StockStatusHelper',
    'PrestaShop\AiSmartTalk\WidgetLocales',
];

foreach ($requiredClasses as $class) {
    $shortName = substr($class, strrpos($class, '\\') + 1);
    smokeTest("Class $shortName loadable", function () use ($class) {
        return class_exists($class);
    });
}

// ─── Multistore detection ───────────────────────────────────────────────────
echo "\n\033[1m[Multistore]\033[0m\n";

smokeTest('MultistoreHelper::isMultistoreActive() does not crash', function () {
    $result = PrestaShop\AiSmartTalk\MultistoreHelper::isMultistoreActive();
    return is_bool($result);
});

smokeTest('MultistoreHelper::getAllShopIds() returns at least one', function () {
    $ids = PrestaShop\AiSmartTalk\MultistoreHelper::getAllShopIds();
    return is_array($ids) && count($ids) >= 1;
});

smokeTest('MultistoreHelper::getConfig() reads without error', function () {
    $val = PrestaShop\AiSmartTalk\MultistoreHelper::getConfig('AI_SMART_TALK_URL');
    return $val !== null; // can be false (not set) but should not throw
});

// ─── Price calculator (DEV-842: variants + promos) ─────────────────────────
// Validates the runtime contract with Product::getPriceStatic on each PS
// version. The 16-arg signature + the &$specific_price_output reference
// have been stable since PS 1.7.0, but this section catches any drift fast.
echo "\n\033[1m[Price Calculator]\033[0m\n";

// PS 9 refuses Product::getPriceStatic from a CLI context without an employee
// ("If no employee is assigned in the context, cart ID must be provided").
// Bootstrap a minimal admin employee so the static price API behaves like it
// would during a real BO-triggered sync. No-op if an employee is already set.
if (Context::getContext()->employee === null) {
    // No trailing LIMIT 1 — Db::getValue() appends its own via getRow().
    $employeeId = (int) Db::getInstance()->getValue(
        'SELECT id_employee FROM ' . _DB_PREFIX_ . 'employee WHERE active = 1 ORDER BY id_employee'
    );
    if ($employeeId > 0 && class_exists('Employee')) {
        try {
            Context::getContext()->employee = new Employee($employeeId);
        } catch (\Throwable $e) {
            // Best-effort — if it fails we'll let the smoke tests surface the issue.
        }
    }
}

$samplePid = (int) Db::getInstance()->getValue(
    'SELECT p.id_product FROM ' . _DB_PREFIX_ . 'product p
     INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product
     WHERE p.active = 1 AND ps.active = 1 ORDER BY p.id_product'
);

if ($samplePid > 0) {
    smokeTest('PriceCalculator::calculate returns PriceInfo', function () use ($samplePid) {
        $info = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        return $info instanceof PrestaShop\AiSmartTalk\PriceInfo;
    });

    smokeTest('PriceInfo carries finalPrice >= 0', function () use ($samplePid) {
        $info = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        return is_float($info->finalPrice) && $info->finalPrice >= 0;
    });

    smokeTest('PriceInfo carries originalPrice >= finalPrice', function () use ($samplePid) {
        $info = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        return $info->originalPrice >= $info->finalPrice - 0.001;
    });

    smokeTest('PriceInfo.hasDiscount is a boolean', function () use ($samplePid) {
        $info = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        return is_bool($info->hasDiscount);
    });

    smokeTest('PriceInfo.discountType is valid', function () use ($samplePid) {
        $info = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        $valid = ['none', 'percentage', 'amount', 'computed'];
        return in_array($info->discountType, $valid, true);
    });

    smokeTest('PriceInfo.discountPercent in [0, 100]', function () use ($samplePid) {
        $info = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        return is_int($info->discountPercent) && $info->discountPercent >= 0 && $info->discountPercent <= 100;
    });

    // Discount consistency: when hasDiscount, all promo fields must agree.
    smokeTest('PriceInfo internal consistency (no discount → zeroed fields)', function () use ($samplePid) {
        $info = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        if ($info->hasDiscount) {
            return $info->discountAmount > 0 && $info->discountType !== 'none';
        }
        return $info->discountAmount === 0.0
            && $info->discountPercent === 0
            && $info->discountType === 'none';
    });
} else {
    echo "  \033[33m⚠\033[0m Skipped — no active product found in catalog\n";
}

// PriceFormatter is pure (no PrestaShop deps) but smoke-test it on each PS
// version anyway: it's the contract the front and backend depend on for
// 3-decimal currencies like LYD. A regression would silently break .000 prices.
smokeTest('PriceFormatter preserves LYD trailing zeros', function () {
    return PrestaShop\AiSmartTalk\PriceFormatter::format(12, 3) === '12.000';
});

smokeTest('PriceFormatter falls back to 2 decimals when currency precision absent', function () {
    return PrestaShop\AiSmartTalk\PriceFormatter::decimalsFromCurrency(null) === 2;
});

// ─── Category tree ──────────────────────────────────────────────────────────
echo "\n\033[1m[Category Tree]\033[0m\n";

smokeTest('getCategoryTree() returns array', function () {
    $langId = (int) Configuration::get('PS_LANG_DEFAULT');
    $shopId = (int) Context::getContext()->shop->id;
    $tree = PrestaShop\AiSmartTalk\SyncFilterHelper::getCategoryTree($langId, $shopId);
    return is_array($tree);
});

smokeTest('getCategoryTree() has at least one category', function () {
    $langId = (int) Configuration::get('PS_LANG_DEFAULT');
    $shopId = (int) Context::getContext()->shop->id;
    $tree = PrestaShop\AiSmartTalk\SyncFilterHelper::getCategoryTree($langId, $shopId);
    $flat = PrestaShop\AiSmartTalk\SyncFilterHelper::flattenCategoryTree($tree);
    return count($flat) >= 1;
});

// ─── DEV-857: Stock status normalization ─────────────────────────────────────
// The canonical "stock" block is the contract the backend + LLM read to reason
// about availability. Pure logic, but smoke-tested on every PS version so a
// regression is caught where it ships, not in production.
echo "\n\033[1m[DEV-857: Stock Status]\033[0m\n";

smokeTest('StockStatusHelper::normalize → in_stock when qty > 0 (no restock date)', function () {
    $b = PrestaShop\AiSmartTalk\StockStatusHelper::normalize(5, '2026-09-01');
    return $b['status'] === 'in_stock' && $b['quantity'] === 5 && $b['restock_date'] === null;
});

smokeTest('StockStatusHelper::normalize → out_of_stock keeps restock date', function () {
    $b = PrestaShop\AiSmartTalk\StockStatusHelper::normalize(0, '2026-09-01');
    return $b['status'] === 'out_of_stock' && $b['quantity'] === 0 && $b['restock_date'] === '2026-09-01';
});

smokeTest('StockStatusHelper::normalizeDate strips time + rejects zero-date', function () {
    return PrestaShop\AiSmartTalk\StockStatusHelper::normalizeDate('0000-00-00') === null
        && PrestaShop\AiSmartTalk\StockStatusHelper::normalizeDate('2026-09-01 12:00:00') === '2026-09-01';
});

// ─── DEV-857: Canonical product document ──────────────────────────────────────
// The cross-platform v1 shape every connector emits. toMoney's integer minor
// units are the part most sensitive to currency precision (EUR=2, LYD=3).
echo "\n\033[1m[DEV-857: Canonical Document]\033[0m\n";

smokeTest('CanonicalProductMapper::toMoney → integer minor units + uppercased currency', function () {
    $m = PrestaShop\AiSmartTalk\CanonicalProductMapper::toMoney(12.34, 2, 'eur');
    return is_array($m) && $m['amount'] === 1234 && $m['currency'] === 'EUR' && $m['decimals'] === 2;
});

smokeTest('CanonicalProductMapper::toMoney → 3-decimal currency (LYD) keeps .000', function () {
    $m = PrestaShop\AiSmartTalk\CanonicalProductMapper::toMoney(12, 3, 'LYD');
    return $m['amount'] === 12000 && $m['display'] === '12.000';
});

smokeTest('CanonicalProductMapper::toMoney → null on non-numeric', function () {
    return PrestaShop\AiSmartTalk\CanonicalProductMapper::toMoney('n/a', 2, 'EUR') === null;
});

smokeTest('CanonicalProductMapper::availability maps qty → enum', function () {
    return PrestaShop\AiSmartTalk\CanonicalProductMapper::availability(3) === 'in_stock'
        && PrestaShop\AiSmartTalk\CanonicalProductMapper::availability(0) === 'out_of_stock';
});

smokeTest('CanonicalProductMapper::mapAttributes group/value → name/value (drops incomplete)', function () {
    $out = PrestaShop\AiSmartTalk\CanonicalProductMapper::mapAttributes([
        ['group' => 'Color', 'value' => 'Red'],
        ['group' => '', 'value' => 'X'],
    ]);
    return count($out) === 1 && $out[0]['name'] === 'Color' && $out[0]['value'] === 'Red';
});

if ($samplePid > 0) {
    // Full document for a real catalog product — exercises map() + the
    // Product::getPriceStatic / StockAvailable wiring on this exact PS version.
    smokeTest('CanonicalProductMapper::map builds a canonical document for a real product', function () use ($samplePid) {
        $idShop = (int) Context::getContext()->shop->id;
        $priceInfo = PrestaShop\AiSmartTalk\PriceCalculator::calculate($samplePid, 0, 2);
        $qty = (int) StockAvailable::getQuantityAvailableByProduct($samplePid, null, $idShop);
        $doc = PrestaShop\AiSmartTalk\CanonicalProductMapper::map([
            'idProduct' => $samplePid,
            'name' => 'Smoke product',
            'decimals' => 2,
            'currency' => 'EUR',
            'quantity' => $qty,
            'categories' => [],
            'variants' => [],
            'priceInfo' => $priceInfo,
        ]);

        return is_array($doc)
            && $doc['type'] === 'product'
            && $doc['externalId'] === (string) $samplePid
            && is_array($doc['price'])
            && in_array($doc['availability'], ['in_stock', 'out_of_stock'], true)
            && array_key_exists('variants', $doc);
    });

    smokeTest('CanonicalProductMapper::productFeatures / productCategories execute', function () use ($samplePid) {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $feat = PrestaShop\AiSmartTalk\CanonicalProductMapper::productFeatures($samplePid, $idLang);
        $cats = PrestaShop\AiSmartTalk\CanonicalProductMapper::productCategories($samplePid, $idLang);
        return is_array($feat) && is_array($cats);
    });
}

// ─── DEV-857: Combinations (variants) ─────────────────────────────────────────
// Stores that sell everything as combinations (Libyan client) need the variant
// payload built from Product::getAttributeCombinations — whose row shape is the
// kind of thing that drifts between PS 1.7 and 8/9.
echo "\n\033[1m[DEV-857: Combinations]\033[0m\n";

$comboPid = (int) Db::getInstance()->getValue(
    'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_attribute ORDER BY id_product'
);

if ($comboPid > 0) {
    smokeTest('CombinationHelper::hasCombinations true for a combination product', function () use ($comboPid) {
        return PrestaShop\AiSmartTalk\CombinationHelper::hasCombinations($comboPid) === true;
    });

    smokeTest('CombinationHelper::getVariants returns well-formed variant rows', function () use ($comboPid) {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = (int) Context::getContext()->shop->id;
        $variants = PrestaShop\AiSmartTalk\CombinationHelper::getVariants($comboPid, $idLang, $idShop, 2);
        if (!is_array($variants) || count($variants) === 0) {
            return false;
        }
        $v = $variants[0];
        return isset($v['id_product_attribute']) && (int) $v['id_product_attribute'] > 0
            && array_key_exists('price', $v)
            && array_key_exists('quantity', $v)
            && isset($v['attributes']) && is_array($v['attributes']);
    });
} else {
    echo "  \033[33m⚠\033[0m Skipped — no combination product in catalog (seed one with make seed-products)\n";
}

// ─── DEV-857: Out-of-stock sync filter ────────────────────────────────────────
// The toggle that lets out-of-stock products be synced, plus the version-safe
// stock JOIN that has to read BOTH PS 1.7 shared-stock rows (id_shop = 0) and
// PS 8+ per-shop rows (id_shop = X).
echo "\n\033[1m[DEV-857: Out-of-stock Filter]\033[0m\n";

smokeTest('SyncFilterHelper::shouldIncludeOutOfStock returns bool', function () {
    return is_bool(PrestaShop\AiSmartTalk\SyncFilterHelper::shouldIncludeOutOfStock());
});

smokeTest('SyncFilterHelper::buildStockAvailableJoin covers shop AND shop-group rows + runs', function () {
    $idShop = (int) Context::getContext()->shop->id;
    $join = PrestaShop\AiSmartTalk\SyncFilterHelper::buildStockAvailableJoin($idShop, 'p', 'sa');
    if (strpos($join, 'stock_available') === false
        || strpos($join, 'id_shop = 0') === false
        || strpos($join, 'id_shop = ' . $idShop) === false) {
        return false;
    }
    // Splice into a real query: a malformed JOIN throws here and fails the test.
    $sql = 'SELECT p.id_product, sa.quantity FROM ' . _DB_PREFIX_ . 'product p '
        . $join . ' WHERE p.active = 1 LIMIT 1';
    Db::getInstance()->executeS($sql);
    return true;
});

if ($samplePid > 0) {
    smokeTest('SyncFilterHelper::shouldProductBeKept returns bool for a real product', function () use ($samplePid) {
        return is_bool(PrestaShop\AiSmartTalk\SyncFilterHelper::shouldProductBeKept($samplePid));
    });

    smokeTest('MultistoreHelper active-state probes do not crash', function () use ($samplePid) {
        return is_bool(PrestaShop\AiSmartTalk\MultistoreHelper::isProductActiveInAnyShop($samplePid))
            && is_bool(PrestaShop\AiSmartTalk\MultistoreHelper::isProductActiveOnlyInAnyShop($samplePid));
    });
}

// ─── DEV-857: In-widget cart contract ─────────────────────────────────────────
// The same-origin cart controller + its CSRF token. The token uses hash_hmac
// (NOT Tools::encrypt, removed in PS9) precisely so it works on 1.7/8/9.
echo "\n\033[1m[DEV-857: Cart Contract]\033[0m\n";

smokeTest('Cart front controller class loads (parses on this PS version)', function () {
    require_once _PS_MODULE_DIR_ . 'aismarttalk/controllers/front/cart.php';
    return class_exists('AismarttalkCartModuleFrontController');
});

smokeTest('Cart CSRF token round-trips (hash_hmac, version-safe)', function () {
    $token = PrestaShop\AiSmartTalk\ChatbotSettingsBuilder::cartTokenForCustomer(42);
    return is_string($token) && $token !== ''
        && PrestaShop\AiSmartTalk\ChatbotSettingsBuilder::isCartTokenValid($token, 42) === true;
});

smokeTest('Cart CSRF token rejects wrong customer + empty token', function () {
    $token = PrestaShop\AiSmartTalk\ChatbotSettingsBuilder::cartTokenForCustomer(42);
    return PrestaShop\AiSmartTalk\ChatbotSettingsBuilder::isCartTokenValid($token, 43) === false
        && PrestaShop\AiSmartTalk\ChatbotSettingsBuilder::isCartTokenValid('', 42) === false;
});

// ─── DEV-857: Widget locales ──────────────────────────────────────────────────
echo "\n\033[1m[DEV-857: Widget Locales]\033[0m\n";

smokeTest('WidgetLocales::all returns a non-empty picker list', function () {
    $all = PrestaShop\AiSmartTalk\WidgetLocales::all();
    return is_array($all) && count($all) > 0 && isset($all[0]['code'], $all[0]['name']);
});

smokeTest('WidgetLocales::isValid / sanitize filter + dedupe codes', function () {
    return PrestaShop\AiSmartTalk\WidgetLocales::isValid('fr') === true
        && PrestaShop\AiSmartTalk\WidgetLocales::isValid('zz') === false
        && PrestaShop\AiSmartTalk\WidgetLocales::sanitize(['fr', 'zz', 'en', 'fr']) === ['fr', 'en'];
});

// ─── DEV-857: Live-stock hooks ────────────────────────────────────────────────
// actionUpdateQuantity is the baseline stock signal; the ObjectModel
// StockAvailable hooks are best-effort (registered in a try/catch), so a missing
// one is a warning, not a failure.
echo "\n\033[1m[DEV-857: Stock Hooks]\033[0m\n";

if (Module::isInstalled('aismarttalk')) {
    smokeTest("Hook 'actionUpdateQuantity' registered", function () use ($module) {
        return $module->isRegisteredInHook('actionUpdateQuantity');
    });

    foreach (['actionObjectStockAvailableUpdateAfter', 'actionObjectStockAvailableAddAfter'] as $optHook) {
        if ($module->isRegisteredInHook($optHook)) {
            echo "  \033[32m✓\033[0m Optional hook '$optHook' registered\n";
            $passed++;
        } else {
            echo "  \033[33m⚠\033[0m Optional hook '$optHook' not registered (best-effort — live stock degrades gracefully)\n";
        }
    }
} else {
    echo "  \033[33m⚠\033[0m Skipped — module not installed\n";
}

// ─── Uninstall ──────────────────────────────────────────────────────────────
echo "\n\033[1m[Uninstall]\033[0m\n";

if ($canInstallCLI && Module::isInstalled('aismarttalk')) {
    smokeTest('Uninstall succeeds', function () use ($module) {
        return $module->uninstall();
    });

    smokeTest('Tables dropped after uninstall', function () {
        $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_product_sync'");
        return empty($result);
    });

    // Re-install for the container to remain usable
    try {
        $module->install();
    } catch (\Throwable $e) {
        // Best effort
    }
} else {
    echo "  \033[33m⚠\033[0m Skipped (CLI install not available)\n";
}

// ─── Results ────────────────────────────────────────────────────────────────
echo "\n\033[1m=== Results ===\033[0m\n";
echo "Passed: \033[32m$passed\033[0m\n";
echo "Failed: " . ($failed > 0 ? "\033[31m$failed\033[0m" : "0") . "\n";

if (!empty($errors)) {
    echo "\n\033[31mErrors:\033[0m\n";
    foreach ($errors as $err) {
        echo "  • $err\n";
    }
}

echo "\n";
exit($failed > 0 ? 1 : 0);
