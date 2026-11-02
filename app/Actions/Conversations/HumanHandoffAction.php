<?php

namespace App\Actions\Conversations;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Actions\Content\ResolvePublishedContentAction;
use App\Actions\WhatsApp\DispatchOutboundWhatsappMessageAction;
use App\Models\ConversationState;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Traspaso de una conversación de WhatsApp entre el bot y un agente humano.
 *
 * Flujo: el cliente escribe ASESOR (o un agente toma el chat) → 'requested'
 * / 'active' → el bot deja de responder → el agente devuelve el chat al bot.
 */
class HumanHandoffAction
{
    public function __construct(
        protected RecordAdminAuditAction $audit,
        protected DispatchOutboundWhatsappMessageAction $dispatch,
        protected ResolvePublishedContentAction $content,
    ) {}

    /**
     * El cliente pidió hablar con una persona (lo llama el bot).
     */
    public function request(ConversationState $state, ?string $reason = null): ConversationState
    {
        if ($state->isHandledByHuman()) {
            return $state;
        }

        $state->forceFill([
            'handoff_status' => ConversationState::HANDOFF_REQUESTED,
            'handoff_requested_at' => now(),
            'handoff_reason' => $reason,
            'assigned_user_id' => null,
        ])->save();

        $this->audit->execute(
            event: 'conversation.handoff_requested',
            action: 'request_handoff',
            auditable: $state,
            context: ['reason' => $reason],
        );

        return $state;
    }

    /**
     * Un agente toma la conversación (o se la asigna a otro agente).
     */
    public function assign(ConversationState $state, User $agent, User $actor): ConversationState
    {
        return DB::transaction(function () use ($state, $agent, $actor): ConversationState {
            $locked = ConversationState::query()->lockForUpdate()->findOrFail($state->id);
            $before = $this->audit->snapshot($locked);
            $previousAgentId = $locked->assigned_user_id;

            $locked->forceFill([
                'handoff_status' => ConversationState::HANDOFF_ACTIVE,
                'assigned_user_id' => $agent->id,
                'handoff_requested_at' => $locked->handoff_requested_at ?? now(),
                'handoff_started_at' => $locked->handoff_started_at ?? now(),
            ])->save();

            $this->audit->execute(
                event: $previousAgentId !== null && $previousAgentId !== $agent->id
                    ? 'conversation.handoff_transferred'
                    : 'conversation.handoff_taken',
                action: 'assign_agent',
                auditable: $locked,
                before: $before,
                after: $this->audit->snapshot($locked),
                context: ['agent_id' => $agent->id, 'previous_agent_id' => $previousAgentId],
                user: $actor,
            );

            return $locked;
        });
    }

    /**
     * Devuelve la conversación al bot. Por defecto avisa al cliente.
     */
    public function release(ConversationState $state, User $actor, bool $notifyCustomer = true): ConversationState
    {
        $state = DB::transaction(function () use ($state, $actor): ConversationState {
            $locked = ConversationState::query()->lockForUpdate()->findOrFail($state->id);
            $before = $this->audit->snapshot($locked);

            $locked->forceFill([
                'handoff_status' => null,
                'assigned_user_id' => null,
                'handoff_requested_at' => null,
                'handoff_started_at' => null,
                'handoff_reason' => null,
            ])->save();

            $this->audit->execute(
                event: 'conversation.handoff_released',
                action: 'release_to_bot',
                auditable: $locked,
                before: $before,
                after: $this->audit->snapshot($locked),
                user: $actor,
            );

            return $locked;
        });

        if ($notifyCustomer && $state->isWithinCustomerServiceWindow()) {
            $this->send($state, $this->content->byKey(
                'system.handoff.released',
                [],
                'Gracias por escribirnos. Te devolvemos al asistente automático; escribe MENU cuando quieras ver las opciones.',
            ), $actor);
        }

        return $state;
    }

    /**
     * Mensaje escrito por un agente desde el panel. Si nadie atendía el chat,
     * el agente lo toma automáticamente.
     */
    public function sendAgentMessage(ConversationState $state, User $agent, string $text): WhatsappMessage
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException(__('admin.resources.conversations.chat.errors.empty'));
        }

        if (! $state->isWithinCustomerServiceWindow()) {
            throw new InvalidArgumentException(__('admin.resources.conversations.chat.errors.window_closed'));
        }

        if ($state->handoff_status !== ConversationState::HANDOFF_ACTIVE || $state->assigned_user_id === null) {
            $state = $this->assign($state, $agent, $agent);
        }

        $message = $this->send($state, $text, $agent);

        $state->forceFill([
            'last_agent_message_at' => now(),
            'last_outbound_message_id' => $message->id,
            'agent_last_read_at' => now(),
        ])->save();

        return $message;
    }

    public function markRead(ConversationState $state): void
    {
        if ($state->hasUnreadForAgent()) {
            $state->forceFill(['agent_last_read_at' => now()])->saveQuietly();
        }
    }

    protected function send(ConversationState $state, string $text, ?User $agent): WhatsappMessage
    {
        $customer = $state->customer()->firstOrFail();

        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body_text' => $text,
            'payload_json' => ['text' => ['body' => $text], 'source' => 'agent'],
            'status' => 'generated',
            'provider_created_at' => now(),
            'sent_by_user_id' => $agent?->id,
        ]);

        return $this->dispatch->execute($customer, $message);
    }
}
