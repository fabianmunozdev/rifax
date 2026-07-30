<?php

namespace App\Jobs;

use App\Actions\WhatsApp\DispatchOutboundWhatsappMessageAction;
use App\Models\WhatsappMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $backoff;

    public function __construct(public int $whatsappMessageId)
    {
        $this->tries = (int) config('services.whatsapp.retry_attempts', 3);
        $this->backoff = (int) config('services.whatsapp.retry_backoff_seconds', 60);
        $this->afterCommit();
    }

    public function handle(DispatchOutboundWhatsappMessageAction $dispatchOutboundWhatsappMessageAction): void
    {
        $message = WhatsappMessage::query()
            ->with('customer')
            ->find($this->whatsappMessageId);

        if ($message === null || $message->customer === null) {
            return;
        }

        $dispatchOutboundWhatsappMessageAction->execute(
            customer: $message->customer,
            message: $message,
            rethrowFailures: true,
        );
    }
}
