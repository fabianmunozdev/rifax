<?php

namespace App\Actions\WhatsApp;

use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class DispatchOutboundWhatsappMessageAction
{
    public function execute(Customer $customer, WhatsappMessage $message, bool $rethrowFailures = false): WhatsappMessage
    {
        if (! config('services.whatsapp.send_enabled')) {
            return $message->fresh() ?? $message;
        }

        $waId = $customer->wa_id ?: preg_replace('/\D+/', '', $customer->phone ?? '');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $accessToken = (string) config('services.whatsapp.access_token');

        if ($waId === null || $waId === '' || $phoneNumberId === '' || $accessToken === '') {
            $message->forceFill([
                'status' => 'failed',
                'payload_json' => array_merge($message->payload_json ?? [], [
                    'meta_error' => 'Missing WhatsApp outbound configuration.',
                ]),
            ])->save();

            return $message->fresh() ?? $message;
        }

        $payload = $this->buildPayload($waId, $message);

        $endpoint = rtrim((string) config('services.whatsapp.api_base_url'), '/')
            .'/'.trim((string) config('services.whatsapp.graph_version'), '/')
            .'/'.$phoneNumberId.'/messages';

        try {
            $response = Http::timeout((int) config('services.whatsapp.timeout_seconds', 10))
                ->withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload)
                ->throw();

            $message->forceFill([
                'provider_message_id' => Arr::get($response->json(), 'messages.0.id'),
                'status' => 'sent',
                'payload_json' => array_merge($message->payload_json ?? [], [
                    'meta_request' => $payload,
                    'meta_response' => $response->json(),
                ]),
            ])->save();
        } catch (RequestException $exception) {
            $response = $exception->response;

            $message->forceFill([
                'status' => 'failed',
                'payload_json' => array_merge($message->payload_json ?? [], [
                    'meta_request' => $payload,
                    'meta_error' => [
                        'message' => $exception->getMessage(),
                        'status' => $response?->status(),
                        'body' => $response?->json() ?? $response?->body(),
                    ],
                ]),
            ])->save();

            if ($rethrowFailures) {
                throw $exception;
            }
        } catch (Throwable $exception) {
            $message->forceFill([
                'status' => 'failed',
                'payload_json' => array_merge($message->payload_json ?? [], [
                    'meta_request' => $payload,
                    'meta_error' => [
                        'message' => $exception->getMessage(),
                    ],
                ]),
            ])->save();

            if ($rethrowFailures) {
                throw $exception;
            }
        }

        return $message->fresh() ?? $message;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(string $waId, WhatsappMessage $message): array
    {
        if ($message->message_type === 'template') {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $waId,
                'type' => 'template',
                'template' => Arr::get($message->payload_json, 'template', []),
            ];
        }

        if ($message->message_type === 'document') {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $waId,
                'type' => 'document',
                'document' => Arr::get($message->payload_json, 'document', []),
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $waId,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message->body_text,
            ],
        ];
    }
}
