<?php

namespace Tests\Feature\Filament\Payments;

use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentResourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_purchase_customer_and_proof_relations_for_review(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+573001112233',
        ]);

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Pagos',
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

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001']);

        $message = WhatsappMessage::factory()
            ->for($customer)
            ->inboundImage()
            ->create();

        $payment = app(SubmitPaymentProofAction::class)->execute(
            purchase: $purchase,
            whatsappMessage: $message,
            storagePath: 'payment-proofs/test-proof.png',
            originalFilename: 'voucher.png',
            mimeType: 'image/png',
            fileSize: 12345,
        );

        $resourceRecord = PaymentResource::getEloquentQuery()->findOrFail($payment->id);

        $this->assertSame('pending_review', $resourceRecord->status);
        $this->assertSame('+573001112233', $resourceRecord->purchase?->customer?->phone);
        $this->assertSame('Rifa Pagos', $resourceRecord->purchase?->raffle?->title);
        $this->assertCount(1, $resourceRecord->proofs);
        $this->assertNotNull($resourceRecord->review_due_at);
        $this->assertFalse($resourceRecord->isReviewOverdue());
        $this->assertSame('payment-proofs/test-proof.png', $resourceRecord->proofs->first()?->storage_path);
    }
}
