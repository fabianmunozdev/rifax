<?php

namespace App\Actions\Content;

use App\Models\ContentEntry;
use Illuminate\Support\Str;

class ResolvePublishedContentAction
{
    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function byKey(string $key, array $variables = [], ?string $fallback = null): string
    {
        $entry = ContentEntry::query()
            ->where('status', 'published')
            ->where('channel', 'whatsapp')
            ->where('locale', 'es')
            ->where('key', $key)
            ->orderByDesc('priority')
            ->first();

        return $this->render($entry, $variables, $fallback);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function byIntent(string $intent, array $variables = [], ?string $fallback = null): string
    {
        $entry = ContentEntry::query()
            ->where('status', 'published')
            ->where('channel', 'whatsapp')
            ->where('locale', 'es')
            ->where('trigger_intent', $intent)
            ->orderByDesc('priority')
            ->first();

        return $this->render($entry, $variables, $fallback);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    protected function render(?ContentEntry $entry, array $variables, ?string $fallback = null): string
    {
        $template = $entry?->body_text ?: $entry?->fallback_text ?: $fallback ?: '';

        return collect($variables)->reduce(
            fn (string $text, mixed $value, string $key): string => Str::replace('{'.$key.'}', (string) ($value ?? ''), $text),
            $template,
        );
    }
}
