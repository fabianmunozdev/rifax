<?php

namespace App\Filament\Resources\WhatsappMessages;

use App\Actions\WhatsApp\RetryFailedWhatsappMessageAction;
use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WhatsappMessages\Pages\ListWhatsappMessages;
use App\Filament\Resources\WhatsappMessages\Pages\ViewWhatsappMessage;
use App\Filament\Support\OperationsUi;
use App\Filament\Support\PanelAccess;
use App\Models\User;
use App\Models\WhatsappMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class WhatsappMessageResource extends BaseResource
{
    protected static ?string $model = WhatsappMessage::class;

    protected static ?string $translationKey = 'whatsapp_messages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'WhatsApp Messages';

    protected static ?string $modelLabel = 'WhatsApp message';

    protected static ?string $pluralModelLabel = 'WhatsApp messages';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.whatsapp_messages.sections.message'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('direction')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::directionLabel($state)),
                        TextEntry::make('message_type')
                            ->label(__('admin.resources.whatsapp_messages.fields.type'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::messageTypeLabel($state))
                            ->color(fn (?string $state): string => static::messageTypeColor($state)),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => OperationsUi::whatsappMessageStatusLabel($state))
                            ->color(fn (?string $state): string => static::messageStatusColor($state)),
                        TextEntry::make('provider_status')
                            ->label(__('admin.resources.whatsapp_messages.fields.provider_status'))
                            ->badge()
                            ->state(fn (WhatsappMessage $record): string => $record->provider_status ?: 'unknown')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappProviderStatusLabel($state))
                            ->color(fn (string $state): string => static::providerStatusColor($state)),
                        TextEntry::make('customer.phone')
                            ->label(__('admin.resources.whatsapp_messages.fields.customer_phone'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('tracked_ticket_id')
                            ->label(__('admin.resources.whatsapp_messages.fields.ticket_id'))
                            ->placeholder('-'),
                        TextEntry::make('tracked_raffle_id')
                            ->label(__('admin.resources.whatsapp_messages.fields.raffle_id'))
                            ->placeholder('-'),
                        TextEntry::make('tracked_intent')
                            ->label(__('admin.resources.whatsapp_messages.fields.intent'))
                            ->badge()
                            ->state(fn (WhatsappMessage $record): string => $record->tracked_intent ?: 'generic')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappIntentLabel($state))
                            ->color(fn (string $state): string => static::intentColor($state)),
                        TextEntry::make('tracked_winning_number')
                            ->label(__('admin.resources.whatsapp_messages.fields.winning_number'))
                            ->placeholder('-'),
                        TextEntry::make('retry_of_message_id')
                            ->label(__('admin.resources.whatsapp_messages.fields.retry_of_message'))
                            ->placeholder('-'),
                        TextEntry::make('provider_message_id')
                            ->label(__('admin.resources.whatsapp_messages.fields.provider_message_id'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('provider_created_at')
                            ->label(__('admin.resources.whatsapp_messages.fields.provider_created_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('provider_status_at')
                            ->label(__('admin.resources.whatsapp_messages.fields.provider_status_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('provider_conversation_id')
                            ->label(__('admin.resources.whatsapp_messages.fields.provider_conversation'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('provider_pricing_category')
                            ->label(__('admin.resources.whatsapp_messages.fields.pricing_category'))
                            ->badge()
                            ->state(fn (WhatsappMessage $record): string => $record->provider_pricing_category ?: 'unknown')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappPricingCategoryLabel($state))
                            ->color(fn (string $state): string => static::pricingCategoryColor($state)),
                        TextEntry::make('provider_error_code')
                            ->label(__('admin.resources.whatsapp_messages.fields.provider_error_code'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('meta_error_summary')
                            ->label(__('admin.resources.whatsapp_messages.fields.meta_error'))
                            ->placeholder('-')
                            ->copyable(),
                    ]),
                Section::make(__('admin.resources.whatsapp_messages.sections.payload'))
                    ->schema([
                        TextEntry::make('body_text')
                            ->label(__('admin.resources.whatsapp_messages.fields.body'))
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('customer.phone')
                    ->label(__('admin.resources.whatsapp_messages.fields.customer_phone'))
                    ->searchable(),
                TextColumn::make('direction')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::directionLabel($state)),
                TextColumn::make('message_type')
                    ->label(__('admin.resources.whatsapp_messages.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::messageTypeLabel($state))
                    ->color(fn (?string $state): string => static::messageTypeColor($state)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationsUi::whatsappMessageStatusLabel($state))
                    ->color(fn (?string $state): string => static::messageStatusColor($state)),
                TextColumn::make('provider_status')
                    ->label(__('admin.resources.whatsapp_messages.fields.provider_status'))
                    ->badge()
                    ->state(fn (WhatsappMessage $record): string => $record->provider_status ?: 'unknown')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappProviderStatusLabel($state))
                    ->color(fn (string $state): string => static::providerStatusColor($state)),
                TextColumn::make('provider_pricing_category')
                    ->label(__('admin.resources.whatsapp_messages.fields.pricing'))
                    ->badge()
                    ->state(fn (WhatsappMessage $record): string => $record->provider_pricing_category ?: 'unknown')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappPricingCategoryLabel($state))
                    ->color(fn (string $state): string => static::pricingCategoryColor($state))
                    ->toggleable(),
                TextColumn::make('tracked_ticket_id')
                    ->label(__('admin.resources.whatsapp_messages.fields.ticket'))
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('tracked_raffle_id')
                    ->label(__('admin.resources.whatsapp_messages.fields.raffle'))
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tracked_intent')
                    ->label(__('admin.resources.whatsapp_messages.fields.intent'))
                    ->badge()
                    ->state(fn (WhatsappMessage $record): string => $record->tracked_intent ?: 'generic')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::whatsappIntentLabel($state))
                    ->color(fn (string $state): string => static::intentColor($state))
                    ->toggleable(),
                TextColumn::make('tracked_winning_number')
                    ->label(__('admin.resources.whatsapp_messages.fields.winning_number'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider_conversation_id')
                    ->label(__('admin.resources.whatsapp_messages.fields.conversation'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider_error_code')
                    ->label(__('admin.resources.whatsapp_messages.fields.error_code'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('retry_of_message_id')
                    ->label(__('admin.resources.whatsapp_messages.fields.retry_of'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('meta_error_summary')
                    ->label(__('admin.resources.whatsapp_messages.fields.meta_error'))
                    ->placeholder('-')
                    ->limit(40)
                    ->tooltip(fn (WhatsappMessage $record): ?string => $record->meta_error_summary)
                    ->toggleable(),
                TextColumn::make('provider_created_at')
                    ->label(__('admin.resources.whatsapp_messages.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options(static::directionOptions()),
                SelectFilter::make('message_type')
                    ->label(__('admin.resources.whatsapp_messages.filters.type'))
                    ->options(static::messageTypeOptions()),
                SelectFilter::make('status')
                    ->options(static::messageStatusOptions()),
                SelectFilter::make('provider_status')
                    ->label(__('admin.resources.whatsapp_messages.filters.provider_status'))
                    ->options(static::providerStatusOptions()),
                SelectFilter::make('provider_pricing_category')
                    ->label(__('admin.resources.whatsapp_messages.filters.pricing_category'))
                    ->options(static::pricingCategoryOptions()),
                Filter::make('only_ticket_messages')
                    ->label(__('admin.resources.whatsapp_messages.filters.only_ticket_messages'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('tracked_ticket_id'))
                    ->indicator(__('admin.resources.whatsapp_messages.filters.only_ticket_messages')),
                Filter::make('winner_notifications')
                    ->label(__('admin.resources.whatsapp_messages.filters.winner_notifications'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('direction', 'outbound')
                        ->whereRaw("whatsapp_messages.payload_json->>'intent' = 'raffle_winner_notification'"))
                    ->indicator(__('admin.resources.whatsapp_messages.filters.winner_notifications')),
                Filter::make('pending_outbound')
                    ->label(__('admin.resources.whatsapp_messages.filters.pending_outbound'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('direction', 'outbound')
                        ->whereIn('status', ['queued', 'generated']))
                    ->indicator(__('admin.resources.whatsapp_messages.filters.pending_outbound')),
                Filter::make('only_delivered')
                    ->label(__('admin.resources.whatsapp_messages.filters.only_delivered'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('provider_status', 'delivered'))
                    ->indicator(__('admin.resources.whatsapp_messages.filters.only_delivered')),
                Filter::make('only_read')
                    ->label(__('admin.resources.whatsapp_messages.filters.only_read'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('provider_status', 'read'))
                    ->indicator(__('admin.resources.whatsapp_messages.filters.only_read')),
                Filter::make('only_failed_outbound')
                    ->label(__('admin.resources.whatsapp_messages.filters.only_failed_outbound'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('direction', 'outbound')->where(function (Builder $query): Builder {
                        return $query
                            ->where('status', 'failed')
                            ->orWhere('provider_status', 'failed');
                    }))
                    ->indicator(__('admin.resources.whatsapp_messages.filters.only_failed_outbound')),
            ])
            ->recordActions([
                static::makeRetryFailedAction(),
                static::makeOpenLinkedTicketAction(),
                static::makeOpenLinkedRaffleAction(),
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.resources.whatsapp_messages.empty_state.heading'))
            ->emptyStateDescription(__('admin.resources.whatsapp_messages.empty_state.description'))
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
            'index' => ListWhatsappMessages::route('/'),
            'view' => ViewWhatsappMessage::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
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
                'tracked_intent' => WhatsappMessage::query()
                    ->from('whatsapp_messages as intent_source')
                    ->selectRaw("intent_source.payload_json->>'intent'")
                    ->whereColumn('intent_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'tracked_winning_number' => WhatsappMessage::query()
                    ->from('whatsapp_messages as winning_source')
                    ->selectRaw("winning_source.payload_json->>'winning_number'")
                    ->whereColumn('winning_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'retry_of_message_id' => WhatsappMessage::query()
                    ->from('whatsapp_messages as retry_source')
                    ->selectRaw("(retry_source.payload_json->>'retry_of_message_id')::bigint")
                    ->whereColumn('retry_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'provider_conversation_id' => WhatsappMessage::query()
                    ->from('whatsapp_messages as conversation_source')
                    ->selectRaw("conversation_source.payload_json->'provider_status_event'->'conversation'->>'id'")
                    ->whereColumn('conversation_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'provider_pricing_category' => WhatsappMessage::query()
                    ->from('whatsapp_messages as pricing_source')
                    ->selectRaw("pricing_source.payload_json->'provider_status_event'->'pricing'->>'category'")
                    ->whereColumn('pricing_source.id', 'whatsapp_messages.id')
                    ->limit(1),
                'provider_error_code' => WhatsappMessage::query()
                    ->from('whatsapp_messages as provider_error_source')
                    ->selectRaw("
                        coalesce(
                            provider_error_source.payload_json->'provider_status_event'->'errors'->0->>'code',
                            provider_error_source.payload_json->'meta_error'->>'status'
                        )
                    ")
                    ->whereColumn('provider_error_source.id', 'whatsapp_messages.id')
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
            ->with('customer');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::WhatsappMessagesView);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function makeRetryFailedAction(): Action
    {
        return Action::make('retry_failed')
            ->label(__('admin.resources.whatsapp_messages.actions.retry_failed'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::WhatsappMessagesManage))
            ->disabled(fn (WhatsappMessage $record): bool => ! (
                $record->direction === 'outbound'
                && in_array('failed', [$record->status, $record->provider_status], true)
            ))
            ->requiresConfirmation()
            ->action(function (WhatsappMessage $record): void {
                try {
                    $actor = Auth::user();

                    $newAttempt = app(RetryFailedWhatsappMessageAction::class)->execute(
                        $record,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.whatsapp_messages.notifications.retry_queued'))
                        ->body(__('admin.resources.whatsapp_messages.notifications.retry_queued_body', ['id' => $newAttempt->id]))
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

    public static function makeOpenLinkedTicketAction(): Action
    {
        return Action::make('open_linked_ticket')
            ->label(__('admin.resources.whatsapp_messages.actions.open_ticket'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('success')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::TicketsView))
            ->disabled(fn (WhatsappMessage $record): bool => blank($record->tracked_ticket_id))
            ->url(
                fn (WhatsappMessage $record): ?string => filled($record->tracked_ticket_id)
                    ? TicketResource::getUrl('view', ['record' => $record->tracked_ticket_id])
                    : null,
                shouldOpenInNewTab: true
            );
    }

    public static function makeOpenLinkedRaffleAction(): Action
    {
        return Action::make('open_linked_raffle')
            ->label(__('admin.resources.whatsapp_messages.actions.open_raffle'))
            ->icon(Heroicon::OutlinedGiftTop)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::RafflesView))
            ->disabled(fn (WhatsappMessage $record): bool => blank($record->tracked_raffle_id))
            ->url(
                fn (WhatsappMessage $record): ?string => filled($record->tracked_raffle_id)
                    ? RaffleResource::getUrl('view', ['record' => $record->tracked_raffle_id])
                    : null,
                shouldOpenInNewTab: true
            );
    }

    protected static function messageStatusColor(?string $state): string
    {
        return OperationsUi::whatsappMessageStatusColor($state);
    }

    protected static function messageTypeColor(?string $state): string
    {
        return match ($state) {
            'document' => 'success',
            'template' => 'warning',
            'text' => 'info',
            default => 'gray',
        };
    }

    protected static function messageTypeLabel(?string $state): string
    {
        return match ($state) {
            'text' => __('admin.operations.message_types.text'),
            'image' => __('admin.operations.message_types.image'),
            'template' => __('admin.operations.message_types.template'),
            'document' => __('admin.operations.message_types.document'),
            'interactive' => __('admin.operations.message_types.interactive'),
            'other' => __('admin.operations.message_types.other'),
            default => __('admin.operations.message_types.unknown'),
        };
    }

    protected static function directionLabel(?string $state): string
    {
        return match ($state) {
            'inbound' => __('admin.operations.directions.inbound'),
            'outbound' => __('admin.operations.directions.outbound'),
            default => __('admin.operations.directions.outbound'),
        };
    }

    protected static function providerStatusColor(?string $state): string
    {
        return OperationsUi::whatsappProviderStatusColor($state);
    }

    protected static function pricingCategoryColor(?string $state): string
    {
        return OperationsUi::whatsappPricingCategoryColor($state);
    }

    protected static function intentColor(?string $state): string
    {
        return OperationsUi::whatsappIntentColor($state);
    }

    /**
     * @return array<string, string>
     */
    protected static function directionOptions(): array
    {
        return [
            'inbound' => __('admin.operations.directions.inbound'),
            'outbound' => __('admin.operations.directions.outbound'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function messageTypeOptions(): array
    {
        return [
            'text' => __('admin.operations.message_types.text'),
            'image' => __('admin.operations.message_types.image'),
            'template' => __('admin.operations.message_types.template'),
            'document' => __('admin.operations.message_types.document'),
            'interactive' => __('admin.operations.message_types.interactive'),
            'other' => __('admin.operations.message_types.other'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function messageStatusOptions(): array
    {
        return [
            'queued' => __('admin.operations.whatsapp_message_statuses.queued'),
            'generated' => __('admin.operations.whatsapp_message_statuses.generated'),
            'sent' => __('admin.operations.whatsapp_message_statuses.sent'),
            'failed' => __('admin.operations.whatsapp_message_statuses.failed'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function providerStatusOptions(): array
    {
        return [
            'sent' => __('admin.operations.whatsapp_provider_statuses.sent'),
            'delivered' => __('admin.operations.whatsapp_provider_statuses.delivered'),
            'read' => __('admin.operations.whatsapp_provider_statuses.read'),
            'failed' => __('admin.operations.whatsapp_provider_statuses.failed'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function pricingCategoryOptions(): array
    {
        return [
            'service' => __('admin.operations.whatsapp_pricing_categories.service'),
            'utility' => __('admin.operations.whatsapp_pricing_categories.utility'),
            'marketing' => __('admin.operations.whatsapp_pricing_categories.marketing'),
            'authentication' => __('admin.operations.whatsapp_pricing_categories.authentication'),
        ];
    }
}
