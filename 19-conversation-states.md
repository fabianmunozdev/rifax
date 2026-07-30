# Conversation States

## Objetivo
Definir el esquema real de persistencia del estado conversacional para que el backend pueda implementar la maquina de estados de WhatsApp sin ambiguedad.

## Enfoque
- Una fila activa por cliente y canal.
- Persistencia ligera, orientada al estado actual y al contexto minimo necesario.
- El historial detallado vive en `whatsapp_messages`; `conversation_states` solo mantiene el contexto operativo vigente.
- Debe ser facil de resetear, expirar y reconstruir si el flujo se corta.

## Tabla Propuesta
Nombre: `conversation_states`

## Columnas Recomendadas
- `id`
- `customer_id`
- `channel`
- `status`
- `substatus`
- `current_raffle_id`
- `requested_quantity`
- `selection_mode`
- `selected_numbers_json`
- `reservation_id`
- `purchase_id`
- `payment_id`
- `last_inbound_message_id`
- `last_outbound_message_id`
- `last_user_message_at`
- `last_bot_message_at`
- `context_expires_at`
- `locked_at`
- `locked_by`
- `metadata_json`
- `created_at`
- `updated_at`

## Descripcion de Campos
### Identidad
- `customer_id`: referencia al comprador por telefono.
- `channel`: valor como `whatsapp` para permitir extensiones futuras sin redisenar la tabla.

### Estado Actual
- `status`: estado principal del flujo.
- `substatus`: detalle opcional del paso actual para casos mas finos sin inflar `status`.

Estados sugeridos en `status`:
- `main_menu`
- `purchase_select_raffle`
- `purchase_enter_quantity`
- `purchase_choose_mode`
- `purchase_select_numbers`
- `purchase_random_assignment`
- `purchase_reservation_pending`
- `purchase_payment_instructions`
- `purchase_proof_received`
- `purchase_under_review`
- `purchase_paid`
- `purchase_rejected`
- `purchase_expired`
- `info_available_numbers`
- `info_my_numbers`
- `info_statistics`
- `info_upcoming_raffles`
- `info_conditions`
- `info_help`

### Contexto de Compra
- `current_raffle_id`: rifa en curso.
- `requested_quantity`: cantidad solicitada por el usuario.
- `selection_mode`: `manual` o `random`.
- `selected_numbers_json`: numeros elegidos o propuestos.
- `reservation_id`: reserva activa, si existe.
- `purchase_id`: compra asociada al flujo.
- `payment_id`: pago asociado cuando el comprobante ya fue registrado.

### Trazabilidad de Mensajes
- `last_inbound_message_id`: ultimo mensaje entrante procesado.
- `last_outbound_message_id`: ultimo mensaje saliente enviado.
- `last_user_message_at`: ultimo mensaje del comprador.
- `last_bot_message_at`: ultima respuesta del sistema.

### Control Operativo
- `context_expires_at`: vencimiento del contexto o de la conversacion activa.
- `locked_at`: marca de bloqueo temporal mientras un worker procesa el estado.
- `locked_by`: identificador del proceso o job que tomo el lock.
- `metadata_json`: payload flexible para flags o datos no esenciales.

## Restricciones Recomendadas
- Un indice unico por `customer_id + channel`.
- `status` debe limitarse a un catalogo permitido.
- `selection_mode` debe limitarse a `manual` o `random` cuando no sea nulo.
- `customer_id` debe tener foreign key obligatoria.
- `current_raffle_id`, `reservation_id`, `purchase_id` y `payment_id` deben tener foreign keys segun corresponda.

## Payload de `selected_numbers_json`
Ejemplo:

```json
[
  {"number": "0012", "source": "manual"},
  {"number": "0458", "source": "manual"}
]
```

## Payload de `metadata_json`
Usar solo para datos accesorios que no ameriten columna propia.

Ejemplo:

```json
{
  "invalid_attempts": 1,
  "last_error_code": "number_unavailable",
  "faq_route": "payment_methods",
  "window_state": "open",
  "resume_template_sent": false
}
```

## Reglas de Persistencia
- Cada vez que cambia el `status`, debe actualizarse `updated_at`.
- Si el usuario usa `MENU`, el estado vuelve a `main_menu` y se limpia contexto transaccional no necesario.
- Si el usuario usa `CANCELAR`, deben limpiarse `reservation_id`, `selected_numbers_json`, `requested_quantity` y contexto de compra si no existe pago en revision.
- Si una reserva expira, el estado debe pasar a `purchase_expired`.
- Si un pago es aprobado, el estado debe pasar a `purchase_paid`.
- Si un pago es rechazado, el estado debe pasar a `purchase_rejected`.

## Politica de Limpieza de Contexto
### Reset suave
Usar cuando el usuario vuelve a menu pero conviene conservar trazabilidad ligera.

Limpiar:
- `selected_numbers_json`
- `requested_quantity`
- `selection_mode`
- `reservation_id`

Conservar:
- `current_raffle_id` si ayuda al retorno rapido.
- `purchase_id` si la compra sigue viva.

### Reset duro
Usar cuando se cancela el flujo o cuando hay corrupcion de estado.

Limpiar:
- `current_raffle_id`
- `requested_quantity`
- `selection_mode`
- `selected_numbers_json`
- `reservation_id`
- `purchase_id`
- `payment_id`
- `metadata_json`

Dejar:
- `status = main_menu`

## Locking Recomendado
- Antes de procesar un mensaje entrante, intentar lock optimista o pesimista del registro.
- Si un worker ya esta procesando ese `conversation_state`, evitar doble procesamiento del mismo mensaje.
- Los locks deben tener expiracion defensiva para no dejar la conversacion bloqueada indefinidamente.

## Casos de Uso Tipicos
### Ejemplo 1: usuario entra a comprar
```json
{
  "status": "purchase_enter_quantity",
  "current_raffle_id": 15,
  "requested_quantity": null,
  "selection_mode": null,
  "selected_numbers_json": [],
  "reservation_id": null,
  "purchase_id": null
}
```

### Ejemplo 2: usuario ya tiene reserva activa
```json
{
  "status": "purchase_payment_instructions",
  "current_raffle_id": 15,
  "requested_quantity": 3,
  "selection_mode": "manual",
  "selected_numbers_json": [
    {"number": "0012", "source": "manual"},
    {"number": "0045", "source": "manual"},
    {"number": "0310", "source": "manual"}
  ],
  "reservation_id": 92,
  "purchase_id": 184,
  "context_expires_at": "2026-07-01T19:15:00Z"
}
```

### Ejemplo 3: pago en revision
```json
{
  "status": "purchase_under_review",
  "purchase_id": 184,
  "payment_id": 41,
  "metadata_json": {
    "window_state": "open",
    "last_review_notice_sent": true
  }
}
```

## Criterios de Aceptacion
- El backend puede reconstruir el siguiente paso del flujo solo con `conversation_states` y `whatsapp_messages`.
- El estado actual refleja con precision la etapa de compra del cliente.
- El modelo soporta `MENU`, `CANCELAR`, expiracion y reintentos sin corromper el contexto.
- Existe suficiente informacion para depurar errores de enrutamiento conversacional.
