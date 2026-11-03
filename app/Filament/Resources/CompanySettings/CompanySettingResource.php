<?php

namespace App\Filament\Resources\CompanySettings;

use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\CompanySettings\Pages\CreateCompanySetting;
use App\Filament\Resources\CompanySettings\Pages\EditCompanySetting;
use App\Filament\Resources\CompanySettings\Pages\ListCompanySettings;
use App\Filament\Support\PanelAccess;
use App\Models\CompanySetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CompanySettingResource extends BaseResource
{
    protected static ?string $model = CompanySetting::class;

    protected static ?string $translationKey = 'company_settings';

    protected static ?string $navigationGroupTranslationKey = 'admin.navigation.groups.configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Configuración de empresa';

    protected static ?string $modelLabel = 'Configuración de empresa';

    protected static ?string $pluralModelLabel = 'Configuración de empresa';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Empresa')
                    ->columns(2)
                    ->schema([
                        TextInput::make('trade_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('legal_name')
                            ->maxLength(255),
                        TextInput::make('tax_id')
                            ->maxLength(255),
                        TextInput::make('whatsapp_bot_phone')
                            ->label('Teléfono del bot de WhatsApp')
                            ->required()
                            ->tel()
                            ->maxLength(255)
                            ->regex('/^\+?[0-9]{8,20}$/')
                            ->helperText('Número al que la página pública envía a los compradores para seguir la compra con el bot de WhatsApp.'),
                        TextInput::make('support_phone')
                            ->required()
                            ->tel()
                            ->maxLength(255)
                            ->regex('/^\+?[0-9]{8,20}$/')
                            ->helperText('Teléfono de soporte para ayuda manual y avisos internos de comprobantes de pago.'),
                        TextInput::make('support_email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('website_url')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('terms_url')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('privacy_policy_url')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('currency_code')
                            ->required()
                            ->length(3)
                            ->ascii()
                            ->alpha()
                            ->regex('/^[A-Z]{3}$/'),
                        Select::make('default_locale')
                            ->options(User::supportedPanelLocaleOptions())
                            ->required()
                            ->default('es'),
                        Select::make('timezone')
                            ->options([
                                'America/Bogota' => 'America/Bogota',
                            ])
                            ->required()
                            ->default('America/Bogota'),
                    ]),
                Section::make('Marca')
                    ->columns(3)
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('company-settings/logos')
                            ->image()
                            ->imageEditor()
                            ->maxSize(4096)
                            ->helperText('Sube el logo de la empresa. Si lo reemplazas, la imagen anterior se borra.')
                            ->columnSpanFull(),
                        ColorPicker::make('primary_color'),
                        ColorPicker::make('secondary_color'),
                        ColorPicker::make('accent_color'),
                    ]),
                Section::make('Soporte')
                    ->schema([
                        Textarea::make('help_message')
                            ->required()
                            ->rows(5)
                            ->helperText('Texto corto de ayuda que se usa en WhatsApp y en los puntos de soporte.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->square()
                    ->imageSize(48)
                    ->toggleable(),
                TextColumn::make('trade_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('support_phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('whatsapp_bot_phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('support_email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('timezone')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('La empresa aún no está configurada')
            ->emptyStateDescription('Crea el perfil de la empresa que se usa en los boletos, la marca y los textos de soporte.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanySettings::route('/'),
            'create' => CreateCompanySetting::route('/create'),
            'edit' => EditCompanySetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return PanelAccess::allows(PanelPermission::CompanySettingsManage)
            && CompanySetting::query()->count() === 0;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::CompanySettingsManage);
    }

    public static function canEdit(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::CompanySettingsManage) && $record instanceof CompanySetting;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
