<?php

namespace App\Actions\WhatsApp\Bot;

use App\Actions\Purchases\ReserveNumbersAction;
use App\Actions\Purchases\SelectRandomRaffleNumbersAction;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Raffle;
use App\Models\RafflePickerIntent;
use App\Support\WhatsAppReply;
use InvalidArgumentException;

/**
 * Flujo de compra: elegir rifa, cantidad, modo y números, y reservar.
 */
class PurchaseFlow
{
    public function __construct(
        protected BotMessages $botMessages,
        protected BotRaffles $botRaffles,
        protected OnboardingFlow $onboardingFlow,
        protected ReserveNumbersAction $reserveNumbersAction,
        protected SelectRandomRaffleNumbersAction $selectRandomRaffleNumbersAction,
    ) {}

    public function handlePurchaseSelectRaffle(ConversationState $state, string $text): WhatsAppReply|string
    {
        if ($state->current_raffle_id === null) {
            $raffles = $this->botRaffles->getActiveRaffles();

            if ($raffles->isEmpty()) {
                $this->botRaffles->resetToMainMenu($state);

                return WhatsAppReply::prefixed('No encontramos rifas activas para continuar.'.PHP_EOL.PHP_EOL, $this->botMessages->renderMainMenu());
            }

            if ($raffles->count() > 1) {
                $selectedRaffle = $raffles->values()->get(((int) $text) - 1);

                if (! $selectedRaffle instanceof Raffle) {
                    return WhatsAppReply::prefixed('Responde con el número de la rifa que deseas comprar.'.PHP_EOL.PHP_EOL, $this->botMessages->renderRaffleOptions($raffles));
                }

                $state->forceFill([
                    'status' => 'purchase_enter_quantity',
                    'current_raffle_id' => $selectedRaffle->id,
                ])->save();

                return $this->botMessages->renderQuantityPrompt($selectedRaffle);
            }
        }

        if ($text === '1') {
            $raffle = $this->botRaffles->getConversationRaffle($state);

            if ($raffle === null) {
                $this->botRaffles->resetToMainMenu($state);

                return WhatsAppReply::prefixed('No encontramos una rifa activa para continuar. '.PHP_EOL.PHP_EOL, $this->botMessages->renderMainMenu());
            }

            $state->forceFill([
                'status' => 'purchase_enter_quantity',
            ])->save();

            return $this->botMessages->renderQuantityPrompt($raffle);
        }

        if ($text === '2') {
            $this->botRaffles->resetToMainMenu($state);

            return $this->botMessages->renderMainMenu();
        }

        return 'Responde 1 para continuar o 2 para volver al menú.';
    }

    public function handlePurchaseEnterQuantity(ConversationState $state, string $text): WhatsAppReply|string
    {
        $raffle = $this->botRaffles->getConversationRaffle($state);

        if ($raffle === null) {
            $this->botRaffles->resetToMainMenu($state);

            return $this->botMessages->renderMainMenu();
        }

        if (! ctype_digit($text) || (int) $text < 1) {
            return 'Responde con una cantidad válida en números.'.PHP_EOL.PHP_EOL.'Ejemplo: 2';
        }

        $quantity = (int) $text;

        if ($quantity < $raffle->min_numbers_per_purchase) {
            return "Lo siento, la cantidad mínima para esta rifa es {$raffle->min_numbers_per_purchase} número(s).".PHP_EOL.PHP_EOL
                .'Intentémoslo de nuevo. ¿Cuántos números deseas comprar?';
        }

        $max = $raffle->maxHeldNumbersPerCustomer();

        if ($quantity > $max) {
            return "Lo siento, el máximo por persona en esta rifa es {$max} número(s).".PHP_EOL.PHP_EOL
                .'Intentémoslo de nuevo. ¿Cuántos números deseas comprar?';
        }

        $state->forceFill([
            'status' => 'purchase_choose_mode',
            'requested_quantity' => $quantity,
        ])->save();

        return $this->botMessages->renderChooseMode();
    }

