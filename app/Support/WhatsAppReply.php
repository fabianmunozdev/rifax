<?php

namespace App\Support;

use InvalidArgumentException;

final class WhatsAppReply
{
    public readonly string $body;

    /** @var list<array{id: string, title: string}> */
    public readonly array $buttons;

    /**
     * @param  string  $body
     * @param  list<array{id?: string, title?: string, 0?: string, 1?: string}|string>  $buttons
     */
    public function __construct(string $body, array $buttons = [])
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('WhatsAppReply body cannot be empty.');
        }

        $cleanButtons = [];
        foreach ($buttons as $idx => $button) {
            if (is_string($button)) {
                $title = trim($button);
                $id = 'btn_'.($idx + 1).'_'.mb_strtolower((string) preg_replace('/[^A-Z0-9Ñ]+/u', '_', $this->normalize($title)));
            } else {
                $title = trim((string) ($button['title'] ?? $button[1] ?? ''));
                $id = trim((string) ($button['id'] ?? $button[0] ?? ''));
                if ($id === '') {
                    $id = 'btn_'.($idx + 1).'_'.mb_strtolower((string) preg_replace('/[^A-Z0-9Ñ]+/u', '_', $this->normalize($title)));
                }
            }

            if ($title === '') {
                continue;
            }

            if (mb_strlen($title) > 20) {
                $title = mb_substr($title, 0, 19).'…';
            }

            if (mb_strlen($id) > 256) {
                $id = substr($id, 0, 250).'_'.dechex(crc32($id));
            }

            $cleanButtons[] = ['id' => $id, 'title' => $title];
        }

        if (count($cleanButtons) > 3) {
            $cleanButtons = array_slice($cleanButtons, 0, 3);
        }

        $this->body = $body;
        $this->buttons = $cleanButtons;
    }

    public function hasButtons(): bool
    {
        return $this->buttons !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toInteractiveMetaPayload(): array
    {
        $buttons = [];
        foreach ($this->buttons as $button) {
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'],
                    'title' => $button['title'],
                ],
            ];
        }

        return [
            'type' => 'button',
            'body' => [
                'text' => $this->body,
            ],
            'action' => [
                'buttons' => $buttons,
            ],
        ];
    }

    /**
     * @param  list<array{id: string, title: string}>  $buttons
     */
    public static function make(string $body, array $buttons = []): self
    {
        return new self($body, $buttons);
    }

    private function normalize(string $text): string
    {
        $normalized = mb_strtoupper($text);
        if (function_exists('normalizer_normalize')) {
            $decomposed = normalizer_normalize($normalized, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                $normalized = (string) preg_replace('/\p{Mn}/u', '', $decomposed);
            }
        }

        return trim($normalized);
    }
}
