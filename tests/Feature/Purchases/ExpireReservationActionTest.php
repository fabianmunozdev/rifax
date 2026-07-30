<?php

namespace Tests\Feature\Purchases;

use App\Actions\Purchases\ExpireReservationAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireReservationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_the_reservation_and_releases_the_numbers(): void
    {
        $customer = Customer::factory()->create();
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
        $reservation = $purchase->reservation()->firstOrFail();

        $reservation->update([
            'expires_at' => now()->subMinute(),
        ]);

        app(ExpireReservationAction::class)->execute($reservation);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0001',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0002',
            'status' => 'available',
        ]);

        $this->assertDatabaseMissing('purchase_numbers', [
            'purchase_id' => $purchase->id,
        ]);

        $this->assertDatabaseHas('conversation_states', [
            'customer_id' => $customer->id,
            'status' => 'purchase_expired',
            'purchase_id' => $purchase->id,
        ]);
    }

    public function test_it_does_not_expire_a_purchase_when_payment_proof_was_submitted(): void
    {
        $customer = Customer::factory()->create();
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
        $reservation = $purchase->reservation()->firstOrFail();

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
        );

        $reservation->refresh();
        $reservation->update([
            'expires_at' => now()->subMinute(),
        ]);

        app(ExpireReservationAction::class)->execute($reservation);

        $purchase->refresh();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'payment_submitted',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0001',
            'status' => 'reserved',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0002',
            'status' => 'reserved',
        ]);

        $this->assertDatabaseHas('conversation_states', [
            'customer_id' => $customer->id,
            'status' => 'purchase_under_review',
            'purchase_id' => $purchase->id,
        ]);
    }
}
