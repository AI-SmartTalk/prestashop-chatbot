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

/* Chatbot Customization Styles */
.aismarttalk-config .button-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.aismarttalk-config .button-type-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
}
.aismarttalk-config .button-type-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
}
.aismarttalk-config .button-type-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}
.aismarttalk-config .button-type-card .preview {
    font-size: 24px;
    margin-bottom: 8px;
}
.aismarttalk-config .button-type-card .label {
    font-size: 12px;
    font-weight: 500;
    color: #495057;
}
.aismarttalk-config .button-type-card input[type="radio"] {
    display: none;
}

.aismarttalk-config .layout-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.aismarttalk-config .layout-grid .form-group {
    margin-bottom: 0;
}
.aismarttalk-config .layout-grid label {
    font-weight: 500;
    font-size: 13px;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

.aismarttalk-config .color-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}
.aismarttalk-config .color-section h5 {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #495057;
}
.aismarttalk-config .color-inputs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}
.aismarttalk-config .color-input-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.aismarttalk-config .color-input-group label {
    font-weight: 500;
    font-size: 13px;
    color: #495057;
    min-width: 80px;
}
.aismarttalk-config .color-input-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}
.aismarttalk-config .color-input-wrapper input[type="color"] {
    width: 40px;
    height: 40px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    padding: 2px;
}
.aismarttalk-config .color-input-wrapper input[type="text"] {
    flex: 1;
    max-width: 100px;
}
.aismarttalk-config .color-input-wrapper input[type="checkbox"] {
    margin-left: 10px;
}

.aismarttalk-config .features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}
.aismarttalk-config .feature-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.aismarttalk-config .feature-item .feature-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    font-size: 13px;
    color: #495057;
}
.aismarttalk-config .feature-item .feature-label i {
    color: #667eea;
    width: 20px;
    text-align: center;
}
.aismarttalk-config .feature-item select {
    width: auto;
    min-width: 130px;
}
.aismarttalk-config .badge-new {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 5px;
    font-weight: 600;
}

