<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\WhatsappChannelHealthWidget;
use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappChannelHealthWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_channel_metrics_and_degraded_health_when_failures_exist(): void
    {
        config()->set('services.whatsapp.send_enabled', true);

        $customer = Customer::factory()->create();

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => 'Mensaje enviado.',
            'payload_json' => ['text' => ['body' => 'Mensaje enviado.']],
            'status' => 'sent',
            'provider_created_at' => now()->subHour(),
            'provider_status' => 'sent',
            'provider_status_at' => now()->subHour(),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto fallido.',
            'payload_json' => [
                'ticket_id' => 8,
                'meta_error' => ['message' => 'Meta outage'],
            ],
            'status' => 'failed',
            'provider_created_at' => now()->subMinutes(30),
            'provider_status' => 'failed',
            'provider_status_at' => now()->subMinutes(30),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto en cola.',
            'payload_json' => ['ticket_id' => 8],
            'status' => 'queued',
            'provider_created_at' => now()->subMinutes(20),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto entregado.',
            'payload_json' => ['ticket_id' => 8],
            'status' => 'sent',
            'provider_created_at' => now()->subMinutes(10),
            'provider_status' => 'delivered',
            'provider_status_at' => now()->subMinutes(10),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto leido.',
            'payload_json' => ['ticket_id' => 8],
            'status' => 'sent',
            'provider_created_at' => now()->subMinutes(5),
            'provider_status' => 'read',
            'provider_status_at' => now()->subMinutes(5),
        ]);

        $widget = new class extends WhatsappChannelHealthWidget
        {
            /**
             * @return array{0: string, 1: string, 2: string, 3: string}
             */
            public function exposedResolveHealth(bool $sendEnabled, int $failedLast24h, int $queuedNow, int $staleQueued): array
            {
                return $this->resolveHealth($sendEnabled, $failedLast24h, $queuedNow, $staleQueued);
            }
        };

        $health = $widget->exposedResolveHealth(true, 1, 1, 1);

        $this->assertSame('degraded', $health[0]);
        $this->assertSame('danger', $health[2]);

        Livewire::test(WhatsappChannelHealthWidget::class)
            ->assertSee('WhatsApp channel health')
            ->assertSee('Channel status')
            ->assertSee('Meta sent (24h)')
            ->assertSee('Delivered (24h)')
            ->assertSee('Read (24h)')
            ->assertSee('Failed (24h)')
            ->assertSee('1');
    }
}
