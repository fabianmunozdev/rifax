<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\FinanceReviewHealthWidget;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceReviewHealthWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_finance_review_metrics(): void
    {
        $this->actingAs(User::factory()->finance()->create());

        Payment::factory()->pendingReview()->create([
            'proof_received_at' => now()->subHours(3),
        ]);

        Payment::factory()->approved()->create([
            'expected_amount' => 40000,
            'received_amount' => 40000,
            'reviewed_at' => now()->subDays(3),
        ]);

        Purchase::factory()->paid()->create([
            'total_amount' => 30000,
            'paid_at' => now()->subDays(5),
        ]);

        Purchase::factory()->pendingPayment()->create([
            'reserved_until' => now()->addMinutes(20),
        ]);

        Livewire::test(FinanceReviewHealthWidget::class)
            ->assertSee('Finance review health')
            ->assertSee('Pending payment review')
            ->assertSee('Approved revenue (30d)')
            ->assertSee('Paid purchases (30d)')
            ->assertSee('Expiring reservations')
            ->assertSee('40,000')
            ->assertSee('1');
    }
}
