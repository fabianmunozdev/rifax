<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalPage extends Model
{
    public const TERMS = 'terminos-y-condiciones';

    public const PRIVACY = 'politica-de-privacidad';

    /**
     * Datos de la empresa que se pueden escribir en el texto entre llaves, por ejemplo {empresa}.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        '{empresa}' => 'Nombre comercial',
        '{razon_social}' => 'Razón social',
        '{nit}' => 'NIT',
        '{correo}' => 'Correo de soporte',
        '{telefono}' => 'Teléfono de soporte',
        '{whatsapp}' => 'WhatsApp del bot',
        '{sitio}' => 'Dirección del sitio',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'title',
        'summary',
        'body',
        'is_published',
        'published_at',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publicUrl(): string
    {
        return url('/'.$this->slug);
    }

    public static function findPublished(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->where('is_published', true)->first();
    }

    /**
     * Cambia {empresa}, {nit}, etc. por los datos reales. Los valores se escapan porque van dentro de HTML.
     */
    public function renderBody(?CompanySetting $company): string
    {
        $values = [
            '{empresa}' => $company?->trade_name ?: config('app.name'),
            '{razon_social}' => $company?->legal_name ?: ($company?->trade_name ?: config('app.name')),
            '{nit}' => $company?->tax_id ?: '[NIT pendiente]',
            '{correo}' => $company?->support_email ?: '[correo pendiente]',
            '{telefono}' => $company?->support_phone ?: '[teléfono pendiente]',
            '{whatsapp}' => $company?->whatsapp_bot_phone ?: ($company?->support_phone ?: '[WhatsApp pendiente]'),
            '{sitio}' => $company?->website_url ?: url('/'),
        ];

        // El renderizador de Filament limpia el HTML (quita scripts y atributos peligrosos).
        $html = RichContentRenderer::make((string) $this->body)->toHtml();

        return strtr($html, array_map(fn (string $value): string => e($value), $values));
    }
}
