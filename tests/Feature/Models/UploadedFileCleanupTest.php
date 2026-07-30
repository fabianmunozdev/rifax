<?php

namespace Tests\Feature\Models;

use App\Models\CompanySetting;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadedFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_previous_company_logo_when_replaced(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('company-settings/logos/old-logo.png', 'old');
        Storage::disk('public')->put('company-settings/logos/new-logo.png', 'new');

        $setting = CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'legal_name' => 'Rifax SAS',
            'tax_id' => '900000000-1',
            'support_phone' => '+573001112233',
            'support_email' => 'soporte@rifax.test',
            'website_url' => 'https://rifax.test',
            'logo_path' => 'company-settings/logos/old-logo.png',
            'primary_color' => '#F59E0B',
            'secondary_color' => '#111827',
            'accent_color' => '#DC2626',
            'timezone' => 'America/Bogota',
            'currency_code' => 'COP',
            'default_locale' => 'es',
            'help_message' => 'Ayuda base.',
        ]);

        $setting->update([
            'logo_path' => 'company-settings/logos/new-logo.png',
        ]);

        $this->assertFalse(Storage::disk('public')->exists('company-settings/logos/old-logo.png'));
        $this->assertTrue(Storage::disk('public')->exists('company-settings/logos/new-logo.png'));
    }

    public function test_it_deletes_the_previous_raffle_cover_when_replaced(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('raffles/covers/old-cover.png', 'old');
        Storage::disk('public')->put('raffles/covers/new-cover.png', 'new');

        $raffle = Raffle::factory()->create([
            'cover_image_path' => 'raffles/covers/old-cover.png',
        ]);

        $raffle->update([
            'cover_image_path' => 'raffles/covers/new-cover.png',
        ]);

        $this->assertFalse(Storage::disk('public')->exists('raffles/covers/old-cover.png'));
        $this->assertTrue(Storage::disk('public')->exists('raffles/covers/new-cover.png'));
    }

    public function test_it_deletes_uploaded_files_when_the_record_is_deleted(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('company-settings/logos/logo.png', 'logo');
        Storage::disk('public')->put('raffles/covers/cover.png', 'cover');

        $setting = CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'legal_name' => 'Rifax SAS',
            'tax_id' => '900000000-1',
            'support_phone' => '+573001112233',
            'support_email' => 'soporte@rifax.test',
            'website_url' => 'https://rifax.test',
            'logo_path' => 'company-settings/logos/logo.png',
            'primary_color' => '#F59E0B',
            'secondary_color' => '#111827',
            'accent_color' => '#DC2626',
            'timezone' => 'America/Bogota',
            'currency_code' => 'COP',
            'default_locale' => 'es',
            'help_message' => 'Ayuda base.',
        ]);

        $raffle = Raffle::factory()->create([
            'cover_image_path' => 'raffles/covers/cover.png',
        ]);

        $setting->delete();
        $raffle->delete();

        $this->assertFalse(Storage::disk('public')->exists('company-settings/logos/logo.png'));
        $this->assertFalse(Storage::disk('public')->exists('raffles/covers/cover.png'));
    }
}
