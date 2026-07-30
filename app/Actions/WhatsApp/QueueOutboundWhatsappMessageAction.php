<?php

namespace App\Actions\WhatsApp;

use App\Jobs\DispatchWhatsappMessageJob;
use App\Models\Customer;
use App\Models\WhatsappMessage;

class QueueOutboundWhatsappMessageAction
{
    /**
     * @param  array<string, mixed>  $payloadJson
     */
    public function execute(
        Customer $customer,
        string $messageType,
        ?string $bodyText = null,
        array $payloadJson = [],
    ): WhatsappMessage {
        $message = WhatsappMessage::query()->create([
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'message_type' => $messageType,
            'body_text' => $bodyText,
            'payload_json' => $payloadJson,
            'status' => config('services.whatsapp.send_enabled') ? 'queued' : 'generated',
            'provider_created_at' => now(),
        ]);

        if (config('services.whatsapp.send_enabled')) {
            DispatchWhatsappMessageJob::dispatch($message->id);
        }

        return $message->fresh() ?? $message;
    }
}
