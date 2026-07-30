<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\WhatsApp\SendRaffleDrawReminderWhatsappAction;
use App\Jobs\DispatchWhatsappMessageJob;
use App\Models\ContentEntry;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\Ticket;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueRaffleDrawReminderCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_draw_reminders_for_paid_purchases_and_skips_duplicates(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.send_enabled', true);

        ContentEntry::query()->create([
            'type' => 'faq_fixed',
            'key' => 'campaign.raffle.draw.reminder.test',
            'title' => 'Recordatorio de sorteo',
            'category' => 'campaigns',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => 'raffle_draw_reminder',
            'body_text' => 'Hola {customer_name}, {raffle_title} se juega el {draw_date}.',
            'priority' => 500,
            'is_ai_eligible' => false,
        ]);

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Sorteo',
            'draw_date' => now()->addDay()->toDateString(),
            'draw_time' => '18:00',
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Cliente Sorteo',
        ]);

        $purchase = Purchase::factory()
            ->for($customer)
            ->for($raffle)
            ->paid()
            ->create();

        Ticket::query()->create([
            'purchase_id' => $purchase->id,
            'code' => 'TK-DRAW-1',
            'verification_token' => 'verify-draw-1',
            'public_url' => 'https://rifax.test/tickets/1',
            'image_path' => 'tickets/tk-draw-1.svg',
            'thumbnail_path' => null,
            'version' => 1,
            'generated_at' => now(),
        ]);

        ConversationState::factory()->for($customer)->for($purchase)->create([
            'last_user_message_at' => now()->subHours(2),
        ]);

        $firstRun = app(SendRaffleDrawReminderWhatsappAction::class)->execute($raffle);
        $secondRun = app(SendRaffleDrawReminderWhatsappAction::class)->execute($raffle);

        $this->assertSame(['queued' => 1, 'skipped' => 0], $firstRun);
        $this->assertSame(['queued' => 0, 'skipped' => 1], $secondRun);

        $outboundMessage = WhatsappMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('text', $outboundMessage->message_type);
        $this->assertSame('raffle_draw_reminder', data_get($outboundMessage->payload_json, 'intent'));
        $this->assertSame($raffle->id, data_get($outboundMessage->payload_json, 'raffle_id'));
        $this->assertSame($purchase->id, data_get($outboundMessage->payload_json, 'purchase_id'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class, 1);
    }
}
