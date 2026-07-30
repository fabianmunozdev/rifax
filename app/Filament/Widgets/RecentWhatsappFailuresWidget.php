<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasOperationalDashboardAccess;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Support\OperationsUi;
use App\Models\WhatsappMessage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentWhatsappFailuresWidget extends TableWidget
{
    use HasOperationalDashboardAccess;

    protected static ?int $sort = -19;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return __('admin.widgets.recent_whatsapp_failures.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFailuresQuery())
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
                    ->label(__('admin.widgets.recent_whatsapp_failures.columns.customer'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('message_type')
                    ->label(__('admin.widgets.recent_whatsapp_failures.columns.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'document' => __('admin.operations.message_types.document'),
                        'template' => __('admin.operations.message_types.template'),
                        'text' => __('admin.operations.message_types.text'),
                        'image' => __('admin.operations.message_types.image'),
                        default => __('admin.operations.message_types.unknown'),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'document' => 'success',
                        'template' => 'warning',
                        'text' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('tracked_ticket_id')
                    ->label(__('admin.widgets.recent_whatsapp_failures.columns.ticket'))
                    ->placeholder('-')
                    ->url(
                        fn (WhatsappMessage $record): ?string => filled($record->tracked_ticket_id)
                            ? TicketResource::getUrl('view', ['record' => $record->tracked_ticket_id])
                            : null,
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('meta_error_summary')
                    ->label(__('admin.widgets.recent_whatsapp_failures.columns.meta_error'))
                    ->placeholder('-')
                    ->limit(60)
                    ->tooltip(fn (WhatsappMessage $record): ?string => $record->meta_error_summary),
                TextColumn::make('provider_status')
                    ->label(__('admin.widgets.recent_whatsapp_failures.columns.provider_status'))
                    ->badge()
                    ->state(fn (WhatsappMessage $record): string => $record->provider_status ?: 'failed')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappProviderStatusLabel($state))
                    ->color(fn (string $state): string => OperationsUi::whatsappProviderStatusColor($state)),
                TextColumn::make('provider_status_at')
                    ->label(__('admin.widgets.recent_whatsapp_failures.columns.failed_at'))
                    ->dateTime()
                    ->sortable(),
            ]);
    }

    protected function getFailuresQuery(): Builder
    {
        return WhatsappMessage::query()
            ->select('whatsapp_messages.*')
            ->addSelect([
                'tracked_ticket_id' => WhatsappMessage::query()
                    ->from('whatsapp_messages as tracking_source')
                    ->selectRaw("(tracking_source.payload_json->>'ticket_id')::bigint")
                    ->whereColumn('tracking_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'meta_error_summary' => WhatsappMessage::query()
                    ->from('whatsapp_messages as error_source')
                    ->selectRaw("
                        case
                            when jsonb_typeof(error_source.payload_json->'provider_status_event'->'errors') = 'array'
                                then coalesce(
                                    error_source.payload_json->'provider_status_event'->'errors'->0->>'title',
                                    error_source.payload_json->'provider_status_event'->'errors'->0->>'message',
                                    error_source.payload_json->'provider_status_event'->'errors'->0->>'code'
                                )
                            when jsonb_typeof(error_source.payload_json->'meta_error') = 'object'
                                then coalesce(error_source.payload_json->'meta_error'->>'message', error_source.payload_json->'meta_error'->>'status')
                            else error_source.payload_json->>'meta_error'
                        end
                    ")
                    ->whereColumn('error_source.id', 'whatsapp_messages.id')
                    ->limit(1),
            ])
            ->with('customer')
            ->where('direction', 'outbound')
            ->where(function (Builder $query): Builder {
                return $query
                    ->where('provider_status', 'failed')
                    ->orWhere('status', 'failed');
            })
            ->latest('provider_status_at')
            ->latest('provider_created_at')
            ->latest('id');
    }
}
