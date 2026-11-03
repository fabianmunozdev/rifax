<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class RaffleEarlyPrize extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_WON = 'won';

    public const STATUS_VOID = 'void';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'raffle_id',
        'title',
        'description',
        'image_path',
        'lottery_name',
        'lottery_draw_number',
        'draw_date',
        'draw_time',
        'lottery_reference_url',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'draw_date' => 'date',
            'result_published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class);
    }

    public function winnerNumber(): BelongsTo
    {
        return $this->belongsTo(RaffleNumber::class, 'winner_raffle_number_id');
    }

    public function winnerPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'winner_purchase_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_published_by');
    }

    public function status(): string
    {
        if ($this->result_published_at === null) {
            return self::STATUS_PENDING;
        }

        return $this->winner_purchase_id !== null ? self::STATUS_WON : self::STATUS_VOID;
    }

    public function drawsAt(): Carbon
    {
        $date = $this->draw_date instanceof Carbon ? $this->draw_date->copy() : Carbon::parse((string) $this->draw_date);

        return filled($this->draw_time)
            ? $date->setTimeFromTimeString((string) $this->draw_time)
            : $date->endOfDay();
    }

    /**
     * Ganador para la página pública, sin exponer datos: "Ana P. · ****8877".
     */
    public function publicWinnerLabel(): ?string
    {
        $customer = $this->winnerPurchase?->customer;

        if ($customer === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $customer->phone) ?: '';
        $phone = $digits !== '' ? '****'.substr($digits, -4) : null;
        $parts = preg_split('/\s+/', trim((string) $customer->name)) ?: [];

        if (($parts[0] ?? '') !== '') {
            $name = $parts[0].(isset($parts[1]) && $parts[1] !== '' ? ' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : '');

            return $phone ? "{$name} · {$phone}" : $name;
        }

        return $phone ? "Comprador verificado · {$phone}" : 'Comprador verificado';
    }

    public function imageUrl(): ?string
    {
        return filled($this->image_path) ? Storage::disk('public')->url($this->image_path) : null;
    }
}
