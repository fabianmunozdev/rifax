<?php

namespace App\Actions\WhatsApp;

use App\Models\ContentEntry;
use App\Models\Purchase;
use App\Support\WhatsAppReply;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendPurchasePaidWhatsappNotificationAction
{
    public function __construct(
        protected QueueOutboundWhatsappMessageAction $queueOutboundWhatsappMessageAction,
    ) {
    }

    public function execute(Purchase $purchase): void
    {
        $purchase->loadMissing(['customer', 'raffle', 'conversationStates']);
        $purchase->loadMissing('ticket');

        if ($purchase->customer === null) {
            return;
        }

        $contentEntry = ContentEntry::query()
            ->where('status', 'published')
            ->where('channel', 'whatsapp')
            ->where('locale', 'es')
            ->where('trigger_intent', 'payment_approved_ticket')
            ->orderByDesc('priority')
            ->first();

        $variables = [
            'customer_name' => $purchase->customer->name ?: 'cliente',
            'raffle_title' => $purchase->raffle_title_snapshot ?: $purchase->raffle?->title ?: 'tu rifa',
            'ticket_code' => $purchase->ticket?->code ?: 'P-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT),
            'ticket_url' => $purchase->ticket?->public_url ?: '',
        ];

        $bodyText = $this->renderTemplate($contentEntry?->body_text, $variables)
            ?: (function () use ($variables): string {
                $base = "Hola {$variables['customer_name']}, tu pago para {$variables['raffle_title']} fue aprobado. ";
                if (filled($variables['ticket_url'] ?? null)) {
                    $base .= "Tu boleto oficial: {$variables['ticket_url']} . ";
                }
                $base .= 'Cualquier novedad nos comunicamos por este medio. Gracias por tu participacion.';

                return $base;
            })();

        if ($this->shouldUseTemplateBridge($purchase) && $contentEntry !== null && $contentEntry->type === 'template_bridge') {
            $payloadJson = [
                'template' => [
                    'name' => (string) Arr::get($contentEntry->variables_json, 'template_name', 'payment_approved_ticket'),
                    'language' => [
                        'code' => (string) Arr::get(
                            $contentEntry->variables_json,
                            'language',
                            config('services.whatsapp.default_template_language', 'es_CO'),
                        ),
                    ],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => collect(Arr::get($contentEntry->variables_json, 'body_parameters', ['customer_name', 'raffle_title']))
                            ->map(fn (string $parameter): array => [
                                'type' => 'text',
                                'text' => (string) ($variables[$parameter] ?? ''),
                            ])
                            ->values()
                            ->all(),
                    ]],
                ],
                'template_bridge' => [
                    'intent' => 'payment_approved_ticket',
                ],
                'ticket_id' => $purchase->ticket?->id,
            ];

            $this->queueOutboundWhatsappMessageAction->execute(
                customer: $purchase->customer,
                messageType: 'template',
                bodyText: $bodyText,
                payloadJson: $payloadJson,
            );

            return;
        }

        $buttons = [];
        if (filled($variables['ticket_url'] ?? null)) {
            $buttons[] = ['id' => 'view_ticket:'.($purchase->ticket?->token ?? 'paid'), 'title' => 'Ver boleto'];
        }
        $buttons[] = ['id' => 'paid_menu', 'title' => 'Menú'];

        if ($buttons !== []) {
            $reply = WhatsAppReply::make($bodyText, $buttons);
            $this->queueOutboundWhatsappMessageAction->execute(
                customer: $purchase->customer,
                messageType: 'interactive',
                bodyText: $bodyText,
                payloadJson: [
                    'interactive' => $reply->toInteractiveMetaPayload(),
                    'interactive_buttons' => $reply->buttons,
                    'ticket_id' => $purchase->ticket?->id,
                ],
            );

            return;
        }

        $this->queueOutboundWhatsappMessageAction->execute(
            customer: $purchase->customer,
            messageType: 'text',
            bodyText: $bodyText,
            payloadJson: [
                'text' => ['body' => $bodyText],
                'ticket_id' => $purchase->ticket?->id,
            ],
        );
    }

    protected function shouldUseTemplateBridge(Purchase $purchase): bool
    {
        $conversationState = $purchase->conversationStates()
            ->latest('updated_at')
            ->first();

        if ($conversationState?->last_user_message_at === null) {
            return true;
        }

        return $conversationState->last_user_message_at->lt(now()->subHours(24));
    }

    /**
     * @param  array<string, string>  $variables
     */
    protected function renderTemplate(?string $template, array $variables): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return collect($variables)->reduce(
            fn (string $text, string $value, string $key): string => Str::replace('{'.$key.'}', $value, $text),
            $template,
        );
    }
}
