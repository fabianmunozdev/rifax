<?php

namespace App\Actions\Conversations;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\ConversationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SoftResetConversationAction
{
    public function __construct(
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(ConversationState $state, ?string $reason = null, ?User $actor = null): ConversationState
    {
        return DB::transaction(function () use ($state, $reason, $actor): ConversationState {
            /** @var ConversationState $lockedState */
            $lockedState = ConversationState::query()
                ->lockForUpdate()
                ->findOrFail($state->id);

            $before = $this->recordAdminAuditAction->snapshot($lockedState);
            $metadata = $lockedState->metadata_json ?? [];

            $lockedState->forceFill([
                'status' => 'main_menu',
                'substatus' => null,
                'current_raffle_id' => null,
                'requested_quantity' => null,
                'selection_mode' => null,
                'selected_numbers_json' => [],
                'context_expires_at' => null,
                'locked_at' => null,
                'locked_by' => null,
                'metadata_json' => array_merge($metadata, [
                    'soft_reset_at' => now()->toIso8601String(),
                    'follow_up_required' => false,
                    'follow_up_note' => $reason,
                ]),
            ])->save();

            $freshState = $lockedState->fresh([
                'customer',
                'currentRaffle',
                'purchase',
                'payment',
                'reservation',
            ]) ?? $lockedState;

            $this->recordAdminAuditAction->execute(
                event: 'conversation.soft_reset',
                action: 'soft_reset',
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
