<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\WhatsApp\RetryFailedWhatsappMessageAction;
use App\Jobs\DispatchWhatsappMessageJob;
use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryFailedWhatsappMessageActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_new_retry_attempt_from_a_failed_outbound_message(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.send_enabled', true);

        $customer = Customer::factory()->create();

        $failedMessage = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Tu boleto ya esta disponible.',
            'payload_json' => [
                'ticket_id' => 44,
                'document' => [
                    'link' => 'https://example.test/storage/tickets/ticket.svg',
                    'filename' => 'ticket-TK-DEMO.svg',
                ],
                'meta_error' => [
                    'message' => 'Meta temporary outage',
                ],
            ],
            'status' => 'failed',
            'provider_created_at' => now(),
        ]);

        $retryMessage = app(RetryFailedWhatsappMessageAction::class)->execute($failedMessage);

        $this->assertNotSame($failedMessage->id, $retryMessage->id);
        $this->assertSame('queued', $retryMessage->status);
        $this->assertSame($failedMessage->message_type, $retryMessage->message_type);
        $this->assertSame(44, data_get($retryMessage->payload_json, 'ticket_id'));
        $this->assertSame($failedMessage->id, data_get($retryMessage->payload_json, 'retry_of_message_id'));
        $this->assertNull(data_get($retryMessage->payload_json, 'meta_error'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class);
    }

    public function test_it_allows_retry_when_the_provider_status_failed_even_if_internal_status_is_sent(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.send_enabled', true);

        $customer = Customer::factory()->create();

        $failedMessage = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Tu numero fue ganador.',
            'payload_json' => [
                'ticket_id' => 81,
                'intent' => 'raffle_winner_notification',
                'provider_status_event' => [
                    'status' => 'failed',
                ],
                'provider_status_history' => [
                    ['status' => 'sent'],
                    ['status' => 'failed'],
                ],
            ],
            'status' => 'sent',
            'provider_status' => 'failed',
            'provider_created_at' => now(),
            'provider_status_at' => now(),
        ]);

        $retryMessage = app(RetryFailedWhatsappMessageAction::class)->execute($failedMessage);

        $this->assertNotSame($failedMessage->id, $retryMessage->id);
        $this->assertSame('queued', $retryMessage->status);
        $this->assertSame($failedMessage->id, data_get($retryMessage->payload_json, 'retry_of_message_id'));
        $this->assertNull(data_get($retryMessage->payload_json, 'provider_status_event'));
        $this->assertNull(data_get($retryMessage->payload_json, 'provider_status_history'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class);
    }
}
