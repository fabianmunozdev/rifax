# Meta y webhook de WhatsApp en produccion

## Objetivo

Este documento resume que cambios deben hacerse en `Meta` al pasar el proyecto a produccion en:

- `https://rifax.fabianmunoz.dev`

y donde deben reflejarse esos mismos valores en el `.env` del servidor.

## 1. Si, hay cambios que revisar en Meta

Al mover el sistema desde desarrollo o `ngrok` a produccion, normalmente debes revisar y/o actualizar:

- `Webhook callback URL`
- `Verify token`
- `App Secret`
- `Phone Number ID`
- `Access Token`
- suscripcion del campo `messages`

## 2. URL correcta del webhook en este proyecto

La ruta del webhook esta definida en:

- [routes/api.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/routes/api.php#L8-L9)

Endpoints:

- `GET /api/webhooks/whatsapp` -> verificacion de Meta
- `POST /api/webhooks/whatsapp` -> eventos entrantes de WhatsApp

Por tanto, la URL publica de produccion que debes registrar en Meta es:

```text
https://rifax.fabianmunoz.dev/api/webhooks/whatsapp
```

## 3. Donde configurarlo en Meta

Ruta general en Meta Developers:

1. entrar a tu `App`
2. abrir el producto `WhatsApp`
3. entrar a `Configuration`
4. buscar la seccion `Webhook`
5. editar:
   - `Callback URL`
   - `Verify token`

## 4. Que poner en Meta

### Callback URL

```text
https://rifax.fabianmunoz.dev/api/webhooks/whatsapp
```

### Verify token

Debe ser exactamente el mismo valor que tengas en el `.env` de produccion en:

```env
WHATSAPP_WEBHOOK_VERIFY_TOKEN=...
```

Si no coinciden, Meta no podra validar el webhook.

## 5. Que poner en el `.env` del servidor

En el servidor:

```bash
ssh deploy@95.217.177.163
cd /var/www/rifax
nano .env
```

Variables relevantes:

```env
WHATSAPP_WEBHOOK_VERIFY_TOKEN=...
WHATSAPP_WEBHOOK_APP_SECRET=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_SEND_ENABLED=true
```

## 6. Que representa cada variable

### `WHATSAPP_WEBHOOK_VERIFY_TOKEN`

- token libre definido por ti
- debe coincidir exactamente con el que pongas en la configuracion del webhook en Meta

### `WHATSAPP_WEBHOOK_APP_SECRET`

- debe ser el `App Secret` real de tu aplicacion de Meta
- este proyecto valida la firma `X-Hub-Signature-256` en cada `POST`
- si este valor no coincide con el `App Secret` real, el sistema rechazara el webhook

La validacion esta implementada en:

- [ValidateIncomingWhatsappWebhookSignatureAction.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/app/Actions/WhatsApp/ValidateIncomingWhatsappWebhookSignatureAction.php)

### `WHATSAPP_PHONE_NUMBER_ID`

- id del numero de WhatsApp Business conectado
- se usa para envios salientes a la API de Meta

### `WHATSAPP_ACCESS_TOKEN`

- token de acceso valido para el numero / app configurada
- idealmente uno estable de produccion

### `WHATSAPP_SEND_ENABLED`

- `false`: no envia mensajes reales
- `true`: habilita envios reales a Meta

## 7. Campo que debes suscribir en Meta

En la configuracion del webhook, asegúrate de suscribir el campo:

- `messages`

En la Cloud API de WhatsApp, ese campo cubre tanto:

- mensajes entrantes
- actualizaciones de estado

El proyecto procesa ambos en:

- [WhatsappWebhookController.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/app/Http/Controllers/Api/WhatsappWebhookController.php)

## 8. Orden recomendado para hacer el cambio

1. actualizar `.env` en el servidor con valores reales
2. aplicar cambios de Laravel:

```bash
php artisan optimize:clear
php artisan optimize
sudo supervisorctl restart rifax-worker
sudo systemctl reload php8.5-fpm
```

3. ir a Meta y actualizar:
   - `Callback URL`
   - `Verify token`
4. ejecutar la verificacion del webhook desde Meta
5. confirmar que el campo `messages` quede suscrito
6. hacer una prueba real de mensaje entrante

## 9. Que pasa si antes usabas ngrok

Si antes Meta apuntaba a una URL de `ngrok`, debes reemplazarla por:

```text
https://rifax.fabianmunoz.dev/api/webhooks/whatsapp
```

Ese es el cambio principal del webhook "de vuelta".

## 10. Checklist rapido de produccion

- `Callback URL` en Meta apunta al dominio productivo
- `Verify token` de Meta coincide con `WHATSAPP_WEBHOOK_VERIFY_TOKEN`
- `App Secret` real esta en `WHATSAPP_WEBHOOK_APP_SECRET`
- `Phone Number ID` correcto en `.env`
- `Access Token` correcto en `.env`
- `WHATSAPP_SEND_ENABLED=true`
- campo `messages` suscrito
- webhook verificado correctamente

## 11. Dónde editarlo y recordatorios

### En Meta

- `Meta Developers -> App -> WhatsApp -> Configuration -> Webhook`

### En el servidor

- [documentation/acceso-servidor-y-cambios-env.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/documentation/acceso-servidor-y-cambios-env.md)

### Rutas del proyecto

- [routes/api.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/routes/api.php#L8-L9)

### Configuracion del servicio

- [config/services.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/config/services.php#L39-L52)
