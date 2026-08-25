# Despliegue manual y base para CI/CD

## Contexto actual

- Servidor productivo: `95.217.177.163`
- Usuario SSH: `deploy`
- Ruta del proyecto: `/var/www/rifax`
- Dominio: `https://rifax.fabianmunoz.dev`
- Stack productivo:
  - `Nginx`
  - `PHP 8.5`
  - `PostgreSQL`
  - `Redis`
  - `Supervisor`
  - `pnpm`

## 1. Objetivo de este documento

Este documento define:

- como hacer despliegues manuales de forma segura
- que validar antes y despues de publicar cambios
- una base de referencia para automatizar luego con `CI/CD`

## 2. Estrategia actual de despliegue

Actualmente el despliegue puede hacerse de forma manual con este flujo:

1. preparar cambios localmente
2. subir codigo al servidor
3. instalar dependencias
4. compilar assets
5. ejecutar migraciones
6. refrescar cache de Laravel
7. reiniciar worker de colas
8. validar sitio y panel admin

## 3. Checklist previo al despliegue

Antes de desplegar:

- confirmar que el proyecto funciona localmente
- revisar que el `.env` de produccion no requiera cambios adicionales
- confirmar si hay migraciones nuevas
- confirmar si hay cambios de frontend que requieran `pnpm build`
- confirmar si hay cambios en colas, scheduler o configuracion de `Nginx`

## 4. Acceso al servidor

```bash
ssh deploy@95.217.177.163
cd /var/www/rifax
```

## 5. Forma manual recomendada de subir cambios

### Opcion A: `rsync` desde la maquina local

```bash
rsync -az --delete \
  --exclude='.env' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='.trae' \
  --exclude='.DS_Store' \
  --exclude='.phpunit.result.cache' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='bootstrap/cache/*.php' \
  -e 'ssh' \
  /Users/macbookpro/Documents/DESARROLLO/Rifax/ \
  deploy@95.217.177.163:/var/www/rifax/
```

Esta fue la estrategia usada para el primer despliegue.

### Opcion B: `git pull`

Cuando el repo remoto quede listo para despliegue desde servidor:

```bash
ssh deploy@95.217.177.163
cd /var/www/rifax
git pull origin main
```

Esta opcion sera mejor cuando dejemos `CI/CD`.

## 6. Secuencia de despliegue manual en servidor

Ya dentro del servidor:

```bash
cd /var/www/rifax
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

## 7. Si hay cambios en Nginx

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 8. Validaciones posteriores al despliegue

### Validacion web

```bash
curl -I https://rifax.fabianmunoz.dev
curl -I https://rifax.fabianmunoz.dev/admin/login
```

### Validacion de colas

```bash
sudo supervisorctl status rifax-worker
```

### Validacion del scheduler

```bash
crontab -l
```

### Validacion de logs

```bash
tail -f storage/logs/laravel.log
sudo tail -f /var/log/nginx/rifax.error.log
sudo tail -f /var/log/supervisor/rifax-worker.log
```

## 9. Casos donde debes tener cuidado

### Cambios en `.env`

Si cambias variables de:

- base de datos
- Redis
- correo
- WhatsApp
- sesiones
- cache

entonces debes ejecutar:

```bash
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

### Migraciones sensibles

Si una migracion cambia estructura usada por flujos activos:

- validar primero en local
- revisar si requiere ventana corta de mantenimiento
- evaluar si necesita script de backfill

### Cambios en jobs o colas

Si cambias clases de jobs o listeners:

- recompilar caches
- reiniciar worker obligatoriamente

## 10. Rollback operativo basico

Hoy no tenemos un sistema formal de releases versionadas en carpetas separadas.  
Por eso el rollback todavia es manual y depende de:

- volver a sincronizar una version anterior del codigo
- restaurar `.env` si hubo cambios
- revertir migraciones solo si es seguro hacerlo

Comandos tipicos despues de restaurar una version previa:

