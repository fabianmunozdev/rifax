# Prompt WhatsApp

## Objetivo
Implementar la integracion con WhatsApp Cloud API para soportar el flujo de compra, consulta y soporte basico de rifas.

## Debe Incluir
- Verificacion y recepcion de webhook.
- Normalizacion de mensajes entrantes.
- Persistencia del historial conversacional.
- Maquina de estados conversacional.
- Menu principal y flujos deterministas.
- Uso de plantillas cuando la ventana de 24 horas lo requiera.
- Fallbacks para mensajes no soportados.
- Priorizacion de FAQ fija y respuestas parametrizadas antes de cualquier uso de IA.

## Restricciones
- La IA no controla el flujo de compra.
- Reducir al maximo la necesidad de usar IA.
- No asumir multitenancy.
- Respetar limites y reglas de Meta.
- Registrar errores y mensajes relevantes.

## Flujos Minimos
- Comprar.
- Ver disponibles.
- Ver mis numeros.
- Ver estadisticas.
- Ver proximas rifas.
- Ver condiciones.
- Pedir ayuda.

## Casos Borde
- Mensajes duplicados.
- Reserva vencida.
- Usuario fuera de flujo.
- Audio o imagen no esperada.
- Reapertura de conversacion despues de horas o dias.
