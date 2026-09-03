<?php

namespace App\Actions\WhatsApp;

use App\Models\ContentEntry;
use App\Models\Purchase;
use App\Models\Raffle;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SendRaffleWinnerWhatsappNotificationAction
{
    public function __construct(
        protected QueueOutboundWhatsappMessageAction $queueOutboundWhatsappMessageAction,
    ) {
    }

    public function execute(Raffle $raffle): bool
    {
        $raffle->loadMissing([
            'winnerNumber.purchaseNumber.purchase.customer',
            'winnerNumber.purchaseNumber.purchase.ticket',
            'winnerNumber.purchaseNumber.purchase.conversationStates',
        ]);

        $purchase = $raffle->winnerNumber?->purchaseNumber?->purchase;

        if ($purchase?->customer === null) {
            return false;
        }

        $contentEntry = ContentEntry::query()
            ->where('status', 'published')
            ->where('channel', 'whatsapp')
            ->where('locale', 'es')
            ->where('trigger_intent', 'raffle_winner_notification')
            ->orderByDesc('priority')
            ->first();

        $variables = [
            'customer_name' => $purchase->customer->name ?: 'cliente',
            'raffle_title' => $raffle->title ?: 'tu rifa',
            'winning_number' => $raffle->winnerNumber?->number ?: $raffle->result_number ?: '',
            'result_number' => $raffle->result_number ?: '',
            'ticket_code' => $purchase->ticket?->code ?: 'P-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT),
            'ticket_url' => $purchase->ticket?->public_url ?: '',
            'lottery_name' => $raffle->lottery_name ?: 'la lotería oficial',
            'lottery_draw_number' => $raffle->lottery_draw_number ?: '',
            'lottery_reference_url' => $raffle->lottery_reference_url ?: '',
        ];

        $bodyText = $this->renderTemplate($contentEntry?->body_text, $variables)
            ?: 'Hola '.$variables['customer_name']
                .', tu numero '.$variables['winning_number']
                .' fue ganador en '.$variables['raffle_title']
                .'. Conserva tu boleto '.$variables['ticket_code']
                .' y revisa '.$variables['ticket_url'].'.';

        $payloadJson = [
            'intent' => 'raffle_winner_notification',
            'raffle_id' => $raffle->id,
            'ticket_id' => $purchase->ticket?->id,
            'winning_number' => $variables['winning_number'],
            'result_number' => $variables['result_number'],
        ];

        if ($this->shouldUseTemplateBridge($purchase) && $contentEntry !== null && $contentEntry->type === 'template_bridge') {
            $payloadJson['template'] = [
                'name' => (string) Arr::get($contentEntry->variables_json, 'template_name', 'raffle_winner_notification'),
                'language' => [
                    'code' => (string) Arr::get(
                        $contentEntry->variables_json,
                        'language',
                        config('services.whatsapp.default_template_language', 'es_CO'),
                    ),
                ],
                'components' => [[
                    'type' => 'body',
                    'parameters' => collect(Arr::get(
                        $contentEntry->variables_json,
                        'body_parameters',
                        ['customer_name', 'raffle_title', 'winning_number', 'ticket_code', 'ticket_url'],
                    ))
                        ->map(fn (string $parameter): array => [
                            'type' => 'text',
                            'text' => (string) ($variables[$parameter] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ]],
            ];

            $this->queueOutboundWhatsappMessageAction->execute(
                customer: $purchase->customer,
                messageType: 'template',
                bodyText: $bodyText,
                payloadJson: $payloadJson,
            );

            return true;
        }

        $payloadJson['text'] = ['body' => $bodyText];

        $this->queueOutboundWhatsappMessageAction->execute(
            customer: $purchase->customer,
            messageType: 'text',
            bodyText: $bodyText,
            payloadJson: $payloadJson,
        );

        return true;
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
