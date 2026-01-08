{*
 * Copyright (c) 2024 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2024 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}

<!-- AI SmartTalk Universal Chatbot -->
<script>
    window.chatbotSettings = {
        chatModelId: '{$chatModelId|escape:'javascript':'UTF-8'}',
        integrationToken: '{$integrationToken|escape:'javascript':'UTF-8'}',
        integrationType: 'PRESTASHOP',
        apiUrl: '{$apiUrl|escape:'javascript':'UTF-8'}',
        wsUrl: '{$wsUrl|escape:'javascript':'UTF-8'}',
        cdnUrl: '{$CDN|escape:'javascript':'UTF-8'}',
        source: '{$source|escape:'javascript':'UTF-8'}',
        {if isset($userToken) && $userToken}
        userToken: '{$userToken|escape:'javascript':'UTF-8'}',
        {/if}
    };
</script>
<script 
    id="aismarttalk-chatbot"
    type="text/javascript" 
    src="{$CDN|escape:'html':'UTF-8'}/universal-chatbot.js"
    async
></script>
