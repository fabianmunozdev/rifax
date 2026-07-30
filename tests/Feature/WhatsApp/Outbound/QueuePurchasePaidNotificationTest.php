<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Jobs\DispatchWhatsappMessageJob;
use App\Models\ContentEntry;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QueuePurchasePaidNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_template_notification_when_the_conversation_is_outside_the_24h_window(): void
    {
        Queue::fake();
        Storage::fake('public');

        config()->set('services.whatsapp.send_enabled', true);

        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create();

        ContentEntry::query()->create([
            'type' => 'template_bridge',
            'key' => 'template.payment.approved.ticket.test',
            'title' => 'Pago aprobado fuera de ventana',
            'category' => 'payments',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => 'payment_approved_ticket',
            'body_text' => 'Hola {customer_name}, tu pago para {raffle_title} fue aprobado. Tu boleto sera compartido en breve por este medio.',
            'variables_json' => [
                'template_name' => 'payment_approved_ticket',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'ticket_code', 'ticket_url'],
            ],
            'priority' => 500,
            'is_ai_eligible' => false,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $whatsappMessage = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $whatsappMessage,
            storagePath: 'payment-proofs/test-proof.png',
        );

        ConversationState::query()
            ->where('purchase_id', $purchase->id)
            ->update([
                'last_user_message_at' => now()->subDays(2),
            ]);

        app(ApprovePaymentAction::class)->execute($payment, $reviewer);

        $outboundMessage = WhatsappMessage::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('template', $outboundMessage->message_type);
        $this->assertSame('queued', $outboundMessage->status);
        $this->assertSame('payment_approved_ticket', data_get($outboundMessage->payload_json, 'template.name'));
        $this->assertSame($purchase->fresh('ticket')->ticket?->id, data_get($outboundMessage->payload_json, 'ticket_id'));
        $this->assertNotEmpty(data_get($outboundMessage->payload_json, 'template.components.0.parameters.2.text'));
        $this->assertStringContainsString('/tickets/', (string) data_get($outboundMessage->payload_json, 'template.components.0.parameters.3.text'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class);
    }
}
