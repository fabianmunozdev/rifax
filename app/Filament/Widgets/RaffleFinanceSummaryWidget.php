<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Support\ResourceTableLink;
use App\Filament\Widgets\Concerns\HasFinancialDashboardAccess;
use App\Models\Raffle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RaffleFinanceSummaryWidget extends TableWidget
{
    use HasFinancialDashboardAccess;

    protected static ?int $sort = -11;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return __('admin.widgets.raffle_finance_summary.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getRaffleFinanceQuery())
            ->defaultPaginationPageOption(5)
            ->paginated([5])
            ->defaultSort('pending_review_count', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.widgets.raffle_finance_summary.columns.raffle'))
                    ->searchable()
                    ->url(
                        fn (Raffle $record): string => RaffleResource::getUrl('view', ['record' => $record]),
                        shouldOpenInNewTab: true,
                    ),
                TextColumn::make('available_numbers_count')
                    ->label(__('admin.widgets.raffle_finance_summary.columns.available'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('pending_review_count')
                    ->label(__('admin.widgets.raffle_finance_summary.columns.pending_review'))
                    ->badge()
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'warning' : 'success')
                    ->url(
                        fn (Raffle $record): string => ResourceTableLink::payments([
                            'status' => ResourceTableLink::value('pending_review'),
                            'raffle_id' => ResourceTableLink::value($record->id),
                        ]),
                        shouldOpenInNewTab: true,
                    )
                    ->sortable(),
                TextColumn::make('paid_purchases_count')
                    ->label(__('admin.widgets.raffle_finance_summary.columns.paid_purchases'))
                    ->badge()
                    ->color(fn (mixed $state): string => ((int) $state) > 0 ? 'success' : 'gray')
                    ->url(
                        fn (Raffle $record): string => ResourceTableLink::purchases([
                            'status' => ResourceTableLink::value('paid'),
                            'raffle_id' => ResourceTableLink::value($record->id),
                        ]),
                        shouldOpenInNewTab: true,
                    )
                    ->sortable(),
                TextColumn::make('paid_revenue')
                    ->label(__('admin.widgets.raffle_finance_summary.columns.approved_revenue'))
                    ->state(fn (Raffle $record): float => (float) ($record->paid_revenue ?? 0))
                    ->money('COP')
                    ->sortable(),
            ]);
    }

    protected function getRaffleFinanceQuery(): Builder
    {
        return Raffle::query()
            ->withCount([
                'numbers as available_numbers_count' => fn (Builder $query): Builder => $query->where('status', 'available'),
                'purchases as paid_purchases_count' => fn (Builder $query): Builder => $query->where('status', 'paid'),
                'purchases as pending_review_count' => fn (Builder $query): Builder => $query->whereHas(
                    'latestPayment',
                    fn (Builder $query): Builder => $query->where('status', 'pending_review'),
                ),
            ])
            ->addSelect([
                'paid_revenue' => Raffle::query()
                    ->from('purchases')
                    ->selectRaw('coalesce(sum(total_amount), 0)')
                    ->whereColumn('purchases.raffle_id', 'raffles.id')
                    ->where('purchases.status', 'paid'),
            ])
            ->whereIn('status', ['published', 'closed'])
            ->orderByDesc('pending_review_count')
            ->orderByDesc('paid_revenue')
            ->orderBy('title');
    }
}
