# Modelo de Datos

## Objetivo
Definir una base de datos relacional normalizada, auditable y preparada para consultas operativas frecuentes.

## Tablas Principales
- `users`: usuarios administrativos del sistema.
- `customers`: compradores identificados por telefono de WhatsApp y datos basicos opcionales.
- `company_settings`: configuracion general de la empresa y branding.
- `payment_methods`: metodos de pago e instrucciones visibles al comprador.
- `raffles`: rifas.
- `raffle_numbers`: numeros disponibles por rifa.
- `reservations`: reservas temporales.
- `purchases`: compras.
- `purchase_numbers`: relacion entre compra y numeros asignados.
- `payments`: registros de pago.
- `payment_proofs`: comprobantes o evidencia asociada.
- `tickets`: boletos generados.
- `ticket_verifications`: historial de verificaciones publicas o internas.
- `whatsapp_messages`: mensajes entrantes y salientes.
- `conversation_states`: estado actual de conversacion por cliente.
- `content_entries`: catalogo administrable de FAQ y textos fijos.
- `campaigns`: definicion de campanas.
- `campaign_runs`: ejecuciones de campanas.
- `campaign_recipients`: destinatarios y resultado por envio.
- `audit_logs`: acciones administrativas criticas.

## Relaciones Principales
- `raffles` -> `raffle_numbers`
- `customers` -> `purchases`
- `purchases` -> `purchase_numbers`
- `purchases` -> `payments`
- `purchases` -> `tickets`
- `payments` -> `payment_proofs`
- `customers` -> `whatsapp_messages`
- `customers` -> `conversation_states`
- `campaigns` -> `campaign_runs`
- `campaign_runs` -> `campaign_recipients`

## Reglas de Modelado
- Todo numero debe ser unico dentro de una rifa.
- `raffle_numbers` debe tener indice unico por `raffle_id + number`.
- `customers.phone` debe ser unico en formato internacional normalizado.
- `customers` no requiere credenciales de acceso en el MVP.
- `purchases` debe conservar snapshots de precio, nombre de rifa y moneda si se requiere historial consistente.
- `payments` debe guardar estado, monto esperado, monto recibido, referencia y timestamps de revision.
- `payment_proofs` debe almacenar evidencia recibida por WhatsApp.
- `tickets` debe guardar codigo unico, URL publica, version y hash o token de verificacion.

## Indices Sugeridos
- `raffle_numbers(raffle_id, status)`
- `reservations(expires_at, status)`
- `purchases(customer_id, status)`
- `payments(status, reviewed_at)`
- `whatsapp_messages(customer_id, created_at)`
- `campaign_recipients(campaign_run_id, status)`
- `audit_logs(entity_type, entity_id, created_at)`

## Consideraciones de Consistencia
- La reserva y asignacion de numeros deben ejecutarse dentro de transacciones seguras.
- Deben evitarse dobles asignaciones mediante constraints y bloqueo adecuado.
- Los jobs de expiracion deben ser idempotentes.

## Campos Importantes por Tabla
### `raffles`
- `title`
- `slug`
- `description`
- `status`
- `min_numbers_per_purchase`
- `lottery_name`
- `lottery_draw_number`
- `draw_date`
- `draw_time`
- `lottery_reference_url`
- `price_per_number`
- `reservation_timeout_minutes`
- `cover_image_path`

El campo `min_numbers_per_purchase` debe iniciar con valor por defecto `1`.

### `raffle_numbers`
- `raffle_id`
- `number`
- `status`
- `reserved_until`

### `customers`
- `phone`
- `name`
- `wa_id`
- `last_interaction_at`

### `purchases`
- `customer_id`
- `raffle_id`
- `status`
- `total_amount`
- `currency`
- `payment_instructions_snapshot`

### `payments`
- `purchase_id`
- `status`
- `reference`
- `proof_received_at`
- `proof_channel`
- `reviewed_by`
- `reviewed_at`
- `rejection_reason`

### `conversation_states`
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

### `content_entries`
- `type`
- `key`
- `title`
- `category`
- `locale`
- `channel`
- `status`
- `trigger_intent`
- `body_text`
- `variables_json`
- `fallback_text`
- `priority`
- `is_ai_eligible`
- `notes`
- `created_by`
- `updated_by`
- `published_at`

### `tickets`
- `purchase_id`
- `code`
- `verification_token`
- `public_url`
- `image_path`
- `thumbnail_path`
- `generated_at`

## Pendientes a Definir
- Soporte de multiples monedas.
- Entidad final para registrar numero ganador y cierre de rifa.
- Politica exacta de borrado o retencion de mensajes y comprobantes.

Ver detalle operativo de esta tabla en [19-conversation-states.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/19-conversation-states.md).
Ver detalle operativo del catalogo editable en [20-catalogo-faq-y-textos.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/20-catalogo-faq-y-textos.md).
Ver plan de migraciones reales en [22-migraciones-laravel.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/22-migraciones-laravel.md).
