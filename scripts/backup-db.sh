#!/usr/bin/env bash
#
# Respaldo de PostgreSQL de Rifax.
#
# - Lee las credenciales del .env del proyecto (DB_HOST, DB_PORT, DB_DATABASE,
#   DB_USERNAME, DB_PASSWORD).
# - Crea un volcado comprimido (formato custom de pg_dump) en BACKUP_DIR.
# - Verifica que el archivo se pueda leer con pg_restore --list.
# - Borra respaldos locales con más de RETENTION_DAYS días.
# - Si RCLONE_REMOTE está definido (p. ej. "b2:rifax-backups"), copia el
#   respaldo fuera del servidor con rclone.
#
# Uso:  scripts/backup-db.sh [/ruta/al/proyecto]
# Cron: 15 3 * * * /var/www/rifax/scripts/backup-db.sh /var/www/rifax >> /var/log/rifax-backup.log 2>&1

set -euo pipefail

PROJECT_DIR="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
ENV_FILE="$PROJECT_DIR/.env"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/rifax}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
RCLONE_REMOTE="${RCLONE_REMOTE:-}"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

if [[ ! -f "$ENV_FILE" ]]; then
  log "ERROR: no existe $ENV_FILE"
  exit 1
fi

# Lee una variable del .env sin ejecutar el archivo (quita comillas).
env_value() {
  local value
  value="$(grep -E "^$1=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f 2- || true)"
  value="${value%\"}"; value="${value#\"}"
  value="${value%\'}"; value="${value#\'}"
  printf '%s' "$value"
}

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
export PGPASSWORD="$(env_value DB_PASSWORD)"

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=5432}"

if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" ]]; then
  log "ERROR: faltan DB_DATABASE o DB_USERNAME en $ENV_FILE"
  exit 1
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

FILE="$BACKUP_DIR/${DB_DATABASE}-$(date '+%Y%m%d-%H%M%S').dump"
TMP="$FILE.partial"

log "Respaldando $DB_DATABASE@$DB_HOST:$DB_PORT → $FILE"
pg_dump --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
  --format=custom --compress=9 --no-owner --no-privileges \
  --file="$TMP" "$DB_DATABASE"

# Un respaldo que no se puede leer no sirve: se valida antes de darlo por bueno.
pg_restore --list "$TMP" > /dev/null
mv "$TMP" "$FILE"
chmod 600 "$FILE"
log "OK ($(du -h "$FILE" | cut -f1))"

if [[ -n "$RCLONE_REMOTE" ]]; then
  log "Copiando a $RCLONE_REMOTE"
  rclone copy "$FILE" "$RCLONE_REMOTE" --no-traverse
fi

find "$BACKUP_DIR" -name '*.dump' -type f -mtime "+$RETENTION_DAYS" -print -delete \
  | sed 's/^/Borrado respaldo antiguo: /'

log "Listo"
