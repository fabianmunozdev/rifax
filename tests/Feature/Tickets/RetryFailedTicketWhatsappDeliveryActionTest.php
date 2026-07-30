<?php

namespace Tests\Feature\Tickets;

use App\Actions\Tickets\RetryFailedTicketWhatsappDeliveryAction;
use App\Jobs\DispatchWhatsappMessageJob;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\Ticket;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryFailedTicketWhatsappDeliveryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retries_the_latest_ticket_message_when_provider_status_failed(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.send_enabled', true);

        $customer = Customer::factory()->create();
        $raffle = Raffle::factory()->published()->create();
        $purchase = Purchase::factory()
            ->for($customer)
            ->for($raffle)
            ->paid()
            ->create([
                'raffle_title_snapshot' => $raffle->title,
            ]);

        $ticket = Ticket::query()->create([
            'purchase_id' => $purchase->id,
            'code' => 'TK-RETRY-001',
            'verification_token' => 'tk-retry-001-token',
            'public_url' => 'http://localhost/tickets/tk-retry-001-token',
            'image_path' => null,
            'thumbnail_path' => null,
            'version' => 1,
            'generated_at' => now(),
        ]);

        $failedMessage = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Tu boleto ya esta disponible.',
            'payload_json' => [
                'ticket_id' => $ticket->id,
                'document' => [
                    'link' => 'https://example.test/storage/tickets/ticket.svg',
                ],
                'provider_status_event' => [
                    'status' => 'failed',
                ],
            ],
            'status' => 'sent',
            'provider_status' => 'failed',
            'provider_created_at' => now(),
            'provider_status_at' => now(),
        ]);

        $retryMessage = app(RetryFailedTicketWhatsappDeliveryAction::class)->execute($ticket);

        $this->assertNotSame($failedMessage->id, $retryMessage->id);
        $this->assertSame('queued', $retryMessage->status);
        $this->assertSame($failedMessage->id, data_get($retryMessage->payload_json, 'retry_of_message_id'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class);
    }
}
