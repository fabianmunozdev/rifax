<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConversationResource::makeOpenCustomerAction(),
            ConversationResource::makeOpenPurchaseAction(),
            ConversationResource::makeOpenPaymentAction(),
            ConversationResource::makeOpenCurrentRaffleAction(),
            ConversationResource::makeOpenLastWhatsappAction(),
            ConversationResource::makeMarkFollowUpAction(),
            ConversationResource::makeSoftResetAction(),
            ConversationResource::makeHardResetAction(),
        ];
    }
}
