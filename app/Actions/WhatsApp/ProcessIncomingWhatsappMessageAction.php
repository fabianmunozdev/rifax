<?php

namespace App\Actions\WhatsApp;

use App\Actions\Content\ResolvePublishedContentAction;
use App\Actions\Conversations\HumanHandoffAction;
use App\Actions\WhatsApp\Bot\BotKeywords;
use App\Actions\WhatsApp\Bot\BotMessages;
use App\Actions\WhatsApp\Bot\BotRaffles;
use App\Actions\WhatsApp\Bot\InboundNormalizer;
use App\Actions\WhatsApp\Bot\MainMenuFlow;
use App\Actions\WhatsApp\Bot\OnboardingFlow;
use App\Actions\WhatsApp\Bot\PaymentFlow;
use App\Actions\WhatsApp\Bot\PurchaseFlow;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\WhatsappMessage;
use App\Support\WhatsAppReply;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Punto de entrada del bot de WhatsApp: identifica al cliente, guarda el
 * mensaje, decide qué flujo responde (ver App\Actions\WhatsApp\Bot) y envía
 * la respuesta.
 */
class ProcessIncomingWhatsappMessageAction
{
    public function __construct(
        protected BotKeywords $botKeywords,
        protected BotMessages $botMessages,
        protected BotRaffles $botRaffles,
        protected InboundNormalizer $inboundNormalizer,
        protected MainMenuFlow $mainMenuFlow,
        protected OnboardingFlow $onboardingFlow,
        protected PaymentFlow $paymentFlow,
        protected PurchaseFlow $purchaseFlow,
        protected DispatchOutboundWhatsappMessageAction $dispatchOutboundWhatsappMessageAction,
        protected HumanHandoffAction $humanHandoffAction,
        protected ResolvePublishedContentAction $resolvePublishedContentAction,
    ) {}

    /**
     * @param  array<mixed>  $message
     * @param  array<mixed>  $contacts
     * @return array<string, mixed>
     */
    public function execute(array $message, array $contacts = []): array
    {
        $customer = $this->resolveCustomer($message, $contacts);

        $conversationState = ConversationState::query()->firstOrCreate([
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
        ], [
            'status' => 'main_menu',
        ]);

        $normalizedMessage = $this->inboundNormalizer->normalizeMessage($message);

        $resolvedInbound = $this->resolveInboundMessage($customer, $conversationState, $message, $normalizedMessage);
        $inboundMessage = $resolvedInbound['message'];

        $customer->forceFill([
            'last_interaction_at' => now(),
        ])->save();

        if ($resolvedInbound['duplicate_reply'] instanceof WhatsappMessage) {
            return [
                'customer_id' => $customer->id,
                'conversation_status' => $conversationState->fresh()->status,
                'reply' => $resolvedInbound['duplicate_reply']->body_text,
                'delivery_status' => $resolvedInbound['duplicate_reply']->status,
            ];
        }

        $conversationState = $conversationState->fresh();

        if ($conversationState->isHandledByHuman()) {
            $humanResult = $this->handleHumanConversation($customer, $conversationState, $inboundMessage);

            if ($humanResult !== null) {
                return $humanResult;
            }
        }

        $reply = $this->resolveReply($customer, $conversationState, $inboundMessage);

        if ($reply instanceof WhatsAppReply && $reply->hasButtons()) {
            $outboundMessage = WhatsappMessage::query()->create([
                'customer_id' => $customer->id,
                'direction' => 'outbound',
                'message_type' => 'interactive',
                'body_text' => $reply->body,
                'payload_json' => [
                    'interactive' => $reply->toInteractiveMetaPayload(),
                    'interactive_buttons' => $reply->buttons,
                ],
                'status' => 'generated',
                'provider_created_at' => now(),
            ]);
        } else {
            $bodyText = $reply instanceof WhatsAppReply ? $reply->body : (string) $reply;

            $outboundMessage = WhatsappMessage::query()->create([
                'customer_id' => $customer->id,
                'direction' => 'outbound',
                'message_type' => 'text',
                'body_text' => $bodyText,
                'payload_json' => ['text' => ['body' => $bodyText]],
                'status' => 'generated',
                'provider_created_at' => now(),
            ]);
        }

        $outboundMessage = $this->dispatchOutboundWhatsappMessageAction->execute($customer, $outboundMessage);

        $updatedState = ConversationState::query()
            ->where('customer_id', $customer->id)
            ->where('channel', 'whatsapp')
            ->firstOrFail();

        $updatedState->forceFill([
            'last_inbound_message_id' => $inboundMessage->id,
            'last_outbound_message_id' => $outboundMessage->id,
            'last_user_message_at' => now(),
            'last_bot_message_at' => now(),
        ])->save();

        return [
            'customer_id' => $customer->id,
            'conversation_status' => $updatedState->status,
            'reply' => (string) $reply,
            'reply_buttons' => $reply instanceof WhatsAppReply ? $reply->buttons : [],
            'delivery_status' => $outboundMessage->status,
        ];
    }

