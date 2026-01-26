#!/bin/bash
# Configure PrestaShop to work behind a reverse proxy (nginx SSL termination)

set -e

echo "=== Configuring PrestaShop for reverse proxy ==="

# 1. Create auto_prepend PHP file (runs BEFORE any PrestaShop code)
PREPEND_FILE="/var/www/html/config/proxy_prepend.php"
cat > "$PREPEND_FILE" << 'EOFPHP'
<?php
/**
 * Reverse proxy configuration - executed before any PrestaShop code
 * Handles SSL termination at nginx level
 */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $_SERVER['HTTPS'] = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'on' : 'off';
    if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $_SERVER['SERVER_PORT'] = 443;
        $_SERVER['REQUEST_SCHEME'] = 'https';
    }
}
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}
if (isset($_SERVER['HTTP_X_REAL_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}
EOFPHP
echo "Created $PREPEND_FILE"

# 2. Configure Apache to use auto_prepend_file
HTACCESS_FILE="/var/www/html/.htaccess"
if [ -f "$HTACCESS_FILE" ]; then
    # Remove any existing proxy config first
    sed -i '/# BEGIN PROXY CONFIG/,/# END PROXY CONFIG/d' "$HTACCESS_FILE"
    
    # Add proxy configuration at the very beginning of .htaccess
    TEMP_FILE=$(mktemp)
    cat > "$TEMP_FILE" << 'EOFHTACCESS'
# BEGIN PROXY CONFIG
# Trust reverse proxy headers (nginx SSL termination)
<IfModule mod_setenvif.c>
    SetEnvIf X-Forwarded-Proto "https" HTTPS=on
    SetEnvIf X-Forwarded-Proto "https" REQUEST_SCHEME=https
</IfModule>

# Auto prepend proxy configuration
<IfModule mod_php.c>
    php_value auto_prepend_file "/var/www/html/config/proxy_prepend.php"
</IfModule>
<IfModule mod_php7.c>
    php_value auto_prepend_file "/var/www/html/config/proxy_prepend.php"
</IfModule>
<IfModule mod_php8.c>
    php_value auto_prepend_file "/var/www/html/config/proxy_prepend.php"
</IfModule>
# END PROXY CONFIG

EOFHTACCESS
    cat "$HTACCESS_FILE" >> "$TEMP_FILE"
    mv "$TEMP_FILE" "$HTACCESS_FILE"
    chmod 644 "$HTACCESS_FILE"
    echo "Updated $HTACCESS_FILE with proxy configuration"
else
    echo "Warning: $HTACCESS_FILE not found"
fi

# 3. Also add to defines.inc.php as fallback (in case .htaccess doesn't work)
DEFINES_FILE="/var/www/html/config/defines.inc.php"
if [ -f "$DEFINES_FILE" ]; then
    if ! grep -q "proxy_prepend.php" "$DEFINES_FILE" 2>/dev/null; then
        # Add include at the very beginning after <?php
        sed -i '1a\/* Include proxy configuration */\nif (file_exists(_PS_ROOT_DIR_.\"/config/proxy_prepend.php\")) { require_once(_PS_ROOT_DIR_.\"/config/proxy_prepend.php\"); }' "$DEFINES_FILE"
        echo "Updated $DEFINES_FILE with proxy include"
    else
        echo "$DEFINES_FILE already configured"
    fi
else
    echo "Warning: $DEFINES_FILE not found"
fi

# 4. Create/update PHP ini configuration for FPM (if using PHP-FPM)
PHP_INI_DIR="/usr/local/etc/php/conf.d"
if [ -d "$PHP_INI_DIR" ]; then
    cat > "$PHP_INI_DIR/99-proxy.ini" << 'EOFINI'
; Reverse proxy auto prepend
auto_prepend_file = /var/www/html/config/proxy_prepend.php
EOFINI
    echo "Created PHP ini configuration"
fi

echo "=== Proxy configuration complete ==="
