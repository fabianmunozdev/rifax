# Campanas

## Objetivo
Permitir comunicaciones operativas y comerciales controladas, medibles y ejecutadas mediante colas.

## Automatizaciones Iniciales
- Recordatorio de pago pendiente.
- Quedan pocos numeros.
- Manana es el sorteo.
- Proxima rifa.
- Upselling para compradores existentes.

## Reglas Operativas
- Todas las campanas deben ejecutarse mediante `Redis Queue`.
- Debe existir control de duplicados por cliente, campana y ventana temporal.
- Deben respetarse horarios de envio configurables.
- Los mensajes fuera de la ventana libre deben usar plantillas aprobadas.
- Deben registrarse intentos, exitos, errores y rechazos.

## Segmentacion Minima
- Compradores con pago pendiente.
- Compradores pagados.
- Clientes sin compra reciente.
- Interesados en una rifa especifica.
- Compradores recurrentes.

## Metricas
- Enviados.
- Entregados.
- Fallidos.
- Respondidos.
- Conversion atribuible si aplica.

## Casos Borde
- Evitar enviar recordatorio de pago a una compra ya pagada.
- Evitar enviar mensajes a clientes bloqueados u opt-out si esa politica se implementa.
- Evitar reintentos infinitos ante errores permanentes de Meta.
