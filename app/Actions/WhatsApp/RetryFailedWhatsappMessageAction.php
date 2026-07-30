<?php

namespace App\Actions\WhatsApp;

use App\Actions\Admin\RecordAdminAuditAction;
use App\Models\User;
use App\Models\WhatsappMessage;
use InvalidArgumentException;

class RetryFailedWhatsappMessageAction
{
    public function __construct(
        protected QueueOutboundWhatsappMessageAction $queueOutboundWhatsappMessageAction,
        protected RecordAdminAuditAction $recordAdminAuditAction,
    ) {
    }

    public function execute(WhatsappMessage $message, ?User $actor = null): WhatsappMessage
    {
        $message->loadMissing('customer');
        $before = $this->recordAdminAuditAction->snapshot($message);

        $hasFailure = $message->status === 'failed' || $message->provider_status === 'failed';

        if ($message->direction !== 'outbound' || ! $hasFailure) {
            throw new InvalidArgumentException('Only failed outbound WhatsApp messages can be retried.');
        }

        if ($message->customer === null) {
            throw new InvalidArgumentException('The WhatsApp message has no customer associated.');
        }

        $payloadJson = array_merge($message->payload_json ?? [], [
            'retry_of_message_id' => $message->id,
        ]);

        unset(
            $payloadJson['meta_request'],
            $payloadJson['meta_response'],
            $payloadJson['meta_error'],
            $payloadJson['provider_status_event'],
            $payloadJson['provider_status_history'],
        );

        $newMessage = $this->queueOutboundWhatsappMessageAction->execute(
            customer: $message->customer,
            messageType: $message->message_type,
            bodyText: $message->body_text,
            payloadJson: $payloadJson,
        );

        $this->recordAdminAuditAction->execute(
            event: 'whatsapp.retry_requested',
            action: 'retry',
            auditable: $newMessage,
            before: $before,
            after: $this->recordAdminAuditAction->snapshot($newMessage),
            context: [
                'retried_message_id' => $message->id,
            ],
            user: $actor,
        );

        return $newMessage;
    }
}
