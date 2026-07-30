<?php

namespace App\Actions\Tickets;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Actions\WhatsApp\SendPurchasePaidWhatsappNotificationAction;
use App\Actions\WhatsApp\SendTicketDocumentWhatsappAction;
use App\Models\Ticket;
use App\Models\User;
use InvalidArgumentException;

class ResendTicketWhatsappAction
{
    public function __construct(
        protected GenerateTicketForPurchaseAction $generateTicketForPurchaseAction,
        protected SendTicketDocumentWhatsappAction $sendTicketDocumentWhatsappAction,
        protected SendPurchasePaidWhatsappNotificationAction $sendPurchasePaidWhatsappNotificationAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(Ticket $ticket, ?User $actor = null): string
    {
        $ticket->loadMissing(['purchase.customer', 'purchase.raffle', 'purchase.numbers', 'purchase.conversationStates']);
        $before = $this->recordAdminAuditAction->snapshot($ticket);

        $purchase = $ticket->purchase;

        if ($purchase === null || $purchase->status !== 'paid') {
            throw new InvalidArgumentException('Only paid purchases can resend a ticket.');
        }

        $purchase->setRelation('ticket', $ticket);
        $ticket = $this->generateTicketForPurchaseAction->execute($purchase);

        if ($this->sendTicketDocumentWhatsappAction->execute($purchase->fresh(['customer', 'raffle', 'ticket', 'numbers', 'conversationStates']) ?? $purchase)) {
            $this->recordAdminAuditAction->execute(
                event: 'ticket.whatsapp_resent',
                action: 'resend_whatsapp',
                auditable: $ticket->fresh(),
                before: $before,
                after: $this->recordAdminAuditAction->snapshot($ticket->fresh()),
                context: [
                    'delivery_mode' => 'document',
                ],
                user: $actor,
            );

            return 'document';
        }

        $this->sendPurchasePaidWhatsappNotificationAction->execute($purchase->fresh(['customer', 'raffle', 'ticket', 'conversationStates']) ?? $purchase);

        $this->recordAdminAuditAction->execute(
            event: 'ticket.whatsapp_resent',
            action: 'resend_whatsapp',
            auditable: $ticket->fresh(),
            before: $before,
            after: $this->recordAdminAuditAction->snapshot($ticket->fresh()),
            context: [
                'delivery_mode' => 'template_or_text',
            ],
            user: $actor,
        );

        return 'template_or_text';
    }
}
