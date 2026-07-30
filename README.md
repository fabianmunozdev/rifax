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

## Documentacion del Proyecto
- Vision: [00-vision.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/00-vision.md)
- Arquitectura: [01-arquitectura.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/01-arquitectura.md)
- Plan de implementacion: [25-plan-implementacion.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/25-plan-implementacion.md)
- PRD formal: [.trae/documents/prd-rifax-mvp.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/.trae/documents/prd-rifax-mvp.md)
- Arquitectura formal: [.trae/documents/arquitectura-tecnica-rifax-mvp.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/.trae/documents/arquitectura-tecnica-rifax-mvp.md)

## Siguiente Fase Tecnica
- Implementar migraciones del dominio base.
- Crear recursos Filament de rifas, pagos, compras, contenido y conversaciones.
- Sustituir tablas default por el modelo del negocio documentado.
