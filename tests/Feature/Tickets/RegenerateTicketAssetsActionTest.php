<?php

namespace Tests\Feature\Tickets;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Actions\Tickets\RegenerateTicketAssetsAction;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegenerateTicketAssetsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_regenerates_ticket_assets_and_increments_the_version(): void
    {
        Storage::fake('public');

        $customer = \App\Models\Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Regenerable',
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

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
        );

        $approvedPayment = app(ApprovePaymentAction::class)->execute($payment, $reviewer);
        $ticket = $approvedPayment->purchase->fresh('ticket')->ticket;

        $this->assertSame(1, $ticket->version);
        $this->assertStringContainsString('ticket-v1.svg', $ticket->image_path);

        $regeneratedTicket = app(RegenerateTicketAssetsAction::class)->execute($ticket);

        $this->assertSame(2, $regeneratedTicket->version);
        $this->assertStringContainsString('ticket-v2.svg', $regeneratedTicket->image_path);
        $this->assertStringContainsString('ticket-thumb-v2.svg', $regeneratedTicket->thumbnail_path);
        $this->assertTrue(Storage::disk('public')->exists($regeneratedTicket->image_path));
        $this->assertTrue(Storage::disk('public')->exists($regeneratedTicket->thumbnail_path));
    }
}
