<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasOperationalDashboardAccess;
use App\Filament\Support\OperationsUi;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Support\ResourceTableLink;
use App\Models\Ticket;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TicketDeliveryAttentionHealthWidget extends StatsOverviewWidget
{
    use HasOperationalDashboardAccess;

    protected static ?int $sort = -16;
    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('admin.widgets.ticket_delivery_attention.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.widgets.ticket_delivery_attention.description');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $baseQuery = $this->monitoredTicketsQuery();

        $withoutDelivery = (clone $baseQuery)
            ->whereNull('last_whatsapp_message_id')
            ->count();

        $awaitingDelivery = (clone $baseQuery)
            ->whereNotNull('last_whatsapp_message_id')
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereIn('last_whatsapp_message_status', ['queued', 'generated'])
                    ->orWhere(function (Builder $query): Builder {
                        return $query
                            ->where('last_whatsapp_message_status', 'sent')
                            ->where(function (Builder $query): Builder {
                                return $query
                                    ->whereNull('last_whatsapp_provider_status')
                                    ->orWhere('last_whatsapp_provider_status', 'sent');
                            });
                    });
            })
            ->count();

        $deliveredNotRead = (clone $baseQuery)
            ->where('last_whatsapp_provider_status', 'delivered')
            ->count();

        $failed = (clone $baseQuery)
            ->where(function (Builder $query): Builder {
                return $query
                    ->where('last_whatsapp_message_status', 'failed')
                    ->orWhere('last_whatsapp_provider_status', 'failed');
            })
            ->count();

        [$healthLabel, $healthDescription, $healthColor, $healthIcon] = $this->resolveHealth(
            withoutDelivery: $withoutDelivery,
            awaitingDelivery: $awaitingDelivery,
            deliveredNotRead: $deliveredNotRead,
            failed: $failed,
        );

        return [
            Stat::make(__('admin.widgets.ticket_delivery_attention.stats.status'), $healthLabel)
                ->description($healthDescription)
                ->descriptionIcon($healthIcon)
                ->color($healthColor)
                ->chartColor($healthColor)
                ->url(ResourceTableLink::tickets()),
            Stat::make(__('admin.widgets.ticket_delivery_attention.stats.no_delivery_attempt'), number_format($withoutDelivery))
                ->description(__('admin.widgets.ticket_delivery_attention.details.no_attempts'))
                ->descriptionIcon(Heroicon::OutlinedNoSymbol)
                ->color($withoutDelivery > 0 ? 'warning' : 'success')
                ->url(ResourceTableLink::tickets([
                    'without_delivery' => ResourceTableLink::toggle(),
                ])),
            Stat::make(__('admin.widgets.ticket_delivery_attention.stats.pending_provider_delivery'), number_format($awaitingDelivery))
                ->description(__('admin.widgets.ticket_delivery_attention.details.pending_provider'))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($awaitingDelivery > 0 ? 'info' : 'success')
                ->url(ResourceTableLink::tickets([
                    'awaiting_delivery' => ResourceTableLink::toggle(),
                ])),
            Stat::make(__('admin.widgets.ticket_delivery_attention.stats.delivered_awaiting_read'), number_format($deliveredNotRead))
                ->description(__('admin.widgets.ticket_delivery_attention.details.awaiting_read'))
                ->descriptionIcon(Heroicon::OutlinedEyeSlash)
                ->color($deliveredNotRead > 0 ? OperationsUi::ticketAttentionReasonColor('delivered_not_read') : 'success')
                ->url(ResourceTableLink::tickets([
                    'delivered_not_read' => ResourceTableLink::toggle(),
                ])),
            Stat::make(__('admin.widgets.ticket_delivery_attention.stats.failed'), number_format($failed))
                ->description($failed > 0
                    ? __('admin.widgets.ticket_delivery_attention.details.needs_follow_up')
                    : __('admin.widgets.ticket_delivery_attention.details.no_failed_deliveries'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($failed > 0 ? 'danger' : 'success')
                ->url(ResourceTableLink::tickets([
                    'delivery_failed' => ResourceTableLink::toggle(),
                ])),
        ];
    }

    protected function monitoredTicketsQuery(): Builder
    {
        return Ticket::query()
            ->fromSub(TicketResource::getEloquentQuery()->getQuery(), 'tickets')
            ->select('tickets.*');
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function resolveHealth(int $withoutDelivery, int $awaitingDelivery, int $deliveredNotRead, int $failed): array
    {
        if ($failed > 0) {
            return [
                __('admin.widgets.ticket_delivery_attention.details.degraded'),
                __('admin.widgets.ticket_delivery_attention.details.degraded_description'),
                'danger',
                Heroicon::OutlinedExclamationTriangle,
            ];
        }

        if ($withoutDelivery > 0 || $awaitingDelivery > 0 || $deliveredNotRead > 0) {
            return [
                __('admin.widgets.ticket_delivery_attention.details.warning'),
                __('admin.widgets.ticket_delivery_attention.details.warning_description'),
                'warning',
                Heroicon::OutlinedSignal,
            ];
        }

        if (Ticket::query()->count() === 0) {
            return [
                __('admin.widgets.ticket_delivery_attention.details.idle'),
                __('admin.widgets.ticket_delivery_attention.details.idle_description'),
                'gray',
                Heroicon::OutlinedPauseCircle,
            ];
        }

        return [
            __('admin.widgets.ticket_delivery_attention.details.healthy'),
            __('admin.widgets.ticket_delivery_attention.details.healthy_description'),
            'success',
            Heroicon::OutlinedCheckBadge,
        ];
    }
}
