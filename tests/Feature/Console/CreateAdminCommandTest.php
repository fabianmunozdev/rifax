<?php

namespace Tests\Feature\Console;

use App\Enums\PanelRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_admin(): void
    {
        $this->artisan('rifax:create-admin', ['email' => 'Dueno@MegaElite.com', '--name' => 'Dueño'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'una-clave-segura-123')
            ->expectsQuestion('Repite la contraseña', 'una-clave-segura-123')
            ->assertSuccessful();

        $user = User::query()->where('email', 'dueno@megaelite.com')->firstOrFail();
        $this->assertSame(PanelRole::Admin->value, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('una-clave-segura-123', $user->password));
    }

    public function test_it_rejects_short_passwords(): void
    {
        $this->artisan('rifax:create-admin', ['email' => 'a@b.co', '--name' => 'X'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'corta')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
