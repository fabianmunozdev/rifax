<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasOperationalDashboardAccess;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Support\OperationsUi;
use App\Models\WhatsappMessage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentWinnerNotificationsWidget extends TableWidget
{
    use HasOperationalDashboardAccess;

    protected static ?int $sort = -17;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return __('admin.widgets.recent_winner_notifications.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getWinnerNotificationsQuery())
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->url(
                        fn (WhatsappMessage $record): string => WhatsappMessageResource::getUrl('view', ['record' => $record]),
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('customer.phone')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.customer'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('tracked_winning_number')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.winning_number'))
                    ->placeholder('-'),
                TextColumn::make('tracked_ticket_id')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.ticket'))
                    ->placeholder('-')
                    ->url(
                        fn (WhatsappMessage $record): ?string => filled($record->tracked_ticket_id)
                            ? TicketResource::getUrl('view', ['record' => $record->tracked_ticket_id])
                            : null,
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('tracked_raffle_id')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.raffle'))
                    ->placeholder('-')
                    ->url(
                        fn (WhatsappMessage $record): ?string => filled($record->tracked_raffle_id)
                            ? RaffleResource::getUrl('view', ['record' => $record->tracked_raffle_id])
                            : null,
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('message_type')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'template' => __('admin.operations.message_types.template'),
                        'text' => __('admin.operations.message_types.text'),
                        'document' => __('admin.operations.message_types.document'),
                        default => __('admin.operations.message_types.unknown'),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'template' => 'warning',
                        'text' => 'info',
                        'document' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('provider_status')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.provider_status'))
                    ->badge()
                    ->state(fn (WhatsappMessage $record): string => $record->provider_status ?: $record->status ?: 'unknown')
                    ->formatStateUsing(fn (string $state): string => in_array($state, ['queued', 'generated'], true)
                        ? OperationsUi::whatsappMessageStatusLabel($state)
                        : OperationsUi::whatsappProviderStatusLabel($state))
                    ->color(fn (string $state): string => in_array($state, ['queued', 'generated'], true)
                        ? OperationsUi::whatsappMessageStatusColor($state)
                        : OperationsUi::whatsappProviderStatusColor($state)),
                TextColumn::make('provider_created_at')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.queued_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('provider_status_at')
                    ->label(__('admin.widgets.recent_winner_notifications.columns.provider_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
            ]);
    }

    protected function getWinnerNotificationsQuery(): Builder
    {
        return WhatsappMessage::query()
            ->select('whatsapp_messages.*')
            ->addSelect([
                'tracked_ticket_id' => WhatsappMessage::query()
                    ->from('whatsapp_messages as tracking_source')
                    ->selectRaw("(tracking_source.payload_json->>'ticket_id')::bigint")
                    ->whereColumn('tracking_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'tracked_raffle_id' => WhatsappMessage::query()
                    ->from('whatsapp_messages as raffle_source')
                    ->selectRaw("(raffle_source.payload_json->>'raffle_id')::bigint")
                    ->whereColumn('raffle_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'tracked_winning_number' => WhatsappMessage::query()
                    ->from('whatsapp_messages as winning_source')
                    ->selectRaw("winning_source.payload_json->>'winning_number'")
                    ->whereColumn('winning_source.id', 'whatsapp_messages.id')
                    ->limit(1),
            ])
            ->with('customer')
            ->where('direction', 'outbound')
            ->whereRaw("whatsapp_messages.payload_json->>'intent' = 'raffle_winner_notification'")
            ->latest('provider_status_at')
            ->latest('provider_created_at')
            ->latest('id');
    }
}
