# Acceso al servidor y cambios en `.env`

## Datos actuales

- Servidor: `95.217.177.163`
- Usuario SSH: `deploy`
- Ruta del proyecto: `/var/www/rifax`
- Dominio productivo: `https://rifax.fabianmunoz.dev`

## 1. Ingresar al servidor

Desde tu Mac:

```bash
ssh deploy@95.217.177.163
```

Si la llave SSH local ya esta configurada, no deberia pedirte contrasena del servidor.

## 2. Entrar al proyecto

```bash
cd /var/www/rifax
```

## 3. Editar el archivo `.env`

Con `nano`:

```bash
nano .env
```

Con `vim`:

```bash
vim .env
```

## 4. Variables tipicas a actualizar

Algunas variables que normalmente se cambian en produccion:

```env
APP_URL=https://rifax.fabianmunoz.dev

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rifax
DB_USERNAME=rifax
DB_PASSWORD=...

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

MAIL_MAILER=...
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...

WHATSAPP_WEBHOOK_VERIFY_TOKEN=...
WHATSAPP_WEBHOOK_APP_SECRET=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_SEND_ENABLED=true
```

## 5. Aplicar cambios de configuracion

Despues de guardar el `.env`, ejecutar:

```bash
php artisan optimize:clear
php artisan optimize
```

## 6. Reiniciar servicios relacionados

Si cambias variables de aplicacion, Redis, colas, correo o WhatsApp:

```bash
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

Si cambias configuracion de `Nginx`:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 7. Flujo recomendado completo

```bash
ssh deploy@95.217.177.163
cd /var/www/rifax
nano .env
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

## 8. Verificaciones rapidas

Comprobar que la app responde:

```bash
curl -I https://rifax.fabianmunoz.dev
```

Comprobar worker:

```bash
sudo supervisorctl status rifax-worker
```

Comprobar scheduler del usuario `deploy`:

```bash
crontab -l
```

## 9. Notas operativas

- No editar `.env` como `root` salvo que sea estrictamente necesario.
- Antes de activar `WHATSAPP_SEND_ENABLED=true`, confirmar que las credenciales reales ya estan cargadas.
- Si cambias base de datos, cache o sesiones, conviene validar inmediatamente el acceso al panel admin.
