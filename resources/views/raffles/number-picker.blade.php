<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $raffle->title }} | Seleccion de numeros</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --border: #dbe3ee;
            --primary: #0f766e;
            --primary-soft: #ccfbf1;
            --danger: #dc2626;
            --disabled: #94a3b8;
            --warning: #d97706;
            --warning-soft: #fef3c7;
            --paid: #1d4ed8;
            --paid-soft: #dbeafe;
            --winner: #7c3aed;
            --winner-soft: #ede9fe;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
            padding: 20px;
        }
        .hero {
            display: grid;
            gap: 16px;
            margin-bottom: 20px;
        }
        .title {
            margin: 0;
            font-size: 28px;
            line-height: 1.1;
        }
        .meta, .hint, .empty {
            color: var(--muted);
            line-height: 1.5;
        }
        .meta strong {
            color: var(--text);
        }
        .toolbar {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }
        .toolbar input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 16px;
        }
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            color: var(--muted);
            font-size: 14px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 600;
        }
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 0 20px;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 14px;
        }
        .legend li {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .legend-swatch {
            width: 14px;
            height: 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
        }
        .legend-swatch--available {
            background: var(--primary-soft);
            border-color: var(--primary);
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
            grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
            gap: 10px;
        }
        .number-button {
            width: 100%;
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 12px;
            padding: 12px 8px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: 120ms ease;
        }
        .number-button[data-status="available"] {
            background: #fff;
        }
        .number-button:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .number-button.is-selected {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .number-button[data-status="reserved"] {
            background: var(--warning-soft);
            border-color: var(--warning);
            color: #92400e;
            cursor: not-allowed;
        }
        .number-button[data-status="paid"] {
            background: var(--paid-soft);
            border-color: var(--paid);
            color: var(--paid);
            cursor: not-allowed;
        }
        .number-button[data-status="winner"] {
            background: var(--winner-soft);
            border-color: var(--winner);
            color: var(--winner);
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
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .actions {
            position: sticky;
            bottom: 12px;
            margin-top: 20px;
            display: grid;
            gap: 12px;
            padding: 14px;
            border: 1px solid rgba(219, 227, 238, 0.9);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
            z-index: 20;
        }
        .actions-copy {
            display: grid;
            gap: 8px;
            min-width: 0;
            align-content: center;
        }
        .selection-preview {
            min-height: 46px;
            padding: 12px 14px;
            border: 1px dashed var(--border);
            border-radius: 14px;
            background: #fff;
            color: var(--muted);
            display: flex;
            align-items: center;
        }
        .feedback {
            min-height: 22px;
            color: var(--muted);
            font-size: 14px;
        }
        .feedback.is-error {
            color: var(--danger);
            font-weight: 600;
        }
        .cta-help {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
            padding: 0 2px;
        }
        .cta {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 14px 16px;
            background: var(--primary);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            min-height: 100%;
            box-shadow: 0 14px 28px rgba(15, 118, 110, 0.18);
            text-align: center;
        }
        .cta[aria-disabled="true"] {
            pointer-events: none;
            background: var(--disabled);
            box-shadow: none;
        }
        .warning {
            color: var(--danger);
            font-weight: 600;
        }
        @media (min-width: 768px) {
            .hero { grid-template-columns: 1.4fr 1fr; }
            .toolbar { grid-template-columns: 1fr auto; align-items: center; }
            .actions { grid-template-columns: minmax(0, 1fr) 320px; align-items: stretch; }
            .actions-copy { min-height: 92px; }
            .cta { min-height: 92px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="hero">
                <div>
                    <p class="badge">Seleccion visual</p>
                    <h1 class="title">{{ $raffle->title }}</h1>
                    <p class="meta">
                        <strong>Valor por numero:</strong> {{ $raffle->price_per_number }}<br>
                        <strong>Sorteo:</strong> {{ $raffle->lottery_name }} #{{ $raffle->lottery_draw_number }}<br>
                        <strong>Fecha:</strong> {{ $raffle->draw_date?->format('Y-m-d') }} {{ $raffle->draw_time }}
                    </p>
                </div>
                <div>
                    <p class="hint">
                        Selecciona exactamente <strong>{{ $quantity }}</strong> numero(s) disponibles y luego toca el boton para continuar la compra por WhatsApp.
                        Antes de abrir el chat, guardaremos esta seleccion con un codigo temporal para que el bot la reconozca correctamente.
                    </p>
                    <p class="hint">
                        Esta rifa usa <strong>{{ $digits }}</strong> cifra(s) por numero.
                    </p>
                    <p class="hint">
                        La disponibilidad final se confirma al continuar por WhatsApp.
                    </p>
                    @if (! $supportPhoneDigits)
                        <p class="warning">No hay un telefono de soporte/WhatsApp configurado en administracion. Configura `support_phone` para habilitar el envio directo.</p>
                    @endif
                </div>
            </div>

            @if ($numbers === [])
                <p class="empty">Esta rifa aun no tiene numeros cargados.</p>
            @else
                <div class="toolbar">
                    <input type="search" id="number-search" placeholder="Buscar numero..." autocomplete="off">
                    <div class="summary">
                        <span>{{ count($numbers) }} numero(s) en catalogo</span>
                        <span>{{ $availableCount }} disponible(s)</span>
                        <span id="selection-status">0 seleccionados de {{ $quantity }}</span>
                    </div>
                </div>

                <ul class="legend" aria-label="Estados de numeros">
                    <li><span class="legend-swatch legend-swatch--available"></span>Disponible</li>
                    <li><span class="legend-swatch legend-swatch--reserved"></span>Reservado</li>
                    <li><span class="legend-swatch legend-swatch--paid"></span>Pagado</li>
                    <li><span class="legend-swatch legend-swatch--winner"></span>Ganador</li>
                </ul>

                <div class="numbers-grid" id="numbers-grid" data-quantity="{{ $quantity }}">
                    @foreach ($numbers as $number)
                        @php
                            $isSelectable = $number->status === 'available';
                            $statusLabel = match ($number->status) {
                                'reserved' => 'Reservado',
                                'paid' => 'Pagado',
                                'winner' => 'Ganador',
                                default => 'Disponible',
                            };
                        @endphp
                        <button
                            type="button"
                            class="number-button"
                            data-number="{{ $number->number }}"
                            data-status="{{ $number->status }}"
                            aria-pressed="false"
                            @disabled(! $isSelectable)
                        >
                            <span class="number-label">{{ $number->number }}</span>
                            <span class="number-state">{{ $statusLabel }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="actions">
                    <div class="actions-copy">
                        <div class="selection-preview" id="selection-preview">
                            Aun no has seleccionado numeros.
                        </div>
                        <div class="cta-help">
                            Se abrira un mensaje listo para enviar. No necesitas editarlo.
                        </div>
                        <div class="feedback" id="selection-feedback" aria-live="polite"></div>
                    </div>
                    <a
                        id="send-selection-link"
                        class="cta"
                        href="#"
                        aria-disabled="true"
                        data-phone="{{ $supportPhoneDigits }}"
                        data-intent-url="{{ $pickerIntentUrl }}"
                        data-picker-trace='@json($pickerTrace)'
                    >
                        Continuar compra por WhatsApp
                    </a>
                </div>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const grid = document.getElementById('numbers-grid');
            if (!grid) return;

            const requiredQuantity = Number(grid.dataset.quantity || '1');
            const buttons = Array.from(document.querySelectorAll('.number-button'));
            const search = document.getElementById('number-search');
            const status = document.getElementById('selection-status');
            const preview = document.getElementById('selection-preview');
            const feedback = document.getElementById('selection-feedback');
            const link = document.getElementById('send-selection-link');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const pickerTrace = JSON.parse(link?.dataset.pickerTrace || '{}');
            const selected = new Set();
            let isSubmitting = false;

            const setFeedback = (message = '', isError = false) => {
                feedback.textContent = message;
                feedback.classList.toggle('is-error', Boolean(message) && isError);
            };

            const sync = () => {
                const selectedNumbers = Array.from(selected).sort();
                const count = selectedNumbers.length;
                status.textContent = `${count} seleccionados de ${requiredQuantity}`;
                preview.textContent = count > 0
                    ? `Seleccion actual: ${selectedNumbers.join(', ')}`
                    : 'Aun no has seleccionado numeros.';

                const phone = link.dataset.phone || '';
                const isReady = !isSubmitting && phone !== '' && count === requiredQuantity;

                link.setAttribute('aria-disabled', isReady ? 'false' : 'true');
                link.textContent = isSubmitting ? 'Preparando WhatsApp...' : 'Continuar compra por WhatsApp';
                link.href = '#';
            };

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const number = button.dataset.number;
                    const status = button.dataset.status;

                    if (!number || status !== 'available') return;

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
            });

            search?.addEventListener('input', (event) => {
                const term = String(event.target.value || '').trim();

                buttons.forEach((button) => {
                    const number = button.dataset.number || '';
                    button.style.display = term === '' || number.includes(term) ? '' : 'none';
                });
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
                        throw new Error(payload.message || 'No pudimos preparar tu seleccion. Intenta nuevamente.');
                    }

                    const whatsappMessage = encodeURIComponent(payload.whatsapp_message || `PICKER ${payload.token}`);
                    window.location.href = `https://wa.me/${phone}?text=${whatsappMessage}`;
                } catch (error) {
                    setFeedback(error instanceof Error ? error.message : 'No pudimos preparar tu seleccion. Intenta nuevamente.', true);
                } finally {
                    isSubmitting = false;
                    sync();
                }
            });

            sync();
        })();
    </script>
</body>
</html>
