<?php

namespace App\Actions\Payments;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\ConversationState;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RejectPaymentAction
{
    public function __construct(
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(Payment $payment, string $reason, ?User $reviewer = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $reviewer): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->with('purchase')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->status !== 'pending_review') {
                throw new InvalidArgumentException('Only pending review payments can be rejected.');
            }

            $before = $this->recordAdminAuditAction->snapshot($lockedPayment);
            $purchase = $lockedPayment->purchase;

            $lockedPayment->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'review_due_at' => null,
                'rejection_reason' => $reason,
            ])->save();

            $purchase->forceFill([
                'status' => 'rejected',
            ])->save();

            ConversationState::query()
                ->where('purchase_id', $purchase->id)
                ->update([
                    'status' => 'purchase_rejected',
                    'payment_id' => $lockedPayment->id,
                    'last_bot_message_at' => now(),
                ]);

            $this->recordAdminAuditAction->execute(
                event: 'payment.rejected',
                action: 'reject',
                auditable: $lockedPayment,
                before: $before,
                after: $this->recordAdminAuditAction->snapshot($lockedPayment->fresh()),
                context: [
                    'purchase_id' => $purchase->id,
                    'reason' => $reason,
                ],
                user: $reviewer,
            );

            return $lockedPayment->fresh('purchase');
        });
    }
}
