<?php

namespace App\Actions\Tickets;

use App\Models\Purchase;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
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

        $needRender = blank($ticket->image_path) || blank($ticket->thumbnail_path);

        if (! $needRender) {
            $disk = Storage::disk('public');
            $imgExists = $disk->exists((string) $ticket->image_path);
            $thumbExists = $disk->exists((string) $ticket->thumbnail_path);
            $imgIsEmpty = false;
            $thumbIsEmpty = false;
            try {
                if ($imgExists) {
                    $imgIsEmpty = $disk->size((string) $ticket->image_path) < 256;
                }
                if ($thumbExists) {
                    $thumbIsEmpty = $disk->size((string) $ticket->thumbnail_path) < 256;
                }
            } catch (\Throwable) {
                $imgIsEmpty = ! $imgExists;
                $thumbIsEmpty = ! $thumbExists;
            }
            $needRender = ! $imgExists || ! $thumbExists || $imgIsEmpty || $thumbIsEmpty;
        }

        if ($needRender) {
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
