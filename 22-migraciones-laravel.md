# Migraciones Laravel y PostgreSQL

## Objetivo
Traducir la especificacion funcional y de datos a un plan realista de migraciones para Laravel 12 con PostgreSQL.

## Alcance
Este documento prioriza:
- orden de migraciones
- dependencias entre tablas
- columnas reales sugeridas
- indices
- foreign keys
- decisiones de integridad para `conversation_states` y `content_entries`

## Principios
- Preferir columnas `string` + `check constraint` sobre `enum` nativo para facilitar evolucion.
- Usar `jsonb` en PostgreSQL para payloads flexibles.
- Agregar indices desde la primera migracion cuando soporten consultas operativas frecuentes.
- Mantener nombres de columnas consistentes con la documentacion funcional.
- Evitar logica de negocio dentro de la migracion; la migracion define estructura e integridad base.

## Orden Recomendado de Migraciones
### Fase 1: Base
1. `create_users_table`
2. `create_customers_table`
3. `create_company_settings_table`
4. `create_payment_methods_table`
5. `create_raffles_table`
6. `create_raffle_numbers_table`

### Fase 2: Compra y Pago
7. `create_reservations_table`
8. `create_purchases_table`
9. `create_purchase_numbers_table`
10. `create_payments_table`
11. `create_payment_proofs_table`

### Fase 3: Boletos y Mensajeria
12. `create_tickets_table`
13. `create_ticket_verifications_table`
14. `create_whatsapp_messages_table`
15. `create_conversation_states_table`

### Fase 4: Catalogo y Operacion
16. `create_content_entries_table`
17. `create_campaigns_table`
18. `create_campaign_runs_table`
19. `create_campaign_recipients_table`
20. `create_audit_logs_table`

## Notas de Dependencia
- `conversation_states` depende de `customers` y opcionalmente de `raffles`, `reservations`, `purchases`, `payments`, `whatsapp_messages`.
- `content_entries` depende opcionalmente de `users` para `created_by` y `updated_by`.
- `purchase_numbers` depende de `purchases` y `raffle_numbers`.
- `payment_proofs` depende de `payments`.

## Tabla `customers`
### Archivo sugerido
`2026_07_01_000100_create_customers_table.php`

### Columnas sugeridas
```php
$table->id();
$table->string('phone')->unique();
$table->string('name')->nullable();
$table->string('wa_id')->nullable()->index();
$table->timestamp('last_interaction_at')->nullable()->index();
$table->timestamps();
```

### Observaciones
- `phone` debe almacenarse en formato internacional normalizado.
- `wa_id` puede ser util para payload oficial de Meta.

## Tabla `raffles`
### Archivo sugerido
`2026_07_01_000500_create_raffles_table.php`

### Columnas sugeridas
```php
$table->id();
$table->string('title');
$table->string('slug')->unique();
$table->text('description')->nullable();
$table->string('status')->default('draft')->index();
$table->unsignedInteger('min_numbers_per_purchase')->default(1);
$table->string('lottery_name')->nullable();
$table->string('lottery_draw_number')->nullable();
$table->date('draw_date')->nullable()->index();
$table->time('draw_time')->nullable();
$table->string('lottery_reference_url')->nullable();
$table->decimal('price_per_number', 12, 2);
$table->unsignedInteger('reservation_timeout_minutes')->default(15);
$table->string('cover_image_path')->nullable();
$table->timestamps();
```

### Check constraints sugeridos
- `min_numbers_per_purchase >= 1`
- `reservation_timeout_minutes >= 1`

## Tabla `whatsapp_messages`
### Archivo sugerido
`2026_07_01_001400_create_whatsapp_messages_table.php`

### Columnas sugeridas
```php
$table->id();
$table->foreignId('customer_id')->constrained()->cascadeOnDelete();
$table->string('direction')->index(); // inbound | outbound
$table->string('message_type')->index(); // text | image | template | interactive | other
$table->string('provider_message_id')->nullable()->unique();
$table->text('body_text')->nullable();
$table->jsonb('payload_json')->nullable();
$table->string('status')->nullable()->index(); // sent | delivered | read | failed
$table->timestamp('provider_created_at')->nullable()->index();
$table->timestamps();
```

