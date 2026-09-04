<?php

namespace App\Support;

use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\RafflePickerIntent;
use InvalidArgumentException;

final class PickerPurchaseOrchestrator
{
    /**
     * @param  list<string>  $numbers
     * @return array{ok:true, purchase:Purchase, customer:Customer, outbound_sent:bool, outbound_status:string, outbound_error:?string}
     */
    public static function confirmFromIntent(Customer $customer, Raffle $raffle, RafflePickerIntent $intent, array $numbers): array
    {
        if ($intent->consumed_at !== null) {
            throw new InvalidArgumentException('Esta selección visual ya fue usada anteriormente. Si deseas continuar, vuelve al selector y genera una nueva selección.');
        }
        if ($intent->isExpired()) {
            throw new InvalidArgumentException('La selección visual ya venció. Vuelve al selector de números y genera una nueva selección para continuar.');
        }
        if ($raffle->status !== 'published') {
            throw new InvalidArgumentException('La rifa asociada a esta selección ya no está disponible para compra.');
        }

        $state = ConversationState::query()->firstOrCreate(
            [
                'customer_id' => $customer->id,
                'channel' => 'whatsapp',
            ],
            [
                'status' => 'main_menu',
            ]
        );

        if ($state->purchase !== null && in_array($state->purchase->status, ['reserved', 'payment_submitted', 'under_review', 'rejected'], true)) {
            throw new InvalidArgumentException('Ya tienes una compra en curso. Envía tu comprobante, espera la revisión o escribe CANCELAR si deseas liberar tu reserva actual antes de iniciar otra compra.');
        }

        $required = self::requiredOnboardingStatus($customer);
        if ($required !== null) {
            $metadata = $state->metadata_json ?? [];
            $metadata = array_merge($metadata, [
                'pending_action' => 'picker_intent',
                'pending_picker_token' => $intent->token,
            ]);
            $state->forceFill([
                'status' => $required,
                'metadata_json' => $metadata,
            ])->save();
            $outbound = self::dispatchOutboundText($customer, self::renderOnboardingPrompt($required));

            return [
                'ok' => true,
                'purchase' => $state->purchase ?? \App::make(Purchase::class),
                'customer' => $customer,
                'outbound_sent' => $outbound['sent'],
                'outbound_status' => $outbound['status'],
                'outbound_error' => $outbound['error'],
                'requires_onboarding' => true,
                'onboarding_status' => $required,
            ];
        }

        if ($numbers === [] || count($numbers) !== $intent->quantity) {
            throw new InvalidArgumentException('La selección visual no es válida para continuar. Vuelve al selector y genera una nueva selección.');
        }

        try {
            $purchase = app(\App\Actions\Purchases\ReserveNumbersAction::class)->execute(
                $customer,
                $raffle,
                $numbers,
                'manual',
                self::buildPickerTraceMetadata($intent),
            );
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('La disponibilidad de los números seleccionados cambió antes de finalizar la compra. Vuelve al selector visual y elige nuevamente.');
        }

        $intent->forceFill([
            'consumed_at' => now(),
            'consumed_by_customer_id' => $customer->id,
        ])->save();

        $body = self::renderReservationConfirmationBody($purchase);
        $buttons = [
            ['id' => 'cancel_purchase', 'title' => 'Cancelar'],
            ['id' => 'payment_menu', 'title' => 'Menú'],
        ];

        $outbound = self::dispatchOutboundInteractive($customer, $body, $buttons);

        return [
            'ok' => true,
            'purchase' => $purchase,
            'customer' => $customer,
            'outbound_sent' => $outbound['sent'],
            'outbound_status' => $outbound['status'],
            'outbound_error' => $outbound['error'],
        ];
    }

    public static function requiredOnboardingStatus(Customer $customer): ?string
    {
        if ($customer->accepted_privacy_at === null) {
            return 'onboarding_privacy_consent';
        }
        if (! filled($customer->name)) {
            return 'onboarding_collect_name';
        }
        if (! filled($customer->document_number)) {
            return 'onboarding_collect_document';
        }

        return null;
    }

