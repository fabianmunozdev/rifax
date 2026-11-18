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
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $company?->primary_color) ? $company->primary_color : '#bfb21a';
        [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
        $accentInk = ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.55 ? '#181715' : '#f4f4f0';
        $timeout = (int) ($featuredRaffle?->reservation_timeout_minutes ?: 15);
    @endphp
    <title>{{ $brandName }} | Rifas activas</title>
    <meta name="description" content="{{ $featuredRaffle ? 'Rifa ' . $featuredRaffle->title . ': ' . $money($featuredRaffle->price_per_number) . ' por número. Elige tus números y paga por WhatsApp.' : 'Rifas activas de ' . $brandName . '. Elige tus números y paga por WhatsApp.' }}">
    <meta name="theme-color" content="#181715">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rifax.css') }}?v={{ @filemtime(public_path('css/rifax.css')) }}">
    <style>:root { --accent: {{ $accent }}; --accent-ink: {{ $accentInk }}; }</style>
    <script>document.documentElement.classList.add('js');</script>
</head>
<body @class(['has-sticky' => $raffles->isNotEmpty() || $stickyWhatsappUrl])>
<div class="main">

    {{-- 1. La boleta: qué se rifa, cuánto cuesta, cuándo se juega y la acción. --}}
    <section class="hero field-night">
        <div class="wrap">
            <header class="bar">
                <a class="bar-brand" href="{{ url('/') }}">
                    @if ($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="" width="40" height="40">
                    @endif
                    <span>{{ $brandName }}</span>
                </a>
                <nav class="bar-links" aria-label="Secciones">
                    <a href="#raffles">Rifas</a>
                    <a href="#como-funciona">Cómo funciona</a>
                    @if ($publicFaqEntries->isNotEmpty())
                        <a href="#preguntas">Preguntas</a>
                    @endif
                </nav>
                @if ($featuredRaffle)
                    <a class="btn btn-paper" href="{{ $featuredPickerUrl }}">Elegir números</a>
                @elseif ($whatsappFor('MENU'))
                    <a class="btn btn-paper" href="{{ $whatsappFor('MENU') }}">Escribir por WhatsApp</a>
                @endif
            </header>

            <div class="hero-stage">
                @if ($featuredRaffle)
                    @php
                        $featuredCover = $coverFor($featuredRaffle);
                        $featuredDraw = $drawLabel($featuredRaffle);
                        $featuredWhatsappUrl = $whatsappFor('Quiero comprar en ' . $featuredRaffle->title);
                    @endphp
                    <article class="ticket paper" aria-labelledby="ticket-title">
                        <div class="ticket-body">
                            <h1 id="ticket-title" class="display">{{ $featuredRaffle->title }}</h1>
                            <p class="ticket-lede lead">
                                Rifa destacada de {{ $brandName }}: {{ $money($featuredRaffle->price_per_number) }} por número.
                                @if ($lotteryFor($featuredRaffle) !== '')
                                    Se juega con la {{ $lotteryFor($featuredRaffle) }}.
                                @endif
                            </p>

                            @if (filled($featuredRaffle->description))
                                <p class="ticket-desc">{{ $featuredRaffle->description }}</p>
                            @endif

                            @if ($featuredCover)
                                <figure class="ticket-photo">
                                    <img src="{{ $featuredCover }}" alt="Premio: {{ $featuredRaffle->title }}" width="1200" height="900" fetchpriority="high">
                                </figure>
                            @endif

                            <dl class="ticket-facts">
                                <div>
                                    <dt>Sorteo</dt>
                                    <dd>{{ $featuredDraw ? ucfirst($featuredDraw) : 'Fecha por confirmar' }}</dd>
                                </div>
                                <div>
                                    <dt>Disponibles</dt>
                                    <dd class="num">Quedan {{ $count($featuredRaffle->available_numbers_count) }} de {{ $count($featuredRaffle->numbers_count) }}</dd>
                                </div>
                                <div>
                                    <dt>Compra mínima</dt>
                                    <dd>{{ $featuredRaffle->min_numbers_per_purchase }} {{ (int) $featuredRaffle->min_numbers_per_purchase === 1 ? 'número' : 'números' }} de {{ $featuredRaffle->number_digits }} cifras</dd>
                                </div>
                                <div>
                                    <dt>Tiempo para pagar</dt>
                                    <dd>{{ $timeout }} minutos de reserva</dd>
                                </div>
                            </dl>

                            @if ($drawIso($featuredRaffle))
                                <p class="ticket-countdown num" data-countdown data-draw-at="{{ $drawIso($featuredRaffle) }}">
                                    Faltan <span data-countdown-text>…</span> para el sorteo
                                </p>
                            @endif
                        </div>

                        <div class="ticket-stub perforated notches">
                            <div class="stub-row">
                                <a class="btn btn-accent" href="{{ $featuredPickerUrl }}">Elegir números ahora</a>
                                @if ($featuredWhatsappUrl)
                                    <a class="link" href="{{ $featuredWhatsappUrl }}">Comprar esta rifa por WhatsApp</a>
                                @endif
                            </div>

                            <form class="lookup" method="get" action="{{ route('raffles.number-picker', $featuredRaffle->slug) }}"
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
                                    <button class="btn btn-ink" type="submit">Buscar</button>
                                </div>
                                <p class="lookup-help" id="lookup-help" aria-live="polite" data-lookup-status>Te decimos al instante si está libre.</p>
                                <div class="lookup-results" data-lookup-results></div>
                            </form>
                        </div>
                    </article>
                @else
                    <article class="ticket paper" aria-labelledby="ticket-title">
                        <div class="ticket-body">
                            <h1 id="ticket-title" class="display">{{ $brandName }}</h1>
                            <p class="ticket-lede lead">
                                {{ $raffles->isNotEmpty()
                                    ? 'Rifas con números de la lotería oficial. Elige tus números y paga por WhatsApp.'
                                    : ($company?->help_message ?: 'No hay rifas abiertas en este momento. Escríbenos y te avisamos cuando salga la próxima.') }}
                            </p>
                        </div>
                        <div class="ticket-stub perforated notches">
                            <div class="stub-row">
                                <a class="btn btn-accent" href="#raffles">Ver rifas actuales</a>
                                @if ($whatsappFor('MENU'))
                                    <a class="link" href="{{ $whatsappFor('MENU') }}">Comprar por WhatsApp</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endif
            </div>
        </div>
    </section>

    {{-- 1b. Premios anticipados de la rifa destacada. --}}
    @if ($featuredRaffle && $featuredRaffle->earlyPrizes->isNotEmpty())
        <section class="section field-paper early-prizes-section">
            <div class="wrap reveal">
                @include('partials.early-prizes', ['raffle' => $featuredRaffle])
            </div>
        </section>
    @endif

    {{-- 2. Cómo se compra, impreso como un recibo. --}}
    <section class="section field-surface" id="como-funciona">
        <div class="wrap grid">
            <div class="steps-head reveal">
                <h2 class="head">Cómo funciona</h2>
                <p class="lead">Eliges, pagas y recibes tu boleta por WhatsApp. Una persona revisa cada pago antes de emitirla.</p>
            </div>
            <div class="slip zigzag-bottom reveal" style="--delay: 90ms">
                <div class="slip-group reveal" style="--delay: 150ms">
                    <h3>Ahora</h3>
                    <p class="slip-row"><span>Eliges tus números o los pedimos al azar</span><span class="lead-dots" aria-hidden="true"></span><span>2 min</span></p>
                    <p class="slip-row"><span>Quedan apartados a tu nombre</span><span class="lead-dots" aria-hidden="true"></span><span>{{ $timeout }} min</span></p>
                </div>
                <div class="slip-group reveal" style="--delay: 230ms">
                    <h3>Antes de que venza la reserva</h3>
                    <p class="slip-row"><span>Pagas con uno de los métodos de abajo</span><span class="lead-dots" aria-hidden="true"></span><span>Nequi o banco</span></p>
                    <p class="slip-row"><span>Envías la foto del comprobante al WhatsApp</span><span class="lead-dots" aria-hidden="true"></span><span>1 foto</span></p>
                </div>
                <div class="slip-group reveal" style="--delay: 310ms">
                    <h3>Cuando aprobamos el pago</h3>
                    <p class="slip-row"><span>Revisión del comprobante</span><span class="lead-dots" aria-hidden="true"></span><span>el mismo día</span></p>
                    <p class="slip-row"><span>Te llega la boleta con código QR</span><span class="lead-dots" aria-hidden="true"></span><span>por WhatsApp</span></p>
                </div>
                <p class="slip-total"><span>Tu boleta verificable</span><span>lista para el sorteo</span></p>
            </div>
        </div>
    </section>

    {{-- 3. Las rifas abiertas, en una hoja impresa. --}}
    <section class="section field-night" id="raffles">
        <div class="wrap sheet-section">
            <h2 class="head reveal">{{ $featuredRaffle && $otherRaffles->isNotEmpty() ? 'Más rifas activas' : 'Rifas disponibles' }}</h2>

            <div class="sheet paper scallop-top reveal" style="--delay: 90ms">
                @if ($raffles->isEmpty())
                    <div class="empty-state">
                        <h3 class="title">No hay rifas activas por ahora</h3>
                        <p>Vuelve pronto. Cada rifa nueva aparece aquí apenas se publica.</p>
                    </div>
                @else
                    <p class="sheet-note muted">{{ $catalogRaffles->count() === 1 ? 'Una rifa abierta' : $catalogRaffles->count() . ' rifas abiertas' }}. Cada una se juega con la lotería indicada.</p>

                    @if ($catalogRaffles->count() > 1)
                        <div class="filters" role="group" aria-label="Filtrar rifas">
                            <button type="button" class="filter-chip is-active" data-filter="all" aria-pressed="true">Todas</button>
                            @foreach ($catalogRaffles as $raffle)
                                <button type="button" class="filter-chip" data-filter="{{ $raffle->slug }}" aria-pressed="false">{{ $raffle->title }}</button>
                            @endforeach
                        </div>
                    @endif

                    <ul class="sheet-list" id="raffle-grid">
                        @foreach ($catalogRaffles as $raffle)
                            @php
                                $cover = $coverFor($raffle);
                                $draw = $drawLabel($raffle);
                                $directWhatsappUrl = $whatsappFor('Quiero comprar en ' . $raffle->title);
                            @endphp
                            <li class="sheet-item raffle-card reveal" style="--delay: {{ 120 + $loop->index * 80 }}ms" data-raffle-slug="{{ $raffle->slug }}">
                                <div class="sheet-thumb">
                                    @if ($cover)
                                        <img src="{{ $cover }}" alt="Premio: {{ $raffle->title }}" width="224" height="224" loading="lazy">
                                    @else
                                        <span class="sheet-thumb-number" aria-hidden="true">{{ str_repeat('0', max(1, (int) $raffle->number_digits)) }}</span>
                                    @endif
                                </div>
                                <div class="sheet-line">
                                    <h3 class="title">{{ $raffle->title }}</h3>
                                    <span class="sheet-price num">{{ $money($raffle->price_per_number) }}</span>
                                </div>
                                @if (filled($raffle->description))
                                    <p class="sheet-desc">{{ \Illuminate\Support\Str::limit($raffle->description, 140) }}</p>
                                @endif
                                <p class="sheet-meta">
                                    {{ $draw ? 'Sorteo el ' . $draw : 'Fecha de sorteo pendiente de configuración.' }}@if ($lotteryFor($raffle) !== ''), {{ $lotteryFor($raffle) }}@endif.
                                    @if ($drawIso($raffle))
                                        <span data-countdown data-draw-at="{{ $drawIso($raffle) }}">Faltan <span data-countdown-text>…</span>.</span>
                                    @endif
                                </p>
                                <p class="sheet-status num">
                                    @if ($raffle->available_numbers_count <= 25)
                                        Últimos {{ $count($raffle->available_numbers_count) }} disponibles
                                    @else
                                        Quedan {{ $count($raffle->available_numbers_count) }} de {{ $count($raffle->numbers_count) }}
                                    @endif
                                </p>
                                <div class="sheet-actions">
                                    <a class="link" href="{{ $pickerUrlFor($raffle, 'landing_catalog') }}">Seleccionar números</a>
                                    @if ($directWhatsappUrl)
                                        <a class="link" href="{{ $directWhatsappUrl }}">Comprar por WhatsApp</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    {{-- 4. Ganadores ya publicados: la prueba de que se entrega. --}}
    @if ($recentResults->isNotEmpty())
        <section class="section winners field-night" id="ganadores">
            <div class="wrap">
                <div class="winners-card paper reveal">
                    <h2 class="title">Ganadores recientes</h2>
                    <ul class="winners-list">
                        @foreach ($recentResults as $recentResult)
                            <li>
                                <span class="winners-number num">{{ $recentResult->result_number ?: 'N/A' }}</span>
                                {{ $recentResult->public_winner_label }}, {{ $recentResult->title }}
                                <span class="muted">({{ $recentResult->lottery_name ?: 'Referencia pendiente' }}@if($recentResult->lottery_draw_number) #{{ $recentResult->lottery_draw_number }}@endif, {{ $recentResult->result_published_at?->locale('es')->translatedFormat('j \d\e F') ?: 'publicado' }})</span>
                                @if ($recentResult->lottery_reference_url)
                                    <br><a href="{{ $recentResult->lottery_reference_url }}" target="_blank" rel="noopener noreferrer">Ver resultado oficial</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @endif

    {{-- 5. Preguntas y cómo pagar. --}}
    <section class="section field-paper" id="preguntas">
        <div class="wrap grid faq-grid">
            <div class="faq-col reveal">
                <h2 class="head">{{ $publicFaqEntries->isNotEmpty() ? 'Preguntas frecuentes' : 'Cómo pagar' }}</h2>
                @if ($publicFaqEntries->isNotEmpty())
                    <div class="faq-list">
                        @foreach ($publicFaqEntries as $faqEntry)
                            <details class="faq-item reveal" style="--delay: {{ 80 + $loop->index * 60 }}ms">
                                <summary>{{ $faqEntry->title }}</summary>
                                <div class="faq-answer">{!! nl2br(e($faqEntry->body_text)) !!}</div>
                            </details>
                        @endforeach
                    </div>
                @endif
                @if ($supportPhoneDigits)
                    <p style="margin-top: 2rem"><a class="btn btn-ink" href="https://wa.me/{{ $supportPhoneDigits }}?text={{ rawurlencode('PAGOS') }}">Hablar con soporte</a></p>
                @endif
            </div>

            <aside class="faq-side reveal" style="--delay: 90ms" aria-labelledby="pay-title">
                <div class="pay-card">
                    <h3 id="pay-title">Información operativa</h3>
                    <p style="margin-top: 1rem">Los métodos de pago visibles se confirman por WhatsApp y cada comprobante pasa por revisión administrativa antes de emitir la boleta digital. El resultado oficial se toma de la lotería de referencia de cada rifa.</p>
                    @if ($paymentMethods->isNotEmpty())
                        <p style="margin-top: 1.5rem; font-weight: 500">Métodos visibles:</p>
                        <ul class="pay-list">
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
                    <ul class="pay-rules">
                        <li>Tus números se reservan por tiempo limitado mientras completas el pago.</li>
                        <li>Si envías el comprobante antes del sorteo, la compra queda en revisión y tus números no se liberan automáticamente.</li>
                        <li>Las compras pendientes se resuelven antes de publicar el resultado final.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </section>
</div>

{{-- 6. El reverso de la boleta: se descubre al final, como una cortina. --}}
<footer class="footer">
    <div class="wrap">
        <div class="footer-card paper scallop-top reveal">
            <div class="footer-cols">
                <div>
                    <p class="footer-signoff display reveal" style="--delay: 120ms">Suerte en el sorteo</p>
                    <p class="footer-copy">{{ $company?->help_message ?: 'Si tienes dudas con un pago o una boleta, escríbenos por WhatsApp y te respondemos en el mismo chat.' }}</p>
                    @if ($company?->support_email)
                        <p class="footer-copy">{{ $company->support_email }}</p>
                    @endif
                </div>
                <ul class="footer-links">
                    @if ($supportPhoneDigits)
                        <li><a class="link" href="https://wa.me/{{ $supportPhoneDigits }}?text={{ rawurlencode('PAGOS') }}">Soporte por WhatsApp</a></li>
                    @endif
                    @if ($termsUrl)
                        <li><a class="link" href="{{ $termsUrl }}">Términos y condiciones</a></li>
                    @endif
                    @if ($privacyUrl)
                        <li><a class="link" href="{{ $privacyUrl }}">Datos personales</a></li>
                    @endif
                    @if ($company?->website_url)
                        <li><a class="link" href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">Sitio web</a></li>
                    @endif
                </ul>
            </div>
            <p class="footer-imprint">© {{ now()->year }} {{ $company?->legal_name ?: $brandName }}</p>
        </div>
    </div>
</footer>

@if ($raffles->isNotEmpty() || $stickyWhatsappUrl)
    <div class="sticky-cta" data-testid="mobile-sticky-cta">
        <a class="btn btn-accent" href="{{ $featuredRaffle ? $pickerUrlFor($featuredRaffle, 'landing_sticky') : $featuredPickerUrl }}">{{ $featuredRaffle ? 'Elegir números' : 'Ver rifas' }}</a>
        @if ($stickyWhatsappUrl)
            <a class="btn btn-paper" href="{{ $stickyWhatsappUrl }}">WhatsApp</a>
        @else
            <a class="btn btn-paper" href="#raffles">Explorar</a>
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
