<?php

namespace App\Actions\Payments;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Actions\Tickets\GenerateTicketForPurchaseAction;
use App\Actions\WhatsApp\SendPurchasePaidWhatsappNotificationAction;
use App\Actions\WhatsApp\SendTicketDocumentWhatsappAction;
use App\Models\ConversationState;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApprovePaymentAction
{
    public function __construct(
        protected GenerateTicketForPurchaseAction $generateTicketForPurchaseAction,
        protected SendPurchasePaidWhatsappNotificationAction $sendPurchasePaidWhatsappNotificationAction,
        protected SendTicketDocumentWhatsappAction $sendTicketDocumentWhatsappAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(Payment $payment, ?User $reviewer = null): Payment
    {
        return DB::transaction(function () use ($payment, $reviewer): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->with('purchase.numbers.raffleNumber')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->status !== 'pending_review') {
                throw new InvalidArgumentException('Only pending review payments can be approved.');
            }

            $before = $this->recordAdminAuditAction->snapshot($lockedPayment);
            $purchase = $lockedPayment->purchase;

            if ($purchase === null) {
                throw new InvalidArgumentException('The payment does not have a purchase linked.');
            }

            if ($purchase->status === 'expired' || $purchase->expired_at !== null) {
                throw new InvalidArgumentException('Cannot approve payment for an expired purchase.');
            }

            if ($purchase->numbers->isEmpty()) {
                throw new InvalidArgumentException('Cannot approve payment because the purchase numbers are no longer reserved.');
            }

            $invalidNumbers = $purchase->numbers
                ->filter(fn ($purchaseNumber): bool => $purchaseNumber->raffleNumber === null || $purchaseNumber->raffleNumber->status !== 'reserved')
                ->values()
                ->all();

            if ($invalidNumbers !== []) {
                throw new InvalidArgumentException('Cannot approve payment because one or more numbers are no longer reserved.');
            }

            $raffle = $purchase->raffle;

            if ($raffle !== null && $raffle->hasDrawStarted()) {
                $proofReceivedAt = $lockedPayment->proof_received_at;
                $drawAt = $raffle->drawAt();

                if ($proofReceivedAt !== null && $drawAt !== null && $proofReceivedAt->greaterThanOrEqualTo($drawAt)) {
                    throw new InvalidArgumentException('Cannot approve payment because the proof was received after the draw time.');
                }
            }

            $lockedPayment->forceFill([
                'status' => 'approved',
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'review_due_at' => null,
            ])->save();

            $purchase->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            foreach ($purchase->numbers as $purchaseNumber) {
                $purchaseNumber->raffleNumber?->forceFill([
                    'status' => 'paid',
                    'reserved_until' => null,
                ])->save();
            }

            ConversationState::query()
                ->where('purchase_id', $purchase->id)
                ->update([
                    'status' => 'purchase_paid',
                    'payment_id' => $lockedPayment->id,
                    'last_bot_message_at' => now(),
                ]);

            $this->recordAdminAuditAction->execute(
                event: 'payment.approved',
                action: 'approve',
                auditable: $lockedPayment,
                before: $before,
                after: $this->recordAdminAuditAction->snapshot($lockedPayment->fresh()),
                context: [
                    'purchase_id' => $purchase->id,
                ],
                user: $reviewer,
            );

            DB::afterCommit(function () use ($purchase): void {
                $freshPurchase = $purchase->fresh(['customer', 'raffle', 'ticket']) ?? $purchase;

                $this->generateTicketForPurchaseAction->execute($freshPurchase);
                $purchaseWithTicket = $freshPurchase->fresh(['customer', 'raffle', 'ticket', 'numbers', 'conversationStates']) ?? $freshPurchase;

                $this->sendPurchasePaidWhatsappNotificationAction->execute($purchaseWithTicket);
                $this->sendTicketDocumentWhatsappAction->execute($purchaseWithTicket);
            });

            return $lockedPayment->fresh(['purchase.numbers.raffleNumber']);
        });
    }
}
