<?php

namespace App\Actions\WhatsApp\Bot;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Support\WhatsAppReply;
use Illuminate\Support\Str;

/**
 * Registro previo a la compra: aceptación de datos, nombre y cédula.
 */
class OnboardingFlow
{
    public function __construct(
        protected BotMessages $botMessages,
        protected BotRaffles $botRaffles,
    ) {}

    public function redirectToPurchaseOnboardingIfNeeded(Customer $customer, ConversationState $state, string $pendingAction, array $metadata = []): WhatsAppReply|string|null
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
            'onboarding_privacy_consent' => $this->botMessages->renderPrivacyConsentPrompt(),
            'onboarding_collect_name' => $this->botMessages->renderCollectNamePrompt(),
            'onboarding_collect_document' => $this->botMessages->renderCollectDocumentPrompt(),
            default => $this->botMessages->renderMainMenu(),
        };
    }

    public function requiredPurchaseOnboardingStatus(Customer $customer): ?string
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

    public function handleOnboardingPrivacyConsent(Customer $customer, ConversationState $state, string $text): WhatsAppReply|string
    {
        if (in_array($text, ['1', 'ACEPTO', 'ACEPTAR', 'SI ACEPTO', 'SÍ ACEPTO'], true)) {
            $customer->forceFill([
                'accepted_privacy_at' => now(),
            ])->save();

            $state->forceFill([
                'status' => 'onboarding_collect_name',
            ])->save();

            return $this->botMessages->renderCollectNamePrompt();
        }

        if (in_array($text, ['2', 'NO ACEPTO', 'NO', 'RECHAZO'], true)) {
            $this->botRaffles->resetToMainMenu($state);

            return WhatsAppReply::prefixed('Entendido. No continuaremos con la compra sin tu autorización.'.PHP_EOL.PHP_EOL, $this->botMessages->renderMainMenu());
        }

        return $this->botMessages->renderPrivacyConsentPrompt();
    }

    public function handleOnboardingCollectName(Customer $customer, ConversationState $state, string $rawText): WhatsAppReply|string|null
    {
        $name = trim($rawText);

        if (Str::length($name) < 2) {
            return $this->botMessages->renderCollectNamePrompt();
        }

        $customer->forceFill([
            'name' => $name,
        ])->save();

        $nextStatus = $this->requiredPurchaseOnboardingStatus($customer);

        if ($nextStatus === null) {
            // Registro completo: el router retoma la acción pendiente.
            return null;
        }

        $state->forceFill([
            'status' => $nextStatus,
        ])->save();

        return match ($nextStatus) {
            'onboarding_collect_document' => $this->botMessages->renderCollectDocumentPrompt(),
            default => $this->botMessages->renderMainMenu(),
        };
    }

    public function handleOnboardingCollectDocument(Customer $customer, ConversationState $state, string $rawText): WhatsAppReply|string|null
    {
        $digits = preg_replace('/\D+/', '', $rawText) ?? '';

        if ($digits === '' || ! ctype_digit($digits) || strlen($digits) < 5 || strlen($digits) > 15) {
            return $this->botMessages->renderCollectDocumentPrompt();
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
                'onboarding_collect_name' => $this->botMessages->renderCollectNamePrompt(),
                default => $this->botMessages->renderMainMenu(),
            };
        }

        // Registro completo: el router retoma la acción pendiente.
        return null;
    }

    public function shouldRecoverCompletedOnboarding(Customer $customer, ConversationState $state): bool
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
}
