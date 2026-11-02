<?php

namespace App\Actions\WhatsApp\Bot;

use App\Actions\Content\ResolvePublishedContentAction;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Support\PickerAuthToken;
use App\Support\WhatsAppReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Textos y botones que envía el bot (menús, instrucciones de pago, información de rifas).
 */
class BotMessages
{
    public function __construct(
        protected BotRaffles $botRaffles,
        protected ResolvePublishedContentAction $resolvePublishedContentAction,
    ) {}

    public function renderPrivacyConsentPrompt(): WhatsAppReply|string
    {
        $body = 'Antes de continuar con tu compra necesitamos tu autorización para el tratamiento de datos personales y la aceptación de las condiciones de compra (nombre y cédula) con el fin de gestionar tu participación.'.PHP_EOL.PHP_EOL
            .'Responde ACEPTAR o NO ACEPTAR, o usa los botones de abajo.';

        return WhatsAppReply::make($body, [
            ['id' => 'privacy_accept', 'title' => 'Aceptar'],
            ['id' => 'privacy_reject', 'title' => 'No acepto'],
        ]);
    }

    public function renderCollectNamePrompt(): WhatsAppReply|string
    {
        return 'Para continuar con tu compra, por favor responde con tu nombre completo.';
    }

    public function renderCollectDocumentPrompt(): WhatsAppReply|string
    {
        return 'Ahora responde con tu número de cédula (solo números).';
    }

    /**
     * @return list<string>
     */
    public function extractNumbers(string $text, int $digits): array
    {
        return collect(preg_split('/[\s,;]+/', trim($text)) ?: [])
            ->filter()
            ->map(function (string $value) use ($digits): string {
                return str_pad($value, $digits, '0', STR_PAD_LEFT);
            })
            ->values()
            ->all();
    }

    public function renderMainMenu(): WhatsAppReply|string
    {
        $welcome = $this->resolvePublishedContentAction->byKey(
            'system.menu.welcome',
            [],
            'Hola, soy el asistente de Rifax. Responde con la opción que necesitas o usa los botones de abajo.',
        );

        $body = $welcome.PHP_EOL.PHP_EOL
            .'Estas son tus opciones:'.PHP_EOL
            .'1. Comprar'.PHP_EOL
            .'2. Números disponibles'.PHP_EOL
            .'3. Mis números'.PHP_EOL
            .'4. Estadísticas'.PHP_EOL
            .'5. Próximas rifas'.PHP_EOL
            .'6. Condiciones'.PHP_EOL
            .'7. Ayuda'.PHP_EOL.PHP_EOL
            .'Responde con el número de la opción o toca un botón.';

        return WhatsAppReply::make($body, [
            ['id' => 'menu_1', 'title' => 'Comprar'],
            ['id' => 'menu_3', 'title' => 'Mis números'],
            ['id' => 'menu_7', 'title' => 'Ayuda'],
        ]);
    }

    public function renderRaffleSelection(Raffle $raffle): WhatsAppReply|string
    {
        $body = 'Tenemos esta rifa activa:'.PHP_EOL
            ."{$raffle->title}".PHP_EOL.PHP_EOL
            .'Valor por número: '.$this->formatMoneyWithoutDecimals($raffle->price_per_number).PHP_EOL
            .$this->formatLotteryReference($raffle).PHP_EOL
            .'Fecha: '.$this->formatRaffleDrawDate($raffle).PHP_EOL.PHP_EOL
            .'Responde:'.PHP_EOL
            .'1. Continuar'.PHP_EOL
            .'2. Volver al menú';

        return WhatsAppReply::make($body, [
            ['id' => 'raffle_continue', 'title' => 'Continuar'],
            ['id' => 'raffle_back', 'title' => 'Menú'],
        ]);
    }

    /**
     * @param  Collection<int, Raffle>  $raffles
     */
    public function renderRaffleOptions(Collection $raffles): WhatsAppReply|string
    {
        $options = $raffles
            ->values()
            ->map(function (Raffle $raffle, int $index): string {
                $position = $index + 1;

                return $position.'. '.$raffle->title
                    .' | Valor: '.$this->formatMoneyWithoutDecimals($raffle->price_per_number)
                    .' | '.$this->formatLotteryReference($raffle)
                    .' | Fecha: '.$this->formatRaffleDrawDate($raffle);
            })
            ->implode(PHP_EOL);

        return 'Tenemos varias rifas activas disponibles.'.PHP_EOL.PHP_EOL
            .$options.PHP_EOL.PHP_EOL
            .'Responde con el número de la rifa que deseas comprar o escribe MENU.';
    }

