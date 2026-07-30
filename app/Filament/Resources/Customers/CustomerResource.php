<?php

namespace App\Filament\Resources\Customers;

use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Support\PanelAccess;
use App\Filament\Support\OperationsUi;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends BaseResource
{
    protected static ?string $model = Customer::class;

    protected static ?string $translationKey = 'customers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.customers.sections.customer'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('phone')
                            ->copyable(),
                        TextEntry::make('name')
                            ->placeholder('-'),
                        TextEntry::make('wa_id')
                            ->label(__('admin.resources.customers.fields.whatsapp_id'))
                            ->placeholder('-'),
                        TextEntry::make('last_interaction_at')
                            ->label(__('admin.resources.customers.fields.last_interaction'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.customers.sections.current_conversation'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('currentConversationState.status')
                            ->label(__('admin.resources.customers.fields.status'))
                            ->state(fn (Customer $record): string => $record->currentConversationState?->status ?: 'unknown')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => OperationsUi::conversationStatusLabel($state))
                            ->color(fn (string $state): string => OperationsUi::conversationStatusColor($state)),
                        TextEntry::make('currentConversationState.currentRaffle.title')
                            ->label(__('admin.resources.customers.fields.current_raffle'))
                            ->placeholder('-'),
                        TextEntry::make('currentConversationState.substatus')
                            ->label(__('admin.resources.customers.fields.substatus'))
                            ->placeholder('-'),
                        TextEntry::make('currentConversationState.context_expires_at')
                            ->label(__('admin.resources.customers.fields.context_expires'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.customers.sections.operational_summary'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('purchases_count')
                            ->label(__('admin.resources.customers.fields.purchases')),
                        TextEntry::make('pending_purchases_count')
                            ->label(__('admin.resources.customers.fields.pending_purchases'))
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),
                        TextEntry::make('recent_purchases_summary')
                            ->label(__('admin.resources.customers.fields.recent_purchases'))
                            ->state(fn (Customer $record): string => $record->purchases()
                                ->latest('id')
                                ->limit(5)
                                ->get()
                                ->map(fn ($purchase): string => '#'.$purchase->id.' '.OperationsUi::purchaseStatusLabel($purchase->status))
                                ->implode(' | '))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('recent_whatsapp_summary')
                            ->label(__('admin.resources.customers.fields.recent_whatsapp_messages'))
                            ->state(fn (Customer $record): string => $record->whatsappMessages()
                                ->latest('id')
                                ->limit(5)
                                ->get()
                                ->map(fn ($message): string => strtoupper(substr((string) $message->direction, 0, 1)).' '.$message->message_type)
                                ->implode(' | '))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('currentConversationState.status')
                    ->label(__('admin.resources.customers.fields.conversation'))
                    ->state(fn (Customer $record): string => $record->currentConversationState?->status ?: 'unknown')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperationsUi::conversationStatusLabel($state))
                    ->color(fn (string $state): string => OperationsUi::conversationStatusColor($state)),
                TextColumn::make('purchases_count')
                    ->label(__('admin.resources.customers.fields.purchases'))
                    ->sortable(),
                TextColumn::make('pending_purchases_count')
                    ->label(__('admin.resources.customers.fields.pending'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success')
                    ->sortable(),
                TextColumn::make('last_interaction_at')
                    ->label(__('admin.resources.customers.fields.last_interaction'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_interaction_at', 'desc')
            ->filters([
                Filter::make('active_conversation')
                    ->label(__('admin.resources.customers.filters.active_conversation'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('conversationStates', fn (Builder $query): Builder => $query->whatsapp()->active())),
                Filter::make('pending_purchases')
                    ->label(__('admin.resources.customers.filters.pending_purchases'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('purchases', fn (Builder $query): Builder => $query->whereIn('status', ['reserved', 'payment_submitted', 'under_review', 'rejected']))),
            ])
            ->recordActions([
                static::makeOpenConversationAction(),
                static::makeOpenPurchasesAction(),
                static::makeOpenWhatsappMessagesAction(),
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.resources.customers.empty_state.heading'))
            ->emptyStateDescription(__('admin.resources.customers.empty_state.description'))
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'currentConversationState.currentRaffle',
                'latestWhatsappMessage',
            ])
            ->withCount('purchases')
            ->withCount([
                'purchases as pending_purchases_count' => fn (Builder $query): Builder => $query->whereIn('status', ['reserved', 'payment_submitted', 'under_review', 'rejected']),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::CustomersView);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function makeOpenConversationAction(): Action
    {
        return Action::make('open_conversation')
            ->label(__('admin.resources.customers.actions.open_conversation'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color(Color::Blue)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::ConversationsView))
            ->disabled(fn (Customer $record): bool => $record->currentConversationState === null)
            ->url(
                fn (Customer $record): ?string => $record->currentConversationState !== null
                    ? ConversationResource::getUrl('view', ['record' => $record->currentConversationState])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenPurchasesAction(): Action
    {
        return Action::make('open_purchases')
            ->label(__('admin.resources.customers.actions.open_purchases'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(Color::Amber)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PurchasesView))
            ->url(
                fn (Customer $record): string => PurchaseResource::getUrl('index', [
                    'tableSearch' => $record->phone,
                ]),
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenWhatsappMessagesAction(): Action
    {
        return Action::make('open_whatsapp_messages')
            ->label(__('admin.resources.customers.actions.open_whatsapp_messages'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::WhatsappMessagesView))
            ->url(
                fn (Customer $record): string => WhatsappMessageResource::getUrl('index', [
                    'tableSearch' => $record->phone,
                ]),
                shouldOpenInNewTab: true,
            );
    }
}
