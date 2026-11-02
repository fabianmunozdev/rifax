<?php

namespace App\Livewire;

use App\Actions\Conversations\HumanHandoffAction;
use App\Enums\PanelPermission;
use App\Filament\Support\PanelAccess;
use App\Models\ConversationState;
use App\Models\User;
use App\Models\WhatsappMessage;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Historial de WhatsApp de una conversación con caja de respuesta para agentes.
 * Se refresca solo cada pocos segundos (wire:poll).
 */
class ConversationChat extends Component
{
    /** Mensajes que se cargan inicialmente y en cada "ver anteriores". */
    protected const PAGE_SIZE = 50;

    public ConversationState $record;

    public string $draft = '';

    public int $limit = self::PAGE_SIZE;

    public function send(HumanHandoffAction $handoff): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canReply(), 403);

        $this->validate(['draft' => ['required', 'string', 'max:4000']]);

        try {
            $message = $handoff->sendAgentMessage($this->record->fresh(), $user, $this->draft);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->draft = '';
        $this->record->refresh();
        $this->dispatch('handoff-updated');
        $this->dispatch('rfx-chat-sent');

        if ($message->status === 'failed') {
            Notification::make()
                ->title(__('admin.resources.conversations.chat.errors.send_failed'))
                ->danger()
                ->send();
        }
    }

    public function loadOlder(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function canReply(): bool
    {
        return PanelAccess::allows(PanelPermission::ConversationsManage);
    }

    public function render(HumanHandoffAction $handoff): View
    {
        $this->record->refresh();
        $handoff->markRead($this->record);

        $query = WhatsappMessage::query()
            ->where('customer_id', $this->record->customer_id)
            ->with('sentBy:id,name');

        $total = (clone $query)->count();

        /** @var Collection<int, WhatsappMessage> $messages */
        $messages = $query
            ->orderByDesc('id')
            ->limit($this->limit)
            ->get()
            ->reverse()
            ->values();

        return view('livewire.conversation-chat', [
            'messages' => $messages,
            'hasOlder' => $total > $messages->count(),
            'windowOpen' => $this->record->isWithinCustomerServiceWindow(),
            'canReply' => $this->canReply(),
        ]);
    }
}