### Indices sugeridos
- `index(['customer_id', 'created_at'])`
- `index(['direction', 'created_at'])`

## Tabla `conversation_states`
### Archivo sugerido
`2026_07_01_001500_create_conversation_states_table.php`

### Objetivo tecnico
Mantener una sola fila activa por cliente y canal con el contexto minimo necesario para reconstruir el siguiente paso del flujo.

### Columnas sugeridas
```php
$table->id();
$table->foreignId('customer_id')->constrained()->cascadeOnDelete();
$table->string('channel')->default('whatsapp');
$table->string('status')->default('main_menu')->index();
$table->string('substatus')->nullable();
$table->foreignId('current_raffle_id')->nullable()->constrained('raffles')->nullOnDelete();
$table->unsignedInteger('requested_quantity')->nullable();
$table->string('selection_mode')->nullable();
$table->jsonb('selected_numbers_json')->nullable();
$table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
$table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
$table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
$table->foreignId('last_inbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
$table->foreignId('last_outbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
$table->timestamp('last_user_message_at')->nullable()->index();
$table->timestamp('last_bot_message_at')->nullable()->index();
$table->timestamp('context_expires_at')->nullable()->index();
$table->timestamp('locked_at')->nullable()->index();
$table->string('locked_by')->nullable();
$table->jsonb('metadata_json')->nullable();
$table->timestamps();
```

### Indices sugeridos
```php
$table->unique(['customer_id', 'channel']);
$table->index(['status', 'updated_at']);
$table->index(['current_raffle_id', 'status']);
$table->index(['purchase_id', 'status']);
```

### Check constraints sugeridos
- `requested_quantity is null or requested_quantity >= 1`
- `selection_mode in ('manual', 'random') or selection_mode is null`
- `channel in ('whatsapp')`
- `status` restringido al catalogo documentado

### Catalogo sugerido para `status`
- `main_menu`
- `purchase_select_raffle`
- `purchase_enter_quantity`
- `purchase_choose_mode`
- `purchase_select_numbers`
- `purchase_random_assignment`
- `purchase_reservation_pending`
- `purchase_payment_instructions`
- `purchase_proof_received`
- `purchase_under_review`
- `purchase_paid`
- `purchase_rejected`
- `purchase_expired`
- `info_available_numbers`
- `info_my_numbers`
- `info_statistics`
- `info_upcoming_raffles`
- `info_conditions`
- `info_help`

### Ejemplo de constraint en PostgreSQL
```sql
alter table conversation_states
add constraint conversation_states_status_check
check (
  status in (
    'main_menu',
    'purchase_select_raffle',
    'purchase_enter_quantity',
    'purchase_choose_mode',
    'purchase_select_numbers',
    'purchase_random_assignment',
    'purchase_reservation_pending',
    'purchase_payment_instructions',
    'purchase_proof_received',
    'purchase_under_review',
    'purchase_paid',
    'purchase_rejected',
    'purchase_expired',
    'info_available_numbers',
    'info_my_numbers',
    'info_statistics',
    'info_upcoming_raffles',
    'info_conditions',
    'info_help'
  )
);
```

## Tabla `content_entries`
### Archivo sugerido
`2026_07_01_001600_create_content_entries_table.php`

### Objetivo tecnico
Soportar FAQ fija, FAQ parametrizada, mensajes de sistema y puentes de plantilla sin tocar codigo.

### Columnas sugeridas
```php
$table->id();
$table->string('type')->index();
$table->string('key');
$table->string('title');
$table->string('category')->index();
$table->string('locale')->default('es')->index();
$table->string('channel')->default('whatsapp')->index();
$table->string('status')->default('draft')->index();
$table->string('trigger_intent')->nullable()->index();
$table->text('body_text');
$table->jsonb('variables_json')->nullable();
$table->text('fallback_text')->nullable();
$table->unsignedInteger('priority')->default(100);
$table->boolean('is_ai_eligible')->default(false)->index();
$table->text('notes')->nullable();
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('published_at')->nullable()->index();
$table->timestamps();
```

### Indices sugeridos
```php
$table->unique(['key', 'locale', 'channel']);
$table->index(['type', 'status']);
$table->index(['category', 'status']);
$table->index(['trigger_intent', 'status']);
```

