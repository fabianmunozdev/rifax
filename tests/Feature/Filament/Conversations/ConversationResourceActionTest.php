<?php

namespace Tests\Feature\Filament\Conversations;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationResourceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_actions_use_resource_urls(): void
    {
        $customer = Customer::factory()->create();
        $raffle = Raffle::factory()->create();
        $purchase = Purchase::factory()->for($customer)->for($raffle)->create();
        $payment = Payment::factory()->for($purchase)->create();
        $message = WhatsappMessage::factory()->for($customer)->create();

        $conversation = ConversationState::factory()->for($customer)->create([
            'current_raffle_id' => $raffle->id,
            'purchase_id' => $purchase->id,
            'payment_id' => $payment->id,
            'last_outbound_message_id' => $message->id,
        ]);

        $conversation->load(['customer', 'currentRaffle', 'purchase', 'payment']);

        $this->assertSame(
            CustomerResource::getUrl('view', ['record' => $customer]),
            ConversationResource::makeOpenCustomerAction()->record($conversation)->getUrl(),
        );

        $this->assertSame(
            PurchaseResource::getUrl('view', ['record' => $purchase]),
            ConversationResource::makeOpenPurchaseAction()->record($conversation)->getUrl(),
        );

        $this->assertSame(
            PaymentResource::getUrl('view', ['record' => $payment]),
            ConversationResource::makeOpenPaymentAction()->record($conversation)->getUrl(),
        );

        $this->assertSame(
            RaffleResource::getUrl('view', ['record' => $raffle]),
            ConversationResource::makeOpenCurrentRaffleAction()->record($conversation)->getUrl(),
        );

        $this->assertSame(
            WhatsappMessageResource::getUrl('view', ['record' => $message]),
            ConversationResource::makeOpenLastWhatsappAction()->record($conversation)->getUrl(),
        );
    }
}
