<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reserva confirmada | {{ $raffle->title }}</title>
    @php
        $company = $company ?? null;
        $brandName = $company?->trade_name ?: config('app.name', 'Rifax');
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $company?->primary_color) ? $company->primary_color : '#bfb21a';
        [$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
        $accentInk = ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.55 ? '#181715' : '#f4f4f0';
        $showCountdown = (! ($requiresOnboarding ?? false))
            && isset($reservedUntilIso) && is_string($reservedUntilIso) && $reservedUntilIso !== '';
        $hasWhatsapp = isset($whatsappOpenUrl) && is_string($whatsappOpenUrl) && $whatsappOpenUrl !== '';
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
        <a class="bar-brand" href="{{ url('/') }}" style="color: var(--night-ink)"><span>{{ $brandName }}</span></a>
        <a class="link" href="{{ url('/') }}#raffles" style="color: var(--night-ink)">Ver otras rifas</a>
    </header>

    <div class="result">
        <article class="result-card paper ticket">
            <div class="result-body">
                <h1 class="head">Tu reserva está confirmada</h1>
                <p class="lead">
                    Apartamos tus números en {{ $raffle->title }}.
                    @if ($requiresOnboarding)
                        Revisa el mensaje que te enviamos por WhatsApp: allí te pedimos unos datos rápidos para terminar tu registro y completar la reserva.
                    @else
                        Te enviamos la confirmación por WhatsApp. Continúa allí con el pago.
                    @endif
                </p>

                @if ($hasWhatsapp)
                    <div class="result-actions" style="margin-top: 1.75rem">
                        <a class="btn btn-accent cta" href="{{ $whatsappOpenUrl }}" target="_blank" rel="noopener noreferrer">Abrir WhatsApp para continuar</a>
                    </div>
                @endif

                <h2 class="visually-hidden">Números reservados</h2>
                <div class="result-numbers num">
                    @foreach ($reservedNumbers as $num)
                        <span>{{ $num }}</span>
                    @endforeach
                </div>

                <dl class="result-facts num">
                    <div><dt>Cantidad de números</dt><dd>{{ count($reservedNumbers) }}</dd></div>
                    @if (isset($unitPrice))
                        <div><dt>Valor por número</dt><dd>${{ number_format((float) $unitPrice, 0, ',', '.') }}</dd></div>
                    @endif
                    @if (isset($totalAmount))
                        <div><dt>Total a pagar</dt><dd>${{ number_format((float) $totalAmount, 0, ',', '.') }}</dd></div>
                    @endif
                    @if (isset($reservedUntilText) && is_string($reservedUntilText) && $reservedUntilText !== '' && ! $showCountdown)
                        <div><dt>Expira la reserva</dt><dd>{{ $reservedUntilText }}</dd></div>
                    @endif
                    @if (isset($referenceLabel) && is_string($referenceLabel) && $referenceLabel !== '')
                        <div><dt>Referencia de tu compra</dt><dd>{{ $referenceLabel }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="result-stub perforated notches">
                @if ($showCountdown)
                    <div id="countdown" role="timer" aria-live="polite">
                        <p class="display num" aria-hidden="true"><span id="cd-mm">00</span>:<span id="cd-ss">00</span></p>
                        @php($untilText = (isset($reservedUntilAbsolute) && is_string($reservedUntilAbsolute) && $reservedUntilAbsolute !== '') ? ', hasta las ' . $reservedUntilAbsolute : '')
                        <p class="lead" style="margin-top: 0.5rem">para pagar y enviar el comprobante{{ $untilText }}{{ str_ends_with($untilText, '.') ? '' : '.' }}</p>
                        <p id="cd-status" style="margin-top: 1rem">Si pagas y envías el comprobante antes de que termine el tiempo, tus números quedan reservados para ti.</p>
                    </div>
                @endif
                <p style="margin-top: 1.5rem"><strong>¿No recibiste el mensaje?</strong> Revisa que sea el mismo WhatsApp con el que entraste. Si tienes dudas, escribe <strong>HOLA</strong> al bot.</p>
            </div>
        </article>
    </div>
</main>

@if ($showCountdown)
    <script>
        (function () {
            const target = new Date(@json($reservedUntilIso)).getTime();
            const mm = document.getElementById('cd-mm'), ss = document.getElementById('cd-ss'), status = document.getElementById('cd-status');
            const ctas = Array.from(document.querySelectorAll('a.cta'));
            const pad = (n) => String(n).padStart(2, '0');
            const render = () => {
                const diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
                mm.textContent = pad(Math.floor(diff / 60));
                ss.textContent = pad(diff % 60);
                if (diff > 0) return false;
                status.innerHTML = '<strong>Tu reserva venció.</strong> Los números se liberaron. Si todavía quieres participar, haz una nueva selección en el chat del bot.';
                ctas.forEach((a) => a.setAttribute('aria-disabled', 'true'));
                return true;
            };
            if (!render()) { const id = setInterval(() => { if (render()) clearInterval(id); }, 1000); }
        })();
    </script>
@endif
</body>
</html>
