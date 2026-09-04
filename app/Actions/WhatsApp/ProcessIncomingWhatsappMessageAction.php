<?php

namespace App\Actions\WhatsApp;

use App\Actions\Content\ResolvePublishedContentAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Purchases\CancelPurchaseFlowAction;
use App\Actions\Purchases\ReserveNumbersAction;
use App\Actions\Purchases\SelectRandomRaffleNumbersAction;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\RafflePickerIntent;
use App\Models\WhatsappMessage;
use App\Support\PickerAuthToken;
use App\Support\WhatsAppReply;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcessIncomingWhatsappMessageAction
{
    public function __construct(
        protected DispatchOutboundWhatsappMessageAction $dispatchOutboundWhatsappMessageAction,
        protected ResolvePublishedContentAction $resolvePublishedContentAction,
        protected ReserveNumbersAction $reserveNumbersAction,
        protected SelectRandomRaffleNumbersAction $selectRandomRaffleNumbersAction,
        protected CancelPurchaseFlowAction $cancelPurchaseFlowAction,
        protected SubmitPaymentProofAction $submitPaymentProofAction,
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

        $normalizedMessage = $this->normalizeMessage($message);

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

        $reply = $this->resolveReply($customer, $conversationState->fresh(), $inboundMessage);

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
            'reply' => $reply,
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

    /**
     * @param  array<mixed>  $message
     * @return array{type: string, body_text: string|null, provider_message_id: string|null}
     */
    protected function normalizeMessage(array $message): array
    {
        $type = (string) Arr::get($message, 'type', 'other');

        $bodyText = match ($type) {
            'text' => Arr::get($message, 'text.body'),
            'image' => Arr::get($message, 'image.caption'),
            'interactive' => $this->normalizeInteractiveBody($message),
            'button' => $this->normalizeButtonBody($message),
            default => null,
        };

        return [
            'type' => $type,
            'body_text' => $bodyText,
            'provider_message_id' => Arr::get($message, 'id'),
        ];
    }

    /**
     * @param  array<mixed>  $message
     */
    protected function normalizeInteractiveBody(array $message): ?string
    {
        $interactiveType = (string) Arr::get($message, 'interactive.type', '');

        $payload = match ($interactiveType) {
            'button_reply' => (string) Arr::get($message, 'interactive.button_reply.id'),
            'list_reply' => (string) Arr::get($message, 'interactive.list_reply.id'),
            default => '',
        };

        if ($payload === '') {
            return null;
        }

        return $this->expandButtonPayloadToText($payload);
    }

    /**
     * @param  array<mixed>  $message
     */
    protected function normalizeButtonBody(array $message): ?string
    {
        $payload = (string) Arr::get($message, 'button.payload');
        $text = (string) Arr::get($message, 'button.text');

        if ($payload !== '') {
            return $this->expandButtonPayloadToText($payload);
        }

        return $text !== '' ? $text : null;
    }

    protected function expandButtonPayloadToText(string $payload): string
    {
        $payload = trim($payload);

        if ($payload === '') {
            return '';
        }

        $alias = [
            'main_menu' => 'MENU',
            'cancel_purchase' => 'CANCELAR',
            'buy_now' => '1',
            'menu_1' => '1',
            'menu_2' => '2',
            'menu_3' => '3',
            'menu_4' => '4',
            'menu_5' => '5',
            'menu_6' => '6',
            'menu_7' => '7',
            'replace_proof' => 'REEMPLAZAR',
            'expired_new_purchase' => '1',
            'expired_menu' => 'MENU',
            'raffle_continue' => '1',
            'raffle_back' => 'MENU',
            'payment_menu' => 'MENU',
            'payment_help' => '7',
            'under_review_replace' => 'REEMPLAZAR',
            'under_review_menu' => 'MENU',
            'privacy_accept' => 'ACEPTO',
            'privacy_reject' => 'NO ACEPTO',
            'mode_manual' => '1',
            'mode_random' => '2',
        ];

        if (isset($alias[$payload])) {
            return $alias[$payload];
        }

        if (Str::startsWith($payload, 'paid_menu')) {
            return 'MENU';
        }

        if (Str::startsWith($payload, 'menu_')) {
            $digit = substr($payload, 5);
            if (ctype_digit($digit)) {
                return $digit;
            }
        }

        if (Str::startsWith($payload, 'raffle_')) {
            $rest = substr($payload, 7);
            if (ctype_digit($rest)) {
                return $rest;
            }
        }

        if (Str::startsWith($payload, 'view_ticket:')) {
            return 'MENU';
        }

        if (Str::startsWith($payload, 'ticket_web:')) {
            return '3';
        }

        return $payload;
    }

    protected function resolveReply(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage): WhatsAppReply|string
    {
        $rawText = trim((string) ($inboundMessage->body_text ?? ''));
        $text = trim(Str::upper($rawText));
        $normText = $this->normalizeKeywordText($text);

        if ($this->isMenuCommand($normText)) {
            $this->resetToMainMenu($state);

            return $this->renderMainMenu();
        }

        if ($this->isCancelCommand($normText)) {
            $result = $this->cancelPurchaseFlowAction->execute($state);

            return $result['cancelled']
                ? 'Proceso cancelado y reserva liberada correctamente.'.PHP_EOL.PHP_EOL.$this->renderMainMenu()
                : 'Proceso cancelado.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
        }

        if ($inboundMessage->message_type === 'image' && ! in_array($state->status, ['purchase_payment_instructions', 'purchase_rejected', 'purchase_under_review'], true)) {
            return 'Recibimos una imagen, pero ahora mismo no estamos esperando un comprobante de pago.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
        }

        if ($this->isFaqShortcut($normText)) {
            return $this->handleFaqShortcut($state, $text);
        }

        if (($pickerToken = $this->extractPickerIntentToken($text)) !== null) {
            return $this->handlePickerIntent($customer, $state, $pickerToken);
        }

        if ($this->shouldRecoverCompletedOnboarding($customer, $state)) {
            return $this->recoverCompletedOnboarding($customer, $state, $text);
        }

        if ($this->shouldReenterClosedPurchaseFlow($state, $normText)) {
            return $this->handleClosedPurchaseReentry($state, $normText);
        }

        return match ($state->status) {
            'main_menu' => $this->handleMainMenu($customer, $state, $text),
            'purchase_select_raffle' => $this->handlePurchaseSelectRaffle($state, $text),
            'purchase_enter_quantity' => $this->handlePurchaseEnterQuantity($state, $text),
            'purchase_choose_mode' => $this->handlePurchaseChooseMode($customer, $state, $text),
            'purchase_select_numbers' => $this->handlePurchaseSelectNumbers($customer, $state, $text),
            'purchase_payment_instructions', 'purchase_rejected' => $this->handlePaymentProofStep($customer, $state, $inboundMessage),
            'purchase_under_review' => $this->handlePurchaseUnderReview($customer, $state, $inboundMessage, $normText),
            'purchase_paid' => 'Tu compra ya fue aprobada. Muy pronto podrás consultar tu boleto desde este chat.',
            'purchase_expired' => $this->handleExpiredState($customer, $state, $text),
            'onboarding_privacy_consent' => $this->handleOnboardingPrivacyConsent($customer, $state, $text),
            'onboarding_collect_name' => $this->handleOnboardingCollectName($customer, $state, $rawText),
            'onboarding_collect_document' => $this->handleOnboardingCollectDocument($customer, $state, $rawText),
            default => $this->renderMainMenu(),
        };
    }

    protected function handleMainMenu(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if ($text === '' || ! in_array($text, ['1', '2', '3', '4', '5', '6', '7'], true)) {
            return $this->renderMainMenu();
        }

        if ($text === '1') {
            if (($onboardingReply = $this->redirectToPurchaseOnboardingIfNeeded($customer, $state, 'purchase_start')) !== null) {
                return $onboardingReply;
            }

            $raffles = $this->getActiveRaffles();

            if ($raffles->isEmpty()) {
                return 'Ahora mismo no tenemos una rifa activa disponible.';
            }

            if ($raffles->count() > 1) {
                $state->forceFill([
                    'status' => 'purchase_select_raffle',
                    'current_raffle_id' => null,
                ])->save();

                return $this->renderRaffleOptions($raffles);
            }

            $raffle = $raffles->first();

            $state->forceFill([
                'status' => 'purchase_select_raffle',
                'current_raffle_id' => $raffle?->id,
            ])->save();

            return $this->renderRaffleSelection($raffle);
        }

        return match ($text) {
            '2' => $this->renderAvailableNumbers(),
            '3' => $this->renderMyNumbers($state->customer),
            '4' => $this->renderStatistics(),
            '5' => $this->renderUpcomingRaffles(),
            '6' => $this->renderConditions(),
            '7' => $this->renderHelp(),
        };
    }

    protected function handlePurchaseSelectRaffle(ConversationState $state, string $text): WhatsAppReply|string
    {
        if ($state->current_raffle_id === null) {
            $raffles = $this->getActiveRaffles();

            if ($raffles->isEmpty()) {
                $this->resetToMainMenu($state);

                return 'No encontramos rifas activas para continuar.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
            }

            if ($raffles->count() > 1) {
                $selectedRaffle = $raffles->values()->get(((int) $text) - 1);

                if (! $selectedRaffle instanceof Raffle) {
                    return 'Responde con el número de la rifa que deseas comprar.'.PHP_EOL.PHP_EOL.$this->renderRaffleOptions($raffles);
                }

                $state->forceFill([
                    'status' => 'purchase_enter_quantity',
                    'current_raffle_id' => $selectedRaffle->id,
                ])->save();

                return $this->renderQuantityPrompt($selectedRaffle);
            }
        }

        if ($text === '1') {
            $raffle = $this->getConversationRaffle($state);

            if ($raffle === null) {
                $this->resetToMainMenu($state);

                return 'No encontramos una rifa activa para continuar. '.PHP_EOL.PHP_EOL.$this->renderMainMenu();
            }

            $state->forceFill([
                'status' => 'purchase_enter_quantity',
            ])->save();

            return $this->renderQuantityPrompt($raffle);
        }

        if ($text === '2') {
            $this->resetToMainMenu($state);

            return $this->renderMainMenu();
        }

        return 'Responde 1 para continuar o 2 para volver al menú.';
    }

    protected function handlePurchaseEnterQuantity(ConversationState $state, string $text): WhatsAppReply|string
    {
        $raffle = $this->getConversationRaffle($state);

        if ($raffle === null) {
            $this->resetToMainMenu($state);

            return $this->renderMainMenu();
        }

        if (! ctype_digit($text) || (int) $text < 1) {
            return 'Responde con una cantidad válida en números.'.PHP_EOL.PHP_EOL.'Ejemplo: 2';
        }

        $quantity = (int) $text;

        if ($quantity < $raffle->min_numbers_per_purchase) {
            return "Lo siento, la cantidad mínima para esta rifa es {$raffle->min_numbers_per_purchase} número(s).".PHP_EOL.PHP_EOL
                .'Intentémoslo de nuevo. ¿Cuántos números deseas comprar?';
        }

        $state->forceFill([
            'status' => 'purchase_choose_mode',
            'requested_quantity' => $quantity,
        ])->save();

        return $this->renderChooseMode();
    }

    protected function handlePurchaseChooseMode(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if (! in_array($text, ['1', '2'], true)) {
            return 'Responde 1 para elegir manualmente o 2 para asignación aleatoria.';
        }

        if ($text === '1') {
            $state->forceFill([
                'status' => 'purchase_select_numbers',
                'selection_mode' => 'manual',
            ])->save();

            $raffle = $this->getConversationRaffle($state);

            if ($raffle === null) {
                $this->resetToMainMenu($state);

                return $this->renderMainMenu();
            }

            return $this->renderNumberSelectionPrompt($raffle, (int) $state->requested_quantity, $state->customer);
        }

        $raffle = $this->getConversationRaffle($state);

        if ($raffle === null || $state->requested_quantity === null) {
            $this->resetToMainMenu($state);

            return $this->renderMainMenu();
        }

        try {
            $numbers = $this->selectRandomRaffleNumbersAction->execute($raffle, (int) $state->requested_quantity);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        try {
            $purchase = $this->reserveNumbersAction->execute($customer, $raffle, $numbers, 'random');
        } catch (InvalidArgumentException) {
            return 'No pudimos reservar los números aleatorios en este momento. Intenta nuevamente.';
        }

        $redirect = $this->buildConfirmedRedirectUrl($raffle, $numbers, $purchase, false);

        return $this->renderReservationConfirmation($purchase, $redirect['redirect_url'] ?? null);
    }

    protected function handlePurchaseSelectNumbers(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        $raffle = $this->getConversationRaffle($state);

        if ($raffle === null || $state->requested_quantity === null) {
            $this->resetToMainMenu($state);

            return $this->renderMainMenu();
        }

        $parsedNumbers = $this->parseManualNumbers($text, $raffle->normalizedNumberDigits());

        if ($parsedNumbers['error'] !== null) {
            return $parsedNumbers['error'];
        }

        $numbers = $parsedNumbers['numbers'];

        if (count($numbers) !== (int) $state->requested_quantity) {
            return "Debes enviar exactamente {$state->requested_quantity} número(s).".PHP_EOL.PHP_EOL
                .'Ejemplo: '.$this->renderNumberExamples((int) $state->requested_quantity, $raffle->normalizedNumberDigits());
        }

        try {
            $purchase = $this->reserveNumbersAction->execute($customer, $raffle, $numbers, 'manual');
        } catch (InvalidArgumentException) {
            return 'Uno o más números no están disponibles. Puedes elegir otros números o escribir MENU.';
        }

        $redirect = $this->buildConfirmedRedirectUrl($raffle, $numbers, $purchase, false);

        return $this->renderReservationConfirmation($purchase, $redirect['redirect_url'] ?? null);
    }

    protected function handlePaymentProofStep(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage): WhatsAppReply|string
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

                return $this->handleExpiredState($customer, $state, '');
            }

            if ($state->status === 'purchase_rejected') {
                $reminder = $purchase !== null
                    ? $this->renderPaymentWaitingReminder($purchase)
                    : 'Envía un nuevo comprobante por imagen para continuar.';

                return 'Tu pago fue rechazado. Envía un nuevo comprobante por imagen para continuar.'.PHP_EOL.PHP_EOL.$reminder;
            }

            if ($purchase === null) {
                return 'No encontramos una compra activa. Si deseas iniciar una nueva, escribe COMPRAR o MENU.';
            }

            return $this->renderPaymentWaitingReminder($purchase);
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

    protected function handlePurchaseUnderReview(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage, string $normText): WhatsAppReply|string
    {
        $purchase = $state->purchase;

        if ($purchase === null) {
            $purchase = Purchase::query()
                ->where('customer_id', $customer->id)
                ->latest('id')
                ->first();
        }

        if ($purchase === null || ! in_array($purchase->status, ['payment_submitted'], true)) {
            $this->resetToMainMenu($state);

            return 'Tu compra ya no está en revisión. Regresamos al menú principal.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
        }

        $pendingPayment = $purchase->payments()
            ->where('status', 'pending_review')
            ->latest('id')
            ->first();

        if ($this->isReplaceCommand($normText) || $inboundMessage->message_type === 'image') {
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

            if ($this->isReplaceCommand($normText)) {
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

    protected function handlePickerIntent(Customer $customer, ConversationState $state, string $token): WhatsAppReply|string
    {
        $intent = RafflePickerIntent::query()
            ->with('raffle')
            ->where('token', $token)
            ->first();

        if (! $intent instanceof RafflePickerIntent) {
            return 'No encontramos una selección visual válida para continuar. Vuelve al selector y genera una nueva selección.';
        }

        if ($intent->consumed_at !== null) {
            return 'Esta selección visual ya fue usada anteriormente. Si deseas continuar, vuelve al selector y genera una nueva selección.';
        }

        if ($intent->isExpired()) {
            return 'La selección visual ya venció. Vuelve al selector de números y genera una nueva selección para continuar.';
        }

        $raffle = $intent->raffle;

        if (! $raffle instanceof Raffle || $raffle->status !== 'published') {
            return 'La rifa asociada a esta selección ya no está disponible para compra.';
        }

        if ($state->purchase !== null && in_array($state->purchase->status, ['reserved', 'payment_submitted', 'under_review', 'rejected'], true)) {
            return 'Ya tienes una compra en curso. Envía tu comprobante, espera la revisión o escribe CANCELAR si deseas liberar tu reserva actual antes de iniciar otra compra.';
        }

        if (($onboardingReply = $this->redirectToPurchaseOnboardingIfNeeded($customer, $state, 'picker_intent', [
            'pending_picker_token' => $token,
        ])) !== null) {
            return $onboardingReply;
        }

        $numbers = collect($intent->selected_numbers_json ?? [])
            ->map(fn (mixed $number): string => (string) $number)
            ->filter()
            ->values()
            ->all();

        if ($numbers === [] || count($numbers) !== $intent->quantity) {
            return 'La selección visual no es válida para continuar. Vuelve al selector y genera una nueva selección.';
        }

        try {
            $purchase = $this->reserveNumbersAction->execute(
                $customer,
                $raffle,
                $numbers,
                'manual',
                $this->buildPickerTraceMetadata($intent)
            );
        } catch (InvalidArgumentException) {
            return 'La disponibilidad de los números seleccionados cambió antes de finalizar la compra. Vuelve al selector visual y elige nuevamente.';
        }

        $intent->forceFill([
            'consumed_at' => now(),
            'consumed_by_customer_id' => $customer->id,
        ])->save();

        $redirect = $this->buildConfirmedRedirectUrl($raffle, $numbers, $purchase, false);

        return $this->renderReservationConfirmation($purchase, $redirect['redirect_url'] ?? null);
    }

    protected function shouldReenterClosedPurchaseFlow(ConversationState $state, string $text): bool
    {
        if (! in_array($state->status, ['purchase_paid', 'purchase_expired'], true)) {
            return false;
        }

        if ($state->purchase !== null && in_array($state->purchase->status, ['reserved', 'payment_submitted', 'rejected'], true)) {
            return false;
        }

        return $text === ''
            || $this->isGreeting($text)
            || $this->isRepurchaseShortcut($text);
    }

    protected function handleClosedPurchaseReentry(ConversationState $state, string $text): WhatsAppReply|string
    {
        $previousStatus = $state->status;

        $this->resetToMainMenu($state);

        if ($this->isRepurchaseShortcut($text)) {
            return $this->handleMainMenu($state->customer, $state->fresh(), '1');
        }

        $intro = $previousStatus === 'purchase_paid'
            ? 'Tu compra anterior ya fue aprobada. Si quieres participar de nuevo, puedes iniciar otra compra desde aquí.'
            : 'Tu compra anterior ya terminó. Si quieres participar de nuevo, puedes iniciar otra compra desde aquí.';

        return $intro.PHP_EOL.PHP_EOL.$this->renderMainMenu();
    }

    protected function resetToMainMenu(ConversationState $state): void
    {
        $metadata = $state->metadata_json ?? [];
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
    }

    protected function handleExpiredState(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if ($text === '1') {
            if (($onboardingReply = $this->redirectToPurchaseOnboardingIfNeeded($customer, $state, 'purchase_start')) !== null) {
                return $onboardingReply;
            }

            $raffle = $this->getActiveRaffle();

            if ($raffle === null) {
                return 'Ahora mismo no tenemos una rifa activa disponible.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
            }

            $state->forceFill([
                'status' => 'purchase_select_raffle',
                'current_raffle_id' => $raffle->id,
            ])->save();

            return $this->renderRaffleSelection($raffle);
        }

        $body = 'Tu reserva ya venció. Los números que seleccionaste fueron liberados y pueden ser tomados por otras personas.'.PHP_EOL.PHP_EOL
            .'Responde 1 para iniciar una nueva compra, o escribe MENU para volver al inicio.';

        return WhatsAppReply::make($body, [
            ['id' => 'expired_new_purchase', 'title' => 'Comprar de nuevo'],
            ['id' => 'expired_menu', 'title' => 'Menú'],
        ]);
    }

    protected function redirectToPurchaseOnboardingIfNeeded(Customer $customer, ConversationState $state, string $pendingAction, array $metadata = []): ?string
    {
        $requiredStatus = $this->requiredPurchaseOnboardingStatus($customer);

        if ($requiredStatus === null) {
            return null;
        }

        $stateMetadata = $state->metadata_json ?? [];
        $stateMetadata = array_merge($stateMetadata, [
            'pending_action' => $pendingAction,
        ], $metadata);

        $state->forceFill([
            'status' => $requiredStatus,
            'metadata_json' => $stateMetadata,
        ])->save();

        return match ($requiredStatus) {
            'onboarding_privacy_consent' => $this->renderPrivacyConsentPrompt(),
            'onboarding_collect_name' => $this->renderCollectNamePrompt(),
            'onboarding_collect_document' => $this->renderCollectDocumentPrompt(),
            default => $this->renderMainMenu(),
        };
    }

    protected function requiredPurchaseOnboardingStatus(Customer $customer): ?string
    {
        if ($customer->accepted_privacy_at === null) {
            return 'onboarding_privacy_consent';
        }

        if (! filled($customer->name)) {
            return 'onboarding_collect_name';
        }

        if (! filled($customer->document_number)) {
            return 'onboarding_collect_document';
        }

        return null;
    }

    protected function handleOnboardingPrivacyConsent(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if (in_array($text, ['1', 'ACEPTO', 'ACEPTAR', 'SI ACEPTO', 'SÍ ACEPTO'], true)) {
            $customer->forceFill([
                'accepted_privacy_at' => now(),
            ])->save();

            $state->forceFill([
                'status' => 'onboarding_collect_name',
            ])->save();

            return $this->renderCollectNamePrompt();
        }

        if (in_array($text, ['2', 'NO ACEPTO', 'NO', 'RECHAZO'], true)) {
            $this->resetToMainMenu($state);

            return 'Entendido. No continuaremos con la compra sin tu autorización.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
        }

        return $this->renderPrivacyConsentPrompt();
    }

    protected function handleOnboardingCollectName(Customer $customer, ConversationState $state, string $rawText): WhatsAppReply|string
    {
        $name = trim($rawText);

        if (Str::length($name) < 2) {
            return $this->renderCollectNamePrompt();
        }

        $customer->forceFill([
            'name' => $name,
        ])->save();

        $nextStatus = $this->requiredPurchaseOnboardingStatus($customer);

        if ($nextStatus === null) {
            return $this->resumePendingAction($customer, $state);
        }

        $state->forceFill([
            'status' => $nextStatus,
        ])->save();

        return match ($nextStatus) {
            'onboarding_collect_document' => $this->renderCollectDocumentPrompt(),
            default => $this->renderMainMenu(),
        };
    }

    protected function handleOnboardingCollectDocument(Customer $customer, ConversationState $state, string $rawText): WhatsAppReply|string
    {
        $digits = preg_replace('/\D+/', '', $rawText) ?? '';

        if ($digits === '' || ! ctype_digit($digits) || strlen($digits) < 5 || strlen($digits) > 15) {
            return $this->renderCollectDocumentPrompt();
        }

        $customer->forceFill([
            'document_number' => $digits,
        ])->save();

        $nextStatus = $this->requiredPurchaseOnboardingStatus($customer);

        if ($nextStatus !== null) {
            $state->forceFill([
                'status' => $nextStatus,
            ])->save();

            return match ($nextStatus) {
                'onboarding_collect_name' => $this->renderCollectNamePrompt(),
                default => $this->renderMainMenu(),
            };
        }

        return $this->resumePendingAction($customer, $state);
    }

    protected function resumePendingAction(Customer $customer, ConversationState $state): string
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
            'purchase_start' => $this->handleMainMenu($customer, $state->fresh(), '1'),
            'picker_intent' => $pendingPickerToken !== '' ? $this->handlePickerIntent($customer, $state->fresh(), $pendingPickerToken) : $this->renderMainMenu(),
            default => $this->renderMainMenu(),
        };
    }

    protected function shouldRecoverCompletedOnboarding(Customer $customer, ConversationState $state): bool
    {
        if (! in_array($state->status, [
            'onboarding_privacy_consent',
            'onboarding_collect_name',
            'onboarding_collect_document',
        ], true)) {
            return false;
        }

        return $this->requiredPurchaseOnboardingStatus($customer) === null;
    }

    protected function recoverCompletedOnboarding(Customer $customer, ConversationState $state, string $text): string
    {
        $pendingAction = (string) (($state->metadata_json ?? [])['pending_action'] ?? '');

        if ($pendingAction !== '') {
            return $this->resumePendingAction($customer, $state);
        }

        $this->resetToMainMenu($state);

        return in_array($text, ['1', '2', '3', '4', '5', '6', '7'], true)
            ? $this->handleMainMenu($customer, $state->fresh(), $text)
            : $this->renderMainMenu();
    }

    protected function renderPrivacyConsentPrompt(): WhatsAppReply|string
    {
        $body = 'Antes de continuar con tu compra necesitamos tu autorización para el tratamiento de datos personales y la aceptación de las condiciones de compra (nombre y cédula) con el fin de gestionar tu participación.'.PHP_EOL.PHP_EOL
            .'Responde ACEPTAR o NO ACEPTAR, o usa los botones de abajo.';

        return WhatsAppReply::make($body, [
            ['id' => 'privacy_accept', 'title' => 'Aceptar'],
            ['id' => 'privacy_reject', 'title' => 'No acepto'],
        ]);
    }

    protected function renderCollectNamePrompt(): WhatsAppReply|string
    {
        return 'Para continuar con tu compra, por favor responde con tu nombre completo.';
    }

    protected function renderCollectDocumentPrompt(): WhatsAppReply|string
    {
        return 'Ahora responde con tu número de cédula (solo números).';
    }

    protected function getActiveRaffle(): ?Raffle
    {
        return $this->getActiveRaffles()->first();
    }

    /**
     * @return Collection<int, Raffle>
     */
    protected function getActiveRaffles(): Collection
    {
        return Raffle::query()
            ->where('status', 'published')
            ->orderBy('draw_date')
            ->orderBy('draw_time')
            ->get()
            ->filter(fn (Raffle $raffle): bool => $raffle->salesAreOpen())
            ->values();
    }

    protected function getConversationRaffle(ConversationState $state): ?Raffle
    {
        return $state->currentRaffle ?: $this->getActiveRaffle();
    }

    /**
     * @return list<string>
     */
    protected function extractNumbers(string $text, int $digits): array
    {
        return collect(preg_split('/[\s,;]+/', trim($text)) ?: [])
            ->filter()
            ->map(function (string $value) use ($digits): string {
                return str_pad($value, $digits, '0', STR_PAD_LEFT);
            })
            ->values()
            ->all();
    }

    /**
     * @return array{numbers: list<string>, error: string|null}
     */
    protected function parseManualNumbers(string $text, int $digits): array
    {
        $rawTokens = collect(preg_split('/[\s,;]+/', trim($text)) ?: [])
            ->filter(fn (string $value): bool => $value !== '')
            ->values();

        if ($rawTokens->isEmpty()) {
            return [
                'numbers' => [],
                'error' => 'No identificamos números válidos en tu mensaje.'.PHP_EOL.PHP_EOL
                    .'Para continuar vuelve al enlace "Escoge aquí tus números" o responde solo con los dígitos separados por coma o espacio. Ejemplo: '.$this->renderNumberExamples(2, $digits),
            ];
        }

        $invalidTokens = $rawTokens
            ->filter(fn (string $value): bool => ! ctype_digit($value) || strlen($value) > $digits)
            ->values()
            ->all();

        if ($invalidTokens !== []) {
            return [
                'numbers' => [],
                'error' => 'Encontramos valores inválidos: '.implode(', ', $invalidTokens).'.'.PHP_EOL.PHP_EOL
                    ."Cada número debe contener solo dígitos y tener hasta {$digits} cifra(s)."
                    .PHP_EOL.'Ejemplo: '.$this->renderNumberExamples(2, $digits),
            ];
        }

        $numbers = $this->extractNumbers($rawTokens->implode(','), $digits);
        $duplicates = collect($numbers)
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->values()
            ->all();

        if ($duplicates !== []) {
            return [
                'numbers' => [],
                'error' => 'No puedes repetir números en la misma compra. Duplicados detectados: '.implode(', ', $duplicates).'.',
            ];
        }

        return [
            'numbers' => $numbers,
            'error' => null,
        ];
    }

    protected function renderMainMenu(): WhatsAppReply|string
    {
        $welcome = $this->resolvePublishedContentAction->byKey(
            'system.menu.welcome',
            [],
            'Hola, soy el asistente de Rifax. Responde con la opción que necesitas o usa los botones de abajo.',
        );

        $body = $welcome.PHP_EOL.PHP_EOL
            .'Estas son tus opciones:'.PHP_EOL
            .'1. Comprar'.PHP_EOL
            .'2. Números disponibles'.PHP_EOL
            .'3. Mis números'.PHP_EOL
            .'4. Estadísticas'.PHP_EOL
            .'5. Próximas rifas'.PHP_EOL
            .'6. Condiciones'.PHP_EOL
            .'7. Ayuda'.PHP_EOL.PHP_EOL
            .'Responde con el número de la opción o toca un botón.';

        return WhatsAppReply::make($body, [
            ['id' => 'menu_1', 'title' => 'Comprar'],
            ['id' => 'menu_3', 'title' => 'Mis números'],
            ['id' => 'menu_7', 'title' => 'Ayuda'],
        ]);
    }

    protected function renderRaffleSelection(Raffle $raffle): WhatsAppReply|string
    {
        $body = 'Tenemos esta rifa activa:'.PHP_EOL
            ."{$raffle->title}".PHP_EOL.PHP_EOL
            .'Valor por número: '.$this->formatMoneyWithoutDecimals($raffle->price_per_number).PHP_EOL
            .$this->formatLotteryReference($raffle).PHP_EOL
            .'Fecha: '.$this->formatRaffleDrawDate($raffle).PHP_EOL.PHP_EOL
            .'Responde:'.PHP_EOL
            .'1. Continuar'.PHP_EOL
            .'2. Volver al menú';

        return WhatsAppReply::make($body, [
            ['id' => 'raffle_continue', 'title' => 'Continuar'],
            ['id' => 'raffle_back', 'title' => 'Menú'],
        ]);
    }

    /**
     * @param  Collection<int, Raffle>  $raffles
     */
    protected function renderRaffleOptions(Collection $raffles): WhatsAppReply|string
    {
        $options = $raffles
            ->values()
            ->map(function (Raffle $raffle, int $index): string {
                $position = $index + 1;

                return $position.'. '.$raffle->title
                    .' | Valor: '.$this->formatMoneyWithoutDecimals($raffle->price_per_number)
                    .' | '.$this->formatLotteryReference($raffle)
                    .' | Fecha: '.$this->formatRaffleDrawDate($raffle);
            })
            ->implode(PHP_EOL);

        return 'Tenemos varias rifas activas disponibles.'.PHP_EOL.PHP_EOL
            .$options.PHP_EOL.PHP_EOL
            .'Responde con el número de la rifa que deseas comprar o escribe MENU.';
    }

    protected function renderQuantityPrompt(Raffle $raffle): WhatsAppReply|string
    {
        return '¿Cuántos números deseas comprar?'.PHP_EOL.PHP_EOL
            ."Compra mínima para esta rifa: {$raffle->min_numbers_per_purchase}";
    }

    protected function renderChooseMode(): WhatsAppReply|string
    {
        $body = '¿Cómo deseas elegir tus números?'.PHP_EOL
            .'1. Elegir manualmente'.PHP_EOL
            .'2. Asignación aleatoria';

        return WhatsAppReply::make($body, [
            ['id' => 'mode_manual', 'title' => 'Elegir yo'],
            ['id' => 'mode_random', 'title' => 'Aleatorio'],
        ]);
    }

    protected function renderNumberSelectionPrompt(Raffle $raffle, int $quantity, ?Customer $customer = null): WhatsAppReply|string
    {
        $query = [
            'raffle' => $raffle->slug,
            'quantity' => $quantity,
            'source' => 'whatsapp_manual_prompt',
        ];
        if ($customer instanceof Customer && ($customer->phone !== null || $customer->wa_id !== null)) {
            try {
                $query['pt'] = PickerAuthToken::generate($customer);
            } catch (\Throwable) {
                unset($query['pt']);
            }
        }
        $pickerUrl = route('raffles.number-picker', $query);

        return 'Escoge aquí tus números:'.PHP_EOL.$pickerUrl;
    }

    protected function renderNumberExamples(int $quantity, int $digits): WhatsAppReply|string
    {
        $count = max(2, min($quantity, 3));

        return collect(range(1, $count))
            ->map(fn (int $value): string => str_pad((string) $value, $digits, '0', STR_PAD_LEFT))
            ->implode(',');
    }

    protected function renderReservationConfirmation(Purchase $purchase, ?string $confirmedUrl = null): WhatsAppReply|string
    {
        $reservedNumbers = $purchase->numbers->pluck('number')->implode(', ');
        $paymentInstructions = $this->renderPaymentInstructionsList($purchase);

        $message = 'Listo. Tu reserva fue creada correctamente.'.PHP_EOL.PHP_EOL
            .'Números reservados: '.$reservedNumbers.PHP_EOL
            .'Total a pagar: '.$this->formatMoneyWithoutDecimals($purchase->total_amount).PHP_EOL
            .$this->renderReservationWindowMessage($purchase);

        if ($paymentInstructions !== '') {
            $message .= PHP_EOL.PHP_EOL
                .'Opciones de pago disponibles:'.PHP_EOL.PHP_EOL
                .$paymentInstructions;
        }

        $body = $message.PHP_EOL.PHP_EOL
            .'Después de pagar, envía una foto clara del comprobante por este chat para continuar.';

        if (is_string($confirmedUrl) && $confirmedUrl !== '') {
            $body .= PHP_EOL.PHP_EOL.'⏱️ Mira el tiempo restante en vivo y continúa el proceso aquí:'.$confirmedUrl;
        }

        return WhatsAppReply::make($body, [
            ['id' => 'cancel_purchase', 'title' => 'Cancelar'],
            ['id' => 'payment_menu', 'title' => 'Menú'],
        ]);
    }

    /**
     * @param  list<string>  $numbers
     * @return array{redirect_url:string}
     */
    protected function buildConfirmedRedirectUrl(Raffle $raffle, array $numbers, ?Purchase $purchase, bool $requiresOnboarding): array
    {
        $params = [
            'numbers' => array_values(array_map('strval', $numbers)),
            'requires_onboarding' => $requiresOnboarding ? '1' : '0',
        ];

        if ($purchase instanceof Purchase && $purchase->exists) {
            $params['amount'] = (string) $purchase->total_amount;
            $params['unit'] = (string) $purchase->unit_price;
            if ($purchase->reserved_until !== null) {
                try {
                    $until = $purchase->reserved_until->tz('America/Bogota');
                    $params['until'] = $until->toIso8601String();
                } catch (\Throwable) {
                    $params['until'] = $purchase->reserved_until->toIso8601String();
                }
            }
            $params['ref'] = 'PUR-'.$purchase->id;
        }

        return [
            'redirect_url' => URL::temporarySignedRoute(
                'raffles.number-picker.confirmed',
                now()->addMinutes(60),
                array_merge(['raffle' => $raffle->slug], $params)
            ),
        ];
    }

    protected function renderReservationWindowMessage(Purchase $purchase): WhatsAppReply|string
    {
        $purchase->loadMissing('raffle');

        $minutes = $purchase->raffle?->reservation_timeout_minutes;

        if (! is_int($minutes) || $minutes < 1) {
            $minutes = $purchase->reserved_until?->diffInMinutes(now());
        }

        if (! is_int($minutes) || $minutes < 1) {
            return 'Tu reserva quedará activa por tiempo limitado.';
        }

        $minuteLabel = $minutes === 1 ? 'minuto' : 'minutos';

        return "Tienes {$minutes} {$minuteLabel} para enviar tu comprobante de pago antes de que los números sean liberados nuevamente.";
    }

    protected function renderPaymentWaitingReminder(?Purchase $purchase): WhatsAppReply|string
    {
        $body = (! $purchase instanceof Purchase)
            ? 'Envía tu comprobante de pago por imagen en este chat para continuar.'
            : (function () use ($purchase): string {
                $paymentInstructions = $this->renderPaymentInstructionsList($purchase);
                if ($paymentInstructions === '') {
                    return 'Envía tu comprobante de pago por imagen en este chat para continuar.';
                }

                return 'Aún estamos esperando tu comprobante de pago.'.PHP_EOL.PHP_EOL
                    .'Te recuerdo las opciones de pago disponibles para esta compra:'.PHP_EOL.PHP_EOL
                    .$paymentInstructions.PHP_EOL.PHP_EOL
                    .'Cuando completes el pago, envía una foto clara del comprobante por este chat.';
            })();

        return WhatsAppReply::make($body, [
            ['id' => 'cancel_purchase', 'title' => 'Cancelar'],
            ['id' => 'payment_help', 'title' => 'Ayuda'],
            ['id' => 'payment_menu', 'title' => 'Menú'],
        ]);
    }

    protected function renderPaymentInstructionsList(Purchase $purchase): WhatsAppReply|string
    {
        $snapshot = collect($purchase->payment_instructions_snapshot ?? [])
            ->map(function (mixed $method, int $index): ?string {
                if (! is_array($method)) {
                    return null;
                }

                return $this->renderPaymentInstructionEntry($method, $index + 1);
            })
            ->filter()
            ->values();

        if ($snapshot->isNotEmpty()) {
            return $snapshot->implode(PHP_EOL.PHP_EOL);
        }

        return PaymentMethod::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->map(fn (PaymentMethod $paymentMethod, int $index): string => $this->renderPaymentInstructionEntry([
                'name' => $paymentMethod->name,
                'instructions' => $paymentMethod->instructions,
                'account_holder' => $paymentMethod->account_holder,
                'account_reference' => $paymentMethod->account_reference,
                'details' => $paymentMethod->details_json,
            ], $index + 1))
            ->filter()
            ->implode(PHP_EOL.PHP_EOL);
    }

    /**
     * @param  array{name?: mixed, instructions?: mixed, account_holder?: mixed, account_reference?: mixed, details?: mixed}  $method
     */
    protected function renderPaymentInstructionEntry(array $method, ?int $index = null): WhatsAppReply|string
    {
        $lines = [];
        $name = trim((string) ($method['name'] ?? ''));
        $accountHolder = trim((string) ($method['account_holder'] ?? ''));
        $accountReference = trim((string) ($method['account_reference'] ?? ''));
        $instructions = trim((string) ($method['instructions'] ?? ''));
        $details = is_array($method['details'] ?? null) ? $method['details'] : [];

        if ($index !== null) {
            $lines[] = $name !== ''
                ? "{$index}. {$name}"
                : "{$index}. Método de pago";
        } elseif ($name !== '') {
            $lines[] = $name;
        }

        if ($accountHolder !== '') {
            $lines[] = "Titular: {$accountHolder}";
        }

        if ($accountReference !== '') {
            $lines[] = "Número de cuenta: {$accountReference}";
        }

        foreach ($details as $key => $value) {
            $normalizedValue = trim((string) $value);

            if ($normalizedValue === '') {
                continue;
            }

            $label = Str::of((string) $key)
                ->replace('_', ' ')
                ->squish()
                ->title()
                ->toString();

            $lines[] = "{$label}: {$normalizedValue}";
        }

        if ($instructions !== '') {
            $lines[] = "Cómo pagar: {$instructions}";
        }

        return implode(PHP_EOL, $lines);
    }

    protected function renderAvailableNumbers(): WhatsAppReply|string
    {
        $raffles = $this->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return 'Ahora mismo no tenemos una rifa activa disponible.';
        }

        if ($raffles->count() === 1) {
            $raffle = $raffles->first();
            $availableCount = $raffle?->numbers()->where('status', 'available')->count();

            return "La rifa {$raffle->title} tiene {$availableCount} número(s) disponibles.".PHP_EOL.PHP_EOL.'Si deseas comprar, responde 1.';
        }

        $summary = $raffles
            ->map(function (Raffle $raffle): string {
                $availableCount = $raffle->numbers()->where('status', 'available')->count();

                return "- {$raffle->title}: {$availableCount} número(s) disponibles";
            })
            ->implode(PHP_EOL);

        return 'Estas son las rifas activas y su disponibilidad actual:'.PHP_EOL
            .$summary.PHP_EOL.PHP_EOL
            .'Si deseas comprar, responde 1.';
    }

    protected function renderMyNumbers(Customer $customer): WhatsAppReply|string
    {
        $summary = $customer->purchases()
            ->with('numbers')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Purchase $purchase): string {
                $numbers = $purchase->numbers->pluck('number')->implode(', ');

                return "- Compra {$purchase->id}: {$purchase->status} | Números: {$numbers}";
            })
            ->implode(PHP_EOL);

        return $summary !== ''
            ? 'Estas son tus compras registradas:'.PHP_EOL.$summary
            : 'Aún no tienes compras registradas.';
    }

    protected function renderStatistics(): WhatsAppReply|string
    {
        $raffles = $this->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return 'Ahora mismo no tenemos una rifa activa disponible.';
        }

        if ($raffles->count() === 1) {
            $raffle = $raffles->first();
            $availableCount = $raffle?->numbers()->where('status', 'available')->count();
            $soldCount = $raffle?->numbers()->where('status', 'paid')->count();

            return "Estado actual de {$raffle->title}:".PHP_EOL
                ."- Vendidos: {$soldCount}".PHP_EOL
                ."- Disponibles: {$availableCount}".PHP_EOL
                .'- '.$this->formatLotteryReference($raffle).PHP_EOL
                .'- Fecha: '.$this->formatRaffleDrawDate($raffle);
        }

        $summary = $raffles
            ->map(function (Raffle $raffle): string {
                $availableCount = $raffle->numbers()->where('status', 'available')->count();
                $soldCount = $raffle->numbers()->where('status', 'paid')->count();

                return $raffle->title.PHP_EOL
                    ."- Vendidos: {$soldCount}".PHP_EOL
                    ."- Disponibles: {$availableCount}".PHP_EOL
                    .'- '.$this->formatLotteryReference($raffle).PHP_EOL
                    .'- Fecha: '.$this->formatRaffleDrawDate($raffle);
            })
            ->implode(PHP_EOL.PHP_EOL);

        return 'Estas son las estadísticas de las rifas activas:'.PHP_EOL.PHP_EOL.$summary;
    }

    protected function renderConditions(): WhatsAppReply|string
    {
        return $this->resolvePublishedContentAction->byIntent(
            'terms_conditions',
            [],
            'Estas son las condiciones principales de la rifa:'.PHP_EOL
            .'- La compra se realiza por este chat.'.PHP_EOL
            .'- Los números se reservan por tiempo limitado.'.PHP_EOL
            .'- El pago se confirma manualmente.'.PHP_EOL
            .'- El boleto se envía cuando el pago es aprobado.'.PHP_EOL.PHP_EOL
            .'Si deseas comprar, responde 1. Si deseas volver al menú, escribe MENU.',
        );
    }

    protected function renderHelp(): WhatsAppReply|string
    {
        return $this->resolvePublishedContentAction->byIntent(
            'help_support',
            [],
            'Puedo ayudarte con:'.PHP_EOL
            .'1. Condiciones de la rifa'.PHP_EOL
            .'2. Métodos de pago'.PHP_EOL
            .'3. Estado de tu compra'.PHP_EOL
            .'4. Hablar con soporte',
        );
    }

    protected function renderUpcomingRaffles(): WhatsAppReply|string
    {
        $base = $this->resolvePublishedContentAction->byIntent(
            'upcoming_raffles',
            [],
            'Pronto compartiremos las próximas rifas disponibles. Escribe MENU para volver.',
        );

        $raffles = $this->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return $base;
        }

        $summary = $raffles
            ->map(fn (Raffle $raffle): string => "- {$raffle->title}: ".$this->formatRaffleDrawDate($raffle).' | '.$this->formatLotteryReference($raffle))
            ->implode(PHP_EOL);

        return $base.PHP_EOL.PHP_EOL.'Rifas activas actuales:'.PHP_EOL.$summary;
    }

    protected function formatMoneyWithoutDecimals(mixed $amount): string
    {
        if (! is_numeric($amount)) {
            return '$0';
        }

        return '$'.number_format((float) $amount, 0, ',', '.');
    }

    protected function formatRaffleDrawDate(Raffle $raffle): string
    {
        $drawAt = $raffle->drawAt();

        if ($drawAt === null) {
            return 'Fecha pendiente por confirmar';
        }

        $drawAt = $drawAt->copy()->locale('es');
        $month = Str::ucfirst($drawAt->translatedFormat('F'));

        return $drawAt->translatedFormat('j').' de '.$month.' de '.$drawAt->translatedFormat('Y')
            .' a las '.$drawAt->format('g:i A');
    }

    protected function formatLotteryReference(Raffle $raffle): string
    {
        $lotteryName = trim((string) ($raffle->lottery_name ?? ''));
        $lotteryText = trim((string) ($raffle->lottery_text ?? ''));
        $drawNumber = trim((string) ($raffle->lottery_draw_number ?? ''));

        if ($lotteryText !== '') {
            $segments = [];
            $headline = trim($lotteryText.' '.$lotteryName);

            if ($headline !== '') {
                $segments[] = $headline;
            }

            if ($drawNumber !== '') {
                $segments[] = 'sorteo #'.$drawNumber;
            }

            return implode(' ', $segments);
        }

        if ($lotteryName === '') {
            return $drawNumber !== ''
                ? 'Sorteo: #'.$drawNumber
                : 'Sorteo: Referencia pendiente';
        }

        return $drawNumber !== ''
            ? "Sorteo: {$lotteryName} #{$drawNumber}"
            : "Sorteo: {$lotteryName}";
    }

    protected function extractPickerIntentToken(string $text): ?string
    {
        if (! preg_match('/(?:^|[\s:>-])PICKER\s+([A-Z0-9]+)(?:$|\b)/', $text, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPickerTraceMetadata(RafflePickerIntent $intent): array
    {
        $trace = collect($intent->metadata_json ?? [])
            ->filter(function (mixed $value): bool {
                if (is_array($value)) {
                    return $value !== [];
                }

                return $value !== null && $value !== '';
            })
            ->all();

        return [
            'picker_trace' => array_merge($trace, [
                'source' => $intent->source ?: 'picker_direct',
                'intent_token' => $intent->token,
                'quantity' => $intent->quantity,
                'selected_numbers' => $intent->selected_numbers_json ?? [],
            ]),
        ];
    }

    protected function normalizeKeywordText(string $text): string
    {
        $normalized = Str::upper(trim($text));

        if (function_exists('normalizer_normalize')) {
            $decomposed = normalizer_normalize($normalized, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                $normalized = (string) preg_replace('/\p{Mn}/u', '', $decomposed);
            }
        }

        $withoutPunct = (string) preg_replace('/[^A-Z0-9Ñ\s]+/u', ' ', $normalized);
        $withoutPunct = (string) preg_replace('/\s+/u', ' ', $withoutPunct);

        return trim($withoutPunct);
    }

    /**
     * @param  list<string>  $keywords
     */
    protected function containsAnyKeyword(string $normText, array $keywords): bool
    {
        if ($normText === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            if (str_contains($normText, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function isMenuCommand(string $normText): bool
    {
        return $normText === 'MENU'
            || $this->containsAnyKeyword($normText, ['MENU', 'VOLVER AL MENU', 'INICIO', 'REGRESAR AL INICIO']);
    }

    protected function isCancelCommand(string $normText): bool
    {
        return $normText === 'CANCELAR'
            || $this->containsAnyKeyword($normText, [
                'CANCELAR', 'CANCELA', 'CANCELO', 'ANULAR', 'ANULA', 'ANULO',
                'CANCELAR COMPRA', 'ANULAR COMPRA', 'CANCELAR RESERVA', 'LIBERAR NUMEROS',
            ]);
    }

    protected function isReplaceCommand(string $normText): bool
    {
        return $normText === 'REEMPLAZAR'
            || $this->containsAnyKeyword($normText, [
                'REEMPLAZAR', 'REEMPLAZA', 'REEMPLAZO', 'CAMBIAR COMPROBANTE', 'CAMBIO COMPROBANTE',
                'NUEVO COMPROBANTE', 'OTRO COMPROBANTE', 'CORREGIR COMPROBANTE',
            ]);
    }

    protected function isFaqShortcut(string $normText): bool
    {
        if ($normText === '') {
            return false;
        }

        return $this->containsAnyKeyword($normText, ['AYUDA', 'CONDICIONES', 'PAGOS', 'METODOS DE PAGO', 'SORTEO']);
    }

    protected function isGreeting(string $normText): bool
    {
        if ($normText === '') {
            return false;
        }

        return $this->containsAnyKeyword($normText, [
            'HOLA', 'BUENAS', 'BUEN DIA', 'BUENOS DIAS', 'BUENAS TARDES', 'BUENAS NOCHES', 'HELLO', 'QUE TAL',
        ]);
    }

    protected function isRepurchaseShortcut(string $normText): bool
    {
        if ($normText === '') {
            return false;
        }

        if ($normText === '1') {
            return true;
        }

        return $this->containsAnyKeyword($normText, [
            'COMPRAR', 'QUIERO COMPRAR', 'COMPRAR DE NUEVO', 'VOLVER A COMPRAR',
            'NUEVA COMPRA', 'OTRA VEZ', 'OTRA COMPRA', 'PARTICIPAR DE NUEVO', 'QUIERO OTRO',
        ]);
    }

    protected function handleFaqShortcut(ConversationState $state, string $text): WhatsAppReply|string
    {
        $norm = $this->normalizeKeywordText($text);

        return match (true) {
            $this->containsAnyKeyword($norm, ['AYUDA']) => $this->renderHelp(),
            $this->containsAnyKeyword($norm, ['CONDICIONES']) => $this->renderConditions(),
            $this->containsAnyKeyword($norm, ['PAGOS', 'METODOS DE PAGO']) => $this->renderPaymentMethods(),
            $this->containsAnyKeyword($norm, ['SORTEO']) => $this->renderDrawDate($state),
            default => $this->renderMainMenu(),
        };
    }

    protected function renderPaymentMethods(): WhatsAppReply|string
    {
        $base = $this->resolvePublishedContentAction->byIntent(
            'payment_methods',
            [],
            'Puedes pagar usando los métodos habilitados por la empresa. Te enviaremos las instrucciones exactas dentro del flujo de compra.',
        );

        $methods = PaymentMethod::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentMethod $paymentMethod): string => "- {$paymentMethod->name}: {$paymentMethod->account_reference}")
            ->implode(PHP_EOL);

        return $methods !== ''
            ? $base.PHP_EOL.PHP_EOL.$methods
            : $base;
    }

    protected function renderDrawDate(ConversationState $state): WhatsAppReply|string
    {
        $raffle = $state->currentRaffle;

        if ($raffle !== null) {
            return $this->resolvePublishedContentAction->byIntent(
                'draw_date',
                [
                    'raffle_title' => $raffle->title,
                    'draw_date' => $raffle->draw_date?->format('Y-m-d'),
                    'draw_time' => $raffle->draw_time,
                    'lottery_name' => $raffle->lottery_name,
                    'lottery_draw_number' => $raffle->lottery_draw_number,
                ],
                "El sorteo de {$raffle->title} se realiza el {$raffle->draw_date?->format('Y-m-d')} a las {$raffle->draw_time}.",
            );
        }

        $raffles = $this->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return 'Ahora mismo no tenemos una rifa activa disponible.';
        }

        if ($raffles->count() === 1) {
            $singleRaffle = $raffles->first();

            return $this->resolvePublishedContentAction->byIntent(
                'draw_date',
                [
                    'raffle_title' => $singleRaffle->title,
                    'draw_date' => $singleRaffle->draw_date?->format('Y-m-d'),
                    'draw_time' => $singleRaffle->draw_time,
                    'lottery_name' => $singleRaffle->lottery_name,
                    'lottery_draw_number' => $singleRaffle->lottery_draw_number,
                ],
                "El sorteo de {$singleRaffle->title} se realiza el {$singleRaffle->draw_date?->format('Y-m-d')} a las {$singleRaffle->draw_time}.",
            );
        }

        $summary = $raffles
            ->map(fn (Raffle $activeRaffle): string => "- {$activeRaffle->title}: {$activeRaffle->draw_date?->format('Y-m-d')} {$activeRaffle->draw_time} | {$activeRaffle->lottery_name} #{$activeRaffle->lottery_draw_number}")
            ->implode(PHP_EOL);

        return 'Estas son las fechas de sorteo de las rifas activas:'.PHP_EOL.$summary;
    }
}
