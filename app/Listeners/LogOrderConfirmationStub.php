<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
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
        sleep(3); // Simulate a delay for sending the email

        Log::info('order.confirmation_stub', [
            'order_id' => $event->order->id,
            'user_id' => $event->order->user_id,
            'message' => "Confirmation email would be sent to user {$event->order->user_id} for order {$event->order->id}.",
        ]);
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
