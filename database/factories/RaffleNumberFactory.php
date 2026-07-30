<?php

namespace Database\Factories;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RaffleNumber>
 */
class RaffleNumberFactory extends Factory
{
    protected $model = RaffleNumber::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'raffle_id' => Raffle::factory(),
            'number' => fake()->unique()->numerify('####'),
            'status' => 'available',
            'reserved_until' => null,
        ];
    }

    public function available(): static
    {
        return $this->state(fn (): array => ['status' => 'available', 'reserved_until' => null]);
    }

    public function reserved(): static
    {
        return $this->state(fn (): array => ['status' => 'reserved', 'reserved_until' => now()->addMinutes(15)]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['status' => 'paid', 'reserved_until' => null]);
    }
}
