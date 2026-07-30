<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\TicketsNeedingAttentionWidget;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketsNeedingAttentionWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_tickets_that_need_delivery_attention(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $this->createTicket('573002220001', 'Rifa Sin Entrega', 'TK-LIST-1');
        $deliveredTicket = $this->createTicket('573002220002', 'Rifa Entregada', 'TK-LIST-2');
        $failedTicket = $this->createTicket('573002220003', 'Rifa Fallida', 'TK-LIST-3');
        $readTicket = $this->createTicket('573002220004', 'Rifa Leida', 'TK-LIST-4');

        WhatsappMessage::query()->create([
            'customer_id' => $deliveredTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto entregado.',
            'payload_json' => [
                'ticket_id' => $deliveredTicket->id,
                'intent' => 'ticket_delivery',
            ],
            'status' => 'sent',
            'provider_status' => 'delivered',
            'provider_created_at' => now()->subMinutes(20),
            'provider_status_at' => now()->subMinutes(20),
        ]);

        $failedMessage = WhatsappMessage::query()->create([
            'customer_id' => $failedTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Entrega fallida.',
            'payload_json' => [
                'ticket_id' => $failedTicket->id,
                'intent' => 'raffle_winner_notification',
            ],
            'status' => 'failed',
            'provider_created_at' => now()->subMinutes(10),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $readTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto leido.',
            'payload_json' => [
                'ticket_id' => $readTicket->id,
                'intent' => 'ticket_delivery',
            ],
            'status' => 'sent',
            'provider_status' => 'read',
            'provider_created_at' => now()->subMinutes(5),
            'provider_status_at' => now()->subMinutes(5),
        ]);

        Livewire::test(TicketsNeedingAttentionWidget::class)
            ->assertSee('Tickets needing attention')
            ->assertSee('573002220001')
            ->assertSee('573002220002')
            ->assertSee('573002220003')
            ->assertSee((string) $failedMessage->id)
            ->assertSee('No delivery attempt')
            ->assertSee('Delivered, awaiting read')
            ->assertSee('Failed delivery')
            ->assertSee('Winner notification')
            ->assertDontSee('573002220004');
    }

    protected function createTicket(string $phone, string $raffleTitle, string $code): Ticket
    {
        $customer = Customer::factory()->create([
            'phone' => $phone,
            'wa_id' => preg_replace('/\D+/', '', $phone),
        ]);

        $raffle = Raffle::factory()->published()->create([
            'title' => $raffleTitle,
        ]);

        $purchase = Purchase::factory()
            ->for($customer)
            ->for($raffle)
            ->paid()
            ->create([
                'raffle_title_snapshot' => $raffleTitle,
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
