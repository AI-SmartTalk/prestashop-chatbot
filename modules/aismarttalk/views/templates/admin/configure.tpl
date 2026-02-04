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

/* SmartFlow Styles - Legacy */
.aismarttalk-smartflows-loading,
.aismarttalk-smartflows-empty,
.aismarttalk-templates-loading,
.aismarttalk-templates-empty {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #6c757d;
}

.aismarttalk-smartflows-grid,
.aismarttalk-templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.aismarttalk-smartflow-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.aismarttalk-smartflow-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.aismarttalk-smartflow-info h5 {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
}

.aismarttalk-smartflow-info p {
    margin: 0 0 8px 0;
    font-size: 12px;
    color: #6c757d;
}

.aismarttalk-smartflow-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.aismarttalk-smartflow-status.active {
    background: rgba(40, 167, 69, 0.15);
    color: #28a745;
}

.aismarttalk-smartflow-status.inactive {
    background: rgba(108, 117, 125, 0.15);
    color: #6c757d;
}

.aismarttalk-template-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.aismarttalk-template-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.aismarttalk-template-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.aismarttalk-template-icon {
    font-size: 28px;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.aismarttalk-template-title h5 {
    margin: 0 0 2px 0;
    font-size: 14px;
    font-weight: 600;
}

.aismarttalk-template-title span {
    font-size: 12px;
    color: #6c757d;
}

.aismarttalk-template-body {
    padding: 15px;
}

.aismarttalk-template-desc {
    margin: 0 0 10px 0;
    font-size: 13px;
    color: #495057;
    line-height: 1.5;
}

.aismarttalk-template-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 15px;
}

.aismarttalk-template-tag {
    padding: 3px 8px;
    background: #e9ecef;
    border-radius: 4px;
    font-size: 11px;
    color: #495057;
}

.aismarttalk-template-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
}

.aismarttalk-template-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #ffc107;
}

.aismarttalk-template-install {
    padding: 8px 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 6px;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.aismarttalk-template-install:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.aismarttalk-template-configure {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border: none;
    border-radius: 6px;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.aismarttalk-template-configure:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    color: #fff;
    text-decoration: none;
}

/* =============================================
   AI Skills - Modern UI Styles
   ============================================= */

/* Update Banner */
.aismarttalk-skills-update-banner {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.15) 0%, rgba(245, 158, 11, 0.1) 100%);
    border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 12px;
    margin-bottom: 24px;
}

.aismarttalk-update-banner-content {
    display: flex;
    align-items: center;
    gap: 16px;
}

.aismarttalk-update-banner-icon {
    font-size: 28px;
}

.aismarttalk-update-banner-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.aismarttalk-update-banner-text strong {
    color: #d97706;
    font-size: 14px;
}

.aismarttalk-update-banner-text span {
    color: #78716c;
    font-size: 13px;
}

/* Skills Section */
.aismarttalk-skills-section {
    margin-bottom: 32px;
}

.aismarttalk-skills-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}

.aismarttalk-skills-section-title {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.aismarttalk-skills-section-icon {
    font-size: 32px;
    line-height: 1;
}

.aismarttalk-skills-section-title h3 {
    margin: 0 0 4px 0;
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
}

.aismarttalk-skills-section-title p {
    margin: 0;
    font-size: 14px;
    color: #6b7280;
}

/* Skills Stats */
.aismarttalk-skills-stats {
    display: flex;
    gap: 16px;
}

.aismarttalk-skill-stat {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #f3f4f6;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
}

.aismarttalk-skill-stat.active {
    color: #059669;
    background: rgba(16, 185, 129, 0.1);
}

.aismarttalk-skill-stat.inactive {
    color: #6b7280;
}

/* Skills Container */
.aismarttalk-skills-container {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 24px;
}

/* Loading State */
.aismarttalk-skills-loading,
.aismarttalk-marketplace-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px;
    gap: 16px;
}

.aismarttalk-loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(102, 126, 234, 0.2);
    border-top-color: #667eea;
    border-radius: 50%;
    animation: aismarttalk-spin 0.8s linear infinite;
}

@keyframes aismarttalk-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.aismarttalk-skills-loading p,
.aismarttalk-marketplace-loading p {
    color: #6b7280;
    font-size: 14px;
    margin: 0;
}

/* Empty State */
.aismarttalk-empty-state {
    text-align: center;
    padding: 48px 24px;
}

.aismarttalk-empty-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 16px;
}

.aismarttalk-empty-state h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
}

.aismarttalk-empty-state p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

/* Skills Grid (My Skills) */
.aismarttalk-skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
}

/* Skill Card */
.aismarttalk-skill-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    transition: all 0.2s ease;
}

.aismarttalk-skill-card:hover {
    background: #fafafa;
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.aismarttalk-skill-card.has-update {
    border-color: rgba(251, 191, 36, 0.4);
    background: rgba(251, 191, 36, 0.05);
}

.aismarttalk-skill-card.paused {
    opacity: 0.7;
}

/* Manual workflow card styling */
.aismarttalk-skill-card.is-manual {
    border-left: 3px solid rgba(139, 92, 246, 0.5);
}

.aismarttalk-skill-icon {
    font-size: 32px;
    line-height: 1;
    flex-shrink: 0;
}

.aismarttalk-skill-content {
    flex: 1;
    min-width: 0;
}

.aismarttalk-skill-header-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.aismarttalk-skill-header-row .aismarttalk-skill-icon {
    font-size: 28px;
}

.aismarttalk-skill-header-row .aismarttalk-skill-name {
    flex: 1;
    margin: 0;
    font-size: 16px;
}

.aismarttalk-skill-name {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.aismarttalk-skill-update-badge {
    font-size: 14px;
    animation: aismarttalk-pulse 2s infinite;
}

@keyframes aismarttalk-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.aismarttalk-skill-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}

.aismarttalk-skill-version {
    font-size: 11px;
    color: #9ca3af;
    font-family: 'Monaco', 'Consolas', monospace;
}

.aismarttalk-skill-status {
    font-size: 12px;
}

.aismarttalk-skill-status.active {
    color: #059669;
}

.aismarttalk-skill-status.paused {
    color: #6b7280;
}

.aismarttalk-skill-desc {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
}

/* Skill Actions */
.aismarttalk-skill-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid #f3f4f6;
    margin-top: auto;
}

.aismarttalk-skill-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.aismarttalk-skill-btn:hover {
    background: #e5e7eb;
    transform: scale(1.05);
}

.aismarttalk-skill-btn-update {
    background: rgba(251, 191, 36, 0.15);
    border-color: rgba(251, 191, 36, 0.3);
}

.aismarttalk-skill-btn-update:hover {
    background: rgba(251, 191, 36, 0.25);
}

.aismarttalk-skill-btn-configure {
    background: rgba(102, 126, 234, 0.15);
    border-color: rgba(102, 126, 234, 0.3);
}

.aismarttalk-skill-btn-configure:hover {
    background: rgba(102, 126, 234, 0.25);
}

.aismarttalk-skill-btn-remove {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
}

.aismarttalk-skill-btn-remove:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* =============================================
   Skills Marketplace
   ============================================= */

.aismarttalk-marketplace-section {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 28px;
}

/* Marketplace Toolbar */
.aismarttalk-marketplace-toolbar {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding-bottom: 20px;
    margin-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}

/* Search */
.aismarttalk-marketplace-search {
    position: relative;
    max-width: 100%;
}

.aismarttalk-search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    opacity: 0.6;
}

.aismarttalk-marketplace-search input {
    width: 100%;
    padding: 14px 20px 14px 48px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    color: #1f2937;
    font-size: 15px;
    transition: all 0.2s ease;
}

.aismarttalk-marketplace-search input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
}

.aismarttalk-marketplace-search input::placeholder {
    color: #9ca3af;
}

/* Filters */
.aismarttalk-marketplace-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.aismarttalk-filter-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.aismarttalk-filter-item label {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.aismarttalk-filter-select {
    padding: 10px 16px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    color: #1f2937;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 140px;
}

.aismarttalk-filter-select:hover {
    background: #fafafa;
    border-color: #d1d5db;
}

.aismarttalk-filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}

/* Count */
.aismarttalk-marketplace-count {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 16px;
}

