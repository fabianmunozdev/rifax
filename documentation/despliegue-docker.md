# Despliegue con Docker — megaeliteoficial.com

Rifax corre en producción con Docker Compose (`compose.prod.yaml`):

| Contenedor | Qué hace |
|---|---|
| `web` | Caddy: HTTPS automático (Let's Encrypt), sirve archivos públicos y pasa PHP a `app` |
| `app` | PHP-FPM 8.5 con Laravel, Chromium y Node (boletos PNG) |
| `worker` | Cola: envíos de WhatsApp, boletos, aprobación de pagos |
| `scheduler` | Tareas programadas: vencimiento de reservas, recordatorios |
| `postgres` | Base de datos (volumen `pgdata`) |
| `redis` | Cola y caché (volumen `redisdata`) |

Los archivos subidos (comprobantes, boletos, logos) viven en el volumen `storage`.

> El dominio `megaeliteoficial.com` hoy apunta a `95.217.177.163`, el servidor donde corre la
> versión de pruebas instalada directamente (Nginx + PHP-FPM + Supervisor). La versión de pruebas
> se reemplaza: no se migran sus datos.

---

## 1. Preparar el servidor (una sola vez)

Ubuntu 24.04, como usuario con `sudo`.

### 1.1 Apagar la instalación anterior

Docker necesita los puertos 80 y 443, que hoy usa Nginx.

```bash
sudo systemctl disable --now nginx php8.5-fpm supervisor || true
# Si PostgreSQL/Redis del sistema solo los usaba la versión de pruebas:
sudo systemctl disable --now postgresql redis-server || true
```

Para conservar una copia de la versión de pruebas por si acaso:

```bash
sudo -u postgres pg_dump -Fc rifax > ~/rifax-pruebas-$(date +%F).dump
sudo tar -czf ~/rifax-pruebas-storage-$(date +%F).tar.gz -C /var/www/rifax storage/app
```

### 1.2 Instalar Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy   # luego cerrar sesión y volver a entrar
sudo mkdir -p /var/backups/rifax && sudo chown deploy:deploy /var/backups/rifax   # respaldos de deploy.sh
docker compose version
```

### 1.3 Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp
sudo ufw enable
```

### 1.4 Descargar el código

El repositorio es privado. Crear una *deploy key* de solo lectura:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/rifax_deploy -N "" -C "rifax-deploy"
cat ~/.ssh/rifax_deploy.pub
```

Pegarla en GitHub → `fabianmunozdev/rifax` → Settings → Deploy keys (sin permiso de escritura) y luego:

```bash
cat >> ~/.ssh/config <<'CFG'
Host github-rifax
  HostName github.com
  IdentityFile ~/.ssh/rifax_deploy
CFG

sudo mkdir -p /opt/rifax && sudo chown deploy:deploy /opt/rifax
git clone git@github-rifax:fabianmunozdev/rifax.git /opt/rifax
cd /opt/rifax
git checkout main
```

### 1.5 Configurar `.env`

```bash
cp .env.production.example .env
chmod 600 .env
nano .env
```

Completar todos los valores `<...>`:

- `DB_PASSWORD`: `openssl rand -base64 32 | tr -d '/+=' | cut -c1-32`
- `APP_KEY`: se genera en el paso 2.1
- `TWILIO_*`: datos de la consola de Twilio
- `MAIL_*`: SMTP para correos

### 1.6 DNS

`megaeliteoficial.com` y `www.megaeliteoficial.com` deben tener un registro **A** hacia la IP del
servidor. Hoy ya apuntan a `95.217.177.163`. Si el servidor cambia, actualizarlos **antes** del
primer arranque: Caddy necesita el DNS correcto para emitir el certificado HTTPS.

---

## 2. Primer arranque

```bash
cd /opt/rifax

# 2.1 Clave de la aplicación → copiarla a APP_KEY en .env
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml run --rm --no-deps --entrypoint php app artisan key:generate --show

# 2.2 Desplegar (construye, migra y levanta todo)
scripts/deploy.sh

# 2.3 Crear el administrador del panel (pide la contraseña sin mostrarla)
docker compose -f compose.prod.yaml exec app php artisan rifax:create-admin
```

**No ejecutar `db:seed` en producción**: crea usuarios de prueba con contraseña `password`.

Comprobar:

- https://megaeliteoficial.com → landing
- https://megaeliteoficial.com/admin → panel
- `docker compose -f compose.prod.yaml ps` → todos `Up` / `healthy`

### 2.4 Conectar Twilio

En la consola de Twilio, en el número o sender de WhatsApp:

- **When a message comes in**: `https://megaeliteoficial.com/api/webhooks/twilio/whatsapp` (POST)
- El *status callback* lo configura la aplicación sola en cada envío.

Escribir al número desde un celular: el bot debe responder con el menú.

### 2.5 Configuración inicial en el panel

Configuración de Empresa, Métodos de Pago, Entradas de Contenido y la primera rifa.

---

## 3. Respaldos

```bash
sudo mkdir -p /var/backups/rifax && sudo chown deploy:deploy /var/backups/rifax
scripts/docker-backup.sh        # prueba manual
crontab -e
```

```cron
15 3 * * * /opt/rifax/scripts/docker-backup.sh >> /var/backups/rifax/backup.log 2>&1
```

Respalda la base de datos (`.dump`) y los archivos (`-storage.tar.gz`), verifica ambos y guarda
14 días. Para copiar fuera del servidor (muy recomendado), configurar `rclone` y añadir
`RCLONE_REMOTE=storagebox:rifax` al inicio de la línea del cron.

### Restaurar

```bash
docker compose -f compose.prod.yaml exec -T postgres \
  sh -c 'dropdb -U "$POSTGRES_USER" --if-exists rifax_restore && createdb -U "$POSTGRES_USER" rifax_restore'
docker compose -f compose.prod.yaml exec -T postgres \
  sh -c 'pg_restore -U "$POSTGRES_USER" -d rifax_restore --no-owner' < /var/backups/rifax/rifax-AAAAMMDD-HHMMSS.dump
```

Revisar `rifax_restore` y solo entonces reemplazar la base real. Archivos:

```bash
docker compose -f compose.prod.yaml exec -T app tar -xzf - -C /var/www/html/storage \
  < /var/backups/rifax/rifax-AAAAMMDD-HHMMSS-storage.tar.gz
```

---

## 4. Desplegar cambios

```bash
cd /opt/rifax
scripts/deploy.sh          # última versión de main
scripts/deploy.sh v1.0.1   # un tag concreto
```

El script hace respaldo → `git pull` → build → migraciones → reemplazo de contenedores →
reinicio de la cola. Si el build falla, la versión anterior sigue funcionando.

### Volver a la versión anterior

```bash
git log --oneline -5
scripts/deploy.sh <commit-anterior>
```

Si la versión nueva traía migraciones, revisar si hay que deshacerlas antes
(`docker compose -f compose.prod.yaml exec app php artisan migrate:rollback --step=1`).

---

## 5. Operación diaria

```bash
cd /opt/rifax
alias dc='docker compose -f compose.prod.yaml'

dc ps                          # estado
dc logs -f --tail=100 web      # accesos HTTP
dc logs -f --tail=100 worker   # cola (WhatsApp, boletos)
dc exec app tail -f storage/logs/laravel-$(date +%F).log
dc exec app php artisan tinker
dc restart worker              # tras cambiar .env: dc up -d (recarga todo)
```

Después de editar `.env` hay que recrear los contenedores para que lo lean:
`docker compose -f compose.prod.yaml up -d`.
