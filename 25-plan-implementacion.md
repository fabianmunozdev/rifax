# Plan de Implementacion

## Objetivo
Definir una secuencia de ejecucion concreta para construir el MVP de Rifax con el menor riesgo posible, aprovechando la documentacion ya cerrada de datos, panel, pruebas y flujos.

## Principios
- Construir primero cimientos tecnicos y reglas de negocio, luego interfaces e integraciones.
- No empezar por WhatsApp ni por Filament sin antes cerrar el dominio y las migraciones base.
- Proteger los flujos criticos con tests desde etapas tempranas.
- Priorizar el camino mas corto hacia un MVP operable y auditable.
- Minimizar IA desde el inicio: primero FAQ fija, luego fallback controlado.

## Resultado Esperado del MVP
Al final del plan debe existir un sistema que permita:
- crear rifas y numeros desde panel
- conversar por WhatsApp con flujo guiado de compra
- reservar numeros
- recibir comprobantes solo por WhatsApp
- aprobar o rechazar pagos desde panel
- generar y verificar boletos
- responder FAQ sin IA en la mayor parte de consultas
- mantener trazabilidad de acciones criticas

## Orden Recomendado
1. Entorno y bootstrap del proyecto.
2. Dominio base y migraciones.
3. Casos de uso nucleares y tests del dominio.
4. Panel admin para operar rifas, pagos y contenido.
5. Webhook de WhatsApp y maquina conversacional.
6. Generacion de boletos y verificacion publica.
7. Campanas operativas y hardening.
8. Cierre MVP y salida a entorno de prueba.

## Fase 0: Bootstrap
### Objetivo
Levantar el esqueleto tecnico del proyecto y dejar un entorno reproducible.

### Incluye
- inicializar proyecto Laravel 12
- configurar `Docker Compose`
- configurar `PostgreSQL`, `Redis` y variables de entorno
- definir uso de `pnpm` si se requiere tooling Node
- instalar Filament
- configurar autenticacion administrativa base
- preparar estructura de carpetas del dominio

### Entregables
- proyecto Laravel corriendo en local
- entorno dockerizado documentado
- login admin funcional
- CI minima o al menos comando local de test y lint

### Dependencias
- [15-desarrollo-local.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/15-desarrollo-local.md)

### Criterio de salida
- cualquier miembro puede levantar el proyecto y acceder al panel admin base

## Fase 1: Dominio Base y Migraciones
### Objetivo
Crear la estructura de datos principal antes de tocar la logica conversacional real.

### Incluye
- implementar migraciones base
- modelos Eloquent principales
- relaciones
- factories iniciales
- seeders de apoyo para desarrollo
- constraints e indices criticos

### Tablas Prioritarias
- `users`
- `customers`
- `company_settings`
- `payment_methods`
- `raffles`
- `raffle_numbers`
- `reservations`
- `purchases`
- `purchase_numbers`
- `payments`
- `payment_proofs`
- `tickets`
- `whatsapp_messages`
- `conversation_states`
- `content_entries`
- `audit_logs`

### Entregables
- migraciones ejecutables
- modelos y relaciones validadas
- factories basicas
- seed inicial de empresa, metodos de pago y rifa demo

### Dependencias
- [04-base-datos.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/04-base-datos.md)
- [19-conversation-states.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/19-conversation-states.md)
- [20-catalogo-faq-y-textos.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/20-catalogo-faq-y-textos.md)
- [22-migraciones-laravel.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/22-migraciones-laravel.md)

### Riesgos
- querer modelar demasiado pronto campañas avanzadas o IA
- dejar estados como texto libre sin controles

### Criterio de salida
- la base soporta rifas, compras, pagos, conversaciones y FAQ sin huecos estructurales

## Fase 2: Casos de Uso Nucleares
### Objetivo
Implementar el corazon del negocio antes del canal de WhatsApp.

