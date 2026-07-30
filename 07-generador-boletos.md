# Generador de Boletos

## Disparador
Pago aprobado manualmente por un administrador.

## Objetivo
Generar un boleto visual, verificable y consistente con el branding de la empresa.

## Contenido
- Logo de la empresa.
- Nombre de la rifa.
- Imagen del premio.
- Numero o numeros comprados.
- Fecha del sorteo.
- Nombre de la loteria de referencia.
- Numero del sorteo de referencia.
- Hora del sorteo.
- Codigo unico del boleto.
- QR de verificacion.
- Texto legal o condiciones resumidas si aplica.

## Salidas
- PNG principal `1080x1350`.
- Miniatura `600x600`.
- URL publica del boleto.
- Registro interno de generacion.

## Reglas
- El boleto solo se genera para compras con pago aprobado.
- El codigo del boleto debe ser unico y no predecible.
- El QR debe resolver a una vista o endpoint de verificacion publica.
- La generacion debe ser idempotente o tener control de version.
- Si la imagen cambia por regeneracion, debe quedar trazabilidad.

## Verificacion
La verificacion publica debe mostrar como minimo:
- Estado del boleto.
- Nombre de la rifa.
- Numeros asociados.
- Fecha del sorteo.
- Loteria de referencia.
- Numero del sorteo.
- Hora del sorteo.
- Codigo del boleto.

No debe mostrar nombre, telefono ni otros datos sensibles del comprador.

## Almacenamiento
- Guardar imagen final y miniatura en almacenamiento persistente.
- Guardar rutas, timestamps y version del boleto.
- Definir politica de regeneracion y reemplazo.

## Casos Borde
- Si falla la generacion de imagen, reintentar por job.
- Si ya existe boleto valido, evitar duplicados sin control.
- Si la rifa cambia branding luego del pago, definir si el boleto conserva snapshot historico.
