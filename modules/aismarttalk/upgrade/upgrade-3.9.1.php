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
 * Upgrade module to 3.9.1 — register the generic ObjectModel combination hooks.
 *
 * PrestaShop 8/9's new (Symfony) product page deletes/edits combinations through
 * the ObjectModel base — which fires actionObjectCombination*After — rather than
 * the legacy actionProductAttribute* hooks. Without these, deleting a variant in
 * the new BO never re-syncs the product, leaving an orphan variant document on
 * AI SmartTalk. Register them so incremental sync works on modern PrestaShop.
 */
function upgrade_module_3_9_1($module)
{
    $hooks = [
        'actionObjectCombinationAddAfter',
        'actionObjectCombinationUpdateAfter',
        'actionObjectCombinationDeleteAfter',
    ];

    $ok = true;
    foreach ($hooks as $hook) {
        if (!$module->isRegisteredInHook($hook)) {
            $ok = $module->registerHook($hook) && $ok;
        }
    }

    return $ok;
}
