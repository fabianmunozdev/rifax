<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $brandName = $company?->trade_name ?: config('app.name', 'Rifax');
        $brandLogoUrl = filled($company?->logo_path) ? asset('storage/' . ltrim($company->logo_path, '/')) : null;
        $catalogRaffles = $otherRaffles->isNotEmpty() ? $otherRaffles : $raffles;
        $landingTracking = array_filter([
            'utm_source' => request()->query('utm_source'),
            'utm_medium' => request()->query('utm_medium'),
            'utm_campaign' => request()->query('utm_campaign'),
            'utm_content' => request()->query('utm_content'),
            'utm_term' => request()->query('utm_term'),
        ], fn ($value) => filled($value));
        $pickerUrlFor = fn ($raffle, string $source, array $extra = []) => route('raffles.number-picker', array_merge([
            'raffle' => $raffle->slug,
            'quantity' => max(1, (int) $raffle->min_numbers_per_purchase),
            'source' => $source,
        ], $landingTracking, $extra));
        $featuredPickerUrl = $featuredRaffle ? $pickerUrlFor($featuredRaffle, 'landing_featured') : '#raffles';
        $whatsappFor = fn (string $text) => $botPhoneDigits ? 'https://wa.me/' . $botPhoneDigits . '?text=' . rawurlencode($text) : null;
        $stickyWhatsappUrl = $whatsappFor($featuredRaffle ? 'Quiero comprar en ' . $featuredRaffle->title : 'MENU');
        $money = fn ($value) => '$' . number_format((float) $value, 0, ',', '.');
        $count = fn ($value) => number_format((int) $value, 0, ',', '.');
        $drawLabel = function ($raffle) use ($company) {
            if (! $raffle->draw_date) {
                return null;
            }
            $tz = $company?->timezone ?: config('app.timezone');
            $at = $raffle->draw_time
                ? \Illuminate\Support\Carbon::parse($raffle->draw_date->format('Y-m-d') . ' ' . $raffle->draw_time, $tz)
                : $raffle->draw_date->copy()->setTimezone($tz);
            $label = $at->locale('es')->translatedFormat('l j \d\e F');

            return $raffle->draw_time ? $label . ', ' . $at->format('g:i') . ' ' . ($at->format('A') === 'AM' ? 'a. m.' : 'p. m.') : $label;
        };
        $drawIso = function ($raffle) use ($company) {
            return ($raffle->draw_date && $raffle->draw_time)
                ? \Illuminate\Support\Carbon::parse($raffle->draw_date->format('Y-m-d') . ' ' . $raffle->draw_time, $company?->timezone ?: config('app.timezone'))->toIso8601String()
                : null;
        };
        $coverFor = fn ($raffle) => filled($raffle?->cover_image_path) ? asset('storage/' . ltrim($raffle->cover_image_path, '/')) : null;
        $lotteryFor = fn ($raffle) => trim(($raffle->lottery_name ?: '') . ($raffle->lottery_draw_number ? ', sorteo ' . $raffle->lottery_draw_number : ''));

        // El color principal de la marca es el acento; el texto encima se elige por contraste.
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $company?->primary_color) ? $company->primary_color : '#F9C80E';
        $hot = preg_match('/^#[0-9a-f]{6}$/i', (string) $company?->accent_color) ? $company->accent_color : '#E3141B';
        [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
        $accentInk = ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.55 ? '#181715' : '#f4f4f0';
        $timeout = (int) ($featuredRaffle?->reservation_timeout_minutes ?: 15);
    @endphp
    <title>{{ $brandName }} | Rifas activas</title>
    <meta name="description" content="{{ $featuredRaffle ? 'Rifa ' . $featuredRaffle->title . ': ' . $money($featuredRaffle->price_per_number) . ' por número. Elige tus números y paga por WhatsApp.' : 'Rifas activas de ' . $brandName . '. Elige tus números y paga por WhatsApp.' }}">
    <meta name="theme-color" content="{{ $accent }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,700;1,700;1,800&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rifax.css') }}?v={{ @filemtime(public_path('css/rifax.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mega-elite.css') }}?v={{ @filemtime(public_path('css/mega-elite.css')) }}">
    <style>:root { --brand: {{ $accent }}; --brand-ink: {{ $accentInk }}; --hot: {{ $hot }}; }</style>
    <script>document.documentElement.classList.add('js');</script>
</head>
<body @class(['me', 'has-sticky' => $raffles->isNotEmpty() || $stickyWhatsappUrl])>

{{-- Barra superior en el amarillo de la marca. --}}
<header class="me-top">
    <div class="wrap me-top-row">
        <a class="me-brand" href="{{ url('/') }}" aria-label="{{ $brandName }}, inicio">
            @if ($brandLogoUrl)
                <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" height="44">
            @else
                <span>{{ $brandName }}</span>
            @endif
        </a>
        <nav class="me-nav" aria-label="Secciones">
            @if ($featuredRaffle)
                <a href="#premios">Premios</a>
            @endif
            <a href="#como-funciona">Cómo participar</a>
            @if ($catalogRaffles->count() > 1 || ! $featuredRaffle)
                <a href="#raffles">Rifas</a>
            @endif
            @if ($publicFaqEntries->isNotEmpty())
                <a href="#preguntas">Preguntas</a>
            @endif
        </nav>
        @if ($featuredRaffle)
            <a class="me-btn me-btn-dark" href="{{ $featuredPickerUrl }}">Elegir números</a>
        @elseif ($whatsappFor('MENU'))
            <a class="me-btn me-btn-dark" href="{{ $whatsappFor('MENU') }}">Escribir por WhatsApp</a>
        @endif
    </div>
</header>

<main>
    {{-- 1. Portada: el premio, cuándo se juega y la acción. --}}
    <section class="me-hero">
        <div class="wrap me-hero-grid">
            @if ($featuredRaffle)
                @php
                    $featuredCover = $coverFor($featuredRaffle);
                    $featuredDraw = $drawLabel($featuredRaffle);
                    $featuredWhatsappUrl = $whatsappFor('Quiero comprar en ' . $featuredRaffle->title);
                    $featuredLottery = $lotteryFor($featuredRaffle);
                    $earlyCount = $featuredRaffle->earlyPrizes->count();
                @endphp
                <div class="me-hero-copy">
                    <p class="me-tag">Rifa destacada · {{ $brandName }}</p>
                    <h1 class="me-display">{{ $featuredRaffle->title }}</h1>
                    @if ($earlyCount > 0)
                        <p class="me-sub">+ {{ $earlyCount }} {{ $earlyCount === 1 ? 'premio anticipado' : 'premios anticipados' }}</p>
                    @endif
                    <p class="me-lead">
                        Son {{ $count($featuredRaffle->numbers_count) }} oportunidades.
                        @if ($featuredDraw)
                            Juega el {{ rtrim($featuredDraw, ".") }}.
                        @endif
                        @if ($featuredLottery !== '')
                            Se juega con la {{ $featuredLottery }}.
                        @endif
                    </p>
                    @if (filled($featuredRaffle->description))
                        <p class="me-desc">{{ $featuredRaffle->description }}</p>
                    @endif

                    <div class="me-hero-actions">
                        <a class="me-btn me-btn-brand me-btn-lg" href="{{ $featuredPickerUrl }}">Elegir números ahora</a>
                        <p class="me-price">Oportunidad a <strong class="num">{{ $money($featuredRaffle->price_per_number) }}</strong></p>
                    </div>
                    @if ($featuredWhatsappUrl)
                        <p class="me-alt"><a class="link" href="{{ $featuredWhatsappUrl }}">Comprar esta rifa por WhatsApp</a></p>
                    @endif

                    @if ($drawIso($featuredRaffle))
                        <p class="me-countdown num" data-countdown data-draw-at="{{ $drawIso($featuredRaffle) }}">
                            Faltan <span data-countdown-text>…</span> para el sorteo
                        </p>
                    @endif
                </div>

                <div class="me-hero-media">
                    <figure class="me-photo">
                        @if ($featuredCover)
                            <img src="{{ $featuredCover }}" alt="Premio: {{ $featuredRaffle->title }}" width="1200" height="900" fetchpriority="high">
                        @else
                            <span class="me-photo-number num" aria-hidden="true">{{ str_repeat('0', max(1, (int) $featuredRaffle->number_digits)) }}</span>
                        @endif
                        <figcaption class="me-flag">Premio mayor</figcaption>
                    </figure>

                    <form class="lookup me-lookup" method="get" action="{{ route('raffles.number-picker', $featuredRaffle->slug) }}"
                          data-lookup data-feed-url="{{ route('raffles.number-picker.numbers', $featuredRaffle->slug) }}"
                          data-picker-url="{{ $pickerUrlFor($featuredRaffle, 'landing_lookup') }}"
                          data-digits="{{ (int) $featuredRaffle->number_digits }}">
                        <input type="hidden" name="quantity" value="{{ max(1, (int) $featuredRaffle->min_numbers_per_purchase) }}">
                        <input type="hidden" name="source" value="landing_lookup">
                        @foreach ($landingTracking as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <label for="lookup-number">¿Ya tienes un número de la suerte?</label>
                        <div class="lookup-row">
                            <input id="lookup-number" name="pick" type="text" inputmode="numeric" pattern="[0-9]*"
                                   maxlength="{{ (int) $featuredRaffle->number_digits }}" autocomplete="off"
                                   placeholder="Escribe hasta {{ (int) $featuredRaffle->number_digits }} cifras" aria-describedby="lookup-help">
                            <button class="me-btn me-btn-brand" type="submit">Buscar</button>
                        </div>
                        <p class="lookup-help" id="lookup-help" aria-live="polite" data-lookup-status>Te decimos al instante si está libre.</p>
                        <div class="lookup-results" data-lookup-results></div>
                    </form>
                </div>
            @else
                <div class="me-hero-copy">
                    <p class="me-tag">{{ $brandName }}</p>
                    <h1 class="me-display">{{ $raffles->isNotEmpty() ? 'Rifas abiertas' : 'Pronto, nueva rifa' }}</h1>
                    <p class="me-lead">
                        {{ $raffles->isNotEmpty()
                            ? 'Rifas con números de la lotería oficial. Elige tus números y paga por WhatsApp.'
                            : ($company?->help_message ?: 'No hay rifas abiertas en este momento. Escríbenos y te avisamos cuando salga la próxima.') }}
                    </p>
                    <div class="me-hero-actions">
                        <a class="me-btn me-btn-brand me-btn-lg" href="#raffles">Ver rifas actuales</a>
                        @if ($whatsappFor('MENU'))
                            <a class="link" href="{{ $whatsappFor('MENU') }}">Comprar por WhatsApp</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if ($featuredRaffle)
        {{-- 2. Franja con los datos clave. --}}
        <section class="me-stats" aria-label="Datos de la rifa">
            <dl class="wrap me-stats-grid">
                <div>
                    <dt class="num">{{ $money($featuredRaffle->price_per_number) }}</dt>
                    <dd>Valor de la oportunidad</dd>
                </div>
                <div>
                    <dt>{{ $featuredRaffle->draw_date ? $featuredRaffle->draw_date->copy()->locale('es')->translatedFormat('j M') : 'Por definir' }}</dt>
                    <dd>Sorteo principal{{ $featuredLottery !== '' ? ', ' . $featuredLottery : '' }}</dd>
                </div>
                <div>
                    <dt class="num">{{ $count($featuredRaffle->available_numbers_count) }}</dt>
                    <dd>Disponibles de {{ $count($featuredRaffle->numbers_count) }}</dd>
                </div>
                <div>
                    <dt class="num">{{ $earlyCount > 0 ? $earlyCount : $timeout . ' min' }}</dt>
                    <dd>{{ $earlyCount > 0 ? ($earlyCount === 1 ? 'Premio anticipado antes del sorteo' : 'Premios anticipados antes del sorteo') : 'Para pagar tu reserva' }}</dd>
                </div>
            </dl>
        </section>

        {{-- 3. Fechas para ganar: premio mayor y premios anticipados. --}}
        <section class="me-section" id="premios">
            <div class="wrap">
                <p class="me-kicker reveal">Premios</p>
                <h2 class="me-head reveal">{{ $earlyCount > 0 ? ($earlyCount + 1) . ' fechas para ganar' : 'Lo que te puedes ganar' }}</h2>

                <article class="me-main-prize reveal">
                    <p class="me-kicker-dark">Premio mayor</p>
                    <h3 class="me-main-title">{{ $featuredRaffle->title }}</h3>
                    <p class="me-main-when">
                        <strong>{{ $featuredDraw ? ucfirst($featuredDraw) : 'Fecha por confirmar' }}</strong>
                        @if ($featuredLottery !== '')
                            <span>{{ $featuredLottery }}</span>
                        @endif
                    </p>
                </article>

                @if ($earlyCount > 0)
                    @php
                        $formatEarlyDraw = function (\App\Models\RaffleEarlyPrize $prize): string {
                            $date = $prize->draw_date->copy()->locale('es')->translatedFormat('l j \d\e F');
                            if (filled($prize->draw_time)) {
                                $at = $prize->drawsAt();
                                $date .= ', ' . $at->format('g:i') . ' ' . ($at->format('A') === 'AM' ? 'a. m.' : 'p. m.');
                            }

                            return $date;
                        };
                    @endphp
                    <p class="me-note reveal" id="premios-anticipados">Premios anticipados: se juegan antes del premio mayor con los mismos números. Si ganas uno, sigues jugando por el premio mayor.</p>
                    <ol class="me-prize-grid">
                        @foreach ($featuredRaffle->earlyPrizes as $prize)
                            @php $status = $prize->status(); @endphp
                            <li class="me-prize reveal is-{{ $status }}" style="--delay: {{ 80 + $loop->index * 80 }}ms">
                                @if ($prize->imageUrl())
                                    <img class="me-prize-img" src="{{ $prize->imageUrl() }}" alt="{{ $prize->title }}" loading="lazy" width="480" height="300">
                                @endif
                                <p class="me-kicker">Anticipado {{ $loop->iteration }}</p>
                                <h3 class="me-prize-when">{{ ucfirst($formatEarlyDraw($prize)) }}</h3>
                                <p class="me-prize-title">{{ $prize->title }}</p>
                                @if (filled($prize->description))
                                    <p class="me-prize-desc">{!! nl2br(e($prize->description)) !!}</p>
                                @endif
                                <p class="me-prize-meta">{{ $prize->lottery_name }}@if ($prize->lottery_draw_number), sorteo {{ $prize->lottery_draw_number }}@endif</p>
                                <p class="me-prize-status">
                                    @switch($status)
                                        @case(\App\Models\RaffleEarlyPrize::STATUS_WON)
                                            Ganó el {{ $prize->result_number }}: {{ $prize->publicWinnerLabel() }}
                                            @break
                                        @case(\App\Models\RaffleEarlyPrize::STATUS_VOID)
                                            Cayó el {{ $prize->result_number }}, que no se vendió. Premio desierto.
                                            @break
                                        @default
                                            Por jugar
                                    @endswitch
                                    @if ($prize->result_published_at && $prize->lottery_reference_url)
                                        · <a href="{{ $prize->lottery_reference_url }}" target="_blank" rel="noopener noreferrer">Ver resultado oficial</a>
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <p class="me-center reveal"><a class="me-btn me-btn-brand" href="{{ $pickerUrlFor($featuredRaffle, 'landing_prizes') }}">Adquiere tu oportunidad</a></p>
            </div>
        </section>
    @endif

    {{-- 4. Cómo participar en 3 pasos, con los métodos de pago. --}}
    <section class="me-section me-section-alt" id="como-funciona">
        <div class="wrap me-steps-grid">
            <div>
                <p class="me-kicker reveal">Cómo funciona</p>
                <h2 class="me-head reveal">Asegura tu oportunidad en 3 pasos</h2>
                <ol class="me-steps">
                    <li class="reveal">
                        <span class="me-step-n" aria-hidden="true">1</span>
                        <div>
                            <h3>Elige tus números</h3>
                            <p>Escoge tus números en esta página o pídelos al azar. Quedan apartados a tu nombre por {{ $timeout }} minutos.</p>
                        </div>
                    </li>
                    <li class="reveal" style="--delay: 80ms">
                        <span class="me-step-n" aria-hidden="true">2</span>
                        <div>
                            <h3>Paga y envía el comprobante</h3>
                            <p>Paga con uno de los métodos de abajo y envía la foto del comprobante por WhatsApp antes de que venza la reserva.</p>
                        </div>
                    </li>
                    <li class="reveal" style="--delay: 160ms">
                        <span class="me-step-n" aria-hidden="true">3</span>
                        <div>
                            <h3>Recibe tu boleta</h3>
                            <p>Una persona revisa tu pago y te llega la boleta con código QR por WhatsApp. Puedes verificarla aquí cuando quieras.</p>
                        </div>
                    </li>
                </ol>
            </div>

            <aside class="me-pay reveal" style="--delay: 90ms" aria-labelledby="pay-title">
                <p class="me-kicker">Pagos</p>
                <h3 id="pay-title">Información operativa</h3>
                <p>Los métodos de pago visibles se confirman por WhatsApp y cada comprobante pasa por revisión administrativa antes de emitir la boleta digital. El resultado oficial se toma de la lotería de referencia de cada rifa.</p>
                @if ($paymentMethods->isNotEmpty())
                    <p class="me-pay-label">Métodos visibles:</p>
                    <ul class="me-pay-list">
                        @foreach ($paymentMethods as $paymentMethod)
                            <li>
                                <strong>{{ $paymentMethod->name }}@if($paymentMethod->account_reference): {{ $paymentMethod->account_reference }}@endif</strong>
                                @if ($paymentMethod->account_holder)
                                    <span>A nombre de {{ $paymentMethod->account_holder }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <ul class="me-pay-rules">
                    <li>Tus números se reservan por tiempo limitado mientras completas el pago.</li>
                    <li>Si envías el comprobante antes del sorteo, la compra queda en revisión y tus números no se liberan automáticamente.</li>
                    <li>Las compras pendientes se resuelven antes de publicar el resultado final.</li>
                </ul>
                @if ($supportPhoneDigits)
                    <p><a class="me-btn me-btn-outline" href="https://wa.me/{{ $supportPhoneDigits }}?text={{ rawurlencode('PAGOS') }}">Hablar con soporte</a></p>
                @endif
            </aside>
        </div>
    </section>

    {{-- 5. Las demás rifas abiertas (o todas, si no hay destacada). --}}
    @if (! $featuredRaffle || $otherRaffles->isNotEmpty() || $raffles->isEmpty())
        <section class="me-section" id="raffles">
            <div class="wrap">
                <p class="me-kicker reveal">Rifas</p>
                <h2 class="me-head reveal">{{ $featuredRaffle && $otherRaffles->isNotEmpty() ? 'Más rifas activas' : 'Rifas disponibles' }}</h2>

                @if ($raffles->isEmpty())
                    <div class="me-empty reveal">
                        <h3>No hay rifas activas por ahora</h3>
                        <p>Vuelve pronto. Cada rifa nueva aparece aquí apenas se publica.</p>
                    </div>
                @else
                    @if ($catalogRaffles->count() > 1)
                        <div class="filters" role="group" aria-label="Filtrar rifas">
                            <button type="button" class="filter-chip is-active" data-filter="all" aria-pressed="true">Todas</button>
                            @foreach ($catalogRaffles as $raffle)
                                <button type="button" class="filter-chip" data-filter="{{ $raffle->slug }}" aria-pressed="false">{{ $raffle->title }}</button>
                            @endforeach
                        </div>
                    @endif

                    <ul class="me-raffles" id="raffle-grid">
                        @foreach ($catalogRaffles as $raffle)
                            @php
                                $cover = $coverFor($raffle);
                                $draw = $drawLabel($raffle);
                                $directWhatsappUrl = $whatsappFor('Quiero comprar en ' . $raffle->title);
                            @endphp
                            <li class="me-raffle raffle-card reveal" style="--delay: {{ 80 + $loop->index * 80 }}ms" data-raffle-slug="{{ $raffle->slug }}">
                                <div class="me-raffle-thumb">
                                    @if ($cover)
                                        <img src="{{ $cover }}" alt="Premio: {{ $raffle->title }}" width="480" height="320" loading="lazy">
                                    @else
                                        <span class="num" aria-hidden="true">{{ str_repeat('0', max(1, (int) $raffle->number_digits)) }}</span>
                                    @endif
                                </div>
                                <div class="me-raffle-body">
                                    <div class="me-raffle-line">
                                        <h3>{{ $raffle->title }}</h3>
                                        <span class="me-raffle-price num">{{ $money($raffle->price_per_number) }}</span>
                                    </div>
                                    @if (filled($raffle->description))
                                        <p class="me-raffle-desc">{{ \Illuminate\Support\Str::limit($raffle->description, 140) }}</p>
                                    @endif
                                    <p class="me-raffle-meta">
                                        {{ $draw ? 'Sorteo el ' . $draw : 'Fecha de sorteo pendiente de configuración.' }}@if ($lotteryFor($raffle) !== ''), {{ $lotteryFor($raffle) }}@endif.
                                        @if ($drawIso($raffle))
                                            <span data-countdown data-draw-at="{{ $drawIso($raffle) }}">Faltan <span data-countdown-text>…</span>.</span>
                                        @endif
                                    </p>
                                    <p class="me-raffle-status num">
                                        @if ($raffle->available_numbers_count <= 25)
                                            Últimos {{ $count($raffle->available_numbers_count) }} disponibles
                                        @else
                                            Quedan {{ $count($raffle->available_numbers_count) }} de {{ $count($raffle->numbers_count) }}
                                        @endif
                                    </p>
                                    <div class="me-raffle-actions">
                                        <a class="me-btn me-btn-brand" href="{{ $pickerUrlFor($raffle, 'landing_catalog') }}">Seleccionar números</a>
                                        @if ($directWhatsappUrl)
                                            <a class="link" href="{{ $directWhatsappUrl }}">Comprar por WhatsApp</a>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    {{-- 6. Ganadores publicados: la prueba de que se entrega. --}}
    @if ($recentResults->isNotEmpty())
        <section class="me-section me-section-alt" id="ganadores">
            <div class="wrap">
                <p class="me-kicker reveal">Resultados</p>
                <h2 class="me-head reveal">Ganadores recientes</h2>
                <ul class="me-winners">
                    @foreach ($recentResults as $recentResult)
                        <li class="reveal" style="--delay: {{ 60 + $loop->index * 60 }}ms">
                            <span class="me-winner-number num">{{ $recentResult->result_number ?: 'N/A' }}</span>
                            <div>
                                <strong>{{ $recentResult->public_winner_label }}</strong>
                                <span>{{ $recentResult->title }}</span>
                                <span class="me-muted">{{ $recentResult->lottery_name ?: 'Referencia pendiente' }}@if($recentResult->lottery_draw_number) #{{ $recentResult->lottery_draw_number }}@endif, {{ $recentResult->result_published_at?->locale('es')->translatedFormat('j \d\e F') ?: 'publicado' }}</span>
                                @if ($recentResult->lottery_reference_url)
                                    <a class="link" href="{{ $recentResult->lottery_reference_url }}" target="_blank" rel="noopener noreferrer">Ver resultado oficial</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- 7. Preguntas frecuentes. --}}
    @if ($publicFaqEntries->isNotEmpty())
        <section class="me-section" id="preguntas">
            <div class="wrap me-faq">
                <div>
                    <p class="me-kicker reveal">Dudas</p>
                    <h2 class="me-head reveal">Preguntas frecuentes</h2>
                </div>
                <div class="faq-list">
                    @foreach ($publicFaqEntries as $faqEntry)
                        <details class="faq-item reveal" style="--delay: {{ 60 + $loop->index * 50 }}ms">
                            <summary>{{ $faqEntry->title }}</summary>
                            <div class="faq-answer">{!! nl2br(e($faqEntry->body_text)) !!}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 8. Llamado final. --}}
    @if ($featuredRaffle)
        <section class="me-final">
            <div class="wrap reveal">
                <h2 class="me-display me-final-title">Asegura tu oportunidad <span>hoy</span></h2>
                <p>Por {{ $money($featuredRaffle->price_per_number) }} participas por {{ $featuredRaffle->title }}{{ $earlyCount > 0 ? ' y ' . ($earlyCount === 1 ? 'un premio anticipado' : $earlyCount . ' premios anticipados') : '' }}.</p>
                <a class="me-btn me-btn-brand me-btn-lg" href="{{ $pickerUrlFor($featuredRaffle, 'landing_final') }}">Adquiere tu oportunidad</a>
            </div>
        </section>
    @endif
