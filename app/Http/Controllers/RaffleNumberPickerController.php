<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\RafflePickerIntent;
use App\Support\PickerAuthToken;
use App\Support\PickerPurchaseOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RaffleNumberPickerController extends Controller
{
    protected const NUMBER_PICKER_PAGE_SIZE = 240;

    public function show(Request $request, Raffle $raffle): View
    {
        abort_unless($raffle->salesAreOpen(), 404);

        $trace = $this->resolvePickerTrace($request);
        $quantity = max($raffle->min_numbers_per_purchase, (int) $request->integer('quantity', $raffle->min_numbers_per_purchase));
        $company = CompanySetting::query()->first();
        $botPhone = $company?->whatsapp_bot_phone;
        $catalogCount = $raffle->numbers()->count();
        $availableCount = $raffle->numbers()
            ->where('status', 'available')
            ->count();
        $initialChunk = $this->fetchNumberChunk($raffle);

        $pickerTokenRaw = (string) $request->query('pt', '');
        $pickerCustomer = null;
        $pickerAuthError = null;
        if ($pickerTokenRaw !== '') {
            $pickerCustomer = PickerAuthToken::verify($pickerTokenRaw);
            if (! $pickerCustomer instanceof Customer) {
                $pickerAuthError = 'Enlace de confirmación inválido o expirado. Para confirmar tu selección sin escribir al bot, usa el enlace fresco que te enviamos por WhatsApp. Si no lo tienes, puedes continuar abriendo WhatsApp manualmente.';
            }
        }

        $confirmUrl = $pickerCustomer instanceof Customer
            ? route('raffles.number-picker.confirm', array_filter([
                'raffle' => $raffle->slug,
                'pt' => $pickerTokenRaw,
                'source' => $trace['source'],
                'utm_source' => $trace['utm_source'],
                'utm_medium' => $trace['utm_medium'],
                'utm_campaign' => $trace['utm_campaign'],
                'utm_content' => $trace['utm_content'],
                'utm_term' => $trace['utm_term'],
            ], fn (mixed $value): bool => $value !== null && $value !== ''))
            : null;

        return view('raffles.number-picker', [
            'raffle' => $raffle,
            'company' => $company,
            'numbers' => $initialChunk['items'],
            'catalogCount' => $catalogCount,
            'availableCount' => $availableCount,
            'quantity' => $quantity,
            'digits' => $raffle->normalizedNumberDigits(),
            'botPhone' => $botPhone,
            'botPhoneDigits' => $this->normalizePhoneDigits($botPhone),
            'numbersFeedUrl' => route('raffles.number-picker.numbers', [
                'raffle' => $raffle->slug,
            ]),
            'numbersNextCursor' => $initialChunk['next_cursor'],
            'pickerIntentUrl' => route('raffles.number-picker.intents', array_filter([
                'raffle' => $raffle->slug,
                'source' => $trace['source'],
                'utm_source' => $trace['utm_source'],
                'utm_medium' => $trace['utm_medium'],
                'utm_campaign' => $trace['utm_campaign'],
                'utm_content' => $trace['utm_content'],
                'utm_term' => $trace['utm_term'],
            ], fn (mixed $value): bool => $value !== null && $value !== '')),
            'pickerConfirmUrl' => $confirmUrl,
            'pickerAuthCustomer' => $pickerCustomer,
            'pickerAuthError' => $pickerAuthError,
            'pickerTrace' => $trace,
        ]);
    }

    public function numbers(Request $request, Raffle $raffle): JsonResponse
    {
        abort_unless($raffle->salesAreOpen(), 404);

        $validator = Validator::make($request->query(), [
            'cursor' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:32'],
            'per_page' => ['nullable', 'integer', 'min:24', 'max:400'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los parametros de carga de numeros no son validos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $chunk = $this->fetchNumberChunk(
            $raffle,
            cursor: Arr::get($validated, 'cursor'),
            search: Arr::get($validated, 'search'),
            perPage: (int) ($validated['per_page'] ?? self::NUMBER_PICKER_PAGE_SIZE),
        );

        return response()->json($chunk);
    }

    public function store(Request $request, Raffle $raffle): JsonResponse
    {
        if (! $raffle->salesAreOpen()) {
            return response()->json([
                'message' => 'Esta rifa ya cerro ventas porque alcanzo la hora programada del sorteo.',
            ], 422);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'selected_numbers' => ['required', 'array', 'min:1'],
            'selected_numbers.*' => ['required', 'string'],
            'trace' => ['nullable', 'array'],
            'trace.source' => ['nullable', 'string', 'max:100'],
            'trace.referrer_url' => ['nullable', 'string', 'max:2048'],
            'trace.picker_page_url' => ['nullable', 'string', 'max:2048'],
            'trace.utm_source' => ['nullable', 'string', 'max:255'],
            'trace.utm_medium' => ['nullable', 'string', 'max:255'],
            'trace.utm_campaign' => ['nullable', 'string', 'max:255'],
            'trace.utm_content' => ['nullable', 'string', 'max:255'],
            'trace.utm_term' => ['nullable', 'string', 'max:255'],
        ]);

        $quantity = max($raffle->min_numbers_per_purchase, (int) $validated['quantity']);
        $digits = $raffle->normalizedNumberDigits();
        $selectedNumbers = collect($validated['selected_numbers'])
            ->map(fn (string $number): string => trim($number))
            ->filter()
            ->values();

        if ($selectedNumbers->count() !== $quantity) {
            return response()->json([
                'message' => "Debes seleccionar exactamente {$quantity} número(s) para continuar.",
            ], 422);
        }

        $invalidNumbers = $selectedNumbers
            ->filter(fn (string $number): bool => ! ctype_digit($number) || strlen($number) !== $digits)
            ->values()
            ->all();

        if ($invalidNumbers !== []) {
            return response()->json([
                'message' => "La seleccion contiene numeros invalidos. Cada numero debe tener exactamente {$digits} cifra(s).",
            ], 422);
        }

        if ($selectedNumbers->unique()->count() !== $selectedNumbers->count()) {
            return response()->json([
                'message' => 'No puedes repetir numeros en la misma seleccion.',
            ], 422);
        }

        $raffleNumbers = $raffle->numbers()
            ->whereIn('number', $selectedNumbers->all())
            ->get(['number', 'status'])
            ->keyBy('number');

        if ($raffleNumbers->count() !== $selectedNumbers->count()) {
            return response()->json([
                'message' => 'Uno o mas numeros ya no existen en el catalogo de esta rifa.',
            ], 422);
        }

        $unavailableNumbers = $raffleNumbers
            ->filter(fn ($raffleNumber): bool => $raffleNumber->status !== 'available')
            ->keys()
            ->values()
            ->all();

        if ($unavailableNumbers !== []) {
            return response()->json([
                'message' => 'Uno o mas numeros ya no estan disponibles. Actualiza la pagina y elige nuevamente.',
            ], 422);
        }

        $trace = $this->resolvePickerTrace($request, Arr::get($validated, 'trace', []));

        $intent = RafflePickerIntent::query()->create([
            'raffle_id' => $raffle->id,
            'token' => $this->generateUniqueIntentToken(),
            'quantity' => $quantity,
            'source' => $trace['source'],
            'selected_numbers_json' => $selectedNumbers->all(),
            'metadata_json' => $this->formatPickerIntentMetadata($trace),
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'token' => $intent->token,
            'whatsapp_message' => $this->buildPickerWhatsappMessage($raffle, $intent),
            'expires_at' => $intent->expires_at?->toIso8601String(),
        ]);
    }

    public function confirm(Request $request, Raffle $raffle): JsonResponse
    {
        $throttleKey = 'picker-confirm|ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            return response()->json([
                'message' => 'Estamos procesando muchas solicitudes. Intenta nuevamente en 60 segundos.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        if (! $raffle->salesAreOpen()) {
            return response()->json([
                'message' => 'Esta rifa ya cerro ventas porque alcanzo la hora programada del sorteo.',
            ], 422);
        }

        $pickerTokenRaw = (string) $request->query('pt', '');
        if ($pickerTokenRaw === '') {
            $pickerTokenRaw = (string) $request->input('pt', '');
        }

        $customer = $pickerTokenRaw !== '' ? PickerAuthToken::verify($pickerTokenRaw) : null;
        if (! $customer instanceof Customer) {
            return response()->json([
                'message' => 'El enlace de confirmación es inválido o ya expiró. Por favor vuelve al chat de WhatsApp y usa el enlace más reciente.',
                'fallback_legacy' => true,
            ], 422);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'selected_numbers' => ['required', 'array', 'min:1'],
            'selected_numbers.*' => ['required', 'string'],
            'trace' => ['nullable', 'array'],
            'trace.source' => ['nullable', 'string', 'max:100'],
            'trace.referrer_url' => ['nullable', 'string', 'max:2048'],
            'trace.picker_page_url' => ['nullable', 'string', 'max:2048'],
            'trace.utm_source' => ['nullable', 'string', 'max:255'],
            'trace.utm_medium' => ['nullable', 'string', 'max:255'],
            'trace.utm_campaign' => ['nullable', 'string', 'max:255'],
            'trace.utm_content' => ['nullable', 'string', 'max:255'],
            'trace.utm_term' => ['nullable', 'string', 'max:255'],
            'intent_token' => ['nullable', 'string', 'max:32'],
        ]);

        $quantity = max($raffle->min_numbers_per_purchase, (int) $validated['quantity']);
        $digits = $raffle->normalizedNumberDigits();
        $selectedNumbers = collect($validated['selected_numbers'])
            ->map(fn (string $n): string => trim($n))
            ->filter()
            ->values();

        if ($selectedNumbers->count() !== $quantity) {
            return response()->json([
                'message' => "Debes seleccionar exactamente {$quantity} número(s) para continuar.",
            ], 422);
        }

        $invalidNumbers = $selectedNumbers
            ->filter(fn (string $n): bool => ! ctype_digit($n) || strlen($n) !== $digits)
            ->values()
            ->all();
        if ($invalidNumbers !== []) {
            return response()->json([
                'message' => "La seleccion contiene numeros invalidos. Cada numero debe tener exactamente {$digits} cifra(s).",
            ], 422);
        }
        if ($selectedNumbers->unique()->count() !== $selectedNumbers->count()) {
            return response()->json([
                'message' => 'No puedes repetir numeros en la misma seleccion.',
            ], 422);
        }

        $raffleNumbers = $raffle->numbers()
            ->whereIn('number', $selectedNumbers->all())
            ->get(['number', 'status'])
            ->keyBy('number');
        if ($raffleNumbers->count() !== $selectedNumbers->count()) {
            return response()->json([
                'message' => 'Uno o mas numeros ya no existen en el catalogo de esta rifa.',
            ], 422);
        }

        $unavailableNumbers = $raffleNumbers
            ->filter(fn ($r): bool => $r->status !== 'available')
            ->keys()
            ->values()
            ->all();
        if ($unavailableNumbers !== []) {
            return response()->json([
                'message' => 'Uno o mas numeros ya no estan disponibles. Actualiza la pagina y elige nuevamente.',
            ], 422);
        }

        $trace = $this->resolvePickerTrace($request, Arr::get($validated, 'trace', []));

        try {
            DB::beginTransaction();

            $intent = RafflePickerIntent::query()->create([
                'raffle_id' => $raffle->id,
                'token' => isset($validated['intent_token']) && is_string($validated['intent_token']) && $validated['intent_token'] !== ''
                    ? $validated['intent_token']
                    : $this->generateUniqueIntentToken(),
                'quantity' => $quantity,
                'source' => $trace['source'],
                'selected_numbers_json' => $selectedNumbers->all(),
                'metadata_json' => $this->formatPickerIntentMetadata($trace),
                'expires_at' => now()->addMinutes(10),
            ]);

            $result = PickerPurchaseOrchestrator::confirmFromIntent(
                customer: $customer,
                raffle: $raffle,
                intent: $intent,
                numbers: $selectedNumbers->all(),
            );

            DB::commit();
        } catch (InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
                'fallback_legacy' => true,
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'message' => 'No pudimos confirmar tu reserva en este momento. Intenta nuevamente, o usa el botón para abrir WhatsApp y confirmar manualmente.',
                'fallback_legacy' => true,
                'debug' => app()->hasDebugModeEnabled() ? $e->getMessage() : null,
            ], 500);
        }

        /** @var Purchase|null $purchase */
        $purchase = $result['purchase'] ?? null;
        $requiresOnboarding = (bool) ($result['requires_onboarding'] ?? false);
        $onboardingStatus = (string) ($result['onboarding_status'] ?? '');

        if ($requiresOnboarding) {
            $redirect = $this->buildConfirmedRedirectUrl($raffle, $selectedNumbers->all(), null, true);

            return response()->json([
                'ok' => true,
                'requires_onboarding' => true,
                'onboarding_status' => $onboardingStatus,
                'outbound_sent' => (bool) ($result['outbound_sent'] ?? false),
                'outbound_status' => (string) ($result['outbound_status'] ?? ''),
                'message' => 'Para finalizar tu reserva necesitamos que completes unos datos rápidos por WhatsApp. Revisa tu chat, ya te enviamos las instrucciones.',
                ...$redirect,
            ], 200);
        }

        if (! $purchase instanceof Purchase || ! $purchase->exists) {
            return response()->json([
                'message' => 'No pudimos confirmar tu reserva en este momento. Intenta nuevamente, o usa el botón para abrir WhatsApp y confirmar manualmente.',
                'fallback_legacy' => true,
            ], 500);
        }

        $outboundSent = (bool) ($result['outbound_sent'] ?? false);
        $outboundError = isset($result['outbound_error']) && is_string($result['outbound_error']) ? $result['outbound_error'] : null;

        $redirect = $this->buildConfirmedRedirectUrl($raffle, $selectedNumbers->all(), $purchase, false);

        $body = [
            'ok' => true,
            'purchase_id' => $purchase->id,
            'reservation_id' => $purchase->reservation_id,
            'reserved_numbers' => $selectedNumbers->all(),
            'total_amount' => $purchase->total_amount,
            'reserved_until' => $purchase->reserved_until?->toIso8601String(),
            'outbound_sent' => $outboundSent,
            'outbound_status' => (string) ($result['outbound_status'] ?? ''),
            'fallback_legacy' => ! $outboundSent,
            'message' => $outboundSent
                ? '¡Reserva confirmada! Revisa tu WhatsApp, ya te enviamos la confirmación y los datos de pago.'
                : 'Tu reserva fue creada correctamente, pero no pudimos enviarte el mensaje por WhatsApp en este momento. Por favor usa el botón para continuar el proceso por WhatsApp.',
            ...$redirect,
        ];
        if ($outboundError !== null && $outboundError !== '') {
            $body['outbound_error'] = $outboundError;
        }

        return response()->json($body, 200);
    }

    public function confirmed(Request $request, Raffle $raffle): View
    {
        if (! URL::hasValidSignature($request)) {
            abort(403, 'Enlace de confirmación inválido. Vuelve a intentar la selección desde el bot de WhatsApp.');
        }

        $validated = $request->validate([
            'numbers' => ['required', 'array', 'min:1'],
            'numbers.*' => ['required', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'numeric', 'min:0'],
            'until' => ['nullable', 'string', 'max:80'],
            'requires_onboarding' => ['nullable', 'boolean'],
            'ref' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $numbers = array_values(array_filter(array_map('strval', $validated['numbers'])));
        $company = CompanySetting::query()->first();
        $botPhoneDigits = $this->normalizePhoneDigits($company?->whatsapp_bot_phone);

        $whatsappOpenUrl = $botPhoneDigits !== null
            ? 'https://wa.me/'.$botPhoneDigits
            : '';

        return view('raffles.number-picker-confirmed', [
            'raffle' => $raffle,
            'company' => $company,
            'reservedNumbers' => $numbers,
            'totalAmount' => isset($validated['amount']) ? (float) $validated['amount'] : null,
            'unitPrice' => isset($validated['unit']) ? (float) $validated['unit'] : null,
            'reservedUntilText' => isset($validated['until']) && is_string($validated['until']) ? (string) $validated['until'] : null,
            'requiresOnboarding' => (bool) ($validated['requires_onboarding'] ?? false),
            'referenceLabel' => isset($validated['ref']) && is_string($validated['ref']) ? (string) $validated['ref'] : null,
            'whatsappOpenUrl' => $whatsappOpenUrl,
        ]);
    }

    /**
     * @param  list<string>  $numbers
     * @return array{redirect_url:string}
     */
    protected function buildConfirmedRedirectUrl(Raffle $raffle, array $numbers, ?Purchase $purchase, bool $requiresOnboarding): array
    {
        $params = [
            'numbers' => array_values(array_map('strval', $numbers)),
            'requires_onboarding' => $requiresOnboarding ? '1' : '0',
        ];

        if ($purchase instanceof Purchase && $purchase->exists) {
            $params['amount'] = (string) $purchase->total_amount;
            $params['unit'] = (string) $purchase->unit_price;
            if ($purchase->reserved_until !== null) {
                try {
                    $params['until'] = $purchase->reserved_until
                        ->timezone(config('app.timezone', 'America/Bogota'))
                        ->isoFormat('D MMM YYYY, h:mm A');
                } catch (\Throwable) {
                    $params['until'] = $purchase->reserved_until->format('Y-m-d H:i');
                }
            }
            $params['ref'] = 'PUR-'.$purchase->id;
        }

        $customerPhone = $purchase?->customer?->phone;
        if (is_string($customerPhone) && $customerPhone !== '') {
            $digits = preg_replace('/\D+/', '', $customerPhone) ?: '';
            if ($digits !== '') {
                $params['phone'] = $digits;
            }
        }

        return [
            'redirect_url' => URL::temporarySignedRoute(
                'raffles.number-picker.confirmed',
                now()->addMinutes(60),
                array_merge(['raffle' => $raffle->slug], $params)
            ),
        ];
    }

    protected function normalizePhoneDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: null;

        return blank($digits) ? null : $digits;
    }

    protected function generateUniqueIntentToken(): string
    {
        do {
            $token = Str::upper(Str::random(10));
        } while (RafflePickerIntent::query()->where('token', $token)->exists());

        return $token;
    }

    protected function buildPickerWhatsappMessage(Raffle $raffle, RafflePickerIntent $intent): string
    {
        return "Ya seleccioné mis números y quiero continuar, el código de mi selección es: PICKER {$intent->token}".PHP_EOL
            ."Rifa: {$raffle->title}".PHP_EOL
            .'Por favor, no modifiques este mensaje para continuar con la compra.';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{source: string, referrer_url: string|null, picker_page_url: string|null, utm_source: string|null, utm_medium: string|null, utm_campaign: string|null, utm_content: string|null, utm_term: string|null}
     */
    protected function resolvePickerTrace(Request $request, array $input = []): array
    {
        return [
            'source' => $this->normalizePickerSource((string) ($input['source'] ?? $request->query('source', 'picker_direct'))),
            'referrer_url' => $this->sanitizeNullableString($input['referrer_url'] ?? $request->headers->get('referer')),
            'picker_page_url' => $this->sanitizeNullableString($input['picker_page_url'] ?? $request->fullUrl()),
            'utm_source' => $this->sanitizeNullableString($input['utm_source'] ?? $request->query('utm_source')),
            'utm_medium' => $this->sanitizeNullableString($input['utm_medium'] ?? $request->query('utm_medium')),
            'utm_campaign' => $this->sanitizeNullableString($input['utm_campaign'] ?? $request->query('utm_campaign')),
            'utm_content' => $this->sanitizeNullableString($input['utm_content'] ?? $request->query('utm_content')),
            'utm_term' => $this->sanitizeNullableString($input['utm_term'] ?? $request->query('utm_term')),
        ];
    }

    /**
     * @param  array{source: string, referrer_url: string|null, picker_page_url: string|null, utm_source: string|null, utm_medium: string|null, utm_campaign: string|null, utm_content: string|null, utm_term: string|null}  $trace
     * @return array<string, mixed>|null
     */
    protected function formatPickerIntentMetadata(array $trace): ?array
    {
        $metadata = collect($trace)
            ->except('source')
            ->filter(function (mixed $value): bool {
                if (is_array($value)) {
                    return $value !== [];
                }

                return $value !== null && $value !== '';
            })
            ->all();

        return $metadata !== [] ? $metadata : null;
    }

    protected function normalizePickerSource(string $source): string
    {
        $normalized = Str::of($source)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        return $normalized !== '' ? $normalized : 'picker_direct';
    }

    protected function sanitizeNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array{items: array<int, array{number: string, status: string, status_label: string, selectable: bool}>, next_cursor: string|null}
     */
    protected function fetchNumberChunk(
        Raffle $raffle,
        ?string $cursor = null,
        ?string $search = null,
        int $perPage = self::NUMBER_PICKER_PAGE_SIZE,
    ): array {
        $query = $raffle->numbers()
            ->orderBy('number');

        if (filled($search)) {
            $query->where('number', 'like', '%'.trim((string) $search).'%');
        }

        if (filled($cursor)) {
            $query->where('number', '>', trim((string) $cursor));
        }

        $items = $query
            ->limit($perPage + 1)
            ->get(['number', 'status']);

        $hasMore = $items->count() > $perPage;
        $pageItems = $items->take($perPage)->values();
        $nextCursor = $hasMore ? (string) $pageItems->last()->number : null;

        return [
            'items' => $pageItems
                ->map(fn ($number): array => [
                    'number' => $number->number,
                    'status' => $number->status,
                    'status_label' => match ($number->status) {
                        'reserved' => 'Reservado',
                        'paid' => 'Pagado',
                        'winner' => 'Ganador',
                        default => 'Disponible',
                    },
                    'selectable' => $number->status === 'available',
                ])
                ->all(),
            'next_cursor' => $nextCursor,
        ];
    }
}
