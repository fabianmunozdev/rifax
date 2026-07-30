<?php

namespace Tests\Feature\Filament\WhatsappMessages;

use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappMessageResourceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_action_is_only_enabled_for_failed_outbound_messages(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $customer = Customer::factory()->create();

        $inboundFailed = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body_text' => 'hola',
            'payload_json' => [],
            'status' => 'failed',
        ]);

        $outboundSent = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => 'hola',
            'payload_json' => [],
            'status' => 'sent',
        ]);

        $outboundFailed = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'boleto',
            'payload_json' => [],
            'status' => 'failed',
        ]);

        $this->assertTrue(WhatsappMessageResource::makeRetryFailedAction()->record($inboundFailed)->isDisabled());
        $this->assertTrue(WhatsappMessageResource::makeRetryFailedAction()->record($outboundSent)->isDisabled());
        $this->assertFalse(WhatsappMessageResource::makeRetryFailedAction()->record($outboundFailed)->isDisabled());
    }

    public function test_linked_ticket_and_raffle_actions_use_resource_urls(): void
    {
        $customer = Customer::factory()->create();

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Ganaste',
            'payload_json' => [
                'ticket_id' => 88,
                'raffle_id' => 12,
                'intent' => 'raffle_winner_notification',
            ],
            'status' => 'queued',
        ]);

        $resourceRecord = WhatsappMessageResource::getEloquentQuery()->findOrFail($message->id);

        $this->assertSame(
            TicketResource::getUrl('view', ['record' => 88]),
            WhatsappMessageResource::makeOpenLinkedTicketAction()->record($resourceRecord)->getUrl(),
        );

        $this->assertSame(
            RaffleResource::getUrl('view', ['record' => 12]),
            WhatsappMessageResource::makeOpenLinkedRaffleAction()->record($resourceRecord)->getUrl(),
        );
    }
}
