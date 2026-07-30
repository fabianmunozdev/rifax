<?php

namespace Tests\Feature\WhatsApp\Webhook;

use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.webhook_app_secret', 'rifax-test-app-secret');
    }

    public function test_it_updates_provider_status_for_an_existing_outbound_message(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'provider_message_id' => 'wamid.document123',
            'body_text' => 'Tu boleto ya esta disponible.',
            'payload_json' => [
                'ticket_id' => 77,
                'document' => [
                    'link' => 'https://example.test/storage/tickets/ticket.svg',
                ],
            ],
            'status' => 'sent',
            'provider_created_at' => now()->subMinute(),
        ]);

        $timestamp = (string) now()->timestamp;

        $response = $this->postSignedWhatsappWebhook($this->statusPayload('wamid.document123', 'delivered', $timestamp));

        $response->assertOk()
            ->assertJsonPath('processed', true)
            ->assertJsonPath('responses.0.updated', true)
            ->assertJsonPath('responses.0.provider_status', 'delivered');

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame('delivered', $message->provider_status);
        $this->assertNotNull($message->provider_status_at);
        $this->assertSame('delivered', data_get($message->payload_json, 'provider_status_event.status'));
        $this->assertSame('conversation-demo', data_get($message->payload_json, 'provider_status_event.conversation.id'));
        $this->assertSame('service', data_get($message->payload_json, 'provider_status_event.pricing.category'));
        $this->assertCount(1, data_get($message->payload_json, 'provider_status_history', []));
    }

    public function test_it_marks_the_message_as_failed_when_meta_reports_a_failed_status(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'provider_message_id' => 'wamid.failed123',
            'body_text' => 'Mensaje de prueba.',
            'payload_json' => ['text' => ['body' => 'Mensaje de prueba.']],
            'status' => 'sent',
            'provider_created_at' => now()->subMinute(),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->failedStatusPayload('wamid.failed123'));

        $response->assertOk()
            ->assertJsonPath('responses.0.updated', true)
            ->assertJsonPath('responses.0.provider_status', 'failed');

        $message->refresh();

        $this->assertSame('failed', $message->status);
        $this->assertSame('failed', $message->provider_status);
        $this->assertSame('Message failed to send because more than 24 hours have passed since the customer last replied to this number.', data_get($message->payload_json, 'provider_status_event.errors.0.title'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function statusPayload(string $providerMessageId, string $status, string $timestamp): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => $providerMessageId,
                            'status' => $status,
                            'timestamp' => $timestamp,
                            'recipient_id' => '573001112233',
                            'conversation' => [
                                'id' => 'conversation-demo',
                                'origin' => ['type' => 'service'],
                            ],
                            'pricing' => [
                                'billable' => true,
                                'pricing_model' => 'CBP',
                                'category' => 'service',
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
    protected function failedStatusPayload(string $providerMessageId): array
    {
        $payload = $this->statusPayload($providerMessageId, 'failed', (string) now()->timestamp);
        $payload['entry'][0]['changes'][0]['value']['statuses'][0]['errors'] = [[
            'code' => 131047,
            'title' => 'Message failed to send because more than 24 hours have passed since the customer last replied to this number.',
        ]];

        return $payload;
    }
}
