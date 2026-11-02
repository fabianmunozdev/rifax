<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * WhatsApp vía Twilio (Programmable Messaging + Content API).
 *
 * - Texto y documentos: Messages API con Body / MediaUrl.
 * - Botones: plantilla "twilio/quick-reply" creada al vuelo por cada conjunto
 *   de botones (cacheada); el texto viaja como variable {{1}}. Dentro de la
 *   ventana de 24 h no requiere aprobación de Meta.
 * - Plantillas (fuera de 24 h): se mapea el nombre de la plantilla de Meta a un
 *   ContentSid aprobado en Twilio (TWILIO_CONTENT_SID_MAP).
 */
class TwilioWhatsappProvider implements WhatsappProvider
{
    /** Límite de Twilio para el cuerpo de una plantilla quick-reply. */
    protected const QUICK_REPLY_BODY_LIMIT = 1024;

    public function name(): string
    {
        return 'twilio';
    }

    public function isConfigured(): bool
    {
        return $this->accountSid() !== ''
            && (string) config('services.twilio.auth_token') !== ''
            && ($this->fromAddress() !== '' || (string) config('services.twilio.messaging_service_sid') !== '');
    }

    public function send(string $recipientDigits, WhatsappMessage $message): array
    {
        $params = $this->buildParams($recipientDigits, $message);
        $endpoint = rtrim((string) config('services.twilio.api_base_url'), '/')
            .'/2010-04-01/Accounts/'.$this->accountSid().'/Messages.json';

        try {
            $response = $this->client()->asForm()->post($endpoint, $params)->throw();
        } catch (RequestException $exception) {
            $response = $exception->response;

            throw new WhatsappProviderException($exception->getMessage(), $params, [
                'message' => (string) ($response?->json('message') ?? $exception->getMessage()),
                'status' => $response?->status(),
                'code' => $response?->json('code'),
                'body' => $response?->json() ?? $response?->body(),
            ], $exception);
        } catch (\Throwable $exception) {
            throw new WhatsappProviderException($exception->getMessage(), $params, [
                'message' => $exception->getMessage(),
            ], $exception);
        }

        return [
            'provider_message_id' => $response->json('sid'),
            'request' => $params,
            'response' => $response->json(),
        ];
    }