### Incluye
- servicio de disponibilidad de numeros
- creacion y expiracion de reservas
- construccion de compras a partir de reservas
- registro de comprobantes
- aprobacion y rechazo de pagos
- generacion de boleto tras aprobacion
- resolucion de FAQ fija y parametrizada

### Casos de Uso Prioritarios
- `reserve_numbers`
- `expire_reservation`
- `submit_payment_proof`
- `approve_payment`
- `reject_payment`
- `generate_ticket`
- `resolve_content_entry`

### Entregables
- servicios o acciones de dominio implementados
- eventos y jobs donde aporten valor
- auditoria en acciones administrativas criticas

### Dependencias
- Fase 1 completa
- [21-casos-prueba-funcionales.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/21-casos-prueba-funcionales.md)
- [24-tests-laravel.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/24-tests-laravel.md)

### Criterio de salida
- el dominio puede ejecutarse por codigo o tests sin depender aun de WhatsApp real

## Fase 3: Suite de Tests Base
### Objetivo
Blindar primero el camino critico del MVP para que el resto del desarrollo no rompa reglas esenciales.

### Implementar Primero
- `ShowMainMenuTest`
- `QuantityValidationTest`
- `ManualNumberSelectionTest`
- `ExpireReservationTest`
- `ReceivePaymentProofTest`
- `ApprovePaymentTest`
- `RejectPaymentTest`
- `VerifyPublicTicketTest`
- `ResolveFixedFaqTest`
- `SendResumeTemplateTest`
- `PreventConcurrentConversationProcessingTest`

### Entregables
- primera suite roja/verde de alto valor
- helpers de WhatsApp
- helpers de asserts de conversacion y auditoria

### Dependencias
- [24-tests-laravel.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/24-tests-laravel.md)

### Criterio de salida
- los flujos criticos tienen proteccion automatizada suficiente para seguir construyendo

## Fase 4: Panel Administrativo MVP
### Objetivo
Habilitar operacion real del negocio desde Filament.

### Recursos a Implementar Primero
- `RaffleResource`
- `PaymentResource`
- `PurchaseResource`
- `ContentEntryResource`
- `ConversationResource`
- `CompanySettingsPage`
- `PaymentMethodResource`

### Prioridad Operativa
1. rifas
2. pagos
3. compras
4. FAQ y textos
5. configuracion de empresa
6. conversaciones

### Entregables
- panel capaz de crear rifas
- panel capaz de revisar pagos
- panel capaz de publicar FAQ y textos fijos
- configuracion editable de empresa y branding

### Dependencias
- [06-panel-admin.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/06-panel-admin.md)
- [23-recursos-filament.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/23-recursos-filament.md)

### Riesgos
- usar formularios CRUD para cambiar estados sensibles
- omitir auditoria en acciones de aprobacion, rechazo o reset

### Criterio de salida
- el equipo puede operar rifas, pagos y contenido sin tocar base de datos manualmente

## Fase 5: WhatsApp y Maquina Conversacional
### Objetivo
Conectar el dominio con WhatsApp Cloud API usando un flujo determinista.

### Incluye
- endpoint de webhook
- validacion de autenticidad
- parser de mensajes
- enrutamiento por estado
- persistencia en `whatsapp_messages`
- manejo de `conversation_states`
- comandos `MENU` y `CANCELAR`
- FAQ resuelta desde `content_entries`
- plantillas de 24 horas

### Camino de Implementacion
1. recibir mensaje
2. crear o recuperar cliente
3. cargar `conversation_state`
4. resolver intencion o estado actual
5. ejecutar caso de uso correspondiente
6. persistir mensajes y nuevo estado
7. responder por canal

### Entregables
- compra guiada desde WhatsApp
- recepcion de comprobante por imagen
- reanudacion de flujo con plantillas fuera de 24 horas

