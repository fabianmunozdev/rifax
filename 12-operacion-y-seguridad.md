# Operacion y Seguridad

## Operacion
- Usar colas para generacion de boletos, envio de campanas, limpieza de reservas y tareas pesadas.
- Programar jobs para expirar reservas, reintentos y metricas.
- Registrar logs estructurados por contexto: rifa, compra, pago, cliente y mensaje.
- Definir alertas para fallos de webhook, jobs y generacion de boletos.

## Desarrollo Local
- Docker Compose es recomendado para estandarizar entorno local de Laravel, PostgreSQL y Redis.
- El uso de Docker no es obligatorio para el dominio del negocio, pero si altamente recomendable para reducir diferencias entre equipos y entornos.
- Si existe tooling Node para assets o utilidades del panel, se debe usar `pnpm` y evitar `npm`.
- Los ejemplos de instalacion, scripts y documentacion interna deben usar comandos basados en `pnpm`.

## Seguridad
- Validar firma de WhatsApp en cada webhook.
- Proteger endpoints administrativos con Sanctum y politicas por rol.
- Limitar acceso a verificaciones y recursos publicos con rate limiting.
- Guardar secretos y tokens en variables de entorno seguras.
- Aplicar auditoria a aprobacion de pagos, cambios de numeros y ediciones criticas.

## Trazabilidad
Como minimo debe quedar registro de:
- Quien aprobo o rechazo un pago.
- Cuando se genero un boleto.
- Que mensaje disparo una accion automatica importante.
- Que campana se envio, a quien y con que resultado.

## Backups y Retencion
- Definir backup regular de base de datos y archivos.
- Definir politica de retencion para comprobantes, mensajes y logs.
- Definir politica de restauracion y pruebas de recuperacion.
