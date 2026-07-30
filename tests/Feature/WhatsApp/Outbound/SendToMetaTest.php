<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\WhatsApp\DispatchOutboundWhatsappMessageAction;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendToMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.webhook_app_secret', 'rifax-test-app-secret');
    }

    public function test_it_sends_the_generated_reply_to_meta_when_outbound_delivery_is_enabled(): void
    {
        config()->set('services.whatsapp.send_enabled', true);
        config()->set('services.whatsapp.api_base_url', 'https://graph.facebook.com');
        config()->set('services.whatsapp.graph_version', 'v23.0');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.access_token', 'test-access-token');

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

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.HBgLNQ123'],
                ],
            ], 200),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'PAGOS'));

        $response->assertOk()
            ->assertJsonPath('responses.0.delivery_status', 'sent');

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'status' => 'sent',
            'provider_message_id' => 'wamid.HBgLNQ123',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '573001112233'
                && $request['type'] === 'text';
        });
    }

    public function test_it_marks_the_outbound_message_as_failed_when_meta_returns_an_error(): void
    {
        config()->set('services.whatsapp.send_enabled', true);
        config()->set('services.whatsapp.api_base_url', 'https://graph.facebook.com');
        config()->set('services.whatsapp.graph_version', 'v23.0');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.access_token', 'test-access-token');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                ],
            ], 401),
        ]);

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'AYUDA'));

        $response->assertOk()
            ->assertJsonPath('responses.0.delivery_status', 'failed');

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'status' => 'failed',
        ]);
    }

    public function test_it_sends_a_template_payload_to_meta_when_the_message_type_is_template(): void
    {
        config()->set('services.whatsapp.send_enabled', true);
        config()->set('services.whatsapp.api_base_url', 'https://graph.facebook.com');
        config()->set('services.whatsapp.graph_version', 'v23.0');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.access_token', 'test-access-token');

        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Hola cliente, tu pago fue aprobado.',
            'payload_json' => [
                'template' => [
                    'name' => 'payment_approved_ticket',
                    'language' => ['code' => 'es_CO'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => 'Cliente Demo'],
                            ['type' => 'text', 'text' => 'Rifa Demo'],
                        ],
                    ]],
                ],
            ],
            'status' => 'queued',
            'provider_created_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.template123'],
                ],
            ], 200),
        ]);

        app(DispatchOutboundWhatsappMessageAction::class)->execute($customer, $message);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $message->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.template123',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'payment_approved_ticket'
                && $request['template']['language']['code'] === 'es_CO';
        });
    }

    public function test_it_sends_a_document_payload_to_meta_when_the_message_type_is_document(): void
    {
        config()->set('services.whatsapp.send_enabled', true);
        config()->set('services.whatsapp.api_base_url', 'https://graph.facebook.com');
        config()->set('services.whatsapp.graph_version', 'v23.0');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.access_token', 'test-access-token');

        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
            'wa_id' => '573001112233',
        ]);

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Tu boleto ya esta disponible.',
            'payload_json' => [
                'document' => [
                    'link' => 'https://example.test/storage/tickets/ticket.svg',
                    'filename' => 'ticket-TK-DEMO.svg',
                    'caption' => 'Tu boleto ya esta disponible.',
                ],
            ],
            'status' => 'queued',
            'provider_created_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.document123'],
                ],
            ], 200),
        ]);

        app(DispatchOutboundWhatsappMessageAction::class)->execute($customer, $message);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $message->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.document123',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
                && $request['type'] === 'document'
                && $request['document']['filename'] === 'ticket-TK-DEMO.svg'
                && $request['document']['link'] === 'https://example.test/storage/tickets/ticket.svg';
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function textPayload(string $from, string $body): array
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
}
