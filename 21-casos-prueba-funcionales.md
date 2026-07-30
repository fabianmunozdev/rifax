# Casos de Prueba Funcionales

## Objetivo
Definir una suite funcional de referencia para validar el backend del flujo conversacional, compras, pagos, FAQ y plantillas sin depender de interpretaciones ambiguas.

## Enfoque
- Priorizar feature tests e integracion.
- Cubrir flujo feliz y casos borde relevantes.
- Verificar siempre resultado funcional y persistencia asociada.
- Validar `conversation_states`, compras, pagos, boletos y mensajes cuando aplique.

## Convencion de Casos
Cada caso debe definir:
- precondiciones
- entrada o evento
- respuesta esperada
- cambios esperados en base de datos
- estado conversacional esperado

## Bloque 1: Menu y Enrutamiento
### `TC-WA-001` Bienvenida y menu principal
- Precondicion: cliente sin `conversation_state` activo.
- Evento: mensaje inicial del cliente.
- Esperado: respuesta de bienvenida con menu principal.
- DB: crear o actualizar `conversation_states.status = main_menu`.
- Validar: no se crea compra ni reserva.

### `TC-WA-002` Comando `MENU`
- Precondicion: cliente en cualquier estado transaccional.
- Evento: cliente envia `MENU`.
- Esperado: respuesta con menu principal.
- DB: limpiar contexto operativo segun reset suave.
- Validar: `conversation_states.status = main_menu`.

### `TC-WA-003` Comando `CANCELAR`
- Precondicion: cliente con reserva activa y flujo de compra en progreso.
- Evento: cliente envia `CANCELAR`.
- Esperado: mensaje de cancelacion del proceso.
- DB: liberar reserva si no existe pago en revision, limpiar contexto.
- Validar: `conversation_states.status = main_menu`.

### `TC-WA-004` Mensaje duplicado
- Precondicion: webhook ya proceso un mensaje con mismo identificador.
- Evento: llega el mismo mensaje otra vez.
- Esperado: no duplicar acciones criticas.
- DB: no crear segunda compra, reserva o pago.
- Validar: idempotencia.

## Bloque 2: Flujo de Compra
### `TC-WA-010` Inicio de compra
- Precondicion: cliente en `main_menu`.
- Evento: responde `1`.
- Esperado: sistema presenta rifa activa o seleccion de rifa.
- DB: `conversation_states.status = purchase_select_raffle`.

### `TC-WA-011` Seleccion de rifa valida
- Precondicion: cliente en `purchase_select_raffle`.
- Evento: elige una rifa valida.
- Esperado: sistema solicita cantidad.
- DB: guardar `current_raffle_id`.
- Validar: `conversation_states.status = purchase_enter_quantity`.

### `TC-WA-012` Cantidad menor al minimo
- Precondicion: rifa con `min_numbers_per_purchase = 3`.
- Evento: cliente responde `1`.
- Esperado: mensaje de error por minimo no cumplido.
- DB: no crear compra ni reserva.
- Validar: permanece en `purchase_enter_quantity`.

### `TC-WA-013` Cantidad valida
- Precondicion: cliente en `purchase_enter_quantity`.
- Evento: responde cantidad valida.
- Esperado: sistema solicita modo de seleccion.
- DB: guardar `requested_quantity`.
- Validar: `conversation_states.status = purchase_choose_mode`.

### `TC-WA-014` Seleccion manual
- Precondicion: cliente en `purchase_choose_mode`.
- Evento: responde `1`.
- Esperado: sistema solicita numeros.
- DB: `selection_mode = manual`.
- Validar: `conversation_states.status = purchase_select_numbers`.

### `TC-WA-015` Seleccion aleatoria
- Precondicion: cliente en `purchase_choose_mode`.
- Evento: responde `2`.
- Esperado: sistema prepara asignacion aleatoria.
- DB: `selection_mode = random`.
- Validar: `conversation_states.status = purchase_random_assignment`.

### `TC-WA-016` Lista manual valida
- Precondicion: cliente en `purchase_select_numbers` con cantidad solicitada de 2.
- Evento: envia dos numeros disponibles y validos.
- Esperado: se crea reserva y se devuelven instrucciones de pago.
- DB: crear `reservation`, `purchase`, `purchase_numbers`, actualizar `raffle_numbers`.
- Validar: `conversation_states.status = purchase_payment_instructions`.

