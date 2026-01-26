#!/bin/bash
# Configure PrestaShop to work behind a reverse proxy

DEFINES_FILE="/var/www/html/config/defines.inc.php"

# Check if the proxy config is already added
if ! grep -q "X-Forwarded-Proto" "$DEFINES_FILE" 2>/dev/null; then
    echo "Adding reverse proxy configuration to PrestaShop..."

    # Add proxy configuration before the closing PHP tag or at the end
    cat >> "$DEFINES_FILE" << 'EOFPHP'

/* Reverse proxy configuration */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}
EOFPHP

    echo "Reverse proxy configuration added."
else
    echo "Reverse proxy configuration already exists."
fi
