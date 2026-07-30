<?php

namespace App\Actions\WhatsApp;

use App\Models\Purchase;

class SendTicketDocumentWhatsappAction
{
    public function __construct(
        protected QueueOutboundWhatsappMessageAction $queueOutboundWhatsappMessageAction,
    ) {
    }

    public function execute(Purchase $purchase): bool
    {
        $purchase->loadMissing(['customer', 'raffle', 'conversationStates', 'ticket']);

        if ($purchase->customer === null || $purchase->ticket === null || blank($purchase->ticket->image_path)) {
            return false;
        }

        if (! $this->canSendDirectDocument($purchase)) {
            return false;
        }

        $raffleTitle = $purchase->raffle_title_snapshot ?: $purchase->raffle?->title ?: 'tu rifa';
        $caption = 'Tu boleto para '.$raffleTitle.' ya esta disponible.';
        $filename = 'ticket-'.$purchase->ticket->code.'.svg';

        $this->queueOutboundWhatsappMessageAction->execute(
            customer: $purchase->customer,
            messageType: 'document',
            bodyText: $caption,
            payloadJson: [
                'document' => [
                    'link' => asset('storage/'.$purchase->ticket->image_path),
                    'filename' => $filename,
                    'caption' => $caption,
                ],
                'ticket_id' => $purchase->ticket->id,
            ],
        );

        return true;
    }

    public function canSendDirectDocument(Purchase $purchase): bool
    {
        $conversationState = $purchase->conversationStates()
            ->latest('updated_at')
            ->first();

        if ($conversationState?->last_user_message_at === null) {
            return false;
        }

        return $conversationState->last_user_message_at->gte(now()->subHours(24));
    }
}
