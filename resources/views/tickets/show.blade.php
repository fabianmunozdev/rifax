<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($company?->trade_name ?: config('app.name', 'Rifax')) . ' | Boleto ' . data_get($payload, 'ticket.code') }}</title>
    @php
        $brandPrimary = $company?->primary_color ?: '#0f766e';
        $brandSecondary = $company?->secondary_color ?: '#0f172a';
        $brandAccent = $company?->accent_color ?: '#2563eb';
        $logoUrl = filled($company?->logo_path) ? asset('storage/' . ltrim($company->logo_path, '/')) : null;
        $ticketImageUrl = data_get($payload, 'ticket.image_url');
        $numbers = collect(data_get($payload, 'numbers', []));
        $supportPhoneDigits = preg_replace('/\D+/', '', (string) ($company?->support_phone ?? ''));
        $supportUrl = $supportPhoneDigits !== ''
            ? 'https://wa.me/' . $supportPhoneDigits . '?text=' . rawurlencode('Hola, necesito ayuda con mi boleto ' . data_get($payload, 'ticket.code'))
            : null;
    @endphp
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-alt: #eef4fb;
            --text: #0f172a;
            --muted: #475569;
            --border: #d6e0ea;
            --primary: {{ $brandPrimary }};
            --secondary: {{ $brandSecondary }};
            --accent: {{ $brandAccent }};
            --shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 12%, transparent), transparent 28%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 12%, transparent), transparent 24%),
                var(--bg);
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px 16px 56px;
        }

        .hero {
            display: grid;
            gap: 18px;
            padding: 28px;
            border-radius: 28px;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            color: #fff;
            box-shadow: var(--shadow);
        }

        .hero-head {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .hero-logo {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.12);
            padding: 8px;
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .badge {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(32px, 5vw, 52px);
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .hero p {
            margin: 0;
            max-width: 760px;
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.6;
            font-size: 17px;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            font-weight: 700;
        }

        .layout {
            display: grid;
            gap: 22px;
            margin-top: 24px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
        }

        .ticket-card {
            padding: 18px;
        }

        .ticket-card img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: #fff;
        }

        .summary {
            display: grid;
            gap: 18px;
            padding: 22px;
        }

        .summary-grid {
            display: grid;
            gap: 12px;
        }

        .summary-card {
            padding: 16px;
            border-radius: 18px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
        }

        .summary-card strong,
        .summary-card span {
            display: block;
        }

        .summary-card strong {
            margin-bottom: 6px;
            font-size: 12px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .summary-card span {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.3;
        }

        .numbers {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .number-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 78px;
            padding: 10px 14px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--primary) 10%, white);
            border: 1px solid color-mix(in srgb, var(--primary) 22%, white);
            color: var(--secondary);
            font-weight: 800;
            letter-spacing: 0.03em;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 18px;
            border-radius: 14px;
            font-weight: 800;
            text-decoration: none;
        }

        .button-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #fff;
        }

        .button-secondary {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .note {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        @media (min-width: 960px) {
            .layout {
                grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
                align-items: start;
            }

            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="hero">
            <div class="hero-head">
                @if($logoUrl)
                    <img class="hero-logo" src="{{ $logoUrl }}" alt="Logo {{ $company?->trade_name ?: config('app.name', 'Rifax') }}">
                @endif
                <span class="badge">Boleto validado</span>
            </div>

            <div>
                <h1>{{ data_get($payload, 'ticket.code') }}</h1>
                <p>
                    Este es tu boleto para {{ data_get($payload, 'raffle.title') }}.
                    Aqui puedes revisar el detalle del sorteo, los numeros asignados y una vista clara del boleto generado.
                </p>
            </div>

            <div class="hero-meta">
                <span class="hero-pill">Estado: {{ strtoupper((string) data_get($payload, 'ticket.status', 'valid')) }}</span>
                <span class="hero-pill">Loteria: {{ data_get($payload, 'raffle.lottery_name') ?: 'Pendiente' }}</span>
                <span class="hero-pill">Sorteo: {{ data_get($payload, 'raffle.draw_date') }} {{ data_get($payload, 'raffle.draw_time') }}</span>
            </div>
        </section>

        <section class="layout">
            <article class="panel ticket-card">
                @if($ticketImageUrl)
                    <img src="{{ $ticketImageUrl }}" alt="Boleto {{ data_get($payload, 'ticket.code') }}">
                @else
                    <div class="note">Aun no hay una imagen publica disponible para este boleto.</div>
                @endif
            </article>

            <aside class="panel summary">
                <div class="summary-grid">
                    <div class="summary-card">
                        <strong>Codigo</strong>
                        <span>{{ data_get($payload, 'ticket.code') }}</span>
                    </div>
                    <div class="summary-card">
                        <strong>Version</strong>
                        <span>v{{ data_get($payload, 'ticket.version') }}</span>
                    </div>
                    <div class="summary-card">
                        <strong>Rifa</strong>
                        <span>{{ data_get($payload, 'raffle.title') }}</span>
                    </div>
                    <div class="summary-card">
                        <strong>Sorteo oficial</strong>
                        <span>{{ data_get($payload, 'raffle.lottery_name') }} #{{ data_get($payload, 'raffle.lottery_draw_number') }}</span>
                    </div>
                </div>

                <div>
                    <strong style="display:block; margin-bottom:10px; font-size:13px; text-transform:uppercase; letter-spacing:0.04em; color:var(--muted);">Numeros asignados</strong>
                    <div class="numbers">
                        @forelse($numbers as $number)
                            <span class="number-pill">{{ $number }}</span>
                        @empty
                            <span class="note">No hay numeros asociados a este boleto.</span>
                        @endforelse
                    </div>
                </div>

                <div class="actions">
                    @if($ticketImageUrl)
                        <a class="button button-primary" href="{{ $ticketImageUrl }}" target="_blank" rel="noopener noreferrer">Abrir imagen del boleto</a>
                    @endif
                    @if($supportUrl)
                        <a class="button button-secondary" href="{{ $supportUrl }}" target="_blank" rel="noopener noreferrer">Solicitar ayuda por WhatsApp</a>
                    @endif
                </div>

                <div class="note">
                    Conserva este enlace para consultar tu boleto cuando lo necesites.
                    La plataforma no expone datos personales del comprador en esta vista publica.
                </div>
            </aside>
        </section>
    </main>
</body>
</html>
