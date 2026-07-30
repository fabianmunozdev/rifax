<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Support\PanelAccess;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaymentMethodResource extends BaseResource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $translationKey = 'payment_methods';

    protected static ?string $navigationGroupTranslationKey = 'admin.navigation.groups.configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Payment Methods';

    protected static ?string $modelLabel = 'Payment method';

    protected static ?string $pluralModelLabel = 'Payment Methods';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Method')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->ascii()
                            ->unique(ignoreRecord: true)
                            ->helperText('Stable machine key used across flows and seeds.'),
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->required()
                            ->default('active')
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== 'active') {
                                    $set('is_visible', false);
                                }
                            }),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0),
                        Toggle::make('is_visible')
                            ->label('Visible in purchase flow')
                            ->default(true)
                            ->disabled(fn (Get $get): bool => $get('status') !== 'active')
                            ->dehydrated()
                            ->helperText(fn (Get $get): string => $get('status') === 'active'
                                ? 'Hidden methods stay configured but are not offered to buyers.'
                                : 'Inactive methods are automatically hidden from buyers.'),
                        TextInput::make('account_holder')
                            ->maxLength(255),
                        TextInput::make('account_reference')
                            ->maxLength(255),
                        Textarea::make('instructions')
                            ->requiredIf('status', 'active')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Required for active methods so buyers receive actionable payment guidance.'),
                        KeyValue::make('details_json')
                            ->label('Structured details')
                            ->columnSpanFull()
                            ->reorderable()
                            ->helperText('Optional metadata such as bank, wallet, account type or phone.'),
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
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
                TextColumn::make('account_reference')
                    ->label('Reference')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
                TernaryFilter::make('is_visible')
                    ->label('Visibility'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::PaymentMethodsManage);
    }

    public static function canCreate(): bool
    {
        return PanelAccess::allows(PanelPermission::PaymentMethodsManage);
    }

    public static function canEdit(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::PaymentMethodsManage) && $record instanceof PaymentMethod;
    }

    public static function canDelete(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::PaymentMethodsManage) && $record instanceof PaymentMethod;
    }
}
