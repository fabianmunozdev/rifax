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
