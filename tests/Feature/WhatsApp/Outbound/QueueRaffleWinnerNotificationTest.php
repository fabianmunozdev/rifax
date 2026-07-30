<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Actions\Raffles\PublishRaffleResultAction;
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

class QueueRaffleWinnerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_text_notification_for_the_winner_inside_the_24h_window(): void
    {
        Queue::fake();
        Storage::fake('public');

        config()->set('services.whatsapp.send_enabled', true);
        config()->set('app.url', 'http://localhost');

        $customer = Customer::factory()->create([
            'name' => 'Cliente Ganador',
        ]);
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Ganadora',
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

        app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        app(PublishRaffleResultAction::class)->execute($raffle, '2');

        $outboundMessage = WhatsappMessage::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->firstOrFail();

        $ticket = $purchase->fresh('ticket')->ticket;

        $this->assertSame('text', $outboundMessage->message_type);
        $this->assertSame('raffle_winner_notification', data_get($outboundMessage->payload_json, 'intent'));
        $this->assertSame($raffle->id, data_get($outboundMessage->payload_json, 'raffle_id'));
        $this->assertSame($ticket?->id, data_get($outboundMessage->payload_json, 'ticket_id'));
        $this->assertSame('0002', data_get($outboundMessage->payload_json, 'winning_number'));
        $this->assertStringContainsString('0002', (string) $outboundMessage->body_text);

        Queue::assertPushed(DispatchWhatsappMessageJob::class, 3);
    }

    public function test_it_queues_a_template_notification_for_the_winner_outside_the_24h_window(): void
    {
        Queue::fake();
        Storage::fake('public');

        config()->set('services.whatsapp.send_enabled', true);
        config()->set('app.url', 'http://localhost');

        $customer = Customer::factory()->create([
            'name' => 'Cliente Plantilla',
        ]);
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Plantilla',
        ]);

        ContentEntry::query()->create([
            'type' => 'template_bridge',
            'key' => 'template.raffle.winner.notification.test',
            'title' => 'Ganador fuera de ventana',
            'category' => 'raffles',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'published',
            'trigger_intent' => 'raffle_winner_notification',
            'body_text' => 'Hola {customer_name}, tu numero {winning_number} fue ganador en {raffle_title}. Ticket: {ticket_code}. Link: {ticket_url}',
            'variables_json' => [
                'template_name' => 'raffle_winner_notification',
                'language' => 'es_CO',
                'body_parameters' => ['customer_name', 'raffle_title', 'winning_number', 'ticket_code', 'ticket_url'],
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

        app(ApprovePaymentAction::class)->execute($payment, $reviewer);

        ConversationState::query()
            ->where('purchase_id', $purchase->id)
            ->update([
                'last_user_message_at' => now()->subDays(2),
            ]);

        app(PublishRaffleResultAction::class)->execute($raffle, '2');

        $outboundMessage = WhatsappMessage::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->firstOrFail();

        $ticket = $purchase->fresh('ticket')->ticket;

        $this->assertSame('template', $outboundMessage->message_type);
        $this->assertSame('raffle_winner_notification', data_get($outboundMessage->payload_json, 'intent'));
        $this->assertSame('raffle_winner_notification', data_get($outboundMessage->payload_json, 'template.name'));
        $this->assertSame($ticket?->id, data_get($outboundMessage->payload_json, 'ticket_id'));
        $this->assertSame('0002', data_get($outboundMessage->payload_json, 'winning_number'));

        Queue::assertPushed(DispatchWhatsappMessageJob::class, 3);
    }
}
