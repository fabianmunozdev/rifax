<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\FinanceReviewHealthWidget;
use App\Filament\Widgets\RaffleFinanceSummaryWidget;
use App\Filament\Widgets\RecentPendingPaymentsWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFinancialWidgetVisibilityByRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_dashboard_widgets_are_visible_for_admin_and_finance(): void
    {
        foreach ([
            User::factory()->admin()->create(),
            User::factory()->finance()->create(),
        ] as $user) {
            $this->actingAs($user);

            $this->assertTrue(FinanceReviewHealthWidget::canView());
            $this->assertTrue(RecentPendingPaymentsWidget::canView());
            $this->assertTrue(RaffleFinanceSummaryWidget::canView());
        }
    }

    public function test_financial_dashboard_widgets_are_hidden_for_operator_support_and_inactive_users(): void
    {
        foreach ([
            User::factory()->operator()->create(),
            User::factory()->support()->create(),
            User::factory()->finance()->inactive()->create(),
        ] as $user) {
            $this->actingAs($user);

            $this->assertFalse(FinanceReviewHealthWidget::canView());
            $this->assertFalse(RecentPendingPaymentsWidget::canView());
            $this->assertFalse(RaffleFinanceSummaryWidget::canView());
        }
    }
}
