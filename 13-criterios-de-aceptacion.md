# Criterios de Aceptacion

## Objetivo
Definir condiciones observables para considerar terminado cada modulo del MVP.

## Gestion de Empresa y Branding
- Existe una pantalla para editar nombre comercial, logo, colores, datos legales y textos operativos.
- La configuracion guardada se refleja en panel, mensajes y boletos donde corresponda.
- Los metodos de pago pueden configurarse y actualizarse sin tocar codigo.

## Rifas
- Un administrador puede crear, editar, publicar y cerrar una rifa.
- Cada rifa permite configurar precio, numeros, tiempo de reserva e imagenes.
- Cada rifa permite configurar una cantidad minima de numeros por compra.
- Si no se configura un minimo especifico, el sistema usa valor por defecto `1`.
- Cada rifa registra loteria de referencia, numero del sorteo, fecha y hora del sorteo.
- La rifa expone disponibilidad y datos publicos sin revelar informacion sensible de compradores.

## Numeros y Reservas
- El sistema genera numeros de forma masiva para una rifa.
- Un numero no puede reservarse dos veces al mismo tiempo.
- Una reserva expira automaticamente y libera numeros al vencer.
- Una compra puede contener varios numeros de la misma rifa.

## Clientes
- El comprador se identifica por telefono de WhatsApp.
- El sistema puede recuperar historial de compras y mensajes por telefono.
- No existe login de cliente en el MVP.

## Flujo de Compra por WhatsApp
- El usuario puede iniciar compra desde el menu principal.
- El sistema permite elegir cantidad y modo de asignacion de numeros.
- El sistema valida la cantidad minima permitida antes de continuar el flujo.
- El sistema confirma reserva e informa instrucciones de pago.
- El sistema puede reconducir al usuario si sale del flujo esperado.
- El sistema permite consultar mis numeros, disponibles, estadisticas, proximas rifas, condiciones y ayuda.
- Existe una maquina de estados conversacional documentada y consistente con el flujo de compra.
- Existen plantillas definidas para retomar conversaciones fuera de la ventana de 24 horas.
- Existe persistencia operativa de `conversation_states` para reconstruir el estado actual del cliente.

## Pagos
- El comprobante se recibe unicamente por WhatsApp.
- Un administrador puede revisar cada comprobante recibido.
- Un pago puede aprobarse o rechazarse con trazabilidad.
- Un pago aprobado no puede aprobarse nuevamente.
- El rechazo de pago registra motivo y permite comunicarlo al comprador.

## Boletos
- Al aprobar un pago se genera un boleto de manera automatica.
- El boleto incluye branding, rifa, numeros comprados, codigo unico y datos del sorteo.
- El boleto dispone de QR y URL publica de verificacion.
- La verificacion publica muestra informacion minima y no expone nombre ni telefono del comprador.
- Si el boleto se regenera, queda evidencia de la accion.

## Sorteo y Resultado
- Cada rifa deja documentada la loteria externa usada como referencia.
- El sistema muestra de forma informativa nombre de la loteria, numero del sorteo, fecha y hora.
- El numero ganador se registra manualmente segun el resultado publicado por la loteria externa.
- El sistema no calcula ni genera el sorteo por cuenta propia en el MVP.

## Panel Administrativo
- El dashboard muestra ventas, ingresos, pagos pendientes y disponibilidad por rifa.
- El panel permite operar rifas, compras, pagos, boletos, clientes, conversaciones y configuracion.
- Existen filtros utiles por estado, fecha y rifa.
- Las acciones criticas quedan registradas en auditoria.
- Existe un blueprint claro de recursos Filament para conversaciones, FAQ, pagos y configuracion operativa.

## Campanas
- Las campanas se ejecutan mediante colas.
- Se pueden lanzar recordatorios y mensajes operativos basicos.
- El sistema evita duplicados evidentes dentro de una misma ventana operativa.
- Queda registro de intentos, envios exitosos y fallos.

## IA y FAQ
- La mayor parte de preguntas frecuentes se responde sin IA mediante contenido fijo o parametrizado.
- La IA solo responde preguntas abiertas que no puedan resolverse con FAQ fija o datos del sistema.
- La IA no reserva numeros, no confirma pagos y no cambia estados de negocio.
- Si la intencion es transaccional, el sistema prioriza el flujo estructurado.
- Si hay baja confianza o ambiguedad, el sistema deriva a menu o soporte humano.
- Existe un catalogo administrable de FAQ y textos fijos publicado desde el panel.

## Seguridad y Trazabilidad
- El webhook de WhatsApp valida autenticidad.
- Los endpoints administrativos usan autenticacion y permisos.
- Las acciones criticas quedan auditadas con usuario, fecha y accion.
- Los endpoints publicos no exponen datos sensibles de compradores.

## Definicion de Terminado del MVP
- Los modulos anteriores funcionan de punta a punta en entorno de prueba.
- Existe trazabilidad suficiente para revisar pagos, boletos y mensajes.
- Los principales casos borde estan cubiertos por pruebas o validaciones manuales documentadas.
- El sistema puede operarse sin procesos manuales fuera de WhatsApp, panel admin y confirmacion humana de pagos.
- Existe una matriz de casos de prueba funcionales alineada con el flujo conversacional, FAQ, plantillas y `conversation_states`.
- Existe un blueprint concreto de tests Laravel con clases, factories y helpers para implementar la suite sin ambiguedad.
