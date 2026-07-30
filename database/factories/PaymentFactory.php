<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'status' => 'pending_review',
            'reference' => null,
            'expected_amount' => 20000,
            'received_amount' => null,
            'proof_received_at' => now(),
            'review_due_at' => now()->addHours((int) config('rifax.payments.review_timeout_hours', 48)),
            'proof_channel' => 'whatsapp',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'metadata_json' => [],
        ];
    }

    public function pendingReview(): static
    {
        return $this->state(fn (): array => ['status' => 'pending_review', 'reviewed_at' => null]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'reviewed_at' => now(),
            'rejection_reason' => 'Rejected by factory state.',
        ]);
    }
}
