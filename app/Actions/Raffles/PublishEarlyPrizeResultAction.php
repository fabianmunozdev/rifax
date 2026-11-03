<?php

namespace App\Actions\Raffles;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\Purchase;
use App\Models\RaffleEarlyPrize;
use App\Models\RaffleNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Publica el resultado de un premio anticipado.
 *
 * Se toman las últimas cifras del resultado de la lotería (tantas como tenga la rifa).
 * Gana la compra pagada que tenga ese número; si no se vendió, el premio queda desierto.
 * El número ganador sigue jugando por el premio mayor, así que su estado no cambia.
 */
class PublishEarlyPrizeResultAction
{
    public function __construct(
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(RaffleEarlyPrize $prize, string $lotteryResult, ?User $actor = null): RaffleEarlyPrize
    {
        return DB::transaction(function () use ($prize, $lotteryResult, $actor): RaffleEarlyPrize {
            /** @var RaffleEarlyPrize $locked */
            $locked = RaffleEarlyPrize::query()->with('raffle')->lockForUpdate()->findOrFail($prize->id);

            if ($locked->result_published_at !== null) {
                throw new InvalidArgumentException('El resultado de este premio ya fue publicado.');
            }

            if (! in_array($locked->raffle->status, ['published', 'closed'], true)) {
                throw new InvalidArgumentException('Solo se pueden publicar resultados de rifas publicadas.');
            }

            $digits = $locked->raffle->normalizedNumberDigits();
            $lotteryResult = preg_replace('/\D/', '', $lotteryResult) ?? '';

            if ($lotteryResult === '') {
                throw new InvalidArgumentException('Escribe el número ganador de la lotería.');
            }

            $resultNumber = strlen($lotteryResult) >= $digits
                ? substr($lotteryResult, -$digits)
                : str_pad($lotteryResult, $digits, '0', STR_PAD_LEFT);

            $raffleNumber = RaffleNumber::query()
                ->where('raffle_id', $locked->raffle_id)
                ->where('number', $resultNumber)
                ->lockForUpdate()
                ->first();

            if ($raffleNumber !== null && $raffleNumber->status === 'reserved') {
                throw new InvalidArgumentException("El número {$resultNumber} tiene una compra pendiente de pago. Apruébala o recházala antes de publicar el resultado.");
            }

            $winnerPurchase = $raffleNumber === null ? null : Purchase::query()
                ->where('raffle_id', $locked->raffle_id)
                ->where('status', 'paid')
                ->whereHas('numbers', fn ($query) => $query->where('raffle_number_id', $raffleNumber->id))
                ->latest('id')
                ->first();

            $before = $this->recordAdminAuditAction->snapshot($locked);

            $locked->forceFill([
                'lottery_result' => $lotteryResult,
                'result_number' => $resultNumber,
                'winner_raffle_number_id' => $winnerPurchase !== null ? $raffleNumber?->id : null,
                'winner_purchase_id' => $winnerPurchase?->id,
                'result_published_at' => now(),
                'result_published_by' => $actor?->id,
            ])->save();

            $this->recordAdminAuditAction->execute(
                event: 'raffle.early_prize_result_published',
                action: 'publish_early_prize_result',
                auditable: $locked,
                before: $before,
                after: $this->recordAdminAuditAction->snapshot($locked->fresh()),
                context: [
                    'raffle_id' => $locked->raffle_id,
                    'result_number' => $resultNumber,
                    'winner_purchase_id' => $winnerPurchase?->id,
                ],
                user: $actor,
            );

            return $locked->fresh(['winnerPurchase.customer', 'raffle']) ?? $locked;
        });
    }
}
