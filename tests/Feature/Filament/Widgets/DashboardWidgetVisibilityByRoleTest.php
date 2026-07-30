<?php

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\RaffleWinnerNotificationHealthWidget;
use App\Filament\Widgets\RafflesNeedingAttentionWidget;
use App\Filament\Widgets\RecentWhatsappFailuresWidget;
use App\Filament\Widgets\RecentWinnerNotificationsWidget;
use App\Filament\Widgets\TicketDeliveryAttentionHealthWidget;
use App\Filament\Widgets\TicketsNeedingAttentionWidget;
use App\Filament\Widgets\WhatsappChannelHealthWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetVisibilityByRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_dashboard_widgets_are_visible_for_admin_operator_and_support(): void
    {
        foreach ([
            User::factory()->admin()->create(),
            User::factory()->operator()->create(),
            User::factory()->support()->create(),
        ] as $user) {
            $this->actingAs($user);

            $this->assertTrue(WhatsappChannelHealthWidget::canView());
            $this->assertTrue(RecentWhatsappFailuresWidget::canView());
            $this->assertTrue(TicketDeliveryAttentionHealthWidget::canView());
            $this->assertTrue(TicketsNeedingAttentionWidget::canView());
            $this->assertTrue(RaffleWinnerNotificationHealthWidget::canView());
            $this->assertTrue(RecentWinnerNotificationsWidget::canView());
            $this->assertTrue(RafflesNeedingAttentionWidget::canView());
        }
    }

    public function test_operational_dashboard_widgets_are_hidden_for_finance_and_inactive_users(): void
    {
        foreach ([
            User::factory()->finance()->create(),
            User::factory()->support()->inactive()->create(),
        ] as $user) {
            $this->actingAs($user);

            $this->assertFalse(WhatsappChannelHealthWidget::canView());
            $this->assertFalse(RecentWhatsappFailuresWidget::canView());
            $this->assertFalse(TicketDeliveryAttentionHealthWidget::canView());
            $this->assertFalse(TicketsNeedingAttentionWidget::canView());
            $this->assertFalse(RaffleWinnerNotificationHealthWidget::canView());
            $this->assertFalse(RecentWinnerNotificationsWidget::canView());
            $this->assertFalse(RafflesNeedingAttentionWidget::canView());
        }
    }
}
