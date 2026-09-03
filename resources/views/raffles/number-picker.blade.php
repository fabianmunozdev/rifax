<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $raffle->title }} | Seleccion de numeros</title>
    @php
        $brandPrimary = $company?->primary_color ?: '#0f766e';
        $brandSecondary = $company?->secondary_color ?: '#0f172a';
        $brandAccent = $company?->accent_color ?: '#2563eb';
        $drawAtIso = $raffle->drawAt()?->toIso8601String();
    @endphp
    <style>
        :root {
            color-scheme: dark;
            --bg: #070b12;
            --bg-elevated: #0b1220;
            --card: #111926;
            --card-strong: #0b1220;
            --card-soft: #182233;
            --surface-soft: rgba(255, 255, 255, 0.04);
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --primary: {{ $brandPrimary }};
            --secondary: {{ $brandSecondary }};
            --accent: {{ $brandAccent }};
            --primary-soft: color-mix(in srgb, var(--primary) 18%, rgba(255, 255, 255, 0.05));
            --danger: #dc2626;
            --disabled: #64748b;
            --warning: #d97706;
            --warning-soft: rgba(217, 119, 6, 0.16);
            --paid: #1d4ed8;
            --paid-soft: rgba(29, 78, 216, 0.16);
            --winner: #7c3aed;
            --winner-soft: rgba(124, 58, 237, 0.16);
            --shadow: 0 22px 52px rgba(2, 6, 23, 0.38);
            --whatsapp: #25d366;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 16%, transparent), transparent 34%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 14%, transparent), transparent 28%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0)),
                var(--bg);
            color: var(--text);
        }
        .page {
            max-width: 1320px;
            margin: 0 auto;
            padding: 24px 16px 56px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow);
        }
        .picker-shell {
            display: grid;
            gap: 20px;
            padding: 20px;
        }
        .picker-header {
            display: grid;
            gap: 14px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01)),
                linear-gradient(135deg, rgba(5, 10, 18, 0.98), color-mix(in srgb, var(--secondary) 54%, rgba(5, 10, 18, 0.96)) 55%, color-mix(in srgb, var(--primary) 22%, rgba(5, 10, 18, 0.94)) 100%);
        }
        .picker-header-bar {
            display: grid;
            gap: 12px;
            align-items: center;
        }
        .picker-headline {
            display: flex;
            align-items: center;
            min-width: 0;
        }
        .title {
            margin: 0;
            font-size: clamp(24px, 3vw, 32px);
            line-height: 1.05;
            letter-spacing: -0.025em;
        }
        .picker-copy,
        .empty {
            color: rgba(255, 255, 255, 0.92);
            line-height: 1.5;
            font-size: 12px;
        }
        .picker-copy {
            margin: 0;
        }
        .picker-copy strong {
            color: #fff;
        }
        .picker-meta {
            display: grid;
            gap: 3px;
        }
        .picker-countdown-wrap {
            display: grid;
            gap: 8px;
            min-width: 0;
        }
        .picker-countdown-title {
            color: rgba(255, 255, 255, 0.64);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: left;
        }
        .picker-countdown {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }
        .picker-count-card {
            padding: 8px 6px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
        }
        .picker-count-value {
            display: block;
            font-size: 16px;
            font-weight: 800;
            line-height: 1;
        }
        .picker-count-label {
            display: block;
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }
        .summary--compact {
            margin: 0;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.62);
        }
        .search-shell {
            position: relative;
        }
        .search-shell input {
            width: 100%;
            min-height: 54px;
            padding: 14px 18px;
            border: 1px solid color-mix(in srgb, var(--primary) 34%, rgba(255, 255, 255, 0.12));
            border-radius: 16px;
            font-size: 15px;
            font-weight: 600;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02));
            color: var(--text);
            outline: none;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }
        .search-shell input::placeholder {
            color: var(--muted);
        }
        .search-shell input:focus {
            border-color: color-mix(in srgb, var(--primary) 62%, white);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 14%, transparent);
        }
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 0;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 12px;
        }
        .legend li {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .legend-swatch {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--card-strong);
        }
        .legend-swatch--available {
            background: color-mix(in srgb, var(--primary) 20%, rgba(255, 255, 255, 0.04));
            border-color: color-mix(in srgb, var(--primary) 72%, white);
        }
        .legend-swatch--reserved {
            background: var(--warning-soft);
            border-color: var(--warning);
        }
        .legend-swatch--paid {
            background: var(--paid-soft);
            border-color: var(--paid);
        }
        .legend-swatch--winner {
            background: var(--winner-soft);
            border-color: var(--winner);
        }
        .numbers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
            gap: 8px;
        }
        .numbers-feed {
            display: grid;
            gap: 14px;
        }
        .number-button {
            width: 100%;
            border: 1px solid var(--border);
            background: var(--card-strong);
            border-radius: 12px;
            padding: 10px 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            color: var(--text);
            transition: transform 120ms ease, border-color 120ms ease, background 120ms ease, box-shadow 120ms ease;
        }
        .number-button[data-status="available"] {
            background: var(--card-strong);
        }
        .number-button:hover {
            border-color: color-mix(in srgb, var(--primary) 68%, white);
            transform: translateY(-1px);
        }
        .number-button.is-selected {
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 86%, white), color-mix(in srgb, var(--accent) 68%, black));
            border-color: transparent;
            color: #fff;
            box-shadow: 0 14px 28px color-mix(in srgb, var(--primary) 18%, transparent);
        }
        .number-button[data-status="reserved"] {
            background: var(--warning-soft);
            border-color: var(--warning);
            color: #facc15;
            cursor: not-allowed;
        }
        .number-button[data-status="paid"] {
            background: var(--paid-soft);
            border-color: var(--paid);
            color: #bfdbfe;
            cursor: not-allowed;
        }
        .number-button[data-status="winner"] {
            background: var(--winner-soft);
            border-color: var(--winner);
            color: #ddd6fe;
            cursor: not-allowed;
        }
        .number-button[data-status="reserved"]:hover,
        .number-button[data-status="paid"]:hover,
        .number-button[data-status="winner"]:hover {
            transform: none;
        }
        .number-label {
            display: block;
        }
        .number-state {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .actions {
            position: sticky;
            bottom: 12px;
            margin-top: 8px;
            display: grid;
            gap: 8px;
            padding: 12px;
            align-items: start;
            border: 1px solid color-mix(in srgb, var(--primary) 42%, rgba(255, 255, 255, 0.1));
            border-radius: 18px;
            background:
                linear-gradient(135deg,
                    color-mix(in srgb, var(--primary) 72%, #04101d) 0%,
                    color-mix(in srgb, var(--secondary) 88%, #020617) 100%);
            box-shadow:
                0 18px 36px rgba(2, 6, 23, 0.34),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
            z-index: 20;
        }
        .actions-row,
        .actions-foot {
            display: grid;
            gap: 8px;
            min-width: 0;
        }
        .actions-foot {
            gap: 4px;
        }
        .selection-preview {
            min-height: 46px;
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 14px;
            background: rgba(3, 10, 24, 0.46);
            color: rgba(255, 255, 255, 0.94);
            display: flex;
            align-items: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .feedback {
            min-height: 16px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 12px;
            padding: 0 2px;
        }
        .feedback.is-error {
            color: var(--danger);
            font-weight: 600;
        }
        .cta-help {
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
            line-height: 1.4;
            padding: 0 2px;
        }
        .cta {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            border: 1px solid rgba(37, 211, 102, 0.28);
            border-radius: 14px;
            padding: 10px 14px;
            background: var(--whatsapp);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 14px 28px rgba(37, 211, 102, 0.18);
            text-align: center;
            white-space: nowrap;
        }
        .cta[aria-disabled="true"] {
            pointer-events: none;
            background: var(--disabled);
            box-shadow: none;
            border-color: transparent;
        }
        .cta-icon {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
        }
        .warning {
            color: var(--danger);
            font-weight: 600;
        }
        .section-stack {
            display: grid;
            gap: 18px;
        }
        .numbers-status {
            min-height: 18px;
            color: var(--muted);
            font-size: 12px;
        }
        @media (min-width: 768px) {
            .picker-header-bar { grid-template-columns: minmax(0, 1fr) 260px; align-items: start; }
            .actions-row { grid-template-columns: minmax(0, 1fr) 320px; align-items: center; }
        }
        @media (max-width: 640px) {
            .page {
                padding-top: 18px;
            }
            .picker-shell {
                padding: 16px;
            }
            .picker-header {
                padding: 16px;
            }
            .numbers-grid {
                grid-template-columns: repeat(auto-fill, minmax(78px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card picker-shell">
            <div class="picker-header">
                <div class="picker-header-bar">
                    <div class="picker-headline">
                        <h1 class="title">{{ $raffle->title }}</h1>
                    </div>

                    <div class="picker-countdown-wrap">
                        <span class="picker-countdown-title">Cuenta regresiva</span>
                        <div class="picker-countdown" @if($drawAtIso) data-draw-at="{{ $drawAtIso }}" @endif>
                            <div class="picker-count-card">
                                <span class="picker-count-value" data-unit="days">--</span>
                                <span class="picker-count-label">Dias</span>
                            </div>
                            <div class="picker-count-card">
                                <span class="picker-count-value" data-unit="hours">--</span>
                                <span class="picker-count-label">Horas</span>
                            </div>
                            <div class="picker-count-card">
                                <span class="picker-count-value" data-unit="minutes">--</span>
                                <span class="picker-count-label">Min</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="picker-meta">
                <p class="picker-copy">
                    Elige exactamente <strong>{{ $quantity }}</strong> número(s) disponibles y continua la compra por WhatsApp con una selección temporal que el bot reconocerá automáticamente.
                </p>

                <div class="summary summary--compact">
                    @if ($raffle->draw_date || $raffle->draw_time)
                        <span>Sorteo {{ $raffle->draw_date?->format('Y-m-d') ?: 'Pendiente' }} {{ $raffle->draw_time ?: '' }}</span>
                    @else
                        <span>Fecha de sorteo pendiente de configuración.</span>
                    @endif
                    <span>Catálogo total: {{ number_format((int) $catalogCount) }}</span>
                    <span>Disponibles ahora: {{ number_format((int) $availableCount) }}</span>
                    <span id="selection-status">0 seleccionados de {{ $quantity }}</span>
                </div>
            </div>

            @if (! $botPhoneDigits)
                <p class="warning">No hay un número de WhatsApp del bot configurado en administración. Configura `whatsapp_bot_phone` para habilitar el flujo web hacia WhatsApp.</p>
            @endif

            @if ($numbers === [])
                <p class="empty">Esta rifa aún no tiene números cargados.</p>
            @else
                <div class="section-stack">
                    <div class="search-shell">
                        <input type="search" id="number-search" placeholder="Buscar número..." autocomplete="off">
                    </div>

                    <ul class="legend" aria-label="Estados de números">
                        <li><span class="legend-swatch legend-swatch--available"></span>Disponible</li>
                        <li><span class="legend-swatch legend-swatch--reserved"></span>Reservado</li>
                        <li><span class="legend-swatch legend-swatch--paid"></span>Pagado</li>
                        <li><span class="legend-swatch legend-swatch--winner"></span>Ganador</li>
                    </ul>

                    <div class="numbers-feed">
                    <div
                        class="numbers-grid"
                        id="numbers-grid"
                        data-quantity="{{ $quantity }}"
                        data-feed-url="{{ $numbersFeedUrl }}"
                        data-next-cursor="{{ $numbersNextCursor ?? '' }}"
                    >
                        @foreach ($numbers as $number)
                            @php
                                $isSelectable = ($number['selectable'] ?? false) === true;
                            @endphp
                            <button
                                type="button"
                                class="number-button"
                                data-number="{{ $number['number'] }}"
                                data-status="{{ $number['status'] }}"
                                aria-pressed="false"
                                @disabled(! $isSelectable)
                            >
                                <span class="number-label">{{ $number['number'] }}</span>
                                <span class="number-state">{{ $number['status_label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                        <div class="numbers-status" id="numbers-status" aria-live="polite"></div>
                        <div id="numbers-sentinel" aria-hidden="true"></div>
                    </div>

                    <div class="actions">
                        <div class="actions-row">
                            <div class="selection-preview" id="selection-preview">
                                Aún no has seleccionado números.
                            </div>
                            <a
                                id="send-selection-link"
                                class="cta"
                                href="#"
                                aria-disabled="true"
                                data-phone="{{ $botPhoneDigits }}"
                                data-intent-url="{{ $pickerIntentUrl }}"
                                data-picker-trace='@json($pickerTrace)'
                            >
                                <svg class="cta-icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                                    <path d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.57 2 2.12 6.42 2.12 11.88c0 1.75.46 3.46 1.34 4.97L2 22l5.3-1.39a9.9 9.9 0 0 0 4.73 1.21h.01c5.46 0 9.88-4.42 9.88-9.88a9.8 9.8 0 0 0-2.87-7.03Zm-7.02 15.24h-.01a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.15.83.84-3.07-.2-.32a8.16 8.16 0 0 1-1.25-4.38c0-4.5 3.67-8.16 8.19-8.16a8.1 8.1 0 0 1 5.78 2.4 8.12 8.12 0 0 1 2.38 5.78c0 4.51-3.67 8.17-8.1 8.24Zm4.48-6.13c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.54.12-.16.24-.62.78-.76.94-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.92-1.19-.71-.63-1.19-1.4-1.33-1.64-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.31-.74-1.8-.2-.47-.4-.4-.54-.4h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.68 2.56 4.07 3.59.57.25 1.01.4 1.36.52.57.18 1.08.15 1.48.09.45-.07 1.42-.58 1.62-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28Z"/>
                                </svg>
                                <span>Continuar compra por WhatsApp</span>
                            </a>
                        </div>
                        <div class="actions-foot">
                            <div class="cta-help">
                                Se abrira un mensaje listo para enviar. No necesitas editarlo.
                            </div>
                            <div class="feedback" id="selection-feedback" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const countdowns = Array.from(document.querySelectorAll('.picker-countdown[data-draw-at]'));

            const renderCountdown = (container) => {
                const drawAt = container.dataset.drawAt;

                if (!drawAt) {
                    return;
                }

                const diff = new Date(drawAt).getTime() - Date.now();
                const safeDiff = Math.max(diff, 0);
                const days = Math.floor(safeDiff / 86400000);
                const hours = Math.floor((safeDiff % 86400000) / 3600000);
                const minutes = Math.floor((safeDiff % 3600000) / 60000);

                container.querySelector('[data-unit="days"]').textContent = String(days).padStart(2, '0');
                container.querySelector('[data-unit="hours"]').textContent = String(hours).padStart(2, '0');
                container.querySelector('[data-unit="minutes"]').textContent = String(minutes).padStart(2, '0');
            };

            countdowns.forEach(renderCountdown);

            if (countdowns.length > 0) {
                window.setInterval(() => countdowns.forEach(renderCountdown), 1000);
            }
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
                const isReady = !isSubmitting && phone !== '' && count === requiredQuantity;

                link.setAttribute('aria-disabled', isReady ? 'false' : 'true');
                link.innerHTML = isSubmitting ? 'Preparando WhatsApp...' : defaultCtaHtml;
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
                const selectedNumbers = Array.from(selected).sort();

                if (phone === '' || intentUrl === '' || selectedNumbers.length !== requiredQuantity) {
                    return;
                }

                isSubmitting = true;
                setFeedback('');
                sync();

                try {
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
                    setFeedback(error instanceof Error ? error.message : 'No pudimos preparar tu selección. Intenta nuevamente.', true);
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
        })();
    </script>
</body>
</html>
