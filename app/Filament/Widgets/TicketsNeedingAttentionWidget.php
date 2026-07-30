<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasOperationalDashboardAccess;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Support\OperationsUi;
use App\Models\Ticket;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TicketsNeedingAttentionWidget extends TableWidget
{
    use HasOperationalDashboardAccess;

    protected static ?int $sort = -15;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return __('admin.widgets.tickets_needing_attention.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAttentionQuery())
            ->defaultSort('last_whatsapp_message_at', 'asc')
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.ticket'))
                    ->searchable()
                    ->url(
                        fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record]),
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('purchase.customer.phone')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.customer'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('purchase.raffle.title')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.raffle'))
                    ->placeholder('-')
                    ->url(
                        fn (Ticket $record): ?string => $record->purchase?->raffle !== null
                            ? RaffleResource::getUrl('view', ['record' => $record->purchase->raffle])
                            : null,
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('attention_reason')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.reason'))
                    ->badge()
                    ->state(fn (Ticket $record): string => static::attentionReason($record))
                    ->formatStateUsing(fn (string $state): string => OperationsUi::ticketAttentionReasonLabel($state))
                    ->color(fn (string $state): string => static::attentionReasonColor($state)),
                TextColumn::make('last_whatsapp_intent')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.intent'))
                    ->badge()
                    ->state(fn (Ticket $record): string => $record->last_whatsapp_intent ?: 'ticket_delivery')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappIntentLabel($state))
                    ->color(fn (string $state): string => OperationsUi::whatsappIntentColor($state)),
                TextColumn::make('last_whatsapp_provider_status')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.provider'))
                    ->badge()
                    ->state(fn (Ticket $record): string => $record->last_whatsapp_provider_status ?: 'unknown')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappProviderStatusLabel($state))
                    ->color(fn (string $state): string => OperationsUi::whatsappProviderStatusColor($state)),
                TextColumn::make('last_whatsapp_message_at')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.last_message_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('last_whatsapp_message_id')
                    ->label(__('admin.widgets.tickets_needing_attention.columns.last_message'))
                    ->placeholder('-')
                    ->url(
                        fn (Ticket $record): ?string => filled($record->last_whatsapp_message_id)
                            ? WhatsappMessageResource::getUrl('view', ['record' => $record->last_whatsapp_message_id])
                            : null,
                        shouldOpenInNewTab: true,
                    )
                    ->toggleable(),
            ]);
    }

    protected function getAttentionQuery(): Builder
    {
        return Ticket::query()
            ->fromSub(TicketResource::getEloquentQuery()->getQuery(), 'tickets')
            ->select('tickets.*')
            ->with([
                'purchase.customer',
                'purchase.raffle',
            ])
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereNull('last_whatsapp_message_id')
                    ->orWhere('last_whatsapp_message_status', 'failed')
                    ->orWhere('last_whatsapp_provider_status', 'failed')
                    ->orWhere(function (Builder $query): Builder {
                        return $query
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
                                                    ->orWhereIn('last_whatsapp_provider_status', ['sent', 'delivered']);
                                            });
                                    });
                            });
                    });
            })
            ->orderByRaw("
                case
                    when last_whatsapp_message_status = 'failed' or last_whatsapp_provider_status = 'failed' then 0
                    when last_whatsapp_message_id is null then 1
                    when last_whatsapp_provider_status = 'delivered' then 2
                    else 3
                end
            ")
            ->orderBy('last_whatsapp_message_at');
    }

    protected static function attentionReason(Ticket $record): string
    {
        if ($record->last_whatsapp_message_status === 'failed' || $record->last_whatsapp_provider_status === 'failed') {
            return 'failed';
        }

        if ($record->last_whatsapp_message_id === null) {
            return 'without_delivery';
        }

        if ($record->last_whatsapp_provider_status === 'delivered') {
            return 'delivered_not_read';
        }

        return 'awaiting_delivery';
    }

    protected static function attentionReasonColor(string $state): string
    {
        return OperationsUi::ticketAttentionReasonColor($state);
    }
}