</main>

<footer class="me-footer">
    <div class="wrap me-footer-grid">
        <div>
            @if ($brandLogoUrl)
                <img class="me-footer-logo" src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" height="56">
            @else
                <p class="me-display me-footer-name">{{ $brandName }}</p>
            @endif
            <p class="me-muted">{{ $company?->help_message ?: 'Si tienes dudas con un pago o una boleta, escríbenos por WhatsApp y te respondemos en el mismo chat.' }}</p>
        </div>
        <div>
            <p class="me-footer-label">Contacto</p>
            <ul class="me-footer-links">
                @if ($supportPhoneDigits)
                    <li><a class="link" href="https://wa.me/{{ $supportPhoneDigits }}?text={{ rawurlencode('PAGOS') }}">Soporte por WhatsApp</a></li>
                @endif
                @if ($company?->support_email)
                    <li>{{ $company->support_email }}</li>
                @endif
                @if ($company?->website_url)
                    <li><a class="link" href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">Sitio web</a></li>
                @endif
            </ul>
        </div>
        <div>
            <p class="me-footer-label">Legal</p>
            <ul class="me-footer-links">
                @if ($termsUrl)
                    <li><a class="link" href="{{ $termsUrl }}">Términos y condiciones</a></li>
                @endif
                @if ($privacyUrl)
                    <li><a class="link" href="{{ $privacyUrl }}">Datos personales</a></li>
                @endif
                <li class="me-muted">Solo mayores de 18 años</li>
            </ul>
        </div>
    </div>
    <p class="wrap me-imprint">© {{ now()->year }} {{ $company?->legal_name ?: $brandName }}</p>
