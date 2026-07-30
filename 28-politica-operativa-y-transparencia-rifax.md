# Politica Operativa y Transparencia de Rifax

## 1. Objetivo

Este documento resume dos cosas:

- como opera internamente el flujo de reservas, pagos y revision manual;
- que partes conviene explicar al comprador final para dar confianza y transparencia.

## 2. Politica operativa interna

### 2.1 Reserva inicial de numeros

- Cuando un comprador selecciona numeros, el sistema los reserva por un tiempo limitado.
- Si el comprador no envia comprobante dentro de esa ventana, la reserva puede expirar y los numeros vuelven a quedar disponibles.

### 2.2 Comprobante enviado por el comprador

- Si el comprador ya envio su comprobante por WhatsApp, la reserva deja de manejarse como una reserva comun pendiente.
- Desde ese momento, los numeros no deben liberarse por expiracion automatica de la reserva.
- La compra queda en revision manual por parte del equipo administrativo.

### 2.3 Ventana maxima de revision

- La revision de pagos tiene una ventana operativa maxima configurada en el sistema.
- Valor actual recomendado: **48 horas**.
- Cuando se supera esa ventana, el pago se considera **revision vencida** para efectos operativos.
- Esto **no significa rechazo automatico** ni liberacion de numeros.
- Significa que el caso necesita atencion humana prioritaria.

### 2.4 Aprobacion y rechazo

- Si el pago es aprobado, la compra pasa a pagada y se generan los activos posteriores (boleto y notificaciones).
- Si el pago es rechazado, el comprador puede reenviar un comprobante segun el flujo definido.
- No se debe permitir aprobar pagos de compras que ya hayan expirado de forma inconsistente.

### 2.5 Cierre comercial al llegar la hora del sorteo

- Cuando llega la fecha y hora programada del sorteo, la rifa deja de aceptar nuevas reservas o compras.
- Esto evita ventas tardias o ambiguas cuando el numero ya esta a punto de jugarse o ya se jugo.
- Para efectos de integridad, no se deben aceptar comprobantes recibidos despues de la hora del sorteo.

### 2.6 Compras pendientes de revision cerca del sorteo

- Si el comprador envio su comprobante antes del cierre comercial, la compra puede seguir en revision manual.
- Mientras existan compras pendientes de validacion para esa rifa, no se debe publicar el resultado oficial dentro del sistema.
- Primero se resuelven esas compras y despues se publica el cierre oficial de la rifa.

### 2.7 Resultado oficial de la loteria externa

- El numero ganador oficial lo publica la loteria externa correspondiente.
- Rifax no define manualmente el numero ganador ni altera ese resultado.
- El rol operativo del administrador es registrar en la plataforma el resultado oficial ya publicado por la loteria y disparar el cierre interno de la rifa.

## 3. Transparencia hacia el comprador final

Lo recomendable es publicar una version corta y clara, no todo el detalle interno.

### 3.1 Lo que si conviene comunicar

- que los numeros elegidos se reservan temporalmente;
- que el comprador debe enviar su comprobante dentro del tiempo indicado;
- que el comprobante debe enviarse antes de la hora del sorteo para que la participacion sea valida;
- que una vez enviado el comprobante, el pago entra en revision manual;
- que la confirmacion final depende de esa revision;
- que el numero ganador oficial lo publica la loteria externa y Rifax lo replica para confirmar al ganador dentro de la plataforma;
- que al llegar la hora del sorteo ya no se aceptan nuevas compras para esa rifa;
- que el sistema protege la disponibilidad de los numeros durante el proceso de revision.

### 3.2 Lo que no hace falta exponer con tanto detalle

- reglas internas exactas de base de datos;
- tiempos tecnicos internos de limpieza o reconciliacion;
- validaciones operativas pensadas solo para administracion.

## 4. Texto sugerido para comprador final

> Tus numeros quedan reservados temporalmente mientras completas el pago.  
> Cuando envias tu comprobante por WhatsApp, tu compra pasa a revision manual.  
> El numero ganador oficial lo publica la loteria externa correspondiente y Rifax lo usa para confirmar al ganador dentro de la plataforma.  
> Te confirmaremos la aprobacion de tu compra y cualquier novedad por este mismo medio.

## 5. Recomendacion

Si, esto deberia existir en dos niveles:

- **Politica operativa interna** para el equipo;
- **Resumen de transparencia** para compradores dentro de la landing, FAQ o terminos del servicio.
