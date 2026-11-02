<?php

namespace App\Actions\WhatsApp\Bot;

use Illuminate\Support\Arr;

/**
 * Convierte el mensaje recibido de WhatsApp (texto, botón, imagen) a un formato común.
 */
class InboundNormalizer
{
    public function __construct(
        protected BotKeywords $botKeywords,
    ) {}

    /**
     * @param  array<mixed>  $message
     * @return array{type: string, body_text: string|null, provider_message_id: string|null}
     */
    public function normalizeMessage(array $message): array
    {
        $type = (string) Arr::get($message, 'type', 'other');

        $bodyText = match ($type) {
            'text' => Arr::get($message, 'text.body'),
            'image' => Arr::get($message, 'image.caption'),
            'interactive' => $this->normalizeInteractiveBody($message),
            'button' => $this->normalizeButtonBody($message),
            default => null,
        };

        return [
            'type' => $type,
            'body_text' => $bodyText,
            'provider_message_id' => Arr::get($message, 'id'),
        ];
    }

    /**
     * @param  array<mixed>  $message
     */
    public function normalizeInteractiveBody(array $message): ?string
    {
        $interactiveType = (string) Arr::get($message, 'interactive.type', '');

        $payload = match ($interactiveType) {
            'button_reply' => (string) Arr::get($message, 'interactive.button_reply.id'),
            'list_reply' => (string) Arr::get($message, 'interactive.list_reply.id'),
            default => '',
        };

        if ($payload === '') {
            return null;
        }

        return $this->botKeywords->expandButtonPayloadToText($payload);
    }

    /**
     * @param  array<mixed>  $message
     */
    public function normalizeButtonBody(array $message): ?string
    {
        $payload = (string) Arr::get($message, 'button.payload');
        $text = (string) Arr::get($message, 'button.text');

        if ($payload !== '') {
            return $this->botKeywords->expandButtonPayloadToText($payload);
        }

        return $text !== '' ? $text : null;
    }
}