.aismarttalk-marketplace-count #aismarttalk-templates-total {
    color: #1f2937;
    font-weight: 600;
}

/* Marketplace Grid */
.aismarttalk-marketplace-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

/* Marketplace Card */
.aismarttalk-marketplace-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
}

.aismarttalk-marketplace-card:hover {
    border-color: #667eea;
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
}

.aismarttalk-marketplace-card.installed {
    border-color: rgba(16, 185, 129, 0.3);
}

.aismarttalk-marketplace-card.has-update {
    border-color: rgba(251, 191, 36, 0.4);
}

/* Card Header */
.aismarttalk-marketplace-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 20px 20px 0 20px;
}

.aismarttalk-marketplace-card-icon {
    font-size: 40px;
    line-height: 1;
}

.aismarttalk-marketplace-card-badges {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-end;
}

.aismarttalk-badge-installed {
    font-size: 11px;
    padding: 4px 10px;
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border-radius: 6px;
    font-weight: 600;
}

.aismarttalk-badge-update {
    font-size: 11px;
    padding: 4px 10px;
    background: rgba(251, 191, 36, 0.15);
    color: #d97706;
    border-radius: 6px;
    font-weight: 600;
    animation: aismarttalk-pulse 2s infinite;
}

/* Card Body */
.aismarttalk-marketplace-card-body {
    padding: 16px 20px;
    flex: 1;
}

.aismarttalk-marketplace-card-title-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.aismarttalk-marketplace-card-title-row .aismarttalk-marketplace-card-icon {
    font-size: 32px;
    line-height: 1;
}

.aismarttalk-marketplace-card-title {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.3;
    flex: 1;
}

.aismarttalk-marketplace-card-desc {
    margin: 0 0 12px 0;
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Detail rows (When/Action) */
.aismarttalk-marketplace-card-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 10px;
    margin-bottom: 12px;
}

.aismarttalk-detail-row {
    display: flex;
    gap: 8px;
    font-size: 12px;
    line-height: 1.4;
}

.aismarttalk-detail-label {
    color: #9ca3af;
    font-weight: 600;
    white-space: nowrap;
    min-width: 60px;
}

.aismarttalk-detail-value {
    color: #4b5563;
    flex: 1;
}

/* Platform Tags */
.aismarttalk-marketplace-card-platforms {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.aismarttalk-platform-tag {
    font-size: 10px;
    padding: 4px 8px;
    background: rgba(102, 126, 234, 0.15);
    color: #667eea;
    border-radius: 5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.aismarttalk-platform-tag.missing {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

/* Card Footer */
.aismarttalk-marketplace-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}

.aismarttalk-marketplace-card-stats {
    display: flex;
    gap: 12px;
}

.aismarttalk-stat-item {
    font-size: 12px;
    color: #6b7280;
}

.aismarttalk-version-tag {
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 11px;
}

/* Skill Action Buttons */
.aismarttalk-skill-btn-install {
    padding: 10px 18px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.aismarttalk-skill-btn-install:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.aismarttalk-skill-missing {
    font-size: 12px;
    color: #d97706;
    padding: 8px 14px;
    background: rgba(251, 191, 36, 0.1);
    border-radius: 8px;
}

/* Pagination */
.aismarttalk-marketplace-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding-top: 24px;
    margin-top: 24px;
    border-top: 1px solid #e5e7eb;
}

.aismarttalk-pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    padding: 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    color: #1f2937;
    cursor: pointer;
    transition: all 0.2s ease;
}

.aismarttalk-pagination-btn:hover:not(:disabled) {
    background: rgba(102, 126, 234, 0.1);
    border-color: #667eea;
}

.aismarttalk-pagination-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

#aismarttalk-templates-page-info {
    font-size: 14px;
    color: #6b7280;
    min-width: 70px;
    text-align: center;
    font-weight: 500;
}

/* =============================================
   Skill Type Badges
   ============================================= */

.aismarttalk-skill-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.aismarttalk-skill-type-badge.tool {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.15) 100%);
    color: #667eea;
    border: 1px solid rgba(102, 126, 234, 0.3);
}

.aismarttalk-skill-type-badge.auto {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.15) 100%);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.aismarttalk-skill-type-badge.webhook {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.15) 100%);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.aismarttalk-skill-type-badge.default {
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #e5e7eb;
}

.aismarttalk-skill-type-badge.aismarttalk-skill-type-custom {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(124, 58, 237, 0.15) 100%);
    color: #7c3aed;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

/* =============================================
   Channels Section
   ============================================= */

.aismarttalk-channels-section {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 24px;
}

.aismarttalk-channels-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

/* =============================================
   Integrations Section
   ============================================= */

.aismarttalk-integrations-section {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 28px;
}

.aismarttalk-integrations-container {
    min-height: 100px;
}

.aismarttalk-integrations-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    gap: 16px;
}

.aismarttalk-integrations-loading p {
    color: #6b7280;
    font-size: 14px;
    margin: 0;
}

.aismarttalk-integrations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.aismarttalk-integration-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    transition: all 0.2s ease;
}

.aismarttalk-integration-card:hover {
    background: #fafafa;
    border-color: #d1d5db;
}

.aismarttalk-integration-card.connected {
    border-color: rgba(16, 185, 129, 0.25);
    background: rgba(16, 185, 129, 0.03);
}

.aismarttalk-integration-info {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 0;
}

.aismarttalk-integration-logo {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}

.aismarttalk-integration-logo img {
    width: 28px;
    height: 28px;
    object-fit: contain;
}

.aismarttalk-integration-logo-fallback {
    font-size: 24px;
    line-height: 1;
}

.aismarttalk-integration-icon {
    font-size: 28px;
    line-height: 1;
}

.aismarttalk-integration-details {
    flex: 1;
    min-width: 0;
}

.aismarttalk-integration-details h4 {
    margin: 0 0 2px 0;
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
}

.aismarttalk-integration-category {
    font-size: 11px;
    color: #9ca3af;
    text-transform: capitalize;
}

.aismarttalk-integration-details p {
    margin: 0;
    font-size: 12px;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.aismarttalk-integration-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.aismarttalk-integration-badge {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 500;
    white-space: nowrap;
}

.aismarttalk-integration-badge.connected {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
}

.aismarttalk-integration-badge.not-connected {
    background: #f3f4f6;
    color: #6b7280;
}

.aismarttalk-integration-link {
    font-size: 12px;
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
}

.aismarttalk-integration-link:hover {
    color: #4f46e5;
    text-decoration: underline;
}

.aismarttalk-integrations-empty {
    padding: 40px;
}

/* Hidden integration cards (collapsed state) */
.aismarttalk-integration-hidden {
    display: none;
}

/* Expand/Collapse button */
.aismarttalk-expand-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 16px;
    padding: 12px 20px;
    background: #fff;
    border: 1px dashed #d1d5db;
    border-radius: 12px;
    color: #6b7280;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.aismarttalk-expand-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    color: #4b5563;
}

.aismarttalk-expand-btn:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.3);
}

.aismarttalk-expand-text {
    font-size: 13px;
}

.aismarttalk-expand-icon {
    font-size: 10px;
    transition: transform 0.2s ease;
}

.aismarttalk-expand-btn[data-expanded="true"] .aismarttalk-expand-icon {
    transform: rotate(180deg);
}

/* =============================================
   Responsive Styles for AI Skills
   ============================================= */

