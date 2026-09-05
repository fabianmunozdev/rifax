<?php

namespace App\Actions\Purchases;

use App\Actions\WhatsApp\QueueOperationalCampaignWhatsappAction;
use App\Actions\WhatsApp\QueueOutboundWhatsappMessageAction;
use App\Models\ConversationState;
use App\Models\PurchaseNumber;
use App\Models\Reservation;
use App\Support\WhatsAppReply;
use Illuminate\Support\Facades\DB;

class ExpireReservationAction
{
    public function __construct(
        protected QueueOperationalCampaignWhatsappAction $queueOperationalCampaignWhatsappAction,
        protected QueueOutboundWhatsappMessageAction $queueOutboundWhatsappMessageAction,
    ) {
    }

    public function execute(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            /** @var Reservation $lockedReservation */
            $lockedReservation = Reservation::query()
                ->with(['purchase.numbers.raffleNumber', 'purchase.customer', 'purchase.raffle'])
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

            $customer = null;
            $raffle = null;
            $reservedNumbers = '';

            if ($purchase !== null && in_array($purchase->status, ['reserved', 'rejected'], true)) {
                $customer = $purchase->customer;
                $raffle = $purchase->raffle;
                $reservedNumbers = collect($purchase->numbers ?? [])->pluck('number')->implode(', ');
                if ($reservedNumbers === '') {
                    $reservedNumbers = collect($purchase->selected_numbers_json ?? [])->pluck('number')->implode(', ');
                }

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

            if ($customer !== null && $customer->exists) {
                DB::afterCommit(function () use ($customer, $raffle, $reservedNumbers, $purchase): void {
                    $this->sendExpirationNotification($customer, $purchase, $raffle, $reservedNumbers);
                });
            }

            return $lockedReservation->fresh(['purchase.numbers.raffleNumber', 'purchase.customer', 'purchase.raffle']);
        });
    }

    protected function sendExpirationNotification(
        mixed $customer,
        mixed $purchase,
        mixed $raffle,
        string $reservedNumbers,
    ): void {
        try {
            $variables = [
                'customer_name' => $customer->name ?: 'cliente',
                'raffle_title' => $purchase?->raffle_title_snapshot ?: $raffle?->title ?: 'tu rifa',
                'reserved_numbers' => $reservedNumbers !== '' ? $reservedNumbers : 'los números seleccionados',
            ];

            $context = [
                'purchase_id' => $purchase?->id,
                'reservation_id' => $purchase?->reservation_id,
                'campaign_type' => 'purchase_expired',
            ];

            $fallback = 'Hola {customer_name}, tu reserva para {raffle_title} venció y tus números fueron liberados. Si quieres participar de nuevo, escribe COMPRAR o toca el botón para continuar.';

            $message = $this->queueOperationalCampaignWhatsappAction->execute(
                customer: $customer,
                intent: 'purchase_expired',
                variables: $variables,
                context: $context,
                fallback: $fallback,
                dedupHours: 2,
            );

            if ($message !== null) {
                $body = (string) ($message->body_text ?? $this->renderTemplate($fallback, $variables));
                if (trim($body) === '') {
                    return;
                }

                $reply = WhatsAppReply::make($body, [
                    ['id' => 'expired_new_purchase', 'title' => 'Comprar de nuevo'],
                    ['id' => 'expired_menu', 'title' => 'Menú'],
                ]);

                $this->queueOutboundWhatsappMessageAction->execute(
                    customer: $customer,
                    messageType: 'interactive',
                    bodyText: $body,
                    payloadJson: array_merge($message->payload_json ?? [], [
                        'interactive' => $reply->toInteractiveMetaPayload(),
                        'interactive_buttons' => $reply->buttons,
                    ]),
                );
            }
        } catch (\Throwable $e) {
            rescue(fn () => logs()->error('ExpireReservationAction sendExpirationNotification failed.', [
                'customer_id' => $customer?->id,
                'purchase_id' => $purchase?->id,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    protected function renderTemplate(?string $template, array $variables): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return collect($variables)->reduce(
            fn (string $text, mixed $value, string $key): string => str_replace('{'.$key.'}', (string) $value, $text),
            $template,
        );
    }
}
