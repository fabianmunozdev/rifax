<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Thumbnail {{ $ticket['code'] }}</title>
    @php
        $fmt = function (int $amount): string {
            return '$'.number_format($amount, 0, ',', '.');
        };
    @endphp
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: transparent; }
        body {
            padding: 0;
            font-family: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .thumb {
            width: 800px;
            height: 420px;
            border-radius: 28px;
            overflow: hidden;
            background:
                radial-gradient(820px 320px at 20% 0%, rgba(212, 175, 55, 0.18), transparent 60%),
                linear-gradient(135deg, #0a0810 0%, #1a0b16 100%);
            color: #fff5d4;
            position: relative;
            box-shadow: inset 0 0 0 1px rgba(212, 175, 55, 0.22);
        }
        .thumb::after {
            content: "";
            position: absolute;
            inset: 16px;
            border-radius: 22px;
            pointer-events: none;
            border: 1px solid rgba(212, 175, 55, 0.28);
        }
        .top-band {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 14px;
            background: linear-gradient(90deg, #d4af37 0%, #9a1b1b 100%);
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 230px;
            gap: 22px;
            align-items: center;
            height: 100%;
            padding: 48px 44px;
        }
        .brand {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.22em;
            color: #e8d58a;
            text-transform: uppercase;
            margin: 0 0 14px;
        }
        .title {
            margin: 0;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.05;
            color: #fff6dc;
        }
        .code-label {
            margin-top: 28px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(232, 213, 138, 0.72);
        }
        .code {
            margin-top: 6px;
            font-size: 30px;
            font-weight: 900;
            letter-spacing: 0.12em;
            color: #fff6dc;
            font-variant-numeric: tabular-nums;
        }
        .nums-label {
            margin-top: 26px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: rgba(232, 213, 138, 0.72);
            text-transform: uppercase;
        }
        .nums {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .num {
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(212, 175, 55, 0.12);
            border: 1px solid rgba(212, 175, 55, 0.26);
            color: #fff6dc;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.04em;
            font-variant-numeric: tabular-nums;
        }
        .qr-wrap {
            width: 206px;
            height: 206px;
            border-radius: 20px;
            background: #fffef6;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 44px rgba(0,0,0,0.4), inset 0 0 0 1px rgba(212, 175, 55, 0.35);
        }
        .qr-wrap img {
            width: 100%;
            height: 100%;
            display: block;
        }
        .meta {
            margin-top: 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 12px;
            font-size: 12px;
            color: rgba(245, 228, 166, 0.78);
            font-weight: 600;
        }
        .meta b {
            color: #fff6dc;
            font-weight: 800;
        }
    </style>
</head>
<body>
    <div class="thumb">
        <div class="top-band"></div>
        <div class="grid">
            <div>
                <div class="brand">{{ $brand['name'] }}</div>
                <h1 class="title">{{ $raffle['title'] }}</h1>

                <div class="code-label">Código del boleto</div>
                <div class="code">{{ $ticket['code'] }}</div>

                <div class="nums-label">Números ({{ $purchase['quantity'] }})</div>
                <div class="nums">
                    @foreach(array_slice($numbers, 0, 8) as $n)
                        <span class="num">{{ $n['value'] }}</span>
                    @endforeach
                    @if(count($numbers) > 8)
                        <span class="num">+{{ count($numbers) - 8 }}</span>
                    @endif
                </div>
            </div>

            <div>
                <div class="qr-wrap">
                    <img src="{{ $ticket['qr_data_uri'] }}" alt="QR boleto {{ $ticket['code'] }}">
                </div>
                <div class="meta">
                    <div>Total: <b>{{ $fmt((int) ($purchase['total_amount'] ?? 0)) }}</b></div>
                    <div>Sorteo: <b>{{ $ticket['boleta'] }}</b></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
