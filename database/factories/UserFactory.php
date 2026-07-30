<?php

namespace Database\Factories;

use App\Enums\PanelRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => null,
            'is_active' => true,
            'preferred_locale' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => PanelRole::Admin->value,
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn (): array => [
            'role' => PanelRole::Operator->value,
        ]);
    }

    public function finance(): static
    {
        return $this->state(fn (): array => [
            'role' => PanelRole::Finance->value,
        ]);
    }

    public function support(): static
    {
        return $this->state(fn (): array => [
            'role' => PanelRole::Support->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
