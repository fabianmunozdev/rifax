<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reserva confirmada | {{ $raffle->title }}</title>
    @php
        $company = $company ?? null;
        $brandPrimary = $company?->primary_color ?: '#0f766e';
        $brandSecondary = $company?->secondary_color ?: '#0f172a';
        $brandAccent = $company?->accent_color ?: '#2563eb';
    @endphp
    <style>
        :root {
            color-scheme: dark;
            --bg: #070b12;
            --card: #111926;
            --border: rgba(255, 255, 255, 0.08);
            --text: #f8fafc;
            --muted: #94a3b8;
            --primary: {{ $brandPrimary }};
            --secondary: {{ $brandSecondary }};
            --accent: {{ $brandAccent }};
            --success: #10b981;
            --whatsapp: #25d366;
            --shadow: 0 22px 52px rgba(2, 6, 23, 0.38);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 16%, transparent), transparent 34%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 14%, transparent), transparent 28%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .page {
            max-width: 780px;
            margin: 0 auto;
            padding: 32px 16px 56px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 28px 24px;
        }
        .hero {
            display: grid;
            gap: 14px;
            justify-items: start;
            margin-bottom: 22px;
        }
        .check {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.18);
            border: 1px solid rgba(16, 185, 129, 0.45);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #34d399;
        }
        .title {
            margin: 0;
            font-size: clamp(24px, 3.4vw, 32px);
            line-height: 1.05;
            letter-spacing: -0.02em;
        }
        .subtitle {
            margin: 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 15px;
            line-height: 1.5;
            max-width: 60ch;
        }
        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }
        .section {
            border-top: 1px solid var(--border);
            padding-top: 20px;
            margin-top: 20px;
            display: grid;
            gap: 14px;
        }
        .section h2 {
            margin: 0;
            font-size: 14px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }
        .numbers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(78px, 1fr));
            gap: 10px;
        }
        .number-pill {
            display: grid;
            place-items: center;
            gap: 4px;
            padding: 14px 10px;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.14);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #d1fae5;
        }
        .number-pill .num {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .number-pill .lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(209, 250, 229, 0.8);
            font-weight: 700;
        }
        .kv {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px 16px;
            font-size: 14px;
        }
        .kv .k { color: var(--muted); }
        .kv .v { color: rgba(255, 255, 255, 0.92); font-weight: 600; text-align: right; }
        .cta {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            width: 100%;
            border: 1px solid rgba(37, 211, 102, 0.28);
            border-radius: 14px;
            padding: 12px 16px;
            background: var(--whatsapp);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 14px 28px rgba(37, 211, 102, 0.18);
            text-align: center;
            white-space: nowrap;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(37, 211, 102, 0.24);
        }
        .cta:active {
            transform: translateY(0);
        }
        .cta--hero {
            margin-top: 2px;
            padding: 14px 18px;
            font-size: 16px;
        }
        .cta-icon { width: 18px; height: 18px; flex: 0 0 18px; }
        .countdown {
            margin-top: 4px;
            padding: 14px 16px;
            border-radius: 16px;
            width: 100%;
            display: grid;
            gap: 8px;
            background: linear-gradient(180deg, rgba(37,211,102,0.12), rgba(37,211,102,0.04));
            border: 1px solid rgba(37, 211, 102, 0.32);
        }
        .countdown--expired {
            background: linear-gradient(180deg, rgba(239,68,68,0.12), rgba(239,68,68,0.04));
            border-color: rgba(239,68,68,0.34);
        }
        .countdown__label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: rgba(255,255,255,0.86);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .countdown__abs {
            color: rgba(255,255,255,0.74);
            text-transform: none;
            letter-spacing: 0;
            font-weight: 600;
            font-size: 12px;
        }
        .countdown__clock {
            display: flex;
            align-items: baseline;
            gap: 4px;
            font-variant-numeric: tabular-nums;
        }
        .countdown__num {
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border);
            border-radius: 12px;
            min-width: 56px;
            padding: 8px 10px;
            font-size: 26px;
            line-height: 1;
            text-align: center;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: #fff;
        }
        .countdown__sep { color: rgba(255,255,255,0.7); font-weight: 800; font-size: 24px; padding: 0 2px; }
        .countdown__status {
            font-size: 13px;
            color: rgba(255,255,255,0.78);
            line-height: 1.4;
        }
        .countdown--expired .countdown__num { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.28); color: #fecaca; }
        .cta.is-disabled { pointer-events: none; opacity: 0.6; background: #475569; border-color: rgba(148,163,184,0.3); box-shadow: none; }
        .footnote {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }
        .footnote strong { color: rgba(255, 255, 255, 0.84); }
        @media (max-width: 640px) {
            .page { padding-top: 18px; }
            .card { padding: 22px 16px; border-radius: 18px; }
            .countdown__num { font-size: 22px; min-width: 48px; padding: 7px 8px; }
            .countdown__sep { font-size: 20px; }
        }
    </style>
</head>
<body>
    @php
        $showCountdown = (!($requiresOnboarding ?? false))
            && isset($reservedUntilIso)
            && is_string($reservedUntilIso)
            && $reservedUntilIso !== '';
    @endphp
    <div class="page">
        <div class="card">
            <div class="hero">
                <span class="check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span class="meta-chip">
                    <span aria-hidden="true">🎟️</span>
                    <span>{{ $raffle->title }}</span>
                </span>
                <h1 class="title">Tu reserva está confirmada</h1>
                <p class="subtitle">
                    @if ($requiresOnboarding)
                        Revisa el mensaje que te enviamos por WhatsApp: allí te pedimos unos datos rápidos para finalizar tu registro y completar la reserva.
                    @else
                        Revisa la confirmación que te enviamos a WhatsApp. Continúa allí con el proceso de pago.
                    @endif
                </p>
                @if ($showCountdown)
                    <div class="countdown" id="countdown" role="timer" aria-live="polite">
                        <div class="countdown__label">
                            <span>Tiempo restante para completar el pago</span>
                            @if (isset($reservedUntilAbsolute) && is_string($reservedUntilAbsolute) && $reservedUntilAbsolute !== '')
                                <span class="countdown__abs">Expira a las {{ $reservedUntilAbsolute }}</span>
                            @endif
                        </div>
                        <div class="countdown__clock" aria-hidden="true">
                            <div class="countdown__num" id="cd-mm">00</div>
                            <span class="countdown__sep">:</span>
                            <div class="countdown__num" id="cd-ss">00</div>
                        </div>
                        <div class="countdown__status" id="cd-status">
                            Si completas el pago y envías el comprobante antes de que termine el tiempo, tus números quedan reservados para ti.
                        </div>
                    </div>
                @endif
                @if (isset($whatsappOpenUrl) && is_string($whatsappOpenUrl) && $whatsappOpenUrl !== '')
                    <a class="cta cta--hero cta-primary" href="{{ $whatsappOpenUrl }}" target="_blank" rel="noopener noreferrer">
                        <svg class="cta-icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                            <path d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.57 2 2.12 6.42 2.12 11.88c0 1.75.46 3.46 1.34 4.97L2 22l5.3-1.39a9.9 9.9 0 0 0 4.73 1.21h.01c5.46 0 9.88-4.42 9.88-9.88a9.8 9.8 0 0 0-2.87-7.03ZM12 20.15a8.27 8.27 0 0 1-4.22-1.16l-.3-.18-3.16.83.84-3.08-.2-.32a8.2 8.2 0 0 1-1.26-4.36c0-4.55 3.71-8.25 8.26-8.25 2.2 0 4.27.87 5.83 2.42a8.22 8.22 0 0 1 2.4 5.85c0 4.55-3.7 8.26-8.19 8.27Z"/>
                        </svg>
                        <span>Abrir WhatsApp para continuar</span>
                    </a>
                @endif
            </div>

            <div class="section">
                <h2>Números reservados</h2>
                <div class="numbers-grid">
                    @foreach ($reservedNumbers as $num)
                        <div class="number-pill">
                            <span class="num">{{ $num }}</span>
                            <span class="lbl">Reservado</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="section">
                <h2>Detalle de la reserva</h2>
                <div class="kv">
                    <div class="k">Cantidad de números</div>
                    <div class="v">{{ count($reservedNumbers) }}</div>
                    @if (isset($unitPrice))
                        <div class="k">Valor por número</div>
                        <div class="v">${{ number_format((float) $unitPrice, 0, ',', '.') }}</div>
                    @endif
                    @if (isset($totalAmount))
                        <div class="k">Total a pagar</div>
                        <div class="v">${{ number_format((float) $totalAmount, 0, ',', '.') }}</div>
                    @endif
                    @if (isset($reservedUntilText) && is_string($reservedUntilText) && $reservedUntilText !== '' && !$showCountdown)
                        <div class="k">Expira la reserva</div>
                        <div class="v">{{ $reservedUntilText }}</div>
                    @endif
                </div>
            </div>

            @if (isset($whatsappOpenUrl) && is_string($whatsappOpenUrl) && $whatsappOpenUrl !== '')
                <div style="padding-top: 2px; margin-top: -2px;">
                    <a class="cta cta-secondary" href="{{ $whatsappOpenUrl }}" target="_blank" rel="noopener noreferrer">
                        <svg class="cta-icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                            <path d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.57 2 2.12 6.42 2.12 11.88c0 1.75.46 3.46 1.34 4.97L2 22l5.3-1.39a9.9 9.9 0 0 0 4.73 1.21h.01c5.46 0 9.88-4.42 9.88-9.88a9.8 9.8 0 0 0-2.87-7.03ZM12 20.15a8.27 8.27 0 0 1-4.22-1.16l-.3-.18-3.16.83.84-3.08-.2-.32a8.2 8.2 0 0 1-1.26-4.36c0-4.55 3.71-8.25 8.26-8.25 2.2 0 4.27.87 5.83 2.42a8.22 8.22 0 0 1 2.4 5.85c0 4.55-3.7 8.26-8.19 8.27Z"/>
                        </svg>
                        <span>Abrir WhatsApp para continuar</span>
                    </a>
                </div>
            @endif

            <div class="footnote">
                <strong>¿No recibiste el mensaje?</strong> Asegúrate de estar registrado con el número de WhatsApp con el que ingresaste.
                Si tienes dudas, escribe <strong>HOLA</strong> al bot y con gusto te ayudamos.
                @if (isset($referenceLabel) && is_string($referenceLabel) && $referenceLabel !== '')
                    <br>
                    <span>Referencia de tu compra: <strong>{{ $referenceLabel }}</strong></span>
                @endif
            </div>
        </div>
    </div>

    @if ($showCountdown)
        <script>
            (function () {
                const targetIso = @json($reservedUntilIso);
                const mmEl = document.getElementById('cd-mm');
                const ssEl = document.getElementById('cd-ss');
                const statusEl = document.getElementById('cd-status');
                const wrapEl = document.getElementById('countdown');
                const ctas = Array.from(document.querySelectorAll('a.cta'));

                function pad(n) { return n < 10 ? '0' + n : '' + n; }

                function render() {
                    const now = Date.now();
                    const target = new Date(targetIso).getTime();
                    let diff = Math.max(0, Math.floor((target - now) / 1000));
                    const expired = diff <= 0;

                    const mm = Math.floor(diff / 60);
                    const ss = diff - mm * 60;
                    if (mmEl) mmEl.textContent = pad(mm);
                    if (ssEl) ssEl.textContent = pad(ss);

                    if (expired) {
                        if (wrapEl && !wrapEl.classList.contains('countdown--expired')) {
                            wrapEl.classList.add('countdown--expired');
                        }
                        if (statusEl) {
                            statusEl.innerHTML = '<strong>Tu reserva ha expirado.</strong> Los números fueron liberados y pueden volver a ser elegidos por otros usuarios. Si todavía quieres participar, genera una nueva selección en el chat del bot.';
                        }
                        ctas.forEach(function (a) { a.classList.add('is-disabled'); a.setAttribute('aria-disabled', 'true'); });
                        return true;
                    }
                    return false;
                }

                if (mmEl && ssEl) {
                    render();
                    const id = window.setInterval(function () {
                        const done = render();
                        if (done) window.clearInterval(id);
                    }, 1000);
                }
            })();
        </script>
    @endif
</body>
</html>
