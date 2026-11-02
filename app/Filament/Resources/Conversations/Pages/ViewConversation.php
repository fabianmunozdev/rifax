<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    /** Refresca encabezado y estado cuando el chat cambia la atención. */
    protected $listeners = ['handoff-updated' => '$refresh'];

    protected function getHeaderActions(): array
    {
        return [
            ConversationResource::makeTakeAction(),
            ConversationResource::makeAssignAction(),
            ConversationResource::makeReleaseAction(),
            ActionGroup::make([
                ConversationResource::makeOpenCustomerAction(),
                ConversationResource::makeOpenPurchaseAction(),
                ConversationResource::makeOpenPaymentAction(),
                ConversationResource::makeOpenCurrentRaffleAction(),
                ConversationResource::makeOpenLastWhatsappAction(),
            ])
                ->label(__('admin.resources.conversations.actions.open_related'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->button()
                ->color('gray'),
            ActionGroup::make([
                ConversationResource::makeMarkFollowUpAction(),
                ConversationResource::makeSoftResetAction(),
                ConversationResource::makeHardResetAction(),
            ])
                ->label(__('admin.resources.conversations.actions.more'))
                ->button()
                ->color('gray'),
        ];
    }
}
