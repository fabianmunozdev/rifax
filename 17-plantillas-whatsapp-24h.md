# Plantillas de WhatsApp para Ventana de 24 Horas

## Objetivo
Definir plantillas base para retomar conversaciones cuando no sea posible responder con texto libre fuera de la ventana de 24 horas.

## Principios
- Usar plantillas solo cuando sean necesarias por politica de Meta.
- Mantenerlas cortas, claras y alineadas al flujo real.
- Evitar plantillas genericas que no lleven a una accion concreta.
- Reutilizar pocas plantillas bien definidas en lugar de muchas variantes.

## Variables Soportadas
- `customer_name`
- `raffle_title`
- `ticket_code`
- `ticket_url`
- `lottery_name`
- `lottery_draw_number`
- `draw_date`
- `draw_time`
- `payment_deadline`
- `support_contact`

## Plantillas Recomendadas
### `purchase_resume`
Uso:
- Retomar una compra con reserva activa o proceso interrumpido.

Texto sugerido:
```text
Hola {{customer_name}}, tu proceso para la rifa {{raffle_title}} sigue disponible.

Si deseas continuar, responde a este mensaje y te guiaremos con el siguiente paso.
```

CTA sugerida:
- `Continuar compra`

### `payment_reminder`
Uso:
- Recordar envio de comprobante mientras la compra siga pendiente y la reserva aun tenga sentido operativa o comercialmente.

Texto sugerido:
```text
Hola {{customer_name}}, tu compra para {{raffle_title}} sigue pendiente de confirmacion.

Si ya realizaste el pago, responde con tu comprobante por este chat.
```

CTA sugerida:
- `Enviar comprobante`

### `payment_review_update`
Uso:
- Informar que el comprobante fue recibido y el pago sigue en revision cuando no se pueda responder libremente.

Texto sugerido:
```text
Hola {{customer_name}}, recibimos tu comprobante para {{raffle_title}}.

Tu pago sigue en revision y te avisaremos por este medio cuando quede validado.
```

### `payment_approved_ticket`
Uso:
- Informar aprobacion de pago y compartir acceso al boleto.

Texto sugerido:
```text
Hola {{customer_name}}, tu pago para {{raffle_title}} fue aprobado.

Tu boleto esta disponible con codigo {{ticket_code}}.
Accede aqui: {{ticket_url}}
```

CTA sugerida:
- `Ver boleto`

### `payment_rejected_retry`
Uso:
- Informar rechazo y pedir nuevo comprobante.

Texto sugerido:
```text
Hola {{customer_name}}, no pudimos validar tu pago para {{raffle_title}}.

Por favor responde a este mensaje y envia un nuevo comprobante por este chat para continuar.
```

CTA sugerida:
- `Reenviar comprobante`

### `draw_reminder`
Uso:
- Recordar fecha y referencia del sorteo.

Texto sugerido:
```text
Te recordamos el sorteo de {{raffle_title}}:
{{lottery_name}} #{{lottery_draw_number}}
Fecha: {{draw_date}}
Hora: {{draw_time}}
```

### `support_reopen`
Uso:
- Reabrir contacto cuando el cliente pidio ayuda o quedo pendiente soporte.

Texto sugerido:
```text
Hola {{customer_name}}, estamos retomando tu solicitud de ayuda sobre {{raffle_title}}.

Responde a este mensaje y continuaremos tu atencion.
```

## Reglas de Uso
- No usar plantillas para reemplazar el flujo normal dentro de la ventana activa.
- No usar una plantilla de marketing para un evento operativo de compra o pago.
- Antes de enviar una plantilla, validar que el estado de compra siga vigente.
- Si la compra ya fue pagada, no enviar recordatorio de pago.
- Si la reserva ya expiro, no enviar plantilla de continuidad de reserva sin revisar la politica comercial.

## Mapeo Sugerido por Estado
- `purchase_payment_instructions` fuera de ventana -> `purchase_resume` o `payment_reminder`
- `purchase_under_review` fuera de ventana -> `payment_review_update`
- `purchase_paid` fuera de ventana -> `payment_approved_ticket`
- `purchase_rejected` fuera de ventana -> `payment_rejected_retry`
- postventa o soporte -> `support_reopen`

## Criterios de Aceptacion
- Existe al menos una plantilla por evento critico fuera de ventana.
- Las plantillas usan variables claras y controladas.
- Cada plantilla conduce a una siguiente accion concreta.
- El catalogo de plantillas evita depender de IA para reactivar la conversacion.
