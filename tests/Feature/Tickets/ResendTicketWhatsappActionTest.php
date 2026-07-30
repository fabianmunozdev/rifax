<?php

namespace Tests\Feature\Tickets;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Actions\Tickets\ResendTicketWhatsappAction;
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

class ResendTicketWhatsappActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requeues_the_ticket_as_a_document_inside_the_24h_window(): void
    {
        Queue::fake();
        Storage::fake('public');

        config()->set('services.whatsapp.send_enabled', true);

        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Reenvio Directo',
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

        $approvedPayment = app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        $ticket = $approvedPayment->purchase->fresh('ticket')->ticket;

        $result = app(ResendTicketWhatsappAction::class)->execute($ticket);

        $this->assertSame('document', $result);

        $outboundMessage = WhatsappMessage::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('document', $outboundMessage->message_type);
        $this->assertSame($ticket->id, data_get($outboundMessage->payload_json, 'ticket_id'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class);
    }

    public function test_it_falls_back_to_template_or_text_when_resending_outside_the_24h_window(): void
    {
        Queue::fake();
        Storage::fake('public');

        config()->set('services.whatsapp.send_enabled', true);

        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Reenvio Diferido',
        ]);

        ContentEntry::query()->create([
            'type' => 'template_bridge',
            'key' => 'template.payment.approved.ticket.resend',
            'title' => 'Pago aprobado fuera de ventana',
            'category' => 'payments',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => 'payment_approved_ticket',
            'body_text' => 'Hola {customer_name}, tu boleto de {raffle_title} sigue disponible. Codigo: {ticket_code}. Link: {ticket_url}',
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

        $approvedPayment = app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        $ticket = $approvedPayment->purchase->fresh('ticket')->ticket;

        $result = app(ResendTicketWhatsappAction::class)->execute($ticket);

        $this->assertSame('template_or_text', $result);

        $outboundMessage = WhatsappMessage::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('template', $outboundMessage->message_type);
        $this->assertSame('payment_approved_ticket', data_get($outboundMessage->payload_json, 'template.name'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class);
    }
}
