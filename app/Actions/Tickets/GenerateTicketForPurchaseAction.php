<?php

namespace App\Actions\Tickets;

use App\Models\Purchase;
use App\Models\Ticket;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GenerateTicketForPurchaseAction
{
    public function __construct(
        protected RenderTicketAssetsAction $renderTicketAssetsAction,
    ) {
    }

    public function execute(Purchase $purchase): Ticket
    {
        $purchase->loadMissing(['ticket', 'numbers', 'raffle']);

        if ($purchase->status !== 'paid') {
            throw new InvalidArgumentException('A ticket can only be generated for a paid purchase.');
        }

        $ticket = $purchase->ticket;

        if ($ticket === null) {
            $ticket = Ticket::query()->create([
                'purchase_id' => $purchase->id,
                'code' => $this->generateUniqueCode(),
                'verification_token' => $this->generateUniqueVerificationToken(),
                'version' => 1,
                'generated_at' => now(),
            ]);
        }

        if (blank($ticket->public_url)) {
            $ticket->forceFill([
                'public_url' => url('/tickets/'.$ticket->verification_token),
            ])->save();
        }

        if (blank($ticket->image_path) || blank($ticket->thumbnail_path)) {
            $ticket = $this->renderTicketAssetsAction->execute($ticket);
        }

        return $ticket->fresh() ?? $ticket;
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = 'TK-'.Str::upper(Str::random(10));
        } while (Ticket::query()->where('code', $code)->exists());

        return $code;
    }

    protected function generateUniqueVerificationToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (Ticket::query()->where('verification_token', $token)->exists());

        return $token;
    }
}
