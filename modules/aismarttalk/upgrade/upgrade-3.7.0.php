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
 * Upgrade module to 3.7.0 — Variants, currency precision and promotions.
 *
 * What this version adds at the data/wiring level:
 *  - Three new hooks for product combinations (actionProductAttributeCreate /
 *    Update / Delete) so that editing a single variant triggers a parent
 *    re-sync. Existing installations have those hooks missing in ps_hook_module
 *    because the install() ran before they were declared.
 *
 * Why an upgrade script — instead of relying on ensureHooksRegistered() being
 * called from getContent() on the next BO visit:
 *   - Stores that never open the configuration page (silent sync only)
 *     would otherwise wait indefinitely.
 *   - We want the upgrade to be self-contained and observable in the upgrade
 *     log so support knows it ran.
 *
 * Everything else in 3.7.0 (PriceCalculator, CombinationHelper, the new
 * payload fields original_price / discount_percent / discount_type / variants
 * — and the front auto-badge fallback) ships in the code; no DB migration
 * is required.
 */
function upgrade_module_3_7_0($module)
{
    if (method_exists($module, 'registerAiSmartTalkHooks')) {
        // registerAiSmartTalkHooks already iterates the canonical hooks list
        // and skips those already registered, so it's safe to call.
        $module->registerAiSmartTalkHooks();
        return true;
    }

    // Defensive fallback if the helper is renamed in a future refactor.
    foreach (['actionProductAttributeCreate', 'actionProductAttributeUpdate', 'actionProductAttributeDelete'] as $hook) {
        if (!$module->isRegisteredInHook($hook)) {
            $module->registerHook($hook);
        }
    }

    return true;
}
