<?php

namespace Tests\Feature\WhatsApp\Outbound;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Jobs\DispatchWhatsappMessageJob;
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

class QueueTicketDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_the_ticket_document_when_the_conversation_is_inside_the_24h_window(): void
    {
        Queue::fake([
            DispatchWhatsappMessageJob::class,
        ]);
        Storage::fake('public');

        config()->set('services.whatsapp.send_enabled', true);
        config()->set('app.url', 'http://localhost');

        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Documento',
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

        $documentMessage = WhatsappMessage::query()
            ->where('customer_id', $customer->id)
            ->where('direction', 'outbound')
            ->where('message_type', 'document')
            ->latest('id')
            ->firstOrFail();

        $ticket = $purchase->fresh('ticket')->ticket;
        $expectedExtension = pathinfo($ticket->image_path, PATHINFO_EXTENSION) ?: 'svg';

        $this->assertSame('queued', $documentMessage->status);
        $this->assertSame($ticket->id, data_get($documentMessage->payload_json, 'ticket_id'));
        $this->assertStringContainsString('/storage/tickets/', (string) data_get($documentMessage->payload_json, 'document.link'));
        $this->assertSame('ticket-'.$ticket->code.'.'.$expectedExtension, data_get($documentMessage->payload_json, 'document.filename'));
        $this->assertTrue(Storage::disk('public')->exists($ticket->image_path));

        Queue::assertPushed(DispatchWhatsappMessageJob::class, 2);
    }
}
