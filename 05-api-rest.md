# API REST

## Objetivo
Exponer endpoints claros para integraciones, panel administrativo y verificaciones publicas.

## Principios
- Todas las respuestas deben ser JSON salvo endpoints publicos de visualizacion o descarga.
- Los endpoints administrativos deben autenticarse con un unico mecanismo consistente.
- Los webhooks deben validar firma y ser idempotentes.
- Los errores deben usar codigos HTTP correctos y payload estandar.

## Endpoints Base
### WhatsApp
- `GET /api/webhooks/whatsapp` para verificacion inicial del webhook.
- `POST /api/webhooks/whatsapp` para eventos entrantes de Meta.
- El `POST` debe validar `X-Hub-Signature-256` usando HMAC SHA-256 sobre el cuerpo crudo y el secreto configurado.
- El `POST` debe estar protegido con rate limiting dedicado y responder `429` con `retry_after_seconds` cuando se exceda el umbral configurado.
- Los rechazos por firma y por rate limit deben quedar trazados como eventos de sistema para observabilidad operativa.

### Compras
- `POST /api/purchases` crea compra y reserva de numeros.
- `GET /api/purchases/{id}` consulta detalle de compra.
- `POST /api/purchases/{id}/submit-proof` registra comprobante de pago recibido desde WhatsApp.
- `POST /api/purchases/{id}/cancel` cancela compra si aplica.

### Pagos
- `GET /api/payments` lista pagos pendientes o filtrados para admin.
- `POST /api/payments/{id}/approve` confirma pago.
- `POST /api/payments/{id}/reject` rechaza pago con motivo.

### Boletos
- `POST /api/tickets/generate` genera boleto cuando corresponda.
- `GET /api/tickets/{code}/verify` valida boleto de forma publica.
- `GET /api/tickets/{code}` devuelve metadata o acceso protegido segun contexto.

### Rifas
- `GET /api/raffles/active` lista rifas activas visibles al comprador.
- `GET /api/raffles/{id}` devuelve detalle publico o administrativo segun auth.
- `GET /api/raffles/{id}/numbers` devuelve disponibilidad resumida.

### Campanas
- `POST /api/campaigns/send` ejecuta una campana manual o programada.
- `GET /api/campaigns/{id}/runs` consulta historial de ejecucion.

### Configuracion
- `GET /api/settings/company` obtiene configuracion de empresa.
- `PUT /api/settings/company` actualiza branding y datos operativos.
- `GET /api/payment-methods` lista metodos de pago.
- `PUT /api/payment-methods/{id}` actualiza instrucciones.

## Autenticacion
- Panel administrativo y endpoints internos: `Sanctum`.
- Endpoints publicos: sin autenticacion, con controles de visibilidad y rate limiting.
- Webhook de WhatsApp: verificacion de firma y secretos de configuracion.

## Formato de Error
```json
{
  "message": "Validation failed",
  "errors": {
    "field": ["The field is required."]
  }
}
```

## Reglas Importantes
- Aprobar un pago debe ser idempotente.
- No se deben generar dos boletos activos para la misma compra sin trazabilidad.
- La API debe registrar auditoria en acciones administrativas sensibles.
- Los endpoints publicos deben filtrar informacion personal.
- La verificacion publica del boleto no debe exponer nombre ni telefono del comprador.
- La creacion de compra debe validar la cantidad minima de numeros configurada para la rifa.
