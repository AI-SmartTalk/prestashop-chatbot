# Production Setup

PrestaShop demo instance running at `https://prestashop.demo.aismarttalk.tech`

## Architecture

- **PrestaShop** (port 8082) - behind reverse proxy with SSL
- **MySQL 5.7** - data persisted in `./data/mysql/`
- **phpMyAdmin** - localhost only (127.0.0.1:8083), access via SSH tunnel
- **Automated backups** - daily at 2am, 7-day rotation

## Initial Setup (VPS)

The only manual step is creating the `.env` file. Everything else (directories, permissions, cron, network) is handled automatically by the deploy workflow.

```bash
cd /home/debian/prestashop-chatbot
cp .env.example .env
nano .env  # Set real credentials
```

**Important:** `DB_PASSWD` and `MYSQL_PASSWORD` must have the same value.

Then push to `main` and the GitHub Actions workflow handles the rest.

## What the deploy workflow does automatically

1. Checks `.env` exists (fails if missing)
2. Creates `data/` and `backups/` directories
3. Makes scripts executable
4. Creates Docker network if needed
5. Runs a pre-deploy MySQL backup
6. `git pull` + `docker compose up -d --build`
7. Waits for all services to be healthy (up to 180s)
8. Fixes data permissions (mysql:999, www-data:33)
9. Configures domain/SSL in database
10. Clears PrestaShop cache
11. Installs backup cron (daily at 2am)

## Migration from Named Volumes

If migrating from an existing setup with Docker named volumes:

```bash
# Copy data (while containers are stopped)
docker compose -f docker-compose.prod.yml down
mkdir -p data/mysql data/prestashop
cp -a /var/lib/docker/volumes/prestashop-chatbot_prestashop_dbdata/_data/* data/mysql/
cp -a /var/lib/docker/volumes/prestashop-chatbot_psdata/_data/* data/prestashop/
chown -R 999:999 data/mysql
chown -R 33:33 data/prestashop
docker compose -f docker-compose.prod.yml up -d
```

## Daily Operations

### Backups

Backups run automatically via cron. Manual commands:

```bash
./scripts/backup-mysql.sh                              # manual backup
ls -lh backups/mysql/                                   # list backups
./scripts/restore-mysql.sh backups/mysql/<file>.sql.gz  # restore
```

### phpMyAdmin

Not exposed publicly. Access via SSH tunnel:

```bash
ssh -L 8083:localhost:8083 debian@<VPS_IP>
# Then open http://localhost:8083
```

### Logs

```bash
docker compose -f docker-compose.prod.yml logs -f prestashop
docker compose -f docker-compose.prod.yml logs -f prestashop_db
tail -f backups/backup.log
```