    public function downloadMedia(string $mediaReference): array
    {
        if (! str_starts_with($mediaReference, 'https://')) {
            throw new RuntimeException('Twilio media reference must be an https MediaUrl.');
        }

        // Twilio responde con una redirección a un almacenamiento temporal.
        $response = $this->client()->withOptions(['allow_redirects' => true])
            ->get($mediaReference)
            ->throw();

        $contents = $response->body();
        $mimeType = $response->header('Content-Type');

        return [
            'contents' => $contents,
            'mime_type' => $mimeType !== '' ? $mimeType : null,
            'file_size' => strlen($contents),
            'meta' => ['url' => $mediaReference, 'mime_type' => $mimeType],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildParams(string $recipientDigits, WhatsappMessage $message): array
    {
        $params = ['To' => 'whatsapp:+'.$recipientDigits];

        $messagingServiceSid = (string) config('services.twilio.messaging_service_sid');
        if ($messagingServiceSid !== '') {
            $params['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $params['From'] = $this->fromAddress();
        }

        $statusCallback = (string) config('services.twilio.status_callback_url');
        if ($statusCallback === '') {
            $statusCallback = route('webhooks.twilio.whatsapp.status');
        }
        $params['StatusCallback'] = $statusCallback;

        $payload = $message->payload_json ?? [];

        switch ($message->message_type) {
            case 'document':
                $params['MediaUrl'] = (string) Arr::get($payload, 'document.link');
                $caption = (string) (Arr::get($payload, 'document.caption') ?: $message->body_text);
                if ($caption !== '') {
                    $params['Body'] = $caption;
                }
                break;

            case 'template':
                $params += $this->templateParams($payload);
                break;

            case 'interactive':
                $params += $this->interactiveParams($message);
                break;

            default:
                $params['Body'] = (string) $message->body_text;
        }

        return $params;
    }

    /**
     * @return array<string, string>
     */
    protected function interactiveParams(WhatsappMessage $message): array
    {
        $body = (string) $message->body_text;
        $buttons = $this->extractButtons($message);

        if ($buttons !== [] && mb_strlen($body) <= self::QUICK_REPLY_BODY_LIMIT) {
            try {
                return [
                    'ContentSid' => $this->quickReplyContentSid($buttons),
                    'ContentVariables' => json_encode(['1' => $body], JSON_UNESCAPED_UNICODE),
                ];
            } catch (\Throwable $exception) {
                Log::warning('[Twilio] quick-reply content unavailable, falling back to text', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // Respaldo: texto con las opciones. El bot ya entiende las palabras clave.
        $options = collect($buttons)->map(fn (array $b): string => '• '.$b['title'])->implode(PHP_EOL);

        return ['Body' => $options === '' ? $body : $body.PHP_EOL.PHP_EOL.$options];
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    protected function extractButtons(WhatsappMessage $message): array
    {
        $buttons = Arr::get($message->payload_json, 'interactive_buttons');

        if (! is_array($buttons) || $buttons === []) {
            $buttons = collect(Arr::get($message->payload_json, 'interactive.action.buttons', []))
                ->map(fn ($b): array => [
                    'id' => (string) Arr::get($b, 'reply.id'),
                    'title' => (string) Arr::get($b, 'reply.title'),
                ])
                ->all();
        }

        return collect($buttons)
            ->filter(fn ($b): bool => is_array($b) && ($b['title'] ?? '') !== '')
            ->map(fn (array $b): array => ['id' => (string) ($b['id'] ?? $b['title']), 'title' => (string) $b['title']])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: string, title: string}>  $buttons
     */
    protected function quickReplyContentSid(array $buttons): string
    {
        $hash = substr(sha1(json_encode($buttons, JSON_UNESCAPED_UNICODE) ?: ''), 0, 16);
        $cacheKey = 'twilio:quick-reply-content:'.$this->accountSid().':'.$hash;

        return Cache::rememberForever($cacheKey, function () use ($buttons, $hash): string {
            $response = $this->client()
                ->asJson()
                ->post(rtrim((string) config('services.twilio.content_api_base_url'), '/').'/v1/Content', [
                    'friendly_name' => 'rifax_qr_'.$hash,
                    'language' => (string) config('services.twilio.content_language', 'es'),
                    'variables' => ['1' => 'Mensaje'],
                    'types' => [
                        'twilio/quick-reply' => [
                            'body' => '{{1}}',
                            'actions' => array_map(fn (array $b): array => [
                                'id' => $b['id'],
                                'title' => $b['title'],
                            ], $buttons),
                        ],
                        // Respaldo para canales sin botones.
                        'twilio/text' => ['body' => '{{1}}'],
                    ],
                ])
                ->throw();

            $sid = (string) $response->json('sid');

            if ($sid === '') {
                throw new RuntimeException('Twilio Content API did not return a sid.');
            }

            return $sid;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function templateParams(array $payload): array
    {
        $name = (string) Arr::get($payload, 'template.name', '');
        $map = config('services.twilio.content_sid_map', []);
        $contentSid = is_array($map) ? (string) ($map[$name] ?? '') : '';

        if ($contentSid === '') {
            throw new WhatsappProviderException(
                "No Twilio ContentSid mapped for template [{$name}].",
                ['template' => $name],
                ['message' => "No Twilio ContentSid mapped for template [{$name}]. Configure TWILIO_CONTENT_SID_MAP."],
            );
        }

        // Parámetros del cuerpo de la plantilla Meta → variables 1..n.
        $variables = [];
        $position = 1;
        foreach (Arr::get($payload, 'template.components', []) as $component) {
            if (strtolower((string) Arr::get($component, 'type')) !== 'body') {
                continue;
            }
            foreach (Arr::get($component, 'parameters', []) as $parameter) {
                $variables[(string) $position++] = (string) (Arr::get($parameter, 'text') ?? '');
            }
        }

        $params = ['ContentSid' => $contentSid];
        if ($variables !== []) {
            $params['ContentVariables'] = json_encode($variables, JSON_UNESCAPED_UNICODE);
        }

        return $params;
    }

    protected function client(): PendingRequest
    {
        $username = (string) (config('services.twilio.api_key_sid') ?: $this->accountSid());
        $password = (string) (config('services.twilio.api_key_secret') ?: config('services.twilio.auth_token'));

        return Http::timeout((int) config('services.whatsapp.timeout_seconds', 10))
            ->withBasicAuth($username, $password)
            ->acceptJson();
    }

    protected function accountSid(): string
    {
        return (string) config('services.twilio.account_sid');
    }

    protected function fromAddress(): string
    {
        $from = trim((string) config('services.twilio.whatsapp_from'));

        if ($from === '') {
            return '';
        }

        return str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:+'.ltrim($from, '+');
    }
}
