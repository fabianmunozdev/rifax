# Estados y Eventos

## Objetivo
Formalizar el comportamiento del sistema para reducir ambiguedad en implementacion y pruebas.

## Estados de Numero
- `available`: libre para seleccion.
- `reserved`: bloqueado temporalmente por una reserva activa.
- `paid`: asociado a una compra pagada.
- `cancelled`: liberado manualmente o por cancelacion consolidada.
- `winner`: numero ganador al cierre del sorteo.

## Estados de Compra
- `draft`: compra iniciada pero aun no confirmada por el flujo.
- `pending_payment`: esperando pago.
- `payment_submitted`: comprobante recibido, pendiente de revision.
- `paid`: pago aprobado.
- `rejected`: pago rechazado.
- `cancelled`: cancelada por usuario u operador.
- `expired`: reserva vencida o proceso abandonado.

## Estados de Pago
- `pending_review`: esperando revision.
- `approved`: aprobado por administrador.
- `rejected`: rechazado con motivo.

## Eventos de Dominio
- `purchase_created`
- `numbers_reserved`
- `reservation_expired`
- `payment_proof_received`
- `payment_approved`
- `payment_rejected`
- `ticket_generated`
- `ticket_regenerated`
- `campaign_dispatched`
- `message_received`
- `message_sent`

## Efectos Secundarios Esperados
- `payment_approved` -> cambia compra a `paid`, cambia numeros a `paid`, dispara generacion de boleto.
- `payment_rejected` -> informa al comprador, mantiene o redefine siguiente estado segun politica.
- `reservation_expired` -> libera numeros y actualiza compra a `expired` si sigue pendiente.
- `ticket_generated` -> guarda artefactos y notifica al comprador.

## Reglas de Transicion
- Un numero no puede pasar de `available` a `paid` sin pasar por reserva o asignacion controlada.
- Una compra `paid` no puede volver a `pending_payment`.
- Un pago `approved` no puede editarse sin dejar auditoria especial.
