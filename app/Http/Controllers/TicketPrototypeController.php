<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Ticket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TicketPrototypeController extends Controller
{
    public function show(Request $request): View
    {
        $realTicket = null;
        $lookup = trim((string) $request->query('ticket', ''));
        if ($lookup !== '') {
            $realTicket = Ticket::query()
                ->where('code', $lookup)
                ->orWhere('verification_token', $lookup)
                ->first();
            if ($realTicket !== null) {
                $realTicket->loadMissing(['purchase.customer', 'purchase.raffle', 'purchase.numbers']);
            }
        }

        if ($realTicket !== null) {
            $payload = $this->buildPayloadFromTicket($realTicket);
        } else {
            $payload = $this->buildMockPayload();
        }

        return view('tickets.prototype', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayloadFromTicket(Ticket $ticket): array
    {
        $company = CompanySetting::query()->first();
        $purchase = $ticket->purchase;
        $raffle = $purchase?->raffle;
        $customer = $purchase?->customer;
        $numbers = $purchase?->numbers
            ?->pluck('number')
            ?->filter()
            ?->values() ?? new Collection();

        $publicUrl = $ticket->public_url ?: route('tickets.show', ['verificationToken' => $ticket->verification_token], true);

        $numbersFormatted = $numbers->map(fn (mixed $number, int $index): array => [
            'index' => $index + 1,
            'value' => $this->padNumber((string) $number, (int) ($raffle?->number_digits ?? 4)),
        ])->values()->all();

        return [
            'brand' => [
                'name' => (string) ($company?->trade_name ?: 'Rifax'),
                'support_phone' => (string) ($company?->support_phone ?: ''),
                'bot_phone' => (string) ($company?->whatsapp_bot_phone ?: ''),
            ],
            'raffle' => [
                'title' => (string) ($purchase?->raffle_title_snapshot ?: $raffle?->title ?: 'Rifa'),
                'description' => (string) ($raffle?->description ?: 'Boleto digital de participación validado por Rifax.'),
                'draw_reference' => $this->buildLotteryReference($raffle),
                'draw_date_text' => $this->formatDrawDateTime($raffle),
                'price_per_number' => (int) round((float) ($raffle?->price_per_number ?? 0)),
            ],
            'ticket' => [
                'code' => (string) $ticket->code,
                'generated_at' => $this->formatTicketGenerationDate($ticket->generated_at),
                'serie' => (string) ($ticket->metadata_json['serie'] ?? strtoupper(substr((string) $ticket->verification_token, 0, 1) ?: 'A')),
                'boleta' => (string) ($ticket->metadata_json['boleta'] ?? str_pad((string) ($ticket->id ?? '1'), 6, '0', STR_PAD_LEFT)),
                'public_url' => $publicUrl,
                'qr_data_uri' => $this->buildQrDataUri($publicUrl),
            ],
            'purchase' => [
                'quantity' => (int) ($purchase?->quantity ?? $numbers->count() ?: 1),
                'total_amount' => (int) round((float) ($purchase?->total_amount ?? 0)),
            ],
            'customer' => [
                'name' => (string) ($customer?->name ?: ''),
                'phone' => (string) ($customer?->phone ?: ''),
                'document_number' => (string) ($customer?->document_number ?: ''),
                'seller' => (string) ($purchase?->metadata_json['seller_name'] ?? ''),
                'city' => (string) ($purchase?->metadata_json['city'] ?? ''),
            ],
            'numbers' => $numbersFormatted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMockPayload(): array
    {
        $brandName = 'Rifax';
        $supportPhone = '+57 300 123 4567';
        $botPhone = '+57 300 987 6543';
        $raffleTitle = 'Rifa Casa Campestre Premium';
        $raffleDescription = 'Boleto digital oficial de participación. Al terminar el sorteo, el número ganador será publicado en nuestra página oficial y el ganador será contactado directamente.';
        $lotteryText = 'RIFA';
        $lotteryName = 'Casa Campestre';
        $drawNumber = '0014';
        $drawReference = trim("{$lotteryText} {$lotteryName}")." sorteo #{$drawNumber}";
        $pricePerNumber = 4000;
        $quantity = 8;
        $totalAmount = $pricePerNumber * $quantity;
        $ticketCode = 'RFX-7A3F9D2K';
        $serie = 'B';
        $boleta = '000318';
        $publicUrl = 'https://rifax.fabianmunoz.dev/tickets/demo-7A3F9D2K';
        $generatedAt = $this->formatTicketGenerationDate(Carbon::now());
        $drawDate = Carbon::now()->addDays(14)->hour(22)->minute(0)->second(0);
        $drawDateText = ucfirst($drawDate->setTimezone(config('app.timezone'))->locale('es')->translatedFormat('j \d\e F \d\e Y \a \l\a\s g:i A'));

        $mockNumbers = ['0147', '0812', '1234', '2056', '3089', '4421', '6503', '8875'];
        $numbersFormatted = collect($mockNumbers)->map(fn (string $n, int $i): array => [
            'index' => $i + 1,
            'value' => $n,
        ])->values()->all();

        return [
            'brand' => [
                'name' => $brandName,
                'support_phone' => $supportPhone,
                'bot_phone' => $botPhone,
            ],
            'raffle' => [
                'title' => $raffleTitle,
                'description' => $raffleDescription,
                'draw_reference' => $drawReference,
                'draw_date_text' => $drawDateText,
                'price_per_number' => $pricePerNumber,
            ],
            'ticket' => [
                'code' => $ticketCode,
                'generated_at' => $generatedAt,
                'serie' => $serie,
                'boleta' => $boleta,
                'public_url' => $publicUrl,
                'qr_data_uri' => $this->buildQrDataUri($publicUrl),
            ],
            'purchase' => [
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
            ],
            'customer' => [
                'name' => 'Carlos Andrés Méndez',
                'phone' => '573101234567',
                'document_number' => '1.023.456.789',
                'seller' => 'Laura Jiménez',
                'city' => 'Bogotá, D.C.',
            ],
            'numbers' => $numbersFormatted,
        ];
    }

    protected function buildLotteryReference(mixed $raffle): string
    {
        if ($raffle === null) {
            return 'Pendiente por definir';
        }

        $segments = [];
        $lotteryText = trim((string) ($raffle->lottery_text ?? ''));
        $lotteryName = trim((string) ($raffle->lottery_name ?? ''));
        $drawNumber = trim((string) ($raffle->lottery_draw_number ?? ''));

        if ($lotteryText !== '') {
            $segments[] = $lotteryText;
        }

        if ($lotteryName !== '') {
            $segments[] = $lotteryName;
        }

        $reference = trim(implode(' ', $segments));

        if ($drawNumber !== '') {
            $reference = trim($reference.' sorteo #'.$drawNumber);
        }

        return $reference !== '' ? $reference : 'Pendiente por definir';
    }

    protected function formatDrawDateTime(mixed $raffle): string
    {
        if ($raffle === null || $raffle->draw_date === null || blank((string) ($raffle->draw_time ?? ''))) {
            return 'Pendiente por definir';
        }

        $drawAt = Carbon::parse(
            $raffle->draw_date->format('Y-m-d').' '.$raffle->draw_time,
            config('app.timezone'),
        )->locale('es');

        return ucfirst($drawAt->translatedFormat('j \d\e F \d\e Y \a \l\a\s g:i A'));
    }

    protected function formatTicketGenerationDate(mixed $generatedAt): string
    {
        $reference = ($generatedAt ?? now())->setTimezone(config('app.timezone'))->locale('es');

        return ucfirst($reference->translatedFormat('j \d\e F \d\e Y, g:i A'));
    }

    protected function buildQrDataUri(string $value): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(512),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $svg = $writer->writeString($value);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    protected function padNumber(string $number, int $digits): string
    {
        $clean = preg_replace('/\D+/', '', $number) ?: $number;

        return str_pad($clean, max(2, $digits), '0', STR_PAD_LEFT);
    }
}
