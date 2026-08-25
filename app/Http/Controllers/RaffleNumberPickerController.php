<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Raffle;
use App\Models\RafflePickerIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RaffleNumberPickerController extends Controller
{
    protected const NUMBER_PICKER_PAGE_SIZE = 240;

    public function show(Raffle $raffle): View
    {
        abort_unless($raffle->salesAreOpen(), 404);

        $trace = $this->resolvePickerTrace(request());
        $quantity = max($raffle->min_numbers_per_purchase, (int) request()->integer('quantity', $raffle->min_numbers_per_purchase));
        $company = CompanySetting::query()->first();
        $botPhone = $company?->whatsapp_bot_phone;
        $catalogCount = $raffle->numbers()->count();
        $availableCount = $raffle->numbers()
            ->where('status', 'available')
            ->count();
        $initialChunk = $this->fetchNumberChunk($raffle);

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
                'message' => "Debes seleccionar exactamente {$quantity} numero(s) para continuar.",
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
        return "Hola, quiero continuar con mi seleccion visual de la rifa {$raffle->title}.".PHP_EOL
            ."Codigo de seleccion: PICKER {$intent->token}".PHP_EOL
            .'Por favor, mantener este mensaje sin modificar para continuar con la compra.';
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
