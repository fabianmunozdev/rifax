<?php

namespace Tests\Feature\Filament\AdminResources;

use App\Filament\Resources\CompanySettings\Pages\CreateCompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingFormValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_setting_requires_bot_phone_support_phone_help_message_and_a_valid_currency_code(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCompanySetting::class)
            ->set('data', [
                'trade_name' => 'Rifax',
                'legal_name' => 'Rifax SAS',
                'tax_id' => '900000000-1',
                'whatsapp_bot_phone' => null,
                'support_phone' => null,
                'support_email' => 'soporte@rifax.test',
                'website_url' => 'https://rifax.test',
                'logo_path' => null,
                'terms_url' => null,
                'privacy_policy_url' => null,
                'currency_code' => '1$2',
                'default_locale' => 'es',
                'timezone' => 'America/Bogota',
                'primary_color' => '#F59E0B',
                'secondary_color' => '#111827',
                'accent_color' => '#DC2626',
                'help_message' => null,
            ])
            ->call('create')
            ->assertHasErrors([
                'data.whatsapp_bot_phone',
                'data.support_phone',
                'data.currency_code',
                'data.help_message',
            ]);
    }
}
