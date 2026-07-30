<?php

namespace Tests\Feature\Filament\AdminResources;

use App\Filament\Resources\CompanySettings\CompanySettingResource;
use App\Filament\Resources\ContentEntries\ContentEntryResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminResourcesCrudAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_methods_and_content_entries_allow_admin_crud(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(PaymentMethodResource::canCreate());
        $this->assertTrue(ContentEntryResource::canCreate());
    }

    public function test_company_settings_allows_only_one_record_to_be_created(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(CompanySettingResource::canCreate());

        CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'legal_name' => 'Rifax SAS',
            'tax_id' => '900000000-1',
            'support_phone' => '+573001234567',
            'support_email' => 'soporte@rifax.test',
            'website_url' => 'https://rifax.test',
            'logo_path' => null,
            'primary_color' => '#F59E0B',
            'secondary_color' => '#111827',
            'accent_color' => '#DC2626',
            'timezone' => 'America/Bogota',
            'currency_code' => 'COP',
            'default_locale' => 'es',
            'help_message' => 'Ayuda base.',
            'terms_url' => null,
            'privacy_policy_url' => null,
        ]);

        $this->assertFalse(CompanySettingResource::canCreate());
    }

    public function test_company_settings_cannot_be_deleted_from_the_resource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $setting = CompanySetting::query()->create([
            'trade_name' => 'Rifax',
            'legal_name' => 'Rifax SAS',
            'tax_id' => '900000000-1',
            'support_phone' => '+573001234567',
            'support_email' => 'soporte@rifax.test',
            'website_url' => 'https://rifax.test',
            'logo_path' => null,
            'primary_color' => '#F59E0B',
            'secondary_color' => '#111827',
            'accent_color' => '#DC2626',
            'timezone' => 'America/Bogota',
            'currency_code' => 'COP',
            'default_locale' => 'es',
            'help_message' => 'Ayuda base.',
            'terms_url' => null,
            'privacy_policy_url' => null,
        ]);

        $this->assertFalse(CompanySettingResource::canDelete($setting));
    }
}
