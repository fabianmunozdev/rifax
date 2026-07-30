<?php

namespace Tests\Feature\Filament\Payments;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Purchase;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentResourceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_navigation_actions_use_resource_urls(): void
    {
        $purchase = Purchase::factory()->paid()->create();
        $ticket = Ticket::query()->create([
            'purchase_id' => $purchase->id,
            'code' => 'TK-'.$purchase->id,
            'verification_token' => 'token-'.$purchase->id,
            'public_url' => 'https://rifax.test/tickets/'.$purchase->id,
            'image_path' => 'tickets/'.$purchase->id.'.svg',
            'thumbnail_path' => null,
            'version' => 1,
            'generated_at' => now(),
        ]);
        $payment = Payment::factory()->for($purchase)->approved()->create();

        $payment->load(['purchase.raffle', 'purchase.ticket']);

        $this->assertSame(
            PurchaseResource::getUrl('view', ['record' => $purchase]),
            PaymentResource::makeOpenPurchaseAction()->record($payment)->getUrl(),
        );

        $this->assertSame(
            RaffleResource::getUrl('view', ['record' => $purchase->raffle]),
            PaymentResource::makeOpenRaffleAction()->record($payment)->getUrl(),
        );

        $this->assertSame(
            TicketResource::getUrl('view', ['record' => $ticket]),
            PaymentResource::makeOpenTicketAction()->record($payment)->getUrl(),
        );
    }

    public function test_open_payment_proof_action_uses_public_storage_asset_url(): void
    {
        $payment = Payment::factory()->create();

        PaymentProof::query()->create([
            'payment_id' => $payment->id,
            'whatsapp_message_id' => null,
            'storage_disk' => 'public',
            'storage_path' => 'payment-proofs/test-proof.png',
            'original_filename' => 'test-proof.png',
            'mime_type' => 'image/png',
            'file_size' => 12345,
            'uploaded_at' => now(),
            'metadata_json' => [],
        ]);

        $payment->load('proofs');

        $this->assertSame(
            asset('storage/payment-proofs/test-proof.png'),
            PaymentResource::makeOpenProofAction()->record($payment)->getUrl(),
        );
    }
}
