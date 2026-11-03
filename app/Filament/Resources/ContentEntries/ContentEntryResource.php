<?php

namespace App\Filament\Resources\ContentEntries;

use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ContentEntries\Pages\CreateContentEntry;
use App\Filament\Resources\ContentEntries\Pages\EditContentEntry;
use App\Filament\Resources\ContentEntries\Pages\ListContentEntries;
use App\Filament\Support\PanelAccess;
use App\Models\ContentEntry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ContentEntryResource extends BaseResource
{
    protected static ?string $model = ContentEntry::class;

    protected static ?string $translationKey = 'content_entries';

    protected static ?string $navigationGroupTranslationKey = 'admin.navigation.groups.configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Entradas de contenido';

    protected static ?string $modelLabel = 'Entrada de contenido';

    protected static ?string $pluralModelLabel = 'Entradas de contenido';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entrada')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->options([
                                'faq_fixed' => 'Pregunta frecuente',
                                'faq_parametrized' => 'Pregunta frecuente con variables',
                                'system_message' => 'Mensaje del sistema',
                                'support_message' => 'Mensaje de soporte',
                                'template_bridge' => 'Plantilla de WhatsApp',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (! in_array($state, ['faq_fixed', 'faq_parametrized'], true)) {
                                    $set('is_ai_eligible', false);
                                }
                            }),
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->helperText('No puede repetirse en el mismo idioma y canal.'),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('category')
                            ->required()
                            ->maxLength(255),
                        Select::make('locale')
                            ->options([
                                'es' => 'es',
                            ])
                            ->default('es')
                            ->required(),
                        Select::make('channel')
                            ->options([
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->default('whatsapp')
                            ->required(),
                        Select::make('status')
                            ->options([
                                'draft' => 'Borrador',
                                'published' => 'Publicado',
                                'archived' => 'Archivado',
                            ])
                            ->default('draft')
                            ->required(),
                        TextInput::make('trigger_intent')
                            ->required(fn (Get $get): bool => $get('type') === 'template_bridge')
                            ->maxLength(255)
                            ->helperText(fn (Get $get): string => $get('type') === 'template_bridge'
                                ? 'Obligatorio en plantillas que se activan por intención de WhatsApp.'
                                : 'Intención del flujo de WhatsApp que muestra esta entrada.'),
                        TextInput::make('priority')
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->minValue(0),
                        Toggle::make('is_ai_eligible')
                            ->label('Disponible para IA')
                            ->default(false)
                            ->disabled(fn (Get $get): bool => ! in_array($get('type'), ['faq_fixed', 'faq_parametrized'], true))
                            ->dehydrated(),
                        Toggle::make('is_public')
                            ->label('Visible en la página pública')
                            ->default(false)
                            ->helperText('Actívalo solo en preguntas frecuentes que deben verse en la página pública.')
                            ->disabled(fn (Get $get): bool => ! in_array($get('type'), ['faq_fixed', 'faq_parametrized'], true))
                            ->dehydrated(),
                        DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->helperText('Si el estado es Publicado y este campo está vacío, se guarda la fecha y hora actual.'),
                        Textarea::make('body_text')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                        KeyValue::make('variables_json')
                            ->label('Variables / configuración')
                            ->columnSpanFull()
                            ->reorderable()
                            ->required(fn (Get $get): bool => in_array($get('type'), ['faq_parametrized', 'template_bridge'], true))
                            ->helperText(fn (Get $get): string => in_array($get('type'), ['faq_parametrized', 'template_bridge'], true)
                                ? 'Obligatorio en contenido con variables y plantillas.'
                                : 'En contenido con variables o plantillas, guarda aquí las variables y datos de envío.'),
                        Textarea::make('fallback_text')
                            ->required(fn (Get $get): bool => in_array($get('type'), ['faq_parametrized', 'template_bridge'], true))
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('audience')
                    ->label('Audiencia')
                    ->badge()
                    ->state(fn (ContentEntry $record): string => static::audienceLabel($record))
                    ->color(fn (ContentEntry $record): string => static::audienceColor($record))
                    ->toggleable(),
                TextColumn::make('key')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => [
                        'faq_fixed' => 'Pregunta frecuente',
                        'faq_parametrized' => 'Pregunta frecuente con variables',
                        'system_message' => 'Mensaje del sistema',
                        'support_message' => 'Mensaje de soporte',
                        'template_bridge' => 'Plantilla de WhatsApp',
                    ][$state] ?? (string) $state),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => [
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                        'archived' => 'Archivado',
                    ][$state] ?? (string) $state)
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'archived' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('trigger_intent')
                    ->label('Intención')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('priority')
                    ->sortable(),
                IconColumn::make('is_ai_eligible')
                    ->label('IA')
                    ->boolean(),
                IconColumn::make('is_public')
                    ->label('Pública')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('priority')
            ->filters([
                SelectFilter::make('audience')
                    ->label('Audiencia')
                    ->options([
                        'public_landing' => 'Página pública',
                        'whatsapp_bot' => 'Bot de WhatsApp',
                        'whatsapp_templates' => 'Plantillas de WhatsApp',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === 'public_landing') {
                            return $query->where('is_public', true);
                        }

                        if ($value === 'whatsapp_templates') {
                            return $query->where('type', 'template_bridge');
                        }

                        if ($value === 'whatsapp_bot') {
                            return $query->where('channel', 'whatsapp')->where('type', '!=', 'template_bridge');
                        }

                        return $query;
                    }),
                SelectFilter::make('type')
                    ->options([
                        'faq_fixed' => 'Pregunta frecuente',
                        'faq_parametrized' => 'Pregunta frecuente con variables',
                        'system_message' => 'Mensaje del sistema',
                        'support_message' => 'Mensaje de soporte',
                        'template_bridge' => 'Plantilla de WhatsApp',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                        'archived' => 'Archivado',
                    ]),
                Filter::make('with_trigger_intent')
                    ->label('Con intención')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('trigger_intent')),
                Filter::make('public_entries')
                    ->label('Página pública')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_public', true)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No hay entradas de contenido')
            ->emptyStateDescription('Crea la primera pregunta frecuente, mensaje del sistema o plantilla para el canal de WhatsApp.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentEntries::route('/'),
            'create' => CreateContentEntry::route('/create'),
            'edit' => EditContentEntry::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::ContentEntriesManage);
    }

    public static function canCreate(): bool
    {
        return PanelAccess::allows(PanelPermission::ContentEntriesManage);
    }

    public static function canEdit(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::ContentEntriesManage) && $record instanceof ContentEntry;
    }

    public static function canDelete(mixed $record): bool
    {
        return PanelAccess::allows(PanelPermission::ContentEntriesManage) && $record instanceof ContentEntry;
    }

    protected static function audienceLabel(ContentEntry $record): string
    {
        if ($record->is_public) {
            return 'Página pública';
        }

        if ($record->type === 'template_bridge') {
            return 'Plantillas de WhatsApp';
        }

        return 'Bot de WhatsApp';
    }

    protected static function audienceColor(ContentEntry $record): string
    {
        if ($record->is_public) {
            return 'success';
        }

        if ($record->type === 'template_bridge') {
            return 'info';
        }

        return 'warning';
    }
}
