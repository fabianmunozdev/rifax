<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WhatsappMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'direction',
        'message_type',
        'provider_message_id',
        'body_text',
        'payload_json',
        'status',
        'provider_status',
        'provider_created_at',
        'provider_status_at',
        'sent_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'provider_created_at' => 'datetime',
            'provider_status_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Agente que escribió el mensaje (null = bot o sistema).
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function paymentProof(): HasOne
    {
        return $this->hasOne(PaymentProof::class);
    }
}
