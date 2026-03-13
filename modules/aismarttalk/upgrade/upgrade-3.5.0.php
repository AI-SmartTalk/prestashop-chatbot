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
 * Upgrade module to 3.5.0
 * - Payload encryption is now always active — remove the optional config key.
 * - Clear stale local avatar URL — avatar is now sourced from the platform embed config.
 */
function upgrade_module_3_5_0($module)
{
    Configuration::deleteByName('AI_SMART_TALK_ENCRYPT_PAYLOADS');
    Configuration::deleteByName('AI_SMART_TALK_AVATAR_URL');

    return true;
}
