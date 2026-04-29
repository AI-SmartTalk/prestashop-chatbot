#!/bin/bash
# Bootstrap the PrestaShop 1.7.5.1 (PHP 7.2) test environment after `make ps1751`.
# Idempotent — safe to re-run.
#
# - Removes /install
# - Fixes filesystem ownership (the pre-install script ran as root)
# - Installs the module's composer dependencies
# - Installs and enables the aismarttalk module against the live PS instance

set -e

CONTAINER="prestashop1751"
DB_CONTAINER="prestashop1751_db"

echo "=== PrestaShop 1.7.5.1 Test Environment Bootstrap ==="

# 1. Remove install dir
echo "[1/4] Removing install directory..."
docker exec "$CONTAINER" rm -rf /var/www/html/install 2>/dev/null || true

# 2. Reclaim ownership for Apache (www-data) — the seed script ran as root
echo "[2/4] Fixing filesystem ownership..."
docker exec "$CONTAINER" chown -R www-data:www-data \
    /var/www/html/var \
    /var/www/html/cache \
    /var/www/html/img \
    /var/www/html/translations \
    /var/www/html/modules \
    /var/www/html/themes \
    /var/www/html/config 2>/dev/null

# 3. Install module dependencies (no-dev: phpunit 9.6 in dev-deps is parsed by PHP 7.2
#    which can't handle trailing commas, leading to 500s from any module page)
echo "[3/4] Installing module composer dependencies (no-dev)..."
docker exec "$CONTAINER" bash -c '
    if [ ! -f /usr/local/bin/composer ]; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null 2>&1
    fi
    cd /var/www/html/modules/aismarttalk && rm -rf vendor && composer install --no-dev --quiet 2>&1
'

# 4. Install + enable the module via PS API (idempotent)
echo "[4/4] Installing the aismarttalk module..."
docker exec "$CONTAINER" bash -c 'rm -rf /var/www/html/var/cache/*; cd /var/www/html && php -r "
require_once \"config/config.inc.php\";
\$m = Module::getInstanceByName(\"aismarttalk\");
if (!\$m) { fwrite(STDERR, \"Module class not loadable\\n\"); exit(1); }
if (!\$m->id) {
    if (!\$m->install()) {
        fwrite(STDERR, \"Install failed: \" . implode(\" / \", \$m->getErrors()) . \"\\n\");
        exit(1);
    }
    echo \"Installed (id=\" . \$m->id . \")\\n\";
} else {
    echo \"Already installed (id=\" . \$m->id . \")\\n\";
}
"'

# Final cache clear + ownership fix — composer + module install ran as root
# and may have left non-www-data files in var/cache, which 500s every BO request
docker exec "$CONTAINER" sh -c '
    rm -rf /var/www/html/var/cache/* /var/www/html/cache/smarty/cache/* /var/www/html/cache/smarty/compile/* 2>/dev/null
    chown -R www-data:www-data /var/www/html/var /var/www/html/cache 2>/dev/null
'

ADMIN_PATH=$(docker exec "$CONTAINER" sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")

echo ""
echo "=== Ready ==="
echo "Front:  http://localhost:8093"
echo "Admin:  http://localhost:8093/$ADMIN_PATH/"
echo "Email:  demo@prestashop.com"
echo "Pass:   Admin_Presta1751!"
