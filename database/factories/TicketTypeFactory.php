<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'General Admission',
            'price' => 49.00,
            'quantity' => 200,
        ];
    }

    /**
     * General admission: standard entry-level ticket.
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'General Admission',
            'price' => 49.00,
            'quantity' => 200,
        ]);
    }

    /**
     * VIP: premium ticket with limited quantity.
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'VIP',
            'price' => 149.00,
            'quantity' => 50,
        ]);
    }

    /**
     * Early bird: discounted ticket, most limited quantity.
     */
    public function earlyBird(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Early Bird',
            'price' => 29.00,
            'quantity' => 30,
        ]);
    }
}
