<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\RecentWhatsappFailuresWidget;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecentWhatsappFailuresWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_recent_failed_whatsapp_messages(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $customer = Customer::factory()->create([
            'phone' => '573001112233',
        ]);

        $failedMessage = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body_text' => 'Boleto fallido.',
            'payload_json' => [
                'ticket_id' => 77,
                'meta_error' => ['message' => 'Meta temporary outage'],
            ],
            'status' => 'failed',
            'provider_created_at' => now(),
            'provider_status' => 'failed',
            'provider_status_at' => now(),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => 'Mensaje enviado.',
            'payload_json' => ['text' => ['body' => 'Mensaje enviado.']],
            'status' => 'sent',
            'provider_created_at' => now(),
        ]);

        Livewire::test(RecentWhatsappFailuresWidget::class)
            ->assertSee('Recent WhatsApp failures')
            ->assertSee((string) $failedMessage->id)
            ->assertSee('573001112233')
            ->assertSee('Meta temporary outage')
            ->assertSee('77')
            ->assertDontSee('Mensaje enviado.');
    }
}