    public function renderQuantityPrompt(Raffle $raffle): WhatsAppReply|string
    {
        return '¿Cuántos números deseas comprar?'.PHP_EOL.PHP_EOL
            ."Compra mínima para esta rifa: {$raffle->min_numbers_per_purchase}";
    }

    public function renderChooseMode(): WhatsAppReply|string
    {
        $body = '¿Cómo deseas elegir tus números?'.PHP_EOL
            .'1. Elegir manualmente'.PHP_EOL
            .'2. Asignación aleatoria';

        return WhatsAppReply::make($body, [
            ['id' => 'mode_manual', 'title' => 'Elegir yo'],
            ['id' => 'mode_random', 'title' => 'Aleatorio'],
        ]);
    }

    public function renderNumberSelectionPrompt(Raffle $raffle, int $quantity, ?Customer $customer = null): WhatsAppReply|string
    {
        $query = [
            'raffle' => $raffle->slug,
            'quantity' => $quantity,
            'source' => 'whatsapp_manual_prompt',
        ];
        if ($customer instanceof Customer && ($customer->phone !== null || $customer->wa_id !== null)) {
            try {
                $query['pt'] = PickerAuthToken::generate($customer);
            } catch (\Throwable) {
                unset($query['pt']);
            }
        }
        $pickerUrl = route('raffles.number-picker', $query);

        return 'Escoge aquí tus números:'.PHP_EOL.$pickerUrl;
    }

    public function renderNumberExamples(int $quantity, int $digits): WhatsAppReply|string
    {
        $count = max(2, min($quantity, 3));

        return collect(range(1, $count))
            ->map(fn (int $value): string => str_pad((string) $value, $digits, '0', STR_PAD_LEFT))
            ->implode(',');
    }

    public function renderReservationConfirmation(Purchase $purchase): WhatsAppReply|string
    {
        $reservedNumbers = $purchase->numbers->pluck('number')->implode(', ');
        $paymentInstructions = $this->renderPaymentInstructionsList($purchase);

        $message = 'Listo. Tu reserva fue creada correctamente.'.PHP_EOL.PHP_EOL
            .'Números reservados: '.$reservedNumbers.PHP_EOL
            .'Total a pagar: '.$this->formatMoneyWithoutDecimals($purchase->total_amount).PHP_EOL
            .$this->renderReservationWindowMessage($purchase);

        if ($paymentInstructions !== '') {
            $message .= PHP_EOL.PHP_EOL
                .'Opciones de pago disponibles:'.PHP_EOL.PHP_EOL
                .$paymentInstructions;
        }

        $body = $message.PHP_EOL.PHP_EOL
            .'Después de pagar, envía una foto clara del comprobante por este chat para continuar.';

        return WhatsAppReply::make($body, []);
    }

    public function renderReservationWindowMessage(Purchase $purchase): WhatsAppReply|string
    {
        $purchase->loadMissing('raffle');

        $minutes = $purchase->raffle?->reservation_timeout_minutes;

        if (! is_int($minutes) || $minutes < 1) {
            $minutes = $purchase->reserved_until?->diffInMinutes(now());
        }

        if (! is_int($minutes) || $minutes < 1) {
            return 'Tu reserva quedará activa por tiempo limitado.';
        }

        $minuteLabel = $minutes === 1 ? 'minuto' : 'minutos';

        return "Tienes {$minutes} {$minuteLabel} para enviar tu comprobante de pago antes de que los números sean liberados nuevamente.";
    }

