<?php

namespace App\Actions\Purchases;

use App\Models\ConversationState;
use Illuminate\Support\Facades\DB;

class CancelPurchaseFlowAction
{
    /**
     * @return array{cancelled: bool, released_numbers: int}
     */
    public function execute(ConversationState $state): array
    {
        return DB::transaction(function () use ($state): array {
            /** @var ConversationState $lockedState */
            $lockedState = ConversationState::query()
                ->with(['reservation.purchase.numbers.raffleNumber', 'purchase.numbers.raffleNumber'])
                ->lockForUpdate()
                ->findOrFail($state->id);

            $releasedNumbers = 0;
            $cancelled = false;

            $reservation = $lockedState->reservation;
            $purchase = $lockedState->purchase ?: $reservation?->purchase;

            if ($reservation !== null && $reservation->status === 'active') {
                $reservation->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ])->save();

                $cancelled = true;
            }

            if ($purchase !== null && in_array($purchase->status, ['reserved', 'rejected'], true)) {
                $purchase->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'reserved_until' => null,
                ])->save();

                foreach ($purchase->numbers as $purchaseNumber) {
                    if ($purchaseNumber->raffleNumber?->status === 'reserved') {
                        $purchaseNumber->raffleNumber->forceFill([
                            'status' => 'available',
                            'reserved_until' => null,
                        ])->save();

                        $releasedNumbers++;
                    }
                }

                $cancelled = true;
            }

            $lockedState->forceFill([
                'status' => 'main_menu',
                'requested_quantity' => null,
                'selection_mode' => null,
                'selected_numbers_json' => [],
                'reservation_id' => null,
                'purchase_id' => null,
                'payment_id' => null,
                'context_expires_at' => null,
                'metadata_json' => array_merge($lockedState->metadata_json ?? [], [
                    'cancelled_at' => now()->toIso8601String(),
                    'released_numbers' => $releasedNumbers,
                ]),
            ])->save();

            return [
                'cancelled' => $cancelled,
                'released_numbers' => $releasedNumbers,
            ];
        });
    }
}
