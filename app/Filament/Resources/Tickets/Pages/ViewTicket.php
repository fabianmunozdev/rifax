<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TicketResource::makeRegenerateAssetsAction(),
            TicketResource::makeResendWhatsappAction(),
            TicketResource::makeOpenPurchaseAction(),
            TicketResource::makeOpenRaffleAction(),
            TicketResource::makeOpenLastWhatsappAction(),
            TicketResource::makeOpenPublicTicketAction(),
            TicketResource::makeOpenAssetAction(),
        ];
    }
}
