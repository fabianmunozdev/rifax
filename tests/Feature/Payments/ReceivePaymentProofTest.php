<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReceivePaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_the_payment_proof_and_moves_the_purchase_to_review(): void
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

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
            originalFilename: 'proof.png',
            mimeType: 'image/png',
            fileSize: 2048,
            metadata: ['source' => 'test'],
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'purchase_id' => $purchase->id,
            'status' => 'pending_review',
            'proof_channel' => 'whatsapp',
        ]);

        $this->assertDatabaseHas('payment_proofs', [
            'payment_id' => $payment->id,
            'whatsapp_message_id' => $message->id,
            'storage_path' => 'payment-proofs/test-proof.png',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'payment_submitted',
        ]);

        $this->assertDatabaseHas('conversation_states', [
            'customer_id' => $customer->id,
            'purchase_id' => $purchase->id,
            'payment_id' => $payment->id,
            'status' => 'purchase_under_review',
        ]);
    }

    public function test_it_rejects_payment_proofs_after_the_draw_time(): void
    {
        $customer = Customer::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'draw_date' => now()->addMinute()->toDateString(),
            'draw_time' => now()->addMinute()->format('H:i:s'),
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-draw',
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

        $raffle->forceFill([
            'draw_date' => now()->subMinute()->toDateString(),
            'draw_time' => now()->subMinute()->format('H:i:s'),
        ])->save();

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The raffle draw time has already been reached. Payment proofs can no longer be received for this raffle.');

        app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
        );
    }
}
