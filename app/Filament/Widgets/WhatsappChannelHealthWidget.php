<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasOperationalDashboardAccess;
use App\Filament\Support\ResourceTableLink;
use App\Models\WhatsappMessage;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class WhatsappChannelHealthWidget extends StatsOverviewWidget
{
    use HasOperationalDashboardAccess;

    protected static ?int $sort = -20;
    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('admin.widgets.whatsapp_channel_health.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.widgets.whatsapp_channel_health.description');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $sendEnabled = (bool) config('services.whatsapp.send_enabled');
        $windowStart = now()->subDay();
        $queuedThreshold = now()->subMinutes(10);

        $providerSentLast24h = $this->countOutboundByProviderStatusSince('sent', $windowStart);
        $deliveredLast24h = $this->countOutboundByProviderStatusSince('delivered', $windowStart);
        $readLast24h = $this->countOutboundByProviderStatusSince('read', $windowStart);
        $failedLast24h = $this->countOutboundByProviderStatusSince('failed', $windowStart);
        $queuedNow = WhatsappMessage::query()
            ->where('direction', 'outbound')
            ->where('status', 'queued')
            ->count();
        $staleQueued = WhatsappMessage::query()
            ->where('direction', 'outbound')
            ->where('status', 'queued')
            ->where('created_at', '<=', $queuedThreshold)
            ->count();

        [$healthLabel, $healthDescription, $healthColor, $healthIcon] = $this->resolveHealth(
            sendEnabled: $sendEnabled,
            failedLast24h: $failedLast24h,
            queuedNow: $queuedNow,
            staleQueued: $staleQueued,
        );

        return [
            Stat::make(__('admin.widgets.whatsapp_channel_health.stats.status'), $healthLabel)
                ->description($healthDescription)
                ->descriptionIcon($healthIcon)
                ->color($healthColor)
                ->chartColor($healthColor)
                ->url(ResourceTableLink::whatsappMessages()),
            Stat::make(__('admin.widgets.whatsapp_channel_health.stats.meta_sent_24h'), number_format($providerSentLast24h))
                ->description(__('admin.widgets.whatsapp_channel_health.details.accepted_by_meta'))
                ->descriptionIcon(Heroicon::OutlinedPaperAirplane)
                ->color('info')
                ->chart($this->providerStatusChart('sent'))
                ->url(ResourceTableLink::whatsappMessages([
                    'provider_status' => ResourceTableLink::value('sent'),
                ])),
            Stat::make(__('admin.widgets.whatsapp_channel_health.stats.delivered_24h'), number_format($deliveredLast24h))
                ->description(__('admin.widgets.whatsapp_channel_health.details.delivered_to_device'))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->chart($this->providerStatusChart('delivered'))
                ->url(ResourceTableLink::whatsappMessages([
                    'provider_status' => ResourceTableLink::value('delivered'),
                ])),
            Stat::make(__('admin.widgets.whatsapp_channel_health.stats.read_24h'), number_format($readLast24h))
                ->description(__('admin.widgets.whatsapp_channel_health.details.read_confirmations'))
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->color('warning')
                ->chart($this->providerStatusChart('read'))
                ->url(ResourceTableLink::whatsappMessages([
                    'provider_status' => ResourceTableLink::value('read'),
                ])),
            Stat::make(__('admin.widgets.whatsapp_channel_health.stats.failed_24h'), number_format($failedLast24h))
                ->description($failedLast24h > 0
                    ? __('admin.widgets.whatsapp_channel_health.details.provider_failures_need_review')
                    : __('admin.widgets.whatsapp_channel_health.details.no_provider_failures'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($failedLast24h > 0 ? 'danger' : 'success')
                ->chart($this->providerStatusChart('failed'))
                ->url(ResourceTableLink::whatsappMessages([
                    'only_failed_outbound' => ResourceTableLink::toggle(),
                ])),
        ];
    }

    protected function countOutboundByProviderStatusSince(string $providerStatus, Carbon $windowStart): int
    {
        return WhatsappMessage::query()
            ->where('direction', 'outbound')
            ->where('provider_status', $providerStatus)
            ->where('provider_status_at', '>=', $windowStart)
            ->count();
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
    protected function resolveHealth(bool $sendEnabled, int $failedLast24h, int $queuedNow, int $staleQueued): array
    {
        if (! $sendEnabled) {
            return [
                __('admin.widgets.whatsapp_channel_health.details.disabled'),
                __('admin.widgets.whatsapp_channel_health.details.disabled_description'),
                'gray',
                Heroicon::OutlinedPauseCircle,
            ];
        }

        if ($staleQueued > 0 || $failedLast24h >= 5) {
            return [
                __('admin.widgets.whatsapp_channel_health.details.degraded'),
                __('admin.widgets.whatsapp_channel_health.details.degraded_description'),
                'danger',
                Heroicon::OutlinedExclamationTriangle,
            ];
        }

        if ($queuedNow > 0 || $failedLast24h > 0) {
            return [
                __('admin.widgets.whatsapp_channel_health.details.warning'),
                __('admin.widgets.whatsapp_channel_health.details.warning_description'),
                'warning',
                Heroicon::OutlinedSignal,
            ];
        }

        return [
            __('admin.widgets.whatsapp_channel_health.details.healthy'),
            __('admin.widgets.whatsapp_channel_health.details.healthy_description'),
            'success',
            Heroicon::OutlinedCheckBadge,
        ];
    }
}
