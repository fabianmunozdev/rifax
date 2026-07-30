<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'raffle_id',
        'reservation_id',
        'status',
        'quantity',
        'unit_price',
        'total_amount',
        'currency',
        'raffle_title_snapshot',
        'payment_instructions_snapshot',
        'reserved_until',
        'proof_submitted_at',
        'paid_at',
        'expired_at',
        'cancelled_at',
        'metadata_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payment_instructions_snapshot' => 'array',
            'reserved_until' => 'datetime',
            'proof_submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(PurchaseNumber::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function conversationStates(): HasMany
    {
        return $this->hasMany(ConversationState::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
