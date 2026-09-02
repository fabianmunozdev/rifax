<?php

namespace App\Actions\Tickets;

use App\Models\CompanySetting;
use App\Models\Ticket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Throwable;

class RenderTicketAssetsAction
{
    public function execute(Ticket $ticket): Ticket
    {
        $ticket->loadMissing(['purchase.customer', 'purchase.raffle', 'purchase.numbers']);

        $company = CompanySetting::query()->first();
        $purchase = $ticket->purchase;
        $raffle = $purchase?->raffle;
        $customer = $purchase?->customer;
        $numbers = $purchase?->numbers
            ?->pluck('number')
            ?->filter()
            ?->values() ?? new Collection();

        $version = max(1, (int) $ticket->version);
        $directory = 'tickets/'.$ticket->verification_token;

        $payload = $this->buildPayload($ticket, $company, $raffle, $customer, $purchase, $numbers);

        $renderedWithBrowsershot = false;
        try {
            [$ticketContents, $thumbnailContents, $imageExt, $thumbExt] = $this->renderWithBrowsershot($payload);
            $renderedWithBrowsershot = true;
        } catch (Throwable $e) {
            Log::warning('Ticket browsershot render failed, falling back to SVG.', [
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->code,
                'error' => $e->getMessage(),
            ]);

            [$ticketContents, $thumbnailContents, $imageExt, $thumbExt] = [
                $this->buildTicketSvg($ticket, $company, $numbers),
                $this->buildThumbnailSvg($ticket, $company, $numbers),
                'svg',
                'svg',
            ];
        }

        $imagePath = $directory.'/ticket-v'.$version.'.'.$imageExt;
        $thumbnailPath = $directory.'/ticket-thumb-v'.$version.'.'.$thumbExt;

        try {
            $putOk = true;
            if (filled($ticketContents)) {
                $putOk = Storage::disk('public')->put($imagePath, $ticketContents) && $putOk;
            } else {
                $putOk = false;
                Log::warning('Ticket asset ticket contents is empty, cannot write.', [
                    'ticket_id' => $ticket->id,
                    'ticket_code' => $ticket->code,
                ]);
            }
            if (filled($thumbnailContents)) {
                $putOk = Storage::disk('public')->put($thumbnailPath, $thumbnailContents) && $putOk;
            } else {
                $putOk = false;
                Log::warning('Ticket asset thumbnail contents is empty, cannot write.', [
                    'ticket_id' => $ticket->id,
                    'ticket_code' => $ticket->code,
                ]);
            }

            if (! $putOk) {
                throw new \RuntimeException('Storage::disk public put returned false or empty contents.');
            }

            $persistedImage = $imagePath;
            $persistedThumb = $thumbnailPath;
        } catch (Throwable $e) {
            Log::warning('Ticket asset storage write failed, falling back to inline SVG paths on best-effort.', [
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->code,
                'error' => $e->getMessage(),
            ]);

            $ticketSvgFallback = $this->buildTicketSvg($ticket, $company, $numbers);
            $thumbSvgFallback = $this->buildThumbnailSvg($ticket, $company, $numbers);
            $fallbackImagePath = $directory.'/ticket-v'.$version.'.svg';
            $fallbackThumbPath = $directory.'/ticket-thumb-v'.$version.'.svg';

            try {
                Storage::disk('public')->put($fallbackImagePath, $ticketSvgFallback);
                Storage::disk('public')->put($fallbackThumbPath, $thumbSvgFallback);
                $persistedImage = $fallbackImagePath;
                $persistedThumb = $fallbackThumbPath;
            } catch (Throwable $e2) {
                Log::error('Ticket SVG fallback storage write also failed; persisting previous paths if any.', [
                    'ticket_id' => $ticket->id,
                    'ticket_code' => $ticket->code,
                    'previous_image_path' => $ticket->getOriginal('image_path'),
                    'previous_thumbnail_path' => $ticket->getOriginal('thumbnail_path'),
                    'svg_fallback_error' => $e2->getMessage(),
                    'primary_error' => $e->getMessage(),
                ]);
                $persistedImage = $ticket->getOriginal('image_path') ?? $ticket->image_path ?? null;
                $persistedThumb = $ticket->getOriginal('thumbnail_path') ?? $ticket->thumbnail_path ?? null;
            }
        }

        $persistedImage = filled($persistedImage) ? $persistedImage : $ticket->getOriginal('image_path') ?? $ticket->image_path;
        $persistedThumb = filled($persistedThumb) ? $persistedThumb : $ticket->getOriginal('thumbnail_path') ?? $ticket->thumbnail_path;

        $ticket->forceFill([
            'image_path' => $persistedImage,
            'thumbnail_path' => $persistedThumb,
        ])->save();

        Log::debug('Ticket assets rendered.', [
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->code,
            'version' => $version,
            'image_path' => $persistedImage,
            'thumbnail_path' => $persistedThumb,
            'renderer' => $renderedWithBrowsershot ? 'browsershot-png' : 'legacy-svg',
            'storage_write_ok' => isset($putOk) ? $putOk : null,
        ]);

        return $ticket->fresh() ?? $ticket;
    }

