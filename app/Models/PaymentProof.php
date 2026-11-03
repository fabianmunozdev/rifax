<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentProof extends Model
{
    use HasFactory;

    /**
     * Los comprobantes tienen datos bancarios del cliente: van al disco privado
     * y solo se sirven por la ruta autenticada del panel.
     */
    public const STORAGE_DISK = 'local';

    public static function newStoragePath(int|string $whatsappMessageId): string
    {
        return 'whatsapp-proofs/'.$whatsappMessageId.'-'.Str::random(32).'.jpg';
    }

    public function adminUrl(): ?string
    {
        if (blank($this->storage_path) || $this->getKey() === null) {
            return null;
        }

        return route('admin.payment-proofs.show', $this);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'whatsapp_message_id',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_at',
        'metadata_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class);
    }
}
