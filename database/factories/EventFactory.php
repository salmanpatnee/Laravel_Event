<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('+1 week', '+6 months');
        $endTime = (clone $startTime)->modify('+'.fake()->numberBetween(1, 8).' hours');

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'venue' => fake()->company().' '.fake()->randomElement(['Arena', 'Hall', 'Center', 'Theatre']),
            'status' => fake()->randomElement(['draft', 'published']),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }
}
