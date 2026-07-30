<?php

namespace Tests\Feature\Filament\Customers;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerResourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_current_conversation_and_pending_purchase_counts(): void
    {
        $customer = Customer::factory()->create();

        Purchase::factory()->for($customer)->paid()->create();
        Purchase::factory()->for($customer)->underReview()->create();

        ConversationState::factory()->for($customer)->create([
            'status' => 'purchase_payment_instructions',
            'channel' => 'whatsapp',
        ]);

        $record = CustomerResource::getEloquentQuery()->findOrFail($customer->id);

        $this->assertSame(2, $record->purchases_count);
        $this->assertSame(1, $record->pending_purchases_count);
        $this->assertSame('purchase_payment_instructions', $record->currentConversationState?->status);
    }
}
