# Prompt Backend

## Objetivo
Implementar el backend en Laravel 12 para el dominio de rifas por WhatsApp, manteniendo separacion clara entre flujo transaccional, integraciones externas y tareas asincronas.

## Debe Incluir
- Casos de uso o acciones por flujo critico.
- Validaciones robustas.
- Servicios para reservas, compras, pagos y boletos.
- Jobs, events y listeners cuando aporten valor.
- Policies y control de permisos.
- Auditoria para acciones administrativas criticas.
- API JSON consistente.
- Persistencia y transiciones de `conversation_states`.
- Resolucion de FAQ y textos fijos desde catalogo administrable.

## Restricciones
- No introducir multitenancy.
- La empresa es unica, pero existe configuracion editable de branding y datos operativos.
- La IA no decide estados de compra ni pago.
- Priorizar simplicidad y mantenibilidad sobre patrones innecesarios.
- Si el backend requiere tooling Node para assets o build, usar `pnpm` como package manager.

## Flujos Criticos
- Webhook de WhatsApp.
- Reserva de numeros.
- Envio y revision de comprobantes.
- Aprobacion y rechazo de pagos.
- Generacion y entrega de boletos.
- Ejecucion de campanas.
- Reconstruccion segura del estado conversacional por cliente.

## Criterios de Aceptacion
- Acciones idempotentes donde aplique.
- Validaciones de negocio expresas.
- Cobertura de logs y errores util.
- Pruebas de integracion para flujos principales.
- Estructura de tests alineada con [24-tests-laravel.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/24-tests-laravel.md).
