# Changelog

All notable changes to the AI SmartTalk PrestaShop module will be documented in this file.

## [3.0.0] - 2026-01-16

### Added
- **OAuth 2.0 + PKCE authentication**: Secure connection with AI SmartTalk without manually copying tokens
- **"Connect with AI SmartTalk" button**: One-click connection via OAuth flow
- **Unified configuration interface**: Modern, reorganized admin UI with better UX
- **Frontend/Backend URL separation**: Support for Docker and whitelabel deployments with different URLs
- **Collapsible advanced settings**: WhiteLabel configuration hidden by default

### Changed
- Replaced manual Chat Model ID/Token entry with OAuth connection
- Reorganized admin interface into logical sections:
  - Connection status
  - Chatbot settings (activation + position)
  - Data synchronization (products + customers)
  - AI SmartTalk Dashboard access
  - Advanced/WhiteLabel settings
- Modern UI with gradient styling and responsive grid layout
- All API calls now use centralized OAuthHandler for credentials

### Technical
- New `OAuthHandler` class for OAuth 2.0 + PKCE implementation
- New `controllers/front/oauthcallback.php` front controller for OAuth callback
- OAuth state stored in Configuration table (works across admin/front contexts)
- Backward compatible with legacy `CHAT_MODEL_ID` / `CHAT_MODEL_TOKEN` configuration

### Security
- PKCE (Proof Key for Code Exchange) for secure public client OAuth
- CSRF protection with state parameter
- Token expiration handling (10-minute OAuth state validity)

## [2.5.0] - Previous versions

- Product synchronization with AI SmartTalk
- Customer CRM sync
- Chatbot embedding via CDN
- WhiteLabel support

