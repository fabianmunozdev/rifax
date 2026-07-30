# WhatsApp: Mensajes y Casos Borde

## Objetivo
Definir mensajes funcionales de referencia para el MVP y reducir ambiguedad en la implementacion del flujo conversacional.

## Principios de Mensajeria
- Mensajes cortos, claros y accionables.
- Una sola instruccion principal por mensaje.
- Usar listas, botones o respuestas numeradas cuando sea posible.
- Confirmar siempre lo que el sistema entendio antes de bloquear numeros o registrar acciones.
- Mantener tono amable, directo y confiable.

## Mensaje de Bienvenida
Ejemplo:

```text
Hola, soy el asistente de Rifax.

Estas son tus opciones:
1. Comprar
2. Numeros disponibles
3. Mis numeros
4. Estadisticas
5. Proximas rifas
6. Condiciones
7. Ayuda

Responde con el numero de la opcion.
```

## Flujo de Compra
### Seleccion de Rifa
```text
Tenemos esta rifa activa:
{raffle_title}

Premio: {prize_name}
Valor por numero: {price}
Sorteo: {lottery_name} #{lottery_draw_number}
Fecha: {draw_date} {draw_time}

Responde:
1. Continuar
2. Volver al menu
```

### Solicitud de Cantidad
```text
Cuantos numeros deseas comprar?

Compra minima para esta rifa: {min_numbers_per_purchase}
```

### Error por Minimo No Cumplido
```text
La cantidad minima permitida para esta rifa es {min_numbers_per_purchase} numero(s).

Por favor responde con una cantidad igual o mayor.
```

### Seleccion de Modo
```text
Como deseas elegir tus numeros?
1. Elegir manualmente
2. Asignacion aleatoria
```

### Confirmacion de Reserva
```text
Listo. Hemos reservado estos numeros para ti:
{reserved_numbers}

Total a pagar: {total_amount}
Tiempo limite de reserva: {reservation_expires_in}

Envia tu comprobante de pago por este chat para continuar.
```

### Compra en Revision
```text
Hemos recibido tu comprobante y tu compra esta en revision.

Te avisaremos por este medio cuando el pago sea aprobado o rechazado.
```

### Pago Aprobado
```text
Tu pago fue aprobado.

Aqui tienes tu boleto:
{ticket_url}

Codigo: {ticket_code}
```

### Pago Rechazado
```text
Tu pago no pudo ser aprobado.

Motivo: {rejection_reason}

Por favor revisa la informacion y envia un nuevo comprobante por este chat.
```

## Consultas Complementarias
### Numeros Disponibles
```text
Numeros disponibles para {raffle_title}:
{available_numbers_preview}

Si deseas comprar, responde 1.
Si deseas volver al menu, responde 0.
```

### Mis Numeros
```text
Estas son tus compras registradas:
{purchases_summary}

Si una compra ya fue aprobada, aqui veras tambien el acceso a tu boleto.
```

### Estadisticas
```text
Estado actual de {raffle_title}:
- Vendidos: {sold_count}
- Disponibles: {available_count}
- Sorteo: {lottery_name} #{lottery_draw_number}
- Fecha: {draw_date} {draw_time}
```

## Casos Borde
### Mensaje Fuera de Flujo
```text
No entendi esa respuesta dentro del proceso actual.

Responde con una opcion valida o escribe MENU para volver al inicio.
```

### Reserva Vencida
```text
Tu reserva ya vencio y los numeros fueron liberados.

Si deseas continuar, responde 1 para iniciar una nueva compra.
```

### Numero No Disponible
```text
Uno o mas numeros ya no estan disponibles.

Puedes:
1. Elegir otros numeros
2. Recibir numeros aleatorios
3. Volver al menu
```

### Audio o Archivo No Esperado
```text
Por ahora solo puedo procesar texto y comprobantes de pago en el paso correspondiente.

Si necesitas ayuda, responde 7 o escribe MENU.
```

### Ventana de 24 Horas Cerrada
```text
Necesitamos retomar tu proceso con un mensaje autorizado.

Te enviaremos una plantilla aprobada para continuar.
```

### Ayuda y Soporte
```text
Puedo ayudarte con:
1. Condiciones de la rifa
2. Metodos de pago
3. Estado de tu compra
4. Hablar con soporte
```

## Reglas Operativas
- Los textos exactos pueden ajustarse, pero deben conservar el sentido funcional.
- Toda confirmacion importante debe incluir datos verificables: rifa, numeros, monto o codigo.
- Los mensajes de error deben explicar la accion siguiente.
- El bot debe aceptar `MENU` como escape global al menu principal.
