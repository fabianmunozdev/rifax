<?php

namespace Tests\Feature\Filament\WhatsappMessages;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappMessageResourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_provider_conversation_pricing_and_error_metadata(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'provider_message_id' => 'wamid.meta123',
            'body_text' => 'Tu boleto ya esta disponible.',
            'payload_json' => [
                'ticket_id' => 55,
                'provider_status_event' => [
                    'status' => 'failed',
                    'conversation' => [
                        'id' => 'conversation-demo',
                    ],
                    'pricing' => [
                        'category' => 'service',
                    ],
                    'errors' => [[
                        'code' => 131047,
                        'title' => '24h window expired',
                    ]],
                ],
            ],
            'status' => 'failed',
            'provider_status' => 'failed',
            'provider_created_at' => now()->subMinute(),
            'provider_status_at' => now(),
        ]);

        $resourceRecord = WhatsappMessageResource::getEloquentQuery()->findOrFail($message->id);

        $this->assertSame(55, (int) $resourceRecord->tracked_ticket_id);
        $this->assertNull($resourceRecord->tracked_raffle_id);
        $this->assertNull($resourceRecord->tracked_intent);
        $this->assertNull($resourceRecord->tracked_winning_number);
        $this->assertSame('conversation-demo', $resourceRecord->provider_conversation_id);
        $this->assertSame('service', $resourceRecord->provider_pricing_category);
        $this->assertSame('131047', (string) $resourceRecord->provider_error_code);
        $this->assertSame('24h window expired', $resourceRecord->meta_error_summary);
    }

    public function test_it_exposes_operational_winner_notification_metadata(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573004445566',
            'wa_id' => '573004445566',
        ]);

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'provider_message_id' => 'wamid.winner123',
            'body_text' => 'Tu numero fue ganador.',
            'payload_json' => [
                'ticket_id' => 88,
                'raffle_id' => 12,
                'intent' => 'raffle_winner_notification',
                'winning_number' => '0007',
            ],
            'status' => 'queued',
            'provider_created_at' => now(),
        ]);

        $resourceRecord = WhatsappMessageResource::getEloquentQuery()->findOrFail($message->id);

        $this->assertSame(88, (int) $resourceRecord->tracked_ticket_id);
        $this->assertSame(12, (int) $resourceRecord->tracked_raffle_id);
        $this->assertSame('raffle_winner_notification', $resourceRecord->tracked_intent);
        $this->assertSame('0007', $resourceRecord->tracked_winning_number);
    }
}
