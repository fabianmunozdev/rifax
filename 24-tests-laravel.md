# Tests Laravel

## Objetivo
Traducir la matriz funcional del proyecto a una estructura concreta de pruebas para Laravel 12 que sirva como base real de implementacion.

## Principios
- Priorizar `Feature` tests para flujos de negocio y endpoints.
- Usar `Unit` tests solo donde encapsulen reglas complejas reutilizables.
- Cada test debe validar respuesta observable y persistencia relevante.
- Los flujos criticos deben correr sin depender de servicios externos reales.
- La estrategia de pruebas debe reforzar el enfoque sin IA por defecto.

## Estructura Recomendada
```text
tests/
  Feature/
    WhatsApp/
      Webhook/
        ReceiveMessageTest.php
        IgnoreDuplicateWebhookTest.php
      Flow/
        ShowMainMenuTest.php
        StartPurchaseFlowTest.php
        QuantityValidationTest.php
        ManualNumberSelectionTest.php
        RandomNumberSelectionTest.php
        MenuAndCancelCommandsTest.php
      Payments/
        ReceivePaymentProofTest.php
        RejectOutOfContextProofTest.php
      Templates/
        SendResumeTemplateTest.php
        SendPaymentReminderTemplateTest.php
    Purchases/
      CreatePurchaseFromReservationTest.php
      ExpireReservationTest.php
      ReleaseReservationExceptionallyTest.php
    Payments/
      ApprovePaymentTest.php
      RejectPaymentTest.php
      PreventDoubleApprovalTest.php
    Tickets/
      GenerateTicketAfterApprovalTest.php
      VerifyPublicTicketTest.php
      PreventDuplicateTicketGenerationTest.php
    FAQ/
      ResolveFixedFaqTest.php
      ResolveParameterizedFaqTest.php
      IgnoreDraftContentEntryTest.php
      PreventAiFallbackByDefaultTest.php
    Conversations/
      CreateConversationStateTest.php
      SoftResetConversationStateTest.php
      HardResetConversationStateTest.php
      PreventConcurrentConversationProcessingTest.php
    Filament/
      Payments/
        ApprovePaymentActionTest.php
        RejectPaymentActionTest.php
      ContentEntries/
        PublishContentEntryActionTest.php
        ArchiveContentEntryActionTest.php
      Conversations/
        SoftResetConversationActionTest.php
        HardResetConversationActionTest.php
  Unit/
    Domain/
      Reservations/
        ReservationExpiryCalculatorTest.php
        NumberAvailabilityServiceTest.php
      Conversations/
        ConversationStateResolverTest.php
        ConversationResetPolicyTest.php
        ConversationLockServiceTest.php
      FAQ/
        ContentEntryResolverTest.php
        AllowedVariablesValidatorTest.php
      Payments/
        PaymentApprovalPolicyTest.php
        PaymentStatusTransitionTest.php
      Tickets/
        TicketVerificationPayloadBuilderTest.php
  Factories/
    CustomerFactory.php
    RaffleFactory.php
    RaffleNumberFactory.php
    ReservationFactory.php
    PurchaseFactory.php
    PaymentFactory.php
    TicketFactory.php
    WhatsappMessageFactory.php
    ConversationStateFactory.php
    ContentEntryFactory.php
  Helpers/
    CreatesWhatsappPayloads.php
    CreatesPurchaseScenarios.php
    AssertsConversationState.php
    AssertsAuditLogs.php
```

## Base de Test
### `tests/TestCase.php`
Debe incluir:
- helpers compartidos
- configuracion comun
- traits reutilizables cuando aporten valor

### Traits Recomendados
- `RefreshDatabase`
- helper para fake de colas
- helper para fake de eventos
- helper para fake de notificaciones si aplica

## Convencion de Naming
- Un archivo por comportamiento principal.
- El nombre del test debe comunicar la regla de negocio.
- Preferir nombres como `ApprovePaymentTest` en lugar de nombres tecnicos vagos.

