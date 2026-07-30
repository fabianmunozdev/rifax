<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\RecentPendingPaymentsWidget;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecentPendingPaymentsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_recent_pending_payments(): void
    {
        $this->actingAs(User::factory()->finance()->create());

        $pendingPayment = Payment::factory()->pendingReview()->create([
            'expected_amount' => 25000,
        ]);

        Payment::factory()->approved()->create([
            'expected_amount' => 12000,
        ]);

        Livewire::test(RecentPendingPaymentsWidget::class)
            ->assertSee('Recent pending payments')
            ->assertSee((string) $pendingPayment->id)
            ->assertSee((string) $pendingPayment->purchase_id)
            ->assertSee('25,000')
            ->assertDontSee('12,000');
    }
}
