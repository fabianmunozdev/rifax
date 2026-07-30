# FAQ y Enrutamiento sin IA

## Objetivo
Definir como responder preguntas frecuentes con el minimo uso posible de IA y dejar claro cuando se usa FAQ fija, datos del sistema, IA acotada o soporte humano.

## Principio Rector
La primera opcion nunca debe ser IA.

El orden correcto es:
1. Menu o flujo estructurado.
2. FAQ fija.
3. Respuesta parametrizada con datos del sistema.
4. IA acotada a base de conocimiento.
5. Soporte humano.

## Intenciones que No Deben Usar IA
- Comprar.
- Ver numeros disponibles.
- Ver mis numeros.
- Ver estado de compra.
- Enviar comprobante.
- Consultar fecha del sorteo.
- Consultar hora del sorteo.
- Consultar loteria de referencia.
- Pedir metodos de pago.
- Pedir condiciones.
- Solicitar ayuda de soporte.

## FAQ Fija Recomendada
### Condiciones
Pregunta tipo:
- "Cuales son las condiciones?"
- "Como funciona la rifa?"

Respuesta base:
```text
Estas son las condiciones principales de la rifa:
- La compra se realiza por este chat.
- Los numeros se reservan por tiempo limitado.
- El pago se confirma manualmente.
- El boleto se envia cuando el pago es aprobado.

Si deseas comprar, responde 1. Si deseas volver al menu, escribe MENU.
```

### Metodos de Pago
Pregunta tipo:
- "Como pago?"
- "Que cuentas tienen?"

Respuesta base:
```text
Puedes pagar usando los metodos habilitados por la empresa.

Cuando completes tu compra, te enviaremos las instrucciones exactas de pago por este chat.
```

### Fecha del Sorteo
Pregunta tipo:
- "Cuando es el sorteo?"

Respuesta parametrizada:
```text
El sorteo de {raffle_title} se realiza el {draw_date} a las {draw_time} usando como referencia {lottery_name} #{lottery_draw_number}.
```

### Estado de Compra
Pregunta tipo:
- "Mi pago ya quedo?"
- "En que va mi compra?"

Respuesta parametrizada:
```text
Tu compra actual se encuentra en estado: {purchase_status}.

Si ya fue aprobada, tambien puedes revisar tu boleto desde la opcion Mis numeros.
```

### Numeros Comprados
Pregunta tipo:
- "Que numeros tengo?"

Respuesta parametrizada:
```text
Estos son tus numeros registrados:
{purchases_summary}
```

### Reserva Expirada
Pregunta tipo:
- "Se vencio mi compra"

Respuesta base:
```text
Tu reserva ya no esta activa.

Si deseas continuar, responde 1 para iniciar una nueva compra.
```

## Casos donde Puede Entrar IA
- El usuario formula una pregunta abierta sobre reglas que no coincide con una FAQ exacta.
- El usuario pide una explicacion mas natural o resumida del reglamento.
- El usuario hace una duda abierta de soporte general que no requiere accion transaccional.

## Casos donde Debe Escalar a Humano
- El cliente insiste en un problema de pago no resuelto.
- Existe inconformidad con numeros, sorteo o boleto.
- Hay lenguaje agresivo, amenaza o reclamo sensible.
- La IA o FAQ no encuentra respuesta confiable.

## Reglas de Implementacion
- Toda FAQ fija debe existir como texto versionado y editable desde administracion o configuracion.
- Las respuestas parametrizadas deben usar datos confiables del sistema, no inferencias.
- La IA debe recibir solo contexto autorizado, nunca todo el historial completo sin filtro.
- Si una respuesta puede construirse por plantilla o datos, no invocar IA.
- El catalogo editable recomendado se detalla en [20-catalogo-faq-y-textos.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/20-catalogo-faq-y-textos.md).

## Criterios de Aceptacion
- Las intenciones frecuentes del comprador se resuelven sin IA.
- Las respuestas transaccionales salen de reglas o datos del sistema.
- El uso de IA queda limitado a preguntas abiertas no estructuradas.
- Existe una ruta clara de escalamiento a soporte humano.