    /**
     * @return array{0:string,1:string,2:string,3:string}
     */
    protected function renderWithBrowsershot(array $payload): array
    {
        $ticketHtml = view('tickets.render', $payload)->render();
        $thumbHtml = view('tickets.render-thumbnail', $payload)->render();

        $nodeBinary = $this->detectNodeBinaryPath();
        $npmBinary = $this->detectNpmBinaryPath();
        $chromeBinary = $this->detectChromeBinaryPath();

        Log::debug('Browsershot runtime detect.', [
            'ticket_code' => $payload['ticket']['code'] ?? null,
            'node' => $nodeBinary,
            'npm' => $npmBinary,
            'chrome' => $chromeBinary,
            'posix_user' => function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? null) : null,
            'home_env' => getenv('HOME'),
        ]);

        $browsershot = Browsershot::html($ticketHtml)
            ->waitUntilNetworkIdle()
            ->setScreenshotType('png')
            ->deviceScaleFactor(2)
            ->showBackground()
            ->windowSize(700, 1400)
            ->ignoreHttpsErrors();

        if (filled($nodeBinary)) {
            $browsershot->setNodeBinary($nodeBinary);
        }
        if (filled($npmBinary)) {
            $browsershot->setNpmBinary($npmBinary);
        }
        if (filled($chromeBinary)) {
            $browsershot->setChromePath($chromeBinary);
        }

        $booleanFlags = [
            'no-sandbox',
            'disable-setuid-sandbox',
            'disable-dev-shm-usage',
            'disable-gpu',
            'hide-scrollbars',
        ];
        $valuedFlags = [
            'font-render-hinting' => 'none',
        ];
        $browsershot->addChromiumArguments($booleanFlags);
        foreach ($valuedFlags as $name => $value) {
            $browsershot->addChromiumArguments([$name => $value]);
        }

        $ticketPng = $browsershot->select('.ticket')->screenshot();

        $thumbShot = Browsershot::html($thumbHtml)
            ->waitUntilNetworkIdle()
            ->setScreenshotType('png')
            ->deviceScaleFactor(2)
            ->showBackground()
            ->windowSize(860, 480)
            ->ignoreHttpsErrors();

        if (filled($nodeBinary)) {
            $thumbShot->setNodeBinary($nodeBinary);
        }
        if (filled($npmBinary)) {
            $thumbShot->setNpmBinary($npmBinary);
        }
        if (filled($chromeBinary)) {
            $thumbShot->setChromePath($chromeBinary);
        }
        $thumbShot->addChromiumArguments($booleanFlags);
        foreach ($valuedFlags as $name => $value) {
            $thumbShot->addChromiumArguments([$name => $value]);
        }

        $thumbPng = $thumbShot->select('.thumb')->screenshot();

