# Production Setup

PrestaShop demo instance running at `https://prestashop.demo.aismarttalk.tech`

## Architecture

- **PrestaShop** (port 8082) - behind reverse proxy with SSL
- **MySQL 5.7** - data persisted in `./data/mysql/`
- **phpMyAdmin** - localhost only (127.0.0.1:8083), access via SSH tunnel
- **Automated backups** - daily at 2am, 7-day rotation

## Initial Setup (VPS)

### 1. Create directories

```bash
cd /home/debian/prestashop-chatbot
mkdir -p data/mysql data/prestashop backups/mysql
```

### 2. Create `.env`

```bash
cp .env.example .env
nano .env  # Set real credentials
```

**Important:** `DB_PASSWD` and `MYSQL_PASSWORD` must have the same value.

### 3. Create Docker network (if not exists)

```bash
docker network create ai-toolkit-network || true
```

### 4. Start services

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

### 5. Fix permissions

```bash
chown -R 33:33 data/prestashop    # www-data
chown -R 999:999 data/mysql        # mysql
```

### 6. Setup automated backups

```bash
chmod +x scripts/*.sh
crontab -e
```

Add this line:
```
0 2 * * * /home/debian/prestashop-chatbot/scripts/backup-mysql.sh >> /home/debian/prestashop-chatbot/backups/backup.log 2>&1
```

### 7. Verify

```bash
# All services should show "healthy"
docker compose -f docker-compose.prod.yml ps

# Test backup
./scripts/backup-mysql.sh

# Check site
curl -I https://prestashop.demo.aismarttalk.tech
```

## Migration from Named Volumes

If migrating from an existing setup with Docker named volumes:

```bash
# Find existing volume data
docker volume inspect prestashop_dbdata | grep Mountpoint
docker volume inspect psdata | grep Mountpoint

# Copy data (while containers are stopped)
docker compose -f docker-compose.prod.yml down
cp -a /var/lib/docker/volumes/prestashop-chatbot_prestashop_dbdata/_data/* data/mysql/
cp -a /var/lib/docker/volumes/prestashop-chatbot_psdata/_data/* data/prestashop/

# Fix permissions
chown -R 999:999 data/mysql
chown -R 33:33 data/prestashop

# Start with bind mounts
docker compose -f docker-compose.prod.yml up -d
```

## Daily Operations

### Backups

Backups run automatically via cron. Manual commands:

```bash
# Manual backup
./scripts/backup-mysql.sh

# List backups
ls -lh backups/mysql/

# Restore from backup
./scripts/restore-mysql.sh backups/mysql/<filename>.sql.gz
```

### phpMyAdmin

Not exposed publicly. Access via SSH tunnel:

```bash
ssh -L 8083:localhost:8083 debian@<VPS_IP>
# Then open http://localhost:8083
```

### Logs

```bash
# Service logs
docker compose -f docker-compose.prod.yml logs -f prestashop
docker compose -f docker-compose.prod.yml logs -f prestashop_db

# Backup logs
tail -f backups/backup.log
```

### Restart

```bash
docker compose -f docker-compose.prod.yml restart
```

## Deployment

Automatic via GitHub Actions on push to `main`. The workflow:

1. Checks `.env` exists on VPS
2. Runs a pre-deploy MySQL backup
3. `git pull` + `docker compose up -d --build`
4. Waits for all services to be healthy (up to 180s)
5. Configures domain/SSL in database
6. Clears PrestaShop cache
