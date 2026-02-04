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
 * Upgrade module to version 3.1.0
 *
 * This upgrade migrates product sync data from ps_product columns
 * to a dedicated ps_aismarttalk_product_sync table.
 *
 * @param AiSmartTalk $module
 * @return bool
 */
function upgrade_module_3_1_0($module)
{
    require_once dirname(__FILE__) . '/../vendor/autoload.php';

    // Create the new product sync table
    if (!PrestaShop\AiSmartTalk\AiSmartTalkProductSync::createTable()) {
        return false;
    }

    // Migrate data from legacy columns to new table
    PrestaShop\AiSmartTalk\AiSmartTalkProductSync::migrateFromLegacyColumns();

    // Remove legacy columns from ps_product
    PrestaShop\AiSmartTalk\AiSmartTalkProductSync::removeLegacyColumns();

    return true;
}
