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
/* ================================================
   AI SMART TALK - MODERN ADMIN INTERFACE
   ================================================ */

/* Reset & Base */
.ast-app {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: #f5f7fb;
    min-height: 100vh;
    margin: -20px;
    padding: 0;
}

/* Header */
.ast-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 24px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}
.ast-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
.ast-logo {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.ast-header-title h1 {
    margin: 0;
    color: #fff;
    font-size: 22px;
    font-weight: 700;
}
.ast-header-title p {
    margin: 4px 0 0;
    color: rgba(255,255,255,0.8);
    font-size: 13px;
}
.ast-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.ast-status-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.ast-status-badge.connected {
    background: rgba(16, 185, 129, 0.2);
    color: #fff;
}
.ast-status-badge.disconnected {
    background: rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.9);
}
.ast-status-badge i {
    font-size: 10px;
}
.ast-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: ast-pulse 2s infinite;
}
@keyframes ast-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.ast-header-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.ast-header-btn.primary {
    background: #fff;
    color: #667eea;
}
.ast-header-btn.primary:hover {
    background: #f0f0f0;
    transform: translateY(-1px);
}
.ast-header-btn.danger {
    background: rgba(239, 68, 68, 0.2);
    color: #fff;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.ast-header-btn.danger:hover {
    background: rgba(239, 68, 68, 0.3);
}

/* Main Container */
.ast-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px 40px;
}

/* Tabs Navigation */
.ast-tabs {
    display: flex;
    gap: 4px;
    background: #fff;
    padding: 8px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-top: 20px;
    position: relative;
    z-index: 10;
    flex-wrap: wrap;
}
.ast-tab {
    flex: 1;
    min-width: 120px;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.ast-tab:hover {
    background: #f1f5f9;
    color: #334155;
}
.ast-tab.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}
.ast-tab i {
    font-size: 16px;
}
.ast-tab-badge {
    background: rgba(255,255,255,0.3);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}
.ast-tab.active .ast-tab-badge {
    background: rgba(255,255,255,0.3);
}

/* Tab Panels */
.ast-panel {
    display: none;
    animation: ast-fadeIn 0.3s ease;
}
.ast-panel.active {
    display: block;
}
@keyframes ast-fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Content Area */
.ast-content {
    margin-top: 24px;
}

/* Cards */
.ast-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 20px;
    overflow: hidden;
}
.ast-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ast-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ast-card-header h3 i {
    color: #667eea;
}
.ast-card-body {
    padding: 24px;
}

/* Grid Layout */
.ast-grid {
    display: grid;
    gap: 20px;
}
.ast-grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}
.ast-grid-3 {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

/* Form Elements */
.ast-form-group {
    margin-bottom: 20px;
}
.ast-form-group:last-child {
    margin-bottom: 0;
}
.ast-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
}
.ast-help {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 6px;
}
.ast-input, .ast-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s;
    background: #fff;
}
.ast-input:focus, .ast-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

/* Toggle Switch */
.ast-toggle-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    background: #f8fafc;
    border-radius: 12px;
    transition: all 0.2s;
}
.ast-toggle-card:hover {
    background: #f1f5f9;
}
.ast-toggle-info h4 {
    margin: 0 0 4px;
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}
.ast-toggle-info p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}
.ast-switch {
    position: relative;
    width: 52px;
    height: 28px;
}
.ast-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.ast-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #cbd5e1;
    border-radius: 28px;
    transition: 0.3s;
}
.ast-switch-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.ast-switch input:checked + .ast-switch-slider {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.ast-switch input:checked + .ast-switch-slider:before {
    transform: translateX(24px);
}

/* Buttons */
.ast-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.ast-btn-primary,
a.ast-btn-primary,
a.ast-btn-primary:visited {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff !important;
}
.ast-btn-primary:hover,
a.ast-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: #fff !important;
}
.ast-btn-secondary {
    background: #f1f5f9;
    color: #475569;
}
.ast-btn-secondary:hover {
    background: #e2e8f0;
}
.ast-btn-warning,
a.ast-btn-warning,
a.ast-btn-warning:visited {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff !important;
}
a.ast-btn-warning:hover {
    color: #fff !important;
}
.ast-btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

/* Quick Actions */
.ast-quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
}

/* Stat Cards */
.ast-stat-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.ast-stat-icon {
    font-size: 32px;
    margin-bottom: 12px;
}
.ast-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
}
.ast-stat-label {
    font-size: 13px;
    color: #64748b;
    margin-top: 4px;
}

/* Empty State */
.ast-empty {
    text-align: center;
    padding: 60px 20px;
}
.ast-empty-icon {
    font-size: 64px;
    margin-bottom: 20px;
}
.ast-empty h3 {
    margin: 0 0 8px;
    font-size: 20px;
    color: #1e293b;
}
.ast-empty p {
    margin: 0 0 24px;
    color: #64748b;
}

/* Filter Section */
.ast-filter-badge {
    background: #10b981;
    color: #fff;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

/* Product Type Chips */
.ast-types-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 20px;
    padding: 16px 20px;
    background: #f8fafc;
    border-radius: 10px;
    flex-wrap: wrap;
}
.ast-types-label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    white-space: nowrap;
}
.ast-types-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.ast-type-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
    color: #64748b;
    user-select: none;
}
.ast-type-chip:hover {
    border-color: #cbd5e1;
}
.ast-type-chip.checked {
    border-color: #667eea;
    background: #f5f3ff;
    color: #4338ca;
}
.ast-type-chip input[type="checkbox"] {
    width: 14px;
    height: 14px;
    accent-color: #667eea;
    margin: 0;
}
.ast-type-chip-label {
    font-weight: 500;
}
.ast-type-chip-count {
    font-size: 11px;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 1px 7px;
    border-radius: 10px;
}
.ast-type-chip.checked .ast-type-chip-count {
    background: #e0e7ff;
    color: #6366f1;
}

/* Filter Warning */
.ast-filter-warning {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 10px 14px;
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-radius: 8px;
    font-size: 13px;
    color: #92400e;
}

/* Category Mode Selector */
.ast-category-mode-selector {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
}
.ast-mode-option {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.ast-mode-option:hover {
    border-color: #cbd5e1;
}
.ast-mode-option.active {
    border-color: #667eea;
    background: #f5f3ff;
}
.ast-mode-option input[type="radio"] {
    margin-top: 2px;
    accent-color: #667eea;
}
.ast-mode-content {
    flex: 1;
}
.ast-mode-content strong {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 2px;
}
.ast-mode-content small {
    font-size: 12px;
    color: #94a3b8;
}
@media (max-width: 768px) {
    .ast-category-mode-selector {
        flex-direction: column;
    }
}

/* Category Selector */
.ast-category-box {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    margin-top: 12px;
}
.ast-category-header {
    display: flex;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
}
.ast-category-search {
    flex: 1;
    min-width: 200px;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
}
.ast-category-list {
    max-height: 280px;
    overflow-y: auto;
    padding: 8px;
}
.ast-category-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;
}
.ast-category-item:hover {
    background: #f1f5f9;
}
.ast-category-item input {
    width: 16px;
    height: 16px;
    accent-color: #667eea;
}
.ast-category-item .name {
    flex: 1;
    font-size: 13px;
    color: #334155;
}
.ast-category-item .count {
    font-size: 11px;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 10px;
}
.ast-category-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    font-size: 13px;
    color: #64748b;
}

/* Category Tree */
.ast-category-tree {
    max-height: 350px;
    overflow-y: auto;
    padding: 12px;
}
.ast-tree-node {
    display: flex;
    align-items: center;
    padding: 6px 8px;
    border-radius: 6px;
    transition: background 0.15s;
}
.ast-tree-node:hover {
    background: #f1f5f9;
}
.ast-tree-toggle {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #94a3b8;
    font-size: 10px;
    transition: transform 0.2s;
    user-select: none;
    flex-shrink: 0;
}
.ast-tree-toggle.expanded {
    transform: rotate(90deg);
}
.ast-tree-spacer {
    width: 20px;
    flex-shrink: 0;
}
.ast-tree-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    flex: 1;
    min-width: 0;
}
.ast-tree-checkbox {
    width: 16px;
    height: 16px;
    accent-color: #667eea;
    flex-shrink: 0;
}
.ast-tree-checkbox:indeterminate {
    opacity: 0.6;
}
.ast-tree-name {
    font-size: 13px;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ast-tree-count {
    font-size: 11px;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
}
.ast-tree-node.hidden {
    display: none !important;
}

/* Customization Section */
.ast-color-picker {
    display: flex;
    align-items: center;
    gap: 12px;
}
.ast-color-picker input[type="color"] {
    width: 48px;
    height: 48px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    padding: 4px;
}
.ast-color-picker input[type="text"] {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    font-family: monospace;
}

/* Button Type Selector */
.ast-button-types {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
}
.ast-button-type {
    padding: 16px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
}
.ast-button-type:hover {
    border-color: #667eea;
}
.ast-button-type.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}
.ast-button-type input {
    display: none;
}
.ast-button-type .preview {
    font-size: 24px;
    margin-bottom: 8px;
}
.ast-button-type .label {
    font-size: 12px;
    font-weight: 500;
    color: #64748b;
}

/* Feature Toggles */
.ast-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.ast-feature-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: #f8fafc;
    border-radius: 10px;
}
.ast-feature-toggle .label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
}
.ast-feature-toggle .label i {
    color: #667eea;
}

/* Webhooks Section */
.ast-webhooks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
}
.ast-webhook-card {
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    transition: all 0.2s;
}
.ast-webhook-card:hover {
    border-color: #cbd5e1;
    background: #fafafa;
}
.ast-webhook-card.active {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.03);
}
.ast-webhook-content {
    flex: 1;
}
.ast-webhook-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.ast-webhook-header .ast-switch {
    margin-left: auto;
    flex-shrink: 0;
}
.ast-webhook-icon {
    font-size: 28px;
}
.ast-webhook-info {
    flex: 1;
}
.ast-webhook-info h5 {
    margin: 0 0 4px;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}
.ast-webhook-info p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    line-height: 1.4;
}
.ast-webhook-payload {
    background: #f8fafc;
    border-radius: 8px;
    padding: 10px 12px;
}
.ast-webhook-payload-label {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ast-webhook-payload-fields {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Code', monospace;
}
.ast-doc-section h4 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 600;
    color: #334155;
}
.ast-example-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}
.ast-example-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #f8fafc;
    border-radius: 10px;
    padding: 14px;
}
.ast-example-icon {
    font-size: 24px;
}
.ast-example-card strong {
    display: block;
    font-size: 13px;
    color: #334155;
    margin-bottom: 4px;
}
.ast-example-card p {
    margin: 0;
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
}
.ast-tab-badge-success {
    background: rgba(16, 185, 129, 0.15) !important;
    color: #059669 !important;
}

