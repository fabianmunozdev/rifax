<?php

namespace App\Actions\WhatsApp;

use App\Models\ContentEntry;
use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class QueueOperationalCampaignWhatsappAction
{
    public function __construct(
        protected QueueOutboundWhatsappMessageAction $queueOutboundWhatsappMessageAction,
    ) {
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  array<string, scalar|null>  $context
     * @param  array<string, scalar|null>  $templateDefaults
     */
    public function execute(
        Customer $customer,
        string $intent,
        array $variables,
        array $context = [],
        ?string $fallback = null,
        int $dedupHours = 24,
        array $templateDefaults = [],
    ): ?WhatsappMessage {
        if ($this->recentDuplicateExists($customer, $intent, $context, $dedupHours)) {
            return null;
        }

        $contentEntry = ContentEntry::query()
            ->where('status', 'published')
            ->where('channel', 'whatsapp')
            ->where('locale', 'es')
            ->where('trigger_intent', $intent)
            ->orderByDesc('priority')
            ->first();

        $bodyText = $this->renderTemplate($contentEntry?->body_text, $variables)
            ?: $this->renderTemplate($contentEntry?->fallback_text, $variables)
            ?: $this->renderTemplate($fallback, $variables)
            ?: '';

        $payloadJson = array_merge($this->normalizePayload($context), [
            'intent' => $intent,
        ]);

        if ($this->shouldUseTemplateBridge($customer) && $contentEntry?->type === 'template_bridge') {
            $payloadJson['template'] = [
                'name' => (string) Arr::get(
                    $contentEntry->variables_json,
                    'template_name',
                    $templateDefaults['template_name'] ?? $intent,
                ),
                'language' => [
                    'code' => (string) Arr::get(
                        $contentEntry->variables_json,
                        'language',
                        $templateDefaults['language'] ?? config('services.whatsapp.default_template_language', 'es_CO'),
                    ),
                ],
                'components' => [[
                    'type' => 'body',
                    'parameters' => collect(Arr::get($contentEntry->variables_json, 'body_parameters', array_keys($variables)))
                        ->map(fn (string $parameter): array => [
                            'type' => 'text',
                            'text' => (string) ($variables[$parameter] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ]],
            ];
            $payloadJson['template_bridge'] = [
                'intent' => $intent,
            ];

            return $this->queueOutboundWhatsappMessageAction->execute(
                customer: $customer,
                messageType: 'template',
                bodyText: $bodyText,
                payloadJson: $payloadJson,
            );
        }

        $payloadJson['text'] = [
            'body' => $bodyText,
        ];

        return $this->queueOutboundWhatsappMessageAction->execute(
            customer: $customer,
            messageType: 'text',
            bodyText: $bodyText,
            payloadJson: $payloadJson,
        );
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    protected function recentDuplicateExists(Customer $customer, string $intent, array $context, int $dedupHours): bool
    {
        $query = $customer->whatsappMessages()
            ->where('direction', 'outbound')
            ->where('created_at', '>=', now()->subHours($dedupHours))
            ->whereRaw("whatsapp_messages.payload_json->>'intent' = ?", [$intent]);

        foreach ($this->normalizePayload($context) as $key => $value) {
            if ($value === null) {
                continue;
            }

            $query->whereRaw("whatsapp_messages.payload_json->>? = ?", [$key, (string) $value]);
        }

        return $query->exists();
    }

    protected function shouldUseTemplateBridge(Customer $customer): bool
    {
        $customer->loadMissing('currentConversationState');

        $lastUserMessageAt = $customer->currentConversationState?->last_user_message_at
            ?? $customer->last_interaction_at;

        if ($lastUserMessageAt === null) {
            return true;
        }

        return $lastUserMessageAt->lt(now()->subHours(24));
    }

    /**
     * @param  array<string, scalar|null>  $payload
     * @return array<string, scalar|null>
     */
    protected function normalizePayload(array $payload): array
    {
        return collect($payload)
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => is_bool($value) ? (int) $value : $value])
            ->all();
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    protected function renderTemplate(?string $template, array $variables): string
    {
        if (blank($template)) {
            return '';
        }

        return collect($variables)->reduce(
            fn (string $text, mixed $value, string $key): string => Str::replace('{'.$key.'}', (string) ($value ?? ''), $text),
            $template,
        );
    }
}