    public function renderPaymentWaitingReminder(?Purchase $purchase): WhatsAppReply|string
    {
        $body = (! $purchase instanceof Purchase)
            ? 'Envía tu comprobante de pago por imagen en este chat para continuar.'
            : (function () use ($purchase): string {
                $paymentInstructions = $this->renderPaymentInstructionsList($purchase);
                if ($paymentInstructions === '') {
                    return 'Envía tu comprobante de pago por imagen en este chat para continuar.';
                }

                return 'Aún estamos esperando tu comprobante de pago.'.PHP_EOL.PHP_EOL
                    .'Te recuerdo las opciones de pago disponibles para esta compra:'.PHP_EOL.PHP_EOL
                    .$paymentInstructions.PHP_EOL.PHP_EOL
                    .'Cuando completes el pago, envía una foto clara del comprobante por este chat.';
            })();

        return WhatsAppReply::make($body, [
            ['id' => 'payment_help', 'title' => 'Ayuda'],
            ['id' => 'payment_menu', 'title' => 'Menú'],
        ]);
    }

    public function renderPaymentInstructionsList(Purchase $purchase): WhatsAppReply|string
    {
        $snapshot = collect($purchase->payment_instructions_snapshot ?? [])
            ->map(function (mixed $method, int $index): ?string {
                if (! is_array($method)) {
                    return null;
                }

                return $this->renderPaymentInstructionEntry($method, $index + 1);
            })
            ->filter()
            ->values();

        if ($snapshot->isNotEmpty()) {
            return $snapshot->implode(PHP_EOL.PHP_EOL);
        }

        return PaymentMethod::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->map(fn (PaymentMethod $paymentMethod, int $index): string => $this->renderPaymentInstructionEntry([
                'name' => $paymentMethod->name,
                'instructions' => $paymentMethod->instructions,
                'account_holder' => $paymentMethod->account_holder,
                'account_reference' => $paymentMethod->account_reference,
                'details' => $paymentMethod->details_json,
            ], $index + 1))
            ->filter()
            ->implode(PHP_EOL.PHP_EOL);
    }

    /**
     * @param  array{name?: mixed, instructions?: mixed, account_holder?: mixed, account_reference?: mixed, details?: mixed}  $method
     */
    public function renderPaymentInstructionEntry(array $method, ?int $index = null): WhatsAppReply|string
    {
        $lines = [];
        $name = trim((string) ($method['name'] ?? ''));
        $accountHolder = trim((string) ($method['account_holder'] ?? ''));
        $accountReference = trim((string) ($method['account_reference'] ?? ''));
        $instructions = trim((string) ($method['instructions'] ?? ''));
        $details = is_array($method['details'] ?? null) ? $method['details'] : [];

        if ($index !== null) {
            $lines[] = $name !== ''
                ? "{$index}. {$name}"
                : "{$index}. Método de pago";
        } elseif ($name !== '') {
            $lines[] = $name;
        }

        if ($accountHolder !== '') {
            $lines[] = "Titular: {$accountHolder}";
        }

        if ($accountReference !== '') {
            $lines[] = "Número de cuenta: {$accountReference}";
        }

        foreach ($details as $key => $value) {
            $normalizedValue = trim((string) $value);

            if ($normalizedValue === '') {
                continue;
            }

            $label = Str::of((string) $key)
                ->replace('_', ' ')
                ->squish()
                ->title()
                ->toString();

            $lines[] = "{$label}: {$normalizedValue}";
        }

        if ($instructions !== '') {
            $lines[] = "Cómo pagar: {$instructions}";
        }

        return implode(PHP_EOL, $lines);
    }

    public function renderAvailableNumbers(): WhatsAppReply|string
    {
        $raffles = $this->botRaffles->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return 'Ahora mismo no tenemos una rifa activa disponible.';
        }

        if ($raffles->count() === 1) {
            $raffle = $raffles->first();
            $availableCount = $raffle?->numbers()->where('status', 'available')->count();

            return "La rifa {$raffle->title} tiene {$availableCount} número(s) disponibles.".PHP_EOL.PHP_EOL.'Si deseas comprar, responde 1.';
        }

        $summary = $raffles
            ->map(function (Raffle $raffle): string {
                $availableCount = $raffle->numbers()->where('status', 'available')->count();

                return "- {$raffle->title}: {$availableCount} número(s) disponibles";
            })
            ->implode(PHP_EOL);

