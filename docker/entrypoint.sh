#!/bin/sh
# Arranque de los contenedores de Rifax (app, worker y scheduler).
set -e

cd /var/www/html

# Cachea configuración, rutas, vistas y eventos con las variables del .env actual.
# Si falla (p. ej. .env incompleto) se muestra el error y el contenedor no arranca.
php artisan optimize --no-interaction

exec "$@"
