#!/bin/bash
set -euo pipefail

# Resolve project root (parent of scripts/)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"

# Load .env
if [ ! -f "$ENV_FILE" ]; then
  echo "ERROR: .env file not found at $ENV_FILE"
  exit 1
fi
set -a
source "$ENV_FILE"
set +a

# Check argument
if [ $# -lt 1 ]; then
  echo "Usage: $0 <backup_file.sql.gz>"
  echo ""
  echo "Available backups:"
  ls -lh "$PROJECT_DIR/backups/mysql/"*.sql.gz 2>/dev/null || echo "  No backups found"
  exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
  echo "ERROR: File not found: $BACKUP_FILE"
  exit 1
fi

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "WARNING: You are about to restore the database '$MYSQL_DATABASE' from:"
echo "  File: $BACKUP_FILE"
echo "  Size: $BACKUP_SIZE"
echo ""
echo "This will OVERWRITE all current data in the database."
echo ""
read -p "Are you sure? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
  echo "Restore cancelled."
  exit 0
fi

echo "[$(date)] Restoring database '$MYSQL_DATABASE' from $BACKUP_FILE..."

gunzip -c "$BACKUP_FILE" | docker compose -f "$PROJECT_DIR/docker-compose.prod.yml" exec -T prestashop_db \
  mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"

echo "[$(date)] Restore completed successfully."
