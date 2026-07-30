<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\RejectPaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejectPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_the_payment_and_sets_the_purchase_back_to_rejected(): void
    {
        $customer = Customer::factory()->create();
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

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
        );

        app(RejectPaymentAction::class)->execute($payment, 'El comprobante no coincide.', $reviewer);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'review_due_at' => null,
            'rejection_reason' => 'El comprobante no coincide.',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('conversation_states', [
            'customer_id' => $customer->id,
            'purchase_id' => $purchase->id,
            'payment_id' => $payment->id,
            'status' => 'purchase_rejected',
        ]);
    }
}
