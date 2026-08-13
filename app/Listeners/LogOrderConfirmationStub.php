<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogOrderConfirmationStub
{
    /**
     * Create the event listener.
     */
    public function __construct(Public OrderPlaced $event)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
       Log::info('order.confirmation_stub', [
            'order_id' => $event->order->id,
            'user_id' => $event->order->user_id,
            'message' => "Confirmation email would be sent to user {$event->order->user_id} for order {$event->order->id}.",
        ]);
    }
}
