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

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Métodos de pago';

    protected static ?string $modelLabel = 'Método de pago';

    protected static ?string $pluralModelLabel = 'Métodos de pago';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Método de pago')
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
                            ->helperText('Clave interna fija; no la cambies después de crearla.'),
                        Select::make('status')
                            ->options([
                                'active' => 'Activo',
                                'inactive' => 'Inactivo',
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
                            ->label('Visible al comprar')
                            ->default(true)
                            ->disabled(fn (Get $get): bool => $get('status') !== 'active')
                            ->dehydrated()
                            ->helperText(fn (Get $get): string => $get('status') === 'active'
                                ? 'Los métodos ocultos se conservan, pero no se ofrecen al comprador.'
                                : 'Los métodos inactivos se ocultan automáticamente a los compradores.'),
                        TextInput::make('account_holder')
                            ->maxLength(255),
                        TextInput::make('account_reference')
                            ->maxLength(255),
                        Textarea::make('instructions')
                            ->requiredIf('status', 'active')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Obligatorio en métodos activos, para que el comprador sepa cómo pagar.'),
                        KeyValue::make('details_json')
                            ->label('Datos adicionales')
                            ->columnSpanFull()
                            ->reorderable()
                            ->helperText('Datos opcionales como banco, billetera, tipo de cuenta o teléfono.'),
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
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'Activo' : 'Inactivo')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
                TextColumn::make('account_reference')
                    ->label('Referencia')
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
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                    ]),
                TernaryFilter::make('is_visible')
                    ->label('Visibilidad'),
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
