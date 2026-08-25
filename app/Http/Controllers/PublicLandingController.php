<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\ContentEntry;
use App\Models\PaymentMethod;
use App\Models\Raffle;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class PublicLandingController extends Controller
{
    public function __invoke(): View
    {
        $company = CompanySetting::query()->first();

        $raffles = Raffle::query()
            ->where('status', 'published')
            ->whereNull('result_published_at')
            ->withCount([
                'numbers',
                'numbers as available_numbers_count' => fn (Builder $query): Builder => $query->where('status', 'available'),
                'numbers as reserved_numbers_count' => fn (Builder $query): Builder => $query->where('status', 'reserved'),
                'numbers as paid_numbers_count' => fn (Builder $query): Builder => $query->whereIn('status', ['paid', 'winner']),
            ])
            ->orderByDesc('is_featured')
            ->orderBy('draw_date')
            ->orderBy('draw_time')
            ->get()
            ->filter(fn (Raffle $raffle): bool => $raffle->salesAreOpen())
            ->values();

        $featuredRaffle = $this->resolveFeaturedRaffle($raffles);
        $highlightedRaffleIds = $featuredRaffle ? [$featuredRaffle->id] : [];
        $paymentMethods = PaymentMethod::query()
            ->where('status', 'active')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $recentResults = Raffle::query()
            ->whereNotNull('result_published_at')
            ->with([
                'winnerNumber.purchaseNumber.purchase.customer',
            ])
            ->orderByDesc('result_published_at')
            ->limit(3)
            ->get()
            ->map(function (Raffle $raffle): Raffle {
                $raffle->setAttribute('public_winner_label', $this->resolvePublicWinnerLabel($raffle));

                return $raffle;
            });
        $publicFaqEntries = ContentEntry::query()
            ->whereIn('type', ['faq_fixed', 'faq_parametrized'])
            ->where('status', 'published')
            ->where('is_public', true)
            ->orderBy('priority')
            ->orderBy('title')
            ->get();

        return view('landing', [
            'company' => $company,
            'raffles' => $raffles,
            'featuredRaffle' => $featuredRaffle,
            'otherRaffles' => $raffles->reject(fn (Raffle $raffle): bool => in_array($raffle->id, $highlightedRaffleIds, true))->values(),
            'paymentMethods' => $paymentMethods,
            'recentResults' => $recentResults,
            'publicFaqEntries' => $publicFaqEntries,
            'botPhoneDigits' => $this->normalizePhoneDigits($company?->whatsapp_bot_phone),
            'supportPhoneDigits' => $this->normalizePhoneDigits($company?->support_phone),
        ]);
    }

    /**
     * @param  Collection<int, Raffle>  $raffles
     */
    protected function resolveFeaturedRaffle(Collection $raffles): ?Raffle
    {
        /** @var Raffle|null $featured */
        $featured = $raffles->firstWhere('is_featured', true);

        return $featured ?? $raffles->first();
    }

    protected function normalizePhoneDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: null;

        return blank($digits) ? null : $digits;
    }

    protected function resolvePublicWinnerLabel(Raffle $raffle): string
    {
        $customer = $raffle->winnerNumber?->purchaseNumber?->purchase?->customer;
        $phoneSuffix = $this->maskedPhoneSuffix($customer?->phone);

        if (filled($customer?->name)) {
            $nameParts = preg_split('/\s+/', trim((string) $customer->name)) ?: [];
            $firstName = $nameParts[0] ?? 'Cliente';
            $lastInitial = isset($nameParts[1]) && $nameParts[1] !== ''
                ? ' '.strtoupper(substr($nameParts[1], 0, 1)).'.'
                : '';
            $phoneLabel = $phoneSuffix ? ' · '.$phoneSuffix : '';

            return trim($firstName.$lastInitial.$phoneLabel);
        }

        if ($phoneSuffix !== null) {
            return 'Comprador verificado · '.$phoneSuffix;
        }

        return $raffle->winnerNumber !== null
            ? 'Ganador verificado'
            : 'Resultado oficial publicado';
    }

    protected function maskedPhoneSuffix(?string $phone): ?string
    {
        $digits = $this->normalizePhoneDigits($phone);

        if ($digits === null) {
            return null;
        }

        return '****'.substr($digits, -4);
    }
}
