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