        return 'Estas son las rifas activas y su disponibilidad actual:'.PHP_EOL
            .$summary.PHP_EOL.PHP_EOL
            .'Si deseas comprar, responde 1.';
    }

    public function renderMyNumbers(Customer $customer): WhatsAppReply|string
    {
        $summary = $customer->purchases()
            ->with('numbers')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Purchase $purchase): string {
                $numbers = $purchase->numbers->pluck('number')->implode(', ');

                return "- Compra {$purchase->id}: {$purchase->status} | Números: {$numbers}";
            })
            ->implode(PHP_EOL);

        return $summary !== ''
            ? 'Estas son tus compras registradas:'.PHP_EOL.$summary
            : 'Aún no tienes compras registradas.';
    }

    public function renderStatistics(): WhatsAppReply|string
    {
        $raffles = $this->botRaffles->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return 'Ahora mismo no tenemos una rifa activa disponible.';
        }

        if ($raffles->count() === 1) {
            $raffle = $raffles->first();
            $availableCount = $raffle?->numbers()->where('status', 'available')->count();
            $soldCount = $raffle?->numbers()->where('status', 'paid')->count();

            return "Estado actual de {$raffle->title}:".PHP_EOL
                ."- Vendidos: {$soldCount}".PHP_EOL
                ."- Disponibles: {$availableCount}".PHP_EOL
                .'- '.$this->formatLotteryReference($raffle).PHP_EOL
                .'- Fecha: '.$this->formatRaffleDrawDate($raffle);
        }

        $summary = $raffles
            ->map(function (Raffle $raffle): string {
                $availableCount = $raffle->numbers()->where('status', 'available')->count();
                $soldCount = $raffle->numbers()->where('status', 'paid')->count();

                return $raffle->title.PHP_EOL
                    ."- Vendidos: {$soldCount}".PHP_EOL
                    ."- Disponibles: {$availableCount}".PHP_EOL
                    .'- '.$this->formatLotteryReference($raffle).PHP_EOL
                    .'- Fecha: '.$this->formatRaffleDrawDate($raffle);
            })
            ->implode(PHP_EOL.PHP_EOL);

        return 'Estas son las estadísticas de las rifas activas:'.PHP_EOL.PHP_EOL.$summary;
    }

    public function renderConditions(): WhatsAppReply|string
    {
        return $this->resolvePublishedContentAction->byIntent(
            'terms_conditions',
            [],
            'Estas son las condiciones principales de la rifa:'.PHP_EOL
            .'- La compra se realiza por este chat.'.PHP_EOL
            .'- Los números se reservan por tiempo limitado.'.PHP_EOL
            .'- El pago se confirma manualmente.'.PHP_EOL
            .'- El boleto se envía cuando el pago es aprobado.'.PHP_EOL.PHP_EOL
            .'Si deseas comprar, responde 1. Si deseas volver al menú, escribe MENU.',
        );
    }

    public function renderHelp(): WhatsAppReply|string
    {
        return $this->resolvePublishedContentAction->byIntent(
            'help_support',
            [],
            'Puedo ayudarte con:'.PHP_EOL
            .'1. Condiciones de la rifa'.PHP_EOL
            .'2. Métodos de pago'.PHP_EOL
            .'3. Estado de tu compra'.PHP_EOL
            .'4. Hablar con una persona: escribe ASESOR',
        );
    }

    public function renderUpcomingRaffles(): WhatsAppReply|string
    {
        $base = $this->resolvePublishedContentAction->byIntent(
            'upcoming_raffles',
            [],
            'Pronto compartiremos las próximas rifas disponibles. Escribe MENU para volver.',
        );

        $raffles = $this->botRaffles->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return $base;
        }

        $summary = $raffles
            ->map(fn (Raffle $raffle): string => "- {$raffle->title}: ".$this->formatRaffleDrawDate($raffle).' | '.$this->formatLotteryReference($raffle))
            ->implode(PHP_EOL);

        return $base.PHP_EOL.PHP_EOL.'Rifas activas actuales:'.PHP_EOL.$summary;
    }

    public function formatMoneyWithoutDecimals(mixed $amount): string
    {
        if (! is_numeric($amount)) {
            return '$0';
        }

        return '$'.number_format((float) $amount, 0, ',', '.');
    }

    public function formatRaffleDrawDate(Raffle $raffle): string
    {
        $drawAt = $raffle->drawAt();

        if ($drawAt === null) {
            return 'Fecha pendiente por confirmar';
        }

        $drawAt = $drawAt->copy()->locale('es');
        $month = Str::ucfirst($drawAt->translatedFormat('F'));

        return $drawAt->translatedFormat('j').' de '.$month.' de '.$drawAt->translatedFormat('Y')
            .' a las '.$drawAt->format('g:i A');
    }

    public function formatLotteryReference(Raffle $raffle): string
    {
        $lotteryName = trim((string) ($raffle->lottery_name ?? ''));
        $lotteryText = trim((string) ($raffle->lottery_text ?? ''));
        $drawNumber = trim((string) ($raffle->lottery_draw_number ?? ''));

        if ($lotteryText !== '') {
            $segments = [];
            $headline = trim($lotteryText.' '.$lotteryName);

            if ($headline !== '') {
                $segments[] = $headline;
            }

            if ($drawNumber !== '') {
                $segments[] = 'sorteo #'.$drawNumber;
            }

            return implode(' ', $segments);
        }

        if ($lotteryName === '') {
            return $drawNumber !== ''
                ? 'Sorteo: #'.$drawNumber
                : 'Sorteo: Referencia pendiente';
        }

        return $drawNumber !== ''
            ? "Sorteo: {$lotteryName} #{$drawNumber}"
            : "Sorteo: {$lotteryName}";
    }

    public function renderPaymentMethods(): WhatsAppReply|string
    {
        $base = $this->resolvePublishedContentAction->byIntent(
            'payment_methods',
            [],
            'Puedes pagar usando los métodos habilitados por la empresa. Te enviaremos las instrucciones exactas dentro del flujo de compra.',
        );

        $methods = PaymentMethod::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentMethod $paymentMethod): string => "- {$paymentMethod->name}: {$paymentMethod->account_reference}")
            ->implode(PHP_EOL);

        return $methods !== ''
            ? $base.PHP_EOL.PHP_EOL.$methods
            : $base;
    }

    public function renderDrawDate(ConversationState $state): WhatsAppReply|string
    {
        $raffle = $state->currentRaffle;

        if ($raffle !== null) {
            return $this->resolvePublishedContentAction->byIntent(
                'draw_date',
                [
                    'raffle_title' => $raffle->title,
                    'draw_date' => $raffle->draw_date?->format('Y-m-d'),
                    'draw_time' => $raffle->draw_time,
                    'lottery_name' => $raffle->lottery_name,
                    'lottery_draw_number' => $raffle->lottery_draw_number,
                ],
                "El sorteo de {$raffle->title} se realiza el {$raffle->draw_date?->format('Y-m-d')} a las {$raffle->draw_time}.",
            );
        }

        $raffles = $this->botRaffles->getActiveRaffles();

        if ($raffles->isEmpty()) {
            return 'Ahora mismo no tenemos una rifa activa disponible.';
        }

        if ($raffles->count() === 1) {
            $singleRaffle = $raffles->first();

            return $this->resolvePublishedContentAction->byIntent(
                'draw_date',
                [
                    'raffle_title' => $singleRaffle->title,
                    'draw_date' => $singleRaffle->draw_date?->format('Y-m-d'),
                    'draw_time' => $singleRaffle->draw_time,
                    'lottery_name' => $singleRaffle->lottery_name,
                    'lottery_draw_number' => $singleRaffle->lottery_draw_number,
                ],
                "El sorteo de {$singleRaffle->title} se realiza el {$singleRaffle->draw_date?->format('Y-m-d')} a las {$singleRaffle->draw_time}.",
            );
        }

        $summary = $raffles
            ->map(fn (Raffle $activeRaffle): string => "- {$activeRaffle->title}: {$activeRaffle->draw_date?->format('Y-m-d')} {$activeRaffle->draw_time} | {$activeRaffle->lottery_name} #{$activeRaffle->lottery_draw_number}")
            ->implode(PHP_EOL);

        return 'Estas son las fechas de sorteo de las rifas activas:'.PHP_EOL.$summary;
    }
}
