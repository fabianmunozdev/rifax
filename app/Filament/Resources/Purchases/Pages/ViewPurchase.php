<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PurchaseResource::makeSendPaymentReminderAction(),
            PurchaseResource::makeOpenLatestPaymentAction(),
            PurchaseResource::makeOpenTicketAction(),
            PurchaseResource::makeOpenRaffleAction(),
            PurchaseResource::makeOpenPublicTicketAction(),
        ];
    }
}
