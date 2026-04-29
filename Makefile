.PHONY: up down logs logs-error bash test test-verbose test-filter test-coverage test-install test-integration test-db-up test-db-down test-all smoke-test smoke-test-ps17 smoke-test-ps1751 e2e e2e-install e2e-ps17 e2e-ps1751 e2e-headed e2e-ui e2e-setup e2e-reset e2e-reset-ps17 e2e-reset-ps1751 e2e-all e2e-multistore e2e-multistore-ps17 e2e-multistore-ps1751 e2e-multistore-enable e2e-multistore-disable e2e-multistore-enable-ps17 e2e-multistore-disable-ps17 e2e-multistore-enable-ps1751 e2e-multistore-disable-ps1751 ps1751 ps1751-stop ps1751-clean ps1751-logs ps1751-bash ps1751-admin init-test init-test-ps1751

# ──────────────────────────────────────────────
# Unit Tests (no DB needed)
# ──────────────────────────────────────────────
MODULE_DIR = modules/aismarttalk
PHPUNIT    = vendor/bin/phpunit
PHPUNIT_CFG = phpunit.xml
PHPUNIT_INT_CFG = phpunit.integration.xml

# Install test dependencies (PHPUnit)
test-install:
	cd $(MODULE_DIR) && composer install

# Run unit tests
test:
	@cd $(MODULE_DIR) && composer install --quiet 2>/dev/null; $(PHPUNIT) --configuration $(PHPUNIT_CFG)

# Run unit tests with verbose output
test-verbose:
	@cd $(MODULE_DIR) && composer install --quiet 2>/dev/null; $(PHPUNIT) --configuration $(PHPUNIT_CFG) --verbose

# Run a specific test file or filter
# Usage: make test-filter FILTER=MultistoreHelper
test-filter:
	@cd $(MODULE_DIR) && $(PHPUNIT) --configuration $(PHPUNIT_CFG) --filter="$(FILTER)"

# Run tests with code coverage (requires Xdebug or PCOV)
test-coverage:
	@cd $(MODULE_DIR) && XDEBUG_MODE=coverage $(PHPUNIT) --configuration $(PHPUNIT_CFG) --coverage-text --coverage-html=tests/coverage

# ──────────────────────────────────────────────
# Integration Tests (real MySQL DB via Docker)
# ──────────────────────────────────────────────
# Start test database
test-db-up:
	docker compose -f docker-compose.test.yml up -d
	@echo "Waiting for MySQL to be ready..."
	@until docker exec aismarttalk_test_db mysqladmin ping -h localhost -u test -ptest --silent 2>/dev/null; do sleep 1; done
	@echo "Test DB ready on port 3399"

# Stop test database
test-db-down:
	docker compose -f docker-compose.test.yml down

# Run integration tests (starts DB if needed)
test-integration: test-db-up
	@cd $(MODULE_DIR) && composer install --quiet 2>/dev/null; $(PHPUNIT) --configuration $(PHPUNIT_INT_CFG)

# Run integration tests with SQL query logging
# Level 1: show queries | Level 2: show queries + results
test-integration-verbose: test-db-up
	@cd $(MODULE_DIR) && TEST_SQL_LOG=2 $(PHPUNIT) --configuration $(PHPUNIT_INT_CFG) --verbose

# ──────────────────────────────────────────────
# Smoke Tests (run inside a real PS container)
# ──────────────────────────────────────────────
# Requires a running PS container (make up, make ps17, etc.)

# Smoke test on PS 9 (default container)
smoke-test:
	docker exec prestashop php modules/aismarttalk/tests/Smoke/run_smoke_tests.php

# Smoke test on PS 1.7
smoke-test-ps17:
	docker exec prestashop17 php modules/aismarttalk/tests/Smoke/run_smoke_tests.php

# Smoke test on PS 1.7.5.1
smoke-test-ps1751:
	docker exec prestashop1751 php modules/aismarttalk/tests/Smoke/run_smoke_tests.php

