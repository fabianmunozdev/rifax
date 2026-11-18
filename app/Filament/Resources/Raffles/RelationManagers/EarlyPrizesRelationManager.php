<?php

namespace App\Filament\Resources\Raffles\RelationManagers;

use App\Actions\Raffles\PublishEarlyPrizeResultAction;
use App\Enums\PanelPermission;
use App\Filament\Support\PanelAccess;
use App\Models\Raffle;
use App\Models\RaffleEarlyPrize;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Premios anticipados de una rifa: se juegan antes del premio mayor, con su propia
 * lotería y fecha, y un solo ganador con las mismas cifras de la rifa.
 */
class EarlyPrizesRelationManager extends RelationManager
{
    protected static string $relationship = 'earlyPrizes';

    protected static ?string $title = 'Premios anticipados';

    protected static ?string $modelLabel = 'premio anticipado';

    protected static ?string $pluralModelLabel = 'premios anticipados';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        $count = $ownerRecord instanceof Raffle ? $ownerRecord->earlyPrizes()->count() : 0;

        return $count > 0 ? "Premios anticipados ({$count})" : 'Premios anticipados';
    }

    public function isReadOnly(): bool
    {
        return ! PanelAccess::allows(PanelPermission::RafflesManage);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label('Título')
                    ->placeholder('Ej. Bono de $500.000')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(3)
                    ->columnSpanFull(),
                ToggleButtons::make('draw_method')
                    ->label('¿Cómo se juega?')
                    ->options([
                        RaffleEarlyPrize::METHOD_LOTTERY => 'Con una lotería',
                        RaffleEarlyPrize::METHOD_LIVE => 'En vivo en redes',
                    ])
                    ->icons([
                        RaffleEarlyPrize::METHOD_LOTTERY => Heroicon::OutlinedTicket,
                        RaffleEarlyPrize::METHOD_LIVE => Heroicon::OutlinedVideoCamera,
                    ])
                    ->inline()
                    ->default(RaffleEarlyPrize::METHOD_LOTTERY)
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('lottery_name')
                    ->label('Lotería')
                    ->placeholder('Ej. Lotería de Medellín')
                    ->required(fn (Get $get): bool => $get('draw_method') !== RaffleEarlyPrize::METHOD_LIVE)
                    ->visible(fn (Get $get): bool => $get('draw_method') !== RaffleEarlyPrize::METHOD_LIVE)
                    ->maxLength(255),
                TextInput::make('lottery_draw_number')
                    ->label('Número de sorteo')
                    ->visible(fn (Get $get): bool => $get('draw_method') !== RaffleEarlyPrize::METHOD_LIVE)
                    ->maxLength(255),
                DatePicker::make('draw_date')
                    ->label('Fecha del sorteo')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->rule(function (): \Closure {
                        return function (string $attribute, mixed $value, \Closure $fail): void {
                            $raffle = $this->getOwnerRecord();

                            if ($raffle instanceof Raffle && $raffle->draw_date !== null && $value !== null
                                && \Illuminate\Support\Carbon::parse($value)->startOfDay()->gt(\Illuminate\Support\Carbon::parse($raffle->draw_date)->startOfDay())) {
                                $fail('Debe jugar en la fecha del premio mayor o antes ('.\Illuminate\Support\Carbon::parse($raffle->draw_date)->format('d/m/Y').').');
                            }
                        };
                    }),
                TimePicker::make('draw_time')
                    ->label('Hora del sorteo')
                    ->seconds(false),
                TextInput::make('lottery_reference_url')
                    ->label(fn (Get $get): string => $get('draw_method') === RaffleEarlyPrize::METHOD_LIVE ? 'Enlace del en vivo' : 'URL de resultados')
                    ->placeholder(fn (Get $get): ?string => $get('draw_method') === RaffleEarlyPrize::METHOD_LIVE ? 'Ej. https://www.instagram.com/megaeliteoficial' : null)
                    ->helperText(fn (Get $get): ?string => $get('draw_method') === RaffleEarlyPrize::METHOD_LIVE
                        ? 'La página muestra la red (Instagram, Facebook, TikTok, YouTube…) y un botón para ir al en vivo.'
                        : null)
                    ->required(fn (Get $get): bool => $get('draw_method') === RaffleEarlyPrize::METHOD_LIVE)
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Imagen del premio')
                    ->disk('public')
                    ->directory('raffles/early-prizes')
                    ->image()
                    ->imageEditor()
                    ->maxSize(4096)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Orden')
                    ->helperText('Para premios del mismo día: el número menor va primero.')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('draw_date')
            ->columns([
                TextColumn::make('title')
                    ->label('Premio')
                    ->description(fn (RaffleEarlyPrize $record): string => $record->drawSourceLabel())
                    ->wrap()
                    ->searchable(),
                TextColumn::make('draw_date')
                    ->label('Juega')
                    ->formatStateUsing(fn (RaffleEarlyPrize $record): string => $record->draw_date->translatedFormat('j M Y')
                        .(filled($record->draw_time) ? ' · '.substr((string) $record->draw_time, 0, 5) : ''))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (RaffleEarlyPrize $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        RaffleEarlyPrize::STATUS_WON => 'Con ganador',
                        RaffleEarlyPrize::STATUS_VOID => 'Desierto',
                        default => 'Por jugar',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        RaffleEarlyPrize::STATUS_WON => 'success',
                        RaffleEarlyPrize::STATUS_VOID => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('result_number')
                    ->label('Número ganador')
                    ->placeholder('—')
                    ->fontFamily('mono'),
                TextColumn::make('winnerPurchase.customer.name')
                    ->label('Ganador')
                    ->description(fn (RaffleEarlyPrize $record): ?string => $record->winnerPurchase?->customer?->phone)
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar premio anticipado')
                    ->modalHeading('Nuevo premio anticipado'),
            ])
            ->recordActions([
                $this->publishResultAction(),
                EditAction::make()
                    ->visible(fn (RaffleEarlyPrize $record): bool => $record->result_published_at === null),
                DeleteAction::make()
                    ->visible(fn (RaffleEarlyPrize $record): bool => $record->result_published_at === null),
            ])
            ->emptyStateHeading('Sin premios anticipados')
            ->emptyStateDescription('Agrega premios que se juegan antes del premio mayor, cada uno con su lotería y fecha.');
    }

    protected function publishResultAction(): Action
    {
        return Action::make('publish_early_prize_result')
            ->label('Publicar resultado')
            ->icon(Heroicon::OutlinedTrophy)
            ->color('success')
            ->visible(fn (RaffleEarlyPrize $record): bool => $record->result_published_at === null
                && PanelAccess::allows(PanelPermission::RafflesManage))
            ->modalHeading(fn (RaffleEarlyPrize $record): string => 'Resultado: '.$record->title)
            ->modalDescription(function (RaffleEarlyPrize $record): string {
                $digits = $this->getOwnerRecord() instanceof Raffle ? $this->getOwnerRecord()->normalizedNumberDigits() : 4;

                if ($record->isLive()) {
                    return "Escribe el número que salió en el en vivo ({$digits} cifras): gana la compra pagada con ese número. Si no se vendió, el premio queda desierto. El ganador sigue jugando por el premio mayor.";
                }

                return "Escribe el número que publicó la lotería. Se toman sus últimas {$digits} cifras: gana la compra pagada con ese número. Si no se vendió, el premio queda desierto. El ganador sigue jugando por el premio mayor.";
            })
            ->schema([
                TextInput::make('lottery_result')
                    ->label(fn (RaffleEarlyPrize $record): string => $record->isLive() ? 'Número ganador del en vivo' : 'Número ganador de la lotería')
                    ->required()
                    ->regex('/^\d{1,10}$/')
                    ->validationMessages(['regex' => 'Escribe solo cifras.'])
                    ->live(debounce: 300)
                    ->helperText(function (Get $get): ?string {
                        $value = preg_replace('/\D/', '', (string) $get('lottery_result'));
                        $raffle = $this->getOwnerRecord();

                        if ($value === '' || ! $raffle instanceof Raffle) {
                            return null;
                        }

                        $digits = $raffle->normalizedNumberDigits();
                        $number = strlen($value) >= $digits ? substr($value, -$digits) : str_pad($value, $digits, '0', STR_PAD_LEFT);

                        return "Número de la rifa: {$number}";
                    }),
            ])
            ->modalSubmitActionLabel('Publicar resultado')
            ->action(function (RaffleEarlyPrize $record, array $data): void {
                try {
                    $actor = Auth::user();
                    $prize = app(PublishEarlyPrizeResultAction::class)->execute(
                        $record,
                        (string) $data['lottery_result'],
                        $actor instanceof User ? $actor : null,
                    );

                    $winner = $prize->winnerPurchase?->customer;

                    Notification::make()
                        ->title($winner !== null
                            ? "Ganó el {$prize->result_number}: ".($winner->name ?: $winner->phone)
                            : "El {$prize->result_number} no se vendió: el premio queda desierto")
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
}
