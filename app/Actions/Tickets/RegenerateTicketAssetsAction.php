<?php

namespace App\Actions\Tickets;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Ticket;
use App\Models\User;
use InvalidArgumentException;

class RegenerateTicketAssetsAction
{
    public function __construct(
        protected RenderTicketAssetsAction $renderTicketAssetsAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(Ticket $ticket, ?User $actor = null): Ticket
    {
        $ticket->loadMissing('purchase');
        $before = $this->recordAdminAuditAction->snapshot($ticket);

        if ($ticket->purchase === null || $ticket->purchase->status !== 'paid') {
            throw new InvalidArgumentException('Only tickets from paid purchases can regenerate assets.');
        }

        if (blank($ticket->public_url)) {
            $ticket->forceFill([
                'public_url' => url('/tickets/'.$ticket->verification_token),
            ])->save();
        }

        $ticket->forceFill([
            'version' => max(1, (int) $ticket->version) + 1,
            'generated_at' => now(),
        ])->save();

        $freshTicket = $this->renderTicketAssetsAction->execute($ticket);

        $this->recordAdminAuditAction->execute(
            event: 'ticket.assets_regenerated',
            action: 'regenerate_assets',
            auditable: $freshTicket,
            before: $before,
            after: $this->recordAdminAuditAction->snapshot($freshTicket),
            user: $actor,
        );

        return $freshTicket;
    }
}
