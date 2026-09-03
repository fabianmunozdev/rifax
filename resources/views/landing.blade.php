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
        $stickyWhatsappUrl = $botPhoneDigits
            ? 'https://wa.me/' . $botPhoneDigits . '?text=' . rawurlencode($stickyWhatsappText)
            : null;
    @endphp
    <style>
        :root {
            color-scheme: dark;
            --bg: #070b12;
            --bg-elevated: #0b1220;
            --surface: #111926;
            --surface-alt: #182233;
            --surface-soft: rgba(255, 255, 255, 0.04);
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --primary: {{ $brandPrimary }};
            --secondary: {{ $brandSecondary }};
            --accent: {{ $brandAccent }};
            --primary-soft: color-mix(in srgb, var(--primary) 20%, rgba(255, 255, 255, 0.05));
            --accent-soft: color-mix(in srgb, var(--accent) 16%, rgba(255, 255, 255, 0.05));
            --shadow: 0 22px 52px rgba(2, 6, 23, 0.38);
            --whatsapp: #25d366;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 18%, transparent), transparent 34%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 16%, transparent), transparent 28%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0)),
                var(--bg);
            color: var(--text);
            padding-bottom: 108px;
        }

        a { color: inherit; text-decoration: none; }

        .page {
            max-width: 1320px;
            margin: 0 auto;
            padding: 24px 16px 56px;
        }

        section + section {
            margin-top: 36px;
        }

        .hero {
            display: grid;
            gap: 18px;
            padding: 22px;
            background:
                linear-gradient(135deg, rgba(7, 11, 18, 0.92), color-mix(in srgb, var(--secondary) 78%, black) 46%, color-mix(in srgb, var(--primary) 52%, black) 100%);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 26px;
            box-shadow: var(--shadow);
        }

        .hero--featured {
            background:
                linear-gradient(118deg, rgba(7, 11, 18, 0.66), color-mix(in srgb, var(--secondary) 46%, transparent) 42%, color-mix(in srgb, var(--primary) 28%, transparent) 100%),
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
            width: 56px;
            height: 56px;
            border-radius: 16px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 6px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.86);
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(30px, 4.2vw, 46px);
            line-height: 0.98;
            letter-spacing: -0.03em;
        }

        .hero p {
            margin: 0;
            max-width: 720px;
            color: rgba(255, 255, 255, 0.72);
            line-height: 1.65;
            font-size: 14px;
        }

        .hero-support-copy {
            margin-top: 8px;
        }

        .hero-actions, .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .hero-meta {
            margin-top: 4px;
        }

        .hero-highlight {
            display: grid;
            gap: 12px;
            padding: 18px;
            border-radius: 22px;
            background: rgba(10, 15, 24, 0.56);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
        }

        .hero-highlight h2 {
            margin: 0;
            font-size: 24px;
            line-height: 1.08;
        }

        .hero-highlight p {
            font-size: 14px;
        }

        .hero-kpis {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .hero-kpi {
            padding: 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .hero-kpi strong,
        .hero-kpi span {
            display: block;
        }

        .hero-kpi strong {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.58);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .hero-kpi span {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
        }

        .hero-callouts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .hero-callout {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-weight: 700;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.88);
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.78);
            font-size: 12px;
            font-weight: 600;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 42px;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-weight: 700;
            font-size: 13px;
            line-height: 1;
            letter-spacing: 0.01em;
            cursor: pointer;
            transition: transform 150ms ease, border-color 150ms ease, background 150ms ease, box-shadow 150ms ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button--primary {
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 84%, white), color-mix(in srgb, var(--accent) 72%, white));
            color: #fff;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 24%, transparent);
        }

        .button--secondary {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.10);
            color: #fff;
        }

        .button--whatsapp {
            background: var(--whatsapp);
            color: #fff;
            border-color: rgba(37, 211, 102, 0.28);
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin: 0 0 16px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 24px;
            line-height: 1.12;
            letter-spacing: -0.02em;
        }

        .section-title p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .trust-grid,
        .steps-grid,
        .faq-grid,
        .social-grid {
            display: grid;
            gap: 14px;
            margin: 0;
        }

        .info-card {
            display: grid;
            gap: 12px;
            padding: 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .steps-grid .info-card {
            text-align: center;
            justify-items: center;
        }

        .info-card h3 {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
        }

        .info-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.65;
            font-size: 13px;
        }

        .step-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 78%, white), color-mix(in srgb, var(--accent) 56%, black));
            color: #fff;
            font-size: 24px;
            line-height: 1;
            font-weight: 800;
            box-shadow: 0 12px 28px color-mix(in srgb, var(--primary) 22%, transparent);
        }

        .list-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .list-chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 12px;
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

        .site-footer {
            margin-top: 40px;
            padding: 18px 0 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .site-footer-grid {
            display: grid;
            gap: 12px;
        }

        .site-footer-title {
            margin: 0;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.56);
        }

        .site-footer-copy,
        .site-footer-list,
        .site-footer-links {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .site-footer-list {
            display: grid;
            gap: 4px;
            padding-left: 16px;
        }

        .site-footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .site-footer-links a {
            color: rgba(255, 255, 255, 0.78);
            text-decoration: underline;
            text-decoration-color: rgba(255, 255, 255, 0.18);
            text-underline-offset: 3px;
        }

        .policy-list {
            display: grid;
            gap: 8px;
            margin: 4px 0 0;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 13px;
        }

        .policy-list li {
            position: relative;
            padding-left: 18px;
            line-height: 1.55;
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
            gap: 12px;
            padding: 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .result-topline {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .result-card h3 {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
        }

        .result-number {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 14px;
            background: var(--primary-soft);
            color: #fff;
            font-size: 22px;
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
            font-size: 13px;
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
            padding: 16px 18px;
            font-weight: 800;
            font-size: 14px;
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
            font-size: 20px;
            line-height: 1;
            color: var(--primary);
        }

        .faq-item[open] summary::after {
            content: '-';
        }

        .faq-answer {
            padding: 0 18px 18px;
            color: var(--muted);
            line-height: 1.65;
            font-size: 13px;
            border-top: 1px solid var(--border);
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 16px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface-soft);
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .filter-chip.is-active {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: #fff;
        }

        .raffle-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: 1fr;
        }

        .raffle-card {
            display: grid;
            gap: 12px;
            padding: 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .raffle-card.is-hidden {
            display: none;
        }

        .raffle-cover {
            min-height: 118px;
            border-radius: 14px;
            background:
                linear-gradient(145deg, color-mix(in srgb, var(--primary) 22%, transparent), color-mix(in srgb, var(--accent) 12%, transparent)),
                var(--raffle-cover, var(--surface-alt));
            background-size: cover;
            background-position: center;
        }

        .raffle-content {
            display: grid;
            gap: 12px;
        }

        .raffle-head {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: space-between;
            align-items: flex-start;
        }

        .raffle-title {
            margin: 0;
            font-size: 17px;
            line-height: 1.2;
        }

        .raffle-price {
            display: inline-flex;
            align-items: center;
            padding: 6px 9px;
            border-radius: 10px;
            background: var(--primary-soft);
            color: #fff;
            font-size: 12px;
            font-weight: 800;
        }

        .raffle-description {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
            font-size: 12px;
            line-clamp: 2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sales-copy {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 5px 8px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .countdown {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
        }

        .countdown-card {
            padding: 8px 6px;
            border-radius: 12px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            text-align: center;
        }

        .countdown-value {
            display: block;
            font-size: 15px;
            font-weight: 800;
            line-height: 1;
        }

        .countdown-label {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .countdown-caption {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.55;
        }

        .meta-grid {
            display: grid;
            gap: 6px;
        }

        .meta-item {
            display: grid;
            gap: 4px;
            padding: 9px 10px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-soft);
        }

        .meta-item strong {
            font-size: 10px;
            color: var(--muted);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .meta-item span {
            font-weight: 700;
            font-size: 12px;
            line-height: 1.5;
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .card-actions .button {
            min-width: 0;
            width: 100%;
        }

        .button--teal {
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 86%, white), color-mix(in srgb, var(--accent) 68%, black));
            color: #fff;
        }

        .button--ghost {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text);
            border-color: var(--border);
        }

        .button-icon {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
        }

        .empty {
            padding: 28px 22px;
            border-radius: 22px;
            border: 1px dashed var(--border);
            background: rgba(255, 255, 255, 0.03);
            text-align: center;
            color: var(--muted);
        }

        .empty h3 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 22px;
        }

        .sticky-cta {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: 12px;
            z-index: 50;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            padding: 8px;
            border-radius: 18px;
            background: rgba(7, 11, 18, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            box-shadow: 0 18px 50px rgba(2, 6, 23, 0.34);
        }

        .sticky-cta .button {
            min-height: 46px;
            width: 100%;
        }

        .sticky-cta .button--ghost {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.08);
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

            .steps-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .raffle-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .trust-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .social-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .sticky-cta {
                display: none;
            }
        }

        @media (min-width: 1180px) {
            .raffle-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .page {
                padding-top: 18px;
            }

            .hero {
                padding: 18px;
                border-radius: 22px;
            }

            .hero h1 {
                font-size: 28px;
            }

            .hero-highlight,
            .info-card,
            .result-card,
            .raffle-card {
                padding: 16px;
            }

            .card-actions {
                grid-template-columns: 1fr;
            }

            .section-title h2 {
                font-size: 22px;
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
                        Descubre la rifa destacada del momento y entra directo a comprar por WhatsApp o desde la selección visual sin fricción.
                    @else
                        {{ $company?->help_message ?: 'Consulta las rifas publicadas, revisa su disponibilidad actual y entra directo a la selección visual de números para completar tu compra por WhatsApp.' }}
                    @endif
                </p>
                <div class="hero-actions">
                    <a class="button button--primary" href="#raffles">Ver rifas actuales</a>
                    @if ($botPhoneDigits)
                        <a class="button button--whatsapp" href="https://wa.me/{{ $botPhoneDigits }}?text={{ rawurlencode('MENU') }}">Comprar por WhatsApp</a>
                    @endif
                    @if ($company?->website_url)
                        <a class="button button--secondary" href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">Sitio web</a>
                    @endif
                </div>
            </div>
            <div>
                @if ($featuredRaffle)
                    @php
                        $featuredWhatsappUrl = $botPhoneDigits
                            ? 'https://wa.me/' . $botPhoneDigits . '?text=' . rawurlencode('Quiero comprar en ' . $featuredRaffle->title)
                            : null;
                    @endphp
                    <div class="hero-highlight">
                        <span class="hero-badge">Rifa destacada</span>
                        <h2>{{ $featuredRaffle->title }}</h2>
                        <p>{{ $featuredRaffle->description ?: 'Compra guiada, selección visual y seguimiento por WhatsApp en una sola experiencia.' }}</p>
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
                                <strong>Catálogo</strong>
                                <span>{{ number_format((int) $featuredRaffle->numbers_count) }}</span>
                            </div>
                        </div>
                        <div class="hero-callouts">
                            <span class="hero-callout">Compra mínima: {{ $featuredRaffle->min_numbers_per_purchase }} número(s)</span>
                            <span class="hero-callout">{{ $featuredRaffle->number_digits }} cifra(s) por número</span>
                            @if ($featuredRaffle->available_numbers_count > 0)
                                <span class="hero-callout">Todavía quedan {{ number_format((int) $featuredRaffle->available_numbers_count) }} disponibles</span>
                            @endif
                        </div>
                        <div class="hero-actions">
                            <a class="button button--primary" href="{{ $featuredPickerUrl }}">Elegir números ahora</a>
                            @if ($featuredWhatsappUrl)
                                <a class="button button--whatsapp" href="{{ $featuredWhatsappUrl }}">Comprar esta rifa por WhatsApp</a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="hero-meta">
                        <span class="meta-pill">{{ $raffles->count() }} rifa(s) activa(s)</span>
                        @if ($company?->whatsapp_bot_phone)
                            <span class="meta-pill">Bot WhatsApp: {{ $company->whatsapp_bot_phone }}</span>
                        @endif
                        @if ($company?->support_phone)
                            <span class="meta-pill">Soporte: {{ $company->support_phone }}</span>
                        @endif
                        @if ($company?->support_email)
                            <span class="meta-pill">{{ $company->support_email }}</span>
                        @endif
                    </div>
                    <p class="hero-support-copy">
                        @if ($raffles->isNotEmpty())
                            Explora las rifas vigentes, filtra la que te interesa y entra a la tabla visual de números para comprar de forma guiada.
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
                    <h2>Cómo funciona</h2>
                    <p>Un flujo simple, guiado y con validación humana antes de emitir el boleto.</p>
                </div>
            </div>
            <div class="steps-grid">
                <article class="info-card">
                    <span class="step-index">1</span>
                    <h3>Elige tu rifa</h3>
                    <p>Entras por la landing o por WhatsApp, revisas disponibilidad y eliges tus números manualmente o al azar.</p>
                </article>
                <article class="info-card">
                    <span class="step-index">2</span>
                    <h3>Envías el pago</h3>
                    <p>El sistema te muestra los métodos disponibles y luego envías el comprobante por WhatsApp para revisión.</p>
                </article>
                <article class="info-card">
                    <span class="step-index">3</span>
                    <h3>Revisión administrativa</h3>
                    <p>Tu comprobante queda en revisión dentro del panel admin para aprobar o rechazar el pago con trazabilidad.</p>
                </article>
                <article class="info-card">
                    <span class="step-index">4</span>
                    <h3>Recibes tu boleto</h3>
                    <p>Una vez aprobado, el sistema genera el ticket y lo envía por WhatsApp con tus números y enlace de verificación.</p>
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

        <section id="raffles">
            <div class="section-title">
                <div>
                    <h2>{{ $featuredRaffle && $otherRaffles->isNotEmpty() ? 'Más rifas activas' : 'Rifas disponibles' }}</h2>
                    <p>Información pública orientada a compra directa, filtro rápido y selección visual de números.</p>
                </div>
            </div>

            @if ($raffles->isEmpty())
                <div class="empty">
                    <h3>No hay rifas activas por ahora</h3>
                    <p>Vuelve pronto o publica una rifa desde administración para que aparezca automáticamente aquí.</p>
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
                            $directWhatsappUrl = $botPhoneDigits
                                ? 'https://wa.me/' . $botPhoneDigits . '?text=' . rawurlencode('Quiero comprar en ' . $raffle->title)
                                : null;
                        @endphp
                        <article class="raffle-card" data-raffle-slug="{{ $raffle->slug }}">
                            <div class="raffle-cover" @if($coverUrl) style="--raffle-cover: url('{{ $coverUrl }}');" @endif></div>
                            <div class="raffle-content">
                                <div class="raffle-head">
                                    <div>
                                        <span class="sales-copy">
                                            @if ($raffle->available_numbers_count <= 25)
                                                Últimos {{ number_format((int) $raffle->available_numbers_count) }} disponibles
                                            @elseif ($raffle->reserved_numbers_count > 0)
                                                Alta demanda: {{ number_format((int) $raffle->reserved_numbers_count) }} reservado(s)
                                            @else
                                                Compra guiada y selección visual
                                            @endif
                                        </span>
                                        <h3 class="raffle-title">{{ $raffle->title }}</h3>
                                    </div>
                                    <span class="raffle-price">${{ number_format((float) $raffle->price_per_number, 0, ',', '.') }}</span>
                                </div>

                                <div class="countdown" @if($drawAt) data-draw-at="{{ $drawAt }}" @endif>
                                    <div class="countdown-card">
                                        <span class="countdown-value" data-unit="days">--</span>
                                        <span class="countdown-label">Días</span>
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
                                        Sorteo {{ $raffle->draw_date?->format('Y-m-d') ?: 'Pendiente' }} {{ $raffle->draw_time ?: '' }}
                                    @else
                                        Fecha de sorteo pendiente de configuración.
                                    @endif
                                </div>

                                <div class="card-actions">
                                    <a class="button button--teal" href="{{ $pickerUrl }}">Seleccionar números</a>
                                    @if ($directWhatsappUrl)
                                        <a class="button button--whatsapp" href="{{ $directWhatsappUrl }}">
                                            <svg class="button-icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                                                <path d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.57 2 2.12 6.42 2.12 11.88c0 1.75.46 3.46 1.34 4.97L2 22l5.3-1.39a9.9 9.9 0 0 0 4.73 1.21h.01c5.46 0 9.88-4.42 9.88-9.88a9.8 9.8 0 0 0-2.87-7.03Zm-7.02 15.24h-.01a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.15.83.84-3.07-.2-.32a8.16 8.16 0 0 1-1.25-4.38c0-4.5 3.67-8.16 8.19-8.16a8.1 8.1 0 0 1 5.78 2.4 8.12 8.12 0 0 1 2.38 5.78c0 4.51-3.67 8.17-8.1 8.24Zm4.48-6.13c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.54.12-.16.24-.62.78-.76.94-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.92-1.19-.71-.63-1.19-1.4-1.33-1.64-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.31-.74-1.8-.2-.47-.4-.4-.54-.4h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.68 2.56 4.07 3.59.57.25 1.01.4 1.36.52.57.18 1.08.15 1.48.09.45-.07 1.42-.58 1.62-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28Z"/>
                                            </svg>
                                            <span>Comprar por WhatsApp</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

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

        <footer class="site-footer">
            <div class="site-footer-grid">
                <p class="site-footer-title">Información operativa</p>

                <p class="site-footer-copy">
                    Los métodos de pago visibles se confirman por WhatsApp y cada comprobante pasa por revisión administrativa antes de emitir el boleto digital.
                    El resultado oficial se toma de la lotería de referencia configurada para cada rifa.
                </p>

                @if ($paymentMethods->isNotEmpty())
                    <p class="site-footer-copy">
                        Métodos visibles:
                        @foreach ($paymentMethods as $paymentMethod)
                            <span>{{ $paymentMethod->name }}@if($paymentMethod->account_reference): {{ $paymentMethod->account_reference }}@endif</span>@if (! $loop->last) · @endif
                        @endforeach
                    </p>
                @endif

                <ul class="site-footer-list">
                    <li>Tus números se reservan por tiempo limitado mientras completas el pago.</li>
                    <li>Si envías el comprobante antes del sorteo, la compra queda en revisión y tus números no se liberan automáticamente.</li>
                    <li>Las compras pendientes deben resolverse antes de publicar el resultado final dentro de la plataforma.</li>
                </ul>

                <div class="site-footer-links">
                    @if ($supportPhoneDigits)
                        <a href="https://wa.me/{{ $supportPhoneDigits }}?text={{ rawurlencode('PAGOS') }}">Soporte por WhatsApp</a>
                    @endif
                    @if ($company?->terms_url)
                        <a href="{{ $company->terms_url }}" target="_blank" rel="noopener noreferrer">Términos</a>
                    @endif
                    @if ($company?->privacy_policy_url)
                        <a href="{{ $company->privacy_policy_url }}" target="_blank" rel="noopener noreferrer">Privacidad</a>
                    @endif
                    @if ($company?->website_url)
                        <a href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">Sitio web</a>
                    @endif
                </div>
            </div>
        </footer>
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
