<?php

namespace App\Filament\Resources\Purchases;

use App\Actions\WhatsApp\SendPurchasePaymentReminderWhatsappAction;
use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Support\PanelAccess;
use App\Filament\Support\OperationsUi;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class PurchaseResource extends BaseResource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $translationKey = 'purchases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Purchases';

    protected static ?string $modelLabel = 'Purchase';

    protected static ?string $pluralModelLabel = 'Purchases';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.purchases.sections.purchase'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => static::purchaseStatusColor($state)),
                        TextEntry::make('quantity'),
                        TextEntry::make('total_amount')
                            ->money(fn (Purchase $record): string => $record->currency ?: 'COP'),
                        TextEntry::make('currency'),
                        TextEntry::make('purchase_numbers')
                            ->label(__('admin.resources.purchases.fields.numbers'))
                            ->state(fn (Purchase $record): string => $record->numbers->pluck('number')->implode(', ') ?: '-'),
                        TextEntry::make('reserved_until')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('proof_submitted_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('paid_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('cancelled_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.purchases.sections.customer'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer.phone')
                            ->label(__('admin.resources.purchases.fields.customer_phone'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('customer.name')
                            ->label(__('admin.resources.purchases.fields.customer_name'))
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.purchases.sections.raffle'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('raffle.title')
                            ->label(__('admin.resources.purchases.fields.raffle'))
                            ->placeholder('-'),
                        TextEntry::make('raffle_title_snapshot')
                            ->label(__('admin.resources.purchases.fields.title_snapshot'))
                            ->placeholder('-'),
                        TextEntry::make('raffle.lottery_name')
                            ->label(__('admin.resources.purchases.fields.lottery'))
                            ->placeholder('-'),
                        TextEntry::make('raffle.lottery_draw_number')
                            ->label(__('admin.resources.purchases.fields.draw_number'))
                            ->placeholder('-'),
                        TextEntry::make('raffle.draw_date')
                            ->label(__('admin.resources.purchases.fields.draw_date'))
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('raffle.draw_time')
                            ->label(__('admin.resources.purchases.fields.draw_time'))
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.purchases.sections.latest_payment'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('latestPayment.id')
                            ->label(__('admin.resources.purchases.fields.payment_id'))
                            ->placeholder('-'),
                        TextEntry::make('latestPayment.status')
                            ->label(__('admin.resources.purchases.fields.payment_status'))
                            ->badge()
                            ->state(fn (Purchase $record): string => $record->latestPayment?->status ?: 'none')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::paymentStatusLabel($state))
                            ->color(fn (string $state): string => static::paymentStatusColor($state)),
                        TextEntry::make('latestPayment.proof_received_at')
                            ->label(__('admin.resources.purchases.fields.proof_received_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('latestPayment.reviewer.name')
                            ->label(__('admin.resources.purchases.fields.reviewed_by'))
                            ->placeholder('-'),
                        TextEntry::make('latestPayment.reviewed_at')
                            ->label(__('admin.resources.purchases.fields.reviewed_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('latestPayment.rejection_reason')
                            ->label(__('admin.resources.purchases.fields.rejection_reason'))
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.purchases.sections.ticket'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ticket.code')
                            ->label(__('admin.resources.purchases.fields.ticket_code'))
                            ->placeholder('-'),
                        TextEntry::make('ticket.version')
                            ->placeholder('-'),
                        TextEntry::make('ticket.public_url')
                            ->label(__('admin.resources.payments.fields.public_url'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('ticket.image_path')
                            ->label(__('admin.resources.purchases.fields.asset_path'))
                            ->placeholder('-')
                            ->copyable(),
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
                    ->label(__('admin.resources.purchases.fields.customer_phone'))
                    ->searchable(),
                TextColumn::make('raffle.title')
                    ->label(__('admin.resources.purchases.fields.raffle'))
                    ->searchable(),
                TextColumn::make('quantity')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label(__('admin.resources.purchases.fields.total'))
                    ->money(fn (Purchase $record): string => $record->currency ?: 'COP')
                    ->sortable(),
                TextColumn::make('purchase_numbers')
                    ->label(__('admin.resources.purchases.fields.numbers'))
                    ->state(fn (Purchase $record): string => $record->numbers->pluck('number')->implode(', ') ?: '-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationsUi::purchaseStatusLabel($state))
                    ->color(fn (?string $state): string => static::purchaseStatusColor($state)),
                TextColumn::make('latestPayment.status')
                    ->label(__('admin.resources.purchases.fields.latest_payment'))
                    ->badge()
                    ->state(fn (Purchase $record): string => $record->latestPayment?->status ?: 'none')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::paymentStatusLabel($state))
                    ->color(fn (string $state): string => static::paymentStatusColor($state)),
                TextColumn::make('ticket.code')
                    ->label(__('admin.resources.purchases.fields.ticket'))
                    ->placeholder('-')
                    ->toggleable(),
                IconColumn::make('has_ticket')
                    ->label(__('admin.resources.purchases.fields.ticket_ready'))
                    ->boolean()
                    ->state(fn (Purchase $record): bool => $record->ticket !== null),
                TextColumn::make('proof_submitted_at')
                    ->label(__('admin.resources.purchases.fields.proof_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('reserved_until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(static::purchaseStatusOptions()),
                SelectFilter::make('raffle_id')
                    ->label(__('admin.resources.purchases.filters.raffle'))
                    ->options(fn (): array => Raffle::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable(),
                Filter::make('pending_payment_review')
                    ->label(__('admin.resources.purchases.filters.pending_payment_review'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('latestPayment', fn (Builder $query): Builder => $query->where('status', 'pending_review')))
                    ->indicator(__('admin.resources.purchases.filters.pending_payment_review')),
                Filter::make('with_ticket')
                    ->label(__('admin.resources.purchases.filters.with_ticket'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('ticket'))
                    ->indicator(__('admin.resources.purchases.filters.with_ticket')),
            ])
            ->recordActions([
                static::makeSendPaymentReminderAction(),
                static::makeOpenLatestPaymentAction(),
                static::makeOpenTicketAction(),
                static::makeOpenRaffleAction(),
                static::makeOpenPublicTicketAction(),
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.resources.purchases.empty_state.heading'))
            ->emptyStateDescription(__('admin.resources.purchases.empty_state.description'))
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'customer',
            'raffle',
            'numbers',
            'latestPayment.reviewer',
            'ticket',
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::PurchasesView);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function makeOpenLatestPaymentAction(): Action
    {
        return Action::make('open_latest_payment')
            ->label(__('admin.resources.purchases.actions.open_latest_payment'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color(Color::Amber)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PaymentsView))
            ->disabled(fn (Purchase $record): bool => $record->latestPayment === null)
            ->url(
                fn (Purchase $record): ?string => $record->latestPayment !== null
                    ? PaymentResource::getUrl('view', ['record' => $record->latestPayment])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeSendPaymentReminderAction(): Action
    {
        return Action::make('send_payment_reminder')
            ->label(__('admin.resources.purchases.actions.send_payment_reminder'))
            ->icon(Heroicon::OutlinedBellAlert)
            ->color('warning')
            ->visible(fn (): bool => PanelAccess::allowsAny([
                PanelPermission::WhatsappMessagesManage,
                PanelPermission::PaymentsReview,
            ]))
            ->disabled(fn (Purchase $record): bool => ! in_array($record->status, ['reserved', 'rejected'], true))
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.purchases.modals.send_payment_reminder'))
            ->action(function (Purchase $record): void {
                try {
                    $actor = Auth::user();
                    $message = app(SendPurchasePaymentReminderWhatsappAction::class)->execute(
                        $record,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title($message !== null
                            ? __('admin.resources.purchases.notifications.payment_reminder_queued')
                            : __('admin.resources.purchases.notifications.payment_reminder_exists'))
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

    public static function makeOpenTicketAction(): Action
    {
        return Action::make('open_ticket')
            ->label(__('admin.resources.purchases.actions.open_ticket'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('success')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::TicketsView))
            ->disabled(fn (Purchase $record): bool => $record->ticket === null)
            ->url(
                fn (Purchase $record): ?string => $record->ticket !== null
                    ? TicketResource::getUrl('view', ['record' => $record->ticket])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenRaffleAction(): Action
    {
        return Action::make('open_raffle')
            ->label(__('admin.resources.purchases.actions.open_raffle'))
            ->icon(Heroicon::OutlinedGiftTop)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::RafflesView))
            ->disabled(fn (Purchase $record): bool => $record->raffle === null)
            ->url(
                fn (Purchase $record): ?string => $record->raffle !== null
                    ? RaffleResource::getUrl('view', ['record' => $record->raffle])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenPublicTicketAction(): Action
    {
        return Action::make('open_public_ticket')
            ->label(__('admin.resources.purchases.actions.open_public_ticket'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->disabled(fn (Purchase $record): bool => blank($record->ticket?->public_url))
            ->url(fn (Purchase $record): ?string => $record->ticket?->public_url, shouldOpenInNewTab: true);
    }

    protected static function purchaseStatusColor(?string $state): string
    {
        return OperationsUi::purchaseStatusColor($state);
    }

    protected static function paymentStatusColor(?string $state): string
    {
        return OperationsUi::paymentStatusColor($state);
    }

    /**
     * @return array<string, string>
     */
    protected static function purchaseStatusOptions(): array
    {
        return [
            'reserved' => __('admin.operations.purchase_statuses.reserved'),
            'payment_submitted' => __('admin.operations.purchase_statuses.payment_submitted'),
            'under_review' => __('admin.operations.purchase_statuses.under_review'),
            'paid' => __('admin.operations.purchase_statuses.paid'),
            'rejected' => __('admin.operations.purchase_statuses.rejected'),
            'expired' => __('admin.operations.purchase_statuses.expired'),
            'cancelled' => __('admin.operations.purchase_statuses.cancelled'),
        ];
    }
}
