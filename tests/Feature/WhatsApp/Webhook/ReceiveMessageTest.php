<?php

namespace Tests\Feature\WhatsApp\Webhook;

use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\ContentEntry;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\RafflePickerIntent;
use App\Models\RaffleNumber;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.webhook_app_secret', 'rifax-test-app-secret');
    }

    public function test_it_starts_the_purchase_flow_from_the_main_menu(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Principal',
        ]);

        RaffleNumber::factory()->for($raffle)->count(3)->create();

        Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1'));

        $response->assertOk()
            ->assertJsonPath('processed', true)
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_raffle');

        $this->assertDatabaseHas('customers', [
            'phone' => '+573001112233',
        ]);

        $this->assertDatabaseHas('conversation_states', [
            'status' => 'purchase_select_raffle',
            'current_raffle_id' => $raffle->id,
        ]);

        $this->assertStringContainsString('Rifa Principal', $response->json('responses.0.reply'));
    }

    public function test_it_requires_privacy_consent_and_identity_before_starting_a_purchase(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Onboarding',
        ]);

        RaffleNumber::factory()->for($raffle)->count(3)->create();

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_privacy_consent');

        $this->assertStringContainsString('ACEPTO', $response->json('responses.0.reply'));

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'ACEPTO'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_collect_name');

        $this->assertDatabaseHas('customers', [
            'phone' => '+573001112233',
        ]);

        $customer = Customer::query()->where('phone', '+573001112233')->firstOrFail();
        $this->assertNotNull($customer->accepted_privacy_at);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'Juan Perez'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_collect_document');

        $customer->refresh();
        $this->assertSame('Juan Perez', $customer->name);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '123.456.789'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_raffle');

        $customer->refresh();
        $this->assertSame('123456789', $customer->document_number);
        $this->assertStringContainsString('Rifa Onboarding', $response->json('responses.0.reply'));
    }

    public function test_it_requests_name_after_privacy_acceptance_even_if_whatsapp_profile_name_exists(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Perfil',
        ]);

        RaffleNumber::factory()->for($raffle)->count(3)->create();

        $response = $this->postSignedWhatsappWebhook($this->textPayload(
            '573001112233',
            '1',
            'wamid.profile-start',
            'FabianM',
        ));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_privacy_consent');

        $response = $this->postSignedWhatsappWebhook($this->textPayload(
            '573001112233',
            'ACEPTO',
            'wamid.profile-consent',
            'FabianM',
        ));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_collect_name');

        $this->assertStringContainsString('nombre completo', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_is_idempotent_when_meta_retries_the_same_inbound_message(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Duplicados',
        ]);

        RaffleNumber::factory()->for($raffle)->count(3)->create();

        $firstResponse = $this->postSignedWhatsappWebhook($this->textPayload(
            '573001112233',
            '1',
            'wamid.same-message-id',
        ));

        $firstResponse->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_privacy_consent');

        $secondResponse = $this->postSignedWhatsappWebhook($this->textPayload(
            '573001112233',
            '1',
            'wamid.same-message-id',
        ));

        $secondResponse->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_privacy_consent')
            ->assertJsonPath('responses.0.reply', $firstResponse->json('responses.0.reply'));

        $this->assertSame(1, WhatsappMessage::query()->where('direction', 'inbound')->count());
        $this->assertSame(1, WhatsappMessage::query()->where('direction', 'outbound')->count());
    }

    public function test_it_requires_onboarding_before_consuming_a_picker_intent_token(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Picker Onboarding',
            'number_digits' => 3,
            'min_numbers_per_purchase' => 2,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '001', 'status' => 'available']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '543', 'status' => 'available']);

        RafflePickerIntent::query()->create([
            'raffle_id' => $raffle->id,
            'token' => 'PICKERONB1',
            'quantity' => 2,
            'source' => 'landing_featured',
            'selected_numbers_json' => ['001', '543'],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'PICKER PICKERONB1'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_privacy_consent');

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'ACEPTO'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'onboarding_collect_name');

        $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'Cliente Onboarding'));
        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '900111222'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '001',
        ]);
        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '543',
        ]);
    }

    public function test_menu_command_returns_the_user_to_the_main_menu(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_enter_quantity',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'MENU'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('Estas son tus opciones', $response->json('responses.0.reply'));
    }

    public function test_it_validates_the_minimum_quantity_before_continuing(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->withMinNumbers(3)->create();

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_enter_quantity',
            'current_raffle_id' => $raffle->id,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_enter_quantity');

        $this->assertStringContainsString('La cantidad minima permitida', $response->json('responses.0.reply'));
    }

    public function test_it_requires_a_numeric_quantity_before_continuing(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create();

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_enter_quantity',
            'current_raffle_id' => $raffle->id,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'dos'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_enter_quantity');

        $this->assertStringContainsString('cantidad valida', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_accepts_manual_numbers_using_the_configured_raffle_digits(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 3,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-manual-3',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '023']);

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_select_numbers',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 2,
            'selection_mode' => 'manual',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1,23'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $this->assertStringContainsString('Opciones de pago disponibles', $response->json('responses.0.reply'));
        $this->assertStringContainsString('1. Transferencia', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Numero de cuenta: 123456789', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Titular: Rifax SAS', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Como pagar: Transfiere y envia el comprobante.', $response->json('responses.0.reply'));
        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '001',
        ]);
        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '023',
        ]);
    }

    public function test_manual_selection_prompt_includes_the_visual_picker_link(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Visual',
            'slug' => 'rifa-visual',
            'number_digits' => 3,
        ]);

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_choose_mode',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 2,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_numbers');

        $this->assertStringContainsString('/raffles/rifa-visual/number-picker?quantity=2&source=whatsapp_manual_prompt', $response->json('responses.0.reply'));
    }

    public function test_it_consumes_a_picker_intent_token_and_reserves_the_selected_numbers(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Picker',
            'number_digits' => 3,
            'min_numbers_per_purchase' => 2,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '001', 'status' => 'available']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '543', 'status' => 'available']);

        Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $intent = RafflePickerIntent::query()->create([
            'raffle_id' => $raffle->id,
            'token' => 'PICKERABC1',
            'quantity' => 2,
            'source' => 'landing_featured',
            'selected_numbers_json' => ['001', '543'],
            'metadata_json' => [
                'referrer_url' => 'https://rifax.test/',
                'picker_page_url' => 'https://rifax.test/raffles/rifa-picker/number-picker?quantity=2&source=landing_featured',
                'utm_source' => 'facebook',
                'utm_campaign' => 'lanzamiento',
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload(
            '573001112233',
            'Hola, quiero continuar con mi seleccion visual de la rifa Rifa Picker.'."\n"
            .'Codigo de seleccion: PICKER PICKERABC1'."\n"
            .'Por favor, mantener este mensaje sin modificar para continuar con la compra.'
        ));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '001',
        ]);
        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '543',
        ]);
        $this->assertDatabaseHas('conversation_states', [
            'status' => 'purchase_payment_instructions',
            'current_raffle_id' => $raffle->id,
        ]);

        $intent->refresh();
        $purchase = Purchase::query()->latest('id')->firstOrFail();

        $this->assertNotNull($intent->consumed_at);
        $this->assertNotNull($intent->consumed_by_customer_id);
        $this->assertSame('landing_featured', $purchase->metadata_json['picker_trace']['source'] ?? null);
        $this->assertSame('facebook', $purchase->metadata_json['picker_trace']['utm_source'] ?? null);
        $this->assertSame('https://rifax.test/', $purchase->reservation?->metadata_json['picker_trace']['referrer_url'] ?? null);
        $this->assertSame('PICKERABC1', $purchase->metadata_json['picker_trace']['intent_token'] ?? null);
        $this->assertStringContainsString('Tu reserva fue creada correctamente', $response->json('responses.0.reply'));
    }

    public function test_it_accepts_a_contextual_picker_message_when_the_user_keeps_extra_text(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Contextual',
            'number_digits' => 3,
            'min_numbers_per_purchase' => 2,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '111', 'status' => 'available']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '222', 'status' => 'available']);

        Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        RafflePickerIntent::query()->create([
            'raffle_id' => $raffle->id,
            'token' => 'PICKERCTX1',
            'quantity' => 2,
            'source' => 'picker_direct',
            'selected_numbers_json' => ['111', '222'],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload(
            '573001112233',
            'Hola equipo, quiero seguir con mi compra.'."\n"
            .'Codigo de seleccion: PICKER PICKERCTX1'
        ));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '111',
        ]);
        $this->assertDatabaseHas('purchase_numbers', [
            'number' => '222',
        ]);
    }

    public function test_it_rejects_expired_picker_intent_tokens(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 3,
            'min_numbers_per_purchase' => 2,
        ]);

        RafflePickerIntent::query()->create([
            'raffle_id' => $raffle->id,
            'token' => 'PICKEROLD1',
            'quantity' => 2,
            'selected_numbers_json' => ['001', '543'],
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'PICKER PICKEROLD1'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('seleccion visual ya vencio', Strtolower($response->json('responses.0.reply')));
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_it_rejects_manual_numbers_with_invalid_tokens(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 4,
        ]);

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_select_numbers',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 2,
            'selection_mode' => 'manual',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '12A,2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_numbers');

        $this->assertStringContainsString('valores invalidos', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_rejects_duplicate_manual_numbers_before_reserving(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 3,
        ]);

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_select_numbers',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 2,
            'selection_mode' => 'manual',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1,001'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_numbers');

        $this->assertStringContainsString('duplicados detectados', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_assigns_random_numbers_using_the_configured_raffle_digits(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 3,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-random-3',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '987654321',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        foreach (['001', '120', '543', '777'] as $number) {
            RaffleNumber::factory()->for($raffle)->create([
                'number' => $number,
                'status' => 'available',
            ]);
        }

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_choose_mode',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 2,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $purchase = Purchase::query()->latest('id')->with('numbers')->firstOrFail();

        $this->assertSame(2, $purchase->numbers->count());
        $this->assertSame('random', $purchase->reservation?->selection_mode);
        $this->assertTrue($purchase->numbers->every(fn ($number): bool => strlen($number->number) === 3));
    }

    public function test_it_assigns_random_numbers_by_two_blocks_when_the_raffle_option_is_enabled(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 4,
            'random_selection_by_blocks' => true,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-random-blocks-2',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123123123',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        foreach (['0100', '7500'] as $number) {
            RaffleNumber::factory()->for($raffle)->create([
                'number' => $number,
                'status' => 'available',
            ]);
        }

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_choose_mode',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 2,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $purchase = Purchase::query()->latest('id')->with('numbers')->firstOrFail();
        $numbers = $purchase->numbers->pluck('number')->sort()->values()->all();

        $this->assertSame(['0100', '7500'], $numbers);
    }

    public function test_it_assigns_random_numbers_by_three_even_blocks_when_the_raffle_option_is_enabled(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 4,
            'random_selection_by_blocks' => true,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-random-blocks-3',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '456456456',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        foreach (['0100', '4000', '9000'] as $number) {
            RaffleNumber::factory()->for($raffle)->create([
                'number' => $number,
                'status' => 'available',
            ]);
        }

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_choose_mode',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 3,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $purchase = Purchase::query()->latest('id')->with('numbers')->firstOrFail();
        $numbers = $purchase->numbers->pluck('number')->sort()->values()->all();

        $this->assertSame(['0100', '4000', '9000'], $numbers);
    }

    public function test_it_completes_the_random_assignment_from_other_blocks_when_one_block_has_no_available_numbers(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 4,
            'random_selection_by_blocks' => true,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-random-blocks-insufficient',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '789789789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        foreach (['0100', '0200', '9000'] as $number) {
            RaffleNumber::factory()->for($raffle)->create([
                'number' => $number,
                'status' => 'available',
            ]);
        }

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_choose_mode',
            'current_raffle_id' => $raffle->id,
            'requested_quantity' => 3,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $purchase = Purchase::query()->latest('id')->with('numbers')->firstOrFail();
        $numbers = $purchase->numbers->pluck('number')->sort()->values()->all();

        $this->assertSame(['0100', '0200', '9000'], $numbers);
    }

    public function test_it_receives_a_payment_proof_in_the_payment_instructions_step(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create();

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $response = $this->postSignedWhatsappWebhook($this->imagePayload('573001112233'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_under_review');

        $this->assertDatabaseHas('payments', [
            'purchase_id' => $purchase->id,
            'status' => 'pending_review',
        ]);

        $this->assertStringContainsString('tu compra esta en revision', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_repeats_configured_payment_accounts_while_waiting_for_payment_proof(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create();

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-recordatorio',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'ya pague'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_payment_instructions');

        $this->assertStringContainsString('Aun estamos esperando tu comprobante de pago', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Te recuerdo las opciones de pago disponibles para esta compra', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Numero de cuenta: 123456789', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Titular: Rifax SAS', $response->json('responses.0.reply'));
    }

    public function test_hola_from_a_paid_purchase_returns_the_customer_to_the_main_menu(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $purchase = Purchase::factory()
            ->for($customer)
            ->paid()
            ->create();

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_paid',
            'purchase_id' => $purchase->id,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'hola'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('tu compra anterior ya fue aprobada', Strtolower($response->json('responses.0.reply')));
        $this->assertStringContainsString('estas son tus opciones', Strtolower($response->json('responses.0.reply')));
    }

    public function test_comprar_from_a_paid_purchase_reopens_the_purchase_flow(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $purchase = Purchase::factory()
            ->for($customer)
            ->paid()
            ->create();

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Recompra',
        ]);

        RaffleNumber::factory()->for($raffle)->count(2)->create();

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_paid',
            'purchase_id' => $purchase->id,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'comprar'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_raffle');

        $this->assertStringContainsString('Rifa Recompra', $response->json('responses.0.reply'));
        $this->assertDatabaseHas('conversation_states', [
            'customer_id' => $customer->id,
            'status' => 'purchase_select_raffle',
            'current_raffle_id' => $raffle->id,
            'purchase_id' => null,
        ]);
    }

    public function test_quiero_comprar_de_nuevo_from_a_paid_purchase_reopens_the_purchase_flow(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $purchase = Purchase::factory()
            ->for($customer)
            ->paid()
            ->create();

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Reingreso Natural',
        ]);

        RaffleNumber::factory()->for($raffle)->count(2)->create();

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_paid',
            'purchase_id' => $purchase->id,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'quiero comprar'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_raffle');

        $this->assertStringContainsString('Rifa Reingreso Natural', $response->json('responses.0.reply'));
    }

    public function test_hola_from_an_expired_purchase_returns_the_customer_to_the_main_menu(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $purchase = Purchase::factory()
            ->for($customer)
            ->create([
                'status' => 'expired',
                'expired_at' => now()->subMinute(),
                'reserved_until' => now()->subMinute(),
            ]);

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_expired',
            'purchase_id' => $purchase->id,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'hola'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('tu compra anterior ya termino', Strtolower($response->json('responses.0.reply')));
        $this->assertStringContainsString('estas son tus opciones', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_lists_multiple_published_raffles_before_choosing_one(): void
    {
        $firstRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Uno',
            'draw_date' => now()->addDays(5)->toDateString(),
            'draw_time' => '18:00:00',
        ]);

        $secondRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Dos',
            'draw_date' => now()->addDays(6)->toDateString(),
            'draw_time' => '20:00:00',
        ]);

        RaffleNumber::factory()->for($firstRaffle)->count(2)->create();
        RaffleNumber::factory()->for($secondRaffle)->count(2)->create();

        Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_raffle');

        $this->assertStringContainsString('varias rifas activas', Strtolower($response->json('responses.0.reply')));
        $this->assertStringContainsString('Rifa Uno', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Rifa Dos', $response->json('responses.0.reply'));

        $this->assertDatabaseHas('conversation_states', [
            'status' => 'purchase_select_raffle',
            'current_raffle_id' => null,
        ]);
    }

    public function test_it_allows_choosing_one_raffle_when_multiple_are_published(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $firstRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Temprana',
            'draw_date' => now()->addDays(5)->toDateString(),
            'draw_time' => '18:00:00',
        ]);

        $secondRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Elegida',
            'draw_date' => now()->addDays(6)->toDateString(),
            'draw_time' => '20:00:00',
        ]);

        RaffleNumber::factory()->for($firstRaffle)->count(2)->create();
        RaffleNumber::factory()->for($secondRaffle)->count(2)->create();

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_select_raffle',
            'current_raffle_id' => null,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'purchase_enter_quantity');

        $this->assertStringContainsString('Cuantos numeros deseas comprar', $response->json('responses.0.reply'));
        $this->assertDatabaseHas('conversation_states', [
            'customer_id' => $customer->id,
            'status' => 'purchase_enter_quantity',
            'current_raffle_id' => $secondRaffle->id,
        ]);
    }

    public function test_available_numbers_lists_all_active_raffles_when_more_than_one_is_published(): void
    {
        $firstRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Disponible Uno',
        ]);

        $secondRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Disponible Dos',
        ]);

        RaffleNumber::factory()->for($firstRaffle)->create(['number' => '0001', 'status' => 'available']);
        RaffleNumber::factory()->for($firstRaffle)->create(['number' => '0002', 'status' => 'paid']);
        RaffleNumber::factory()->for($secondRaffle)->create(['number' => '0003', 'status' => 'available']);
        RaffleNumber::factory()->for($secondRaffle)->create(['number' => '0004', 'status' => 'available']);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '2'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('Rifa Disponible Uno', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Rifa Disponible Dos', $response->json('responses.0.reply'));
        $this->assertStringContainsString('1 numero(s) disponibles', $response->json('responses.0.reply'));
        $this->assertStringContainsString('2 numero(s) disponibles', $response->json('responses.0.reply'));
    }

    public function test_statistics_lists_all_active_raffles_when_more_than_one_is_published(): void
    {
        $firstRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Stats Uno',
            'lottery_name' => 'Loteria A',
            'lottery_draw_number' => '1111',
        ]);

        $secondRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Stats Dos',
            'lottery_name' => 'Loteria B',
            'lottery_draw_number' => '2222',
        ]);

        RaffleNumber::factory()->for($firstRaffle)->create(['number' => '0001', 'status' => 'available']);
        RaffleNumber::factory()->for($firstRaffle)->create(['number' => '0002', 'status' => 'paid']);
        RaffleNumber::factory()->for($secondRaffle)->create(['number' => '0003', 'status' => 'paid']);
        RaffleNumber::factory()->for($secondRaffle)->create(['number' => '0004', 'status' => 'paid']);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '4'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('Rifa Stats Uno', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Rifa Stats Dos', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Vendidos: 1', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Vendidos: 2', $response->json('responses.0.reply'));
    }

    public function test_upcoming_raffles_lists_all_active_raffles_when_more_than_one_is_published(): void
    {
        Raffle::factory()->published()->create([
            'title' => 'Rifa Agenda Uno',
            'draw_date' => now()->addDays(3)->toDateString(),
            'draw_time' => '17:00:00',
            'lottery_name' => 'Loteria Uno',
            'lottery_draw_number' => '3001',
        ]);

        Raffle::factory()->published()->create([
            'title' => 'Rifa Agenda Dos',
            'draw_date' => now()->addDays(4)->toDateString(),
            'draw_time' => '19:00:00',
            'lottery_name' => 'Loteria Dos',
            'lottery_draw_number' => '3002',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '5'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('Rifas activas actuales', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Rifa Agenda Uno', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Rifa Agenda Dos', $response->json('responses.0.reply'));
    }

    public function test_draw_date_shortcut_lists_all_active_raffles_when_more_than_one_is_published_and_no_raffle_is_selected(): void
    {
        Raffle::factory()->published()->create([
            'title' => 'Rifa Sorteo Uno',
            'draw_date' => now()->addDays(2)->toDateString(),
            'draw_time' => '16:00:00',
            'lottery_name' => 'Loteria X',
            'lottery_draw_number' => '9101',
        ]);

        Raffle::factory()->published()->create([
            'title' => 'Rifa Sorteo Dos',
            'draw_date' => now()->addDays(6)->toDateString(),
            'draw_time' => '20:30:00',
            'lottery_name' => 'Loteria Y',
            'lottery_draw_number' => '9102',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'SORTEO'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('fechas de sorteo', Strtolower($response->json('responses.0.reply')));
        $this->assertStringContainsString('Rifa Sorteo Uno', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Rifa Sorteo Dos', $response->json('responses.0.reply'));
    }

    public function test_cancelar_releases_the_active_reservation_and_returns_to_the_main_menu(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create();

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'CANCELAR'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0001',
            'status' => 'available',
        ]);

        $this->assertStringContainsString('reserva liberada', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_rejects_a_payment_proof_when_there_is_no_purchase_waiting_for_it(): void
    {
        Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $response = $this->postSignedWhatsappWebhook($this->imagePayload('573001112233'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertDatabaseCount('payments', 0);
        $this->assertStringContainsString('no estamos esperando un comprobante', Strtolower($response->json('responses.0.reply')));
    }

    public function test_it_uses_the_published_content_entry_for_payment_methods_shortcuts(): void
    {
        Raffle::factory()->published()->create();

        PaymentMethod::query()->create([
            'name' => 'Nequi',
            'slug' => 'nequi',
            'status' => 'active',
            'instructions' => 'Paga y envia soporte por WhatsApp.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '3001234567',
            'details_json' => ['wallet' => 'Nequi'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        ContentEntry::query()->create([
            'type' => 'faq_fixed',
            'key' => 'faq.payment.methods.test',
            'title' => 'Metodos de pago de prueba',
            'category' => 'payments',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => 'payment_methods',
            'body_text' => 'Metodos activos para esta empresa:',
            'variables_json' => [],
            'priority' => 500,
            'is_ai_eligible' => false,
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'PAGOS'));

        $response->assertOk()
            ->assertJsonPath('responses.0.conversation_status', 'main_menu');

        $this->assertStringContainsString('Metodos activos para esta empresa:', $response->json('responses.0.reply'));
        $this->assertStringContainsString('Nequi', $response->json('responses.0.reply'));
        $this->assertStringContainsString('3001234567', $response->json('responses.0.reply'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function textPayload(string $from, string $body, ?string $messageId = null, ?string $profileName = null): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => $from,
                            'profile' => array_filter([
                                'name' => $profileName,
                            ], fn (?string $value): bool => $value !== null),
                        ]],
                        'messages' => [[
                            'id' => $messageId ?? fake()->uuid(),
                            'from' => $from,
                            'type' => 'text',
                            'text' => [
                                'body' => $body,
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function imagePayload(string $from): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'wa_id' => $from,
                        ]],
                        'messages' => [[
                            'id' => fake()->uuid(),
                            'from' => $from,
                            'type' => 'image',
                            'image' => [
                                'id' => fake()->uuid(),
                                'mime_type' => 'image/jpeg',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
