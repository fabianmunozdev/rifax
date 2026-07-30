<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Support\OperationsUi;
use App\Filament\Widgets\Concerns\HasFinancialDashboardAccess;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentPendingPaymentsWidget extends TableWidget
{
    use HasFinancialDashboardAccess;

    protected static ?int $sort = -12;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return __('admin.widgets.recent_pending_payments.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getPendingPaymentsQuery())
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->url(
                        fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]),
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('purchase.customer.phone')
                    ->label(__('admin.widgets.recent_pending_payments.columns.customer'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('purchase.raffle.title')
                    ->label(__('admin.widgets.recent_pending_payments.columns.raffle'))
                    ->placeholder('-')
                    ->url(
                        fn (Payment $record): ?string => $record->purchase?->raffle !== null
                            ? RaffleResource::getUrl('view', ['record' => $record->purchase->raffle])
                            : null,
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('purchase.id')
                    ->label(__('admin.widgets.recent_pending_payments.columns.purchase'))
                    ->url(
                        fn (Payment $record): ?string => $record->purchase !== null
                            ? PurchaseResource::getUrl('view', ['record' => $record->purchase])
                            : null,
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('expected_amount')
                    ->label(__('admin.widgets.recent_pending_payments.columns.expected'))
                    ->money(fn (Payment $record): string => $record->purchase?->currency ?: 'COP'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationsUi::paymentStatusLabel($state))
                    ->color(fn (?string $state): string => OperationsUi::paymentStatusColor($state)),
                TextColumn::make('proof_received_at')
                    ->label(__('admin.widgets.recent_pending_payments.columns.proof_at'))
                    ->dateTime()
                    ->sortable(),
            ]);
    }

    protected function getPendingPaymentsQuery(): Builder
    {
        return Payment::query()
            ->with([
                'purchase.customer',
                'purchase.raffle',
            ])
            ->where('status', 'pending_review')
            ->latest('proof_received_at')
            ->latest('id');
    }
}
