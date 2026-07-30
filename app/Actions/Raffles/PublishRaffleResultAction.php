<?php

namespace App\Actions\Raffles;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\User;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Actions\WhatsApp\SendRaffleWinnerWhatsappNotificationAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PublishRaffleResultAction
{
    public function __construct(
        protected SendRaffleWinnerWhatsappNotificationAction $sendRaffleWinnerWhatsappNotificationAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(Raffle $raffle, string $resultNumber, ?User $actor = null): Raffle
    {
        return DB::transaction(function () use ($raffle, $resultNumber, $actor): Raffle {
            /** @var Raffle $lockedRaffle */
            $lockedRaffle = Raffle::query()
                ->lockForUpdate()
                ->findOrFail($raffle->id);

            if ($lockedRaffle->status !== 'published') {
                throw new InvalidArgumentException('Only published raffles can publish a result.');
            }

            if ($lockedRaffle->result_published_at !== null) {
                throw new InvalidArgumentException('This raffle result has already been published.');
            }

            $pendingPurchaseCount = $lockedRaffle->purchases()
                ->whereIn('status', ['reserved', 'payment_submitted', 'under_review', 'rejected'])
                ->count();

            if ($pendingPurchaseCount > 0) {
                throw new InvalidArgumentException('Cannot publish the raffle result while there are purchases pending payment validation or active reservations.');
            }

            $before = $this->recordAdminAuditAction->snapshot($lockedRaffle);
            $normalizedResultNumber = $this->normalizeResultNumber($lockedRaffle, $resultNumber);

            $winningNumber = RaffleNumber::query()
                ->where('raffle_id', $lockedRaffle->id)
                ->where('number', $normalizedResultNumber)
                ->lockForUpdate()
                ->first();

            if ($winningNumber !== null) {
                $winningNumber->forceFill([
                    'status' => 'winner',
                    'reserved_until' => null,
                ])->save();
            }

            $lockedRaffle->forceFill([
                'status' => 'closed',
                'result_number' => $normalizedResultNumber,
                'result_published_at' => now(),
            ])->save();

            $this->recordAdminAuditAction->execute(
                event: 'raffle.result_published',
                action: 'publish_result',
                auditable: $lockedRaffle,
                before: $before,
                after: $this->recordAdminAuditAction->snapshot($lockedRaffle->fresh()),
                context: [
                    'result_number' => $normalizedResultNumber,
                    'winner_number_id' => $winningNumber?->id,
                ],
                user: $actor,
            );

            DB::afterCommit(function () use ($lockedRaffle): void {
                $freshRaffle = $lockedRaffle->fresh([
                    'winnerNumber.purchaseNumber.purchase.customer',
                    'winnerNumber.purchaseNumber.purchase.ticket',
                    'winnerNumber.purchaseNumber.purchase.conversationStates',
                ]) ?? $lockedRaffle;

                $this->sendRaffleWinnerWhatsappNotificationAction->execute($freshRaffle);
            });

            return $lockedRaffle->fresh([
                'winnerNumber.purchaseNumber.purchase.customer',
            ]) ?? $lockedRaffle;
        });
    }

    protected function normalizeResultNumber(Raffle $raffle, string $resultNumber): string
    {
        $normalized = trim($resultNumber);

        if ($normalized === '') {
            throw new InvalidArgumentException('The result number is required.');
        }

        $maxLength = $raffle->normalizedNumberDigits();

        if ($maxLength > 0 && ctype_digit($normalized) && strlen($normalized) < $maxLength) {
            return str_pad($normalized, $maxLength, '0', STR_PAD_LEFT);
        }

        return $normalized;
    }
}
