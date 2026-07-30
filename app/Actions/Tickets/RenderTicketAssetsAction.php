<?php

namespace App\Actions\Tickets;

use App\Models\CompanySetting;
use App\Models\Ticket;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RenderTicketAssetsAction
{
    public function execute(Ticket $ticket): Ticket
    {
        $ticket->loadMissing(['purchase.customer', 'purchase.raffle', 'purchase.numbers']);

        $company = CompanySetting::query()->first();
        $purchase = $ticket->purchase;
        $numbers = $purchase->numbers
            ->pluck('number')
            ->filter()
            ->values();

        $version = max(1, (int) $ticket->version);
        $directory = 'tickets/'.$ticket->verification_token;
        $imagePath = $directory.'/ticket-v'.$version.'.svg';
        $thumbnailPath = $directory.'/ticket-thumb-v'.$version.'.svg';

        Storage::disk('public')->put($imagePath, $this->buildTicketSvg($ticket, $company, $numbers));
        Storage::disk('public')->put($thumbnailPath, $this->buildThumbnailSvg($ticket, $company, $numbers));

        $ticket->forceFill([
            'image_path' => $imagePath,
            'thumbnail_path' => $thumbnailPath,
        ])->save();

        return $ticket->fresh() ?? $ticket;
    }

    protected function buildTicketSvg(Ticket $ticket, ?CompanySetting $company, Collection $numbers): string
    {
        $purchase = $ticket->purchase;
        $raffle = $purchase->raffle;
        $primaryColor = $this->sanitizeColor($company?->primary_color, '#F59E0B');
        $secondaryColor = $this->sanitizeColor($company?->secondary_color, '#111827');
        $accentColor = $this->sanitizeColor($company?->accent_color, '#DC2626');
        $brandName = $this->escape($company?->trade_name ?: 'Rifax');
        $raffleTitle = $this->escape($purchase->raffle_title_snapshot ?: $raffle?->title ?: 'Raffle');
        $supportPhone = $this->escape($company?->support_phone ?: '');
        $drawDate = $raffle?->draw_date?->format('Y-m-d') ?: 'Pending';
        $drawTime = $raffle?->draw_time ?: 'Pending';
        $lotteryName = $this->escape($raffle?->lottery_name ?: 'External lottery');
        $lotteryDrawNumber = $this->escape($raffle?->lottery_draw_number ?: '-');
        $ticketCode = $this->escape($ticket->code);
        $ticketUrl = $this->escape($ticket->public_url ?: '');
        $generatedAt = $ticket->generated_at?->setTimezone(config('app.timezone'))->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i');
        $numberLines = $numbers->chunk(6);
        $numbersSvg = '';

        foreach ($numberLines as $index => $line) {
            $numbersSvg .= '<text x="60" y="'.(270 + ($index * 42)).'" font-family="Inter, Arial, sans-serif" font-size="28" font-weight="700" fill="#0F172A">'
                .$this->escape($line->implode('   '))
                .'</text>';
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="820" viewBox="0 0 1400 820" fill="none">
  <defs>
    <linearGradient id="ticket-bg" x1="0" y1="0" x2="1400" y2="820" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="{$secondaryColor}" />
      <stop offset="100%" stop-color="#0F172A" />
    </linearGradient>
    <linearGradient id="ticket-accent" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$primaryColor}" />
      <stop offset="100%" stop-color="{$accentColor}" />
    </linearGradient>
  </defs>
  <rect width="1400" height="820" rx="40" fill="url(#ticket-bg)" />
  <rect x="36" y="36" width="1328" height="748" rx="28" fill="#FFFFFF" fill-opacity="0.97" />
  <rect x="60" y="60" width="820" height="700" rx="24" fill="#F8FAFC" />
  <rect x="910" y="60" width="430" height="700" rx="24" fill="url(#ticket-bg)" />
  <rect x="60" y="60" width="820" height="14" rx="7" fill="url(#ticket-accent)" />
  <text x="60" y="128" font-family="Inter, Arial, sans-serif" font-size="28" font-weight="700" fill="{$secondaryColor}">{$brandName}</text>
  <text x="60" y="176" font-family="Inter, Arial, sans-serif" font-size="52" font-weight="700" fill="#0F172A">Official Ticket</text>
  <text x="60" y="220" font-family="Inter, Arial, sans-serif" font-size="26" fill="#475569">{$raffleTitle}</text>
  <text x="60" y="322" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#64748B">Assigned numbers</text>
  {$numbersSvg}
  <rect x="60" y="470" width="780" height="142" rx="20" fill="#FFFFFF" />
  <text x="92" y="524" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#64748B">Ticket code</text>
  <text x="92" y="570" font-family="Inter, Arial, sans-serif" font-size="34" font-weight="700" fill="#0F172A">{$ticketCode}</text>
  <text x="420" y="524" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#64748B">Generated at</text>
  <text x="420" y="570" font-family="Inter, Arial, sans-serif" font-size="28" font-weight="600" fill="#0F172A">{$this->escape($generatedAt)}</text>
  <rect x="60" y="636" width="780" height="124" rx="20" fill="{$secondaryColor}" />
  <text x="92" y="688" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#CBD5E1">Draw reference</text>
  <text x="92" y="728" font-family="Inter, Arial, sans-serif" font-size="26" font-weight="600" fill="#FFFFFF">{$lotteryName} #{$lotteryDrawNumber}</text>
  <text x="472" y="688" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#CBD5E1">Date and time</text>
  <text x="472" y="728" font-family="Inter, Arial, sans-serif" font-size="26" font-weight="600" fill="#FFFFFF">{$this->escape($drawDate)} {$this->escape($drawTime)}</text>
  <text x="942" y="122" font-family="Inter, Arial, sans-serif" font-size="24" font-weight="700" fill="#E2E8F0">Verify online</text>
  <rect x="962" y="160" width="326" height="326" rx="22" fill="#FFFFFF" />
  <image x="986" y="184" width="278" height="278" href="{$this->buildQrDataUri($ticket->public_url ?: '')}" />
  <text x="942" y="548" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#E2E8F0">Verification URL</text>
  <text x="942" y="586" font-family="Inter, Arial, sans-serif" font-size="17" fill="#FFFFFF">{$ticketUrl}</text>
  <text x="942" y="650" font-family="Inter, Arial, sans-serif" font-size="20" font-weight="700" fill="#E2E8F0">Support</text>
  <text x="942" y="688" font-family="Inter, Arial, sans-serif" font-size="24" font-weight="600" fill="#FFFFFF">{$supportPhone}</text>
  <text x="942" y="736" font-family="Inter, Arial, sans-serif" font-size="16" fill="#CBD5E1">Public verification omits buyer personal data.</text>
</svg>
SVG;
    }

    protected function buildThumbnailSvg(Ticket $ticket, ?CompanySetting $company, Collection $numbers): string
    {
        $purchase = $ticket->purchase;
        $raffle = $purchase->raffle;
        $primaryColor = $this->sanitizeColor($company?->primary_color, '#F59E0B');
        $secondaryColor = $this->sanitizeColor($company?->secondary_color, '#111827');
        $ticketCode = $this->escape($ticket->code);
        $raffleTitle = $this->escape($purchase->raffle_title_snapshot ?: $raffle?->title ?: 'Raffle');
        $numbersText = $this->escape($numbers->implode(', '));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="420" viewBox="0 0 800 420" fill="none">
  <rect width="800" height="420" rx="28" fill="{$secondaryColor}" />
  <rect x="24" y="24" width="752" height="372" rx="22" fill="#FFFFFF" />
  <rect x="24" y="24" width="752" height="14" rx="7" fill="{$primaryColor}" />
  <text x="54" y="96" font-family="Inter, Arial, sans-serif" font-size="24" font-weight="700" fill="#0F172A">{$this->escape($company?->trade_name ?: 'Rifax')}</text>
  <text x="54" y="140" font-family="Inter, Arial, sans-serif" font-size="34" font-weight="700" fill="#0F172A">{$raffleTitle}</text>
  <text x="54" y="192" font-family="Inter, Arial, sans-serif" font-size="18" font-weight="700" fill="#64748B">Ticket code</text>
  <text x="54" y="228" font-family="Inter, Arial, sans-serif" font-size="30" font-weight="700" fill="#0F172A">{$ticketCode}</text>
  <text x="54" y="282" font-family="Inter, Arial, sans-serif" font-size="18" font-weight="700" fill="#64748B">Numbers</text>
  <text x="54" y="318" font-family="Inter, Arial, sans-serif" font-size="24" font-weight="600" fill="#0F172A">{$numbersText}</text>
  <rect x="540" y="96" width="188" height="188" rx="18" fill="#F8FAFC" />
  <image x="558" y="114" width="152" height="152" href="{$this->buildQrDataUri($ticket->public_url ?: '')}" />
</svg>
SVG;
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
}
