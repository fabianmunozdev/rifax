<?php

namespace App\Filament\Resources\Raffles\Pages;

use App\Filament\Resources\Raffles\RaffleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

class ViewRaffle extends ViewRecord
{
    protected static string $resource = RaffleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            RaffleResource::makeSendDrawReminderAction(),
            RaffleResource::makeSendUpcomingAnnouncementAction(),
            RaffleResource::makePublishResultAction(),
            DeleteAction::make(),
        ];
    }
}
