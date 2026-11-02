# Rifax

Plataforma para vender rifas por WhatsApp con backend en Laravel, panel administrativo en Filament, PostgreSQL, Redis y generacion automatica de boletos.

## Stack Base
- `Laravel 12`
- `Filament 5`
- `PostgreSQL`
- `Redis`
- `Docker Compose` via `Laravel Sail`
- `pnpm` para tooling Node y build de assets

## Primer Arranque
```bash
composer install
pnpm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

## URLs Locales
- App: [http://localhost:8080](http://localhost:8080)
- Admin: [http://localhost:8080/admin/login](http://localhost:8080/admin/login)
- Mailpit: [http://localhost:8026](http://localhost:8026)

## Credenciales Iniciales
- Email: `admin@rifax.test`
- Password: `password`

## Comandos Utiles
```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
pnpm run build
```

## Puertos Locales
- `8080` app web
- `5433` PostgreSQL expuesto
- `6380` Redis expuesto
- `1026` SMTP Mailpit
- `8026` dashboard Mailpit

## Producción

Producción corre con Docker en https://megaeliteoficial.com. Ver
[documentation/despliegue-docker.md](documentation/despliegue-docker.md).

```bash
scripts/deploy.sh   # en el servidor, dentro de /opt/rifax
```

## Documentación del proyecto
- Visión: [00-vision.md](00-vision.md)
- Arquitectura: [01-arquitectura.md](01-arquitectura.md)
- Reglas de negocio: [02-reglas-negocio.md](02-reglas-negocio.md)
- Plan de implementación: [25-plan-implementacion.md](25-plan-implementacion.md)
- Estructura del bot de WhatsApp: [documentation/estructura-bot-whatsapp.md](documentation/estructura-bot-whatsapp.md)
- Despliegue con Docker: [documentation/despliegue-docker.md](documentation/despliegue-docker.md)
- Respaldos: [documentation/respaldos-base-de-datos.md](documentation/respaldos-base-de-datos.md)
