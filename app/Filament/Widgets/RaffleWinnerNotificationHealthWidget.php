<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasOperationalDashboardAccess;
use App\Filament\Support\ResourceTableLink;
use App\Models\WhatsappMessage;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RaffleWinnerNotificationHealthWidget extends StatsOverviewWidget
{
    use HasOperationalDashboardAccess;

    protected static ?int $sort = -18;
    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('admin.widgets.winner_notification_health.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.widgets.winner_notification_health.description');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $windowStart = now()->subDay();

        $queuedLast24h = $this->winnerNotificationsSince($windowStart)
            ->whereIn('status', ['queued', 'generated'])
            ->count();
        $deliveredLast24h = $this->winnerNotificationsByProviderStatusSince('delivered', $windowStart);
        $readLast24h = $this->winnerNotificationsByProviderStatusSince('read', $windowStart);
        $failedLast24h = $this->winnerNotificationsSince($windowStart)
            ->where(function (Builder $query): Builder {
                return $query
                    ->where('status', 'failed')
                    ->orWhere('provider_status', 'failed');
            })
            ->count();

        [$healthLabel, $healthDescription, $healthColor, $healthIcon] = $this->resolveHealth(
            queuedLast24h: $queuedLast24h,
            deliveredLast24h: $deliveredLast24h,
            readLast24h: $readLast24h,
            failedLast24h: $failedLast24h,
        );

        $totalLast24h = $this->winnerNotificationsSince($windowStart)->count();

        return [
            Stat::make(__('admin.widgets.winner_notification_health.stats.status'), $healthLabel)
                ->description($healthDescription)
                ->descriptionIcon($healthIcon)
                ->color($healthColor)
                ->chartColor($healthColor)
                ->url(ResourceTableLink::whatsappMessages([
                    'winner_notifications' => ResourceTableLink::toggle(),
                ])),
            Stat::make(__('admin.widgets.winner_notification_health.stats.queued_24h'), number_format($queuedLast24h))
                ->description(__('admin.widgets.winner_notification_health.details.queued_waiting'))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($queuedLast24h > 0 ? 'warning' : 'gray')
                ->url(ResourceTableLink::whatsappMessages([
                    'winner_notifications' => ResourceTableLink::toggle(),
                    'pending_outbound' => ResourceTableLink::toggle(),
                ]))
                ->chart($this->statusChart('queued')),
            Stat::make(__('admin.widgets.winner_notification_health.stats.delivered_24h'), number_format($deliveredLast24h))
                ->description(__('admin.widgets.winner_notification_health.details.delivered_meta'))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->url(ResourceTableLink::whatsappMessages([
                    'winner_notifications' => ResourceTableLink::toggle(),
                    'only_delivered' => ResourceTableLink::toggle(),
                ]))
                ->chart($this->providerStatusChart('delivered')),
            Stat::make(__('admin.widgets.winner_notification_health.stats.read_24h'), number_format($readLast24h))
                ->description($totalLast24h > 0
                    ? __('admin.widgets.winner_notification_health.details.read_confirmations')
                    : __('admin.widgets.winner_notification_health.details.no_notifications_last_24h'))
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->color($readLast24h > 0 ? 'warning' : 'gray')
                ->url(ResourceTableLink::whatsappMessages([
                    'winner_notifications' => ResourceTableLink::toggle(),
                    'only_read' => ResourceTableLink::toggle(),
                ]))
                ->chart($this->providerStatusChart('read')),
            Stat::make(__('admin.widgets.winner_notification_health.stats.failed_24h'), number_format($failedLast24h))
                ->description($failedLast24h > 0
                    ? __('admin.widgets.winner_notification_health.details.support_action_needed')
                    : __('admin.widgets.winner_notification_health.details.no_failures'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($failedLast24h > 0 ? 'danger' : 'success')
                ->url(ResourceTableLink::whatsappMessages([
                    'winner_notifications' => ResourceTableLink::toggle(),
                    'only_failed_outbound' => ResourceTableLink::toggle(),
                ]))
                ->chart($this->providerStatusChart('failed')),
        ];
    }

    protected function winnerNotificationsSince(Carbon $windowStart): Builder
    {
        return WhatsappMessage::query()
            ->where('direction', 'outbound')
            ->whereRaw("whatsapp_messages.payload_json->>'intent' = 'raffle_winner_notification'")
            ->where('provider_created_at', '>=', $windowStart);
    }

    protected function winnerNotificationsByProviderStatusSince(string $providerStatus, Carbon $windowStart): int
    {
        return WhatsappMessage::query()
            ->where('direction', 'outbound')
            ->whereRaw("whatsapp_messages.payload_json->>'intent' = 'raffle_winner_notification'")
            ->where('provider_status', $providerStatus)
            ->where('provider_status_at', '>=', $windowStart)
            ->count();
    }

    /**
     * @return array<float>
     */
    protected function statusChart(string $status): array
    {
        $start = now()->startOfHour()->subHours(5);

        return collect(range(0, 5))
            ->map(function (int $offset) use ($start, $status): float {
                $slotStart = $start->copy()->addHours($offset);
                $slotEnd = $slotStart->copy()->addHour();

                return (float) WhatsappMessage::query()
                    ->where('direction', 'outbound')
                    ->whereRaw("whatsapp_messages.payload_json->>'intent' = 'raffle_winner_notification'")
                    ->where('status', $status)
                    ->where('provider_created_at', '>=', $slotStart)
                    ->where('provider_created_at', '<', $slotEnd)
                    ->count();
            })
            ->all();
    }

    /**
     * @return array<float>
     */
    protected function providerStatusChart(string $providerStatus): array
    {
        $start = now()->startOfHour()->subHours(5);

        return collect(range(0, 5))
            ->map(function (int $offset) use ($start, $providerStatus): float {
                $slotStart = $start->copy()->addHours($offset);
                $slotEnd = $slotStart->copy()->addHour();

                return (float) WhatsappMessage::query()
                    ->where('direction', 'outbound')
                    ->whereRaw("whatsapp_messages.payload_json->>'intent' = 'raffle_winner_notification'")
                    ->where('provider_status', $providerStatus)
                    ->where('provider_status_at', '>=', $slotStart)
                    ->where('provider_status_at', '<', $slotEnd)
                    ->count();
            })
            ->all();
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function resolveHealth(int $queuedLast24h, int $deliveredLast24h, int $readLast24h, int $failedLast24h): array
    {
        if ($failedLast24h > 0) {
            return [
                __('admin.widgets.winner_notification_health.details.degraded'),
                __('admin.widgets.winner_notification_health.details.degraded_description'),
                'danger',
                Heroicon::OutlinedExclamationTriangle,
            ];
        }

        if ($queuedLast24h > 0 || ($deliveredLast24h > $readLast24h)) {
            return [
                __('admin.widgets.winner_notification_health.details.warning'),
                __('admin.widgets.winner_notification_health.details.warning_description'),
                'warning',
                Heroicon::OutlinedSignal,
            ];
        }

        if ($readLast24h > 0) {
            return [
                __('admin.widgets.winner_notification_health.details.healthy'),
                __('admin.widgets.winner_notification_health.details.healthy_description'),
                'success',
                Heroicon::OutlinedCheckBadge,
            ];
        }

        return [
            __('admin.widgets.winner_notification_health.details.idle'),
            __('admin.widgets.winner_notification_health.details.idle_description'),
            'gray',
            Heroicon::OutlinedPauseCircle,
        ];
    }
}
