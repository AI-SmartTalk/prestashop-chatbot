demo@prestashop.com / prestashop_demo

Activate debug mode manually in the file ```/var/www/html/config/defines.inc.php```

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