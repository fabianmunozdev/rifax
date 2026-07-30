# Flujos de WhatsApp

## Objetivo
Definir un flujo claro, transaccional y recuperable para la compra de rifas por WhatsApp.

## Menu Principal
1. Comprar.
2. Numeros disponibles.
3. Mis numeros.
4. Estadisticas de la rifa.
5. Proximas rifas.
6. Condiciones.
7. Ayuda.

## Flujo de Compra
1. El usuario inicia conversacion.
2. El sistema identifica la rifa activa o muestra opciones disponibles.
3. El usuario indica cantidad de numeros.
4. El sistema valida que la cantidad cumpla el minimo configurado para la rifa.
5. El sistema ofrece seleccion manual o asignacion aleatoria.
6. El sistema valida disponibilidad y crea reserva.
7. El sistema envia resumen de compra e instrucciones de pago.
8. El usuario envia el comprobante exclusivamente por WhatsApp.
9. La compra queda en revision manual.
10. Un administrador aprueba o rechaza el pago.
11. Si se aprueba, se genera y envia el boleto.
12. Si se rechaza, se informa el motivo y se indican pasos siguientes.

## Flujos Complementarios
### Numeros Disponibles
- Mostrar rangos o lista resumida segun volumen.
- Evitar mensajes demasiado largos.
- Permitir refrescar disponibilidad.

### Mis Numeros
- Identificar compras del contacto por numero de WhatsApp.
- Mostrar compras vigentes, pagadas y pendientes.
- Incluir acceso al boleto si ya fue emitido.
- Soportar compras con uno o varios numeros dentro de la misma rifa.

### Estadisticas
- Mostrar datos publicos permitidos: porcentaje vendido, numeros restantes y fecha del sorteo.
- Mostrar tambien referencia informativa del sorteo externo cuando aplique: loteria, numero del sorteo y hora.
- No exponer informacion sensible de otros compradores.

### Proximas Rifas
- Mostrar rifas programadas y opcion de recibir recordatorio.

### Condiciones
- Responder reglas de compra, pagos, reservas y politicas generales.

### Ayuda
- Intentar resolver con FAQ.
- Permitir derivacion a soporte humano cuando aplique.

## Casos Borde
- Si la cantidad solicitada es menor al minimo permitido por la rifa, el sistema debe informarlo y pedir una nueva cantidad.
- Si el usuario escribe fuera del flujo esperado, el sistema debe reconducir sin perder contexto.
- Si la reserva expira, se debe informar claramente y ofrecer nueva seleccion.
- Si los numeros elegidos ya no estan disponibles, el sistema debe proponer alternativas.
- Si llega un audio, imagen o mensaje no soportado, se debe responder con fallback claro.
- Si la ventana de 24 horas no permite respuesta libre, el sistema debe usar plantilla aprobada cuando corresponda.

## Reglas de Conversacion
- El flujo transaccional no depende de IA.
- Cada mensaje relevante debe asociarse a un estado conversacional persistido.
- Debe existir un comando o accion para volver al menu.
- Debe existir una accion para cancelar el proceso actual.
- Las consultas FAQ deben resolverse primero con respuestas fijas antes de considerar IA.
- Si la ventana de 24 horas esta cerrada, el sistema debe retomar con plantilla aprobada y no con texto libre.

## Derivacion Humana
El sistema debe permitir marcar conversaciones para atencion manual cuando:
- El usuario reporta un problema de pago.
- El bot no entiende multiples mensajes consecutivos.
- Existe disputa por numero o comprobante.
- Un operador necesita intervenir por excepcion.
