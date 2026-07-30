# Prompt Testing

## Objetivo
Crear pruebas utiles para proteger los flujos criticos del negocio sin agregar ruido innecesario.

## Debe Cubrir
- Reserva de numeros y expiracion.
- Creacion de compra.
- Recepcion de comprobante.
- Aprobacion y rechazo de pago.
- Generacion de boleto.
- Verificacion publica de boleto.
- Ejecucion basica de campanas.
- Webhook de WhatsApp con idempotencia.
- Maquina de estados conversacional y `conversation_states`.
- FAQ fija, FAQ parametrizada y plantillas fuera de 24 horas.

## Enfoque
- Priorizar pruebas de integracion y feature tests.
- Usar unit tests donde encapsulen reglas de negocio complejas.
- Validar casos borde importantes.
- Usar [21-casos-prueba-funcionales.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/21-casos-prueba-funcionales.md) como matriz base de cobertura.
- Usar [24-tests-laravel.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/24-tests-laravel.md) como blueprint de estructura real de tests, factories y helpers.

## Casos Borde Minimos
- Numero ya reservado.
- Reserva vencida.
- Pago ya aprobado.
- Boleto duplicado.
- Mensaje de WhatsApp repetido.
- MENU y CANCELAR en estados transaccionales.
- Pregunta frecuente resuelta sin IA.
- Recontacto por plantilla fuera de ventana.
