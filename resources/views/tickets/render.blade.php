<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boleto {{ $ticket['code'] }}</title>
    @php
        $fmt = function (int $amount): string {
            return '$'.number_format($amount, 0, ',', '.');
        };
        $customerName = trim((string) ($customer['name'] ?? ''));
        $customerPhone = trim((string) ($customer['phone'] ?? ''));
        $customerDoc = trim((string) ($customer['document_number'] ?? ''));
        $customerSeller = trim((string) ($customer['seller'] ?? ''));
        $customerCity = trim((string) ($customer['city'] ?? ''));
        $supportPhone = trim((string) ($brand['support_phone'] ?? ''));
    @endphp
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: transparent !important;
            color: #f3ede0;
            font-family: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body {
            padding: 4px;
        }

        .ticket {
            position: relative;
            width: 620px;
            margin: 0 auto;
            padding: 36px 30px 26px;
            border-radius: 30px;
            overflow: hidden;
            isolation: isolate;
            color: #f3ede0;
            background:
                radial-gradient(960px 420px at 50% -10%, rgba(212, 175, 55, 0.24), transparent 60%),
                radial-gradient(900px 600px at 50% 120%, rgba(127, 29, 29, 0.28), transparent 60%),
                linear-gradient(180deg, #120f1a 0%, #0a0810 50%, #120b16 100%);
            box-shadow:
                0 40px 90px rgba(0, 0, 0, 0.55),
                inset 0 0 0 1px rgba(212, 175, 55, 0.22);
        }

        .ticket::before {
            content: "";
            position: absolute;
            inset: -30% -30% -30% -30%;
            z-index: -1;
            opacity: 0.35;
            background: repeating-conic-gradient(from 0deg at 50% 24%, rgba(212, 175, 55, 0.22) 0deg 3deg, transparent 3deg 10deg, rgba(212, 175, 55, 0.16) 10deg 11deg, transparent 11deg 18deg);
            mask-image: radial-gradient(circle at 50% 22%, rgba(0, 0, 0, 1) 42%, rgba(0, 0, 0, 0.2) 68%, rgba(0, 0, 0, 0) 92%);
            -webkit-mask-image: radial-gradient(circle at 50% 22%, rgba(0, 0, 0, 1) 42%, rgba(0, 0, 0, 0.2) 68%, rgba(0, 0, 0, 0) 92%);
        }

        .ticket::after {
            content: "";
            position: absolute;
            inset: 10px;
            border-radius: 22px;
            pointer-events: none;
            border: 1px solid rgba(212, 175, 55, 0.28);
            box-shadow: inset 0 0 0 1px rgba(212, 175, 55, 0.12);
        }

        .crest {
            position: relative;
            width: 92px;
            height: 106px;
            margin: 0 auto 8px;
            background:
                radial-gradient(44px 44px at 50% 32%, #fff6cc 0%, rgba(255, 246, 204, 0) 70%),
                linear-gradient(180deg, #f6d98a 0%, #d4af37 40%, #b38c1e 72%, #8c6b15 100%);
            clip-path: polygon(50% 0%, 100% 16%, 92% 72%, 50% 100%, 8% 72%, 0% 16%);
            -webkit-clip-path: polygon(50% 0%, 100% 16%, 92% 72%, 50% 100%, 8% 72%, 0% 16%);
            filter: drop-shadow(0 10px 18px rgba(0, 0, 0, 0.35));
        }
        .crest-inner {
            position: absolute;
            inset: 10px;
            background:
                radial-gradient(38px 38px at 50% 28%, rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0) 62%),
                linear-gradient(180deg, rgba(18, 12, 4, 0.82) 0%, rgba(40, 27, 8, 0.82) 100%);
            clip-path: polygon(50% 0%, 100% 16%, 92% 72%, 50% 100%, 8% 72%, 0% 16%);
            -webkit-clip-path: polygon(50% 0%, 100% 16%, 92% 72%, 50% 100%, 8% 72%, 0% 16%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f6d98a;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.45);
        }
        .crest-star {
            width: 20px;
            height: 20px;
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            filter: drop-shadow(0 4px 8px rgba(212, 175, 55, 0.5));
        }

        .brand-wordmark {
            text-align: center;
            letter-spacing: 0.26em;
            font-weight: 800;
            font-size: 12px;
            color: rgba(232, 213, 138, 0.88);
            text-transform: uppercase;
            margin-top: 4px;
        }

        .prize {
            position: relative;
            margin: 18px -14px 0;
            padding: 16px 22px 18px;
            text-align: center;
            background:
                radial-gradient(300px 150px at 50% 120%, rgba(255, 228, 160, 0.26), transparent 70%),
                linear-gradient(180deg, #9a1b1b 0%, #7a1416 50%, #5f0f11 100%);
            box-shadow: inset 0 0 0 1px rgba(212, 175, 55, 0.4), inset 0 -18px 40px rgba(0,0,0,0.3), 0 14px 26px rgba(127,29,29,0.28);
            clip-path: polygon(0 0, 100% 0, 100% 78%, 96% 100%, 4% 100%, 0 78%);
            -webkit-clip-path: polygon(0 0, 100% 0, 100% 78%, 96% 100%, 4% 100%, 0 78%);
        }
        .prize::before, .prize::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 40px;
            height: 0;
            border-top: 2px dashed rgba(255, 228, 160, 0.38);
            transform: translateY(-50%);
        }
        .prize::before { left: 18px; }
        .prize::after { right: 18px; }
        .prize-title {
            margin: 0;
            font-size: 19px;
            font-weight: 900;
            letter-spacing: 0.3em;
            color: #ffe8ae;
            text-transform: uppercase;
            text-shadow: 0 3px 10px rgba(0,0,0,0.4);
        }
        .prize-sub {
            margin: 6px 0 0;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255, 232, 174, 0.82);
            letter-spacing: 0.04em;
        }
        .prize-prize {
            margin: 12px 0 2px;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.15;
            color: #fff7dd;
            text-shadow: 0 3px 10px rgba(0,0,0,0.45);
        }

        .section-title {
            margin: 24px 0 4px;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
        }
        .section-title h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #e8d58a;
        }
        .section-title .serie {
            font-size: 11px;
            letter-spacing: 0.2em;
            font-weight: 700;
            color: rgba(232, 213, 138, 0.74);
            text-transform: uppercase;
        }
        .serie strong {
            color: #fff0c2;
            font-weight: 800;
        }

        .numbers {
            margin: 14px 0 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 11px;
        }
        .num {
            position: relative;
            aspect-ratio: 3 / 2.3;
            border-radius: 15px;
            background:
                linear-gradient(180deg, rgba(212, 175, 55, 0.17) 0%, rgba(212, 175, 55, 0.05) 100%);
            border: 1px solid rgba(212, 175, 55, 0.34);
            box-shadow: inset 0 0 0 1px rgba(255, 236, 179, 0.08), 0 10px 22px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 2px;
        }
        .num-idx {
            position: absolute;
            top: 5px;
            left: 6px;
            width: 20px;
            height: 20px;
            border-radius: 999px;
            background: rgba(212, 175, 55, 0.16);
            color: #f5e4a6;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(212, 175, 55, 0.28);
        }
        .num-val {
            text-align: center;
            font-weight: 900;
            font-size: 19px;
            letter-spacing: 0.06em;
            color: #fff5d4;
            text-shadow: 0 2px 8px rgba(0,0,0,0.4);
            font-variant-numeric: tabular-nums;
        }
        .num-txt {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.22em;
            color: rgba(245, 228, 166, 0.72);
            font-weight: 700;
        }

        .info {
            margin: 24px 0 0;
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr;
            gap: 11px;
        }
        .info-box {
            border-radius: 15px;
            padding: 11px 13px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(212, 175, 55, 0.19);
        }
        .info-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(232, 213, 138, 0.72);
        }
        .info-value {
            margin-top: 5px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.3;
            color: #fff6dc;
        }
        .info-value.money {
            font-size: 16px;
            color: #ffe8ae;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .prizes {
            margin: 24px 0 0;
            padding: 15px 15px 13px;
            border-radius: 17px;
            background:
                linear-gradient(180deg, rgba(212, 175, 55, 0.09), rgba(212, 175, 55, 0.02));
            border: 1px solid rgba(212, 175, 55, 0.23);
        }
        .prizes h4 {
            margin: 0 0 11px;
            font-size: 11px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            font-weight: 800;
            color: #e8d58a;
            text-align: center;
        }
        .prize-row {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 16px;
            align-items: center;
            padding: 11px 12px;
            border-radius: 13px;
            background: rgba(127, 29, 29, 0.2);
            border: 1px solid rgba(212, 175, 55, 0.24);
        }
        .prize-tier {
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #ffe8ae;
            padding: 7px 9px;
            border-radius: 11px;
            background: linear-gradient(180deg, #9a1b1b, #6a1113);
            border: 1px solid rgba(255, 232, 174, 0.24);
        }
        .prize-detail {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .prize-detail strong {
            font-size: 15px;
            color: #fff6dc;
            font-weight: 800;
            line-height: 1.25;
        }
        .prize-detail span {
            font-size: 12px;
            color: rgba(245, 228, 166, 0.78);
            font-weight: 600;
        }

        .fine-print {
            margin: 19px 0 0;
            font-size: 11px;
            line-height: 1.6;
            color: rgba(227, 221, 206, 0.78);
            padding: 0 2px;
        }
        .divider {
            margin: 14px 0;
            height: 0;
            border: none;
            border-top: 1px dashed rgba(212, 175, 55, 0.3);
        }
        .buyer {
            margin: 6px 0 0;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 13px 18px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .field-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(232, 213, 138, 0.72);
        }
        .field-value {
            font-size: 12px;
            font-weight: 600;
            color: #fff5d4;
            min-height: 17px;
            line-height: 1.4;
            padding-bottom: 5px;
            border-bottom: 1px dashed rgba(212, 175, 55, 0.28);
            word-break: break-word;
        }
        .field-value:empty::after {
            content: "—";
            color: rgba(245, 228, 166, 0.35);
        }

        .foot {
            margin: 24px -6px -4px;
            padding: 17px 15px 7px;
            border-radius: 17px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(0,0,0,0.18));
            border: 1px solid rgba(212, 175, 55, 0.17);
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            align-items: center;
        }
        .qr-box {
            width: 134px;
            height: 134px;
            border-radius: 15px;
            background: #fffef6;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 24px rgba(0,0,0,0.35), inset 0 0 0 1px rgba(212, 175, 55, 0.38);
        }
        .qr-box img {
            width: 100%;
            height: 100%;
            display: block;
        }
        .foot-right {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
        }
        .foot-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .foot-brand b {
            letter-spacing: 0.28em;
            font-size: 15px;
            font-weight: 900;
            color: #fff0c2;
            text-transform: uppercase;
        }
        .foot-brand span {
            font-size: 11px;
            color: rgba(232, 213, 138, 0.74);
            font-weight: 600;
        }
        .ticket-code {
            font-size: 11px;
            color: rgba(245, 228, 166, 0.88);
            letter-spacing: 0.06em;
            font-weight: 600;
        }
        .ticket-code strong {
            color: #fff6dc;
            letter-spacing: 0.16em;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .ticket-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 14px;
            font-size: 10.5px;
            color: rgba(245, 228, 166, 0.76);
            font-weight: 600;
        }
        .ticket-meta b {
            color: #fff6dc;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .verification-url {
            font-size: 10.5px;
            color: rgba(227, 221, 206, 0.82);
            font-weight: 600;
            line-height: 1.45;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <section class="ticket" aria-label="Boleto {{ $ticket['code'] }}">
        <div class="crest" aria-hidden="true">
            <svg class="crest-star" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2.5l2.6 5.5 6.1.7-4.5 4.2 1.2 6L12 16l-5.4 2.9 1.2-6L3.3 8.7l6.1-.7L12 2.5z" fill="#ffe8ae" stroke="#b38c1e" stroke-width="0.6" stroke-linejoin="round"/>
            </svg>
            <div class="crest-inner">{{ strtoupper(substr((string) ($brand['name'] ?? 'R'), 0, 1)) }}</div>
        </div>

        <div class="brand-wordmark">{{ $brand['name'] }}</div>

        <div class="prize">
            <h2 class="prize-title">Premio Mayor</h2>
            <p class="prize-sub">Ganador según resultado oficial del sorteo</p>
            <p class="prize-prize">{{ $raffle['title'] }}</p>
        </div>

        <div class="section-title">
            <h3>Sus {{ $purchase['quantity'] }} número(s)</h3>
            <div class="serie">Serie <strong>{{ $ticket['serie'] }}</strong> · Boleta <strong>{{ $ticket['boleta'] }}</strong></div>
        </div>

        <div class="numbers">
            @foreach($numbers as $n)
                <div class="num">
                    <span class="num-idx">{{ $n['index'] }}</span>
                    <div class="num-val">{{ $n['value'] }}</div>
                    <div class="num-txt">{{ $brand['name'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="info">
            <div class="info-box">
                <div class="info-label">Lotería</div>
                <div class="info-value">{{ $raffle['draw_reference'] }}</div>
            </div>
            <div class="info-box">
                <div class="info-label">Fecha Sorteo</div>
                <div class="info-value">{{ $raffle['draw_date_text'] }}</div>
            </div>
            <div class="info-box">
                <div class="info-label">Valor Total</div>
                <div class="info-value money">{{ $fmt((int) ($purchase['total_amount'] ?? 0)) }}</div>
            </div>
        </div>

        <div class="prizes">
            <h4>Detalle del premio</h4>
            <div class="prize-row">
                <div class="prize-tier">1er Lugar</div>
                <div class="prize-detail">
                    <strong>{{ $raffle['title'] }}</strong>
                    <span>Entrega oficial después de la validación del número ganador.</span>
                </div>
            </div>
        </div>

        <p class="fine-print">
            {{ $raffle['description'] }} Este boleto es válido únicamente para el sorteo indicado y ampara los números aquí registrados a nombre del titular. Conserva este comprobante; en caso de premio, será requerido junto con la cédula de ciudadanía del comprador. Soporte: {{ $supportPhone !== '' ? $supportPhone : 'Canales oficiales de la marca' }}.
        </p>

        <hr class="divider" />

        <div class="buyer">
            <div class="field">
                <span class="field-label">Nombre</span>
                <span class="field-value">{{ $customerName }}</span>
            </div>
            <div class="field">
                <span class="field-label">Celular</span>
                <span class="field-value">{{ $customerPhone }}</span>
            </div>
            <div class="field">
                <span class="field-label">Documento</span>
                <span class="field-value">{{ $customerDoc }}</span>
            </div>
            <div class="field">
                <span class="field-label">Ciudad</span>
                <span class="field-value">{{ $customerCity }}</span>
            </div>
            <div class="field">
                <span class="field-label">Vendedor</span>
                <span class="field-value">{{ $customerSeller }}</span>
            </div>
            <div class="field">
                <span class="field-label">Generado</span>
                <span class="field-value">{{ $ticket['generated_at'] }}</span>
            </div>
        </div>

        <div class="foot">
            <div class="qr-box">
                <img src="{{ $ticket['qr_data_uri'] }}" alt="QR de verificación del boleto {{ $ticket['code'] }}">
            </div>
            <div class="foot-right">
                <div class="foot-brand">
                    <b>{{ $brand['name'] }}</b>
                    <span>QR · Verificación pública</span>
                </div>
                <div class="ticket-code">Código del boleto: <strong>{{ $ticket['code'] }}</strong></div>
                <div class="ticket-meta">
                    <div>Sorteo: <b>{{ $ticket['boleta'] }}</b></div>
                    <div>Serie: <b>{{ $ticket['serie'] }}</b></div>
                    <div>Números: <b>{{ $purchase['quantity'] }}</b></div>
                    <div>Valor: <b>{{ $fmt((int) ($purchase['total_amount'] ?? 0)) }}</b></div>
                </div>
                <div class="verification-url">{{ $ticket['public_url'] }}</div>
            </div>
        </div>
    </section>
</body>
</html>
