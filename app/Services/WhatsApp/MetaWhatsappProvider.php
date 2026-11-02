<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsappProvider implements WhatsappProvider
{
    public function name(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return (string) config('services.whatsapp.phone_number_id') !== ''
            && (string) config('services.whatsapp.access_token') !== '';
    }

    public function send(string $recipientDigits, WhatsappMessage $message): array
    {
        $payload = $this->buildPayload($recipientDigits, $message);

        $endpoint = rtrim((string) config('services.whatsapp.api_base_url'), '/')
            .'/'.trim((string) config('services.whatsapp.graph_version'), '/')
            .'/'.config('services.whatsapp.phone_number_id').'/messages';

        try {
            $response = Http::timeout((int) config('services.whatsapp.timeout_seconds', 10))
                ->withToken((string) config('services.whatsapp.access_token'))
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload)
                ->throw();
        } catch (RequestException $exception) {
            $response = $exception->response;

            throw new WhatsappProviderException($exception->getMessage(), $payload, [
                'message' => $exception->getMessage(),
                'status' => $response?->status(),
                'body' => $response?->json() ?? $response?->body(),
            ], $exception);
        } catch (\Throwable $exception) {
            throw new WhatsappProviderException($exception->getMessage(), $payload, [
                'message' => $exception->getMessage(),
            ], $exception);
        }

        return [
            'provider_message_id' => Arr::get($response->json(), 'messages.0.id'),
            'request' => $payload,
            'response' => $response->json(),
        ];
    }

    public function downloadMedia(string $mediaReference): array
    {
        $accessToken = (string) config('services.whatsapp.access_token');
        $graphVersion = trim((string) config('services.whatsapp.graph_version'), '/');
        $baseUrl = rtrim((string) config('services.whatsapp.api_base_url'), '/');
        $timeout = (int) config('services.whatsapp.timeout_seconds', 10);

        if ($accessToken === '' || $graphVersion === '' || $baseUrl === '') {
            throw new RuntimeException('Missing WhatsApp media download configuration.');
        }

        $metadata = Http::timeout($timeout)
            ->withToken($accessToken)
            ->acceptJson()
            ->get($baseUrl.'/'.$graphVersion.'/'.$mediaReference)
            ->throw()
            ->json();

        $downloadUrl = (string) Arr::get($metadata, 'url', '');

        if ($downloadUrl === '') {
            throw new RuntimeException('Unable to resolve WhatsApp media download URL.');
        }

        $contents = Http::timeout($timeout)
            ->withToken($accessToken)
            ->get($downloadUrl)
            ->throw()
            ->body();

        $mimeType = Arr::get($metadata, 'mime_type');
        $fileSize = Arr::get($metadata, 'file_size');

        return [
            'contents' => $contents,
            'mime_type' => is_string($mimeType) ? $mimeType : null,
            'file_size' => is_int($fileSize) ? $fileSize : strlen($contents),
            'meta' => is_array($metadata) ? $metadata : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(string $waId, WhatsappMessage $message): array
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $waId,
        ];

        return match ($message->message_type) {
            'template' => $base + [
                'type' => 'template',
                'template' => Arr::get($message->payload_json, 'template', []),
            ],
            'document' => $base + [
                'type' => 'document',
                'document' => Arr::get($message->payload_json, 'document', []),
            ],
            'interactive' => $base + [
                'type' => 'interactive',
                'interactive' => Arr::get($message->payload_json, 'interactive', []),
            ],
            default => $base + [
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message->body_text,
                ],
            ],
        };
    }
}
