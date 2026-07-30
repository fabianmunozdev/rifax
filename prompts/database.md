# Prompt Base de Datos

## Objetivo
Disenar PostgreSQL normalizado para soportar rifas, reservas, compras, pagos, boletos, conversaciones y campanas.

## Debe Incluir
- Migraciones Laravel.
- Claves foraneas y restricciones.
- Indices para consultas frecuentes.
- Unicidad por rifa y numero.
- Trazabilidad de pagos y boletos.
- Tablas para configuracion de empresa y branding.
- Tabla `conversation_states` alineada con la maquina de estados conversacional.
- Orden realista de migraciones y `check constraints` compatibles con PostgreSQL.

## Restricciones
- No modelar multiempresa real en esta fase.
- Preparar diseno limpio que pueda evolucionar en el futuro.
- Asegurar consistencia en reservas y asignacion de numeros.

## Validaciones Esperadas
- Evitar doble reserva.
- Evitar doble aprobacion de pago.
- Permitir historial consistente de compra y ticket.
- Permitir reconstruir el estado conversacional vigente por cliente.

Usar como referencia de detalle [22-migraciones-laravel.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/22-migraciones-laravel.md).