    /**
     * @param  array<mixed>  $message
     * @param  array<mixed>  $contacts
     */
    protected function resolveCustomer(array $message, array $contacts): Customer
    {
        $waIdRaw = (string) (Arr::get($contacts, '0.wa_id') ?: Arr::get($message, 'from'));
        $fromRaw = (string) Arr::get($message, 'from');
        $fromUserIdRaw = (string) Arr::get($message, 'from_user_id');
        $waDigits = (string) preg_replace('/\D+/', '', $waIdRaw);
        if ($waDigits === '') {
            $waDigits = (string) preg_replace('/\D+/', '', $fromRaw);
        }

        $phoneFromWa = null;
        if ($waDigits !== '' && strlen($waDigits) <= 15) {
            $phoneFromWa = Customer::normalizePhone($waDigits);
        }

        if ($waDigits === '' || strlen($waDigits) < 7) {
            $userIdWaDigits = null;
            if (str_contains($fromUserIdRaw, '.')) {
                $userIdAfterDot = (string) substr($fromUserIdRaw, (int) strpos($fromUserIdRaw, '.') + 1);
                $d = (string) preg_replace('/\D+/', '', $userIdAfterDot);
                if (strlen($d) >= 7) {
                    $userIdWaDigits = $d;
                }
            }
            if ($userIdWaDigits === null) {
                $d = (string) preg_replace('/\D+/', '', $fromUserIdRaw);
                if (strlen($d) >= 7) {
                    $userIdWaDigits = $d;
                }
            }
            if ($userIdWaDigits !== null) {
                $waDigits = $userIdWaDigits;
            }
        }

        if ($waDigits === '' || strlen($waDigits) < 7) {
            throw new InvalidArgumentException(
                'No se pudo obtener un número de teléfono válido desde el mensaje. waIdRaw='
                .$waIdRaw.' fromRaw='.$fromRaw.' fromUserIdRaw='.$fromUserIdRaw,
            );
        }

        $waDigitsLen = strlen($waDigits);
        if ($phoneFromWa === null && $waDigitsLen <= 15) {
            $phoneFromWa = Customer::normalizePhone($waDigits);
        }

        $name = trim((string) Arr::get($contacts, '0.profile.name', ''));

        if ($phoneFromWa !== null) {
            $customer = Customer::query()->firstOrNew([
                'phone' => $phoneFromWa,
            ]);
        } else {
            $customer = Customer::query()->firstOrNew([
                'wa_id' => $waDigits,
            ]);
            if ($customer->exists && $customer->phone === null && $phoneFromWa !== null) {
                $customer->phone = $phoneFromWa;
            }
        }

        if ($name !== '') {
            $customer->name = $name;
        }

        $customer->wa_id = $waDigits;
        if ($phoneFromWa !== null && $customer->phone === null) {
            $customer->phone = $phoneFromWa;
        }
        $customer->last_interaction_at = now();
        $customer->save();

        return $customer;
    }

    /**
     * @param  array<mixed>  $message
     * @param  array{type: string, body_text: string|null, provider_message_id: string|null}  $normalizedMessage
     * @return array{message: WhatsappMessage, duplicate_reply: WhatsappMessage|null}
     */
    protected function resolveInboundMessage(
        Customer $customer,
        ConversationState $conversationState,
        array $message,
        array $normalizedMessage,
    ): array {
        $providerMessageId = $normalizedMessage['provider_message_id'];

        if (filled($providerMessageId)) {
            $existingInboundMessage = WhatsappMessage::query()
                ->where('direction', 'inbound')
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if ($existingInboundMessage instanceof WhatsappMessage) {
                $conversationState->loadMissing('lastOutboundMessage');

                $duplicateReply = $conversationState->last_inbound_message_id === $existingInboundMessage->id
                    ? $conversationState->lastOutboundMessage
                    : null;

                return [
                    'message' => $existingInboundMessage,
                    'duplicate_reply' => $duplicateReply instanceof WhatsappMessage ? $duplicateReply : null,
                ];
            }
        }

        return [
            'message' => WhatsappMessage::query()->create([
                'customer_id' => $customer->id,
                'direction' => 'inbound',
                'message_type' => $normalizedMessage['type'],
                'provider_message_id' => $providerMessageId,
                'body_text' => $normalizedMessage['body_text'],
                'payload_json' => $message,
                'provider_created_at' => now(),
            ]),
            'duplicate_reply' => null,
        ];
    }

