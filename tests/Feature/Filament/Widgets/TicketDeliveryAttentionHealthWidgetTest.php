<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\TicketDeliveryAttentionHealthWidget;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDeliveryAttentionHealthWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_attention_metrics_and_degraded_health_when_failed_tickets_exist(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $this->createTicket('573001110001', 'Rifa Sin Entrega', 'TK-ATTN-1');
        $awaitingDeliveryTicket = $this->createTicket('573001110002', 'Rifa En Cola', 'TK-ATTN-2');
        $deliveredTicket = $this->createTicket('573001110003', 'Rifa Entregada', 'TK-ATTN-3');
        $failedTicket = $this->createTicket('573001110004', 'Rifa Fallida', 'TK-ATTN-4');

        WhatsappMessage::query()->create([
            'customer_id' => $awaitingDeliveryTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto en cola.',
            'payload_json' => ['ticket_id' => $awaitingDeliveryTicket->id],
            'status' => 'queued',
            'provider_created_at' => now()->subMinutes(30),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $deliveredTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto entregado.',
            'payload_json' => ['ticket_id' => $deliveredTicket->id],
            'status' => 'sent',
            'provider_status' => 'delivered',
            'provider_created_at' => now()->subMinutes(20),
            'provider_status_at' => now()->subMinutes(20),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $failedTicket->purchase->customer_id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto fallido.',
            'payload_json' => [
                'ticket_id' => $failedTicket->id,
                'meta_error' => ['message' => 'Meta outage'],
            ],
            'status' => 'failed',
            'provider_created_at' => now()->subMinutes(10),
        ]);

        $widget = new class extends TicketDeliveryAttentionHealthWidget
        {
            /**
             * @return array{0: string, 1: string, 2: string, 3: string}
             */
            public function exposedResolveHealth(int $withoutDelivery, int $awaitingDelivery, int $deliveredNotRead, int $failed): array
            {
                return $this->resolveHealth($withoutDelivery, $awaitingDelivery, $deliveredNotRead, $failed);
            }
        };

        $health = $widget->exposedResolveHealth(1, 1, 1, 1);

        $this->assertSame('degraded', $health[0]);
        $this->assertSame('danger', $health[2]);

        Livewire::test(TicketDeliveryAttentionHealthWidget::class)
            ->assertSee('Ticket delivery attention')
            ->assertSee('Attention status')
            ->assertSee('No delivery attempt')
            ->assertSee('Pending provider delivery')
            ->assertSee('Delivered, awaiting read')
            ->assertSee('Failed')
            ->assertSee('1');
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
