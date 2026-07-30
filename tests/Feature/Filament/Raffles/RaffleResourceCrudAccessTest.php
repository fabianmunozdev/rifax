<?php

namespace Tests\Feature\Filament\Raffles;

use App\Filament\Resources\Raffles\RaffleResource;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaffleResourceCrudAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_creating_raffles(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(RaffleResource::canCreate());
    }

    public function test_it_allows_editing_open_raffles_and_blocks_closed_raffles(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $draftRaffle = Raffle::factory()->create([
            'status' => 'draft',
        ]);

        $closedRaffle = Raffle::factory()->create([
            'status' => 'closed',
            'result_published_at' => now(),
        ]);

        $this->assertTrue(RaffleResource::canEdit($draftRaffle));
        $this->assertFalse(RaffleResource::canEdit($closedRaffle));
    }

    public function test_it_only_allows_deleting_draft_raffles_without_activity(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $draftRaffle = Raffle::factory()->create([
            'status' => 'draft',
        ]);

        $activeRaffle = Raffle::factory()->create([
            'status' => 'draft',
        ]);

        Purchase::factory()->for($activeRaffle)->create();

        $this->assertTrue(RaffleResource::canDelete($draftRaffle));
        $this->assertFalse(RaffleResource::canDelete($activeRaffle));
    }
}
