<?php

namespace App\Actions\WhatsApp;

use App\Models\Purchase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendTicketDocumentWhatsappAction
{
    public function __construct(
        protected QueueOutboundWhatsappMessageAction $queueOutboundWhatsappMessageAction,
    ) {
    }

    public function execute(Purchase $purchase): bool
    {
        $purchase->loadMissing(['customer', 'raffle', 'conversationStates', 'ticket']);

        if ($purchase->customer === null) {
            Log::warning('SendTicketDocumentWhatsappAction skipped: no customer on purchase.', [
                'purchase_id' => $purchase->id,
            ]);

            return false;
        }

        if ($purchase->ticket === null) {
            Log::warning('SendTicketDocumentWhatsappAction skipped: ticket does not exist for purchase.', [
                'purchase_id' => $purchase->id,
                'customer_id' => $purchase->customer->id,
            ]);

            return false;
        }

        if (blank($purchase->ticket->image_path)) {
            Log::warning('SendTicketDocumentWhatsappAction skipped: ticket image_path is blank.', [
                'purchase_id' => $purchase->id,
                'ticket_id' => $purchase->ticket->id,
                'ticket_code' => $purchase->ticket->code,
                'ticket_image_path' => $purchase->ticket->image_path,
            ]);

            return false;
        }

        if (! $this->canSendDirectDocument($purchase)) {
            $cs = $purchase->conversationStates()->latest('updated_at')->first();
            Log::warning('SendTicketDocumentWhatsappAction skipped: outside 24h customer window.', [
                'purchase_id' => $purchase->id,
                'ticket_id' => $purchase->ticket->id,
                'ticket_code' => $purchase->ticket->code,
                'last_user_message_at' => $cs?->last_user_message_at,
                'paid_at' => $purchase->paid_at,
            ]);

            return false;
        }

        $raffleTitle = $purchase->raffle_title_snapshot ?: $purchase->raffle?->title ?: 'tu rifa';
        $caption = 'Tu boleto para '.$raffleTitle.' ya esta disponible.';
        $extension = pathinfo($purchase->ticket->image_path, PATHINFO_EXTENSION) ?: 'png';
        $filename = 'ticket-'.$purchase->ticket->code.'.'.$extension;

        try {
            $publicDocumentUrl = filled($purchase->ticket->image_path) && str_starts_with($purchase->ticket->image_path, 'http')
                ? $purchase->ticket->image_path
                : Storage::disk('public')->url($purchase->ticket->image_path);
        } catch (\Throwable $e) {
            $publicDocumentUrl = asset('storage/'.$purchase->ticket->image_path);
        }

        $this->queueOutboundWhatsappMessageAction->execute(
            customer: $purchase->customer,
            messageType: 'document',
            bodyText: $caption,
            payloadJson: [
                'document' => [
                    'link' => $publicDocumentUrl,
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