```bash
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

## 11. Estado actual del `CI/CD`

Todavia no esta implementado.  
La base recomendada para el siguiente paso es:

- repositorio remoto en `GitHub`
- despliegue al servidor por `SSH`
- pipeline que haga:
  - pruebas
  - build
  - despliegue
  - migraciones
  - reinicio de worker

## 12. Propuesta de pipeline futuro

### Fase 1

Pipeline basico:

1. disparador en push a `main`
2. instalar dependencias
3. correr pruebas
4. abrir conexion `SSH` al servidor
5. ejecutar despliegue remoto

### Fase 2

Mejoras:

- build reproducible
- despliegue con usuario dedicado
- validaciones post-deploy
- respaldo automatico antes de migraciones delicadas

### Fase 3

Hardening:

- releases por timestamp
- symlink `current`
- rollback mas limpio
- deploy sin downtime perceptible

## 13. Script remoto sugerido para futuro CI/CD

Cuando automaticemos, la secuencia remota base probablemente sera algo como:

```bash
cd /var/www/rifax
git pull origin main
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

## 14. Recomendaciones operativas

- no desplegar cambios grandes directamente sin probar local
- no activar `WHATSAPP_SEND_ENABLED=true` sin validar credenciales
- revisar siempre `laravel.log` despues de migraciones
- si cambia autenticacion o panel admin, validar de inmediato el login
- si hay cambios en pagos, tickets o reservas, hacer una prueba funcional completa

## 15. Proximo paso recomendado

El siguiente documento o trabajo operativo ideal es:

- `CI/CD` con `GitHub Actions`
- despliegue por `SSH`
- reinicio controlado de servicios
- validacion automatica minima despues del release

## 16. Boleto Premium — Instalar Chromium (render PNG con Browsershot)

Desde esta version, la generacion de boletos usa **Spatie\Browsershot + Puppeteer/Chromium** para producir boletos PNG de alta calidad con estetica premium (fondo oscuro, acentos dorados, grilla 4 columnas, 1 premio, QR en footer).

Si Chromium no esta disponible en el servidor, el pipeline **automaticamente cae en fallback SVG legado** (no se pierde ningun boleto, solo se mantiene el diseño anterior).

### 16.1 Estado actual del pipeline

- Renderer productivo en: `app/Actions/Tickets/RenderTicketAssetsAction.php`
- Plantillas Blade: `resources/views/tickets/render.blade.php` y `render-thumbnail.blade.php`
- Extension guardada: `.png` si Browsershot funciona; `.svg` si cae en fallback.
- Todos los consumidores (verificacion publica, envio WhatsApp, panel admin) son **agnosticos a la extension** (usan `asset('storage/'.$path)` generico).

### 16.2 Prerrequisitos en Hetzner CX23 (95.217.177.163)

**Node 20+ y npm** deben estar disponibles para el usuario `deploy` y para `www-data` (PHP-FPM). Validar:

```bash
ssh deploy@95.217.177.163
node -v   # >= 20
npm -v    # >= 10
```

Si faltan:

```bash
# NodeSource (Ubuntu 24.04 amd64)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs
node -v && npm -v
```

### 16.3 Instalar Chromium para Browsershot (3 vias)

#### Via A (recomendada) — puppeteer npm dentro del proyecto

```bash
cd /var/www/rifax

# Instalar dependencias npm (incluye puppeteer en devDependencies)
npm ci --include=dev

# (pnpm alternativa) si usas pnpm en vez de npm:
# pnpm install --frozen-lockfile
# pnpm approve-builds puppeteer

# Forzar la descarga del binario Chromium para linux x86_64
node node_modules/puppeteer/install.mjs

# Validar que chrome se descargo
ls -la /home/deploy/.cache/puppeteer/chrome/linux_amd64-*/chrome-linux64/chrome
```

El renderer detecta automaticamente esta ubicacion via `$HOME/.cache/puppeteer`.

#### Via B — Chromium del sistema (alternativa)

```bash
sudo apt-get install -y chromium-browser
# Validar
/usr/bin/chromium-browser --version
```

El renderer tambien detecta `/usr/bin/chromium-browser` como ultimo fallback.

#### Via C — Usar cache global compartido www-data + deploy

