<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'status',
        'reference',
        'expected_amount',
        'received_amount',
        'proof_received_at',
        'review_due_at',
        'proof_channel',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'metadata_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'proof_received_at' => 'datetime',
            'review_due_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function conversationStates(): HasMany
    {
        return $this->hasMany(ConversationState::class);
    }

    public function isReviewOverdue(): bool
    {
        return $this->status === 'pending_review'
            && $this->review_due_at !== null
            && $this->review_due_at->isPast();
    }
}