        return [$ticketPng, $thumbPng, 'png', 'png'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(
        Ticket $ticket,
        mixed $company,
        mixed $raffle,
        mixed $customer,
        mixed $purchase,
        Collection $numbers,
    ): array {
        $publicUrl = $ticket->public_url ?: url('/tickets/'.$ticket->verification_token);
        $metadataJson = $ticket->metadata_json ?? null;
        if (! is_array($metadataJson)) {
            $metadataJson = [];
        }
        $purchaseMetadata = is_array($purchase?->metadata_json ?? null) ? $purchase->metadata_json : [];

        $numbersFormatted = $numbers->map(function (mixed $number, int $index) use ($raffle): array {
            return [
                'index' => $index + 1,
                'value' => $this->padNumber((string) $number, (int) ($raffle?->number_digits ?? 4)),
            ];
        })->values()->all();

        $quantity = (int) ($purchase?->quantity ?? max(1, count($numbersFormatted)));
        $unitPrice = (int) round((float) ($raffle?->price_per_number ?? 0));
        $totalAmount = (int) round((float) ($purchase?->total_amount ?? ($unitPrice * $quantity)));

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
                'price_per_number' => $unitPrice,
            ],
            'ticket' => [
                'code' => (string) $ticket->code,
                'generated_at' => $this->formatTicketGenerationDate($ticket->generated_at),
                'serie' => (string) ($metadataJson['serie'] ?? strtoupper(substr((string) $ticket->verification_token, 0, 1) ?: 'A')),
                'boleta' => (string) ($metadataJson['boleta'] ?? str_pad((string) ($ticket->id ?? '1'), 6, '0', STR_PAD_LEFT)),
                'public_url' => $publicUrl,
                'qr_data_uri' => $this->buildQrDataUri($publicUrl),
            ],
            'purchase' => [
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
            ],
            'customer' => [
                'name' => (string) ($customer?->name ?? ''),
                'phone' => (string) ($customer?->phone ?? ''),
                'document_number' => (string) ($customer?->document_number ?? ''),
                'seller' => (string) ($purchaseMetadata['seller_name'] ?? ''),
                'city' => (string) ($purchaseMetadata['city'] ?? ''),
            ],
            'numbers' => $numbersFormatted,
        ];
    }

    protected function buildTicketSvg(Ticket $ticket, ?CompanySetting $company, Collection $numbers): string
    {
        $purchase = $ticket->purchase;
        $raffle = $purchase?->raffle;
        $brandName = $this->escape($company?->trade_name ?: 'Rifax');
        $raffleTitle = $this->escape($purchase?->raffle_title_snapshot ?: $raffle?->title ?: 'Rifa');
        $raffleDescription = $raffle?->description ?: 'Boleto digital de participación validado por Rifax.';
        $supportPhone = $this->escape($company?->support_phone ?: '');
        $drawReference = $this->escape($this->buildLotteryReference($raffle));
        $drawDateTime = $this->escape($this->formatDrawDateTime($raffle));
        $ticketCode = $this->escape($ticket->code);
        $generatedAt = $this->escape($this->formatTicketGenerationDate($ticket->generated_at));
        $descriptionLines = $this->wrapTextForSvg($raffleDescription, 92);
        $urlLines = $this->wrapTextForSvg((string) ($ticket->public_url ?: url('/tickets/'.$ticket->verification_token)), 94);
        $numberCardsSvg = '';
        $ticketUrlSvg = '';
        $perforationSvg = '';
        $descriptionSvg = [];

        $headerTop = 74;
        $titleY = 128;
        $descriptionStartY = 172;
        $descriptionLineHeight = 24;
        $descriptionBottomY = $descriptionStartY + ((max(1, count($descriptionLines)) - 1) * $descriptionLineHeight);
        $lotteryLabelY = $descriptionBottomY + 42;
        $lotteryValueY = $lotteryLabelY + 30;
        $drawDateLabelY = $lotteryValueY + 48;
        $drawDateValueY = $drawDateLabelY + 30;
        $gridTop = $drawDateValueY + 66;
        $gridColumns = 4;
        $cardWidth = 292;
        $cardHeight = 134;
        $cardGapX = 20;
        $cardGapY = 20;
        $gridRows = max(1, (int) ceil(max(1, $numbers->count()) / $gridColumns));
        $gridHeight = ($gridRows * $cardHeight) + (($gridRows - 1) * $cardGapY);
        $footerTop = $gridTop + $gridHeight + 54;
        $metaFooterLabelY = $footerTop;
        $metaFooterValueY = $footerTop + 34;
        $qrHeadingY = $footerTop + 94;
        $qrCardY = $footerTop + 118;
        $qrImageY = $qrCardY + 28;
        $urlHeadingY = $qrCardY + 350;
        $urlTextStartY = $urlHeadingY + 28;
        $supportLabelY = $urlTextStartY + (count($urlLines) * 20) + 30;
        $supportValueY = $supportLabelY + 32;
        $canvasHeight = $supportValueY + 74;
        $contentHeight = $canvasHeight - 88;

        foreach ($numbers->values() as $index => $number) {
            $column = $index % $gridColumns;
            $row = intdiv($index, $gridColumns);
            $x = 90 + ($column * ($cardWidth + $cardGapX));
            $y = $gridTop + ($row * ($cardHeight + $cardGapY));
            $innerX = $x + 14;
            $innerY = $y + 12;
            $innerWidth = $cardWidth - 28;
            $innerHeight = $cardHeight - 24;
            $numberTextX = $x + 33;
            $numberTextY = $y + (int) round($cardHeight / 2);
            $ticketLabelX = $x + 92;
            $ticketLabelY = $y + 78;
            $brandTextX = $x + 96;
            $brandTextY = $y + 101;
            $leftCutoutTopY = $y + 22;
            $leftCutoutBottomY = $y + $cardHeight - 22;
            $rightCutoutX = $x + $cardWidth;
            $dividerX = $x + 62;
            $dividerTopY = $y + 18;
            $dividerBottomY = $y + $cardHeight - 18;
            $numberValue = $this->padNumber((string) $number, (int) ($raffle?->number_digits ?? 4));
            $numberValueEscaped = $this->escape($numberValue);

            $numberCardsSvg .= <<<SVG
  <g>
    <rect x="{$x}" y="{$y}" width="{$cardWidth}" height="{$cardHeight}" rx="18" fill="#F95B33" />
    <rect x="{$innerX}" y="{$innerY}" width="{$innerWidth}" height="{$innerHeight}" rx="16" fill="none" stroke="#C93718" stroke-width="3" />
    <circle cx="{$x}" cy="{$leftCutoutTopY}" r="10" fill="#FFFDF8" />
    <circle cx="{$x}" cy="{$leftCutoutBottomY}" r="10" fill="#FFFDF8" />
    <circle cx="{$rightCutoutX}" cy="{$leftCutoutTopY}" r="10" fill="#FFFDF8" />
    <circle cx="{$rightCutoutX}" cy="{$leftCutoutBottomY}" r="10" fill="#FFFDF8" />
    <line x1="{$dividerX}" y1="{$dividerTopY}" x2="{$dividerX}" y2="{$dividerBottomY}" stroke="#C93718" stroke-width="2" />
    <text transform="translate({$numberTextX} {$numberTextY}) rotate(-90)" text-anchor="middle" dominant-baseline="middle" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="700" fill="#111827">{$numberValueEscaped}</text>
    <text x="{$ticketLabelX}" y="{$ticketLabelY}" font-family="Arial Black, Inter, Arial, sans-serif" font-size="40" font-weight="900" fill="#111827">TICKET</text>
    <text x="{$brandTextX}" y="{$brandTextY}" font-family="Inter, Arial, sans-serif" font-size="14" font-weight="700" letter-spacing="3" fill="#7C2D12">{$brandName}</text>
  </g>
SVG;
        }

        foreach ($urlLines as $index => $line) {
            $ticketUrlSvg .= '<text x="90" y="'.($urlTextStartY + ($index * 20)).'" font-family="Inter, Arial, sans-serif" font-size="14" fill="#334155">'
                .$this->escape($line)
                .'</text>';
        }

        foreach ($descriptionLines as $index => $line) {
            $descriptionSvg[] = '<text x="90" y="'.($descriptionStartY + ($index * $descriptionLineHeight)).'" font-family="Inter, Arial, sans-serif" font-size="20" fill="#475569">'
                .$this->escape($line)
                .'</text>';
        }

        for ($offset = 118; $offset <= $contentHeight - 38; $offset += 54) {
            $perforationSvg .= '<circle cx="44" cy="'.$offset.'" r="12" fill="#FFFDF8" />';
            $perforationSvg .= '<circle cx="1356" cy="'.$offset.'" r="12" fill="#FFFDF8" />';
        }

        $descriptionBlockSvg = implode("\n  ", $descriptionSvg ?? []);
        $numbersHeadingY = $gridTop - 16;
        $publicUrlEscapedQr = $this->buildQrDataUri((string) ($ticket->public_url ?: url('/tickets/'.$ticket->verification_token)));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="{$canvasHeight}" viewBox="0 0 1400 {$canvasHeight}" fill="none">
  <defs>
  </defs>
  <rect width="1400" height="{$canvasHeight}" rx="40" fill="#FFFDF8" />
  <rect x="56" y="44" width="1288" height="{$contentHeight}" rx="30" fill="#FFFCF7" />
  {$perforationSvg}
  <text x="90" y="{$headerTop}" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#64748B">{$brandName}</text>
  <text x="90" y="{$titleY}" font-family="Inter, Arial, sans-serif" font-size="46" font-weight="800" fill="#0F172A">{$raffleTitle}</text>
  {$descriptionBlockSvg}
  <text x="90" y="{$lotteryLabelY}" font-family="Inter, Arial, sans-serif" font-size="16" font-weight="700" fill="#94A3B8">Lotería del sorteo</text>
  <text x="90" y="{$lotteryValueY}" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="700" fill="#0F172A">{$drawReference}</text>
  <text x="90" y="{$drawDateLabelY}" font-family="Inter, Arial, sans-serif" font-size="16" font-weight="700" fill="#94A3B8">Fecha del sorteo</text>
  <text x="90" y="{$drawDateValueY}" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="700" fill="#0F172A">{$drawDateTime}</text>
  <text x="90" y="{$numbersHeadingY}" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="700" fill="#334155">Números comprados</text>
  {$numberCardsSvg}
  <text x="90" y="{$metaFooterLabelY}" font-family="Inter, Arial, sans-serif" font-size="16" font-weight="700" fill="#94A3B8">Código del boleto</text>
  <text x="90" y="{$metaFooterValueY}" font-family="Inter, Arial, sans-serif" font-size="30" font-weight="800" fill="#0F172A">{$ticketCode}</text>
  <text x="730" y="{$metaFooterLabelY}" font-family="Inter, Arial, sans-serif" font-size="16" font-weight="700" fill="#94A3B8">Fecha de generación</text>
  <text x="730" y="{$metaFooterValueY}" font-family="Inter, Arial, sans-serif" font-size="24" font-weight="700" fill="#0F172A">{$generatedAt}</text>
  <text x="700" y="{$qrHeadingY}" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="700" fill="#334155">QR de verificación</text>
  <rect x="538" y="{$qrCardY}" width="324" height="324" rx="28" fill="#FFFFFF" />
  <image x="565" y="{$qrImageY}" width="270" height="270" href="{$publicUrlEscapedQr}" />
  <text x="90" y="{$urlHeadingY}" font-family="Inter, Arial, sans-serif" font-size="16" font-weight="700" fill="#94A3B8">URL de verificación</text>
  {$ticketUrlSvg}
  <text x="90" y="{$supportLabelY}" font-family="Inter, Arial, sans-serif" font-size="16" font-weight="700" fill="#94A3B8">Número de soporte</text>
  <text x="90" y="{$supportValueY}" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="700" fill="#0F172A">{$supportPhone}</text>
</svg>
SVG;
    }

    protected function buildThumbnailSvg(Ticket $ticket, ?CompanySetting $company, Collection $numbers): string
    {
        $purchase = $ticket->purchase;
        $raffle = $purchase?->raffle;
        $ticketCode = $this->escape($ticket->code);
        $raffleTitle = $this->escape($purchase?->raffle_title_snapshot ?: $raffle?->title ?: 'Rifa');
        $primaryColor = $this->sanitizeColor($company?->primary_color, '#F59E0B');
        $secondaryColor = $this->sanitizeColor($company?->secondary_color, '#111827');
        $numbersText = $this->escape($numbers->map(fn (mixed $n) => $this->padNumber((string) $n, (int) ($raffle?->number_digits ?? 4)))->implode(', '));
        $publicUrlEscapedQr = $this->buildQrDataUri((string) ($ticket->public_url ?: url('/tickets/'.$ticket->verification_token)));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="420" viewBox="0 0 800 420" fill="none">
  <rect width="800" height="420" rx="28" fill="{$secondaryColor}" />
  <rect x="24" y="24" width="752" height="372" rx="22" fill="#FFFFFF" />
  <rect x="24" y="24" width="752" height="14" rx="7" fill="{$primaryColor}" />
  <text x="54" y="96" font-family="Inter, Arial, sans-serif" font-size="24" font-weight="700" fill="#0F172A">{$this->escape($company?->trade_name ?: 'Rifax')}</text>
  <text x="54" y="140" font-family="Inter, Arial, sans-serif" font-size="34" font-weight="700" fill="#0F172A">{$raffleTitle}</text>
  <text x="54" y="192" font-family="Inter, Arial, sans-serif" font-size="18" font-weight="700" fill="#64748B">Código del boleto</text>
  <text x="54" y="228" font-family="Inter, Arial, sans-serif" font-size="30" font-weight="700" fill="#0F172A">{$ticketCode}</text>
  <text x="54" y="282" font-family="Inter, Arial, sans-serif" font-size="18" font-weight="700" fill="#64748B">Números</text>
  <text x="54" y="318" font-family="Inter, Arial, sans-serif" font-size="24" font-weight="600" fill="#0F172A">{$numbersText}</text>
  <rect x="540" y="96" width="188" height="188" rx="18" fill="#F8FAFC" />
  <image x="558" y="114" width="152" height="152" href="{$publicUrlEscapedQr}" />
</svg>
SVG;
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

    /**
     * @return list<string>
     */
    protected function wrapTextForSvg(string $value, int $lineLength): array
    {
        if ($value === '') {
            return ['-'];
        }

        return collect(explode("\n", wordwrap($value, $lineLength, "\n", true)))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
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

    protected function sanitizeColor(?string $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) === 1
            ? (string) $value
            : $fallback;
    }

    protected function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function padNumber(string $number, int $digits): string
    {
        $clean = preg_replace('/\D+/', '', $number) ?: $number;

        return str_pad($clean, max(2, $digits), '0', STR_PAD_LEFT);
    }

    protected function detectNodeBinaryPath(): ?string
    {
        $configured = config('services.browsershot.node_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            base_path('node_modules/.bin/node'),
            '/usr/local/bin/node',
            '/usr/bin/node',
            '/opt/homebrew/bin/node',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = $this->shellWhich('node');

        return $which;
    }

    protected function detectNpmBinaryPath(): ?string
    {
        $configured = config('services.browsershot.npm_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            base_path('node_modules/.bin/npm'),
            '/usr/local/bin/npm',
            '/usr/bin/npm',
            '/opt/homebrew/bin/npm',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return $this->shellWhich('npm');
    }

    protected function detectChromeBinaryPath(): ?string
    {
        $configured = config('services.browsershot.chrome_path');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $puppeteerCacheDirs = [
            '$HOME/.cache/puppeteer',
            '/var/www/.cache/puppeteer',
            '/root/.cache/puppeteer',
            '/home/sail/.cache/puppeteer',
            '/home/deploy/.cache/puppeteer',
            '/Users/'.get_current_user().'/.cache/puppeteer',
        ];

        $searchPatterns = [
            'chrome/linux_arm-*/chrome-linux64/chrome',
            'chrome/linux_amd64-*/chrome-linux64/chrome',
            'chrome/linux-*/chrome-linux64/chrome',
            'chrome-headless-shell/linux_arm-*/chrome-headless-shell-linux64/chrome-headless-shell',
            'chrome-headless-shell/linux_amd64-*/chrome-headless-shell-linux64/chrome-headless-shell',
            'chrome-headless-shell/linux-*/chrome-headless-shell-linux64/chrome-headless-shell',
            'chrome/mac_arm-*/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
            'chrome/mac-x64-*/chrome-mac-x64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
        ];

        foreach ($puppeteerCacheDirs as $dir) {
            $expandedDir = str_replace('$HOME', (string) getenv('HOME'), $dir);
            if (! is_dir($expandedDir)) {
                continue;
            }
            foreach ($searchPatterns as $pattern) {
                $matches = glob($expandedDir.'/'.$pattern);
                if ($matches === false) {
                    continue;
                }
                foreach ($matches as $match) {
                    if (is_file($match) && is_executable($match)) {
                        return $match;
                    }
                }
            }
        }

        $systemCandidates = [
            '/opt/homebrew/bin/chromium',
            '/opt/google/chrome/chrome',
            '/usr/bin/chromium',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
        ];

        foreach ($systemCandidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return $this->shellWhich('chromium')
            ?? $this->shellWhich('chromium-browser')
            ?? $this->shellWhich('google-chrome-stable')
            ?? $this->shellWhich('google-chrome');
    }

    protected function shellWhich(string $binary): ?string
    {
        try {
            $result = shell_exec('command -v '.escapeshellarg($binary).' 2>/dev/null');
            if (! is_string($result)) {
                return null;
            }
            $path = trim($result);
            if ($path === '' || ! is_file($path) || ! is_executable($path)) {
                return null;
            }

            return $path;
        } catch (Throwable) {
            return null;
        }
    }
}
