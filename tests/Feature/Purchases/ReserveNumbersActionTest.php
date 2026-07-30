<?php

namespace Tests\Feature\Purchases;

use App\Actions\Purchases\ReserveNumbersAction;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReserveNumbersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_reservation_purchase_and_updates_conversation_state(): void
    {
        $customer = Customer::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'min_numbers_per_purchase' => 2,
            'price_per_number' => 15000,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Nequi',
            'slug' => 'nequi',
            'status' => 'active',
            'instructions' => 'Paga y envia soporte por WhatsApp.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '3001234567',
            'details_json' => ['wallet' => 'Nequi'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);
        RaffleNumber::factory()->for($raffle)->create(['number' => '0002']);

        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001', '0002']);

        $this->assertDatabaseHas('reservations', [
            'id' => $purchase->reservation_id,
            'customer_id' => $customer->id,
            'raffle_id' => $raffle->id,
            'status' => 'active',
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'customer_id' => $customer->id,
            'raffle_id' => $raffle->id,
            'status' => 'reserved',
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('purchase_numbers', [
            'purchase_id' => $purchase->id,
            'number' => '0001',
        ]);

        $this->assertDatabaseHas('purchase_numbers', [
            'purchase_id' => $purchase->id,
            'number' => '0002',
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
            'status' => 'purchase_payment_instructions',
            'purchase_id' => $purchase->id,
        ]);

        $conversationState = ConversationState::query()
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->assertSame('manual', $conversationState->selection_mode);
        $this->assertCount(2, $conversationState->selected_numbers_json);
    }

    public function test_it_rejects_new_reservations_when_the_draw_time_has_been_reached(): void
    {
        $customer = Customer::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'draw_date' => now()->subMinute()->toDateString(),
            'draw_time' => now()->subMinute()->format('H:i:s'),
        ]);

        RaffleNumber::factory()->for($raffle)->create(['number' => '0001']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This raffle is no longer accepting reservations because the draw time has been reached.');

        app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0001']);
    }
}
