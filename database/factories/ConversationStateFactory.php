<?php

namespace Database\Factories;

use App\Models\ConversationState;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationState>
 */
class ConversationStateFactory extends Factory
{
    protected $model = ConversationState::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'channel' => 'whatsapp',
            'status' => 'main_menu',
            'substatus' => null,
            'current_raffle_id' => null,
            'requested_quantity' => null,
            'selection_mode' => null,
            'selected_numbers_json' => [],
            'reservation_id' => null,
            'purchase_id' => null,
            'payment_id' => null,
            'last_inbound_message_id' => null,
            'last_outbound_message_id' => null,
            'last_user_message_at' => now(),
            'last_bot_message_at' => now(),
            'context_expires_at' => null,
            'locked_at' => null,
            'locked_by' => null,
            'metadata_json' => [],
        ];
    }

    public function mainMenu(): static
    {
        return $this->state(fn (): array => ['status' => 'main_menu']);
    }

    public function paymentInstructions(): static
    {
        return $this->state(fn (): array => ['status' => 'purchase_payment_instructions']);
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => ['status' => 'purchase_under_review']);
    }
}