## Mapeo desde Casos Funcionales
### WhatsApp y Conversacion
- `TC-WA-001` -> `Feature/WhatsApp/Flow/ShowMainMenuTest.php`
- `TC-WA-002` y `TC-WA-003` -> `Feature/WhatsApp/Flow/MenuAndCancelCommandsTest.php`
- `TC-WA-004` -> `Feature/WhatsApp/Webhook/IgnoreDuplicateWebhookTest.php`
- `TC-WA-010` y `TC-WA-011` -> `Feature/WhatsApp/Flow/StartPurchaseFlowTest.php`
- `TC-WA-012` -> `Feature/WhatsApp/Flow/QuantityValidationTest.php`
- `TC-WA-014`, `TC-WA-016`, `TC-WA-017` -> `Feature/WhatsApp/Flow/ManualNumberSelectionTest.php`
- `TC-WA-015`, `TC-WA-018` -> `Feature/WhatsApp/Flow/RandomNumberSelectionTest.php`
- `TC-CS-001` -> `Feature/Conversations/CreateConversationStateTest.php`
- `TC-CS-002` -> `Feature/Conversations/PreventConcurrentConversationProcessingTest.php`
- `TC-CS-003` -> `Feature/Conversations/SoftResetConversationStateTest.php`
- `TC-CS-004` -> `Feature/Conversations/HardResetConversationStateTest.php`

### Reservas, Compras y Pagos
- `TC-WA-019` -> `Feature/Purchases/ExpireReservationTest.php`
- `TC-WA-030` -> `Feature/WhatsApp/Payments/ReceivePaymentProofTest.php`
- `TC-WA-031` -> `Feature/WhatsApp/Payments/RejectOutOfContextProofTest.php`
- `TC-WA-032` -> `Feature/Payments/ApprovePaymentTest.php`
- `TC-WA-033` -> `Feature/Payments/RejectPaymentTest.php`
- `TC-WA-035` -> `Feature/Payments/PreventDoubleApprovalTest.php`

### Boletos
- `TC-TK-001` -> `Feature/Tickets/GenerateTicketAfterApprovalTest.php`
- `TC-TK-002` -> `Feature/Tickets/VerifyPublicTicketTest.php`
- `TC-TK-003` -> `Feature/Tickets/PreventDuplicateTicketGenerationTest.php`

### FAQ y Plantillas
- `TC-FAQ-001` -> `Feature/FAQ/ResolveFixedFaqTest.php`
- `TC-FAQ-002` -> `Feature/FAQ/ResolveParameterizedFaqTest.php`
- `TC-FAQ-003` -> `Feature/FAQ/IgnoreDraftContentEntryTest.php`
- `TC-FAQ-005` -> `Feature/FAQ/PreventAiFallbackByDefaultTest.php`
- `TC-TPL-001` -> `Feature/WhatsApp/Templates/SendResumeTemplateTest.php`
- `TC-TPL-002` -> `Feature/WhatsApp/Templates/SendPaymentReminderTemplateTest.php`

## Tests de Filament
### Objetivo
Proteger acciones administrativas de alto riesgo sin testear cada pixel del panel.

### Cobertura recomendada
- `ApprovePaymentActionTest`
- `RejectPaymentActionTest`
- `PublishContentEntryActionTest`
- `ArchiveContentEntryActionTest`
- `SoftResetConversationActionTest`
- `HardResetConversationActionTest`

### Que validar
- permisos
- validaciones
- cambio de estado esperado
- auditoria
- side effects criticos

## Factories Minimas Recomendadas
### `CustomerFactory`
- `phone`
- `name`
- `wa_id`

### `RaffleFactory`
- estados `draft`, `published`, `closed`
- `min_numbers_per_purchase`
- `draw_date`, `draw_time`

### `RaffleNumberFactory`
- `raffle_id`
- `number`
- `status`

