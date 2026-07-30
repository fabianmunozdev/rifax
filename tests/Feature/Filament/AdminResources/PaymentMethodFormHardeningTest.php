<?php

namespace Tests\Feature\Filament\AdminResources;

use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodFormHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_payment_method_requires_instructions(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreatePaymentMethod::class)
            ->set('data', [
                'name' => 'Transferencia',
                'slug' => 'transferencia',
                'status' => 'active',
                'sort_order' => 10,
                'is_visible' => true,
                'account_holder' => 'Rifax SAS',
                'account_reference' => '123456789',
                'instructions' => null,
                'details_json' => [],
            ])
            ->call('create')
            ->assertHasErrors(['data.instructions']);
    }

    public function test_inactive_payment_method_is_saved_as_hidden_even_if_the_toggle_was_enabled(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreatePaymentMethod::class)
            ->set('data', [
                'name' => 'Transferencia',
                'slug' => 'transferencia',
                'status' => 'inactive',
                'sort_order' => 10,
                'is_visible' => true,
                'account_holder' => 'Rifax SAS',
                'account_reference' => '123456789',
                'instructions' => null,
                'details_json' => ['bank' => 'Demo'],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $paymentMethod = PaymentMethod::query()->where('slug', 'transferencia')->firstOrFail();

        $this->assertFalse($paymentMethod->is_visible);
    }
}
