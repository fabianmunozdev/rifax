<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+57'.fake()->unique()->numerify('3#########');

        return [
            'phone' => $phone,
            'name' => fake()->name(),
            'document_number' => fake()->numerify('##########'),
            'wa_id' => preg_replace('/\D+/', '', $phone),
            'last_interaction_at' => now(),
            'accepted_privacy_at' => now(),
        ];
    }
}
