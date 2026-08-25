<?php

namespace Database\Factories;

use App\Models\Raffle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Raffle>
 */
class RaffleFactory extends Factory
{
    protected $model = Raffle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'is_featured' => false,
            'number_digits' => 4,
            'min_numbers_per_purchase' => 1,
            'random_selection_by_blocks' => false,
            'lottery_text' => 'Loteria',
            'lottery_name' => fake()->company().' Lottery',
            'lottery_draw_number' => fake()->numerify('####'),
            'draw_date' => now()->addWeek()->toDateString(),
            'draw_time' => '21:30:00',
            'lottery_reference_url' => fake()->url(),
            'price_per_number' => 10000,
            'reservation_timeout_minutes' => 15,
            'cover_image_path' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published']);
    }

    public function withMinNumbers(int $value): static
    {
        return $this->state(fn (): array => ['min_numbers_per_purchase' => $value]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
