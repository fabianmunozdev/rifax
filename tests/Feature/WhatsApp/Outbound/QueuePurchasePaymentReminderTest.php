<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\WhatsApp\SendPurchasePaymentReminderWhatsappAction;
use App\Jobs\DispatchWhatsappMessageJob;
use App\Models\ContentEntry;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuePurchasePaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_template_payment_reminder_and_skips_recent_duplicates(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.send_enabled', true);

        ContentEntry::query()->create([
            'type' => 'template_bridge',
            'key' => 'template.purchase.payment.reminder.test',
            'title' => 'Recordatorio de pago',
            'category' => 'campaigns',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => 'purchase_payment_reminder',
            'body_text' => 'Hola {customer_name}, tu compra para {raffle_title} sigue pendiente.',
            'variables_json' => [
                'template_name' => 'purchase_payment_reminder',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'reservation_expires_at'],
            ],
            'priority' => 500,
            'is_ai_eligible' => false,
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Cliente Recordatorio',
        ]);
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Pendiente',
        ]);
        $purchase = Purchase::factory()
            ->for($customer)
            ->for($raffle)
            ->pendingPayment()
            ->create([
                'reserved_until' => now()->addHour(),
            ]);

        ConversationState::factory()->for($customer)->for($purchase)->create([
            'last_user_message_at' => now()->subDays(2),
        ]);

        $message = app(SendPurchasePaymentReminderWhatsappAction::class)->execute($purchase);
        $duplicate = app(SendPurchasePaymentReminderWhatsappAction::class)->execute($purchase);

        $this->assertNotNull($message);
        $this->assertNull($duplicate);

        $outboundMessage = WhatsappMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('template', $outboundMessage->message_type);
        $this->assertSame('purchase_payment_reminder', data_get($outboundMessage->payload_json, 'intent'));
        $this->assertSame($purchase->id, data_get($outboundMessage->payload_json, 'purchase_id'));
        $this->assertSame('purchase_payment_reminder', data_get($outboundMessage->payload_json, 'template.name'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class, 1);
    }
}
