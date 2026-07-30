<?php

namespace App\Actions\WhatsApp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DownloadWhatsappMediaToStorageAction
{
    /**
     * @return array{disk: string, path: string, mime_type: string|null, file_size: int|null, meta: array<string, mixed>}
     */
    public function execute(string $mediaId, string $disk, string $path): array
    {
        $accessToken = (string) config('services.whatsapp.access_token');
        $graphVersion = trim((string) config('services.whatsapp.graph_version'), '/');
        $baseUrl = rtrim((string) config('services.whatsapp.api_base_url'), '/');

        if ($mediaId === '') {
            throw new RuntimeException('Missing WhatsApp media id.');
        }

        if ($accessToken === '' || $graphVersion === '' || $baseUrl === '') {
            throw new RuntimeException('Missing WhatsApp media download configuration.');
        }

        $metadataUrl = $baseUrl.'/'.$graphVersion.'/'.$mediaId;

        try {
            $metadataResponse = Http::timeout((int) config('services.whatsapp.timeout_seconds', 10))
                ->withToken($accessToken)
                ->acceptJson()
                ->get($metadataUrl)
                ->throw();

            $metadata = $metadataResponse->json();
            $downloadUrl = (string) Arr::get($metadata, 'url', '');

            if ($downloadUrl === '') {
                throw new RuntimeException('Unable to resolve WhatsApp media download URL.');
            }

            $downloadResponse = Http::timeout((int) config('services.whatsapp.timeout_seconds', 10))
                ->withToken($accessToken)
                ->get($downloadUrl)
                ->throw();

            $contents = $downloadResponse->body();

            Storage::disk($disk)->put($path, $contents);

            $mimeType = Arr::get($metadata, 'mime_type');
            $fileSize = Arr::get($metadata, 'file_size');

            if (! is_int($fileSize)) {
                $fileSize = is_string($contents) ? strlen($contents) : null;
            }

            return [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => is_string($mimeType) ? $mimeType : null,
                'file_size' => $fileSize,
                'meta' => is_array($metadata) ? $metadata : [],
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