# ──────────────────────────────────────────────
# E2E Tests (Playwright — browser-based)
# ──────────────────────────────────────────────
# Requires: a running PS container + Node.js installed

# Common variables
PS9_ADMIN_PATH = $$(docker exec prestashop sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")
PS17_ADMIN_PATH = $$(docker exec prestashop17 sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")
PS1751_ADMIN_PATH = $$(docker exec prestashop1751 sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")
OAUTH_KEYS = 'AI_SMART_TALK_OAUTH_CONNECTED','AI_SMART_TALK_ACCESS_TOKEN','AI_SMART_TALK_CHAT_MODEL_ID','AI_SMART_TALK_OAUTH_SCOPE','AI_SMART_TALK_SITE_IDENTIFIER','AI_SMART_TALK_OAUTH_PENDING','AI_SMART_TALK_OAUTH_SUCCESS','AI_SMART_TALK_OAUTH_ERROR','CHAT_MODEL_ID','CHAT_MODEL_TOKEN','AI_SMART_TALK_ENABLED'

# Cache-clearing macros
define clear-ps9-cache
	@docker exec prestashop bash -c "rm -rf /var/www/html/var/cache/prod/* /var/www/html/var/cache/dev/* /var/www/html/cache/smarty/cache/* /var/www/html/cache/smarty/compile/* /var/www/html/cache/cachefs/* 2>/dev/null" || true
endef

define clear-ps17-cache
	@docker exec prestashop17 bash -c "rm -rf /var/www/html/var/cache/prod/* /var/www/html/var/cache/dev/* /var/www/html/cache/smarty/cache/* /var/www/html/cache/smarty/compile/* /var/www/html/cache/cachefs/* 2>/dev/null" || true
endef

define clear-ps1751-cache
	@docker exec prestashop1751 bash -c "rm -rf /var/www/html/var/cache/prod/* /var/www/html/var/cache/dev/* /var/www/html/cache/smarty/cache/* /var/www/html/cache/smarty/compile/* /var/www/html/cache/cachefs/* 2>/dev/null" || true
endef

# Install Playwright
e2e-install:
	cd tests/e2e && npm install && npx playwright install chromium

# Reset OAuth state (PS9)
e2e-reset:
	@docker exec prestashop_db mysql -u prestashop -pprestashop prestashop -e "DELETE FROM ps_configuration WHERE name IN ($(OAUTH_KEYS));" 2>/dev/null || true
	$(call clear-ps9-cache)
	@echo "✓ PS9 OAuth reset"

# Reset OAuth state (PS 1.7)
e2e-reset-ps17:
	@docker exec prestashop17_db mysql -u prestashop -pprestashop prestashop -e "DELETE FROM ps_configuration WHERE name IN ($(OAUTH_KEYS));" 2>/dev/null || true
	$(call clear-ps17-cache)
	@echo "✓ PS 1.7 OAuth reset"

# Reset OAuth state (PS 1.7.5.1)
e2e-reset-ps1751:
	@docker exec prestashop1751_db mysql -u prestashop -pprestashop prestashop -e "DELETE FROM ps_configuration WHERE name IN ($(OAUTH_KEYS));" 2>/dev/null || true
	$(call clear-ps1751-cache)
	@echo "✓ PS 1.7.5.1 OAuth reset"

# Run E2E on PS 9 — auto-resets before running
e2e: e2e-reset
	cd tests/e2e && PS_URL=http://localhost PS_ADMIN_PATH=$(PS9_ADMIN_PATH) npx playwright test

# Run E2E on PS 1.7 — auto-resets before running
e2e-ps17: e2e-reset-ps17
	cd tests/e2e && PS_URL=http://localhost:8091 PS_ADMIN_PATH=$(PS17_ADMIN_PATH) ADMIN_EMAIL=demo@prestashop.com ADMIN_PASS=Admin_Presta17! npx playwright test

# Run E2E on PS 1.7.5.1 — auto-resets before running
e2e-ps1751: e2e-reset-ps1751
	cd tests/e2e && PS_URL=http://localhost:8093 PS_ADMIN_PATH=$(PS1751_ADMIN_PATH) ADMIN_EMAIL=demo@prestashop.com ADMIN_PASS=Admin_Presta1751! npx playwright test

# Run E2E headed (visible browser)
e2e-headed: e2e-reset
	cd tests/e2e && PS_URL=http://localhost PS_ADMIN_PATH=$(PS9_ADMIN_PATH) npx playwright test --headed

# Run E2E in Playwright UI mode
e2e-ui: e2e-reset
	cd tests/e2e && PS_URL=http://localhost PS_ADMIN_PATH=$(PS9_ADMIN_PATH) npx playwright test --ui --project=chromium

# Run OAuth setup only (headed)
e2e-setup: e2e-reset
	cd tests/e2e && PS_URL=http://localhost PS_ADMIN_PATH=$(PS9_ADMIN_PATH) npx playwright test --headed --project=setup

# Enable/disable multistore on PS9
e2e-multistore-enable:
	@docker exec -i prestashop_db mysql -u prestashop -pprestashop prestashop < tests/e2e/fixtures/enable-multistore.sql
	$(call clear-ps9-cache)
	@echo "✓ Multistore enabled (2 shops)"

e2e-multistore-disable:
	@docker exec -i prestashop_db mysql -u prestashop -pprestashop prestashop < tests/e2e/fixtures/disable-multistore.sql
	$(call clear-ps9-cache)
	@echo "✓ Multistore disabled (single shop)"

# Enable/disable multistore on PS 1.7
e2e-multistore-enable-ps17:
	@docker exec -i prestashop17_db mysql -u prestashop -pprestashop prestashop < tests/e2e/fixtures/enable-multistore.sql
	$(call clear-ps17-cache)
	@echo "✓ Multistore enabled on PS 1.7 (2 shops)"

e2e-multistore-disable-ps17:
	@docker exec -i prestashop17_db mysql -u prestashop -pprestashop prestashop < tests/e2e/fixtures/disable-multistore.sql
	$(call clear-ps17-cache)
	@echo "✓ Multistore disabled on PS 1.7 (single shop)"

# Enable/disable multistore on PS 1.7.5.1 (no `color` column on ps_shop)
e2e-multistore-enable-ps1751:
	@docker exec -i prestashop1751_db mysql -u prestashop -pprestashop prestashop < tests/e2e/fixtures/enable-multistore-ps1751.sql
	$(call clear-ps1751-cache)
	@echo "✓ Multistore enabled on PS 1.7.5.1 (2 shops)"

e2e-multistore-disable-ps1751:
	@docker exec -i prestashop1751_db mysql -u prestashop -pprestashop prestashop < tests/e2e/fixtures/disable-multistore.sql
	$(call clear-ps1751-cache)
	@echo "✓ Multistore disabled on PS 1.7.5.1 (single shop)"

# Run E2E on PS9 with multistore (enables, resets OAuth, runs, disables)
e2e-multistore: e2e-multistore-enable e2e-reset
	cd tests/e2e && PS_URL=http://localhost PS_ADMIN_PATH=$(PS9_ADMIN_PATH) npx playwright test; \
	EXIT_CODE=$$?; \
	$(MAKE) -C $(CURDIR) -s e2e-multistore-disable; \
	exit $$EXIT_CODE

# Run E2E on PS 1.7 with multistore
e2e-multistore-ps17: e2e-multistore-enable-ps17 e2e-reset-ps17
	cd tests/e2e && PS_URL=http://localhost:8091 PS_ADMIN_PATH=$(PS17_ADMIN_PATH) ADMIN_EMAIL=demo@prestashop.com ADMIN_PASS=Admin_Presta17! npx playwright test; \
	EXIT_CODE=$$?; \
	$(MAKE) -C $(CURDIR) -s e2e-multistore-disable-ps17; \
	exit $$EXIT_CODE

# Run E2E on PS 1.7.5.1 with multistore
e2e-multistore-ps1751: e2e-multistore-enable-ps1751 e2e-reset-ps1751
	cd tests/e2e && PS_URL=http://localhost:8093 PS_ADMIN_PATH=$(PS1751_ADMIN_PATH) ADMIN_EMAIL=demo@prestashop.com ADMIN_PASS=Admin_Presta1751! npx playwright test; \
	EXIT_CODE=$$?; \
	$(MAKE) -C $(CURDIR) -s e2e-multistore-disable-ps1751; \
	exit $$EXIT_CODE

# Run ALL E2E tests (PS 9 + PS 1.7, single-shop & multistore)
e2e-all:
	@echo "═══════════════════════════════════════"
	@echo "  E2E Tests — PrestaShop 9"
	@echo "═══════════════════════════════════════"
	$(MAKE) e2e
	@echo ""
	@echo "═══════════════════════════════════════"
	@echo "  E2E Tests — PrestaShop 1.7"
	@echo "═══════════════════════════════════════"
	$(MAKE) e2e-ps17
	@echo ""
	@echo "═══════════════════════════════════════"
	@echo "  E2E Tests — PS 9 Multistore"
	@echo "═══════════════════════════════════════"
	$(MAKE) e2e-multistore
	@echo ""
	@echo "═══════════════════════════════════════"
	@echo "  E2E Tests — PS 1.7 Multistore"
	@echo "═══════════════════════════════════════"
	$(MAKE) e2e-multistore-ps17
	@echo ""
	@echo "═══════════════════════════════════════"
	@echo "  E2E Tests — PrestaShop 1.7.5.1"
	@echo "═══════════════════════════════════════"
	$(MAKE) e2e-ps1751
	@echo ""
	@echo "═══════════════════════════════════════"
	@echo "  E2E Tests — PS 1.7.5.1 Multistore"
	@echo "═══════════════════════════════════════"
	$(MAKE) e2e-multistore-ps1751
	@echo ""
	@echo "✅ All E2E tests passed: PS 9 + PS 1.7 + PS 1.7.5.1 (single-shop & multistore)"

# ──────────────────────────────────────────────
# Run ALL tests (unit + integration)
# ──────────────────────────────────────────────
test-all: test test-integration

# Define the services
SERVICES = prestashop prestashop_db

# Start the containers
up:
	docker compose up -d

# Init test environment: remove install dir, set admin path to /admin-qa, reset credentials
init-test:
	bash scripts/init-test-env.sh

# Init PS 1.7.5.1 test environment: fix perms, install composer deps, install the module
init-test-ps1751:
	bash scripts/init-test-env-ps1751.sh

# Stop the containers
down:
	docker compose down

down-v:
	docker compose down -v	

# View logs for all services
logs:
	docker compose logs -f

logs-error:
	docker compose logs -f | grep -i error

# Access bash in the prestashop container
bash:
	docker compose exec prestashop bash

# Access bash in the prestashop_db container
db_bash:
	docker compose exec prestashop_db bash

# Clean up unused images and containers
clean:
	docker system prune -f

# Restart the containers
restart: down up

# Build the containers
build:
	docker compose build

# Display admin folder name for quick URL access
admin:
	@docker compose exec prestashop sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename"

# ──────────────────────────────────────────────
# Build Production Archive
# ──────────────────────────────────────────────
# Builds in a temp dir with `composer install --no-dev` so PHPUnit (require-dev,
# uses PHP 7.3+ syntax) is never shipped — required for PS 1.7.5.1 / PHP 7.2.
build-prod:
	@echo "📦 Building production zip for aismarttalk module..."
	@rm -f aismarttalk.zip
	@TMP=$$(mktemp -d) && \
		cp -R modules/aismarttalk "$$TMP/" && \
		rm -rf "$$TMP/aismarttalk/vendor" "$$TMP/aismarttalk/tests" && \
		(cd "$$TMP/aismarttalk" && composer install --no-dev --quiet --no-progress --no-interaction) && \
		(cd "$$TMP" && zip -q -r "$(CURDIR)/aismarttalk.zip" aismarttalk \
			-x "aismarttalk/phpunit*" \
			-x "aismarttalk/.phpunit*" \
			-x "aismarttalk/.git/*" \
			-x "aismarttalk/.gitignore" \
			-x "*/.DS_Store") && \
		rm -rf "$$TMP"
	@echo "✅ Build complete: aismarttalk.zip generated in the root directory."

# ──────────────────────────────────────────────
# PrestaShop Multi-Site + AI SmartTalk Backend
# ──────────────────────────────────────────────
MULTISITE_COMPOSE=docker compose -f docker-compose.multisite.yml

multisite:
	docker network create ai-toolkit-network || true
	$(MULTISITE_COMPOSE) down
	$(MULTISITE_COMPOSE) up -d
	@echo ""
	@echo "=== PrestaShop Multi-Site Ready ==="
	@echo "PrestaShop Site 1 (FR): http://localhost:8081"
	@echo "PrestaShop Site 2 (EN): http://localhost:8082"
	@echo "PhpMyAdmin:             http://localhost:8080"
	@echo ""
	@echo "Admin credentials:"
	@echo "  Site 1: demo@prestashop.com / Admin_Presta1!"
	@echo "  Site 2: demo@prestashop.com / Admin_Presta2!"

multisite-stop:
	$(MULTISITE_COMPOSE) down

multisite-clean:
	$(MULTISITE_COMPOSE) down -v

multisite-logs:
	$(MULTISITE_COMPOSE) logs -f

admin-multisite:
	@echo "PrestaShop 1 (FR):" && echo "  http://localhost:8081/$$(docker exec prestashop1 sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")"
	@echo "PrestaShop 2 (EN):" && echo "  http://localhost:8082/$$(docker exec prestashop2 sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")"

# ──────────────────────────────────────────────
# PrestaShop 1.7 (test environment)
# ──────────────────────────────────────────────
PS17_COMPOSE=docker compose -f docker-compose.ps17.yml

ps17:
	docker network create ai-toolkit-network || true
	$(PS17_COMPOSE) up -d
	@echo ""
	@echo "=== PrestaShop 1.7 Ready ==="
	@echo "PrestaShop 1.7: http://localhost:8091"
	@echo "PhpMyAdmin:     http://localhost:8092"
	@echo ""
	@echo "Admin credentials: demo@prestashop.com / Admin_Presta17!"

ps17-stop:
	$(PS17_COMPOSE) down

ps17-clean:
	$(PS17_COMPOSE) down -v

ps17-logs:
	$(PS17_COMPOSE) logs -f

ps17-bash:
	docker exec -it prestashop17 bash

ps17-admin:
	@echo "PrestaShop 1.7:" && echo "  http://localhost:8091/$$(docker exec prestashop17 sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")"

# ──────────────────────────────────────────────
# PrestaShop 1.7.5.1 (PHP 7.2 — legacy support)
# ──────────────────────────────────────────────
PS1751_COMPOSE=docker compose -f docker-compose.ps1751.yml

ps1751:
	docker network create ai-toolkit-network || true
	$(PS1751_COMPOSE) up -d
	@echo ""
	@echo "=== PrestaShop 1.7.5.1 Ready ==="
	@echo "PrestaShop 1.7.5.1: http://localhost:8093"
	@echo "PhpMyAdmin:         http://localhost:8094"
	@echo ""
	@echo "Admin credentials: demo@prestashop.com / Admin_Presta1751!"

ps1751-stop:
	$(PS1751_COMPOSE) down

ps1751-clean:
	$(PS1751_COMPOSE) down -v

ps1751-logs:
	$(PS1751_COMPOSE) logs -f

ps1751-bash:
	docker exec -it prestashop1751 bash

ps1751-admin:
	@echo "PrestaShop 1.7.5.1:" && echo "  http://localhost:8093/$$(docker exec prestashop1751 sh -c "ls -d /var/www/html/admin* | grep -v admin-api | head -1 | xargs basename")"