    public function handlePurchaseChooseMode(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if (! in_array($text, ['1', '2'], true)) {
            return 'Responde 1 para elegir manualmente o 2 para asignación aleatoria.';
        }

        if ($text === '1') {
            $state->forceFill([
                'status' => 'purchase_select_numbers',
                'selection_mode' => 'manual',
            ])->save();

            $raffle = $this->botRaffles->getConversationRaffle($state);

            if ($raffle === null) {
                $this->botRaffles->resetToMainMenu($state);

                return $this->botMessages->renderMainMenu();
            }

            return $this->botMessages->renderNumberSelectionPrompt($raffle, (int) $state->requested_quantity, $state->customer);
        }

        $raffle = $this->botRaffles->getConversationRaffle($state);

        if ($raffle === null || $state->requested_quantity === null) {
            $this->botRaffles->resetToMainMenu($state);

            return $this->botMessages->renderMainMenu();
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

        return $this->botMessages->renderReservationConfirmation($purchase);
    }

    public function handlePurchaseSelectNumbers(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        $raffle = $this->botRaffles->getConversationRaffle($state);

        if ($raffle === null || $state->requested_quantity === null) {
            $this->botRaffles->resetToMainMenu($state);

            return $this->botMessages->renderMainMenu();
        }

        $parsedNumbers = $this->parseManualNumbers($text, $raffle->normalizedNumberDigits());

        if ($parsedNumbers['error'] !== null) {
            return $parsedNumbers['error'];
        }

        $numbers = $parsedNumbers['numbers'];

        if (count($numbers) !== (int) $state->requested_quantity) {
            return "Debes enviar exactamente {$state->requested_quantity} número(s).".PHP_EOL.PHP_EOL
                .'Ejemplo: '.$this->botMessages->renderNumberExamples((int) $state->requested_quantity, $raffle->normalizedNumberDigits());
        }

        try {
            $purchase = $this->reserveNumbersAction->execute($customer, $raffle, $numbers, 'manual');
        } catch (InvalidArgumentException) {
            return 'Uno o más números no están disponibles. Puedes elegir otros números o escribir MENU.';
        }

        return $this->botMessages->renderReservationConfirmation($purchase);
    }

    public function handlePickerIntent(Customer $customer, ConversationState $state, string $token): WhatsAppReply|string
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

        if (($onboardingReply = $this->onboardingFlow->redirectToPurchaseOnboardingIfNeeded($customer, $state, 'picker_intent', [
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

        return $this->botMessages->renderReservationConfirmation($purchase);
    }

    public function handleExpiredState(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if ($text === '1') {
            if (($onboardingReply = $this->onboardingFlow->redirectToPurchaseOnboardingIfNeeded($customer, $state, 'purchase_start')) !== null) {
                return $onboardingReply;
            }

            $raffle = $this->botRaffles->getActiveRaffle();

            if ($raffle === null) {
                return WhatsAppReply::prefixed('Ahora mismo no tenemos una rifa activa disponible.'.PHP_EOL.PHP_EOL, $this->botMessages->renderMainMenu());
            }

            $state->forceFill([
                'status' => 'purchase_select_raffle',
                'current_raffle_id' => $raffle->id,
            ])->save();

            return $this->botMessages->renderRaffleSelection($raffle);
        }

        $body = 'Tu reserva ya venció. Los números que seleccionaste fueron liberados y pueden ser tomados por otras personas.'.PHP_EOL.PHP_EOL
            .'Responde 1 para iniciar una nueva compra, o escribe MENU para volver al inicio.';

        return WhatsAppReply::make($body, [
            ['id' => 'expired_new_purchase', 'title' => 'Comprar de nuevo'],
            ['id' => 'expired_menu', 'title' => 'Menú'],
        ]);
    }

    /**
     * @return array{numbers: list<string>, error: string|null}
     */
    public function parseManualNumbers(string $text, int $digits): array
    {
        $rawTokens = collect(preg_split('/[\s,;]+/', trim($text)) ?: [])
            ->filter(fn (string $value): bool => $value !== '')
            ->values();

        if ($rawTokens->isEmpty()) {
            return [
                'numbers' => [],
                'error' => 'No identificamos números válidos en tu mensaje.'.PHP_EOL.PHP_EOL
                    .'Para continuar vuelve al enlace "Escoge aquí tus números" o responde solo con los dígitos separados por coma o espacio. Ejemplo: '.$this->botMessages->renderNumberExamples(2, $digits),
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
                    .PHP_EOL.'Ejemplo: '.$this->botMessages->renderNumberExamples(2, $digits),
            ];
        }

        $numbers = $this->botMessages->extractNumbers($rawTokens->implode(','), $digits);
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

    /**
     * @return array<string, mixed>
     */
    public function buildPickerTraceMetadata(RafflePickerIntent $intent): array
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
}