### `ReservationFactory`
- `customer_id`
- `raffle_id`
- `status`
- `expires_at`

### `PurchaseFactory`
- `customer_id`
- `raffle_id`
- `status`
- `total_amount`

### `PaymentFactory`
- `purchase_id`
- `status`
- `expected_amount`
- `received_amount`

### `ConversationStateFactory`
- `customer_id`
- `channel = whatsapp`
- `status`
- `metadata_json`

### `ContentEntryFactory`
- `type`
- `key`
- `status`
- `trigger_intent`
- `is_ai_eligible`

## States Utiles en Factories
### `RaffleFactory`
- `published()`
- `withMinNumbers(int $value)`
- `withLotteryReference()`

### `RaffleNumberFactory`
- `available()`
- `reserved()`
- `paid()`

### `PurchaseFactory`
- `pendingPayment()`
- `underReview()`
- `paid()`
- `rejected()`

### `PaymentFactory`
- `pendingReview()`
- `approved()`
- `rejected()`

### `ConversationStateFactory`
- `mainMenu()`
- `selectingQuantity()`
- `paymentInstructions()`
- `underReview()`

### `ContentEntryFactory`
- `draft()`
- `published()`
- `faqFixed()`
- `faqParameterized()`

## Seeders de Soporte
### `Database\Seeders\TestCatalogSeeder`
Debe cargar:
- FAQ fijas minimas
- mensajes de sistema minimos
- plantillas puente minimas

### `Database\Seeders\TestRaffleSeeder`
Debe cargar:
- una rifa publicada
- numeros disponibles base
- metodos de pago base

## Helpers Recomendados
### `CreatesWhatsappPayloads`
Construir payloads consistentes para:
- texto entrante
- imagen de comprobante
- mensaje duplicado
- mensaje interactivo

### `CreatesPurchaseScenarios`
Permitir crear rapidamente:
- cliente con rifa publicada
- reserva activa
- compra en revision
- compra pagada

### `AssertsConversationState`
Atajos para validar:
- `status`
- `current_raffle_id`
- `requested_quantity`
- `selection_mode`
- `purchase_id`

### `AssertsAuditLogs`
Atajos para validar:
- accion registrada
- usuario actor
- entidad afectada

## Uso de Fakes
- `Queue::fake()` para jobs de expiracion, generacion de boleto y campañas.
- `Event::fake()` para eventos de dominio cuando no se quiere probar listeners reales.
- `Storage::fake()` para comprobantes y boletos.
- `Http::fake()` para integraciones con WhatsApp Cloud API.

## Datos que Siempre Deben Validarse
### En flujo conversacional
- `conversation_states`
- `whatsapp_messages`

### En compras
- `reservations`
- `purchases`
- `purchase_numbers`
- `raffle_numbers`

### En pagos
- `payments`
- `payment_proofs`

### En boletos
- `tickets`
- `ticket_verifications` cuando aplique

### En FAQ
- `content_entries`

## Piramide Recomendada
- Base MVP:
  - 12 a 18 `Feature` tests de alto valor
  - 6 a 10 `Unit` tests de reglas sensibles
  - 4 a 6 tests de acciones Filament

## Suite Minima Inicial
Implementar primero:
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

## Suite de Segunda Ola
- `IgnoreDuplicateWebhookTest`
- `RandomNumberSelectionTest`
- `PreventDoubleApprovalTest`
- `ResolveParameterizedFaqTest`
- `IgnoreDraftContentEntryTest`
- `SoftResetConversationActionTest`
- `HardResetConversationActionTest`

## Criterios de Aceptacion
- Existe una estructura clara de tests `Feature` y `Unit` alineada con el dominio.
- Los casos funcionales tienen correspondencia directa con clases de prueba.
- Existen factories y helpers suficientes para escribir tests sin friccion excesiva.
- La suite protege reservas, pagos, boletos, FAQ y estados conversacionales.
