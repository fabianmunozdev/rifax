<?php

namespace App\Filament\Widgets;

use App\Filament\Support\ResourceTableLink;
use App\Filament\Widgets\Concerns\HasFinancialDashboardAccess;
use App\Models\Payment;
use App\Models\Purchase;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceReviewHealthWidget extends StatsOverviewWidget
{
    use HasFinancialDashboardAccess;

    protected static ?int $sort = -13;
    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('admin.widgets.finance_review_health.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.widgets.finance_review_health.description');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $windowStart = now()->subDays(30);
        $expiringSoonThreshold = now()->addMinutes(30);

        $pendingReview = Payment::query()
            ->where('status', 'pending_review')
            ->count();

        $stalePendingReview = Payment::query()
            ->where('status', 'pending_review')
            ->whereNotNull('review_due_at')
            ->where('review_due_at', '<=', now())
            ->count();

        $approvedRevenue = (float) Payment::query()
            ->where('status', 'approved')
            ->where('reviewed_at', '>=', $windowStart)
            ->selectRaw('coalesce(sum(coalesce(received_amount, expected_amount)), 0) as aggregate')
            ->value('aggregate');

        $approvedPurchases = Purchase::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $windowStart)
            ->count();

        $expiringReservations = Purchase::query()
            ->where('status', 'reserved')
            ->whereNotNull('reserved_until')
            ->whereBetween('reserved_until', [now(), $expiringSoonThreshold])
            ->count();

        return [
            Stat::make(__('admin.widgets.finance_review_health.stats.pending_payment_review'), number_format($pendingReview))
                ->description($stalePendingReview > 0
                    ? __('admin.widgets.finance_review_health.details.stale_pending_over_2h', ['count' => $stalePendingReview])
                    : __('admin.widgets.finance_review_health.details.pending_within_window'))
                ->descriptionIcon($stalePendingReview > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedClock)
                ->color($stalePendingReview > 0 ? 'danger' : ($pendingReview > 0 ? 'warning' : 'success'))
                ->chartColor($stalePendingReview > 0 ? 'danger' : ($pendingReview > 0 ? 'warning' : 'success'))
                ->url(ResourceTableLink::payments([
                    'status' => ResourceTableLink::value('pending_review'),
                ])),
            Stat::make(__('admin.widgets.finance_review_health.stats.approved_revenue_30d'), number_format($approvedRevenue, 0))
                ->description(__('admin.widgets.finance_review_health.details.approved_value_last_30d'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($approvedRevenue > 0 ? 'success' : 'gray')
                ->url(ResourceTableLink::payments([
                    'status' => ResourceTableLink::value('approved'),
                ])),
            Stat::make(__('admin.widgets.finance_review_health.stats.paid_purchases_30d'), number_format($approvedPurchases))
                ->description(__('admin.widgets.finance_review_health.details.paid_purchases_last_30d'))
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color($approvedPurchases > 0 ? 'info' : 'gray')
                ->url(ResourceTableLink::purchases([
                    'status' => ResourceTableLink::value('paid'),
                ])),
            Stat::make(__('admin.widgets.finance_review_health.stats.expiring_reservations'), number_format($expiringReservations))
                ->description($expiringReservations > 0
                    ? __('admin.widgets.finance_review_health.details.reserved_closing_30m')
                    : __('admin.widgets.finance_review_health.details.no_reservations_expiring'))
                ->descriptionIcon(Heroicon::OutlinedExclamationCircle)
                ->color($expiringReservations > 0 ? 'warning' : 'success')
                ->url(ResourceTableLink::purchases([
                    'status' => ResourceTableLink::value('reserved'),
                ])),
        ];
    }
}