```bash
# Ubicacion global que ambos usuarios pueden leer
sudo mkdir -p /var/www/.cache/puppeteer
sudo chown -R deploy:www-data /var/www/.cache
sudo chmod -R u+rwX,g+rwX,o+rX /var/www/.cache

# Como usuario deploy
PUPPETEER_CACHE_DIR=/var/www/.cache/puppeteer node /var/www/rifax/node_modules/puppeteer/install.mjs
```

### 16.4 Configuracion opcional (override manual)

Si la deteccion automatica falla, se pueden declarar explicitamente las rutas en `config/services.php` o via `.env`:

**`config/services.php`** (solo si hace falta):

```php
'browsershot' => [
    'node_binary'  => env('BROWSERSHOT_NODE_BINARY'),
    'npm_binary'   => env('BROWSERSHOT_NPM_BINARY'),
    'chrome_path'  => env('BROWSERSHOT_CHROME_PATH'),
],
```

**`.env`** (ejemplo para Hetzner):

```
BROWSERSHOT_NODE_BINARY=/usr/bin/node
BROWSERSHOT_NPM_BINARY=/usr/bin/npm
BROWSERSHOT_CHROME_PATH=/home/deploy/.cache/puppeteer/chrome/linux_amd64-152.0.7977.42/chrome-linux64/chrome
```

Despues de cambiar `.env` o `config/services.php`:

```bash
cd /var/www/rifax
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

### 16.5 Validacion post-instalacion

```bash
cd /var/www/rifax

# Probar renderer desde tinker
php artisan tinker --execute="
    \$ticket = App\Models\Ticket::query()->latest('id')->first();
    if (!\$ticket) { echo 'No hay tickets'; exit(1); }
    (new App\Actions\Tickets\RegenerateTicketAssetsAction())->execute(\$ticket);
    echo 'image_path:     ' . \$ticket->fresh()->image_path . PHP_EOL;
    echo 'thumbnail_path: ' . \$ticket->fresh()->thumbnail_path . PHP_EOL;
"

# Mirar logs y confirmar renderer = browsershot-png (no legacy-svg)
tail -30 storage/logs/laravel.log | grep renderer

# Validar que los PNG tienen tamano > 1KB (no archivos vacios)
ls -lah public/storage/tickets/*/ticket-v*.png | tail -5

# HTTP smoke test al ultimo ticket generado
curl -I $(php artisan tinker --execute="echo asset('storage/'.App\Models\Ticket::query()->latest('id')->first()?->image_path);")
```

Si `renderer = browsershot-png` y el `.png` pesa >50KB, el renderer premium esta operativo.

### 16.6 Backfill / regenerar boletos antiguos (opcional)

Por defecto, los boletos emitidos **antes** de instalar Chromium siguen en SVG (su URL no cambia, no hay links rotos).

Si quieres regenerar TODOS los boletos historicos con el nuevo diseño PNG:

```bash
cd /var/www/rifax

# Opcion A: comando one-liner (puede tardar varios minutos)
php artisan tinker --execute="
    App\Models\Ticket::query()
        ->whereNotNull('id')
        ->orderBy('id')
        ->chunk(100, function (\$chunk) {
            foreach (\$chunk as \$t) {
                try {
                    (new App\Actions\Tickets\RegenerateTicketAssetsAction())->execute(\$t);
                    echo \"OK ticket_id={$t->id} code={$t->code}\n\";
                } catch (\Throwable \$e) {
                    echo \"FAIL ticket_id={$t->id}: {\$e->getMessage()}\n\";
                }
            }
        });
"
```

Ejecuta primero con 1 ticket de prueba antes de lanzar el backfill completo.

### 16.7 Que pasa si Chromium no se instala

Nada se rompe. El `RenderTicketAssetsAction` envuelve la llamada a Browsershot en `try/catch Throwable` y, ante CUALQUIER error, registra un `Log::warning('Ticket browsershot render failed, falling back to SVG.')` y produce el boleto SVG legado. Todos los flujos siguen funcionando; solo no tendran el estilo premium PNG hasta que se instale Chromium.
