<?php

namespace Tests\Feature\Filament\Purchases;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseResourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_latest_payment_and_ticket_for_a_paid_purchase(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Operativa',
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

        app(ApprovePaymentAction::class)->execute($payment, $reviewer);

        $resourceRecord = PurchaseResource::getEloquentQuery()->findOrFail($purchase->id);

        $this->assertSame('paid', $resourceRecord->status);
        $this->assertSame('approved', $resourceRecord->latestPayment?->status);
        $this->assertSame('0001, 0002', $resourceRecord->numbers->pluck('number')->implode(', '));
        $this->assertNotNull($resourceRecord->ticket?->code);
    }
}
