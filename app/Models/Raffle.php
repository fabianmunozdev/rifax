<?php

namespace App\Models;

use App\Models\Concerns\DeletesReplacedUploadedFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Raffle extends Model
{
    use DeletesReplacedUploadedFiles;
    use HasFactory;

    public const MIN_SUPPORTED_NUMBER_DIGITS = 2;

    public const MAX_SUPPORTED_NUMBER_DIGITS = 5;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'is_featured',
        'number_digits',
        'min_numbers_per_purchase',
        'random_selection_by_blocks',
        'lottery_name',
        'lottery_draw_number',
        'draw_date',
        'draw_time',
        'lottery_reference_url',
        'result_number',
        'result_published_at',
        'price_per_number',
        'reservation_timeout_minutes',
        'cover_image_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'draw_date' => 'date',
            'result_published_at' => 'datetime',
            'price_per_number' => 'decimal:2',
            'is_featured' => 'boolean',
            'number_digits' => 'integer',
            'min_numbers_per_purchase' => 'integer',
            'random_selection_by_blocks' => 'boolean',
            'reservation_timeout_minutes' => 'integer',
        ];
    }

    public function normalizedNumberDigits(): int
    {
        return max(self::MIN_SUPPORTED_NUMBER_DIGITS, min(self::MAX_SUPPORTED_NUMBER_DIGITS, (int) ($this->number_digits ?? 4)));
    }

    public function numberRangeStart(): int
    {
        return 0;
    }

    public function numberRangeEnd(): int
    {
        return (10 ** $this->normalizedNumberDigits()) - 1;
    }

    public function expectedNumberCatalogCount(): int
    {
        return $this->numberRangeEnd() - $this->numberRangeStart() + 1;
    }

    public function drawAt(): ?Carbon
    {
        if ($this->draw_date === null || blank($this->draw_time)) {
            return null;
        }

        return Carbon::parse(
            $this->draw_date->format('Y-m-d').' '.$this->draw_time,
            config('app.timezone'),
        );
    }

    public function hasDrawStarted(?Carbon $reference = null): bool
    {
        $drawAt = $this->drawAt();

        if ($drawAt === null) {
            return false;
        }

        $reference ??= now($drawAt->getTimezone());

        return $reference->greaterThanOrEqualTo($drawAt);
    }

    public function salesAreOpen(?Carbon $reference = null): bool
    {
        if ($this->status !== 'published' || $this->result_published_at !== null) {
            return false;
        }

        return ! $this->hasDrawStarted($reference);
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(RaffleNumber::class);
    }

    public function winnerNumber(): HasOne
    {
        return $this->hasOne(RaffleNumber::class)->where('status', 'winner');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function conversationStates(): HasMany
    {
        return $this->hasMany(ConversationState::class, 'current_raffle_id');
    }

    public function pickerIntents(): HasMany
    {
        return $this->hasMany(RafflePickerIntent::class);
    }

    /**
     * @return list<string>
     */
    public function managedUploadedFileAttributes(): array
    {
        return ['cover_image_path'];
    }
}
