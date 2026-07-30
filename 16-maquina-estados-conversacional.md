# Maquina de Estados Conversacional

## Objetivo
Definir el estado exacto de cada conversacion de compra para que el flujo sea implementable, auditable y no dependa de IA.

## Principios
- Un solo estado activo por cliente y por conversacion.
- El flujo transaccional siempre tiene prioridad sobre respuestas abiertas.
- `MENU` reinicia al estado de menu principal.
- `CANCELAR` cierra el flujo en curso y libera reservas si corresponde.
- Si el usuario envia un mensaje incompatible con el estado actual, el sistema intenta reconducirlo sin perder contexto.

## Estados Principales
### `main_menu`
Estado inicial o de retorno.

Entradas validas:
- `1` o intencion equivalente de comprar.
- `2` disponibles.
- `3` mis numeros.
- `4` estadisticas.
- `5` proximas rifas.
- `6` condiciones.
- `7` ayuda.

Salidas:
- A `purchase_select_raffle`.
- A flujos informativos no transaccionales.

### `purchase_select_raffle`
El sistema presenta la rifa activa o lista de rifas disponibles.

Entradas validas:
- Confirmacion de continuar.
- Seleccion de una rifa valida.
- `MENU`.
- `CANCELAR`.

Salidas:
- A `purchase_enter_quantity`.
- A `main_menu`.

### `purchase_enter_quantity`
El sistema espera la cantidad deseada de numeros.

Validaciones:
- Debe ser entero positivo.
- Debe cumplir `min_numbers_per_purchase`.

Entradas validas:
- Un numero entero valido.
- `MENU`.
- `CANCELAR`.

Salidas:
- A `purchase_choose_mode`.
- Permanece en el mismo estado si la cantidad es invalida.

### `purchase_choose_mode`
El sistema espera que el usuario elija entre seleccion manual o aleatoria.

Entradas validas:
- `1` elegir manualmente.
- `2` asignacion aleatoria.
- `MENU`.
- `CANCELAR`.

Salidas:
- A `purchase_select_numbers`.
- A `purchase_random_assignment`.

### `purchase_select_numbers`
El sistema espera lista de numeros o seleccion incremental.

Validaciones:
- La cantidad de numeros debe coincidir con la solicitada.
- Los numeros deben pertenecer a la rifa.
- Los numeros deben estar disponibles.

Entradas validas:
- Lista de numeros valida.
- `CAMBIAR`.
- `MENU`.
- `CANCELAR`.

Salidas:
- A `purchase_reservation_pending`.
- Permanece en el mismo estado si hay numeros invalidos o no disponibles.

### `purchase_random_assignment`
El sistema asigna numeros aleatorios disponibles.

Entradas validas:
- Confirmacion de aceptar.
- Solicitud de volver a generar si la politica lo permite.
- `MENU`.
- `CANCELAR`.

Salidas:
- A `purchase_reservation_pending`.
- A `purchase_choose_mode` si se rechaza la propuesta.

### `purchase_reservation_pending`
Estado tecnico corto mientras el backend intenta reservar numeros.

Eventos esperados:
- `numbers_reserved`
- error de disponibilidad

Salidas:
- A `purchase_payment_instructions` si la reserva se logra.
- A `purchase_select_numbers` o `purchase_random_assignment` si falla.

### `purchase_payment_instructions`
El usuario ya tiene reserva activa y recibe monto, numeros e instrucciones de pago.

Entradas validas:
- Comprobante de pago por WhatsApp.
- Consulta breve sobre el proceso.
- `MENU`.
- `CANCELAR`.

Reglas:
- Si llega un comprobante valido, pasar a revision.
- Si vence la reserva antes del comprobante, pasar a expirado.

Salidas:
- A `purchase_proof_received`.
- A `purchase_expired`.

### `purchase_proof_received`
El sistema confirma recepcion de comprobante y deja la compra en revision.

Entradas validas:
- Consultas sobre estado.
- `MENU`.

Salidas:
- A `purchase_under_review`.

### `purchase_under_review`
Estado pasivo mientras un administrador revisa el pago.

Entradas validas:
- Consultas sobre estado.
- `MENU`.

Eventos esperados:
- `payment_approved`
- `payment_rejected`

Salidas:
- A `purchase_paid`.
- A `purchase_rejected`.

### `purchase_paid`
Estado final exitoso.

Acciones:
- Enviar boleto.
- Permitir consulta posterior desde `Mis numeros`.

Salidas:
- A `main_menu` o cierre conversacional.

### `purchase_rejected`
El pago fue rechazado.

Entradas validas:
- Nuevo comprobante.
- Consulta del motivo.
- `MENU`.
- `CANCELAR`.

Salidas:
- A `purchase_proof_received` si se recibe nuevo comprobante.
- A `main_menu`.

### `purchase_expired`
La reserva vencio sin comprobante valido a tiempo.

Entradas validas:
- Reiniciar compra.
- `MENU`.

Salidas:
- A `purchase_select_raffle`.
- A `main_menu`.

## Estados Informativos
### `info_available_numbers`
- Muestra disponibilidad resumida.
- Puede saltar a `purchase_select_raffle`.

### `info_my_numbers`
- Muestra compras y boletos del telefono actual.
- No cambia estados de compra.

### `info_statistics`
- Muestra vendidos, disponibles y datos del sorteo.

### `info_upcoming_raffles`
- Muestra rifas futuras o recordatorios.

### `info_conditions`
- Muestra condiciones y reglas.

### `info_help`
- Ofrece FAQ fija y soporte humano.

## Eventos Globales
- `MENU` -> `main_menu`
- `CANCELAR` -> cancelar flujo actual y limpiar contexto
- mensaje no compatible -> fallback contextual
- mensaje duplicado de webhook -> ignorar o responder idempotentemente

## Prioridad de Enrutamiento
1. Comandos globales.
2. Estado conversacional actual.
3. Intencion transaccional detectada por reglas fijas.
4. FAQ fija.
5. IA acotada.
6. Soporte humano.

## Persistencia Minima
Por cada cliente debe guardarse:
- `status`
- `current_raffle_id`
- `requested_quantity`
- `selection_mode`
- `selected_numbers`
- `reservation_id`
- `purchase_id`
- `last_message_at`
- `context_expires_at`

La estructura persistida recomendada se detalla en [19-conversation-states.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/19-conversation-states.md).
