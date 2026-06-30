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
 * Upgrade module to 3.8.0 — "Include out-of-stock products" toggle.
 *
 * The new SyncFilterHelper::CONFIG_INCLUDE_OUT_OF_STOCK option lets shops
 * synchronize every active product, regardless of stock level. Default is
 * OFF so existing installs keep the historical behaviour (only stock > 0
 * products are sent).
 *
 * We explicitly write the default value here — rather than relying on the
 * implicit falsy return of Configuration::get() — so the row exists in
 * ps_configuration. That gives support a single place to read/audit the
 * current value, and lets future migrations diff against a known state.
 */
function upgrade_module_3_8_0($module)
{
    if (Configuration::get('AI_SMART_TALK_SYNC_INCLUDE_OUT_OF_STOCK') === false) {
        Configuration::updateValue('AI_SMART_TALK_SYNC_INCLUDE_OUT_OF_STOCK', '0');
    }

    return true;
}
