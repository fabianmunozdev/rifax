# Recursos Filament

## Objetivo
Definir la estructura de recursos Filament para operar Rifax con foco en productividad, trazabilidad y seguridad operativa.

## Principios
- El panel debe acelerar operacion diaria, no exponer toda la complejidad tecnica.
- Los recursos deben reflejar el dominio real: compras, pagos, conversaciones, FAQ y configuracion.
- Las acciones de alto riesgo deben estar encapsuladas y auditadas.
- El admin puede editar contenido y parametros, pero no romper reglas de negocio criticas.

## Navegacion Recomendada
### Grupo `Operacion`
- `RaffleResource`
- `PurchaseResource`
- `PaymentResource`
- `TicketResource`
- `CustomerResource`
- `ConversationResource`

### Grupo `Contenido`
- `ContentEntryResource`
- `CampaignResource`

### Grupo `Configuracion`
- `CompanySettingsPage`
- `PaymentMethodResource`

### Grupo `Control`
- `AuditLogResource`
- `Dashboard`

## Recurso `RaffleResource`
### Objetivo
Crear y operar rifas con sus parametros de venta y sorteo.

### Tabla
- `title`
- `status`
- `price_per_number`
- `min_numbers_per_purchase`
- `draw_date`
- `draw_time`
- `lottery_name`
- `numbers_sold_count`
- `numbers_available_count`

### Filtros
- estado
- fecha de sorteo
- rifa activa/inactiva

### Formulario
#### Seccion `General`
- `title`
- `slug`
- `description`
- `status`

#### Seccion `Venta`
- `price_per_number`
- `min_numbers_per_purchase`
- `reservation_timeout_minutes`

#### Seccion `Sorteo`
- `lottery_name`
- `lottery_draw_number`
- `draw_date`
- `draw_time`
- `lottery_reference_url`

#### Seccion `Media`
- `cover_image_path`

### Acciones
- crear rifa
- editar rifa
- cambiar estado
- generar numeros en lote
- ver disponibilidad

### Guardrails
- no permitir borrar rifas con compras asociadas
- validar `min_numbers_per_purchase >= 1`
- auditar cambios de estado

## Recurso `PurchaseResource`
### Objetivo
Dar visibilidad al ciclo de compra sin volver editable la logica sensible.

### Tabla
- `id`
- `customer.phone`
- `raffle.title`
- `status`
- `total_amount`
- `numbers_count`
- `created_at`

### Filtros
- estado
- rifa
- fecha
- cliente

### Vista Detalle
- resumen de compra
- numeros asociados
- reserva asociada
- pago asociado
- boleto asociado
- historial de mensajes relacionados si aplica

### Acciones
- ver detalle
- abrir pago asociado
- abrir cliente
- forzar liberacion de reserva en casos excepcionales

### Guardrails
- no permitir editar manualmente `status` desde formulario generico
- cualquier accion excepcional debe registrar motivo y usuario

## Recurso `PaymentResource`
### Objetivo
Resolver aprobacion o rechazo de pagos con el menor numero de clics y maxima trazabilidad.

### Tabla
- `id`
- `purchase_id`
- `customer.phone`
- `status`
- `expected_amount`
- `received_amount`
- `proof_received_at`
- `reviewed_at`

### Filtros
- estado
- fecha de comprobante
- revisor

### Vista Detalle
- comprobante recibido
- compra asociada
- numeros
- monto esperado vs recibido
- notas de revision

### Acciones Primarias
- `ApprovePaymentAction`
- `RejectPaymentAction`

### Modal `ApprovePaymentAction`
- confirmacion
- resumen de compra
- vista del comprobante
- campo opcional de nota interna

### Modal `RejectPaymentAction`
- motivo obligatorio
- opcion de texto sugerido para respuesta al cliente

### Guardrails
- no permitir doble aprobacion
- bloquear edicion libre del estado
- auditar aprobacion y rechazo

## Recurso `TicketResource`
### Objetivo
Consultar y verificar boletos generados.

### Tabla
- `code`
- `purchase_id`
- `customer.phone`
- `raffle.title`
- `generated_at`
- `version`

### Acciones
- ver boleto
- abrir URL publica
- regenerar con trazabilidad si la politica lo permite

### Guardrails
- no regenerar sin motivo
- mantener historial o version

## Recurso `CustomerResource`
### Objetivo
Centralizar clientes identificados por telefono.

### Tabla
- `phone`
- `name`
- `wa_id`
- `last_interaction_at`
- `purchases_count`
- `pending_purchases_count`

### Vista Detalle
- datos basicos
- historial de compras
- historial de mensajes
- estado conversacional actual

### Acciones
- abrir conversacion
- abrir compras
- marcar seguimiento

## Recurso `ConversationResource`
### Objetivo
Dar visibilidad operativa al estado conversacional y permitir seguimiento humano sin editar indebidamente la maquina de estados.

