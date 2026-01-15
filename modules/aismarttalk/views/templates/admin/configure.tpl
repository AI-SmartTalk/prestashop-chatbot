{*
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
 *}

<style>
.aismarttalk-config .panel {
    border-radius: 4px;
    margin-bottom: 20px;
}
.aismarttalk-config .panel-heading {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 4px 4px 0 0;
    padding: 15px 20px;
}
.aismarttalk-config .panel-heading h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}
.aismarttalk-config .panel-heading h3 i {
    margin-right: 10px;
}
.aismarttalk-config .connection-status {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 15px;
}
.aismarttalk-config .connection-status.connected {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: #fff;
}
.aismarttalk-config .connection-status.disconnected {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    color: #495057;
}
.aismarttalk-config .connection-status i {
    font-size: 24px;
    margin-right: 15px;
}
.aismarttalk-config .connection-status .status-text h4 {
    margin: 0 0 5px 0;
    font-weight: 600;
}
.aismarttalk-config .connection-status .status-text p {
    margin: 0;
    opacity: 0.9;
}
.aismarttalk-config .btn-connect {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: #fff;
    padding: 12px 30px;
    font-size: 16px;
    border-radius: 4px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.aismarttalk-config .btn-connect:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: #fff;
}
.aismarttalk-config .settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}
.aismarttalk-config .setting-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 20px;
}
.aismarttalk-config .setting-card h4 {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    display: flex;
    align-items: center;
}
.aismarttalk-config .setting-card h4 i {
    margin-right: 10px;
    color: #667eea;
}
.aismarttalk-config .sync-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}
.aismarttalk-config .advanced-toggle {
    cursor: pointer;
    padding: 15px 20px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}
.aismarttalk-config .advanced-toggle:hover {
    background: #e9ecef;
}
.aismarttalk-config .advanced-content {
    display: none;
}
.aismarttalk-config .advanced-content.show {
    display: block;
}
</style>

