# Notificacion de comprobantes al soporte

## Objetivo

Cuando un comprador envia un comprobante de pago por WhatsApp, el sistema genera una notificacion operativa al numero configurado en:

- `Company Settings -> support_phone`

La notificacion busca acelerar la revision para que el administrador entre al panel y apruebe o rechace la compra.

## Que envia el sistema

La alerta enviada al soporte incluye:

- id de la compra
- id del pago
- nombre del cliente
- telefono del cliente
- documento del cliente
- titulo de la rifa
- numeros comprados
- total de la compra
- URL publica del comprobante
- URL directa al pago en el panel admin

## Campo de configuracion

Debe existir un numero valido en:

- `Company Settings -> support_phone`

Si ese campo esta vacio, la notificacion no se envia.

## Flujo tecnico

1. el comprador envia la imagen del comprobante
2. el sistema crea:
   - `payments`
   - `payment_proofs`
3. la compra pasa a estado `payment_submitted`
4. la conversacion pasa a `purchase_under_review`
5. despues de eso se genera la notificacion al soporte

## Comportamiento ante errores

La notificacion al soporte se ejecuta fuera de la transaccion principal.  
Eso significa:

- si la alerta falla, el comprobante sigue registrado
- la compra sigue pasando a revision
- el comprador no queda bloqueado por una falla operativa de aviso

## Referencias de codigo

- [SubmitPaymentProofAction.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/app/Actions/Payments/SubmitPaymentProofAction.php)
- [NotifySupportOfPaymentProofAction.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/app/Actions/WhatsApp/NotifySupportOfPaymentProofAction.php)
- [ReceivePaymentProofTest.php](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/tests/Feature/Payments/ReceivePaymentProofTest.php)

## Consideracion importante de WhatsApp

Esta implementacion reutiliza el canal saliente existente y hoy envia un mensaje de texto con fallback operativo.

Si el numero de soporte no tiene una ventana activa de 24 horas con el numero de negocio, Meta podria exigir una plantilla aprobada para garantizar la entrega.

En ese caso, el siguiente endurecimiento recomendado es:

- crear un `ContentEntry`/template bridge para el intent:
  - `admin_payment_proof_submitted`

Con eso la notificacion operativa quedaria lista tambien para escenarios fuera de la ventana de 24 horas.
