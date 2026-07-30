<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\WhatsApp\SendUpcomingRaffleAnnouncementWhatsappAction;
use App\Jobs\DispatchWhatsappMessageJob;
use App\Models\ContentEntry;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueUpcomingRaffleAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_announces_upcoming_raffles_to_existing_customers_without_active_purchases_in_that_raffle(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.send_enabled', true);

        ContentEntry::query()->create([
            'type' => 'faq_fixed',
            'key' => 'campaign.upcoming.raffle.announcement.test',
            'title' => 'Anuncio de rifa',
            'category' => 'campaigns',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => 'upcoming_raffle_announcement',
            'body_text' => 'Hola {customer_name}, ya esta disponible {raffle_title}.',
            'priority' => 500,
            'is_ai_eligible' => false,
        ]);

        $newRaffle = Raffle::factory()->published()->create([
            'title' => 'Nueva Rifa',
        ]);

        $eligibleCustomer = Customer::factory()->create([
            'name' => 'Cliente Elegible',
            'last_interaction_at' => now()->subDay(),
        ]);
        Purchase::factory()->for($eligibleCustomer)->paid()->create();

        $excludedCustomer = Customer::factory()->create([
            'name' => 'Cliente Excluido',
            'last_interaction_at' => now()->subDay(),
        ]);
        Purchase::factory()->for($excludedCustomer)->for($newRaffle)->paid()->create();

        $result = app(SendUpcomingRaffleAnnouncementWhatsappAction::class)->execute($newRaffle);

        $this->assertSame(['queued' => 1, 'skipped' => 0], $result);
        $this->assertDatabaseHas('whatsapp_messages', [
            'customer_id' => $eligibleCustomer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
        ]);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'customer_id' => $excludedCustomer->id,
            'direction' => 'outbound',
        ]);

        $outboundMessage = WhatsappMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('upcoming_raffle_announcement', data_get($outboundMessage->payload_json, 'intent'));
        $this->assertSame($newRaffle->id, data_get($outboundMessage->payload_json, 'raffle_id'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class, 1);
    }
}
