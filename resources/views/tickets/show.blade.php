<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ ($company?->trade_name ?: config('app.name', 'Rifax')) . ' | Boleto ' . data_get($payload, 'ticket.code') }}</title>
    @php
        $brandName = $company?->trade_name ?: config('app.name', 'Rifax');
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $company?->primary_color) ? $company->primary_color : '#bfb21a';
        [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
        $accentInk = ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.55 ? '#181715' : '#f4f4f0';
        $drawRaw = trim((string) data_get($payload, 'raffle.draw_date') . ' ' . (string) data_get($payload, 'raffle.draw_time'));
        try {
            $drawAt = $drawRaw !== '' ? \Illuminate\Support\Carbon::parse($drawRaw)->locale('es') : null;
        } catch (\Throwable) {
            $drawAt = null;
        }
        $drawText = $drawAt
            ? $drawAt->translatedFormat('j \\d\\e F \\d\\e Y') . (data_get($payload, 'raffle.draw_time') ? ', ' . $drawAt->format('g:i') . ' ' . ($drawAt->format('A') === 'AM' ? 'a. m.' : 'p. m.') : '')
            : null;
        $status = strtolower((string) data_get($payload, 'ticket.status', 'valid'));
        $statusLabel = match ($status) { 'valid', 'issued', 'active' => 'Boleto válido', 'void', 'cancelled', 'revoked' => 'Boleto anulado', default => ucfirst($status) };
        $statusClass = in_array($status, ['valid', 'issued', 'active'], true) ? 'is-ok' : (in_array($status, ['void', 'cancelled', 'revoked'], true) ? 'is-bad' : 'is-warn');
        $logoUrl = filled($company?->logo_path) ? asset('storage/' . ltrim($company->logo_path, '/')) : null;
        $ticketImageUrl = data_get($payload, 'ticket.image_url');
        $numbers = collect(data_get($payload, 'numbers', []));
        $supportPhoneDigits = preg_replace('/\D+/', '', (string) ($company?->support_phone ?? ''));
        $supportUrl = $supportPhoneDigits !== ''
            ? 'https://wa.me/' . $supportPhoneDigits . '?text=' . rawurlencode('Hola, necesito ayuda con mi boleto ' . data_get($payload, 'ticket.code'))
            : null;
    @endphp
    <meta name="theme-color" content="#181715">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/rifax.css') }}?v={{ @filemtime(public_path('css/rifax.css')) }}">
    <style>:root { --accent: {{ $accent }}; --accent-ink: {{ $accentInk }}; }</style>
</head>
<body class="field-night">
<main class="wrap">
    <header class="bar">
        <a class="bar-brand" href="{{ url('/') }}" style="color: var(--night-ink)">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="" width="40" height="40">
            @endif
            <span>{{ $brandName }}</span>
        </a>
        <a class="link" href="{{ url('/') }}#raffles" style="color: var(--night-ink)">Ver rifas</a>
    </header>

    <div class="result">
        <article class="result-card paper ticket">
            <div class="result-body">
                <h1 class="head num">{{ data_get($payload, 'ticket.code') }}</h1>
                <p class="lead"><span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span> Boleto validado de {{ data_get($payload, 'raffle.title') }}. Se juega con la {{ data_get($payload, 'raffle.lottery_name') ?: 'lotería por confirmar' }}@if (data_get($payload, 'raffle.lottery_draw_number')), sorteo {{ data_get($payload, 'raffle.lottery_draw_number') }}@endif.</p>

                <div class="result-actions" style="margin-top: 1.75rem">
                    @if ($ticketImageUrl)
                        <a class="btn btn-accent" href="{{ $ticketImageUrl }}" target="_blank" rel="noopener noreferrer">Abrir imagen del boleto</a>
                    @endif
                    @if ($supportUrl)
                        <a class="link" href="{{ $supportUrl }}" target="_blank" rel="noopener noreferrer">Solicitar ayuda por WhatsApp</a>
                    @endif
                </div>

                <h2 class="visually-hidden">Números asignados</h2>
                <div class="result-numbers num">
                    @forelse ($numbers as $number)
                        <span>{{ $number }}</span>
                    @empty
                        <p class="muted">No hay números asociados a este boleto.</p>
                    @endforelse
                </div>

                <dl class="result-facts num">
                    <div><dt>Sorteo</dt><dd>{{ $drawText ?: 'Por confirmar' }}</dd></div>
                    <div><dt>Versión del boleto</dt><dd>v{{ data_get($payload, 'ticket.version') }}</dd></div>
                </dl>

                @if ($ticketImageUrl)
                    <figure class="result-image">
                        <img src="{{ $ticketImageUrl }}" alt="Boleto {{ data_get($payload, 'ticket.code') }}" loading="lazy">
                    </figure>
                @else
                    <p class="notice">Aún no hay una imagen pública de este boleto.</p>
                @endif
            </div>

            <div class="result-stub perforated notches">
                <p>Guarda este enlace para consultar tu boleto cuando quieras. Esta página no muestra datos personales del comprador.</p>
            </div>
        </article>
    </div>
</main>
</body>
</html>
