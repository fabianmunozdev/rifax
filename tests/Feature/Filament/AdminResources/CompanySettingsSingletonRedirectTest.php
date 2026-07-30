<?php

namespace Tests\Feature\Filament\AdminResources;

use App\Filament\Resources\CompanySettings\CompanySettingResource;
use App\Filament\Resources\CompanySettings\Pages\ListCompanySettings;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsSingletonRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_page_redirects_to_create_when_company_settings_do_not_exist(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListCompanySettings::class)
            ->assertRedirect(CompanySettingResource::getUrl('create'));
    }

    public function test_list_page_redirects_to_edit_when_company_settings_already_exist(): void
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

        Livewire::test(ListCompanySettings::class)
            ->assertRedirect(CompanySettingResource::getUrl('edit', ['record' => $setting]));
    }
}
