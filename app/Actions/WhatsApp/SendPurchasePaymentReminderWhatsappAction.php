<?php

namespace App\Actions\WhatsApp;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Filament\Support\OperationsUi;
use App\Models\Purchase;
use App\Models\User;
use App\Models\WhatsappMessage;
use InvalidArgumentException;

class SendPurchasePaymentReminderWhatsappAction
{
    public function __construct(
        protected QueueOperationalCampaignWhatsappAction $queueOperationalCampaignWhatsappAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(Purchase $purchase, ?User $actor = null): ?WhatsappMessage
    {
        $purchase->loadMissing([
            'customer',
            'raffle',
            'latestPayment',
        ]);

        if ($purchase->customer === null) {
            throw new InvalidArgumentException('The purchase does not have a customer linked to WhatsApp.');
        }

        if (! in_array($purchase->status, ['reserved', 'rejected'], true)) {
            throw new InvalidArgumentException('Payment reminders are only available for reserved or rejected purchases.');
        }

        $message = $this->queueOperationalCampaignWhatsappAction->execute(
            customer: $purchase->customer,
            intent: 'purchase_payment_reminder',
            variables: [
                'customer_name' => $purchase->customer->name ?: 'cliente',
                'raffle_title' => $purchase->raffle_title_snapshot ?: $purchase->raffle?->title ?: 'tu rifa',
                'purchase_total' => number_format((float) $purchase->total_amount, 0),
                'purchase_status' => OperationsUi::purchaseStatusLabel($purchase->status),
                'reservation_expires_at' => $purchase->reserved_until?->format('Y-m-d H:i') ?: '-',
            ],
            context: [
                'purchase_id' => $purchase->id,
                'raffle_id' => $purchase->raffle_id,
                'campaign_type' => 'purchase_payment_reminder',
            ],
            fallback: $purchase->status === 'rejected'
                ? 'Hola {customer_name}, el ultimo comprobante para {raffle_title} no pudo ser aprobado. Puedes enviar un nuevo comprobante por este chat para conservar tu compra.'
                : 'Hola {customer_name}, tu compra en {raffle_title} sigue pendiente de pago. Si deseas conservar tus numeros, comparte tu comprobante por este chat antes de {reservation_expires_at}.',
            dedupHours: 12,
            templateDefaults: [
                'template_name' => 'purchase_payment_reminder',
            ],
        );

        $this->recordAdminAuditAction->execute(
            event: 'campaign.payment_reminder_requested',
            action: 'launch_payment_reminder',
            auditable: $purchase,
            context: [
                'purchase_id' => $purchase->id,
                'raffle_id' => $purchase->raffle_id,
                'message_id' => $message?->id,
                'duplicate_skipped' => $message === null,
            ],
            user: $actor,
        );

        return $message;
    }
}