    public static function renderOnboardingPrompt(string $status): string
    {
        return match ($status) {
            'onboarding_privacy_consent' => 'Antes de continuar con tu compra necesitamos tu autorización para el tratamiento de datos personales y la aceptación de las condiciones de compra (nombre y cédula) con el fin de gestionar tu participación. Responde ACEPTAR o NO ACEPTAR por este mismo chat de WhatsApp.',
            'onboarding_collect_name' => 'Para continuar con tu compra, por favor responde con tu nombre completo en este chat de WhatsApp.',
            'onboarding_collect_document' => 'Ahora responde con tu número de cédula (solo números) en este chat de WhatsApp.',
            default => '',
        };
    }

    /**
     * @return array{sent:bool, status:string, error:?string, message_id:?string}
     */
    public static function dispatchOutboundInteractive(Customer $customer, string $body, array $buttons): array
    {
        try {
            $message = $customer->whatsappMessages()->create([
                'direction' => 'outbound',
                'message_type' => 'interactive',
                'status' => 'queued',
                'body_text' => $body,
                'payload_json' => [
                    'interactive' => [
                        'type' => 'button',
                        'body' => ['text' => $body],
                        'action' => [
                            'buttons' => collect($buttons)->take(3)->map(fn (array $b, int $i): array => [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => (string) ($b['id'] ?? 'btn_'.($i + 1)),
                                    'title' => mb_substr(trim((string) ($b['title'] ?? '')), 0, 20),
                                ],
                            ])->values()->all(),
                        ],
                    ],
                    'source' => 'picker_confirm_api',
                ],
            ]);
            $result = app(\App\Actions\WhatsApp\DispatchOutboundWhatsappMessageAction::class)->execute($customer, $message, true);

            return [
                'sent' => $result->status === 'sent',
                'status' => $result->status,
                'error' => null,
                'message_id' => $result->provider_message_id,
            ];
        } catch (\Throwable $e) {
            return [
                'sent' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'message_id' => null,
            ];
        }
    }

    /**
     * @return array{sent:bool, status:string, error:?string, message_id:?string}
     */
    public static function dispatchOutboundText(Customer $customer, string $body): array
    {
        try {
            $message = $customer->whatsappMessages()->create([
                'direction' => 'outbound',
                'message_type' => 'text',
                'status' => 'queued',
                'body_text' => $body,
                'payload_json' => ['source' => 'picker_confirm_api'],
            ]);
            $result = app(\App\Actions\WhatsApp\DispatchOutboundWhatsappMessageAction::class)->execute($customer, $message, true);

            return [
                'sent' => $result->status === 'sent',
                'status' => $result->status,
                'error' => null,
                'message_id' => $result->provider_message_id,
            ];
        } catch (\Throwable $e) {
            return [
                'sent' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'message_id' => null,
            ];
        }
    }

    public static function renderReservationConfirmationBody(Purchase $purchase): string
    {
        $purchase->loadMissing(['numbers.raffleNumber', 'raffle']);
        $reservedNumbers = collect($purchase->numbers ?? [])->pluck('number')->implode(', ');
        if ($reservedNumbers === '') {
            $reservedNumbers = collect($purchase->selected_numbers_json ?? [])->pluck('number')->implode(', ');
        }
        $message = 'Listo. Tu reserva fue creada correctamente.'.PHP_EOL.PHP_EOL
            .'Números reservados: '.$reservedNumbers.PHP_EOL
            .'Total a pagar: '.self::formatMoneyWithoutDecimals($purchase->total_amount).PHP_EOL
            .self::renderReservationWindowMessage($purchase);

        $paymentInstructions = self::renderPaymentInstructionsList($purchase);
        if ($paymentInstructions !== '') {
            $message .= PHP_EOL.PHP_EOL
                .'Opciones de pago disponibles:'.PHP_EOL.PHP_EOL
                .$paymentInstructions;
        }

        return $message.PHP_EOL.PHP_EOL
            .'Después de pagar, envía una foto clara del comprobante por este chat para continuar.';
    }

    public static function renderReservationWindowMessage(Purchase $purchase): string
    {
        $minutes = $purchase->raffle?->reservation_timeout_minutes;
        if (! is_int($minutes) || $minutes < 1) {
            $minutes = $purchase->reserved_until?->diffInMinutes(now());
        }
        if (! is_int($minutes) || $minutes < 1) {
            return 'Tu reserva quedará activa por tiempo limitado.';
        }
        $label = $minutes === 1 ? 'minuto' : 'minutos';

        return "Tienes {$minutes} {$label} para enviar tu comprobante de pago antes de que los números sean liberados nuevamente.";
    }

    public static function renderPaymentInstructionsList(Purchase $purchase): string
    {
        $snapshot = collect($purchase->payment_instructions_snapshot ?? [])
            ->map(function (mixed $method, int $index): ?string {
                if (! is_array($method)) {
                    return null;
                }

                return self::renderPaymentInstructionEntry($method, $index + 1);
            })
            ->filter()
            ->values();

        if ($snapshot->isNotEmpty()) {
            return $snapshot->implode(PHP_EOL.PHP_EOL);
        }

        return \App\Models\PaymentMethod::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->map(fn (\App\Models\PaymentMethod $m, int $i): string => self::renderPaymentInstructionEntry([
                'name' => $m->name,
                'instructions' => $m->instructions,
                'account_holder' => $m->account_holder,
                'account_reference' => $m->account_reference,
                'details' => $m->details_json,
            ], $i + 1))
            ->filter()
            ->implode(PHP_EOL.PHP_EOL);
    }

    /**
     * @param  array{name?: mixed, instructions?: mixed, account_holder?: mixed, account_reference?: mixed, details?: mixed}  $method
     */
    public static function renderPaymentInstructionEntry(array $method, ?int $index = null): string
    {
        $lines = [];
        $name = trim((string) ($method['name'] ?? ''));
        $holder = trim((string) ($method['account_holder'] ?? ''));
        $ref = trim((string) ($method['account_reference'] ?? ''));
        $instructions = trim((string) ($method['instructions'] ?? ''));
        $details = is_array($method['details'] ?? null) ? $method['details'] : [];

        if ($index !== null) {
            $lines[] = $name !== '' ? "{$index}. {$name}" : "{$index}. Método de pago";
        } elseif ($name !== '') {
            $lines[] = $name;
        }
        if ($holder !== '') {
            $lines[] = "Titular: {$holder}";
        }
        if ($ref !== '') {
            $lines[] = "Número de cuenta: {$ref}";
            foreach (['banco', 'Banco', 'bank', 'Bank'] as $k) {
                if (isset($details[$k]) && is_string($details[$k]) && trim($details[$k]) !== '') {
                    $lines[] = 'Banco: '.trim($details[$k]);
                    break;
                }
            }
        }
        if ($instructions !== '') {
            $lines[] = "Cómo pagar: {$instructions}";
        }

        return implode(PHP_EOL, $lines);
    }

    public static function formatMoneyWithoutDecimals(mixed $amount): string
    {
        if (! is_numeric($amount)) {
            return '$0';
        }

        return '$'.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPickerTraceMetadata(RafflePickerIntent $intent): array
    {
        $trace = collect($intent->metadata_json ?? [])
            ->filter(static function (mixed $value): bool {
                if (is_array($value)) {
                    return $value !== [];
                }

                return $value !== null && $value !== '';
            })
            ->all();

        return [
            'picker_trace' => array_merge($trace, [
                'source' => $intent->source ?: 'picker_direct',
                'intent_token' => $intent->token,
                'quantity' => $intent->quantity,
                'selected_numbers' => $intent->selected_numbers_json ?? [],
                'confirmed_via_api' => true,
                'confirmed_at' => now()->toIso8601String(),
            ]),
        ];
    }
}
