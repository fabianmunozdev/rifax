<?php

namespace Tests\Feature\Filament\Conversations;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationResourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scopes_to_whatsapp_and_loads_related_context(): void
    {
        $customer = Customer::factory()->create();
        $raffle = Raffle::factory()->create();
        $purchase = Purchase::factory()->for($customer)->underReview()->for($raffle)->create();
        $payment = Payment::factory()->for($purchase)->pendingReview()->create();

        $conversation = ConversationState::factory()->for($customer)->create([
            'channel' => 'whatsapp',
            'status' => 'purchase_payment_instructions',
            'current_raffle_id' => $raffle->id,
            'purchase_id' => $purchase->id,
            'payment_id' => $payment->id,
            'context_expires_at' => now()->subMinute(),
            'metadata_json' => [
                'follow_up_required' => true,
            ],
        ]);

        $record = ConversationResource::getEloquentQuery()->findOrFail($conversation->id);

        $this->assertSame($customer->id, $record->customer?->id);
        $this->assertSame($purchase->id, $record->purchase?->id);
        $this->assertSame('pending_review', $record->payment?->status);
        $this->assertTrue(ConversationState::query()->expired()->whereKey($conversation)->exists());
        $this->assertTrue(ConversationState::query()->needsAttention()->whereKey($conversation)->exists());
        $this->assertSame($conversation->id, ConversationResource::getEloquentQuery()->sole()->id);
    }
}
