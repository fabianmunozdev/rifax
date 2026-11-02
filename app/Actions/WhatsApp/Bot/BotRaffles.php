<?php

namespace App\Actions\WhatsApp\Bot;

use App\Models\ConversationState;
use App\Models\Raffle;
use Illuminate\Support\Collection;

/**
 * Consultas de rifas activas y reinicio del estado de la conversación al menú.
 */
class BotRaffles
{
    public function resetToMainMenu(ConversationState $state): void
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

    public function getActiveRaffle(): ?Raffle
    {
        return $this->getActiveRaffles()->first();
    }

    /**
     * @return Collection<int, Raffle>
     */
    public function getActiveRaffles(): Collection
    {
        return Raffle::query()
            ->where('status', 'published')
            ->orderBy('draw_date')
            ->orderBy('draw_time')
            ->get()
            ->filter(fn (Raffle $raffle): bool => $raffle->salesAreOpen())
            ->values();
    }

    public function getConversationRaffle(ConversationState $state): ?Raffle
    {
        return $state->currentRaffle ?: $this->getActiveRaffle();
    }
}
