<?php

namespace App\Filament\Resources\Raffles;

use App\Actions\Raffles\ProvisionRaffleNumbersAction;
use App\Actions\Raffles\PublishRaffleResultAction;
use App\Actions\WhatsApp\SendRaffleDrawReminderWhatsappAction;
use App\Actions\WhatsApp\SendUpcomingRaffleAnnouncementWhatsappAction;
use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Raffles\Pages\CreateRaffle;
use App\Filament\Resources\Raffles\Pages\EditRaffle;
use App\Filament\Resources\Raffles\Pages\ListRaffles;
use App\Filament\Resources\Raffles\Pages\ViewRaffle;
use App\Filament\Support\PanelAccess;
use App\Models\Raffle;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class RaffleResource extends BaseResource
{
    protected static ?string $model = Raffle::class;

    protected static ?string $translationKey = 'raffles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGiftTop;

    protected static ?string $navigationLabel = 'Raffles';

    protected static ?string $modelLabel = 'Raffle';

    protected static ?string $pluralModelLabel = 'Raffles';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.raffles.sections.raffle'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('admin.resources.raffles.fields.title'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('admin.resources.raffles.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label(__('admin.resources.raffles.fields.description'))
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label(__('admin.resources.raffles.fields.status'))
                            ->options(static::raffleStatusOptions(includeClosed: false))
                            ->default('draft')
                            ->required()
                            ->hiddenOn('create'),
                        Toggle::make('is_featured')
                            ->label(__('admin.resources.raffles.fields.featured_on_landing'))
                            ->helperText(__('admin.resources.raffles.help.featured_on_landing'))
                            ->inline(false),
                        TextInput::make('price_per_number')
                            ->label(__('admin.resources.raffles.fields.price_per_number'))
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        TextInput::make('number_digits')
                            ->label(__('admin.resources.raffles.fields.number_digits'))
                            ->numeric()
                            ->required()
                            ->default(4)
                            ->minValue(Raffle::MIN_SUPPORTED_NUMBER_DIGITS)
                            ->maxValue(Raffle::MAX_SUPPORTED_NUMBER_DIGITS)
                            ->disabled(fn (?Raffle $record): bool => $record?->numbers()->exists() === true)
                            ->helperText(__('admin.resources.raffles.help.number_digits')),
                        TextInput::make('min_numbers_per_purchase')
                            ->label(__('admin.resources.raffles.fields.min_numbers_per_purchase'))
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1),
                        Toggle::make('random_selection_by_blocks')
                            ->label(__('admin.resources.raffles.fields.random_selection_by_blocks'))
                            ->helperText(__('admin.resources.raffles.help.random_selection_by_blocks'))
                            ->inline(false),
                        TextInput::make('reservation_timeout_minutes')
                            ->label(__('admin.resources.raffles.fields.reservation_timeout_minutes'))
                            ->numeric()
                            ->required()
                            ->default(15)
                            ->minValue(1),
                        FileUpload::make('cover_image_path')
                            ->label(__('admin.resources.raffles.fields.cover_image_path'))
                            ->disk('public')
                            ->directory('raffles/covers')
                            ->image()
                            ->imageEditor()
                            ->maxSize(4096)
                            ->helperText('Upload the raffle cover image. Replacing the file removes the previous image automatically.'),
                    ]),
                Section::make(__('admin.resources.raffles.sections.lottery'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('lottery_name')
                            ->label(__('admin.resources.raffles.fields.lottery'))
                            ->maxLength(255),
                        TextInput::make('lottery_draw_number')
                            ->label(__('admin.resources.raffles.fields.draw_number'))
                            ->maxLength(255),
                        DatePicker::make('draw_date')
                            ->label(__('admin.resources.raffles.fields.draw_date')),
                        TimePicker::make('draw_time')
                            ->label(__('admin.resources.raffles.fields.draw_time'))
                            ->seconds(false),
                        TextInput::make('lottery_reference_url')
                            ->label(__('admin.resources.raffles.fields.reference_url'))
                            ->url()
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.raffles.sections.raffle'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('status')
                            ->label(__('admin.resources.raffles.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::raffleStatusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),
                        TextEntry::make('is_featured')
                            ->label(__('admin.resources.raffles.fields.featured'))
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state
                                ? __('admin.resources.raffles.featured_states.yes')
                                : __('admin.resources.raffles.featured_states.no'))
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                        TextEntry::make('title')
                            ->label(__('admin.resources.raffles.fields.title')),
                        TextEntry::make('slug')
                            ->label(__('admin.resources.raffles.fields.slug'))
                            ->copyable(),
                        TextEntry::make('description')
                            ->label(__('admin.resources.raffles.fields.description'))
                            ->columnSpanFull()
                            ->placeholder('-'),
                        ImageEntry::make('cover_image_path')
                            ->label(__('admin.resources.raffles.fields.cover_image_path'))
                            ->disk('public')
                            ->imageHeight('220px')
                            ->columnSpanFull(),
                        TextEntry::make('price_per_number')
                            ->label(__('admin.resources.raffles.fields.price_per_number'))
                            ->money('COP'),
                        TextEntry::make('number_digits')
                            ->label(__('admin.resources.raffles.fields.number_digits')),
                        TextEntry::make('min_numbers_per_purchase')
                            ->label(__('admin.resources.raffles.fields.min_numbers_per_purchase')),
                        TextEntry::make('random_selection_by_blocks')
                            ->label(__('admin.resources.raffles.fields.random_selection_by_blocks'))
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state
                                ? __('admin.common.yes')
                                : __('admin.common.no'))
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('reservation_timeout_minutes')
                            ->label(__('admin.resources.raffles.fields.reservation_timeout_minutes')),
                        TextEntry::make('cover_image_path')
                            ->label(__('admin.resources.raffles.fields.cover_image_path'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('created_at')
                            ->label(__('admin.resources.raffles.fields.created_at'))
                            ->dateTime(),
                    ]),
                Section::make(__('admin.resources.raffles.sections.lottery'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lottery_name')
                            ->label(__('admin.resources.raffles.fields.lottery'))
                            ->placeholder('-'),
                        TextEntry::make('lottery_draw_number')
                            ->label(__('admin.resources.raffles.fields.draw_number'))
                            ->placeholder('-'),
                        TextEntry::make('draw_date')
                            ->label(__('admin.resources.raffles.fields.draw_date'))
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('draw_time')
                            ->label(__('admin.resources.raffles.fields.draw_time'))
                            ->placeholder('-'),
                        TextEntry::make('lottery_reference_url')
                            ->label(__('admin.resources.raffles.fields.reference_url'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('result_number')
                            ->label(__('admin.resources.raffles.fields.official_result'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('result_published_at')
                            ->label(__('admin.resources.raffles.fields.result_published_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('winnerNumber.number')
                            ->label(__('admin.resources.raffles.fields.winner_number'))
                            ->placeholder('-'),
                    ]),
                Section::make(__('admin.resources.raffles.sections.operations'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('numbers_count')
                            ->label(__('admin.resources.raffles.fields.total_numbers')),
                        TextEntry::make('available_numbers_count')
                            ->label(__('admin.resources.raffles.fields.available_numbers')),
                        TextEntry::make('reserved_numbers_count')
                            ->label(__('admin.resources.raffles.fields.reserved_numbers')),
                        TextEntry::make('paid_numbers_count')
                            ->label(__('admin.resources.raffles.fields.paid_numbers')),
                        TextEntry::make('purchases_count')
                            ->label(__('admin.resources.raffles.fields.purchases')),
                        TextEntry::make('pending_purchases_count')
                            ->label(__('admin.resources.raffles.fields.pending_purchases'))
                            ->placeholder('0'),
                        TextEntry::make('paid_purchases_count')
                            ->label(__('admin.resources.raffles.fields.paid_purchases')),
                        TextEntry::make('winnerNumber.purchaseNumber.purchase.customer.phone')
                            ->label(__('admin.resources.raffles.fields.winner_customer_phone'))
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
                ImageColumn::make('cover_image_path')
                    ->label('Cover')
                    ->disk('public')
                    ->square()
                    ->imageSize(52)
                    ->toggleable(),
                TextColumn::make('title')
                    ->label(__('admin.resources.raffles.fields.title'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('admin.resources.raffles.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::raffleStatusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state)),
                TextColumn::make('is_featured')
                    ->label(__('admin.resources.raffles.fields.featured'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.resources.raffles.featured_states.featured')
                        : __('admin.resources.raffles.featured_states.standard'))
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('draw_date')
                    ->label(__('admin.resources.raffles.fields.draw_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('number_digits')
                    ->label(__('admin.resources.raffles.fields.digits'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('random_selection_by_blocks')
                    ->label(__('admin.resources.raffles.fields.random_selection_by_blocks_short'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.common.yes')
                        : __('admin.common.no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('draw_time')
                    ->label(__('admin.resources.raffles.fields.draw_time'))
                    ->toggleable(),
                TextColumn::make('result_number')
                    ->label(__('admin.resources.raffles.fields.result'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('winnerNumber.number')
                    ->label(__('admin.resources.raffles.fields.winner'))
                    ->placeholder('-'),
                TextColumn::make('numbers_count')
                    ->label(__('admin.resources.raffles.fields.numbers'))
                    ->sortable(),
                TextColumn::make('paid_numbers_count')
                    ->label(__('admin.resources.raffles.fields.paid'))
                    ->sortable(),
                TextColumn::make('purchases_count')
                    ->label(__('admin.resources.raffles.fields.purchases'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('pending_purchases_count')
                    ->label(__('admin.resources.raffles.fields.pending_purchases_short'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_purchases_count')
                    ->label(__('admin.resources.raffles.fields.paid_purchases'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('result_published_at')
                    ->label(__('admin.resources.raffles.fields.result_published_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('is_featured', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(static::raffleStatusOptions()),
                Filter::make('pending_result')
                    ->label(__('admin.resources.raffles.filters.pending_result'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'published')
                        ->whereNull('result_published_at'))
                    ->indicator(__('admin.resources.raffles.filters.pending_result')),
                Filter::make('with_result')
                    ->label(__('admin.resources.raffles.filters.with_result'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('result_published_at'))
                    ->indicator(__('admin.resources.raffles.filters.with_result')),
            ])
            ->recordActions([
                static::makeProvisionNumbersAction(),
                static::makeSendDrawReminderAction(),
                static::makeSendUpcomingAnnouncementAction(),
                static::makePublishResultAction(),
                EditAction::make(),
                DeleteAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRaffles::route('/'),
            'create' => CreateRaffle::route('/create'),
            'view' => ViewRaffle::route('/{record}'),
            'edit' => EditRaffle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'winnerNumber.purchaseNumber.purchase.customer',
            ])
            ->withCount([
                'numbers',
                'numbers as available_numbers_count' => fn (Builder $query): Builder => $query->where('status', 'available'),
                'numbers as reserved_numbers_count' => fn (Builder $query): Builder => $query->where('status', 'reserved'),
                'numbers as paid_numbers_count' => fn (Builder $query): Builder => $query->whereIn('status', ['paid', 'winner']),
                'purchases',
                'purchases as pending_purchases_count' => fn (Builder $query): Builder => $query->whereIn('status', ['reserved', 'payment_submitted', 'under_review', 'rejected']),
                'purchases as paid_purchases_count' => fn (Builder $query): Builder => $query->where('status', 'paid'),
            ]);
    }

    public static function canCreate(): bool
    {
        return PanelAccess::allows(PanelPermission::RafflesManage);
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::RafflesView);
    }

    public static function canEdit(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::RafflesManage)
            && $record instanceof Raffle
            ? $record->status !== 'closed' && $record->result_published_at === null
            : false;
    }

    public static function canDelete(mixed $record): bool
    {
        return $record instanceof Raffle ? static::canDeleteRecord($record) : false;
    }

    public static function makeProvisionNumbersAction(): Action
    {
        return Action::make('provision_numbers')
            ->label(__('admin.resources.raffles.actions.generate_full_catalog'))
            ->icon(Heroicon::OutlinedQueueList)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::RafflesManage))
            ->disabled(fn (Raffle $record): bool => ! static::canProvisionNumberCatalog($record))
            ->requiresConfirmation()
            ->modalDescription(fn (Raffle $record): string => __('admin.resources.raffles.modals.generate_full_catalog', [
                'digits' => $record->normalizedNumberDigits(),
                'count' => $record->expectedNumberCatalogCount(),
            ]))
            ->action(function (Raffle $record): void {
                try {
                    $createdCount = app(ProvisionRaffleNumbersAction::class)->execute($record);

                    Notification::make()
                        ->title(__('admin.resources.raffles.notifications.numbers_provisioned'))
                        ->body($createdCount > 0
                            ? __('admin.resources.raffles.notifications.numbers_added', ['count' => $createdCount])
                            : __('admin.resources.raffles.notifications.numbers_already_exist'))
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

    public static function makePublishResultAction(): Action
    {
        return Action::make('publish_result')
            ->label(__('admin.resources.raffles.actions.publish_result'))
            ->icon(Heroicon::OutlinedTrophy)
            ->color('success')
            ->visible(fn (): bool => PanelAccess::allows(PanelPermission::RafflesManage))
            ->disabled(fn (Raffle $record): bool => $record->status !== 'published'
                || $record->result_published_at !== null
                || $record->purchases()->whereIn('status', ['reserved', 'payment_submitted', 'under_review', 'rejected'])->exists())
            ->schema([
                TextInput::make('result_number')
                    ->label(__('admin.resources.raffles.fields.official_result_number'))
                    ->required()
                    ->maxLength(32)
                    ->helperText(__('admin.resources.raffles.help.official_result_number')),
            ])
            ->modalDescription(__('admin.resources.raffles.modals.publish_result'))
            ->action(function (Raffle $record, array $data): void {
                try {
                    $actor = Auth::user();

                    app(PublishRaffleResultAction::class)->execute(
                        $record,
                        (string) $data['result_number'],
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.raffles.notifications.result_published'))
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

    public static function makeSendDrawReminderAction(): Action
    {
        return Action::make('send_draw_reminder')
            ->label(__('admin.resources.raffles.actions.send_draw_reminder'))
            ->icon(Heroicon::OutlinedBellAlert)
            ->color('info')
            ->visible(fn (): bool => PanelAccess::allowsAny([
                PanelPermission::RafflesManage,
                PanelPermission::WhatsappMessagesManage,
            ]))
            ->disabled(fn (Raffle $record): bool => $record->status !== 'published' || $record->result_published_at !== null)
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.raffles.modals.send_draw_reminder'))
            ->action(function (Raffle $record): void {
                try {
                    $actor = Auth::user();
                    $result = app(SendRaffleDrawReminderWhatsappAction::class)->execute(
                        $record,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.raffles.notifications.draw_reminder_processed'))
                        ->body(__('admin.resources.raffles.notifications.queue_summary', [
                            'queued' => $result['queued'],
                            'skipped' => $result['skipped'],
                        ]))
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

    public static function makeSendUpcomingAnnouncementAction(): Action
    {
        return Action::make('send_upcoming_raffle_announcement')
            ->label(__('admin.resources.raffles.actions.announce_upcoming_raffle'))
            ->icon(Heroicon::OutlinedMegaphone)
            ->color('success')
            ->visible(fn (): bool => PanelAccess::allowsAny([
                PanelPermission::RafflesManage,
                PanelPermission::WhatsappMessagesManage,
            ]))
            ->disabled(fn (Raffle $record): bool => $record->status !== 'published' || $record->result_published_at !== null)
            ->requiresConfirmation()
            ->modalDescription(__('admin.resources.raffles.modals.announce_upcoming_raffle'))
            ->action(function (Raffle $record): void {
                try {
                    $actor = Auth::user();
                    $result = app(SendUpcomingRaffleAnnouncementWhatsappAction::class)->execute(
                        $record,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title(__('admin.resources.raffles.notifications.upcoming_announcement_processed'))
                        ->body(__('admin.resources.raffles.notifications.queue_summary', [
                            'queued' => $result['queued'],
                            'skipped' => $result['skipped'],
                        ]))
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

    protected static function statusColor(?string $state): string
    {
        return match ($state) {
            'published' => 'success',
            'closed' => 'warning',
            'cancelled' => 'danger',
            'draft' => 'gray',
            default => 'gray',
        };
    }

    protected static function canProvisionNumberCatalog(Raffle $record): bool
    {
        return $record->status === 'draft'
            && $record->result_published_at === null
            && ! static::hasRaffleActivity($record);
    }

    protected static function canDeleteRecord(Raffle $record): bool
    {
        return $record->status === 'draft'
            && $record->result_published_at === null
            && ! static::hasRaffleActivity($record);
    }

    protected static function hasRaffleActivity(Raffle $record): bool
    {
        return $record->purchases()->exists()
            || $record->reservations()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected static function raffleStatusOptions(bool $includeClosed = true): array
    {
        $options = [
            'draft' => __('admin.resources.raffles.statuses.draft'),
            'published' => __('admin.resources.raffles.statuses.published'),
            'cancelled' => __('admin.resources.raffles.statuses.cancelled'),
        ];

        if ($includeClosed) {
            $options = [
                'draft' => __('admin.resources.raffles.statuses.draft'),
                'published' => __('admin.resources.raffles.statuses.published'),
                'closed' => __('admin.resources.raffles.statuses.closed'),
                'cancelled' => __('admin.resources.raffles.statuses.cancelled'),
            ];
        }

        return $options;
    }

    protected static function raffleStatusLabel(?string $state): string
    {
        return match ($state) {
            'draft' => __('admin.resources.raffles.statuses.draft'),
            'published' => __('admin.resources.raffles.statuses.published'),
            'closed' => __('admin.resources.raffles.statuses.closed'),
            'cancelled' => __('admin.resources.raffles.statuses.cancelled'),
            default => filled($state) ? str($state)->replace('_', ' ')->title()->toString() : __('admin.resources.raffles.statuses.draft'),
        };
    }
}
