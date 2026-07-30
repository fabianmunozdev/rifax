<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'name',
        'document_number',
        'wa_id',
        'last_interaction_at',
        'accepted_privacy_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_interaction_at' => 'datetime',
            'accepted_privacy_at' => 'datetime',
        ];
    }

    public function conversationStates(): HasMany
    {
        return $this->hasMany(ConversationState::class);
    }

    public function currentConversationState(): HasOne
    {
        return $this->hasOne(ConversationState::class)
            ->where('channel', 'whatsapp')
            ->latestOfMany();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function latestWhatsappMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class)->latestOfMany();
    }
}
