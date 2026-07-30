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

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Content Entries';

    protected static ?string $modelLabel = 'Content entry';

    protected static ?string $pluralModelLabel = 'Content Entries';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entry')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->options([
                                'faq_fixed' => 'FAQ fixed',
                                'faq_parametrized' => 'FAQ parametrized',
                                'system_message' => 'System message',
                                'support_message' => 'Support message',
                                'template_bridge' => 'Template bridge',
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
                            ->helperText('Must be unique per locale and channel.'),
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
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                        TextInput::make('trigger_intent')
                            ->required(fn (Get $get): bool => $get('type') === 'template_bridge')
                            ->maxLength(255)
                            ->helperText(fn (Get $get): string => $get('type') === 'template_bridge'
                                ? 'Required for template bridges resolved by WhatsApp intent.'
                                : 'Use the intent consumed by the WhatsApp flow when this entry should resolve by intent.'),
                        TextInput::make('priority')
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->minValue(0),
                        Toggle::make('is_ai_eligible')
                            ->label('AI eligible')
                            ->default(false)
                            ->disabled(fn (Get $get): bool => ! in_array($get('type'), ['faq_fixed', 'faq_parametrized'], true))
                            ->dehydrated(),
                        Toggle::make('is_public')
                            ->label('Visible on public landing')
                            ->default(false)
                            ->helperText('Use this only for FAQ entries that should appear publicly on the landing page.')
                            ->disabled(fn (Get $get): bool => ! in_array($get('type'), ['faq_fixed', 'faq_parametrized'], true))
                            ->dehydrated(),
                        DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->helperText('If status is published and this field is empty, the current time is stored automatically.'),
                        Textarea::make('body_text')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                        KeyValue::make('variables_json')
                            ->label('Variables / config')
                            ->columnSpanFull()
                            ->reorderable()
                            ->required(fn (Get $get): bool => in_array($get('type'), ['faq_parametrized', 'template_bridge'], true))
                            ->helperText(fn (Get $get): string => in_array($get('type'), ['faq_parametrized', 'template_bridge'], true)
                                ? 'Required for parametrized content and template bridges.'
                                : 'For parametrized content or template bridges, store variables and delivery metadata here.'),
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
                    ->label('Audience')
                    ->badge()
                    ->state(fn (ContentEntry $record): string => static::audienceLabel($record))
                    ->color(fn (ContentEntry $record): string => static::audienceColor($record))
                    ->toggleable(),
                TextColumn::make('key')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'archived' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('trigger_intent')
                    ->label('Intent')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('priority')
                    ->sortable(),
                IconColumn::make('is_ai_eligible')
                    ->label('AI')
                    ->boolean(),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('priority')
            ->filters([
                SelectFilter::make('audience')
                    ->label('Audience')
                    ->options([
                        'public_landing' => 'Public landing',
                        'whatsapp_bot' => 'WhatsApp bot',
                        'whatsapp_templates' => 'WhatsApp templates',
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
                        'faq_fixed' => 'FAQ fixed',
                        'faq_parametrized' => 'FAQ parametrized',
                        'system_message' => 'System message',
                        'support_message' => 'Support message',
                        'template_bridge' => 'Template bridge',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Filter::make('with_trigger_intent')
                    ->label('With trigger intent')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('trigger_intent')),
                Filter::make('public_entries')
                    ->label('Public landing')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_public', true)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No content entries found')
            ->emptyStateDescription('Create the first FAQ, system message or template bridge for the WhatsApp channel.');
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
            return 'Public landing';
        }

        if ($record->type === 'template_bridge') {
            return 'WhatsApp templates';
        }

        return 'WhatsApp bot';
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
