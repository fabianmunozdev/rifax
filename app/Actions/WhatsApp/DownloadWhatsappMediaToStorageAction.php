<?php

namespace App\Actions\WhatsApp;

use App\Services\WhatsApp\WhatsappProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DownloadWhatsappMediaToStorageAction
{
    public function __construct(
        protected WhatsappProvider $provider,
    ) {}

    /**
     * $mediaId es el media id de Meta o la MediaUrl de Twilio.
     *
     * @return array{disk: string, path: string, mime_type: string|null, file_size: int|null, meta: array<string, mixed>}
     */
    public function execute(string $mediaId, string $disk, string $path): array
    {
        if ($mediaId === '') {
            throw new RuntimeException('Missing WhatsApp media id.');
        }

        try {
            $media = $this->provider->downloadMedia($mediaId);

            Storage::disk($disk)->put($path, $media['contents']);

            return [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $media['mime_type'],
                'file_size' => $media['file_size'],
                'meta' => $media['meta'],
            ];
        } catch (RequestException $exception) {
            $response = $exception->response;

            throw new RuntimeException(json_encode([
                'message' => $exception->getMessage(),
                'status' => $response?->status(),
                'body' => $response?->json() ?? $response?->body(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $exception->getMessage(), previous: $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }
}
