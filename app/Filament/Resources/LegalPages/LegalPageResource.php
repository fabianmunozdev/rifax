<?php

namespace App\Filament\Resources\LegalPages;

use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\LegalPages\Pages\EditLegalPage;
use App\Filament\Resources\LegalPages\Pages\ListLegalPages;
use App\Filament\Support\PanelAccess;
use App\Models\LegalPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LegalPageResource extends BaseResource
{
    protected static ?string $model = LegalPage::class;

    protected static ?string $translationKey = 'legal_pages';

    protected static ?string $navigationGroupTranslationKey = 'admin.navigation.groups.configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Páginas legales';

    protected static ?string $modelLabel = 'página legal';

    protected static ?string $pluralModelLabel = 'páginas legales';

    public static function form(Schema $schema): Schema
    {
        $placeholders = collect(LegalPage::PLACEHOLDERS)
            ->map(fn (string $label, string $token): string => $token.' = '.$label)
            ->implode(' · ');

        return $schema
            ->components([
                Section::make('Página')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Dirección')
                            ->prefix(url('/').'/')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Es fija para que los enlaces del sitio y del bot no se rompan.'),
                        Textarea::make('summary')
                            ->label('Resumen para buscadores')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label('Publicada')
                            ->helperText('Si la desactivas, la página deja de verse y el enlace del pie de página desaparece.'),
                        RichEditor::make('body')
                            ->label('Texto')
                            ->required()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('Puedes escribir estos datos entre llaves y se llenan solos con la Configuración de empresa: '.$placeholders.'.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Dirección')
                    ->formatStateUsing(fn (string $state): string => '/'.$state),
                IconColumn::make('is_published')
                    ->label('Publicada')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updater.name')
                    ->label('Editada por')
                    ->placeholder('—'),
            ])
            ->defaultSort('slug', 'desc')
            ->recordActions([
                Action::make('view_public')
                    ->label('Ver página')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (LegalPage $record): string => $record->publicUrl(), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalPages::route('/'),
            'edit' => EditLegalPage::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::CompanySettingsManage);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::CompanySettingsManage) && $record instanceof LegalPage;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
