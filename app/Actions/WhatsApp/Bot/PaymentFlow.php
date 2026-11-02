<?php

namespace App\Actions\WhatsApp\Bot;

use App\Actions\Payments\SubmitPaymentProofAction;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\WhatsappMessage;
use App\Support\WhatsAppReply;
use InvalidArgumentException;

/**
 * Recepción de comprobantes de pago y compras en revisión.
 */
class PaymentFlow
{
    public function __construct(
        protected BotKeywords $botKeywords,
        protected BotMessages $botMessages,
        protected BotRaffles $botRaffles,
        protected PurchaseFlow $purchaseFlow,
        protected SubmitPaymentProofAction $submitPaymentProofAction,
    ) {}

    public function handlePaymentProofStep(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage): WhatsAppReply|string
    {
        $purchase = $state->purchase;

        if ($purchase === null) {
            $purchase = Purchase::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['reserved', 'rejected'])
                ->latest('id')
                ->first();
        }

        if ($inboundMessage->message_type !== 'image') {
            if ($purchase !== null && $purchase->status === 'expired') {
                $state->forceFill([
                    'status' => 'purchase_expired',
                    'context_expires_at' => now(),
                ])->save();

                return $this->purchaseFlow->handleExpiredState($customer, $state, '');
            }

            if ($state->status === 'purchase_rejected') {
                $reminder = $purchase !== null
                    ? $this->botMessages->renderPaymentWaitingReminder($purchase)
                    : 'Envía un nuevo comprobante por imagen para continuar.';

                return 'Tu pago fue rechazado. Envía un nuevo comprobante por imagen para continuar.'.PHP_EOL.PHP_EOL.$reminder;
            }

            if ($purchase === null) {
                return 'No encontramos una compra activa. Si deseas iniciar una nueva, escribe COMPRAR o MENU.';
            }

            return $this->botMessages->renderPaymentWaitingReminder($purchase);
        }

        if ($purchase === null) {
            return 'No encontramos una compra activa para asociar este comprobante. Si lo deseas, escribe COMPRAR para iniciar una nueva o MENU para volver al inicio.';
        }

        try {
            $this->submitPaymentProofAction->execute(
                purchase: $purchase,
                whatsappMessage: $inboundMessage,
                storagePath: 'whatsapp-proofs/'.$inboundMessage->id.'.jpg',
                originalFilename: 'whatsapp-proof-'.$inboundMessage->id.'.jpg',
                mimeType: 'image/jpeg',
                metadata: ['replace_previous' => false],
            );
        } catch (InvalidArgumentException $e) {
            $message = match (true) {
                str_contains($e->getMessage(), 'already submitted') => 'Ya tienes un comprobante en revisión. Si deseas reemplazarlo, escribe REEMPLAZAR y luego envía la nueva imagen.',
                default => 'No fue posible registrar el comprobante para la compra actual: '.$e->getMessage(),
            };

            return $message;
        }

        return 'Hemos recibido tu comprobante y tu compra está en revisión.'.PHP_EOL.PHP_EOL.'Te avisaremos por este medio cuando el pago sea aprobado o rechazado.'.PHP_EOL.PHP_EOL.'Si necesitas reemplazar el comprobante antes de la revisión, escribe REEMPLAZAR y envía la nueva imagen.';
    }

    public function handlePurchaseUnderReview(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage, string $normText): WhatsAppReply|string
    {
        $purchase = $state->purchase;

        if ($purchase === null) {
            $purchase = Purchase::query()
                ->where('customer_id', $customer->id)
                ->latest('id')
                ->first();
        }

        if ($purchase === null || ! in_array($purchase->status, ['payment_submitted'], true)) {
            $this->botRaffles->resetToMainMenu($state);

            return WhatsAppReply::prefixed('Tu compra ya no está en revisión. Regresamos al menú principal.'.PHP_EOL.PHP_EOL, $this->botMessages->renderMainMenu());
        }

        $pendingPayment = $purchase->payments()
            ->where('status', 'pending_review')
            ->latest('id')
            ->first();

        if ($this->botKeywords->isReplaceCommand($normText) || $inboundMessage->message_type === 'image') {
            if ($pendingPayment !== null && $inboundMessage->message_type === 'image') {
                try {
                    $this->submitPaymentProofAction->execute(
                        purchase: $purchase,
                        whatsappMessage: $inboundMessage,
                        storagePath: 'whatsapp-proofs/'.$inboundMessage->id.'.jpg',
                        originalFilename: 'whatsapp-proof-'.$inboundMessage->id.'.jpg',
                        mimeType: 'image/jpeg',
                        metadata: ['replace_previous_payment_id' => $pendingPayment->id, 'replace_previous' => true],
                    );
                } catch (InvalidArgumentException $e) {
                    return 'No fue posible reemplazar el comprobante: '.$e->getMessage().PHP_EOL.PHP_EOL
                        .'Recuerda que también puedes escribir MENU para ver las opciones disponibles.';
                }

                return 'Hemos reemplazado tu comprobante y la compra sigue en revisión. Te avisaremos cuando tengamos una respuesta.';
            }

            if ($this->botKeywords->isReplaceCommand($normText)) {
                $state->forceFill([
                    'metadata_json' => array_merge($state->metadata_json ?? [], [
                        'awaiting_replacement_proof' => true,
                        'replacement_for_payment_id' => $pendingPayment?->id,
                    ]),
                ])->save();

                return 'Listo. Envía la nueva imagen del comprobante en el siguiente mensaje para reemplazar el comprobante actual.';
            }

            if ($inboundMessage->message_type === 'image') {
                return 'Recibimos una imagen. Para reemplazar tu comprobante actual primero escribe REEMPLAZAR y luego envía la nueva imagen.';
            }
        }

        $info = 'Tu compra sigue en revisión. Te avisaremos por este medio cuando tengamos una respuesta.';
        $buttons = [];

        if ($pendingPayment !== null) {
            $info .= PHP_EOL.PHP_EOL
                .'Si necesitas reemplazar el comprobante antes de nuestra revisión, escribe REEMPLAZAR y luego envía la nueva imagen.';
            $buttons[] = ['id' => 'under_review_replace', 'title' => 'Reemplazar'];
        } else {
            $info .= PHP_EOL.PHP_EOL.'Próximamente recibirás nuestra respuesta.';
        }

        $buttons[] = ['id' => 'under_review_menu', 'title' => 'Menú'];

        return WhatsAppReply::make($info, $buttons);
    }
}
