.PHONY: up down logs logs-error bash test test-verbose test-filter test-coverage test-install

# ──────────────────────────────────────────────
# Tests
# ──────────────────────────────────────────────
MODULE_DIR = modules/aismarttalk
PHPUNIT    = vendor/bin/phpunit
PHPUNIT_CFG = phpunit.xml

# Install test dependencies (PHPUnit)
test-install:
	cd $(MODULE_DIR) && composer install

# Run all tests
test:
	@cd $(MODULE_DIR) && composer install --quiet 2>/dev/null; $(PHPUNIT) --configuration $(PHPUNIT_CFG)

# Run tests with verbose output
test-verbose:
	@cd $(MODULE_DIR) && composer install --quiet 2>/dev/null; $(PHPUNIT) --configuration $(PHPUNIT_CFG) --verbose

# Run a specific test file or filter
# Usage: make test-filter FILTER=MultistoreHelper
#        make test-filter FILTER=testGetConfigReadsGlobalValue
test-filter:
	@cd $(MODULE_DIR) && $(PHPUNIT) --configuration $(PHPUNIT_CFG) --filter="$(FILTER)"

# Run tests with code coverage (requires Xdebug or PCOV)
test-coverage:
	@cd $(MODULE_DIR) && XDEBUG_MODE=coverage $(PHPUNIT) --configuration $(PHPUNIT_CFG) --coverage-text --coverage-html=tests/coverage

# Define the services
SERVICES = prestashop prestashop_db

# Start the containers
up:
	docker compose up -d

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
