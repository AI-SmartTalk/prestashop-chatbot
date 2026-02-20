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
 * Upgrade module to 3.3.0
 * Compute and store site identifier for multi-site support
 */
function upgrade_module_3_3_0($module)
{
    $host = parse_url(Tools::getShopDomainSsl(true), PHP_URL_HOST)
        ?: Tools::getShopDomainSsl(true);

    Configuration::updateValue('AI_SMART_TALK_SITE_IDENTIFIER', $host);

    return true;
}
