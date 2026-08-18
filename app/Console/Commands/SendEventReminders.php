<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\OrderStatusEnum;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:send-event-reminders')]
#[Description('Log a reminder line for each order tied to an event starting in the next 24 hours.')]
class SendEventReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $orders = Order::query()
            ->with(['event', 'user'])
            ->whereIn('status', [OrderStatusEnum::Confirmed, OrderStatusEnum::Pending])
            ->whereHas('event', function ($query) {
                $query->whereBetween('start_time', [now(), now()->addDay()]);
            })
            ->get();

        $this->info("Event reminder query finished: {$orders->count()} orders found");

        foreach ($orders as $order) {
            Log::info('Event reminder due', [
                'order_id' => $order->id,
                'event' => $order->event->name,
                'starts_at' => $order->event->start_time->toDateTimeString(),
                'user_email' => $order->user->email,
            ]);
        }
    }
}
