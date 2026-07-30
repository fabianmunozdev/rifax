<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ContentEntry extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'key',
        'title',
        'category',
        'locale',
        'channel',
        'status',
        'trigger_intent',
        'body_text',
        'variables_json',
        'fallback_text',
        'priority',
        'is_ai_eligible',
        'is_public',
        'notes',
        'created_by',
        'updated_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables_json' => 'array',
            'priority' => 'integer',
            'is_ai_eligible' => 'boolean',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