### Fuente de Datos
Base principal en `conversation_states`, con relacion a `customers` y `whatsapp_messages`.

### Tabla
- `customer.phone`
- `status`
- `substatus`
- `current_raffle.title`
- `purchase_id`
- `payment_id`
- `last_user_message_at`
- `last_bot_message_at`
- `context_expires_at`

### Filtros
- estado conversacional
- rifa actual
- con compra activa
- con pago en revision
- vencidas

### Vista Detalle
#### Widget `Estado Actual`
- `status`
- `substatus`
- `requested_quantity`
- `selection_mode`
- `selected_numbers_json`
- `context_expires_at`

#### Widget `Contexto Relacionado`
- cliente
- compra
- pago
- reserva

#### Widget `Historial`
- timeline de `whatsapp_messages`

### Acciones
- marcar seguimiento humano
- agregar nota interna
- reset suave
- reset duro solo para roles autorizados

### Guardrails
- no permitir edicion libre de `status` en formulario abierto
- `reset duro` requiere confirmacion y motivo
- cualquier accion operativa debe registrarse en auditoria

## Recurso `ContentEntryResource`
### Objetivo
Operar FAQ y textos fijos sin tocar codigo.

### Tabla
- `key`
- `title`
- `type`
- `category`
- `status`
- `channel`
- `trigger_intent`
- `priority`
- `is_ai_eligible`
- `published_at`

### Filtros
- tipo
- categoria
- estado
- canal
- escalable a IA

### Formulario
#### Seccion `Identidad`
- `key`
- `title`
- `type`
- `category`
- `locale`
- `channel`

#### Seccion `Resolucion`
- `trigger_intent`
- `priority`
- `is_ai_eligible`

#### Seccion `Contenido`
- `body_text`
- `variables_json`
- `fallback_text`
- `notes`

#### Seccion `Publicacion`
- `status`
- `published_at`

### Acciones
- crear
- editar
- duplicar
- publicar
- archivar
- previsualizar texto resuelto

### Guardrails
- validar unicidad de `key + locale + channel`
- validar variables permitidas
- `is_ai_eligible` debe iniciar desactivado
- no permitir publicar si faltan variables invalidas o texto vacio

## Recurso `CampaignResource`
### Objetivo
Configurar campañas y revisar ejecuciones.

### Tabla
- `name`
- `status`
- `trigger_type`
- `audience_type`
- `last_run_at`

### Acciones
- crear
- activar o pausar
- ver ejecuciones

## Recurso `PaymentMethodResource`
### Objetivo
Administrar metodos de pago visibles al comprador.

### Tabla
- `name`
- `status`
- `sort_order`
- `updated_at`

### Formulario
- nombre
- instrucciones
- datos visibles
- estado
- orden

## Pagina `CompanySettingsPage`
### Objetivo
Gestionar empresa y branding como singleton.

### Secciones
- nombre comercial
- logo
- colores
- datos legales
- textos generales de ayuda
- parametros globales

### Guardrails
- acceso restringido a roles altos
- cambios importantes auditados

## Recurso `AuditLogResource`
### Objetivo
Consultar trazabilidad de acciones sensibles.

### Tabla
- `user`
- `action`
- `entity_type`
- `entity_id`
- `created_at`

### Guardrails
- solo lectura
- filtros por usuario, entidad y fecha

## Widgets Recomendados
### Dashboard
- ventas del dia
- pagos pendientes
- compras en revision
- reservas por vencer
- conversaciones con seguimiento
- entradas FAQ en draft

### Purchase Detail Widgets
- numeros comprados
- estado del pago
- estado del boleto

### Conversation Detail Widgets
- ultimo mensaje del cliente
- ultimo mensaje del bot
- edad del contexto

## Permisos Minimos por Rol
### `admin`
- acceso total

### `operator`
- ver rifas, compras, clientes, conversaciones y FAQ
- no aprobar pagos criticos ni cambiar configuracion sensible

### `finance`
- revisar y resolver pagos
- ver compras y boletos

### `support`
- ver conversaciones, clientes, FAQ y textos
- no cambiar pagos ni rifas

## Convenciones de Implementacion
- usar `Resource` para entidades CRUD principales
- usar `Page` para configuracion singleton
- usar `RelationManager` donde ayude a navegar sin ruido
- usar `Tables\Actions\Action` para operaciones de negocio auditables
- mostrar badges de estado con colores consistentes

## Criterios de Aceptacion
- El panel permite operar FAQ, conversaciones y pagos sin recurrir a SQL ni ediciones manuales de DB.
- Las acciones criticas tienen confirmacion, permisos y auditoria.
- Los recursos reflejan el dominio documentado sin exponer campos peligrosos como edicion libre de estados.
- El diseno favorece operacion rapida y bajo riesgo de error humano.
