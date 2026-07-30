# Panel Administrativo

## Objetivo
Centralizar la operacion del negocio desde Filament con foco en productividad, control y trazabilidad.

Ver blueprint detallado de recursos en [23-recursos-filament.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/23-recursos-filament.md).

## Dashboard
- Ventas del dia, semana y mes.
- Ingresos confirmados.
- Compras pendientes de pago o revision.
- Numeros vendidos y disponibles por rifa.
- Conversion basica del embudo.
- Actividad reciente y alertas operativas.

## Modulos
- Rifas.
- Numeros.
- Clientes.
- Compras.
- Pagos.
- Boletos.
- Campanas.
- WhatsApp / conversaciones.
- FAQ y textos fijos.
- Configuracion de empresa y branding.
- Estadisticas.
- Auditoria.

## Rifa
Debe permitir:
- Crear y editar rifas.
- Configurar precio, fecha de sorteo y estado.
- Configurar cantidad minima de numeros permitida por compra.
- Prellenar cantidad minima de numeros por compra con valor por defecto `1`.
- Configurar loteria de referencia, numero del sorteo y hora del sorteo.
- Cargar portada e imagen del premio.
- Generar numeros de forma masiva.
- Configurar tiempo de reserva.

## Compras y Pagos
Debe permitir:
- Ver compras por estado.
- Revisar comprobantes.
- Aprobar o rechazar pagos.
- Ver numeros asociados.
- Visualizar compras con uno o varios numeros.
- Regenerar boleto con trazabilidad si aplica.
- Forzar liberacion de reserva en casos excepcionales.

## Conversaciones
Debe permitir:
- Ver historial de mensajes por cliente.
- Identificar estado conversacional actual.
- Marcar conversacion para seguimiento humano.
- Registrar notas internas.

## FAQ y Textos Fijos
Debe permitir:
- Crear, editar y archivar entradas del catalogo.
- Clasificar por tipo, categoria, canal y estado.
- Gestionar respuestas fijas y parametrizadas.
- Validar variables permitidas antes de publicar.
- Definir si una entrada puede o no escalar a IA.
- Previsualizar el texto final antes de activarlo.

## Configuracion
Debe permitir gestionar:
- Nombre comercial.
- Logo y colores.
- Datos legales.
- Metodos de pago.
- Textos de ayuda y condiciones.
- Parametros globales como expiracion por defecto.

## Permisos Sugeridos
- `admin`: acceso total.
- `operator`: operacion diaria sin configuraciones criticas.
- `finance`: revision de pagos y reportes.
- `support`: seguimiento de conversaciones y clientes.

## UX del Panel
- Acciones frecuentes en un clic.
- Filtros por estado, fecha y rifa.
- Indicadores visuales claros para pagos pendientes y reservas vencidas.
- Formularios simples con validaciones explicitas.
- No exponer edicion libre de estados de compra, pago o conversacion cuando deban pasar por acciones controladas.