### `TC-WA-017` Lista manual con numero no disponible
- Precondicion: uno de los numeros ya reservado o pagado.
- Evento: cliente envia lista con numero no disponible.
- Esperado: mensaje proponiendo elegir otros o aleatorios.
- DB: no crear reserva nueva.
- Validar: permanece en `purchase_select_numbers`.

### `TC-WA-018` Asignacion aleatoria exitosa
- Precondicion: existen suficientes numeros disponibles.
- Evento: cliente acepta propuesta aleatoria.
- Esperado: reserva creada e instrucciones de pago enviadas.
- DB: igual que en reserva manual.
- Validar: `conversation_states.status = purchase_payment_instructions`.

### `TC-WA-019` Reserva expirada
- Precondicion: compra pendiente con reserva activa ya vencida.
- Evento: job de expiracion corre o usuario intenta continuar tarde.
- Esperado: numeros liberados y mensaje de expiracion.
- DB: `reservations` expirada, `raffle_numbers` vuelven a disponibles, compra a `expired`.
- Validar: `conversation_states.status = purchase_expired`.

## Bloque 3: Pagos
### `TC-WA-030` Comprobante recibido por WhatsApp
- Precondicion: cliente en `purchase_payment_instructions` con compra activa.
- Evento: envia imagen o evidencia valida como comprobante.
- Esperado: mensaje de compra en revision.
- DB: crear `payment`, crear `payment_proof`, compra a `payment_submitted`.
- Validar: `conversation_states.status = purchase_under_review` o paso intermedio segun implementacion.

### `TC-WA-031` Comprobante fuera de contexto
- Precondicion: cliente en `main_menu` sin compra activa pendiente.
- Evento: envia comprobante.
- Esperado: mensaje indicando que no hay compra activa o derivacion.
- DB: no crear `payment` huerfano.

### `TC-WA-032` Pago aprobado
- Precondicion: compra con pago en revision.
- Evento: admin aprueba pago.
- Esperado: se envia confirmacion y acceso al boleto.
- DB: pago `approved`, compra `paid`, numeros `paid`, boleto generado.
- Validar: `conversation_states.status = purchase_paid`.

### `TC-WA-033` Pago rechazado
- Precondicion: compra con pago en revision.
- Evento: admin rechaza con motivo.
- Esperado: se informa rechazo y se solicita nuevo comprobante.
- DB: pago `rejected`, compra en estado consistente de reproceso segun politica.
- Validar: `conversation_states.status = purchase_rejected`.

### `TC-WA-034` Reenvio de comprobante tras rechazo
- Precondicion: cliente en `purchase_rejected`.
- Evento: envia nuevo comprobante.
- Esperado: vuelve a revision.
- DB: nuevo registro o nueva version segun politica de pagos.
- Validar: `conversation_states.status = purchase_under_review`.

### `TC-WA-035` Doble aprobacion de pago
- Precondicion: pago ya aprobado.
- Evento: intento adicional de aprobar.
- Esperado: rechazo idempotente de la accion.
- DB: no crear segundo boleto ni cambiar estados.

## Bloque 4: Boletos
### `TC-TK-001` Generacion de boleto tras aprobacion
- Precondicion: pago aprobado.
- Evento: listener o job de generacion.
- Esperado: boleto generado con codigo y URL publica.
- DB: crear `tickets`, almacenar rutas y timestamps.

### `TC-TK-002` Verificacion publica del boleto
- Precondicion: existe boleto valido.
- Evento: consulta a endpoint publico de verificacion.
- Esperado: devuelve estado, rifa, numeros y datos del sorteo.
- Validar: no expone nombre ni telefono del comprador.

### `TC-TK-003` Evitar boleto duplicado
- Precondicion: compra ya tiene boleto valido.
- Evento: se intenta regenerar sin motivo autorizado.
- Esperado: no duplicar sin control.
- DB: mantener integridad o registrar nueva version con trazabilidad.

## Bloque 5: FAQ y Catalogo
### `TC-FAQ-001` FAQ fija
- Precondicion: existe entrada `faq.payment.methods` publicada.
- Evento: cliente pregunta como pagar.
- Esperado: respuesta desde `content_entries`.
- DB: no invocar IA.
- Validar: coincide con entrada publicada.

