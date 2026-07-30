# Reglas de Negocio

## Entidades Principales
- Rifa.
- Numero de rifa.
- Reserva.
- Compra.
- Pago.
- Boleto.
- Mensaje de WhatsApp.
- Campana.

## Reglas Base
- Un numero pertenece a una sola rifa.
- Un numero de una rifa solo puede estar asignado a una compra activa a la vez.
- El comprador del MVP se identifica por su numero de telefono de WhatsApp.
- Los estados validos del numero son: `available`, `reserved`, `paid`, `cancelled`, `winner`.
- Una reserva expira automaticamente luego del tiempo configurado.
- La confirmacion de pago solo la realiza un administrador autorizado.
- El boleto se genera solo despues de confirmar el pago.
- Todo boleto debe tener un codigo unico y un QR verificable.
- Toda accion administrativa critica debe dejar auditoria.

## Reglas de Reserva
- La reserva bloquea temporalmente los numeros seleccionados.
- El tiempo sugerido inicial de expiracion es 15 minutos, configurable por rifa o por sistema.
- Cada rifa puede definir una cantidad minima de numeros permitida por compra.
- Si una rifa no define un minimo explicito, el valor por defecto debe ser `1`.
- El sistema no debe permitir continuar una compra si la cantidad solicitada es menor al minimo configurado para esa rifa.
- Si una reserva expira, los numeros vuelven a `available`.
- Si el comprador cambia de numeros antes de pagar, la reserva anterior debe liberarse correctamente.
- No se deben permitir reservas duplicadas sobre el mismo numero.

## Reglas de Compra
- Una compra puede contener uno o varios numeros de la misma rifa.
- Una compra debe conservar snapshot del precio, nombre de la rifa y datos relevantes al momento de la transaccion.
- Una compra puede pasar por estados como `pending_payment`, `payment_submitted`, `paid`, `cancelled`, `expired`.
- Una compra expirada o cancelada no puede recibir boleto.

## Reglas de Pago
- El pago del MVP es manual.
- El comprador envia el comprobante exclusivamente por WhatsApp.
- Un pago puede ser `pending_review`, `approved`, `rejected`.
- Un rechazo debe registrar motivo.
- Un pago aprobado no debe poder aprobarse nuevamente.
- El monto esperado debe validarse contra la compra.

## Reglas de Boleto
- Cada boleto pertenece a una compra pagada.
- El boleto debe incluir datos suficientes para validacion publica.
- El QR debe dirigir a una URL publica de verificacion o contener un codigo verificable equivalente.
- La verificacion publica del boleto debe mostrar informacion minima y no exponer nombre ni telefono del comprador.
- Si se regenera el boleto, debe quedar registro de version o motivo.

## Reglas de Sorteo
- Cada rifa debe configurarse con informacion de la loteria o casa de sorteo utilizada como referencia.
- Cada rifa debe almacenar al menos nombre de la loteria, numero del sorteo, fecha del sorteo y hora del sorteo.
- El sistema no genera el resultado del sorteo; solo registra y comunica la referencia del sorteo externo.
- El numero ganador se carga manualmente en funcion del resultado publicado por la loteria externa.

## Reglas de Comunicacion
- El flujo de compra usa mensajes estructurados y reglas deterministas.
- La IA solo responde cuando la intencion no corresponde a una accion transaccional.
- Si el sistema no entiende el mensaje, debe ofrecer menu, ayuda o derivacion humana.

## Reglas de Auditoria
Deben registrarse como minimo:
- Creacion y edicion de rifas.
- Confirmacion y rechazo de pagos.
- Cambios manuales de numeros.
- Regeneracion de boletos.
- Ejecucion manual de campanas.
