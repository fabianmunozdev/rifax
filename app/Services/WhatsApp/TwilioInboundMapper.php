<?php

namespace App\Services\WhatsApp;

/**
 * Convierte los parámetros de Twilio al formato de mensaje/estado de Meta, que
 * es el que entiende ProcessIncomingWhatsappMessageAction. Así el bot no
 * depende del proveedor.
 */
class TwilioInboundMapper
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{message: array<string, mixed>, contacts: list<array<string, mixed>>}
     */
    public function message(array $params): array
    {
        $waId = (string) preg_replace('/\D+/', '', (string) ($params['WaId'] ?? $params['From'] ?? ''));
        $body = (string) ($params['Body'] ?? '');

        $message = [
            'from' => $waId,
            'id' => (string) ($params['MessageSid'] ?? ''),
            'timestamp' => (string) now()->timestamp,
            'provider' => 'twilio',
        ];

        $buttonPayload = (string) ($params['ButtonPayload'] ?? '');
        $numMedia = (int) ($params['NumMedia'] ?? 0);

        if ($buttonPayload !== '') {
            $message['type'] = 'interactive';
            $message['interactive'] = [
                'type' => 'button_reply',
                'button_reply' => [
                    'id' => $buttonPayload,
                    'title' => (string) ($params['ButtonText'] ?? $body),
                ],
            ];
        } elseif ($numMedia > 0) {
            $mime = strtolower((string) ($params['MediaContentType0'] ?? ''));
            $media = [
                'id' => (string) ($params['MediaUrl0'] ?? ''),
                'mime_type' => $mime,
                'caption' => $body,
            ];

            $type = match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'audio/') => 'audio',
                str_starts_with($mime, 'video/') => 'video',
                default => 'document',
            };

            $message['type'] = $type;
            $message[$type] = $media;
        } else {
            $message['type'] = 'text';
            $message['text'] = ['body' => $body];
        }

        $contact = ['wa_id' => $waId];
        if (($params['ProfileName'] ?? '') !== '') {
            $contact['profile'] = ['name' => (string) $params['ProfileName']];
        }

        return ['message' => $message, 'contacts' => [$contact]];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function status(array $params): array
    {
        $status = strtolower((string) ($params['MessageStatus'] ?? $params['SmsStatus'] ?? ''));

        $mapped = match ($status) {
            'undelivered', 'failed', 'canceled' => 'failed',
            'queued', 'accepted', 'sending', 'scheduled' => 'sent',
            default => $status,
        };

        $event = [
            'id' => (string) ($params['MessageSid'] ?? ''),
            'status' => $mapped,
            'timestamp' => (string) now()->timestamp,
            'recipient_id' => (string) preg_replace('/\D+/', '', (string) ($params['To'] ?? '')),
            'twilio_status' => $status,
        ];

        if (($params['ErrorCode'] ?? '') !== '') {
            $event['errors'] = [[
                'code' => (string) $params['ErrorCode'],
                'title' => (string) ($params['ErrorMessage'] ?? 'Twilio error '.$params['ErrorCode']),
            ]];
        }

        return $event;
    }
}
