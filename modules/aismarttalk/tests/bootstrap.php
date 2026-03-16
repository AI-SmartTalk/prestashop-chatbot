<?php
/**
 * PHPUnit bootstrap for AI SmartTalk module tests.
 *
 * Provides mock implementations of PrestaShop core classes and functions
 * so that module unit tests can run without a full PrestaShop installation.
 */

// Simulate PrestaShop constants
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}

// Load PrestaShop mocks before module autoloader
require_once __DIR__ . '/Mocks/PrestashopMocks.php';

// Load module autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';
