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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to version 3.2.0
 *
 * This upgrade adds configuration keys for advanced product sync filtering:
 * - Filter by categories (include/exclude mode)
 * - Include/exclude subcategories option
 * - Filter by product types (standard, virtual, pack)
 *
 * @param AiSmartTalk $module
 * @return bool
 */
function upgrade_module_3_2_0($module)
{
    // Initialize default configuration values for sync filters
    // By default, no filters are active (all products synced)
    $defaults = [
        'AI_SMART_TALK_SYNC_FILTER_MODE' => 'include',
        'AI_SMART_TALK_SYNC_CATEGORIES' => '[]',
        'AI_SMART_TALK_SYNC_INCLUDE_SUBCATEGORIES' => '1',
        'AI_SMART_TALK_SYNC_PRODUCT_TYPES' => '["standard","virtual","pack"]',
    ];

    foreach ($defaults as $key => $value) {
        // Only set if not already configured (preserve existing settings)
        if (Configuration::get($key) === false) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }
    }

    return true;
}
