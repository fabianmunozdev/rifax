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
use Illuminate\Support\Arr;
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
    ) {
    }

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

        $outboundMessage = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => $reply,
            'payload_json' => ['text' => ['body' => $reply]],
            'status' => 'generated',
            'provider_created_at' => now(),
        ]);

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
        $waId = (string) (Arr::get($contacts, '0.wa_id') ?: Arr::get($message, 'from'));
        $digits = preg_replace('/\D+/', '', $waId) ?? $waId;
        $phone = Str::startsWith($digits, '+') ? $digits : '+'.$digits;
        $name = trim((string) Arr::get($contacts, '0.profile.name', ''));

        $customer = Customer::query()->firstOrNew([
            'phone' => $phone,
        ]);

        if ($name !== '') {
            $customer->name = $name;
        }

        $customer->wa_id = $digits;
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

        return [
            'type' => $type,
            'body_text' => match ($type) {
                'text' => Arr::get($message, 'text.body'),
                'image' => Arr::get($message, 'image.caption'),
                default => null,
            },
            'provider_message_id' => Arr::get($message, 'id'),
        ];
    }

    protected function resolveReply(Customer $customer, ConversationState $state, WhatsappMessage $inboundMessage): string
    {
        $rawText = trim((string) ($inboundMessage->body_text ?? ''));
        $text = trim(Str::upper($rawText));

        if ($inboundMessage->message_type === 'image' && ! in_array($state->status, ['purchase_payment_instructions', 'purchase_rejected'], true)) {
            return 'Recibimos una imagen, pero ahora mismo no estamos esperando un comprobante de pago.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
        }

        if ($this->isFaqShortcut($text)) {
            return $this->handleFaqShortcut($state, $text);
        }

        if ($text === 'MENU') {
            $this->resetToMainMenu($state);

            return $this->renderMainMenu();
        }

        if ($text === 'CANCELAR') {
            $result = $this->cancelPurchaseFlowAction->execute($state);

            return $result['cancelled']
                ? 'Proceso cancelado y reserva liberada correctamente.'.PHP_EOL.PHP_EOL.$this->renderMainMenu()
                : 'Proceso cancelado.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
        }

        if (($pickerToken = $this->extractPickerIntentToken($text)) !== null) {
            return $this->handlePickerIntent($customer, $state, $pickerToken);
        }

        if ($this->shouldReenterClosedPurchaseFlow($state, $text)) {
            return $this->handleClosedPurchaseReentry($state, $text);
        }

        return match ($state->status) {
            'main_menu' => $this->handleMainMenu($customer, $state, $text),
            'purchase_select_raffle' => $this->handlePurchaseSelectRaffle($state, $text),
            'purchase_enter_quantity' => $this->handlePurchaseEnterQuantity($state, $text),
            'purchase_choose_mode' => $this->handlePurchaseChooseMode($customer, $state, $text),
            'purchase_select_numbers' => $this->handlePurchaseSelectNumbers($customer, $state, $text),
            'purchase_payment_instructions', 'purchase_rejected' => $this->handlePaymentProofStep($state, $inboundMessage),
            'purchase_under_review' => 'Tu compra sigue en revision. Te avisaremos por este medio cuando tengamos una respuesta.',
            'purchase_paid' => 'Tu compra ya fue aprobada. Muy pronto podras consultar tu boleto desde este chat.',
            'purchase_expired' => $this->handleExpiredState($customer, $state, $text),
            'onboarding_privacy_consent' => $this->handleOnboardingPrivacyConsent($customer, $state, $text),
            'onboarding_collect_name' => $this->handleOnboardingCollectName($customer, $state, $rawText),
            'onboarding_collect_document' => $this->handleOnboardingCollectDocument($customer, $state, $rawText),
            default => $this->renderMainMenu(),
        };
    }

    protected function handleMainMenu(Customer $customer, ConversationState $state, string $text): string
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

    protected function handlePurchaseSelectRaffle(ConversationState $state, string $text): string
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
                    return 'Responde con el numero de la rifa que deseas comprar.'.PHP_EOL.PHP_EOL.$this->renderRaffleOptions($raffles);
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

        return 'Responde 1 para continuar o 2 para volver al menu.';
    }

    protected function handlePurchaseEnterQuantity(ConversationState $state, string $text): string
    {
        $raffle = $this->getConversationRaffle($state);

        if ($raffle === null) {
            $this->resetToMainMenu($state);

            return $this->renderMainMenu();
        }

        if (! ctype_digit($text) || (int) $text < 1) {
            return 'Responde con una cantidad valida en numeros.'.PHP_EOL.PHP_EOL.'Ejemplo: 2';
        }

        $quantity = (int) $text;

        if ($quantity < $raffle->min_numbers_per_purchase) {
            return "La cantidad minima permitida para esta rifa es {$raffle->min_numbers_per_purchase} numero(s).".PHP_EOL.PHP_EOL.'Por favor responde con una cantidad igual o mayor.';
        }

        $state->forceFill([
            'status' => 'purchase_choose_mode',
            'requested_quantity' => $quantity,
        ])->save();

        return $this->renderChooseMode();
    }

    protected function handlePurchaseChooseMode(Customer $customer, ConversationState $state, string $text): string
    {
        if (! in_array($text, ['1', '2'], true)) {
            return 'Responde 1 para elegir manualmente o 2 para asignacion aleatoria.';
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

            return $this->renderNumberSelectionPrompt($raffle, (int) $state->requested_quantity);
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
            return 'No pudimos reservar los numeros aleatorios en este momento. Intenta nuevamente.';
        }

        return $this->renderReservationConfirmation($purchase);
    }

    protected function handlePurchaseSelectNumbers(Customer $customer, ConversationState $state, string $text): string
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
            return "Debes enviar exactamente {$state->requested_quantity} numero(s).".PHP_EOL.PHP_EOL
                .'Ejemplo: '.$this->renderNumberExamples((int) $state->requested_quantity, $raffle->normalizedNumberDigits());
        }

        try {
            $purchase = $this->reserveNumbersAction->execute($customer, $raffle, $numbers, 'manual');
        } catch (InvalidArgumentException) {
            return 'Uno o mas numeros no estan disponibles. Puedes elegir otros numeros o escribir MENU.';
        }

        return $this->renderReservationConfirmation($purchase);
    }

    protected function handlePaymentProofStep(ConversationState $state, WhatsappMessage $inboundMessage): string
    {
        $purchase = $state->purchase;

        if ($inboundMessage->message_type !== 'image') {
            if ($state->status === 'purchase_rejected') {
                return 'Tu pago fue rechazado. Envia un nuevo comprobante por imagen para continuar.'.PHP_EOL.PHP_EOL
                    .$this->renderPaymentWaitingReminder($purchase);
            }

            return $this->renderPaymentWaitingReminder($purchase);
        }

        if ($purchase === null) {
            return 'No encontramos una compra activa para asociar este comprobante.';
        }

        try {
            $this->submitPaymentProofAction->execute(
                purchase: $purchase,
                whatsappMessage: $inboundMessage,
                storagePath: 'whatsapp-proofs/'.$inboundMessage->id.'.jpg',
                originalFilename: 'whatsapp-proof-'.$inboundMessage->id.'.jpg',
                mimeType: 'image/jpeg',
            );
        } catch (InvalidArgumentException) {
            return 'No fue posible registrar el comprobante para la compra actual.';
        }

        return 'Hemos recibido tu comprobante y tu compra esta en revision.'.PHP_EOL.PHP_EOL.'Te avisaremos por este medio cuando el pago sea aprobado o rechazado.';
    }

    protected function handlePickerIntent(Customer $customer, ConversationState $state, string $token): string
    {
        $intent = RafflePickerIntent::query()
            ->with('raffle')
            ->where('token', $token)
            ->first();

        if (! $intent instanceof RafflePickerIntent) {
            return 'No encontramos una seleccion visual valida para continuar. Vuelve al selector y genera una nueva seleccion.';
        }

        if ($intent->consumed_at !== null) {
            return 'Esta seleccion visual ya fue usada anteriormente. Si deseas continuar, vuelve al selector y genera una nueva seleccion.';
        }

        if ($intent->isExpired()) {
            return 'La seleccion visual ya vencio. Vuelve al selector de numeros y genera una nueva seleccion para continuar.';
        }

        $raffle = $intent->raffle;

        if (! $raffle instanceof Raffle || $raffle->status !== 'published') {
            return 'La rifa asociada a esta seleccion ya no esta disponible para compra.';
        }

        if ($state->purchase !== null && in_array($state->purchase->status, ['reserved', 'payment_submitted', 'under_review', 'rejected'], true)) {
            return 'Ya tienes una compra en curso. Envia tu comprobante, espera la revision o escribe CANCELAR si deseas liberar tu reserva actual antes de iniciar otra compra.';
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
            return 'La seleccion visual no es valida para continuar. Vuelve al selector y genera una nueva seleccion.';
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
            return 'La disponibilidad de los numeros seleccionados cambio antes de finalizar la compra. Vuelve al selector visual y elige nuevamente.';
        }

        $intent->forceFill([
            'consumed_at' => now(),
            'consumed_by_customer_id' => $customer->id,
        ])->save();

        return $this->renderReservationConfirmation($purchase);
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

    protected function handleClosedPurchaseReentry(ConversationState $state, string $text): string
    {
        $previousStatus = $state->status;

        $this->resetToMainMenu($state);

        if ($this->isRepurchaseShortcut($text)) {
            return $this->handleMainMenu($state->customer, $state->fresh(), '1');
        }

        $intro = $previousStatus === 'purchase_paid'
            ? 'Tu compra anterior ya fue aprobada. Si quieres participar de nuevo, puedes iniciar otra compra desde aqui.'
            : 'Tu compra anterior ya termino. Si quieres participar de nuevo, puedes iniciar otra compra desde aqui.';

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

    protected function handleExpiredState(Customer $customer, ConversationState $state, string $text): string
    {
        if ($text === '1') {
            if (($onboardingReply = $this->redirectToPurchaseOnboardingIfNeeded($customer, $state, 'purchase_start')) !== null) {
                return $onboardingReply;
            }

            $raffle = $this->getActiveRaffle();

            if ($raffle === null) {
                return 'Ahora mismo no tenemos una rifa activa disponible.';
            }

            $state->forceFill([
                'status' => 'purchase_select_raffle',
                'current_raffle_id' => $raffle->id,
            ])->save();

            return $this->renderRaffleSelection($raffle);
        }

        return 'Tu reserva ya vencio. Responde 1 para iniciar una nueva compra o escribe MENU.';
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

    protected function handleOnboardingPrivacyConsent(Customer $customer, ConversationState $state, string $text): string
    {
        if (in_array($text, ['ACEPTO', 'ACEPTAR', 'SI ACEPTO', 'SÍ ACEPTO'], true)) {
            $customer->forceFill([
                'accepted_privacy_at' => now(),
            ])->save();

            $state->forceFill([
                'status' => 'onboarding_collect_name',
            ])->save();

            return $this->renderCollectNamePrompt();
        }

        if (in_array($text, ['NO ACEPTO', 'NO', 'RECHAZO'], true)) {
            $this->resetToMainMenu($state);

            return 'Entendido. No continuaremos con la compra sin tu autorizacion.'.PHP_EOL.PHP_EOL.$this->renderMainMenu();
        }

        return $this->renderPrivacyConsentPrompt();
    }

    protected function handleOnboardingCollectName(Customer $customer, ConversationState $state, string $rawText): string
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

    protected function handleOnboardingCollectDocument(Customer $customer, ConversationState $state, string $rawText): string
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
            'metadata_json' => $metadata,
        ])->save();

        return match ($pendingAction) {
            'purchase_start' => $this->handleMainMenu($customer, $state->fresh(), '1'),
            'picker_intent' => $pendingPickerToken !== '' ? $this->handlePickerIntent($customer, $state->fresh(), $pendingPickerToken) : $this->renderMainMenu(),
            default => $this->renderMainMenu(),
        };
    }

    protected function renderPrivacyConsentPrompt(): string
    {
        return 'Antes de continuar con tu compra necesitamos tu autorizacion para el tratamiento de datos personales y la aceptacion de las condiciones de compra (nombre y cedula) con el fin de gestionar tu participacion.'.PHP_EOL.PHP_EOL
            .'Responde ACEPTO para continuar o NO ACEPTO para volver al menu.';
    }

    protected function renderCollectNamePrompt(): string
    {
        return 'Para continuar con tu compra, por favor responde con tu nombre completo.';
    }

    protected function renderCollectDocumentPrompt(): string
    {
        return 'Ahora responde con tu numero de cedula (solo numeros).';
    }

    protected function getActiveRaffle(): ?Raffle
    {
        return $this->getActiveRaffles()->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Raffle>
     */
    protected function getActiveRaffles(): \Illuminate\Support\Collection
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
                'error' => 'No identificamos numeros validos en tu mensaje.'.PHP_EOL.PHP_EOL
                    .'Envia solo numeros separados por coma o espacio. Ejemplo: '.$this->renderNumberExamples(2, $digits),
            ];
        }

        $invalidTokens = $rawTokens
            ->filter(fn (string $value): bool => ! ctype_digit($value) || strlen($value) > $digits)
            ->values()
            ->all();

        if ($invalidTokens !== []) {
            return [
                'numbers' => [],
                'error' => 'Encontramos valores invalidos: '.implode(', ', $invalidTokens).'.'.PHP_EOL.PHP_EOL
                    ."Cada numero debe contener solo digitos y tener hasta {$digits} cifra(s)."
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
                'error' => 'No puedes repetir numeros en la misma compra. Duplicados detectados: '.implode(', ', $duplicates).'.',
            ];
        }

        return [
            'numbers' => $numbers,
            'error' => null,
        ];
    }

    protected function renderMainMenu(): string
    {
        $welcome = $this->resolvePublishedContentAction->byKey(
            'system.menu.welcome',
            [],
            'Hola, soy el asistente de Rifax. Responde con la opcion que necesitas o escribe MENU para volver aqui.',
        );

        return $welcome.PHP_EOL.PHP_EOL
            .'Estas son tus opciones:'.PHP_EOL
            .'1. Comprar'.PHP_EOL
            .'2. Numeros disponibles'.PHP_EOL
            .'3. Mis numeros'.PHP_EOL
            .'4. Estadisticas'.PHP_EOL
            .'5. Proximas rifas'.PHP_EOL
            .'6. Condiciones'.PHP_EOL
            .'7. Ayuda'.PHP_EOL.PHP_EOL
            .'Responde con el numero de la opcion.';
    }

    protected function renderRaffleSelection(Raffle $raffle): string
    {
        return "Tenemos esta rifa activa:".PHP_EOL
            ."{$raffle->title}".PHP_EOL.PHP_EOL
            ."Valor por numero: {$raffle->price_per_number}".PHP_EOL
            ."Sorteo: {$raffle->lottery_name} #{$raffle->lottery_draw_number}".PHP_EOL
            ."Fecha: {$raffle->draw_date?->format('Y-m-d')} {$raffle->draw_time}".PHP_EOL.PHP_EOL
            .'Responde:'.PHP_EOL
            .'1. Continuar'.PHP_EOL
            .'2. Volver al menu';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Raffle>  $raffles
     */
    protected function renderRaffleOptions(\Illuminate\Support\Collection $raffles): string
    {
        $options = $raffles
            ->values()
            ->map(function (Raffle $raffle, int $index): string {
                $position = $index + 1;

                return $position.'. '.$raffle->title
                    .' | Valor: '.$raffle->price_per_number
                    .' | Fecha: '.$raffle->draw_date?->format('Y-m-d').' '.$raffle->draw_time;
            })
            ->implode(PHP_EOL);

        return 'Tenemos varias rifas activas disponibles.'.PHP_EOL.PHP_EOL
            .$options.PHP_EOL.PHP_EOL
            .'Responde con el numero de la rifa que deseas comprar o escribe MENU.';
    }

    protected function renderQuantityPrompt(Raffle $raffle): string
    {
        return 'Cuantos numeros deseas comprar?'.PHP_EOL.PHP_EOL
            ."Compra minima para esta rifa: {$raffle->min_numbers_per_purchase}";
    }

    protected function renderChooseMode(): string
    {
        return 'Como deseas elegir tus numeros?'.PHP_EOL
            .'1. Elegir manualmente'.PHP_EOL
            .'2. Asignacion aleatoria';
    }

    protected function renderNumberSelectionPrompt(Raffle $raffle, int $quantity): string
    {
        $digits = $raffle->normalizedNumberDigits();
        $pickerUrl = route('raffles.number-picker', [
            'raffle' => $raffle->slug,
            'quantity' => $quantity,
            'source' => 'whatsapp_manual_prompt',
        ]);

        return "Envia {$quantity} numero(s) separados por coma o espacio.".PHP_EOL.PHP_EOL
            ."Cada numero debe tener hasta {$digits} cifra(s).".PHP_EOL
            .'Ejemplo: '.$this->renderNumberExamples($quantity, $digits).PHP_EOL.PHP_EOL
            .'Si prefieres verlos en una tabla y seleccionar visualmente, abre este link:'.PHP_EOL
            .$pickerUrl;
    }

    protected function renderNumberExamples(int $quantity, int $digits): string
    {
        $count = max(2, min($quantity, 3));

        return collect(range(1, $count))
            ->map(fn (int $value): string => str_pad((string) $value, $digits, '0', STR_PAD_LEFT))
            ->implode(',');
    }

    protected function renderReservationConfirmation(Purchase $purchase): string
    {
        $reservedNumbers = $purchase->numbers->pluck('number')->implode(', ');
        $expiresAt = $purchase->reserved_until?->format('Y-m-d H:i');
        $paymentInstructions = $this->renderPaymentInstructionsList($purchase);

        $message = 'Listo. Tu reserva fue creada correctamente.'.PHP_EOL.PHP_EOL
            .'Numeros reservados: '.$reservedNumbers.PHP_EOL
            .'Total a pagar: '.$purchase->total_amount.PHP_EOL
            .'Reserva valida hasta: '.$expiresAt;

        if ($paymentInstructions !== '') {
            $message .= PHP_EOL.PHP_EOL
                .'Opciones de pago disponibles:'.PHP_EOL.PHP_EOL
                .$paymentInstructions;
        }

        return $message.PHP_EOL.PHP_EOL
            .'Despues de pagar, envia una foto clara del comprobante por este chat para continuar.';
    }

    protected function renderPaymentWaitingReminder(?Purchase $purchase): string
    {
        if (! $purchase instanceof Purchase) {
            return 'Envia tu comprobante de pago por imagen en este chat para continuar.';
        }

        $paymentInstructions = $this->renderPaymentInstructionsList($purchase);

        if ($paymentInstructions === '') {
            return 'Envia tu comprobante de pago por imagen en este chat para continuar.';
        }

        return 'Aun estamos esperando tu comprobante de pago.'.PHP_EOL.PHP_EOL
            .'Te recuerdo las opciones de pago disponibles para esta compra:'.PHP_EOL.PHP_EOL
            .$paymentInstructions.PHP_EOL.PHP_EOL
            .'Cuando completes el pago, envia una foto clara del comprobante por este chat.';
    }

    protected function renderPaymentInstructionsList(Purchase $purchase): string
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
    protected function renderPaymentInstructionEntry(array $method, ?int $index = null): string
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
                : "{$index}. Metodo de pago";
        } elseif ($name !== '') {
            $lines[] = $name;
        }

        if ($accountHolder !== '') {
            $lines[] = "Titular: {$accountHolder}";
        }

        if ($accountReference !== '') {
            $lines[] = "Numero de cuenta: {$accountReference}";
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
            $lines[] = "Como pagar: {$instructions}";
        }

        return implode(PHP_EOL, $lines);
    }

    protected function renderAvailableNumbers(): string
    {
        $raffles = $this->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return 'Ahora mismo no tenemos una rifa activa disponible.';
        }

        if ($raffles->count() === 1) {
            $raffle = $raffles->first();
            $availableCount = $raffle?->numbers()->where('status', 'available')->count();

            return "La rifa {$raffle->title} tiene {$availableCount} numero(s) disponibles.".PHP_EOL.PHP_EOL.'Si deseas comprar, responde 1.';
        }

        $summary = $raffles
            ->map(function (Raffle $raffle): string {
                $availableCount = $raffle->numbers()->where('status', 'available')->count();

                return "- {$raffle->title}: {$availableCount} numero(s) disponibles";
            })
            ->implode(PHP_EOL);

        return 'Estas son las rifas activas y su disponibilidad actual:'.PHP_EOL
            .$summary.PHP_EOL.PHP_EOL
            .'Si deseas comprar, responde 1.';
    }

    protected function renderMyNumbers(Customer $customer): string
    {
        $summary = $customer->purchases()
            ->with('numbers')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Purchase $purchase): string {
                $numbers = $purchase->numbers->pluck('number')->implode(', ');

                return "- Compra {$purchase->id}: {$purchase->status} | Numeros: {$numbers}";
            })
            ->implode(PHP_EOL);

        return $summary !== ''
            ? 'Estas son tus compras registradas:'.PHP_EOL.$summary
            : 'Aun no tienes compras registradas.';
    }

    protected function renderStatistics(): string
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
                ."- Sorteo: {$raffle->lottery_name} #{$raffle->lottery_draw_number}".PHP_EOL
                ."- Fecha: {$raffle->draw_date?->format('Y-m-d')} {$raffle->draw_time}";
        }

        $summary = $raffles
            ->map(function (Raffle $raffle): string {
                $availableCount = $raffle->numbers()->where('status', 'available')->count();
                $soldCount = $raffle->numbers()->where('status', 'paid')->count();

                return $raffle->title.PHP_EOL
                    ."- Vendidos: {$soldCount}".PHP_EOL
                    ."- Disponibles: {$availableCount}".PHP_EOL
                    ."- Sorteo: {$raffle->lottery_name} #{$raffle->lottery_draw_number}".PHP_EOL
                    ."- Fecha: {$raffle->draw_date?->format('Y-m-d')} {$raffle->draw_time}";
            })
            ->implode(PHP_EOL.PHP_EOL);

        return 'Estas son las estadisticas de las rifas activas:'.PHP_EOL.PHP_EOL.$summary;
    }

    protected function renderConditions(): string
    {
        return $this->resolvePublishedContentAction->byIntent(
            'terms_conditions',
            [],
            'Estas son las condiciones principales de la rifa:'.PHP_EOL
            .'- La compra se realiza por este chat.'.PHP_EOL
            .'- Los numeros se reservan por tiempo limitado.'.PHP_EOL
            .'- El pago se confirma manualmente.'.PHP_EOL
            .'- El boleto se envia cuando el pago es aprobado.'.PHP_EOL.PHP_EOL
            .'Si deseas comprar, responde 1. Si deseas volver al menu, escribe MENU.',
        );
    }

    protected function renderHelp(): string
    {
        return $this->resolvePublishedContentAction->byIntent(
            'help_support',
            [],
            'Puedo ayudarte con:'.PHP_EOL
            .'1. Condiciones de la rifa'.PHP_EOL
            .'2. Metodos de pago'.PHP_EOL
            .'3. Estado de tu compra'.PHP_EOL
            .'4. Hablar con soporte',
        );
    }

    protected function renderUpcomingRaffles(): string
    {
        $base = $this->resolvePublishedContentAction->byIntent(
            'upcoming_raffles',
            [],
            'Pronto compartiremos las proximas rifas disponibles. Escribe MENU para volver.',
        );

        $raffles = $this->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return $base;
        }

        $summary = $raffles
            ->map(fn (Raffle $raffle): string => "- {$raffle->title}: {$raffle->draw_date?->format('Y-m-d')} {$raffle->draw_time} | {$raffle->lottery_name} #{$raffle->lottery_draw_number}")
            ->implode(PHP_EOL);

        return $base.PHP_EOL.PHP_EOL.'Rifas activas actuales:'.PHP_EOL.$summary;
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

    protected function isFaqShortcut(string $text): bool
    {
        return in_array($text, ['AYUDA', 'CONDICIONES', 'PAGOS', 'METODOS DE PAGO', 'SORTEO'], true);
    }

    protected function isGreeting(string $text): bool
    {
        return in_array($text, [
            'HOLA',
            'HOLA!',
            'BUENAS',
            'BUEN DIA',
            'BUENOS DIAS',
            'BUENAS TARDES',
            'BUENAS NOCHES',
        ], true);
    }

    protected function isRepurchaseShortcut(string $text): bool
    {
        return in_array($text, [
            '1',
            'COMPRAR',
            'QUIERO COMPRAR',
            'COMPRAR DE NUEVO',
            'VOLVER A COMPRAR',
            'NUEVA COMPRA',
            'OTRA VEZ',
            'OTRA',
        ], true);
    }

    protected function handleFaqShortcut(ConversationState $state, string $text): string
    {
        return match ($text) {
            'AYUDA' => $this->renderHelp(),
            'CONDICIONES' => $this->renderConditions(),
            'PAGOS', 'METODOS DE PAGO' => $this->renderPaymentMethods(),
            'SORTEO' => $this->renderDrawDate($state),
            default => $this->renderMainMenu(),
        };
    }

    protected function renderPaymentMethods(): string
    {
        $base = $this->resolvePublishedContentAction->byIntent(
            'payment_methods',
            [],
            'Puedes pagar usando los metodos habilitados por la empresa. Te enviaremos las instrucciones exactas dentro del flujo de compra.',
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

    protected function renderDrawDate(ConversationState $state): string
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
