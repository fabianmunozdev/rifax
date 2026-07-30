<?php

namespace Tests\Feature\Filament\Raffles;

use App\Filament\Resources\Raffles\RaffleResource;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaffleResourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_operational_counts_and_winner_information(): void
    {
        $raffle = Raffle::factory()->create([
            'title' => 'Rifa Cerrada',
            'status' => 'closed',
            'result_number' => '0004',
            'result_published_at' => now(),
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '0001',
            'status' => 'available',
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '0002',
            'status' => 'reserved',
        ]);

        RaffleNumber::factory()->for($raffle)->create([
            'number' => '0003',
            'status' => 'paid',
        ]);

        $winner = RaffleNumber::factory()->for($raffle)->create([
            'number' => '0004',
            'status' => 'winner',
        ]);

        Purchase::factory()->for($raffle)->paid()->create();
        Purchase::factory()->for($raffle)->create([
            'status' => 'reserved',
        ]);

        $resourceRecord = RaffleResource::getEloquentQuery()->findOrFail($raffle->id);

        $this->assertSame('Rifa Cerrada', $resourceRecord->title);
        $this->assertSame(4, $resourceRecord->numbers_count);
        $this->assertSame(1, $resourceRecord->available_numbers_count);
        $this->assertSame(1, $resourceRecord->reserved_numbers_count);
        $this->assertSame(2, $resourceRecord->paid_numbers_count);
        $this->assertSame(2, $resourceRecord->purchases_count);
        $this->assertSame(1, $resourceRecord->paid_purchases_count);
        $this->assertTrue($resourceRecord->relationLoaded('winnerNumber'));
        $this->assertSame($winner->id, $resourceRecord->winnerNumber?->id);
        $this->assertSame('0004', $resourceRecord->winnerNumber?->number);
    }
}
