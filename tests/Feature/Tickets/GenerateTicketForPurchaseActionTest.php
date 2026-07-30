<?php

namespace Tests\Feature\Tickets;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Actions\Tickets\GenerateTicketForPurchaseAction;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateTicketForPurchaseActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_generate_a_duplicate_ticket_for_the_same_purchase(): void
    {
        Storage::fake('public');

        $customer = \App\Models\Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create();

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
        $firstTicket = $approvedPayment->purchase->fresh('ticket')->ticket;
        $secondTicket = app(GenerateTicketForPurchaseAction::class)->execute($approvedPayment->purchase->fresh());

        $this->assertSame($firstTicket->id, $secondTicket->id);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertNotNull($secondTicket->image_path);
        $this->assertNotNull($secondTicket->thumbnail_path);
        $this->assertTrue(Storage::disk('public')->exists($secondTicket->image_path));
        $this->assertTrue(Storage::disk('public')->exists($secondTicket->thumbnail_path));
    }
}