### Check constraints sugeridos
- `type in ('faq_fixed', 'faq_parametrized', 'system_message', 'support_message', 'template_bridge')`
- `status in ('draft', 'published', 'archived')`
- `priority >= 0`

## Tabla `payments`
### Archivo sugerido
`2026_07_01_001000_create_payments_table.php`

### Columnas sugeridas
```php
$table->id();
$table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
$table->string('status')->default('pending_review')->index();
$table->string('reference')->nullable()->index();
$table->decimal('expected_amount', 12, 2)->nullable();
$table->decimal('received_amount', 12, 2)->nullable();
$table->timestamp('proof_received_at')->nullable()->index();
$table->string('proof_channel')->default('whatsapp');
$table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('reviewed_at')->nullable()->index();
$table->text('rejection_reason')->nullable();
$table->timestamps();
```

### Check constraints sugeridos
- `status in ('pending_review', 'approved', 'rejected')`
- `proof_channel in ('whatsapp')`

## Tabla `tickets`
### Archivo sugerido
`2026_07_01_001200_create_tickets_table.php`

### Columnas sugeridas
```php
$table->id();
$table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
$table->string('code')->unique();
$table->string('verification_token')->unique();
$table->string('public_url')->nullable()->unique();
$table->string('image_path')->nullable();
$table->string('thumbnail_path')->nullable();
$table->unsignedInteger('version')->default(1);
$table->timestamp('generated_at')->nullable()->index();
$table->timestamps();
```

## Ejemplo de Migracion Laravel para `conversation_states`
```php
Schema::create('conversation_states', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->string('channel')->default('whatsapp');
    $table->string('status')->default('main_menu')->index();
    $table->string('substatus')->nullable();
    $table->foreignId('current_raffle_id')->nullable()->constrained('raffles')->nullOnDelete();
    $table->unsignedInteger('requested_quantity')->nullable();
    $table->string('selection_mode')->nullable();
    $table->jsonb('selected_numbers_json')->nullable();
    $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
    $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
    $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
    $table->foreignId('last_inbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
    $table->foreignId('last_outbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
    $table->timestamp('last_user_message_at')->nullable()->index();
    $table->timestamp('last_bot_message_at')->nullable()->index();
    $table->timestamp('context_expires_at')->nullable()->index();
    $table->timestamp('locked_at')->nullable()->index();
    $table->string('locked_by')->nullable();
    $table->jsonb('metadata_json')->nullable();
    $table->timestamps();

    $table->unique(['customer_id', 'channel']);
    $table->index(['status', 'updated_at']);
    $table->index(['current_raffle_id', 'status']);
    $table->index(['purchase_id', 'status']);
});
```

## Ejemplo de Migracion Laravel para `content_entries`
```php
Schema::create('content_entries', function (Blueprint $table) {
    $table->id();
    $table->string('type')->index();
    $table->string('key');
    $table->string('title');
    $table->string('category')->index();
    $table->string('locale')->default('es')->index();
    $table->string('channel')->default('whatsapp')->index();
    $table->string('status')->default('draft')->index();
    $table->string('trigger_intent')->nullable()->index();
    $table->text('body_text');
    $table->jsonb('variables_json')->nullable();
    $table->text('fallback_text')->nullable();
    $table->unsignedInteger('priority')->default(100);
    $table->boolean('is_ai_eligible')->default(false)->index();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('published_at')->nullable()->index();
    $table->timestamps();

    $table->unique(['key', 'locale', 'channel']);
    $table->index(['type', 'status']);
    $table->index(['category', 'status']);
    $table->index(['trigger_intent', 'status']);
});
```

## Recomendacion de Implementacion
- Crear primero las migraciones estructurales.
- Luego agregar `check constraints` con `DB::statement()` cuando Laravel schema builder no exprese bien la restriccion.
- Mantener nombres de indices legibles si el equipo usa convencion explicita.
- Acompanhar cada migracion clave con un feature test o migration smoke test si el proyecto lo amerita.

## Criterios de Aceptacion
- Las migraciones reflejan el dominio documentado.
- `conversation_states` soporta la maquina de estados sin hacks adicionales.
- `content_entries` permite operar FAQ y textos fijos desde panel.
- Las tablas criticas tienen indices y foreign keys utiles desde la primera version.
