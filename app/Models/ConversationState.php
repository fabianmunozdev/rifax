<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ConversationState extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'channel',
        'status',
        'substatus',
        'current_raffle_id',
        'requested_quantity',
        'selection_mode',
        'selected_numbers_json',
        'reservation_id',
        'purchase_id',
        'payment_id',
        'last_inbound_message_id',
        'last_outbound_message_id',
        'last_user_message_at',
        'last_bot_message_at',
        'context_expires_at',
        'locked_at',
        'locked_by',
        'metadata_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'selected_numbers_json' => 'array',
            'metadata_json' => 'array',
            'last_user_message_at' => 'datetime',
            'last_bot_message_at' => 'datetime',
            'context_expires_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currentRaffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class, 'current_raffle_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function lastInboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'last_inbound_message_id');
    }

    public function lastOutboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'last_outbound_message_id');
    }

    public function scopeWhatsapp(Builder $query): Builder
    {
        return $query->where('channel', 'whatsapp');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['main_menu', 'purchase_paid']);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('context_expires_at')
            ->where('context_expires_at', '<', now());
    }

    public function scopeWithActivePurchase(Builder $query): Builder
    {
        return $query->whereNotNull('purchase_id');
    }

    public function scopeWithPendingReviewPayment(Builder $query): Builder
    {
        return $query->whereHas('payment', function (Builder $query): void {
            $query->where('status', 'pending_review');
        });
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->expired()
                ->orWhere(function (Builder $query): void {
                    $query
                        ->whereNotNull('purchase_id')
                        ->whereHas('purchase', function (Builder $query): void {
                            $query->whereIn('status', ['payment_submitted', 'rejected']);
                        });
                })
                ->orWhere(function (Builder $query): void {
                    $query->whereRaw("coalesce((metadata_json->>'follow_up_required')::boolean, false) = true");
                });
        });
    }
}
