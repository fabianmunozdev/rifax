# Respaldos de la base de datos

El script [`scripts/backup-db.sh`](../scripts/backup-db.sh) crea un respaldo de PostgreSQL,
verifica que se pueda leer y borra los respaldos de más de 14 días.

## Instalación en el servidor (una sola vez)

```bash
sudo mkdir -p /var/backups/rifax
sudo chown deploy:deploy /var/backups/rifax
chmod +x /var/www/rifax/scripts/backup-db.sh

# Probar a mano
/var/www/rifax/scripts/backup-db.sh /var/www/rifax
```

Programarlo todos los días a las 3:15 a. m. (`crontab -e` como `deploy`):

```cron
15 3 * * * /var/www/rifax/scripts/backup-db.sh /var/www/rifax >> /var/log/rifax-backup.log 2>&1
```

## Copia fuera del servidor (recomendado)

Un respaldo guardado solo en el mismo servidor se pierde si el servidor falla.
Con [rclone](https://rclone.org) configurado (por ejemplo, Hetzner Storage Box o Backblaze B2):

```cron
15 3 * * * RCLONE_REMOTE=storagebox:rifax-backups /var/www/rifax/scripts/backup-db.sh /var/www/rifax >> /var/log/rifax-backup.log 2>&1
```

## Variables opcionales

| Variable | Por defecto | Uso |
|---|---|---|
| `BACKUP_DIR` | `/var/backups/rifax` | Carpeta de respaldos locales |
| `RETENTION_DAYS` | `14` | Días que se guardan localmente |
| `RCLONE_REMOTE` | vacío | Destino externo de rclone |

## Restaurar

```bash
createdb rifax_restore
pg_restore --no-owner -d rifax_restore /var/backups/rifax/rifax-AAAAMMDD-HHMMSS.dump
```

Restaurar primero en una base nueva, revisar los datos y solo entonces reemplazar la de producción.
Conviene hacer una restauración de prueba una vez al mes.