@media (max-width: 960px) {
    .aismarttalk-marketplace-filters {
        flex-wrap: wrap;
    }

    .aismarttalk-filter-select {
        min-width: calc(50% - 5px);
    }

    .aismarttalk-skills-section-header {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .aismarttalk-skills-grid,
    .aismarttalk-marketplace-grid,
    .aismarttalk-integrations-grid,
    .aismarttalk-channels-grid {
        grid-template-columns: 1fr;
    }

    .aismarttalk-skill-card {
        flex-wrap: wrap;
    }

    .aismarttalk-skill-actions {
        width: 100%;
        justify-content: flex-end;
        margin-top: 8px;
    }

    .aismarttalk-skills-stats {
        width: 100%;
        justify-content: flex-start;
    }

    .aismarttalk-marketplace-toolbar {
        gap: 12px;
    }

    .aismarttalk-filter-select {
        min-width: 100%;
    }

    .aismarttalk-filter-item {
        flex: 1;
        min-width: calc(50% - 5px);
    }

    .aismarttalk-marketplace-section,
    .aismarttalk-channels-section,
    .aismarttalk-integrations-section {
        padding: 20px;
    }

    .aismarttalk-marketplace-card-footer {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
    }

    .aismarttalk-marketplace-card-stats {
        justify-content: center;
    }

    .aismarttalk-skill-btn-install {
        width: 100%;
        justify-content: center;
    }

    .aismarttalk-integration-card {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .aismarttalk-integration-status {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
    }
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
            {* Configuration source indicator and sync buttons *}
            <div class="alert {if $hasLocalOverrides}alert-info{else}alert-success{/if}" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>
                    {if $hasLocalOverrides}
                        <i class="icon icon-pencil"></i>
                        <strong>{l s='Using local customizations' mod='aismarttalk'}</strong>
                        <span style="opacity: 0.8;"> - {l s='Your saved settings override the API defaults.' mod='aismarttalk'}</span>
                    {else}
                        <i class="icon icon-cloud-download"></i>
                        <strong>{l s='Using API defaults' mod='aismarttalk'}</strong>
                        <span style="opacity: 0.8;"> - {l s='Configuration is synced from AI SmartTalk.' mod='aismarttalk'}</span>
                    {/if}
                    {if $cacheMetadata}
                        <br><small style="opacity: 0.6;">
                            {l s='Last sync:' mod='aismarttalk'} {$cacheMetadata.created_at|date_format:"%Y-%m-%d %H:%M:%S"}
                            {if $cacheMetadata.ttl_remaining > 0}
                                ({l s='refreshes in' mod='aismarttalk'} {($cacheMetadata.ttl_remaining/60)|intval} min)
                            {/if}
                        </small>
                    {/if}
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="{$moduleLink|escape:'html':'UTF-8'}&refreshEmbedConfig=1" class="btn btn-default btn-sm" title="{l s='Fetch latest configuration from AI SmartTalk' mod='aismarttalk'}">
                        <i class="icon icon-refresh"></i> {l s='Sync from API' mod='aismarttalk'}
                    </a>
                    {if $hasLocalOverrides}
                        <a href="{$moduleLink|escape:'html':'UTF-8'}&resetLocalCustomizations=1" class="btn btn-warning btn-sm" onclick="return confirm('{l s='This will clear all your local customizations and use API defaults. Continue?' mod='aismarttalk'}')" title="{l s='Reset to API defaults' mod='aismarttalk'}">
                            <i class="icon icon-undo"></i> {l s='Reset to defaults' mod='aismarttalk'}
                        </a>
                    {/if}
                </div>
            </div>

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

    {* ===== SECTION 5: AI SKILLS ===== *}
    <div class="panel" id="smartflow">
        <div class="panel-heading">
            <h3><span style="margin-right: 8px;">⚡</span> {l s='AI Skills' mod='aismarttalk'}</h3>
            <p class="text-muted" style="margin: 5px 0 0 0; font-weight: normal;">{l s='Add skills to your AI assistant. It can create content, react to visitors, and connect to your tools.' mod='aismarttalk'}</p>
        </div>
        <div class="panel-body">

            {* Updates Available Alert *}
            <div id="aismarttalk-updates-alert" class="aismarttalk-skills-update-banner" style="display: none;">
                <div class="aismarttalk-update-banner-content">
                    <span class="aismarttalk-update-banner-icon">🔄</span>
                    <div class="aismarttalk-update-banner-text">
                        <strong>{l s='Updates Available' mod='aismarttalk'}</strong>
                        <span><span id="aismarttalk-updates-count">0</span> {l s='skill(s) can be updated to the latest version.' mod='aismarttalk'}</span>
                    </div>
                </div>
            </div>

            {* My Skills (Installed) *}
            <div class="aismarttalk-skills-section">
                <div class="aismarttalk-skills-section-header">
                    <div class="aismarttalk-skills-section-title">
                        <span class="aismarttalk-skills-section-icon">🎯</span>
                        <div>
                            <h3>{l s='My Skills' mod='aismarttalk'}</h3>
                            <p>{l s='Active skills on your assistant. It uses them automatically based on context.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                    <div id="aismarttalk-skills-stats" class="aismarttalk-skills-stats" style="display: none;">
                        <span class="aismarttalk-skill-stat active"><span id="stat-active">0</span> {l s='active' mod='aismarttalk'}</span>
                        <span class="aismarttalk-skill-stat inactive"><span id="stat-inactive">0</span> {l s='paused' mod='aismarttalk'}</span>
                    </div>
                </div>

                <div id="aismarttalk-smartflows-container" class="aismarttalk-skills-container">
                    <div class="aismarttalk-smartflows-loading aismarttalk-skills-loading">
                        <div class="aismarttalk-loading-spinner"></div>
                        <p>{l s='Loading your skills...' mod='aismarttalk'}</p>
                    </div>
                    <div class="aismarttalk-smartflows-grid aismarttalk-skills-grid" style="display: none;"></div>
                    <div class="aismarttalk-smartflows-empty aismarttalk-skills-empty" style="display: none;">
                        <div class="aismarttalk-empty-state">
                            <span class="aismarttalk-empty-icon">🚀</span>
                            <h4>{l s='No skills installed yet' mod='aismarttalk'}</h4>
                            <p>{l s='Explore the Marketplace below to get started.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                    <div class="aismarttalk-smartflows-error aismarttalk-skills-error" style="display: none;">
                        <i class="icon icon-warning"></i>
                        <p></p>
                    </div>
                </div>
            </div>

            {* Skills Marketplace *}
            <div class="aismarttalk-skills-section aismarttalk-marketplace-section">
                <div class="aismarttalk-skills-section-header">
                    <div class="aismarttalk-skills-section-title">
                        <span class="aismarttalk-skills-section-icon">🏪</span>
                        <div>
                            <h3>{l s='Marketplace' mod='aismarttalk'}</h3>
                            <p>{l s='Discover new skills for your assistant. One-click install.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                </div>

                {* Search and Filters *}
                <div id="aismarttalk-templates-toolbar" class="aismarttalk-marketplace-toolbar">
                    <div class="aismarttalk-marketplace-search">
                        <span class="aismarttalk-search-icon">🔍</span>
                        <input type="text" id="aismarttalk-templates-search-input" placeholder="{l s='Search skills... (e.g., "create article", "welcome")' mod='aismarttalk'}" />
                    </div>
                    <div class="aismarttalk-marketplace-filters">
                        <div class="aismarttalk-filter-item">
                            <label>{l s='Platform' mod='aismarttalk'}</label>
                            <select id="aismarttalk-filter-platform" class="aismarttalk-filter-select">
                                <option value="">{l s='All platforms' mod='aismarttalk'}</option>
                                <option value="prestashop" selected>🛒 {l s='PrestaShop' mod='aismarttalk'}</option>
                                <option value="wordpress">🌐 {l s='WordPress' mod='aismarttalk'}</option>
                                <option value="shopify">🛍️ {l s='Shopify' mod='aismarttalk'}</option>
                                <option value="joomla">📦 {l s='Joomla' mod='aismarttalk'}</option>
                                <option value="webflow">🎨 {l s='Webflow' mod='aismarttalk'}</option>
                                <option value="docusaurus">📚 {l s='Docusaurus' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="aismarttalk-filter-item">
                            <label>{l s='Integration' mod='aismarttalk'}</label>
                            <select id="aismarttalk-filter-integration" class="aismarttalk-filter-select">
                                <option value="">{l s='All integrations' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="aismarttalk-filter-item">
                            <label>{l s='Skill type' mod='aismarttalk'}</label>
                            <select id="aismarttalk-filter-trigger" class="aismarttalk-filter-select">
                                <option value="">{l s='All types' mod='aismarttalk'}</option>
                                <option value="CONVERSATION_TOOL">🤖 {l s='Conversation tool' mod='aismarttalk'}</option>
                                <option value="WEBHOOK">🔗 {l s='Webhook' mod='aismarttalk'}</option>
                                <option value="SMART_FORM_WORKFLOW">📝 {l s='SmartForm' mod='aismarttalk'}</option>
                                <option value="NAVIGATION_EVENT">🧭 {l s='Navigation' mod='aismarttalk'}</option>
                                <option value="CHAT_SERVICE">💬 {l s='Chat' mod='aismarttalk'}</option>
                                <option value="SCHEDULE_WORKFLOW">⏰ {l s='Scheduled' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="aismarttalk-filter-item">
                            <label>{l s='Status' mod='aismarttalk'}</label>
                            <select id="aismarttalk-filter-status" class="aismarttalk-filter-select">
                                <option value="">{l s='All' mod='aismarttalk'}</option>
                                <option value="installed">✅ {l s='Installed' mod='aismarttalk'}</option>
                                <option value="not-installed">➕ {l s='Not installed' mod='aismarttalk'}</option>
                                <option value="has-update">🔄 {l s='Update available' mod='aismarttalk'}</option>
                            </select>
                        </div>
                        <div class="aismarttalk-filter-item">
                            <label>{l s='Sort by' mod='aismarttalk'}</label>
                            <select id="aismarttalk-filter-sort" class="aismarttalk-filter-select">
                                <option value="downloads">🔥 {l s='Popular' mod='aismarttalk'}</option>
                                <option value="createdAt">✨ {l s='Recent' mod='aismarttalk'}</option>
                                <option value="name">🔤 A-Z</option>
                            </select>
                        </div>
                    </div>
                </div>

                {* Results Count *}
                <div id="aismarttalk-templates-count" class="aismarttalk-marketplace-count" style="display: none;">
                    <span id="aismarttalk-templates-total">0</span> {l s='skills available' mod='aismarttalk'}
                </div>

                <div id="aismarttalk-templates-container" class="aismarttalk-marketplace-container">
                    <div class="aismarttalk-templates-loading aismarttalk-marketplace-loading">
                        <div class="aismarttalk-loading-spinner"></div>
                        <p>{l s='Discovering skills...' mod='aismarttalk'}</p>
                    </div>
                    <div class="aismarttalk-templates-grid aismarttalk-marketplace-grid" style="display: none;"></div>
                    <div class="aismarttalk-templates-empty aismarttalk-marketplace-empty" style="display: none;">
                        <div class="aismarttalk-empty-state">
                            <span class="aismarttalk-empty-icon">🔎</span>
                            <h4>{l s='No skills match your criteria' mod='aismarttalk'}</h4>
                            <p>{l s='Try different filters.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                    <div class="aismarttalk-templates-error aismarttalk-marketplace-error" style="display: none;">
                        <i class="icon icon-warning"></i>
                        <p></p>
                    </div>
                </div>

                {* Pagination *}
                <div id="aismarttalk-templates-pagination" class="aismarttalk-marketplace-pagination" style="display: none;">
                    <button type="button" id="aismarttalk-templates-prev" class="aismarttalk-pagination-btn" disabled>
                        ◀
                    </button>
                    <span id="aismarttalk-templates-page-info">1 / 1</span>
                    <button type="button" id="aismarttalk-templates-next" class="aismarttalk-pagination-btn" disabled>
                        ▶
                    </button>
                </div>
            </div>

            {* Channels Section *}
            <div class="aismarttalk-skills-section aismarttalk-channels-section">
                <div class="aismarttalk-skills-section-header">
                    <div class="aismarttalk-skills-section-title">
                        <span class="aismarttalk-skills-section-icon">💬</span>
                        <div>
                            <h3>{l s='Channels' mod='aismarttalk'}</h3>
                            <p>{l s='Communication platforms where your assistant can interact with users.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                </div>

                <div id="aismarttalk-channels-container" class="aismarttalk-integrations-container">
                    <div class="aismarttalk-integrations-loading">
                        <div class="aismarttalk-loading-spinner"></div>
                        <p>{l s='Loading channels...' mod='aismarttalk'}</p>
                    </div>
                    <div class="aismarttalk-channels-grid" style="display: none;"></div>
                    <div class="aismarttalk-integrations-empty" style="display: none;">
                        <div class="aismarttalk-empty-state">
                            <span class="aismarttalk-empty-icon">💬</span>
                            <h4>{l s='No channels available' mod='aismarttalk'}</h4>
                            <p>{l s='Channels will appear here once configured.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                </div>
            </div>

            {* Integrations Section *}
            <div class="aismarttalk-skills-section aismarttalk-integrations-section">
                <div class="aismarttalk-skills-section-header">
                    <div class="aismarttalk-skills-section-title">
                        <span class="aismarttalk-skills-section-icon">🔗</span>
                        <div>
                            <h3>{l s='Integrations' mod='aismarttalk'}</h3>
                            <p>{l s='Connect your assistant to your tools and services.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                </div>

                <div id="aismarttalk-integrations-container" class="aismarttalk-integrations-container">
                    <div class="aismarttalk-integrations-loading">
                        <div class="aismarttalk-loading-spinner"></div>
                        <p>{l s='Loading integrations...' mod='aismarttalk'}</p>
                    </div>
                    <div class="aismarttalk-integrations-grid" style="display: none;"></div>
                    <div class="aismarttalk-integrations-empty" style="display: none;">
                        <div class="aismarttalk-empty-state">
                            <span class="aismarttalk-empty-icon">🔌</span>
                            <h4>{l s='No integrations available' mod='aismarttalk'}</h4>
                            <p>{l s='Integrations will appear here once configured.' mod='aismarttalk'}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* ===== SECTION 6: AI SMARTTALK BACKOFFICE ===== *}
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
<!-- AI Skills Loading Script -->
<script>
(function() {
    // ============================
    // AI Skills API v1
    // ============================
    var chatModelId = '{$chatModelId|escape:'javascript':'UTF-8'}';
    var accessToken = '{$accessToken|escape:'javascript':'UTF-8'}';
    var apiBaseUrl = '{$apiUrl|escape:'javascript':'UTF-8'}';
    var currentLang = '{$currentLang|escape:'javascript':'UTF-8'}';

    // DOM Elements - Installed SmartFlows (My Skills)
    var smartflowsContainer = document.getElementById('aismarttalk-smartflows-container');
    var smartflowsLoading = smartflowsContainer ? smartflowsContainer.querySelector('.aismarttalk-smartflows-loading') : null;
    var smartflowsGrid = smartflowsContainer ? smartflowsContainer.querySelector('.aismarttalk-smartflows-grid') : null;
    var smartflowsEmpty = smartflowsContainer ? smartflowsContainer.querySelector('.aismarttalk-smartflows-empty') : null;
    var smartflowsError = smartflowsContainer ? smartflowsContainer.querySelector('.aismarttalk-smartflows-error') : null;

    // DOM Elements - Templates Store (Marketplace)
    var templatesContainer = document.getElementById('aismarttalk-templates-container');
    var templatesLoading = templatesContainer ? templatesContainer.querySelector('.aismarttalk-templates-loading') : null;
    var templatesGrid = templatesContainer ? templatesContainer.querySelector('.aismarttalk-templates-grid') : null;
    var templatesEmpty = templatesContainer ? templatesContainer.querySelector('.aismarttalk-templates-empty') : null;
    var templatesError = templatesContainer ? templatesContainer.querySelector('.aismarttalk-templates-error') : null;

    // DOM Elements - Search & Filters
    var searchInput = document.getElementById('aismarttalk-templates-search-input');
    var filterPlatform = document.getElementById('aismarttalk-filter-platform');
    var filterIntegration = document.getElementById('aismarttalk-filter-integration');
    var filterTrigger = document.getElementById('aismarttalk-filter-trigger');
    var filterStatus = document.getElementById('aismarttalk-filter-status');
    var filterSort = document.getElementById('aismarttalk-filter-sort');
    var templatesCountEl = document.getElementById('aismarttalk-templates-count');
    var templatesTotalEl = document.getElementById('aismarttalk-templates-total');

    // DOM Elements - Pagination
    var paginationEl = document.getElementById('aismarttalk-templates-pagination');
    var prevBtn = document.getElementById('aismarttalk-templates-prev');
    var nextBtn = document.getElementById('aismarttalk-templates-next');
    var pageInfoEl = document.getElementById('aismarttalk-templates-page-info');

    // DOM Elements - Updates alert
    var updatesAlert = document.getElementById('aismarttalk-updates-alert');
    var updatesCount = document.getElementById('aismarttalk-updates-count');

    // Store installed templates data for reference
    var installedTemplatesData = {};

    // Current filters state
    var currentFilters = {
        search: '',
        platform: 'prestashop',
        integration: '',
        triggerType: '',
        installed: null,
        hasUpdate: null,
        sortBy: 'downloads',
        sortOrder: 'desc',
        page: 1,
        limit: 20
    };

    // Pagination state
    var paginationState = {
        page: 1,
        totalPages: 1,
        total: 0
    };

    // Debounce helper
    var searchTimeout = null;
    function debounce(func, wait) {
        return function() {
            var args = arguments;
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                func.apply(null, args);
            }, wait);
        };
    }

    // Build configuration URL for a workflow
    function getConfigureUrl(workflowId) {
        return apiBaseUrl + '/' + currentLang + '/admin/chatModel/' + chatModelId + '/smartflows/' + workflowId;
    }

    // API request helper
    function apiRequest(method, endpoint, body) {
        var options = {
            method: method,
            headers: {
                'Authorization': 'Bearer ' + accessToken,
                'Content-Type': 'application/json',
                'x-chat-model-id': chatModelId
            }
        };
        if (body) {
            options.body = JSON.stringify(body);
        }
        console.log('[AI SmartTalk] API Request:', method, apiBaseUrl + endpoint);
        return fetch(apiBaseUrl + endpoint, options).then(function(response) {
            if (!response.ok) {
                console.error('[AI SmartTalk] API Error:', response.status, response.statusText);
                return response.json().then(function(err) {
                    return Promise.reject(err);
                }).catch(function() {
                    return Promise.reject({ error: 'HTTP ' + response.status });
                });
            }
            return response.json();
        });
    }

    // Build query string from filters
    function buildQueryString() {
        var params = [];
        // Only add platform filter if a specific platform is selected
        if (currentFilters.platform) {
            params.push('platform=' + encodeURIComponent(currentFilters.platform));
        }
        params.push('lang=' + encodeURIComponent(currentLang));
        params.push('limit=' + currentFilters.limit);
        params.push('page=' + currentFilters.page);

        if (currentFilters.search) {
            params.push('search=' + encodeURIComponent(currentFilters.search));
        }
        if (currentFilters.integration) {
            params.push('integrations=' + encodeURIComponent(currentFilters.integration));
        }
        if (currentFilters.triggerType) {
            params.push('triggerTypes=' + encodeURIComponent(currentFilters.triggerType));
        }
        if (currentFilters.installed !== null) {
            params.push('installed=' + currentFilters.installed);
        }
        if (currentFilters.hasUpdate !== null) {
            params.push('hasUpdate=' + currentFilters.hasUpdate);
        }
        if (currentFilters.sortBy) {
            params.push('sortBy=' + currentFilters.sortBy);
            params.push('sortOrder=' + currentFilters.sortOrder);
        }

        return params.join('&');
    }

    // Skill type helpers
    function getSkillTypeBadge(triggerType) {
        switch (triggerType) {
            case 'CHAT_SERVICE':
                return { emoji: '🔧', label: '{l s='AI Tool' mod='aismarttalk' js=1}', class: 'tool' };
            case 'NAVIGATION_EVENT':
                return { emoji: '👋', label: '{l s='Automatic' mod='aismarttalk' js=1}', class: 'auto' };
            case 'WEBHOOK':
                return { emoji: '🔗', label: '{l s='Connection' mod='aismarttalk' js=1}', class: 'webhook' };
            default:
                return { emoji: '⚡', label: '{l s='Skill' mod='aismarttalk' js=1}', class: 'default' };
        }
    }

    function getSkillTriggerText(template) {
        if (template.triggerDescription) {
            return template.triggerDescription;
        }

        var triggerType = template.triggerType || 'CHAT_SERVICE';
        switch (triggerType) {
            case 'CHAT_SERVICE':
                return '{l s='When a visitor asks your assistant' mod='aismarttalk' js=1}';
            case 'NAVIGATION_EVENT':
                return '{l s='When a visitor browses your site' mod='aismarttalk' js=1}';
            case 'WEBHOOK':
                return '{l s='When an event is triggered' mod='aismarttalk' js=1}';
            default:
                return '{l s='Based on context' mod='aismarttalk' js=1}';
        }
    }

    function getSkillActionText(template) {
        return template.actionDescription || template.description || '';
    }

    // Load filters from API
    function loadFilters() {
        apiRequest('GET', '/api/v1/smartflow-templates/filters?lang=' + currentLang)
        .then(function(data) {
            // Populate integrations/platforms
            if (filterIntegration && data.integrations) {
                data.integrations.forEach(function(item) {
                    var option = document.createElement('option');
                    option.value = item.type;
                    var label = (item.emoji || '') + ' ' + item.label;
                    if (item.count !== undefined) {
                        label += ' (' + item.count + ')';
                    }
                    if (item.installed) {
                        label += ' ✓';
                    }
                    option.textContent = label;
                    filterIntegration.appendChild(option);
                });
            }
        })
        .catch(function(error) {
            console.error('Error loading filters:', error);
        });
    }

    // Render a single integration card
    function renderIntegrationCard(integration, fallbackIcon, isHidden) {
        var isConnected = integration.connected || integration.status === 'connected';
        var primaryColor = integration.colors ? integration.colors.primary : '#667eea';
        var name = integration.name || integration.label || integration.type;
        var icon = fallbackIcon || '🔌';
        var hiddenClass = isHidden ? ' aismarttalk-integration-hidden' : '';

        var html = '<div class="aismarttalk-integration-card' + (isConnected ? ' connected' : '') + hiddenClass + '">';
        html += '<div class="aismarttalk-integration-info">';

        // Logo or fallback icon
        if (integration.logoUrl) {
            html += '<div class="aismarttalk-integration-logo" style="background-color: ' + primaryColor + '20;">';
            html += '<img src="' + integration.logoUrl + '" alt="' + name + '" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';" />';
            html += '<span class="aismarttalk-integration-logo-fallback" style="display:none;">' + icon + '</span>';
            html += '</div>';
        } else {
            html += '<div class="aismarttalk-integration-logo" style="background-color: ' + primaryColor + '20;">';
            html += '<span class="aismarttalk-integration-logo-fallback">' + icon + '</span>';
            html += '</div>';
        }

        html += '<div class="aismarttalk-integration-details">';
        html += '<h4>' + name + '</h4>';
        html += '<span class="aismarttalk-integration-category">' + (integration.category || '') + '</span>';
        html += '</div>';
        html += '</div>';

        html += '<div class="aismarttalk-integration-status">';
        if (isConnected) {
            html += '<span class="aismarttalk-integration-badge connected" style="background-color: ' + primaryColor + '20; color: ' + primaryColor + ';">✓ {l s='Connected' mod='aismarttalk' js=1}</span>';
        } else {
            html += '<span class="aismarttalk-integration-badge not-connected">⚪ {l s='Not configured' mod='aismarttalk' js=1}</span>';
        }
        if (integration.configUrl) {
            var linkText = isConnected ? '{l s='Manage' mod='aismarttalk' js=1}' : '{l s='Configure' mod='aismarttalk' js=1}';
            html += '<a href="' + integration.configUrl + '" target="_blank" class="aismarttalk-integration-link" style="color: ' + primaryColor + ';">' + linkText + ' →</a>';
        }
        html += '</div>';
        html += '</div>';

        return html;
    }

    // Render expand/collapse button
    function renderExpandButton(hiddenCount, type) {
        var showMoreText = '{l s='Show more' mod='aismarttalk' js=1}';
        var showLessText = '{l s='Show less' mod='aismarttalk' js=1}';
        return '<button type="button" class="aismarttalk-expand-btn" data-type="' + type + '" data-expanded="false" data-show-more="' + showMoreText + ' (' + hiddenCount + ')" data-show-less="' + showLessText + '">' +
            '<span class="aismarttalk-expand-text">' + showMoreText + ' (' + hiddenCount + ')</span>' +
            '<span class="aismarttalk-expand-icon">▼</span>' +
            '</button>';
    }

    // Toggle expand/collapse for channels or integrations
    function setupExpandToggle() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.aismarttalk-expand-btn');
            if (!btn) return;

            var type = btn.getAttribute('data-type');
            var isExpanded = btn.getAttribute('data-expanded') === 'true';
            var container = type === 'channels'
                ? document.getElementById('aismarttalk-channels-container')
                : document.getElementById('aismarttalk-integrations-container');

            if (!container) return;

            var hiddenCards = container.querySelectorAll('.aismarttalk-integration-hidden');
            var textSpan = btn.querySelector('.aismarttalk-expand-text');
            var iconSpan = btn.querySelector('.aismarttalk-expand-icon');

            if (isExpanded) {
                // Collapse
                hiddenCards.forEach(function(card) { card.style.display = 'none'; });
                btn.setAttribute('data-expanded', 'false');
                textSpan.textContent = btn.getAttribute('data-show-more');
                iconSpan.textContent = '▼';
            } else {
                // Expand
                hiddenCards.forEach(function(card) { card.style.display = 'flex'; });
                btn.setAttribute('data-expanded', 'true');
                textSpan.textContent = btn.getAttribute('data-show-less');
                iconSpan.textContent = '▲';
            }
        });
    }

    // Load integrations from API
    function loadIntegrations() {
        var channelsContainer = document.getElementById('aismarttalk-channels-container');
        var integrationsContainer = document.getElementById('aismarttalk-integrations-container');

        if (!channelsContainer && !integrationsContainer) {
            console.log('[AI SmartTalk] Integrations/Channels containers not found');
            return;
        }

        // Get elements for channels
        var channelsLoading = channelsContainer ? channelsContainer.querySelector('.aismarttalk-integrations-loading') : null;
        var channelsGrid = channelsContainer ? channelsContainer.querySelector('.aismarttalk-channels-grid') : null;
        var channelsEmpty = channelsContainer ? channelsContainer.querySelector('.aismarttalk-integrations-empty') : null;

        // Get elements for integrations
        var integrationsLoading = integrationsContainer ? integrationsContainer.querySelector('.aismarttalk-integrations-loading') : null;
        var integrationsGrid = integrationsContainer ? integrationsContainer.querySelector('.aismarttalk-integrations-grid') : null;
        var integrationsEmpty = integrationsContainer ? integrationsContainer.querySelector('.aismarttalk-integrations-empty') : null;

        console.log('[AI SmartTalk] Loading integrations...');
        apiRequest('GET', '/api/v1/integrations?integrationType=PRESTASHOP')
        .then(function(data) {
            console.log('[AI SmartTalk] Integrations response:', data);

            // Hide loading spinners
            if (channelsLoading) channelsLoading.style.display = 'none';
            if (integrationsLoading) integrationsLoading.style.display = 'none';

            // API returns pre-sorted and grouped data
            var channels = data.channels || [];
            var integrations = data.integrations || [];
            var visibleCount = data.config ? data.config.visibleCount : 4;

            // Render channels
            if (channels.length > 0 && channelsGrid) {
                var channelsHtml = '';
                channels.forEach(function(channel, index) {
                    var isHidden = index >= visibleCount;
                    channelsHtml += renderIntegrationCard(channel, '💬', isHidden);
                });
                channelsGrid.innerHTML = channelsHtml;
                channelsGrid.style.display = 'grid';

                // Add expand button if there are hidden items
                if (channels.length > visibleCount) {
                    var hiddenCount = channels.length - visibleCount;
                    channelsGrid.insertAdjacentHTML('afterend', renderExpandButton(hiddenCount, 'channels'));
                }
            } else if (channelsEmpty) {
                channelsEmpty.style.display = 'flex';
            }

            // Render integrations
            if (integrations.length > 0 && integrationsGrid) {
                var integrationsHtml = '';
                integrations.forEach(function(integration, index) {
                    var isHidden = index >= visibleCount;
                    integrationsHtml += renderIntegrationCard(integration, '🔌', isHidden);
                });
                integrationsGrid.innerHTML = integrationsHtml;
                integrationsGrid.style.display = 'grid';

                // Add expand button if there are hidden items
                if (integrations.length > visibleCount) {
                    var hiddenCount = integrations.length - visibleCount;
                    integrationsGrid.insertAdjacentHTML('afterend', renderExpandButton(hiddenCount, 'integrations'));
                }
            } else if (integrationsEmpty) {
                integrationsEmpty.style.display = 'flex';
            }
        })
        .catch(function(error) {
            console.error('Error loading integrations:', error);
            if (channelsLoading) channelsLoading.style.display = 'none';
            if (integrationsLoading) integrationsLoading.style.display = 'none';
            if (channelsEmpty) channelsEmpty.style.display = 'flex';
            if (integrationsEmpty) integrationsEmpty.style.display = 'flex';
        });
    }

    // Setup expand toggle listener
    setupExpandToggle();

    // Render action buttons for a skill based on installation status
    function renderSkillAction(template) {
        var installation = template.installation || {};
        var hasUpdate = installation.hasUpdate || false;

        // Check if template is installed via installedTemplatesData
        var installedData = installedTemplatesData[template.id];
        if (installedData) {
            installation.isInstalled = true;
            installation.workflowId = installedData.workflowId;
            hasUpdate = installedData.hasUpdate || false;
        }

        if (installation.isInstalled && installation.workflowId) {
            var actions = '<div class="aismarttalk-skill-actions">';
            if (hasUpdate) {
                actions += '<button type="button" class="aismarttalk-skill-btn aismarttalk-skill-btn-update" data-template-id="' + template.id + '" title="{l s='Update available' mod='aismarttalk' js=1}">🔄</button>';
            }
            actions += '<a href="' + getConfigureUrl(installation.workflowId) + '" target="_blank" class="aismarttalk-skill-btn aismarttalk-skill-btn-configure" title="{l s='Configure' mod='aismarttalk' js=1}">⚙️</a>';
            actions += '<button type="button" class="aismarttalk-skill-btn aismarttalk-skill-btn-remove" data-template-id="' + template.id + '" title="{l s='Remove' mod='aismarttalk' js=1}">🗑️</button>';
            actions += '</div>';
            return actions;
        }

        // Check for missing integrations
        if (template.missingIntegrations && template.missingIntegrations.length > 0) {
            return '<span class="aismarttalk-skill-missing" title="{l s='Requires:' mod='aismarttalk' js=1} ' + template.missingIntegrations.join(', ') + '">🔗 {l s='Setup needed' mod='aismarttalk' js=1}</span>';
        }

        return '<button type="button" class="aismarttalk-skill-btn-install" data-template-id="' + template.id + '">➕ {l s='Add skill' mod='aismarttalk' js=1}</button>';
    }

    // Render installed skills (My Skills section)
    function renderInstalledSmartflows(data) {
        if (smartflowsLoading) smartflowsLoading.style.display = 'none';

        var installed = data.installed || [];
        var stats = data.stats || {};

        // Update stats display
        var skillsStatsEl = document.getElementById('aismarttalk-skills-stats');
        if (skillsStatsEl && installed.length > 0) {
            var statActive = document.getElementById('stat-active');
            var statInactive = document.getElementById('stat-inactive');
            if (statActive) statActive.textContent = stats.activeCount || 0;
            if (statInactive) statInactive.textContent = stats.inactiveCount || 0;
            skillsStatsEl.style.display = 'flex';
        }

        // Show updates alert if available
        if (updatesAlert && updatesCount && stats.updatesAvailable > 0) {
            updatesCount.textContent = stats.updatesAvailable;
            updatesAlert.style.display = 'flex';
        }

        if (!installed || installed.length === 0) {
            if (smartflowsEmpty) smartflowsEmpty.style.display = 'flex';
            return;
        }

        // Store for reference - map by workflowId for quick lookup (works for both template-based and manual)
        installed.forEach(function(item) {
            installedTemplatesData[item.workflowId] = item;
            // Also map by templateId for backwards compatibility
            if (item.templateId) {
                installedTemplatesData[item.templateId] = item;
            }
        });

        var html = '';
        installed.forEach(function(skill) {
            var isManual = skill.isManual || !skill.templateId;
            var hasUpdate = !isManual && (skill.hasUpdate || (skill.installedVersion !== skill.currentVersion));
            var statusClass = skill.isActive ? 'active' : 'paused';
            var typeBadge = getSkillTypeBadge(skill.triggerType);
            var skillId = skill.workflowId;

            html += '<div class="aismarttalk-skill-card' + (hasUpdate ? ' has-update' : '') + (isManual ? ' is-manual' : '') + ' ' + statusClass + '" data-workflow-id="' + skillId + '" data-template-id="' + (skill.templateId || '') + '" data-is-manual="' + (isManual ? 'true' : 'false') + '">';

            // Type badge - show "Custom" for manual workflows
            if (isManual) {
                html += '<div class="aismarttalk-skill-type-badge aismarttalk-skill-type-custom">🛠️ {l s='Custom' mod='aismarttalk' js=1}</div>';
            } else {
                html += '<div class="aismarttalk-skill-type-badge ' + typeBadge.class + '">' + typeBadge.emoji + ' ' + typeBadge.label + '</div>';
            }

            // Skill header with icon and name
            html += '<div class="aismarttalk-skill-header-row">';
            html += '<span class="aismarttalk-skill-icon">' + (skill.icon || '⚡') + '</span>';
            html += '<h4 class="aismarttalk-skill-name">' + (skill.workflowName || skill.templateName || 'Skill') + '</h4>';
            if (hasUpdate) {
                html += '<span class="aismarttalk-skill-update-badge" title="v' + skill.currentVersion + ' {l s='available' mod='aismarttalk' js=1}">🔄</span>';
            }
            html += '</div>';

            // Skill meta
            html += '<div class="aismarttalk-skill-meta">';
            if (isManual) {
                html += '<span class="aismarttalk-skill-version">{l s='Custom' mod='aismarttalk' js=1}</span>';
            } else {
                html += '<span class="aismarttalk-skill-version">v' + (skill.installedVersion || '1.0.0') + '</span>';
            }
            html += '<span class="aismarttalk-skill-status ' + statusClass + '">';
            html += skill.isActive ? '🟢 {l s='Active' mod='aismarttalk' js=1}' : '⏸️ {l s='Paused' mod='aismarttalk' js=1}';
            html += '</span>';
            html += '</div>';

            // Description if available
            var description = skill.templateDescription || skill.description;
            if (description) {
                html += '<p class="aismarttalk-skill-desc">' + description + '</p>';
            }

            // Skill actions
            html += '<div class="aismarttalk-skill-actions">';
            // Update button only for template-based workflows with updates
            if (hasUpdate && skill.templateId) {
                html += '<button type="button" class="aismarttalk-skill-btn aismarttalk-skill-btn-update" data-template-id="' + skill.templateId + '" title="{l s='Update to' mod='aismarttalk' js=1} v' + skill.currentVersion + '">🔄 {l s='Update' mod='aismarttalk' js=1}</button>';
            }
            html += '<a href="' + getConfigureUrl(skill.workflowId) + '" target="_blank" class="aismarttalk-skill-btn aismarttalk-skill-btn-configure">⚙️ {l s='Edit' mod='aismarttalk' js=1}</a>';
            // Remove button only for template-based workflows (not manual)
            if (!isManual && skill.templateId) {
                html += '<button type="button" class="aismarttalk-skill-btn aismarttalk-skill-btn-remove" data-template-id="' + skill.templateId + '" title="{l s='Remove' mod='aismarttalk' js=1}">🗑️</button>';
            }
            html += '</div>';

            html += '</div>';
        });

        if (smartflowsGrid) {
            smartflowsGrid.innerHTML = html;
            smartflowsGrid.style.display = 'grid';

            // Add click handlers for update buttons
            var updateButtons = smartflowsGrid.querySelectorAll('.aismarttalk-skill-btn-update');
            updateButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var templateId = btn.getAttribute('data-template-id');
                    updateTemplate(templateId, btn);
                });
            });

            // Add click handlers for remove buttons
            var removeButtons = smartflowsGrid.querySelectorAll('.aismarttalk-skill-btn-remove');
            removeButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var templateId = btn.getAttribute('data-template-id');
                    uninstallTemplate(templateId, btn);
                });
            });
        }
    }

    // Render skills marketplace
    function renderTemplates(data) {
        if (templatesLoading) templatesLoading.style.display = 'none';

        var templates = data.templates || [];
        var pagination = data.pagination || {};

        // Update pagination state
        paginationState.page = pagination.page || 1;
        paginationState.totalPages = pagination.totalPages || 1;
        paginationState.total = pagination.total || 0;

        // Update total count
        if (templatesCountEl && templatesTotalEl) {
            templatesTotalEl.textContent = paginationState.total;
            templatesCountEl.style.display = 'block';
        }

        // Update pagination UI
        updatePaginationUI();

        if (!Array.isArray(templates) || templates.length === 0) {
            if (templatesGrid) templatesGrid.style.display = 'none';
            if (templatesEmpty) templatesEmpty.style.display = 'flex';
            return;
        }

        if (templatesEmpty) templatesEmpty.style.display = 'none';

        var html = '';
        templates.forEach(function(template) {
            var installation = template.installation || {};

            // Check if we have local installed data
            var localInstalled = installedTemplatesData[template.id];
            if (localInstalled) {
                installation.isInstalled = true;
                installation.workflowId = localInstalled.workflowId;
                installation.hasUpdate = localInstalled.hasUpdate;
            }

            var isInstalled = installation.isInstalled || false;
            var hasUpdate = installation.hasUpdate || false;
            var typeBadge = getSkillTypeBadge(template.triggerType);

            html += '<div class="aismarttalk-marketplace-card' + (isInstalled ? ' installed' : '') + (hasUpdate ? ' has-update' : '') + '" data-template-id="' + template.id + '">';

            // Card header with type badge and status badges
            html += '<div class="aismarttalk-marketplace-card-header">';
            html += '<span class="aismarttalk-skill-type-badge ' + typeBadge.class + '">' + typeBadge.emoji + ' ' + typeBadge.label + '</span>';
            html += '<div class="aismarttalk-marketplace-card-badges">';
            if (isInstalled) {
                html += '<span class="aismarttalk-badge-installed">✅</span>';
            }
            if (hasUpdate) {
                html += '<span class="aismarttalk-badge-update">🔄</span>';
            }
            html += '</div>';
            html += '</div>';

            // Card body with icon and title
            html += '<div class="aismarttalk-marketplace-card-body">';
            html += '<div class="aismarttalk-marketplace-card-title-row">';
            html += '<span class="aismarttalk-marketplace-card-icon">' + (template.icon || '⚡') + '</span>';
            html += '<h4 class="aismarttalk-marketplace-card-title">' + (template.name || 'Skill') + '</h4>';
            html += '</div>';

            // Trigger (When) and Action fields
            html += '<div class="aismarttalk-marketplace-card-details">';
            html += '<div class="aismarttalk-detail-row">';
            html += '<span class="aismarttalk-detail-label">{l s='When:' mod='aismarttalk' js=1}</span>';
            html += '<span class="aismarttalk-detail-value">' + getSkillTriggerText(template) + '</span>';
            html += '</div>';
            html += '<div class="aismarttalk-detail-row">';
            html += '<span class="aismarttalk-detail-label">{l s='Action:' mod='aismarttalk' js=1}</span>';
            html += '<span class="aismarttalk-detail-value">' + getSkillActionText(template) + '</span>';
            html += '</div>';
            html += '</div>';

            // Platform tags
            if (template.requiredIntegrations && template.requiredIntegrations.length > 0) {
                html += '<div class="aismarttalk-marketplace-card-platforms">';
                template.requiredIntegrations.forEach(function(integration) {
                    var isMissing = template.missingIntegrations && template.missingIntegrations.indexOf(integration) !== -1;
                    html += '<span class="aismarttalk-platform-tag' + (isMissing ? ' missing' : '') + '">🏷️ ' + integration + '</span>';
                });
                html += '</div>';
            }
            html += '</div>';

            // Card footer
            html += '<div class="aismarttalk-marketplace-card-footer">';
            html += '<div class="aismarttalk-marketplace-card-stats">';
            if (template.downloads !== undefined) {
                html += '<span class="aismarttalk-stat-item">📦 ' + template.downloads + ' {l s='installs' mod='aismarttalk' js=1}</span>';
            }
            if (template.version) {
                html += '<span class="aismarttalk-stat-item aismarttalk-version-tag">v' + template.version + '</span>';
            }
            html += '</div>';
            html += renderSkillAction(template);
            html += '</div>';

            html += '</div>';
        });

        if (templatesGrid) {
            templatesGrid.innerHTML = html;
            templatesGrid.style.display = 'grid';

            // Add click handlers for install buttons
            var installButtons = templatesGrid.querySelectorAll('.aismarttalk-skill-btn-install');
            installButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var templateId = btn.getAttribute('data-template-id');
                    installTemplate(templateId, btn);
                });
            });

            // Add click handlers for update buttons
            var updateButtons = templatesGrid.querySelectorAll('.aismarttalk-skill-btn-update');
            updateButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var templateId = btn.getAttribute('data-template-id');
                    updateTemplate(templateId, btn);
                });
            });

            // Add click handlers for remove buttons
            var removeButtons = templatesGrid.querySelectorAll('.aismarttalk-skill-btn-remove');
            removeButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var templateId = btn.getAttribute('data-template-id');
                    uninstallTemplate(templateId, btn);
                });
            });
        }
    }

    // Update pagination UI
    function updatePaginationUI() {
        if (!paginationEl) return;

        if (paginationState.totalPages <= 1) {
            paginationEl.style.display = 'none';
            return;
        }

        paginationEl.style.display = 'flex';
        pageInfoEl.textContent = paginationState.page + ' / ' + paginationState.totalPages;
        prevBtn.disabled = paginationState.page <= 1;
        nextBtn.disabled = paginationState.page >= paginationState.totalPages;
    }

    // Fetch all workflows (both manual and template-based)
    function fetchInstalledTemplates() {
        if (smartflowsLoading) smartflowsLoading.style.display = 'flex';
        if (smartflowsGrid) smartflowsGrid.style.display = 'none';
        if (smartflowsEmpty) smartflowsEmpty.style.display = 'none';

        // Don't filter by platform - show ALL installed workflows (both template-based and manual)
        console.log('[AI SmartTalk] Fetching all workflows with chatModelId:', chatModelId);
        apiRequest('GET', '/api/v1/smartflow-templates/installed?lang=' + currentLang)
        .then(function(data) {
            console.log('[AI SmartTalk] Installed workflows response:', data);
            renderInstalledSmartflows(data);
        })
        .catch(function(error) {
            console.error('Error fetching installed workflows:', error);
            if (smartflowsLoading) smartflowsLoading.style.display = 'none';
            if (smartflowsEmpty) smartflowsEmpty.style.display = 'flex';
        });
    }

    // Fetch templates with current filters
    function fetchTemplates() {
        if (templatesLoading) templatesLoading.style.display = 'flex';
        if (templatesGrid) templatesGrid.style.display = 'none';
        if (templatesEmpty) templatesEmpty.style.display = 'none';

        var queryString = buildQueryString();
        apiRequest('GET', '/api/v1/smartflow-templates?' + queryString)
        .then(function(data) {
            renderTemplates(data);
        })
        .catch(function(error) {
            console.error('Error fetching templates:', error);
            if (templatesLoading) templatesLoading.style.display = 'none';
            if (templatesEmpty) templatesEmpty.style.display = 'flex';
        });
    }

    // Handle filter changes
    function onFilterChange() {
        currentFilters.page = 1; // Reset to first page
        fetchTemplates();
    }

    // Setup event listeners for search and filters
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(e) {
            currentFilters.search = e.target.value.trim();
            onFilterChange();
        }, 300));
    }

    if (filterPlatform) {
        filterPlatform.addEventListener('change', function(e) {
            currentFilters.platform = e.target.value;
            onFilterChange();
        });
    }

    if (filterIntegration) {
        filterIntegration.addEventListener('change', function(e) {
            currentFilters.integration = e.target.value;
            onFilterChange();
        });
    }

    if (filterTrigger) {
        filterTrigger.addEventListener('change', function(e) {
            currentFilters.triggerType = e.target.value;
            onFilterChange();
        });
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', function(e) {
            var value = e.target.value;
            currentFilters.installed = null;
            currentFilters.hasUpdate = null;

            if (value === 'installed') {
                currentFilters.installed = true;
            } else if (value === 'not-installed') {
                currentFilters.installed = false;
            } else if (value === 'has-update') {
                currentFilters.hasUpdate = true;
            }
            onFilterChange();
        });
    }

    if (filterSort) {
        filterSort.addEventListener('change', function(e) {
            currentFilters.sortBy = e.target.value;
            currentFilters.sortOrder = (e.target.value === 'name') ? 'asc' : 'desc';
            onFilterChange();
        });
    }

    // Pagination handlers
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentFilters.page > 1) {
                currentFilters.page--;
                fetchTemplates();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (currentFilters.page < paginationState.totalPages) {
                currentFilters.page++;
                fetchTemplates();
            }
        });
    }

    // Install template function
    function installTemplate(templateId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<div class="aismarttalk-loading-spinner" style="width:18px;height:18px;border-width:2px;"></div>';

        apiRequest('POST', '/api/v1/smartflow-templates/' + templateId + '/install', {
            configuration: {},
            lang: currentLang
        })
        .then(function(data) {
            if (data.success || data.workflowId) {
                // Reload the page to refresh both lists, staying on AI Skills section
                window.location.hash = '#smartflow';
                location.reload();
            } else {
                btn.disabled = false;
                btn.innerHTML = '➕ {l s='Add skill' mod='aismarttalk' js=1}';
                alert(data.message || data.error || '{l s='Failed to install skill.' mod='aismarttalk' js=1}');
            }
        })
        .catch(function(error) {
            console.error('Error installing template:', error);
            btn.disabled = false;
            btn.innerHTML = '➕ {l s='Add skill' mod='aismarttalk' js=1}';
            alert('{l s='Failed to install skill. Please try again.' mod='aismarttalk' js=1}');
        });
    }

    // Update template function (reinstall with latest version)
    function updateTemplate(templateId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<div class="aismarttalk-loading-spinner" style="width:18px;height:18px;border-width:2px;"></div>';

        apiRequest('POST', '/api/v1/smartflow-templates/' + templateId + '/install', {
            configuration: {},
            lang: currentLang
        })
        .then(function(data) {
            if (data.success || data.workflowId) {
                window.location.hash = '#smartflow';
                location.reload();
            } else {
                btn.disabled = false;
                btn.innerHTML = '🔄';
                alert(data.message || data.error || '{l s='Failed to update skill.' mod='aismarttalk' js=1}');
            }
        })
        .catch(function(error) {
            console.error('Error updating template:', error);
            btn.disabled = false;
            btn.innerHTML = '🔄';
            alert('{l s='Failed to update skill. Please try again.' mod='aismarttalk' js=1}');
        });
    }

    // Uninstall template function
    function uninstallTemplate(templateId, btn) {
        if (!confirm('{l s='Are you sure you want to remove this skill? This action cannot be undone.' mod='aismarttalk' js=1}')) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="aismarttalk-loading-spinner" style="width:18px;height:18px;border-width:2px;"></div>';

        apiRequest('DELETE', '/api/v1/smartflow-templates/' + templateId)
        .then(function(data) {
            if (data.success) {
                window.location.hash = '#smartflow';
                location.reload();
            } else {
                btn.disabled = false;
                btn.innerHTML = '🗑️';
                alert(data.message || data.error || '{l s='Failed to remove skill.' mod='aismarttalk' js=1}');
            }
        })
        .catch(function(error) {
            console.error('Error uninstalling template:', error);
            btn.disabled = false;
            btn.innerHTML = '🗑️';
            alert('{l s='Failed to remove skill. Please try again.' mod='aismarttalk' js=1}');
        });
    }

    // Load SmartFlow data
    if (chatModelId && accessToken) {
        // Load filters first
        loadFilters();

        // Fetch installed templates and marketplace templates
        fetchInstalledTemplates();
        fetchTemplates();

        // Fetch integrations
        loadIntegrations();
    }

    // Handle hash navigation - scroll to AI Skills section if hash is #smartflow
    if (window.location.hash === '#smartflow') {
        setTimeout(function() {
            var smartflowSection = document.getElementById('smartflow');
            if (smartflowSection) {
                smartflowSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
})();
</script>

<!-- AI SmartTalk Chatbot Embedding Code for Backoffice -->
<script>
window.chatbotSettings = JSON.parse(atob("{$chatbotSettingsEncoded|escape:'html':'UTF-8'}"));
</script>
<script src="{$cdnUrl|escape:'html':'UTF-8'}/universal-chatbot.js" async></script>
{/if}
