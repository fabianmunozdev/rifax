# Vision del Proyecto

## Objetivo
Construir una plataforma para vender rifas por WhatsApp usando la API oficial de Meta, con operacion centralizada desde un panel administrativo en Laravel y Filament.

El MVP debe permitir publicar rifas, reservar numeros, recibir comprobantes de pago, confirmar pagos manualmente, generar boletos verificables y enviar comunicaciones clave al comprador.

## Alcance Actual
El sistema operara para una sola empresa.

No se implementara multitenancy en esta etapa. En su lugar, el sistema debe permitir gestionar:
- Datos generales de la empresa.
- Branding visual.
- Numero(s) de WhatsApp conectados.
- Metodos e instrucciones de pago.
- Politicas comerciales y textos legales.

## Principios
- Automatizacion antes que IA.
- La IA no controla el flujo de compra.
- La IA solo responde preguntas abiertas, FAQ y soporte acotado.
- El flujo transaccional debe ser determinista y auditable.
- Los pagos se confirman manualmente en el MVP.
- Las operaciones criticas deben quedar registradas.
- La arquitectura debe permitir evolucionar a multiempresa en el futuro sin forzar esa complejidad hoy.

## Propuesta de Valor
- Reducir trabajo operativo en ventas por WhatsApp.
- Evitar asignaciones manuales de numeros.
- Estandarizar la confirmacion de pagos.
- Entregar boletos verificables de forma automatica.
- Medir ventas, conversion y disponibilidad en tiempo real.

## Usuarios del Sistema
- Administrador: configura rifas, confirma pagos, revisa compras y opera campanas.
- Operador: atiende casos manuales, soporte y seguimiento.
- Comprador: interactua por WhatsApp y recibe su boleto.

## Modulos del MVP
- Gestion de rifas.
- Gestion de numeros y disponibilidad.
- Flujo de compra por WhatsApp.
- Confirmacion manual de pagos.
- Generador de boletos con QR verificable.
- Panel administrativo con metricas basicas.
- Campanas operativas basicas.
- Base de conocimiento para FAQ y soporte conversacional.

## Fuera de Alcance del MVP
- Pasarelas de pago automaticas.
- Multiempresa real.
- App movil nativa.
- Sorteos en vivo automatizados.
- IA como motor principal de decisiones.

## Metricas de Exito
- Tiempo promedio desde reserva hasta confirmacion.
- Tasa de conversion de conversacion a compra.
- Cantidad de pagos pendientes por revisar.
- Numeros vendidos por rifa.
- Tiempo promedio de entrega del boleto tras confirmar pago.
