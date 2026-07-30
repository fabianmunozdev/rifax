<?php

namespace App\Filament\Resources\Payments;

use App\Actions\Payments\ApprovePaymentAction;
use App\Actions\Payments\RejectPaymentAction;
use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Support\PanelAccess;
use App\Filament\Support\OperationsUi;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Raffle;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
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

class PaymentResource extends BaseResource
{
    protected static ?string $model = Payment::class;

    protected static ?string $translationKey = 'payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $modelLabel = 'Payment';

    protected static ?string $pluralModelLabel = 'Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.payments.sections.payment'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state, Payment $record): string => OperationsUi::paymentStatusLabel(
                                $record->isReviewOverdue() ? 'pending_review_overdue' : $state
                            ))
                            ->color(fn (?string $state, Payment $record): string => static::paymentStatusColor(
                                $record->isReviewOverdue() ? 'pending_review_overdue' : $state
                            )),
                        TextEntry::make('expected_amount')
                            ->money(fn (Payment $record): string => $record->purchase?->currency ?: 'COP'),
                        TextEntry::make('received_amount')
                            ->money(fn (Payment $record): string => $record->purchase?->currency ?: 'COP')
                            ->placeholder('-'),
                        TextEntry::make('reference')
                            ->placeholder('-'),
                        TextEntry::make('proof_channel')
                            ->placeholder('-'),
                        TextEntry::make('proof_received_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('review_due_at')
                            ->label(__('admin.resources.payments.fields.review_due_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('reviewer.name')
                            ->label(__('admin.resources.payments.fields.reviewed_by'))
                            ->placeholder('-'),
                        TextEntry::make('reviewed_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('rejection_reason')
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.payments.sections.purchase'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('purchase.id')
                            ->label(__('admin.resources.payments.fields.purchase_id')),
                        TextEntry::make('purchase.status')
                            ->label(__('admin.resources.payments.fields.purchase_status'))
                            ->badge()
                            ->state(fn (Payment $record): string => $record->purchase?->status ?: 'unknown')
                            ->formatStateUsing(fn (string $state): string => OperationsUi::purchaseStatusLabel($state))
                            ->color(fn (string $state): string => static::purchaseStatusColor($state)),
                        TextEntry::make('purchase.customer.phone')
                            ->label(__('admin.resources.payments.fields.customer_phone'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('purchase.raffle.title')
                            ->label(__('admin.resources.payments.fields.raffle'))
                            ->placeholder('-'),
                        TextEntry::make('purchase_total')
                            ->label(__('admin.resources.payments.fields.purchase_total'))
                            ->state(fn (Payment $record): string => (string) ($record->purchase?->total_amount ?? '-')),
                        TextEntry::make('purchase_numbers')
                            ->label(__('admin.resources.payments.fields.numbers'))
                            ->state(fn (Payment $record): string => $record->purchase?->numbers?->pluck('number')->implode(', ') ?: '-'),
                    ]),
                Section::make(__('admin.resources.payments.sections.proof'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('proof_path')
                            ->label(__('admin.resources.payments.fields.storage_path'))
                            ->state(fn (Payment $record): string => $record->proofs->first()?->storage_path ?: '-')
                            ->copyable(),
                        TextEntry::make('proof_url')
                            ->label(__('admin.resources.payments.fields.public_url'))
                            ->state(fn (Payment $record): ?string => static::proofUrl($record->proofs->first()))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('proof_original_filename')
                            ->label(__('admin.resources.payments.fields.original_filename'))
                            ->state(fn (Payment $record): string => $record->proofs->first()?->original_filename ?: '-'),
                        TextEntry::make('proof_mime_type')
                            ->label(__('admin.resources.payments.fields.mime_type'))
                            ->state(fn (Payment $record): string => $record->proofs->first()?->mime_type ?: '-'),
                    ]),
                Section::make(__('admin.resources.payments.sections.ticket'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('purchase.ticket.code')
                            ->label(__('admin.resources.payments.fields.ticket_code'))
                            ->placeholder('-'),
                        TextEntry::make('purchase.ticket.public_url')
                            ->label(__('admin.resources.payments.fields.public_url'))
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
                TextColumn::make('purchase.id')
                    ->label(__('admin.resources.payments.fields.purchase'))
                    ->searchable(),
                TextColumn::make('purchase.customer.phone')
                    ->label(__('admin.resources.payments.fields.customer_phone'))
                    ->searchable(),
                TextColumn::make('purchase.raffle.title')
                    ->label(__('admin.resources.payments.fields.raffle'))
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Payment $record): string => OperationsUi::paymentStatusLabel(
                        $record->isReviewOverdue() ? 'pending_review_overdue' : $state
                    ))
                    ->color(fn (?string $state, Payment $record): string => static::paymentStatusColor(
                        $record->isReviewOverdue() ? 'pending_review_overdue' : $state
                    )),
                TextColumn::make('purchase.status')
                    ->label(__('admin.resources.payments.fields.purchase_status'))
                    ->badge()
                    ->state(fn (Payment $record): string => $record->purchase?->status ?: 'unknown')
                    ->formatStateUsing(fn (string $state): string => OperationsUi::purchaseStatusLabel($state))
                    ->color(fn (string $state): string => static::purchaseStatusColor($state)),
                TextColumn::make('expected_amount')
                    ->label(__('admin.resources.payments.fields.expected'))
                    ->money(fn (Payment $record): string => $record->purchase?->currency ?: 'COP')
                    ->sortable(),
                TextColumn::make('proof_channel')
                    ->badge(),
                IconColumn::make('has_proof')
                    ->label(__('admin.resources.payments.fields.proof'))
                    ->boolean()
                    ->state(fn (Payment $record): bool => $record->proofs->isNotEmpty()),
                IconColumn::make('has_ticket')
                    ->label(__('admin.resources.payments.fields.ticket'))
                    ->boolean()
                    ->state(fn (Payment $record): bool => $record->purchase?->ticket !== null),
                TextColumn::make('reviewer.name')
                    ->label(__('admin.resources.payments.fields.reviewer'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('proof_received_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('review_due_at')
                    ->label(__('admin.resources.payments.fields.review_due_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('rejection_reason')
                    ->limit(40)
                    ->tooltip(fn (Payment $record): ?string => $record->rejection_reason)
                    ->toggleable(),
            ])
            ->defaultSort('proof_received_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(static::paymentStatusOptions()),
                SelectFilter::make('raffle_id')
                    ->label(__('admin.resources.payments.filters.raffle'))
                    ->options(fn (): array => Raffle::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas('purchase', fn (Builder $query): Builder => $query->where('raffle_id', $data['value']))
                    )),
                Filter::make('with_proof')
                    ->label(__('admin.resources.payments.filters.with_proof'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('proofs'))
                    ->indicator(__('admin.resources.payments.filters.with_proof')),
                Filter::make('with_ticket')
                    ->label(__('admin.resources.payments.filters.with_ticket'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('purchase.ticket'))
                    ->indicator(__('admin.resources.payments.filters.with_ticket')),
                Filter::make('review_overdue')
                    ->label(__('admin.resources.payments.filters.review_overdue'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'pending_review')
                        ->whereNotNull('review_due_at')
                        ->where('review_due_at', '<=', now()))
                    ->indicator(__('admin.resources.payments.filters.review_overdue')),
            ])
            ->recordActions([
                static::makeApproveAction(),
                static::makeRejectAction(),
                static::makeOpenPurchaseAction(),
                static::makeOpenRaffleAction(),
                static::makeOpenTicketAction(),
                static::makeOpenProofAction(),
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.resources.payments.empty_state.heading'))
            ->emptyStateDescription(__('admin.resources.payments.empty_state.description'))
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'purchase.customer',
            'purchase.raffle',
            'purchase.numbers',
            'purchase.ticket',
            'proofs.whatsappMessage',
            'reviewer',
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::PaymentsView);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function makeApproveAction(): Action
    {
        return Action::make('approve_payment')
            ->label(__('admin.resources.payments.actions.approve_payment'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PaymentsReview))
            ->disabled(fn (Payment $record): bool => $record->status !== 'pending_review')
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.payments.modals.approve_payment'))
            ->action(function (Payment $record): void {
                try {
                    $reviewer = Auth::user();

                    app(ApprovePaymentAction::class)->execute(
                        $record,
                        $reviewer instanceof User ? $reviewer : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.payments.notifications.payment_approved'))
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

    public static function makeRejectAction(): Action
    {
        return Action::make('reject_payment')
            ->label(__('admin.resources.payments.actions.reject_payment'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PaymentsReview))
            ->disabled(fn (Payment $record): bool => $record->status !== 'pending_review')
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin.resources.payments.fields.reason'))
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->action(function (Payment $record, array $data): void {
                try {
                    $reviewer = Auth::user();

                    app(RejectPaymentAction::class)->execute(
                        $record,
                        (string) $data['reason'],
                        $reviewer instanceof User ? $reviewer : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.payments.notifications.payment_rejected'))
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
            ->label(__('admin.resources.payments.actions.open_purchase'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(Color::Amber)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PurchasesView))
            ->disabled(fn (Payment $record): bool => $record->purchase === null)
            ->url(
                fn (Payment $record): ?string => $record->purchase !== null
                    ? PurchaseResource::getUrl('view', ['record' => $record->purchase])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenRaffleAction(): Action
    {
        return Action::make('open_raffle')
            ->label(__('admin.resources.payments.actions.open_raffle'))
            ->icon(Heroicon::OutlinedGiftTop)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::RafflesView))
            ->disabled(fn (Payment $record): bool => $record->purchase?->raffle === null)
            ->url(
                fn (Payment $record): ?string => $record->purchase?->raffle !== null
                    ? RaffleResource::getUrl('view', ['record' => $record->purchase->raffle])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenTicketAction(): Action
    {
        return Action::make('open_ticket')
            ->label(__('admin.resources.payments.actions.open_ticket'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('success')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::TicketsView))
            ->disabled(fn (Payment $record): bool => $record->purchase?->ticket === null)
            ->url(
                fn (Payment $record): ?string => $record->purchase?->ticket !== null
                    ? TicketResource::getUrl('view', ['record' => $record->purchase->ticket])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenProofAction(): Action
    {
        return Action::make('open_proof')
            ->label(__('admin.resources.payments.actions.open_payment_proof'))
            ->icon(Heroicon::OutlinedPhoto)
            ->disabled(fn (Payment $record): bool => blank(static::proofUrl($record->proofs->first())))
            ->url(fn (Payment $record): ?string => static::proofUrl($record->proofs->first()), shouldOpenInNewTab: true);
    }

    protected static function proofUrl(?PaymentProof $proof): ?string
    {
        if ($proof === null || blank($proof->storage_path) || $proof->storage_disk !== 'public') {
            return null;
        }

        return asset('storage/'.$proof->storage_path);
    }

    protected static function paymentStatusColor(?string $state): string
    {
        return OperationsUi::paymentStatusColor($state);
    }

    protected static function purchaseStatusColor(string $state): string
    {
        return OperationsUi::purchaseStatusColor($state);
    }

    /**
     * @return array<string, string>
     */
    protected static function paymentStatusOptions(): array
    {
        return [
            'pending_review' => __('admin.operations.payment_statuses.pending_review'),
            'approved' => __('admin.operations.payment_statuses.approved'),
            'rejected' => __('admin.operations.payment_statuses.rejected'),
        ];
    }
}
