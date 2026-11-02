<?php

namespace App\Actions\WhatsApp;

use App\Models\Customer;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsappProvider;
use App\Services\WhatsApp\WhatsappProviderException;

/**
 * Envía un mensaje con el proveedor activo y registra el resultado.
 *
 * Las claves meta_request / meta_response / meta_error se mantienen para
 * cualquier proveedor porque el panel las consulta; 'provider' indica cuál envió.
 */
class DispatchOutboundWhatsappMessageAction
{
    public function __construct(
        protected WhatsappProvider $provider,
    ) {}

    public function execute(Customer $customer, WhatsappMessage $message, bool $rethrowFailures = false): WhatsappMessage
    {
        if (! config('services.whatsapp.send_enabled')) {
            return $message->fresh() ?? $message;
        }

        $recipient = (string) preg_replace('/\D+/', '', (string) ($customer->wa_id ?: $customer->phone));

        if (strlen($recipient) < 7 || ! $this->provider->isConfigured()) {
            $message->forceFill([
                'status' => 'failed',
                'payload_json' => array_merge($message->payload_json ?? [], [
                    'provider' => $this->provider->name(),
                    'meta_error' => 'Missing WhatsApp outbound configuration.',
                ]),
            ])->save();

            return $message->fresh() ?? $message;
        }

        try {
            $result = $this->provider->send($recipient, $message);

            $message->forceFill([
                'provider_message_id' => $result['provider_message_id'],
                'status' => 'sent',
                'payload_json' => array_merge($message->payload_json ?? [], [
                    'provider' => $this->provider->name(),
                    'meta_request' => $result['request'],
                    'meta_response' => $result['response'],
                ]),
            ])->save();
        } catch (WhatsappProviderException $exception) {
            $message->forceFill([
                'status' => 'failed',
                'payload_json' => array_merge($message->payload_json ?? [], [
                    'provider' => $this->provider->name(),
                    'meta_request' => $exception->request,
                    'meta_error' => $exception->error,
                ]),
            ])->save();

            if ($rethrowFailures) {
                throw $exception->getPrevious() ?? $exception;
            }
        }

        return $message->fresh() ?? $message;
    }
}
