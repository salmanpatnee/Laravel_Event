<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Backoff([5, 15])]
class LogOrderConfirmationStub implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        $event->order->user->notify(new OrderConfirmation($event->order));
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderPlaced $event, Throwable $exception): void
    {
        Log::error('order.confirmation_stub_failed', [
            'order_id' => $event->order->id,
            'user_id' => $event->order->user_id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
