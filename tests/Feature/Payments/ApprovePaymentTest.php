<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ApprovePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_approves_the_payment_marks_the_purchase_as_paid_and_updates_numbers(): void
    {
        Storage::fake('public');

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

        app(ApprovePaymentAction::class)->execute($payment, $reviewer);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'review_due_at' => null,
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0001',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0002',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('conversation_states', [
            'customer_id' => $customer->id,
            'purchase_id' => $purchase->id,
            'payment_id' => $payment->id,
            'status' => 'purchase_paid',
        ]);

        $this->assertDatabaseHas('tickets', [
            'purchase_id' => $purchase->id,
        ]);
    }

    public function test_it_blocks_approving_payments_for_expired_purchases(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create();

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-expired',
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

        $purchase->forceFill([
            'status' => 'expired',
            'expired_at' => now(),
        ])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot approve payment for an expired purchase.');

        app(ApprovePaymentAction::class)->execute($payment, $reviewer);
    }
}