    protected function resolveReply(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage): WhatsAppReply|string
    {
        $rawText = trim((string) ($inboundMessage->body_text ?? ''));
        $text = trim(Str::upper($rawText));
        $normText = $this->botKeywords->normalizeKeywordText($text);

        if ($this->botKeywords->isMenuCommand($normText)) {
            $this->botRaffles->resetToMainMenu($state);

            return $this->botMessages->renderMainMenu();
        }

        if ($this->botKeywords->isHumanAgentRequest($normText)) {
            $this->humanHandoffAction->request($state, mb_substr($rawText, 0, 1000));

            return $this->resolvePublishedContentAction->byKey(
                'system.handoff.requested',
                [],
                'Listo, un asesor te atenderá por este chat lo antes posible.'.PHP_EOL.PHP_EOL
                .'Mientras tanto el asistente automático queda en pausa. Si prefieres volver a las opciones automáticas, escribe MENU.',
            );
        }

        if ($this->botKeywords->isCancelCommand($normText)) {
            $activePurchase = $state->purchase;
            $hasActiveReservedPurchase = $activePurchase instanceof Purchase
                && in_array($activePurchase->status, ['reserved', 'rejected'], true);

            if ($hasActiveReservedPurchase) {
                return (function () use ($activePurchase): string {
                    $minutes = $activePurchase->raffle?->reservation_timeout_minutes;
                    if (! is_int($minutes) || $minutes < 1) {
                        $minutes = $activePurchase->reserved_until?->diffInRealMinutes(now()) ?? 0;
                    }
                    $minuteLabel = $minutes === 1 ? 'minuto' : 'minutos';
                    $hint = $minutes > 0
                        ? "Tu reserva actual se mantiene por {$minutes} {$minuteLabel} más. Si completas el pago y envías el comprobante en este plazo, conservas tus números."
                        : 'Los números se liberarán en cualquier momento si no completas el pago.';

                    return 'Para evitar que pierdas tu reserva por error, este chat no puede cancelarla automáticamente.'.PHP_EOL.PHP_EOL
                        .$hint.PHP_EOL.PHP_EOL
                        .'Si necesitas ayuda, escribe ASESOR y te atendemos personalmente. Para volver al listado principal escribe MENU.';
                })();
            }

            return 'Actualmente no tienes una reserva activa en curso.'.PHP_EOL.PHP_EOL
                .'Para volver al menú principal escribe MENU.';
        }

        if ($inboundMessage->message_type === 'image' && ! in_array($state->status, ['purchase_payment_instructions', 'purchase_rejected', 'purchase_under_review'], true)) {
            return WhatsAppReply::prefixed('Recibimos una imagen, pero ahora mismo no estamos esperando un comprobante de pago.'.PHP_EOL.PHP_EOL, $this->botMessages->renderMainMenu());
        }

        if ($this->botKeywords->isFaqShortcut($normText)) {
            return $this->mainMenuFlow->handleFaqShortcut($state, $text);
        }

        if (($pickerToken = $this->botKeywords->extractPickerIntentToken($text)) !== null) {
            return $this->purchaseFlow->handlePickerIntent($customer, $state, $pickerToken);
        }

        if ($this->onboardingFlow->shouldRecoverCompletedOnboarding($customer, $state)) {
            return $this->recoverCompletedOnboarding($customer, $state, $text);
        }

        if ($this->mainMenuFlow->shouldReenterClosedPurchaseFlow($state, $normText)) {
            return $this->mainMenuFlow->handleClosedPurchaseReentry($state, $normText);
        }

        return match ($state->status) {
            'main_menu' => $this->mainMenuFlow->handleMainMenu($customer, $state, $text),
            'purchase_select_raffle' => $this->purchaseFlow->handlePurchaseSelectRaffle($state, $text),
            'purchase_enter_quantity' => $this->purchaseFlow->handlePurchaseEnterQuantity($state, $text),
            'purchase_choose_mode' => $this->purchaseFlow->handlePurchaseChooseMode($customer, $state, $text),
            'purchase_select_numbers' => $this->purchaseFlow->handlePurchaseSelectNumbers($customer, $state, $text),
            'purchase_payment_instructions', 'purchase_rejected' => $this->paymentFlow->handlePaymentProofStep($customer, $state, $inboundMessage),
            'purchase_under_review' => $this->paymentFlow->handlePurchaseUnderReview($customer, $state, $inboundMessage, $normText),
            'purchase_paid' => 'Tu compra ya fue aprobada. Muy pronto podrás consultar tu boleto desde este chat.',
            'purchase_expired' => $this->purchaseFlow->handleExpiredState($customer, $state, $text),
            'onboarding_privacy_consent' => $this->onboardingFlow->handleOnboardingPrivacyConsent($customer, $state, $text),
            'onboarding_collect_name' => ($this->onboardingFlow->handleOnboardingCollectName($customer, $state, $rawText) ?? $this->resumePendingAction($customer, $state)),
            'onboarding_collect_document' => ($this->onboardingFlow->handleOnboardingCollectDocument($customer, $state, $rawText) ?? $this->resumePendingAction($customer, $state)),
            default => $this->botMessages->renderMainMenu(),
        };
    }

