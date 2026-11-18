<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $brandName = $company?->trade_name ?: config('app.name', 'Rifax');
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $company?->primary_color) ? $company->primary_color : '#bfb21a';
        [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
        $accentInk = ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.55 ? '#181715' : '#f4f4f0';
        $updatedAt = ($page->updated_at ?? $page->published_at)?->locale('es')->translatedFormat('j \d\e F \d\e Y');
    @endphp
    <title>{{ $page->title }} | {{ $brandName }}</title>
    @if ($page->summary)
        <meta name="description" content="{{ strtr($page->summary, ['{empresa}' => $brandName]) }}">
    @endif
    <meta name="theme-color" content="#181715">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rifax.css') }}?v={{ @filemtime(public_path('css/rifax.css')) }}">
    <style>
        :root { --accent: {{ $accent }}; --accent-ink: {{ $accentInk }}; }
        .legal { padding-block: clamp(1.5rem, 4vw, 3rem) clamp(3rem, 8vw, 6rem); }
        .legal-top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; color: var(--night-ink); margin-bottom: clamp(1.5rem, 4vw, 3rem); }
        .legal-top a { color: var(--night-ink); }
        .legal-brand { font-family: var(--font-display); font-size: 1.5rem; text-transform: uppercase; text-decoration: none; }
        .legal-sheet { max-width: 52rem; margin-inline: auto; padding: clamp(1.25rem, 5vw, 3.5rem); }
        .legal-sheet h1 { font-family: var(--font-display); font-size: clamp(2.25rem, 1.4rem + 4vw, 4rem); line-height: 1; text-transform: uppercase; }
        .legal-meta { color: var(--muted); font-size: var(--step-small); margin-top: 0.75rem; }
        .legal-body { margin-top: 2rem; }
        .legal-body h2 { font-family: var(--font-display); font-size: var(--step-lead); text-transform: uppercase; margin: 2.25rem 0 0.75rem; }
        .legal-body h3 { font-size: 1.1em; margin: 1.5rem 0 0.5rem; }
        .legal-body p { margin: 0 0 0.9rem; }
        .legal-body ul, .legal-body ol { margin: 0 0 1rem; padding-left: 1.4rem; }
        .legal-body li { margin-bottom: 0.4rem; }
        .legal-body a { text-decoration: underline; }
        .legal-nav { display: flex; flex-wrap: wrap; gap: 1rem 1.5rem; margin-top: 2.5rem; padding-top: 1.25rem; border-top: 1px solid var(--paper-2); font-size: var(--step-small); }
    </style>
</head>
<body>
<main class="legal field-night">
    <div class="wrap">
        <header class="legal-top">
            <a class="legal-brand" href="{{ url('/') }}">{{ $brandName }}</a>
            <a class="link" href="{{ url('/') }}">Volver a las rifas</a>
        </header>

        <article class="legal-sheet paper">
            <h1>{{ $page->title }}</h1>
            @if ($updatedAt)
                <p class="legal-meta">Última actualización: {{ $updatedAt }}</p>
            @endif

            <div class="legal-body">
                {!! $body !!}
            </div>

            <nav class="legal-nav" aria-label="Páginas legales">
                <a class="link" href="{{ route('legal.terms') }}">Términos y condiciones</a>
                <a class="link" href="{{ route('legal.privacy') }}">Política de datos personales</a>
                <span>© {{ now()->year }} {{ $company?->legal_name ?: $brandName }}</span>
            </nav>
        </article>
    </div>
</main>
</body>
</html>