.aismarttalk-config .section-divider {
    border-top: 1px solid #e9ecef;
    margin: 25px 0;
    padding-top: 25px;
}
.aismarttalk-config .section-title {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.aismarttalk-config .section-title i {
    color: #667eea;
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

    {* ===== SECTION 3: CHATBOT CUSTOMIZATION ===== *}
    <div class="panel">
        <div class="panel-heading">
            <h3><i class="icon icon-paint-brush"></i> {l s='Chatbot Customization' mod='aismarttalk'}</h3>
        </div>
        <div class="panel-body">
            <form action="{$formAction|escape:'html':'UTF-8'}" method="post" class="form-horizontal" id="chatbot-customization-form" enctype="multipart/form-data">

                {* Button Style Section *}
                <div class="section-title">
                    <i class="icon icon-hand-pointer-o"></i> {l s='Button Style' mod='aismarttalk'}
                </div>
                <div class="button-type-grid">
                    <label class="button-type-card {if $buttonType == ''}selected{/if}">
                        <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="" {if $buttonType == ''}checked{/if}>
                        <div class="preview">API</div>
                        <div class="label">{l s='API Default' mod='aismarttalk'}</div>
                    </label>
                    <label class="button-type-card {if $buttonType == 'default'}selected{/if}">
                        <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="default" {if $buttonType == 'default'}checked{/if}>
                        <div class="preview">Chat</div>
                        <div class="label">{l s='Default' mod='aismarttalk'}</div>
                    </label>
                    <label class="button-type-card {if $buttonType == 'icon'}selected{/if}">
                        <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="icon" {if $buttonType == 'icon'}checked{/if}>
                        <div class="preview"><i class="icon icon-comments"></i></div>
                        <div class="label">{l s='Icon Only' mod='aismarttalk'}</div>
                    </label>
                    <label class="button-type-card {if $buttonType == 'avatar'}selected{/if}">
                        <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="avatar" {if $buttonType == 'avatar'}checked{/if}>
                        <div class="preview"><img src="{if $effectiveAvatarUrl}{$effectiveAvatarUrl|escape:'html':'UTF-8'}{else}https://aismarttalk.tech/images/favicons/favicon-128.png{/if}" alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%;"></div>
                        <div class="label">{l s='Avatar' mod='aismarttalk'}</div>
                    </label>
                    <label class="button-type-card {if $buttonType == 'minimal'}selected{/if}">
                        <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="minimal" {if $buttonType == 'minimal'}checked{/if}>
                        <div class="preview"><i class="icon icon-comment-o"></i></div>
                        <div class="label">{l s='Minimal' mod='aismarttalk'}</div>
                    </label>
                </div>

                {* Button Text *}
                <div class="form-group">
                    <label class="control-label">{l s='Button Text' mod='aismarttalk'}</label>
                    <input type="text" name="AI_SMART_TALK_BUTTON_TEXT" value="{$buttonText|escape:'html':'UTF-8'}" class="form-control" placeholder="{l s='Leave empty for API default (e.g., Chat)' mod='aismarttalk'}">
                    <p class="help-block">{l s='Custom text displayed on the chat button.' mod='aismarttalk'}</p>
                </div>

                {* Avatar Upload *}
                <div class="form-group avatar-upload-group" id="avatar-upload-group" style="{if $buttonType != 'avatar'}display: none;{/if}">
                    <label class="control-label">{l s='Avatar Image' mod='aismarttalk'}</label>

                    {* Show current avatar (from embed config or local upload) *}
                    {if $effectiveAvatarUrl}
                    <div class="alert alert-info" style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <img src="{$effectiveAvatarUrl|escape:'html':'UTF-8'}" alt="Current avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #667eea;">
                        <div>
                            <strong>{l s='Current Avatar' mod='aismarttalk'}</strong>
                            {if $avatarUrl}
                            <p style="margin: 0; font-size: 12px; opacity: 0.8;">{l s='Custom avatar uploaded. Upload a new image to replace it.' mod='aismarttalk'}</p>
                            {else}
                            <p style="margin: 0; font-size: 12px; opacity: 0.8;">{l s='Using avatar from AI SmartTalk. Upload an image to customize.' mod='aismarttalk'}</p>
                            {/if}
                        </div>
                    </div>
                    {/if}

                    <div class="input-group" style="max-width: 400px;">
                        <span class="input-group-addon"><i class="icon icon-upload"></i></span>
                        <input type="file" name="AI_SMART_TALK_AVATAR_FILE" id="avatar-file-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" style="padding: 6px;">
                    </div>
                    <p class="help-block">{l s='Upload an image for the chatbot avatar. Accepted formats: JPEG, PNG, GIF, WebP. Recommended size: 60x60px. Max: 5MB.' mod='aismarttalk'}</p>

                    {* Preview for new file selection *}
                    <div id="avatar-file-preview" style="margin-top: 10px; display: none;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img id="avatar-file-preview-img" src="" alt="Avatar preview" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #667eea;">
                            <span class="text-info"><i class="icon icon-eye"></i> {l s='Preview - Save to apply' mod='aismarttalk'}</span>
                        </div>
                    </div>
                </div>

                {* Layout & Position Section *}
                <div class="section-divider">
                    <div class="section-title">
                        <i class="icon icon-th-large"></i> {l s='Layout & Position' mod='aismarttalk'}
                    </div>
                    <div class="layout-grid">
                        <div class="form-group">
                            <label>{l s='Chat Window Size' mod='aismarttalk'}</label>
                            <select name="AI_SMART_TALK_CHAT_SIZE" class="form-control">
                                <option value="" {if $chatSize == ''}selected{/if}>{l s='API Default' mod='aismarttalk'}</option>
                                <option value="small" {if $chatSize == 'small'}selected{/if}>{l s='Small (350x500px)' mod='aismarttalk'}</option>
                                <option value="medium" {if $chatSize == 'medium'}selected{/if}>{l s='Medium (400x600px)' mod='aismarttalk'}</option>
                                <option value="large" {if $chatSize == 'large'}selected{/if}>{l s='Large (450x700px)' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{l s='Button Position' mod='aismarttalk'}</label>
                            <select name="AI_SMART_TALK_BUTTON_POSITION" class="form-control">
                                <option value="" {if $buttonPosition == ''}selected{/if}>{l s='API Default' mod='aismarttalk'}</option>
                                <option value="bottom-right" {if $buttonPosition == 'bottom-right'}selected{/if}>{l s='Bottom Right' mod='aismarttalk'}</option>
                                <option value="bottom-left" {if $buttonPosition == 'bottom-left'}selected{/if}>{l s='Bottom Left' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{l s='Color Mode' mod='aismarttalk'}</label>
                            <select name="AI_SMART_TALK_COLOR_MODE" class="form-control">
                                <option value="" {if $colorMode == ''}selected{/if}>{l s='API Default' mod='aismarttalk'}</option>
                                <option value="light" {if $colorMode == 'light'}selected{/if}>{l s='Light' mod='aismarttalk'}</option>
                                <option value="dark" {if $colorMode == 'dark'}selected{/if}>{l s='Dark' mod='aismarttalk'}</option>
                                <option value="auto" {if $colorMode == 'auto'}selected{/if}>{l s='Auto (System)' mod='aismarttalk'}</option>
                            </select>
                        </div>
                    </div>
                </div>

                {* Brand Colors Section *}
                <div class="section-divider">
                    <div class="section-title">
                        <i class="icon icon-tint"></i> {l s='Brand Colors' mod='aismarttalk'}
                    </div>
                    <div class="color-section">
                        <p class="text-muted" style="margin-bottom: 15px;">{l s='Customize the chatbot colors to match your brand. Check the box to override API defaults.' mod='aismarttalk'}</p>
                        <div class="color-inputs">
                            <div class="color-input-group">
                                <label>{l s='Primary Color' mod='aismarttalk'}</label>
                                <div class="color-input-wrapper">
                                    <input type="checkbox" class="color-toggle" id="toggle_primary_color" {if $primaryColor}checked{/if}>
                                    <input type="color" id="picker_primary_color" value="{if $primaryColor}{$primaryColor|escape:'html':'UTF-8'}{else}#667eea{/if}" {if !$primaryColor}disabled{/if}>
                                    <input type="text" name="AI_SMART_TALK_PRIMARY_COLOR" id="input_primary_color" class="form-control" value="{$primaryColor|escape:'html':'UTF-8'}" placeholder="#667eea" {if !$primaryColor}disabled{/if}>
                                </div>
                            </div>
                            <div class="color-input-group">
                                <label>{l s='Secondary Color' mod='aismarttalk'}</label>
                                <div class="color-input-wrapper">
                                    <input type="checkbox" class="color-toggle" id="toggle_secondary_color" {if $secondaryColor}checked{/if}>
                                    <input type="color" id="picker_secondary_color" value="{if $secondaryColor}{$secondaryColor|escape:'html':'UTF-8'}{else}#a5b4fc{/if}" {if !$secondaryColor}disabled{/if}>
                                    <input type="text" name="AI_SMART_TALK_SECONDARY_COLOR" id="input_secondary_color" class="form-control" value="{$secondaryColor|escape:'html':'UTF-8'}" placeholder="#a5b4fc" {if !$secondaryColor}disabled{/if}>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {* Features Section *}
                <div class="section-divider">
                    <div class="section-title">
                        <i class="icon icon-sliders"></i> {l s='Features' mod='aismarttalk'}
                    </div>
                    <div class="features-grid">
                        <div class="feature-item">
                            <span class="feature-label">
                                <i class="icon icon-paperclip"></i> {l s='File Attachments' mod='aismarttalk'}
                            </span>
                            <select name="AI_SMART_TALK_ENABLE_ATTACHMENT" class="form-control">
                                <option value="" {if $enableAttachment == ''}selected{/if}>{l s='API Default' mod='aismarttalk'}</option>
                                <option value="on" {if $enableAttachment == 'on'}selected{/if}>{l s='Enabled' mod='aismarttalk'}</option>
                                <option value="off" {if $enableAttachment == 'off'}selected{/if}>{l s='Disabled' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">
                                <i class="icon icon-thumbs-up"></i> {l s='Feedback' mod='aismarttalk'}
                            </span>
                            <select name="AI_SMART_TALK_ENABLE_FEEDBACK" class="form-control">
                                <option value="" {if $enableFeedback == ''}selected{/if}>{l s='API Default' mod='aismarttalk'}</option>
                                <option value="on" {if $enableFeedback == 'on'}selected{/if}>{l s='Enabled' mod='aismarttalk'}</option>
                                <option value="off" {if $enableFeedback == 'off'}selected{/if}>{l s='Disabled' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">
                                <i class="icon icon-microphone"></i> {l s='Voice Input' mod='aismarttalk'}
                            </span>
                            <select name="AI_SMART_TALK_ENABLE_VOICE_INPUT" class="form-control">
                                <option value="" {if $enableVoiceInput == ''}selected{/if}>{l s='API Default' mod='aismarttalk'}</option>
                                <option value="on" {if $enableVoiceInput == 'on'}selected{/if}>{l s='Enabled' mod='aismarttalk'}</option>
                                <option value="off" {if $enableVoiceInput == 'off'}selected{/if}>{l s='Disabled' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">
                                <i class="icon icon-phone"></i> {l s='Voice Mode' mod='aismarttalk'} <span class="badge-new">NEW</span>
                            </span>
                            <select name="AI_SMART_TALK_ENABLE_VOICE_MODE" class="form-control">
                                <option value="" {if $enableVoiceMode == ''}selected{/if}>{l s='API Default' mod='aismarttalk'}</option>
                                <option value="on" {if $enableVoiceMode == 'on'}selected{/if}>{l s='Enabled' mod='aismarttalk'}</option>
                                <option value="off" {if $enableVoiceMode == 'off'}selected{/if}>{l s='Disabled' mod='aismarttalk'}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="panel-footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button type="submit" name="submitChatbotCustomization" class="btn btn-primary">
                        <i class="icon icon-save"></i> {l s='Save Customization' mod='aismarttalk'}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {* ===== SECTION 4: DATA SYNCHRONIZATION ===== *}
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

    {* ===== SECTION 5: AI SMARTTALK BACKOFFICE ===== *}
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

    {* ===== SECTION 6: ADVANCED SETTINGS ===== *}
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Button type selection
    var buttonTypeCards = document.querySelectorAll('.button-type-card');
    var avatarUploadGroup = document.getElementById('avatar-upload-group');

    function updateAvatarUploadVisibility(selectedValue) {
        if (avatarUploadGroup) {
            if (selectedValue === 'avatar') {
                avatarUploadGroup.style.display = 'block';
            } else {
                avatarUploadGroup.style.display = 'none';
            }
        }
    }

    buttonTypeCards.forEach(function(card) {
        card.addEventListener('click', function() {
            buttonTypeCards.forEach(function(c) {
                c.classList.remove('selected');
            });
            this.classList.add('selected');
            var radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            updateAvatarUploadVisibility(radio.value);
        });
    });

    // Avatar file preview with validation
    var avatarFileInput = document.getElementById('avatar-file-input');
    var avatarFilePreview = document.getElementById('avatar-file-preview');
    var avatarFilePreviewImg = document.getElementById('avatar-file-preview-img');

    // Avatar validation constants
    var AVATAR_MAX_SIZE = 5 * 1024 * 1024; // 5MB
    var AVATAR_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function showAvatarError(message) {
        // Remove existing error
        var existingError = document.getElementById('avatar-validation-error');
        if (existingError) existingError.remove();

        // Create error element
        var errorDiv = document.createElement('div');
        errorDiv.id = 'avatar-validation-error';
        errorDiv.className = 'alert alert-danger';
        errorDiv.style.marginTop = '10px';
        errorDiv.innerHTML = '<i class="icon icon-warning"></i> ' + message;

        avatarFileInput.parentNode.parentNode.appendChild(errorDiv);
    }

    function clearAvatarError() {
        var existingError = document.getElementById('avatar-validation-error');
        if (existingError) existingError.remove();
    }

    if (avatarFileInput && avatarFilePreview && avatarFilePreviewImg) {
        avatarFileInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            clearAvatarError();

            if (file) {
                // Validate file type
                if (AVATAR_ALLOWED_TYPES.indexOf(file.type) === -1) {
                    showAvatarError('{l s='Invalid file type. Allowed formats: JPEG, PNG, GIF, WebP' mod='aismarttalk' js=1}');
                    avatarFileInput.value = '';
                    avatarFilePreview.style.display = 'none';
                    avatarFilePreviewImg.src = '';
                    return;
                }

                // Validate file size
                if (file.size > AVATAR_MAX_SIZE) {
                    showAvatarError('{l s='File too large. Maximum size: 5MB. Your file:' mod='aismarttalk' js=1} ' + formatFileSize(file.size));
                    avatarFileInput.value = '';
                    avatarFilePreview.style.display = 'none';
                    avatarFilePreviewImg.src = '';
                    return;
                }

                // Show preview
                var reader = new FileReader();
                reader.onload = function(e) {
                    avatarFilePreviewImg.src = e.target.result;
                    avatarFilePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                avatarFilePreview.style.display = 'none';
                avatarFilePreviewImg.src = '';
            }
        });
    }

    // Color toggle functionality
    function setupColorToggle(toggleId, pickerId, inputId) {
        var toggle = document.getElementById(toggleId);
        var picker = document.getElementById(pickerId);
        var input = document.getElementById(inputId);

        if (!toggle || !picker || !input) return;

        toggle.addEventListener('change', function() {
            if (this.checked) {
                picker.disabled = false;
                input.disabled = false;
                if (!input.value) {
                    input.value = picker.value;
                }
            } else {
                picker.disabled = true;
                input.disabled = true;
                input.value = '';
            }
        });

        picker.addEventListener('input', function() {
            input.value = this.value;
        });

        input.addEventListener('input', function() {
            if (/^#[a-fA-F0-9]{6}$/.test(this.value)) {
                picker.value = this.value;
            }
        });
    }

    setupColorToggle('toggle_primary_color', 'picker_primary_color', 'input_primary_color');
    setupColorToggle('toggle_secondary_color', 'picker_secondary_color', 'input_secondary_color');

    // Clear disabled color inputs on form submit
    var customizationForm = document.getElementById('chatbot-customization-form');
    if (customizationForm) {
        customizationForm.addEventListener('submit', function() {
            var primaryToggle = document.getElementById('toggle_primary_color');
            var secondaryToggle = document.getElementById('toggle_secondary_color');
            var primaryInput = document.getElementById('input_primary_color');
            var secondaryInput = document.getElementById('input_secondary_color');

            if (primaryToggle && !primaryToggle.checked && primaryInput) {
                primaryInput.disabled = false;
                primaryInput.value = '';
            }
            if (secondaryToggle && !secondaryToggle.checked && secondaryInput) {
                secondaryInput.disabled = false;
                secondaryInput.value = '';
            }
        });
    }
});
</script>

{if $isConnected}
<!-- AI SmartTalk Chatbot Embedding Code for Backoffice -->
<script>
window.chatbotSettings = JSON.parse(atob("{$chatbotSettingsEncoded|escape:'html':'UTF-8'}"));
</script>
<script src="{$cdnUrl|escape:'html':'UTF-8'}/universal-chatbot.js" async></script>
{/if}