    protected function resumePendingAction(Customer $customer, ConversationState $state): WhatsAppReply|string
    {
        $metadata = $state->metadata_json ?? [];
        $pendingAction = (string) ($metadata['pending_action'] ?? '');
        $pendingPickerToken = (string) ($metadata['pending_picker_token'] ?? '');

        unset($metadata['pending_action'], $metadata['pending_picker_token']);

        $state->forceFill([
            'status' => 'main_menu',
            'current_raffle_id' => null,
            'requested_quantity' => null,
            'selection_mode' => null,
            'selected_numbers_json' => [],
            'reservation_id' => null,
            'purchase_id' => null,
            'payment_id' => null,
            'context_expires_at' => null,
            'metadata_json' => $metadata,
        ])->save();

        return match ($pendingAction) {
            'purchase_start' => $this->mainMenuFlow->handleMainMenu($customer, $state->fresh(), '1'),
            'picker_intent' => $pendingPickerToken !== '' ? $this->purchaseFlow->handlePickerIntent($customer, $state->fresh(), $pendingPickerToken) : $this->botMessages->renderMainMenu(),
            default => $this->botMessages->renderMainMenu(),
        };
    }

    protected function recoverCompletedOnboarding(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        $pendingAction = (string) (($state->metadata_json ?? [])['pending_action'] ?? '');

        if ($pendingAction !== '') {
            return $this->resumePendingAction($customer, $state);
        }

        $this->botRaffles->resetToMainMenu($state);

        return in_array($text, ['1', '2', '3', '4', '5', '6', '7'], true)
            ? $this->mainMenuFlow->handleMainMenu($customer, $state->fresh(), $text)
            : $this->botMessages->renderMainMenu();
    }

    /**
     * El chat lo atiende (o espera) un agente: se guarda el mensaje sin
     * respuesta automática. Devuelve null cuando el bot debe retomar.
     *
     * @return array<string, mixed>|null
     */
    protected function handleHumanConversation(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage): ?array
    {
        $normText = $this->botKeywords->normalizeKeywordText(trim(Str::upper((string) ($inboundMessage->body_text ?? ''))));

        $lastHumanActivity = collect([$state->handoff_requested_at, $state->handoff_started_at, $state->last_agent_message_at])
            ->filter()
            ->max();
        $idleMinutes = (int) config('rifax.handoff.idle_release_minutes', 720);
        $isIdle = $idleMinutes > 0 && $lastHumanActivity !== null && $lastHumanActivity->lt(now()->subMinutes($idleMinutes));

        // Mientras nadie lo ha tomado, el cliente puede volver al bot con MENU.
        $customerCancelled = $state->handoff_status === ConversationState::HANDOFF_REQUESTED && $this->botKeywords->isMenuCommand($normText);

        if ($isIdle || $customerCancelled) {
            $state->forceFill([
                'handoff_status' => null,
                'assigned_user_id' => null,
                'handoff_requested_at' => null,
                'handoff_started_at' => null,
                'handoff_reason' => null,
            ])->save();

            return null;
        }

        $state->forceFill([
            'last_inbound_message_id' => $inboundMessage->id,
            'last_user_message_at' => now(),
        ])->save();

        return [
            'customer_id' => $customer->id,
            'conversation_status' => $state->status,
            'handoff_status' => $state->handoff_status,
            'reply' => null,
            'delivery_status' => null,
        ];
    }
}
