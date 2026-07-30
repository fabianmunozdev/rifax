<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    protected $model = WhatsappMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'direction' => 'inbound',
            'message_type' => 'text',
            'provider_message_id' => fake()->unique()->uuid(),
            'body_text' => fake()->sentence(),
            'payload_json' => ['text' => ['body' => fake()->sentence()]],
            'status' => null,
            'provider_created_at' => now(),
        ];
    }

    public function inboundImage(): static
    {
        return $this->state(fn (): array => [
            'direction' => 'inbound',
            'message_type' => 'image',
            'body_text' => null,
            'payload_json' => ['image' => ['id' => fake()->uuid()]],
        ]);
    }
}
