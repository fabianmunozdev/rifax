<?php

namespace Tests\Feature\Conversations;

use App\Actions\Conversations\HumanHandoffAction;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Livewire\ConversationChat;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class HumanHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.webhook_app_secret', 'rifax-test-app-secret');
        config()->set('services.whatsapp.send_enabled', false);
    }

    public function test_customer_asking_for_an_agent_pauses_the_bot(): void
    {
        $this->customer();

        $response = $this->postSignedWhatsappWebhook($this->textPayload('Quiero hablar con un asesor'));

        $response->assertOk();
        $this->assertStringContainsString('asesor te atenderá', $response->json('responses.0.reply'));

        $state = ConversationState::query()->firstOrFail();
        $this->assertSame(ConversationState::HANDOFF_REQUESTED, $state->handoff_status);
        $this->assertNotNull($state->handoff_requested_at);

        $outboundBefore = WhatsappMessage::query()->where('direction', 'outbound')->count();

        $this->postSignedWhatsappWebhook($this->textPayload('Hola? sigo esperando'))
            ->assertOk()
            ->assertJsonPath('responses.0.reply', null)
            ->assertJsonPath('responses.0.handoff_status', ConversationState::HANDOFF_REQUESTED);

        $this->assertSame($outboundBefore, WhatsappMessage::query()->where('direction', 'outbound')->count());
        $this->assertDatabaseHas('whatsapp_messages', ['direction' => 'inbound', 'body_text' => 'Hola? sigo esperando']);
    }

    public function test_customer_can_return_to_the_bot_with_menu_before_an_agent_takes_the_chat(): void
    {
        $state = $this->stateFor($this->customer(), ['handoff_status' => 'requested', 'handoff_requested_at' => now()]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('MENU'));

        $this->assertNotNull($response->json('responses.0.reply'));
        $this->assertNull($state->fresh()->handoff_status);
    }

    public function test_menu_does_not_interrupt_an_active_agent(): void
    {
        $agent = User::factory()->operator()->create();
        $state = $this->stateFor($this->customer(), [
            'handoff_status' => 'active',
            'assigned_user_id' => $agent->id,
            'handoff_started_at' => now(),
        ]);

        $this->postSignedWhatsappWebhook($this->textPayload('MENU'))
            ->assertJsonPath('responses.0.reply', null);

        $this->assertSame('active', $state->fresh()->handoff_status);
    }

    public function test_idle_handoffs_are_released_back_to_the_bot(): void
    {
        config()->set('rifax.handoff.idle_release_minutes', 60);
        $state = $this->stateFor($this->customer(), [
            'handoff_status' => 'requested',
            'handoff_requested_at' => now()->subHours(3),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('hola'));

        $this->assertNotNull($response->json('responses.0.reply'));
        $this->assertNull($state->fresh()->handoff_status);
    }

    public function test_agent_message_takes_the_conversation_and_is_attributed(): void
    {
        $agent = User::factory()->operator()->create();
        $state = $this->stateFor($this->customer(), ['last_user_message_at' => now()->subMinutes(5)]);

        $message = app(HumanHandoffAction::class)->sendAgentMessage($state, $agent, '  Hola, te ayudo  ');

        $this->assertSame('Hola, te ayudo', $message->body_text);
        $this->assertSame($agent->id, $message->sent_by_user_id);

        $state->refresh();
        $this->assertSame('active', $state->handoff_status);
        $this->assertSame($agent->id, $state->assigned_user_id);
        $this->assertNotNull($state->last_agent_message_at);
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'conversation.handoff_taken']);
    }

    public function test_agent_cannot_write_outside_the_24h_window(): void
    {
        $agent = User::factory()->operator()->create();
        $state = $this->stateFor($this->customer(), ['last_user_message_at' => now()->subHours(25)]);

        $this->expectException(InvalidArgumentException::class);

        app(HumanHandoffAction::class)->sendAgentMessage($state, $agent, 'Hola');
    }

    public function test_release_returns_the_chat_to_the_bot_and_notifies_the_customer(): void
    {
        $agent = User::factory()->operator()->create();
        $state = $this->stateFor($this->customer(), [
            'handoff_status' => 'active',
            'assigned_user_id' => $agent->id,
            'last_user_message_at' => now(),
        ]);

        app(HumanHandoffAction::class)->release($state, $agent);

        $state->refresh();
        $this->assertNull($state->handoff_status);
        $this->assertNull($state->assigned_user_id);
        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'sent_by_user_id' => $agent->id,
            'body_text' => 'Gracias por escribirnos. Te devolvemos al asistente automático; escribe MENU cuando quieras ver las opciones.',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'conversation.handoff_released']);
    }

    public function test_chat_component_shows_history_and_sends_replies(): void
    {
        $agent = User::factory()->operator()->create(['name' => 'Laura']);
        $customer = $this->customer();
        $state = $this->stateFor($customer, ['last_user_message_at' => now()]);

        WhatsappMessage::factory()->for($customer)->create([
            'direction' => 'inbound',
            'message_type' => 'text',
            'body_text' => 'Necesito ayuda con mi pago',
        ]);

        $this->actingAs($agent);

        Livewire::test(ConversationChat::class, ['record' => $state])
            ->assertSee('Necesito ayuda con mi pago')
            ->set('draft', 'Claro, ¿qué pasó?')
            ->call('send')
            ->assertSet('draft', '')
            ->assertSee('Claro, ¿qué pasó?')
            ->assertSee('Laura')
            ->assertDispatched('handoff-updated');

        $this->assertNotNull($state->fresh()->agent_last_read_at);
    }

    public function test_finance_users_can_not_reply(): void
    {
        $state = $this->stateFor($this->customer(), ['last_user_message_at' => now()]);

        $this->actingAs(User::factory()->finance()->create());

        Livewire::test(ConversationChat::class, ['record' => $state])
            ->set('draft', 'Hola')
            ->call('send')
            ->assertForbidden();
    }

    public function test_view_page_renders_and_agent_can_take_the_conversation(): void
    {
        $agent = User::factory()->operator()->create();
        $state = $this->stateFor($this->customer(), ['handoff_status' => 'requested', 'handoff_requested_at' => now()]);

        $this->actingAs($agent);

        $this->assertSame('1', ConversationResource::getNavigationBadge());

        Livewire::test(ViewConversation::class, ['record' => $state->getRouteKey()])
            ->assertOk()
            ->assertSee(__('admin.resources.conversations.handoff.requested'))
            ->callAction('take_conversation');

        $this->assertSame($agent->id, $state->fresh()->assigned_user_id);
        $this->assertNull(ConversationResource::getNavigationBadge());
    }

    protected function customer(): Customer
    {
        return Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
            'name' => 'Ana',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function stateFor(Customer $customer, array $attributes = []): ConversationState
    {
        return ConversationState::factory()->for($customer)->create(array_merge([
            'channel' => 'whatsapp',
            'status' => 'main_menu',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    protected function textPayload(string $body): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [['wa_id' => '573001112233', 'profile' => ['name' => 'Ana']]],
                        'messages' => [[
                            'id' => fake()->uuid(),
                            'from' => '573001112233',
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
