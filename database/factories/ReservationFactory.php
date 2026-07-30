<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Raffle;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'raffle_id' => Raffle::factory(),
            'status' => 'active',
            'quantity' => 2,
            'selection_mode' => 'manual',
            'unit_price' => 10000,
            'total_amount' => 20000,
            'currency' => 'COP',
            'expires_at' => now()->addMinutes(15),
            'expired_at' => null,
            'cancelled_at' => null,
            'numbers_snapshot_json' => [],
            'metadata_json' => [],
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => 'expired',
            'expires_at' => now()->subMinute(),
            'expired_at' => now(),
        ]);
    }
}
