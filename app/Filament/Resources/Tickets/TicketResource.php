<?php

namespace App\Filament\Resources\Tickets;

use App\Actions\Tickets\ResendTicketWhatsappAction;
use App\Actions\Tickets\RegenerateTicketAssetsAction;
use App\Actions\Tickets\RetryFailedTicketWhatsappDeliveryAction;
use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Support\PanelAccess;
use App\Filament\Support\OperationsUi;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\Raffle;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class TicketResource extends BaseResource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $translationKey = 'tickets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.tickets.sections.ticket'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label(__('admin.resources.tickets.fields.code')),
                        TextEntry::make('version'),
                        TextEntry::make('purchase.status')
                            ->badge()
                            ->label(__('admin.resources.tickets.fields.purchase_status'))
                            ->formatStateUsing(fn (?string $state): string => OperationsUi::purchaseStatusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),
                        TextEntry::make('generated_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('next_delivery_mode')
                            ->label(__('admin.resources.tickets.fields.next_whatsapp_delivery'))
                            ->badge()
                            ->state(fn (Ticket $record): string => static::nextDeliveryMode($record))
                            ->formatStateUsing(fn (string $state): string => static::deliveryModeLabel($state))
                            ->color(fn (string $state): string => static::deliveryModeColor($state)),
                        TextEntry::make('public_url')
                            ->label(__('admin.resources.tickets.fields.public_url'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('image_path')
                            ->label(__('admin.resources.tickets.fields.asset_path'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('asset_url')
                            ->label(__('admin.resources.tickets.fields.asset_url'))
                            ->state(fn (Ticket $record): ?string => static::assetUrl($record))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('last_whatsapp_message_type')
                            ->label(__('admin.resources.tickets.fields.last_whatsapp_type'))
                            ->badge()
                            ->state(fn (Ticket $record): string => $record->last_whatsapp_message_type ?: 'not_sent')
                            ->formatStateUsing(fn (string $state): string => static::messageTypeLabel($state))
                            ->color(fn (string $state): string => static::messageTypeColor($state)),
                        TextEntry::make('last_whatsapp_intent')
                            ->label(__('admin.resources.tickets.fields.last_whatsapp_intent'))
                            ->badge()
                            ->state(fn (Ticket $record): string => $record->last_whatsapp_intent ?: 'ticket_delivery')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappIntentLabel($state))
                            ->color(fn (string $state): string => static::intentColor($state)),
                        TextEntry::make('last_whatsapp_message_status')
                            ->label(__('admin.resources.tickets.fields.last_whatsapp_status'))
                            ->badge()
                            ->state(fn (Ticket $record): string => $record->last_whatsapp_message_status ?: 'not_sent')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappMessageStatusLabel($state))
                            ->color(fn (string $state): string => static::messageStatusColor($state)),
                        TextEntry::make('last_whatsapp_provider_status')
                            ->label(__('admin.resources.tickets.fields.last_provider_status'))
                            ->badge()
                            ->state(fn (Ticket $record): string => $record->last_whatsapp_provider_status ?: 'unknown')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappProviderStatusLabel($state))
                            ->color(fn (string $state): string => static::providerStatusColor($state)),
                        TextEntry::make('last_whatsapp_message_at')
                            ->label(__('admin.resources.tickets.fields.last_whatsapp_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('last_whatsapp_provider_status_at')
                            ->label(__('admin.resources.tickets.fields.last_provider_status_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('last_whatsapp_provider_conversation_id')
                            ->label(__('admin.resources.tickets.fields.last_provider_conversation'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('last_whatsapp_provider_pricing_category')
                            ->label(__('admin.resources.tickets.fields.last_pricing_category'))
                            ->badge()
                            ->state(fn (Ticket $record): string => $record->last_whatsapp_provider_pricing_category ?: 'unknown')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappPricingCategoryLabel($state))
                            ->color(fn (string $state): string => static::pricingCategoryColor($state)),
                        TextEntry::make('last_whatsapp_winning_number')
                            ->label(__('admin.resources.tickets.fields.last_winning_number'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('last_whatsapp_error_code')
                            ->label(__('admin.resources.tickets.fields.last_provider_error_code'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('last_whatsapp_error_summary')
                            ->label(__('admin.resources.tickets.fields.last_whatsapp_error'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('whatsapp_retry_count')
                            ->label(__('admin.resources.tickets.fields.retry_attempts'))
                            ->badge()
                            ->state(fn (Ticket $record): string => (string) ($record->whatsapp_retry_count ?? 0))
                            ->color(fn (string $state): string => ((int) $state) > 0 ? 'warning' : 'gray'),
                    ]),
                Section::make(__('admin.resources.tickets.sections.purchase'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('purchase.id')
                            ->label(__('admin.resources.tickets.fields.purchase_id')),
                        TextEntry::make('purchase.customer.phone')
                            ->label(__('admin.resources.tickets.fields.customer_phone'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('purchase.customer.name')
                            ->label(__('admin.resources.tickets.fields.customer_name'))
                            ->placeholder('-'),
                        TextEntry::make('purchase.raffle.title')
                            ->label(__('admin.resources.tickets.fields.raffle'))
                            ->placeholder('-'),
                        TextEntry::make('purchase.total_amount')
                            ->label(__('admin.resources.tickets.fields.total_amount'))
                            ->money(fn (Ticket $record): string => $record->purchase?->currency ?: 'COP'),
                        TextEntry::make('purchase_numbers')
                            ->label(__('admin.resources.tickets.fields.numbers'))
                            ->state(fn (Ticket $record): string => $record->purchase?->numbers?->pluck('number')->implode(', ') ?: '-'),
                    ]),
                Section::make(__('admin.resources.tickets.sections.audit'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('purchase.id')
                    ->label(__('admin.resources.tickets.fields.purchase'))
                    ->searchable(),
                TextColumn::make('purchase.customer.phone')
                    ->label(__('admin.resources.tickets.fields.customer_phone'))
                    ->searchable(),
                TextColumn::make('purchase.raffle.title')
                    ->label(__('admin.resources.tickets.fields.raffle'))
                    ->searchable(),
                TextColumn::make('purchase_numbers')
                    ->label(__('admin.resources.tickets.fields.numbers'))
                    ->state(fn (Ticket $record): string => $record->purchase?->numbers?->pluck('number')->implode(', ') ?: '-')
                    ->toggleable(),
                TextColumn::make('purchase.status')
                    ->label(__('admin.resources.tickets.fields.purchase_status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationsUi::purchaseStatusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state)),
                TextColumn::make('next_delivery_mode')
                    ->label(__('admin.resources.tickets.fields.next_delivery'))
                    ->badge()
                    ->state(fn (Ticket $record): string => static::nextDeliveryMode($record))
                    ->formatStateUsing(fn (string $state): string => static::deliveryModeLabel($state))
                    ->color(fn (string $state): string => static::deliveryModeColor($state)),
                IconColumn::make('image_ready')
                    ->label(__('admin.resources.tickets.fields.asset'))
                    ->boolean()
                    ->state(fn (Ticket $record): bool => filled($record->image_path)),
                TextColumn::make('public_url')
                    ->label(__('admin.resources.tickets.fields.public_ticket'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('admin.resources.tickets.notifications.open') : '-')
                    ->url(fn (Ticket $record): ?string => $record->public_url, shouldOpenInNewTab: true),
                TextColumn::make('asset_url')
                    ->label(__('admin.resources.tickets.fields.asset_file'))
                    ->state(fn (Ticket $record): ?string => static::assetUrl($record))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('admin.resources.tickets.notifications.open_asset') : '-')
                    ->url(fn (Ticket $record): ?string => static::assetUrl($record), shouldOpenInNewTab: true)
                    ->toggleable(),
                TextColumn::make('last_whatsapp_message_type')
                    ->label(__('admin.resources.tickets.fields.last_whatsapp_type'))
                    ->badge()
                    ->state(fn (Ticket $record): string => $record->last_whatsapp_message_type ?: 'not_sent')
                    ->formatStateUsing(fn (string $state): string => static::messageTypeLabel($state))
                    ->color(fn (string $state): string => static::messageTypeColor($state))
                    ->toggleable(),
                TextColumn::make('last_whatsapp_intent')
                    ->label(__('admin.resources.tickets.fields.last_whatsapp_intent'))
                    ->badge()
                    ->state(fn (Ticket $record): string => $record->last_whatsapp_intent ?: 'ticket_delivery')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappIntentLabel($state))
                    ->color(fn (string $state): string => static::intentColor($state))
                    ->toggleable(),
                TextColumn::make('last_whatsapp_message_status')
                    ->label(__('admin.resources.tickets.fields.last_whatsapp_status'))
                    ->badge()
                    ->state(fn (Ticket $record): string => $record->last_whatsapp_message_status ?: 'not_sent')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappMessageStatusLabel($state))
                    ->color(fn (string $state): string => static::messageStatusColor($state)),
                TextColumn::make('last_whatsapp_provider_status')
                    ->label(__('admin.resources.tickets.fields.provider_status'))
                    ->badge()
                    ->state(fn (Ticket $record): string => $record->last_whatsapp_provider_status ?: 'unknown')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappProviderStatusLabel($state))
                    ->color(fn (string $state): string => static::providerStatusColor($state))
                    ->toggleable(),
                TextColumn::make('last_whatsapp_provider_pricing_category')
                    ->label(__('admin.resources.tickets.fields.pricing'))
                    ->badge()
                    ->state(fn (Ticket $record): string => $record->last_whatsapp_provider_pricing_category ?: 'unknown')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappPricingCategoryLabel($state))
                    ->color(fn (string $state): string => static::pricingCategoryColor($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_whatsapp_message_at')
                    ->label(__('admin.resources.tickets.fields.last_whatsapp_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_whatsapp_provider_status_at')
                    ->label(__('admin.resources.tickets.fields.provider_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_whatsapp_error_summary')
                    ->label(__('admin.resources.tickets.fields.last_whatsapp_error'))
                    ->placeholder('-')
                    ->limit(40)
                    ->tooltip(fn (Ticket $record): ?string => $record->last_whatsapp_error_summary)
                    ->toggleable(),
                TextColumn::make('whatsapp_retry_count')
                    ->label(__('admin.resources.tickets.fields.retries'))
                    ->badge()
                    ->state(fn (Ticket $record): string => (string) ($record->whatsapp_retry_count ?? 0))
                    ->color(fn (string $state): string => ((int) $state) > 0 ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('generated_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('version')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('generated_at', 'desc')
            ->filters([
                SelectFilter::make('purchase_status')
                    ->label(__('admin.resources.tickets.filters.purchase_status'))
                    ->options(static::ticketPurchaseStatusOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'purchase',
                            fn (Builder $purchaseQuery): Builder => $purchaseQuery->where('status', $data['value'])
                        ),
                    ))
                    ->indicator(__('admin.resources.tickets.filters.purchase_status')),
                SelectFilter::make('next_delivery_mode')
                    ->label(__('admin.resources.tickets.filters.next_delivery'))
                    ->options(static::deliveryModeOptions())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'document' => $query->whereHas('purchase.conversationStates', fn (Builder $conversationQuery): Builder => $conversationQuery
                            ->whereNotNull('last_user_message_at')
                            ->where('last_user_message_at', '>=', now()->subHours(24))),
                        'template_or_text' => $query->where(function (Builder $query): Builder {
                            return $query
                                ->whereDoesntHave('purchase.conversationStates', fn (Builder $conversationQuery): Builder => $conversationQuery->whereNotNull('last_user_message_at'))
                                ->orWhereHas('purchase.conversationStates', fn (Builder $conversationQuery): Builder => $conversationQuery
                                    ->whereNotNull('last_user_message_at')
                                    ->where('last_user_message_at', '<', now()->subHours(24)));
                        }),
                        default => $query,
                    })
                    ->indicator(__('admin.resources.tickets.filters.next_delivery')),
                SelectFilter::make('raffle_id')
                    ->label(__('admin.resources.tickets.filters.raffle'))
                    ->options(fn (): array => Raffle::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'purchase',
                            fn (Builder $purchaseQuery): Builder => $purchaseQuery->where('raffle_id', $data['value'])
                        ),
                    ))
                    ->indicator(__('admin.resources.tickets.filters.raffle')),
                Filter::make('generated_between')
                    ->label(__('admin.resources.tickets.filters.generated_between'))
                    ->schema([
                        DatePicker::make('generated_from')
                            ->label(__('admin.resources.tickets.fields.from')),
                        DatePicker::make('generated_until')
                            ->label(__('admin.resources.tickets.fields.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['generated_from'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('generated_at', '>=', $data['generated_from'])
                            )
                            ->when(
                                filled($data['generated_until'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('generated_at', '<=', $data['generated_until'])
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['generated_from'] ?? null)) {
                            $indicators['generated_from'] = __('admin.resources.tickets.fields.from').': '.$data['generated_from'];
                        }

                        if (filled($data['generated_until'] ?? null)) {
                            $indicators['generated_until'] = __('admin.resources.tickets.fields.until').': '.$data['generated_until'];
                        }

                        return $indicators;
                    }),
                Filter::make('image_ready')
                    ->label(__('admin.resources.tickets.filters.asset_ready'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('image_path'))
                    ->indicator(__('admin.resources.tickets.filters.asset_ready')),
                Filter::make('public_url_ready')
                    ->label(__('admin.resources.tickets.filters.public_url_ready'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('public_url'))
                    ->indicator(__('admin.resources.tickets.filters.public_url_ready')),
                Filter::make('delivery_failed')
                    ->label(__('admin.resources.tickets.filters.only_failed_deliveries'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query): Builder {
                        return $query
                            ->where('latest_tracked_message.tracked_message_status', 'failed')
                            ->orWhere('latest_tracked_message.tracked_provider_status', 'failed');
                    }))
                    ->indicator(__('admin.resources.tickets.filters.only_failed_deliveries')),
                Filter::make('provider_delivered')
                    ->label(__('admin.resources.tickets.filters.only_delivered'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('latest_tracked_message.tracked_provider_status', 'delivered'))
                    ->indicator(__('admin.resources.tickets.filters.only_delivered')),
                Filter::make('provider_read')
                    ->label(__('admin.resources.tickets.filters.only_read'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('latest_tracked_message.tracked_provider_status', 'read'))
                    ->indicator(__('admin.resources.tickets.filters.only_read')),
                Filter::make('awaiting_delivery')
                    ->label(__('admin.resources.tickets.filters.pending_provider_delivery'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('latest_tracked_message.tracked_ticket_id')
                        ->where(function (Builder $query): Builder {
                            return $query
                                ->whereIn('latest_tracked_message.tracked_message_status', ['queued', 'generated'])
                                ->orWhere(function (Builder $query): Builder {
                                    return $query
                                        ->where('latest_tracked_message.tracked_message_status', 'sent')
                                        ->where(function (Builder $query): Builder {
                                            return $query
                                                ->whereNull('latest_tracked_message.tracked_provider_status')
                                                ->orWhere('latest_tracked_message.tracked_provider_status', 'sent');
                                        });
                                });
                        }))
                    ->indicator(__('admin.resources.tickets.filters.pending_provider_delivery')),
                Filter::make('delivered_not_read')
                    ->label(__('admin.resources.tickets.filters.delivered_awaiting_read'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('latest_tracked_message.tracked_provider_status', 'delivered'))
                    ->indicator(__('admin.resources.tickets.filters.delivered_awaiting_read')),
                Filter::make('winner_notification')
                    ->label(__('admin.resources.tickets.filters.winner_notifications'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('latest_tracked_message.tracked_intent', 'raffle_winner_notification'))
                    ->indicator(__('admin.resources.tickets.filters.winner_notifications')),
                Filter::make('without_delivery')
                    ->label(__('admin.resources.tickets.filters.no_delivery_attempt'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('latest_tracked_message.tracked_ticket_id'))
                    ->indicator(__('admin.resources.tickets.filters.no_delivery_attempt')),
            ])
            ->recordActions([
                static::makeRetryFailedDeliveryAction(),
                static::makeRegenerateAssetsAction(),
                static::makeResendWhatsappAction(),
                static::makeOpenPurchaseAction(),
                static::makeOpenRaffleAction(),
                static::makeOpenLastWhatsappAction(),
                static::makeOpenPublicTicketAction(),
                static::makeOpenAssetAction(),
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.resources.tickets.empty_state.heading'))
            ->emptyStateDescription(__('admin.resources.tickets.empty_state.description'))
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $latestTrackedMessagesQuery = WhatsappMessage::query()
            ->join('purchases as tracked_purchases', 'tracked_purchases.customer_id', '=', 'whatsapp_messages.customer_id')
            ->where('whatsapp_messages.direction', 'outbound')
            ->whereRaw("(whatsapp_messages.payload_json->>'ticket_id') is not null")
            ->selectRaw("
                distinct on (((whatsapp_messages.payload_json->>'ticket_id')::bigint))
                ((whatsapp_messages.payload_json->>'ticket_id')::bigint) as tracked_ticket_id,
                tracked_purchases.id as tracked_purchase_id,
                whatsapp_messages.id as tracked_message_id,
                coalesce(whatsapp_messages.provider_created_at, whatsapp_messages.created_at) as tracked_message_at,
                whatsapp_messages.status as tracked_message_status,
                whatsapp_messages.provider_status as tracked_provider_status,
                whatsapp_messages.provider_status_at as tracked_provider_status_at,
                whatsapp_messages.payload_json->>'intent' as tracked_intent,
                whatsapp_messages.payload_json->>'winning_number' as tracked_winning_number,
                whatsapp_messages.payload_json->'provider_status_event'->'conversation'->>'id' as tracked_provider_conversation_id,
                whatsapp_messages.payload_json->'provider_status_event'->'pricing'->>'category' as tracked_provider_pricing_category,
                coalesce(
                    whatsapp_messages.payload_json->'provider_status_event'->'errors'->0->>'code',
                    whatsapp_messages.payload_json->'meta_error'->>'status'
                ) as tracked_error_code,
                whatsapp_messages.message_type as tracked_message_type,
                case
                    when jsonb_typeof(whatsapp_messages.payload_json->'provider_status_event'->'errors') = 'array'
                        then coalesce(
                            whatsapp_messages.payload_json->'provider_status_event'->'errors'->0->>'title',
                            whatsapp_messages.payload_json->'provider_status_event'->'errors'->0->>'message',
                            whatsapp_messages.payload_json->'provider_status_event'->'errors'->0->>'code'
                        )
                    when jsonb_typeof(whatsapp_messages.payload_json->'meta_error') = 'object'
                        then coalesce(whatsapp_messages.payload_json->'meta_error'->>'message', whatsapp_messages.payload_json->'meta_error'->>'status')
                    else whatsapp_messages.payload_json->>'meta_error'
                end as tracked_error_summary
            ")
            ->orderByRaw("((whatsapp_messages.payload_json->>'ticket_id')::bigint)")
            ->orderBy('tracked_purchases.id')
            ->orderByRaw('coalesce(whatsapp_messages.provider_created_at, whatsapp_messages.created_at) desc')
            ->orderByDesc('whatsapp_messages.id');

        return parent::getEloquentQuery()
            ->select('tickets.*')
            ->leftJoinSub($latestTrackedMessagesQuery, 'latest_tracked_message', function ($join): void {
                $join
                    ->on('latest_tracked_message.tracked_ticket_id', '=', 'tickets.id')
                    ->on('latest_tracked_message.tracked_purchase_id', '=', 'tickets.purchase_id');
            })
            ->addSelect([
                'latest_tracked_message.tracked_message_at as last_whatsapp_message_at',
                'latest_tracked_message.tracked_message_id as last_whatsapp_message_id',
                'latest_tracked_message.tracked_message_status as last_whatsapp_message_status',
                'latest_tracked_message.tracked_provider_status as last_whatsapp_provider_status',
                'latest_tracked_message.tracked_provider_status_at as last_whatsapp_provider_status_at',
                'latest_tracked_message.tracked_intent as last_whatsapp_intent',
                'latest_tracked_message.tracked_winning_number as last_whatsapp_winning_number',
                'latest_tracked_message.tracked_provider_conversation_id as last_whatsapp_provider_conversation_id',
                'latest_tracked_message.tracked_provider_pricing_category as last_whatsapp_provider_pricing_category',
                'latest_tracked_message.tracked_error_code as last_whatsapp_error_code',
                'latest_tracked_message.tracked_message_type as last_whatsapp_message_type',
                'latest_tracked_message.tracked_error_summary as last_whatsapp_error_summary',
            ])
            ->selectSub(
                WhatsappMessage::query()
                    ->join('purchases as retry_purchases', 'retry_purchases.customer_id', '=', 'whatsapp_messages.customer_id')
                    ->where('whatsapp_messages.direction', 'outbound')
                    ->whereRaw("(whatsapp_messages.payload_json->>'retry_of_message_id') is not null")
                    ->whereColumn('retry_purchases.id', 'tickets.purchase_id')
                    ->whereRaw("(whatsapp_messages.payload_json->>'ticket_id')::bigint = tickets.id")
                    ->selectRaw('count(*)'),
                'whatsapp_retry_count'
            )
            ->with([
                'purchase.customer',
                'purchase.raffle',
                'purchase.numbers',
                'purchase.conversationStates',
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::TicketsView);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function makeResendWhatsappAction(): Action
    {
        return Action::make('resend_whatsapp')
            ->label(__('admin.resources.tickets.actions.resend_whatsapp'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color(Color::Amber)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::TicketsManage))
            ->disabled(fn (Ticket $record): bool => $record->purchase?->status !== 'paid')
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.tickets.modals.resend_whatsapp'))
            ->action(function (Ticket $record): void {
                try {
                    $actor = Auth::user();

                    $result = app(ResendTicketWhatsappAction::class)->execute(
                        $record,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title($result === 'document'
                            ? __('admin.resources.tickets.notifications.resent_as_document')
                            : __('admin.resources.tickets.notifications.queued_template_or_fallback'))
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function makeRetryFailedDeliveryAction(): Action
    {
        return Action::make('retry_failed_delivery')
            ->label(__('admin.resources.tickets.actions.retry_failed_delivery'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::TicketsManage))
            ->disabled(fn (Ticket $record): bool => ! in_array('failed', [
                $record->last_whatsapp_message_status,
                $record->last_whatsapp_provider_status,
            ], true))
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.tickets.modals.retry_failed_delivery'))
            ->action(function (Ticket $record): void {
                try {
                    $actor = Auth::user();

                    $newAttempt = app(RetryFailedTicketWhatsappDeliveryAction::class)->execute(
                        $record,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.tickets.notifications.retry_queued'))
                        ->body(__('admin.resources.tickets.notifications.retry_queued_body', ['id' => $newAttempt->id]))
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function makeOpenPurchaseAction(): Action
    {
        return Action::make('open_purchase')
            ->label(__('admin.resources.tickets.actions.open_purchase'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(Color::Amber)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PurchasesView))
            ->disabled(fn (Ticket $record): bool => $record->purchase === null)
            ->url(
                fn (Ticket $record): ?string => $record->purchase !== null
                    ? PurchaseResource::getUrl('view', ['record' => $record->purchase])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenRaffleAction(): Action
    {
        return Action::make('open_raffle')
            ->label(__('admin.resources.tickets.actions.open_raffle'))
            ->icon(Heroicon::OutlinedGiftTop)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::RafflesView))
            ->disabled(fn (Ticket $record): bool => $record->purchase?->raffle === null)
            ->url(
                fn (Ticket $record): ?string => $record->purchase?->raffle !== null
                    ? RaffleResource::getUrl('view', ['record' => $record->purchase->raffle])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenLastWhatsappAction(): Action
    {
        return Action::make('open_last_whatsapp')
            ->label(__('admin.resources.tickets.actions.open_whatsapp_message'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::WhatsappMessagesView))
            ->disabled(fn (Ticket $record): bool => blank($record->last_whatsapp_message_id))
            ->url(
                fn (Ticket $record): ?string => filled($record->last_whatsapp_message_id)
                    ? WhatsappMessageResource::getUrl('view', ['record' => $record->last_whatsapp_message_id])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeRegenerateAssetsAction(): Action
    {
        return Action::make('regenerate_assets')
            ->label(__('admin.resources.tickets.actions.regenerate_assets'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color(Color::Slate)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::TicketsManage))
            ->disabled(fn (Ticket $record): bool => $record->purchase?->status !== 'paid')
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.tickets.modals.regenerate_assets'))
            ->action(function (Ticket $record): void {
                try {
                    $actor = Auth::user();

                    app(RegenerateTicketAssetsAction::class)->execute(
                        $record,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.tickets.notifications.assets_regenerated'))
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function makeOpenPublicTicketAction(): Action
    {
        return Action::make('open_public_ticket')
            ->label(__('admin.resources.tickets.actions.open_public_ticket'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->disabled(fn (Ticket $record): bool => blank($record->public_url))
            ->url(fn (Ticket $record): ?string => $record->public_url, shouldOpenInNewTab: true);
    }

    public static function makeOpenAssetAction(): Action
    {
        return Action::make('open_asset')
            ->label(__('admin.resources.tickets.actions.open_asset'))
            ->icon(Heroicon::OutlinedPhoto)
            ->disabled(fn (Ticket $record): bool => blank(static::assetUrl($record)))
            ->url(fn (Ticket $record): ?string => static::assetUrl($record), shouldOpenInNewTab: true);
    }

    protected static function statusColor(?string $state): string
    {
        return OperationsUi::purchaseStatusColor($state);
    }

    protected static function deliveryModeColor(string $state): string
    {
        return match ($state) {
            'document' => 'success',
            'template_or_text' => 'warning',
            default => 'gray',
        };
    }

    protected static function nextDeliveryMode(Ticket $record): string
    {
        $lastUserMessageAt = $record->purchase?->conversationStates
            ?->sortByDesc('updated_at')
            ->first()
            ?->last_user_message_at;

        if ($lastUserMessageAt instanceof Carbon && $lastUserMessageAt->gte(now()->subHours(24))) {
            return 'document';
        }

        return 'template_or_text';
    }

    protected static function deliveryModeLabel(string $state): string
    {
        return match ($state) {
            'document' => __('admin.operations.delivery_modes.document'),
            'template_or_text' => __('admin.operations.delivery_modes.template_or_text'),
            default => __('admin.operations.delivery_modes.unknown'),
        };
    }

    protected static function assetUrl(Ticket $record): ?string
    {
        if (blank($record->image_path)) {
            return null;
        }

        return asset('storage/'.$record->image_path);
    }

    protected static function messageStatusColor(string $state): string
    {
        return OperationsUi::whatsappMessageStatusColor($state);
    }

    protected static function messageTypeColor(string $state): string
    {
        return match ($state) {
            'document' => 'success',
            'template' => 'warning',
            'text' => 'info',
            default => 'gray',
        };
    }

    protected static function messageTypeLabel(string $state): string
    {
        return match ($state) {
            'document' => __('admin.operations.message_types.document'),
            'template' => __('admin.operations.message_types.template'),
            'text' => __('admin.operations.message_types.text'),
            'image' => __('admin.operations.message_types.image'),
            'interactive' => __('admin.operations.message_types.interactive'),
            'other' => __('admin.operations.message_types.other'),
            default => __('admin.operations.message_types.unknown'),
        };
    }

    protected static function providerStatusColor(string $state): string
    {
        return OperationsUi::whatsappProviderStatusColor($state);
    }

    protected static function pricingCategoryColor(string $state): string
    {
        return OperationsUi::whatsappPricingCategoryColor($state);
    }

    protected static function intentColor(string $state): string
    {
        return OperationsUi::whatsappIntentColor($state);
    }

    /**
     * @return array<string, string>
     */
    protected static function ticketPurchaseStatusOptions(): array
    {
        return [
            'paid' => __('admin.operations.purchase_statuses.paid'),
            'payment_submitted' => __('admin.operations.purchase_statuses.payment_submitted'),
            'under_review' => __('admin.operations.purchase_statuses.under_review'),
            'cancelled' => __('admin.operations.purchase_statuses.cancelled'),
            'expired' => __('admin.operations.purchase_statuses.expired'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function deliveryModeOptions(): array
    {
        return [
            'document' => __('admin.operations.delivery_modes.document'),
            'template_or_text' => __('admin.operations.delivery_modes.template_or_text'),
        ];
    }
}
