<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\RecentWinnerNotificationsWidget;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecentWinnerNotificationsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_recent_winner_notifications(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $customer = Customer::factory()->create([
            'phone' => '573009998877',
        ]);

        $winnerMessage = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body_text' => 'Tu numero fue ganador.',
            'payload_json' => [
                'intent' => 'raffle_winner_notification',
                'ticket_id' => 91,
                'raffle_id' => 12,
                'winning_number' => '0042',
            ],
            'status' => 'sent',
            'provider_status' => 'delivered',
            'provider_created_at' => now(),
            'provider_status_at' => now(),
        ]);

        WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => 'Mensaje generico.',
            'payload_json' => ['text' => ['body' => 'Mensaje generico.']],
            'status' => 'sent',
            'provider_created_at' => now(),
        ]);

        Livewire::test(RecentWinnerNotificationsWidget::class)
            ->assertSee('Recent winner notifications')
            ->assertSee((string) $winnerMessage->id)
            ->assertSee('573009998877')
            ->assertSee('0042')
            ->assertSee('91')
            ->assertSee('12')
            ->assertSee('Delivered')
            ->assertDontSee('Mensaje generico.');
    }
}
