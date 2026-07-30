<?php

namespace App\Actions\Purchases;

use App\Models\ConversationState;
use App\Models\PurchaseNumber;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ExpireReservationAction
{
    public function execute(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            /** @var Reservation $lockedReservation */
            $lockedReservation = Reservation::query()
                ->with(['purchase.numbers.raffleNumber'])
                ->lockForUpdate()
                ->findOrFail($reservation->id);

            if ($lockedReservation->status !== 'active' || $lockedReservation->expires_at->isFuture()) {
                return $lockedReservation;
            }

            $purchase = $lockedReservation->purchase;

            if ($purchase !== null && in_array($purchase->status, ['payment_submitted', 'under_review'], true)) {
                return $lockedReservation;
            }

            $lockedReservation->forceFill([
                'status' => 'expired',
                'expired_at' => now(),
            ])->save();

            if ($purchase !== null && in_array($purchase->status, ['reserved', 'rejected'], true)) {
                $purchase->forceFill([
                    'status' => 'expired',
                    'expired_at' => now(),
                    'reserved_until' => null,
                ])->save();

                foreach ($purchase->numbers as $purchaseNumber) {
                    $purchaseNumber->raffleNumber?->forceFill([
                        'status' => 'available',
                        'reserved_until' => null,
                    ])->save();
                }

                PurchaseNumber::query()
                    ->where('purchase_id', $purchase->id)
                    ->delete();

                ConversationState::query()
                    ->where('purchase_id', $purchase->id)
                    ->update([
                        'status' => 'purchase_expired',
                        'reservation_id' => $lockedReservation->id,
                        'context_expires_at' => now(),
                        'last_bot_message_at' => now(),
                    ]);
            }

            return $lockedReservation->fresh(['purchase.numbers.raffleNumber']);
        });
    }
}
