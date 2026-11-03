{{--
    Premios anticipados de una rifa. Se juegan antes del premio mayor y el ganador
    sigue participando por el premio mayor.
    Variables: $raffle (con earlyPrizes cargados), $headingTag y $collapsible (opcionales).
--}}
@php
    $earlyPrizes = $raffle->earlyPrizes;
    $headingTag = $headingTag ?? 'h2';
    $collapsible = $collapsible ?? false;
    $nextPrize = $earlyPrizes->first(fn ($prize) => $prize->result_published_at === null);
    $formatDraw = function (\App\Models\RaffleEarlyPrize $prize): string {
        $date = $prize->draw_date->copy()->locale('es')->translatedFormat('l j \d\e F');
        if (filled($prize->draw_time)) {
            $at = $prize->drawsAt();
            $date .= ', ' . $at->format('g:i') . ' ' . ($at->format('A') === 'AM' ? 'a. m.' : 'p. m.');
        }

        return $date;
    };
@endphp
@if ($earlyPrizes->isNotEmpty())
    @if ($collapsible)
    <details class="early-prizes early-prizes-fold" id="premios-anticipados">
        <summary>
            <span class="title">Premios anticipados ({{ $earlyPrizes->count() }})</span>
            @if ($nextPrize)
                <span class="muted">Próximo: {{ $nextPrize->title }}, {{ $nextPrize->draw_date->copy()->locale('es')->translatedFormat('j \d\e F') }}</span>
            @endif
        </summary>
    @else
    <div class="early-prizes" id="premios-anticipados">
        <{{ $headingTag }} class="head">Premios anticipados</{{ $headingTag }}>
    @endif
        <p class="lead early-prizes-lead">
            Antes del premio mayor se juegan {{ $earlyPrizes->count() === 1 ? 'este premio' : 'estos ' . $earlyPrizes->count() . ' premios' }}
            con los mismos números. Si ganas uno, sigues jugando por el premio mayor.
        </p>
        <ol class="sheet-list early-prizes-list">
            @foreach ($earlyPrizes as $prize)
                @php $status = $prize->status(); @endphp
                <li class="sheet-item early-prize is-{{ $status }}">
                    <div class="sheet-thumb">
                        @if ($prize->imageUrl())
                            <img src="{{ $prize->imageUrl() }}" alt="{{ $prize->title }}" loading="lazy" width="112" height="112">
                        @else
                            <span class="sheet-thumb-number num">{{ $loop->iteration }}</span>
                        @endif
                    </div>
                    <div class="sheet-line">
                        <h3 class="title">{{ $prize->title }}</h3>
                        @if ($status === \App\Models\RaffleEarlyPrize::STATUS_WON)
                            <span class="sheet-price num">{{ $prize->result_number }}</span>
                        @endif
                    </div>
                    @if (filled($prize->description))
                        <p class="sheet-desc">{!! nl2br(e($prize->description)) !!}</p>
                    @endif
                    <p class="sheet-meta">
                        {{ $prize->lottery_name }}@if ($prize->lottery_draw_number), sorteo {{ $prize->lottery_draw_number }}@endif
                        · {{ $formatDraw($prize) }}
                    </p>
                    <p class="sheet-status">
                        @switch($status)
                            @case(\App\Models\RaffleEarlyPrize::STATUS_WON)
                                Ganó el {{ $prize->result_number }}: {{ $prize->publicWinnerLabel() }}
                                @break
                            @case(\App\Models\RaffleEarlyPrize::STATUS_VOID)
                                Cayó el {{ $prize->result_number }}, que no se vendió. Premio desierto.
                                @break
                            @default
                                Por jugar
                        @endswitch
                        @if ($prize->result_published_at && $prize->lottery_reference_url)
                            · <a href="{{ $prize->lottery_reference_url }}" target="_blank" rel="noopener noreferrer">Ver resultado oficial</a>
                        @endif
                    </p>
                </li>
            @endforeach
        </ol>
    @if ($collapsible)
    </details>
    <script>
        // El enlace del encabezado abre el bloque plegado.
        document.querySelectorAll('a[href="#premios-anticipados"]').forEach((link) => link.addEventListener('click', () => {
            document.getElementById('premios-anticipados').open = true;
        }));
    </script>
    @else
    </div>
    @endif
@endif
