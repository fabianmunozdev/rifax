<?php

namespace App\Filament\Resources\Conversations;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Actions\Conversations\HardResetConversationAction;
use App\Actions\Conversations\HumanHandoffAction;
use App\Actions\Conversations\SoftResetConversationAction;
use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Raffles\RaffleResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Support\OperationsUi;
use App\Filament\Support\PanelAccess;
use App\Livewire\ConversationChat;
use App\Models\ConversationState;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Livewire;
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

class ConversationResource extends BaseResource
{
    protected static ?string $model = ConversationState::class;

    protected static ?string $translationKey = 'conversations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 16;

    protected static ?string $navigationLabel = 'Conversations';

    protected static ?string $modelLabel = 'Conversation';

    protected static ?string $pluralModelLabel = 'Conversations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.conversations.sections.chat'))
                    ->description(fn (ConversationState $record): string => static::handoffLabel($record))
                    ->columnSpanFull()
                    ->schema([
                        Livewire::make(ConversationChat::class)->key('conversation-chat'),
                    ]),
                Section::make(__('admin.resources.conversations.sections.current_state'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => OperationsUi::conversationStatusLabel($state))
                            ->color(fn (?string $state): string => OperationsUi::conversationStatusColor($state)),
                        TextEntry::make('substatus')
                            ->placeholder('-'),
                        TextEntry::make('requested_quantity')
                            ->label(__('admin.resources.conversations.fields.requested_quantity'))
                            ->placeholder('-'),
                        TextEntry::make('selection_mode')
                            ->placeholder('-'),
                        TextEntry::make('selected_numbers_json')
                            ->label(__('admin.resources.conversations.fields.selected_numbers'))
                            ->state(fn (ConversationState $record): string => collect($record->selected_numbers_json ?? [])
                                ->map(fn (array $item): string => (string) ($item['number'] ?? '-'))
                                ->implode(', '))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('context_expires_at')
                            ->label(__('admin.resources.conversations.fields.context_expires'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.conversations.sections.related_context'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer.phone')
                            ->label(__('admin.resources.conversations.fields.customer'))
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('currentRaffle.title')
                            ->label(__('admin.resources.conversations.fields.raffle'))
                            ->placeholder('-'),
                        TextEntry::make('purchase.id')
                            ->label(__('admin.resources.conversations.fields.purchase'))
                            ->placeholder('-'),
                        TextEntry::make('payment.status')
                            ->label(__('admin.resources.conversations.fields.payment_status'))
                            ->state(fn (ConversationState $record): string => $record->payment?->status ?: 'unknown')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => OperationsUi::paymentStatusLabel($state))
                            ->color(fn (string $state): string => OperationsUi::paymentStatusColor($state)),
                        TextEntry::make('reservation.status')
                            ->label(__('admin.resources.conversations.fields.reservation_status'))
                            ->placeholder('-'),
                        TextEntry::make('follow_up_note')
                            ->label(__('admin.resources.conversations.fields.follow_up_note'))
                            ->state(fn (ConversationState $record): ?string => data_get($record->metadata_json, 'follow_up_note'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('admin.resources.conversations.sections.recent_activity'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('last_user_message_at')
                            ->label(__('admin.resources.conversations.fields.last_user_message'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('last_bot_message_at')
                            ->label(__('admin.resources.conversations.fields.last_bot_message'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('lastInboundMessage.body_text')
                            ->label(__('admin.resources.conversations.fields.last_inbound_text'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('lastOutboundMessage.body_text')
                            ->label(__('admin.resources.conversations.fields.last_outbound_text'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.phone')
                    ->label(__('admin.resources.conversations.fields.customer'))
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label(__('admin.resources.conversations.fields.customer_name'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('handoff_status')
                    ->label(__('admin.resources.conversations.fields.attention'))
                    ->badge()
                    ->state(fn (ConversationState $record): string => $record->handoff_status ?? 'bot')
                    ->formatStateUsing(fn (string $state, ConversationState $record): string => static::handoffLabel($record))
                    ->color(fn (string $state): string => match ($state) {
                        ConversationState::HANDOFF_REQUESTED => 'danger',
                        ConversationState::HANDOFF_ACTIVE => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (ConversationState $record): ?Heroicon => $record->hasUnreadForAgent() && $record->isHandledByHuman()
                        ? Heroicon::Envelope
                        : null),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationsUi::conversationStatusLabel($state))
                    ->color(fn (?string $state): string => OperationsUi::conversationStatusColor($state)),
                TextColumn::make('lastInboundMessage.body_text')
                    ->label(__('admin.resources.conversations.fields.last_inbound_text'))
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('substatus')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currentRaffle.title')
                    ->label(__('admin.resources.conversations.fields.raffle'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('purchase.id')
                    ->label(__('admin.resources.conversations.fields.purchase'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('payment.status')
                    ->label(__('admin.resources.conversations.fields.payment'))
                    ->state(fn (ConversationState $record): string => $record->payment?->status ?: 'unknown')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperationsUi::paymentStatusLabel($state))
                    ->color(fn (string $state): string => OperationsUi::paymentStatusColor($state)),
                IconColumn::make('metadata_json.follow_up_required')
                    ->label(__('admin.resources.conversations.fields.follow_up'))
                    ->boolean()
                    ->state(fn (ConversationState $record): bool => (bool) data_get($record->metadata_json, 'follow_up_required', false)),
                TextColumn::make('last_user_message_at')
                    ->label(__('admin.resources.conversations.fields.last_user_message'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('context_expires_at')
                    ->label(__('admin.resources.conversations.fields.context_expires'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('last_user_message_at', 'desc')
            ->poll('15s')
            ->filters([
                Filter::make('awaiting_agent')
                    ->label(__('admin.resources.conversations.filters.awaiting_agent'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->awaitingAgent()),
                Filter::make('with_human')
                    ->label(__('admin.resources.conversations.filters.with_human'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->withHuman()),
                Filter::make('assigned_to_me')
                    ->label(__('admin.resources.conversations.filters.assigned_to_me'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('assigned_user_id', PanelAccess::user()?->id)),
                SelectFilter::make('status')
                    ->options(static::conversationStatusOptions()),
                SelectFilter::make('current_raffle_id')
                    ->label(__('admin.resources.conversations.filters.raffle'))
                    ->relationship('currentRaffle', 'title'),
                Filter::make('active_purchase')
                    ->label(__('admin.resources.conversations.filters.with_active_purchase'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->withActivePurchase()),
                Filter::make('pending_review_payment')
                    ->label(__('admin.resources.conversations.filters.with_payment_under_review'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->withPendingReviewPayment()),
                Filter::make('expired')
                    ->label(__('admin.resources.conversations.filters.expired_context'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->expired()),
                Filter::make('follow_up_required')
                    ->label(__('admin.resources.conversations.filters.follow_up_required'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereRaw("coalesce((metadata_json->>'follow_up_required')::boolean, false) = true")),
            ])
            ->recordActions([
                static::makeTakeAction(),
                static::makeOpenCustomerAction(),
                static::makeOpenPurchaseAction(),
                static::makeOpenPaymentAction(),
                static::makeOpenCurrentRaffleAction(),
                static::makeOpenLastWhatsappAction(),
                static::makeMarkFollowUpAction(),
                static::makeSoftResetAction(),
                static::makeHardResetAction(),
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.resources.conversations.empty_state.heading'))
            ->emptyStateDescription(__('admin.resources.conversations.empty_state.description'))
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whatsapp()
            ->with([
                'customer',
                'currentRaffle',
                'purchase.latestPayment',
                'payment',
                'reservation',
                'lastInboundMessage',
                'lastOutboundMessage',
                'assignedUser:id,name',
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ConversationState::query()->whatsapp()->awaitingAgent()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('admin.resources.conversations.filters.awaiting_agent');
    }

    public static function handoffLabel(ConversationState $record): string
    {
        return match ($record->handoff_status) {
            ConversationState::HANDOFF_REQUESTED => __('admin.resources.conversations.handoff.requested'),
            ConversationState::HANDOFF_ACTIVE => __('admin.resources.conversations.handoff.active', [
                'agent' => $record->assignedUser?->name ?? '-',
            ]),
            default => __('admin.resources.conversations.handoff.bot'),
        };
    }

    public static function makeTakeAction(): Action
    {
        return Action::make('take_conversation')
            ->label(__('admin.resources.conversations.actions.take'))
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('success')
            ->visible(fn (ConversationState $record): bool => PanelAccess::allows(PanelPermission::ConversationsManage)
                && $record->assigned_user_id !== PanelAccess::user()?->id)
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.conversations.modals.take'))
            ->action(function (ConversationState $record, $livewire): void {
                $user = PanelAccess::user();

                app(HumanHandoffAction::class)->assign($record, $user, $user);

                Notification::make()
                    ->title(__('admin.resources.conversations.notifications.taken'))
                    ->success()
                    ->send();

                $livewire->dispatch('handoff-updated');
            });
    }

    public static function makeAssignAction(): Action
    {
        return Action::make('assign_conversation')
            ->label(__('admin.resources.conversations.actions.assign'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::ConversationsManage))
            ->schema([
                Select::make('agent_id')
                    ->label(__('admin.resources.conversations.fields.agent'))
                    ->options(fn (): array => User::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->filter(fn (User $user): bool => $user->hasPanelPermission(PanelPermission::ConversationsManage))
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (ConversationState $record, array $data, $livewire): void {
                $agent = User::query()->findOrFail($data['agent_id']);
                abort_unless($agent->hasPanelPermission(PanelPermission::ConversationsManage), 422);

                app(HumanHandoffAction::class)->assign($record, $agent, PanelAccess::user());

                Notification::make()
                    ->title(__('admin.resources.conversations.notifications.assigned', ['agent' => $agent->name]))
                    ->success()
                    ->send();

                $livewire->dispatch('handoff-updated');
            });
    }

    public static function makeReleaseAction(): Action
    {
        return Action::make('release_to_bot')
            ->label(__('admin.resources.conversations.actions.release'))
            ->icon(Heroicon::OutlinedCpuChip)
            ->color('gray')
            ->visible(fn (ConversationState $record): bool => PanelAccess::allows(PanelPermission::ConversationsManage)
                && $record->isHandledByHuman())
            ->schema([
                Toggle::make('notify_customer')
                    ->label(__('admin.resources.conversations.fields.notify_customer'))
                    ->default(true),
            ])
            ->modalDescription(__('admin.resources.conversations.modals.release'))
            ->action(function (ConversationState $record, array $data, $livewire): void {
                app(HumanHandoffAction::class)->release($record, PanelAccess::user(), (bool) ($data['notify_customer'] ?? true));

                Notification::make()
                    ->title(__('admin.resources.conversations.notifications.released'))
                    ->success()
                    ->send();

                $livewire->dispatch('handoff-updated');
            });
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::ConversationsView);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function makeOpenCustomerAction(): Action
    {
        return Action::make('open_customer')
            ->label(__('admin.resources.conversations.actions.open_customer'))
            ->icon(Heroicon::OutlinedUsers)
            ->color('success')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::CustomersView))
            ->disabled(fn (ConversationState $record): bool => $record->customer === null)
            ->url(
                fn (ConversationState $record): ?string => $record->customer !== null
                    ? CustomerResource::getUrl('view', ['record' => $record->customer])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenPurchaseAction(): Action
    {
        return Action::make('open_purchase')
            ->label(__('admin.resources.conversations.actions.open_purchase'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(Color::Amber)
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PurchasesView))
            ->disabled(fn (ConversationState $record): bool => $record->purchase === null)
            ->url(
                fn (ConversationState $record): ?string => $record->purchase !== null
                    ? PurchaseResource::getUrl('view', ['record' => $record->purchase])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenPaymentAction(): Action
    {
        return Action::make('open_payment')
            ->label(__('admin.resources.conversations.actions.open_payment'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('warning')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::PaymentsView))
            ->disabled(fn (ConversationState $record): bool => $record->payment === null)
            ->url(
                fn (ConversationState $record): ?string => $record->payment !== null
                    ? PaymentResource::getUrl('view', ['record' => $record->payment])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenCurrentRaffleAction(): Action
    {
        return Action::make('open_raffle')
            ->label(__('admin.resources.conversations.actions.open_raffle'))
            ->icon(Heroicon::OutlinedTrophy)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::RafflesView))
            ->disabled(fn (ConversationState $record): bool => $record->currentRaffle === null)
            ->url(
                fn (ConversationState $record): ?string => $record->currentRaffle !== null
                    ? RaffleResource::getUrl('view', ['record' => $record->currentRaffle])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeOpenLastWhatsappAction(): Action
    {
        return Action::make('open_last_whatsapp')
            ->label(__('admin.resources.conversations.actions.open_whatsapp_message'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::WhatsappMessagesView))
            ->disabled(fn (ConversationState $record): bool => static::latestMessageId($record) === null)
            ->url(
                fn (ConversationState $record): ?string => ($messageId = static::latestMessageId($record)) !== null
                    ? WhatsappMessageResource::getUrl('view', ['record' => $messageId])
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    public static function makeMarkFollowUpAction(): Action
    {
        return Action::make('mark_follow_up')
            ->label(__('admin.resources.conversations.actions.mark_follow_up'))
            ->icon(Heroicon::OutlinedBookmarkSquare)
            ->color('warning')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::ConversationsManage))
            ->schema([
                Textarea::make('note')
                    ->label(__('admin.resources.conversations.fields.follow_up_note'))
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->action(function (ConversationState $record, array $data): void {
                $before = app(RecordAdminAuditAction::class)->snapshot($record);
                $metadata = $record->metadata_json ?? [];

                $record->forceFill([
                    'metadata_json' => array_merge($metadata, [
                        'follow_up_required' => true,
                        'follow_up_note' => (string) $data['note'],
                        'follow_up_marked_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                $actor = Auth::user();

                app(RecordAdminAuditAction::class)->execute(
                    event: 'conversation.follow_up_marked',
                    action: 'mark_follow_up',
                    auditable: $record,
                    before: $before,
                    after: app(RecordAdminAuditAction::class)->snapshot($record->fresh()),
                    context: [
                        'note' => (string) $data['note'],
                    ],
                    user: $actor instanceof User ? $actor : null,
                );

                Notification::make()
                    ->title(__('admin.resources.conversations.notifications.follow_up_marked'))
                    ->success()
                    ->send();
            });
    }

    public static function makeSoftResetAction(): Action
    {
        return Action::make('soft_reset')
            ->label(__('admin.resources.conversations.actions.soft_reset'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::ConversationsManage))
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin.resources.payments.fields.reason'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.conversations.modals.soft_reset'))
            ->action(function (ConversationState $record, array $data): void {
                try {
                    $actor = Auth::user();

                    app(SoftResetConversationAction::class)->execute(
                        $record,
                        filled($data['reason'] ?? null) ? (string) $data['reason'] : null,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.conversations.notifications.soft_reset_done'))
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

    public static function makeHardResetAction(): Action
    {
        return Action::make('hard_reset')
            ->label(__('admin.resources.conversations.actions.hard_reset'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::ConversationsManage))
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin.resources.payments.fields.reason'))
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.conversations.modals.hard_reset'))
            ->action(function (ConversationState $record, array $data): void {
                try {
                    $actor = Auth::user();

                    app(HardResetConversationAction::class)->execute(
                        $record,
                        (string) $data['reason'],
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.conversations.notifications.hard_reset_done'))
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

    /**
     * @return array<string, string>
     */
    protected static function conversationStatusOptions(): array
    {
        return collect([
            'main_menu',
            'purchase_select_raffle',
            'purchase_enter_quantity',
            'purchase_choose_mode',
            'purchase_select_numbers',
            'purchase_payment_instructions',
            'purchase_rejected',
            'purchase_paid',
            'purchase_under_review',
        ])->mapWithKeys(fn (string $status): array => [$status => OperationsUi::conversationStatusLabel($status)])->all();
    }

    protected static function latestMessageId(ConversationState $record): ?int
    {
        return max(
            (int) ($record->last_inbound_message_id ?? 0),
            (int) ($record->last_outbound_message_id ?? 0),
        ) ?: null;
    }
}
