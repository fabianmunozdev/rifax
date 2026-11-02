#!/usr/bin/env bash
#
# Despliega Rifax con Docker en el servidor.
#
#   scripts/deploy.sh            # actualiza a la última versión de la rama actual
#   scripts/deploy.sh v1.2.0     # despliega un tag o rama concretos
#
# Pasos: respaldo → git → build → migraciones → reinicio. Si algo falla, se
# detiene y la versión anterior sigue en marcha (los contenedores solo se
# reemplazan después de construir la imagen nueva).

set -euo pipefail

cd "$(dirname "$0")/.."
REF="${1:-}"
COMPOSE=(docker compose -f compose.prod.yaml)
log() { echo -e "\n\033[1;34m▶ $*\033[0m"; }

[[ -f .env ]] || { echo "Falta .env (copiar de .env.production.example)"; exit 1; }

if "${COMPOSE[@]}" ps --status running --services 2>/dev/null | grep -qx postgres; then
  log "Respaldo previo de la base de datos"
  scripts/docker-backup.sh
fi

log "Actualizando código"
git fetch --tags --prune origin
if [[ -n "$REF" ]]; then
  git checkout --quiet "$REF"
fi
if git symbolic-ref -q HEAD >/dev/null; then
  git pull --ff-only
fi
export RIFAX_IMAGE_TAG="$(git rev-parse --short HEAD)"
echo "Versión: $RIFAX_IMAGE_TAG ($(git log -1 --format=%s))"

log "Construyendo imágenes"
"${COMPOSE[@]}" build --pull

log "Levantando base de datos y Redis"
"${COMPOSE[@]}" up -d --wait postgres redis

log "Migraciones"
"${COMPOSE[@]}" run --rm --no-deps app php artisan migrate --force

log "Reemplazando contenedores"
"${COMPOSE[@]}" up -d --remove-orphans --wait app
"${COMPOSE[@]}" up -d --remove-orphans worker scheduler web
# Los workers terminan el trabajo en curso y cargan el código nuevo.
"${COMPOSE[@]}" exec -T app php artisan queue:restart

log "Comprobando"
"${COMPOSE[@]}" ps
"${COMPOSE[@]}" exec -T app php artisan about --only=environment | head -n 12

# Limpia imágenes viejas sin usar (conserva las de los últimos 7 días para volver atrás).
docker image prune -f --filter "until=168h" >/dev/null

log "Listo: versión $RIFAX_IMAGE_TAG"
