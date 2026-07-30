# Catalogo de FAQ y Textos Fijos

## Objetivo
Definir un catalogo administrable desde el panel para responder la mayor parte de consultas sin IA y sin tocar codigo.

## Principio Rector
- Las respuestas frecuentes deben salir de contenido fijo o parametrizado.
- El equipo operativo debe poder editar textos sin desplegar codigo.
- La IA solo entra cuando el catalogo no cubre la consulta y no existe respuesta determinista del sistema.

## Alcance del Catalogo
El catalogo debe cubrir como minimo:
- FAQ de condiciones.
- FAQ de metodos de pago.
- FAQ de fechas y sorteos.
- FAQ de estados de compra.
- FAQ de numeros comprados.
- Mensajes de fallback.
- Mensajes de ayuda.
- Mensajes de error conocidos.
- Textos de menu y soporte.

## Tipos de Contenido
### `faq_fixed`
Respuesta totalmente fija.

Ejemplos:
- condiciones
- como funciona la rifa
- como comprar

### `faq_parametrized`
Respuesta fija con variables del sistema.

Ejemplos:
- fecha del sorteo
- hora del sorteo
- estado de compra
- numeros comprados

### `system_message`
Texto operativo del bot.

Ejemplos:
- bienvenida
- error por minimo no cumplido
- reserva vencida
- numero no disponible

### `support_message`
Texto para derivacion o seguimiento humano.

Ejemplos:
- hablar con soporte
- seguimiento manual

### `template_bridge`
Texto base asociado a una plantilla aprobada de WhatsApp fuera de 24 horas.

## Tabla Propuesta Principal
Nombre sugerido: `content_entries`

## Campos Recomendados
- `id`
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
- `created_at`
- `updated_at`

## Significado de Campos
- `type`: tipo de contenido como `faq_fixed`, `faq_parametrized`, `system_message`, `support_message`, `template_bridge`.
- `key`: identificador unico estable, por ejemplo `faq.payment_methods`.
- `title`: nombre legible en administracion.
- `category`: agrupacion funcional, por ejemplo `payments`, `raffles`, `support`, `fallbacks`.
- `locale`: idioma del contenido, por ahora al menos `es`.
- `channel`: canal de uso, por ejemplo `whatsapp`.
- `status`: `draft`, `published`, `archived`.
- `trigger_intent`: intencion o ruta que activa esta entrada.
- `body_text`: texto principal.
- `variables_json`: variables permitidas para esta entrada.
- `fallback_text`: texto alternativo si faltan datos.
- `priority`: orden de resolucion cuando varias entradas coinciden.
- `is_ai_eligible`: define si, ante ausencia de respuesta suficiente, puede pasar a IA.
- `notes`: notas internas para operacion.

## Restricciones Recomendadas
- `key` debe ser unico por `locale + channel`.
- Solo entradas `published` pueden responder a compradores.
- `is_ai_eligible` debe ser `false` por defecto.
- `type`, `status` y `channel` deben usar catalogos cerrados.

## Variables Permitidas
Ejemplos de variables seguras:
- `{raffle_title}`
- `{draw_date}`
- `{draw_time}`
- `{lottery_name}`
- `{lottery_draw_number}`
- `{purchase_status}`
- `{purchases_summary}`
- `{ticket_url}`
- `{ticket_code}`
- `{min_numbers_per_purchase}`

## Variables No Permitidas
- Datos sensibles no necesarios del comprador.
- Campos internos de auditoria.
- Tokens o secretos.
- Contenido libre generado por IA sin validacion.

## Categorias Iniciales Recomendadas
- `purchase_flow`
- `payments`
- `raffles`
- `draws`
- `tickets`
- `support`
- `fallbacks`
- `legal`

## Claves Iniciales Recomendadas
- `system.menu.welcome`
- `system.purchase.min_quantity_error`
- `system.purchase.reservation_expired`
- `system.purchase.number_unavailable`
- `faq.raffle.conditions`
- `faq.raffle.how_it_works`
- `faq.payment.methods`
- `faq.purchase.status`
- `faq.purchase.my_numbers`
- `faq.draw.date`
- `faq.draw.time`
- `faq.draw.reference`
- `support.contact.start`
- `fallback.unrecognized_input`

## Logica de Resolucion
Orden recomendado:
1. Comando global o estado conversacional.
2. Ruta transaccional del flujo.
3. Entrada `system_message`.
4. Entrada `faq_fixed`.
5. Entrada `faq_parametrized`.
6. Entrada `support_message`.
7. IA, solo si `is_ai_eligible = true` y no existe respuesta suficiente.

## Estados de Publicacion
### `draft`
- Visible solo en administracion.
- No se usa en produccion.

### `published`
- Disponible para respuestas reales.

### `archived`
- Conservado para historial, no reutilizable en flujo activo.

## Gestion desde Panel
El panel debe permitir:
- Crear, editar y archivar entradas.
- Buscar por `key`, categoria o tipo.
- Previsualizar el texto final con variables.
- Validar variables permitidas antes de publicar.
- Marcar si una entrada puede o no escalar a IA.
- Duplicar entradas para nuevas rifas o nuevas variantes.

## Reglas de Operacion
- Ninguna intencion transaccional debe depender de una entrada editable para definir logica de negocio.
- Los textos pueden cambiar, pero la logica del flujo no.
- Si una entrada parametrizada no tiene datos suficientes, usar `fallback_text` o derivar a soporte.
- No usar el catalogo para ocultar reglas de negocio; usarlo para presentarlas.

## Ejemplo de Entrada `faq_fixed`
```json
{
  "type": "faq_fixed",
  "key": "faq.payment.methods",
  "title": "Metodos de pago",
  "category": "payments",
  "locale": "es",
  "channel": "whatsapp",
  "status": "published",
  "trigger_intent": "payment_methods",
  "body_text": "Puedes pagar usando los metodos habilitados por la empresa. Cuando completes tu compra, te enviaremos las instrucciones exactas de pago por este chat.",
  "variables_json": [],
  "fallback_text": null,
  "priority": 100,
  "is_ai_eligible": false
}
```

## Ejemplo de Entrada `faq_parametrized`
```json
{
  "type": "faq_parametrized",
  "key": "faq.draw.date",
  "title": "Fecha del sorteo",
  "category": "draws",
  "locale": "es",
  "channel": "whatsapp",
  "status": "published",
  "trigger_intent": "draw_date",
  "body_text": "El sorteo de {raffle_title} se realiza el {draw_date} a las {draw_time} usando como referencia {lottery_name} #{lottery_draw_number}.",
  "variables_json": ["raffle_title", "draw_date", "draw_time", "lottery_name", "lottery_draw_number"],
  "fallback_text": "Ahora mismo no tengo disponible la fecha exacta del sorteo. Escribe MENU o solicita ayuda.",
  "priority": 100,
  "is_ai_eligible": false
}
```

## Criterios de Aceptacion
- El equipo puede editar FAQ y textos fijos desde administracion.
- El bot resuelve la mayoria de preguntas frecuentes sin IA.
- Las respuestas parametrizadas usan solo variables autorizadas.
- El catalogo soporta version operativa mediante `draft`, `published` y `archived`.
- La resolucion prioriza texto fijo y datos del sistema antes de IA.
