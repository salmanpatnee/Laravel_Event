<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\OrderStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'status' => OrderStatusEnum::Pending,
            'total_amount' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
