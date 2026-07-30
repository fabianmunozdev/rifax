<?php

namespace App\Actions\Conversations;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Actions\Purchases\CancelPurchaseFlowAction;
use App\Models\ConversationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HardResetConversationAction
{
    public function __construct(
        protected CancelPurchaseFlowAction $cancelPurchaseFlowAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(ConversationState $state, string $reason, ?User $actor = null): ConversationState
    {
        return DB::transaction(function () use ($state, $reason, $actor): ConversationState {
            /** @var ConversationState $lockedState */
            $lockedState = ConversationState::query()
                ->with(['reservation.purchase.numbers.raffleNumber', 'purchase.numbers.raffleNumber'])
                ->lockForUpdate()
                ->findOrFail($state->id);

            $before = $this->recordAdminAuditAction->snapshot($lockedState);
            $this->cancelPurchaseFlowAction->execute($lockedState);

            $resetState = ConversationState::query()->lockForUpdate()->findOrFail($lockedState->id);
            $metadata = $resetState->metadata_json ?? [];

            $resetState->forceFill([
                'current_raffle_id' => null,
                'substatus' => null,
                'locked_at' => null,
                'locked_by' => null,
                'metadata_json' => array_merge($metadata, [
                    'hard_reset_at' => now()->toIso8601String(),
                    'hard_reset_reason' => $reason,
                    'follow_up_required' => false,
                ]),
            ])->save();

            $freshState = $resetState->fresh([
                'customer',
                'currentRaffle',
                'purchase',
                'payment',
                'reservation',
            ]) ?? $resetState;

            $this->recordAdminAuditAction->execute(
                event: 'conversation.hard_reset',
                action: 'hard_reset',
                auditable: $freshState,
                before: $before,
                after: $this->recordAdminAuditAction->snapshot($freshState),
                context: [
                    'reason' => $reason,
                ],
                user: $actor,
            );

            return $freshState;
        });
    }
}
