<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($company?->trade_name ?: config('app.name', 'Rifax')) . ' | Rifas activas' }}</title>
    @php
        $brandPrimary = $company?->primary_color ?: '#0f766e';
        $brandSecondary = $company?->secondary_color ?: '#0f172a';
        $brandAccent = $company?->accent_color ?: '#2563eb';
        $brandLogoUrl = filled($company?->logo_path) ? asset('storage/' . ltrim($company->logo_path, '/')) : null;
        $featuredCoverUrl = filled($featuredRaffle?->cover_image_path) ? asset('storage/' . ltrim($featuredRaffle->cover_image_path, '/')) : null;
        $catalogRaffles = $otherRaffles->isNotEmpty() ? $otherRaffles : $raffles;
        $landingTracking = array_filter([
            'utm_source' => request()->query('utm_source'),
            'utm_medium' => request()->query('utm_medium'),
            'utm_campaign' => request()->query('utm_campaign'),
            'utm_content' => request()->query('utm_content'),
            'utm_term' => request()->query('utm_term'),
        ], fn ($value) => filled($value));
        $featuredPickerUrl = $featuredRaffle
            ? route('raffles.number-picker', array_merge([
                'raffle' => $featuredRaffle->slug,
                'quantity' => max(1, (int) $featuredRaffle->min_numbers_per_purchase),
                'source' => 'landing_featured',
            ], $landingTracking))
            : '#raffles';
        $stickyPrimaryLabel = $featuredRaffle ? 'Elegir numeros' : 'Ver rifas';
        $stickyWhatsappText = $featuredRaffle
            ? 'Quiero comprar en ' . $featuredRaffle->title
            : 'MENU';
        $stickyWhatsappUrl = $supportPhoneDigits
            ? 'https://wa.me/' . $supportPhoneDigits . '?text=' . rawurlencode($stickyWhatsappText)
            : null;
    @endphp
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f8fb;
            --surface: #ffffff;
            --surface-alt: #eef3f8;
            --text: #0f172a;
            --muted: #475569;
            --border: #d8e1eb;
            --primary: {{ $brandPrimary }};
            --secondary: {{ $brandSecondary }};
            --accent: {{ $brandAccent }};
            --primary-soft: color-mix(in srgb, var(--primary) 16%, white);
            --accent-soft: color-mix(in srgb, var(--accent) 12%, white);
            --shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            --whatsapp: #25d366;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 12%, transparent), transparent 30%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 12%, transparent), transparent 24%),
                var(--bg);
            color: var(--text);
            padding-bottom: 108px;
        }

        a { color: inherit; text-decoration: none; }

        .page {
            max-width: 1240px;
            margin: 0 auto;
            padding: 28px 16px 56px;
        }

        .hero {
            display: grid;
            gap: 22px;
            padding: 28px;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            color: #fff;
            border-radius: 28px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .hero--featured {
            background:
                linear-gradient(120deg, rgba(15, 23, 42, 0.88), rgba(15, 118, 110, 0.72)),
                var(--hero-cover, linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%));
            background-size: cover;
            background-position: center;
        }

        .hero-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
        }

        .hero-logo {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 8px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
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
            color: rgba(255, 255, 255, 0.84);
            line-height: 1.6;
            font-size: 17px;
        }

        .hero-actions, .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-meta {
            margin-top: 8px;
        }

        .hero-highlight {
            display: grid;
            gap: 14px;
            padding: 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(8px);
        }

        .hero-highlight h2 {
            margin: 0;
            font-size: 28px;
            line-height: 1.05;
        }

        .hero-highlight p {
            font-size: 15px;
        }

        .hero-kpis {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .hero-kpi {
            padding: 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .hero-kpi strong,
        .hero-kpi span {
            display: block;
        }

        .hero-kpi strong {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.72);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }

        .hero-kpi span {
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
        }

        .hero-callouts {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .hero-callout {
            display: inline-flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.14);
            font-weight: 700;
            color: rgba(255, 255, 255, 0.96);
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: rgba(255, 255, 255, 0.92);
            font-weight: 600;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 14px;
            border: 1px solid transparent;
            font-weight: 700;
            cursor: pointer;
        }

        .button--primary {
            background: #fff;
            color: var(--secondary);
        }

        .button--secondary {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.16);
            color: #fff;
        }

        .button--whatsapp {
            background: var(--whatsapp);
            color: #fff;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin: 8px 0 18px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 28px;
            line-height: 1.1;
        }

        .section-title p {
            margin: 0;
            color: var(--muted);
        }

        .trust-grid,
        .steps-grid,
        .faq-grid,
        .social-grid {
            display: grid;
            gap: 18px;
            margin: 0 0 28px;
        }

        .info-card {
            display: grid;
            gap: 14px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .info-card h3 {
            margin: 0;
            font-size: 22px;
            line-height: 1.1;
        }

        .info-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .step-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--secondary);
            font-weight: 800;
        }

        .list-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .list-chip {
            display: inline-flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 999px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            color: var(--text);
            font-weight: 700;
        }

        .link-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .link-row a {
            color: var(--primary);
            font-weight: 700;
        }

        .policy-list {
            display: grid;
            gap: 10px;
            margin: 4px 0 0;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .policy-list li {
            position: relative;
            padding-left: 18px;
            line-height: 1.6;
        }

        .policy-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 9px;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--accent);
        }

        .result-card {
            display: grid;
            gap: 14px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .result-topline {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .result-card h3 {
            margin: 0;
            font-size: 24px;
            line-height: 1.1;
        }

        .result-number {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 16px;
            background: var(--primary-soft);
            color: var(--secondary);
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 0.04em;
        }

        .result-meta {
            display: grid;
            gap: 10px;
        }

        .result-line {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            color: var(--muted);
        }

        .result-line strong {
            color: var(--text);
        }

        .faq-item {
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
            overflow: hidden;
        }

        .faq-item summary {
            list-style: none;
            cursor: pointer;
            padding: 18px 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .faq-item summary::-webkit-details-marker {
            display: none;
        }

        .faq-item summary::after {
            content: '+';
            font-size: 24px;
            line-height: 1;
            color: var(--primary);
        }

        .faq-item[open] summary::after {
            content: '-';
        }

        .faq-answer {
            padding: 0 20px 20px;
            color: var(--muted);
            line-height: 1.7;
            border-top: 1px solid var(--border);
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 0 20px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.8);
            color: var(--muted);
            font-weight: 700;
            cursor: pointer;
        }

        .filter-chip.is-active {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: var(--secondary);
        }

        .raffle-grid {
            display: grid;
            gap: 18px;
        }

        .raffle-card {
            display: grid;
            gap: 18px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .raffle-card.is-hidden {
            display: none;
        }

        .raffle-cover {
            min-height: 220px;
            border-radius: 20px;
            background:
                linear-gradient(145deg, color-mix(in srgb, var(--primary) 22%, transparent), color-mix(in srgb, var(--accent) 12%, transparent)),
                var(--surface-alt);
            background-size: cover;
            background-position: center;
        }

        .raffle-head {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: flex-start;
        }

        .raffle-title {
            margin: 0;
            font-size: 28px;
            line-height: 1.05;
        }

        .raffle-price {
            display: inline-flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 14px;
            background: var(--primary-soft);
            color: var(--secondary);
            font-weight: 800;
        }

        .raffle-description {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .sales-copy {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--secondary);
            font-size: 13px;
            font-weight: 700;
        }

        .countdown {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .countdown-card {
            padding: 12px;
            border-radius: 16px;
            background: var(--accent-soft);
            border: 1px solid var(--border);
            text-align: center;
        }

        .countdown-value {
            display: block;
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
        }

        .countdown-label {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .countdown-caption {
            color: var(--muted);
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .stat {
            padding: 14px;
            border-radius: 16px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
        }

        .stat-label {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .stat-value {
            display: block;
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
        }

        .meta-grid {
            display: grid;
            gap: 10px;
        }

        .meta-item {
            display: grid;
            gap: 4px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .meta-item strong {
            font-size: 13px;
            color: var(--muted);
        }

        .meta-item span {
            font-weight: 700;
        }

        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-actions .button {
            flex: 1 1 210px;
        }

        .button--teal {
            background: var(--primary);
            color: #fff;
        }

        .button--ghost {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border);
        }

        .empty {
            padding: 32px 24px;
            border-radius: 24px;
            border: 1px dashed var(--border);
            background: rgba(255, 255, 255, 0.65);
            text-align: center;
            color: var(--muted);
        }

        .empty h3 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 24px;
        }

        .sticky-cta {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: 12px;
            z-index: 50;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            padding: 10px;
            border-radius: 22px;
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.24);
        }

        .sticky-cta .button {
            min-height: 52px;
            width: 100%;
        }

        .sticky-cta .button--ghost {
            background: rgba(255, 255, 255, 0.10);
            border-color: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        @media (min-width: 900px) {
            body {
                padding-bottom: 0;
            }

            .hero {
                grid-template-columns: 1.25fr 0.85fr;
                align-items: end;
            }

            .raffle-card {
                grid-template-columns: minmax(280px, 360px) 1fr;
                align-items: stretch;
            }

            .steps-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .trust-grid {
                grid-template-columns: 1.2fr 1fr;
            }

            .social-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .sticky-cta {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="hero @if($featuredRaffle) hero--featured @endif" @if($featuredCoverUrl) style="--hero-cover: url('{{ $featuredCoverUrl }}');" @endif>
            <div>
                <div class="hero-head">
                    @if ($brandLogoUrl)
                        <img class="hero-logo" src="{{ $brandLogoUrl }}" alt="{{ $company?->trade_name ?: 'Rifax' }}">
                    @endif
                    <span class="hero-badge">Rifas activas</span>
                </div>
                <h1>{{ $company?->trade_name ?: 'Rifax' }}</h1>
                <p>
                    @if ($featuredRaffle)
                        Descubre la rifa destacada del momento y entra directo a comprar por WhatsApp o desde la seleccion visual sin friccion.
                    @else
                        {{ $company?->help_message ?: 'Consulta las rifas publicadas, revisa su disponibilidad actual y entra directo a la seleccion visual de numeros para completar tu compra por WhatsApp.' }}
                    @endif
                </p>
                <div class="hero-actions">
                    <a class="button button--primary" href="#raffles">Ver rifas actuales</a>
                    @if ($supportPhoneDigits)
                        <a class="button button--whatsapp" href="https://wa.me/{{ $supportPhoneDigits }}?text={{ rawurlencode('MENU') }}">Comprar por WhatsApp</a>
                    @endif
                    @if ($company?->website_url)
                        <a class="button button--secondary" href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">Sitio web</a>
                    @endif
                </div>
            </div>
            <div>
                @if ($featuredRaffle)
                    @php
                        $featuredWhatsappUrl = $supportPhoneDigits
                            ? 'https://wa.me/' . $supportPhoneDigits . '?text=' . rawurlencode('Quiero comprar en ' . $featuredRaffle->title)
                            : null;
                    @endphp
                    <div class="hero-highlight">
                        <span class="hero-badge">Rifa destacada</span>
                        <h2>{{ $featuredRaffle->title }}</h2>
                        <p>{{ $featuredRaffle->description ?: 'Compra guiada, seleccion visual y seguimiento por WhatsApp en una sola experiencia.' }}</p>
                        <div class="hero-kpis">
                            <div class="hero-kpi">
                                <strong>Precio</strong>
                                <span>${{ number_format((float) $featuredRaffle->price_per_number, 0, ',', '.') }}</span>
                            </div>
                            <div class="hero-kpi">
                                <strong>Disponibles</strong>
                                <span>{{ number_format((int) $featuredRaffle->available_numbers_count) }}</span>
                            </div>
                            <div class="hero-kpi">
                                <strong>Sorteo</strong>
                                <span>{{ $featuredRaffle->draw_date?->format('Y-m-d') ?: 'Pendiente' }}</span>
                            </div>
                            <div class="hero-kpi">
                                <strong>Catalogo</strong>
                                <span>{{ number_format((int) $featuredRaffle->numbers_count) }}</span>
                            </div>
                        </div>
                        <div class="hero-callouts">
                            <span class="hero-callout">Compra minima: {{ $featuredRaffle->min_numbers_per_purchase }} numero(s)</span>
                            <span class="hero-callout">{{ $featuredRaffle->number_digits }} cifra(s) por numero</span>
                            @if ($featuredRaffle->available_numbers_count > 0)
                                <span class="hero-callout">Todavia quedan {{ number_format((int) $featuredRaffle->available_numbers_count) }} disponibles</span>
                            @endif
                        </div>
                        <div class="hero-actions">
                            <a class="button button--primary" href="{{ $featuredPickerUrl }}">Elegir numeros ahora</a>
                            @if ($featuredWhatsappUrl)
                                <a class="button button--whatsapp" href="{{ $featuredWhatsappUrl }}">Comprar esta rifa por WhatsApp</a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="hero-meta">
                        <span class="meta-pill">{{ $raffles->count() }} rifa(s) activa(s)</span>
                        @if ($company?->support_phone)
                            <span class="meta-pill">WhatsApp: {{ $company->support_phone }}</span>
                        @endif
                        @if ($company?->support_email)
                            <span class="meta-pill">{{ $company->support_email }}</span>
                        @endif
                    </div>
                    <p style="margin-top:16px;">
                        @if ($raffles->isNotEmpty())
                            Explora las rifas vigentes, filtra la que te interesa y entra a la tabla visual de numeros para comprar de forma guiada.
                        @else
                            No hay rifas activas publicadas ahora mismo, pero esta landing ya queda lista para mostrarlas apenas se publiquen.
                        @endif
                    </p>
                @endif
            </div>
        </section>

        <section>
            <div class="section-title">
                <div>
                    <h2>Como Funciona</h2>
                    <p>Un flujo simple, guiado y con validacion humana antes de emitir el boleto.</p>
                </div>
            </div>
            <div class="steps-grid">
                <article class="info-card">
                    <span class="step-index">1</span>
                    <h3>Elige tu rifa</h3>
                    <p>Entras por la landing o por WhatsApp, revisas disponibilidad y eliges tus numeros manualmente o al azar.</p>
                </article>
                <article class="info-card">
                    <span class="step-index">2</span>
                    <h3>Envias el pago</h3>
                    <p>El sistema te muestra los metodos disponibles y luego envias el comprobante por WhatsApp para revision.</p>
                </article>
                <article class="info-card">
                    <span class="step-index">3</span>
                    <h3>Revision administrativa</h3>
                    <p>Tu comprobante queda en revision dentro del panel admin para aprobar o rechazar el pago con trazabilidad.</p>
                </article>
                <article class="info-card">
                    <span class="step-index">4</span>
                    <h3>Recibes tu boleto</h3>
                    <p>Una vez aprobado, el sistema genera el ticket y lo envia por WhatsApp con tus numeros y enlace de verificacion.</p>
                </article>
            </div>
        </section>

        <section>
            <div class="section-title">
                <div>
                    <h2>Compra Con Confianza</h2>
                    <p>Informacion visible para reducir friccion y dar claridad antes de pagar.</p>
                </div>
            </div>
            <div class="trust-grid">
                <article class="info-card">
                    <h3>Metodos de Pago</h3>
                    <p>Estos son los canales visibles configurados actualmente para recibir comprobantes y procesar compras.</p>
                    @if ($paymentMethods->isEmpty())
                        <p>No hay metodos de pago visibles todavia. Configuralos desde administracion para mostrarlos aqui.</p>
                    @else
                        <div class="list-inline">
                            @foreach ($paymentMethods as $paymentMethod)
                                <span class="list-chip">{{ $paymentMethod->name }}: {{ $paymentMethod->account_reference }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if ($supportPhoneDigits)
                        <div class="hero-actions">
                            <a class="button button--whatsapp" href="https://wa.me/{{ $supportPhoneDigits }}?text={{ rawurlencode('PAGOS') }}">Consultar pagos por WhatsApp</a>
                        </div>
                    @endif
                </article>

                <article class="info-card">
                    <h3>Respaldo y Transparencia</h3>
                    <p>La loteria externa publica el numero ganador oficial. Rifax toma ese resultado para identificar al ganador dentro de la rifa y confirmar por WhatsApp las compras validadas correctamente.</p>
                    <div class="list-inline">
                        <span class="list-chip">Revision humana del comprobante</span>
                        <span class="list-chip">Ticket digital por WhatsApp</span>
                        <span class="list-chip">Seguimiento de estados</span>
                        <span class="list-chip">Soporte directo</span>
                    </div>
                    <div class="link-row">
                        @if ($company?->terms_url)
                            <a href="{{ $company->terms_url }}" target="_blank" rel="noopener noreferrer">Terminos</a>
                        @endif
                        @if ($company?->privacy_policy_url)
                            <a href="{{ $company->privacy_policy_url }}" target="_blank" rel="noopener noreferrer">Privacidad</a>
                        @endif
                        @if ($company?->website_url)
                            <a href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">Sitio web</a>
                        @endif
                    </div>
                </article>

                <article class="info-card">
                    <h3>Politica de Reserva y Sorteo</h3>
                    <p>Reglas visibles para que sepas que ocurre si compras cerca de la hora del sorteo.</p>
                    <ul class="policy-list">
                        <li>Tus numeros se reservan por tiempo limitado mientras completas el pago.</li>
                        <li>Cuando envias el comprobante por WhatsApp, tu compra pasa a revision manual y tus numeros no se liberan automaticamente.</li>
                        <li>Para participar, el comprobante debe enviarse antes de la hora del sorteo.</li>
                        <li>La loteria externa publica el resultado oficial y Rifax lo replica para confirmar al ganador dentro de la plataforma.</li>
                        <li>Al llegar la hora programada del sorteo, la rifa deja de aceptar nuevas reservas o compras.</li>
                        <li>Si todavia hay compras pendientes de revision, el resultado oficial se publica una vez esas compras queden resueltas.</li>
                    </ul>
                </article>
            </div>
        </section>

        @if ($recentResults->isNotEmpty())
            <section>
                <div class="section-title">
                    <div>
                        <h2>Ganadores Recientes</h2>
                        <p>Resultados oficiales ya publicados para reforzar confianza y mostrar trazabilidad de rifas cerradas.</p>
                    </div>
                </div>
                <div class="social-grid">
                    @foreach ($recentResults as $recentResult)
                        <article class="result-card">
                            <div class="result-topline">
                                <span>Resultado oficial</span>
                                <span>{{ $recentResult->result_published_at?->format('Y-m-d') ?: 'Publicado' }}</span>
                            </div>
                            <div>
                                <h3>{{ $recentResult->title }}</h3>
                            </div>
                            <span class="result-number">{{ $recentResult->result_number ?: 'N/A' }}</span>
                            <div class="result-meta">
                                <div class="result-line">
                                    <strong>Ganador:</strong>
                                    <span>{{ $recentResult->public_winner_label }}</span>
                                </div>
                                <div class="result-line">
                                    <strong>Sorteo:</strong>
                                    <span>{{ $recentResult->lottery_name ?: 'Referencia pendiente' }}@if($recentResult->lottery_draw_number) #{{ $recentResult->lottery_draw_number }}@endif</span>
                                </div>
                                @if ($recentResult->lottery_reference_url)
                                    <div class="link-row">
                                        <a href="{{ $recentResult->lottery_reference_url }}" target="_blank" rel="noopener noreferrer">Ver referencia del sorteo</a>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($publicFaqEntries->isNotEmpty())
            <section>
                <div class="section-title">
                    <div>
                        <h2>Preguntas Frecuentes</h2>
                        <p>Contenido administrable desde el panel para resolver dudas comunes antes de comprar.</p>
                    </div>
                </div>
                <div class="faq-grid">
                    @foreach ($publicFaqEntries as $faqEntry)
                        <details class="faq-item">
                            <summary>{{ $faqEntry->title }}</summary>
                            <div class="faq-answer">{!! nl2br(e($faqEntry->body_text)) !!}</div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        <section id="raffles">
            <div class="section-title">
                <div>
                    <h2>{{ $featuredRaffle && $otherRaffles->isNotEmpty() ? 'Mas Rifas Activas' : 'Rifas Disponibles' }}</h2>
                    <p>Informacion publica orientada a compra directa, filtro rapido y seleccion visual de numeros.</p>
                </div>
            </div>

            @if ($raffles->isEmpty())
                <div class="empty">
                    <h3>No hay rifas activas por ahora</h3>
                    <p>Vuelve pronto o publica una rifa desde administracion para que aparezca automaticamente aqui.</p>
                </div>
            @else
                @if ($catalogRaffles->count() > 1)
                    <div class="filters" aria-label="Filtros de rifas">
                        <button type="button" class="filter-chip is-active" data-filter="all">Todas</button>
                        @foreach ($catalogRaffles as $raffle)
                            <button type="button" class="filter-chip" data-filter="{{ $raffle->slug }}">{{ $raffle->title }}</button>
                        @endforeach
                    </div>
                @endif

                <div class="raffle-grid" id="raffle-grid">
                    @foreach ($catalogRaffles as $raffle)
                        @php
                            $pickerUrl = route('raffles.number-picker', array_merge([
                                'raffle' => $raffle->slug,
                                'quantity' => max(1, (int) $raffle->min_numbers_per_purchase),
                                'source' => 'landing_catalog',
                            ], $landingTracking));
                            $tablePickerUrl = route('raffles.number-picker', array_merge([
                                'raffle' => $raffle->slug,
                                'quantity' => max(1, (int) $raffle->min_numbers_per_purchase),
                                'source' => 'landing_catalog_table',
                            ], $landingTracking));
                            $coverUrl = filled($raffle->cover_image_path) ? asset('storage/' . ltrim($raffle->cover_image_path, '/')) : null;
                            $drawAt = ($raffle->draw_date && $raffle->draw_time)
                                ? \Illuminate\Support\Carbon::parse($raffle->draw_date->format('Y-m-d') . ' ' . $raffle->draw_time, $company?->timezone ?: config('app.timezone'))->toIso8601String()
                                : null;
                            $directWhatsappUrl = $supportPhoneDigits
                                ? 'https://wa.me/' . $supportPhoneDigits . '?text=' . rawurlencode('Quiero comprar en ' . $raffle->title)
                                : null;
                        @endphp
                        <article class="raffle-card" data-raffle-slug="{{ $raffle->slug }}">
                            <div class="raffle-cover" @if($coverUrl) style="background-image: linear-gradient(145deg, color-mix(in srgb, var(--primary) 18%, transparent), color-mix(in srgb, var(--accent) 10%, transparent)), url('{{ $coverUrl }}');" @endif></div>
                            <div style="display:grid; gap:16px;">
                                <div class="raffle-head">
                                    <div>
                                        <span class="sales-copy">
                                            @if ($raffle->available_numbers_count <= 25)
                                                Ultimos {{ number_format((int) $raffle->available_numbers_count) }} disponibles
                                            @elseif ($raffle->reserved_numbers_count > 0)
                                                Alta demanda: {{ number_format((int) $raffle->reserved_numbers_count) }} reservado(s)
                                            @else
                                                Compra guiada y seleccion visual
                                            @endif
                                        </span>
                                        <h3 class="raffle-title">{{ $raffle->title }}</h3>
                                        @if (filled($raffle->description))
                                            <p class="raffle-description">{{ $raffle->description }}</p>
                                        @endif
                                    </div>
                                    <span class="raffle-price">${{ number_format((float) $raffle->price_per_number, 0, ',', '.') }}</span>
                                </div>

                                <div class="countdown" @if($drawAt) data-draw-at="{{ $drawAt }}" @endif>
                                    <div class="countdown-card">
                                        <span class="countdown-value" data-unit="days">--</span>
                                        <span class="countdown-label">Dias</span>
                                    </div>
                                    <div class="countdown-card">
                                        <span class="countdown-value" data-unit="hours">--</span>
                                        <span class="countdown-label">Horas</span>
                                    </div>
                                    <div class="countdown-card">
                                        <span class="countdown-value" data-unit="minutes">--</span>
                                        <span class="countdown-label">Min</span>
                                    </div>
                                    <div class="countdown-card">
                                        <span class="countdown-value" data-unit="seconds">--</span>
                                        <span class="countdown-label">Seg</span>
                                    </div>
                                </div>
                                <div class="countdown-caption">
                                    @if ($raffle->draw_date || $raffle->draw_time)
                                        Sorteo programado para {{ $raffle->draw_date?->format('Y-m-d') ?: 'Pendiente' }} {{ $raffle->draw_time ?: '' }}
                                    @else
                                        Fecha de sorteo pendiente de configuracion.
                                    @endif
                                </div>

                                <div class="meta-grid">
                                    <div class="meta-item">
                                        <strong>Sorteo</strong>
                                        <span>{{ $raffle->lottery_name ?: 'Pendiente de definir' }} @if($raffle->lottery_draw_number) #{{ $raffle->lottery_draw_number }} @endif</span>
                                    </div>
                                    <div class="meta-item">
                                        <strong>Cifras y compra minima</strong>
                                        <span>{{ $raffle->number_digits }} cifra(s) | minimo {{ $raffle->min_numbers_per_purchase }} numero(s)</span>
                                    </div>
                                </div>

                                <div class="stats">
                                    <div class="stat">
                                        <span class="stat-label">Catalogo total</span>
                                        <span class="stat-value">{{ number_format((int) $raffle->numbers_count) }}</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-label">Disponibles</span>
                                        <span class="stat-value">{{ number_format((int) $raffle->available_numbers_count) }}</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-label">Reservados</span>
                                        <span class="stat-value">{{ number_format((int) $raffle->reserved_numbers_count) }}</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-label">Pagados</span>
                                        <span class="stat-value">{{ number_format((int) $raffle->paid_numbers_count) }}</span>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <a class="button button--teal" href="{{ $pickerUrl }}">Seleccionar numeros</a>
                                    @if ($directWhatsappUrl)
                                        <a class="button button--whatsapp" href="{{ $directWhatsappUrl }}">Comprar por WhatsApp</a>
                                    @endif
                                    <a class="button button--ghost" href="{{ $tablePickerUrl }}">Ver tabla completa</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    @if ($raffles->isNotEmpty() || $stickyWhatsappUrl)
        <div class="sticky-cta" data-testid="mobile-sticky-cta">
            @php
                $stickyPickerUrl = $featuredRaffle
                    ? route('raffles.number-picker', array_merge([
                        'raffle' => $featuredRaffle->slug,
                        'quantity' => max(1, (int) $featuredRaffle->min_numbers_per_purchase),
                        'source' => 'landing_sticky',
                    ], $landingTracking))
                    : $featuredPickerUrl;
            @endphp
            <a class="button button--primary" href="{{ $stickyPickerUrl }}">{{ $stickyPrimaryLabel }}</a>
            @if ($stickyWhatsappUrl)
                <a class="button button--whatsapp" href="{{ $stickyWhatsappUrl }}">WhatsApp</a>
            @else
                <a class="button button--ghost" href="#raffles">Explorar</a>
            @endif
        </div>
    @endif

    <script>
        (() => {
            const countdowns = Array.from(document.querySelectorAll('.countdown[data-draw-at]'));

            const renderCountdown = (container) => {
                const drawAt = container.dataset.drawAt;
                if (!drawAt) return;

                const diff = new Date(drawAt).getTime() - Date.now();
                const safeDiff = Math.max(diff, 0);
                const days = Math.floor(safeDiff / 86400000);
                const hours = Math.floor((safeDiff % 86400000) / 3600000);
                const minutes = Math.floor((safeDiff % 3600000) / 60000);
                const seconds = Math.floor((safeDiff % 60000) / 1000);

                container.querySelector('[data-unit="days"]').textContent = String(days).padStart(2, '0');
                container.querySelector('[data-unit="hours"]').textContent = String(hours).padStart(2, '0');
                container.querySelector('[data-unit="minutes"]').textContent = String(minutes).padStart(2, '0');
                container.querySelector('[data-unit="seconds"]').textContent = String(seconds).padStart(2, '0');
            };

            countdowns.forEach(renderCountdown);

            if (countdowns.length > 0) {
                window.setInterval(() => countdowns.forEach(renderCountdown), 1000);
            }

            const chips = Array.from(document.querySelectorAll('.filter-chip'));
            const cards = Array.from(document.querySelectorAll('.raffle-card[data-raffle-slug]'));

            chips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    const filter = chip.dataset.filter || 'all';

                    chips.forEach((currentChip) => currentChip.classList.remove('is-active'));
                    chip.classList.add('is-active');

                    cards.forEach((card) => {
                        const shouldShow = filter === 'all' || card.dataset.raffleSlug === filter;
                        card.classList.toggle('is-hidden', !shouldShow);
                    });
                });
            });
        })();
    </script>
</body>
</html>
