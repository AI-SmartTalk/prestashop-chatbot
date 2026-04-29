demo@prestashop.com / prestashop_demo

Activate debug mode manually in the file ```/var/www/html/config/defines.inc.php```

## Supported PrestaShop versions

The module is validated end-to-end (Playwright) against:

| Version    | PHP   | Make targets                                  |
|------------|-------|------------------------------------------------|
| 9.0.1      | 8.x   | `make up` / `make e2e`        (port 80)        |
| 1.7.8.11   | 7.4   | `make ps17` / `make e2e-ps17` (port 8091)      |
| 1.7.5.1    | 7.2   | `make ps1751` / `make e2e-ps1751` (port 8093)  |

`ps_versions_compliancy.min` in the module is `1.7.0.0`.

### PS 1.7.5.1 / PHP 7.2 notes

The shipped PrestaShop image fetches CLDR data from a URL (`i18n.prestashop.com`) that no longer exists, which prevents the installer from completing. The compose file (`docker-compose.ps1751.yml`) seeds the cache from `scripts/ps1751-cldr-data/` and patches `icanboogie/cldr` so missing remote data degrades gracefully — no network access at install time.

Bring the env up:

```bash
make ps1751           # start prestashop1751 / prestashop1751_db
make init-test-ps1751 # composer install --no-dev + module install
make e2e-ps1751       # run the full e2e suite
```

`composer install --no-dev` is required: PHPUnit 9 is in `require-dev` and uses PHP 7.3+ syntax (trailing commas) that PHP 7.2 fails to parse, which would 500 every BO page through the bind-mounted `vendor/`.

## Local Development Configuration

When running in Docker, you need to configure different URLs for backend (server-to-server) and frontend (browser) communication:

| Setting | Description | Example Value |
|---------|-------------|---------------|
| AI SmartTalk API URL (Backend) | Used for server-to-server API calls from PrestaShop to AI SmartTalk | `http://ai-toolkit-node:3000` |
| AI SmartTalk URL (Frontend) | Used for browser redirects and OAuth flow | `http://localhost:3001` |
| AI SmartTalk CDN URL | CDN for chatbot JavaScript and assets | `http://localhost:3001` |

**Why two different URLs?**
- The **Backend URL** is used by PHP code running in Docker, which can reach `ai-toolkit-node:3000` via Docker's internal network.
- The **Frontend URL** is used for browser redirects during OAuth, which needs a URL accessible from the user's browser (e.g., `localhost:3001`).

## OAuth Integration

The module now uses OAuth 2.0 with PKCE for secure authentication. Simply click "Connect with AI SmartTalk" in the module configuration to authenticate.

For local development, make sure to configure the Frontend URL (`AI_SMART_TALK_FRONT_URL`) to point to a URL accessible from your browser.