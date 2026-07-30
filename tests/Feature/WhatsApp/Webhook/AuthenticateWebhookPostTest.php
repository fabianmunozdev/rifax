<?php

namespace Tests\Feature\WhatsApp\Webhook;

use App\Models\AdminAuditLog;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticateWebhookPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.webhook_app_secret', 'rifax-test-app-secret');
    }

    public function test_it_accepts_an_authenticated_post_webhook_request(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Segura',
        ]);

        RaffleNumber::factory()->for($raffle)->count(2)->create();

        $response = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1'));

        $response->assertOk()
            ->assertJsonPath('processed', true)
            ->assertJsonPath('responses.0.conversation_status', 'purchase_select_raffle');
    }

    public function test_it_rejects_the_post_webhook_when_the_signature_header_is_missing(): void
    {
        $response = $this->postJson('/api/webhooks/whatsapp', $this->textPayload('573001112233', '1'));

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden',
            ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'event' => 'webhook.signature_rejected',
            'action' => 'reject_signature',
        ]);
    }

    public function test_it_rejects_the_post_webhook_when_the_signature_is_invalid(): void
    {
        $response = $this->withHeaders([
            'X-Hub-Signature-256' => 'sha256=invalid-signature',
        ])->postJson('/api/webhooks/whatsapp', $this->textPayload('573001112233', '1'));

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden',
            ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'event' => 'webhook.signature_rejected',
            'action' => 'reject_signature',
        ]);
    }

    public function test_it_rate_limits_the_post_webhook_and_records_a_system_event(): void
    {
        config()->set('services.whatsapp.webhook_rate_limit_max_attempts', 1);
        config()->set('services.whatsapp.webhook_rate_limit_decay_seconds', 60);

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Limitada',
        ]);

        RaffleNumber::factory()->for($raffle)->count(2)->create();

        $firstResponse = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', '1'));
        $secondResponse = $this->postSignedWhatsappWebhook($this->textPayload('573001112233', 'MENU'));

        $firstResponse->assertOk();
        $secondResponse->assertStatus(429)
            ->assertJsonPath('message', 'Too Many Requests');

        $this->assertDatabaseHas('admin_audit_logs', [
            'event' => 'webhook.rate_limited',
            'action' => 'rate_limit',
        ]);

        $rateLimitLog = AdminAuditLog::query()
            ->where('event', 'webhook.rate_limited')
            ->latest('id')
            ->first();

        $this->assertNotNull($rateLimitLog);
        $this->assertSame(1, data_get($rateLimitLog?->context_json, 'max_attempts'));
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
