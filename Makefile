.PHONY: up down logs logs-error bash

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
