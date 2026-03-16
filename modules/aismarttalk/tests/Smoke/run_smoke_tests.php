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

// Uninstall first if already installed
if (Module::isInstalled('aismarttalk')) {
    smokeTest('Uninstall (pre-clean)', function () use ($module) {
        return $module->uninstall();
    });
}

smokeTest('Install succeeds', function () use ($module) {
    return $module->install();
});

smokeTest('Module is installed after install()', function () {
    return Module::isInstalled('aismarttalk');
});

smokeTest('Product sync table exists', function () {
    $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_product_sync'");
    return !empty($result);
});

smokeTest('Customer sync table exists', function () {
    $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_customer_sync'");
    return !empty($result);
});

smokeTest('Default config values set', function () {
    return Configuration::get('AI_SMART_TALK_URL') !== false
        && Configuration::get('AI_SMART_TALK_CDN') !== false
        && Configuration::get('AI_SMART_TALK_IFRAME_POSITION') !== false;
});

// ─── Hooks ──────────────────────────────────────────────────────────────────
echo "\n\033[1m[Hooks Registration]\033[0m\n";

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
    // Simulate admin context
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

    // Check for key UI elements
    return strpos($output, 'ast-app') !== false
        && strpos($output, 'panel-chatbot') !== false
        && strpos($output, 'panel-sync') !== false;
});

// ─── Module classes ─────────────────────────────────────────────────────────
echo "\n\033[1m[Module Classes]\033[0m\n";

$requiredClasses = [
    'PrestaShop\AiSmartTalk\MultistoreHelper',
    'PrestaShop\AiSmartTalk\SyncFilterHelper',
    'PrestaShop\AiSmartTalk\SynchProductsToAiSmartTalk',
    'PrestaShop\AiSmartTalk\CleanProductDocuments',
    'PrestaShop\AiSmartTalk\AdminFormHandler',
    'PrestaShop\AiSmartTalk\ApiClient',
    'PrestaShop\AiSmartTalk\CustomerSync',
    'PrestaShop\AiSmartTalk\WebhookHandler',
    'PrestaShop\AiSmartTalk\PayloadEncryptor',
    'PrestaShop\AiSmartTalk\ChatbotSettingsBuilder',
    'PrestaShop\AiSmartTalk\OAuthHandler',
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

smokeTest('Uninstall succeeds', function () use ($module) {
    return $module->uninstall();
});

smokeTest('Tables dropped after uninstall', function () {
    $result = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "aismarttalk_product_sync'");
    return empty($result);
});

// Re-install for the container to remain usable
$module->install();

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