</footer>

@if ($raffles->isNotEmpty() || $stickyWhatsappUrl)
    <div class="sticky-cta" data-testid="mobile-sticky-cta">
        <a class="me-btn me-btn-brand" href="{{ $featuredRaffle ? $pickerUrlFor($featuredRaffle, 'landing_sticky') : $featuredPickerUrl }}">{{ $featuredRaffle ? 'Elegir números' : 'Ver rifas' }}</a>
        @if ($stickyWhatsappUrl)
            <a class="me-btn me-btn-outline" href="{{ $stickyWhatsappUrl }}">WhatsApp</a>
        @else
            <a class="me-btn me-btn-outline" href="#raffles">Explorar</a>
        @endif
    </div>
@endif

<script>
(() => {
    // Cuenta regresiva en palabras ("19 días y 4 horas").
    const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;
    const countdowns = Array.from(document.querySelectorAll('[data-countdown][data-draw-at]'));
    const renderCountdown = (el) => {
        const diff = Math.max(new Date(el.dataset.drawAt).getTime() - Date.now(), 0);
        const d = Math.floor(diff / 86400000), h = Math.floor((diff % 86400000) / 3600000), m = Math.floor((diff % 3600000) / 60000), s = Math.floor((diff % 60000) / 1000);
        const text = d > 0 ? `${plural(d, 'día', 'días')} y ${plural(h, 'hora', 'horas')}` : h > 0 ? `${plural(h, 'hora', 'horas')} y ${plural(m, 'minuto', 'minutos')}` : `${plural(m, 'minuto', 'minutos')} y ${plural(s, 'segundo', 'segundos')}`;
        const target = el.querySelector('[data-countdown-text]');
        if (target) target.textContent = diff === 0 ? 'unos instantes' : text;
    };
    countdowns.forEach(renderCountdown);
    if (countdowns.length) setInterval(() => countdowns.forEach(renderCountdown), 1000);

    // Filtro de rifas.
    const chips = Array.from(document.querySelectorAll('.filter-chip'));
    const cards = Array.from(document.querySelectorAll('.raffle-card[data-raffle-slug]'));
    chips.forEach((chip) => chip.addEventListener('click', () => {
        const filter = chip.dataset.filter || 'all';
        chips.forEach((c) => { c.classList.toggle('is-active', c === chip); c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
        cards.forEach((card) => card.classList.toggle('is-hidden', !(filter === 'all' || card.dataset.raffleSlug === filter)));
    }));

    // Busca tu número: consulta la disponibilidad real mientras escribes.
    const lookup = document.querySelector('[data-lookup]');
    if (lookup) {
        const input = lookup.querySelector('input[name="pick"]');
        const results = lookup.querySelector('[data-lookup-results]');
        const status = lookup.querySelector('[data-lookup-status]');
        const digits = Number(lookup.dataset.digits || 0);
        let timer = null, seq = 0;
        const pickUrl = (number) => { const u = new URL(lookup.dataset.pickerUrl, location.href); u.searchParams.set('pick', number); return u.toString(); };
        const render = (items, term) => {
            results.replaceChildren();
            const exact = items.find((i) => i.number === term.padStart(digits, '0'));
            const list = (exact ? [exact, ...items.filter((i) => i !== exact)] : items).slice(0, 8);
            list.forEach((item) => {
                const el = document.createElement(item.selectable ? 'a' : 'span');
                el.className = 'chip-number';
                if (item.selectable) el.href = pickUrl(item.number); else el.setAttribute('aria-disabled', 'true');
                el.innerHTML = `<strong></strong><span></span>`;
                el.querySelector('strong').textContent = item.number;
                el.querySelector('span').textContent = item.selectable ? 'Libre' : item.status_label;
                results.appendChild(el);
            });
            const free = list.filter((i) => i.selectable).length;
            status.textContent = list.length === 0 ? 'No encontramos ese número en esta rifa.'
                : free === 0 ? 'Esos números ya no están libres. Prueba con otras cifras.'
                : 'Toca un número libre para apartarlo.';
        };
        const search = async (term) => {
            const id = ++seq;
            status.textContent = 'Buscando…';
            try {
                const res = await fetch(`${lookup.dataset.feedUrl}?${new URLSearchParams({ search: term, per_page: '24' })}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (id === seq) render(data.items || [], term);
            } catch (_) {
                if (id === seq) status.textContent = 'No pudimos consultar ahora. Usa el botón Buscar.';
            }
        };
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D+/g, '').slice(0, digits || 6);
            clearTimeout(timer);
            if (input.value === '') { results.replaceChildren(); status.textContent = 'Te decimos al instante si está libre.'; return; }
            timer = setTimeout(() => search(input.value), 220);
        });
    }

    // Entradas de cada sección al aparecer (el contenido ya está; solo se mueve).
    const reveals = Array.from(document.querySelectorAll('.reveal'));
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => entries.forEach((e) => {
            if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
        }), { rootMargin: '0px 0px -10% 0px' });
        reveals.forEach((el) => io.observe(el));
    } else {
        reveals.forEach((el) => el.classList.add('is-in'));
    }
})();
</script>
</body>
</html>
