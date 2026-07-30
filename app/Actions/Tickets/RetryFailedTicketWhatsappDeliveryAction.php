<?php

namespace App\Actions\Tickets;

use App\Actions\WhatsApp\RetryFailedWhatsappMessageAction;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappMessage;
use InvalidArgumentException;

class RetryFailedTicketWhatsappDeliveryAction
{
    public function __construct(
        protected RetryFailedWhatsappMessageAction $retryFailedWhatsappMessageAction,
    ) {
    }

    public function execute(Ticket $ticket, ?User $actor = null): WhatsappMessage
    {
        $failedMessage = WhatsappMessage::query()
            ->where('customer_id', $ticket->purchase?->customer_id)
            ->where('direction', 'outbound')
            ->where(function ($query): void {
                $query
                    ->where('status', 'failed')
                    ->orWhere('provider_status', 'failed');
            })
            ->whereRaw("(payload_json->>'ticket_id')::bigint = ?", [$ticket->id])
            ->latest('id')
            ->first();

        if ($failedMessage === null) {
            throw new InvalidArgumentException('This ticket has no failed WhatsApp delivery to retry.');
        }

        return $this->retryFailedWhatsappMessageAction->execute($failedMessage, $actor);
    }
}
