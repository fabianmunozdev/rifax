<?php

namespace App\Models;

use App\Models\Concerns\DeletesReplacedUploadedFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use DeletesReplacedUploadedFiles;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trade_name',
        'legal_name',
        'tax_id',
        'whatsapp_bot_phone',
        'support_phone',
        'support_email',
        'website_url',
        'logo_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'timezone',
        'currency_code',
        'default_locale',
        'help_message',
        'terms_url',
        'privacy_policy_url',
    ];

    /**
     * @return list<string>
     */
    public function managedUploadedFileAttributes(): array
    {
        return ['logo_path'];
    }
}