### Dependencias
- [03-flujos-whatsapp.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/03-flujos-whatsapp.md)
- [14-whatsapp-mensajes-y-casos.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/14-whatsapp-mensajes-y-casos.md)
- [16-maquina-estados-conversacional.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/16-maquina-estados-conversacional.md)
- [17-plantillas-whatsapp-24h.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/17-plantillas-whatsapp-24h.md)
- [18-faq-y-enrutamiento.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/18-faq-y-enrutamiento.md)
- [19-conversation-states.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/19-conversation-states.md)

### Criterio de salida
- un usuario puede completar el flujo principal de compra y comprobante desde WhatsApp

## Fase 6: Boletos y Verificacion Publica
### Objetivo
Cerrar el circuito transaccional del comprador.

### Incluye
- generacion de boleto en PNG
- URL publica de verificacion
- QR
- payload minimo de verificacion
- proteccion de datos sensibles

### Entregables
- boleto generado automaticamente tras pago aprobado
- endpoint o pagina publica de verificacion funcionando

### Dependencias
- [07-generador-boletos.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/07-generador-boletos.md)

### Criterio de salida
- cada compra pagada tiene boleto verificable sin exponer datos privados del comprador

## Fase 7: Campanas y Operacion Basica
### Objetivo
Agregar automatizaciones de valor sin desordenar el MVP.

### Implementar Primero
- recordatorio de pago
- recordatorio de sorteo
- proxima rifa

### Incluye
- jobs en cola
- trazabilidad de intentos
- reglas basicas para evitar duplicados

### Dependencias
- [08-campañas.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/08-campañas.md)

### Criterio de salida
- existen campañas operativas simples, medibles y seguras

## Fase 8: Hardening y Salida a Prueba
### Objetivo
Preparar el MVP para uso controlado en entorno real o preproductivo.

### Incluye
- revisar observabilidad y logs
- revisar auditoria
- revisar roles y permisos
- mejorar seeds demo
- validar rendimiento basico de consultas frecuentes
- correr suite de tests minima y segunda ola
- checklist de aceptacion funcional

### Entregables
- entorno de prueba estable
- documentacion operativa minima
- checklist de salida

### Dependencias
- [13-criterios-de-aceptacion.md](file:///Users/macbookpro/Documents/DESARROLLO/Rifax/13-criterios-de-aceptacion.md)

### Criterio de salida
- el MVP puede ser probado con usuarios controlados sin depender de ajustes manuales constantes

## Secuencia Recomendada de Sprints
### Sprint 1
- Fase 0
- Fase 1 parcial

### Sprint 2
- Fase 1 cierre
- Fase 2 inicio
- Fase 3 inicio

### Sprint 3
- Fase 2 cierre
- Fase 4 inicio

### Sprint 4
- Fase 4 cierre
- Fase 5 inicio

### Sprint 5
- Fase 5 cierre
- Fase 6

### Sprint 6
- Fase 7
- Fase 8

## Que No Hacer Primero
- no empezar por IA
- no empezar por campañas avanzadas
- no empezar por métricas sofisticadas
- no empezar por portal de cliente
- no empezar por integraciones de pago automatico

## Camino Mas Corto al MVP
Si se necesita recortar tiempo, la ruta minima es:
1. entorno
2. migraciones
3. reservas y pagos
4. panel de pagos y rifas
5. webhook y flujo principal
6. boleto y verificacion

Dejar para despues:
- campañas no esenciales
- reportes avanzados
- refinamientos visuales no criticos

## Checklist por Fase
Antes de cerrar una fase confirmar:
- codigo entregado
- tests relevantes pasando
- seeds o datos demo disponibles si aplica
- documento fuente actualizado si hubo cambio de criterio
- criterio de salida cumplido

## Criterios de Aceptacion
- Existe una secuencia clara para construir el MVP sin ambiguedad.
- Las dependencias entre dominio, panel, WhatsApp y pruebas estan ordenadas.
- El plan permite priorizar el camino mas corto hacia un sistema operable.
- El equipo puede usar este documento como guia de ejecucion real.
