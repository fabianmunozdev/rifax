<?php

namespace App\Actions\WhatsApp\Bot;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Support\WhatsAppReply;

/**
 * Menú principal, atajos de preguntas frecuentes y regreso tras una compra cerrada.
 */
class MainMenuFlow
{
    public function __construct(
        protected BotKeywords $botKeywords,
        protected BotMessages $botMessages,
        protected BotRaffles $botRaffles,
        protected OnboardingFlow $onboardingFlow,
    ) {}

    public function handleMainMenu(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if ($text === '' || ! in_array($text, ['1', '2', '3', '4', '5', '6', '7'], true)) {
            return $this->botMessages->renderMainMenu();
        }

        if ($text === '1') {
            if (($onboardingReply = $this->onboardingFlow->redirectToPurchaseOnboardingIfNeeded($customer, $state, 'purchase_start')) !== null) {
                return $onboardingReply;
            }

            $raffles = $this->botRaffles->getActiveRaffles();

            if ($raffles->isEmpty()) {
                return 'Ahora mismo no tenemos una rifa activa disponible.';
            }

            if ($raffles->count() > 1) {
                $state->forceFill([
                    'status' => 'purchase_select_raffle',
                    'current_raffle_id' => null,
                ])->save();

                return $this->botMessages->renderRaffleOptions($raffles);
            }

            $raffle = $raffles->first();

            $state->forceFill([
                'status' => 'purchase_select_raffle',
                'current_raffle_id' => $raffle?->id,
            ])->save();

            return $this->botMessages->renderRaffleSelection($raffle);
        }

        return match ($text) {
            '2' => $this->botMessages->renderAvailableNumbers(),
            '3' => $this->botMessages->renderMyNumbers($state->customer),
            '4' => $this->botMessages->renderStatistics(),
            '5' => $this->botMessages->renderUpcomingRaffles(),
            '6' => $this->botMessages->renderConditions(),
            '7' => $this->botMessages->renderHelp(),
        };
    }

    public function shouldReenterClosedPurchaseFlow(ConversationState $state, string $text): bool
    {
        if (! in_array($state->status, ['purchase_paid', 'purchase_expired'], true)) {
            return false;
        }

        if ($state->purchase !== null && in_array($state->purchase->status, ['reserved', 'payment_submitted', 'rejected'], true)) {
            return false;
        }

        return $text === ''
            || $this->botKeywords->isGreeting($text)
            || $this->botKeywords->isRepurchaseShortcut($text);
    }

    public function handleClosedPurchaseReentry(ConversationState $state, string $text): WhatsAppReply|string
    {
        $previousStatus = $state->status;

        $this->botRaffles->resetToMainMenu($state);

        if ($this->botKeywords->isRepurchaseShortcut($text)) {
            return $this->handleMainMenu($state->customer, $state->fresh(), '1');
        }

        $intro = $previousStatus === 'purchase_paid'
            ? 'Tu compra anterior ya fue aprobada. Si quieres participar de nuevo, puedes iniciar otra compra desde aquí.'
            : 'Tu compra anterior ya terminó. Si quieres participar de nuevo, puedes iniciar otra compra desde aquí.';

        return WhatsAppReply::prefixed($intro.PHP_EOL.PHP_EOL, $this->botMessages->renderMainMenu());
    }

    public function handleFaqShortcut(ConversationState $state, string $text): WhatsAppReply|string
    {
        $norm = $this->botKeywords->normalizeKeywordText($text);

        return match (true) {
            $this->botKeywords->containsAnyKeyword($norm, ['AYUDA']) => $this->botMessages->renderHelp(),
            $this->botKeywords->containsAnyKeyword($norm, ['CONDICIONES']) => $this->botMessages->renderConditions(),
            $this->botKeywords->containsAnyKeyword($norm, ['PAGOS', 'METODOS DE PAGO']) => $this->botMessages->renderPaymentMethods(),
            $this->botKeywords->containsAnyKeyword($norm, ['SORTEO']) => $this->botMessages->renderDrawDate($state),
            default => $this->botMessages->renderMainMenu(),
        };
    }
}
