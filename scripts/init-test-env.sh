#!/bin/bash
# Init PrestaShop test environment
# Usage: make init-test  (or bash scripts/init-test-env.sh)
#
# Sets up:
# - Removes install dir
# - Renames admin to /admin-qa
# - Sets admin email to admin@test.local
# - Sets admin password to admin123
# - Installs & enables the aismarttalk module

set -e

CONTAINER="prestashop"
DB_CONTAINER="prestashop_db"
DB_USER="prestashop"
DB_PASS="prestashop"
DB_NAME="prestashop"

ADMIN_DIR="admin-qa"
ADMIN_EMAIL="admin@test.local"
ADMIN_PASS_HASH=$(docker exec $CONTAINER php -r "echo md5('pAZLMcookieKeyBigSecret'.'admin123');")

echo "=== PrestaShop Test Environment Setup ==="

# 1. Remove install dir
echo "[1/5] Removing install directory..."
docker exec $CONTAINER bash -c "rm -rf /var/www/html/install" 2>/dev/null || true

# 2. Rename admin to known path
echo "[2/5] Setting admin path to /$ADMIN_DIR..."
docker exec $CONTAINER bash -c "
  # Find current admin dir (could be admin, admin-qa, or admin-randomXXX)
  CURRENT=\$(ls -d /var/www/html/admin?* 2>/dev/null | grep -v admin-api | head -1)
  if [ -z \"\$CURRENT\" ] && [ -d /var/www/html/admin ]; then
    CURRENT=/var/www/html/admin
  fi
  if [ -n \"\$CURRENT\" ] && [ \"\$CURRENT\" != '/var/www/html/$ADMIN_DIR' ]; then
    mv \"\$CURRENT\" /var/www/html/$ADMIN_DIR
  fi
  rm -rf /var/www/html/var/cache/*
"

# 3. Set admin credentials (PS 9 uses bcrypt)
echo "[3/5] Setting admin credentials..."
PASS_HASH=$(docker exec $CONTAINER php -r "echo password_hash('admin123', PASSWORD_BCRYPT);")

docker exec $DB_CONTAINER mysql -u$DB_USER -p$DB_PASS $DB_NAME -e "
  UPDATE ps_employee SET
    email = '$ADMIN_EMAIL',
    passwd = '$PASS_HASH',
    lastname = 'Admin',
    firstname = 'QA'
  WHERE id_employee = 1;
" 2>/dev/null

# 4. Install module dependencies
echo "[4/5] Installing module dependencies..."
docker exec $CONTAINER bash -c "
  if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 2>/dev/null
  fi
  cd /var/www/html/modules/aismarttalk && composer install --quiet 2>/dev/null
  echo 'Module ready'
"

# 5. Verify
echo "[5/5] Verifying..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -L "http://localhost/$ADMIN_DIR/")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
  echo ""
  echo "=== Ready ==="
  echo "Front:  http://localhost"
  echo "Admin:  http://localhost/$ADMIN_DIR/"
  echo "Email:  $ADMIN_EMAIL"
  echo "Pass:   admin123"
else
  echo "WARNING: Admin returned HTTP $HTTP_CODE"
fi
