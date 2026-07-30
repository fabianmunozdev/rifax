<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\RaffleFinanceSummaryWidget;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RaffleFinanceSummaryWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_finance_summary_by_raffle(): void
    {
        $this->actingAs(User::factory()->finance()->create());

        $raffle = Raffle::factory()->published()->create([
            'title' => 'Rifa Finanzas',
        ]);

        RaffleNumber::factory()->for($raffle)->create(['status' => 'available']);
        RaffleNumber::factory()->for($raffle)->create(['status' => 'available']);
        RaffleNumber::factory()->for($raffle)->create(['status' => 'paid']);

        Purchase::factory()->for($raffle)->paid()->create([
            'total_amount' => 50000,
        ]);

        $pendingPurchase = Purchase::factory()->for($raffle)->underReview()->create();

        Payment::factory()->for($pendingPurchase)->pendingReview()->create([
            'expected_amount' => 30000,
        ]);

        Livewire::test(RaffleFinanceSummaryWidget::class)
            ->assertSee('Raffle finance summary')
            ->assertSee('Rifa Finanzas')
            ->assertSee('Available')
            ->assertSee('Pending review')
            ->assertSee('Paid purchases')
            ->assertSee('Approved revenue')
            ->assertSee('50,000')
            ->assertSee('2')
            ->assertSee('1');
    }
}
