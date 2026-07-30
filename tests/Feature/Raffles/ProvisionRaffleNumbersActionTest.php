<?php

namespace Tests\Feature\Raffles;

use App\Actions\Raffles\ProvisionRaffleNumbersAction;
use App\Models\Purchase;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProvisionRaffleNumbersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_the_full_catalog_for_a_draft_raffle_based_on_digits(): void
    {
        $raffle = Raffle::factory()->create([
            'status' => 'draft',
            'number_digits' => 2,
        ]);

        $createdCount = app(ProvisionRaffleNumbersAction::class)->execute($raffle);

        $this->assertSame(100, $createdCount);
        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '00',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '99',
            'status' => 'available',
        ]);
    }

    public function test_it_blocks_provisioning_when_the_raffle_already_has_activity(): void
    {
        $raffle = Raffle::factory()->create([
            'status' => 'draft',
        ]);

        Purchase::factory()->for($raffle)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot provision raffle numbers after raffle activity exists.');

        app(ProvisionRaffleNumbersAction::class)->execute($raffle);
    }

    public function test_it_uses_the_configured_raffle_number_digits_when_provisioning(): void
    {
        $raffle = Raffle::factory()->create([
            'status' => 'draft',
            'number_digits' => 3,
        ]);

        $createdCount = app(ProvisionRaffleNumbersAction::class)->execute($raffle);

        $this->assertSame(1000, $createdCount);
        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '000',
        ]);
        $this->assertDatabaseHas('raffle_numbers', [
            'raffle_id' => $raffle->id,
            'number' => '999',
        ]);
    }

    public function test_it_only_adds_missing_numbers_when_the_catalog_was_partially_generated(): void
    {
        $raffle = Raffle::factory()->create([
            'status' => 'draft',
            'number_digits' => 2,
        ]);

        $raffle->numbers()->createMany([
            ['number' => '00', 'status' => 'available'],
            ['number' => '01', 'status' => 'available'],
            ['number' => '02', 'status' => 'available'],
        ]);

        $createdCount = app(ProvisionRaffleNumbersAction::class)->execute($raffle);

        $this->assertSame(97, $createdCount);
        $this->assertSame(100, $raffle->numbers()->count());
    }
}
