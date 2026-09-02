<?php

namespace App\Jobs;

use App\Actions\Tickets\GenerateTicketForPurchaseAction;
use App\Actions\WhatsApp\SendPurchasePaidWhatsappNotificationAction;
use App\Actions\WhatsApp\SendTicketDocumentWhatsappAction;
use App\Models\Purchase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PostPaymentApprovedJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int,int>
     */
    public array $backoff = [30, 90, 240];

    public function __construct(public int $purchaseId)
    {
        $this->afterCommit();
    }

    public function handle(
        GenerateTicketForPurchaseAction $generateTicketForPurchaseAction,
        SendPurchasePaidWhatsappNotificationAction $sendPurchasePaidWhatsappNotificationAction,
        SendTicketDocumentWhatsappAction $sendTicketDocumentWhatsappAction,
    ): void {
        /** @var Purchase|null $purchase */
        $purchase = Purchase::query()
            ->with(['customer', 'raffle', 'ticket', 'numbers', 'conversationStates'])
            ->find($this->purchaseId);

        if ($purchase === null) {
            Log::warning('PostPaymentApprovedJob: purchase not found, aborting.', [
                'purchase_id' => $this->purchaseId,
            ]);

            return;
        }

        if ($purchase->status !== 'paid') {
            Log::warning('PostPaymentApprovedJob: purchase status is not paid, aborting.', [
                'purchase_id' => $purchase->id,
                'status' => $purchase->status,
            ]);

            return;
        }

        try {
            $generateTicketForPurchaseAction->execute($purchase);
        } catch (\Throwable $e) {
            Log::error('PostPaymentApprovedJob: GenerateTicketForPurchaseAction failed.', [
                'purchase_id' => $purchase->id,
                'ticket_id' => $purchase->ticket?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $purchaseWithTicket = $purchase->fresh(['customer', 'raffle', 'ticket', 'numbers', 'conversationStates']) ?? $purchase;

        try {
            $sendPurchasePaidWhatsappNotificationAction->execute($purchaseWithTicket);
        } catch (\Throwable $e) {
            Log::error('PostPaymentApprovedJob: SendPurchasePaidWhatsappNotificationAction failed.', [
                'purchase_id' => $purchaseWithTicket->id,
                'ticket_id' => $purchaseWithTicket->ticket?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        try {
            $docSent = $sendTicketDocumentWhatsappAction->execute($purchaseWithTicket);
            if (! $docSent) {
                Log::warning('PostPaymentApprovedJob: Ticket document WhatsApp not dispatched (see SendTicketDocumentWhatsappAction warnings).', [
                    'purchase_id' => $purchaseWithTicket->id,
                    'ticket_id' => $purchaseWithTicket->ticket?->id,
                    'ticket_code' => $purchaseWithTicket->ticket?->code,
                    'ticket_image_path' => $purchaseWithTicket->ticket?->image_path,
                    'public_url' => $purchaseWithTicket->ticket?->public_url,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('PostPaymentApprovedJob: SendTicketDocumentWhatsappAction failed.', [
                'purchase_id' => $purchaseWithTicket->id,
                'ticket_id' => $purchaseWithTicket->ticket?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
