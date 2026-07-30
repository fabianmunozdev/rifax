<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\RaffleWinnerNotificationHealthWidget;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RaffleWinnerNotificationHealthWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_winner_notification_metrics_and_warning_health(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $customer = Customer::factory()->create();

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Ganador en cola.',
            'payload_json' => [
                'intent' => 'raffle_winner_notification',
                'ticket_id' => 15,
                'winning_number' => '0008',
            ],
            'status' => 'queued',
            'provider_created_at' => now()->subHour(),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => 'Ganador entregado.',
            'payload_json' => [
                'intent' => 'raffle_winner_notification',
                'ticket_id' => 15,
                'winning_number' => '0008',
            ],
            'status' => 'sent',
            'provider_status' => 'delivered',
            'provider_created_at' => now()->subMinutes(30),
            'provider_status_at' => now()->subMinutes(30),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => 'Ganador leido.',
            'payload_json' => [
                'intent' => 'raffle_winner_notification',
                'ticket_id' => 15,
                'winning_number' => '0008',
            ],
            'status' => 'sent',
            'provider_status' => 'read',
            'provider_created_at' => now()->subMinutes(10),
            'provider_status_at' => now()->subMinutes(10),
        ]);

        $widget = new class extends RaffleWinnerNotificationHealthWidget
        {
            /**
             * @return array{0: string, 1: string, 2: string, 3: string}
             */
            public function exposedResolveHealth(int $queuedLast24h, int $deliveredLast24h, int $readLast24h, int $failedLast24h): array
            {
                return $this->resolveHealth($queuedLast24h, $deliveredLast24h, $readLast24h, $failedLast24h);
            }
        };

        $health = $widget->exposedResolveHealth(1, 1, 1, 0);

        $this->assertSame('warning', $health[0]);
        $this->assertSame('warning', $health[2]);

        Livewire::test(RaffleWinnerNotificationHealthWidget::class)
            ->assertSee('Winner notification health')
            ->assertSee('Winner notification status')
            ->assertSee('Queued (24h)')
            ->assertSee('Delivered (24h)')
            ->assertSee('Read (24h)')
            ->assertSee('Failed (24h)')
            ->assertSee('1');
    }
}
