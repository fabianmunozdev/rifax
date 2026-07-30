<?php

namespace App\Filament\Resources\AdminAuditLogs;

use App\Enums\PanelPermission;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Filament\Resources\AdminAuditLogs\Pages\ViewAuditLog;
use App\Filament\Support\PanelAccess;
use App\Models\AdminAuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AdminAuditLogResource extends BaseResource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static ?string $translationKey = 'admin_audit_logs';

    protected static ?string $navigationGroupTranslationKey = 'admin.navigation.groups.access_control';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 90;

    protected static string|UnitEnum|null $navigationGroup = 'Access Control';

    protected static ?string $navigationLabel = 'Audit logs';

    protected static ?string $modelLabel = 'Audit log';

    protected static ?string $pluralModelLabel = 'Audit logs';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit event')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('event'),
                        TextEntry::make('action'),
                        TextEntry::make('user.name')
                            ->label('User')
                            ->placeholder('System'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('auditable_type')
                            ->label('Entity type')
                            ->placeholder('-'),
                        TextEntry::make('auditable_id')
                            ->label('Entity id')
                            ->placeholder('-'),
                    ]),
                Section::make('Request context')
                    ->schema([
                        TextEntry::make('ip_address')
                            ->placeholder('-'),
                        TextEntry::make('user_agent')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('context_json')
                            ->label('Context')
                            ->state(fn (AdminAuditLog $record): string => json_encode($record->context_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Snapshots')
                    ->schema([
                        TextEntry::make('before_json')
                            ->label('Before')
                            ->state(fn (AdminAuditLog $record): string => json_encode($record->before_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('after_json')
                            ->label('After')
                            ->state(fn (AdminAuditLog $record): string => json_encode($record->after_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('auditable_type')
                    ->label('Entity')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? class_basename($state) : '-')
                    ->toggleable(),
                TextColumn::make('auditable_id')
                    ->label('Entity id')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(fn (): array => static::getEloquentQuery()
                        ->select('action')
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
                Filter::make('system_events')
                    ->label('System events')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('user_id')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No audit logs found')
            ->emptyStateDescription('Critical admin actions appear here once they are executed from the panel.')
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return PanelAccess::allows(PanelPermission::AuditLogsView);
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