<div class="aismarttalk-config">
    
    {* ===== SECTION 1: CONNECTION ===== *}
    <div class="panel">
        <div class="panel-heading">
            <h3><i class="icon icon-plug"></i> {l s='AI SmartTalk Connection' mod='aismarttalk'}</h3>
        </div>
        <div class="panel-body">
            {if $isConnected}
                <div class="connection-status connected">
                    <i class="icon icon-check-circle"></i>
                    <div class="status-text">
                        <h4>{l s='Connected to AI SmartTalk' mod='aismarttalk'}</h4>
                        <p>{l s='Chat Model ID:' mod='aismarttalk'} <code style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 3px;">{$chatModelId|escape:'html':'UTF-8'}</code></p>
                    </div>
                </div>
                <a href="{$moduleLink|escape:'html':'UTF-8'}&amp;disconnectOAuth=1" class="btn btn-danger" onclick="return confirm('{l s='Are you sure you want to disconnect from AI SmartTalk?' mod='aismarttalk' js=1}');">
                    <i class="icon icon-unlink"></i> {l s='Disconnect' mod='aismarttalk'}
                </a>
            {else}
                <div class="connection-status disconnected">
                    <i class="icon icon-info-circle"></i>
                    <div class="status-text">
                        <h4>{l s='Not Connected' mod='aismarttalk'}</h4>
                        <p>{l s='Connect your store to AI SmartTalk to enable the AI chatbot.' mod='aismarttalk'}</p>
                    </div>
                </div>
                <a href="{$moduleLink|escape:'html':'UTF-8'}&amp;connectOAuth=1" class="btn btn-connect">
                    <i class="icon icon-plug"></i> {l s='Connect with AI SmartTalk' mod='aismarttalk'}
                </a>
            {/if}
        </div>
    </div>

    {if $isConnected}
    
    {* ===== SECTION 2: CHATBOT SETTINGS ===== *}
    <div class="panel">
        <div class="panel-heading">
            <h3><i class="icon icon-comments"></i> {l s='Chatbot Settings' mod='aismarttalk'}</h3>
        </div>
        <div class="panel-body">
            <form action="{$formAction|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
                <div class="settings-grid">
                    {* Chatbot Enable/Disable *}
                    <div class="setting-card">
                        <h4><i class="icon icon-power-off"></i> {l s='Chatbot Activation' mod='aismarttalk'}</h4>
                        <p class="text-muted">{l s='Enable or disable the chatbot on your store.' mod='aismarttalk'}</p>
                        <div class="form-group" style="margin-bottom: 0;">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="AI_SMART_TALK_ENABLED" id="AI_SMART_TALK_ENABLED_on" value="1" {if $chatbotEnabled}checked="checked"{/if}>
                                <label for="AI_SMART_TALK_ENABLED_on">{l s='Yes' mod='aismarttalk'}</label>
                                <input type="radio" name="AI_SMART_TALK_ENABLED" id="AI_SMART_TALK_ENABLED_off" value="0" {if !$chatbotEnabled}checked="checked"{/if}>
                                <label for="AI_SMART_TALK_ENABLED_off">{l s='No' mod='aismarttalk'}</label>
                                <a class="slide-button btn"></a>
                            </span>
                        </div>
                    </div>
                    
                    {* Chatbot Position *}
                    <div class="setting-card">
                        <h4><i class="icon icon-arrows"></i> {l s='Display Position' mod='aismarttalk'}</h4>
                        <p class="text-muted">{l s='Choose where to display the chatbot widget.' mod='aismarttalk'}</p>
                        <div class="form-group" style="margin-bottom: 0;">
                            <select name="AI_SMART_TALK_IFRAME_POSITION" class="form-control">
                                <option value="footer" {if $iframePosition == 'footer'}selected{/if}>{l s='In Footer' mod='aismarttalk'}</option>
                                <option value="before_footer" {if $iframePosition == 'before_footer'}selected{/if}>{l s='Before Footer' mod='aismarttalk'}</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="panel-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button type="submit" name="submitChatbotSettings" class="btn btn-primary">
                        <i class="icon icon-save"></i> {l s='Save Chatbot Settings' mod='aismarttalk'}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {* ===== SECTION 3: DATA SYNCHRONIZATION ===== *}
    <div class="panel">
        <div class="panel-heading">
            <h3><i class="icon icon-refresh"></i> {l s='Data Synchronization' mod='aismarttalk'}</h3>
        </div>
        <div class="panel-body">
            <form action="{$formAction|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
                <div class="settings-grid">
                    {* Product Sync *}
                    <div class="setting-card">
                        <h4><i class="icon icon-cube"></i> {l s='Product Synchronization' mod='aismarttalk'}</h4>
                        <p class="text-muted">{l s='Automatically sync products with AI SmartTalk for smart recommendations.' mod='aismarttalk'}</p>
                        <div class="form-group">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="AI_SMART_TALK_PRODUCT_SYNC" id="AI_SMART_TALK_PRODUCT_SYNC_on" value="1" {if $productSyncEnabled}checked="checked"{/if}>
                                <label for="AI_SMART_TALK_PRODUCT_SYNC_on">{l s='Yes' mod='aismarttalk'}</label>
                                <input type="radio" name="AI_SMART_TALK_PRODUCT_SYNC" id="AI_SMART_TALK_PRODUCT_SYNC_off" value="0" {if !$productSyncEnabled}checked="checked"{/if}>
                                <label for="AI_SMART_TALK_PRODUCT_SYNC_off">{l s='No' mod='aismarttalk'}</label>
                                <a class="slide-button btn"></a>
                            </span>
                        </div>
                        {if $productSyncEnabled}
                        <div class="sync-actions">
                            <a href="{$formAction|escape:'html':'UTF-8'}&amp;forceSync=true" class="btn btn-warning btn-sm">
                                <i class="icon icon-refresh"></i> {l s='Sync All Products' mod='aismarttalk'}
                            </a>
                            <a href="{$formAction|escape:'html':'UTF-8'}&amp;clean=1" class="btn btn-default btn-sm">
                                <i class="icon icon-trash"></i> {l s='Clean Deleted Products' mod='aismarttalk'}
                            </a>
                        </div>
                        {/if}
                    </div>
                    
                    {* Customer Sync *}
                    <div class="setting-card">
                        <h4><i class="icon icon-users"></i> {l s='Customer Synchronization' mod='aismarttalk'}</h4>
                        <p class="text-muted">{l s='Sync customer data with AI SmartTalk CRM.' mod='aismarttalk'}</p>
                        <div class="form-group">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="AI_SMART_TALK_CUSTOMER_SYNC" id="AI_SMART_TALK_CUSTOMER_SYNC_on" value="1" {if $customerSyncEnabled}checked="checked"{/if}>
                                <label for="AI_SMART_TALK_CUSTOMER_SYNC_on">{l s='Yes' mod='aismarttalk'}</label>
                                <input type="radio" name="AI_SMART_TALK_CUSTOMER_SYNC" id="AI_SMART_TALK_CUSTOMER_SYNC_off" value="0" {if !$customerSyncEnabled}checked="checked"{/if}>
                                <label for="AI_SMART_TALK_CUSTOMER_SYNC_off">{l s='No' mod='aismarttalk'}</label>
                                <a class="slide-button btn"></a>
                            </span>
                        </div>
                        <div class="sync-actions">
                            <a href="{$formAction|escape:'html':'UTF-8'}&amp;exportCustomers=1" class="btn btn-info btn-sm">
                                <i class="icon icon-upload"></i> {l s='Export All Customers' mod='aismarttalk'}
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button type="submit" name="submitSyncSettings" class="btn btn-primary">
                        <i class="icon icon-save"></i> {l s='Save Sync Settings' mod='aismarttalk'}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {* ===== SECTION 4: AI SMARTTALK BACKOFFICE ===== *}
    <div class="panel">
        <div class="panel-heading">
            <h3><i class="icon icon-external-link"></i> {l s='AI SmartTalk Backoffice' mod='aismarttalk'}</h3>
        </div>
        <div class="panel-body">
            <p>{l s='Access the AI SmartTalk dashboard to configure your chatbot, view conversations, and manage your AI agent.' mod='aismarttalk'}</p>
            <a href="{$backofficeUrl|escape:'html':'UTF-8'}" target="_blank" class="btn btn-primary btn-lg">
                <i class="icon icon-external-link"></i> {l s='Open AI SmartTalk Dashboard' mod='aismarttalk'}
            </a>
        </div>
    </div>

    {/if}

    {* ===== SECTION 5: ADVANCED SETTINGS ===== *}
    <div class="advanced-toggle" onclick="document.getElementById('advanced-settings').classList.toggle('show'); this.querySelector('.toggle-icon').classList.toggle('icon-chevron-down'); this.querySelector('.toggle-icon').classList.toggle('icon-chevron-up');">
        <span><i class="icon icon-cogs"></i> {l s='Advanced Settings (WhiteLabel)' mod='aismarttalk'}</span>
        <i class="icon icon-chevron-down toggle-icon"></i>
    </div>
    
    <div id="advanced-settings" class="advanced-content">
        <div class="panel">
            <div class="panel-body">
                <div class="alert alert-warning">
                    <i class="icon icon-warning"></i>
                    {l s='These settings are for whitelabel implementations. Only modify if you know what you are doing.' mod='aismarttalk'}
                </div>
                
                <form action="{$formAction|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='API Base URL' mod='aismarttalk'}</label>
                        <div class="col-lg-9">
                            <input type="text" name="AI_SMART_TALK_URL" value="{$apiUrl|escape:'html':'UTF-8'}" class="form-control">
                            <p class="help-block">{l s='Your custom API endpoint for whitelabel deployments.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='CDN Base URL' mod='aismarttalk'}</label>
                        <div class="col-lg-9">
                            <input type="text" name="AI_SMART_TALK_CDN" value="{$cdnUrl|escape:'html':'UTF-8'}" class="form-control">
                            <p class="help-block">{l s='Custom CDN for chatbot assets.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='WebSocket URL' mod='aismarttalk'}</label>
                        <div class="col-lg-9">
                            <input type="text" name="AI_SMART_TALK_WS" value="{$wsUrl|escape:'html':'UTF-8'}" class="form-control">
                            <p class="help-block">{l s='Custom WebSocket endpoint.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                    
                    <div class="panel-footer">
                        <button type="submit" name="submitWhiteLabel" class="btn btn-warning">
                            <i class="icon icon-save"></i> {l s='Save Advanced Settings' mod='aismarttalk'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

{if $isConnected}
<!-- AI SmartTalk Chatbot Embedding Code for Backoffice -->
<script>
window.chatbotSettings = {$chatbotSettings nofilter};
</script>
<script src="{$cdnUrl|escape:'html':'UTF-8'}/universal-chatbot.js" async></script>
{/if}

