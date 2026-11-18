<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $raffle->title }} | Selección de números</title>
    @php
        $brandName = $company?->trade_name ?: config('app.name', 'Rifax');
        $drawAt = $raffle->drawAt();
        $drawAtIso = $drawAt?->toIso8601String();
        $drawText = $drawAt
            ? ($drawAt->copy()->locale('es')->translatedFormat('l j \\d\\e F')) . ', ' . $drawAt->format('g:i') . ' ' . ($drawAt->format('A') === 'AM' ? 'a. m.' : 'p. m.')
            : null;
        $money = fn ($value) => '$' . number_format((float) $value, 0, ',', '.');
        $lottery = trim(($raffle->lottery_name ?: '') . ($raffle->lottery_draw_number ? ', sorteo ' . $raffle->lottery_draw_number : ''));
        $hasConfirm = ($pickerConfirmUrl ?? '') !== '';
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $company?->primary_color) ? $company->primary_color : '#bfb21a';
        [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
        $accentInk = ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.55 ? '#181715' : '#f4f4f0';
    @endphp
    <meta name="theme-color" content="#181715">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rifax.css') }}?v={{ @filemtime(public_path('css/rifax.css')) }}">
    <style>:root { --accent: {{ $accent }}; --accent-ink: {{ $accentInk }}; }</style>
</head>
<body>
<div class="main">
    <section class="picker-hero field-night">
        <div class="wrap">
            <header class="bar">
                <a class="bar-brand" href="{{ url('/') }}"><span>{{ $brandName }}</span></a>
                <a class="link" href="{{ url('/') }}#raffles">Ver todas las rifas</a>
            </header>
            <div class="grid">
                <div class="picker-title">
                    <h1 class="head">{{ $raffle->title }}</h1>
                    <p class="lead">
                        Elige {{ $quantity }} {{ $quantity === 1 ? 'número' : 'números' }} de {{ $raffle->number_digits }} cifras, a {{ $money($raffle->price_per_number) }} cada uno.
                        @if ($lottery !== '') Se juega con la {{ $lottery }}. @endif
                    </p>
                    @if ($raffle->earlyPrizes->isNotEmpty())
                        <p><a class="link" href="#premios-anticipados">Además, {{ $raffle->earlyPrizes->count() === 1 ? '1 premio anticipado' : $raffle->earlyPrizes->count().' premios anticipados' }} antes del sorteo</a></p>
                    @endif
                </div>
                <div class="picker-clock" @if($drawAtIso) data-countdown data-draw-at="{{ $drawAtIso }}" @endif>
                    @if ($drawText)
                        <p class="picker-clock-value num">Faltan <span data-countdown-text>…</span></p>
                        <p class="muted">Sorteo el {{ $drawText }}</p>
                    @else
                        <p class="muted">Fecha de sorteo pendiente de configuración.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($raffle->earlyPrizes->isNotEmpty())
        {{-- Arriba y plegable: la cuadrícula carga números sin fin, así que nada debajo de ella se alcanza. --}}
        <section class="field-surface early-prizes-fold-section">
            <div class="wrap">
                @include('partials.early-prizes', ['raffle' => $raffle, 'collapsible' => true])
            </div>
        </section>
    @endif

    <section class="picker-body field-paper">
        <div class="wrap">
            <div class="picker-summary num">
                <span><strong>Disponibles ahora: {{ number_format((int) $availableCount, 0, ',', '.') }}</strong></span>
                <span>Catálogo total: {{ number_format((int) $catalogCount, 0, ',', '.') }}</span>
                <span>Tienes {{ (int) ($raffle->reservation_timeout_minutes ?: 15) }} minutos para pagar después de reservar.</span>
            </div>

            @if (isset($pickerAuthCustomer) && $pickerAuthCustomer instanceof \App\Models\Customer)
                @php
                    $phoneDigits = preg_replace('/\D+/', '', (string) ($pickerAuthCustomer->phone ?? '')) ?: '';
                    $phoneMask = $phoneDigits !== '' ? '+'.substr($phoneDigits, 0, 2).' '.substr($phoneDigits, 2, 3).'***'.substr($phoneDigits, -4) : '';
                @endphp
                <div class="notice notice-ok" role="status">
                    <p><strong>Confirmando como {{ $pickerAuthCustomer->name ?: 'Cliente' }}</strong>@if ($phoneMask !== '') ({{ $phoneMask }})@endif</p>
                    <p>Tu selección se reserva al confirmar y te llegan los datos de pago por este WhatsApp.</p>
                    <p class="small">Si no eres esta persona, cierra esta ventana y escribe HOLA al bot para recibir tu propio enlace.</p>
                </div>
            @elseif (isset($pickerAuthError) && is_string($pickerAuthError) && $pickerAuthError !== '')
                <div class="notice notice-warn" role="alert">
                    <p><strong>Enlace de confirmación inválido o expirado</strong></p>
                    <p>{{ $pickerAuthError }}</p>
                </div>
            @endif

            @if (! $botPhoneDigits)
                <div class="notice notice-warn" role="alert">
                    <p>No hay un número de WhatsApp del bot configurado en administración. Configura <strong>whatsapp_bot_phone</strong> para habilitar el flujo web hacia WhatsApp.</p>
                </div>
            @endif

            @if ($numbers === [])
                <p class="notice">Esta rifa aún no tiene números cargados.</p>
            @else
                <div class="picker-tools">
                    <div class="picker-search">
                        <label class="visually-hidden" for="number-search">Buscar número</label>
                        <input type="search" id="number-search" inputmode="numeric" placeholder="Buscar número…" autocomplete="off">
                    </div>
                    <ul class="legend" aria-label="Estados de números">
                        <li><span class="legend-swatch legend-swatch--available"></span>Disponible</li>
                        <li><span class="legend-swatch legend-swatch--selected"></span>Tu selección</li>
                        <li><span class="legend-swatch legend-swatch--reserved"></span>Reservado</li>
                        <li><span class="legend-swatch legend-swatch--paid"></span>Pagado</li>
                        <li><span class="legend-swatch legend-swatch--winner"></span>Ganador</li>
                    </ul>
                </div>

                <div class="numbers-feed">
                    <div class="numbers-grid" id="numbers-grid"
                         data-quantity="{{ $quantity }}"
                         data-feed-url="{{ $numbersFeedUrl }}"
                         data-next-cursor="{{ $numbersNextCursor ?? '' }}">
                        @foreach ($numbers as $number)
                            <button type="button" class="number-button"
                                    data-number="{{ $number['number'] }}"
                                    data-status="{{ $number['status'] }}"
                                    aria-pressed="false"
                                    @disabled(($number['selectable'] ?? false) !== true)>
                                <span class="number-label">{{ $number['number'] }}</span>
                                <span class="number-state">{{ $number['status_label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="numbers-status" id="numbers-status" aria-live="polite"></div>
                    <div id="numbers-sentinel" aria-hidden="true"></div>
                </div>
            @endif
        </div>
    </section>
</div>

@if ($numbers !== [])
    <div class="picker-bar">
        <div class="wrap picker-bar-inner">
            <div class="picker-bar-text">
                <p class="picker-bar-count num" id="selection-status">0 seleccionados de {{ $quantity }}</p>
                <p class="picker-bar-help" id="selection-preview">Aún no has seleccionado números.</p>
                <p class="picker-bar-help" id="cta-help-text">{{ $hasConfirm
                    ? 'Confirma tus números y la reserva se crea automáticamente. Recibirás todo por WhatsApp.'
                    : 'Se abrirá un mensaje de WhatsApp listo para enviar. No necesitas editarlo.' }}</p>
                <p class="picker-bar-help">Al continuar aceptas los <a class="link" href="{{ route('legal.terms') }}" target="_blank">términos y condiciones</a> y la <a class="link" href="{{ route('legal.privacy') }}" target="_blank">política de datos personales</a>.</p>
                <p class="feedback" id="selection-feedback" aria-live="polite"></p>
            </div>
            <a id="send-selection-link" class="btn btn-accent cta" href="#" aria-disabled="true"
               data-phone="{{ $botPhoneDigits }}"
               data-intent-url="{{ $pickerIntentUrl }}"
               data-confirm-url="{{ $pickerConfirmUrl ?? '' }}"
               data-picker-trace='@json($pickerTrace)'>
                <span class="cta-label">{{ $hasConfirm ? 'Confirmar mi selección' : 'Continuar compra por WhatsApp' }}</span>
            </a>
        </div>
    </div>
@endif

    <script>
        (() => {
            // Cuenta regresiva en palabras.
            const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;
            const el = document.querySelector('[data-countdown][data-draw-at]');
            if (!el) return;
            const render = () => {
                const diff = Math.max(new Date(el.dataset.drawAt).getTime() - Date.now(), 0);
                const d = Math.floor(diff / 86400000), h = Math.floor((diff % 86400000) / 3600000), m = Math.floor((diff % 3600000) / 60000);
                el.querySelector('[data-countdown-text]').textContent = diff === 0 ? 'unos instantes'
                    : d > 0 ? `${plural(d, 'día', 'días')} y ${plural(h, 'hora', 'horas')}` : `${plural(h, 'hora', 'horas')} y ${plural(m, 'minuto', 'minutos')}`;
            };
            render();
            setInterval(render, 30000);
        })();

        (() => {
            const grid = document.getElementById('numbers-grid');
            if (!grid) return;

            const requiredQuantity = Number(grid.dataset.quantity || '1');
            const search = document.getElementById('number-search');
            const status = document.getElementById('selection-status');
            const preview = document.getElementById('selection-preview');
            const feedback = document.getElementById('selection-feedback');
            const numbersStatus = document.getElementById('numbers-status');
            const sentinel = document.getElementById('numbers-sentinel');
            const link = document.getElementById('send-selection-link');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const pickerTrace = JSON.parse(link?.dataset.pickerTrace || '{}');
            const defaultCtaHtml = link?.innerHTML || '';
            const feedUrl = grid.dataset.feedUrl || '';
            const renderedNumbers = new Set(Array.from(grid.querySelectorAll('.number-button')).map((button) => button.dataset.number || ''));
            const selected = new Set();
            let nextCursor = grid.dataset.nextCursor || '';
            let isSubmitting = false;
            let isLoadingNumbers = false;
            let searchTimer = null;
            let activeSearch = '';

            const setFeedback = (message = '', isError = false) => {
                feedback.textContent = message;
                feedback.classList.toggle('is-error', Boolean(message) && isError);
            };

            const setNumbersStatus = (message = '') => {
                numbersStatus.textContent = message;
            };

            const buildNumberButton = (item) => {
                const button = document.createElement('button');
                const isSelected = selected.has(item.number);

                button.type = 'button';
                button.className = `number-button${isSelected ? ' is-selected' : ''}`;
                button.dataset.number = item.number;
                button.dataset.status = item.status;
                button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                button.disabled = !item.selectable;
                button.innerHTML = `<span class="number-label">${item.number}</span><span class="number-state">${item.status_label}</span>`;

                return button;
            };

            const appendNumberButtons = (items, { reset = false } = {}) => {
                if (reset) {
                    grid.innerHTML = '';
                    renderedNumbers.clear();
                }

                items.forEach((item) => {
                    if (!item?.number || renderedNumbers.has(item.number)) {
                        return;
                    }

                    grid.appendChild(buildNumberButton(item));
                    renderedNumbers.add(item.number);
                });
            };

            const fetchNumbers = async ({ reset = false, searchTerm = activeSearch } = {}) => {
                if (!feedUrl || isLoadingNumbers) {
                    return;
                }

                if (!reset && nextCursor === '') {
                    return;
                }

                isLoadingNumbers = true;
                setNumbersStatus(reset ? 'Cargando números...' : 'Cargando más números...');

                try {
                    const params = new URLSearchParams({
                        per_page: '240',
                    });

                    if (!reset && nextCursor !== '') {
                        params.set('cursor', nextCursor);
                    }

                    if (searchTerm.trim() !== '') {
                        params.set('search', searchTerm.trim());
                    }

                    const response = await fetch(`${feedUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'No pudimos cargar más números.');
                    }

                    appendNumberButtons(payload.items || [], { reset });
                    nextCursor = payload.next_cursor || '';
                    activeSearch = searchTerm;

                    if ((payload.items || []).length === 0) {
                        setNumbersStatus(searchTerm.trim() !== ''
                            ? 'No encontramos números para esa búsqueda.'
                            : 'No hay más números por cargar.');

                        return;
                    }

                    setNumbersStatus(nextCursor !== ''
                        ? 'Desliza para cargar más números.'
                        : 'Ya viste todos los números de esta consulta.');
                } catch (error) {
                    setNumbersStatus(error instanceof Error ? error.message : 'No pudimos cargar más números.');
                } finally {
                    isLoadingNumbers = false;
                }
            };

            const sync = () => {
                const selectedNumbers = Array.from(selected).sort();
                const count = selectedNumbers.length;
                status.textContent = `${count} seleccionados de ${requiredQuantity}`;
                preview.textContent = count > 0
                    ? `Selección actual: ${selectedNumbers.join(', ')}`
                    : 'Aún no has seleccionado números.';

                const phone = link.dataset.phone || '';
                const confirmUrl = link.dataset.confirmUrl || '';
                const hasConfirm = confirmUrl !== '';
                const isReady = !isSubmitting && (hasConfirm || phone !== '') && count === requiredQuantity;

                link.setAttribute('aria-disabled', isReady ? 'false' : 'true');
                if (isSubmitting) {
                    link.innerHTML = hasConfirm
                        ? '<svg class="cta-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9"/></svg><span class="cta-label">Reservando selección...</span>'
                        : '<svg class="cta-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9"/></svg><span class="cta-label">Preparando WhatsApp...</span>';
                } else {
                    link.innerHTML = defaultCtaHtml;
                }
                link.href = '#';
            };

            grid.addEventListener('click', (event) => {
                const button = event.target.closest('.number-button');

                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                const number = button.dataset.number;
                const buttonStatus = button.dataset.status;

                if (!number || buttonStatus !== 'available') {
                    return;
                }

                if (selected.has(number)) {
                    selected.delete(number);
                    button.classList.remove('is-selected');
                    button.setAttribute('aria-pressed', 'false');
                    sync();

                    return;
                }

                if (selected.size >= requiredQuantity) {
                    return;
                }

                selected.add(number);
                button.classList.add('is-selected');
                button.setAttribute('aria-pressed', 'true');
                sync();
            });

            search?.addEventListener('input', (event) => {
                const term = String(event.target.value || '').trim();

                if (searchTimer) {
                    window.clearTimeout(searchTimer);
                }

                searchTimer = window.setTimeout(() => {
                    nextCursor = '';
                    activeSearch = term;
                    fetchNumbers({ reset: true, searchTerm: term });
                }, 220);
            });

            link.addEventListener('click', async (event) => {
                event.preventDefault();

                if (link.getAttribute('aria-disabled') === 'true') {
                    return;
                }

                const phone = link.dataset.phone || '';
                const intentUrl = link.dataset.intentUrl || '';
                const confirmUrl = link.dataset.confirmUrl || '';
                const hasConfirm = confirmUrl !== '';
                const selectedNumbers = Array.from(selected).sort();

                if (selectedNumbers.length !== requiredQuantity) {
                    return;
                }
                if (!hasConfirm && (phone === '' || intentUrl === '')) {
                    return;
                }

                isSubmitting = true;
                setFeedback('');
                sync();

                try {
                    if (hasConfirm) {
                        const response = await fetch(confirmUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                quantity: requiredQuantity,
                                selected_numbers: selectedNumbers,
                                trace: pickerTrace,
                            }),
                        });

                        const payload = await response.json();

                        if (response.ok && payload?.ok === true) {
                            const nextUrl = typeof payload?.redirect_url === 'string' && payload.redirect_url !== ''
                                ? payload.redirect_url
                                : null;
                            if (nextUrl !== null) {
                                link.innerHTML = hasConfirm
                                    ? '<svg class="cta-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="cta-label">Redirigiendo...</span>'
                                    : defaultCtaHtml;
                                link.setAttribute('aria-disabled', 'true');
                                isSubmitting = false;
                                try {
                                    window.location.assign(nextUrl);
                                } catch (_) {
                                    window.location.href = nextUrl;
                                }
                                return;
                            }
                            setFeedback(
                                payload?.message || '¡Listo! Tu reserva se creó exitosamente. Revisa tu WhatsApp para continuar con el pago.',
                                false
                            );
                            link.innerHTML = defaultCtaHtml;
                            link.setAttribute('aria-disabled', 'true');
                            isSubmitting = false;
                            return;
                        }

                        const fallbackLegacy = Boolean(payload?.fallback_legacy ?? false);
                        const fallbackMessage = payload?.message
                            || 'No pudimos confirmar tu selección. Intenta nuevamente.';

                        if (fallbackLegacy && phone !== '' && intentUrl !== '') {
                            const intentResp = await fetch(intentUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({
                                    quantity: requiredQuantity,
                                    selected_numbers: selectedNumbers,
                                    trace: pickerTrace,
                                }),
                            });
                            const intentPayload = await intentResp.json();
                            if (!intentResp.ok) {
                                throw new Error(intentPayload.message || fallbackMessage);
                            }
                            const whatsappMessage = encodeURIComponent(
                                intentPayload.whatsapp_message || `PICKER ${intentPayload.token}`
                            );
                            window.location.href = `https://wa.me/${phone}?text=${whatsappMessage}`;
                            return;
                        }

                        throw new Error(fallbackMessage);
                    }

                    const response = await fetch(intentUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            quantity: requiredQuantity,
                            selected_numbers: selectedNumbers,
                            trace: pickerTrace,
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'No pudimos preparar tu selección. Intenta nuevamente.');
                    }

                    const whatsappMessage = encodeURIComponent(payload.whatsapp_message || `PICKER ${payload.token}`);
                    window.location.href = `https://wa.me/${phone}?text=${whatsappMessage}`;
                } catch (error) {
                    setFeedback(error instanceof Error ? error.message : 'No pudimos procesar tu selección. Intenta nuevamente.', true);
                } finally {
                    isSubmitting = false;
                    sync();
                }
            });

            if (sentinel instanceof HTMLElement && 'IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    const shouldLoad = entries.some((entry) => entry.isIntersecting);

                    if (shouldLoad && nextCursor !== '' && !isLoadingNumbers) {
                        fetchNumbers({ searchTerm: activeSearch });
                    }
                }, {
                    rootMargin: '240px 0px',
                });

                observer.observe(sentinel);
            }

            setNumbersStatus(nextCursor !== '' ? 'Desliza para cargar más números.' : '');
            sync();

            // Número que llega desde la portada (?pick=0042): se aparta si sigue libre.
            const picks = (new URLSearchParams(window.location.search).get('pick') || '')
                .split(',').map((v) => v.replace(/\D+/g, '')).filter(Boolean).slice(0, requiredQuantity);
            if (picks.length > 0 && feedUrl) {
                (async () => {
                    for (const pick of picks) {
                        try {
                            const response = await fetch(`${feedUrl}?${new URLSearchParams({ search: pick, per_page: '24' })}`, { headers: { Accept: 'application/json' } });
                            const payload = await response.json();
                            const digits = String((payload.items || [])[0]?.number || '').length || pick.length;
                            const target = (payload.items || []).find((item) => item.number === pick.padStart(digits, '0'));
                            if (!target) continue;
                            if (!renderedNumbers.has(target.number)) {
                                grid.prepend(buildNumberButton(target));
                                renderedNumbers.add(target.number);
                            }
                            if (target.selectable && selected.size < requiredQuantity) {
                                selected.add(target.number);
                                const button = grid.querySelector(`[data-number="${target.number}"]`);
                                button?.classList.add('is-selected');
                                button?.setAttribute('aria-pressed', 'true');
                                setFeedback(`Apartamos el ${target.number} en tu selección.`);
                            } else if (!target.selectable) {
                                setFeedback(`El ${target.number} ya no está libre. Elige otro.`, true);
                            }
                        } catch (_) { /* sin conexión: el cliente elige a mano */ }
                    }
                    sync();
                })();
            }
        })();
    </script>
</body>
</html>