### `TC-FAQ-002` FAQ parametrizada
- Precondicion: existe entrada `faq.draw.date` publicada y rifa activa.
- Evento: cliente pregunta fecha del sorteo.
- Esperado: respuesta con variables resueltas.
- Validar: usa datos reales de rifa.

### `TC-FAQ-003` Entrada en draft no visible
- Precondicion: existe entrada relevante pero en `draft`.
- Evento: cliente hace pregunta correspondiente.
- Esperado: no usar entrada en draft.
- Validar: se usa otra publicada o fallback controlado.

### `TC-FAQ-004` Sin datos suficientes en FAQ parametrizada
- Precondicion: falta una variable necesaria.
- Evento: cliente hace pregunta correspondiente.
- Esperado: usar `fallback_text` o derivar a soporte.
- Validar: no invocar IA automaticamente.

### `TC-FAQ-005` Escalamiento a IA bloqueado por defecto
- Precondicion: entrada con `is_ai_eligible = false`.
- Evento: consulta relacionada no resuelta del todo.
- Esperado: no invocar IA; usar fallback o soporte.

## Bloque 6: Plantillas 24 Horas
### `TC-TPL-001` Reanudar compra fuera de ventana
- Precondicion: cliente fuera de la ventana de 24 horas y compra con reserva o proceso retomable.
- Evento: job o accion de recontacto.
- Esperado: envio de plantilla `purchase_resume`.
- DB: registrar mensaje saliente y marca en metadata si aplica.

### `TC-TPL-002` Recordatorio de pago fuera de ventana
- Precondicion: compra pendiente valida fuera de ventana.
- Evento: disparador operativo.
- Esperado: plantilla `payment_reminder`.
- Validar: no enviar si la compra ya fue pagada o expiro.

### `TC-TPL-003` Pago aprobado fuera de ventana
- Precondicion: compra pagada y cliente fuera de ventana.
- Evento: notificacion de aprobacion.
- Esperado: plantilla `payment_approved_ticket`.

## Bloque 7: Conversation States
### `TC-CS-001` Creacion inicial de estado conversacional
- Precondicion: cliente nuevo.
- Evento: primer mensaje.
- Esperado: crear `conversation_states`.
- Validar: una sola fila por cliente y canal.

### `TC-CS-002` Lock de procesamiento
- Precondicion: dos workers reciben eventos casi simultaneos del mismo cliente.
- Evento: procesamiento concurrente.
- Esperado: un solo worker modifica el estado de forma efectiva.
- Validar: no se duplican transiciones ni reservas.

### `TC-CS-003` Reset suave con MENU
- Precondicion: estado transaccional con contexto parcial.
- Evento: `MENU`.
- Esperado: limpiar seleccion y volver a `main_menu`.

### `TC-CS-004` Reset duro por cancelacion
- Precondicion: flujo inconsistente o cancelado.
- Evento: `CANCELAR`.
- Esperado: limpiar contexto operativo completo segun politica.

## Bloque 8: Soporte Humano
### `TC-SP-001` Derivacion por problema de pago
- Precondicion: cliente con reclamo de pago.
- Evento: mensaje que coincide con regla de soporte.
- Esperado: derivacion o marcado de seguimiento humano.

### `TC-SP-002` Baja confianza en FAQ o IA
- Precondicion: consulta no resuelta con suficiencia.
- Evento: backend no encuentra respuesta confiable.
- Esperado: mensaje de soporte y no respuesta inventada.

## Minimo de Suite para MVP
- Casos criticos minimos a automatizar primero:
- `TC-WA-001`
- `TC-WA-012`
- `TC-WA-016`
- `TC-WA-019`
- `TC-WA-030`
- `TC-WA-032`
- `TC-WA-033`
- `TC-TK-002`
- `TC-FAQ-001`
- `TC-TPL-001`
- `TC-CS-002`

## Criterios de Aceptacion
- La suite cubre flujo feliz y errores relevantes del MVP.
- Cada caso tiene un resultado observable en respuesta y persistencia.
- Los casos priorizan evitar regresiones en compras, pagos y mensajes.
- La estrategia de pruebas protege el enfoque sin IA por defecto.