/* Skills Section */
.ast-skills-section { margin-bottom: 28px; }
.ast-skills-section-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 20px; flex-wrap: wrap; gap: 16px;
}
.ast-skills-section-title { display: flex; align-items: flex-start; gap: 14px; }
.ast-skills-section-icon { font-size: 28px; line-height: 1; }
.ast-skills-section-title h3 { margin: 0 0 4px; font-size: 18px; font-weight: 700; color: #1e293b; }
.ast-skills-section-title p { margin: 0; font-size: 13px; color: #64748b; }
.ast-skills-stats { display: flex; gap: 12px; }
.ast-skill-stat { display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: #f8fafc; border-radius: 8px; font-size: 13px; font-weight: 500; }
.ast-skill-stat.active { color: #059669; background: rgba(16, 185, 129, 0.1); }
.ast-skill-stat.inactive { color: #64748b; }

/* Update Banner */
.ast-update-banner {
    display: flex; align-items: center; padding: 14px 18px; gap: 14px;
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(245, 158, 11, 0.06) 100%);
    border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 12px; margin-bottom: 20px;
}
.ast-update-banner-icon { font-size: 24px; }
.ast-update-banner-text { display: flex; flex-direction: column; gap: 2px; }
.ast-update-banner-text strong { color: #b45309; font-size: 14px; }
.ast-update-banner-text span { color: #92400e; font-size: 13px; }

/* Skills Container */
.ast-skills-container {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;
}

/* Loading/Empty States */
.ast-skills-loading, .ast-marketplace-loading {
    display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px; gap: 16px;
}
.ast-skills-spinner {
    width: 36px; height: 36px; border: 3px solid rgba(99, 102, 241, 0.2);
    border-top-color: #6366f1; border-radius: 50%; animation: ast-spin 0.8s linear infinite;
}
@keyframes ast-spin { to { transform: rotate(360deg); } }
.ast-skills-loading p, .ast-marketplace-loading p { color: #64748b; font-size: 14px; margin: 0; }
.ast-skills-empty, .ast-marketplace-empty { text-align: center; padding: 48px 24px; }
.ast-skills-empty .ast-empty-icon, .ast-marketplace-empty .ast-empty-icon { font-size: 48px; display: block; margin-bottom: 16px; }
.ast-skills-empty h4, .ast-marketplace-empty h4 { margin: 0 0 8px; font-size: 18px; font-weight: 600; color: #1e293b; }
.ast-skills-empty p, .ast-marketplace-empty p { margin: 0; color: #64748b; font-size: 14px; }

/* Skills Grid (My Skills) */
.ast-skills-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px;
}
.ast-skill-card {
    display: flex; flex-direction: column; gap: 10px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; transition: all 0.2s;
}
.ast-skill-card:hover { border-color: #667eea; box-shadow: 0 4px 16px rgba(102, 126, 234, 0.12); transform: translateY(-2px); }
.ast-skill-card.has-update { border-color: rgba(251, 191, 36, 0.5); background: rgba(251, 191, 36, 0.03); }
.ast-skill-card.paused { opacity: 0.7; }
.ast-skill-card.is-manual { border-left: 3px solid #ec4899; }

/* Skill Type Badge */
.ast-skill-type-badge {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px;
    border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;
}
.ast-skill-type-badge.conversation-tool { background: rgba(99, 102, 241, 0.1); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.2); }
.ast-skill-type-badge.webhook { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }
.ast-skill-type-badge.smartform { background: rgba(59, 130, 246, 0.1); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.2); }
.ast-skill-type-badge.sequence { background: rgba(168, 85, 247, 0.1); color: #7c3aed; border: 1px solid rgba(168, 85, 247, 0.2); }
.ast-skill-type-badge.navigation { background: rgba(20, 184, 166, 0.1); color: #0d9488; border: 1px solid rgba(20, 184, 166, 0.2); }
.ast-skill-type-badge.chat { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
.ast-skill-type-badge.scheduled { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }
.ast-skill-type-badge.dictaphone { background: rgba(236, 72, 153, 0.1); color: #db2777; border: 1px solid rgba(236, 72, 153, 0.2); }
.ast-skill-type-badge.custom { background: rgba(236, 72, 153, 0.1); color: #db2777; border: 1px solid rgba(236, 72, 153, 0.2); }
.ast-skill-type-badge.default { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

/* Skill Header Row */
.ast-skill-header-row { display: flex; align-items: center; gap: 10px; }
.ast-skill-header-row .ast-skill-icon { font-size: 28px; line-height: 1; flex-shrink: 0; }
.ast-skill-header-row .ast-skill-name { flex: 1; margin: 0; font-size: 15px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ast-skill-update-badge { font-size: 14px; animation: ast-pulse 2s infinite; }
@keyframes ast-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

/* Skill Meta */
.ast-skill-meta { display: flex; align-items: center; gap: 12px; }
.ast-skill-version { font-size: 11px; color: #94a3b8; font-family: 'Monaco', 'Consolas', monospace; }
.ast-skill-status { font-size: 12px; }
.ast-skill-status.active { color: #059669; }
.ast-skill-status.paused { color: #64748b; }
.ast-skill-desc { margin: 0; font-size: 13px; color: #64748b; line-height: 1.5; }

/* Skill Actions */
.ast-skill-actions {
    display: flex; gap: 8px; justify-content: flex-end;
    padding-top: 10px; border-top: 1px solid #f1f5f9; margin-top: auto;
}
.ast-skill-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.2s; border: 1px solid transparent; text-decoration: none;
}
.ast-skill-btn-configure { background: rgba(99, 102, 241, 0.1); color: #6366f1; border-color: rgba(99, 102, 241, 0.2); }
.ast-skill-btn-configure:hover { background: rgba(99, 102, 241, 0.2); color: #4f46e5; }
.ast-skill-btn-update { background: rgba(251, 191, 36, 0.1); color: #b45309; border-color: rgba(251, 191, 36, 0.3); }
.ast-skill-btn-update:hover { background: rgba(251, 191, 36, 0.2); color: #92400e; }
.ast-skill-btn-remove { background: rgba(239, 68, 68, 0.08); color: #dc2626; border-color: rgba(239, 68, 68, 0.2); padding: 8px 10px; }
.ast-skill-btn-remove:hover { background: rgba(239, 68, 68, 0.15); }

/* Marketplace Section */
.ast-marketplace-section {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px;
}

/* Marketplace Toolbar */
.ast-marketplace-toolbar {
    display: flex; flex-direction: column; gap: 14px;
    padding-bottom: 18px; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0;
}
.ast-marketplace-search { position: relative; }
.ast-search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; opacity: 0.5; }
.ast-marketplace-search input {
    width: 100%; padding: 12px 16px 12px 42px; background: #f8fafc;
    border: 1px solid #e2e8f0; border-radius: 10px; color: #1e293b; font-size: 14px; transition: all 0.2s;
}
.ast-marketplace-search input:focus { outline: none; border-color: #6366f1; background: #fff; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }
.ast-marketplace-search input::placeholder { color: #94a3b8; }
.ast-marketplace-filters { display: flex; flex-wrap: wrap; gap: 10px; }
.ast-filter-item { display: flex; flex-direction: column; gap: 4px; }
.ast-filter-item label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
.ast-filter-select {
    padding: 8px 14px; background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 8px; color: #334155; font-size: 13px; cursor: pointer; transition: all 0.2s; min-width: 130px;
}
.ast-filter-select:hover { border-color: #cbd5e1; }
.ast-filter-select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12); }

/* Marketplace Count */
.ast-marketplace-count { font-size: 13px; color: #64748b; margin-bottom: 14px; }
.ast-marketplace-count strong { color: #1e293b; }

/* Marketplace Grid */
.ast-marketplace-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
.ast-marketplace-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
    overflow: hidden; transition: all 0.25s; display: flex; flex-direction: column;
}
.ast-marketplace-card:hover { border-color: rgba(99, 102, 241, 0.5); transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
.ast-marketplace-card.installed { border-color: rgba(16, 185, 129, 0.3); }
.ast-marketplace-card.has-update { border-color: rgba(251, 191, 36, 0.4); }

.ast-marketplace-card-header { display: flex; align-items: flex-start; justify-content: space-between; padding: 18px 18px 0; }
.ast-marketplace-card-badges { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; }
.ast-badge-installed { font-size: 11px; padding: 3px 8px; background: rgba(16, 185, 129, 0.1); color: #059669; border-radius: 6px; font-weight: 600; }
.ast-badge-update { font-size: 11px; padding: 3px 8px; background: rgba(251, 191, 36, 0.1); color: #b45309; border-radius: 6px; font-weight: 600; animation: ast-pulse 2s infinite; }

.ast-marketplace-card-body { padding: 14px 18px; flex: 1; }
.ast-marketplace-card-title-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.ast-marketplace-card-title-row .ast-marketplace-card-icon { font-size: 32px; line-height: 1; }
.ast-marketplace-card-title-row .ast-marketplace-card-title { flex: 1; margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; }
.ast-marketplace-card-details {
    display: flex; flex-direction: column; gap: 6px; padding: 10px;
    background: #f8fafc; border-radius: 8px; margin-bottom: 10px;
}
.ast-detail-row { display: flex; gap: 8px; font-size: 12px; line-height: 1.4; }
.ast-detail-label { color: #94a3b8; font-weight: 600; white-space: nowrap; min-width: 55px; }
.ast-detail-value { color: #475569; flex: 1; }
.ast-marketplace-card-platforms { display: flex; flex-wrap: wrap; gap: 5px; }
.ast-platform-tag { font-size: 10px; padding: 3px 7px; background: rgba(99, 102, 241, 0.08); color: #6366f1; border-radius: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
.ast-platform-tag.missing { background: rgba(239, 68, 68, 0.08); color: #dc2626; }

.ast-marketplace-card-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; background: #f8fafc; border-top: 1px solid #f1f5f9;
}
.ast-marketplace-card-stats { display: flex; gap: 10px; }
.ast-stat-item { font-size: 12px; color: #64748b; }
.ast-version-tag { font-family: 'Monaco', 'Consolas', monospace; font-size: 11px; }

/* Install Button */
.ast-skill-btn-install {
    padding: 8px 16px; background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 8px; color: #fff; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.ast-skill-btn-install:hover { transform: scale(1.04); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35); }
.ast-skill-btn-install:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
.ast-skill-missing { font-size: 12px; color: #b45309; padding: 6px 12px; background: rgba(251, 191, 36, 0.08); border-radius: 6px; }

/* Pagination */
.ast-marketplace-pagination {
    display: flex; align-items: center; justify-content: center; gap: 14px;
    padding-top: 20px; margin-top: 20px; border-top: 1px solid #e2e8f0;
}
.ast-pagination-btn {
    display: flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; padding: 0; background: #f8fafc;
    border: 1px solid #e2e8f0; border-radius: 10px; color: #475569;
    cursor: pointer; transition: all 0.2s; font-size: 18px;
}
.ast-pagination-btn:hover:not(:disabled) { background: rgba(99, 102, 241, 0.1); border-color: #6366f1; color: #6366f1; }
.ast-pagination-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.ast-page-info { font-size: 14px; color: #64748b; min-width: 60px; text-align: center; font-weight: 500; }

@media (max-width: 768px) {
    .ast-filter-item { flex: 1; min-width: calc(50% - 5px); }
    .ast-skills-grid, .ast-marketplace-grid { grid-template-columns: 1fr; }
}

/* Loading Spinner */
.ast-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    gap: 16px;
}
.ast-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e2e8f0;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: ast-spin 0.8s linear infinite;
}
@keyframes ast-spin {
    to { transform: rotate(360deg); }
}

/* Advanced Settings */
.ast-advanced-form .ast-form-group {
    max-width: 500px;
}

/* Responsive */
@media (max-width: 768px) {
    .ast-header {
        padding: 20px;
    }
    .ast-tabs {
        margin-top: 16px;
    }
    .ast-tab {
        min-width: 80px;
        padding: 12px 14px;
        font-size: 12px;
    }
    .ast-tab span {
        display: none;
    }
    .ast-container {
        padding: 0 16px 30px;
    }
    .ast-card-body {
        padding: 16px;
    }
}

/* ===== USAGE & CREDITS SECTION ===== */

/* Badge styles */
.ast-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ast-badge-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
}
.ast-badge-secondary {
    background: #e5e7eb;
    color: #6b7280;
}
.ast-badge-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
}
.ast-badge-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
}

/* Alert styles */
.ast-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.5;
}
.ast-alert i {
    font-size: 18px;
    margin-top: 2px;
}
.ast-alert-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
}
.ast-alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.ast-alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

/* Usage Grid */
.ast-usage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

/* Usage Item */
.ast-usage-item {
    background: #f8fafc;
    border-radius: 10px;
    padding: 16px;
    border: 1px solid #e2e8f0;
}
.ast-usage-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.ast-usage-label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ast-usage-label i {
    color: #667eea;
}
.ast-usage-value {
    font-size: 12px;
    color: #64748b;
}

/* Progress Bar */
.ast-progress-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 6px;
}
.ast-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}
.ast-progress-fill.warning {
    background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
}
.ast-progress-fill.danger {
    background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}
.ast-usage-percent {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
}

/* Plan Actions */
.ast-plan-info {
    flex: 1;
}
.ast-plan-buttons .ast-btn {
    white-space: nowrap;
}
.ast-btn-success,
a.ast-btn-success,
a.ast-btn-success:visited {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff !important;
}
.ast-btn-success:hover,
a.ast-btn-success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff !important;
}

/* Responsive for usage section */
@media (max-width: 768px) {
    .ast-usage-grid {
        grid-template-columns: 1fr 1fr;
    }
    .ast-plan-actions {
        flex-direction: column;
        align-items: stretch !important;
    }
    .ast-plan-buttons {
        justify-content: center;
    }
}
@media (max-width: 480px) {
    .ast-usage-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="ast-app">
    {* ===== HEADER ===== *}
    <div class="ast-header">
        <div class="ast-header-left">
            <div class="ast-logo">🤖</div>
            <div class="ast-header-title">
                <h1>AI SmartTalk</h1>
                <p>{l s='Intelligent chatbot for your PrestaShop store' mod='aismarttalk'}</p>
            </div>
        </div>
        <div class="ast-header-right">
            {if $isConnected}
                <div class="ast-status-badge connected">
                    <span class="ast-status-dot"></span>
                    {l s='Connected' mod='aismarttalk'}
                </div>
                <a href="{$backofficeUrl|escape:'html':'UTF-8'}" target="_blank" class="ast-header-btn primary">
                    <i class="icon icon-external-link"></i>
                    {l s='Dashboard' mod='aismarttalk'}
                </a>
                <a href="{$moduleLink|escape:'html':'UTF-8'}&amp;disconnectOAuth=1" class="ast-header-btn danger" onclick="return confirm('{l s='Disconnect from AI SmartTalk?' mod='aismarttalk' js=1}');">
                    <i class="icon icon-unlink"></i>
                </a>
            {else}
                <div class="ast-status-badge disconnected">
                    <i class="icon icon-circle-o"></i>
                    {l s='Not connected' mod='aismarttalk'}
                </div>
                <a href="{$moduleLink|escape:'html':'UTF-8'}&amp;connectOAuth=1" class="ast-header-btn primary">
                    <i class="icon icon-plug"></i>
                    {l s='Connect' mod='aismarttalk'}
                </a>
            {/if}
        </div>
    </div>

    <div class="ast-container">
        {if $isConnected}
        {* ===== TABS NAVIGATION ===== *}
        <div class="ast-tabs" role="tablist">
            <button class="ast-tab active" data-tab="chatbot" role="tab">
                <i class="icon icon-comments"></i>
                <span>{l s='Chatbot' mod='aismarttalk'}</span>
            </button>
            <button class="ast-tab" data-tab="appearance" role="tab">
                <i class="icon icon-paint-brush"></i>
                <span>{l s='Appearance' mod='aismarttalk'}</span>
            </button>
            <button class="ast-tab" data-tab="sync" role="tab">
                <i class="icon icon-refresh"></i>
                <span>{l s='Sync' mod='aismarttalk'}</span>
                {if $syncFilterHasActiveFilters}<span class="ast-tab-badge">{l s='Filtered' mod='aismarttalk'}</span>{/if}
            </button>
            <button class="ast-tab" data-tab="webhooks" role="tab">
                <i class="icon icon-flash"></i>
                <span>{l s='Webhooks' mod='aismarttalk'}</span>
                {if $webhooksEnabledTriggers|count > 0}<span class="ast-tab-badge ast-tab-badge-success">{$webhooksEnabledTriggers|count} {l s='active' mod='aismarttalk'}</span>{/if}
            </button>
            <button class="ast-tab" data-tab="skills" role="tab">
                <i class="icon icon-magic"></i>
                <span>{l s='AI Skills' mod='aismarttalk'}</span>
            </button>
            <button class="ast-tab" data-tab="settings" role="tab">
                <i class="icon icon-cog"></i>
                <span>{l s='Settings' mod='aismarttalk'}</span>
            </button>
        </div>

        <div class="ast-content">
            {* ===== TAB 1: CHATBOT ===== *}
            <div class="ast-panel active" id="panel-chatbot" role="tabpanel">
                <div class="ast-grid ast-grid-2">
                    {* Chatbot Activation *}
                    <div class="ast-card">
                        <div class="ast-card-header">
                            <h3><i class="icon icon-power-off"></i> {l s='Chatbot Status' mod='aismarttalk'}</h3>
                        </div>
                        <div class="ast-card-body">
                            <form action="{$formAction|escape:'html':'UTF-8'}" method="post">
                                <div class="ast-toggle-card">
                                    <div class="ast-toggle-info">
                                        <h4>{l s='Enable Chatbot' mod='aismarttalk'}</h4>
                                        <p>{l s='Display the AI assistant on your store' mod='aismarttalk'}</p>
                                    </div>
                                    <label class="ast-switch">
                                        <input type="checkbox" name="AI_SMART_TALK_ENABLED" value="1" {if $chatbotEnabled}checked{/if} onchange="this.form.submit()">
                                        <span class="ast-switch-slider"></span>
                                    </label>
                                    <input type="hidden" name="submitToggleChatbot" value="1">
                                </div>
                            </form>

                            <div class="ast-form-group" style="margin-top: 20px;">
                                <form action="{$formAction|escape:'html':'UTF-8'}" method="post">
                                    <label class="ast-label">{l s='Display Position' mod='aismarttalk'}</label>
                                    <select name="AI_SMART_TALK_IFRAME_POSITION" class="ast-select" onchange="this.form.submit()">
                                        <option value="footer" {if $iframePosition == 'footer'}selected{/if}>{l s='Footer (recommended)' mod='aismarttalk'}</option>
                                        <option value="before_footer" {if $iframePosition == 'before_footer'}selected{/if}>{l s='Before Footer' mod='aismarttalk'}</option>
                                    </select>
                                    <input type="hidden" name="submitIframePosition" value="1">
                                </form>
                            </div>
                        </div>
                    </div>

                    {* Quick Info & Dashboard Link *}
                    <div class="ast-card">
                        <div class="ast-card-header">
                            <h3><i class="icon icon-bar-chart"></i> {l s='Overview' mod='aismarttalk'}</h3>
                        </div>
                        <div class="ast-card-body">
                            <div class="ast-stat-card" style="margin-bottom: 20px;">
                                <div class="ast-stat-icon">💬</div>
                                <div class="ast-stat-label">{l s='Chat Model' mod='aismarttalk'}</div>
                                <div class="ast-stat-value" style="font-size: 14px; word-break: break-all;">{$chatModelId|escape:'html':'UTF-8'|truncate:20:'...'}</div>
                            </div>
                            <a href="{$backofficeUrl|escape:'html':'UTF-8'}" target="_blank" class="ast-btn ast-btn-primary" style="width: 100%; justify-content: center;">
                                <i class="icon icon-external-link"></i>
                                {l s='Open AI SmartTalk Dashboard' mod='aismarttalk'}
                            </a>
                        </div>
                    </div>
                </div>

                {* Usage & Credits Section *}
                {if $planUsage}
                <div class="ast-card" style="margin-top: 20px;">
                    <div class="ast-card-header">
                        <h3><i class="icon icon-dashboard"></i> {l s='Usage & Credits' mod='aismarttalk'}</h3>
                        {if $planUsage.plan}
                            <span class="ast-badge {if $planUsage.plan.isFreePlan}ast-badge-secondary{else}ast-badge-success{/if}">
                                {$planUsage.plan.name|escape:'html':'UTF-8'}
                            </span>
                        {/if}
                    </div>
                    <div class="ast-card-body">
                        {* Usage Warning Alert *}
                        {if $planUsage.overallUsagePercentage > 100}
                        <div class="ast-alert ast-alert-danger" style="margin-bottom: 20px;">
                            <i class="icon icon-warning"></i>
                            <strong>{l s='Critical:' mod='aismarttalk'}</strong> {l s='You have exceeded your plan limits. Upgrade now to avoid service interruption.' mod='aismarttalk'}
                        </div>
                        {elseif $planUsage.overallUsagePercentage >= 80}
                        <div class="ast-alert ast-alert-warning" style="margin-bottom: 20px;">
                            <i class="icon icon-warning"></i>
                            {if $planUsage.overallUsagePercentage >= 100}
                                <strong>{l s='Heads up:' mod='aismarttalk'}</strong> {l s='You have reached your plan limits. Upgrade to get more resources.' mod='aismarttalk'}
                            {else}
                                <strong>{l s='Warning:' mod='aismarttalk'}</strong> {l s='You have used %s of your plan limits. Consider upgrading soon.' sprintf=[$planUsage.overallUsagePercentage|cat:'%'] mod='aismarttalk'}
                            {/if}
                        </div>
                        {/if}

                        {* Usage Bars *}
                        <div class="ast-usage-grid">
                            {* Tokens/Messages Usage *}
                            <div class="ast-usage-item">
                                <div class="ast-usage-header">
                                    <span class="ast-usage-label">
                                        <i class="icon icon-comment"></i> {l s='AI Messages' mod='aismarttalk'}
                                    </span>
                                    <span class="ast-usage-value">
                                        {$planUsage.usage.tokens.used|number_format:0:',':' '} / {$planUsage.usage.tokens.limit|number_format:0:',':' '}
                                    </span>
                                </div>
                                <div class="ast-progress-bar">
                                    <div class="ast-progress-fill {if $planUsage.usage.tokens.percentage >= 90}danger{elseif $planUsage.usage.tokens.percentage >= 70}warning{/if}" style="width: {$planUsage.usage.tokens.percentage}%;"></div>
                                </div>
                                <span class="ast-usage-percent">{$planUsage.usage.tokens.percentage}%</span>
                            </div>

                            {* Documents Usage *}
                            <div class="ast-usage-item">
                                <div class="ast-usage-header">
                                    <span class="ast-usage-label">
                                        <i class="icon icon-file-text-o"></i> {l s='Documents' mod='aismarttalk'}
                                    </span>
                                    <span class="ast-usage-value">
                                        {$planUsage.usage.documents.used|number_format:0:',':' '} / {$planUsage.usage.documents.limit|number_format:0:',':' '}
                                    </span>
                                </div>
                                <div class="ast-progress-bar">
                                    <div class="ast-progress-fill {if $planUsage.usage.documents.percentage >= 90}danger{elseif $planUsage.usage.documents.percentage >= 70}warning{/if}" style="width: {$planUsage.usage.documents.percentage}%;"></div>
                                </div>
                                <span class="ast-usage-percent">{$planUsage.usage.documents.percentage}%</span>
                            </div>

                            {* Agents Usage *}
                            <div class="ast-usage-item">
                                <div class="ast-usage-header">
                                    <span class="ast-usage-label">
                                        <i class="icon icon-robot"></i> {l s='AI Agents' mod='aismarttalk'}
                                    </span>
                                    <span class="ast-usage-value">
                                        {$planUsage.usage.agents.used} / {$planUsage.usage.agents.limit}
                                    </span>
                                </div>
                                <div class="ast-progress-bar">
                                    <div class="ast-progress-fill {if $planUsage.usage.agents.percentage >= 90}danger{elseif $planUsage.usage.agents.percentage >= 70}warning{/if}" style="width: {$planUsage.usage.agents.percentage}%;"></div>
                                </div>
                                <span class="ast-usage-percent">{$planUsage.usage.agents.percentage}%</span>
                            </div>

                            {* Seats Usage *}
                            <div class="ast-usage-item">
                                <div class="ast-usage-header">
                                    <span class="ast-usage-label">
                                        <i class="icon icon-users"></i> {l s='Team Seats' mod='aismarttalk'}
                                    </span>
                                    <span class="ast-usage-value">
                                        {$planUsage.usage.seats.used} / {$planUsage.usage.seats.limit}
                                    </span>
                                </div>
                                <div class="ast-progress-bar">
                                    <div class="ast-progress-fill {if $planUsage.usage.seats.percentage >= 90}danger{elseif $planUsage.usage.seats.percentage >= 70}warning{/if}" style="width: {$planUsage.usage.seats.percentage}%;"></div>
                                </div>
                                <span class="ast-usage-percent">{$planUsage.usage.seats.percentage}%</span>
                            </div>
                        </div>

                        {* Plan Info & Actions *}
                        <div class="ast-plan-actions" style="margin-top: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                            <div class="ast-plan-info">
                                {if $planUsage.plan.isFreePlan}
                                    <span class="ast-hint">
                                        <i class="icon icon-info-circle"></i>
                                        {l s='You are on the Free plan. Upgrade for more features and higher limits.' mod='aismarttalk'}
                                    </span>
                                {else}
                                    <span class="ast-hint">
                                        <i class="icon icon-calendar"></i>
                                        {l s='Resets on:' mod='aismarttalk'} {$planUsage.resetsOn|escape:'html':'UTF-8'}
                                    </span>
                                {/if}
                            </div>
                            <div class="ast-plan-buttons" style="display: flex; gap: 8px;">
                                {if $planUsage.links.upgrade}
                                <a href="{$planUsage.links.upgrade|escape:'html':'UTF-8'}" target="_blank" class="ast-btn {if $planUsage.plan.isFreePlan || $planUsage.overallUsagePercentage >= 70}ast-btn-success{else}ast-btn-secondary{/if}">
                                    <i class="icon icon-rocket"></i>
                                    {l s='Upgrade Plan' mod='aismarttalk'}
                                </a>
                                {/if}
                                {if $planUsage.links.billing && !$planUsage.plan.isFreePlan}
                                <a href="{$planUsage.links.billing|escape:'html':'UTF-8'}" target="_blank" class="ast-btn ast-btn-secondary">
                                    <i class="icon icon-credit-card"></i>
                                    {l s='Billing' mod='aismarttalk'}
                                </a>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
                {/if}
            </div>

            {* ===== TAB 2: APPEARANCE ===== *}
            <div class="ast-panel" id="panel-appearance" role="tabpanel">
                <form action="{$formAction|escape:'html':'UTF-8'}" method="post" enctype="multipart/form-data">
                    <div class="ast-card">
                        <div class="ast-card-header">
                            <h3><i class="icon icon-hand-pointer-o"></i> {l s='Button Style' mod='aismarttalk'}</h3>
                        </div>
                        <div class="ast-card-body">
                            <div class="ast-button-types">
                                <label class="ast-button-type {if $buttonType == ''}selected{/if}">
                                    <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="" {if $buttonType == ''}checked{/if}>
                                    <div class="preview">🌐</div>
                                    <div class="label">{l s='Default' mod='aismarttalk'}</div>
                                </label>
                                <label class="ast-button-type {if $buttonType == 'default'}selected{/if}">
                                    <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="default" {if $buttonType == 'default'}checked{/if}>
                                    <div class="preview">💬</div>
                                    <div class="label">{l s='Text' mod='aismarttalk'}</div>
                                </label>
                                <label class="ast-button-type {if $buttonType == 'icon'}selected{/if}">
                                    <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="icon" {if $buttonType == 'icon'}checked{/if}>
                                    <div class="preview">🔵</div>
                                    <div class="label">{l s='Icon' mod='aismarttalk'}</div>
                                </label>
                                <label class="ast-button-type {if $buttonType == 'avatar'}selected{/if}">
                                    <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="avatar" {if $buttonType == 'avatar'}checked{/if}>
                                    <div class="preview">🤖</div>
                                    <div class="label">{l s='Avatar' mod='aismarttalk'}</div>
                                </label>
                                <label class="ast-button-type {if $buttonType == 'minimal'}selected{/if}">
                                    <input type="radio" name="AI_SMART_TALK_BUTTON_TYPE" value="minimal" {if $buttonType == 'minimal'}checked{/if}>
                                    <div class="preview">•••</div>
                                    <div class="label">{l s='Minimal' mod='aismarttalk'}</div>
                                </label>
                            </div>

                            <div class="ast-form-group" style="margin-top: 20px;">
                                <label class="ast-label">{l s='Button Text' mod='aismarttalk'}</label>
                                <input type="text" name="AI_SMART_TALK_BUTTON_TEXT" value="{$buttonText|escape:'html':'UTF-8'}" class="ast-input" placeholder="{l s='e.g., Need help?' mod='aismarttalk'}">
                            </div>
                        </div>
                    </div>

                    <div class="ast-grid ast-grid-2">
                        <div class="ast-card">
                            <div class="ast-card-header">
                                <h3><i class="icon icon-th-large"></i> {l s='Layout' mod='aismarttalk'}</h3>
                            </div>
                            <div class="ast-card-body">
                                <div class="ast-form-group">
                                    <label class="ast-label">{l s='Window Size' mod='aismarttalk'}</label>
                                    <select name="AI_SMART_TALK_CHAT_SIZE" class="ast-select">
                                        <option value="" {if $chatSize == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="small" {if $chatSize == 'small'}selected{/if}>{l s='Small' mod='aismarttalk'}</option>
                                        <option value="medium" {if $chatSize == 'medium'}selected{/if}>{l s='Medium' mod='aismarttalk'}</option>
                                        <option value="large" {if $chatSize == 'large'}selected{/if}>{l s='Large' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                                <div class="ast-form-group">
                                    <label class="ast-label">{l s='Position' mod='aismarttalk'}</label>
                                    <select name="AI_SMART_TALK_BUTTON_POSITION" class="ast-select">
                                        <option value="" {if $buttonPosition == ''}selected{/if}>{l s='Default (bottom-right)' mod='aismarttalk'}</option>
                                        <option value="bottom-right" {if $buttonPosition == 'bottom-right'}selected{/if}>{l s='Bottom Right' mod='aismarttalk'}</option>
                                        <option value="bottom-left" {if $buttonPosition == 'bottom-left'}selected{/if}>{l s='Bottom Left' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                                <div class="ast-form-group">
                                    <label class="ast-label">{l s='Color Mode' mod='aismarttalk'}</label>
                                    <select name="AI_SMART_TALK_COLOR_MODE" class="ast-select">
                                        <option value="" {if $colorMode == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="light" {if $colorMode == 'light'}selected{/if}>{l s='Light' mod='aismarttalk'}</option>
                                        <option value="dark" {if $colorMode == 'dark'}selected{/if}>{l s='Dark' mod='aismarttalk'}</option>
                                        <option value="auto" {if $colorMode == 'auto'}selected{/if}>{l s='Auto' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                                <div class="ast-form-group">
                                    <label class="ast-label">{l s='Chat Window Corners' mod='aismarttalk'}</label>
                                    <select name="AI_SMART_TALK_BORDER_RADIUS" class="ast-select">
                                        <option value="" {if $borderRadius == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="rounded" {if $borderRadius == 'rounded'}selected{/if}>{l s='Rounded' mod='aismarttalk'}</option>
                                        <option value="slightly-rounded" {if $borderRadius == 'slightly-rounded'}selected{/if}>{l s='Slightly Rounded' mod='aismarttalk'}</option>
                                        <option value="square" {if $borderRadius == 'square'}selected{/if}>{l s='Square' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                                <div class="ast-form-group">
                                    <label class="ast-label">{l s='Button Corners' mod='aismarttalk'}</label>
                                    <select name="AI_SMART_TALK_BUTTON_BORDER_RADIUS" class="ast-select">
                                        <option value="" {if $buttonBorderRadius == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="rounded" {if $buttonBorderRadius == 'rounded'}selected{/if}>{l s='Rounded' mod='aismarttalk'}</option>
                                        <option value="slightly-rounded" {if $buttonBorderRadius == 'slightly-rounded'}selected{/if}>{l s='Slightly Rounded' mod='aismarttalk'}</option>
                                        <option value="square" {if $buttonBorderRadius == 'square'}selected{/if}>{l s='Square' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="ast-card">
                            <div class="ast-card-header">
                                <h3><i class="icon icon-tint"></i> {l s='Brand Colors' mod='aismarttalk'}</h3>
                            </div>
                            <div class="ast-card-body">
                                <div class="ast-form-group">
                                    <label class="ast-label">{l s='Primary Color' mod='aismarttalk'}</label>
                                    <div class="ast-color-picker">
                                        <input type="color" id="picker_primary" value="{if $primaryColor}{$primaryColor|escape:'html':'UTF-8'}{else}#667eea{/if}">
                                        <input type="text" name="AI_SMART_TALK_PRIMARY_COLOR" id="input_primary" value="{$primaryColor|escape:'html':'UTF-8'}" placeholder="#667eea">
                                    </div>
                                </div>
                                <div class="ast-form-group">
                                    <label class="ast-label">{l s='Secondary Color' mod='aismarttalk'}</label>
                                    <div class="ast-color-picker">
                                        <input type="color" id="picker_secondary" value="{if $secondaryColor}{$secondaryColor|escape:'html':'UTF-8'}{else}#a5b4fc{/if}">
                                        <input type="text" name="AI_SMART_TALK_SECONDARY_COLOR" id="input_secondary" value="{$secondaryColor|escape:'html':'UTF-8'}" placeholder="#a5b4fc">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ast-card">
                        <div class="ast-card-header">
                            <h3><i class="icon icon-sliders"></i> {l s='Features' mod='aismarttalk'}</h3>
                        </div>
                        <div class="ast-card-body">
                            <div class="ast-features-grid">
                                <div class="ast-feature-toggle">
                                    <span class="label"><i class="icon icon-paperclip"></i> {l s='Attachments' mod='aismarttalk'}</span>
                                    <select name="AI_SMART_TALK_ENABLE_ATTACHMENT" class="ast-select" style="width: auto;">
                                        <option value="" {if $enableAttachment == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="on" {if $enableAttachment == 'on'}selected{/if}>{l s='On' mod='aismarttalk'}</option>
                                        <option value="off" {if $enableAttachment == 'off'}selected{/if}>{l s='Off' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                                <div class="ast-feature-toggle">
                                    <span class="label"><i class="icon icon-thumbs-up"></i> {l s='Feedback' mod='aismarttalk'}</span>
                                    <select name="AI_SMART_TALK_ENABLE_FEEDBACK" class="ast-select" style="width: auto;">
                                        <option value="" {if $enableFeedback == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="on" {if $enableFeedback == 'on'}selected{/if}>{l s='On' mod='aismarttalk'}</option>
                                        <option value="off" {if $enableFeedback == 'off'}selected{/if}>{l s='Off' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                                <div class="ast-feature-toggle">
                                    <span class="label"><i class="icon icon-microphone"></i> {l s='Voice Input' mod='aismarttalk'}</span>
                                    <select name="AI_SMART_TALK_ENABLE_VOICE_INPUT" class="ast-select" style="width: auto;">
                                        <option value="" {if $enableVoiceInput == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="on" {if $enableVoiceInput == 'on'}selected{/if}>{l s='On' mod='aismarttalk'}</option>
                                        <option value="off" {if $enableVoiceInput == 'off'}selected{/if}>{l s='Off' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                                <div class="ast-feature-toggle">
                                    <span class="label"><i class="icon icon-phone"></i> {l s='Voice Mode' mod='aismarttalk'}</span>
                                    <select name="AI_SMART_TALK_ENABLE_VOICE_MODE" class="ast-select" style="width: auto;">
                                        <option value="" {if $enableVoiceMode == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                        <option value="on" {if $enableVoiceMode == 'on'}selected{/if}>{l s='On' mod='aismarttalk'}</option>
                                        <option value="off" {if $enableVoiceMode == 'off'}selected{/if}>{l s='Off' mod='aismarttalk'}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {* GDPR / Privacy Settings *}
                    <div class="ast-card">
                        <div class="ast-card-header">
                            <h3><i class="icon icon-shield"></i> {l s='Privacy & GDPR' mod='aismarttalk'}</h3>
                            <span class="ast-badge ast-badge-success">{l s='GDPR' mod='aismarttalk'}</span>
                        </div>
                        <div class="ast-card-body">
                            <p style="color: #64748b; margin: 0 0 20px; font-size: 13px;">
                                {l s='Configure privacy information displayed in the chatbot. This helps comply with GDPR requirements.' mod='aismarttalk'}
                            </p>

                            <div class="ast-feature-toggle" style="margin-bottom: 20px;">
                                <span class="label"><i class="icon icon-eye"></i> {l s='Show Privacy Info' mod='aismarttalk'}</span>
                                <select name="AI_SMART_TALK_GDPR_ENABLED" class="ast-select" style="width: auto;">
                                    <option value="" {if $gdprEnabled == ''}selected{/if}>{l s='Default' mod='aismarttalk'}</option>
                                    <option value="on" {if $gdprEnabled == 'on'}selected{/if}>{l s='On' mod='aismarttalk'}</option>
                                    <option value="off" {if $gdprEnabled == 'off'}selected{/if}>{l s='Off' mod='aismarttalk'}</option>
                                </select>
                            </div>

                            <div class="ast-form-group">
                                <label class="ast-label">{l s='Privacy Policy URL' mod='aismarttalk'}</label>
                                <input type="url" name="AI_SMART_TALK_GDPR_PRIVACY_URL" value="{$gdprPrivacyUrl|escape:'html':'UTF-8'}" class="ast-input" placeholder="{$apiUrl|escape:'html':'UTF-8'}/{$currentLang|escape:'html':'UTF-8'}/privacy-policy">
                                <p class="ast-help">{l s='Link to your privacy policy. Leave empty to use AI SmartTalk\'s default privacy policy.' mod='aismarttalk'}</p>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" name="submitChatbotCustomization" class="ast-btn ast-btn-primary">
                            <i class="icon icon-save"></i>
                            {l s='Save Appearance' mod='aismarttalk'}
                        </button>
                    </div>
                </form>
            </div>

            {* ===== TAB 3: SYNC ===== *}
            <div class="ast-panel" id="panel-sync" role="tabpanel">
                <div class="ast-grid ast-grid-2">
                    {* Product Sync *}
                    <div class="ast-card">
                        <div class="ast-card-header">
                            <h3><i class="icon icon-cube"></i> {l s='Product Sync' mod='aismarttalk'}</h3>
                        </div>
                        <div class="ast-card-body">
                            <form action="{$formAction|escape:'html':'UTF-8'}" method="post">
                                <div class="ast-toggle-card">
                                    <div class="ast-toggle-info">
                                        <h4>{l s='Auto-sync Products' mod='aismarttalk'}</h4>
                                        <p>{l s='Your products will be available to the AI chatbot' mod='aismarttalk'}</p>
                                    </div>
                                    <label class="ast-switch">
                                        <input type="checkbox" name="AI_SMART_TALK_PRODUCT_SYNC" value="1" {if $productSyncEnabled}checked{/if} onchange="this.form.submit()">
                                        <span class="ast-switch-slider"></span>
                                    </label>
                                    <input type="hidden" name="submitProductSync" value="1">
                                </div>
                            </form>

                            {if $productSyncEnabled}
                            <div class="ast-quick-actions">
                                <a href="{$formAction|escape:'html':'UTF-8'}&amp;forceSync=true" class="ast-btn ast-btn-warning ast-btn-sm">
                                    <i class="icon icon-refresh"></i> {l s='Sync All' mod='aismarttalk'}
                                </a>
                                <a href="{$formAction|escape:'html':'UTF-8'}&amp;clean=1" class="ast-btn ast-btn-secondary ast-btn-sm">
                                    <i class="icon icon-trash"></i> {l s='Clean' mod='aismarttalk'}
                                </a>
                            </div>
                            {/if}
                        </div>
                    </div>

                    {* Customer Sync *}
                    <div class="ast-card">
                        <div class="ast-card-header">
                            <h3><i class="icon icon-users"></i> {l s='Customer Sync' mod='aismarttalk'}</h3>
                        </div>
                        <div class="ast-card-body">
                            <form action="{$formAction|escape:'html':'UTF-8'}" method="post">
                                <div class="ast-toggle-card">
                                    <div class="ast-toggle-info">
                                        <h4>{l s='Sync Customer Data' mod='aismarttalk'}</h4>
                                        <p>{l s='Connect customers with AI SmartTalk CRM' mod='aismarttalk'}</p>
                                    </div>
                                    <label class="ast-switch">
                                        <input type="checkbox" name="AI_SMART_TALK_CUSTOMER_SYNC" value="1" {if $customerSyncEnabled}checked{/if} onchange="this.form.submit()">
                                        <span class="ast-switch-slider"></span>
                                    </label>
                                    <input type="hidden" name="submitCustomerSync" value="1">
                                </div>
                            </form>

                            <div class="ast-quick-actions">
                                <a href="{$formAction|escape:'html':'UTF-8'}&amp;exportCustomers=1" class="ast-btn ast-btn-secondary ast-btn-sm">
                                    <i class="icon icon-upload"></i> {l s='Export All' mod='aismarttalk'}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {* Sync Filters *}
                {if $productSyncEnabled}
                <div class="ast-card">
                    <div class="ast-card-header">
                        <h3>
                            <i class="icon icon-filter"></i> {l s='Sync Filters' mod='aismarttalk'}
                            {if $syncFilterHasActiveFilters}
                                <span class="ast-filter-badge" style="margin-left: 10px;">{l s='Active' mod='aismarttalk'}</span>
                            {/if}
                        </h3>
                    </div>
                    <div class="ast-card-body">
                        <p style="color: #64748b; margin: 0 0 20px;">{l s='Choose which products to synchronize with AI SmartTalk.' mod='aismarttalk'}</p>

                        <form action="{$formAction|escape:'html':'UTF-8'}" method="post" id="sync-filters-form">

                            {* Step 1: Category filter (main decision) *}
                            <div class="ast-category-mode-selector">
                                <label class="ast-mode-option {if $syncFilterCategoryMode == 'all'}active{/if}">
                                    <input type="radio" name="sync_filter_category_mode" value="all" {if $syncFilterCategoryMode == 'all'}checked{/if}>
                                    <div class="ast-mode-content">
                                        <strong>{l s='All categories' mod='aismarttalk'}</strong>
                                        <small>{l s='Sync products from every category' mod='aismarttalk'}</small>
                                    </div>
                                </label>
                                <label class="ast-mode-option {if $syncFilterCategoryMode == 'include'}active{/if}">
                                    <input type="radio" name="sync_filter_category_mode" value="include" {if $syncFilterCategoryMode == 'include'}checked{/if}>
                                    <div class="ast-mode-content">
                                        <strong>{l s='Only selected' mod='aismarttalk'}</strong>
                                        <small>{l s='Sync only from checked categories' mod='aismarttalk'}</small>
                                    </div>
                                </label>
                                <label class="ast-mode-option {if $syncFilterCategoryMode == 'exclude'}active{/if}">
                                    <input type="radio" name="sync_filter_category_mode" value="exclude" {if $syncFilterCategoryMode == 'exclude'}checked{/if}>
                                    <div class="ast-mode-content">
                                        <strong>{l s='All except selected' mod='aismarttalk'}</strong>
                                        <small>{l s='Exclude checked categories' mod='aismarttalk'}</small>
                                    </div>
                                </label>
                            </div>

                            {* Step 2: Category tree (only when filtering) *}
                            <div id="category-tree-wrapper" style="{if $syncFilterCategoryMode == 'all'}display: none;{/if}">
                                <div class="ast-category-box">
                                    <div class="ast-category-header">
                                        <input type="text" id="category-search" class="ast-category-search" placeholder="{l s='Search categories...' mod='aismarttalk'}">
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" id="expand-all" class="ast-btn ast-btn-secondary ast-btn-sm">{l s='Expand' mod='aismarttalk'}</button>
                                            <button type="button" id="collapse-all" class="ast-btn ast-btn-secondary ast-btn-sm">{l s='Collapse' mod='aismarttalk'}</button>
                                        </div>
                                    </div>
                                    <div class="ast-category-tree" id="category-tree">
                                        {foreach from=$syncFilterCategoryTree item=category}
                                            <div class="ast-tree-node"
                                                 data-id="{$category.id_category|intval}"
                                                 data-parent="{$category.parent_id|intval}"
                                                 data-depth="{$category.depth|intval}"
                                                 data-children="{$category.child_ids|@json_encode|escape:'html':'UTF-8'}"
                                                 data-name="{$category.name|escape:'html':'UTF-8'|lower}"
                                                 style="margin-left: {($category.depth * 24)|intval}px; {if $category.depth > 0}display: none;{/if}">
                                                {if $category.has_children}
                                                    <span class="ast-tree-toggle" data-id="{$category.id_category|intval}">&#9654;</span>
                                                {else}
                                                    <span class="ast-tree-spacer"></span>
                                                {/if}
                                                <label class="ast-tree-label">
                                                    <input type="checkbox"
                                                           class="ast-tree-checkbox"
                                                           value="{$category.id_category|intval}"
                                                           {if in_array($category.id_category, $syncFilterConfig.categories)}checked{/if}>
                                                    <span class="ast-tree-name">{$category.name|escape:'html':'UTF-8'}</span>
                                                    {if $category.product_count > 0}
                                                        <span class="ast-tree-count">{$category.product_count|intval}</span>
                                                    {/if}
                                                </label>
                                            </div>
                                        {/foreach}
                                    </div>
                                    <div class="ast-category-footer">
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" id="select-all-cats" class="ast-btn ast-btn-secondary ast-btn-sm">{l s='Select All' mod='aismarttalk'}</button>
                                            <button type="button" id="clear-categories" class="ast-btn ast-btn-secondary ast-btn-sm">{l s='Clear' mod='aismarttalk'}</button>
                                        </div>
                                        <span><span id="selected-count">0</span> {l s='selected' mod='aismarttalk'}</span>
                                    </div>
                                </div>
                                <div id="category-warning" class="ast-filter-warning" style="display: none;">
                                    <i class="icon icon-warning"></i>
                                    <span id="category-warning-text"></span>
                                </div>
                            </div>
                            <input type="hidden" name="sync_filter_categories" id="sync_filter_categories" value="">

                            {* Step 3: Product types (compact secondary filter) *}
                            <div class="ast-types-bar">
                                <span class="ast-types-label">{l s='Product types to sync:' mod='aismarttalk'}</span>
                                <div class="ast-types-chips">
                                    <label class="ast-type-chip {if in_array('standard', $syncFilterConfig.product_types)}checked{/if}">
                                        <input type="checkbox" name="sync_filter_product_types[]" value="standard" class="ast-type-checkbox" {if in_array('standard', $syncFilterConfig.product_types)}checked{/if}>
                                        <span class="ast-type-chip-label">{l s='Standard' mod='aismarttalk'}</span>
                                        <span class="ast-type-chip-count">{$syncFilterProductTypeCounts.standard|intval}</span>
                                    </label>
                                    <label class="ast-type-chip {if in_array('virtual', $syncFilterConfig.product_types)}checked{/if}">
                                        <input type="checkbox" name="sync_filter_product_types[]" value="virtual" class="ast-type-checkbox" {if in_array('virtual', $syncFilterConfig.product_types)}checked{/if}>
                                        <span class="ast-type-chip-label">{l s='Virtual' mod='aismarttalk'}</span>
                                        <span class="ast-type-chip-count">{$syncFilterProductTypeCounts.virtual|intval}</span>
                                    </label>
                                    <label class="ast-type-chip {if in_array('pack', $syncFilterConfig.product_types)}checked{/if}">
                                        <input type="checkbox" name="sync_filter_product_types[]" value="pack" class="ast-type-checkbox" {if in_array('pack', $syncFilterConfig.product_types)}checked{/if}>
                                        <span class="ast-type-chip-label">{l s='Pack' mod='aismarttalk'}</span>
                                        <span class="ast-type-chip-count">{$syncFilterProductTypeCounts.pack|intval}</span>
                                    </label>
                                </div>
                            </div>
                            <div id="type-warning" class="ast-filter-warning" style="display: none;">
                                <i class="icon icon-warning"></i>
                                {l s='No product type selected. No products will be synchronized.' mod='aismarttalk'}
                            </div>

                            <div style="margin-top: 24px; display: flex; align-items: center; gap: 16px;">
                                <button type="submit" name="submitSyncFilters" class="ast-btn ast-btn-primary">
                                    <i class="icon icon-save"></i> {l s='Save Filters' mod='aismarttalk'}
                                </button>
                                <span style="font-size: 13px; color: #94a3b8;">{l s='Run "Sync All" after saving to apply changes.' mod='aismarttalk'}</span>
                            </div>
                        </form>
                    </div>
                </div>
                {/if}
            </div>

            {* ===== TAB 4: WEBHOOKS ===== *}
            <div class="ast-panel" id="panel-webhooks" role="tabpanel">
                <div class="ast-card">
                    <div class="ast-card-header">
                        <h3><i class="icon icon-flash"></i> {l s='Webhook Triggers' mod='aismarttalk'}</h3>
                        <span class="ast-badge {if $webhooksEnabledTriggers|count > 0}ast-badge-success{else}ast-badge-secondary{/if}">
                            {$webhooksEnabledTriggers|count}/{$webhooksAvailableTriggers|count} {l s='active' mod='aismarttalk'}
                        </span>
                    </div>
                    <div class="ast-card-body">
                        <p style="color: #64748b; margin-bottom: 20px;">
                            {l s='Webhooks send real-time notifications to AI SmartTalk when events occur in your store. Enable the triggers you need to use them in SmartFlow automations.' mod='aismarttalk'}
                        </p>

                        <form action="{$formAction|escape:'html':'UTF-8'}" method="post">
                            <div class="ast-webhooks-grid">
                                {foreach from=$webhooksAvailableTriggers key=triggerKey item=trigger}
                                <div class="ast-webhook-card {if in_array($triggerKey, $webhooksEnabledTriggers)}active{/if}">
                                    <div class="ast-webhook-content">
                                        <div class="ast-webhook-header">
                                            <span class="ast-webhook-icon">
                                                {if $triggerKey == 'ps_on_order_status_changed'}📦
                                                {elseif $triggerKey == 'ps_on_payment_received'}💳
                                                {elseif $triggerKey == 'ps_on_product_out_of_stock'}📉
                                                {elseif $triggerKey == 'ps_on_return_requested'}↩️
                                                {elseif $triggerKey == 'ps_on_review_posted'}⭐
                                                {elseif $triggerKey == 'ps_on_new_order'}🛒
                                                {elseif $triggerKey == 'ps_on_customer_registered'}👤
                                                {elseif $triggerKey == 'ps_on_cart_updated'}🛍️
                                                {elseif $triggerKey == 'ps_on_refund_created'}💸
                                                {elseif $triggerKey == 'ps_on_product_created'}📝
                                                {else}🔔{/if}
                                            </span>
                                            <div class="ast-webhook-info">
                                                <h5>{$trigger.name|escape:'html':'UTF-8'}</h5>
                                                <p>{$trigger.description|escape:'html':'UTF-8'}</p>
                                            </div>
                                            <label class="ast-switch">
                                                <input type="checkbox" name="webhooks_triggers[]" value="{$triggerKey|escape:'html':'UTF-8'}"
                                                       {if in_array($triggerKey, $webhooksEnabledTriggers)}checked{/if}>
                                                <span class="ast-switch-slider"></span>
                                            </label>
                                        </div>
                                        <div class="ast-webhook-payload">
                                            <span class="ast-webhook-payload-label">{l s='Payload:' mod='aismarttalk'}</span>
                                            <code class="ast-webhook-payload-fields">{', '|implode:$trigger.payload_fields}</code>
                                        </div>
                                    </div>
                                </div>
                                {/foreach}
                            </div>

                            <div style="margin-top: 24px;">
                                <button type="submit" name="submitWebhooksSettings" class="ast-btn ast-btn-primary">
                                    <i class="icon icon-save"></i> {l s='Save Webhooks Settings' mod='aismarttalk'}
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

                {* Webhook Documentation *}
                <div class="ast-card" style="margin-top: 20px;">
                    <div class="ast-card-header">
                        <h3><i class="icon icon-book"></i> {l s='Using Webhooks in SmartFlows' mod='aismarttalk'}</h3>
                    </div>
                    <div class="ast-card-body">
                        <div class="ast-doc-section">
                            <h4>{l s='How it works' mod='aismarttalk'}</h4>
                            <ol style="color: #64748b; padding-left: 20px; line-height: 1.8;">
                                <li>{l s='Enable the webhooks you need above' mod='aismarttalk'}</li>
                                <li>{l s='Go to AI SmartTalk Dashboard → SmartFlows' mod='aismarttalk'}</li>
                                <li>{l s='Create a new SmartFlow and select "PrestaShop Trigger" as the starting point' mod='aismarttalk'}</li>
                                <li>{l s='Choose your trigger event and configure the automation' mod='aismarttalk'}</li>
                            </ol>
                        </div>

                        <div class="ast-doc-examples" style="margin-top: 20px;">
                            <h4 style="margin-bottom: 12px;">{l s='Example Use Cases' mod='aismarttalk'}</h4>
                            <div class="ast-example-grid">
                                <div class="ast-example-card">
                                    <span class="ast-example-icon">📦</span>
                                    <div>
                                        <strong>{l s='Order Status Changed' mod='aismarttalk'}</strong>
                                        <p>{l s='Send shipping notifications, update CRM, notify team on Slack' mod='aismarttalk'}</p>
                                    </div>
                                </div>
                                <div class="ast-example-card">
                                    <span class="ast-example-icon">💳</span>
                                    <div>
                                        <strong>{l s='Payment Received' mod='aismarttalk'}</strong>
                                        <p>{l s='Send thank you email, create invoice, notify warehouse' mod='aismarttalk'}</p>
                                    </div>
                                </div>
                                <div class="ast-example-card">
                                    <span class="ast-example-icon">📉</span>
                                    <div>
                                        <strong>{l s='Product Out of Stock' mod='aismarttalk'}</strong>
                                        <p>{l s='Alert purchasing team, notify supplier, update availability' mod='aismarttalk'}</p>
                                    </div>
                                </div>
                                <div class="ast-example-card">
                                    <span class="ast-example-icon">↩️</span>
                                    <div>
                                        <strong>{l s='Return Requested' mod='aismarttalk'}</strong>
                                        <p>{l s='Notify customer service, create support ticket, track returns' mod='aismarttalk'}</p>
                                    </div>
                                </div>
                                <div class="ast-example-card">
                                    <span class="ast-example-icon">⭐</span>
                                    <div>
                                        <strong>{l s='Review Posted' mod='aismarttalk'}</strong>
                                        <p>{l s='Moderate reviews, respond to feedback, analyze sentiment' mod='aismarttalk'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <a href="{$backofficeUrl|escape:'html':'UTF-8'}" target="_blank" class="ast-btn ast-btn-secondary">
                                <i class="icon icon-external-link"></i>
                                {l s='Create SmartFlow' mod='aismarttalk'}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {* ===== TAB 5: AI SKILLS ===== *}
            <div class="ast-panel" id="panel-skills" role="tabpanel">

                {* Updates Available Banner *}
                <div id="ast-updates-alert" class="ast-update-banner" style="display:none;">
                    <span class="ast-update-banner-icon">🔄</span>
                    <div class="ast-update-banner-text">
                        <strong>{l s='Updates Available' mod='aismarttalk'}</strong>
                        <span><span id="ast-updates-count">0</span> {l s='skill(s) can be updated to the latest version.' mod='aismarttalk'}</span>
                    </div>
                </div>

                {* My Skills (Installed) *}
                <div class="ast-skills-section">
                    <div class="ast-skills-section-header">
                        <div class="ast-skills-section-title">
                            <span class="ast-skills-section-icon">🎯</span>
                            <div>
                                <h3>{l s='My Skills' mod='aismarttalk'}</h3>
                                <p>{l s='Active skills on your assistant. It uses them automatically based on context.' mod='aismarttalk'}</p>
                            </div>
                        </div>
                        <div id="ast-skills-stats" class="ast-skills-stats" style="display:none;">
                            <span class="ast-skill-stat active"><span id="ast-stat-active">0</span> {l s='active' mod='aismarttalk'}</span>
                            <span class="ast-skill-stat inactive"><span id="ast-stat-inactive">0</span> {l s='paused' mod='aismarttalk'}</span>
                        </div>
                    </div>

                    <div id="ast-smartflows-container" class="ast-skills-container">
                        <div class="ast-skills-loading" id="ast-smartflows-loading">
                            <div class="ast-skills-spinner"></div>
                            <p>{l s='Loading your skills...' mod='aismarttalk'}</p>
                        </div>
                        <div class="ast-skills-grid" id="ast-smartflows-grid" style="display:none;"></div>
                        <div class="ast-skills-empty" id="ast-smartflows-empty" style="display:none;">
                            <div class="ast-empty-icon">🚀</div>
                            <h4>{l s='No skills installed yet' mod='aismarttalk'}</h4>
                            <p>{l s='Explore the Marketplace below to get started.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                </div>

                {* Skills Marketplace *}
                <div class="ast-skills-section ast-marketplace-section">
                    <div class="ast-skills-section-header">
                        <div class="ast-skills-section-title">
                            <span class="ast-skills-section-icon">🏪</span>
                            <div>
                                <h3>{l s='Marketplace' mod='aismarttalk'}</h3>
                                <p>{l s='Discover new skills for your assistant. One-click install.' mod='aismarttalk'}</p>
                            </div>
                        </div>
                    </div>

                    {* Search and Filters *}
                    <div class="ast-marketplace-toolbar">
                        <div class="ast-marketplace-search">
                            <span class="ast-search-icon">🔍</span>
                            <input type="text" id="ast-templates-search" placeholder="{l s='Search skills... (e.g., "create article", "welcome")' mod='aismarttalk'}" />
                        </div>
                        <div class="ast-marketplace-filters">
                            <div class="ast-filter-item">
                                <label>{l s='Platform' mod='aismarttalk'}</label>
                                <select id="ast-filter-platform" class="ast-filter-select">
                                    <option value="">{l s='All platforms' mod='aismarttalk'}</option>
                                    <option value="prestashop" selected>🛒 PrestaShop</option>
                                    <option value="wordpress">🌐 WordPress</option>
                                    <option value="shopify">🛍️ Shopify</option>
                                    <option value="joomla">📦 Joomla</option>
                                    <option value="webflow">🎨 Webflow</option>
                                    <option value="docusaurus">📚 Docusaurus</option>
                                </select>
                            </div>
                            <div class="ast-filter-item">
                                <label>{l s='Skill type' mod='aismarttalk'}</label>
                                <select id="ast-filter-trigger" class="ast-filter-select">
                                    <option value="">{l s='All types' mod='aismarttalk'}</option>
                                    <option value="CONVERSATION_TOOL">🤖 {l s='Conversation tool' mod='aismarttalk'}</option>
                                    <option value="WEBHOOK">🔗 {l s='Webhook' mod='aismarttalk'}</option>
                                    <option value="SMART_FORM_WORKFLOW">📝 SmartForm</option>
                                    <option value="NAVIGATION_EVENT">🧭 {l s='Navigation' mod='aismarttalk'}</option>
                                    <option value="CHAT_SERVICE">💬 Chat</option>
                                    <option value="SCHEDULE_WORKFLOW">⏰ {l s='Scheduled' mod='aismarttalk'}</option>
                                </select>
                            </div>
                            <div class="ast-filter-item">
                                <label>{l s='Status' mod='aismarttalk'}</label>
                                <select id="ast-filter-status" class="ast-filter-select">
                                    <option value="">{l s='All' mod='aismarttalk'}</option>
                                    <option value="installed">✅ {l s='Installed' mod='aismarttalk'}</option>
                                    <option value="not-installed">➕ {l s='Not installed' mod='aismarttalk'}</option>
                                    <option value="has-update">🔄 {l s='Update available' mod='aismarttalk'}</option>
                                </select>
                            </div>
                            <div class="ast-filter-item">
                                <label>{l s='Sort by' mod='aismarttalk'}</label>
                                <select id="ast-filter-sort" class="ast-filter-select">
                                    <option value="downloads">🔥 {l s='Popular' mod='aismarttalk'}</option>
                                    <option value="createdAt">✨ {l s='Recent' mod='aismarttalk'}</option>
                                    <option value="name">🔤 A-Z</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {* Results Count *}
                    <div id="ast-templates-count" class="ast-marketplace-count" style="display:none;">
                        <strong id="ast-templates-total">0</strong> {l s='skills available' mod='aismarttalk'}
                    </div>

                    {* Templates Grid *}
                    <div id="ast-templates-container">
                        <div class="ast-marketplace-loading" id="ast-templates-loading">
                            <div class="ast-skills-spinner"></div>
                            <p>{l s='Discovering skills...' mod='aismarttalk'}</p>
                        </div>
                        <div class="ast-marketplace-grid" id="ast-templates-grid" style="display:none;"></div>
                        <div class="ast-marketplace-empty" id="ast-templates-empty" style="display:none;">
                            <div class="ast-empty-icon">🔎</div>
                            <h4>{l s='No skills match your criteria' mod='aismarttalk'}</h4>
                            <p>{l s='Try different filters.' mod='aismarttalk'}</p>
                        </div>
                    </div>

                    {* Pagination *}
                    <div id="ast-templates-pagination" class="ast-marketplace-pagination" style="display:none;">
                        <button type="button" id="ast-templates-prev" class="ast-pagination-btn" disabled>&#8249;</button>
                        <span id="ast-templates-page-info" class="ast-page-info">1 / 1</span>
                        <button type="button" id="ast-templates-next" class="ast-pagination-btn" disabled>&#8250;</button>
                    </div>
                </div>
            </div>

            {* ===== TAB 5: SETTINGS ===== *}
            <div class="ast-panel" id="panel-settings" role="tabpanel">
                <div class="ast-card">
                    <div class="ast-card-header">
                        <h3><i class="icon icon-cog"></i> {l s='Advanced Settings' mod='aismarttalk'}</h3>
                    </div>
                    <div class="ast-card-body">
                        <div class="alert alert-warning" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                            <i class="icon icon-warning"></i>
                            <span>{l s='Only modify these settings if you have a custom/whitelabel deployment.' mod='aismarttalk'}</span>
                        </div>

                        <form action="{$formAction|escape:'html':'UTF-8'}" method="post" class="ast-advanced-form">
                            <div class="ast-form-group">
                                <label class="ast-label">{l s='API URL' mod='aismarttalk'}</label>
                                <input type="text" name="AI_SMART_TALK_URL" value="{$apiUrl|escape:'html':'UTF-8'}" class="ast-input">
                            </div>
                            <div class="ast-form-group">
                                <label class="ast-label">{l s='CDN URL' mod='aismarttalk'}</label>
                                <input type="text" name="AI_SMART_TALK_CDN" value="{$cdnUrl|escape:'html':'UTF-8'}" class="ast-input">
                            </div>
                            <div class="ast-form-group">
                                <label class="ast-label">{l s='WebSocket URL' mod='aismarttalk'}</label>
                                <input type="text" name="AI_SMART_TALK_WS" value="{$wsUrl|escape:'html':'UTF-8'}" class="ast-input">
                            </div>
                            <div style="margin-top: 24px;">
                                <button type="submit" name="submitWhiteLabel" class="ast-btn ast-btn-warning">
                                    <i class="icon icon-save"></i> {l s='Save Settings' mod='aismarttalk'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {else}
        {* ===== NOT CONNECTED STATE ===== *}
        <div class="ast-content" style="margin-top: 40px;">
            <div class="ast-card">
                <div class="ast-card-body">
                    <div class="ast-empty">
                        <div class="ast-empty-icon">🔌</div>
                        <h3>{l s='Connect your store to AI SmartTalk' mod='aismarttalk'}</h3>
                        <p>{l s='Get started by connecting your PrestaShop store to AI SmartTalk. It only takes a minute!' mod='aismarttalk'}</p>
                        <a href="{$moduleLink|escape:'html':'UTF-8'}&amp;connectOAuth=1" class="ast-btn ast-btn-primary">
                            <i class="icon icon-plug"></i>
                            {l s='Connect Now' mod='aismarttalk'}
                        </a>

                        {* Discrete advanced settings toggle *}
                        <div style="margin-top: 30px;">
                            <a href="#" onclick="document.getElementById('ast-advanced-urls').style.display = document.getElementById('ast-advanced-urls').style.display === 'none' ? 'block' : 'none'; return false;" style="font-size: 11px; color: #94a3b8; text-decoration: none;">
                                <i class="icon icon-cog"></i> {l s='Advanced' mod='aismarttalk'}
                            </a>
                        </div>

                        <div id="ast-advanced-urls" style="display: none; margin-top: 20px; text-align: left; max-width: 400px; margin-left: auto; margin-right: auto;">
                            <form action="{$formAction|escape:'html':'UTF-8'}" method="post">
                                <div style="font-size: 11px; color: #64748b; margin-bottom: 12px;">
                                    {l s='For development or white-label deployments only.' mod='aismarttalk'}
                                </div>
                                <div class="ast-form-group" style="margin-bottom: 12px;">
                                    <label class="ast-label" style="font-size: 11px;">{l s='API URL' mod='aismarttalk'}</label>
                                    <input type="text" name="AI_SMART_TALK_URL" value="{$apiUrl|escape:'html':'UTF-8'}" class="ast-input" style="font-size: 12px; padding: 8px 12px;">
                                </div>
                                <div class="ast-form-group" style="margin-bottom: 12px;">
                                    <label class="ast-label" style="font-size: 11px;">{l s='CDN URL' mod='aismarttalk'}</label>
                                    <input type="text" name="AI_SMART_TALK_CDN" value="{$cdnUrl|escape:'html':'UTF-8'}" class="ast-input" style="font-size: 12px; padding: 8px 12px;">
                                </div>
                                <div class="ast-form-group" style="margin-bottom: 12px;">
                                    <label class="ast-label" style="font-size: 11px;">{l s='WebSocket URL' mod='aismarttalk'}</label>
                                    <input type="text" name="AI_SMART_TALK_WS" value="{$wsUrl|escape:'html':'UTF-8'}" class="ast-input" style="font-size: 12px; padding: 8px 12px;">
                                </div>
                                <button type="submit" name="submitWhiteLabel" class="ast-btn ast-btn-secondary" style="font-size: 11px; padding: 8px 16px;">
                                    <i class="icon icon-save"></i> {l s='Save' mod='aismarttalk'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== TAB NAVIGATION =====
    var tabs = document.querySelectorAll('.ast-tab');
    var panels = document.querySelectorAll('.ast-panel');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var targetId = 'panel-' + this.dataset.tab;

            // Update tabs
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');

            // Update panels
            panels.forEach(function(p) { p.classList.remove('active'); });
            var targetPanel = document.getElementById(targetId);
            if (targetPanel) targetPanel.classList.add('active');

            // Save to localStorage
            localStorage.setItem('ast_active_tab', this.dataset.tab);
        });
    });

    // Restore last active tab
    var savedTab = localStorage.getItem('ast_active_tab');
    if (savedTab) {
        var savedTabBtn = document.querySelector('.ast-tab[data-tab="' + savedTab + '"]');
        if (savedTabBtn) savedTabBtn.click();
    }

    // ===== BUTTON TYPE SELECTOR =====
    var buttonTypes = document.querySelectorAll('.ast-button-type');
    buttonTypes.forEach(function(btn) {
        btn.addEventListener('click', function() {
            buttonTypes.forEach(function(b) { b.classList.remove('selected'); });
            this.classList.add('selected');
        });
    });

    // ===== COLOR PICKERS =====
    function setupColorPicker(pickerId, inputId) {
        var picker = document.getElementById(pickerId);
        var input = document.getElementById(inputId);
        if (picker && input) {
            picker.addEventListener('input', function() {
                input.value = this.value;
            });
            input.addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    picker.value = this.value;
                }
            });
        }
    }
    setupColorPicker('picker_primary', 'input_primary');
    setupColorPicker('picker_secondary', 'input_secondary');

    // ===== WEBHOOK TOGGLE CARDS =====
    document.querySelectorAll('.ast-webhook-card .ast-switch input').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var card = this.closest('.ast-webhook-card');
            if (this.checked) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        });
    });

    // ===== SYNC FILTERS =====

    // --- Product Type Chips ---
    var typeCheckboxes = document.querySelectorAll('.ast-type-checkbox');
    var typeWarning = document.getElementById('type-warning');

    function updateTypeChips() {
        var anyChecked = false;
        typeCheckboxes.forEach(function(cb) {
            var chip = cb.closest('.ast-type-chip');
            if (chip) {
                if (cb.checked) {
                    chip.classList.add('checked');
                    anyChecked = true;
                } else {
                    chip.classList.remove('checked');
                }
            }
        });
        if (typeWarning) typeWarning.style.display = anyChecked ? 'none' : 'flex';
    }

    typeCheckboxes.forEach(function(cb) {
        cb.addEventListener('change', updateTypeChips);
    });
    updateTypeChips();

    // --- Category Mode Selector ---
    var categoryModeRadios = document.querySelectorAll('input[name="sync_filter_category_mode"]');
    var categoryTreeWrapper = document.getElementById('category-tree-wrapper');
    var categoryWarning = document.getElementById('category-warning');
    var categoryWarningText = document.getElementById('category-warning-text');

    function updateCategoryMode() {
        var mode = 'all';
        categoryModeRadios.forEach(function(r) { if (r.checked) mode = r.value; });

        // Toggle active class on mode options
        document.querySelectorAll('.ast-mode-option').forEach(function(opt) {
            var radio = opt.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                opt.classList.add('active');
            } else {
                opt.classList.remove('active');
            }
        });

        // Show/hide category tree
        if (categoryTreeWrapper) {
            categoryTreeWrapper.style.display = (mode === 'all') ? 'none' : '';
        }

        // Update hidden categories input when "all" is selected
        if (mode === 'all' && categoriesInput) {
            categoriesInput.value = '[]';
        } else {
            updateCategorySelection();
        }

        // Update category warning
        updateCategoryWarning(mode);
    }

    function updateCategoryWarning(mode) {
        if (!categoryWarning || !categoryWarningText) return;
        var count = 0;
        if (categoryTree) {
            categoryTree.querySelectorAll('.ast-tree-checkbox').forEach(function(cb) {
                if (cb.checked) count++;
            });
        }

        if (mode !== 'all' && count === 0) {
            categoryWarningText.textContent = (mode === 'include')
                ? '{l s="No categories selected. No products will be synchronized." mod="aismarttalk" js=1}'
                : '{l s="No categories selected. All products will be synchronized (nothing excluded)." mod="aismarttalk" js=1}';
            categoryWarning.style.display = 'flex';
        } else {
            categoryWarning.style.display = 'none';
        }
    }

    categoryModeRadios.forEach(function(r) {
        r.addEventListener('change', updateCategoryMode);
    });

    // --- Category Tree ---
    var categoryTree = document.getElementById('category-tree');
    var categorySearch = document.getElementById('category-search');
    var categoriesInput = document.getElementById('sync_filter_categories');
    var selectedCount = document.getElementById('selected-count');
    var clearBtn = document.getElementById('clear-categories');
    var selectAllBtn = document.getElementById('select-all-cats');
    var expandAllBtn = document.getElementById('expand-all');
    var collapseAllBtn = document.getElementById('collapse-all');

    // Build parent-children relationships
    var treeData = {};
    if (categoryTree) {
        var nodes = categoryTree.querySelectorAll('.ast-tree-node');
        nodes.forEach(function(node) {
            var id = parseInt(node.dataset.id);
            var parentId = parseInt(node.dataset.parent) || null;
            var children = [];
            try { children = JSON.parse(node.dataset.children || '[]'); } catch(e) {}
            treeData[id] = {
                node: node,
                parentId: parentId,
                childIds: children,
                checkbox: node.querySelector('.ast-tree-checkbox')
            };
        });
    }

    function getAllDescendants(id) {
        var descendants = [];
        var data = treeData[id];
        if (data && data.childIds) {
            data.childIds.forEach(function(childId) {
                descendants.push(childId);
                descendants = descendants.concat(getAllDescendants(childId));
            });
        }
        return descendants;
    }

    function updateCategorySelection() {
        if (!categoryTree) return;
        var checkboxes = categoryTree.querySelectorAll('.ast-tree-checkbox');
        var selected = [];
        checkboxes.forEach(function(cb) {
            if (cb.checked) selected.push(parseInt(cb.value));
        });
        if (categoriesInput) categoriesInput.value = JSON.stringify(selected);
        if (selectedCount) selectedCount.textContent = selected.length;

        // Update warning based on current mode
        var mode = 'all';
        categoryModeRadios.forEach(function(r) { if (r.checked) mode = r.value; });
        updateCategoryWarning(mode);
    }

    function toggleNode(id, expand) {
        var data = treeData[id];
        if (!data || !data.childIds.length) return;
        var toggle = data.node.querySelector('.ast-tree-toggle');
        if (toggle) {
            toggle.classList[expand ? 'add' : 'remove']('expanded');
        }
        data.childIds.forEach(function(childId) {
            var childData = treeData[childId];
            if (childData) {
                childData.node.style.display = expand ? 'flex' : 'none';
                if (!expand) toggleNode(childId, false);
            }
        });
    }

    function handleCheckboxChange(id, checked) {
        var descendants = getAllDescendants(id);
        descendants.forEach(function(descId) {
            var data = treeData[descId];
            if (data && data.checkbox) data.checkbox.checked = checked;
        });
        updateParentStates();
        updateCategorySelection();
    }

    function updateParentStates() {
        var maxDepth = 0;
        Object.values(treeData).forEach(function(data) {
            var depth = parseInt(data.node.dataset.depth) || 0;
            if (depth > maxDepth) maxDepth = depth;
        });

        for (var depth = maxDepth - 1; depth >= 0; depth--) {
            Object.values(treeData).forEach(function(data) {
                var nodeDepth = parseInt(data.node.dataset.depth) || 0;
                if (nodeDepth !== depth || !data.childIds.length) return;

                var allChecked = true;
                var someChecked = false;
                getAllDescendants(parseInt(data.node.dataset.id)).forEach(function(descId) {
                    var descData = treeData[descId];
                    if (descData && descData.checkbox) {
                        if (descData.checkbox.checked) someChecked = true;
                        else allChecked = false;
                    }
                });

                if (data.checkbox) {
                    data.checkbox.checked = allChecked && someChecked;
                    data.checkbox.indeterminate = someChecked && !allChecked;
                }
            });
        }
    }

    // Event listeners
    if (categoryTree) {
        categoryTree.addEventListener('click', function(e) {
            if (e.target.classList.contains('ast-tree-toggle')) {
                var id = parseInt(e.target.dataset.id);
                toggleNode(id, !e.target.classList.contains('expanded'));
            }
        });

        categoryTree.addEventListener('change', function(e) {
            if (e.target.classList.contains('ast-tree-checkbox')) {
                var node = e.target.closest('.ast-tree-node');
                handleCheckboxChange(parseInt(node.dataset.id), e.target.checked);
            }
        });

        // On page load: expand parents of checked items
        Object.values(treeData).forEach(function(data) {
            if (data.checkbox && data.checkbox.checked) {
                data.node.style.display = 'flex';
                var parentId = data.parentId;
                while (parentId) {
                    var parentData = treeData[parentId];
                    if (parentData) {
                        parentData.node.style.display = 'flex';
                        var toggle = parentData.node.querySelector('.ast-tree-toggle');
                        if (toggle) toggle.classList.add('expanded');
                        parentId = parentData.parentId;
                    } else {
                        parentId = null;
                    }
                }
            }
        });

        // Initial updates
        updateCategorySelection();
        updateParentStates();
    }

    // Initialize category mode (show/hide tree)
    updateCategoryMode();

    // Search
    if (categorySearch) {
        categorySearch.addEventListener('input', function() {
            var term = this.value.toLowerCase();
            Object.values(treeData).forEach(function(data) {
                var name = data.node.dataset.name || '';
                if (term === '') {
                    var depth = parseInt(data.node.dataset.depth) || 0;
                    data.node.classList.remove('hidden');
                    data.node.style.display = depth === 0 ? 'flex' : 'none';
                    var toggle = data.node.querySelector('.ast-tree-toggle');
                    if (toggle) toggle.classList.remove('expanded');
                } else {
                    if (name.indexOf(term) !== -1) {
                        data.node.classList.remove('hidden');
                        data.node.style.display = 'flex';
                        var parentId = data.parentId;
                        while (parentId) {
                            var parentData = treeData[parentId];
                            if (parentData) {
                                parentData.node.style.display = 'flex';
                                var toggle = parentData.node.querySelector('.ast-tree-toggle');
                                if (toggle) toggle.classList.add('expanded');
                                parentId = parentData.parentId;
                            } else {
                                parentId = null;
                            }
                        }
                    } else {
                        data.node.classList.add('hidden');
                    }
                }
            });
        });
    }

    // Clear all categories
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Object.values(treeData).forEach(function(data) {
                if (data.checkbox) {
                    data.checkbox.checked = false;
                    data.checkbox.indeterminate = false;
                }
            });
            updateCategorySelection();
        });
    }

    // Select all categories
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Object.values(treeData).forEach(function(data) {
                if (data.checkbox) {
                    data.checkbox.checked = true;
                    data.checkbox.indeterminate = false;
                }
            });
            updateCategorySelection();
        });
    }

    // Expand all
    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Object.values(treeData).forEach(function(data) {
                data.node.style.display = 'flex';
                var toggle = data.node.querySelector('.ast-tree-toggle');
                if (toggle) toggle.classList.add('expanded');
            });
        });
    }

    // Collapse all
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Object.values(treeData).forEach(function(data) {
                var depth = parseInt(data.node.dataset.depth) || 0;
                data.node.style.display = depth === 0 ? 'flex' : 'none';
                var toggle = data.node.querySelector('.ast-tree-toggle');
                if (toggle) toggle.classList.remove('expanded');
            });
        });
    }

    // ===== AI SKILLS - FULL CLIENT-SIDE =====
    var apiBaseUrl = '{$apiUrl|escape:"javascript":"UTF-8"}'.replace(/\/+$/, '');
    var skillAccessToken = '{$accessToken|escape:"javascript":"UTF-8"}';
    var skillChatModelId = '{$chatModelId|escape:"javascript":"UTF-8"}';
    var skillLang = '{$currentLang|escape:"javascript":"UTF-8"}';

    // DOM refs - Installed
    var sfGrid = document.getElementById('ast-smartflows-grid');
    var sfLoading = document.getElementById('ast-smartflows-loading');
    var sfEmpty = document.getElementById('ast-smartflows-empty');
    var sfStats = document.getElementById('ast-skills-stats');

    // DOM refs - Marketplace
    var tplGrid = document.getElementById('ast-templates-grid');
    var tplLoading = document.getElementById('ast-templates-loading');
    var tplEmpty = document.getElementById('ast-templates-empty');
    var tplCountEl = document.getElementById('ast-templates-count');
    var tplTotalEl = document.getElementById('ast-templates-total');
    var tplPagination = document.getElementById('ast-templates-pagination');
    var tplPrev = document.getElementById('ast-templates-prev');
    var tplNext = document.getElementById('ast-templates-next');
    var tplPageInfo = document.getElementById('ast-templates-page-info');
    var updatesAlert = document.getElementById('ast-updates-alert');
    var updatesCount = document.getElementById('ast-updates-count');

    var installedTemplatesData = {};
    var currentFilters = { search: '', platform: 'prestashop', integration: '', triggerType: '', installed: null, hasUpdate: null, sortBy: 'downloads', sortOrder: 'desc', page: 1, limit: 20 };
    var paginationState = { page: 1, totalPages: 1, total: 0 };
    var searchTimeout = null;

    function getConfigureUrl(wId) { return apiBaseUrl + '/' + skillLang + '/admin/chatModel/' + skillChatModelId + '/smartflows/' + wId; }

    function apiRequest(method, endpoint, body) {
        var opts = { method: method, headers: { 'Authorization': 'Bearer ' + skillAccessToken, 'Content-Type': 'application/json', 'x-chat-model-id': skillChatModelId } };
        if (body) opts.body = JSON.stringify(body);
        return fetch(apiBaseUrl + endpoint, opts).then(function(r) {
            if (!r.ok) return r.json().then(function(e) { return Promise.reject(e); }).catch(function() { return Promise.reject({ error: 'HTTP ' + r.status }); });
            return r.json();
        });
    }

    function buildQueryString() {
        var p = [];
        if (currentFilters.platform) p.push('platform=' + encodeURIComponent(currentFilters.platform));
        p.push('lang=' + encodeURIComponent(skillLang));
        p.push('limit=' + currentFilters.limit);
        p.push('page=' + currentFilters.page);
        if (currentFilters.search) p.push('search=' + encodeURIComponent(currentFilters.search));
        if (currentFilters.integration) p.push('integrations=' + encodeURIComponent(currentFilters.integration));
        if (currentFilters.triggerType) p.push('triggerTypes=' + encodeURIComponent(currentFilters.triggerType));
        if (currentFilters.installed !== null) p.push('installed=' + currentFilters.installed);
        if (currentFilters.hasUpdate !== null) p.push('hasUpdate=' + currentFilters.hasUpdate);
        if (currentFilters.sortBy) { p.push('sortBy=' + currentFilters.sortBy); p.push('sortOrder=' + currentFilters.sortOrder); }
        return p.join('&');
    }

    function getSkillTypeBadge(triggerType) {
        var map = {
            'CONVERSATION_TOOL': { emoji: '🤖', label: '{l s="Conversation tool" mod="aismarttalk"}', cls: 'conversation-tool' },
            'WEBHOOK': { emoji: '🔗', label: 'Webhook', cls: 'webhook' },
            'SMART_FORM_WORKFLOW': { emoji: '📝', label: 'SmartForm', cls: 'smartform' },
            'SMART_FORM_SEQUENCE_WORKFLOW': { emoji: '📋', label: 'Sequence', cls: 'sequence' },
            'NAVIGATION_EVENT': { emoji: '🧭', label: 'Navigation', cls: 'navigation' },
            'CHAT_SERVICE': { emoji: '💬', label: 'Chat', cls: 'chat' },
            'SCHEDULE_WORKFLOW': { emoji: '⏰', label: '{l s="Scheduled" mod="aismarttalk"}', cls: 'scheduled' },
            'DICTAPHONE': { emoji: '🎙️', label: 'Dictaphone', cls: 'dictaphone' }
        };
        return map[triggerType] || { emoji: '⚡', label: 'Skill', cls: 'default' };
    }

    function getSkillTriggerText(t) {
        if (t.triggerDescription) return t.triggerDescription;
        var m = { 'CHAT_SERVICE': '{l s="When a visitor asks your assistant" mod="aismarttalk"}', 'NAVIGATION_EVENT': '{l s="When a visitor browses your site" mod="aismarttalk"}', 'WEBHOOK': '{l s="When an event is triggered" mod="aismarttalk"}' };
        return m[t.triggerType] || '{l s="Based on context" mod="aismarttalk"}';
    }

    function getSkillActionText(t) { return t.actionDescription || t.description || ''; }

    function escHtml(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    // ---- RENDER INSTALLED SKILLS ----
    function renderInstalledSmartflows(data) {
        if (sfLoading) sfLoading.style.display = 'none';
        var installed = data.installed || [];
        var stats = data.stats || {};

        if (sfStats && installed.length > 0) {
            var sa = document.getElementById('ast-stat-active');
            var si = document.getElementById('ast-stat-inactive');
            if (sa) sa.textContent = stats.activeCount || 0;
            if (si) si.textContent = stats.inactiveCount || 0;
            sfStats.style.display = 'flex';
        }
        if (updatesAlert && updatesCount && stats.updatesAvailable > 0) {
            updatesCount.textContent = stats.updatesAvailable;
            updatesAlert.style.display = 'flex';
        }
        if (!installed.length) { if (sfEmpty) sfEmpty.style.display = 'block'; return; }

        installed.forEach(function(item) {
            installedTemplatesData[item.workflowId] = item;
            if (item.templateId) installedTemplatesData[item.templateId] = item;
        });

        var html = '';
        installed.forEach(function(sk) {
            var isManual = sk.isManual || !sk.templateId;
            var hasUpdate = !isManual && (sk.hasUpdate || (sk.installedVersion !== sk.currentVersion));
            var statusClass = sk.isActive ? 'active' : 'paused';
            var badge = getSkillTypeBadge(sk.triggerType);

            html += '<div class="ast-skill-card' + (hasUpdate ? ' has-update' : '') + (isManual ? ' is-manual' : '') + ' ' + statusClass + '">';
            if (isManual) {
                html += '<div class="ast-skill-type-badge custom">🛠️ {l s="Custom" mod="aismarttalk"}</div>';
            } else {
                html += '<div class="ast-skill-type-badge ' + badge.cls + '">' + badge.emoji + ' ' + escHtml(badge.label) + '</div>';
            }
            html += '<div class="ast-skill-header-row">';
            html += '<span class="ast-skill-icon">' + (sk.icon || '⚡') + '</span>';
            html += '<h4 class="ast-skill-name">' + escHtml(sk.workflowName || sk.templateName || 'Skill') + '</h4>';
            if (hasUpdate) html += '<span class="ast-skill-update-badge" title="v' + escHtml(sk.currentVersion) + ' {l s="available" mod="aismarttalk"}">🔄</span>';
            html += '</div>';
            html += '<div class="ast-skill-meta">';
            html += '<span class="ast-skill-version">' + (isManual ? '{l s="Custom" mod="aismarttalk"}' : 'v' + escHtml(sk.installedVersion || '1.0.0')) + '</span>';
            html += '<span class="ast-skill-status ' + statusClass + '">' + (sk.isActive ? '🟢 {l s="Active" mod="aismarttalk"}' : '⏸️ {l s="Paused" mod="aismarttalk"}') + '</span>';
            html += '</div>';
            var desc = sk.templateDescription || sk.description;
            if (desc) html += '<p class="ast-skill-desc">' + escHtml(desc) + '</p>';
            html += '<div class="ast-skill-actions">';
            if (hasUpdate && sk.templateId) html += '<button type="button" class="ast-skill-btn ast-skill-btn-update" data-template-id="' + escHtml(sk.templateId) + '">🔄 {l s="Update" mod="aismarttalk"}</button>';
            html += '<a href="' + getConfigureUrl(sk.workflowId) + '" target="_blank" class="ast-skill-btn ast-skill-btn-configure">⚙️ {l s="Edit" mod="aismarttalk"}</a>';
            html += '</div></div>';
        });

        if (sfGrid) {
            sfGrid.innerHTML = html;
            sfGrid.style.display = 'grid';
            sfGrid.querySelectorAll('.ast-skill-btn-update').forEach(function(b) {
                b.addEventListener('click', function(e) { e.stopPropagation(); updateTemplate(b.dataset.templateId, b); });
            });
        }
    }

    // ---- RENDER MARKETPLACE ----
    function renderSkillAction(tpl) {
        var inst = tpl.installation || {};
        var local = installedTemplatesData[tpl.id];
        if (local) { inst.isInstalled = true; inst.workflowId = local.workflowId; inst.hasUpdate = local.hasUpdate; }

        if (inst.isInstalled && inst.workflowId) {
            var a = '<div class="ast-skill-actions">';
            if (inst.hasUpdate) a += '<button type="button" class="ast-skill-btn ast-skill-btn-update" data-template-id="' + escHtml(tpl.id) + '" title="{l s="Update available" mod="aismarttalk"}">🔄</button>';
            a += '<a href="' + getConfigureUrl(inst.workflowId) + '" target="_blank" class="ast-skill-btn ast-skill-btn-configure" title="{l s="Configure" mod="aismarttalk"}">⚙️</a>';
            a += '<button type="button" class="ast-skill-btn ast-skill-btn-remove" data-template-id="' + escHtml(tpl.id) + '" title="{l s="Remove" mod="aismarttalk"}">🗑️</button>';
            a += '</div>';
            return a;
        }
        if (tpl.missingIntegrations && tpl.missingIntegrations.length > 0) {
            return '<span class="ast-skill-missing">🔗 {l s="Setup needed" mod="aismarttalk"}</span>';
        }
        return '<button type="button" class="ast-skill-btn-install" data-template-id="' + escHtml(tpl.id) + '">➕ {l s="Add skill" mod="aismarttalk"}</button>';
    }

    function renderTemplates(data) {
        if (tplLoading) tplLoading.style.display = 'none';
        var templates = data.templates || [];
        var pg = data.pagination || {};
        paginationState.page = pg.page || 1;
        paginationState.totalPages = pg.totalPages || 1;
        paginationState.total = pg.total || 0;
        if (tplCountEl && tplTotalEl) { tplTotalEl.textContent = paginationState.total; tplCountEl.style.display = 'block'; }
        updatePaginationUI();

        if (!templates.length) { if (tplGrid) tplGrid.style.display = 'none'; if (tplEmpty) tplEmpty.style.display = 'block'; return; }
        if (tplEmpty) tplEmpty.style.display = 'none';

        var html = '';
        templates.forEach(function(t) {
            var inst = t.installation || {};
            var local = installedTemplatesData[t.id];
            if (local) { inst.isInstalled = true; inst.workflowId = local.workflowId; inst.hasUpdate = local.hasUpdate; }
            var isInstalled = inst.isInstalled || false;
            var hasUpdate = inst.hasUpdate || false;
            var badge = getSkillTypeBadge(t.triggerType);

            html += '<div class="ast-marketplace-card' + (isInstalled ? ' installed' : '') + (hasUpdate ? ' has-update' : '') + '">';
            html += '<div class="ast-marketplace-card-header">';
            html += '<span class="ast-skill-type-badge ' + badge.cls + '">' + badge.emoji + ' ' + escHtml(badge.label) + '</span>';
            html += '<div class="ast-marketplace-card-badges">';
            if (isInstalled) html += '<span class="ast-badge-installed">✅</span>';
            if (hasUpdate) html += '<span class="ast-badge-update">🔄</span>';
            html += '</div></div>';

            html += '<div class="ast-marketplace-card-body">';
            html += '<div class="ast-marketplace-card-title-row">';
            html += '<span class="ast-marketplace-card-icon">' + (t.icon || '⚡') + '</span>';
            html += '<h4 class="ast-marketplace-card-title">' + escHtml(t.name || 'Skill') + '</h4>';
            html += '</div>';

            html += '<div class="ast-marketplace-card-details">';
            html += '<div class="ast-detail-row"><span class="ast-detail-label">{l s="When:" mod="aismarttalk"}</span><span class="ast-detail-value">' + escHtml(getSkillTriggerText(t)) + '</span></div>';
            html += '<div class="ast-detail-row"><span class="ast-detail-label">{l s="Action:" mod="aismarttalk"}</span><span class="ast-detail-value">' + escHtml(getSkillActionText(t)) + '</span></div>';
            html += '</div>';

            if (t.requiredIntegrations && t.requiredIntegrations.length) {
                html += '<div class="ast-marketplace-card-platforms">';
                t.requiredIntegrations.forEach(function(ig) {
                    var miss = t.missingIntegrations && t.missingIntegrations.indexOf(ig) !== -1;
                    html += '<span class="ast-platform-tag' + (miss ? ' missing' : '') + '">🏷️ ' + escHtml(ig) + '</span>';
                });
                html += '</div>';
            }
            html += '</div>';

            html += '<div class="ast-marketplace-card-footer">';
            html += '<div class="ast-marketplace-card-stats">';
            if (t.downloads !== undefined) html += '<span class="ast-stat-item">📦 ' + t.downloads + ' {l s="installs" mod="aismarttalk"}</span>';
            if (t.version) html += '<span class="ast-stat-item ast-version-tag">v' + escHtml(t.version) + '</span>';
            html += '</div>';
            html += renderSkillAction(t);
            html += '</div></div>';
        });

        if (tplGrid) {
            tplGrid.innerHTML = html;
            tplGrid.style.display = 'grid';
            tplGrid.querySelectorAll('.ast-skill-btn-install').forEach(function(b) {
                b.addEventListener('click', function(e) { e.stopPropagation(); installTemplate(b.dataset.templateId, b); });
            });
            tplGrid.querySelectorAll('.ast-skill-btn-update').forEach(function(b) {
                b.addEventListener('click', function(e) { e.stopPropagation(); updateTemplate(b.dataset.templateId, b); });
            });
            tplGrid.querySelectorAll('.ast-skill-btn-remove').forEach(function(b) {
                b.addEventListener('click', function(e) { e.stopPropagation(); uninstallTemplate(b.dataset.templateId, b); });
            });
        }
    }

    function updatePaginationUI() {
        if (!tplPagination) return;
        if (paginationState.totalPages <= 1) { tplPagination.style.display = 'none'; return; }
        tplPagination.style.display = 'flex';
        tplPageInfo.textContent = paginationState.page + ' / ' + paginationState.totalPages;
        tplPrev.disabled = paginationState.page <= 1;
        tplNext.disabled = paginationState.page >= paginationState.totalPages;
    }

    // ---- FETCH DATA ----
    function fetchInstalledTemplates() {
        if (sfLoading) sfLoading.style.display = 'flex';
        if (sfGrid) sfGrid.style.display = 'none';
        if (sfEmpty) sfEmpty.style.display = 'none';
        apiRequest('GET', '/api/v1/smartflow-templates/installed?lang=' + skillLang)
        .then(renderInstalledSmartflows)
        .catch(function() { if (sfLoading) sfLoading.style.display = 'none'; if (sfEmpty) sfEmpty.style.display = 'block'; });
    }

    function fetchTemplates() {
        if (tplLoading) tplLoading.style.display = 'flex';
        if (tplGrid) tplGrid.style.display = 'none';
        if (tplEmpty) tplEmpty.style.display = 'none';
        apiRequest('GET', '/api/v1/smartflow-templates?' + buildQueryString())
        .then(renderTemplates)
        .catch(function() { if (tplLoading) tplLoading.style.display = 'none'; if (tplEmpty) tplEmpty.style.display = 'block'; });
    }

    function onFilterChange() { currentFilters.page = 1; fetchTemplates(); }

    // ---- INSTALL / UPDATE / UNINSTALL ----
    function installTemplate(templateId, btn) {
        btn.disabled = true; var orig = btn.innerHTML; btn.innerHTML = '⏳';
        apiRequest('POST', '/api/v1/smartflow-templates/' + templateId + '/install', { configuration: {}, lang: skillLang })
        .then(function(d) {
            if (d.success || d.workflowId) { localStorage.setItem('ast_active_tab', 'skills'); location.reload(); }
            else { btn.disabled = false; btn.innerHTML = orig; alert(d.message || d.error || '{l s="Installation failed" mod="aismarttalk"}'); }
        }).catch(function() { btn.disabled = false; btn.innerHTML = orig; alert('{l s="Installation failed. Please try again." mod="aismarttalk"}'); });
    }

    function updateTemplate(templateId, btn) {
        btn.disabled = true; var orig = btn.innerHTML; btn.innerHTML = '⏳';
        apiRequest('POST', '/api/v1/smartflow-templates/' + templateId + '/install', { configuration: {}, lang: skillLang })
        .then(function(d) {
            if (d.success || d.workflowId) { localStorage.setItem('ast_active_tab', 'skills'); location.reload(); }
            else { btn.disabled = false; btn.innerHTML = orig; alert(d.message || d.error || '{l s="Update failed" mod="aismarttalk"}'); }
        }).catch(function() { btn.disabled = false; btn.innerHTML = orig; alert('{l s="Update failed. Please try again." mod="aismarttalk"}'); });
    }

    function uninstallTemplate(templateId, btn) {
        if (!confirm('{l s="Are you sure you want to uninstall this skill? This action cannot be undone." mod="aismarttalk"}')) return;
        btn.disabled = true; var orig = btn.innerHTML; btn.innerHTML = '⏳';
        apiRequest('DELETE', '/api/v1/smartflow-templates/' + templateId)
        .then(function(d) {
            if (d.success) { localStorage.setItem('ast_active_tab', 'skills'); location.reload(); }
            else { btn.disabled = false; btn.innerHTML = orig; alert(d.message || d.error || '{l s="Uninstall failed" mod="aismarttalk"}'); }
        }).catch(function() { btn.disabled = false; btn.innerHTML = orig; alert('{l s="Uninstall failed. Please try again." mod="aismarttalk"}'); });
    }

    // ---- EVENT LISTENERS ----
    var searchEl = document.getElementById('ast-templates-search');
    if (searchEl) searchEl.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        var val = e.target.value.trim();
        searchTimeout = setTimeout(function() { currentFilters.search = val; onFilterChange(); }, 300);
    });
    var fPlatform = document.getElementById('ast-filter-platform');
    if (fPlatform) fPlatform.addEventListener('change', function(e) { currentFilters.platform = e.target.value; onFilterChange(); });
    var fTrigger = document.getElementById('ast-filter-trigger');
    if (fTrigger) fTrigger.addEventListener('change', function(e) { currentFilters.triggerType = e.target.value; onFilterChange(); });
    var fStatus = document.getElementById('ast-filter-status');
    if (fStatus) fStatus.addEventListener('change', function(e) {
        currentFilters.installed = null; currentFilters.hasUpdate = null;
        if (e.target.value === 'installed') currentFilters.installed = true;
        else if (e.target.value === 'not-installed') currentFilters.installed = false;
        else if (e.target.value === 'has-update') currentFilters.hasUpdate = true;
        onFilterChange();
    });
    var fSort = document.getElementById('ast-filter-sort');
    if (fSort) fSort.addEventListener('change', function(e) {
        currentFilters.sortBy = e.target.value;
        currentFilters.sortOrder = (e.target.value === 'name') ? 'asc' : 'desc';
        onFilterChange();
    });
    if (tplPrev) tplPrev.addEventListener('click', function() { if (currentFilters.page > 1) { currentFilters.page--; fetchTemplates(); } });
    if (tplNext) tplNext.addEventListener('click', function() { if (currentFilters.page < paginationState.totalPages) { currentFilters.page++; fetchTemplates(); } });

    // ---- INIT ----
    if (skillChatModelId && skillAccessToken) {
        fetchInstalledTemplates();
        fetchTemplates();
    }

});

</script>

{* ===== CHATBOT PREVIEW IN ADMIN ===== *}
{if $isConnected && $chatModelId}
<!-- AI SmartTalk Chatbot Preview (Admin) -->
<script>
window.chatbotSettings = JSON.parse(atob("{$chatbotSettingsEncoded|escape:'html':'UTF-8'}"));
window.onChatbotLogout = function() {
  document.cookie = 'ai_smarttalk_oauth_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/;';
  delete window.chatbotSettings.userToken;
};
</script>
<script src="{$cdnUrl|escape:'html':'UTF-8'}/universal-chatbot.js" async></script>
{/if}
