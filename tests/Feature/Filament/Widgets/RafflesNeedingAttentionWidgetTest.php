<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\RafflesNeedingAttentionWidget;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\Ticket;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RafflesNeedingAttentionWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_groups_ticket_attention_metrics_by_raffle(): void
    {
        $attentionRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Atencion',
        ]);

        $secondaryRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Secundaria',
        ]);

        $healthyRaffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Saludable',
        ]);

        $this->createTicketForRaffle($attentionRaffle, '573003330001', 'TK-RAFFLE-1');
        $failedTicket = $this->createTicketForRaffle($attentionRaffle, '573003330002', 'TK-RAFFLE-2');
        $awaitingTicket = $this->createTicketForRaffle($secondaryRaffle, '573003330003', 'TK-RAFFLE-3');
        $healthyTicket = $this->createTicketForRaffle($healthyRaffle, '573003330004', 'TK-RAFFLE-4');

        WhatsappMessage::query()->create([
            'customer_id' => $failedTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Entrega fallida.',
            'payload_json' => [
                'ticket_id' => $failedTicket->id,
                'intent' => 'ticket_delivery',
            ],
            'status' => 'failed',
            'provider_created_at' => now()->subMinutes(20),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $awaitingTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Entrega en cola.',
            'payload_json' => [
                'ticket_id' => $awaitingTicket->id,
                'intent' => 'raffle_winner_notification',
            ],
            'status' => 'queued',
            'provider_created_at' => now()->subMinutes(10),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $healthyTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Entrega leida.',
            'payload_json' => [
                'ticket_id' => $healthyTicket->id,
                'intent' => 'ticket_delivery',
            ],
            'status' => 'sent',
            'provider_status' => 'read',
            'provider_created_at' => now()->subMinutes(5),
            'provider_status_at' => now()->subMinutes(5),
        ]);

        Livewire::test(RafflesNeedingAttentionWidget::class)
            ->assertSee('Raffles Needing Attention')
            ->assertSee('Rifa Atencion')
            ->assertSee('Rifa Secundaria')
            ->assertSee('2')
            ->assertSee('1')
            ->assertDontSee('Rifa Saludable');
    }

    protected function createTicketForRaffle(Raffle $raffle, string $phone, string $code): Ticket
    {
        $customer = Customer::factory()->create([
            'phone' => $phone,
            'wa_id' => preg_replace('/\D+/', '', $phone),
        ]);

        $purchase = Purchase::factory()
            ->for($customer)
            ->for($raffle)
            ->paid()
            ->create([
                'raffle_title_snapshot' => $raffle->title,
            ]);

        return Ticket::query()->create([
            'purchase_id' => $purchase->id,
            'code' => $code,
            'verification_token' => strtolower($code).'-token',
            'public_url' => 'http://localhost/tickets/'.strtolower($code).'-token',
            'image_path' => null,
            'thumbnail_path' => null,
            'version' => 1,
            'generated_at' => now(),
        ])->fresh(['purchase.customer', 'purchase.raffle']) ?? throw new \RuntimeException('Ticket not created.');
    }
}
