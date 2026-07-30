<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasOperationalDashboardAccess;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Support\OperationsUi;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Support\ResourceTableLink;
use App\Models\Raffle;
use App\Models\Ticket;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RafflesNeedingAttentionWidget extends TableWidget
{
    use HasOperationalDashboardAccess;

    protected static ?int $sort = -14;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return __('admin.widgets.raffles_needing_attention.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAttentionByRaffleQuery())
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->defaultSort('attention_count', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.widgets.raffles_needing_attention.columns.raffle'))
                    ->searchable()
                    ->url(
                        fn (Raffle $record): string => RaffleResource::getUrl('view', ['record' => $record]),
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'published' => 'success',
                        'closed' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('attention_count')
                    ->label(__('admin.widgets.raffles_needing_attention.columns.attention'))
                    ->badge()
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'danger' : 'success')
                    ->url(
                        fn (Raffle $record): string => ResourceTableLink::tickets([
                            'raffle_id' => ResourceTableLink::value($record->id),
                        ]),
                        shouldOpenInNewTab: true,
                    )
                    ->sortable(),
                TextColumn::make('failed_count')
                    ->label(__('admin.widgets.raffles_needing_attention.columns.failed'))
                    ->badge()
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'danger' : 'gray')
                    ->url(
                        fn (Raffle $record): string => ResourceTableLink::tickets([
                            'raffle_id' => ResourceTableLink::value($record->id),
                            'delivery_failed' => ResourceTableLink::toggle(),
                        ]),
                        shouldOpenInNewTab: true,
                    )
                    ->sortable(),
                TextColumn::make('without_delivery_count')
                    ->label(__('admin.widgets.raffles_needing_attention.columns.no_delivery_attempt'))
                    ->badge()
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'warning' : 'gray')
                    ->url(
                        fn (Raffle $record): string => ResourceTableLink::tickets([
                            'raffle_id' => ResourceTableLink::value($record->id),
                            'without_delivery' => ResourceTableLink::toggle(),
                        ]),
                        shouldOpenInNewTab: true,
                    )
                    ->sortable(),
                TextColumn::make('awaiting_delivery_count')
                    ->label(__('admin.widgets.raffles_needing_attention.columns.pending_provider_delivery'))
                    ->badge()
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'info' : 'gray')
                    ->url(
                        fn (Raffle $record): string => ResourceTableLink::tickets([
                            'raffle_id' => ResourceTableLink::value($record->id),
                            'awaiting_delivery' => ResourceTableLink::toggle(),
                        ]),
                        shouldOpenInNewTab: true,
                    )
                    ->sortable(),
                TextColumn::make('delivered_not_read_count')
                    ->label(__('admin.widgets.raffles_needing_attention.columns.delivered_awaiting_read'))
                    ->badge()
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? OperationsUi::ticketAttentionReasonColor('delivered_not_read') : 'gray')
                    ->url(
                        fn (Raffle $record): string => ResourceTableLink::tickets([
                            'raffle_id' => ResourceTableLink::value($record->id),
                            'delivered_not_read' => ResourceTableLink::toggle(),
                        ]),
                        shouldOpenInNewTab: true,
                    )
                    ->sortable(),
                TextColumn::make('tickets_count')
                    ->label(__('admin.widgets.raffles_needing_attention.columns.tickets'))
                    ->sortable(),
            ]);
    }

    protected function getAttentionByRaffleQuery(): Builder
    {
        $ticketMetrics = Ticket::query()
            ->fromSub(TicketResource::getEloquentQuery()->getQuery(), 'tickets')
            ->join('purchases', 'purchases.id', '=', 'tickets.purchase_id')
            ->selectRaw("
                purchases.raffle_id as raffle_id,
                count(*) as tickets_count,
                sum(case when tickets.last_whatsapp_message_id is null then 1 else 0 end) as without_delivery_count,
                sum(case when tickets.last_whatsapp_message_status = 'failed' or tickets.last_whatsapp_provider_status = 'failed' then 1 else 0 end) as failed_count,
                sum(case when tickets.last_whatsapp_provider_status = 'delivered' then 1 else 0 end) as delivered_not_read_count,
                sum(
                    case
                        when tickets.last_whatsapp_message_id is not null
                            and (
                                tickets.last_whatsapp_message_status in ('queued', 'generated')
                                or (
                                    tickets.last_whatsapp_message_status = 'sent'
                                    and (
                                        tickets.last_whatsapp_provider_status is null
                                        or tickets.last_whatsapp_provider_status = 'sent'
                                    )
                                )
                            )
                        then 1
                        else 0
                    end
                ) as awaiting_delivery_count
            ")
            ->groupBy('purchases.raffle_id');

        return Raffle::query()
            ->leftJoinSub($ticketMetrics->getQuery(), 'ticket_metrics', function ($join): void {
                $join->on('ticket_metrics.raffle_id', '=', 'raffles.id');
            })
            ->select('raffles.*')
            ->selectRaw('coalesce(ticket_metrics.tickets_count, 0) as tickets_count')
            ->selectRaw('coalesce(ticket_metrics.without_delivery_count, 0) as without_delivery_count')
            ->selectRaw('coalesce(ticket_metrics.failed_count, 0) as failed_count')
            ->selectRaw('coalesce(ticket_metrics.delivered_not_read_count, 0) as delivered_not_read_count')
            ->selectRaw('coalesce(ticket_metrics.awaiting_delivery_count, 0) as awaiting_delivery_count')
            ->selectRaw('
                coalesce(ticket_metrics.without_delivery_count, 0)
                + coalesce(ticket_metrics.failed_count, 0)
                + coalesce(ticket_metrics.delivered_not_read_count, 0)
                + coalesce(ticket_metrics.awaiting_delivery_count, 0)
                as attention_count
            ')
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereRaw('coalesce(ticket_metrics.without_delivery_count, 0) > 0')
                    ->orWhereRaw('coalesce(ticket_metrics.failed_count, 0) > 0')
                    ->orWhereRaw('coalesce(ticket_metrics.delivered_not_read_count, 0) > 0')
                    ->orWhereRaw('coalesce(ticket_metrics.awaiting_delivery_count, 0) > 0');
            })
            ->orderByDesc('attention_count')
            ->orderByDesc('failed_count')
            ->orderBy('title');
    }
}
