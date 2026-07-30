<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Raffle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'raffle_id' => Raffle::factory(),
            'reservation_id' => null,
            'status' => 'reserved',
            'quantity' => 2,
            'unit_price' => 10000,
            'total_amount' => 20000,
            'currency' => 'COP',
            'raffle_title_snapshot' => fake()->sentence(3),
            'payment_instructions_snapshot' => [],
            'reserved_until' => now()->addMinutes(15),
            'proof_submitted_at' => null,
            'paid_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
            'metadata_json' => [],
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (): array => ['status' => 'reserved']);
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'status' => 'payment_submitted',
            'proof_submitted_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'proof_submitted_at' => now(),
        ]);
    }
}
