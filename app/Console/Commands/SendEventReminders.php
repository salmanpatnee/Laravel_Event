<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\EventReminder;
use App\OrderStatusEnum;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-event-reminders')]
#[Description('Notify each order not yet reminded about an event starting in the next 24 hours.')]
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
            ->whereNull('reminder_sent_at')
            ->whereHas('event', function ($query) {
                $query->whereBetween('start_time', [now(), now()->addDay()]);
            })
            ->get();

        foreach ($orders as $order) {
            $order->user->notify(new EventReminder($order));

            $order->update(['reminder_sent_at' => now()]);
        }

        $this->info("Event reminder query finished: {$orders->count()} orders found");
    }
}
