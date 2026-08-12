<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\RoleEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $organizer = User::factory()->create([
            'name' => 'Sarah Organizer',
            'email' => 'organizer@example.com',
            'role' => RoleEnum::Organizer,
        ]);

        Event::factory()
            ->count(3)
            ->for($organizer, 'organizer')
            ->create()
            ->each(function (Event $event) {
                TicketType::factory()->for($event)->general()->create();
                TicketType::factory()->for($event)->vip()->create();
                TicketType::factory()->for($event)->earlyBird()->create();
            });
    }
}
