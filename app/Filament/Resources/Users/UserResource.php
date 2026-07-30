<?php

namespace App\Filament\Resources\Users;

use App\Enums\PanelPermission;
use App\Enums\PanelRole;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Support\PanelAccess;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $translationKey = 'users';

    protected static ?string $navigationGroupTranslationKey = 'admin.navigation.groups.access_control';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.resources.users.section'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('role')
                            ->options(PanelRole::options())
                            ->required(),
                        Select::make('preferred_locale')
                            ->label(__('admin.resources.users.fields.preferred_locale'))
                            ->options(User::supportedPanelLocaleOptions())
                            ->default('es')
                            ->required()
                            ->helperText(__('admin.resources.users.fields.preferred_locale_help')),
                        Toggle::make('is_active')
                            ->label(__('admin.resources.users.fields.active_panel_access'))
                            ->default(true)
                            ->helperText(__('admin.resources.users.fields.active_panel_access_help')),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->maxLength(255)
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->minLength(8)
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PanelRole::tryFrom((string) $state)?->label() ?? 'Unassigned'),
                IconColumn::make('is_active')
                    ->label(__('admin.resources.users.fields.active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->options(PanelRole::options()),
                TernaryFilter::make('is_active')
                    ->label(__('admin.resources.users.fields.active_access')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('No users found')
            ->emptyStateDescription('Create admin, operator, finance or support accounts for the internal panel.')
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::UsersManage);
    }

    public static function canCreate(): bool
    {
        return PanelAccess::allows(PanelPermission::UsersManage);
    }

    public static function canEdit(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::UsersManage) && $record instanceof User;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
