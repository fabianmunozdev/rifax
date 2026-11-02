#!/usr/bin/env bash
#
# Respaldo de PostgreSQL de Rifax cuando corre con Docker (compose.prod.yaml).
#
# - Volcado comprimido desde el contenedor "postgres".
# - Archivo .tar.gz con storage/app (comprobantes de pago, boletos, logos).
# - Verifica que se pueda leer (pg_restore --list).
# - Borra respaldos locales con más de RETENTION_DAYS días.
# - Copia fuera del servidor con rclone si RCLONE_REMOTE está definido.
#
# Cron diario (3:15 a. m.):
#   15 3 * * * /opt/rifax/scripts/docker-backup.sh >> /var/log/rifax-backup.log 2>&1

set -euo pipefail

cd "$(dirname "$0")/.."
BACKUP_DIR="${BACKUP_DIR:-/var/backups/rifax}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
RCLONE_REMOTE="${RCLONE_REMOTE:-}"
COMPOSE=(docker compose -f compose.prod.yaml)
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

env_value() {
  local v
  v="$(grep -E "^$1=" .env | tail -n 1 | cut -d '=' -f 2- || true)"
  v="${v%\"}"; v="${v#\"}"; printf '%s' "$v"
}
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
FILE="$BACKUP_DIR/${DB_DATABASE}-$(date '+%Y%m%d-%H%M%S').dump"

log "Respaldando $DB_DATABASE → $FILE"
"${COMPOSE[@]}" exec -T postgres pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" \
  --format=custom --compress=9 --no-owner --no-privileges > "$FILE.partial"

# Un respaldo ilegible no sirve: se valida antes de darlo por bueno.
"${COMPOSE[@]}" exec -T postgres pg_restore --list < "$FILE.partial" > /dev/null
mv "$FILE.partial" "$FILE"
chmod 600 "$FILE"
log "OK ($(du -h "$FILE" | cut -f1))"

FILES="${FILE%.dump}-storage.tar.gz"
log "Respaldando archivos (storage/app) → $FILES"
"${COMPOSE[@]}" exec -T app tar -czf - -C /var/www/html/storage app > "$FILES.partial"
tar -tzf "$FILES.partial" > /dev/null
mv "$FILES.partial" "$FILES"
chmod 600 "$FILES"
log "OK ($(du -h "$FILES" | cut -f1))"

if [[ -n "$RCLONE_REMOTE" ]]; then
  log "Copiando a $RCLONE_REMOTE"
  rclone copy "$FILE" "$RCLONE_REMOTE" --no-traverse
  rclone copy "$FILES" "$RCLONE_REMOTE" --no-traverse
fi

find "$BACKUP_DIR" \( -name '*.dump' -o -name '*-storage.tar.gz' \) -type f -mtime "+$RETENTION_DAYS" -print -delete \
  | sed 's/^/Borrado respaldo antiguo: /'
log "Listo"
