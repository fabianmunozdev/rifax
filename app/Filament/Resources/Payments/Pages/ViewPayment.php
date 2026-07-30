<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PaymentResource::makeApproveAction(),
            PaymentResource::makeRejectAction(),
            PaymentResource::makeOpenPurchaseAction(),
            PaymentResource::makeOpenRaffleAction(),
            PaymentResource::makeOpenTicketAction(),
            PaymentResource::makeOpenProofAction(),
        ];
    }
}
