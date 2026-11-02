# Estructura del bot de WhatsApp

El bot recibe cada mensaje en `ProcessIncomingWhatsappMessageAction` y reparte el trabajo
entre clases pequeñas en `app/Actions/WhatsApp/Bot/`.

| Archivo | Qué hace | Cuándo tocarlo |
|---|---|---|
| `ProcessIncomingWhatsappMessageAction` | Identifica al cliente, guarda el mensaje, pausa el bot si atiende un humano, elige el flujo según el estado y envía la respuesta | Un estado nuevo de conversación o un comando global nuevo |
| `Bot/InboundNormalizer` | Convierte texto, botones e imágenes a un formato común | WhatsApp agrega un tipo de mensaje |
| `Bot/BotKeywords` | Reconoce comandos: MENU, ASESOR, CANCELAR, REEMPLAZAR, saludos, atajos | Agregar o cambiar palabras clave |
| `Bot/BotMessages` | Todos los textos y botones que envía el bot | Cambiar un mensaje (o mejor: crear una entrada en Contenidos) |
| `Bot/BotRaffles` | Rifas activas y volver al menú | Cambiar qué rifa cuenta como activa |
| `Bot/MainMenuFlow` | Menú principal (opciones 1–7) y preguntas frecuentes | Opciones del menú |
| `Bot/OnboardingFlow` | Aceptación de datos, nombre y cédula antes de comprar | Datos que se piden al cliente |
| `Bot/PurchaseFlow` | Elegir rifa, cantidad, modo y números; reservar; selección desde la web | Reglas de compra o reserva |
| `Bot/PaymentFlow` | Recibir y reemplazar comprobantes, compra en revisión | Reglas de pago |

Las capas solo dependen hacia abajo (flujos → mensajes/palabras clave/rifas), así que no hay
dependencias circulares. Cuando el registro termina, `OnboardingFlow` devuelve `null` y el punto
de entrada retoma la acción que el cliente había dejado pendiente.

Las pruebas del bot están en `tests/Feature/WhatsApp/Webhook/ReceiveMessageTest.php` y
`tests/Feature/Conversations/HumanHandoffTest.php`.
