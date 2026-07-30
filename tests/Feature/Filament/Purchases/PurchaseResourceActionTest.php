<?php

namespace Tests\Feature\Filament\Purchases;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseResourceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_links_use_resource_urls(): void
    {
        $purchase = Purchase::factory()->paid()->create();
        $payment = Payment::factory()->for($purchase)->approved()->create();
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

        $purchase->load(['latestPayment', 'ticket', 'raffle']);

        $this->assertSame(
            PaymentResource::getUrl('view', ['record' => $payment]),
            PurchaseResource::makeOpenLatestPaymentAction()->record($purchase)->getUrl(),
        );

        $this->assertSame(
            TicketResource::getUrl('view', ['record' => $ticket]),
            PurchaseResource::makeOpenTicketAction()->record($purchase)->getUrl(),
        );

        $this->assertSame(
            RaffleResource::getUrl('view', ['record' => $purchase->raffle]),
            PurchaseResource::makeOpenRaffleAction()->record($purchase)->getUrl(),
        );
    }
}
