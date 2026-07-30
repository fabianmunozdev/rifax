<?php

namespace Tests\Feature\Raffles;

use App\Actions\Raffles\PublishRaffleResultAction;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Actions\Purchases\ReserveNumbersAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PublishRaffleResultActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_closes_a_published_raffle_and_marks_the_matching_number_as_winner(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 4,
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '0001',
            'status' => 'available',
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '0012',
            'status' => 'paid',
        ]);

        app(PublishRaffleResultAction::class)->execute($raffle, '12');

        $this->assertDatabaseHas('raffles', [
            'id' => $raffle->id,
            'status' => 'closed',
            'result_number' => '0012',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0012',
            'status' => 'winner',
        ]);

        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '0001',
            'status' => 'available',
        ]);
    }

    public function test_it_closes_the_raffle_even_if_the_result_number_is_not_present_in_the_catalog(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 4,
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '0001',
            'status' => 'available',
        ]);

        app(PublishRaffleResultAction::class)->execute($raffle, '9999');

        $this->assertDatabaseHas('raffles', [
            'id' => $raffle->id,
            'status' => 'closed',
            'result_number' => '9999',
        ]);

        $this->assertDatabaseMissing('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'status' => 'winner',
        ]);
    }

    public function test_it_normalizes_the_result_using_the_configured_raffle_digits(): void
    {
        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 3,
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '123',
            'status' => 'paid',
        ]);

        app(PublishRaffleResultAction::class)->execute($raffle, '23');

        $this->assertDatabaseHas('raffles', [
            'id' => $raffle->id,
            'result_number' => '023',
        ]);
    }

    public function test_it_blocks_publishing_the_result_when_there_are_pending_purchases(): void
    {
        $customer = Customer::factory()->create();
        $raffle = Raffle::factory()->published()->create([
            'number_digits' => 4,
        ]);

        PaymentMethod::query()->create([
            'name' => 'Transferencia',
            'slug' => 'transferencia-pendiente-sorteo',
            'status' => 'active',
            'instructions' => 'Transfiere y envia el comprobante.',
            'account_holder' => 'Rifax SAS',
            'account_reference' => '123456789',
            'details_json' => ['bank' => 'Demo'],
            'sort_order' => 10,
            'is_visible' => true,
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '0012',
            'status' => 'available',
        ]);

        /** @var Purchase $purchase */
        $purchase = app(ReserveNumbersAction::class)->execute($customer, $raffle, ['0012']);

        $this->assertSame('reserved', $purchase->status);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot publish the raffle result while there are purchases pending payment validation or active reservations.');

        app(PublishRaffleResultAction::class)->execute($raffle, '12');
    }
}
